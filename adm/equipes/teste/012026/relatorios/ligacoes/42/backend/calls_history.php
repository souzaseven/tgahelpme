<?php
/**
 * =========================================================
 * Calls History – Evolux
 * Endpoint intermediário para o Painel de Ligações
 * Autor: Anderson de Souza
 * Status: FINAL / PRODUÇÃO (CORRIGIDO: fuso/horário)
 * =========================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/evolux_client.php';

header('Content-Type: application/json; charset=utf-8');

function normalizeTime($t, $fallback) {
    if (!is_string($t) || $t === '') return $fallback;
    $t = trim($t);
    return preg_match('/^\d{2}:\d{2}$/', $t) ? $t : $fallback;
}

function toUtcIsoZ(DateTime $dt): string {
    // Converte para UTC e formata como ISO com .000Z
    $dt->setTimezone(new DateTimeZone('UTC'));
    return $dt->format('Y-m-d\TH:i:s') . '.000Z';
}

try {
    /* ==========================
       CLIENTE EVOLUX
    ========================== */
    $config = require __DIR__ . '/config.php';
    $client = new EvoluxClient($config);

    /* ==========================
       DATAS (OBRIGATÓRIAS)
    ========================== */
    $startDate = $_GET['start_date'] ?? null; // YYYY-MM-DD
    $endDate   = $_GET['end_date']   ?? null; // YYYY-MM-DD

    if (!$startDate || !$endDate) {
        throw new Exception('Datas inicial e final são obrigatórias');
    }

    /* ==========================
       HORAS (OPCIONAL)
       Se vier vazio, pega dia inteiro
    ========================== */
    $startTime = normalizeTime($_GET['start_time'] ?? '', '00:00');
    $endTime   = normalizeTime($_GET['end_time']   ?? '', '23:59');

    /* ==========================
       FUSO LOCAL (MT)
       Ajuste aqui se mudar servidor/local:
       America/Cuiaba = UTC-4 (Tangará da Serra/MT)
    ========================== */
    $tzLocal = new DateTimeZone('America/Cuiaba');

    // Monta DateTime LOCAL e converte para UTC (Z)
    $dtStartLocal = new DateTime($startDate . ' ' . $startTime . ':00', $tzLocal);

    // Para o fim, usamos :59 (último minuto) e depois mandamos .000Z (suficiente)
    // (Se quiser ultra preciso: poderia usar microsegundos, mas não é necessário aqui)
    $dtEndLocal   = new DateTime($endDate . ' ' . $endTime . ':59', $tzLocal);

    // Se por acaso o usuário colocar fim menor que início no mesmo dia,
    // a gente não quebra: mantém como está e a API retornará vazio (comportamento esperado).
    $startIsoZ = toUtcIsoZ($dtStartLocal);
    $endIsoZ   = toUtcIsoZ($dtEndLocal);

    /* ==========================
       PARAMS BASE
    ========================== */
    $params = [
        'start_date' => $startIsoZ,
        'end_date'   => $endIsoZ,
        'call_type'  => 'both',
        'limit'      => (int)($_GET['limit'] ?? 50),
        'page'       => (int)($_GET['page']  ?? 1),
    ];

    /* ==========================
       AGENTE
    ========================== */
    $params['agent_id'] = (isset($_GET['agent_id']) && $_GET['agent_id'] !== '')
        ? $_GET['agent_id']
        : 'all';

    /* ==========================
       FILA
    ========================== */
    if (isset($_GET['queue_ids']) && $_GET['queue_ids'] !== '') {
        $params['queue_ids'] = $_GET['queue_ids'];
    }

    /* ==========================
       TELEFONE
    ========================== */
    if (isset($_GET['phone_number']) && trim($_GET['phone_number']) !== '') {
        $params['phone_number'] = trim($_GET['phone_number']);
    }

    /* ==========================
       LIMPEZA FINAL
    ========================== */
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }

    /* ==========================
       CHAMADA EVOLUX
    ========================== */
    $result = $client->get('/api/v1/report/calls_history', $params);

    /**
     * A Evolux pode retornar HTTP 200 com payload incompleto.
     * Garantimos SEMPRE um JSON válido ao frontend.
     */
    if (
        $result['http_code'] !== 200 ||
        !isset($result['response']['data'])
    ) {
        echo json_encode([
            'success' => true,
            'data' => [
                'data' => ['calls' => []],
                'pagination' => null,
                'debug' => [
                    'sent_params' => $params
                ]
            ]
        ]);
        exit;
    }

    /* ==========================
       RESPOSTA FINAL
    ========================== */
    echo json_encode([
        'success' => true,
        'data'    => $result['response'],
        // 🔎 opcional: ajuda a validar se está enviando o horário certo
        'debug'   => [
            'sent_params' => $params
        ]
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
