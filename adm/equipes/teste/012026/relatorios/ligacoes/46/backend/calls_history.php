<?php
/**
 * =========================================================
 * Calls History – Evolux
 * Endpoint intermediário para o Painel de Ligações
 * Autor: Anderson de Souza
 * Status: FINAL / PRODUÇÃO (SEM HORA / SEM PER_PAGE)
 * =========================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/evolux_client.php';

header('Content-Type: application/json; charset=utf-8');

try {

    $config = require __DIR__ . '/config.php';
    $client = new EvoluxClient($config);

    $startDate = $_GET['start_date'] ?? null;
    $endDate   = $_GET['end_date']   ?? null;

    if (!$startDate || !$endDate) {
        throw new Exception('Datas inicial e final são obrigatórias');
    }

    // ✅ Padrão anterior (03:00 → 02:59) como você já usava
    $params = [
        'start_date' => $startDate . 'T03:00:00.000Z',
        'end_date'   => $endDate   . 'T02:59:59.999Z',
        'call_type'  => 'both',
        'limit'      => 50, // fixo (sem filtro de registros)
        'page'       => (int)($_GET['page'] ?? 1),
    ];

    // AGENTE
    $params['agent_id'] = (isset($_GET['agent_id']) && $_GET['agent_id'] !== '')
        ? $_GET['agent_id']
        : 'all';

    // FILA
    if (isset($_GET['queue_ids']) && $_GET['queue_ids'] !== '') {
        $params['queue_ids'] = $_GET['queue_ids'];
    }

    // TELEFONE
    if (isset($_GET['phone_number']) && trim($_GET['phone_number']) !== '') {
        $params['phone_number'] = trim($_GET['phone_number']);
    }

    // LIMPEZA FINAL
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }

    $result = $client->get('/api/v1/report/calls_history', $params);

    if (
        $result['http_code'] !== 200 ||
        !isset($result['response']['data'])
    ) {
        echo json_encode([
            'success' => true,
            'data' => [
                'data' => ['calls' => []],
                'pagination' => null
            ]
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data'    => $result['response']
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
