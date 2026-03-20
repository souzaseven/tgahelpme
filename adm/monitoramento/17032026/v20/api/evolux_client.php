<?php

/*
|--------------------------------------------------------------------------
| CARREGAR CONFIGURAÇÕES DA API
|--------------------------------------------------------------------------
| Este arquivo lê o config/api.php onde estão:
| - base_url da API
| - token de autenticação
| - timeout
*/

$config = require __DIR__ . '/../config/api.php';


/*
|--------------------------------------------------------------------------
| FUNÇÃO DE REQUISIÇÃO PARA API EVOLUX
|--------------------------------------------------------------------------
| Esta função realiza requisições HTTP para a API.
| Todas as chamadas passam por aqui.
|
| Parâmetros:
|   $endpoint → rota da API
|
| Retorno:
|   JSON convertido para array
*/

function evolux_request($endpoint)
{
    global $config;

    /*
    |--------------------------------------------------------------------------
    | MONTAR URL FINAL
    |--------------------------------------------------------------------------
    */

    $url = rtrim($config['base_url'], '/') . $endpoint;


    /*
    |--------------------------------------------------------------------------
    | INICIAR CURL
    |--------------------------------------------------------------------------
    */

    $ch = curl_init($url);


    /*
    |--------------------------------------------------------------------------
    | CONFIGURAÇÕES DA REQUISIÇÃO
    |--------------------------------------------------------------------------
    */

    curl_setopt_array($ch, [

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_HTTPHEADER => [
            'token: ' . $config['token'],
            'Content-Type: application/json'
        ],

        CURLOPT_TIMEOUT => $config['timeout']

    ]);


    /*
    |--------------------------------------------------------------------------
    | EXECUTAR REQUISIÇÃO
    |--------------------------------------------------------------------------
    */

    $response = curl_exec($ch);


    /*
    |--------------------------------------------------------------------------
    | TRATAMENTO DE ERROS
    |--------------------------------------------------------------------------
    */

    if (curl_errno($ch)) {

        return [
            'error' => curl_error($ch)
        ];

    }


    curl_close($ch);


    /*
    |--------------------------------------------------------------------------
    | RETORNAR JSON CONVERTIDO
    |--------------------------------------------------------------------------
    */

    return json_decode($response, true);

}