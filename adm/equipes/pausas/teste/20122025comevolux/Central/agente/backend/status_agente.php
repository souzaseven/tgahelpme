<?php
/**
 * ===================================================
 * STATUS DO AGENTE — BACKEND (PROXY EVOLUX)
 * ---------------------------------------------------
 * ESTRATÉGIA CORRETA:
 * - A Evolux NÃO possui GET /agents/{id}
 * - Status deve ser inferido via GET /agents
 *
 * LÓGICA:
 * - Busca lista de agentes
 * - Localiza o agent_id
 * - Determina:
 *   • ativo
 *   • pausa
 *   • offline
 * ===================================================
 */

header("Content-Type: application/json; charset=utf-8");

// ===================================================
// CONFIGURAÇÃO
// ===================================================
$config = require __DIR__ . "/config.php";

$baseUrl = rtrim($config["base_url"], "/");
$token   = $config["token"];
$timeout = $config["timeout"] ?? 15;


// ===================================================
// VALIDAÇÃO
// ===================================================
$agentId = $_GET["agent_id"] ?? null;

if (!$agentId || !is_numeric($agentId)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "erro"    => "agent_id inválido"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}


// ===================================================
// CONSULTA À API EVOLUX (LISTA DE AGENTES)
// ===================================================
$page  = 1;
$limit = 50;
$found = false;

$status = "offline";
$pausa  = null;

do {

    $url = "{$baseUrl}/api/v1/agents?limit={$limit}&page={$page}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => [
            "token: {$token}",
            "Accept: application/json"
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        http_response_code($httpCode ?: 500);
        echo json_encode([
            "success" => false,
            "erro"    => "Erro ao consultar agentes na Evolux",
            "status"  => $httpCode
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $json = json_decode($response, true);
    $lista = $json["data"] ?? [];

    foreach ($lista as $ag) {
        if ((int)$ag["id"] === (int)$agentId) {

            $found = true;

            // 🔹 Logado
            if (!empty($ag["logged"])) {
                $status = "ativo";
            }

            // 🔹 Pausa tem prioridade
            if (!empty($ag["current_pause"])) {
                $status = "pausa";
                $pausa  = $ag["current_pause"]["description"] ?? "Pausa";
            }

            break;
        }
    }

    $lastPage = $json["pagination"]["last_page"] ?? 1;
    $page++;

} while (!$found && $page <= $lastPage);


// ===================================================
// RESPOSTA FINAL
// ===================================================
echo json_encode([
    "success" => true,
    "status"  => $status,
    "pausa"   => $pausa
], JSON_UNESCAPED_UNICODE);
