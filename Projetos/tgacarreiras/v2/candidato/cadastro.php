<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Criar Conta — TGA Carreiras</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Crie sua conta na TGA Carreiras e encontre as melhores oportunidades profissionais.">
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

        .header-right { display: flex; align-items: center; gap: .75rem; }

        .theme-toggle {
            width: 40px; height: 40px;
            border: 1px solid var(--border); border-radius: 9px;
            background: var(--surface); cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; transition: var(--transition);
        }
        .theme-toggle:hover { background: var(--primary); border-color: var(--primary); }

        /* ════════════════════════════════════════════════════
           LAYOUT PRINCIPAL — Dois painéis
        ════════════════════════════════════════════════════ */
        .main-container {
            flex: 1;
            display: flex;
            align-items: stretch;
            min-height: calc(100vh - 70px);
        }

        /* Painel esquerdo decorativo */
        .side-panel {
            flex: 0 0 42%;
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
        .side-panel::before { top: -15%; right: -10%; width: 300px; height: 300px; background: rgba(255,255,255,.08); }
        .side-panel::after  { bottom: -15%; left: -10%; width: 250px; height: 250px; background: rgba(255,255,255,.05); }

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
            max-width: 340px; margin: 0 auto 2rem;
            line-height: 1.6;
        }

        .side-features {
            display: flex; flex-direction: column; gap: .75rem;
            text-align: left; max-width: 300px; margin: 0 auto;
        }

        .side-feature {
            display: flex; align-items: center; gap: .75rem;
            font-size: .88rem; font-weight: 500; opacity: .9;
        }

        .side-feature-icon {
            width: 36px; height: 36px;
            background: rgba(255,255,255,.15);
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: .95rem; flex-shrink: 0;
        }

        /* Painel direito — formulário */
        .form-panel {
            flex: 1;
            display: flex; align-items: center; justify-content: center;
            padding: 2.5rem 2rem;
            overflow-y: auto;
        }

        .register-box {
            width: 100%; max-width: 480px;
            animation: fadeUp .5s ease-out;
        }

        /* ════════════════════════════════════════════════════
           CABEÇALHO DO FORM
        ════════════════════════════════════════════════════ */
        .register-header {
            text-align: center; margin-bottom: 2rem;
        }

        .register-header-icon {
            display: none; /* Só aparece no mobile */
            width: 64px; height: 64px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 18px;
            margin: 0 auto 1rem;
            align-items: center; justify-content: center;
            font-size: 2rem;
            box-shadow: var(--shadow-lg);
        }

        .register-header h2 {
            font-size: 1.75rem; font-weight: 800;
            color: var(--text); margin-bottom: .35rem;
            letter-spacing: -.5px;
        }

        .register-header p {
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
        .alert-danger {
            background: rgba(239,68,68,.1);
            border: 1px solid rgba(239,68,68,.3);
            color: var(--danger);
        }
        .alert-icon { font-size: 1.2rem; flex-shrink: 0; }

        /* ════════════════════════════════════════════════════
           STEPS INDICATOR
        ════════════════════════════════════════════════════ */
        .steps-indicator {
            display: flex; align-items: center; justify-content: center;
            gap: .5rem; margin-bottom: 1.75rem;
        }

        .step-dot {
            width: 10px; height: 10px; border-radius: 50%;
            background: var(--border); transition: var(--transition);
        }
        .step-dot.active { background: var(--primary); width: 28px; border-radius: 5px; }
        .step-dot.done   { background: var(--success); }

        .step-connector {
            width: 20px; height: 2px; background: var(--border);
            border-radius: 1px;
        }

        /* ════════════════════════════════════════════════════
           FORMULÁRIO
        ════════════════════════════════════════════════════ */
        .form-section-title {
            font-size: .7rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .6px;
            color: var(--primary);
            margin-bottom: 1rem; padding-bottom: .4rem;
            border-bottom: 1px solid var(--border);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .875rem;
        }

        .form-group {
            margin-bottom: 1.1rem;
        }

        .form-label {
            font-weight: 600; font-size: .82rem;
            color: var(--text-sec);
            margin-bottom: .4rem; display: block;
        }
        .form-label .req { color: var(--danger); }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute; left: .875rem; top: 50%;
            transform: translateY(-50%);
            font-size: 1.1rem; color: var(--text-muted);
            pointer-events: none; z-index: 1;
        }

        .form-input {
            width: 100%;
            padding: .75rem .9rem .75rem 2.75rem;
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            background: var(--surface);
            color: var(--text);
            font-size: .88rem; font-family: inherit;
            transition: var(--transition);
            outline: none;
        }
        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,102,255,.12);
        }
        .form-input::placeholder { color: var(--text-muted); }

        /* Select com ícone */
        select.form-input {
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748B' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .875rem center;
            padding-right: 2.25rem;
        }

        /* ── Password Strength ──────────────────────────── */
        .password-strength {
            margin-top: .4rem;
            display: none;
        }
        .password-strength.show { display: block; }

        .strength-bar {
            height: 4px; background: var(--border);
            border-radius: 2px; overflow: hidden;
            margin-bottom: .35rem;
        }
        .strength-fill {
            height: 100%; transition: var(--transition);
            border-radius: 2px; width: 0;
        }
        .strength-text {
            font-size: .72rem; font-weight: 700;
        }

        /* ── Requisitos da senha ────────────────────────── */
        .password-reqs {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: .25rem .75rem; margin-top: .5rem;
        }
        .password-req {
            font-size: .72rem; color: var(--text-muted);
            display: flex; align-items: center; gap: .35rem;
            transition: color .2s;
        }
        .password-req.met { color: var(--success); }
        .password-req-icon { font-size: .65rem; }

        /* ── Terms ──────────────────────────────────────── */
        .terms-group {
            display: flex; align-items: flex-start;
            gap: .65rem; margin: 1.25rem 0 1.5rem;
        }
        .terms-group input[type="checkbox"] {
            width: 18px; height: 18px; cursor: pointer;
            margin-top: .15rem; flex-shrink: 0;
            accent-color: var(--primary);
        }
        .terms-group label {
            color: var(--text-sec); font-size: .82rem;
            cursor: pointer; line-height: 1.5;
        }
        .terms-group label a {
            color: var(--primary); font-weight: 600; text-decoration: none;
        }
        .terms-group label a:hover { text-decoration: underline; }

        /* ── Botão Submit ───────────────────────────────── */
        .btn-submit {
            width: 100%;
            padding: .875rem 1.5rem;
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
        .btn-submit:disabled {
            opacity: .55; cursor: not-allowed; transform: none;
        }

        /* ── Divider ────────────────────────────────────── */
        .divider {
            display: flex; align-items: center;
            margin: 1.5rem 0; color: var(--text-muted); font-size: .8rem;
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
            color: var(--text-sec); font-size: .88rem;
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
            margin-top: 1rem;
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
        footer a { color: var(--primary); text-decoration: none; }

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
            .side-panel { flex: 0 0 38%; padding: 2.5rem 2rem; }
            .side-content h2 { font-size: 1.7rem; }
            .side-icon { width: 76px; height: 76px; font-size: 2.4rem; }
        }

        /* ════════════════════════════════════════════════════
           RESPONSIVO — MOBILE (≤ 768px)
           Side panel vira banner compacto no topo
        ════════════════════════════════════════════════════ */
        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
                min-height: auto;
            }

            .side-panel {
                flex: none;
                padding: 2rem 1.5rem;
            }

            .side-icon { width: 60px; height: 60px; font-size: 2rem; margin-bottom: 1rem; border-radius: 16px; }
            .side-content h2 { font-size: 1.4rem; margin-bottom: .4rem; }
            .side-content p  { font-size: .88rem; margin-bottom: 1.25rem; }
            .side-features { gap: .5rem; }
            .side-feature { font-size: .8rem; }
            .side-feature-icon { width: 30px; height: 30px; font-size: .82rem; }

            .form-panel { padding: 1.75rem 1.25rem; }

            .register-header-icon { display: flex; }
            .register-header h2 { font-size: 1.5rem; }

            .header-content { padding: 0 1rem; }
            .logo-text h1 { font-size: 1.2rem; }
            .logo-text p  { font-size: .72rem; }

            .form-row { grid-template-columns: 1fr; gap: 0; }
        }

        /* ════════════════════════════════════════════════════
           RESPONSIVO — MOBILE PEQUENO (≤ 480px)
        ════════════════════════════════════════════════════ */
        @media (max-width: 480px) {
            .header-content { padding: 0 .75rem; }
            .logo-icon { width: 36px; height: 36px; font-size: 1.05rem; }
            .logo-text h1 { font-size: 1.05rem; }
            .theme-toggle { width: 36px; height: 36px; }

            .side-panel { padding: 1.5rem 1.25rem; }
            .side-icon { width: 50px; height: 50px; font-size: 1.6rem; border-radius: 14px; }
            .side-content h2 { font-size: 1.2rem; }
            .side-content p  { font-size: .82rem; }
            .side-features { display: none; } /* Esconde features no mobile pequeno */

            .form-panel { padding: 1.5rem 1rem; }

            .register-header h2 { font-size: 1.3rem; }
            .register-header p  { font-size: .82rem; }
            .register-header-icon { width: 52px; height: 52px; font-size: 1.6rem; border-radius: 14px; }

            .form-input { padding: .65rem .8rem .65rem 2.5rem; font-size: .84rem; }
            .input-icon { left: .75rem; font-size: 1rem; }

            .btn-submit { padding: .8rem 1.25rem; font-size: .88rem; }

            .password-reqs { grid-template-columns: 1fr; }

            footer { padding: 1.25rem .75rem; }
        }

        /* ════════════════════════════════════════════════════
           RESPONSIVO — TELA MUITO PEQUENA (≤ 360px)
        ════════════════════════════════════════════════════ */
        @media (max-width: 360px) {
            .side-panel { padding: 1.25rem 1rem; }
            .side-content h2 { font-size: 1.1rem; }

            .form-panel { padding: 1.25rem .75rem; }
            .register-header h2 { font-size: 1.15rem; }

            .form-group { margin-bottom: .9rem; }
            .form-input { font-size: .82rem; }
        }
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
        <div class="header-right">
            <button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema">
                <span id="theme-icon">🌙</span>
            </button>
        </div>
    </div>
