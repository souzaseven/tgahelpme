<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/EvoluxAPI.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>🔍 Teste API Evolux</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #0f172a;
            color: #f1f5f9;
            padding: 2rem;
        }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .warning { color: #f59e0b; }
        .info { color: #3b82f6; }
        pre {
            background: #1e293b;
            padding: 1rem;
            border-radius: 8px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>🔍 Teste de Conexão API Evolux</h1>
    <pre><?php

try {
    echo "=== CONFIGURAÇÃO ===\n";
    $config = require __DIR__ . '/../config/api.php';
    echo "✅ Arquivo de config carregado\n";
    echo "URL Base: " . $config['base_url'] . "\n";
    echo "Token: " . substr($config['token'], 0, 20) . "...\n";
    echo "Timeout: " . $config['timeout'] . "s\n\n";
    
    echo "=== INICIALIZAÇÃO ===\n";
    $api = new EvoluxAPI();
    echo "✅ Classe EvoluxAPI instanciada\n\n";
    
    echo "=== TESTANDO ENDPOINT ===\n";
    echo "Endpoint: GET /api/v1/agents\n";
    echo "Fazendo requisição...\n\n";
    
    $result = $api->getAgentes(['limit' => 5, 'page' => 1]);
    
    echo "=== RESPOSTA ===\n";
    echo "Success: " . ($result['success'] ? '✅ SIM' : '❌ NÃO') . "\n";
    echo "HTTP Code: " . ($result['code'] ?? 'N/A') . "\n";
    
    if (isset($result['meta'])) {
        echo "Meta Status: " . $result['meta']['status'] . "\n";
        echo "Meta Message: " . $result['meta']['message'] . "\n";
    }
    
    if (isset($result['error'])) {
        echo "\n❌ ERRO DETECTADO:\n";
        echo $result['error'] . "\n";
    }
    
    echo "\nTem dados: " . (!empty($result['data']) ? '✅ SIM (' . count($result['data']) . ' itens)' : '❌ NÃO') . "\n";
    
    if (!empty($result['data']) && is_array($result['data'])) {
        echo "\n=== PRIMEIRO AGENTE ===\n";
        print_r($result['data'][0]);
    }
    
    echo "\n=== RESPOSTA COMPLETA ===\n";
    print_r($result);
    
    // Teste manual com CURL
    echo "\n\n=== TESTE MANUAL CURL ===\n";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['base_url'] . '/api/v1/agents?limit=1',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'token: ' . $config['token'],
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    if ($error) {
        echo "❌ CURL Error: $error\n";
    }
    echo "Response:\n";
    echo $response . "\n";
    
} catch (Exception $e) {
    echo "\n❌ EXCEPTION:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString();
}

?></pre>
</body>
</html>
```
