<?php
header('Content-Type: text/plain');

$url = "https://tgasistemas.evolux.io/panel/queue?id=all";
$cookies = "seus_cookies_aqui"; // Cole seus cookies

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    CURLOPT_COOKIE => $cookies,
    CURLOPT_HEADER => true,
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

curl_close($ch);

echo "HTTP CODE: $httpCode\n";
echo "HEADERS:\n$headers\n";
echo "BODY LENGTH: " . strlen($body) . "\n";

// Salvar para análise
file_put_contents('debug_response.html', $body);
?>