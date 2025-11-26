// ============================================================
// notificacoes.js — Versão Profissional
// Central de alertas do Controle de Pausas
// ============================================================

console.log("%c[NOTIFICACOES] Central de notificações carregada", "color:#22c55e;font-weight:bold;");

// ------------------ CONFIGURAÇÃO GERAL ------------------
const DEBUG_MODE = false;

const logNotif = (...args) => {
    if (DEBUG_MODE) console.log("[NOTIFICACOES]", ...args);
};

// ------------------ UTILITÁRIOS ------------------
function getOperadorLogadoNotif() {
    try {
        return JSON.parse(localStorage.getItem("tga_operador")) || null;
    } catch (e) {
        console.warn("[NOTIFICACOES] Erro ao ler tga_operador:", e);
        return null;
    }
}

function getPrefsAlerta() {
    const op = getOperadorLogadoNotif() || {};
    return {
        toast: op.pref_toast != 0,
        fala: op.pref_fala != 0,
        audio: op.pref_audio != 0,
        notif: op.pref_notif != 0,
        alvo: op.pref_alerta_alvo || "todos",
        voz: op.pref_voz || "C"
    };
}

window.registrarLogAcao = async function (acao, detalhes = {}) {
    try {
        const op = getOperadorLogadoNotif() || {};
        const body = new URLSearchParams({
            acao,
            detalhes: JSON.stringify(detalhes),
            operador_id: op.id ?? "",
            operador_nome: op.nome || op.operador || op.usuario || "",
            equipe: op.equipe || ""
        });

        await fetch("../backend/registrar_log.php", { method: "POST", body });
    } catch (e) {
        console.warn("[NOTIFICACOES] Falha ao registrar log:", e);
    }
};

function mostrarToastNotificacao(mensagem) {
    const div = document.createElement("div");
    div.className = "cp-toast-notif";
    div.textContent = mensagem;

    Object.assign(div.style, {
        position: "fixed",
        right: "16px",
        top: "16px",
        background: "rgba(15,23,42,0.95)",
        color: "#e2e8f0",
        padding: "10px 14px",
        borderRadius: "8px",
        fontSize: "13px",
        zIndex: 999999,
        boxShadow: "0 0 10px rgba(0,0,0,0.5)",
        maxWidth: "260px",
        border: "1px solid #38bdf8",
        opacity: "0",
        transition: "opacity 0.2s ease",
        pointerEvents: "none"
    });

    document.body.appendChild(div);
    requestAnimationFrame(() => (div.style.opacity = "1"));
    setTimeout(() => {
        div.style.opacity = "0";
        setTimeout(() => div.remove(), 300);
    }, 3500);
}

let notifAudioCtx = null;
function tocarBeepNotificacao(evento) {
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        notifAudioCtx = notifAudioCtx || new Ctx();

        const ctx = notifAudioCtx;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);

        const mapa = {
            virou_primeiro: [1000, 0.25],
            vaga_abriu: [880, 0.22],
            alguem_entrou_pausa: [520, 0.16],
            posicao_mudou: [600, 0.16],
            entrou_fila: [500, 0.16],
            saiu_fila: [430, 0.14],
            saiu_pausa: [480, 0.16],
            limite_pausa: [350, 0.35]
        };

        const [freq, dur] = mapa[evento] || [650, 0.18];
        osc.frequency.value = freq;
        gain.gain.value = 0.15;

        const now = ctx.currentTime;
        osc.start(now);
        osc.stop(now + dur);
    } catch (e) {
        console.warn("[NOTIFICACOES] Falha ao tocar beep:", e);
    }
}

function falarNotificacao(texto, prefs) {
    try {
        if (!("speechSynthesis" in window)) return;
        const msg = new SpeechSynthesisUtterance(texto);
        msg.lang = "pt-BR";
        const vozes = {
            A: [1.0, 0.9],
            B: [1.05, 1.2],
            C: [1.1, 1.0]
        };
        const [rate, pitch] = vozes[prefs.voz] || vozes.C;
        msg.rate = rate;
        msg.pitch = pitch;
        speechSynthesis.speak(msg);
    } catch (e) {
        console.warn("[NOTIFICACOES] Falha ao falar:", e);
    }
}

function notificarDesktop(mensagem, titulo = "Controle de Pausa") {
    if (!("Notification" in window)) return;
    const enviar = () => {
        try {
            new Notification(titulo, { body: mensagem });
        } catch (e) {
            console.warn("[NOTIFICACOES] Notification API falhou:", e);
        }
    };

    if (Notification.permission === "granted") {
        enviar();
    } else if (Notification.permission === "default") {
        Notification.requestPermission().then(p => p === "granted" && enviar());
    }
}

function montarMensagemEvento(evento, extra = {}) {
    const nome = extra?.nome || "desconhecido";
    const pos = extra?.posicao;
    const msgs = {
        entrou_fila: "Você entrou na fila de espera.",
        saiu_fila: "Você saiu da fila de espera.",
        saiu_pausa: "Você saiu da pausa.",
        virou_primeiro: "Você agora é o primeiro da fila.",
        posicao_mudou: pos ? `Sua posição na fila mudou para ${pos}º.` : "Sua posição na fila mudou.",
        vaga_abriu: "Uma vaga de pausa foi liberada na equipe.",
        alguem_entrou_pausa: `O operador ${nome} entrou em pausa.`,
        limite_pausa: "Você passou do limite permitido de pausa.",
        teste: extra.msg || "Notificação do controle de pausas."
    };
    return msgs[evento] || extra.msg || "Notificação do controle de pausas.";
}

window.dispararAlertas = function (evento, extra = {}) {
    const prefs = getPrefsAlerta();
    const alvo = extra.alvo || "meu";
    const msg = montarMensagemEvento(evento, extra);

    logNotif("dispararAlertas", { evento, alvo, prefs, msg });

    if (prefs.alvo === "nenhum" || (prefs.alvo === "meu" && alvo === "outro")) {
        registrarLogAcao("ALERTA_SILENCIADO", {
            evento,
            motivo: prefs.alvo === "nenhum" ? "pref_alerta_alvo = 'nenhum'" : "pref_alerta_alvo = 'meu' e alvo = 'outro'",
            alvo,
            mensagem: msg
        });
        return;
    }

    if (prefs.toast) mostrarToastNotificacao(msg); else logNotif("Toast silenciado");
    if (prefs.audio) tocarBeepNotificacao(evento); else logNotif("Som silenciado");
    if (prefs.fala)  falarNotificacao(msg, prefs); else logNotif("Fala silenciada");
    if (prefs.notif) notificarDesktop(msg); else logNotif("Notificação desktop silenciada");

    registrarLogAcao("ALERTA_ENVIADO", {
        evento,
        alvo,
        mensagem: msg,
        preferencias_usadas: prefs
    });
};
