<?php
require __DIR__ . '/zapi_client.php';

/*============================
_teste_envio_real.php
==============================*/

$res = enviarWhatsapp(
    '556599452676',
    'Teste tgameajuda — Controle de Pausa'
);

error_log('[WHATSAPP TESTE DIRETO] ' . json_encode($res, JSON_UNESCAPED_UNICODE));

header('Content-Type: application/json');
echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
