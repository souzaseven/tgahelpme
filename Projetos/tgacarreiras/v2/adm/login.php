<?php 
session_start(); 
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Login - TGA Carreiras</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Fonts -->
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
        :root {
            --primary-blue: #0066FF;
            --primary-dark: #0052CC;
            --primary-light: #3385FF;
            --bg-primary: #0F172A;
            --bg-secondary: #1E293B;
            --bg-tertiary: #334155;
            --text-primary: #F1F5F9;
            --text-secondary: #CBD5E1;
            --text-tertiary: #64748B;
            --border-color: #334155;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.4);
            --shadow-xl: 0 20px 40px rgba(0, 102, 255, 0.3);
            --success: #10B981;
            --danger: #EF4444;
        }

        [data-theme="light"] {
            --bg-primary: #FAFBFC;
            --bg-secondary: #F5F7FA;
            --bg-tertiary: #EDF0F5;
            --text-primary: #1A202C;
            --text-secondary: #4A5568;
            --text-tertiary: #718096;
            --border-color: #E2E8F0;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.07);
            --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.08);
            --shadow-xl: 0 20px 40px rgba(0, 102, 255, 0.12);
        }

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        header {
            background: var(--bg-primary);
            padding: 1.5rem 0;
            box-shadow: var(--shadow-md);
            border-bottom: 2px solid var(--primary-blue);
        }
        
        .header-content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: var(--shadow-md);
        }
        
        .logo-text h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text-primary);
        }
        
        .logo-text p {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .theme-toggle {
            width: 44px;
            height: 44px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            background: var(--bg-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            transition: all 0.3s;
        }
        
        .theme-toggle:hover {
            background: var(--primary-blue);
            border-color: var(--primary-blue);
            transform: scale(1.05);
        }

        /* Container Principal */
        .main-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 2rem;
        }

        /* Login Box */
        .login-box {
            background: var(--bg-primary);
            border-radius: 24px;
            padding: 3rem;
            width: 100%;
            max-width: 480px;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border-color);
            animation: slideUp 0.5s ease-out;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .login-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-dark));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            box-shadow: var(--shadow-lg);
        }

        .login-header h2 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideDown 0.3s;
            transition: opacity 0.3s, transform 0.3s;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--success);
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--danger);
        }
        
        .alert-icon {
            font-size: 1.5rem;
        }

        /* Form */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .form-label .required { 
            color: var(--danger); 
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.25rem;
            color: var(--text-tertiary);
            pointer-events: none;
        }
        
        .form-input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            border-radius: 10px;
            border: 2px solid var(--border-color);
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 4px rgba(0, 102, 255, 0.1);
        }
        
        .form-input::placeholder {
            color: var(--text-tertiary);
        }

        /* Checkbox */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            cursor: pointer;
        }

        /* Button */
        .btn {
            width: 100%;
            padding: 1rem 2rem;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 16px rgba(0, 102, 255, 0.25);
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 102, 255, 0.35);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }

        /* Links */
        .form-footer {
            margin-top: 2rem;
            text-align: center;
        }
        
        .form-link {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.95rem;
            transition: color 0.2s;
        }
        
        .form-link:hover {
            color: var(--primary-blue);
        }
        
        .form-link strong {
            color: var(--primary-blue);
            font-weight: 700;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 2rem 0;
            color: var(--text-tertiary);
            font-size: 0.85rem;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-color);
        }
        
        .divider span {
            padding: 0 1rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
            margin-top: 1.5rem;
        }
        
        .back-link:hover {
            color: var(--primary-blue);
            transform: translateX(-4px);
        }

        /* Footer */
        footer {
            background: var(--bg-primary);
            padding: 2rem;
            text-align: center;
            border-top: 1px solid var(--border-color);
        }
        
        footer p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .login-box {
                padding: 2rem 1.5rem;
            }
            
            .main-container {
                padding: 2rem 1rem;
            }
        }

        /* Animações */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo-section">
                <div class="logo-icon">🚀</div>
                <div class="logo-text">
                    <h1>TGA Carreiras</h1>
                    <p>Conectando talentos a oportunidades</p>
                </div>
            </div>
            <button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema">
                <span id="theme-icon">☀️</span>
            </button>
        </div>
    </header>

    <div class="main-container">
        <div class="login-box">
            <div class="login-header">
                <div class="login-icon">🔐</div>
                <h2>Bem-vindo de volta!</h2>
                <p>Entre com sua conta para continuar</p>
            </div>

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

            <form action="../backend/auth_admin.php" method="POST" id="loginForm">
                <input type="hidden" name="acao" value="login_admin">
                
                <div class="form-group">
                    <label class="form-label">Email <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <span class="input-icon">📧</span>
                        <input 
                            type="email" 
                            name="email" 
                            class="form-input" 
                            placeholder="seu@email.com" 
                            required 
                            autofocus
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Senha <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔒</span>
                        <input 
                            type="password" 
                            name="senha" 
                            class="form-input" 
                            placeholder="Digite sua senha" 
                            required
                        >
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Lembrar de mim</label>
                </div>

                <button type="submit" class="btn btn-primary">
                    <span>Entrar na conta</span>
                    <span>→</span>
                </button>
            </form>

            <div class="divider">
                <span>OU</span>
            </div>

            <div class="form-footer">
                <p class="form-link">
                    Não tem uma conta? 
                    <a href="cadastro.php"><strong>Criar conta grátis</strong></a>
                </p>
                <p class="form-link" style="margin-top: 1rem;">
                    <a href="#"><strong>Esqueceu sua senha?</strong></a>
                </p>
            </div>

            <div style="text-align: center;">
                <a href="index.php" class="back-link">
                    <span>←</span>
                    <span>Voltar para vagas</span>
                </a>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2024 TGA Carreiras. Todos os direitos reservados.</p>
    </footer>

    <script>
        // Sistema de tema
        function toggleTheme() {
            const html = document.documentElement;
            const themeIcon = document.getElementById('theme-icon');
            const currentTheme = html.getAttribute('data-theme') || 'dark';
            
            if (currentTheme === 'dark') {
                html.setAttribute('data-theme', 'light');
                themeIcon.textContent = '🌙';
                localStorage.setItem('theme', 'light');
            } else {
                html.setAttribute('data-theme', 'dark');
                themeIcon.textContent = '☀️';
                localStorage.setItem('theme', 'dark');
            }
        }

        // Carregar tema salvo
        window.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            const html = document.documentElement;
            const themeIcon = document.getElementById('theme-icon');
            
            html.setAttribute('data-theme', savedTheme);
            themeIcon.textContent = savedTheme === 'light' ? '🌙' : '☀️';
        });

        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>