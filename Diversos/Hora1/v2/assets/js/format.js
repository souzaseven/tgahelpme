// Funções puras de formatação e cálculo — sem DOM, fáceis de testar.

export const SECONDS_PER_DAY = 86400;

/** Limites da saudação, em hora local (0–23). */
export const GREETING_BOUNDS = { dawn: 5, morning: 12, afternoon: 18 };

export const pad2 = (n) => String(n).padStart(2, '0');

export const cap = (s) => (s ? s.charAt(0).toUpperCase() + s.slice(1) : s);

/** Retorna a chave da saudação para uma hora (0–23). */
export function greetingKey(hour) {
    if (hour < GREETING_BOUNDS.dawn) return 'dawn';
    if (hour < GREETING_BOUNDS.morning) return 'morning';
    if (hour < GREETING_BOUNDS.afternoon) return 'afternoon';
    return 'evening';
}

/** Fração do dia já decorrida (0–1) para uma data local. */
export function dayFraction(date) {
    const s = date.getHours() * 3600 + date.getMinutes() * 60 + date.getSeconds();
    return s / SECONDS_PER_DAY;
}

/**
 * Converte um instante UTC (em segundos, ex.: sunrise do OpenWeather) para o
 * relógio de parede "HH:MM" de um deslocamento em segundos.
 */
export function wallClock(unixSeconds, shiftSeconds = 0) {
    const d = new Date((unixSeconds + shiftSeconds) * 1000);
    return `${pad2(d.getUTCHours())}:${pad2(d.getUTCMinutes())}`;
}

/** Rótulo do deslocamento de fuso do navegador, ex.: "GMT-03:00". */
export function gmtLabel(date = new Date()) {
    const offMin = -date.getTimezoneOffset();
    const sign = offMin >= 0 ? '+' : '-';
    const abs = Math.abs(offMin);
    return `GMT${sign}${pad2(Math.floor(abs / 60))}:${pad2(abs % 60)}`;
}

/** Código de ícone do OpenWeather (ex.: "10d") → glifo tipográfico discreto. */
export function weatherGlyph(icon = '') {
    const p = icon.slice(0, 2);
    if (p === '01') return icon.endsWith('n') ? '☾' : '☀';
    if (p === '02' || p === '03' || p === '04') return '☁';
    if (p === '09' || p === '10') return '☂';
    if (p === '11') return '☇';
    if (p === '13') return '❄';
    if (p === '50') return '≈';
    return '·';
}
