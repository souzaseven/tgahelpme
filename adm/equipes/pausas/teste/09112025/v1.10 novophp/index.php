<?php
// ============================================================
// index.php - Painel de Controle de Pausas (v1.6)
// ============================================================
// 🔹 Interface principal em PHP para controle de pausas
// 🔹 Inclui setup dinâmico e integração com backend
// ============================================================

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
$versao = basename(__DIR__);
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
</head>

<body>
  <div class="container">
    <header>
      <h1><i class="fas fa-clock"></i> Controle de Pausa</h1>
      <p class="subtitle">Equipes Matriz</p>
      
      <div id="statusSistema" class="status-sistema">
        <div class="status-item">
          <span class="status-icon status-online"></span>
          <span>Sistema Online</span>
        </div>
        <div class="status-item">
          <span class="status-icon status-online" id="statusBanco"></span>
          <span id="textoBanco">Verificando banco...</span>
        </div>
        <div class="status-item">
          <span class="status-icon status-online" id="statusOperadores"></span>
          <span id="textoOperadores">Carregando operadores...</span>
        </div>
      </div>
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
  <h2 style="display:flex;align-items:center;gap:8px">
    <i class="fas fa-users"></i> Participantes
    <button id="btnToggleEquipes" class="btn-acao" style="margin-left:auto">
      <i class="fas fa-eye"></i> Ver somente minha equipe
    </button>
  </h2>
</div>


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
</body>
</html>
