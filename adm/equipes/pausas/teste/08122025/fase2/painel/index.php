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

    <!-- FontAwesome -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

<!-- Contador -->
<img alt="visitas"
     src="https://hits.sh/tgameajuda.com/teste.html.svg?color=007ced&label=visitas&labelColor=FFFFFF&logo=https%3A%2F%2Fraw.githubusercontent.com%2Fsouzaseven%2Ftgahelpme%2FDesafios%2Ficon%2520bot%2520tga.ico">

<!-- ============================================================
     WRAPPER DO LAYOUT - TUDO DENTRO DELE
============================================================ -->
<div class="layout-wrapper">

<!-- ====================== SIDEBAR =========================== -->
<div class="sidebar" id="sidebar">

    <button class="sidebar-toggle" id="toggleSidebar">
        <i class="fa-solid fa-bars"></i>
    </button>

    <ul class="sidebar-menu">
        <li class="sidebar-item" onclick="window.location.href='index.php'">
            <i class="fa-solid fa-house"></i>
            <span class="texto-item">Início</span>
        </li>

        <li class="sidebar-item">
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


<!-- ====================== PAINEL PRINCIPAL ================== -->
<div class="painel-container">

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

                <!-- Legenda -->
                <span class="legenda-status">
                    <span class="bolinha online"></span> Online
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


<!-- ====================== MODAL PREFERÊNCIAS =================== -->
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

</div><!-- FIM layout-wrapper -->

<!-- ====================== SCRIPTS =================== -->
<script src="painel.js"></script>

<script>
// Carregar operador logado
const dadosOperador = JSON.parse(localStorage.getItem("tga_operador"));
if (!dadosOperador) window.location.href = "../login/login.php";

document.getElementById("subtituloEquipe").textContent =
    "Equipe: " + dadosOperador.equipe;

document.getElementById("boxOperadorLogado").innerHTML = `
    <div class="linha-op">
        <i class="fas fa-user-circle"></i>
        <span><strong>${dadosOperador.operador}</strong></span>
    </div>
    <div class="linha-op">
        <i class="fas fa-users"></i>
        <span>${dadosOperador.equipe}</span>
    </div>
`;

function logoutOperador() {
    localStorage.removeItem("tga_operador");
    window.location.href = "../login/login.php";
}

document.getElementById("toggleSidebar").addEventListener("click", () => {
    document.getElementById("sidebar").classList.toggle("expanded");
});
</script>

</body>
</html>
