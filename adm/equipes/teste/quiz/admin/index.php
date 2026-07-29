<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

// TODO: exigir autenticação + perfil admin quando o módulo de auth existir.

?><!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel do Administrador - <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
</head>
<body>
    <main class="placeholder-page">
        <h1>Painel do Administrador</h1>
        <p>Em construção.</p>
    </main>
</body>
</html>
