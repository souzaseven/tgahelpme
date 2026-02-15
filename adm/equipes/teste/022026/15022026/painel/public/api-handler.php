<?php
/**
 * API Handler - Painel Evolux Admin
 * Versão Enterprise Estável
 */

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// NÃO definir Content-Type aqui (evita conflito com áudio)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();
require_once __DIR__ . '/../app/EvoluxAPI.php';

$api    = new EvoluxAPI();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ✅ permite ligar debug via URL: api-handler.php?action=...&debug=1
$debug = isset($_GET['debug']) && $_GET['debug'] == '1';

try {

    if (!$action) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'meta' => [
                'status' => 400,
                'message' => 'Ação não informada'
            ],
            'error' => 'Parâmetro action obrigatório'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    switch ($action) {

        /* ============================================================
           ================= AGENTES =================
        ============================================================ */

        case 'listar_agentes':
            $result = $api->getAgentes([
                'limit' => $_GET['limit'] ?? 50,
                'page'  => $_GET['page'] ?? 1
            ]);
        break;

        case 'salvar_agente':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

            $payload = [
                'name'   => $data['name'] ?? '',
                'login'  => $data['login'] ?? '',
                'queues' => $data['queues'] ?? []
            ];

            if (!empty($data['password'])) {
                $payload['password'] = $data['password'];
            }

            $result = $api->createAgente($payload);
        break;

        case 'editar_agente':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id   = $data['id'] ?? '';

            if (!$id) {
                throw new Exception('ID do agente não informado');
            }

            unset($data['id']);
            $result = $api->updateAgente($id, $data);
        break;

        case 'deletar_agente':
            $id = $_GET['id'] ?? '';
            $result = $api->deleteAgente($id);
        break;

        case 'pausar_agente':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = $data['id'] ?? $_GET['id'] ?? '';
            $pause_id = (int)($data['pause_id'] ?? 1);

            $result = $api->pausarAgente($id, $pause_id);
        break;

        case 'despausar_agente':
            $id = $_GET['id'] ?? '';
            $result = $api->despausarAgente($id);
        break;

        case 'deslogar_agente':
            $id = $_GET['id'] ?? '';
            $result = $api->deslogarAgente($id);
        break;

        /* ============================================================
           ================= CALLCENTER =================
        ============================================================ */

        case 'buscar_call':
            $callId = $_GET['id'] ?? null;
            if (!$callId) throw new Exception('Call ID não informado');
            $result = $api->getCallById($callId);
        break;

        case 'baixar_gravacao':
            $callId = $_GET['id'] ?? null;

            if (!$callId) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'Call ID não informado'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $audioResult = $api->baixarGravacaoRaw($callId);

            if ($audioResult['code'] !== 200 || empty($audioResult['audio'])) {
                http_response_code($audioResult['code'] ?: 404);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'Gravação não encontrada'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            header('Content-Type: ' . ($audioResult['content_type'] ?: 'audio/wav'));
            header('Content-Disposition: inline; filename="call_' . $callId . '.wav"');
            header('Content-Length: ' . strlen($audioResult['audio']));
            header('Cache-Control: no-cache');
            header('Pragma: no-cache');

            echo $audioResult['audio'];
            exit;
        break;

        /* ============================================================
           ================= CHAMADAS =================
        ============================================================ */

        case 'transferir_chamada':
            $data = json_decode(file_get_contents('php://input'), true);
            $uuid = $data['uuid'] ?? null;
            $destino = $data['destination_number'] ?? null;
            $leg = $data['leg'] ?? 'bleg';
            if (!$uuid || !$destino) throw new Exception('UUID ou destino não informado');
            $result = $api->transferirChamada($uuid, $destino, $leg);
        break;

        case 'desligar_chamada':
            $data = json_decode(file_get_contents('php://input'), true);
            $uuid = $data['uuid'] ?? null;
            if (!$uuid) throw new Exception('UUID não informado');
            $result = $api->desligarChamada($uuid);
        break;

        case 'originar_chamada_agente':
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $api->originarChamadaAgente(
                $data['from'],
                $data['to'],
                $data['call_info'] ?? []
            );
        break;

        case 'originar_chamada_externa':
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $api->originarChamadaExterna(
                $data['from'],
                $data['to'],
                $data['extension_group'],
                $data['call_info'] ?? []
            );
        break;

        case 'call_info':
            $uuid = $_GET['uuid'] ?? null;
            if (!$uuid) throw new Exception('UUID não informado');
            $result = $api->getCallInfo($uuid);
        break;

        /* ============================================================
           ================= CDR =================
        ============================================================ */

        case 'buscar_cdr':
            $uuid = $_GET['uuid'] ?? null;
            if (!$uuid) throw new Exception('UUID não informado');
            $result = $api->getCDR($uuid);
        break;

        /* ============================================================
           ================= DISCADOR =================
        ============================================================ */

        case 'buscar_assinante':
            $result = $api->buscarAssinante($_GET);
        break;

        case 'cadastrar_assinante':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $campaignId = $data['campaign_id'] ?? null;
            if (!$campaignId) throw new Exception('Campaign ID não informado');
            unset($data['campaign_id']);
            $result = $api->cadastrarAssinante($campaignId, $data);
        break;

        case 'criar_campanha':
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $api->criarCampanha($data);
        break;

        case 'editar_campanha':
            $data = json_decode(file_get_contents('php://input'), true);
            $campaignId = $_GET['id'] ?? null;
            if (!$campaignId) throw new Exception('Campaign ID não informado');
            $result = $api->editarCampanha($campaignId, $data);
        break;

        case 'iniciar_campanha':
            $campaignId = $_GET['id'] ?? null;
            if (!$campaignId) throw new Exception('Campaign ID não informado');
            $result = $api->iniciarCampanha($campaignId);
        break;

        case 'parar_campanha':
            $campaignId = $_GET['id'] ?? null;
            if (!$campaignId) throw new Exception('Campaign ID não informado');
            $result = $api->pararCampanha($campaignId);
        break;

        case 'limpar_campanha':
            $campaignId = $_GET['id'] ?? null;
            if (!$campaignId) throw new Exception('Campaign ID não informado');
            $result = $api->limparCampanha($campaignId);
        break;

        /* ============================================================
           ================= FEATURE PLAN =================
        ============================================================ */

        case 'listar_feature_plans':
            $result = $api->getFeaturePlans();
        break;

        /* ============================================================
           ================= FILAS =================
        ============================================================ */

        case 'listar_filas':
            $params = [
                'include_archived' => $_GET['include_archived'] ?? 'true'
            ];
            $result = $api->listarFilas($params);
        break;

        case 'criar_fila':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $payload = [
                'name'          => $data['name'] ?? '',
                'number'        => $data['number'] ?? '',
                'slug'          => $data['slug'] ?? '',
                'public_number' => $data['public_number'] ?? ''
            ];
            $result = $api->criarFila($payload);
        break;

        case 'consultar_fila':
            $queueId = $_GET['id'] ?? null;
            if (!$queueId) throw new Exception('Queue ID não informado');
            $params = ['include_archived' => 'true'];
            $result = $api->consultarFila($queueId, $params);
        break;

        case 'editar_fila':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $queueId = $data['id'] ?? null;
            if (!$queueId) throw new Exception('Queue ID não informado');
            unset($data['id']);
            $result = $api->editarFila($queueId, $data);
        break;

        case 'arquivar_fila':
            $queueId = $_GET['id'] ?? null;
            if (!$queueId) throw new Exception('Queue ID não informado');
            $result = $api->arquivarFila($queueId);
        break;

        case 'listar_pausas_fila':
            $queueId = $_GET['id'] ?? null;
            if (!$queueId) throw new Exception('Queue ID não informado');
            $params = [
                'limit' => $_GET['limit'] ?? 50,
                'page'  => $_GET['page'] ?? 1
            ];
            $result = $api->listarPausasFila($queueId, $params);
        break;

        /* ============================================================
           ================= PBX - RAMAIS =================
        ============================================================ */

        case 'listar_ramais':
            $params = [
                'limit' => $_GET['limit'] ?? 100,
                'page'  => $_GET['page'] ?? 1
            ];
            $result = $api->listarRamais($params);
        break;

       /* ============================================================
   ================= REALTIME (GERAL) =================
============================================================ */

case 'realtime_status':
    $result = $api->getRealtimeStatus();
break;

/*
 * OBS:
 * realtime_agentes, realtime_chamadas e realtime_canais
 * foram removidos pois não existem na API realtime v1
 * e estavam causando erro 500.
 */

case 'realtime_filas':
    $result = $api->getRealtimeFilas();
break;

case 'realtime_campanha':
    $campaignId = $_GET['id'] ?? null;

    if (!$campaignId) {
        throw new Exception('ID da campanha não informado');
    }

    $result = $api->realtimeCampanha($campaignId);
break;

case 'realtime_fila':
    $queueId = $_GET['id'] ?? null;

    if (!$queueId) {
        throw new Exception('ID da fila não informado');
    }

    $result = $api->realtimeFila($queueId);
break;

default:
    http_response_code(404);
    $result = [
        'success' => false,
        'meta' => [
            'status' => 404,
            'message' => 'Ação não encontrada'
        ],
        'error' => "Ação '{$action}' não implementada"
    ];
}

/* ============================================================
   PÓS-PROCESSAMENTO PADRÃO
============================================================ */

// Se a API devolveu erro, repassa o status HTTP
if (!empty($result['code']) && $result['code'] >= 400) {
    http_response_code($result['code']);
}

// Debug opcional
if ($debug) {
    $result['_debug'] = [
        'action' => $action,
        'method' => $method,
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_UNESCAPED_UNICODE);
exit;

} catch (Throwable $e) {

    http_response_code(500);

    $response = [
        'success' => false,
        'meta' => [
            'status'  => 500,
            'message' => 'Erro interno do servidor'
        ],
        'error' => $e->getMessage()
    ];

    if ($debug) {
        $response['trace'] = $e->getTraceAsString();
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}
