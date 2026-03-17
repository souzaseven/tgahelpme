<?php
session_start();

// Se já estiver deslogado, redirecionar para login
if (!isset($_SESSION['candidato_id'])) {
    header("Location: login.php");
    exit;
}

// Armazenar nome antes de destruir sessão (para exibir na tela)
$nome = $_SESSION['candidato_nome'] ?? 'Usuário';

// Destruir sessão
session_destroy();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Saindo - TGA Carreiras</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .logout-container {
            background: var(--bg-primary);
            border-radius: 24px;
            padding: 4rem 3rem;
            width: 100%;
            max-width: 500px;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border-color);
            text-align: center;
            animation: slideUp 0.5s ease-out;
        }

        .logout-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 2rem;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            box-shadow: var(--shadow-lg);
            animation: pulse 2s ease-in-out infinite;
        }

        h1 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .logout-message {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            line-height: 1.8;
        }

        .logout-name {
            color: var(--primary-blue);
            font-weight: 700;
        }

        /* Progress Bar */
        .progress-container {
            margin: 2rem 0;
        }

        .progress-label {
            font-size: 0.9rem;
            color: var(--text-tertiary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .progress-bar {
            height: 6px;
            background: var(--bg-secondary);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-blue), var(--primary-light));
            border-radius: 3px;
            animation: progress 3s linear forwards;
        }

        .countdown {
            font-size: 0.9rem;
            color: var(--text-tertiary);
            font-weight: 600;
        }

        .countdown-number {
            color: var(--primary-blue);
            font-size: 1.1rem;
            font-weight: 800;
        }

        /* Buttons */
        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            flex: 1;
            padding: 1rem 2rem;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 16px rgba(0, 102, 255, 0.25);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 102, 255, 0.35);
        }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-secondary);
        }

        /* Success Icon Animation */
        .success-checkmark {
            display: none;
            width: 100px;
            height: 100px;
            margin: 0 auto 2rem;
        }

        .success-checkmark.show {
            display: block;
            animation: scaleIn 0.5s ease-out;
        }

        .checkmark-circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke: var(--success);
            stroke-width: 3;
            fill: none;
            animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }

        .checkmark-check {
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            stroke: var(--success);
            stroke-width: 3;
            fill: none;
            animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.6s forwards;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .logout-container {
                padding: 3rem 2rem;
            }
            h1 {
                font-size: 1.75rem;
            }
            .button-group {
                flex-direction: column;
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

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        @keyframes progress {
            from {
                width: 0%;
            }
            to {
                width: 100%;
            }
        }

        @keyframes stroke {
            100% {
                stroke-dashoffset: 0;
            }
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }
    </style>
</head>
<body>

<div class="logout-container">
    <div class="logout-icon">👋</div>

    <h1>Até logo!</h1>

    <p class="logout-message">
        Obrigado por usar o TGA Carreiras, <span class="logout-name"><?= htmlspecialchars($nome) ?></span>.<br>
        Você foi desconectado com sucesso.
    </p>

    <div class="progress-container">
        <div class="progress-label">
            <span>🔄</span>
            <span>Redirecionando automaticamente...</span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>
        <div class="countdown">
            Você será redirecionado em <span class="countdown-number" id="countdown">3</span> segundos
        </div>
    </div>

    <div class="button-group">
        <a href="login.php" class="btn btn-primary">
            <span>🔐</span>
            <span>Fazer Login Novamente</span>
        </a>
        <a href="index.php" class="btn btn-secondary">
            <span>💼</span>
            <span>Ver Vagas</span>
        </a>
    </div>
</div>

<script>
// Countdown e redirecionamento
let seconds = 3;
const countdownElement = document.getElementById('countdown');

const interval = setInterval(() => {
    seconds--;
    countdownElement.textContent = seconds;
    
    if (seconds <= 0) {
        clearInterval(interval);
        window.location.href = 'login.php';
    }
}, 1000);

// Permitir cancelar redirecionamento ao clicar em qualquer botão
document.querySelectorAll('.btn').forEach(btn => {
    btn.addEventListener('click', () => {
        clearInterval(interval);
    });
});

// Carregar tema salvo
window.addEventListener('DOMContentLoaded', () => {
    const savedTheme = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
});
</script>

</body>
</html>