<?php
/**
 * ===================================================
 * CRIAR AGENTE — BACKEND (PROXY EVOLUX)
 * Versão: 2.2.0
 * ===================================================
 */

header("Content-Type: application/json; charset=utf-8");

// ---------------------------------------------------
// 1.0 CONFIGURAÇÃO
// ---------------------------------------------------
$config = require __DIR__ . "/config.php";

$baseUrl = rtrim($config["base_url"] ?? "", "/");
$token   = $config["token"] ?? "";
$timeout = $config["timeout"] ?? 15;

if (!$baseUrl || !$token) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "erro" => "Configuração da Evolux inválida"
    ]);
    exit;
}

// ---------------------------------------------------
// 2.0 ENTRADA
// ---------------------------------------------------
$input = json_decode(file_get_contents("php://input"), true);

$nome  = trim($input["nome"]  ?? "");
$login = trim($input["login"] ?? "");
$ramal = trim($input["ramal"] ?? "");
$fila  = (int)($input["fila"] ?? 0);

// ---------------------------------------------------
// 3.0 VALIDAÇÃO
// ---------------------------------------------------
if (!$nome || strlen($nome) < 3) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "erro" => "Nome inválido"
    ]);
    exit;
}

if (!$login) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "erro" => "Login é obrigatório"
    ]);
    exit;
}

if (!$fila) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "erro" => "Fila é obrigatória"
    ]);
    exit;
}

// ---------------------------------------------------
// 4.0 PASSWORD (OBRIGATÓRIO EM MUITOS TENANTS)
// ---------------------------------------------------
// Gera senha temporária segura
$passwordTemp = 'Temp@' . rand(1000, 9999);

// ---------------------------------------------------
// 5.0 PAYLOAD EVOLUX
// ---------------------------------------------------
$payload = [
    "name"      => $nome,
    "login"     => $login,
    "password"  => $passwordTemp,   // 🔥 TESTE CRÍTICO
    "enable"    => true,
    "queue_ids" => [$fila]
];

// Ramal é opcional
if ($ramal !== "") {
    $payload["extension"] = $ramal;
}

// ---------------------------------------------------
// 6.0 REQUISIÇÃO PARA EVOLUX
// ---------------------------------------------------
$url = "{$baseUrl}/api/v1/agents";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => $timeout,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        "token: {$token}",
        "Content-Type: application/json",
        "Accept: application/json"
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE)
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

// ---------------------------------------------------
// 7.0 TRATAMENTO DE ERRO
// ---------------------------------------------------
if ($response === false) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "erro"    => "Erro CURL",
        "debug"   => $curlErr
    ]);
    exit;
}

$json = json_decode($response, true);

if ($httpCode !== 200 && $httpCode !== 201) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "erro"    => "Erro ao criar agente na Evolux",
        "status"  => $httpCode,
        "payload" => $payload,   // 🔍 ajuda debug
        "raw"     => $json
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------
// 8.0 SUCESSO
// ---------------------------------------------------
echo json_encode([
    "success"  => true,
    "mensagem" => "Agente criado com sucesso",
    "id"       => $json["data"]["id"] ?? null,
    "login"    => $login,
    "password_temporaria" => $passwordTemp // opcional exibir
], JSON_UNESCAPED_UNICODE);
