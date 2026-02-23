<?php
require_once __DIR__ . "/protect.php";

// Versão automática baseada no timestamp do arquivo (sem precisar atualizar manualmente)
$v_css = filemtime(__DIR__ . '/css/style.css') ?: '1.0.5';
$v_js  = filemtime(__DIR__ . '/js/app.js')     ?: '1.0.5';

// Nome do usuário logado vindo da sessão
$usuario = htmlspecialchars($_SESSION['usuario'] ?? 'Operador', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gerenciamento de Filas • Evolux</title>

  <!-- Favicon -->
  <link rel="icon" href="https://tgameajuda.com/img/principal/bot-tga.webp" type="image/x-icon">

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- CSS -->
  <link rel="stylesheet" href="css/style.css?v=<?= $v_css ?>">

  <!-- Icons — preload para não bloquear renderização -->
  <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  </noscript>
</head>

<body>

<!-- Contador de visitas (oculto visualmente, ainda registra o hit) -->
<img
  alt=""
  aria-hidden="true"
  style="position:absolute;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:none;"
  src="https://hits.sh/tgameajuda.com/apievoluxcontroledefila.svg?color=007ced&label=visitas&labelColor=FFFFFF"
>

<!-- HEADER -->
<header class="main-header">

  <div class="header-content">

    <div class="logo-area">
      <div class="logo-icon" aria-hidden="true">
        <i class="fas fa-headset"></i>
      </div>
      <div>
        <h1>Gerenciamento de Filas</h1>
        <p class="subtitle">
          Evolux • Painel Administrativo —
          <span id="totalGeral">Carregando...</span>
        </p>
      </div>
    </div>

    <div class="header-actions">
      <span class="user-logado">👤 <?= $usuario ?></span>

      <button class="btn-refresh" onclick="carregar()" aria-label="Atualizar dados">
        <i class="fas fa-sync-alt" aria-hidden="true"></i>
        Atualizar
      </button>

      <a href="logout.php" class="btn btn-secondary" aria-label="Sair do sistema">
        <i class="fas fa-sign-out-alt" aria-hidden="true"></i> Sair
      </a>
    </div>

  </div>
</header>

<!-- MAIN -->
<main class="container">

  <!-- SUMMARY -->
  <div class="summary-cards">

    <div class="card status-card">
      <div class="status-icon" aria-hidden="true"><i class="fas fa-users"></i></div>
      <div class="status-info">
        <span class="status-number" id="totalOperadores">0</span>
        <p>Operadores Ativos</p>
      </div>
    </div>

    <div class="card status-card">
      <div class="status-icon" aria-hidden="true"><i class="fas fa-layer-group"></i></div>
      <div class="status-info">
        <span class="status-number" id="totalEquipes">0</span>
        <p>Equipes</p>
      </div>
    </div>

    <div class="card status-card">
      <div class="status-icon" aria-hidden="true"><i class="fas fa-stream"></i></div>
      <div class="status-info">
        <span class="status-number" id="totalFilas">0</span>
        <p>Filas Disponíveis</p>
      </div>
    </div>

    <div class="card status-card">
      <div class="status-icon" aria-hidden="true"><i class="fas fa-phone"></i></div>
      <div class="status-info">
        <span class="status-number" id="suporteTelefone">0</span>
        <p>Suporte Matriz (Telefone)</p>
      </div>
    </div>

    <div class="card status-card">
      <div class="status-icon" aria-hidden="true"><i class="fas fa-comments"></i></div>
      <div class="status-info">
        <span class="status-number" id="suporteChat">0</span>
        <p>Chat/Whats Matriz</p>
      </div>
    </div>

  </div>

  <!-- OPERADORES -->
  <div class="operators-container">

    <div class="section-header">
      <h2><i class="fas fa-list-ul" aria-hidden="true"></i> Gerenciamento por Equipe</h2>

      <div class="view-controls">
        <button class="btn-expand-all" onclick="expandirTodasEquipes()" aria-label="Expandir todas as equipes">
          <i class="fas fa-expand-alt" aria-hidden="true"></i> Expandir Todas
        </button>

        <button class="btn-collapse-all" onclick="recolherTodasEquipes()" aria-label="Recolher todas as equipes">
          <i class="fas fa-compress-alt" aria-hidden="true"></i> Recolher Todas
        </button>
      </div>
    </div>

    <div id="listaOperadores" class="teams-grid" aria-live="polite" aria-label="Lista de equipes e operadores">
      <div class="loading-state">
        <div class="loading-spinner" role="status" aria-label="Carregando"></div>
        <p>Carregando equipes e operadores...</p>
      </div>
    </div>

  </div>
</main>

<!-- BULK ACTIONS -->
<div class="bulk-actions-bar" id="bulkActions" role="region" aria-label="Ações em massa" aria-hidden="true">
  <div class="bulk-content">

    <div class="bulk-info">
      <span>
        <strong id="bulkCount">0</strong> selecionados
      </span>

      <button class="btn btn-secondary" onclick="limparSelecao()" aria-label="Limpar seleção">
        <i class="fas fa-eraser" aria-hidden="true"></i> Limpar
      </button>
    </div>

    <div class="bulk-controls">
      <label for="fila_bulk" class="sr-only">Selecionar fila para aplicar em massa</label>
      <select id="fila_bulk" aria-label="Fila para aplicar em massa">
        <option value="" disabled selected>Selecione uma fila…</option>
      </select>

      <button class="btn btn-secondary" onclick="editarSelecionados()" aria-label="Abrir links dos selecionados">
        <i class="fas fa-external-link-alt" aria-hidden="true"></i>
        Editar Selecionados
      </button>

      <button class="btn btn-primary" onclick="aplicarFilaSelecionados()" aria-label="Aplicar fila nos selecionados">
        <i class="fas fa-random" aria-hidden="true"></i>
        Aplicar Fila
      </button>
    </div>

  </div>
</div>

<!-- BULK PROGRESS -->
<div class="bulk-progress" id="bulkProgress" style="display:none;" role="status" aria-live="polite">
  <div class="progress-box">
    <span class="progress-text">Aplicando...</span>
    <div class="progress-bar">
      <div class="progress-fill"></div>
    </div>
  </div>
</div>

<!-- JS — defer em todos para não bloquear renderização -->
<script src="js/version-check.js" defer></script>
<script src="js/modal-confirm.js" defer></script>
<script src="js/toast.js"         defer></script>
<script src="js/bulk-actions.js"  defer></script>

<!-- App principal (type=module já tem defer implícito) -->
<script type="module" src="js/app.js?v=<?= $v_js ?>"></script>

</body>
</html>