// painel/js/bootstrap.js - Inicialização do Painel de Pausas v0.004

document.addEventListener("DOMContentLoaded", () => {

    console.log("%c[PAINEL] bootstrap iniciado...", "color:#38bdf8;font-weight:bold;");

    const app = document.getElementById("app");
    if (!app) {
        console.error("[PAINEL] Elemento #app não encontrado.");
        return;
    }

    // ------------------------------
    // 1) Verificar usuário logado
    // ------------------------------
    const dadosStr = localStorage.getItem("tga_operador");
    if (!dadosStr) {
        console.warn("[PAINEL] Nenhum operador logado. Redirecionando para o login...");
        irParaLogin();
        return;
    }

    let dados;
    try {
        dados = JSON.parse(dadosStr);
    } catch (e) {
        console.error("[PAINEL] Erro ao ler tga_operador:", e);
        irParaLogin();
        return;
    }

    const nomeOperador = dados.operador || "Operador";
    const nomeEquipe   = dados.equipe   || "Equipe";

iniciarBotoesOperador(dados);


    // ------------------------------
    // 2) Montar HTML do painel
    // ------------------------------
    app.innerHTML = `
        <div class="painel-container">

            <header class="painel-topo">
                <div class="titulo-sistema">
                    <h1><i class="fas fa-clock"></i> Controle de Pausas</h1>
                    <p>Equipes Matriz - ${window.SISTEMA_VERSAO || "v0.004"}</p>
                </div>

                <div class="info-operador">
                    <div class="linha-op">
                        <i class="fas fa-user-circle"></i>
                        <span id="usuarioLogado">
                            ${nomeOperador} • ${nomeEquipe}
                        </span>
                    </div>
                    <button id="btnTrocarUsuario" class="btn-trocar">
                        <i class="fas fa-right-left"></i> Trocar usuário
                    </button>
                </div>
            </header>

            <section class="painel-status">
                <div class="status-item">
                    <span class="status-bol status-online"></span>
                    <span>Sistema Online</span>
                </div>
                <div class="status-item">
                    <span class="status-bol status-online"></span>
                    <span>Banco de Dados Conectado</span>
                </div>
                <div class="status-item">
                    <span class="status-bol status-online"></span>
                    <span id="resumoEquipes">Carregando resumo...</span>
                </div>
            </section>

            <main class="painel-dashboard">
                <div class="card card-pausas">
                    <h2><i class="fas fa-coffee"></i> Pausas Ativas</h2>
                    <div class="card-meta">
                        <span id="contador-pausa" class="contador">0</span>
                        <span>pessoas em pausa</span>
                    </div>
                    <div id="listaPausas" class="lista-generica">
                        <div class="lista-vazia">
                            Nenhum operador em pausa no momento.
                        </div>
                    </div>
                </div>

                <div class="card card-fila">
                    <h2><i class="fas fa-clock"></i> Fila de Espera</h2>
                    <div class="card-meta">
                        <span id="contador-fila" class="contador">0</span>
                        <span>pessoas na fila</span>
                    </div>
                    <div id="listaFila" class="lista-generica">
                        <div class="lista-vazia">
                            Nenhum operador na fila de espera.
                        </div>
                    </div>
                </div>

                <div class="card card-participantes">
                    <div class="card-header-flex">

                        <div class="grupo-esquerda">
                            <span><i class="fas fa-users"></i> Participantes</span>

                            <span id="legendaStatus" class="legenda-inline">
                                <span class="l-item">
                                    <span class="barra barra-ativo"></span> 🟢 Ativo
                                </span>
                                <span class="l-item">
                                    <span class="barra barra-espera"></span> ⏳ Espera
                                </span>
                                <span class="l-item">
                                    <span class="barra barra-pausa"></span> ☕ Pausa
                                </span>
                                <span class="l-item">
                                    <span class="barra barra-expirada"></span> 🔴 Expirada
                                </span>
                            </span>
                        </div>

                        <span id="hud-operador" class="hud-operador">
                            Carregando equipe...
                        </span>
                    </div>
<div id="acoesOperador" class="acoes-operador">
    <button id="btnEntrarFila" class="acao-btn btn-primario hidden">
        <i class="fas fa-sign-in-alt"></i> Entrar na fila
    </button>

    <button id="btnSairFila" class="acao-btn btn-perigo hidden">
        <i class="fas fa-times"></i> Sair da fila
    </button>

    <button id="btnEntrarPausa" class="acao-btn btn-primario hidden">
        <i class="fas fa-coffee"></i> Entrar em pausa
    </button>

    <button id="btnOpcoesPrimeiro" class="acao-btn btn-secundario hidden">
        <i class="fas fa-ellipsis-h"></i> Opções
    </button>
</div>


                    <div id="listaParticipantes" class="lista-participantes">
                        <div class="lista-vazia">
                            Carregando dados dos operadores...
                        </div>
                    </div>
                </div>
            </main>
        </div>
    `;

    // ------------------------------
    // 3) Eventos de topo + sidebar
    // ------------------------------

    // Trocar usuário (topo)
    const btnTrocar = document.getElementById("btnTrocarUsuario");
    if (btnTrocar) {
        btnTrocar.addEventListener("click", () => {
            localStorage.removeItem("tga_operador");
            irParaLogin();
        });
    }

    // Botão INÍCIO da sidebar
    const btnSidebarInicio = document.getElementById("btnSidebarInicio");
    if (btnSidebarInicio) {
        btnSidebarInicio.addEventListener("click", () => {
            const base = window.SISTEMA_CONFIG?.caminhoBase || "./";
            window.location.href = base + "index.php";
        });
    }

    // Botão "Todas as equipes"
    const btnSidebarEquipes = document.getElementById("btnSidebarEquipes");
    if (btnSidebarEquipes) {
        btnSidebarEquipes.addEventListener("click", () => {
            abrirTelaTodasEquipes();
        });
    }

    // Botão "Preferências"
    const btnSidebarPrefs = document.getElementById("btnSidebarPrefs");
    if (btnSidebarPrefs) {
        btnSidebarPrefs.addEventListener("click", () => {
            abrirModalPreferencias();
        });
    }

    // Botão SAIR da sidebar
    const btnSidebarSair = document.getElementById("btnSidebarSair");
    if (btnSidebarSair) {
        btnSidebarSair.addEventListener("click", () => {
            localStorage.removeItem("tga_operador");
            irParaLogin();
        });
    }

    // Toggle da sidebar (mostrar/ocultar)
    const btnToggleSidebar = document.getElementById("btnToggleSidebar");
    if (btnToggleSidebar) {
        btnToggleSidebar.addEventListener("click", () => {
            document.body.classList.toggle("sidebar-fechada");
            const icone = btnToggleSidebar.querySelector("i");
            if (icone) {
                if (document.body.classList.contains("sidebar-fechada")) {
                    icone.classList.remove("fa-angle-left");
                    icone.classList.add("fa-angle-right");
                } else {
                    icone.classList.remove("fa-angle-right");
                    icone.classList.add("fa-angle-left");
                }
            }
        });
    }

    // ------------------------------
    // 4) HUD + resumo
    // ------------------------------
    const hud = document.getElementById("hud-operador");
    if (hud) {
        hud.textContent = `Equipe: ${nomeEquipe}`;
    }

    const resumo = document.getElementById("resumoEquipes");
    if (resumo) {
        resumo.textContent = `Operador logado: ${nomeOperador} (${nomeEquipe})`;
    }

    // ------------------------------
    // 5) Carregar operadores da equipe do usuário
    // ------------------------------
    carregarParticipantesEquipe(nomeEquipe, nomeOperador);

    // ------------------------------
    // 6) Inicializar Preferências (se existir modal)
    // ------------------------------
    inicializarPreferencias();

    console.log("[PAINEL] Painel montado para:", nomeOperador, "(", nomeEquipe, ")");
});

