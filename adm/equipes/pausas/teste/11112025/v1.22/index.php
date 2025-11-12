<?php
// ============================================================
// index.php - Painel de Controle de Pausas (v1.6) [ATUALIZADO]
// ============================================================
// 🔹 Interface principal em PHP para controle de pausas
// 🔹 Inclui setup dinâmico e integração com backend
// ============================================================

session_start(); // sempre antes de qualquer saída / header

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

  <script src="./setup_sistema.php?v=<?php echo time(); ?>"></script>

  <script async src="https://www.googletagmanager.com/gtag/js?id=G-S8EC5C2WTG"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){ dataLayer.push(arguments); }
    gtag('js', new Date());
    gtag('config', 'G-S8EC5C2WTG');
  </script>

  <link rel="stylesheet" href="./css/estilo_base.css?v=<?php echo $versao; ?>">
<link rel="stylesheet" href="./css/controle_pausa_v14.css">

</head>

<body>
  <div class="container">
    <header class="topo" style="display:flex;justify-content:space-between;align-items:center;width:100%;padding:10px 5%;">
  <div class="titulo" style="text-align:center;width:90%;">
    <h1 style="margin:0;"><i class="fas fa-clock"></i> Controle de Pausa</h1>
    <p class="subtitle" style="margin:0;color:#ccc;">Equipes Matriz</p>
  </div>

  <!-- Painel lateral direito (fixo e proporcional) -->
<aside class="painel-lateral" style="display:flex;flex-direction:column;align-items:flex-end;gap:5px;width:250px;">
  <div class="usuario-logado" id="usuarioLogadoBox" style="color:#9cd;font-weight:600;">
    <i class="fas fa-user-circle"></i>
    <span id="usuarioLogado">
      <?php 
        echo htmlspecialchars($usuario_nome ?? 'Usuário não identificado');
      ?>
    </span>
  </div>


    <div id="statusSistema" class="status-sistema" style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
      <div class="status-item"><span class="status-icon status-online"></span> <span>Sistema Online</span></div>
      <div class="status-item"><span class="status-icon status-online" id="statusBanco"></span> <span id="textoBanco">Banco de Dados Conectado</span></div>
      <div class="status-item"><span class="status-icon status-online" id="statusOperadores"></span> <span id="textoOperadores">3 equipes carregadas</span></div>
    </div>
  </aside>
</header>

  

    <div class="dashboard">
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

<div class="card participantes-container full-width">
  <h2 class="titulo-participantes">
    <i class="fas fa-users"></i> Participantes

    <span id="legendaStatus" class="legenda-inline">
      <span class="l-item"><span class="barra barra-ativo"></span> 🟢 Ativo</span>
      <span class="l-item"><span class="barra barra-espera"></span> ⏳ Espera</span>
      <span class="l-item"><span class="barra barra-pausa"></span> ☕ Pausa</span>
      <span class="l-item"><span class="barra barra-expirada"></span> 🔴 Expirada</span>
    </span>

    <span id="hud-operador" class="hud-operador"></span>
  </h2>

  <div class="lista-participantes" id="listaParticipantes">
    <div class="loading">Carregando equipes e operadores...</div>
  </div>
</div>


  <div id="modalOperador">
    <div class="janela">
      <h2>Identifique-se</h2>
      <input id="inputNome" type="text" placeholder="Seu nome..." autocomplete="off">
      <input id="inputSenha" type="password" placeholder="Senha de admin">
      <button id="btnEntrar">Entrar</button>
      <div class="msg-erro" id="msgErro"></div>
    </div>
  </div>

  <div id="toast">✅ Sessão encerrada com sucesso.</div>

  <button id="btnTrocarUsuario" class="btn-trocar-usuario" title="Trocar usuário">
    <i class="fas fa-user-switch"></i> Trocar Usuário
  </button>

  <script src="./js/inicializacao.js?v=<?php echo $versao; ?>"></script>
<script src="./js/controle_pausa.js?v=<?php echo $versao; ?>"></script>
<script src="./js/notificacoes_pausa.js?v=<?php echo $versao; ?>"></script>
<script src="./js/expiracao_pausa.js?v=<?php echo $versao; ?>"></script>



</body>
</html>
