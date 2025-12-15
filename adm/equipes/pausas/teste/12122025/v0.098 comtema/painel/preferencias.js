// ============================================================
// preferencias.js — FASE 9 COMPLETA (com WebAudio + seleção de voz real)
// ============================================================
// - Preferências integradas ao painel
// - Toast real (no topo)
// - Beeps gerados no navegador (sem arquivo .mp3)
// - Testes reais de áudio, fala e notificações
// - Salva no banco (salvar_preferencias.php)
// - Atualiza localStorage do operador logado
// - FASE 9: ALERTAS automáticos disparados pelo painel.js
//   Eventos suportados:
//     - entrou_fila
//     - saiu_fila
//     - virou_primeiro
//     - posicao_mudou {posicao}
//     - vaga_abriu
//     - alguem_entrou_pausa {nome}
//     - limite_pausa
// ============================================================

console.log("%c[PREFERENCIAS] Fase 9 carregada", "color:#a855f7;font-weight:bold;");

// ------------------------------------------------------------
// Carregar operador logado
// ------------------------------------------------------------
function getOperadorLogado() {
    try {
        return JSON.parse(localStorage.getItem("tga_operador")) || null;
    } catch (e) {
        return null;
    }
}

// ============================================================
// GERENCIAMENTO DE VOZES (speechSynthesis.getVoices)
// ============================================================
let LISTA_VOZES = [];

function atualizarListaVozes() {
    try {
        if (!("speechSynthesis" in window)) return;
        const vozes = window.speechSynthesis.getVoices() || [];
        if (vozes.length > 0) {
            LISTA_VOZES = vozes;
        }
    } catch (e) {
        console.warn("[PREFERENCIAS] Erro ao obter vozes:", e);
    }
}

if ("speechSynthesis" in window) {
    window.speechSynthesis.onvoiceschanged = atualizarListaVozes;
    atualizarListaVozes();
}
/*
function escolherVozPorTipo(tipo) {
    if (!LISTA_VOZES || LISTA_VOZES.length === 0) return null;

    const ptBR = LISTA_VOZES.filter(v => v.lang && v.lang.toLowerCase().startsWith("pt-br"));
    const baseLista = ptBR.length > 0 ? ptBR : LISTA_VOZES;

    if (baseLista.length === 0) return null;

    switch (tipo) {
        case "A": // masculina
            return baseLista[0] || LISTA_VOZES[0];
        case "B": // feminina
            return baseLista[1] || baseLista[0] || LISTA_VOZES[0];
        case "C": // neutra
        default:
            return baseLista[2] || baseLista[0] || LISTA_VOZES[0];
    }
}*/
function escolherVozPorTipo(tipo) {
    if (!LISTA_VOZES || LISTA_VOZES.length === 0) return null;

    // 1️⃣ Prioridade ABSOLUTA: Google PT-BR
    const googlePT = LISTA_VOZES.filter(v =>
        v.lang?.toLowerCase().startsWith("pt-br") &&
        v.name.toLowerCase().includes("google")
    );

    if (googlePT.length) {
        return googlePT[0];
    }

    // 2️⃣ Segunda prioridade: qualquer Google
    const googleAny = LISTA_VOZES.filter(v =>
        v.name.toLowerCase().includes("google")
    );

    if (googleAny.length) {
        return googleAny[0];
    }

    // 3️⃣ Último recurso: Microsoft (volume NÃO será respeitado)
    console.warn(
        "[VOZ] Usando voz Microsoft — controle de volume será ignorado"
    );

    const ptBR = LISTA_VOZES.filter(v =>
        v.lang?.toLowerCase().startsWith("pt-br")
    );

    return ptBR[0] || LISTA_VOZES[0];
}
/*
window.falarMensagem = function (texto) {
    if (!("speechSynthesis" in window)) return;

    const dados = getOperadorLogado() || {};
    const cfg   = getConfigVoz();

    const volUser = Number.isFinite(Number(dados.pref_volume_fala))
        ? Number(dados.pref_volume_fala)
        : 70;

    const volumeFinal = Math.max(0, Math.min(1, volUser / 100));

    speechSynthesis.cancel();

    const msg = new SpeechSynthesisUtterance(texto);
    msg.lang   = cfg.lang;
    msg.rate   = cfg.rate;
    msg.pitch  = cfg.pitch;
    msg.volume = volumeFinal;

    const vozSelecionada = escolherVozPorTipo(cfg.tipo);
    if (vozSelecionada) msg.voice = vozSelecionada;

    console.log(
        `%c[FALA] Volume aplicado: ${volUser}% (final=${volumeFinal})`,
        "color:#22c55e;font-weight:bold;"
    );

    speechSynthesis.speak(msg);
};
*/


