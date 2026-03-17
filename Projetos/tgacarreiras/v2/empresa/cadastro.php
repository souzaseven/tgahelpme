<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Cadastro Empresa — TGA Carreiras</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Cadastre sua empresa na TGA Carreiras e encontre os melhores talentos para suas vagas.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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

        .logo-text h1 { font-size: 1.4rem; font-weight: 800; color: var(--text); letter-spacing: -.3px; }
        .logo-text p  { color: var(--text-muted); font-size: .78rem; font-weight: 500; }

        .header-right { display: flex; align-items: center; gap: .75rem; }

        .theme-toggle {
            width: 40px; height: 40px;
            border: 1px solid var(--border); border-radius: 9px;
            background: var(--surface); cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; transition: var(--transition); color: var(--text);
        }
        .theme-toggle:hover { background: var(--primary); border-color: var(--primary); color: #fff; }

        /* ════════════════════════════════════════════════════
           LAYOUT PRINCIPAL
        ════════════════════════════════════════════════════ */
        .main-container {
            flex: 1; display: flex; align-items: stretch;
            min-height: calc(100vh - 70px);
        }

        .side-panel {
            flex: 0 0 42%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            padding: 3rem 2.5rem;
            position: relative; overflow: hidden;
        }
        .side-panel::before, .side-panel::after {
            content: ''; position: absolute; border-radius: 50%;
            filter: blur(60px); pointer-events: none;
        }
        .side-panel::before { top: -15%; right: -10%; width: 300px; height: 300px; background: rgba(255,255,255,.08); }
        .side-panel::after  { bottom: -15%; left: -10%; width: 250px; height: 250px; background: rgba(255,255,255,.05); }

        .side-content { position: relative; z-index: 1; text-align: center; color: #fff; }

        .side-icon {
            width: 90px; height: 90px;
            background: rgba(255,255,255,.15); border: 2px solid rgba(255,255,255,.2);
            border-radius: 24px;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.4rem; margin: 0 auto 1.75rem;
            backdrop-filter: blur(10px);
        }

        .side-content h2 { font-size: 2rem; font-weight: 800; margin-bottom: .75rem; letter-spacing: -.5px; line-height: 1.2; }
        .side-content p   { font-size: 1rem; opacity: .88; max-width: 340px; margin: 0 auto 2rem; line-height: 1.6; }

        .side-features { display: flex; flex-direction: column; gap: .75rem; text-align: left; max-width: 300px; margin: 0 auto; }
        .side-feature  { display: flex; align-items: center; gap: .75rem; font-size: .88rem; font-weight: 500; opacity: .9; }
        .side-feature-icon {
            width: 36px; height: 36px;
            background: rgba(255,255,255,.15); border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: .95rem; flex-shrink: 0;
        }

        .form-panel {
            flex: 1; display: flex; align-items: center; justify-content: center;
            padding: 2.5rem 2rem; overflow-y: auto;
        }

        .register-box { width: 100%; max-width: 520px; animation: fadeUp .5s ease-out; }

        /* ════════════════════════════════════════════════════
           CABEÇALHO DO FORM
        ════════════════════════════════════════════════════ */
        .register-header { text-align: center; margin-bottom: 2rem; }
        .register-header-icon {
            display: none; width: 64px; height: 64px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 18px; margin: 0 auto 1rem;
            align-items: center; justify-content: center;
            font-size: 1.8rem; box-shadow: var(--shadow-lg); color: #fff;
        }
        .register-header h2 { font-size: 1.75rem; font-weight: 800; color: var(--text); margin-bottom: .35rem; letter-spacing: -.5px; }
        .register-header p   { color: var(--text-muted); font-size: .9rem; }

        /* ════════════════════════════════════════════════════
           ALERTAS
        ════════════════════════════════════════════════════ */
        .alert {
            padding: .875rem 1.1rem; border-radius: var(--radius-sm);
            margin-bottom: 1.25rem;
            display: flex; align-items: center; gap: .75rem;
            animation: slideDown .3s; font-size: .88rem;
        }
        .alert-danger  { background: rgba(239,68,68,.1);  border: 1px solid rgba(239,68,68,.3);  color: var(--danger); }
        .alert-success { background: rgba(16,185,129,.1);  border: 1px solid rgba(16,185,129,.3); color: var(--success); }
        .alert-warning { background: rgba(245,158,11,.1);  border: 1px solid rgba(245,158,11,.3); color: var(--warning); }
        .alert-info    { background: rgba(0,102,255,.1);   border: 1px solid rgba(0,102,255,.3);  color: var(--primary-light); }

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
        .step-connector  { width: 20px; height: 2px; background: var(--border); border-radius: 1px; }

        /* ════════════════════════════════════════════════════
           FORMULÁRIO
        ════════════════════════════════════════════════════ */
        .form-section-title {
            font-size: .7rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .6px;
            color: var(--primary);
            margin-bottom: 1rem; padding-bottom: .4rem;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: .5rem;
        }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: .875rem; }
        .form-group { margin-bottom: 1.1rem; }

        .form-label { font-weight: 600; font-size: .82rem; color: var(--text-sec); margin-bottom: .4rem; display: block; }
        .form-label .req { color: var(--danger); }
        .form-label .opt { color: var(--text-muted); font-weight: 400; }

        .input-wrapper { position: relative; }

        .input-icon {
            position: absolute; left: .875rem; top: 50%;
            transform: translateY(-50%);
            font-size: .95rem; color: var(--text-muted);
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
        .form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0,102,255,.12); }
        .form-input::placeholder { color: var(--text-muted); }
        .form-input:read-only { opacity: .7; cursor: default; }
        .form-input.input-success { border-color: var(--success); box-shadow: 0 0 0 3px rgba(16,185,129,.12); }
        .form-input.input-error   { border-color: var(--danger);  box-shadow: 0 0 0 3px rgba(239,68,68,.12); }

        select.form-input {
            cursor: pointer; appearance: none; -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748B' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right .875rem center;
            padding-right: 2.25rem;
        }

        /* ── CNPJ Input Group ───────────────────────────── */
        .cnpj-input-group { display: flex; gap: .5rem; }
        .cnpj-input-group .input-wrapper { flex: 1; }

        .btn-consultar {
            padding: .75rem 1.1rem;
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--primary);
            background: transparent;
            color: var(--primary);
            font-size: .8rem; font-weight: 700;
            font-family: inherit; cursor: pointer;
            white-space: nowrap;
            transition: var(--transition);
            display: flex; align-items: center; gap: .5rem;
        }
        .btn-consultar:hover { background: var(--primary); color: #fff; }
        .btn-consultar:disabled { opacity: .45; cursor: not-allowed; }
        .btn-consultar.loading { pointer-events: none; }

        .btn-consultar .spinner {
            display: none; width: 16px; height: 16px;
            border: 2px solid transparent; border-top-color: currentColor;
            border-radius: 50%; animation: spin .6s linear infinite;
        }
        .btn-consultar.loading .spinner { display: inline-block; }
        .btn-consultar.loading .btn-text { display: none; }

        .cnpj-validation-icon {
            position: absolute; right: .875rem; top: 50%;
            transform: translateY(-50%); font-size: 1rem;
        }

        /* Feedback CNPJ */
        .cnpj-feedback {
            margin-top: .5rem; font-size: .78rem; font-weight: 600;
            display: none; align-items: center; gap: .4rem;
            animation: slideDown .3s;
        }
        .cnpj-feedback.show { display: flex; }
        .cnpj-feedback.success { color: var(--success); }
        .cnpj-feedback.error   { color: var(--danger); }
        .cnpj-feedback.warning { color: var(--warning); }

        /* ── Password Strength ──────────────────────────── */
        .password-strength { margin-top: .4rem; display: none; }
        .password-strength.show { display: block; }
        .strength-bar { height: 4px; background: var(--border); border-radius: 2px; overflow: hidden; margin-bottom: .35rem; }
        .strength-fill { height: 100%; transition: var(--transition); border-radius: 2px; width: 0; }
        .strength-text { font-size: .72rem; font-weight: 700; }

        .password-reqs { display: grid; grid-template-columns: 1fr 1fr; gap: .25rem .75rem; margin-top: .5rem; }
        .password-req { font-size: .72rem; color: var(--text-muted); display: flex; align-items: center; gap: .35rem; transition: color .2s; }
        .password-req.met { color: var(--success); }

        /* ── Terms ──────────────────────────────────────── */
        .terms-group { display: flex; align-items: flex-start; gap: .65rem; margin: 1.25rem 0 1.5rem; }
        .terms-group input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; margin-top: .15rem; flex-shrink: 0; accent-color: var(--primary); }
        .terms-group label { color: var(--text-sec); font-size: .82rem; cursor: pointer; line-height: 1.5; }
        .terms-group label a { color: var(--primary); font-weight: 600; text-decoration: none; }
        .terms-group label a:hover { text-decoration: underline; }

        /* ── Botão Submit ───────────────────────────────── */
        .btn-submit {
            width: 100%; padding: .875rem 1.5rem;
            border-radius: var(--radius-sm);
            font-weight: 700; font-size: .95rem;
            border: none; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: .5rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            box-shadow: 0 4px 16px rgba(0,102,255,.25);
            transition: var(--transition);
            position: relative; overflow: hidden; font-family: inherit;
        }
        .btn-submit::before {
            content: ''; position: absolute; top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.18), transparent);
            transition: left .5s;
        }
        .btn-submit:hover::before { left: 100%; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,102,255,.35); }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit:disabled { opacity: .55; cursor: not-allowed; transform: none; }

        /* ── Divider & Links ────────────────────────────── */
        .divider { display: flex; align-items: center; margin: 1.5rem 0; color: var(--text-muted); font-size: .8rem; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }
        .divider span { padding: 0 .875rem; }

        .form-footer { text-align: center; }
        .form-footer p { color: var(--text-sec); font-size: .88rem; }
        .form-footer a { color: var(--primary); font-weight: 700; text-decoration: none; transition: var(--transition); }
        .form-footer a:hover { text-decoration: underline; }

        .back-link {
            display: inline-flex; align-items: center; gap: .4rem;
            color: var(--text-muted); text-decoration: none;
            font-weight: 600; font-size: .85rem;
            transition: var(--transition); margin-top: 1rem;
        }
        .back-link:hover { color: var(--primary); transform: translateX(-3px); }

        /* ════════════════════════════════════════════════════
           MODAL — CNPJ Duplicado / Não Encontrado
           (Mesmo padrão do seu sistema de consulta)
        ════════════════════════════════════════════════════ */
        .modal-overlay {
            position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,.75);
            display: flex; justify-content: center; align-items: center;
            z-index: 10000; animation: fadeIn .3s ease;
            padding: 20px;
        }
        .modal-content {
            background: var(--bg-card);
            border-radius: 16px; padding: 0;
            max-width: 560px; width: 95%;
            max-height: 90vh; overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,.5);
            border: 2px solid var(--border);
            animation: slideIn .3s ease;
        }
        .modal-header-bar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid var(--border);
            border-radius: 14px 14px 0 0;
        }
        .modal-header-bar.modal-danger  { background: linear-gradient(135deg, var(--danger), #DC2626); color: #fff; }
        .modal-header-bar.modal-warning { background: linear-gradient(135deg, var(--warning), #D97706); color: #fff; }
        .modal-header-bar.modal-success { background: linear-gradient(135deg, var(--success), #059669); color: #fff; }

        .modal-header-bar h3 {
            margin: 0; color: #fff; display: flex; align-items: center; gap: 10px;
            font-size: 1.15rem; font-weight: 700;
        }
        .modal-close-btn {
            background: rgba(255,255,255,.2); border: none; color: #fff;
            font-size: 1rem; cursor: pointer; padding: 8px;
            border-radius: 8px; transition: var(--transition);
            width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;
        }
        .modal-close-btn:hover { background: rgba(255,255,255,.35); transform: rotate(90deg); }

        .modal-body-content { padding: 25px; }
        .modal-body-content .modal-icon-big {
            width: 70px; height: 70px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem; font-size: 1.8rem; color: #fff;
        }
        .modal-icon-big.bg-danger  { background: var(--danger); }
        .modal-icon-big.bg-warning { background: var(--warning); }

        .modal-body-content h4 { text-align: center; color: var(--text); font-size: 1.1rem; margin-bottom: .5rem; }
        .modal-body-content p  { text-align: center; color: var(--text-sec); font-size: .88rem; line-height: 1.6; margin-bottom: 1rem; }

        .modal-cnpj-display {
            text-align: center; font-size: 1.2rem; font-weight: 700;
            color: var(--primary);
            background: rgba(0,102,255,.08); padding: 10px 16px;
            border-radius: 8px; border: 2px dashed var(--primary);
            margin: 0 auto 1.25rem; max-width: 280px;
        }

        .modal-motivos { margin-bottom: 1.25rem; }
        .modal-motivos h5 { color: var(--primary); font-size: .85rem; margin-bottom: .65rem; display: flex; align-items: center; gap: .5rem; }
        .modal-motivo-item {
            display: flex; align-items: flex-start; gap: .65rem;
            padding: .6rem .75rem; margin-bottom: .4rem;
            background: rgba(0,102,255,.04); border-radius: 8px;
            border-left: 3px solid var(--primary);
            font-size: .82rem; color: var(--text-sec); line-height: 1.4;
        }
        .modal-motivo-item i { color: var(--primary); margin-top: 2px; flex-shrink: 0; }

        .modal-actions {
            display: grid; grid-template-columns: 1fr 1fr; gap: .75rem;
            margin-top: 1.25rem;
        }
        .modal-btn {
            display: flex; align-items: center; justify-content: center; gap: .5rem;
            padding: .75rem 1rem; border-radius: var(--radius-sm);
            font-weight: 700; font-size: .85rem; font-family: inherit;
            text-decoration: none; border: none; cursor: pointer;
            transition: var(--transition);
        }
        .modal-btn-primary { background: var(--primary); color: #fff; }
        .modal-btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); }
        .modal-btn-secondary { background: var(--surface); color: var(--text); border: 1px solid var(--border); }
        .modal-btn-secondary:hover { background: var(--border); transform: translateY(-2px); }

        .modal-footer-bar {
            padding: 15px 25px; border-top: 1px solid var(--border);
            text-align: right; border-radius: 0 0 14px 14px;
        }

        /* ════════════════════════════════════════════════════
           FOOTER
        ════════════════════════════════════════════════════ */
        footer {
            background: var(--bg-card); padding: 1.5rem 2rem;
            text-align: center; border-top: 1px solid var(--border);
        }
        footer p { color: var(--text-muted); font-size: .8rem; }

        /* ════════════════════════════════════════════════════
           ANIMAÇÕES
        ════════════════════════════════════════════════════ */
        @keyframes fadeUp   { from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:translateY(0); } }
        @keyframes slideDown{ from { opacity:0; transform:translateY(-10px);} to { opacity:1; transform:translateY(0); } }
        @keyframes fadeIn   { from { opacity:0; } to { opacity:1; } }
        @keyframes slideIn  { from { opacity:0; transform:translateY(-30px) scale(.95); } to { opacity:1; transform:translateY(0) scale(1); } }
        @keyframes spin     { to { transform:rotate(360deg); } }

        /* ════════════════════════════════════════════════════
           RESPONSIVO
        ════════════════════════════════════════════════════ */
        @media (max-width:1024px) {
            .side-panel { flex:0 0 38%; padding:2.5rem 2rem; }
            .side-content h2 { font-size:1.7rem; }
            .side-icon { width:76px; height:76px; font-size:2rem; }
        }

        @media (max-width:768px) {
            .main-container { flex-direction:column; min-height:auto; }
            .side-panel { flex:none; padding:2rem 1.5rem; }
            .side-icon { width:60px; height:60px; font-size:1.8rem; margin-bottom:1rem; border-radius:16px; }
            .side-content h2 { font-size:1.4rem; margin-bottom:.4rem; }
            .side-content p  { font-size:.88rem; margin-bottom:1.25rem; }
            .side-features { gap:.5rem; }
            .side-feature { font-size:.8rem; }
            .side-feature-icon { width:30px; height:30px; font-size:.82rem; }
            .form-panel { padding:1.75rem 1.25rem; }
            .register-header-icon { display:flex; }
            .register-header h2 { font-size:1.5rem; }
            .header-content { padding:0 1rem; }
            .logo-text h1 { font-size:1.2rem; }
            .logo-text p  { font-size:.72rem; }
            .form-row { grid-template-columns:1fr; gap:0; }
            .modal-actions { grid-template-columns:1fr; }
        }

        @media (max-width:480px) {
            .header-content { padding:0 .75rem; }
            .logo-icon { width:36px; height:36px; font-size:1.05rem; }
            .logo-text h1 { font-size:1.05rem; }
            .theme-toggle { width:36px; height:36px; }
            .side-panel { padding:1.5rem 1.25rem; }
            .side-icon { width:50px; height:50px; font-size:1.4rem; border-radius:14px; }
            .side-content h2 { font-size:1.2rem; }
            .side-content p  { font-size:.82rem; }
            .side-features { display:none; }
            .form-panel { padding:1.5rem 1rem; }
            .register-header h2 { font-size:1.3rem; }
            .register-header p  { font-size:.82rem; }
            .register-header-icon { width:52px; height:52px; font-size:1.4rem; border-radius:14px; }
            .form-input { padding:.65rem .8rem .65rem 2.5rem; font-size:.84rem; }
            .input-icon { left:.75rem; font-size:.85rem; }
            .btn-submit { padding:.8rem 1.25rem; font-size:.88rem; }
            .btn-consultar { padding:.65rem .75rem; font-size:.75rem; }
            .password-reqs { grid-template-columns:1fr; }
            footer { padding:1.25rem .75rem; }
            .cnpj-input-group { flex-direction:column; }
        }

        @media (max-width:360px) {
            .side-panel { padding:1.25rem 1rem; }
            .side-content h2 { font-size:1.1rem; }
            .form-panel { padding:1.25rem .75rem; }
            .register-header h2 { font-size:1.15rem; }
            .form-group { margin-bottom:.9rem; }
            .form-input { font-size:.82rem; }
        }
    </style>
</head>
<body>

<!-- ===== HEADER ===== -->
<header>
    <div class="header-content">
        <a href="../public/index.php" class="logo-section" style="text-decoration:none;color:var(--text)">
            <div class="logo-icon"><i class="fas fa-rocket"></i></div>
            <div class="logo-text">
                <h1>TGA Carreiras</h1>
                <p>Conectando talentos a oportunidades</p>
            </div>
        </a>
        <div class="header-right">
            <button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema">
                <i id="theme-icon" class="fas fa-moon"></i>
            </button>
        </div>
    </div>
</header>

<!-- ===== CONTEÚDO PRINCIPAL ===== -->
<div class="main-container">

    <!-- Painel lateral -->
    <div class="side-panel">
        <div class="side-content">
            <div class="side-icon"><i class="fas fa-building"></i></div>
            <h2>Encontre os melhores<br>talentos</h2>
            <p>Cadastre sua empresa gratuitamente e publique vagas para alcançar milhares de candidatos qualificados.</p>
            <div class="side-features">
                <div class="side-feature">
                    <div class="side-feature-icon"><i class="fas fa-clipboard-list"></i></div>
                    <span>Publique vagas ilimitadas</span>
                </div>
                <div class="side-feature">
                    <div class="side-feature-icon"><i class="fas fa-users"></i></div>
                    <span>Acesse currículos dos candidatos</span>
                </div>
                <div class="side-feature">
                    <div class="side-feature-icon"><i class="fas fa-chart-bar"></i></div>
                    <span>Gerencie candidaturas em um só lugar</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Painel do formulário -->
    <div class="form-panel">
        <div class="register-box">

            <div class="register-header">
                <div class="register-header-icon"><i class="fas fa-building"></i></div>
                <h2>Cadastre sua empresa</h2>
                <p>Digite o CNPJ e preencheremos os dados automaticamente</p>
            </div>

            <!-- Alerta PHP -->
            <?php if(isset($_GET['erro'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($_GET['erro']) ?></span>
            </div>
            <?php endif; ?>

            <!-- Alerta JS dinâmico -->
            <div class="alert alert-info" id="cnpjAlert" style="display:none;">
                <i id="cnpjAlertIcon" class="fas fa-info-circle"></i>
                <span id="cnpjAlertText"></span>
            </div>

            <!-- Steps -->
            <div class="steps-indicator">
                <div class="step-dot active" id="dot1"></div>
                <div class="step-connector"></div>
                <div class="step-dot" id="dot2"></div>
                <div class="step-connector"></div>
                <div class="step-dot" id="dot3"></div>
                <div class="step-connector"></div>
                <div class="step-dot" id="dot4"></div>
            </div>

            <form action="../backend/auth_empresa.php" method="POST" id="registerForm" novalidate>
                <input type="hidden" name="acao" value="cadastro">

                <!-- ── SEÇÃO 1: CNPJ + Dados da Empresa ── -->
                <div class="form-section-title">
                    <i class="fas fa-building"></i> Dados da Empresa
                </div>

                <div class="form-group">
                    <label class="form-label">CNPJ <span class="req">*</span></label>
                    <div class="cnpj-input-group">
                        <div class="input-wrapper">
                            <i class="fas fa-id-card input-icon"></i>
                            <input type="text" name="cnpj" id="cnpj" class="form-input"
                                   placeholder="00.000.000/0000-00"
                                   required maxlength="18" autocomplete="off">
                            <span id="cnpjValidationIcon" class="cnpj-validation-icon"></span>
                        </div>
                        <button type="button" class="btn-consultar" id="btnConsultar" disabled>
                            <span class="spinner"></span>
                            <span class="btn-text"><i class="fas fa-search"></i> Consultar</span>
                        </button>
                    </div>
                    <div class="cnpj-feedback" id="cnpjFeedback">
                        <span id="cnpjFeedbackText"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Razão Social <span class="req">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-landmark input-icon"></i>
                        <input type="text" name="razao_social" id="razao_social" class="form-input"
                               placeholder="Será preenchido ao consultar o CNPJ" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nome Fantasia <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-store input-icon"></i>
                            <input type="text" name="nome_fantasia" id="nome_fantasia" class="form-input"
                                   placeholder="Preenchido automaticamente" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Setor / Ramo <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-industry input-icon"></i>
                            <select name="setor" id="setor" class="form-input" required>
                                <option value="">Selecione o setor</option>
                                <option value="Tecnologia">Tecnologia</option>
                                <option value="Saúde">Saúde</option>
                                <option value="Educação">Educação</option>
                                <option value="Comércio">Comércio</option>
                                <option value="Indústria">Indústria</option>
                                <option value="Serviços">Serviços</option>
                                <option value="Agronegócio">Agronegócio</option>
                                <option value="Construção Civil">Construção Civil</option>
                                <option value="Financeiro">Financeiro</option>
                                <option value="Logística e Transporte">Logística e Transporte</option>
                                <option value="Alimentação">Alimentação</option>
                                <option value="Marketing e Publicidade">Marketing e Publicidade</option>
                                <option value="Jurídico">Jurídico</option>
                                <option value="Recursos Humanos">Recursos Humanos</option>
                                <option value="Outro">Outro</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Porte da Empresa <span class="req">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-chart-bar input-icon"></i>
                        <select name="porte" id="porte" class="form-input" required>
                            <option value="">Selecione o porte</option>
                            <option value="MEI">MEI</option>
                            <option value="ME">Microempresa (ME)</option>
                            <option value="EPP">Empresa de Pequeno Porte (EPP)</option>
                            <option value="Médio">Médio Porte</option>
                            <option value="Grande">Grande Porte</option>
                        </select>
                    </div>
                </div>

                <!-- ── SEÇÃO 2: Responsável e Contato ───── -->
                <div class="form-section-title">
                    <i class="fas fa-user-tie"></i> Responsável e Contato
                </div>

                <div class="form-group">
                    <label class="form-label">Nome do Responsável <span class="req">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="responsavel" class="form-input"
                               placeholder="Nome completo do responsável"
                               required autocomplete="name">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">E-mail Corporativo <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" name="email" class="form-input"
                                   placeholder="contato@empresa.com"
                                   required autocomplete="email">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Telefone / WhatsApp <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-phone input-icon"></i>
                            <input type="text" name="telefone" id="telefone" class="form-input"
                                   placeholder="(00) 00000-0000"
                                   required autocomplete="tel">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Website <span class="opt">(opcional)</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-globe input-icon"></i>
                        <input type="url" name="website" class="form-input"
                               placeholder="https://www.suaempresa.com.br"
                               autocomplete="url">
                    </div>
                </div>

                <!-- ── SEÇÃO 3: Localização ─────────────── -->
                <div class="form-section-title">
                    <i class="fas fa-map-marker-alt"></i> Localização
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Estado <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-map input-icon"></i>
                            <select name="estado" id="estado" class="form-input" required>
                                <option value="">Carregando estados...</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cidade <span class="req">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-city input-icon"></i>
                            <select name="cidade" id="cidade" class="form-input" required disabled>
                                <option value="">Selecione o estado</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ── SEÇÃO 4: Segurança ───────────────── -->
                <div class="form-section-title">
                    <i class="fas fa-shield-alt"></i> Segurança
                </div>

                <div class="form-group">
                    <label class="form-label">Senha <span class="req">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
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
                    <div class="password-reqs">
                        <div class="password-req" id="req-length"><i class="far fa-circle" style="font-size:.6rem"></i> Mín. 6 caracteres</div>
                        <div class="password-req" id="req-upper"><i class="far fa-circle" style="font-size:.6rem"></i> Letra maiúscula</div>
                        <div class="password-req" id="req-lower"><i class="far fa-circle" style="font-size:.6rem"></i> Letra minúscula</div>
                        <div class="password-req" id="req-number"><i class="far fa-circle" style="font-size:.6rem"></i> Número</div>
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

                <button type="submit" class="btn-submit" id="submitBtn">
                    <span>Cadastrar empresa</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <div class="divider"><span>OU</span></div>

            <div class="form-footer">
                <p>Já tem uma conta empresarial? <a href="login.php">Faça login</a></p>
            </div>

            <div style="text-align:center">
                <a href="../public/index.php" class="back-link"><i class="fas fa-arrow-left"></i> Voltar para vagas</a>
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
     (Usando mesma lógica do seu sistema de consulta CNPJ)
═══════════════════════════════════════════════════════════ -->
<script>
"use strict";

/* ══════════════════════════════════════════════════════════
   TEMA
══════════════════════════════════════════════════════════ */
function toggleTheme() {
    const html = document.documentElement;
    const icon = document.getElementById('theme-icon');
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    icon.className = next === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
    localStorage.setItem('theme', next);
}

/* ══════════════════════════════════════════════════════════
   MÁSCARA CNPJ (mesma do seu script.js)
══════════════════════════════════════════════════════════ */
function aplicarMascaraCNPJ(cnpj) {
    cnpj = cnpj.replace(/\D/g, '');
    if (cnpj.length <= 2) return cnpj;
    if (cnpj.length <= 5) return cnpj.replace(/(\d{2})(\d{0,3})/, '$1.$2');
    if (cnpj.length <= 8) return cnpj.replace(/(\d{2})(\d{3})(\d{0,3})/, '$1.$2.$3');
    if (cnpj.length <= 12) return cnpj.replace(/(\d{2})(\d{3})(\d{3})(\d{0,4})/, '$1.$2.$3/$4');
    return cnpj.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{0,2})/, '$1.$2.$3/$4-$5');
}

/* ══════════════════════════════════════════════════════════
   VALIDAÇÃO CNPJ (mesma do seu script.js)
══════════════════════════════════════════════════════════ */
function validarCNPJ(cnpj) {
    cnpj = cnpj.replace(/\D/g, '');
    if (cnpj.length !== 14 || /^(\d)\1+$/.test(cnpj)) return false;

    let tamanho = cnpj.length - 2;
    let numeros = cnpj.substring(0, tamanho);
    let digitos = cnpj.substring(tamanho);
    let soma = 0, pos = tamanho - 7;

    for (let i = tamanho; i >= 1; i--) {
        soma += numeros.charAt(tamanho - i) * pos--;
        if (pos < 2) pos = 9;
    }
    let resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
    if (resultado !== parseInt(digitos.charAt(0))) return false;

    tamanho++;
    numeros = cnpj.substring(0, tamanho);
    soma = 0; pos = tamanho - 7;
    for (let i = tamanho; i >= 1; i--) {
        soma += numeros.charAt(tamanho - i) * pos--;
        if (pos < 2) pos = 9;
    }
    resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
    return resultado === parseInt(digitos.charAt(1));
}

/* ══════════════════════════════════════════════════════════
   MÁSCARA TELEFONE
══════════════════════════════════════════════════════════ */
document.getElementById('telefone')?.addEventListener('input', function() {
    let v = this.value.replace(/\D/g, '').slice(0, 11);
    if (v.length <= 10) v = v.replace(/^(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
    else v = v.replace(/^(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
    this.value = v.trim();
});

/* ══════════════════════════════════════════════════════════
   CNPJ INPUT — Máscara + Validação em tempo real
══════════════════════════════════════════════════════════ */
const cnpjInput       = document.getElementById('cnpj');
const btnConsultar    = document.getElementById('btnConsultar');
const validationIcon  = document.getElementById('cnpjValidationIcon');
const cnpjFeedback    = document.getElementById('cnpjFeedback');
const cnpjFeedbackTxt = document.getElementById('cnpjFeedbackText');
const cnpjAlert       = document.getElementById('cnpjAlert');
const cnpjAlertIcon   = document.getElementById('cnpjAlertIcon');
const cnpjAlertText   = document.getElementById('cnpjAlertText');

let cnpjConsultado = false;

cnpjInput?.addEventListener('input', function() {
    this.value = aplicarMascaraCNPJ(this.value);
    const digits = this.value.replace(/\D/g, '');

    // Validação visual em tempo real (mesmo padrão do seu script.js)
    if (digits.length === 14) {
        if (validarCNPJ(digits)) {
            validationIcon.innerHTML = '<i class="fas fa-check-circle" style="color:var(--success)"></i>';
        } else {
            validationIcon.innerHTML = '<i class="fas fa-times-circle" style="color:var(--danger)"></i>';
        }
    } else if (digits.length > 0) {
        validationIcon.innerHTML = '<i class="fas fa-exclamation-circle" style="color:var(--warning)"></i>';
    } else {
        validationIcon.innerHTML = '';
    }

    btnConsultar.disabled = digits.length !== 14;
    hideFeedback();
    this.classList.remove('input-success', 'input-error');
    updateDots();
});

function showFeedback(type, text) {
    cnpjFeedback.className = 'cnpj-feedback show ' + type;
    cnpjFeedbackTxt.innerHTML = text;
}
function hideFeedback() { cnpjFeedback.className = 'cnpj-feedback'; }

function showAlert(type, icon, text) {
    cnpjAlert.className = 'alert alert-' + type;
    cnpjAlert.style.display = 'flex';
    cnpjAlertIcon.className = 'fas fa-' + icon;
    cnpjAlertText.textContent = text;
}
function hideAlert() { cnpjAlert.style.display = 'none'; }

/* ══════════════════════════════════════════════════════════
   MODAL — Padrão do seu sistema de consulta
══════════════════════════════════════════════════════════ */
function mostrarModalDuplicado(cnpj, nomeEmpresa) {
    const modal = document.createElement('div');
    modal.id = 'modalCnpj';
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header-bar modal-danger">
                <h3><i class="fas fa-exclamation-triangle"></i> CNPJ Já Cadastrado</h3>
                <button class="modal-close-btn" onclick="fecharModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body-content">
                <div class="modal-icon-big bg-danger"><i class="fas fa-ban"></i></div>
                <h4>Este CNPJ já está na plataforma</h4>
                <div class="modal-cnpj-display">${aplicarMascaraCNPJ(cnpj)}</div>
                ${nomeEmpresa ? `<p>Empresa: <strong>${nomeEmpresa}</strong></p>` : ''}
                <p>Uma conta empresarial com este CNPJ já foi cadastrada. Se você é o responsável, faça login na sua conta.</p>
                <div class="modal-actions">
                    <a href="login.php" class="modal-btn modal-btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Fazer Login
                    </a>
                    <button onclick="limparCNPJ()" class="modal-btn modal-btn-secondary">
                        <i class="fas fa-redo"></i> Usar outro CNPJ
                    </button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    modal.addEventListener('click', e => { if (e.target === modal) fecharModal(); });
}

function mostrarModalNaoEncontrado(cnpj) {
    const modal = document.createElement('div');
    modal.id = 'modalCnpj';
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header-bar modal-warning">
                <h3><i class="fas fa-search"></i> CNPJ Não Encontrado na Receita</h3>
                <button class="modal-close-btn" onclick="fecharModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body-content">
                <div class="modal-icon-big bg-warning"><i class="fas fa-question"></i></div>
                <h4>Não encontramos dados para este CNPJ</h4>
                <div class="modal-cnpj-display">${aplicarMascaraCNPJ(cnpj)}</div>

                <div class="modal-motivos">
                    <h5><i class="fas fa-info-circle"></i> Possíveis motivos:</h5>
                    <div class="modal-motivo-item">
                        <i class="fas fa-keyboard"></i> <span>CNPJ digitado incorretamente</span>
                    </div>
                    <div class="modal-motivo-item">
                        <i class="fas fa-calendar-plus"></i> <span>Empresa cadastrada recentemente (até 30 dias)</span>
                    </div>
                    <div class="modal-motivo-item">
                        <i class="fas fa-ban"></i> <span>CNPJ inativo ou baixado na Receita</span>
                    </div>
                    <div class="modal-motivo-item">
                        <i class="fas fa-server"></i> <span>Problema temporário na base de dados</span>
                    </div>
                </div>

                <div class="modal-actions">
                    <a href="https://www.sintegra.gov.br/" target="_blank" class="modal-btn modal-btn-primary">
                        <i class="fas fa-external-link-alt"></i> Consultar Sintegra
                    </a>
                    <button onclick="fecharModal(); cnpjInput.focus(); cnpjInput.select();" class="modal-btn modal-btn-secondary">
                        <i class="fas fa-redo"></i> Tentar Novamente
                    </button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    modal.addEventListener('click', e => { if (e.target === modal) fecharModal(); });
}

function fecharModal() {
    document.getElementById('modalCnpj')?.remove();
}

function limparCNPJ() {
    fecharModal();
    cnpjInput.value = '';
    cnpjInput.classList.remove('input-success', 'input-error');
    validationIcon.innerHTML = '';
    hideFeedback(); hideAlert();
    cnpjInput.focus();
}

// Fechar modal com ESC
document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharModal(); });

/* ══════════════════════════════════════════════════════════
   MAPEAR CNAE → SETOR
══════════════════════════════════════════════════════════ */
function mapearCnaeParaSetor(desc) {
    if (!desc) return '';
    const d = desc.toLowerCase();
    const mapa = [
        { p: ['informática','software','tecnologia','computação','dados','digital','internet','telecom'], s: 'Tecnologia' },
        { p: ['saúde','saude','médic','medic','hospital','clínica','clinica','farmácia','farmacia','odonto','dental'], s: 'Saúde' },
        { p: ['educa','ensino','escola','universidade','curso','treinamento'], s: 'Educação' },
        { p: ['comércio','comercio','varejo','loja','mercado','venda','atacado'], s: 'Comércio' },
        { p: ['indústria','industria','fabricação','fabricacao','manufatura','metalúrg'], s: 'Indústria' },
        { p: ['agro','pecuária','pecuaria','agricultura','fazenda','grão','florestal'], s: 'Agronegócio' },
        { p: ['construção','construcao','obra','engenharia','incorpora','imobiliár'], s: 'Construção Civil' },
        { p: ['financ','banco','crédito','credito','seguro','investimento','contábil','contabil'], s: 'Financeiro' },
        { p: ['transport','logística','logistica','frete','entrega','correio','armazém'], s: 'Logística e Transporte' },
        { p: ['aliment','restaurante','bar ','lanchonete','padaria','buffet','refeição'], s: 'Alimentação' },
        { p: ['publicidade','propaganda','marketing','design','comunicação','mídia','midia'], s: 'Marketing e Publicidade' },
        { p: ['jurídic','juridic','advocacia','direito','legal','cartório'], s: 'Jurídico' },
        { p: ['recursos humanos','recrutamento','seleção','selecao'], s: 'Recursos Humanos' },
    ];
    for (const item of mapa) {
        if (item.p.some(x => d.includes(x))) return item.s;
    }
    return 'Serviços';
}

function mapearPorte(porteApi) {
    if (!porteApi) return '';
    const p = porteApi.toLowerCase();
    if (p.includes('mei'))    return 'MEI';
    if (p.includes('micro'))  return 'ME';
    if (p.includes('pequeno'))return 'EPP';
    if (p.includes('demais') || p.includes('grande')) return 'Grande';
    if (p.includes('médio') || p.includes('medio'))   return 'Médio';
    return '';
}

function preencherCampo(id, valor) {
    const el = document.getElementById(id);
    if (!el || !valor) return;
    if (el.tagName === 'SELECT') {
        const match = Array.from(el.options).find(o => o.value === valor);
        if (match) el.value = valor;
    } else {
        el.value = valor;
    }
    el.classList.add('input-success');
    setTimeout(() => el.classList.remove('input-success'), 2500);
}

/* ══════════════════════════════════════════════════════════
   BOTÃO CONSULTAR — Verificação completa
══════════════════════════════════════════════════════════ */
btnConsultar?.addEventListener('click', async function() {
    const cnpjRaw = cnpjInput.value.replace(/\D/g, '');

    if (cnpjRaw.length !== 14) {
        showFeedback('error', '<i class="fas fa-times-circle"></i> Informe os 14 dígitos do CNPJ.');
        return;
    }

    if (!validarCNPJ(cnpjRaw)) {
        showFeedback('error', '<i class="fas fa-times-circle"></i> CNPJ inválido — dígitos verificadores incorretos.');
        cnpjInput.classList.add('input-error');
        return;
    }

    // Loading
    this.classList.add('loading');
    hideFeedback(); hideAlert();
    cnpjInput.classList.remove('input-success', 'input-error');

    try {
        // ── PASSO 1: Verificar duplicidade no banco ──────
        showFeedback('warning', '<i class="fas fa-spinner fa-spin"></i> Verificando CNPJ no sistema...');

        const dupeResp = await fetch('../backend/verificar_cnpj.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cnpj: cnpjRaw })
        });
        const dupeData = await dupeResp.json();

        if (dupeData.erro && dupeResp.status === 429) {
            showFeedback('warning', '<i class="fas fa-clock"></i> ' + dupeData.erro);
            return;
        }

        if (dupeData.disponivel === false) {
            cnpjInput.classList.add('input-error');
            hideFeedback();
            mostrarModalDuplicado(cnpjRaw, dupeData.empresa || null);
            return;
        }

        // ── PASSO 2: Consultar BrasilAPI ─────────────────
        showFeedback('success', '<i class="fas fa-check-circle"></i> CNPJ disponível! Buscando dados...');

        const apiResp = await fetch(`https://brasilapi.com.br/api/cnpj/v1/${cnpjRaw}`);

        if (!apiResp.ok) {
            if (apiResp.status === 404) {
                hideFeedback();
                mostrarModalNaoEncontrado(cnpjRaw);
                cnpjConsultado = true;
            } else if (apiResp.status === 429) {
                showFeedback('warning', '<i class="fas fa-clock"></i> Limite de consultas excedido. Tente em alguns minutos.');
            } else {
                throw new Error('Erro na API: ' + apiResp.status);
            }
            return;
        }

        const empresa = await apiResp.json();

        // ── PASSO 3: Preencher campos ────────────────────
        preencherCampo('razao_social', empresa.razao_social || '');
        preencherCampo('nome_fantasia', empresa.nome_fantasia || empresa.razao_social || '');

        const porteValor = mapearPorte(empresa.descricao_porte || empresa.porte || '');
        if (porteValor) preencherCampo('porte', porteValor);

        const descCnae = empresa.cnae_fiscal_descricao || '';
        const setorValor = mapearCnaeParaSetor(descCnae);
        if (setorValor) preencherCampo('setor', setorValor);

        // Preencher estado e cidade
if (empresa.uf) {
    const estadoEl = document.getElementById('estado');
    estadoEl.value = empresa.uf;

    // Esperar carregar cidades
    await carregarCidades(empresa.uf);

    if (empresa.municipio) {
        const cidadeEl = document.getElementById('cidade');
        const nomeApi = empresa.municipio.trim().toLowerCase();

        const match = Array.from(cidadeEl.options).find(o =>
            o.value.trim().toLowerCase() === nomeApi
        );

        if (match) {
            cidadeEl.value = match.value;
            cidadeEl.classList.add('input-success');
            setTimeout(() => cidadeEl.classList.remove('input-success'), 2500);
        } else {
            console.warn("Cidade não encontrada:", empresa.municipio);
        }
    }
}

        cnpjInput.classList.add('input-success');
        cnpjConsultado = true;

        // Verificar situação cadastral
        const situacao = empresa.descricao_situacao_cadastral || '';
        if (situacao && situacao.toUpperCase() !== 'ATIVA') {
            showFeedback('warning', `<i class="fas fa-exclamation-triangle"></i> CNPJ encontrado, mas situação: ${situacao}`);
            showAlert('warning', 'exclamation-triangle', `Atenção: situação cadastral "${situacao}". Verifique se está correto.`);
        } else {
            showFeedback('success', '<i class="fas fa-check-circle"></i> Dados preenchidos automaticamente!');
            let alertMsg = `Empresa "${empresa.razao_social}" encontrada! Confira os dados.`;
            if (descCnae) alertMsg += ` · Atividade: ${descCnae}`;
            showAlert('success', 'check-circle', alertMsg);
        }

    } catch (err) {
        console.error('Erro na consulta:', err);
        showFeedback('warning', '<i class="fas fa-exclamation-triangle"></i> Não foi possível consultar. Preencha manualmente.');
        cnpjInput.classList.add('input-success');
        cnpjConsultado = true;
    } finally {
        this.classList.remove('loading');
        updateDots();
    }
});

// Consultar com Enter
cnpjInput?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); if (!btnConsultar.disabled) btnConsultar.click(); }
});

/* ══════════════════════════════════════════════════════════
   FORÇA DA SENHA
══════════════════════════════════════════════════════════ */
const senhaInput   = document.getElementById('senha');
const strengthDiv  = document.getElementById('passwordStrength');
const strengthFill = document.getElementById('strengthFill');
const strengthText = document.getElementById('strengthText');

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
        Object.values(reqs).forEach(el => { el.classList.remove('met'); el.querySelector('i').className = 'far fa-circle'; });
        updateDots(); return;
    }
    strengthDiv.classList.add('show');

    const checks = { length: s.length >= 6, upper: /[A-Z]/.test(s), lower: /[a-z]/.test(s), number: /[0-9]/.test(s) };
    for (const [key, met] of Object.entries(checks)) {
        reqs[key].classList.toggle('met', met);
        reqs[key].querySelector('i').className = met ? 'fas fa-check-circle' : 'far fa-circle';
    }

    let str = 0;
    if (checks.length) str += 25;
    if (checks.upper)  str += 25;
    if (checks.lower)  str += 25;
    if (checks.number) str += 15;
    if (/[^a-zA-Z0-9]/.test(s)) str += 10;
    str = Math.min(100, str);

    let color, text;
    if (str < 40)      { color = '#EF4444'; text = 'Fraca'; }
    else if (str < 70) { color = '#F59E0B'; text = 'Média'; }
    else               { color = '#10B981'; text = 'Forte'; }

    strengthFill.style.width = str + '%';
    strengthFill.style.background = color;
    strengthText.style.color = color;
    strengthText.textContent = text;
    updateDots();
});

