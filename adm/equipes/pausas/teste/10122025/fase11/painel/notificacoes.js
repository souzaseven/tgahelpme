// ============================================================
// notificacoes.js — FASE 11 FINAL
// Central REAL de alertas do Controle de Pausas
// ------------------------------------------------------------
// - Lê preferências do operador (localStorage + preferencias.js)
// - Define window.dispararAlertas(evento, payload)
// - Painel.js usa isso para enviar alertas em tempo real
// - Eventos suportados:
//     • entrou_fila
//     • saiu_fila
//     • saiu_pausa
//     • virou_primeiro
//     • posicao_mudou { posicao }
//     • vaga_abriu
//     • alguem_entrou_pausa { nome }
//     • limite_pausa
//     • teste
// ============================================================

console.log("%c[NOTIFICACOES] Fase 11 carregada", "color:#22c55e;font-weight:bold;");

// Cache para não ficar lendo localStorage toda hora
let cachePreferencias = null;

// ------------------------------------------------------------
// 1) Obter operador logado
// ------------------------------------------------------------
function getOperadorLogado() {
    try {
        return JSON.parse(localStorage.getItem("tga_operador")) || null;
    } catch (e) {
        return null;
    }
}

// ------------------------------------------------------------
// 2) Carregar preferências locais
// ------------------------------------------------------------
function carregarPreferenciasLocal() {
    if (cachePreferencias) return cachePreferencias;

    const op = getOperadorLogado();
    if (!op || !op.id) {
        cachePreferencias = normalizarPreferencias(null);
        return cachePreferencias;
    }

    // Preferencias.js já salvou no próprio objeto?
    if (window.cpPreferenciasAlertas) {
        cachePreferencias = normalizarPreferencias(window.cpPreferenciasAlertas);
        return cachePreferencias;
    }

    // Buscar localStorage padrão usado por preferencias.js
    try {
        const raw = localStorage.getItem("tga_operador");
        if (raw) {
            const obj = JSON.parse(raw);
            cachePreferencias = normalizarPreferencias({
                habilitarSom: obj.pref_audio == 1,
                habilitarToast: obj.pref_toast == 1,
                habilitarNavegador: obj.pref_notif == 1,

                // eventos
                entrou_fila: true,
                saiu_fila: true,
                saiu_pausa: true,
                virou_primeiro: true,
                posicao_mudou: true,
                vaga_abriu: true,
                alguem_entrou_pausa: true,
                limite_pausa: true,
                teste: true
            });

            return cachePreferencias;
        }
    } catch (e) {
        console.warn("[NOTIFICACOES] Falha lendo localStorage:", e);
    }

    cachePreferencias = normalizarPreferencias(null);
    return cachePreferencias;
}

// ------------------------------------------------------------
// 3) Normalizar estrutura das preferências
// ------------------------------------------------------------
function normalizarPreferencias(raw) {
    if (!raw) raw = {};

    const saida = {};

    const eventos = [
        "entrou_fila",
        "saiu_fila",
        "saiu_pausa",
        "virou_primeiro",
        "posicao_mudou",
        "vaga_abriu",
        "alguem_entrou_pausa",
        "limite_pausa",
        "teste"
    ];

    eventos.forEach(ev => {
        const v = raw[ev];
        saida[ev] = (v === true || v === 1 || v === "1" || v === "S");
    });

    saida.habilitarSom       = raw.habilitarSom       ?? true;
    saida.habilitarToast     = raw.habilitarToast     ?? true;
    saida.habilitarNavegador = raw.habilitarNavegador ?? false;

    return saida;
}

// ------------------------------------------------------------
// 4) Tocar SONS (via preferencias.js já usa WebAudio, aqui só usa MP3 extra)
// ------------------------------------------------------------
function tocarSomAlertas() {
    const prefs = carregarPreferenciasLocal();
    if (!prefs.habilitarSom) return;

    try {
        const audio = new Audio("../sons/alerta_pausa.mp3");
        audio.volume = 1.0;
        audio.play().catch(() => {});
    } catch (e) {
        console.warn("[NOTIFICACOES] Falha ao tocar som:", e);
    }
}

// ------------------------------------------------------------
// 5) Toast visual simples
// ------------------------------------------------------------
function mostrarToast(msg) {
    const prefs = carregarPreferenciasLocal();
    if (!prefs.habilitarToast) return;

    const box = document.createElement("div");
    box.className = "cp-toast";
    box.textContent = msg;

    Object.assign(box.style, {
        position: "fixed",
        right: "20px",
        bottom: "20px",
        background: "rgba(0,0,0,0.85)",
        color: "#fff",
        padding: "10px 14px",
        borderRadius: "8px",
        fontSize: "14px",
        zIndex: 999999,
        border: "1px solid #38bdf8",
        boxShadow: "0 0 8px #000",
        opacity: 1,
        transition: "opacity 0.4s"
    });

    document.body.appendChild(box);

    setTimeout(() => {
        box.style.opacity = "0";
        setTimeout(() => box.remove(), 400);
    }, 3500);
}

// ------------------------------------------------------------
// 6) Notificação Desktop (Windows / macOS)
// ------------------------------------------------------------
function notificarDesktop(titulo, corpo) {
    const prefs = carregarPreferenciasLocal();
    if (!prefs.habilitarNavegador) return;

    if (!("Notification" in window)) return;

    const enviar = () => {
        try {
            new Notification(titulo, { body: corpo });
        } catch (e) {
            console.warn("[NOTIFICACOES] Notification API falhou:", e);
        }
    };

    if (Notification.permission === "granted") {
        enviar();
    } else if (Notification.permission === "default") {
        Notification.requestPermission().then(p => {
            if (p === "granted") enviar();
        });
    }
}

// ------------------------------------------------------------
// 7) FUNÇÃO GLOBAL — O Painel chama isso!
// ------------------------------------------------------------
window.dispararAlertas = function (evento, payload = {}) {
    const prefs = carregarPreferenciasLocal();

    // Bloqueado pela preferência individual?
    if (Object.prototype.hasOwnProperty.call(prefs, evento) && !prefs[evento]) {
        console.log("[NOTIFICACOES] Bloqueado pelo usuário:", evento);
        return;
    }

    let msg = "";

    switch (evento) {
        case "entrou_fila":
            msg = "Você entrou na fila de pausa.";
            break;
        case "saiu_fila":
            msg = "Você saiu da fila de pausa.";
            break;
        case "saiu_pausa":
            msg = "Você saiu da pausa.";
            break;
        case "virou_primeiro":
            msg = "Você agora é o primeiro da fila!";
            break;
        case "posicao_mudou":
            msg = `Sua posição mudou para ${payload.posicao}º`;
            break;
        case "vaga_abriu":
            msg = "Uma vaga de pausa foi liberada!";
            break;
        case "alguem_entrou_pausa":
            msg = `${payload.nome || "Um operador"} entrou em pausa.`;
            break;
        case "limite_pausa":
            msg = "⚠ Você ultrapassou o limite máximo de pausa!";
            break;
        case "teste":
        default:
            msg = payload.msg || "Teste de notificação.";
            break;
    }

    console.log("[NOTIFICACOES] Disparando:", evento, msg);

    mostrarToast(msg);
    tocarSomAlertas();
    notificarDesktop("Controle de Pausas", msg);
};
