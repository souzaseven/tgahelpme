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
}

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

function falarMensagem(texto) {
    try {
        if (!("speechSynthesis" in window)) return;

        const cfg = getConfigVoz();
        const msg = new SpeechSynthesisUtterance(texto);

        msg.lang  = cfg.lang;
        msg.rate  = cfg.rate;
        msg.pitch = cfg.pitch;

        const vozSelecionada = escolherVozPorTipo(cfg.tipo);
        if (vozSelecionada) {
            msg.voice = vozSelecionada;
        }

        speechSynthesis.speak(msg);
    } catch (e) {
        console.warn("[PREFERENCIAS] Falha ao falar mensagem:", e);
    }
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
        gain.gain.value     = 0.15;

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

    const prefToast       = dados.pref_toast        ?? 1;
    const prefFala        = dados.pref_fala         ?? 0;
    const prefAudio       = dados.pref_audio        ?? 1;
    const prefNotif       = dados.pref_notif        ?? 0;
    const prefVoz         = dados.pref_voz          || "C";
    const prefAlvoAlertas = dados.pref_alerta_alvo  || "todos"; // 'todos' | 'meu' | 'nenhum'

    extra.innerHTML = `
        <div class="prefs-container fade-in">
            <div class="prefs-header">
                <h2><i class="fa-solid fa-sliders"></i> Preferências do Operador</h2>
                <p>Ajuste alertas visuais, sons, fala e notificações.</p>
            </div>

            <div class="prefs-grid">

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

                <div class="pref-row">
                    <div class="pref-info">
                        <strong>Estilo da voz</strong>
                        <span>Escolha o tipo de voz para as mensagens faladas.</span>
                    </div>
                    <div class="pref-switch">
                        <select id="prefVoz" class="select-pref">
                            <option value="C" ${prefVoz === "C" ? "selected" : ""}>C - Voz neutra / robótica</option>
                            <option value="A" ${prefVoz === "A" ? "selected" : ""}>A - Voz masculina corporativa</option>
                            <option value="B" ${prefVoz === "B" ? "selected" : ""}>B - Voz feminina corporativa</option>
                        </select>
                        <button class="btn-testar-pref" onclick="testarFala()">Ouvir</button>
                    </div>
                </div>

                <div class="pref-row">
                    <div class="pref-info">
                        <strong>Alvo de fala / notificação</strong>
                        <span>Escolha se quer falar/notificar só seus eventos ou também dos outros.</span>
                    </div>
                    <div class="pref-switch">
                        <select id="prefAlvoAlertas" class="select-pref">
                            <option value="todos"  ${prefAlvoAlertas === "todos"  ? "selected" : ""}>Todos (meu e dos outros)</option>
                            <option value="meu"    ${prefAlvoAlertas === "meu"    ? "selected" : ""}>Apenas meu status</option>
                            <option value="nenhum" ${prefAlvoAlertas === "nenhum" ? "selected" : ""}>Nenhum (sem voz/notificação)</option>
                        </select>
                    </div>
                </div>

                <div class="pref-row">
                    <div class="pref-info">
                        <strong>Som de alerta</strong>
                        <span>Toca um beep curto gerado pelo navegador.</span>
                    </div>
                    <div class="pref-switch">
                        <label class="switch">
                            <input type="checkbox" id="prefAudio" ${prefAudio ? "checked" : ""}>
                            <span class="slider"></span>
                        </label>
                        <button class="btn-testar-pref" onclick="testarAudio()">Teste</button>
                    </div>
                </div>

                <div class="pref-row">
                    <div class="pref-info">
                        <strong>Notificação do Windows</strong>
                        <span>Notificações nativas do sistema, mesmo em segundo plano.</span>
                    </div>
                    <div class="pref-switch">
                        <label class="switch">
                            <input type="checkbox" id="prefNotif" ${prefNotif ? "checked" : ""}>
                            <span class="slider"></span>
                        </label>
                        <button class="btn-testar-pref" onclick="testarNotif()">Teste</button>
                    </div>
                </div>

            </div>

            <div class="prefs-actions">
                <button class="btn-pref-voltar" onclick="restaurarPainelPrincipal()">
                    <i class="fa-solid fa-arrow-left"></i> Voltar
                </button>
                <button class="btn-pref-salvar" id="btnSalvarPrefs">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar
                </button>
            </div>
        </div>

        <div id="toastContainer"></div>
    `;

    document.getElementById("btnSalvarPrefs").addEventListener("click", salvarPreferencias);
};

// ------------------------------------------------------------
// SALVAR PREFERÊNCIAS
// ------------------------------------------------------------
async function salvarPreferencias() {

    const dados = getOperadorLogado();
    if (!dados) return;

    const pref_toast       = document.getElementById("prefToast").checked ? 1 : 0;
    const pref_fala        = document.getElementById("prefFala").checked  ? 1 : 0;
    const pref_audio       = document.getElementById("prefAudio").checked ? 1 : 0;
    const pref_notif       = document.getElementById("prefNotif").checked ? 1 : 0;
    const pref_voz         = document.getElementById("prefVoz")?.value || "C";
    const pref_alerta_alvo = document.getElementById("prefAlvoAlertas")?.value || "todos";

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
                pref_alerta_alvo
            })
        });

        const r = await resp.json();

        if (!r.success) {
            testarToast("❌ Erro ao salvar");
            return;
        }

        // Atualiza objeto do operador logado
        dados.pref_toast       = pref_toast;
        dados.pref_fala        = pref_fala;
        dados.pref_audio       = pref_audio;
        dados.pref_notif       = pref_notif;
        dados.pref_voz         = pref_voz;
        dados.pref_alerta_alvo = pref_alerta_alvo;

        localStorage.setItem("tga_operador", JSON.stringify(dados));

        // ====================================================
        // Exporta preferências para o notificacoes.js (Fase 11)
        // ====================================================
        const prefsAlertas = {
            // por evento (aqui estão todos ligados; se quiser, depois pode granular)
            entrou_fila:          true,
            saiu_fila:            true,
            saiu_pausa:           true,
            virou_primeiro:       true,
            posicao_mudou:        true,
            vaga_abriu:           true,
            alguem_entrou_pausa:  true,
            teste:                true,

            // globais (som/toast/notificação)
            habilitarSom:       pref_audio == 1,
            habilitarToast:     pref_toast == 1,
            habilitarNavegador: pref_notif == 1
        };

        // Disponível em memória
        window.cpPreferenciasAlertas = prefsAlertas;

        // Persistido por operador (lido em notificacoes.js)
        try {
            const key = `cp_prefs_alertas_${dados.id}`;
            localStorage.setItem(key, JSON.stringify(prefsAlertas));
        } catch (e) {
            console.warn("[PREFERENCIAS] Erro ao salvar prefs de alertas no localStorage:", e);
        }

        testarToast("✔ Preferências salvas!");

    } catch (e) {
        console.error("[PREFERENCIAS]", e);
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

window.testarFala = function () {
    falarMensagem("Teste de voz no modo atual de preferência.");
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
