<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/evolux_client.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $config = require __DIR__ . '/config.php';
    $client = new EvoluxClient($config);

    /* ==========================
       PARAMETROS RECEBIDOS
    ========================== */
    $startDate = $_GET['start_date'] ?? null;
    $endDate   = $_GET['end_date'] ?? null;

    if (!$startDate || !$endDate) {
        throw new Exception('Datas inicial e final são obrigatórias.');
    }

    // Converte para ISO UTC (03:00 até 02:59 padrão Brasil)
    $params = [
        'start_date' => $startDate . 'T03:00:00.000Z',
        'end_date'   => $endDate   . 'T02:59:59.999Z',
        'agent_id'   => $_GET['agent_id']   ?? 'all',
        'queue_ids'  => $_GET['queue_ids']  ?? '',
        'call_type'  => $_GET['call_type']  ?? 'both',
        'phone_number' => $_GET['phone_number'] ?? '',
        'limit'      => $_GET['limit'] ?? 50,
        'page'       => $_GET['page']  ?? 1,
    ];

    // Remove vazios
    $params = array_filter($params, fn($v) => $v !== '');

    $result = $client->get('/api/v1/report/calls_history', $params);

    if ($result['http_code'] !== 200) {
        throw new Exception('Erro Evolux HTTP ' . $result['http_code']);
    }

    echo json_encode([
        'success' => true,
        'data'    => $result['response']
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
