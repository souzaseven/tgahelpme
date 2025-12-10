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

    <!-- FontAwesome -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

<img alt="visitas"
     src="https://hits.sh/tgameajuda.com/teste.html.svg?color=007ced&label=visitas&labelColor=FFFFFF&logo=https%3A%2F%2Fraw.githubusercontent.com%2Fsouzaseven%2Ftgahelpme%2FDesafios%2Ficon%2520bot%2520tga.ico">

<div class="layout-wrapper">

<!-- ==========================================================
     SIDEBAR
=========================================================== -->
<div class="sidebar" id="sidebar">

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

        <li class="sidebar-item sair" onclick="logoutOperador()">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span class="texto-item">Sair</span>
        </li>
    </ul>
</div>


<!-- ==========================================================
     PAINEL PRINCIPAL
=========================================================== -->
<div class="painel-container">

    <!-- CONTEÚDO EXTRA (Todas equipes) -->
    <div id="conteudoExtra" class="conteudo-extra hidden"></div>

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
                    <span class="bolinha ativo"></span> Ativo
                    <span class="bolinha espera"></span> Fila
                    <span class="bolinha pausa"></span> Pausa
                    <span class="bolinha expirado"></span> Expirado
                </span>
            </h2>

            <span class="hud-operador">Operador logado destacado</span>
        </div>

        <div id="listaEquipeCompleta" class="lista-participantes">
            <p class="lista-vazia">Carregando equipe…</p>
        </div>
    </div>

    <div id="areaBotoesOperador"></div>

</div> <!-- painel-container -->

<!-- ==========================================================
     MODAL PREFERÊNCIAS
=========================================================== -->
<div class="modal-pref hidden" id="modalPreferencias">
    <div class="modal-pref-content">
        <h3>Preferências</h3>

        <div class="pref-item">
            <span>Som de notificações</span>
            <button class="btn-teste">Testar</button>
        </div>

        <button class="btn-salvar-pref">Salvar Preferências</button>
        <button class="btn-fechar-pref" onclick="fecharPreferencias()">Fechar</button>
    </div>
</div>

</div><!-- layout-wrapper -->

<!-- ==========================================================
     SCRIPTS
=========================================================== -->
<script src="painel.js"></script>
<script src="operador.js"></script>

<script>
// Toggle Sidebar
document.getElementById("toggleSidebar").addEventListener("click", () => {
    document.getElementById("sidebar").classList.toggle("expanded");
});
</script>

<script>
// Logout Global
window.logoutOperador = function () {
    localStorage.removeItem("tga_operador");
    window.location.href = "../login/login.php";
};
</script>

<script>
async function carregarTodasEquipes() {

    // Oculta painel principal
    document.querySelector(".painel-topo").style.display = "none";
    document.querySelector(".painel-dashboard").style.display = "none";
    document.querySelector(".card-participantes").style.display = "none";
    document.querySelector("#areaBotoesOperador").style.display = "none";

    const conteudo = document.getElementById("conteudoExtra");
    conteudo.classList.remove("hidden");

    conteudo.innerHTML = `
        <div style="text-align:center;padding:20px;">
            <i class="fa-solid fa-spinner fa-spin" style="font-size:26px;color:#38bdf8;"></i>
            <p style="color:#ccc">Carregando equipes...</p>
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
        console.error("ERRO AO BUSCAR EQUIPES:", e);
        conteudo.innerHTML = "<p style='color:red'>Falha ao comunicar com o servidor.</p>";
    }
}
</script>

</body>
</html>
