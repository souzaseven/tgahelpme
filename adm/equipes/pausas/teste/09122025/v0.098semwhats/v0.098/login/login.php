<?php
// login.php — Tela de Login (FASE 6)
// Login por equipe → operador
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Controle de Pausas</title>

    <link rel="icon" href="https://tgameajuda.com/img/principal/bot-tga.webp">

    <!-- CSS -->
    <link rel="stylesheet" href="login.css">
<link rel="stylesheet" href="ajustafonte.css">

    <!-- FontAwesome -->
    <link rel="stylesheet" 
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
          
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
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044" crossorigin="anonymous"></script>

<!-- ✅ Google Analytics - Tag 1 (G-E7ZNTJSRYR) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-E7ZNTJSRYR"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-E7ZNTJSRYR');
</script>

<!-- ✅ Google Analytics - Tag 2 (G-S8EC5C2WTG) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-S8EC5C2WTG"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-S8EC5C2WTG');
</script>


</head>
<body>

<button id="btn-preferencias" title="Preferências">⚙️</button>

<div id="menu-preferencias">

    <h3>Preferências de Fonte</h3>

    <div class="pref-item">
        <label>Título H1: <span id="valH1" class="valor-preview"></span></label>
        <input type="range" id="rangeH1" min="20" max="80" value="48">
    </div>

    <div class="pref-item">
        <label>Título H2: <span id="valH2" class="valor-preview"></span></label>
        <input type="range" id="rangeH2" min="16" max="60" value="40">
    </div>
<div class="pref-item">
    <label>Título H3: <span id="valH3" class="valor-preview"></span></label>
    <input type="range" id="rangeH3" min="10" max="40" value="18">
</div>

    <div class="pref-item">
        <label>Parágrafo: <span id="valP" class="valor-preview"></span></label>
        <input type="range" id="rangeP" min="12" max="40" value="22">
    </div>

    <div class="pref-item">
        <label>Largura do Container: <span id="valBox" class="valor-preview"></span></label>
        <input type="range" id="rangeBox" min="200" max="900" value="700">
    </div>

    <div class="pref-item">
        <label>Tamanho dos Cards: <span id="valCards" class="valor-preview"></span></label>
        <input type="range" id="rangeCards" min="10" max="26" value="13">
    </div>

    <button id="btnReset">Restaurar Padrões</button>

</div>

<div class="login-container">


    <div class="login-box">

        <h1><i class="fas fa-users"></i> Controle de Pausas</h1>
        <p class="subtitulo">Escolha sua equipe e depois seu operador</p>

        <!-- ============================================================
             STEP 1 — LISTA DE EQUIPES
        ============================================================ -->
        <div id="stepEquipes" class="step ativo">
            <h3>Selecione sua Equipe</h3>

            <div id="listaEquipes" class="grid-equipes">
                <!-- carregado via JS -->
            </div>
        </div>

        <!-- ============================================================
             STEP 2 — LISTA DE OPERADORES
        ============================================================ -->
        <div id="stepOperadores" class="step">
            <h3 id="tituloEquipe">Equipe selecionada:</h3>

            <div id="listaOperadores" class="grid-operadores">
                <!-- carregado via JS -->
            </div>

            <p id="erroOperador" class="erro oculto">Selecione um operador.</p>

            <button id="btnVoltar" class="voltar-btn">
                <i class="fas fa-arrow-left"></i> Voltar
            </button>

            <button id="btnConfirmar" class="confirmar-btn" disabled>
                <i class="fas fa-check-circle"></i> Entrar
            </button>
        </div>

    </div>
</div>

<!-- Scripts -->
<script src="bootstrap_login.js"></script>
<script src="login.js"></script>
<script src="ajustafonte.js"></script>

</body>
</html>
