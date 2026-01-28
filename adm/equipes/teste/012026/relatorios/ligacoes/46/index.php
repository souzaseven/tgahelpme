<?php
require_once __DIR__ . '/../../../verifica_acesso.php';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Painel de Ligações – Evolux</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- CSS -->
  <link rel="stylesheet" href="assets/css/style.css">

  <!-- Favicon -->
  <link rel="shortcut icon"
        href="https://tgameajuda.com/img/principal/bot-tga.webp"
        type="image/x-icon">

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
</head>

<body>

<!-- ================= HEADER ================= -->
<header class="topbar">
  <h1>📞 Painel de Ligações – Evolux</h1>
  <span class="subtitle">Histórico de chamadas (Call Center & Discador)</span>
</header>

<!-- ================= KPIs ================= -->
<section class="kpis">
  <div class="kpi">
    <span id="kpiTotal">0</span>
    <label>Total</label>
  </div>
  <div class="kpi">
    <span id="kpiAtendidas">0</span>
    <label>Atendidas</label>
  </div>
  <div class="kpi">
    <span id="kpiNaoAtendidas">0</span>
    <label>Não atendidas</label>
  </div>
</section>

<!-- ================= FILTROS ================= -->
<section class="filters">

  <div class="filter-group">
    <label>Tipo de Relatório</label>
    <select id="report_type">
      <option value="callcenter">📞 Call Center</option>
      <option value="dialer">🤖 Discador</option>
    </select>
  </div>

  <div class="filter-group">
    <label>Data Inicial</label>
    <input type="date" id="start_date">
  </div>

  <div class="filter-group">
    <label>Data Final</label>
    <input type="date" id="end_date">
  </div>

  <div class="filter-group">
    <label>Agente</label>
    <select id="agent_id">
      <option value="">Todos</option>
    </select>
  </div>

  <!-- CALL CENTER -->
  <div class="filter-group callcenter-only">
    <label>Fila</label>
    <select id="queue_ids">
      <option value="">Todas</option>
    </select>
  </div>

  <div class="filter-group callcenter-only">
    <label>Número (Call Center)</label>
    <input type="text" id="phone_number" placeholder="Ex: 988261791">
  </div>

  <div class="filter-group dialer-only">
    <label>Status do Contato</label>
    <select id="contact_status">
      <option value="ambos">Ambos</option>
      <option value="sucesso">Sucesso</option>
      <option value="erro">Erro</option>
    </select>
  </div>

  <div class="filter-group action">
    <button id="btnBuscar">🔍 Buscar</button>
  </div>

</section>

<!-- ================= RESULTADOS ================= -->
<section class="results">
  <table>
    <thead>
      <tr>
        <th>Data / Hora</th>
        <th>Agente</th>
        <th>Fila / Campanha</th>
        <th>Número</th>
        <th>Duração</th>
        <th>Finalização</th>
        <th>Ações</th>
      </tr>
    </thead>
    <tbody id="callsBody">
      <tr>
        <td colspan="7" class="empty">
          Nenhuma consulta realizada
        </td>
      </tr>
    </tbody>
  </table>
</section>

<!-- ================= PAGINAÇÃO ================= -->
<footer class="footer">
  <div class="pagination">
    <button onclick="loadCalls(1)">⏮ Primeira</button>
    <button id="btnPrev">◀ Anterior</button>

    <span id="paginationInfo">Página 1</span>

    <button id="btnNext">Próxima ▶</button>
  <button onclick="loadCalls(lastPage)">Última ⏭</button>
 </div>
</footer>


<!-- JS PRINCIPAL -->
<script src="assets/js/filters.js"></script>
<script src="assets/js/queues.js"></script>
<script src="assets/js/agents.js"></script>
<script src="assets/js/ui.js"></script>
<script src="assets/js/app.js"></script>

<script>
  // Recarrega a página a cada 5 minutos
  setTimeout(() => {
    location.reload();
  }, 5 * 60 * 1000);
</script>

</body>
</html>