/* ══════════════════════════════════════════════════════════
   STEPS DOTS
══════════════════════════════════════════════════════════ */
function updateDots() {
    const razao    = document.getElementById('razao_social')?.value.trim();
    const fantasia = document.getElementById('nome_fantasia')?.value.trim();
    const cnpjVal  = cnpjInput?.value.replace(/\D/g, '');
    const setor    = document.getElementById('setor')?.value;
    const porte    = document.getElementById('porte')?.value;
    const resp     = document.querySelector('input[name="responsavel"]')?.value.trim();
    const email    = document.querySelector('input[name="email"]')?.value.trim();
    const tel      = document.getElementById('telefone')?.value.trim();
    const estado   = document.getElementById('estado')?.value;
    const cidade   = document.getElementById('cidade')?.value;
    const senha    = document.getElementById('senha')?.value;

    const d1 = document.getElementById('dot1');
    const d2 = document.getElementById('dot2');
    const d3 = document.getElementById('dot3');
    const d4 = document.getElementById('dot4');

    const s1 = razao && fantasia && cnpjVal?.length === 14 && setor && porte;
    d1.className = s1 ? 'step-dot done' : 'step-dot active';
    const s2 = resp && email && tel;
    d2.className = s2 ? 'step-dot done' : (s1 ? 'step-dot active' : 'step-dot');
    const s3 = estado && cidade;
    d3.className = s3 ? 'step-dot done' : (s2 ? 'step-dot active' : 'step-dot');
    const s4 = senha && senha.length >= 6;
    d4.className = s4 ? 'step-dot done' : (s3 ? 'step-dot active' : 'step-dot');
}

