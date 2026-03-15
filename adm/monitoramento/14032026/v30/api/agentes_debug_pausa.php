<?php
$config = require '../config/api.php';
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL            => $config['base_url'] . "/api/v1/agents?page=1",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => $config['timeout'],
    CURLOPT_HTTPHEADER     => ["token: " . $config['token']]
]);
$json = json_decode(curl_exec($curl), true);
curl_close($curl);

// Pega só quem tem current_pause preenchido
$pausados = array_values(array_filter(
    $json['data'] ?? [],
    fn($a) => !empty($a['current_pause'])
));

header('Content-Type: application/json');
echo json_encode($pausados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);