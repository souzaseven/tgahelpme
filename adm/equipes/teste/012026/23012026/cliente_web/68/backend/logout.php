<?php
session_start();
session_destroy();

// Pega o caminho atual
$path = $_SERVER['REQUEST_URI'];

// Divide em partes
$parts = explode('/', trim($path, '/'));

// Remove apenas os dois últimos: "backend" e "logout.php"
$basePath = implode('/', array_slice($parts, 0, -2));

// Redireciona para o login da mesma versão
header("Location: /{$basePath}/login.html");
exit;
