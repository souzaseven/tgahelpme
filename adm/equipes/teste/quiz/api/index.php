<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

// TODO: substituir por um roteador de fato quando os primeiros endpoints
// forem definidos (ex.: /api/questoes, /api/tentativas).

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'app' => APP_NAME,
    'status' => 'ok',
    'mensagem' => 'API ainda não possui endpoints implementados.',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
