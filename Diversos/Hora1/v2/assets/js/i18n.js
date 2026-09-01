// Textos de interface centralizados. Ponto de partida para uma futura tradução:
// bastaria trocar este objeto conforme o idioma detectado.

export const t = {
    greeting: {
        dawn: 'Boa madrugada',
        morning: 'Bom dia',
        afternoon: 'Boa tarde',
        evening: 'Boa noite',
    },
    loadingDate: 'Carregando data…',
    locating: 'Localizando…',
    dash: '—',
    dayProgress: (pct) => `${pct}% do dia`,
    locationUnavailable: 'Localização indisponível',
    weatherUnavailable: 'Clima indisponível',
    offline: 'Você está offline',
    themeToLight: 'Claro',
    themeToDark: 'Escuro',
    consent: {
        text: 'Este site usa cookies de anúncios (Google AdSense) e de métricas (Google Analytics). Você pode aceitar ou recusar.',
        accept: 'Aceitar',
        reject: 'Recusar',
        policy: 'Política de privacidade',
    },
};
