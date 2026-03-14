<?php

/* =========================================================
DEBUG — VERIFICAR CAMPOS DE ESTATÍSTICAS DO AGENTE
Acesse: /api/agentes_stats_debug.php
========================================================= */

$config = require '../config/api.php';

/* Testar endpoints comuns de stats da Evolux */
$endpoints = [
    "agentes"      => "/api/v1/agents?page=1",
    "calls"        => "/api/v1/calls?page=1",
    "sessions"     => "/api/v1/agent_sessions?page=1",
    "stats"        => "/api/v1/agent_stats?page=1",
    "reports"      => "/api/v1/reports/agents?page=1",
];

$resultados = [];

foreach ($endpoints as $nome => $path) {

    $url  = $config['base_url'] . $path;
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPHEADER     => ["token: " . $config['token']]
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode === 200) {

        $json    = json_decode($response, true);
        $primeiro = $json['data'][0] ?? $json[0] ?? null;

        $resultados[$nome] = [
            "status"  => "✅ OK ($httpCode)",
            "campos"  => $primeiro ? array_keys($primeiro) : [],
            "amostra" => $primeiro,
        ];

    } else {

        $resultados[$nome] = [
            "status" => "❌ HTTP $httpCode",
            "campos" => [],
        ];

    }

}

header('Content-Type: application/json');
echo json_encode($resultados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);