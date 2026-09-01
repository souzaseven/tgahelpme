// Relógio, saudação, data e barra de progresso do dia.

import { pad2, cap, greetingKey, dayFraction } from './format.js';
import { t } from './i18n.js';
import { getSettings, onSettingsChange } from './settings.js';

export function initClock(els) {
    const fmtDate = new Intl.DateTimeFormat('pt-BR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    let fmtHM = buildTimeFormat(getSettings());
    let timer = null;
    let lastTitleMinute = -1;

    function buildTimeFormat(s) {
        return new Intl.DateTimeFormat('pt-BR', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: s.hour12,
        });
    }

    function render() {
        const now = new Date();
        const s = getSettings();

        els.timeHM.textContent = fmtHM.format(now);
        els.timeSec.textContent = s.showSeconds ? pad2(now.getSeconds()) : '';
        els.timeSec.hidden = !s.showSeconds;

        els.greeting.textContent = t.greeting[greetingKey(now.getHours())];
        els.date.textContent = cap(fmtDate.format(now));

        const frac = dayFraction(now);
        const pct = Math.round(frac * 100);
        els.progressFill.style.width = `${(frac * 100).toFixed(3)}%`;
        els.progressLabel.textContent = t.dayProgress(pct);
        els.progressTrack.setAttribute('aria-valuenow', String(pct));
        els.progressTrack.setAttribute('aria-valuetext', t.dayProgress(pct));

        const minuteOfDay = now.getHours() * 60 + now.getMinutes();
        if (minuteOfDay !== lastTitleMinute) {
            lastTitleMinute = minuteOfDay;
            document.title = `${els.timeHM.textContent} · Hora Certa`;
        }
    }

    function tick() {
        render();
        const period = getSettings().showSeconds ? 1000 : 60000;
        timer = window.setTimeout(tick, period - (Date.now() % period));
    }

    function start() {
        if (timer === null) tick();
    }

    function stop() {
        window.clearTimeout(timer);
        timer = null;
    }

    function restart() {
        stop();
        start();
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stop();
        else restart();
    });

    onSettingsChange((s) => {
        fmtHM = buildTimeFormat(s);
        restart();
    });

    start();
    return { start, stop };
}
