<?php
// ============================================================
//  login.php — TGA Carreiras
//  Autenticação de usuários com proteção CSRF e session segura
// ============================================================
session_start();
require_once __DIR__ . '/backend/conexao.php';

/* ── 1. Já autenticado → redireciona ────────────────────── */
if (!empty($_SESSION['usuario_id'])) {
    header('Location: listar.php');
    exit;
}

/* ── 2. Token CSRF ──────────────────────────────────────── */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ── 3. Helper: redirecionamento seguro (anti open-redirect) */
function safeRedirect(string $r): string {
    $r = trim($r);
    if ($r === '')                          return 'listar.php';
    if (preg_match('~^(https?:)?//~i', $r)) return 'listar.php';
    if (strpos($r, '\\') !== false)         return 'listar.php';
    if (strpos($r, '..')  !== false)        return 'listar.php';
    if ($r[0] === '/')                      $r = ltrim($r, '/');
    return $r;
}

$redirect = safeRedirect($_GET['redirect'] ?? 'listar.php');

/* ── 4. Estado inicial ──────────────────────────────────── */
$erro  = '';
$email = '';

/* ── 5. Processamento do formulário (POST) ──────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 5a. Valida CSRF
    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $erro = 'Token de segurança inválido. Recarregue a página e tente novamente.';

    } else {

        $email = trim($_POST['email'] ?? '');
        $senha = trim($_POST['senha'] ?? '');

        // 5b. Validação básica
        if ($email === '' || $senha === '') {
            $erro = 'Informe e-mail e senha para continuar.';

        } else {

            // 5c. Busca usuário
            $stmt = $pdo->prepare("
                SELECT id, nome, email, senha, tipo, ativo, is_admin
                FROM usuarios_carreiras
                WHERE email = :email
                LIMIT 1
            ");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // 5d. Verifica existência e status
            if (!$user || (int)$user['ativo'] !== 1) {
                $erro = 'Usuário não encontrado ou inativo.';

            // 5e. Verifica senha (texto puro — migrar para password_hash futuramente)
            } elseif (!hash_equals($user['senha'], $senha)) {
                $erro = 'Senha incorreta. Verifique e tente novamente.';

            } else {

                // 5f. Sessão autenticada
                $_SESSION['usuario_id']    = (int)$user['id'];
                $_SESSION['usuario_nome']  = $user['nome'];
                $_SESSION['usuario_email'] = $user['email'];
                $_SESSION['usuario_tipo']  = $user['tipo'];
                $_SESSION['is_admin']      = (int)$user['is_admin'];

                // 5g. Atualiza último login
                $pdo->prepare("UPDATE usuarios_carreiras SET ultimo_login = NOW() WHERE id = :id")
                    ->execute([':id' => $user['id']]);

                // 5h. Rotaciona token CSRF após login
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header('Location: ' . safeRedirect($_POST['redirect'] ?? $redirect));
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Entrar — TGA Carreiras</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        /* ── Reset ───────────────────────────────────────────── */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        /* ── Tokens ──────────────────────────────────────────── */
        :root {
            --bg:           #070d18;
            --surface:      #0d1626;
            --surface-2:    #111d2e;
            --border:       rgba(56, 100, 168, 0.16);
            --border-h:     rgba(56, 100, 168, 0.35);
            --text:         #d8e5f7;
            --text-sec:     #8daac8;
            --muted:        #4d6a8a;
            --accent:       #2e7cf6;
            --accent-dim:   rgba(46, 124, 246, 0.14);
            --accent-glow:  rgba(46, 124, 246, 0.25);
            --accent-bright:#5a9eff;
            --danger:       #ef4444;
            --danger-dim:   rgba(239, 68, 68, 0.1);
            --success:      #10b981;
            --radius:       12px;
            --radius-sm:    8px;
            --transition:   0.2s cubic-bezier(0.4, 0, 0.2, 1);
            --font-head:    'Syne', sans-serif;
            --font-body:    'DM Sans', sans-serif;
        }

        /* ── Base ────────────────────────────────────────────── */
        html, body {
            height: 100%;
            font-family: var(--font-body);
            font-size: 14px;
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            overflow: hidden;
        }

        /* ── Background decoration ───────────────────────────── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 700px 500px at 60% 0%, rgba(46,124,246,0.08) 0%, transparent 70%),
                radial-gradient(ellipse 500px 400px at 10% 100%, rgba(99,102,241,0.06) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        /* ── Centering wrapper ───────────────────────────────── */
        .page-center {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        /* ── Login card ──────────────────────────────────────── */
        .login-card {
            width: 100%;
            max-width: 420px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(0,0,0,0.5);
            animation: fadeUp 0.45s ease both;
        }

        .card-accent-bar {
            height: 3px;
            background: linear-gradient(90deg, var(--accent) 0%, #6366f1 50%, #38bdf8 100%);
        }

        .card-body { padding: 36px 32px 32px; }

        /* ── Brand ───────────────────────────────────────────── */
        .login-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 32px;
        }

        .brand-logo {
            width: 38px; height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--accent) 0%, #6366f1 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 20px var(--accent-glow);
            flex-shrink: 0;
        }

        .brand-logo svg { width: 19px; height: 19px; color: #fff; }

        .brand-text { display: flex; flex-direction: column; gap: 1px; }

        .brand-name {
            font-family: var(--font-head);
            font-size: 18px;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.01em;
            line-height: 1;
        }

        .brand-name em { font-style: normal; color: var(--accent-bright); }

        .brand-sub {
            font-size: 10.5px;
            color: var(--muted);
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        /* ── Heading ─────────────────────────────────────────── */
        .login-heading {
            margin-bottom: 6px;
        }

        .login-title {
            font-family: var(--font-head);
            font-size: 22px;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.02em;
        }

        .login-subtitle {
            font-size: 13px;
            color: var(--text-sec);
            font-weight: 300;
            margin-top: 4px;
            margin-bottom: 24px;
        }

        /* ── Alert de erro ───────────────────────────────────── */
        .alert-error {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            background: var(--danger-dim);
            border: 1px solid rgba(239, 68, 68, 0.22);
            border-left: 3px solid var(--danger);
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            animation: shake 0.4s ease;
        }

        .alert-error svg {
            width: 15px; height: 15px;
            color: var(--danger);
            flex-shrink: 0;
            margin-top: 1px;
        }

        .alert-error span {
            font-size: 13px;
            color: var(--text);
            line-height: 1.5;
        }

        /* ── Form ────────────────────────────────────────────── */
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
            margin-bottom: 18px;
        }

        .form-group:last-of-type { margin-bottom: 0; }

        label {
            font-size: 12px;
            font-weight: 500;
            color: var(--text-sec);
            letter-spacing: 0.03em;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 12px; top: 50%;
            transform: translateY(-50%);
            width: 15px; height: 15px;
            color: var(--muted);
            pointer-events: none;
            transition: var(--transition);
        }

        .input-wrap:focus-within .input-icon { color: var(--accent); }

        .input-wrap input {
            width: 100%;
            padding: 11px 13px 11px 38px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-family: var(--font-body);
            font-size: 14px;
            outline: none;
            transition: var(--transition);
            appearance: none;
        }

        .input-wrap input::placeholder { color: var(--muted); }

        .input-wrap input:focus {
            border-color: var(--accent);
            background: #0f1d31;
            box-shadow: 0 0 0 3px var(--accent-dim);
        }

        .input-wrap input:hover:not(:focus) { border-color: var(--border-h); }

        /* Botão mostrar/ocultar senha */
        .btn-toggle-pass {
            position: absolute;
            right: 11px; top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--muted);
            padding: 4px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            transition: var(--transition);
        }

        .btn-toggle-pass:hover { color: var(--text); }
        .btn-toggle-pass svg   { width: 15px; height: 15px; }

        .input-wrap input[type="password"],
        .input-wrap input[type="text"] { padding-right: 40px; }

        /* ── Divider ─────────────────────────────────────────── */
        .form-divider { height: 1px; background: var(--border); margin: 22px 0; }

        /* ── Submit button ───────────────────────────────────── */
        .btn-submit {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 16px var(--accent-glow);
            letter-spacing: 0.01em;
        }

        .btn-submit:hover {
            background: var(--accent-bright);
            box-shadow: 0 4px 22px rgba(46, 124, 246, 0.4);
            transform: translateY(-1px);
        }

        .btn-submit:active { transform: translateY(0); }

        .btn-submit svg { width: 15px; height: 15px; }

        .btn-submit.loading {
            pointer-events: none;
            opacity: 0.75;
        }

        /* ── Footer do card ──────────────────────────────────── */
        .login-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: var(--muted);
        }

        /* ── Keyframes ───────────────────────────────────────── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%       { transform: translateX(-6px); }
            40%       { transform: translateX(6px); }
            60%       { transform: translateX(-4px); }
            80%       { transform: translateX(4px); }
        }

        /* ── Scrollbar ───────────────────────────────────────── */
        ::-webkit-scrollbar       { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }
    </style>
