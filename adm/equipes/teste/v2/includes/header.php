<?php $titulo = $titulo_pagina ?? 'Painel Administrativo - Igreja Renascer'; ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?></title>
    <!-- Aplica tema antes do primeiro paint para evitar flash -->
    <script>if(localStorage.getItem('tema')==='claro')document.documentElement.setAttribute('data-theme','light');</script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/app.js" defer></script>
</head>
<body>
<button class="hamburger" id="hamburger" onclick="toggleSidebar()" aria-label="Abrir menu">&#9776;</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
