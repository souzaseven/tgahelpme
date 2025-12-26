<?php
/**
 * =====================================================
 * editar_agente.php — Edita agente na Evolux (OFICIAL)
 * =====================================================
 */

header('Content-Type: application/json; charset=utf-8');

try {

    // =====================================================
    // 1. CONFIGURAÇÃO
    // =====================================================
    $config = require __DIR__ . '/config.php';

    if (
        empty($config['base_url']) ||
        empty($config['token'])
    ) {
        throw new Exception('Configuração da Evolux inválida');
    }

    $baseUrl = rtrim($config['base_url'], '/');
    $token   = $config['token'];
    $timeout = $config['timeout'] ?? 15;

    // =====================================================
    // 2. ENTRADA
    // =====================================================
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['id'])) {
        throw new Exception('agent_id não informado');
    }

    if (empty($input['nome']) || empty($input['login'])) {
        throw new Exception('Nome e login são obrigatórios');
    }

    // ⚠️ filas devem ser IDs numéricos
    $queueIds = [];

    if (!empty($input['queues']) && is_array($input['queues'])) {
        $queueIds = array_map('intval', $input['queues']);
    } elseif (!empty($input['queue_id'])) {
        $queueIds = [(int) $input['queue_id']];
    }

    // =====================================================
    // 3. PAYLOAD EVOLUX (CONFORME DOC)
    // =====================================================
    $payload = [
        'name'   => $input['nome'],
        'login'  => $input['login'],
    ];

    if (!empty($input['password'])) {
        $payload['password'] = $input['password'];
    }

    if (!empty($queueIds)) {
        $payload['queues'] = $queueIds;

        // prioridade padrão = 1
        $payload['levels'] = [[
            'queue_id' => $queueIds,
            'priority' => [1]
        ]];
    }

    // =====================================================
    // 4. CHAMADA À EVOLUX
    // =====================================================
    $url = "{$baseUrl}/api/v1/agents/{$input['id']}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'token: ' . $token
        ],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        throw new Exception('Erro CURL: ' . curl_error($ch));
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Evolux retornou HTTP {$httpCode}");
    }

    // =====================================================
    // 5. SUCESSO REAL
    // =====================================================
    echo json_encode([
        'success' => true
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'erro'    => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
