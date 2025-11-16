<?php
// ============================================================
// index.php - Painel de Controle de Pausas (v1.7) [ATUALIZADO]
// ============================================================
// 🔹 Interface principal em PHP para controle de pausas
// 🔹 Inclui setup dinâmico e integração com backend
// ============================================================

session_start();

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

$versao = basename(__DIR__);
$usuario_nome = $_SESSION['operador_nome'] ?? $_SESSION['usuario_nome'] ?? $_SESSION['nome'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Controle de Pausa - Equipes Matriz</title>

  <link rel="icon" href="https://tgameajuda.com/img/principal/bot-tga.webp" type="image/x-icon">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="./css/estilo_base.css?v=<?php echo $versao; ?>">
  <link rel="stylesheet" href="./css/controle_pausa.css?v=<?php echo $versao; ?>">
  <link rel="stylesheet" href="./css/botoes_operador.css?v=<?php echo $versao; ?>">
  <link rel="stylesheet" href="./css/status_cards.css?v=<?php echo $versao; ?>">
  <link rel="stylesheet" href="./css/operador_logado.css?v=<?php echo $versao; ?>">
  <link rel="stylesheet" href="./css/cronometro.css?v=<?php echo $versao; ?>">
  <link rel="stylesheet" href="./css/login.css?v=<?php echo $versao; ?>">

  <!-- Setup dinâmico -->
  <script src="./setup_sistema.php?v=<?php echo time(); ?>"></script>

  <!-- AdSense -->
  <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044" crossorigin="anonymous"></script>

  <!-- Google Analytics -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-S8EC5C2WTG"></script>
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-E7ZNTJSRYR"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){ dataLayer.push(arguments); }
    gtag('js', new Date());
    gtag('config', 'G-S8EC5C2WTG');
  </script>
</head>

<body>
  <div class="container">

    <!-- BLOCO 1: Título -->
    <header class="topo bloco-titulo" style="display:flex;justify-content:center;align-items:center;width:100%;padding:8px 5%;border-bottom:1px solid rgba(255,255,255,0.1);">
      <div class="titulo" style="text-align:center;">
        <h1 style="margin:0;"><i class="fas fa-clock"></i> Controle de Pausa</h1>
        <p class="subtitle" style="margin:0;color:#ccc;">Equipes Matriz</p>
      </div>
    </header>

    <div class="preferencias-box">
      <button id="btnPreferencias" class="btn-preferencias">
        <i class="fas fa-sliders-h"></i> Preferências
      </button>
    </div>

    <!-- BLOCO 2: Usuário + Status -->
    <header class="topo bloco-status" style="display:flex;justify-content:space-between;align-items:center;width:100%;padding:10px 5%;gap:20px;flex-wrap:nowrap;">

      <aside class="painel-lateral" style="display:flex;flex-direction:row;align-items:center;gap:25px;flex:1;min-width:0;white-space:nowrap;">
        <div class="usuario-logado" id="usuarioLogadoBox"
          style="color:#9cd;font-weight:600;display:flex;align-items:center;gap:6px;">
          <i class="fas fa-user-circle"></i>
          <span id="usuarioLogado">👤 Operador: Anderson • Equipe: Daniel Feix</span>
        </div>
      </aside>

      <!-- Status do sistema -->
      <div id="statusSistema" class="status-sistema"
        style="display:flex;align-items:center;gap:18px;flex-wrap:nowrap;white-space:nowrap;">
        <div class="status-item" style="display:flex;align-items:center;gap:6px;">
          <span class="status-icon status-online"></span>
          <span>Sistema Online</span>
        </div>
        <div class="status-item" style="display:flex;align-items:center;gap:6px;">
          <span class="status-icon status-online" id="statusBanco"></span>
          <span id="textoBanco">Banco de Dados Conectado</span>
        </div>
        <div class="status-item" style="display:flex;align-items:center;gap:6px;">
          <span class="status-icon status-online" id="statusOperadores"></span>
          <span id="textoOperadores">3 equipes carregadas</span>
        </div>
      </div>

      <!-- Controles -->
      <div class="painel-controles" style="display:flex;align-items:center;gap:12px;flex-wrap:nowrap;white-space:nowrap;">
        <button id="btnTrocarUsuario" class="btn-trocar-usuario" title="Trocar usuário">
          <i class="fas fa-user-switch"></i> Trocar Usuário
        </button>

        <div class="filtro-equipe-box" style="display:flex;gap:10px;flex-shrink:0;">
          <button id="btnFiltroEquipe" class="btn-filtro" style="display:inline-block;">👥 Mostrar somente minha equipe</button>
          <button id="btnFiltroTodas" class="btn-filtro" style="display:none;">🌎 Mostrar todas as equipes</button>
        </div>
      </div>
    </header>

    <!-- DASHBOARD PRINCIPAL -->
    <div class="dashboard">
      <!-- Card Pausas -->
      <div class="card pausas-container">
        <h2><i class="fas fa-coffee"></i> Pausas Ativas</h2>
        <div class="contador-status">
          <span class="contador" id="contador-pausa">0</span> pessoas em pausa
          <span class="sync-status" id="sync-status"></span>
        </div>
        <div id="pausa-lista" class="lista-pausas">
          <div class="lista-vazia">
            <i class="fas fa-user-clock"></i>
            Nenhum operador em pausa no momento
          </div>
        </div>
      </div>

      <!-- Card Fila -->
      <div class="card fila-container">
        <h2><i class="fas fa-clock"></i> Fila de Espera</h2>
        <div class="contador-status">
          <span class="contador" id="contador-espera">0</span> pessoas na fila
        </div>
        <div id="lista-espera" class="lista-fila">
          <div class="lista-vazia">
            <i class="fas fa-users"></i>
            Nenhuma pessoa na fila de espera
          </div>
        </div>
      </div>

      <!-- Card Participantes -->
      <div class="card participantes-container full-width">
        <div class="titulo-participantes">
          <div class="grupo-esquerda">
            <span><i class="fas fa-users"></i> Participantes</span>
            <span id="legendaStatus" class="legenda-inline">
              <span class="l-item"><span class="barra barra-ativo"></span> 🟢 Ativo</span>
              <span class="l-item"><span class="barra barra-espera"></span> ⏳ Espera</span>
              <span class="l-item"><span class="barra barra-pausa"></span> ☕ Pausa</span>
              <span class="l-item"><span class="barra barra-expirada"></span> 🔴 Expirada</span>
            </span>
          </div>

          <div class="grupo-direita">
            <span id="hud-operador" class="hud-operador">🌎 3 equipes • 36 operadores</span>
          </div>
        </div>

        <div class="lista-participantes" id="listaParticipantes">
          <div class="loading">Carregando equipes e operadores...</div>
        </div>
      </div>
    </div>

<!-- =============== MODAL LOGIN =============== -->
<div id="modalOperador">
  <div class="janela login-janela">

    <h2 class="titulo-login">Identifique-se</h2>

    <div class="modo-login-botoes">
      <button id="btnModoEquipe" class="modo-btn">
        <i class="fas fa-users"></i> Equipe / Nome
      </button>
    </div>

    <!-- LOGIN MODO MANUAL (removido teclado manual) -->
    <div id="loginModoManual" class="modo-login ativo">
      <button id="btnEntrar">Entrar</button>
      <div class="msg-erro" id="msgErro"></div>
    </div>

    <!-- LOGIN SELEÇÃO DE EQUIPE / OPERADOR -->
    <div id="loginModoEquipe" class="modo-login hidden">
      <div id="stepEquipes">
        <h3 class="subtitulo-login">Escolha sua equipe</h3>
        <p class="texto-secundario">Selecione a equipe à qual você pertence:</p>
        <div id="listaEquipes" class="grid-equipes"></div>
      </div>

      <div id="stepOperadores" class="hidden">
        <button id="btnVoltarEquipes" class="btn-voltar">
          <i class="fas fa-arrow-left"></i> Voltar para equipes
        </button>

        <h3 class="subtitulo-login" id="tituloEquipeSelecionada">Equipe</h3>
        <p class="texto-secundario">Selecione seu nome:</p>

        <div id="listaOperadores" class="grid-operadores"></div>

        <div class="msg-erro" id="msgErroEquipe"></div>

        <button id="btnConfirmarOperador" class="btn-confirmar" disabled>
          <i class="fas fa-check"></i> Confirmar seleção
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Toast -->
<div id="toast">Pronto.</div>

<!-- Modal Preferências -->
<div id="modalPreferencias" class="modal-pref hidden">
  <div class="modal-pref-content">
    <h2><i class="fas fa-sliders-h"></i> Preferências do Operador</h2>

    <div class="pref-group">
      <label class="switch">
        <input type="checkbox" id="prefSom">
        <span class="slider"></span>
      </label>
      <span>Som de notificações</span>
    </div>

    <div class="pref-group">
      <label class="switch">
        <input type="checkbox" id="prefDesktop">
        <span class="slider"></span>
      </label>
      <span>Notificação no Windows</span>
    </div>

    <button id="btnSalvarPreferencias" class="btn-salvar-pref">
      Salvar Preferências
    </button>
  </div>
</div>

<!-- SCRIPTS PRINCIPAIS -->
<script src="./js/inicializacao.js?v=<?php echo $versao; ?>"></script>
<script src="./js/login.js?v=<?php echo $versao; ?>"></script>
<script src="./js/controle_pausa.js?v=<?php echo $versao; ?>"></script>
<script src="./js/notificacoes_pausa.js?v=<?php echo $versao; ?>"></script>
<script src="./js/expiracao_pausa.js?v=<?php echo $versao; ?>"></script>
<script src="./js/acoes_operador.js?v=<?php echo $versao; ?>"></script>
<script src="./js/status_cards.js?v=<?php echo $versao; ?>"></script>
<script src="./js/interface_botoes.js?v=<?php echo $versao; ?>"></script>
<script src="./js/ordenar_logado_primeiro.js?v=<?php echo $versao; ?>"></script>
<script src="./js/cronometro.js?v=<?php echo $versao; ?>"></script>
<script src="./js/troca_fila.js?v=<?php echo $versao; ?>"></script>

<!-- Script Preferências -->
<script>
document.addEventListener("DOMContentLoaded", () => {

    const btnPref = document.getElementById("btnPreferencias");
    const modal = document.getElementById("modalPreferencias");
    const chkSom = document.getElementById("prefSom");
    const chkDesk = document.getElementById("prefDesktop");
    const btnSalvar = document.getElementById("btnSalvarPreferencias");

    // Carrega preferências
    chkSom.checked = localStorage.getItem("pref_som") === "1";
    chkDesk.checked = localStorage.getItem("pref_desktop") === "1";

    btnPref.onclick = () => {
        modal.classList.remove("hidden");
    };

    modal.onclick = (e) => {
        if (e.target === modal) modal.classList.add("hidden");
    };

    btnSalvar.onclick = () => {
        localStorage.setItem("pref_som", chkSom.checked ? "1" : "0");
        localStorage.setItem("pref_desktop", chkDesk.checked ? "1" : "0");

        modal.classList.add("hidden");
        alert("Preferências salvas!");
    };
});
</script>

<img alt="Em fase de teste 12-11-2025"
     src="https://hits.sh/tgameajuda.com/teste11-11-2025.html.svg?color=007ced&label=Em fase de teste 12-11-2025&labelColor=FFFFFF&logo=https%3A%2F%2Fraw.githubusercontent.com%2Fsouzaseven%2Ftgahelpme%2FDesafios%2Ficon%2520bot%2520tga.ico"/>

</body>
</html>
