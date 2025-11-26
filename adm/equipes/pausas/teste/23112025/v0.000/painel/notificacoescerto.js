// ============================================================
// notificacoes.js — FASE 12
// Central REAL de alertas do Controle de Pausas
// ------------------------------------------------------------
// - Lê preferências direto do localStorage ("tga_operador")
//   preenchido pelo login e atualizado pelo salvar_preferencias.php
// - Tudo começa HABILITADO por padrão (toast/fala/som/notif)
// - Respeita as preferências salvas:
//     • pref_toast       (0/1)
//     • pref_fala        (0/1)
//     • pref_audio       (0/1)
//     • pref_notif       (0/1)
//     • pref_alerta_alvo ("todos" | "meu" | "nenhum")
// - Se for silenciado por preferência, loga no console o motivo
// - Gera log em PHP (registrar_log.php) de tudo o que acontece
// - Usado pelo painel.js via window.dispararAlertas(evento, payload)
// ============================================================

console.log("%c[NOTIFICACOES] Fase 12 carregada", "color:#22c55e;font-weight:bold;");

// ------------------------------------------------------------
// 1) Obter operador logado (mesma base dos outros arquivos)
// ------------------------------------------------------------
function getOperadorLogadoNotif() {
    try {
        return JSON.parse(localStorage.getItem("tga_operador")) || null;
    } catch (e) {
        console.warn("[NOTIFICACOES] Erro ao ler tga_operador:", e);
        return null;
    }
}

// ------------------------------------------------------------
// 2) Ler preferências de alerta
//    Regra: por padrão TUDO HABILITADO
// ------------------------------------------------------------
function getPrefsAlerta() {
    const op = getOperadorLogadoNotif();

    // padrão: tudo ligado, alvo = todos, voz neutra
    const base = {
        toast: true,
        fala:  true,
        audio: true,
        notif: true,
        alvo:  "todos", // todos | meu | nenhum
        voz:   "C"      // A | B | C (mesma ideia do preferencias.js)
    };

    if (!op) return base;

    // Se o campo ainda não existe, continua true (tudo habilitado)
    base.toast = (op.pref_toast === undefined) ? true : (op.pref_toast == 1);
    base.fala  = (op.pref_fala  === undefined) ? true : (op.pref_fala  == 1);
    base.audio = (op.pref_audio === undefined) ? true : (op.pref_audio == 1);
    base.notif = (op.pref_notif === undefined) ? true : (op.pref_notif == 1);
    base.alvo  = op.pref_alerta_alvo || "todos";
    base.voz   = op.pref_voz || "C";

    return base;
}

// ------------------------------------------------------------
// 3) Helper: registrar log no servidor (TXT por dia)
// ------------------------------------------------------------
window.registrarLogAcao = async function (acao, detalhes = {}) {
    try {
        const op = getOperadorLogadoNotif() || {};
        const body = new URLSearchParams();

        body.append("acao", acao);
        body.append("detalhes", JSON.stringify(detalhes));
        body.append("operador_id", op.id ?? "");
        body.append("operador_nome", op.nome || op.operador || op.usuario || "");
        body.append("equipe", op.equipe || "");

        await fetch("../backend/registrar_log.php", {
            method: "POST",
            body
        }).catch(() => {
            // se der erro de rede, só loga no console
            console.warn("[NOTIFICACOES] Falha ao enviar log para o servidor.");
        });
    } catch (e) {
        console.warn("[NOTIFICACOES] Erro em registrarLogAcao:", e);
    }
};

// ------------------------------------------------------------
// 4) Helper: Toast visual simples (independente do preferencias.js)
// ------------------------------------------------------------
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
        transition: "opacity 0.2s ease"
    });

    document.body.appendChild(div);

    requestAnimationFrame(() => {
        div.style.opacity = "1";
    });

    setTimeout(() => {
        div.style.opacity = "0";
        setTimeout(() => div.remove(), 300);
    }, 3500);
}

// ------------------------------------------------------------
// 5) Helper: Beep simples via WebAudio
// ------------------------------------------------------------
let notifAudioCtx = null;

function tocarBeepNotificacao(evento) {
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;

        if (!notifAudioCtx) {
            notifAudioCtx = new Ctx();
        }

        const ctx  = notifAudioCtx;
        const osc  = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.connect(gain);
        gain.connect(ctx.destination);

        let freq = 700;
        let dur  = 0.18;

        switch (evento) {
            case "virou_primeiro":
                freq = 1000; dur = 0.25; break;
            case "vaga_abriu":
                freq = 880;  dur = 0.22; break;
            case "alguem_entrou_pausa":
                freq = 520;  dur = 0.16; break;
            case "posicao_mudou":
                freq = 600;  dur = 0.16; break;
            case "entrou_fila":
                freq = 500;  dur = 0.16; break;
            case "saiu_fila":
                freq = 430;  dur = 0.14; break;
            case "saiu_pausa":
                freq = 480;  dur = 0.16; break;
            case "limite_pausa":
                freq = 350;  dur = 0.35; break;
            default:
                freq = 650;  dur = 0.18; break;
        }

        osc.frequency.value = freq;
        gain.gain.value     = 0.15;

        const now = ctx.currentTime;
        osc.start(now);
        osc.stop(now + dur);

    } catch (e) {
        console.warn("[NOTIFICACOES] Falha ao tocar beep:", e);
    }
}

