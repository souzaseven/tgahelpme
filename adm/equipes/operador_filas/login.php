<?php
session_start();
$erro = $_GET['erro'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Login - Painel de Operadores</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>


    <link rel="icon" href="https://tgameajuda.com/img/principal/bot-tga.webp" type="image/x-icon">

  <style>
    body {
      background: url(https://tgameajuda.com/tgawallpaper/tga/lockscreen.default.jpg) no-repeat center center fixed;
      background-size: cover;
      background-color: black;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .login-container {
      background: rgba(255,255,255,0.20);
      padding: 40px;
      border-radius: 12px;
      width: 100%;
      max-width: 400px;
    }
    h2 { text-align: center; }
    input, button {
      width: 100%;
      padding: 12px;
      margin-top: 12px;
      border-radius: 8px;
      border: 1px solid #ccc;
      font-size: 15px;
    }
    button {
      background: #4361ee;
      color: #fff;
      font-weight: bold;
      cursor: pointer;
      border: none;
    }
    .error {
      color: red;
      text-align: center;
      margin-top: 12px;
      font-weight: bold;
    }
  </style>


    <!-- ✅ Google Tag Manager -->
    <script>
        (function (w, d, s, l, i) {
            w[l] = w[l] || [];
            w[l].push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
            var f = d.getElementsByTagName(s)[0],
                j = d.createElement(s),
                dl = l != 'dataLayer' ? '&l=' + l : '';
            j.async = true;
            j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
            f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', 'GTM-K2XFNTVZ');
    </script>
    <!-- End Google Tag Manager -->

    <!-- ✅ Google Ads (AdSense Global) -->
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044"
        crossorigin="anonymous"></script>

    <!-- ✅ Google Analytics - Tag 1 (G-E7ZNTJSRYR) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-E7ZNTJSRYR"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', 'G-E7ZNTJSRYR');
    </script>

    <!-- ✅ Google Analytics - Tag 2 (G-S8EC5C2WTG) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-S8EC5C2WTG"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', 'G-S8EC5C2WTG');
    </script>

</head>
<body>

<div class="login-container">
  <h2>Login 🔐</h2>

  <?php if ($erro === '1'): ?>
    <div class="error">Usuário ou senha inválidos.</div>
  <?php elseif ($erro === 'perm'): ?>
    <div class="error">Você não tem permissão para acessar este painel.</div>
  <?php elseif ($erro === 'exp'): ?>
    <div class="error">Sessão expirada. Faça login novamente.</div>
  <?php endif; ?>

  <form action="auth.php" method="POST">
    <input type="text" name="usuario" placeholder="Usuário" required>
    <input type="password" name="senha" placeholder="Senha" required>
    <button type="submit">Entrar</button>
  </form>
</div>

</body>
</html>