// ============================================
// Funções auxiliares
// ============================================
function irParaLogin() {
    const caminhoLogin = (window.SISTEMA_CONFIG?.caminhos?.login || "./login/") + "login.php";
    window.location.href = caminhoLogin;
}

// Carrega operadores da equipe do usuário logado
function carregarParticipantesEquipe(nomeEquipe, nomeOperador) {
    const lista  = document.getElementById("listaParticipantes");
    const hud    = document.getElementById("hud-operador");
    const resumo = document.getElementById("resumoEquipes");

    if (!lista) return;

    const baseBackend = window.SISTEMA_CONFIG?.caminhos?.backend || "./backend/";
    const url = baseBackend + "listar_equipes_login.php";

    fetch(url)
        .then(r => r.json())
        .then(dados => {
            console.log("[PAINEL] listar_equipes_login.php →", dados);

            if (!dados || !dados.success || !Array.isArray(dados.equipes)) {
                lista.innerHTML = `<div class="lista-vazia">Não foi possível carregar as equipes.</div>`;
                return;
            }

            const equipe = dados.equipes.find(eq => eq.lider === nomeEquipe);

            if (!equipe) {
                lista.innerHTML = `<div class="lista-vazia">Nenhuma equipe encontrada para ${nomeEquipe}.</div>`;
                if (hud) hud.textContent = `Equipe: ${nomeEquipe} (não encontrada no cadastro)`;
                return;
            }

            const operadores = equipe.operadores || [];

            if (hud) {
                hud.textContent = `Equipe: ${nomeEquipe} • ${operadores.length} operadores`;
            }
            if (resumo) {
                resumo.textContent = `Equipe: ${nomeEquipe} • ${operadores.length} operadores cadastrados`;
            }

            if (!operadores.length) {
                lista.innerHTML = `<div class="lista-vazia">Nenhum operador cadastrado para esta equipe.</div>`;
                return;
            }

            lista.innerHTML = "";
            operadores.forEach(op => {
                const linha = document.createElement("div");
                const ehAtual = (op.nome === nomeOperador);

                linha.className = "linha-participante" + (ehAtual ? " atual" : "");
                linha.innerHTML = `
                    <span class="bolinha-estado"></span>
                    <span class="nome-op">${op.nome}</span>
                `;

                lista.appendChild(linha);
            });

        })
        .catch(err => {
            console.error("[PAINEL] Erro ao carregar equipes:", err);
            lista.innerHTML = `<div class="lista-vazia">Erro ao carregar dados de participantes.</div>`;
        });
}

