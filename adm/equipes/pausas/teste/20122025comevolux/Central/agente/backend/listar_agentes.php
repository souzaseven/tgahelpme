<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/evolux_client.php';

header('Content-Type: application/json; charset=utf-8');

try {

    $agentes = [];
    $page = 1;

    do {
        $res = evoluxRequest('GET', "/api/v1/agents?page={$page}");

        if (($res['code'] ?? 500) !== 200) {
            throw new Exception("Erro ao consultar agentes (página {$page})");
        }

        $data = $res['body']['data'] ?? [];
        $pagination = $res['body']['pagination'] ?? [];

        foreach ($data as $agent) {

            // Status real do agente
            if ($agent['archived']) {
                $status = 'arquivado';
            } elseif (!$agent['enable']) {
                $status = 'desativado';
            } elseif (!empty($agent['current_pause'])) {
                $status = 'em_pausa';
            } elseif (empty($agent['current_extension']['endpoint'])) {
                $status = 'deslogado';
            } else {
                $status = 'ativo';
            }

            $agentes[] = [
                'id'        => $agent['id'],
                'nome'      => $agent['name'],
                'login'     => $agent['login'],
                'status'    => $status,
                'fila'      => $agent['current_outbound_queue']['name'] ?? null,
                'pause_id'  => $agent['current_pause']['id'] ?? null,
                'extensao'  => $agent['current_extension']['number'] ?? null,
                'ramal_logado' => !empty($agent['current_extension']['endpoint']),
                'ultimo_login' => $agent['last_login']['time_login'] ?? null
            ];
        }

        $page++;
        $temProximaPagina = !empty($pagination['next_url']);

    } while ($temProximaPagina);

    echo json_encode([
        'success' => true,
        'total'   => count($agentes),
        'agentes' => $agentes
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'erro'    => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