document.querySelectorAll('.form-input').forEach(inp => {
    inp.addEventListener('input', updateDots);
    inp.addEventListener('change', updateDots);
});

/* ══════════════════════════════════════════════════════════
   VALIDAÇÃO SUBMIT
══════════════════════════════════════════════════════════ */
document.getElementById('registerForm')?.addEventListener('submit', function(e) {
    const cnpjVal = cnpjInput?.value.replace(/\D/g, '');
    if (cnpjVal.length !== 14 || !validarCNPJ(cnpjVal)) {
        e.preventDefault();
        alert('Por favor, informe um CNPJ válido.');
        cnpjInput.focus(); return;
    }
    if (!document.getElementById('terms').checked) {
        e.preventDefault();
        alert('Você precisa aceitar os Termos de Uso para continuar.');
    }
});

/* ══════════════════════════════════════════════════════════
   IBGE — Estados e Cidades
══════════════════════════════════════════════════════════ */
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
    } catch { estadoSelect.innerHTML = '<option value="">Erro ao carregar</option>'; }
}

async function carregarCidades(uf) {
    cidadeSelect.innerHTML = '<option value="">Carregando...</option>';
    cidadeSelect.disabled = true;

    try {
        const resp = await fetch(`https://servicodados.ibge.gov.br/api/v1/localidades/estados/${uf}/municipios`);
        const cidades = await resp.json();

        cidadeSelect.innerHTML = '<option value="">Selecione a cidade</option>';

        cidades.forEach(c => {
            const o = document.createElement('option');
            o.value = c.nome;
            o.textContent = c.nome;
            cidadeSelect.appendChild(o);
        });

        cidadeSelect.disabled = false;
    } catch {
        cidadeSelect.innerHTML = '<option value="">Erro ao carregar</option>';
    }
}

estadoSelect?.addEventListener('change', function() {
    const uf = this.value;
    if (!uf) return;
    carregarCidades(uf);
});

/* ══════════════════════════════════════════════════════════
   INIT
══════════════════════════════════════════════════════════ */
window.addEventListener('DOMContentLoaded', () => {
    const saved = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
    document.getElementById('theme-icon').className = saved === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
    carregarEstados();
    setTimeout(() => {
        document.querySelectorAll('.alert:not(#cnpjAlert)').forEach(el => {
            el.style.transition = 'opacity .4s, transform .4s';
            el.style.opacity = '0'; el.style.transform = 'translateY(-10px)';
            setTimeout(() => el.remove(), 400);
        });
    }, 6000);
    updateDots();
});
</script>

</body>
</html>