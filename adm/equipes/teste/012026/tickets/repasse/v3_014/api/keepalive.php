<?php
require_once __DIR__ . '/../includes/session.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['autenticado' => false]);
    exit;
}

$_SESSION['_keepalive'] = time();

echo json_encode(['autenticado' => true]);
