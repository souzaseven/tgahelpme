// Preferências do usuário (formato 12/24 h, segundos) com persistência e pub/sub.

const KEY = 'hora:settings';
const defaults = { hour12: false, showSeconds: true };

let state = read();
const listeners = new Set();

function read() {
    try {
        return { ...defaults, ...(JSON.parse(localStorage.getItem(KEY)) || {}) };
    } catch {
        return { ...defaults };
    }
}

function persist() {
    try {
        localStorage.setItem(KEY, JSON.stringify(state));
    } catch {
        /* localStorage indisponível: vale só nesta sessão */
    }
}

export function getSettings() {
    return { ...state };
}

export function setSetting(key, value) {
    state = { ...state, [key]: value };
    persist();
    listeners.forEach((fn) => fn(getSettings()));
}

export function onSettingsChange(fn) {
    listeners.add(fn);
    return () => listeners.delete(fn);
}

/** Liga os controles de ajustes do rodapé ao estado. */
export function initSettingsUI() {
    const hour12 = document.getElementById('opt-hour12');
    const seconds = document.getElementById('opt-seconds');
    const s = getSettings();

    if (hour12) {
        hour12.checked = s.hour12;
        hour12.addEventListener('change', () => setSetting('hour12', hour12.checked));
    }
    if (seconds) {
        seconds.checked = s.showSeconds;
        seconds.addEventListener('change', () => setSetting('showSeconds', seconds.checked));
    }
}
