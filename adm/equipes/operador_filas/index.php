<?php
require_once __DIR__ . "/protect.php";
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
  <link rel="stylesheet" href="css/style.css?v=1.0.5">

  <!-- Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

<!-- Contador de visitas -->
<img
  alt="visitas"
  src="https://hits.sh/tgameajuda.com/apievoluxcontroledefila.svg?color=007ced&label=visitas&labelColor=FFFFFF"
/>

<!-- HEADER -->
<header class="main-header">
<a href="logout.php" class="btn btn-secondary">
  <i class="fas fa-sign-out-alt"></i> Sair
</a>

  <div class="header-content">

    <div class="logo-area">
      <div class="logo-icon">
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
      <span class="user-logado">👤 Operador</span>

      <button class="btn-refresh" onclick="carregar()">
        <i class="fas fa-sync-alt"></i>
        Atualizar
      </button>
    </div>

  </div>
</header>

<!-- MAIN -->
<main class="container">
<!-- SUMMARY -->
<div class="summary-cards">
  <div class="card status-card">
    <div class="status-icon"><i class="fas fa-users"></i></div>
    <div class="status-info">
      <h3 id="totalOperadores">0</h3>
      <p>Operadores Ativos</p>
    </div>
  </div>

  <div class="card status-card">
    <div class="status-icon"><i class="fas fa-layer-group"></i></div>
    <div class="status-info">
      <h3 id="totalEquipes">0</h3>
      <p>Equipes</p>
    </div>
  </div>

  <div class="card status-card">
    <div class="status-icon"><i class="fas fa-stream"></i></div>
    <div class="status-info">
      <h3 id="totalFilas">0</h3>
      <p>Filas Disponíveis</p>
    </div>
  </div>

  <!-- ✅ Suporte Matriz (Telefone) -->
  <div class="card status-card">
    <div class="status-icon"><i class="fas fa-phone"></i></div>
    <div class="status-info">
      <h3 id="suporteTelefone">0</h3>
      <p>Suporte Matriz (Telefone)</p>
    </div>
  </div>

  <!-- ✅ Chat/Whats Matriz -->
  <div class="card status-card">
    <div class="status-icon"><i class="fas fa-comments"></i></div>
    <div class="status-info">
      <h3 id="suporteChat">0</h3>
      <p>Chat/Whats Matriz</p>
    </div>
  </div>
</div>


  <!-- OPERADORES -->
  <div class="operators-container">

    <div class="section-header">
      <h2><i class="fas fa-list-ul"></i> Gerenciamento por Equipe</h2>

      <div class="view-controls">
        <button class="btn-expand-all" onclick="expandirTodasEquipes()">
          <i class="fas fa-expand-alt"></i> Expandir Todas
        </button>

        <button class="btn-collapse-all" onclick="recolherTodasEquipes()">
          <i class="fas fa-compress-alt"></i> Recolher Todas
        </button>
      </div>
    </div>

    <div id="listaOperadores" class="teams-grid">
      <div class="loading-state">
        <div class="loading-spinner"></div>
        <p>Carregando equipes e operadores...</p>
      </div>
    </div>

  </div>
</main>

<!-- BULK ACTIONS -->
<div class="bulk-actions-bar" id="bulkActions">
  <div class="bulk-content">

    <div class="bulk-info">
      <span>
        <strong id="bulkCount">0</strong> selecionados
      </span>

      <button class="btn btn-secondary" onclick="limparSelecao()">
        <i class="fas fa-eraser"></i> Limpar
      </button>
    </div>

    <div class="bulk-controls">
      <select id="fila_bulk"></select>

      <button class="btn btn-secondary" onclick="editarSelecionados()">
        <i class="fas fa-external-link-alt"></i>
        Editar Selecionados
      </button>

      <button class="btn btn-primary" onclick="aplicarFilaSelecionados()">
        <i class="fas fa-random"></i>
        Aplicar Fila
      </button>
    </div>

  </div>
</div>

<!-- BULK PROGRESS -->
<div class="bulk-progress" id="bulkProgress" style="display:none;">
  <div class="progress-box">
    <span class="progress-text">Aplicando...</span>
    <div class="progress-bar">
      <div class="progress-fill"></div>
    </div>
  </div>
</div>

<!-- JS -->
<!-- Controle automático de versão -->
<script src="js/version-check.js"></script>

<!-- Utilidades -->
<script src="js/modal-confirm.js"></script>
<script src="js/toast.js"></script>

<!-- Bulk -->
<script src="js/bulk-actions.js"></script>

<!-- App principal -->
<script type="module" src="js/app.js?v=1.0.5"></script>

</body>
</html>