// ------------------------------------------------------------
// 6) Helper: Fala (speechSynthesis simples, respeita pref_voz)
// ------------------------------------------------------------
function falarNotificacao(texto, prefs) {
    try {
        if (!("speechSynthesis" in window)) return;

        const msg = new SpeechSynthesisUtterance(texto);
        msg.lang = "pt-BR";

        // ajustes simples baseados em prefs.voz
        switch (prefs.voz) {
            case "A": // masculina
                msg.rate  = 1.0;
                msg.pitch = 0.9;
                break;
            case "B": // feminina
                msg.rate  = 1.05;
                msg.pitch = 1.2;
                break;
            case "C":
            default:
                msg.rate  = 1.1;
                msg.pitch = 1.0;
                break;
        }

        speechSynthesis.speak(msg);
    } catch (e) {
        console.warn("[NOTIFICACOES] Falha ao falar notificação:", e);
    }
}

// ------------------------------------------------------------
// 7) Helper: Notification API (desktop / Windows)
// ------------------------------------------------------------
function notificarDesktop(mensagem) {
    if (!("Notification" in window)) return;

    const enviar = () => {
        try {
            new Notification("Controle de Pausa", { body: mensagem });
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
// 8) Texto padrão para cada evento
// ------------------------------------------------------------
function montarMensagemEvento(evento, extra = {}) {
    switch (evento) {
        case "entrou_fila":
            return "Você entrou na fila de espera.";
        case "saiu_fila":
            return "Você saiu da fila de espera.";
        case "saiu_pausa":
            return "Você saiu da pausa.";
        case "virou_primeiro":
            return "Você agora é o primeiro da fila.";
        case "posicao_mudou":
            if (extra && extra.posicao) {
                return `Sua posição na fila mudou para ${extra.posicao}º.`;
            }
            return "Sua posição na fila mudou.";
        case "vaga_abriu":
            return "Uma vaga de pausa foi liberada na equipe.";
        case "alguem_entrou_pausa":
            return `O operador ${extra?.nome || "desconhecido"} entrou em pausa.`;
        case "limite_pausa":
            return "Você passou do limite permitido de pausa.";
        case "teste":
        default:
            return extra.msg || "Notificação do controle de pausas.";
    }
}

// ------------------------------------------------------------
// 9) Função GLOBAL chamada pelo painel.js
//     window.dispararAlertas(evento, payload?)
// ------------------------------------------------------------
window.dispararAlertas = function (evento, extra = {}) {
    const prefs = getPrefsAlerta();
    const alvo  = extra.alvo || "meu"; // 'meu' | 'outro'
    const mensagem = montarMensagemEvento(evento, extra);

    console.log("[NOTIFICACOES] dispararAlertas:", {
        evento,
        alvo,
        prefs,
        mensagem
    });

    // 1) FILTRO por alvo (todos / meu / nenhum)
    if (prefs.alvo === "nenhum") {
        console.log(
            "[NOTIFICACOES] Evento silenciado (pref_alerta_alvo = 'nenhum'):",
            evento
        );
        registrarLogAcao("ALERTA_SILENCIADO", {
            evento,
            motivo: "pref_alerta_alvo = 'nenhum'",
            alvo,
            mensagem
        });
        return;
    }

    if (prefs.alvo === "meu" && alvo === "outro") {
        console.log(
            "[NOTIFICACOES] Evento silenciado (apenas 'meu status' e evento é de 'outro'):",
            evento
        );
        registrarLogAcao("ALERTA_SILENCIADO", {
            evento,
            motivo: "pref_alerta_alvo = 'meu' e alvo = 'outro'",
            alvo,
            mensagem
        });
        return;
    }

    // 2) Toast
    if (prefs.toast) {
        mostrarToastNotificacao(mensagem);
    } else {
        console.log("[NOTIFICACOES] Toast silenciado por preferência (pref_toast = 0).");
    }

    // 3) Som (beep)
    if (prefs.audio) {
        tocarBeepNotificacao(evento);
    } else {
        console.log("[NOTIFICACOES] Som silenciado por preferência (pref_audio = 0).");
    }

    // 4) Fala (voz)
    if (prefs.fala) {
        falarNotificacao(mensagem, prefs);
    } else {
        console.log("[NOTIFICACOES] Fala silenciada por preferência (pref_fala = 0).");
    }

    // 5) Notificação do Windows
    if (prefs.notif) {
        notificarDesktop(mensagem);
    } else {
        console.log("[NOTIFICACOES] Notificação desktop silenciada (pref_notif = 0).");
    }

    // 6) Log geral de alerta enviado
    registrarLogAcao("ALERTA_ENVIADO", {
        evento,
        alvo,
        mensagem,
        preferencias_usadas: prefs
    });
};
