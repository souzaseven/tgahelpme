<?php
require_once __DIR__ . '/bootstrap.php';

if (empty($_SESSION['admin_logged'])) {
    header('Location: login.php');
    exit;
}

/* Impede que o painel fique em cache do navegador após o logout */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
