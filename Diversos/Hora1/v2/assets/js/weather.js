// Localização (GPS com fallback para IP) e clima, com cache, timeout e
// atualização periódica.

import { cap, wallClock, gmtLabel, weatherGlyph } from './format.js';
import { t } from './i18n.js';

const CACHE_KEY = 'hora:weather';
const CACHE_TTL_MS = 10 * 60 * 1000; // dados considerados "frescos" por 10 min
const REFRESH_MS = 15 * 60 * 1000; // recarrega a cada 15 min com a aba visível
const FETCH_TIMEOUT_MS = 8000;

// Se você publicar o proxy (ver pasta worker/), preencha aqui a URL base dele.
// Com o proxy ativo, as chaves abaixo deixam de ser usadas no cliente.
const PROXY_BASE = '';

const KEYS = {
    openWeather: '6e5f80bfbe2dd7591b7a9d65157d7e4b',
    ipGeo: '13a008ccb7594d1cb4a6e986847fc507',
};

function timeoutFetch(url, ms = FETCH_TIMEOUT_MS) {
    const ac = new AbortController();
    const id = setTimeout(() => ac.abort(), ms);
    return fetch(url, { signal: ac.signal }).finally(() => clearTimeout(id));
}

function geoUrl() {
    return PROXY_BASE
        ? `${PROXY_BASE}/geo`
        : `https://api.ipgeolocation.io/ipgeo?apiKey=${KEYS.ipGeo}`;
}

function weatherUrl(lat, lon) {
    const q = `lat=${lat}&lon=${lon}&units=metric&lang=pt_br`;
    return PROXY_BASE
        ? `${PROXY_BASE}/weather?${q}`
        : `https://api.openweathermap.org/data/2.5/weather?${q}&appid=${KEYS.openWeather}`;
}

function readCache() {
    try {
        const c = JSON.parse(localStorage.getItem(CACHE_KEY));
        if (c && Date.now() - c.at < CACHE_TTL_MS) return c;
    } catch {
        /* ignora */
    }
    return null;
}

function writeCache(payload) {
    try {
        localStorage.setItem(CACHE_KEY, JSON.stringify({ at: Date.now(), ...payload }));
    } catch {
        /* ignora */
    }
}

async function resolveCoords() {
    // 1) GPS do dispositivo — mais preciso que IP.
    if ('geolocation' in navigator) {
        try {
            const pos = await new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, reject, {
                    timeout: 6000,
                    maximumAge: 10 * 60 * 1000,
                    enableHighAccuracy: false,
                });
            });
            return { lat: pos.coords.latitude, lon: pos.coords.longitude, place: null };
        } catch {
            /* sem permissão ou indisponível: cai para IP */
        }
    }

    // 2) Geolocalização por IP.
    const res = await timeoutFetch(geoUrl());
    if (!res.ok) throw new Error(`geo HTTP ${res.status}`);
    const g = await res.json();
    return {
        lat: Number(g.latitude),
        lon: Number(g.longitude),
        place: [g.city, g.country_name].filter(Boolean).join(', ') || null,
    };
}

function paintWeather(els, data) {
    const main = data.main || {};
    const cond = (data.weather && data.weather[0]) || {};
    const sys = data.sys || {};
    const shift = typeof data.timezone === 'number' ? data.timezone : 0;

    if (cond.description) {
        els.cond.textContent = `${weatherGlyph(cond.icon)} ${cap(cond.description)}`.trim();
    }
    if (typeof main.feels_like === 'number') {
        els.feels.textContent = `${main.feels_like.toFixed(1)} °C`;
    }
    if (typeof main.temp_min === 'number' && typeof main.temp_max === 'number') {
        els.minmax.textContent = `${Math.round(main.temp_min)}° / ${Math.round(main.temp_max)}°`;
    }
    if (sys.sunrise) els.sunrise.textContent = wallClock(sys.sunrise, shift);
    if (sys.sunset) els.sunset.textContent = wallClock(sys.sunset, shift);
}

function setStatus(els, msg) {
    if (!els.status) return;
    els.status.textContent = msg;
    els.status.hidden = !msg;
}

function safeTimezone() {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    } catch {
        return '';
    }
}

export function initWeather(els) {
    const tz = safeTimezone();
    els.tz.textContent = tz ? `${tz} · ${gmtLabel()}` : gmtLabel();

    const cached = readCache();
    if (cached?.weather) {
        if (cached.place) els.place.textContent = cached.place;
        paintWeather(els, cached.weather);
    }

    async function load() {
        try {
            const { lat, lon, place } = await resolveCoords();
            if (place) els.place.textContent = place;
            else if (els.place.textContent === t.locating) els.place.textContent = t.dash;

            if (!Number.isFinite(lat) || !Number.isFinite(lon)) return;

            const res = await timeoutFetch(weatherUrl(lat, lon));
            if (!res.ok) throw new Error(`clima HTTP ${res.status}`);
            const data = await res.json();

            paintWeather(els, data);
            writeCache({ place: place ?? els.place.textContent, weather: data });
            setStatus(els, '');
        } catch (err) {
            console.error('Clima/localização:', err);
            if (els.place.textContent === t.locating) {
                els.place.textContent = t.locationUnavailable;
            }
            if (!cached?.weather) setStatus(els, t.weatherUnavailable);
        }
    }

    load();
    const iv = window.setInterval(() => {
        if (!document.hidden) load();
    }, REFRESH_MS);

    window.addEventListener('online', () => {
        setStatus(els, '');
        load();
    });
    window.addEventListener('offline', () => setStatus(els, t.offline));

    return () => window.clearInterval(iv);
}
