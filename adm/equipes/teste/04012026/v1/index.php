<?php
require_once 'conexao.php';
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Painel Unificado - Infra TGA</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Fonte -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

  <!-- CSS -->
  <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<!-- =====================================================
     NAVBAR SUPERIOR
===================================================== -->
<nav class="top-nav">
  <div class="logo">🖥 TGA Infra</div>

  <div class="filters">
    <label>
      <input type="checkbox" checked data-target="clientes">
      Clientes
    </label>
    <label>
      <input type="checkbox" checked data-target="infra">
      Infra / Serviços
    </label>
  </div>
</nav>

<!-- =====================================================
     HEADER PRINCIPAL
===================================================== -->
<header class="header">
  <div class="header-left">
    <h1>📡 Controle de Clientes Web</h1>
    <span class="subtitle">Smart Cliente · API · Infraestrutura</span>
  </div>

  <div class="header-right">
    <div class="search-box">
      <span class="icon">🔍</span>
      <input id="busca" placeholder="Buscar por cliente, código ou servidor">
    </div>
    <button type="button" class="btn-primary" onclick="abrirModal()">➕ Novo Cliente</button>
  </div>
</header>

<!-- =====================================================
     CLIENTES (SMART + API)
===================================================== -->
<section id="clientes">

  <!-- SMART CLIENTE -->
  <section class="card">
    <div class="section-header" onclick="toggleGrupo('on')">
      <h2>🟢 Smart Cliente (Mobile ON)</h2>
      <div class="section-info">
        <span class="badge badge-on" id="countOn">0</span>
        <span class="toggle">▾</span>
      </div>
    </div>

    <div class="section-body" id="grupoOn">
      <table>
        <thead>
          <tr>
            <th onclick="ordenar('cod_cliente')">Código</th>
            <th onclick="ordenar('cliente')">Cliente</th>
            <th onclick="ordenar('acesso_server')">Servidor</th>
            <th onclick="ordenar('porta')">Porta</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody id="listaOn"></tbody>
      </table>
    </div>
  </section>

  <!-- API MOBILE OFF -->
  <section class="card">
    <div class="section-header" onclick="toggleGrupo('api')">
      <h2>🔴 API Força de Vendas (Mobile OFF)</h2>
      <div class="section-info">
        <span class="badge badge-off" id="countApi">0</span>
        <span class="toggle">▾</span>
      </div>
    </div>

    <div class="section-body" id="grupoApi">
      <table>
        <thead>
          <tr>
            <th onclick="ordenar('cod_cliente')">Código</th>
            <th onclick="ordenar('cliente')">Cliente</th>
            <th onclick="ordenar('acesso_server')">Servidor</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody id="listaApi"></tbody>
      </table>
    </div>
  </section>

</section>

<!-- =====================================================
     INFRA / SERVIÇOS
===================================================== -->
<section id="infra">
  <div class="infra-grid">
    <?php include 'modules/servidores_ts.php'; ?>
    <?php include 'modules/fv_mobile_on.php'; ?>
    <?php include 'modules/api_mobile_off.php'; ?>
    <?php include 'modules/whatsapp_service.php'; ?>
    <?php include 'modules/login_clientes.php'; ?>
  </div>
</section>

<!-- =====================================================
     MÓDULO LOGIN DE CLIENTES
===================================================== -->
<section class="card" id="modulo-login-clientes" style="display:none;">

  <div class="section-header">
    <h2>🔐 Cadastro de Login de Clientes</h2>

    <div class="section-info">
      <span class="badge badge-on" id="countLogin">0</span>
      <button type="button" class="btn-outline" onclick="voltarPainel()">⬅ Voltar</button>
    </div>
  </div>

  <!-- BUSCA -->
  <div class="search-box" style="max-width:320px;margin:16px 0;">
    <span class="icon">🔍</span>
    <input id="buscaLogin" placeholder="Buscar por código, cliente ou caminho">
  </div>

  <!-- FILTRO STATUS -->
  <div style="display:flex;gap:18px;align-items:center;margin-bottom:14px;">
    <label>
      <input type="radio" name="filtroStatus" value="ATIVO" checked>
      Ativos
    </label>

    <label>
      <input type="radio" name="filtroStatus" value="INATIVO">
      Inativos
    </label>

    <label>
      <input type="radio" name="filtroStatus" value="TODOS">
      Todos
    </label>
  </div>

  <!-- TABELA -->
  <table>
    <thead>
      <tr>
        <th>Código</th>
        <th>Cliente</th>
        <th>Caminho</th>
        <th>Versão</th>
        <th>Status</th>
        <th>Ações</th>
      </tr>
    </thead>
    <tbody id="listaLogin"></tbody>
  </table>

  <div style="margin-top:20px;">
    <button type="button" class="btn-primary" onclick="abrirModalLogin()">➕ Novo Login</button>
  </div>

</section>

<!-- =====================================================
     MODAL CLIENTES (SMART / API)
===================================================== -->
<div class="modal" id="modal">
  <div class="modal-card">
    <div class="modal-header">
      <h3>Cadastro de Cliente</h3>
      <button type="button" class="modal-close" onclick="fecharModal()">✕</button>
    </div>

    <input type="hidden" id="id">

    <div class="modal-grid">
      <div>
        <label>Código</label>
        <input id="cod_cliente">
      </div>

      <div>
        <label>Cliente</label>
        <input id="cliente">
      </div>

      <div>
        <label>Servidor</label>
        <input id="acesso_server">
      </div>

      <div>
        <label>Porta</label>
        <input id="porta">
      </div>
    </div>

    <label>Observações</label>
    <textarea id="observacao"></textarea>

    <div class="modal-actions">
      <button type="button" class="btn-primary" onclick="salvar()">Salvar</button>
      <button type="button" class="btn-outline" onclick="fecharModal()">Cancelar</button>
    </div>
  </div>
</div>

<!-- =====================================================
     MODAL LOGIN CLIENTES
===================================================== -->
<div class="modal" id="modalLogin" style="display:none;">
  <div class="modal-card">

    <div class="modal-header">
      <h3>Cadastro de Login do Cliente</h3>
      <button type="button" class="modal-close" onclick="fecharModalLogin()">✕</button>
    </div>

    <input type="hidden" id="id_login">

    <div class="modal-grid">
      <div>
        <label>Código do Cliente</label>
        <input id="codigo_cliente">
      </div>

      <div>
        <label>Nome do Cliente</label>
        <input id="nome_cliente">
      </div>

      <div>
        <label>Caminho de Acesso</label>
        <input id="caminho_acesso">
      </div>

      <div>
        <label>Versão Padrão</label>
        <input id="versao_padrao" value="25.12">
      </div>

      <div>
        <label>Status</label>
        <select id="status">
          <option value="ATIVO">ATIVO</option>
          <option value="INATIVO">INATIVO</option>
        </select>
      </div>
    </div>

    <div class="modal-actions">
      <button type="button" class="btn-primary" onclick="salvarLogin()">Salvar</button>
      <button type="button" class="btn-outline" onclick="fecharModalLogin()">Cancelar</button>
    </div>

  </div>
</div>

<!-- =====================================================
     SCRIPTS
===================================================== -->
<script>
const CSRF_TOKEN = "<?= $_SESSION['csrf_token'] ?>";

document.querySelectorAll(".filters input").forEach(chk => {
  chk.addEventListener("change", () => {
    const alvo = document.getElementById(chk.dataset.target);
    if (alvo) alvo.style.display = chk.checked ? "block" : "none";
  });
});
</script>

<script src="assets/js/script.js"></script>
<script src="assets/js/login_clientes.js"></script>

</body>
</html>
