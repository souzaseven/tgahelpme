<?php

/* =========================================================
CARREGAR CONFIGURAÇÃO DA API
========================================================= */

$config = require '../config/api.php';



/* =========================================================
RECEBER PAGINA DA REQUISIÇÃO
========================================================= */

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;



/* =========================================================
MONTAR URL DA API COM PAGINAÇÃO
========================================================= */

$url = $config['base_url'] . "/api/v1/agents?page=" . $page;



/* =========================================================
INICIAR CURL
========================================================= */

$curl = curl_init();



/* =========================================================
CONFIGURAÇÃO DA REQUISIÇÃO
========================================================= */

curl_setopt_array($curl, [

    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => $config['timeout'],
    CURLOPT_HTTPHEADER     => [
        "token: " . $config['token']
    ]

]);



/* =========================================================
EXECUTAR REQUISIÇÃO
========================================================= */

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);



/* =========================================================
VALIDAR RESPOSTA
========================================================= */

if ($httpCode !== 200) {

    header('Content-Type: application/json');
    echo json_encode(["erro" => "Falha ao consultar API Evolux"]);
    exit;

}



/* =========================================================
DECODIFICAR RESPOSTA
========================================================= */

$json = json_decode($response, true);



/* =========================================================
FILTRAR AGENTES
- Remover desabilitados (enable = false)
- Remover arquivados   (archived = true)
- Online/Offline é controlado pelo frontend via filtro de status
========================================================= */

if (isset($json['data']) && is_array($json['data'])) {

    $json['data'] = array_values(
        array_filter($json['data'], function($agent) {

            $habilitado   = $agent['enable']   === true;
            $naoArquivado = $agent['archived'] === false;

            return $habilitado && $naoArquivado;

        })
    );

}



/* =========================================================
RETORNAR RESPOSTA PARA O FRONTEND
========================================================= */

header('Content-Type: application/json');
echo json_encode($json);