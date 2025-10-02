<?php
require_once 'conexao.php';

$links = $pdo->query("SELECT * FROM links ORDER BY data_criacao DESC")->fetchAll(PDO::FETCH_ASSOC);

// Definir todas as categorias disponíveis
$categorias = [
    'topo' => 'TOPO',
    'iniciododia' => 'INÍCIO DO DIA',
    'sites' => 'SITES',
    'contato' => 'CONTATOS',
    'versoes' => 'VERSÕES',
    'telefonia' => 'TELEFONIA EVOLUX',
    'sefaz' => 'SEFAZ',
    'downloads' => 'DOWNLOADS',
    'questionarios' => 'QUESTIONÁRIOS',
    'mobile' => 'MOBILE',
    'tef' => 'TEF',
    'pix' => 'API PIX',
    'api' => 'ADICIONAIS API',
    'cobranca' => 'API COBRANÇA',
    'whatsapp' => 'API WHATSAPP',
    'cmd' => 'CMD',
    'consultaerro' => 'CONSULTA ATENDIMENTO',
    'report' => 'REPORT',
    'pdvoff' => 'PDVOFF',
    'email' => 'EMAIL',
    'backup' => 'BACKUP',
    'fiscal' => 'FISCAL',
    'tgamanuais' => 'MANUAIS',
    'dicasmovidesk' => 'DICAS MOVIDESK',
    'tgainstabilidade' => 'CONSULTA INSTABILIDADE',
    'wallpapers' => 'WALLPAPERS TGA',
    'sugestoes' => 'SUGESTOES',
    'diversos' => 'DIVERSOS',
    'rodape' => 'ENDPAGE'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador de Links | TGA</title>
    <!-- Font Awesome para ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --accent: #4895ef;
            --dark: #1a1a2e;
            --light: #f8f9fa;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #560bad;
        }

        .dark-mode {
            --primary: #4895ef;
            --secondary: #4361ee;
            --accent: #3f37c9;
            --dark: #f8f9fa;
            --light: #1a1a2e;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #560bad;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--light) 0%, var(--dark) 100%);
            color: var(--dark);
            min-height: 100vh;
            padding: 2rem;
            transition: all 0.3s ease;
        }

        body.dark-mode {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        body.dark-mode header {
            background: rgba(30, 30, 46, 0.9);
            color: white;
        }

        h1 {
            color: var(--primary);
            font-size: 2rem;
            font-weight: 700;
        }

        .button-add {
            background: var(--primary);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
        }

        .button-add:hover {
            background: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
        }

        /* Seções estilo carrossel */
        section {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        body.dark-mode section {
            background: rgba(30, 30, 46, 0.9);
            color: white;
        }

        section h2 {
            color: var(--primary);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .carousel-container {
            position: relative;
            overflow: hidden;
            padding: 1rem 0;
        }

        .carousel-track {
            display: flex;
            gap: 1.5rem;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 1rem;
        }

        .carousel-track::-webkit-scrollbar {
            height: 8px;
        }

        .carousel-track::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .carousel-track::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }

        .carousel-track::-webkit-scrollbar-thumb:hover {
            background: var(--secondary);
        }

        .link-item {
            scroll-snap-align: start;
            min-width: 250px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
        }

        body.dark-mode .link-item {
            background: #2c2c54;
            color: white;
        }

        .link-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .link-image {
            width: 100%;
            height: 140px;
            object-fit: cover;
            border-bottom: 1px solid #eee;
        }

        .link-content {
            padding: 1rem;
        }

        .link-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        body.dark-mode .link-title {
            color: white;
        }

        .link-description {
            color: #666;
            font-size: 0.9rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        body.dark-mode .link-description {
            color: #ccc;
        }

        .link-url {
            display: inline-block;
            color: var(--accent);
            font-size: 0.8rem;
            text-decoration: none;
            word-break: break-all;
        }

        .link-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            gap: 5px;
        }

        .btn-action {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: none;
            background: rgba(255, 255, 255, 0.9);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .btn-edit {
            background: var(--success);
        }

        .btn-delete {
            background: var(--danger);
        }

        .btn-action:hover {
            transform: scale(1.1);
        }

        /* Modal modernizado */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
            transform: translateY(-20px);
            opacity: 0;
            transition: all 0.3s ease;
        }

        body.dark-mode .modal-content {
            background: #2c2c54;
            color: white;
        }

        .modal.active .modal-content {
            transform: translateY(0);
            opacity: 1;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.5rem;
            color: var(--primary);
        }

        .close-modal {
            font-size: 1.5rem;
            color: #666;
            cursor: pointer;
            transition: color 0.3s;
        }

        body.dark-mode .close-modal {
            color: #ccc;
        }

        .close-modal:hover {
            color: var(--danger);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 500;
        }

        body.dark-mode .form-group label {
            color: #ccc;
        }

        .form-control {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
            background: white;
            color: #333;
        }

        body.dark-mode .form-control {
            background: #1e1e2e;
            border-color: #444;
            color: white;
        }

        .form-control:focus {
            border-color: var(--accent);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .btn-submit {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            margin-top: 1rem;
        }

        .btn-submit:hover {
            background: var(--secondary);
        }

        /* Responsividade */
        @media (max-width: 768px) {
            header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .carousel-track {
                gap: 1rem;
            }

            .link-item {
                min-width: 200px;
            }
        }

        /* Visitas counter */
        .visitas-counter {
            display: flex;
            justify-content: center;
            margin: 1rem 0;
        }

        /* Categorias */
        .category-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
            scrollbar-width: thin;
        }

        .category-tab {
            padding: 0.5rem 1rem;
            background: white;
            border-radius: 50px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
            border: 1px solid #eee;
        }

        body.dark-mode .category-tab {
            background: #2c2c54;
            color: white;
            border-color: #444;
        }

        .category-tab.active, .category-tab:hover {
            background: var(--primary);
            color: white;
        }

        /* Menu de navegação */
        .nav-menu {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 2rem;
            justify-content: center;
        }

        .nav-menu a {
            padding: 0.5rem 1rem;
            background: white;
            border-radius: 50px;
            color: var(--dark);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
            border: 1px solid #eee;
        }

        body.dark-mode .nav-menu a {
            background: #2c2c54;
            color: white;
            border-color: #444;
        }

        .nav-menu a:hover {
            background: var(--primary);
            color: white;
        }

        /* Âncoras */
        .anchor {
            scroll-margin-top: 100px;
        }

        /* Sidebar */
        .sidebar {
            height: 100%;
            width: 0;
            position: fixed;
            top: 0;
            left: 0;
            background: #2c3e50;
            overflow-x: hidden;
            transition: 0.3s;
            z-index: 100;
            padding-top: 60px;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
        }

        .sidebar li {
            padding: 10px;
        }

        .sidebar a {
            color: #fff;
            text-decoration: none;
            display: block;
            padding: 10px 15px;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .sidebar a:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar-toggle {
            position: fixed;
            left: 10px;
            top: 10px;
            z-index: 101;
            background: var(--primary);
            color: white;
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .sidebar-toggle:hover {
            background: var(--secondary);
        }

        .sidebar-open .sidebar {
            width: 250px;
        }

        .sidebar-open body {
            margin-left: 250px;
        }

        .container {
            transition: margin-left 0.3s ease;
            max-width: 1100px;
            margin: auto;
            padding: 20px;
        }

        .sidebar-open .container {
            margin-left: 270px;
        }

        /* Botão de modo escuro */
        .dark-mode-toggle {
            position: fixed;
            right: 20px;
            top: 20px;
            z-index: 100;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s;
        }

        .dark-mode-toggle:hover {
            background: var(--secondary);
            transform: scale(1.1);
        }

        /* Botão fixo para adicionar novo arquivo */
        .fixed-add-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--primary);
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            transition: all 0.3s;
            z-index: 99;
        }

        .fixed-add-button:hover {
            background: var(--secondary);
            transform: scale(1.1);
        }

        /* Campo de pesquisa */
        .search-container {
            margin-bottom: 2rem;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 12px 20px;
            padding-left: 45px;
            border: 1px solid #ddd;
            border-radius: 50px;
            font-size: 1rem;
            transition: all 0.3s;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        body.dark-mode .search-input {
            background: #2c2c54;
            border-color: #444;
            color: white;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        body.dark-mode .search-icon {
            color: #ccc;
        }

        /* Botões de navegação (topo/final) */
        .scroll-buttons {
            position: fixed;
            right: 20px;
            bottom: 100px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 98;
        }

        .scroll-button {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s;
        }

        .scroll-button:hover {
            background: var(--secondary);
            transform: scale(1.1);
        }

        /* Estilo para seções e itens ocultos na pesquisa */
        .section-hidden {
            display: none !important;
        }

        .item-hidden {
            display: none !important;
        }
    </style>
</head>
<body class="dark-mode">
    <!-- Anúncio -->
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044" crossorigin="anonymous"></script>
    
    <!-- Botão de modo escuro -->
    <button class="dark-mode-toggle" id="darkModeToggle">
        <i class="fas fa-sun"></i>
    </button>
    
    <!-- Botão fixo para adicionar novo arquivo -->
    <div class="fixed-add-button" onclick="abrirModal()">
        <i class="fas fa-plus"></i>
    </div>
    
    <!-- Botões de navegação (topo/final) -->
    <div class="scroll-buttons">
        <button class="scroll-button" onclick="scrollToTop()" title="Ir para o topo">
            <i class="fas fa-arrow-up"></i>
        </button>
        <button class="scroll-button" onclick="scrollToBottom()" title="Ir para o final">
            <i class="fas fa-arrow-down"></i>
        </button>
    </div>
    
    <div class="visitas-counter"> 
        <img alt="visitas" src="https://hits.sh/tgameajuda.com/paineladm.html.svg?color=007ced&label=visitas&labelColor=FFFFFF&logo=https%3A%2F%2Fraw.githubusercontent.com%2Fsouzaseven%2Ftgahelpme%2FDesafios%2Ficon%2520bot%2520tga.ico"/>
    </div>

    <div class="container">
        <header>
            <h1><i class="fas fa-link"></i> Gerenciador de Links TGA</h1>
            <button class="button-add" onclick="abrirModal()">
                <i class="fas fa-plus"></i> Novo Link
            </button>
        </header>

        <!-- Campo de pesquisa -->
        <div class="search-container">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="search-input" id="searchInput" placeholder="Pesquisar links..." autocomplete="off">
        </div>

        <!-- Menu de navegação -->
        <div class="nav-menu">
            <?php foreach ($categorias as $id => $nome): ?>
                <a href="#<?= $id ?>"><?= $nome ?></a>
            <?php endforeach; ?>
        </div>

        <!-- Sidebar -->
        <button class="sidebar-toggle" id="sidebarToggle">☰ Menu</button>
        <div class="sidebar" id="sidebar">
            <ul>
                <?php foreach ($categorias as $id => $nome): ?>
                    <li><a href="#<?= $id ?>"><?= $nome ?></a></li>
                <?php endforeach; ?>
                <li><a href="#" onclick="toggleDarkMode()"><i class="fas fa-moon"></i> Alternar Modo Escuro</a></li>
            </ul>
        </div>

        <!-- Seções por categoria -->
        <?php foreach ($categorias as $id => $nome): ?>
            <section class="anchor" id="<?= $id ?>" data-title="<?= $nome ?>">
                <h2>
                    <?php 
                    // Ícones específicos para algumas categorias
                    $icone = match($id) {
                        'sites' => 'fa-globe',
                        'downloads' => 'fa-download',
                        'mobile' => 'fa-mobile-alt',
                        'email' => 'fa-envelope',
                        'backup' => 'fa-save',
                        'fiscal' => 'fa-file-invoice',
                        'tgamanuais' => 'fa-book',
                        'wallpapers' => 'fa-image',
                        default => 'fa-link'
                    };
                    ?>
                    <i class="fas <?= $icone ?>"></i> <?= $nome ?>
                </h2>
                <div class="carousel-container">
                    <div class="carousel-track">
                        <?php 
                        $links_categoria = array_filter($links, fn($link) => ($link['categoria'] ?? '') === $id);
                        foreach ($links_categoria as $link): 
                        ?>
                            <div class="link-item" data-id="<?= $link['id'] ?>" data-title="<?= htmlspecialchars(strtolower($link['titulo'])) ?>" data-descricao="<?= htmlspecialchars(strtolower($link['descricao'])) ?>">
                                <div class="link-actions">
                                    <button class="btn-action btn-edit" onclick="editarLink(<?= htmlspecialchars(json_encode($link)) ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-action btn-delete" onclick="excluirLink(<?= $link['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <?php if ($link['imagem']): ?>
                                    <img src="<?= htmlspecialchars($link['imagem']) ?>" alt="Imagem" class="link-image">
                                <?php else: ?>
                                    <div class="link-image" style="background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-link" style="color: white; font-size: 2rem;"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="link-content">
                                    <h3 class="link-title"><?= htmlspecialchars($link['titulo']) ?></h3>
                                    <p class="link-description"><?= htmlspecialchars($link['descricao']) ?></p>
                                    <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" class="link-url">
                                        <i class="fas fa-external-link-alt"></i> <?= parse_url($link['url'], PHP_URL_HOST) ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($links_categoria)): ?>
                            <div class="link-item" style="min-width: 100%; text-align: center; padding: 2rem;">
                                <i class="fas fa-folder-open" style="font-size: 2rem; color: #ccc; margin-bottom: 1rem;"></i>
                                <p>Nenhum link nesta categoria</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <!-- Modal -->
    <div class="modal" id="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modal-titulo">Adicionar Link</h3>
                <span class="close-modal" onclick="fecharModal()">&times;</span>
            </div>
            <form id="formLink">
                <input type="hidden" name="id" id="link-id">
                <div class="form-group">
                    <label for="link-url"><i class="fas fa-link"></i> URL</label>
                    <input type="text" class="form-control" name="url" id="link-url" placeholder="https://exemplo.com" required>
                </div>
                <div class="form-group">
                    <label for="link-titulo"><i class="fas fa-heading"></i> Título</label>
                    <input type="text" class="form-control" name="titulo" id="link-titulo" placeholder="Título do link">
                </div>
                <div class="form-group">
                    <label for="link-descricao"><i class="fas fa-align-left"></i> Descrição</label>
                    <textarea class="form-control" name="descricao" id="link-descricao" placeholder="Descrição breve do link"></textarea>
                </div>
                <div class="form-group">
                    <label for="link-imagem"><i class="fas fa-image"></i> URL da Imagem</label>
                    <input type="text" class="form-control" name="imagem" id="link-imagem" placeholder="https://exemplo.com/imagem.jpg">
                </div>
                <div class="form-group">
                    <label for="link-categoria"><i class="fas fa-tag"></i> Categoria</label>
                    <select class="form-control" name="categoria" id="link-categoria" required>
                        <option value="">Selecione uma categoria...</option>
                        <?php foreach ($categorias as $id => $nome): ?>
                            <option value="<?= $id ?>"><?= $nome ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Salvar Link
                </button>
            </form>
        </div>
    </div>

    <script>
        // Iniciar no modo escuro
        document.body.classList.add('dark-mode');
        
        // Modal functions
        function abrirModal(link = null) {
            const modal = document.getElementById('modal');
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.classList.add('active');
            }, 10);
            
            document.getElementById('modal-titulo').innerText = link ? 'Editar Link' : 'Adicionar Link';
            document.getElementById('link-id').value = link ? link.id : '';
            document.getElementById('link-url').value = link ? link.url : '';
            document.getElementById('link-titulo').value = link ? link.titulo : '';
            document.getElementById('link-descricao').value = link ? link.descricao : '';
            document.getElementById('link-imagem').value = link ? link.imagem : '';
            if (link && link.categoria) {
                document.getElementById('link-categoria').value = link.categoria;
            }
        }

        function fecharModal() {
            const modal = document.getElementById('modal');
            modal.classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        // Fechar modal ao clicar fora
        document.getElementById('modal').addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModal();
            }
        });

        // Form submission
        document.getElementById('formLink').addEventListener('submit', function(e) {
            e.preventDefault();
            const dados = new FormData(this);
            
            // Adiciona feedback visual
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
            btn.disabled = true;
            
            fetch('salvar_link.php', {
                method: 'POST',
                body: dados
            })
            .then(res => res.text())
            .then(() => {
                location.reload();
            })
            .catch(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                alert('Erro ao salvar. Tente novamente.');
            });
        });

        function editarLink(link) {
            abrirModal(link);
        }

        function excluirLink(id) {
            if (!confirm("Tem certeza que deseja excluir este link?")) return;
            
            const btn = event.target;
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            const form = new FormData();
            form.append('id', id);
            
            fetch('excluir.php', {
                method: 'POST',
                body: form
            })
            .then(res => res.text())
            .then(() => {
                location.reload();
            })
            .catch(() => {
                btn.innerHTML = originalHTML;
                alert('Erro ao excluir. Tente novamente.');
            });
        }

        // Suavizar rolagem para as âncoras
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Sidebar functions
        let sidebarState = false;

        function toggleSidebar() {
            const body = document.body;
            if (sidebarState) {
                body.classList.remove('sidebar-open');
                sidebarState = false;
            } else {
                body.classList.add('sidebar-open');
                sidebarState = true;
            }
        }

        document.getElementById('sidebarToggle').addEventListener('click', toggleSidebar);

        // Dark mode toggle
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            const isDarkMode = document.body.classList.contains('dark-mode');
            const icon = document.getElementById('darkModeToggle').querySelector('i');
            
            if (isDarkMode) {
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            } else {
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            }
        }

        document.getElementById('darkModeToggle').addEventListener('click', toggleDarkMode);

        // Funções de rolagem
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        function scrollToBottom() {
            window.scrollTo({
                top: document.body.scrollHeight,
                behavior: 'smooth'
            });
        }

        // Função de pesquisa
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const sections = document.querySelectorAll('section');
            
            sections.forEach(section => {
                const sectionTitle = section.getAttribute('data-title').toLowerCase();
                const items = section.querySelectorAll('.link-item');
                let hasVisibleItems = false;
                
                items.forEach(item => {
                    const itemTitle = item.getAttribute('data-title');
                    const itemDesc = item.getAttribute('data-descricao');
                    
                    if (itemTitle.includes(searchTerm) || itemDesc.includes(searchTerm) || sectionTitle.includes(searchTerm)) {
                        item.classList.remove('item-hidden');
                        hasVisibleItems = true;
                    } else {
                        item.classList.add('item-hidden');
                    }
                });
                
                if (hasVisibleItems || sectionTitle.includes(searchTerm)) {
                    section.classList.remove('section-hidden');
                } else {
                    section.classList.add('section-hidden');
                }
            });
        });

        // Mostrar/ocultar botões de rolagem conforme a posição da página
        window.addEventListener('scroll', function() {
            const scrollButtons = document.querySelector('.scroll-buttons');
            if (window.scrollY > 300) {
                scrollButtons.style.display = 'flex';
            } else {
                scrollButtons.style.display = 'none';
            }
        });
    </script>
</body>
</html>