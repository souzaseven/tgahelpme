// ============================================================
// notificacoes.js — Versão Profissional
// Central de alertas do Controle de Pausas
// ============================================================

console.log("%c[NOTIFICACOES] Central de notificações carregada", "color:#22c55e;font-weight:bold;");
// =======================
// CARREGAMENTO DE VOZES
// =======================

/*window.__tgaVozesCarregadas = false;

function carregarVozesTGA(callback) {
    const tentar = () => {
        const vozes = speechSynthesis.getVoices();
        if (vozes && vozes.length > 0) {
            window.__tgaVozesCarregadas = true;
            if (callback) callback(vozes);
        } else {
            setTimeout(tentar, 150);
        }
    };

    tentar();
}

carregarVozesTGA();

// ======================================================
// LOG DA VOZ ESCOLHIDA PELO OPERADOR
// ======================================================
function logPreferenciaVoz(prefs) {
    try {
        const vozes = speechSynthesis.getVoices();

        let descricao = "";
        switch (prefs.voz) {
            case "A": descricao = "Feminina"; break;
            case "B": descricao = "Masculina"; break;
            default:  descricao = "Robótica (C)"; break;
        }

        console.log(`%c[VOZ] Preferência do operador: ${descricao}`, "color:#94a3b8;font-weight:bold;");

        // Procurar voz real correspondente
        let vozSelecionada = null;

        if (prefs.voz === "A") {
            vozSelecionada = vozes.find(v =>
                v.lang.startsWith("pt") &&
                (v.name.toLowerCase().includes("female") ||
                 v.name.toLowerCase().includes("mulher") ||
                 v.name.toLowerCase().includes("maria") ||
                 v.name.toLowerCase().includes("google"))
            );
        }

        if (prefs.voz === "B") {
            vozSelecionada = vozes.find(v =>
                v.lang.startsWith("pt") &&
                (v.name.toLowerCase().includes("male") ||
                 v.name.toLowerCase().includes("guilherme") ||
                 v.name.toLowerCase().includes("homem"))
            );
        }

        if (vozSelecionada) {
            console.log(
                `%c[VOZ] Voz utilizada: ${vozSelecionada.name} (${vozSelecionada.lang})`,
                "color:#38bdf8;font-weight:bold;"
            );
        } else {
            console.log(
                "%c[VOZ] Nenhuma voz real encontrada — usando modo sintético.",
                "color:#f87171;font-weight:bold;"
            );
        }

    } catch (e) {
        console.warn("[VOZ] Erro ao gerar log:", e);
    }
}
*/
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
        alvo: op.pref_alerta_alvo || "todos"
      //  voz: op.pref_voz || "C"
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

// ============================================================
// LOOP PERSISTENTE — LIMITE DE PAUSA (a cada 5s)
// - mantém apenas no navegador do operador que estourou (alvo "meu")
// - evita duplicar setInterval
// - loga apenas START/STOP (não a cada 5s)
// ============================================================

window.__cpLoopLimitePausa = window.__cpLoopLimitePausa || {
    timer: null,
    ativo: false,
    operadorId: null,
    ultimaMsg: ""
};

function iniciarLoopLimitePausa(msg, prefs, contexto = {}) {
    const op = getOperadorLogadoNotif() || {};
    const operadorId = op.id ?? null;

    // só roda se for o próprio operador logado
    if (!operadorId) return;

    // se já está ativo para o mesmo operador, apenas atualiza msg e sai
    if (window.__cpLoopLimitePausa.ativo && window.__cpLoopLimitePausa.operadorId === operadorId) {
        window.__cpLoopLimitePausa.ultimaMsg = msg;
        return;
    }

    // se tinha outro loop (ex: troca de login), limpa
    pararLoopLimitePausa("troca_operador");

    window.__cpLoopLimitePausa.ativo = true;
    window.__cpLoopLimitePausa.operadorId = operadorId;
    window.__cpLoopLimitePausa.ultimaMsg = msg;

    // log START (uma vez)
    registrarLogAcao("LIMITE_PAUSA_LOOP_START", {
        operador_id: operadorId,
        mensagem: msg,
        contexto
    });

    const disparar = () => {
        // segurança: se perdeu o operador logado, para
        const opNow = getOperadorLogadoNotif() || {};
        if (!opNow.id || opNow.id !== operadorId) {
            pararLoopLimitePausa("sessao_alterada");
            return;
        }

        // re-lê prefs a cada ciclo (se o usuário desativar fala/áudio, respeita)
        const prefsAtual = getPrefsAlerta();

        if (prefsAtual.toast) mostrarToastNotificacao(window.__cpLoopLimitePausa.ultimaMsg);
        if (prefsAtual.audio) tocarBeepNotificacao("limite_pausa");
        if (prefsAtual.fala && typeof window.falarMensagem === "function") window.falarMensagem(window.__cpLoopLimitePausa.ultimaMsg);
        if (prefsAtual.notif) notificarDesktop(window.__cpLoopLimitePausa.ultimaMsg);
    };

    // dispara imediatamente e depois a cada 5s
    disparar();
    window.__cpLoopLimitePausa.timer = setInterval(disparar, 5000);
}

