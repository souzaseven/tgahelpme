<?php session_start(); ?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Login — TGA Carreiras</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Acesse sua conta na TGA Carreiras e acompanhe suas candidaturas.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">


<!--icone da pagina-->
<link rel="icon" href="http://tgameajuda.com/Projetos/tgacarreiras/img/icone_logo.ico" type="image/x-icon">


<!-- Google AdSense -->
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044" crossorigin="anonymous"></script>

    <!-- Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-S8EC5C2WTG"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-S8EC5C2WTG');
    </script>

    <style>
        /* ════════════════════════════════════════════════════
           VARIÁVEIS
        ════════════════════════════════════════════════════ */
        :root {
            --primary:       #0066FF;
            --primary-dark:  #0052CC;
            --primary-light: #3385FF;
            --bg:            #0F172A;
            --bg-card:       #1E293B;
            --surface:       #334155;
            --text:          #F1F5F9;
            --text-sec:      #CBD5E1;
            --text-muted:    #64748B;
            --border:        #334155;
            --success:       #10B981;
            --danger:        #EF4444;
            --warning:       #F59E0B;
            --shadow:        0 4px 12px rgba(0,0,0,.3);
            --shadow-lg:     0 10px 30px rgba(0,0,0,.4);
            --shadow-xl:     0 20px 40px rgba(0,102,255,.25);
            --radius:        16px;
            --radius-sm:     10px;
            --transition:    all .3s ease;
        }

        [data-theme="light"] {
            --bg:        #F1F5F9;
            --bg-card:   #FFFFFF;
            --surface:   #EDF0F5;
            --text:      #1A202C;
            --text-sec:  #4A5568;
            --text-muted:#718096;
            --border:    #E2E8F0;
            --shadow:    0 4px 12px rgba(0,0,0,.07);
            --shadow-lg: 0 10px 30px rgba(0,0,0,.08);
            --shadow-xl: 0 20px 40px rgba(0,102,255,.12);
        }

        /* ════════════════════════════════════════════════════
           RESET
        ════════════════════════════════════════════════════ */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { overflow-x: hidden; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: background .3s, color .3s;
        }

        /* ════════════════════════════════════════════════════
           HEADER
        ════════════════════════════════════════════════════ */
        header {
            background: var(--bg-card);
            padding: 1rem 0;
            box-shadow: var(--shadow);
            border-bottom: 2px solid var(--primary);
            position: sticky; top: 0; z-index: 100;
        }

        .header-content {
            max-width: 1200px; margin: 0 auto; padding: 0 2rem;
            display: flex; align-items: center; justify-content: space-between;
        }

        .logo-section {
            display: flex; align-items: center; gap: .875rem;
            text-decoration: none; color: var(--text);
        }

        .logo-icon {
            width: 44px; height: 44px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; flex-shrink: 0;
            box-shadow: var(--shadow);
        }

        .logo-text h1 {
            font-size: 1.4rem; font-weight: 800;
            color: var(--text); letter-spacing: -.3px;
        }
        .logo-text p {
            color: var(--text-muted); font-size: .78rem; font-weight: 500;
        }

        .theme-toggle {
            width: 40px; height: 40px;
            border: 1px solid var(--border); border-radius: 9px;
            background: var(--surface); cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; transition: var(--transition);
        }
        .theme-toggle:hover { background: var(--primary); border-color: var(--primary); }

        /* ════════════════════════════════════════════════════
           LAYOUT — Dois painéis
        ════════════════════════════════════════════════════ */
        .main-container {
            flex: 1;
            display: flex;
            align-items: stretch;
            min-height: calc(100vh - 70px);
        }

        /* ── Painel esquerdo (formulário) ────────────────── */
        .form-panel {
            flex: 1;
            display: flex; align-items: center; justify-content: center;
            padding: 3rem 2.5rem;
            overflow-y: auto;
        }

        .login-box {
            width: 100%; max-width: 440px;
            animation: fadeUp .5s ease-out;
        }

        /* ── Painel direito decorativo ───────────────────── */
        .side-panel {
            flex: 0 0 44%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            padding: 3rem 2.5rem;
            position: relative; overflow: hidden;
        }

        .side-panel::before,
        .side-panel::after {
            content: ''; position: absolute; border-radius: 50%;
            filter: blur(60px); pointer-events: none;
        }
        .side-panel::before { top: -15%; left: -10%; width: 300px; height: 300px; background: rgba(255,255,255,.08); }
        .side-panel::after  { bottom: -15%; right: -10%; width: 250px; height: 250px; background: rgba(255,255,255,.05); }

        .side-content { position: relative; z-index: 1; text-align: center; color: #fff; }

        .side-icon {
            width: 90px; height: 90px;
            background: rgba(255,255,255,.15);
            border: 2px solid rgba(255,255,255,.2);
            border-radius: 24px;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.8rem;
            margin: 0 auto 1.75rem;
            backdrop-filter: blur(10px);
        }

        .side-content h2 {
            font-size: 2rem; font-weight: 800;
            margin-bottom: .75rem; letter-spacing: -.5px;
            line-height: 1.2;
        }

        .side-content p {
            font-size: 1rem; opacity: .88;
            max-width: 340px; margin: 0 auto 2.25rem;
            line-height: 1.6;
        }

        /* Stats no side panel */
        .side-stats {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: .75rem; max-width: 320px; margin: 0 auto;
        }

        .side-stat {
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.15);
            border-radius: 14px;
            padding: 1rem .875rem;
            text-align: center;
            backdrop-filter: blur(6px);
            transition: var(--transition);
        }
        .side-stat:hover { background: rgba(255,255,255,.16); transform: translateY(-2px); }

        .side-stat-icon { font-size: 1.5rem; margin-bottom: .3rem; }
        .side-stat-value { font-size: 1.4rem; font-weight: 800; }
        .side-stat-label { font-size: .7rem; opacity: .75; font-weight: 500; text-transform: uppercase; letter-spacing: .5px; }

        /* ════════════════════════════════════════════════════
           CABEÇALHO DO FORM
        ════════════════════════════════════════════════════ */
        .login-header {
            text-align: center; margin-bottom: 2.25rem;
        }

        .login-icon {
            width: 72px; height: 72px;
            margin: 0 auto 1.25rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.2rem;
            box-shadow: var(--shadow-lg);
        }

        .login-header h2 {
            font-size: 1.75rem; font-weight: 800;
            color: var(--text); margin-bottom: .35rem;
            letter-spacing: -.5px;
        }

        .login-header p {
            color: var(--text-muted); font-size: .9rem;
        }

        /* ════════════════════════════════════════════════════
           ALERTAS
        ════════════════════════════════════════════════════ */
        .alert {
            padding: .875rem 1.1rem; border-radius: var(--radius-sm);
            margin-bottom: 1.25rem;
            display: flex; align-items: center; gap: .75rem;
            animation: slideDown .3s;
            font-size: .88rem;
        }
        .alert-success {
            background: rgba(16,185,129,.1);
            border: 1px solid rgba(16,185,129,.3);
            color: var(--success);
        }
        .alert-danger {
            background: rgba(239,68,68,.1);
            border: 1px solid rgba(239,68,68,.3);
            color: var(--danger);
        }
        .alert-icon { font-size: 1.2rem; flex-shrink: 0; }

        /* ════════════════════════════════════════════════════
           FORMULÁRIO
        ════════════════════════════════════════════════════ */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            font-weight: 600; font-size: .84rem;
            color: var(--text-sec);
            margin-bottom: .45rem; display: block;
        }
        .form-label .req { color: var(--danger); }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute; left: .9rem; top: 50%;
            transform: translateY(-50%);
            font-size: 1.15rem; color: var(--text-muted);
            pointer-events: none; z-index: 1;
        }

        .form-input {
            width: 100%;
            padding: .8rem 1rem .8rem 2.85rem;
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            background: var(--surface);
            color: var(--text);
            font-size: .9rem; font-family: inherit;
            transition: var(--transition);
            outline: none;
        }
        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,102,255,.12);
        }
        .form-input::placeholder { color: var(--text-muted); }

        /* Toggle de visibilidade da senha */
        .password-toggle {
            position: absolute; right: .875rem; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            cursor: pointer; font-size: 1.1rem;
            color: var(--text-muted);
            transition: var(--transition);
            z-index: 1; padding: .2rem;
        }
        .password-toggle:hover { color: var(--primary); }

        /* ── Checkbox / Remember ─────────────────────────── */
        .form-options {
            display: flex; align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            flex-wrap: wrap; gap: .5rem;
        }

        .checkbox-group {
            display: flex; align-items: center; gap: .5rem;
        }
        .checkbox-group input[type="checkbox"] {
            width: 17px; height: 17px; cursor: pointer;
            accent-color: var(--primary);
        }
        .checkbox-group label {
            color: var(--text-sec); font-size: .84rem;
            cursor: pointer; font-weight: 500;
        }

        .forgot-link {
            color: var(--primary); font-size: .84rem;
            font-weight: 600; text-decoration: none;
            transition: var(--transition);
        }
        .forgot-link:hover { text-decoration: underline; opacity: .85; }

        /* ── Botão Submit ───────────────────────────────── */
        .btn-submit {
            width: 100%;
            padding: .9rem 1.5rem;
            border-radius: var(--radius-sm);
            font-weight: 700; font-size: .95rem;
            border: none; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            gap: .5rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            box-shadow: 0 4px 16px rgba(0,102,255,.25);
            transition: var(--transition);
            position: relative; overflow: hidden;
            font-family: inherit;
        }
        .btn-submit::before {
            content: ''; position: absolute; top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.18), transparent);
            transition: left .5s;
        }
        .btn-submit:hover::before { left: 100%; }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,102,255,.35);
        }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit .arrow { transition: transform .3s; }
        .btn-submit:hover .arrow { transform: translateX(3px); }

        /* ── Divider ────────────────────────────────────── */
        .divider {
            display: flex; align-items: center;
            margin: 1.75rem 0; color: var(--text-muted); font-size: .8rem;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px; background: var(--border);
        }
        .divider span { padding: 0 .875rem; }

        /* ── Links ──────────────────────────────────────── */
        .form-footer {
            text-align: center;
        }
        .form-footer p {
            color: var(--text-sec); font-size: .9rem;
        }
        .form-footer a {
            color: var(--primary); font-weight: 700;
            text-decoration: none; transition: var(--transition);
        }
        .form-footer a:hover { text-decoration: underline; }

        .back-link {
            display: inline-flex; align-items: center; gap: .4rem;
            color: var(--text-muted); text-decoration: none;
            font-weight: 600; font-size: .85rem;
            transition: var(--transition);
            margin-top: 1.25rem;
        }
        .back-link:hover { color: var(--primary); transform: translateX(-3px); }

        /* ════════════════════════════════════════════════════
           FOOTER
        ════════════════════════════════════════════════════ */
        footer {
            background: var(--bg-card);
            padding: 1.5rem 2rem;
            text-align: center;
            border-top: 1px solid var(--border);
        }
        footer p { color: var(--text-muted); font-size: .8rem; }

        /* ════════════════════════════════════════════════════
           ANIMAÇÕES
        ════════════════════════════════════════════════════ */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ════════════════════════════════════════════════════
           RESPONSIVO — TABLET (≤ 1024px)
        ════════════════════════════════════════════════════ */
        @media (max-width: 1024px) {
            .side-panel { flex: 0 0 40%; padding: 2.5rem 2rem; }
            .side-content h2 { font-size: 1.7rem; }
            .side-icon { width: 76px; height: 76px; font-size: 2.4rem; }
            .form-panel { padding: 2.5rem 2rem; }
        }

        /* ════════════════════════════════════════════════════
           RESPONSIVO — MOBILE (≤ 768px)
        ════════════════════════════════════════════════════ */
        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
                min-height: auto;
            }

            .side-panel {
                flex: none;
                padding: 1.75rem 1.5rem;
                order: -1; /* Fica no topo */
            }

            .side-icon { width: 56px; height: 56px; font-size: 1.8rem; margin-bottom: 1rem; border-radius: 16px; }
            .side-content h2 { font-size: 1.3rem; margin-bottom: .35rem; }
            .side-content p  { font-size: .85rem; margin-bottom: 1.25rem; }

            .side-stats {
                grid-template-columns: repeat(4, 1fr);
                gap: .5rem;
                max-width: 100%;
            }
            .side-stat { padding: .75rem .5rem; border-radius: 10px; }
            .side-stat-icon { font-size: 1.2rem; }
            .side-stat-value { font-size: 1rem; }
            .side-stat-label { font-size: .58rem; }

            .form-panel { padding: 1.75rem 1.25rem; }

            .login-icon { width: 60px; height: 60px; font-size: 1.8rem; border-radius: 16px; }
            .login-header h2 { font-size: 1.5rem; }
            .login-header { margin-bottom: 1.75rem; }

            .header-content { padding: 0 1rem; }
            .logo-text h1 { font-size: 1.2rem; }
            .logo-text p  { font-size: .72rem; }
        }

        /* ════════════════════════════════════════════════════
           RESPONSIVO — MOBILE PEQUENO (≤ 480px)
        ════════════════════════════════════════════════════ */
        @media (max-width: 480px) {
            .header-content { padding: 0 .75rem; }
            .logo-icon { width: 36px; height: 36px; font-size: 1.05rem; }
            .logo-text h1 { font-size: 1.05rem; }
            .theme-toggle { width: 36px; height: 36px; }

            .side-panel { padding: 1.25rem 1rem; }
            .side-icon { width: 46px; height: 46px; font-size: 1.5rem; border-radius: 13px; margin-bottom: .75rem; }
            .side-content h2 { font-size: 1.15rem; }
            .side-content p  { font-size: .8rem; margin-bottom: 1rem; }
            .side-stats { grid-template-columns: repeat(2, 1fr); }

            .form-panel { padding: 1.5rem 1rem; }

            .login-icon { width: 52px; height: 52px; font-size: 1.6rem; border-radius: 14px; margin-bottom: 1rem; }
            .login-header h2 { font-size: 1.3rem; }
            .login-header p  { font-size: .82rem; }

            .form-input { padding: .7rem .85rem .7rem 2.5rem; font-size: .86rem; }
            .input-icon { left: .75rem; font-size: 1rem; }

            .btn-submit { padding: .8rem 1.25rem; font-size: .9rem; }

            .form-options { flex-direction: column; align-items: flex-start; gap: .65rem; }

            footer { padding: 1.25rem .75rem; }
        }

        /* ════════════════════════════════════════════════════
           RESPONSIVO — TELA MUITO PEQUENA (≤ 360px)
        ════════════════════════════════════════════════════ */
        @media (max-width: 360px) {
            .side-panel { padding: 1rem .75rem; }
            .side-content h2 { font-size: 1.05rem; }
            .side-content p { display: none; }

            .form-panel { padding: 1.25rem .75rem; }
            .login-header h2 { font-size: 1.15rem; }
            .form-group { margin-bottom: 1rem; }
        }


