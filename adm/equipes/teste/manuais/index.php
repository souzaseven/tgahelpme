<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>📚 Manuais do Sistema</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="app">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <h2>📘 Manuais</h2>
      <span>Sistema ERP</span>
    </div>

    <div class="sidebar-search">
      <input
        type="text"
        id="buscaManual"
        placeholder="🔍 Buscar por nome ou conteúdo do manual..."
      />
    </div>

    <div class="recentes">
      <h4>🕘 Últimos acessados</h4>
      <div id="listaRecentes"></div>
    </div>

    <nav class="menu" id="menuManuais">
      <p class="loading">Carregando módulos...</p>
    </nav>
  </aside>

  <!-- CONTEÚDO -->
  <main class="content">
    <div class="content-header">
      <h1>Base de Conhecimento</h1>
      <p>Utilize a busca ou navegue pelos módulos</p>
    </div>

    <div class="manual-body">
      <p>
        📌 Os manuais estão organizados por módulo.<br>
        Use a busca para encontrar rapidamente qualquer procedimento.
      </p>
    </div>
  </main>

</div>

<!-- MODAL TXT -->
<div class="modal hidden" id="modalTxt">
  <div class="modal-content">

    <header>
      <div>
        <div id="breadcrumb" class="breadcrumb"></div>
        <h3 id="modalTitulo">Manual</h3>
        <div id="resultadoInfo" class="resultado-info"></div>
      </div>

      <div class="modal-actions">
        <button id="btnCopiarTxt">📋 Copiar</button>
        <button id="btnBaixarTxt">⬇️ Baixar</button>
        <button id="btnFechar">✖</button>
      </div>
    </header>

    <pre id="modalTexto"></pre>
  </div>
</div>

<script src="assets/js/app.js"></script>
</body>
</html>
