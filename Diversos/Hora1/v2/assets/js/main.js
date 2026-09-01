// Ponto de entrada: coleta os elementos e liga os módulos.

import { initTheme } from './theme.js';
import { initSettingsUI } from './settings.js';
import { initClock } from './clock.js';
import { initWeather } from './weather.js';
import { initConsent } from './consent.js';

const $ = (id) => document.getElementById(id);

// O relógio precisa de dois <span> internos; cria uma vez.
const timeEl = $('time');
const timeHM = document.createElement('span');
timeHM.className = 'time__hm';
const timeSec = document.createElement('span');
timeSec.className = 'time__sec';
timeEl.replaceChildren(timeHM, timeSec);

const els = {
    greeting: $('greeting'),
    timeHM,
    timeSec,
    date: $('date'),
    place: $('place'),
    status: $('status'),
    progressFill: $('progressFill'),
    progressLabel: $('progressLabel'),
    progressTrack: $('progressTrack'),
    cond: $('d-cond'),
    feels: $('d-feels'),
    minmax: $('d-minmax'),
    sunrise: $('d-sunrise'),
    sunset: $('d-sunset'),
    tz: $('d-tz'),
};

initTheme();
initSettingsUI();
initClock(els);
initWeather(els);
initConsent();

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('./sw.js').catch((err) => {
            console.warn('Service worker não registrado:', err);
        });
    });
}