// ======================================================
// SISTEMA DE PREFERÊNCIAS
// ======================================================

function inicializarPreferencias() {
    const modal = document.getElementById("modalPreferencias");
    if (!modal) {
        console.warn("[PREFERENCIAS] Modal não encontrado no DOM.");
        return;
    }

    const btnFecharPref   = document.getElementById("btnFecharPref");
    const btnSalvarPrefer = document.getElementById("btnSalvarPreferencias");
    const btnTesteAudio   = document.getElementById("btnTesteAudio");
    const btnTesteNotif   = document.getElementById("btnTesteNotif");

    if (btnFecharPref) {
        btnFecharPref.addEventListener("click", fecharModalPreferencias);
    }

    if (btnSalvarPrefer) {
        btnSalvarPrefer.addEventListener("click", salvarPreferencias);
    }

    if (btnTesteAudio) {
        btnTesteAudio.addEventListener("click", testarAudioPreferencias);
    }

    if (btnTesteNotif) {
        btnTesteNotif.addEventListener("click", testarNotificacaoPreferencias);
    }
}

function abrirModalPreferencias() {
    const modal = document.getElementById("modalPreferencias");
    if (!modal) return;
    modal.classList.remove("hidden");
    carregarPreferencias();
}

function fecharModalPreferencias() {
    const modal = document.getElementById("modalPreferencias");
    if (!modal) return;
    modal.classList.add("hidden");
}

