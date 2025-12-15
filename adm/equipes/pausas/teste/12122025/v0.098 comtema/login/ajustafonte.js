// Aguarda todo o DOM carregar ANTES de executar qualquer coisa
document.addEventListener("DOMContentLoaded", () => {

// ======================================================
// SISTEMA DE PREFERÊNCIAS DO LOGIN
// Fontes, H3, Cards, Reset, LocalStorage
// ======================================================

// Elementos do menu
const menuPrefs = document.getElementById("menu-preferencias");
const btnPrefs  = document.getElementById("btn-preferencias");
const btnReset  = document.getElementById("btnReset");

// Sliders
const rangeH1    = document.getElementById("rangeH1");
const rangeH2    = document.getElementById("rangeH2");
const rangeH3    = document.getElementById("rangeH3");
const rangeP     = document.getElementById("rangeP");
const rangeBox   = document.getElementById("rangeBox");
const rangeCards = document.getElementById("rangeCards");

// Labels
const valH1    = document.getElementById("valH1");
const valH2    = document.getElementById("valH2");
const valH3    = document.getElementById("valH3");
const valP     = document.getElementById("valP");
const valBox   = document.getElementById("valBox");
const valCards = document.getElementById("valCards");

// Elementos afetados
const h1El  = document.querySelector(".login-box h1");
const h2El  = document.querySelector(".login-box h2");
const h3Els = document.querySelectorAll(".login-box h3");
const pEl   = document.querySelector(".login-box p");
const boxEl = document.querySelector(".login-box");

// Captura dinâmica dos cards
function getCards() {
    return document.querySelectorAll(".card-equipe, .card-operador");
}

// Abrir/fechar menu
btnPrefs?.addEventListener("click", () => {
    menuPrefs.classList.toggle("open");
});

// Carregar valores
function carregarPreferencias() {

    const defaults = {
        pref_login_h1: 48,
        pref_login_h2: 40,
        pref_login_h3: 18,
        pref_login_p: 22,
        pref_login_box: 700,
        pref_login_cards: 13
    };

    // Criar padrões APENAS se não existir nada salvo
    for (const key in defaults) {
        if (localStorage.getItem(key) === null) {
            localStorage.setItem(key, defaults[key]);
        }
    }

    // Carregar valores
    let fH1    = parseFloat(localStorage.getItem("pref_login_h1"));
    let fH2    = parseFloat(localStorage.getItem("pref_login_h2"));
    let fH3    = parseFloat(localStorage.getItem("pref_login_h3"));
    let fP     = parseFloat(localStorage.getItem("pref_login_p"));
    let fBox   = parseFloat(localStorage.getItem("pref_login_box"));
    let fCards = parseFloat(localStorage.getItem("pref_login_cards"));

    // Aplicar valores
    if (h1El) h1El.style.fontSize = `${fH1}px`;
    if (h2El) h2El.style.fontSize = `${fH2}px`;

    h3Els.forEach(h3 => h3.style.fontSize = `${fH3}px`);

    if (pEl) pEl.style.fontSize = `${fP}px`;
    if (boxEl) boxEl.style.maxWidth = `${fBox}px`;

    getCards().forEach(card => {
        card.style.fontSize = `${fCards}px`;
    });

    // Sliders e labels
    rangeH1.value = fH1;
    rangeH2.value = fH2;
    rangeH3.value = fH3;
    rangeP.value  = fP;
    rangeBox.value = fBox;
    rangeCards.value = fCards;

    valH1.innerText = `${fH1}px`;
    valH2.innerText = `${fH2}px`;
    valH3.innerText = `${fH3}px`;
    valP.innerText  = `${fP}px`;
    valBox.innerText = `${fBox}px`;
    valCards.innerText = `${fCards}px`;
}

// EVENTOS — Atualizam e salvam
rangeH1?.addEventListener("input", () => {
    let v = rangeH1.value;
    h1El.style.fontSize = `${v}px`;
    valH1.innerText = `${v}px`;
    localStorage.setItem("pref_login_h1", v);
});

rangeH2?.addEventListener("input", () => {
    let v = rangeH2.value;
    h2El.style.fontSize = `${v}px`;
    valH2.innerText = `${v}px`;
    localStorage.setItem("pref_login_h2", v);
});

rangeH3?.addEventListener("input", () => {
    let v = rangeH3.value;
    h3Els.forEach(h3 => h3.style.fontSize = `${v}px`);
    valH3.innerText = `${v}px`;
    localStorage.setItem("pref_login_h3", v);
});

rangeP?.addEventListener("input", () => {
    let v = rangeP.value;
    pEl.style.fontSize = `${v}px`;
    valP.innerText = `${v}px`;
    localStorage.setItem("pref_login_p", v);
});

rangeBox?.addEventListener("input", () => {
    let v = rangeBox.value;
    boxEl.style.maxWidth = `${v}px`;
    valBox.innerText = `${v}px`;
    localStorage.setItem("pref_login_box", v);
});

rangeCards?.addEventListener("input", () => {
    let v = rangeCards.value;

    getCards().forEach(card => {
        card.style.fontSize = `${v}px`;
    });

    valCards.innerText = `${v}px`;
    localStorage.setItem("pref_login_cards", v);
});

// RESET
btnReset?.addEventListener("click", () => {

    const defaults = {
        pref_login_h1: 48,
        pref_login_h2: 40,
        pref_login_h3: 18,
        pref_login_p: 22,
        pref_login_box: 700,
        pref_login_cards: 13
    };

    for (const key in defaults) {
        localStorage.setItem(key, defaults[key]);
    }

    carregarPreferencias();

    btnReset.innerText = "Redefinido ✔";
    setTimeout(() => btnReset.innerText = "Restaurar Padrões", 1500);
});

// Iniciar
carregarPreferencias();

}); // ← FINAL DO DOMContentLoaded
