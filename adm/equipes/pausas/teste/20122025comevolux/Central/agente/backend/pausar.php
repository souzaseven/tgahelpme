<?php
header("Content-Type: application/json; charset=utf-8");

try {

    $configPath = __DIR__ . '/config.php';
    $mapaPath   = __DIR__ . '/mapa_pausas.php';

    if (!file_exists($configPath)) {
        throw new Exception("config.php não encontrado");
    }

    if (!file_exists($mapaPath)) {
        throw new Exception("mapa_pausas.php não encontrado");
    }

    $config = require $configPath;
    $mapaPausas = require $mapaPath;

    if (!is_array($mapaPausas)) {
        throw new Exception("mapa_pausas.php inválido");
    }

    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        throw new Exception("JSON inválido no body");
    }

    $ids       = $input['agent_ids'] ?? [];
    $pauseType = $input['pause_type'] ?? null;

    if (!is_array($ids) || empty($ids)) {
        throw new Exception("agent_ids ausente ou inválido");
    }

    if (!$pauseType || !isset($mapaPausas[$pauseType])) {
        throw new Exception("pause_type inválido: {$pauseType}");
    }

    $pauseId = (int) $mapaPausas[$pauseType];
    $resultados = [];

    foreach ($ids as $agentId) {

        $url = rtrim($config['base_url'], '/') . "/api/v1/agents/{$agentId}/pause";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $config['timeout'] ?? 15,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                "token: {$config['token']}",
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS     => json_encode([
                "pause_id" => $pauseId
            ])
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            throw new Exception("Erro CURL: " . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $resultados[] = [
            "agent_id" => $agentId,
            "pause_id" => $pauseId,
            "http"     => $httpCode
        ];
    }

    echo json_encode([
        "success"     => true,
        "pause_type" => $pauseType,
        "pause_id"   => $pauseId,
        "resultados" => $resultados
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "erro"    => $e->getMessage(),
        "arquivo" => $e->getFile(),
        "linha"   => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