/* Login do lado direito */
.form-panel { order: 2; }
.side-panel { order: 1; }

    </style>
</head>
<body>

<!-- ===== HEADER ===== -->
<header>
    <div class="header-content">
        <a href="index.php" class="logo-section" style="text-decoration:none;color:var(--text)">
            <div class="logo-icon">🚀</div>
            <div class="logo-text">
                <h1>TGA Carreiras</h1>
                <p>Conectando talentos a oportunidades</p>
            </div>
        </a>
        <button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema">
            <span id="theme-icon">🌙</span>
        </button>
    </div>
</header>

<!-- ===== CONTEÚDO PRINCIPAL ===== -->
<div class="main-container">

    <!-- Painel do formulário (esquerda) -->
    <div class="form-panel">
        <div class="login-box">

            <div class="login-header">
                <div class="login-icon">🔐</div>
                <h2>Bem-vindo de volta!</h2>
                <p>Entre com sua conta para continuar</p>
            </div>

            <!-- Alertas -->
            <?php if(isset($_GET['erro'])): ?>
            <div class="alert alert-danger">
                <span class="alert-icon">❌</span>
                <span><?= htmlspecialchars($_GET['erro']) ?></span>
            </div>
            <?php endif; ?>

            <?php if(isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <span class="alert-icon">✅</span>
                <span>Cadastro realizado com sucesso! Faça login para continuar.</span>
            </div>
            <?php endif; ?>

            <!-- Formulário -->
            <form action="../backend/auth_candidato.php" method="POST" id="loginForm" novalidate>
                <input type="hidden" name="acao" value="login">

                <div class="form-group">
                    <label class="form-label">E-mail <span class="req">*</span></label>
                    <div class="input-wrapper">
                        <span class="input-icon">📧</span>
                        <input type="email" name="email" class="form-input"
                               placeholder="seu@email.com"
                               required autofocus autocomplete="email">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Senha <span class="req">*</span></label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔑</span>
                        <input type="password" name="senha" id="senhaInput" class="form-input"
                               placeholder="Digite sua senha"
                               required autocomplete="current-password">
                        <button type="button" class="password-toggle" id="toggleSenha" title="Mostrar/ocultar senha">
                            👁️
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <div class="checkbox-group">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Lembrar de mim</label>
                    </div>
                    <a href="#" class="forgot-link">Esqueceu a senha?</a>
                </div>

                <button type="submit" class="btn-submit">
                    <span>Entrar na conta</span>
                    <span class="arrow">→</span>
                </button>
            </form>

            <div class="divider"><span>OU</span></div>

            <div class="form-footer">
                <p>Não tem uma conta? <a href="cadastro.php">Criar conta grátis</a></p>
            </div>

            <div style="text-align:center">
                <a href="index.php" class="back-link">← Voltar para vagas</a>
            </div>

        </div>
    </div>

    <!-- Painel decorativo (direita) -->
    <div class="side-panel">
        <div class="side-content">
            <div class="side-icon">🚀</div>
            <h2>Sua carreira começa<br>aqui</h2>
            <p>Acesse sua conta e acompanhe todas as suas candidaturas em tempo real.</p>

            <div class="side-stats">
                <div class="side-stat">
                    <div class="side-stat-icon">💼</div>
                   <div class="side-stat-value" data-target="500">500+</div>
                    <div class="side-stat-label">Vagas ativas</div>
                </div>
                <div class="side-stat">
                    <div class="side-stat-icon">🏢</div>
                   <div class="side-stat-value" data-target="120">120+</div>
                    <div class="side-stat-label">Empresas</div>
                </div>
                <div class="side-stat">
                    <div class="side-stat-icon">👥</div>
                <div class="side-stat-value" data-target="3000">3k+</div>
                    <div class="side-stat-label">Candidatos</div>
                </div>
                <div class="side-stat">
                    <div class="side-stat-icon">✅</div>
                <div class="side-stat-value" data-target="89" data-suffix="%">89%</div>
                    <div class="side-stat-label">Satisfação</div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ===== FOOTER ===== -->
<footer>
    <p>&copy; <?= date('Y') ?> TGA Carreiras · Todos os direitos reservados</p>
</footer>

<!-- ═══════════════════════════════════════════════════════════
     SCRIPTS
═══════════════════════════════════════════════════════════ -->
<script>
"use strict";

/* ── TEMA ────────────────────────────────────────────────── */
function toggleTheme() {
    const html = document.documentElement;
    const icon = document.getElementById('theme-icon');
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    icon.textContent = next === 'dark' ? '🌙' : '☀️';
    localStorage.setItem('theme', next);
}

/* ── TOGGLE VISIBILIDADE DA SENHA ────────────────────────── */
document.getElementById('toggleSenha')?.addEventListener('click', function() {
    const input = document.getElementById('senhaInput');
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    this.textContent = isPassword ? '🙈' : '👁️';
});

/* ── INIT ────────────────────────────────────────────────── */
window.addEventListener('DOMContentLoaded', () => {
    // Tema
    const saved = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
    document.getElementById('theme-icon').textContent = saved === 'dark' ? '🌙' : '☀️';

    // Auto-hide alertas
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(el => {
            el.style.transition = 'opacity .4s, transform .4s';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-10px)';
            setTimeout(() => el.remove(), 400);
        });
    }, 6000);
});