// ============================================================
// AUXÍLIO: VOZ (A/B/C) - ajustes de tom/velocidade + voz real
// ============================================================
function getConfigVoz() {
    const dados = getOperadorLogado() || {};
    const tipo = dados.pref_voz || "C"; // C = neutra padrão

    switch (tipo) {
        case "A": // Masculina corporativa
            return { tipo: "A", rate: 1.0, pitch: 0.9, lang: "pt-BR" };
        case "B": // Feminina corporativa
            return { tipo: "B", rate: 1.05, pitch: 1.2, lang: "pt-BR" };
        case "C": // Neutra / robótica
        default:
            return { tipo: "C", rate: 1.1, pitch: 1.0, lang: "pt-BR" };
    }
}
/*
function falarMensagem(texto) {
    try {
        if (!("speechSynthesis" in window)) return;

        const cfg = getConfigVoz();
        const msg = new SpeechSynthesisUtterance(texto);
*
        msg.lang  = cfg.lang;
        msg.rate  = cfg.rate;
        msg.pitch = cfg.pitch;
*

const dados = getOperadorLogado() || {};
const volUser = parseInt(dados.pref_volume_fala ?? 7);

// converte 0–10 → 0.0–1.0
const volumeFinal = Math.max(0, Math.min(1, volUser / 10));

msg.rate   = cfg.rate;
msg.pitch  = cfg.pitch;
msg.volume = volumeFinal;

        const vozSelecionada = escolherVozPorTipo(cfg.tipo);
        if (vozSelecionada) {
            msg.voice = vozSelecionada;
        }

        speechSynthesis.speak(msg);
    } catch (e) {
        console.warn("[PREFERENCIAS] Falha ao falar mensagem:", e);
    }
}


const sliderVolume = document.getElementById("prefVolumeFala");
const labelVolume  = document.getElementById("labelVolumeFala");

if (sliderVolume && labelVolume) {
    labelVolume.textContent = sliderVolume.value;

    sliderVolume.addEventListener("input", () => {
        labelVolume.textContent = sliderVolume.value;
    });
}
*/

function falarMensagem(texto) {
    if (!("speechSynthesis" in window)) return;

    const dados = getOperadorLogado() || {};
    const cfg   = getConfigVoz();

    const volUser = Number.isFinite(Number(dados.pref_volume_fala))
    ? Number(dados.pref_volume_fala)
    : 70;

    const volumeFinal = Math.max(0, Math.min(1, volUser / 100));

    // FORÇA APLICAR NOVO VOLUME
    speechSynthesis.cancel();

    const msg = new SpeechSynthesisUtterance(texto);
    msg.lang   = cfg.lang;
    msg.rate   = cfg.rate;
    msg.pitch  = cfg.pitch;
    msg.volume = volumeFinal;

    const vozSelecionada = escolherVozPorTipo(cfg.tipo);
    if (vozSelecionada) msg.voice = vozSelecionada;

console.log(
    `%c[FALA EVENTO] Volume aplicado: ${volUser}% (final=${volumeFinal})`,
    "color:#f59e0b;font-weight:bold;"
);


    speechSynthesis.speak(msg);

}


// ============================================================
// AUXÍLIO: BEEPS VIA WebAudio (sem .mp3)
// ============================================================
let audioCtx = null;