</head>
<body>

<div class="page-center">
    <div class="login-card">

        <!-- Barra de acento -->
        <div class="card-accent-bar"></div>

        <div class="card-body">

            <!-- Brand -->
            <div class="login-brand">
                <div class="brand-logo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                        <path d="M2 17l10 5 10-5M2 12l10 5 10-5" stroke-opacity="0.6"/>
                    </svg>
                </div>
                <div class="brand-text">
                    <span class="brand-name">TGA<em> Carreiras</em></span>
                    <span class="brand-sub">Painel de Sugestões</span>
                </div>
            </div>

            <!-- Título -->
            <div class="login-heading">
                <h1 class="login-title">Bem-vindo de volta</h1>
                <p class="login-subtitle">Faça login para acessar o painel</p>
            </div>

            <!-- Erro -->
            <?php if ($erro): ?>
            <div class="alert-error" role="alert">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <span><?= htmlspecialchars($erro) ?></span>
            </div>
            <?php endif; ?>

            <!-- Formulário -->
            <form method="POST"
                  action="login.php?redirect=<?= urlencode($redirect) ?>"
                  id="loginForm"
                  novalidate>

                <!-- Tokens ocultos -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="redirect"   value="<?= htmlspecialchars($redirect) ?>">

                <!-- E-mail -->
                <div class="form-group">
                    <label for="email">E-mail</label>
                    <div class="input-wrap">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        <input type="email"
                               id="email"
                               name="email"
                               value="<?= htmlspecialchars($email) ?>"
                               placeholder="seu@email.com"
                               autocomplete="email"
                               required>
                    </div>
                </div>

                <!-- Senha -->
                <div class="form-group">
                    <label for="senha">Senha</label>
                    <div class="input-wrap">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input type="password"
                               id="senha"
                               name="senha"
                               placeholder="••••••••"
                               autocomplete="current-password"
                               required>
                        <button type="button"
                                class="btn-toggle-pass"
                                id="togglePass"
                                title="Mostrar/ocultar senha"
                                aria-label="Mostrar senha">
                            <!-- Ícone "olho" — trocado via JS -->
                            <svg id="iconEyeOn" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg id="iconEyeOff" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-divider"></div>

                <!-- Submit -->
                <button type="submit" class="btn-submit" id="btnLogin">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                        <polyline points="10 17 15 12 10 7"/>
                        <line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>
                    <span id="btnLabel">Entrar</span>
                </button>

            </form>

            <!-- Footer -->
            <p class="login-footer">
                TGA Carreiras &copy; <?= date('Y') ?> &mdash; Acesso restrito
            </p>

        </div><!-- /card-body -->
    </div><!-- /login-card -->
</div><!-- /page-center -->

<script>
"use strict";

/* ── Toggle senha visível/oculta ────────────────────────── */
const senhaInput  = document.getElementById("senha");
const toggleBtn   = document.getElementById("togglePass");
const iconOn      = document.getElementById("iconEyeOn");
const iconOff     = document.getElementById("iconEyeOff");

toggleBtn?.addEventListener("click", () => {
    const isPass = senhaInput.type === "password";
    senhaInput.type   = isPass ? "text" : "password";
    iconOn.style.display  = isPass ? "none"  : "";
    iconOff.style.display = isPass ? ""      : "none";
    toggleBtn.setAttribute("aria-label", isPass ? "Ocultar senha" : "Mostrar senha");
});

/* ── Loading state no submit ────────────────────────────── */
document.getElementById("loginForm")?.addEventListener("submit", function () {
    const btn   = document.getElementById("btnLogin");
    const label = document.getElementById("btnLabel");
    if (btn && label) {
        btn.classList.add("loading");
        label.textContent = "Entrando…";
    }
});
</script>

</body>
</html>