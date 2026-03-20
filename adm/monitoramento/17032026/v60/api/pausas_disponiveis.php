<?php
$config = require '../config/api.php';

$url = $config['base_url'] . "/api/v1/pauses?enable=true&archived=false&limit=50";

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => $config['timeout'],
    CURLOPT_HTTPHEADER     => ["token: " . $config['token']]
]);

$response = curl_exec($curl);
curl_close($curl);

header('Content-Type: application/json');
echo $response;