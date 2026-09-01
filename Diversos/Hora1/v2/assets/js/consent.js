// Consentimento (LGPD/GDPR): o Google Analytics e o AdSense só são carregados
// depois que o visitante clica em "Aceitar". A escolha fica salva.
//
// Observação: isto reduz impressões de anúncios de quem recusa/ignora. Para uma
// abordagem mais completa, avalie o Google Consent Mode v2.

import { t } from './i18n.js';

const KEY = 'hora:consent'; // 'granted' | 'denied'
const GA_ID = 'G-E7ZNTJSRYR';
const ADSENSE_CLIENT = 'ca-pub-8542251167876044';

function loadGoogle() {
    const ga = document.createElement('script');
    ga.async = true;
    ga.src = `https://www.googletagmanager.com/gtag/js?id=${GA_ID}`;
    document.head.appendChild(ga);

    window.dataLayer = window.dataLayer || [];
    window.gtag = function gtag() {
        window.dataLayer.push(arguments);
    };
    window.gtag('js', new Date());
    window.gtag('config', GA_ID, { anonymize_ip: true });

    const ad = document.createElement('script');
    ad.async = true;
    ad.crossOrigin = 'anonymous';
    ad.src = `https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${ADSENSE_CLIENT}`;
    document.head.appendChild(ad);
}

function showBanner(onChoice) {
    const el = document.createElement('div');
    el.className = 'consent';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-label', 'Aviso de cookies');
    el.innerHTML = `
        <p class="consent__text">
            ${t.consent.text}
            <a class="consent__link" href="privacidade.html">${t.consent.policy}</a>
        </p>
        <div class="consent__actions">
            <button type="button" class="consent__btn" data-choice="denied">${t.consent.reject}</button>
            <button type="button" class="consent__btn consent__btn--primary" data-choice="granted">${t.consent.accept}</button>
        </div>`;

    el.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-choice]');
        if (!btn) return;
        onChoice(btn.dataset.choice);
        el.remove();
    });

    document.body.appendChild(el);
}

export function initConsent() {
    let saved = null;
    try {
        saved = localStorage.getItem(KEY);
    } catch {
        /* ignora */
    }

    if (saved === 'granted') {
        loadGoogle();
        return;
    }
    if (saved === 'denied') return;

    showBanner((choice) => {
        try {
            localStorage.setItem(KEY, choice);
        } catch {
            /* ignora */
        }
        if (choice === 'granted') loadGoogle();
    });
}
