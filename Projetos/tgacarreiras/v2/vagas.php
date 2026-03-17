<?php
require_once __DIR__ . '/backend/conexao.php';

try {
    $stmt = $pdo->prepare("
        SELECT id, titulo, localizacao, salario, data_publicacao
        FROM vagas
        WHERE status = 'ativa'
        ORDER BY data_publicacao DESC
    ");
    $stmt->execute();
    $vagas = $stmt->fetchAll();
} catch (Exception $e) {
    $vagas = [];
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>TGA Carreiras - Oportunidades de Emprego</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            /* Cores Primárias - Azul Profissional */
            --primary-blue: #0066FF;
            --primary-dark: #0052CC;
            --primary-light: #3385FF;
            
            /* Modo Escuro (PADRÃO) */
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
        }

        @media (prefers-color-scheme: light) {
            :root {
                /* Modo Claro Suavizado */
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
        }

        /* Override para tema claro manual */
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

        /* Override para tema escuro manual */
        [data-theme="dark"] {
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
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* Header Moderno */
        header {
            background: var(--bg-primary);
            padding: 1.5rem 0;
            box-shadow: var(--shadow-md);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 2px solid var(--primary-blue);
            backdrop-filter: blur(10px);
        }

        .header-content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-dark) 100%);
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
            letter-spacing: -0.5px;
        }

        .logo-text p {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;
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
            transition: all 0.3s ease;
        }

        .theme-toggle:hover {
            background: var(--primary-blue);
            border-color: var(--primary-blue);
            transform: scale(1.05);
        }

        .stat-badge {
            background: var(--bg-secondary);
            padding: 0.625rem 1.25rem;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid var(--border-color);
        }

        .stat-badge-number {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--primary-blue);
        }

        .stat-badge-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Container Principal */
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }

        .hero-section {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-dark) 100%);
            border-radius: 24px;
            color: white;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            filter: blur(60px);
        }

        .hero-section::after {
            content: '';
            position: absolute;
            bottom: -50%;
            left: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            filter: blur(50px);
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero-section h2 {
            font-size: 2.75rem;
            font-weight: 800;
            margin-bottom: 1rem;
            letter-spacing: -1px;
        }

        .hero-section p {
            font-size: 1.25rem;
            opacity: 0.95;
            font-weight: 400;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Search Bar */
        .search-container {
            max-width: 700px;
            margin: 2rem auto 3rem;
        }

        .search-box {
            display: flex;
            align-items: center;
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 0.75rem 1.5rem;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
        }

        .search-box:focus-within {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 4px rgba(0, 102, 255, 0.1);
        }

        .search-icon {
            font-size: 1.25rem;
            color: var(--text-tertiary);
            margin-right: 1rem;
        }

        .search-input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 1rem;
            background: transparent;
            color: var(--text-primary);
            font-weight: 500;
        }

        .search-input::placeholder {
            color: var(--text-tertiary);
        }

        /* Grid de Vagas */
        .vagas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 1.75rem;
        }

        /* Card de Vaga Premium */
        .vaga-card {
            background: var(--bg-primary);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .vaga-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-blue) 0%, var(--primary-light) 100%);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .vaga-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-blue);
        }

        .vaga-card:hover::before {
            transform: scaleX(1);
        }

        .vaga-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            gap: 1rem;
        }

        .vaga-titulo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
            flex: 1;
        }

        .vaga-badge {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(0, 102, 255, 0.3);
        }

        /* Informações da Vaga */
        .vaga-info {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1.75rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .info-icon {
            width: 48px;
            height: 48px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .vaga-card:hover .info-icon {
            background: var(--primary-blue);
            border-color: var(--primary-blue);
            transform: scale(1.05);
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 0.75rem;
            color: var(--text-tertiary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .info-text {
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.95rem;
        }

        /* Botão Premium */
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            width: 100%;
            padding: 1rem 1.75rem;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-dark) 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(0, 102, 255, 0.25);
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 102, 255, 0.35);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-icon {
            font-size: 1.1rem;
            transition: transform 0.3s ease;
        }

        .btn:hover .btn-icon {
            transform: translateX(4px);
        }

        /* Estado Vazio */
        .sem-vagas {
            background: var(--bg-primary);
            border-radius: 24px;
            padding: 5rem 2rem;
            text-align: center;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }

        .sem-vagas-icon {
            font-size: 5rem;
            margin-bottom: 2rem;
            opacity: 0.5;
            filter: grayscale(1);
        }

        .sem-vagas h3 {
            font-size: 1.75rem;
            color: var(--text-primary);
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .sem-vagas p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            max-width: 500px;
            margin: 0 auto 2rem;
            line-height: 1.8;
        }

        .notify-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 2rem;
            background: var(--primary-blue);
            color: white;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
        }

        .notify-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Footer */
        footer {
            background: var(--bg-primary);
            margin-top: 5rem;
            padding: 3rem 2rem;
            border-top: 1px solid var(--border-color);
        }

        .footer-content {
            max-width: 1280px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 2rem;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .footer-logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-dark) 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .footer-logo-text {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--text-primary);
        }

        .footer-links {
            display: flex;
            gap: 2rem;
        }

        .footer-link {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .footer-link:hover {
            color: var(--primary-blue);
        }

        .footer-social {
            display: flex;
            gap: 1rem;
        }

        .social-icon {
            width: 40px;
            height: 40px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .social-icon:hover {
            background: var(--primary-blue);
            border-color: var(--primary-blue);
            transform: translateY(-2px);
        }

        /* Responsivo */
        @media (max-width: 1024px) {
            .vagas-grid {
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .header-actions {
                width: 100%;
                justify-content: center;
            }

            .hero-section h2 {
                font-size: 2rem;
            }

            .hero-section p {
                font-size: 1rem;
            }

            .vagas-grid {
                grid-template-columns: 1fr;
            }

            .footer-content {
                flex-direction: column;
                text-align: center;
            }

            .footer-links {
                flex-direction: column;
                gap: 1rem;
            }
        }

        /* Animações */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .vaga-card {
            animation: fadeInUp 0.6s ease-out backwards;
        }

        .vaga-card:nth-child(1) { animation-delay: 0.05s; }
        .vaga-card:nth-child(2) { animation-delay: 0.1s; }
        .vaga-card:nth-child(3) { animation-delay: 0.15s; }
        .vaga-card:nth-child(4) { animation-delay: 0.2s; }
        .vaga-card:nth-child(5) { animation-delay: 0.25s; }
        .vaga-card:nth-child(6) { animation-delay: 0.3s; }
        .vaga-card:nth-child(7) { animation-delay: 0.35s; }
        .vaga-card:nth-child(8) { animation-delay: 0.4s; }
        .vaga-card:nth-child(9) { animation-delay: 0.45s; }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.8;
            }
        }

        .vaga-badge {
            animation: pulse 2s ease-in-out infinite;
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
        <div class="header-actions">
            <div class="stat-badge">
                <span class="stat-badge-number"><?= count($vagas) ?></span>
                <span class="stat-badge-label">Vagas Disponíveis</span>
            </div>
            <button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema">
                <span id="theme-icon">☀️</span>
            </button>
        </div>
    </div>
</header>

<div class="container">
    <div class="hero-section">
        <div class="hero-content">
            <h2>Encontre sua próxima oportunidade</h2>
            <p>Explore nossas vagas exclusivas e dê o próximo passo na sua carreira profissional</p>
        </div>
    </div>

    <div class="search-container">
        <div class="search-box">
            <span class="search-icon">🔍</span>
            <input type="text" class="search-input" placeholder="Buscar por cargo, localização ou palavra-chave..." id="searchInput">
        </div>
    </div>

    <?php if (count($vagas) > 0): ?>
        <div class="vagas-grid" id="vagasGrid">
            <?php foreach ($vagas as $vaga): ?>
                <div class="vaga-card" data-titulo="<?= strtolower(htmlspecialchars($vaga['titulo'])) ?>" data-localizacao="<?= strtolower(htmlspecialchars($vaga['localizacao'])) ?>">
                    <div class="vaga-header">
                        <h3 class="vaga-titulo">
                            <?= htmlspecialchars($vaga['titulo']) ?>
                        </h3>
                        <span class="vaga-badge">Nova</span>
                    </div>

                    <div class="vaga-info">
                        <div class="info-item">
                            <div class="info-icon">📍</div>
                            <div class="info-content">
                                <div class="info-label">Localização</div>
                                <div class="info-text"><?= htmlspecialchars($vaga['localizacao']) ?></div>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">💰</div>
                            <div class="info-content">
                                <div class="info-label">Remuneração</div>
                                <div class="info-text"><?= htmlspecialchars($vaga['salario'] ?? 'A combinar') ?></div>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">📅</div>
                            <div class="info-content">
                                <div class="info-label">Publicado</div>
                                <div class="info-text"><?= date('d/m/Y', strtotime($vaga['data_publicacao'])) ?></div>
                            </div>
                        </div>
                    </div>

                    <a href="vaga.php?id=<?= $vaga['id'] ?>" class="btn">
                        <span>Ver detalhes completos</span>
                        <span class="btn-icon">→</span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="sem-vagas">
            <div class="sem-vagas-icon">🔍</div>
            <h3>Nenhuma vaga disponível no momento</h3>
            <p>Estamos sempre em busca de novos talentos. Cadastre-se para receber notificações quando novas oportunidades surgirem!</p>
            <a href="#" class="notify-btn">
                <span>🔔</span>
                <span>Receber notificações</span>
            </a>
        </div>
    <?php endif; ?>

</div>

<footer>
    <div class="footer-content">
        <div class="footer-logo">
            <div class="footer-logo-icon">🚀</div>
            <span class="footer-logo-text">TGA Carreiras</span>
        </div>
        <div class="footer-links">
            <a href="#" class="footer-link">Sobre nós</a>
            <a href="#" class="footer-link">Contato</a>
            <a href="#" class="footer-link">Política de Privacidade</a>
            <a href="#" class="footer-link">Termos de Uso</a>
        </div>
        <div class="footer-social">
            <a href="#" class="social-icon" title="LinkedIn">💼</a>
            <a href="#" class="social-icon" title="Instagram">📷</a>
            <a href="#" class="social-icon" title="Twitter">🐦</a>
        </div>
    </div>
</footer>

<script>
// Sistema de busca em tempo real
document.getElementById('searchInput')?.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const cards = document.querySelectorAll('.vaga-card');
    
    cards.forEach(card => {
        const titulo = card.dataset.titulo || '';
        const localizacao = card.dataset.localizacao || '';
        
        if (titulo.includes(searchTerm) || localizacao.includes(searchTerm)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
});

// Sistema de alternância de tema
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

// Carregar tema salvo (padrão: escuro)
window.addEventListener('DOMContentLoaded', () => {
    const savedTheme = localStorage.getItem('theme') || 'dark';
    const html = document.documentElement;
    const themeIcon = document.getElementById('theme-icon');
    
    html.setAttribute('data-theme', savedTheme);
    
    if (savedTheme === 'light') {
        themeIcon.textContent = '🌙';
    } else {
        themeIcon.textContent = '☀️';
    }
});
</script>

</body>
</html>