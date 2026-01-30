<?php
/**
 * =========================================================
 * Calls History – Evolux
 * Endpoint intermediário para o Painel de Ligações
 * Autor: Anderson de Souza
 * Status: FINAL / PRODUÇÃO (CORREÇÃO DEFINITIVA DE DATA)
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

    /* ==========================
       DATAS (OBRIGATÓRIAS)
    ========================== */
    $startDate = $_GET['start_date'] ?? null;
    $endDate   = $_GET['end_date']   ?? null;

    if (!$startDate || !$endDate) {
        throw new Exception('Datas inicial e final são obrigatórias');
    }

    /* ==========================
       AJUSTE CORRETO DE DATAS
       (funciona para MESMO DIA)
    ========================== */
    $tz = new DateTimeZone('America/Sao_Paulo');

    $start = new DateTime($startDate, $tz);
    $end   = new DateTime($endDate,   $tz);

    // Se for o mesmo dia, avança o fim em +1 dia
    if ($startDate === $endDate) {
        $end->modify('+1 day');
    }

    /* ==========================
       PARAMS BASE
    ========================== */
    $params = [
        'start_date' => $start->setTime(0, 0, 0)->format('Y-m-d\TH:i:s.000\Z'),
        'end_date'   => $end->setTime(23, 59, 59)->format('Y-m-d\TH:i:s.999\Z'),
        'call_type'  => 'both',
        'limit'      => 50,
        'page'       => (int)($_GET['page'] ?? 1),
    ];

    /* ==========================
       AGENTE
    ========================== */
    $params['agent_id'] = (!empty($_GET['agent_id']))
        ? $_GET['agent_id']
        : 'all';

    /* ==========================
       FILA
    ========================== */
    if (!empty($_GET['queue_ids'])) {
        $params['queue_ids'] = $_GET['queue_ids'];
    }

    /* ==========================
       TELEFONE
    ========================== */
    if (!empty($_GET['phone_number'])) {
        $params['phone_number'] = trim($_GET['phone_number']);
    }

    /* ==========================
       LIMPEZA FINAL
    ========================== */
    foreach ($params as $k => $v) {
        if ($v === '' || $v === null) {
            unset($params[$k]);
        }
    }
/* ==========================
   MÚLTIPLAS FILAS (TODAS)
   COM PAGINAÇÃO NORMAL
========================== */
if (!empty($_GET['queue_ids']) && str_contains($_GET['queue_ids'], ',')) {

    $queueIds = array_map('trim', explode(',', $_GET['queue_ids']));
    $page     = (int)($_GET['page'] ?? 1);
    $allCalls = [];

    foreach ($queueIds as $queueId) {

        $paramsFila = $params;
        $paramsFila['queue_ids'] = $queueId;
        $paramsFila['page']      = $page;

        $result = $client->get('/api/v1/report/calls_history', $paramsFila);

        if (
            $result['http_code'] === 200 &&
            isset($result['response']['data']['calls'])
        ) {
            $allCalls = array_merge(
                $allCalls,
                $result['response']['data']['calls']
            );
        }
    }

    // ordena por data (mais recente primeiro)
    usort($allCalls, function ($a, $b) {
        return strtotime($b['date_join']) <=> strtotime($a['date_join']);
    });

    // 🔥 limita a 50 registros (igual padrão Evolux)
    $allCalls = array_slice($allCalls, 0, 50);

    echo json_encode([
        'success' => true,
        'data' => [
            'data' => [
                'calls' => $allCalls
            ],
            // paginação segue sendo controlada no frontend
            'pagination' => [
                'page' => $page,
                'limit' => 50
            ]
        ]
    ]);
    exit;
}


    /* ==========================
       CHAMADA EVOLUX
    ========================== */
    $result = $client->get('/api/v1/report/calls_history', $params);

    /* ==========================
       GARANTIA DE RETORNO
    ========================== */
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