</header>

<!-- ===== CONTEÚDO PRINCIPAL ===== -->
<div class="main-container">

    <!-- Painel lateral decorativo -->
    <div class="side-panel">
        <div class="side-content">
            <div class="side-icon">✨</div>
            <h2>Comece sua jornada<br>profissional</h2>
            <p>Crie sua conta gratuita e tenha acesso a oportunidades exclusivas das melhores empresas.</p>

            <div class="side-features">
                <div class="side-feature">
                    <div class="side-feature-icon">📝</div>
                    <span>Monte seu currículo profissional</span>
                </div>
                <div class="side-feature">
                    <div class="side-feature-icon">🔔</div>
                    <span>Receba alertas de novas vagas</span>
                </div>
                <div class="side-feature">
                    <div class="side-feature-icon">📊</div>
                    <span>Acompanhe suas candidaturas</span>
                </div>
           
            </div>
        </div>
    </div>

    <!-- Painel do formulário -->
    <div class="form-panel">
        <div class="register-box">

            <!-- Ícone que só aparece no mobile -->
            <div class="register-header">
                <div class="register-header-icon">✨</div>
                <h2>Crie sua conta</h2>
                <p>Preencha os dados abaixo para começar</p>
            </div>

            <!-- Alerta de erro -->
            <?php if(isset($_GET['erro'])): ?>
            <div class="alert alert-danger">
                <span class="alert-icon">❌</span>
                <span><?= htmlspecialchars($_GET['erro']) ?></span>
            </div>
            <?php endif; ?>

            <!-- Steps indicator -->
            <div class="steps-indicator">
                <div class="step-dot active" id="dot1"></div>
                <div class="step-connector"></div>
                <div class="step-dot" id="dot2"></div>
                <div class="step-connector"></div>
                <div class="step-dot" id="dot3"></div>
            </div>

            <form action="../backend/auth_candidato.php" method="POST" id="registerForm" novalidate>
                <input type="hidden" name="acao" value="cadastro">

                <!-- ── SEÇÃO 1: Dados pessoais ──────────── -->
                <div class="form-section-title">👤 Dados Pessoais</div>

                <div class="form-group">
                    <label class="form-label">Nome Completo <span class="req">*</span></label>
                    <div class="input-wrapper">
                        <span class="input-icon">👤</span>
                        <input type="text" name="nome" class="form-input"
                               placeholder="Digite seu nome completo"
                               required autofocus autocomplete="name">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">E-mail <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <span class="input-icon">📧</span>
                            <input type="email" name="email" class="form-input"
                                   placeholder="seu@email.com"
                                   required autocomplete="email">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">WhatsApp / Telefone <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <span class="input-icon">📱</span>
                            <input type="text" name="telefone" id="telefone" class="form-input"
                                   placeholder="(00) 00000-0000"
                                   required autocomplete="tel">
                        </div>
                    </div>
                </div>

                <!-- ── SEÇÃO 2: Localização ─────────────── -->
                <div class="form-section-title">📍 Localização</div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Estado <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <span class="input-icon">🗺️</span>
                            <select name="estado" id="estado" class="form-input" required>
                                <option value="">Carregando estados...</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cidade <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <span class="input-icon">🏙️</span>
                            <select name="cidade" id="cidade" class="form-input" required disabled>
                                <option value="">Selecione o estado</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ── SEÇÃO 3: Segurança ───────────────── -->
                <div class="form-section-title">🔒 Segurança</div>

                <div class="form-group">
                    <label class="form-label">Senha <span class="req">*</span></label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔑</span>
                        <input type="password" name="senha" id="senha" class="form-input"
                               placeholder="Crie uma senha forte"
                               required minlength="6" autocomplete="new-password">
                    </div>
                    <div class="password-strength" id="passwordStrength">
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <div class="strength-text" id="strengthText"></div>
                    </div>
                    <div class="password-reqs" id="passwordReqs">
                        <div class="password-req" id="req-length">
                            <span class="password-req-icon">○</span> Mín. 6 caracteres
                        </div>
                        <div class="password-req" id="req-upper">
                            <span class="password-req-icon">○</span> Letra maiúscula
                        </div>
                        <div class="password-req" id="req-lower">
                            <span class="password-req-icon">○</span> Letra minúscula
                        </div>
                        <div class="password-req" id="req-number">
                            <span class="password-req-icon">○</span> Número
                        </div>
                    </div>
                </div>

                <!-- Termos -->
                <div class="terms-group">
                    <input type="checkbox" id="terms" required>
                    <label for="terms">
                        Eu aceito os <a href="#">Termos de Uso</a> e a
                        <a href="#">Política de Privacidade</a> da TGA Carreiras
                    </label>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn-submit" id="submitBtn">
                    <span>Criar minha conta</span>
                    <span>→</span>
                </button>
            </form>

            <div class="divider"><span>OU</span></div>

            <div class="form-footer">
                <p>Já tem uma conta? <a href="login.php">Faça login</a></p>
            </div>

            <div style="text-align:center">
                <a href="index.php" class="back-link">← Voltar para vagas</a>
            </div>

        </div><!-- /register-box -->
    </div><!-- /form-panel -->

