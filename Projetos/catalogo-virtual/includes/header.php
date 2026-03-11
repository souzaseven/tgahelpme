<?php
// includes/header.php

// Detecta automaticamente quantos níveis acima está a raiz
$niveis  = substr_count(str_replace(dirname(dirname(__FILE__)), '', dirname($_SERVER['SCRIPT_FILENAME'])), DIRECTORY_SEPARATOR);
$prefixo = str_repeat('../', $niveis);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo ?? 'Catálogo Virtual' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $prefixo ?>assets/css/style.css">
</head>
<body>

<header class="header-principal">
    <div class="container">

        <!-- Logo -->
        <a href="<?= $prefixo ?>public/index.php" class="logo">
            <div class="logo-icon">📦</div>
            <h1>Catálogo</h1>
        </a>

        <!-- Busca central -->
        <div class="header-busca">
            <form method="GET" action="<?= $prefixo ?>public/index.php">
                <div class="header-busca-inner">
                    <span class="icone-busca">🔍</span>
                    <input
                        type="text"
                        name="busca"
                        placeholder="Buscar produtos..."
                        value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>"
                        autocomplete="off"
                    >
                </div>
            </form>
        </div>

        <!-- Nav -->
        <nav class="header-nav">
            <a href="<?= $prefixo ?>admin/index.php" class="nav-link">
                ⚙️ Admin
            </a>
            <a href="<?= $prefixo ?>public/carrinho.php" class="nav-link nav-carrinho">
                🛒 Carrinho
                <span class="badge-contador" id="contador-carrinho">0</span>
            </a>
        </nav>

    </div>
</header>