// Fechar modal de preferências com ESC
document.addEventListener("keydown", (ev) => {
    if (ev.key === "Escape") {
        const modal = document.getElementById("modalPreferencias");
        if (modal && !modal.classList.contains("hidden")) {
            fecharModalPreferencias();
        }
    }
});

function carregarPreferencias() {
    const operadorStr = localStorage.getItem("tga_operador");
    if (!operadorStr) return;

    let operador;
    try {
        operador = JSON.parse(operadorStr);
    } catch (e) {
        console.error("[PREFERENCIAS] Erro ao ler tga_operador:", e);
        return;
    }

    if (!operador.id) {
        console.warn("[PREFERENCIAS] Operador sem ID, não é possível buscar preferências individuais.");
        return;
    }

    const backend = window.SISTEMA_CONFIG?.caminhos?.backend || "./backend/";
    const url = `${backend}carregar_preferencias.php?id=${encodeURIComponent(operador.id)}`;

    fetch(url)
        .then(r => r.json())
        .then(pref => {
            const chkAudio = document.getElementById("pref_audio");
            const chkNotif = document.getElementById("pref_notif");

            if (chkAudio) chkAudio.checked = pref.audio == 1;
            if (chkNotif) chkNotif.checked = pref.notificacao == 1;
        })
        .catch(err => {
            console.error("[PREFERENCIAS] Erro ao carregar preferências:", err);
        });
}

function salvarPreferencias() {
    const operadorStr = localStorage.getItem("tga_operador");
    if (!operadorStr) return;

    let operador;
    try {
        operador = JSON.parse(operadorStr);
    } catch (e) {
        console.error("[PREFERENCIAS] Erro ao ler tga_operador:", e);
        return;
    }

    if (!operador.id) {
        alert("Não foi possível salvar preferências: operador sem ID.");
        return;
    }

    const chkAudio = document.getElementById("pref_audio");
    const chkNotif = document.getElementById("pref_notif");
    if (!chkAudio || !chkNotif) return;

    const dados = new FormData();
    dados.append("id", operador.id);
    dados.append("audio", chkAudio.checked ? 1 : 0);
    dados.append("notificacao", chkNotif.checked ? 1 : 0);

    const backend = window.SISTEMA_CONFIG?.caminhos?.backend || "./backend/";
    fetch(`${backend}salvar_preferencias.php`, {
        method: "POST",
        body: dados
    })
        .then(r => r.json())
        .then(resp => {
            alert(resp.msg || "Preferências salvas.");

            if (resp.success) {
                fecharModalPreferencias();
            }
        })
        .catch(err => {
            console.error("[PREFERENCIAS] Erro ao salvar preferências:", err);
            alert("Erro ao salvar preferências.");
        });
}

function testarAudioPreferencias() {
    const painelPath = window.SISTEMA_CONFIG?.caminhos?.painel || "./painel/";
    const audioUrl = `${painelPath}audio/teste.mp3`;
    const audio = new Audio(audioUrl);
    audio.play().catch(err => {
        console.error("[PREFERENCIAS] Erro ao tocar áudio de teste:", err);
        alert("Não foi possível tocar o áudio de teste.");
    });
}

function testarNotificacaoPreferencias() {
    if (!("Notification" in window)) {
        alert("Seu navegador não suporta notificações.");
        return;
    }

    Notification.requestPermission().then((perm) => {
        if (perm !== "granted") {
            alert("Permita as notificações no navegador para testar.");
            return;
        }

        new Notification("🔔 Notificação de teste", {
            body: "As notificações estão funcionando!",
            icon: "https://tgameajuda.com/img/principal/bot-tga.webp"
        });
    });
}
