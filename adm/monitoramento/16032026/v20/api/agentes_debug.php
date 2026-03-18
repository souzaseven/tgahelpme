<?php

/* =========================================================
ARQUIVO TEMPORÁRIO DE DEBUG
Acesse: /api/agentes_debug.php no navegador
Vai mostrar o JSON bruto da API para inspecionar os campos
========================================================= */

$config = require '../config/api.php';

$url = $config['base_url'] . "/api/v1/agents?page=1";

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => $config['timeout'],
    CURLOPT_HTTPHEADER     => ["token: " . $config['token']]
]);

$response = curl_exec($curl);
curl_close($curl);

$json = json_decode($response, true);

/* Pegar apenas o PRIMEIRO agente para análise */
$primeiroAgente = $json['data'][0] ?? null;

header('Content-Type: application/json');

echo json_encode([
    "total_agentes"   => count($json['data'] ?? []),
    "campos_disponiveis" => $primeiroAgente ? array_keys($primeiroAgente) : [],
    "primeiro_agente" => $primeiroAgente,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);