<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Deslogar Agentes Evolux</title>
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

  <!-- Google Ads -->
  <script async
    src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044"
    crossorigin="anonymous"></script>

  <!-- Google Analytics -->
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

<!-- GTM (noscript) -->
<noscript>
  <iframe src="https://www.googletagmanager.com/ns.html?id=GTM-K2XFNTVZ"
          height="0" width="0" style="display:none;visibility:hidden"></iframe>
</noscript>

<div class="app">

  <h1>📞 Listagem de Agentes – Deslogar Evolux</h1>

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
      <input type="checkbox" id="agruparFila" checked>
      Agrupar por fila
    </label>

    <label class="chk-agrupar">
      <input type="checkbox" id="mostrarArquivados" checked>
      Mostrar arquivados
    </label>

  </div>

  <!-- CARDS DE FILAS -->
  <div id="cardsFilas" class="cards-filas"></div>

  <!-- LISTAGEM -->
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
  Lista_Agente – Versão 2025.12.31.07
</footer>

<!-- ✅ JS ÚNICO (OFICIAL) -->
<script src="frontend/js/app.js"></script>

</body>
</html>