function pararLoopLimitePausa(motivo = "manual") {
    if (window.__cpLoopLimitePausa?.timer) {
        clearInterval(window.__cpLoopLimitePausa.timer);
    }

    if (window.__cpLoopLimitePausa?.ativo) {
        // log STOP (uma vez)
        const op = getOperadorLogadoNotif() || {};
        registrarLogAcao("LIMITE_PAUSA_LOOP_STOP", {
            operador_id: op.id ?? "",
            motivo
        });
    }

    window.__cpLoopLimitePausa.timer = null;
    window.__cpLoopLimitePausa.ativo = false;
    window.__cpLoopLimitePausa.operadorId = null;
    window.__cpLoopLimitePausa.ultimaMsg = "";
}


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
entrou_pausa: [550, 0.20],

            limite_pausa: [350, 0.35],
pausa_expirada: [300, 0.40],

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
/*
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
*/
/* vai ficar somente no preferencias.js
function falarNotificacao(texto, prefs) {
    try {
        if (!("speechSynthesis" in window)) return;

        // ==========================================================
        // Função interna que realmente executa a fala
        // ==========================================================
        const executarFala = () => {
            const msg = new SpeechSynthesisUtterance(texto);
            msg.lang = "pt-BR";

            const vozes = speechSynthesis.getVoices();
            console.log("[VOZ] Vozes detectadas:", vozes.map(v => v.name));

            // ------------------------------  
            // B = Feminina corporativa  
            // ------------------------------  
            const vozFem = vozes.find(v =>
                v.lang.startsWith("pt") &&
                (
                    v.name.toLowerCase().includes("female") ||
                    v.name.toLowerCase().includes("mulher") ||
                    v.name.toLowerCase().includes("google") ||
                    v.name.toLowerCase().includes("maria") ||
                    v.name.toLowerCase().includes("femin")
                )
            );

            // ------------------------------  
            // A = Masculina  
            // ------------------------------  
            const vozMasc = vozes.find(v =>
                v.lang.startsWith("pt") &&
                (
                    v.name.toLowerCase().includes("male") ||
                    v.name.toLowerCase().includes("homem") ||
                    v.name.toLowerCase().includes("guilherme") ||
                    v.name.toLowerCase().includes("masc")
                )
            );

            console.log("[VOZ] Preferência selecionada:", prefs.voz);

            // ------------------------------  
            // Aplicar prioridade correta  
            // ------------------------------
            if (prefs.voz === "B" && vozFem) {
                console.log("[VOZ] → Usando voz FEMININA real:", vozFem.name);
                msg.voice = vozFem;
            }
            else if (prefs.voz === "A" && vozMasc) {
                console.log("[VOZ] → Usando voz MASCULINA real:", vozMasc.name);
                msg.voice = vozMasc;
            }
            else {
                console.log("[VOZ] → Nenhuma voz real encontrada, usando sintética");
                const mapaSintetica = {
                    A: [1.05, 1.2],  // masculina sintética
                    B: [1.0, 0.9],   // feminina sintética
                    C: [1.1, 1.0]    // robótica
                };
                const [rate, pitch] = mapaSintetica[prefs.voz] || mapaSintetica.C;
                msg.rate = rate;
                msg.pitch = pitch;
            }

            speechSynthesis.speak(msg);
        };

        // ==========================================================
        // GARANTIR QUE AS VOZES CARREGARAM ANTES
        // ==========================================================
        if (speechSynthesis.getVoices().length === 0) {
            console.log("[VOZ] Aguardando carregamento das vozes...");
            speechSynthesis.onvoiceschanged = executarFala;
        } else {
            executarFala();
        }

    } catch (e) {
        console.warn("[NOTIFICACOES] Falha ao falar:", e);
    }
}
*/
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
entrou_pausa: `O operador ${nome} entrou em pausa.`,

        limite_pausa: "Você passou do limite permitido de pausa.",
 pausa_expirada: `O operador ${nome} passou do limite de pausa (20 minutos).`,
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
/*
    if (prefs.toast) mostrarToastNotificacao(msg); else logNotif("Toast silenciado");
    if (prefs.audio) tocarBeepNotificacao(evento); else logNotif("Som silenciado");
    //if (prefs.fala)  falarNotificacao(msg, prefs); else logNotif("Fala silenciada");
if (prefs.fala && typeof window.falarMensagem === "function") {
    window.falarMensagem(msg);*/

// START do loop persistente (somente para o próprio operador)
if (evento === "limite_pausa" && alvo === "meu") {
    iniciarLoopLimitePausa(msg, prefs, { evento, alvo });
}

// STOP do loop quando sair da pausa (somente para o próprio operador)
if (evento === "saiu_pausa" && alvo === "meu") {
    pararLoopLimitePausa("saiu_pausa");
}

// Disparo normal (1x) do alerta do evento atual
if (prefs.toast) mostrarToastNotificacao(msg); else logNotif("Toast silenciado");
if (prefs.audio) tocarBeepNotificacao(evento); else logNotif("Som silenciado");

if (prefs.fala && typeof window.falarMensagem === "function") {
    window.falarMensagem(msg);
} else {
    logNotif("Fala silenciada ou falarMensagem não disponível");
}

if (prefs.notif) notificarDesktop(msg); else logNotif("Notificação desktop silenciada");

    
if (prefs.notif) notificarDesktop(msg); else logNotif("Notificação desktop silenciada");

    registrarLogAcao("ALERTA_ENVIADO", {
        evento,
        alvo,
        mensagem: msg,
        preferencias_usadas: prefs
    });
};
