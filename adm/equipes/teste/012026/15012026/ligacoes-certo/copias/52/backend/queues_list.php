<?php
/**
 * =========================================================
 * Listagem de Filas – Evolux
 * Autor: Anderson de Souza
 * Status: FINAL / PRODUÇÃO
 * =========================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/evolux_client.php';

header('Content-Type: application/json; charset=utf-8');

try {
    /* ==========================
       CLIENTE EVOLUX
    ========================== */
    $config = require __DIR__ . '/config.php';
    $client = new EvoluxClient($config);

    /* =====================================================
       🔴 OPÇÃO 1 (DESATIVADA)
       Trazer TODAS as filas da Evolux
       -----------------------------------------------------
       Útil caso futuramente queira liberar tudo novamente
    ====================================================== */
    /*
    $result = $client->get('/api/v1/queues', [
        'include_archived' => 'true'
    ]);

    if ($result['http_code'] !== 200) {
        throw new Exception('Erro ao buscar filas (HTTP ' . $result['http_code'] . ')');
    }

    echo json_encode([
        'success' => true,
        'queues'  => $result['response']['data'] ?? []
    ]);
    exit;
    */

    /* =====================================================
       🟢 OPÇÃO 2 (ATIVA)
       Trazer SOMENTE filas específicas da Matriz
    ====================================================== */

    // Filas permitidas
    $allowedQueues = [
        '11000', // Suporte Matriz
        '11100', // Matriz Chat / Whats
    ];

    $result = $client->get('/api/v1/queues', [
        'include_archived' => 'true'
    ]);

    if ($result['http_code'] !== 200) {
        throw new Exception('Erro ao buscar filas (HTTP ' . $result['http_code'] . ')');
    }

    $queues   = $result['response']['data'] ?? [];
    $filtered = [];

    foreach ($queues as $queue) {
        if (
            isset($queue['number']) &&
            in_array($queue['number'], $allowedQueues, true)
        ) {
            $filtered[] = $queue;
        }
    }

    echo json_encode([
        'success' => true,
        'queues'  => $filtered
    ]);
    exit;

} catch (Throwable $e) {

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
    exit;
}