function tocarBeep(evento) {
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;

        if (!audioCtx) {
            audioCtx = new Ctx();
        }

        const ctx   = audioCtx;
        const osc   = ctx.createOscillator();
        const gain  = ctx.createGain();

        osc.connect(gain);
        gain.connect(ctx.destination);

        let freq = 700;
        let dur  = 0.18; // segundos

        switch (evento) {
            case "virou_primeiro":
                freq = 1000;
                dur  = 0.25;
                break;
            case "vaga_abriu":
                freq = 880;
                dur  = 0.22;
                break;
            case "alguem_entrou_pausa":
                freq = 520;
                dur  = 0.16;
                break;
            case "posicao_mudou":
                freq = 600;
                dur  = 0.16;
                break;
            case "entrou_fila":
                freq = 500;
                dur  = 0.16;
                break;
            case "saiu_fila":
                freq = 430;
                dur  = 0.14;
                break;
            case "saiu_pausa":
                freq = 480;
                dur  = 0.16;
                break;
            case "limite_pausa":
                freq = 350;
                dur  = 0.35;
                break;
            case "teste_audio":
            default:
                freq = 650;
                dur  = 0.18;
                break;
        }

        osc.frequency.value = freq;
       const dados = getOperadorLogado() || {};
const volUser = Number.isFinite(Number(dados.pref_volume_fala))
    ? Number(dados.pref_volume_fala)
    : 70;

const volumeFinal = Math.max(0, Math.min(1, volUser / 100));

// limite seguro para não estourar
gain.gain.value = volumeFinal * 0.25;


        const now = ctx.currentTime;
        osc.start(now);
        osc.stop(now + dur);

    } catch (e) {
        console.warn("[PREFERENCIAS] Falha ao tocar beep:", e);
    }
}

