<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle de Pausa - Painel</title>

    <link rel="icon" href="https://tgameajuda.com/img/principal/bot-tga.webp" type="image/x-icon">

    <!-- CSS -->
    <link rel="stylesheet" href="painel.css">
    <link rel="stylesheet" href="operador.css">
    <link rel="stylesheet" href="preferencias.css">
    <link rel="stylesheet" href="decidir.css">
    <link rel="stylesheet" href="supervisao.css">
    <link rel="stylesheet" href="chat/chat.css">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- TEMA -->
    <link rel="stylesheet" href="chat/tema.css">



    <!-- ✅ Google Tag Manager -->
    <script>
        (function (w, d, s, l, i) {
            w[l] = w[l] || [];
            w[l].push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
            var f = d.getElementsByTagName(s)[0],
                j = d.createElement(s),
                dl = l != 'dataLayer' ? '&l=' + l : '';
            j.async = true;
            j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
            f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', 'GTM-K2XFNTVZ');
    </script>
    <!-- End Google Tag Manager -->

    <!-- ✅ Google Ads (AdSense Global) -->
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044"
        crossorigin="anonymous"></script>

    <!-- ✅ Google Analytics - Tag 1 (G-E7ZNTJSRYR) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-E7ZNTJSRYR"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', 'G-E7ZNTJSRYR');
    </script>

    <!-- ✅ Google Analytics - Tag 2 (G-S8EC5C2WTG) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-S8EC5C2WTG"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', 'G-S8EC5C2WTG');
    </script>



</head>

<body>

    <!-- Contador de visitas -->
    <img alt="visitas"
        src="https://hits.sh/tgameajuda.com/equipe_daniel_e_alex-22122025.html.svg?color=007ced&label=visitas&labelColor=FFFFFF&logo=https%3A%2F%2Fraw.githubusercontent.com%2Fsouzaseven%2Ftgahelpme%2FDesafios%2Ficon%2520bot%2520tga.ico">

    <!-- BOTÃO DE CONFIGURAÇÕES (ENGRENAGEM) -->
    <div id="chat-config-btn" class="chat-config-btn">
        ⚙️
    </div>

    <!-- MODAL DE TEMA -->
    <div id="chatTemaModal" class="chat-tema-modal hidden">
        <div class="chat-tema-content">
            <h3>Preferências de Tema</h3>
            <label>
                <input type="radio" name="tema" value="claro">
                Claro
            </label>
            <label>
                <input type="radio" name="tema" value="escuro">
                Escuro
            </label>
            <button id="salvarTemaBtn">Salvar</button>
        </div>
    </div>

    <!-- Ícone flutuante do Chat -->
    <div id="chatIcone" class="chat-icone" onclick="toggleChat()">

        <i class="fa-solid fa-comments"></i>

        <!-- Badge -->
        <span id="chatBadge" class="chat-badge hidden">0</span>

        <!-- Preview da última mensagem -->
        <div id="chatPreview" class="chat-preview hidden"></div>

    </div>


    <div class="layout-wrapper">

        <!-- ==========================================================
         SIDEBAR
    =========================================================== -->
        <div class="sidebar collapsed" id="sidebar">

            <button class="sidebar-toggle" id="toggleSidebar">
                <i class="fa-solid fa-bars"></i>
            </button>

            <ul class="sidebar-menu">
                <li class="sidebar-item" onclick="window.location.href='index.php'">
                    <i class="fa-solid fa-house"></i>
                    <span class="texto-item">Início</span>
                </li>

                <li class="sidebar-item" onclick="carregarTodasEquipes()">
                    <i class="fa-solid fa-users"></i>
                    <span class="texto-item">Todas Equipes</span>
                </li>

                <li class="sidebar-item" onclick="abrirPreferencias()">
                    <i class="fa-solid fa-sliders"></i>
                    <span class="texto-item">Preferências</span>
                </li>
                <li class="sidebar-item" onclick="abrirSupervisao()">
                    <i class="fa-solid fa-user-shield"></i>
                    <span class="texto-item">Supervisão</span>
                </li>

                <!-- NOVO BOTÃO: Equipes -->
                <li class="sidebar-item hidden" id="btnEquipesAbas" onclick="abrirSelecaoEquipes()">
                    <i class="fa-solid fa-layer-group"></i>
                    <span class="texto-item">Equipes</span>
                </li>


<li class="sidebar-item" id="menuFerramentasAdmin" style="display: none;" onclick="window.location.href = '../backend/whatsapp/ferramentas_admin.php'">
    <i class="fa-solid fa-tools"></i>
    <span class="texto-item">Ferramentas ADM</span>
</li>



                <li class="sidebar-item sair" onclick="logoutOperador()">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span class="texto-item">Sair</span>
                </li>
            </ul>
        </div>
        <!-- ==========================================================
     PAINEL PRINCIPAL
=========================================================== -->
        <div id="painelPrincipal">

            <div class="painel-container">

                <!-- Abas Internas para Equipes -->
                <div id="abasEquipes" class="abas-equipes hidden"></div>

                <!-- Painéis de cada equipe -->
                <div id="painelEquipesInternas" class="painel-equipes-internas hidden"></div>

                <!-- CONTEÚDO EXTRA -->
                <div id="conteudoExtra" class="conteudo-extra hidden"></div>
                <div id="conteudoSupervisao" class="conteudo-supervisao hidden"></div>

                <!-- TOPO -->
                <div class="painel-topo">
                    <div class="titulo-sistema">
                        <h1>Controle de Pausa – Matriz</h1>
                        <p id="subtituloEquipe">Carregando equipe…</p>
                    </div>

                    <div class="info-operador" id="boxOperadorLogado"></div>
                </div>

                <!-- PAUSA / FILA -->
                <div class="painel-dashboard">

                    <div class="card">
                        <h2>Operadores em Pausa</h2>
                        <div id="listaPausa" class="lista-participantes">
                            <p class="lista-vazia">Carregando…</p>
                        </div>
                    </div>

                    <div class="card">
                        <h2>Fila de Espera</h2>
                        <div id="listaFila" class="lista-participantes">
                            <p class="lista-vazia">Carregando…</p>
                        </div>
                    </div>

                </div>

                <!-- EQUIPE COMPLETA -->
                <div class="card card-participantes">
                    <div class="card-header-flex">
                        <h2>
                            Equipe <span id="nomeEquipeTitulo">Carregando...</span>

                            <span class="legenda-status">
                                <span class="bolinha-estado ativo"></span> Ativo
                                <span class="bolinha-estado espera"></span> Fila
                                <span class="bolinha-estado pausa"></span> Pausa
                                <span class="bolinha-estado expirado"></span> Expirado
                            </span>
                        </h2>

                        <span class="hud-operador">Operador logado destacado</span>
                    </div>

                    <div id="listaEquipeCompleta" class="lista-participantes">
                        <p class="lista-vazia">Carregando equipe…</p>
                    </div>
                </div>

            </div>

        </div>


    </div><!-- layout-wrapper -->
<!-- ==========================================================
     SCRIPTS (ORDEM FINAL + CACHE BUSTING)
=========================================================== -->

<script src="preferencias.js?v=<?= time() ?>" defer></script>

<!-- 🔔 Base de alertas -->
<script src="notificacoes.js?v=<?= time() ?>" defer></script>

<!-- 👤 Operador -->
<script src="operador.js?v=<?= time() ?>" defer></script>

<!-- 📊 Painel principal -->
<script src="painel.js?v=<?= time() ?>" defer></script>
<!--  Botões extras como "Forçar Pausa" -->
<script src="painel_botoes_extras.js?v=<?= time() ?>" defer></script>

<!-- 🧩 Extensões do painel -->
<script src="painel_equipe.js?v=<?= time() ?>" defer></script>
<script src="painel_abas.js?v=<?= time() ?>" defer></script>

<!-- 💬 Chat -->
<script src="chat/chat.js?v=<?= time() ?>" defer></script>
<script src="chat/tema.js?v=<?= time() ?>" defer></script>

<!-- 👁️ Supervisão -->
<script src="supervisao.js?v=<?= time() ?>" defer></script>

<!-- ❗ decidir por último -->
<script src="decidir.js?v=<?= time() ?>" defer></script>



    <script>
        // ==========================================================
        // Sidebar Toggle
        // ==========================================================
        document.getElementById("toggleSidebar").addEventListener("click", () => {
            const sb = document.getElementById("sidebar");
            sb.classList.toggle("expanded");
            sb.classList.toggle("collapsed");
        });

        // ==========================================================
        // Logout
        // ==========================================================
        window.logoutOperador = function () {
            localStorage.removeItem("tga_operador");
            window.location.href = "../login/login.php";
        };
    </script>

    <!-- ==========================================================
     Carregar TODAS equipes
=========================================================== -->
    <script>
        async function carregarTodasEquipes() {

            document.querySelector(".painel-topo").style.display = "none";
            document.querySelector(".painel-dashboard").style.display = "none";
            document.querySelector(".card-participantes").style.display = "none";

            const conteudo = document.getElementById("conteudoExtra");
            conteudo.classList.remove("hidden");

            conteudo.innerHTML = `
        <div style='text-align:center;padding:20px;'>
            <i class="fa-solid fa-spinner fa-spin" style="font-size:26px;color:#38bdf8;"></i>
            <p style='color:#ccc'>Carregando equipes...</p>
        </div>
    `;

            try {
                const resp = await fetch("../backend/todas_equipes.php");
                const dados = await resp.json();

                if (!dados.success) {
                    conteudo.innerHTML = "<p style='color:#f88'>Erro ao carregar equipes.</p>";
                    return;
                }

                const equipes = dados.equipes;

                let html = `
            <h2><i class="fa-solid fa-people-group"></i> Todas as Equipes</h2>
            <div class='todas-equipes-grid'>
        `;

                for (const equipe in equipes) {
                    html += `
                <div class="card-equipe">
                    <h3><i class="fa-solid fa-user-tie"></i> ${equipe}</h3>
                    <div class="lista-simples">
                        ${equipes[equipe].map(n => `
                            <p><i class="fa-solid fa-user"></i> ${n}</p>
                        `).join("")}
                    </div>
                </div>
            `;
                }

                html += "</div>";
                conteudo.innerHTML = html;

            } catch (e) {
                console.error("Erro carregar equipes:", e);
                conteudo.innerHTML = "<p style='color:red'>Falha ao comunicar com o servidor.</p>";
            }
        }
    </script>

    <!-- ==========================================================
     MODAL DECIDIR
=========================================================== -->
    <div id="decidirOverlay" class="decidir-overlay hidden">
        <div class="decidir-modal" id="decidirModal">
            <h3>Decidir na fila de espera</h3>
            <p class="decidir-sub">
                Escolha o que deseja fazer em relação à sua posição na fila.
            </p>

            <div id="decidirOpcoes" class="decidir-opcoes"></div>
            <!--
        <div class="decidir-footer">
            <button class="decidir-btn-fechar" onclick="fecharModalDecidir()">
                <i class="fa-solid fa-xmark"></i> Fechar
            </button>
        </div>-->
        </div>
    </div>

    <!-- ==========================================================
     MODAL CHAT
=========================================================== -->
    <div id="chatOverlay" class="chat-overlay hidden">

        <div id="chatJanela" class="chat-janela">

            <!-- TOPO -->
            <div class="chat-topo">
                <span>
                    <i class="fas fa-comments"></i>
                    Chat Privado
                </span>

                <div class="chat-acoes">

                    <button class="chat-fechar" onclick="fecharChat()">✖</button>
                </div>
            </div>

            <!-- DESTINATÁRIO (somente individual) -->
            <div class="chat-destino">
                <label>Conversar com:</label>
                <select id="chatDestino">
                    <!-- carregado dinamicamente -->
                </select>
            </div>

            <!-- MENSAGENS -->
            <div id="chatMensagens" class="chat-mensagens">
                <p class="chat-info">Selecione um operador para iniciar a conversa</p>
            </div>

            <!-- ENVIO -->
            <div class="chat-envio">
                <input id="chatTexto" type="text" placeholder="Digite sua mensagem..." autocomplete="off" />
                <button onclick="enviarChat()">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>

        </div>

    </div>
    <footer style="text-align:center; font-size:12px; color:#888; margin-top:20px;">
        TGA – Versão 2025.12.23.05
    </footer>
<!-- força atualizar para ultima versao da pagina -->
<script src="js/verificador_versao.js?v=<?= time() ?>" defer></script>

    <!-- Loader Evolux -->
    <div id="evolux-loader" class="evolux-loader hidden">
        <div class="evolux-loader-box">

            <div class="spinner"></div>

            <div class="evolux-progress">
                <div class="evolux-progress-bar" id="evoluxProgressBar"></div>
            </div>

            <p id="evolux-loader-text">Consultando status no Evolux…</p>

            <!-- 🔴 BOTÃO CANCELAR -->
            <button class="evolux-cancelar" onclick="cancelarEvolux()">
                Cancelar
            </button>

        </div>
    </div>


<!-- ========================================================= -->
<!-- ALERTA DE PAUSA EXCEDIDA -->
<!-- ========================================================= -->

<link rel="stylesheet" href="alerta/alerta-pausa.css">

<script src="alerta/alerta-pausa.config.js"></script>
<script src="alerta/alerta-whatsapp.js"></script>
<script src="alerta/alerta-pausa.js"></script>


</body>

</html>