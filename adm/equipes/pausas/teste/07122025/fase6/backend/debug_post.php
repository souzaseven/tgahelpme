<?php
header("Content-Type: application/json; charset=utf-8");

// Captura bruta
$raw = file_get_contents("php://input");

// Captura headers (útil para AJAX que envia errado)
$headers = function_exists('getallheaders') ? getallheaders() : [];

// Resposta completa
echo json_encode([
    "success" => true,
    "metodo"  => $_SERVER["REQUEST_METHOD"] ?? null,
    "get"     => $_GET,
    "post"    => $_POST,
    "headers" => $headers,
    "raw"     => $raw,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

exit;