// ------------------------------------------------------------
// Restaurar painel principal
// ------------------------------------------------------------
window.restaurarPainelPrincipal = function () {
    const topo   = document.querySelector(".painel-topo");
    const dash   = document.querySelector(".painel-dashboard");
    const cardEq = document.querySelector(".card-participantes");
    const extra  = document.getElementById("conteudoExtra");

    if (topo)   topo.style.display   = "";
    if (dash)   dash.style.display   = "";
    if (cardEq) cardEq.style.display = "";

    if (extra) {
        extra.classList.add("hidden");
        extra.innerHTML = "";
    }
};
// ------------------------------------------------------------
// ABRIR PREFERÊNCIAS
// ------------------------------------------------------------
window.abrirPreferencias = function () {

    const dados = getOperadorLogado();
    if (!dados) {
        alert("Sessão expirada. Faça login novamente.");
        window.location.href = "../login/login.php";
        return;
    }

    document.querySelector(".painel-topo").style.display = "none";
    document.querySelector(".painel-dashboard").style.display = "none";
    document.querySelector(".card-participantes").style.display = "none";

    const extra = document.getElementById("conteudoExtra");
    extra.classList.remove("hidden");

    const prefToast       = dados.pref_toast       ?? 1;
    const prefFala        = dados.pref_fala        ?? 0;
    const prefAudio       = dados.pref_audio       ?? 1;
    const prefNotif       = dados.pref_notif       ?? 0;
    const prefVoz         = dados.pref_voz         || "C";
    const prefAlvoAlertas = dados.pref_alerta_alvo || "todos";

    extra.innerHTML = `
        <div class="prefs-container fade-in">

            <div class="prefs-header">
                <h2><i class="fa-solid fa-sliders"></i> Preferências do Operador</h2>
                <p>Ajuste alertas visuais, sons, fala e notificações.</p>
            </div>

            <div class="prefs-grid">

                <!-- Toast -->
                <div class="pref-row">
                    <div class="pref-info">
                        <strong>Toasts na tela</strong>
                        <span>Exibe pequenos avisos na parte superior.</span>
                    </div>
                    <div class="pref-switch">
                        <label class="switch">
                            <input type="checkbox" id="prefToast" ${prefToast ? "checked" : ""}>
                            <span class="slider"></span>
                        </label>
                        <button class="btn-testar-pref" onclick="testarToast()">Teste</button>
                    </div>
                </div>

                <!-- Fala -->
                <div class="pref-row">
                    <div class="pref-info">
                        <strong>Fala (voz sintetizada)</strong>
                        <span>O navegador pode narrar alertas importantes.</span>
                    </div>
                    <div class="pref-switch">
                        <label class="switch">
                            <input type="checkbox" id="prefFala" ${prefFala ? "checked" : ""}>
                            <span class="slider"></span>
                        </label>
                        <button class="btn-testar-pref" onclick="testarFala()">Teste</button>
                    </div>
                </div>

                <!-- Voz -->
                <div class="pref-row">
                    <div class="pref-info">
                        <strong>Estilo da voz</strong>
                        <span>Escolha o tipo de voz para as mensagens faladas.</span>
                    </div>
                    <div class="pref-switch">
                        <select id="prefVoz" class="select-pref">
                            <option value="C" ${prefVoz === "C" ? "selected" : ""}>C - Neutra</option>
                            <option value="A" ${prefVoz === "A" ? "selected" : ""}>A - Masculina</option>
                            <option value="B" ${prefVoz === "B" ? "selected" : ""}>B - Feminina</option>
                        </select>
                        <button class="btn-testar-pref" onclick="testarFala()">Ouvir</button>
                    </div>
                </div>

                <!-- Volume -->
                <div class="pref-row">
                    <div class="pref-info">
                        <strong>Volume da fala</strong>
                        <span>0 = mudo • 100 = máximo</span>
                    </div>
                    <div class="pref-switch" style="flex-direction:column; align-items:stretch;">
                        <input type="range" id="prefVolumeFala" min="0" max="100" step="10"
                               value="${dados.pref_volume_fala ?? 70}">
                        <div style="display:flex; justify-content:space-between; font-size:12px; opacity:.7;">
                            <span>0</span>
                            <span id="labelVolumeFala">${dados.pref_volume_fala ?? 70}</span>
                            <span>100</span>
                        </div>
                        <button class="btn-testar-pref" onclick="testarFala()">Ouvir</button>
                    </div>
                </div>

                <!-- Alvo -->
                <div class="pref-row">
                    <div class="pref-info">
                        <strong>Alvo de fala / notificação</strong>
                        <span>Defina o alcance dos alertas.</span>
                    </div>
                    <div class="pref-switch">
                        <select id="prefAlvoAlertas" class="select-pref">
                            <option value="todos"  ${prefAlvoAlertas === "todos"  ? "selected" : ""}>Todos</option>
                            <option value="meu"    ${prefAlvoAlertas === "meu"    ? "selected" : ""}>Somente meu</option>
                            <option value="nenhum" ${prefAlvoAlertas === "nenhum" ? "selected" : ""}>Nenhum</option>
                        </select>
                    </div>
                </div>

                <!-- Som -->
                <div class="pref-row">
                    <div class="pref-info">
                        <strong>Som de alerta</strong>
                        <span>Beep gerado pelo navegador.</span>
                    </div>
                    <div class="pref-switch">
                        <label class="switch">
                            <input type="checkbox" id="prefAudio" ${prefAudio ? "checked" : ""}>
                            <span class="slider"></span>
                        </label>
                        <button class="btn-testar-pref" onclick="testarAudio()">Teste</button>
                    </div>
                </div>

                <!-- Notificação -->
                <div class="pref-row">
                    <div class="pref-info">
                        <strong>Notificação do Windows</strong>
                        <span>Mesmo em segundo plano.</span>
                    </div>
                    <div class="pref-switch">
                        <label class="switch">
                            <input type="checkbox" id="prefNotif" ${prefNotif ? "checked" : ""}>
                            <span class="slider"></span>
                        </label>
                        <button class="btn-testar-pref" onclick="testarNotif()">Teste</button>
                    </div>
                </div>

               <!-- WhatsApp -->
<div class="pref-row">
    <div class="pref-info">
        <strong>Notificação por WhatsApp</strong>
        <span>Alerta ao exceder o limite de pausa.</span>
    </div>

    <div class="pref-switch" style="flex-direction:column; gap:8px;">
        <label class="switch">
            <input type="checkbox" id="prefWhatsappHabilitado"
                   ${dados.whatsapp_habilitado == 1 ? "checked" : ""}>
            <span class="slider"></span>
        </label>

        <input type="text"
               id="prefWhatsappNumero"
               value="${dados.whatsapp_numero ?? ""}"
               placeholder="Ex: 556533390800">

        <button class="btn-testar-pref"
                onclick="enviarTesteWhatsapp()">
            📲 Enviar teste
        </button>

        <small style="opacity:.7">
            Somente números com DDD
        </small>
    </div>
</div>


            </div> <!-- prefs-grid -->

            <div class="prefs-actions">
                <button class="btn-pref-voltar" onclick="restaurarPainelPrincipal()">
                    <i class="fa-solid fa-arrow-left"></i> Voltar
                </button>
                <button class="btn-pref-salvar" id="btnSalvarPrefs">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar
                </button>
            </div>

            <div id="toastContainer"></div>
        </div>
    `;

    document.getElementById("btnSalvarPrefs")
        .addEventListener("click", salvarPreferencias);

    // -------------------------------
    // CONTROLE DE VOLUME
    // -------------------------------
    const slider = document.getElementById("prefVolumeFala");
    const label  = document.getElementById("labelVolumeFala");

    if (slider && label) {
        label.textContent = slider.value;
        slider.addEventListener("input", () => {
            label.textContent = slider.value;
        });
    }
};



