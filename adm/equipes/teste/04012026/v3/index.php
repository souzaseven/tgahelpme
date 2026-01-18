<?php
/**
 * Painel Unificado - Clientes Web
 * Versão: v3 (base unificada estável)
 * Autor: Anderson de Souza
 */

require_once __DIR__ . '/backend/conexao.php';
$csrf = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

  <title>Painel Unificado - Clientes Web (v3)</title>

  <!-- Fonte -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- Estilo principal -->
  <link rel="stylesheet" href="assets/css/style.css"/>

  <!-- Favicon -->
  <link rel="shortcut icon" href="https://tgameajuda.com/img/principal/bot-tga.webp" type="image/x-icon">

  <!-- ✅ Google Tag Manager -->
  <script>
    (function(w,d,s,l,i){
      w[l]=w[l]||[];
      w[l].push({'gtm.start': new Date().getTime(), event:'gtm.js'});
      var f=d.getElementsByTagName(s)[0],
          j=d.createElement(s),
          dl=l!='dataLayer'?'&l='+l:'';
      j.async=true;
      j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;
      f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-K2XFNTVZ');
  </script>

  <!-- ✅ Google Ads -->
  <script async
    src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044"
    crossorigin="anonymous"></script>

  <!-- ✅ Google Analytics -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-E7ZNTJSRYR"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-E7ZNTJSRYR');
    gtag('config', 'G-S8EC5C2WTG');
  </script>

  <!-- ✅ Ajustes rápidos para: scroll em qualquer view + collapse do sidebar -->
  <style>
    /* Scroll automático dentro de qualquer tela (view) */
    .content{
      height: calc(100vh - 72px); /* ajusta conforme sua topbar */
      overflow: hidden;
    }
    .view{
      height: 100%;
      overflow: auto;
      padding-right: 6px;
    }

    /* Sidebar collapsed: só ícones */
    .sidebar.collapsed{
      width: 72px;
    }
    .sidebar.collapsed .brand-text,
    .sidebar.collapsed .mi-label,
    .sidebar.collapsed .hint span:nth-child(2),
    .sidebar.collapsed #btnToggleTheme{
      display: none !important;
    }
    .sidebar.collapsed .brand{
      justify-content: center;
    }
    .sidebar.collapsed .menu-item{
      justify-content: center;
      gap: 0;
      padding: 12px 10px;
    }
    .sidebar.collapsed .mi-ico{
      font-size: 18px;
    }

    /* Botão "=" no topo do sidebar */
    .btn-collapse{
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 38px;
      height: 38px;
      border-radius: 12px;
      border: 1px solid rgba(255,255,255,.08);
      background: rgba(255,255,255,.04);
      color: #fff;
      cursor: pointer;
      transition: .2s ease;
    }
    .btn-collapse:hover{
      transform: translateY(-1px);
      background: rgba(255,255,255,.07);
    }
  </style>
</head>

<body>
<div class="app">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-icon">🧩</div>

      <div class="brand-text">
        <strong>Clientes Web</strong>
        <span>Controle Interno</span>
      </div>

      <!-- ✅ Botão "=" para ocultar navbar (colapsar) -->
      <button class="btn-collapse" id="btnCollapseSidebar" type="button" title="Ocultar/Exibir menu">=</button>
    </div>

    <nav class="menu">
      <button class="menu-item active" data-tab="dashboard">
        <span class="mi-ico">📊</span>
        <span class="mi-label">Dashboard</span>
      </button>

      <button class="menu-item" data-tab="servidores">
        <span class="mi-ico">🖥</span>
        <span class="mi-label">Servidores</span>
      </button>

      <button class="menu-item" data-tab="logins">
        <span class="mi-ico">🔐</span>
        <span class="mi-label">Logins Web</span>
      </button>

      <button class="menu-item" data-tab="mobiles">
        <span class="mi-ico">📱</span>
        <span class="mi-label">FV / API MOBILE</span>
      </button>

      <button class="menu-item" data-tab="whatsapp">
        <span class="mi-ico">💬</span>
        <span class="mi-label">WhatsApp</span>
      </button>

      <button class="menu-item" data-tab="pdvoff">
        <span class="mi-ico">🧾</span>
        <span class="mi-label">PDV OFF</span>
      </button>
    </nav>

    <div class="sidebar-footer">
      <div class="hint">
        <span class="dot"></span>
        <span>v3 • Base unificada</span>
      </div>
      <button class="btn ghost" id="btnToggleTheme">🌙 Alternar tema</button>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main">

    <!-- TOPBAR -->
    <header class="topbar">
      <button class="icon-btn" id="btnToggleSidebar">☰</button>

      <div class="topbar-title">
        <h1 id="pageTitle">Dashboard</h1>
        <p id="pageSubtitle">Visão geral e saúde dos acessos</p>
      </div>

      <div class="topbar-actions">
        <button class="btn" id="btnRefresh">↻ Atualizar</button>
      </div>
    </header>

    <!-- CONTENT -->
    <section class="content">

      <!-- DASHBOARD -->
      <div class="view" id="view-dashboard">
        <div class="grid kpis">

          <div class="card kpi">
            <span>🔐 Logins Ativos</span>
            <div class="kpi-value" id="kpiLoginsAtivos">—</div>
            <div class="kpi-sub" id="kpiLoginsTotal"></div>
          </div>

          <div class="card kpi">
            <span>📱 FV SMART CLIENT</span>
            <div class="kpi-value" id="kpimobilesOn">—</div>
            <div class="kpi-sub" id="kpiConexoesTotal"></div>
          </div>

          <div class="card kpi">
            <span>⚙️ API FORÇA DE VENDAS</span>
            <div class="kpi-value" id="kpiApis">—</div>
            <div class="kpi-sub" id="kpiApisSub"></div>
          </div>

          <div class="card kpi">
            <span>💬 API WHATSAPP</span>
            <div class="kpi-value" id="kpiMobile">—</div>
            <div class="kpi-sub" id="kpiMobileSub"></div>
          </div>

          <div class="card kpi">
            <div class="kpi-head">
              <span class="kpi-ico">🧾</span>
              <span class="kpi-title">PDV OFF</span>
            </div>
            <div class="kpi-value" id="kpiPdvOff">—</div>
          </div>

        </div>

        <!-- (Opcional) Tabelas do dashboard, se seu dashboard.js usar -->
        <div class="grid" style="margin-top:14px;">
          <div class="card">
            <h3 style="margin:0 0 10px;">Últimos Logins</h3>
            <div class="table-wrap">
              <table class="table" id="tblDashLogins">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Código</th>
                    <th>Cliente</th>
                    <th>Caminho</th>
                    <th>Versão</th>
                    <th>Status</th>
                    <th>Criado</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>

          <div class="card">
            <h3 style="margin:0 0 10px;">Últimos Mobile</h3>
            <div class="table-wrap">
              <table class="table" id="tblDashConexoes">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Código</th>
                    <th>Cliente</th>
                    <th>Servidor</th>
                    <th>Tipo</th>
                    <th>Obs</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>

        </div>
      </div>

      <!-- LOGINS -->
      <div class="view hidden" id="view-logins">
        <div class="card" id="loginsCard">
          <div class="toolbar">
            <div class="search">
              <input id="loginsSearch" placeholder="Buscar por código, cliente, servidor">
            </div>

            <div class="filters">
              <!-- você pediu 10/50/100/tudo -> seus js vão usar esse id -->
              <select id="loginsLimit" class="select">
                <option value="10">10</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="999999">Tudo</option>
              </select>

              <button class="btn primary" id="btnNovoLogin">➕ Novo Login</button>
            </div>
          </div>

          <div class="table-wrap">
            <table class="table" id="tblLogins">
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
              <tbody></tbody>
            </table>
          </div>

          <div class="pager">
            <span id="loginsInfo" class="muted"></span>
            <div>
              <button class="btn ghost" id="loginsPrev">←</button>
              <span class="badge" id="loginsPage">1</span>
              <button class="btn ghost" id="loginsNext">→</button>
            </div>
          </div>
        </div>
      </div>

      <!-- MOBILE -->
      <div class="view hidden" id="view-mobiles">
        <div class="card">

          <div class="toolbar">
            <div class="search">
              <input id="mobileSearch" placeholder="Buscar por código, cliente ou servidor">
            </div>

            <div class="filters">
              <select id="mobileTipo" class="select">
                <option value="">Todos os tipos</option>
                <option value="FV_SMART_CLIENT">FV Smart Client</option>
                <option value="API_FORCA_DE_VENDA">API Força de Venda</option>
              </select>

              <select id="mobileLimit" class="select">
                <option value="10">10</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="999999">Tudo</option>
              </select>

              <button class="btn primary" id="btnNovoMobile">➕ Novo Mobile</button>
            </div>
          </div>

          <div class="table-wrap">
            <table class="table" id="tblMobile">
              <thead>
                <tr>
                  <th>Código</th>
                  <th>Cliente</th>
                  <th>Servidor</th>
                  <th>Tipo</th>
                  <th>Observação</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>

          <div class="pager">
            <span id="mobileInfo" class="muted"></span>
            <div>
              <button class="btn ghost" id="mobilePrev">←</button>
              <span class="badge" id="mobilePage">1</span>
              <button class="btn ghost" id="mobileNext">→</button>
            </div>
          </div>
        </div>
      </div>

      <!-- SERVIDORES -->
      <div class="view hidden" id="view-servidores">
        <div class="server-sections">

          <!-- 1️⃣ Servidor Principal -->
          <div class="card server-section">
            <h3>🖥️ Servidor Principal</h3>
            <p class="muted">Domínio / Infraestrutura base</p>
            <ul class="server-list">
              <li>
                <strong>SRVAD</strong>
                <span class="tag">QUALLIT</span>
                <small>Servidor principal do domínio</small>
              </li>
            </ul>
          </div>

          <!-- 2️⃣ Servidor Revenda -->
          <div class="card server-section">
            <h3>🗄️ Servidor Revenda</h3>
            <p class="muted">Banco de dados Firebird</p>
            <ul class="server-list">
              <li>
                <strong>SRVFIREBIRD-REVRO</strong>
                <span class="tag">QUALLIT</span>
                <small>Banco de dados Firebird para revendas</small>
              </li>
            </ul>
          </div>

          <!-- 3️⃣ Farm TSPlus – QUALLIT -->
          <div class="card server-section">
            <h3>🧩 Farm TSPlus – QUALLIT</h3>
            <p class="muted">Servidores de Terminal Service (acesso remoto)</p>
            <ul class="server-list grid">
              <li><strong>SRVTSPLUS01</strong><span class="tag">QUALLIT</span></li>
              <li><strong>SRVTSPLUS02</strong><span class="tag">QUALLIT</span></li>
              <li><strong>SRVTSPLUS03</strong><span class="tag">QUALLIT</span></li>
              <li><strong>SRVTSPLUS04</strong><span class="tag">QUALLIT</span></li>
              <li><strong>SRVTSPLUS05</strong><span class="tag">QUALLIT</span></li>
              <li><strong>SRVTSPLUS06</strong><span class="tag">QUALLIT</span></li>
              <li><strong>SRVTSPLUS07</strong><span class="tag">QUALLIT</span></li>
              <li><strong>SRVTSPLUS08</strong><span class="tag">QUALLIT</span></li>
              <li><strong>SRVTSPLUS09</strong><span class="tag">QUALLIT</span></li>
            </ul>
          </div>

          <!-- 4️⃣ Gateway -->
          <div class="card server-section">
            <h3>🌐 Gateway</h3>
            <p class="muted">Gateway – Liberação de IP para acesso à Farm TSPlus</p>
            <ul class="server-list">
              <li>
                <strong>SRVTSPLUSGW01</strong>
                <span class="tag">QUALLIT</span>
                <small>Gateway – Liberação de IP</small>
              </li>
            </ul>
          </div>

          <!-- 5️⃣ Força de Vendas – Smart Client -->
          <div class="card server-section">
            <h3>📱 Força de Vendas – Smart Client</h3>
            <p class="muted">SRVTGAFV01 até SRVTGAFV22</p>
            <ul class="server-list grid">
              <li><strong>SRVTGAFV01</strong></li><li><strong>SRVTGAFV02</strong></li><li><strong>SRVTGAFV03</strong></li><li><strong>SRVTGAFV04</strong></li>
              <li><strong>SRVTGAFV05</strong></li><li><strong>SRVTGAFV06</strong></li><li><strong>SRVTGAFV07</strong></li><li><strong>SRVTGAFV08</strong></li>
              <li><strong>SRVTGAFV09</strong></li><li><strong>SRVTGAFV10</strong></li><li><strong>SRVTGAFV11</strong></li><li><strong>SRVTGAFV12</strong></li>
              <li><strong>SRVTGAFV13</strong></li><li><strong>SRVTGAFV14</strong></li><li><strong>SRVTGAFV15</strong></li><li><strong>SRVTGAFV16</strong></li>
              <li><strong>SRVTGAFV17</strong></li><li><strong>SRVTGAFV18</strong></li><li><strong>SRVTGAFV19</strong></li><li><strong>SRVTGAFV20</strong></li>
              <li><strong>SRVTGAFV21</strong></li><li><strong>SRVTGAFV22</strong></li>
            </ul>
          </div>

          <!-- 6️⃣ API – Força de Vendas -->
          <div class="card server-section">
            <h3>⚙️ API – Força de Vendas</h3>
            <p class="muted">Servidores dedicados à API do sistema Força de Vendas</p>
            <ul class="server-list">
              <li><strong>SRVTGAFV23</strong><span class="tag">QUALLIT</span></li>
              <li><strong>SRVTGAFVAPI01</strong><span class="tag">QUALLIT</span></li>
              <li><strong>SRVTGAFVAPI02</strong><span class="tag">QUALLIT</span></li>
              <li><strong>SRVTGAFVAPI03</strong><span class="tag">QUALLIT</span></li>
              <li><strong>SRVTGAFVAPI04</strong><span class="tag">QUALLIT</span></li>
            </ul>
          </div>

        </div>
      </div>

      <!-- WHATSAPP -->
      <div class="view hidden" id="view-whatsapp">
        <div class="card">

          <div class="toolbar">
            <div class="search">
              <input id="whatsSearch" placeholder="Buscar por código, cliente, servidor">
            </div>

            <div class="filters">
              <select id="whatsLimit" class="select">
                <option value="10">10</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="999999">Tudo</option>
              </select>
              <button class="btn primary" id="btnNovoWhats">➕ Novo WhatsApp</button>
            </div>
          </div>

          <div class="table-wrap">
            <table class="table" id="tblWhats">
              <thead>
                <tr>
                  <th>Código</th>
                  <th>Cliente</th>
                  <th>Servidor</th>
                  <th>Observação</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>

          <div class="pager">
            <span id="whatsInfo" class="muted"></span>
            <div>
              <button class="btn ghost" id="whatsPrev">←</button>
              <span class="badge" id="whatsPage">1</span>
              <button class="btn ghost" id="whatsNext">→</button>
            </div>
          </div>

        </div>
      </div>

      <!-- PDV OFF -->
      <div class="view hidden" id="view-pdvoff">
        <div class="card">

          <div class="toolbar">
            <div class="search">
              <input id="pdvOffSearch" placeholder="Buscar por código, cliente, servidor">
            </div>

            <div class="filters">
              <select id="pdvOffLimit" class="select">
                <option value="10">10</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="999999">Tudo</option>
              </select>

              <button class="btn primary" id="btnNovoPdvOff">➕ Novo PDV OFF</button>
            </div>
          </div>

          <div class="table-wrap">
            <table class="table" id="tblPdvOff">
              <thead>
                <tr>
                  <th>Código</th>
                  <th>Cliente</th>
                  <th>Servidor</th>
                  <th>Caixas</th>
                  <th>Observação</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>

          <div class="pager">
            <span id="pdvOffInfo" class="muted"></span>
            <div>
              <button class="btn ghost" id="pdvOffPrev">←</button>
              <span class="badge" id="pdvOffPage">1</span>
              <button class="btn ghost" id="pdvOffNext">→</button>
            </div>
          </div>

        </div>
      </div>

    </section>
  </main>
</div>

<!-- MODAL GLOBAL -->
<div class="modal-backdrop hidden" id="modalBackdrop">
  <div class="modal">
    <div class="modal-head">
      <h2 id="modalTitle">—</h2>
      <button class="icon-btn" id="btnCloseModal" type="button">✕</button>
    </div>

    <form id="modalForm">
      <input type="hidden" id="mEntity">
      <input type="hidden" id="mId">

      <div class="form-grid" id="modalFields"></div>

      <div class="modal-actions">
        <button class="btn ghost" type="button" id="btnCancelModal">Cancelar</button>
        <button class="btn primary" type="submit">Salvar</button>
      </div>
    </form>
  </div>
</div>

<!-- TOASTS -->
<div class="toasts" id="toasts"></div>

<!-- CSRF GLOBAL -->
<script>
  window.__CSRF__ = <?= json_encode($csrf) ?>;
</script>

<!-- API base -->
<script src="assets/js/api.js"></script>

<!-- Módulos -->
<script src="assets/js/dashboard.js"></script>
<script src="assets/js/logins.js"></script>
<script src="assets/js/mobiles.js"></script>
<script src="assets/js/whatsapp.js"></script>
<script src="assets/js/pdvoff.js"></script>

<!-- APP (orquestrador, SEMPRE ÚLTIMO) -->
<script src="assets/js/app.js"></script>

<!-- ✅ Collapse "=" (funciona mesmo se o app.js não tiver isso ainda) -->
<script>
  (function(){
    const sidebar = document.getElementById('sidebar');
    const btn = document.getElementById('btnCollapseSidebar');
    if (!sidebar || !btn) return;

    // restaura preferencia
    const saved = localStorage.getItem('sidebar_collapsed');
    if (saved === '1') sidebar.classList.add('collapsed');

    btn.addEventListener('click', () => {
      sidebar.classList.toggle('collapsed');
      localStorage.setItem('sidebar_collapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
    });
  })();
</script>

</body>
</html>
