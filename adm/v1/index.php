<?php include "conexao.php"; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - TGAMEAJUDA</title>
    <meta name="description" content="Sistema de gerenciamento TGAME - Usuários, Operadores e Devocionais">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="header-content">
            <h1>🚀 Painel Administrativo TGAME</h1>
            <nav>
                <button class="nav-btn" onclick="loadData('usuarios')">👥 Usuários</button>
                <button class="nav-btn" onclick="loadData('operadores')">⚡ Operadores</button>
                <button class="nav-btn" onclick="loadData('devocionais')">📖 Devocionais</button>
            </nav>
        </div>
    </header>

    <main>
        <div id="content">
            <div class="text-center" style="padding: 4rem 2rem;">
                <h1 style="font-size: 2.5rem; margin-bottom: 1rem;">🚀 Painel Administrativo</h1>
                <p class="text-muted" style="font-size: 1.2rem;">Selecione uma tabela no menu acima para começar</p>
            </div>
        </div>
    </main>

    <!-- Modais -->
    <div id="passwordModal" class="modal"></div>
    <div id="filaModal" class="modal"></div>
    <div id="linksLiderModal" class="modal"></div> <!-- NOVO MODAL -->

    <script>
        // CSRF Token para uso no JavaScript
        const CSRF_TOKEN = "<?php echo $_SESSION['csrf_token']; ?>";
    </script>
    <script src="script.js"></script>
</body>
</html>