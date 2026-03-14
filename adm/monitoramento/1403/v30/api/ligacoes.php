<?php

/* =========================================================
HISTÓRICO DE CHAMADAS DO DIA
Endpoint: /api/v1/report/calls_history
Retorna ligações de todos os agentes no dia atual
========================================================= */

$config = require '../config/api.php';



/* =========================================================
MONTAR DATAS DO DIA ATUAL (fuso horário Brasil -03:00)
start_date = hoje às 03:00 UTC (meia-noite Brasília)
end_date   = amanhã às 02:59:59 UTC (23:59:59 Brasília)
========================================================= */

$timezone  = new DateTimeZone('America/Sao_Paulo');
$utc       = new DateTimeZone('UTC');

$hoje      = new DateTime('today', $timezone);
$amanha    = new DateTime('tomorrow', $timezone);

$hoje->setTimezone($utc);
$amanha->setTimezone($utc);
$amanha->modify('-1 second');

$startDate = urlencode($hoje->format('Y-m-d\TH:i:s.000\Z'));
$endDate   = urlencode($amanha->format('Y-m-d\TH:i:s.999\Z'));



/* =========================================================
RECEBER PÁGINA
========================================================= */

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;



/* =========================================================
MONTAR URL
agent_id=all traz todos os agentes
call_type=both traz entrada e saída
========================================================= */

$url = $config['base_url']
     . "/api/v1/report/calls_history"
     . "?start_date={$startDate}"
     . "&end_date={$endDate}"
     . "&agent_id=all"
     . "&call_type=both"
     . "&limit=50"
     . "&page={$page}";



/* =========================================================
EXECUTAR REQUISIÇÃO
========================================================= */

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => $config['timeout'],
    CURLOPT_HTTPHEADER     => ["token: " . $config['token']]
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);



/* =========================================================
VALIDAR RESPOSTA
========================================================= */

if ($httpCode !== 200) {
    header('Content-Type: application/json');
    echo json_encode(["erro" => "Falha ao consultar histórico de chamadas ($httpCode)"]);
    exit;
}



/* =========================================================
RETORNAR RESPOSTA
========================================================= */

header('Content-Type: application/json');
echo $response;