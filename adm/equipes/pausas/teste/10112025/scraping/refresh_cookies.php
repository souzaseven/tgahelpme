<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Arquivo simples para refresh de cookies - será implementado depois
$result = [
    'success' => false,
    'error' => 'Sistema de refresh ainda não implementado',
    'timestamp' => date('Y-m-d H:i:s')
];

echo json_encode($result);
?>