</div><!-- /main-container -->

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

/* ── MÁSCARA TELEFONE ────────────────────────────────────── */
document.getElementById('telefone')?.addEventListener('input', function() {
    let v = this.value.replace(/\D/g, '').slice(0, 11);
    if (v.length <= 10) {
        v = v.replace(/^(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
    } else {
        v = v.replace(/^(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
    }
    this.value = v.trim();
});

/* ── FORÇA DA SENHA + Requisitos ─────────────────────────── */
const senhaInput    = document.getElementById('senha');
const strengthDiv   = document.getElementById('passwordStrength');
const strengthFill  = document.getElementById('strengthFill');
const strengthText  = document.getElementById('strengthText');

const reqs = {
    length: document.getElementById('req-length'),
    upper:  document.getElementById('req-upper'),
    lower:  document.getElementById('req-lower'),
    number: document.getElementById('req-number'),
};

senhaInput?.addEventListener('input', function() {
    const s = this.value;

    if (!s.length) {
        strengthDiv.classList.remove('show');
        Object.values(reqs).forEach(el => { el.classList.remove('met'); el.querySelector('.password-req-icon').textContent = '○'; });
        updateDots();
        return;
    }

    strengthDiv.classList.add('show');

    // Check requirements
    const checks = {
        length: s.length >= 6,
        upper:  /[A-Z]/.test(s),
        lower:  /[a-z]/.test(s),
        number: /[0-9]/.test(s),
    };

    for (const [key, met] of Object.entries(checks)) {
        reqs[key].classList.toggle('met', met);
        reqs[key].querySelector('.password-req-icon').textContent = met ? '●' : '○';
    }

    // Strength calculation
    let strength = 0;
    if (checks.length) strength += 25;
    if (checks.upper)  strength += 25;
    if (checks.lower)  strength += 25;
    if (checks.number) strength += 15;
    if (/[^a-zA-Z0-9]/.test(s)) strength += 10;
    strength = Math.min(100, strength);

    let color, text;
    if (strength < 40)       { color = '#EF4444'; text = '🔴 Fraca'; }
    else if (strength < 70)  { color = '#F59E0B'; text = '🟡 Média'; }
    else                     { color = '#10B981'; text = '🟢 Forte'; }

    strengthFill.style.width = strength + '%';
    strengthFill.style.background = color;
    strengthText.style.color = color;
    strengthText.textContent = text;

    updateDots();
});

/* ── STEPS DOTS — visual feedback conforme preenche ──────── */
function updateDots() {
    const nome   = document.querySelector('input[name="nome"]')?.value.trim();
    const email  = document.querySelector('input[name="email"]')?.value.trim();
    const tel    = document.getElementById('telefone')?.value.trim();
    const estado = document.getElementById('estado')?.value;
    const cidade = document.getElementById('cidade')?.value;
    const senha  = document.getElementById('senha')?.value;

    const dot1 = document.getElementById('dot1');
    const dot2 = document.getElementById('dot2');
    const dot3 = document.getElementById('dot3');

    // Step 1: dados pessoais
    const step1Done = nome && email && tel;
    dot1.className = step1Done ? 'step-dot done' : 'step-dot active';

    // Step 2: localização
    const step2Done = estado && cidade;
    dot2.className = step2Done ? 'step-dot done' : (step1Done ? 'step-dot active' : 'step-dot');

    // Step 3: segurança
    const step3Done = senha && senha.length >= 6;
    dot3.className = step3Done ? 'step-dot done' : (step2Done ? 'step-dot active' : 'step-dot');
}

// Atualiza dots em todos os inputs
document.querySelectorAll('.form-input').forEach(inp => {
    inp.addEventListener('input', updateDots);
    inp.addEventListener('change', updateDots);
});

/* ── VALIDAÇÃO DO FORM ───────────────────────────────────── */
document.getElementById('registerForm')?.addEventListener('submit', function(e) {
    if (!document.getElementById('terms').checked) {
        e.preventDefault();
        alert('Você precisa aceitar os Termos de Uso para continuar.');
        return;
    }
});

/* ── IBGE — Estados e Cidades ────────────────────────────── */
const estadoSelect = document.getElementById('estado');
const cidadeSelect = document.getElementById('cidade');

async function carregarEstados() {
    try {
        const resp = await fetch('https://servicodados.ibge.gov.br/api/v1/localidades/estados?orderBy=nome');
        const estados = await resp.json();

        estadoSelect.innerHTML = '<option value="">Selecione o estado</option>';
        estados.forEach(uf => {
            const opt = document.createElement('option');
            opt.value = uf.sigla;
            opt.textContent = `${uf.nome} (${uf.sigla})`;
            estadoSelect.appendChild(opt);
        });
    } catch {
        estadoSelect.innerHTML = '<option value="">Erro ao carregar</option>';
    }
}

estadoSelect?.addEventListener('change', async function() {
    const uf = this.value;
    cidadeSelect.innerHTML = '<option value="">Carregando...</option>';
    cidadeSelect.disabled = true;

    if (!uf) {
        cidadeSelect.innerHTML = '<option value="">Selecione o estado</option>';
        updateDots();
        return;
    }

    try {
        const resp = await fetch(`https://servicodados.ibge.gov.br/api/v1/localidades/estados/${uf}/municipios`);
        const cidades = await resp.json();

        cidadeSelect.innerHTML = '<option value="">Selecione a cidade</option>';
        cidades.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.nome;
            opt.textContent = c.nome;
            cidadeSelect.appendChild(opt);
        });
        cidadeSelect.disabled = false;
    } catch {
        cidadeSelect.innerHTML = '<option value="">Erro ao carregar</option>';
    }

    updateDots();
});

/* ── INIT ────────────────────────────────────────────────── */
window.addEventListener('DOMContentLoaded', () => {
    // Tema
    const saved = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
    document.getElementById('theme-icon').textContent = saved === 'dark' ? '🌙' : '☀️';

    // Carregar estados
    carregarEstados();

    // Auto-hide alertas
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(el => {
            el.style.transition = 'opacity .4s, transform .4s';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-10px)';
            setTimeout(() => el.remove(), 400);
        });
    }, 6000);

    // Dots iniciais
    updateDots();
});
</script>

</body>
</html>