/* ── ANIMAÇÃO CONTADOR ───────────────────────── */
function animateCounter(el) {
    const target = parseInt(el.getAttribute("data-target"));
    const suffix = el.getAttribute("data-suffix") || "";
    const duration = 1500; // duração da animação
    const startTime = performance.now();

    function update(currentTime) {
        const progress = Math.min((currentTime - startTime) / duration, 1);
        const value = Math.floor(progress * target);

        // Formatação especial para milhar
        if (target >= 1000) {
            el.textContent = Math.floor(value / 1000) + "k+" + suffix;
        } else {
            el.textContent = value + (suffix || "+");
        }

        if (progress < 1) {
            requestAnimationFrame(update);
        } else {
            // Valor final exato
            if (target >= 1000) {
                el.textContent = Math.floor(target / 1000) + "k+" + suffix;
            } else {
                el.textContent = target + (suffix || "+");
            }
        }
    }

    requestAnimationFrame(update);
}

/* ── OBSERVER PARA ANIMAR QUANDO ENTRAR NA TELA ───────── */
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animateCounter(entry.target);
            observer.unobserve(entry.target);
        }
    });
}, { threshold: 0.6 });

document.querySelectorAll(".side-stat-value").forEach(el => {
    observer.observe(el);
});

</script>

</body>
</html>