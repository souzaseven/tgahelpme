<?php
$config = require '../config/api.php';

date_default_timezone_set('America/Cuiaba');
$hoje_start = date('Y-m-d') . 'T03:00:00.000Z';
$hoje_end   = date('Y-m-d', strtotime('+1 day')) . 'T02:59:59.999Z';

$url = $config['base_url'] . "/api/v1/report/complete_pause"
     . "?start_date=" . urlencode($hoje_start)
     . "&end_date="   . urlencode($hoje_end)
     . "&limit=100";

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