// ------------------------------------------------------------
// SALVAR PREFERÊNCIAS
// ------------------------------------------------------------
async function salvarPreferencias() {

    const dados = getOperadorLogado();
    if (!dados) return;

    // ===============================
    // CAPTURA DOS CAMPOS
    // ===============================
    const pref_toast       = document.getElementById("prefToast")?.checked ? 1 : 0;
    const pref_fala        = document.getElementById("prefFala")?.checked  ? 1 : 0;
    const pref_audio       = document.getElementById("prefAudio")?.checked ? 1 : 0;
    const pref_notif       = document.getElementById("prefNotif")?.checked ? 1 : 0;
    const pref_voz         = document.getElementById("prefVoz")?.value || "C";
    const pref_alerta_alvo = document.getElementById("prefAlvoAlertas")?.value || "todos";

    const pref_volume_fala = parseInt(
        document.getElementById("prefVolumeFala")?.value ?? 70,
        10
    );

    const whatsapp_habilitado =
        document.getElementById("prefWhatsappHabilitado")?.checked ? 1 : 0;

    let whatsapp_numero =
        document.getElementById("prefWhatsappNumero")?.value.trim() || null;

    // 🔒 Se WhatsApp estiver desativado, não salva número
    if (!whatsapp_habilitado) {
        whatsapp_numero = null;
    }

    // 🔒 Sanitização extra (frontend)
    if (whatsapp_numero) {
        whatsapp_numero = whatsapp_numero.replace(/\D/g, "");
    }

    // ===============================
    // ENVIO PARA BACKEND
    // ===============================
    try {
        const resp = await fetch("../backend/salvar_preferencias.php", {
            method: "POST",
            body: new URLSearchParams({
                id: dados.id,
                pref_toast,
                pref_fala,
                pref_audio,
                pref_notif,
                pref_voz,
                pref_alerta_alvo,
                pref_volume_fala,
                whatsapp_habilitado,
                whatsapp_numero
            })
        });

        const r = await resp.json();

        if (!r.success) {
            console.error("[PREFERENCIAS] Erro ao salvar:", r);
            testarToast("❌ Erro ao salvar: " + (r.erro || "desconhecido"));
            return;
        }

        // ===============================
        // SINCRONIZAÇÃO LOCAL (MEMÓRIA)
        // ===============================

        dados.pref_toast       = pref_toast;
        dados.pref_fala        = pref_fala;
        dados.pref_audio       = pref_audio;
        dados.pref_notif       = pref_notif;
        dados.pref_voz         = pref_voz;
        dados.pref_alerta_alvo = pref_alerta_alvo;

        // volume SEMPRE vem confiável do backend
        dados.pref_volume_fala = Number(r.pref_volume_fala ?? pref_volume_fala);

        // WhatsApp (confere retorno do backend)
        dados.whatsapp_habilitado = Number(
            r.whatsapp_habilitado ?? whatsapp_habilitado
        ) === 1 ? 1 : 0;

        dados.whatsapp_numero = r.whatsapp_numero ?? whatsapp_numero;

        // Persiste operador atualizado
        localStorage.setItem("tga_operador", JSON.stringify(dados));

        // ===============================
        // EXPORTA PARA SISTEMA DE ALERTAS
        // ===============================
        const prefsAlertas = {
            // eventos (todos ativos por enquanto)
            entrou_fila:         true,
            saiu_fila:           true,
            saiu_pausa:          true,
            virou_primeiro:      true,
            posicao_mudou:       true,
            vaga_abriu:          true,
            alguem_entrou_pausa: true,
            teste:               true,

            // globais
            habilitarSom:       pref_audio === 1,
            habilitarToast:     pref_toast === 1,
            habilitarNavegador: pref_notif === 1
        };

        // disponível em memória
        window.cpPreferenciasAlertas = prefsAlertas;

        // persistido por operador
        try {
            const key = `cp_prefs_alertas_${dados.id}`;
            localStorage.setItem(key, JSON.stringify(prefsAlertas));
        } catch (e) {
            console.warn(
                "[PREFERENCIAS] Falha ao salvar prefs de alertas no localStorage:",
                e
            );
        }

        // ===============================
        // FEEDBACK VISUAL + RELOAD
        // ===============================
        testarToast("✔ Preferências salvas!");

        console.log(
            "%c[PREFERENCIAS] Preferências salvas. Recarregando para aplicar volume.",
            "color:#22c55e;font-weight:bold;"
        );

        // flag para reabrir preferências após reload
        sessionStorage.setItem("cp_pref_salva_agora", "1");

        // pequeno delay para exibir toast
        setTimeout(() => {
            location.reload(true);
        }, 600);

    } catch (e) {
        console.error("[PREFERENCIAS] Falha de comunicação:", e);
        testarToast("❌ Falha de comunicação!");
    }
}

