<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Agentes Evolux</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- CSS -->
  <link rel="stylesheet" href="frontend/css/style.css">

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
  <!-- End Google Tag Manager -->

  <!-- ✅ Google Ads (AdSense Global) -->
  <script async
    src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044"
    crossorigin="anonymous"></script>

  <!-- ✅ Google Analytics - G-E7ZNTJSRYR -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-E7ZNTJSRYR"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-E7ZNTJSRYR');
  </script>

  <!-- ✅ Google Analytics - G-S8EC5C2WTG -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-S8EC5C2WTG"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-S8EC5C2WTG');
  </script>
</head>

<body>

<!-- Google Tag Manager (noscript) -->
<noscript>
  <iframe src="https://www.googletagmanager.com/ns.html?id=GTM-K2XFNTVZ"
          height="0" width="0" style="display:none;visibility:hidden"></iframe>
</noscript>
<!-- End GTM -->

<div class="app">

  <h1>📞 Listagem de Agentes – Evolux</h1>

  <!-- FILTROS -->
  <div class="filtros">

    <select id="filtroStatus">
      <option value="all">Todos os status</option>
      <option value="active">Ativos (Online)</option>
      <option value="inactive">Inativos (Offline)</option>
    </select>

    <input
      type="text"
      id="buscaNome"
      placeholder="Buscar por nome ou login">

    <label class="chk-agrupar">
      <input type="checkbox" id="agruparFila">
      Agrupar por fila
    </label>

  </div>

  <!-- CARDS DE FILAS -->
  <div id="cardsFilas" class="cards-filas"></div>

  <!-- LISTAGEM DE AGENTES -->
  <div id="listaAgentes" class="lista-agentes">
    <div class="loading">Carregando agentes...</div>
  </div>

  <!-- PAGINAÇÃO -->
  <div class="paginacao">
    <button id="prev">◀ Anterior</button>
    <span id="pagina">1</span>
    <button id="next">Próxima ▶</button>
  </div>

</div>

<footer style="text-align:center; font-size:12px; color:#888; margin-top:20px;">
  Lista_Agente – Versão 2025.12.31.05
</footer>

<!-- JS -->
<script src="frontend/js/app.js"></script>
<script src="frontend/js/agente.js"></script>

</body>
</html>
