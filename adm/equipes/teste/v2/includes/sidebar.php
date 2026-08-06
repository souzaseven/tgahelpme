<?php $pag = basename($_SERVER['PHP_SELF']); ?>
<aside class="sidebar" id="sidebar">
    <div class="logo">
        <h2>Renascer</h2>
        <span>Sistema de Membros</span>
    </div>
    <nav>
        <ul>
            <li>
                <a href="index.php" class="<?= $pag === 'index.php' ? 'active' : '' ?>">
                    <span class="nav-icon">&#9699;</span> Dashboard
                </a>
            </li>
            <li>
                <a href="membros.php" class="<?= $pag === 'membros.php' ? 'active' : '' ?>">
                    <span class="nav-icon">&#128101;</span> Membros
                </a>
            </li>
            <li>
                <a href="aniversarios.php" class="<?= $pag === 'aniversarios.php' ? 'active' : '' ?>">
                    <span class="nav-icon">&#127881;</span> Aniversários
                </a>
            </li>
            <li>
                <a href="whatsapp.php" class="<?= $pag === 'whatsapp.php' ? 'active' : '' ?>">
                    <span class="nav-icon">&#128242;</span> WhatsApp
                </a>
            </li>
        </ul>
    </nav>
    <div class="sidebar-sep"></div>
    <div class="sidebar-footer">
        <button class="theme-btn" id="btnTema" onclick="alternarTema()">&#9728; Modo Claro</button>
        <a href="logout.php">&#8594; Sair do Sistema</a>
    </div>
</aside>