// ============================================================
//  FASE 9 — DISPARADOR BASE DE ALERTAS
//   (não usado diretamente pelo painel; quem chama é Fase 11,
//    se quiser reaproveitar voz/toast/notif daqui)
// ============================================================
window.dispararAlertasBASE = function (evento, extra = {}) {

    const dados = getOperadorLogado();
    if (!dados) return;

    const usarToast = dados.pref_toast == 1;
    const usarFala  = dados.pref_fala  == 1;
    const usarAudio = dados.pref_audio == 1;
    const usarNotif = dados.pref_notif == 1;

    const modoAlvo  = dados.pref_alerta_alvo || "todos"; // 'todos' | 'meu' | 'nenhum'
    const alvo      = extra.alvo || "meu";               // 'meu' | 'outro'

    let mensagem = "";

    switch (evento) {

        case "entrou_fila":
            mensagem = "Você entrou na fila de pausa.";
            break;

        case "saiu_fila":
            mensagem = "Você saiu da fila de pausa.";
            break;

        case "virou_primeiro":
            mensagem = "Você é o primeiro da fila.";
            break;

        case "posicao_mudou":
            if (extra && extra.posicao) {
                mensagem = `Sua posição na fila mudou para ${extra.posicao}º.`;
            } else {
                mensagem = "Sua posição na fila mudou.";
            }
            break;

        case "vaga_abriu":
            mensagem = "Vaga disponível para pausar.";
            break;

        case "alguem_entrou_pausa":
            mensagem = `O operador ${extra?.nome || "desconhecido"} entrou em pausa.`;
            break;

        case "saiu_pausa":
            mensagem = "Você saiu da pausa.";
            break;

        case "limite_pausa":
            mensagem = "Você passou do limite permitido de pausa.";
            break;

        default:
            mensagem = "Atualização no controle de pausa.";
    }

    // Toast e beep
    if (usarToast) {
        testarToast("🔔 " + mensagem);
    }
    if (usarAudio) {
        tocarBeep(evento);
    }

    // ALCANCE para fala + notificação
    let permitirFala  = usarFala;
    let permitirNotif = usarNotif;

    if (modoAlvo === "nenhum") {
        permitirFala  = false;
        permitirNotif = false;
    } else if (modoAlvo === "meu" && alvo === "outro") {
        permitirFala  = false;
        permitirNotif = false;
    }

    // FALA
    if (permitirFala) {
        falarMensagem(mensagem);
    }

    // NOTIFICAÇÃO DO WINDOWS
    if (permitirNotif && "Notification" in window) {
        if (Notification.permission === "granted") {
            new Notification("Controle de Pausa", { body: mensagem });
        } else if (Notification.permission === "default") {
            Notification.requestPermission();
        }
    }
};

