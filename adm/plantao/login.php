<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0);

require_once __DIR__ . '/bootstrap.php';

/* Já autenticado → redireciona direto */
if (!empty($_SESSION['admin_logged'])) {
    header('Location: index.php');
    exit;
}

/**
 * Valida a senha do painel.
 * Aceita hash gerado por password_hash() (recomendado) ou texto puro (legado).
 */
function admin_pass_ok(string $input, string $stored): bool
{
    if ($stored === '') {
        return false;
    }
    if (preg_match('/^\$(2y|2a|2b|argon2)/', $stored)) {
        return password_verify($input, $stored);
    }
    return hash_equals($stored, $input);
}

$erro = '';
$now  = time();

$att = $_SESSION['login_attempts'] ?? ['count' => 0, 'blocked_until' => 0];

if (($att['blocked_until'] ?? 0) > $now) {
    $erro = 'Muitas tentativas malsucedidas. Aguarde ' . ($att['blocked_until'] - $now) . ' segundos.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfOk = hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'] ?? '');

    if (!$csrfOk) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $inputUser = trim($_POST['usuario'] ?? '');
        $inputPass = (string)($_POST['senha'] ?? '');

        $adminUser = getenv('ADMIN_USER') ?: '';
        $adminPass = getenv('ADMIN_PASS') ?: '';

        $userOk = $adminUser !== '' && hash_equals($adminUser, $inputUser);
        $passOk = admin_pass_ok($inputPass, $adminPass);

        if ($userOk && $passOk) {
            session_regenerate_id(true);
            unset($_SESSION['login_attempts']);
            $_SESSION['admin_logged'] = true;
            $_SESSION['created_at']   = time();
            header('Location: index.php');
            exit;
        }

        $att['count'] = (int)($att['count'] ?? 0) + 1;
        if ($att['count'] >= 5) {
            $att = ['count' => 0, 'blocked_until' => $now + 300]; // bloqueio de 5 min
        }
        $_SESSION['login_attempts'] = $att;
        usleep(400000); // atrasa a resposta a tentativas incorretas
        $erro = 'Usuário ou senha incorretos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Acesso — Painel de Plantão</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="shortcut icon" href="https://tgameajuda.com/img/principal/bot-tga.webp" type="image/x-icon">

  <style>
    :root {
      --bg-gradient: linear-gradient(135deg, #0a0f1c 0%, #13162b 100%);
      --panel: rgba(19, 22, 43, 0.95);
      --border: rgba(255, 255, 255, 0.08);
      --border-light: rgba(255, 255, 255, 0.15);
      --text: #f0f5ff;
      --text-muted: rgba(240, 245, 255, 0.6);
      --primary: #3b82f6;
      --primary-gradient: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      --primary-glow: 0 0 20px rgba(59, 130, 246, 0.3);
      --danger: #ef4444;
      --radius: 16px;
      --radius-sm: 10px;
      --shadow-lg: 0 20px 50px -12px rgba(0, 0, 0, 0.6);
      --glass-bg: rgba(255, 255, 255, 0.03);
      --transition: 0.25s ease;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      min-height: 100vh;
      font-family: 'Poppins', system-ui, sans-serif;
      background: var(--bg-gradient);
      color: var(--text);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      -webkit-font-smoothing: antialiased;
    }

    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background:
        radial-gradient(600px at 20% 20%, rgba(59, 130, 246, 0.1) 0%, transparent 50%),
        radial-gradient(600px at 80% 80%, rgba(16, 185, 129, 0.06) 0%, transparent 50%);
      pointer-events: none;
      z-index: 0;
    }

    .login-box {
      position: relative;
      z-index: 1;
      width: min(420px, 100%);
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow-lg);
      backdrop-filter: blur(20px);
      overflow: hidden;
      animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    @keyframes slideUp {
      from { opacity: 0; transform: translateY(24px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .login-head {
      padding: 32px 32px 24px;
      text-align: center;
      border-bottom: 1px solid var(--border);
      background: rgba(0,0,0,0.15);
    }

    .login-logo {
      width: 60px;
      height: 60px;
      background: var(--primary-gradient);
      border-radius: 16px;
      display: grid;
      place-items: center;
      font-size: 28px;
      margin: 0 auto 16px;
      box-shadow: var(--primary-glow);
    }

    .login-head h1 {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 4px;
    }

    .login-head p {
      font-size: 13px;
      color: var(--text-muted);
    }

    .login-body {
      padding: 28px 32px 32px;
      display: flex;
      flex-direction: column;
      gap: 18px;
    }

    .field {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .field label {
      font-size: 13px;
      font-weight: 600;
      color: var(--text);
    }

    .field input {
      border: 1px solid var(--border);
      background: var(--glass-bg);
      color: var(--text);
      padding: 12px 16px;
      border-radius: var(--radius-sm);
      font-size: 14px;
      font-family: inherit;
      transition: var(--transition);
      outline: none;
    }

    .field input:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
      background: rgba(59, 130, 246, 0.05);
    }

    .erro {
      background: rgba(239, 68, 68, 0.1);
      border: 1px solid rgba(239, 68, 68, 0.3);
      border-left: 3px solid var(--danger);
      border-radius: var(--radius-sm);
      padding: 12px 16px;
      font-size: 13px;
      color: #fca5a5;
    }

    .btn-entrar {
      width: 100%;
      padding: 13px;
      background: var(--primary-gradient);
      border: none;
      border-radius: var(--radius-sm);
      color: white;
      font-size: 15px;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      transition: var(--transition);
      margin-top: 4px;
    }

    .btn-entrar:hover {
      background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
      transform: translateY(-1px);
      box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
    }

    .btn-entrar:active { transform: translateY(0); }

    :focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
  </style>
</head>
<body>

  <div class="login-box">
    <div class="login-head">
      <div class="login-logo">🛡️</div>
      <h1>Painel de Plantão</h1>
      <p>Acesso restrito à equipe interna</p>
    </div>

    <form class="login-body" method="POST" autocomplete="off">

      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <?php if ($erro !== ''): ?>
        <div class="erro"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <div class="field">
        <label for="usuario">Usuário</label>
        <input
          type="text"
          id="usuario"
          name="usuario"
          placeholder="Digite seu usuário"
          autocomplete="username"
          required
          autofocus
        >
      </div>

      <div class="field">
        <label for="senha">Senha</label>
        <input
          type="password"
          id="senha"
          name="senha"
          placeholder="••••••••"
          autocomplete="current-password"
          required
        >
      </div>

      <button class="btn-entrar" type="submit">Entrar</button>

    </form>
  </div>

</body>
</html>
