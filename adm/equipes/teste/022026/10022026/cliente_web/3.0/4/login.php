<?php
/**
 * login.php — TGA Clientes Web
 *
 * Converti o login.html para PHP por três razões principais:
 *  1. Redirecionar automaticamente quem já tem sessão ativa, evitando
 *     mostrar o formulário para quem já está logado.
 *  2. Gerar e validar um token CSRF, protegendo contra requisições
 *     forjadas de outros domínios.
 *  3. Enviar headers de segurança HTTP diretamente no PHP, sem depender
 *     de configuração de servidor (Apache/Nginx).
 *
 * O HTML interno é idêntico ao login.html anterior — nada foi quebrado.
 * O backend/login.php existente continua sendo chamado pelo login.js via
 * fetch, exatamente como antes. Só adicionei a leitura do CSRF_TOKEN lá.
 */

// ── 1. Sessão ──────────────────────────────────────────────────────────────
session_start();

// Se o usuário já está autenticado, mando direto pro painel.
// Isso evita que quem já logou veja o formulário novamente.
if (!empty($_SESSION['usuario_id']) && !empty($_SESSION['admweb'])) {
    header('Location: index.php');
    exit;
}

// ── 2. Token CSRF ──────────────────────────────────────────────────────────
// Gero um token único por sessão. Ele vai para um <meta> no HTML e o
// login.js o envia no header X-CSRF-Token de cada requisição fetch.
// O backend/login.php deve validar esse header antes de processar o login.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ── 3. Headers de segurança ────────────────────────────────────────────────
// Impedem clickjacking, forçam MIME sniffing correto e controlam referrer.
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>TGA • Acesso ao Painel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!--
    Injeto o token CSRF num <meta> para o login.js ler via:
      document.querySelector('meta[name="csrf-token"]').content
    Isso evita expor o token como variável JS global.
  -->
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

  <!-- Fonte -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- Estilo -->
  <link rel="stylesheet" href="assets/css/login.css">

  <!-- Favicon -->
  <link rel="shortcut icon" href="https://tgameajuda.com/img/principal/bot-tga.webp" type="image/x-icon">

  <!-- Google Tag Manager -->
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

  <!-- Partículas de fundo -->
  <div class="bg-particles" aria-hidden="true">
    <span></span><span></span><span></span>
    <span></span><span></span><span></span>
  </div>

  <div class="login-wrapper">
    <div class="login-card">

      <div class="login-header">
        <div class="logo-ring">
          <img src="https://tgameajuda.com/img/principal/bot-tga.webp" alt="TGA">
        </div>
        <h1>Clientes Web</h1>
        <p>Acesso restrito</p>
      </div>

      <form id="loginForm" autocomplete="off" novalidate>

        <div class="form-group" id="group-usuario">
          <label for="usuario">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
            Usuário
          </label>
          <input
            type="text"
            id="usuario"
            name="usuario"
            placeholder="Digite seu usuário"
            required
            autocomplete="username"
            autofocus>
          <span class="field-error" id="err-usuario"></span>
        </div>

        <div class="form-group" id="group-senha">
          <label for="senha">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Senha
          </label>
          <div class="input-wrap">
            <input
              type="password"
              id="senha"
              name="senha"
              placeholder="••••••••"
              required
              autocomplete="current-password">
            <button type="button" class="toggle-senha" id="toggleSenha" aria-label="Mostrar senha" tabindex="-1">
              <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
              <svg class="icon-eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
          <span class="field-error" id="err-senha"></span>
        </div>

        <button type="submit" class="btn-login" id="btnLogin">
          <span class="btn-text">Entrar</span>
          <span class="btn-spinner" aria-hidden="true"></span>
        </button>

        <div class="login-msg" id="loginMsg" role="alert" aria-live="polite"></div>

      </form>

      <footer class="login-footer">
        TGA Sistemas • 2026
      </footer>

    </div>
  </div>

  <script src="assets/js/login.js"></script>
</body>
</html>