// ------------------------------------------------------------
// TESTES INDIVIDUAIS
// ------------------------------------------------------------
window.testarToast = function (msg = "Exemplo de TOAST") {
    const box = document.getElementById("toastContainer");
    if (!box) return;

    const toast = document.createElement("div");
    toast.className = "toast-msg";
    toast.textContent = msg;

    box.appendChild(toast);

    setTimeout(() => toast.classList.add("show"), 50);
    setTimeout(() => toast.classList.remove("show"), 3000);
    setTimeout(() => toast.remove(), 3500);
};
/*
window.testarFala = function () {
    falarMensagem("Teste de voz no modo atual de preferência.");
};*/
window.testarFala = function () {

    const dados = getOperadorLogado() || {};
    /*const vol = Number(
        document.getElementById("prefVolumeFala")?.value ??
        dados.pref_volume_fala ??
        70
    );*/
const slider = document.getElementById("prefVolumeFala");

const vol = Number.isFinite(Number(slider?.value))
    ? Number(slider.value)
    : Number.isFinite(Number(dados.pref_volume_fala))
        ? Number(dados.pref_volume_fala)
        : 70;


    const volumeFinal = Math.max(0, Math.min(1, vol / 100));

    console.log(
        `%c[TESTE VOZ] Volume configurado: ${vol}% | volumeFinal=${volumeFinal}`,
        "color:#22c55e;font-weight:bold;"
    );

    testarToast(`🔊 Teste de voz — Volume: ${vol}%`);

    // força reaplicação
    speechSynthesis.cancel();

    falarMensagem(`Teste de voz com volume em ${vol} por cento.`);
};


window.testarAudio = function () {
    tocarBeep("teste_audio");
};

window.testarNotif = function () {
    if (!("Notification" in window)) {
        testarToast("❌ Navegador sem suporte");
        return;
    }

    if (Notification.permission === "granted") {
        new Notification("🔔 Teste de notificação", {
            body: "Notificações do controle de pausa estão ativas."
        });
    } else {
        Notification.requestPermission().then(p => {
            if (p === "granted") {
                new Notification("🔔 Notificações ativadas!", {
                    body: "O controle de pausa poderá exibir alertas no Windows."
                });
            } else {
                testarToast("Permissão de notificação negada.");
            }
        });
    }
};


// ------------------------------------------------------------
// TESTE DE WHATSAPP
// ------------------------------------------------------------
window.enviarTesteWhatsapp = async function () {

    const dados = getOperadorLogado();
    if (!dados) {
        testarToast("Sessão expirada.");
        return;
    }

    if (dados.whatsapp_habilitado != 1 || !dados.whatsapp_numero) {
        testarToast("⚠️ WhatsApp não configurado.");
        return;
    }

    testarToast("📲 Enviando mensagem de teste...");

    try {
        const resp = await fetch("../backend/whatsapp/enviar_teste.php", {
            method: "POST",
            body: new URLSearchParams({
                operador_id: dados.id
            })
        });

        const r = await resp.json();

        if (!r.success) {
            testarToast("❌ Falha no envio do teste.");
            console.error("[WHATSAPP TESTE]", r);
            return;
        }

        testarToast("✔ Mensagem de teste enviada com sucesso!");

    } catch (e) {
        console.error("[WHATSAPP TESTE]", e);
        testarToast("❌ Erro de comunicação.");
    }
};


// ============================================================
// ABERTURA AUTOMÁTICA APÓS SALVAR PREFERÊNCIAS
// ============================================================
document.addEventListener("DOMContentLoaded", () => {

    const flag = sessionStorage.getItem("cp_pref_salva_agora");

    if (flag === "1") {
        sessionStorage.removeItem("cp_pref_salva_agora");

        console.log(
            "%c[PREFERENCIAS] Reabertura automática após salvar",
            "color:#0ea5e9;font-weight:bold;"
        );

        // espera painel carregar completamente
        setTimeout(() => {
            if (typeof abrirPreferencias === "function") {
                abrirPreferencias();



                // aviso visual
                setTimeout(() => {
                    testarToast("🔊 Preferências atualizadas. Clique em OUVIR para confirmar o volume.");
                }, 300);
            }
        }, 400);
    }
});
