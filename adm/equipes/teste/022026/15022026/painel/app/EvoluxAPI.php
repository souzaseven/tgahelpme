<?php

class EvoluxAPI {

    private $baseUrl;
    private $token;
    private $timeout;

    public function __construct() {
        $config = require __DIR__ . '/../config/api.php';

        $this->baseUrl = rtrim($config['base_url'], '/');
        $this->token   = $config['token'];
        $this->timeout = $config['timeout'] ?? 30;
    }

    /**
     * ===============================
     * MÉTODO CENTRAL DE REQUISIÇÃO
     * - Agora suporta GET com querystring via $data
     * ===============================
     */
    private function request($method, $endpoint, $data = null) {

        // GET com parâmetros -> vira querystring
        if ($method === 'GET' && is_array($data) && !empty($data)) {
            $qs = http_build_query($data);
            $endpoint .= (str_contains($endpoint, '?') ? '&' : '?') . $qs;
        }

        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();

        $headers = [
            'token: ' . $this->token,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        // Métodos com body
        if (in_array($method, ['POST','PUT','PATCH'])) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
            }
        }

        if ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        // ===============================
        // ERRO CURL
        // ===============================
        if ($curlError) {
            return [
                'success' => false,
                'code'    => 0,
                'error'   => $curlError,
                'meta'    => [
                    'status'  => 0,
                    'message' => $curlError
                ],
                'data'       => [],
                'pagination' => null
            ];
        }

        // ===============================
        // RESPOSTA VAZIA (ex: 204/empty)
        // ===============================
        if ($response === false || $response === '' || $response === null) {
            $success = ($httpCode >= 200 && $httpCode < 300);
            return [
                'success' => $success,
                'code'    => $httpCode,
                'meta'    => [
                    'status'  => $httpCode,
                    'message' => $success ? 'OK (sem conteúdo)' : 'Resposta vazia da API'
                ],
                'data'       => [],
                'pagination' => null,
                'error'      => $success ? null : 'Resposta vazia da API'
            ];
        }

        $decoded = json_decode($response, true);

        // ===============================
        // JSON INVÁLIDO
        // ===============================
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'code'    => $httpCode,
                'error'   => 'Resposta inválida (não é JSON)',
                'meta'    => [
                    'status'  => $httpCode,
                    'message' => 'Resposta inválida da API'
                ],
                'data'       => [],
                'pagination' => null,
                'raw'        => substr($response, 0, 1000)
            ];
        }

        // ===============================
        // RETORNO PADRONIZADO
        // ===============================
        $success = $httpCode >= 200 && $httpCode < 300;

        return [
            'success' => $success,
            'code'    => $httpCode,
            'meta'    => $decoded['meta'] ?? [
                'status'  => $httpCode,
                'message' => $success ? 'OK' : 'Erro na API'
            ],
            'data'       => $decoded['data'] ?? [],
            'pagination' => $decoded['pagination'] ?? null,
            'error'      => !$success ? ($decoded['meta']['message'] ?? 'Erro na API') : null
        ];
    }

    /* ============================================================
       ========== AGENTES (API v1 REAL EVOLUX) ====================
    ============================================================ */

    public function getAgentes($filtros = []) {
        $limit = isset($filtros['limit']) ? (int)$filtros['limit'] : 50;
        $page  = isset($filtros['page']) ? (int)$filtros['page'] : 1;

        if ($limit <= 0) $limit = 50;
        if ($page  <= 0) $page  = 1;

        $offset = ($page - 1) * $limit;

        $query = http_build_query([
            'limit'  => $limit,
            'offset' => $offset
        ]);

        return $this->request('GET', '/api/v1/agents?' . $query);
    }

    public function getAgente($id) {
        return $this->request('GET', "/api/v1/agents/{$id}");
    }

    public function createAgente($data) {
        return $this->request('POST', '/api/v1/agents', $data);
    }

    public function updateAgente($id, $data) {
        return $this->request('PUT', "/api/v1/agents/{$id}", $data);
    }

    public function deleteAgente($id) {
        return $this->request('DELETE', "/api/v1/agents/{$id}");
    }

    public function getAgentePausas($id, $filtros = []) {
        $limit = isset($filtros['limit']) ? (int)$filtros['limit'] : 50;
        $page  = isset($filtros['page']) ? (int)$filtros['page'] : 1;

        if ($limit <= 0) $limit = 50;
        if ($page  <= 0) $page  = 1;

        $offset = ($page - 1) * $limit;

        $query = http_build_query([
            'limit'  => $limit,
            'offset' => $offset
        ]);

        return $this->request('GET', "/api/v1/agents/{$id}/pauses?" . $query);
    }

    public function pausarAgente($id, $pause_id) {
        return $this->request('POST', "/api/v1/agents/{$id}/pause", [
            'pause_id' => (int)$pause_id
        ]);
    }

    public function despausarAgente($id) {
        return $this->request('POST', "/api/v1/agents/{$id}/unpause");
    }

    public function deslogarAgente($id) {
        return $this->request('POST', "/api/v1/agents/{$id}/logoff");
    }

    /* ============================================================
       ========== FILAS (API v1 REAL EVOLUX) ======================
    ============================================================ */

    public function getFilas($filtros = []) {
        $limit = isset($filtros['limit']) ? (int)$filtros['limit'] : 100;
        if ($limit <= 0) $limit = 100;

        $query = http_build_query(['limit' => $limit]);

        return $this->request('GET', '/api/v1/queues?' . $query);
    }

    public function getFila($id) {
        return $this->request('GET', "/api/v1/queues/{$id}");
    }

    public function createFila($data) {
        return $this->request('POST', '/api/v1/queues', $data);
    }

    public function updateFila($id, $data) {
        return $this->request('PUT', "/api/v1/queues/{$id}", $data);
    }

    public function deleteFila($id) {
        return $this->request('DELETE', "/api/v1/queues/{$id}");
    }

    /* ============================================================
       ========== CALLCENTER ======================================
    ============================================================ */

    public function getCallById($callId) {
        return $this->request('GET', "/api/v1/callcenter/call/{$callId}");
    }

    public function downloadCallRecording($callId) {
        return $this->request('GET', "/api/v1/callcenter/call/{$callId}/recording");
    }

    public function baixarGravacaoRaw($callId) {
        $url = $this->baseUrl . "/api/v1/callcenter/call/{$callId}/recording";

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'token: ' . $this->token
            ],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $audio = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        curl_close($ch);

        return [
            'code' => $httpCode,
            'audio' => $audio,
            'content_type' => $contentType
        ];
    }

    /* ============================================================
       ========== CHAMADAS ========================================
    ============================================================ */

    public function transferirChamada($uuid, $destination, $leg) {
        return $this->request(
            'POST',
            "/api/v1/calls/{$uuid}/transfer",
            [
                'destination_number' => $destination,
                'leg' => $leg
            ]
        );
    }

    public function desligarChamada($uuid) {
        return $this->request('POST', "/api/v1/calls/{$uuid}/hangup?skip_survey=true");
    }

    public function originarChamadaAgente($fromLogin, $toNumber, $callInfo = []) {
        return $this->request(
            'POST',
            "/api/v1/call",
            [
                'from' => $fromLogin,
                'to' => $toNumber,
                'call_info' => json_encode($callInfo, JSON_UNESCAPED_UNICODE)
            ]
        );
    }

    public function originarChamadaExterna($fromNumber, $toNumber, $extensionGroup, $callInfo = []) {
        return $this->request(
            'POST',
            "/api/v1/call",
            [
                'from' => $fromNumber,
                'to' => $toNumber,
                'extension_group' => $extensionGroup,
                'call_info' => json_encode($callInfo, JSON_UNESCAPED_UNICODE)
            ]
        );
    }

    public function getCallInfo($uuid) {
        return $this->request('GET', "/api/v1/calls/{$uuid}/info");
    }

    /* ============================================================
       ========== CDR =============================================
    ============================================================ */

    public function getCDR($uuid) {
        return $this->request('GET', "/api/v1/cdr/{$uuid}");
    }

    /* ============================================================
       ========== DISCADOR ========================================
    ============================================================ */

    public function buscarAssinante($params = []) {
        $query = http_build_query($params);
        return $this->request('GET', "/api/v1/subscriber/search?" . $query);
    }

    public function cadastrarAssinante($campaignId, $data) {
        return $this->request('POST', "/api/v1/campaign/{$campaignId}/subscriber", $data);
    }

    public function removerAssinante($campaignId, $externalId) {
        return $this->request('DELETE', "/api/v1/remove_subscriber/{$campaignId}/subscriber/{$externalId}");
    }

    public function criarCampanha($data) {
        return $this->request('POST', '/api/v1/campaign', $data);
    }

    public function editarCampanha($campaignId, $data) {
        return $this->request('PUT', "/api/v1/campaign/{$campaignId}", $data);
    }

    public function iniciarCampanha($campaignId) {
        return $this->request('POST', "/api/v1/campaign/{$campaignId}/start");
    }

    public function pararCampanha($campaignId) {
        return $this->request('POST', "/api/v1/campaign/{$campaignId}/stop");
    }

    public function limparCampanha($campaignId) {
        return $this->request('POST', "/api/v1/campaign/{$campaignId}/subscribers/clear");
    }

    /* ============================================================
       ========== FEATURE PLAN ====================================
    ============================================================ */

    public function getFeaturePlans() {
        return $this->request('GET', '/api/v1/feature_plans');
    }

    /* ============================================================
       ================= FILAS (QUEUES) ===========================
    ============================================================ */

    public function listarFilas($params = []) {
        // GET com query agora funciona via request()
        return $this->request('GET', '/api/v1/queues', $params);
    }

    public function criarFila($data) {
        return $this->request('POST', '/api/v1/queues', $data);
    }

    public function consultarFila($queueId, $params = []) {
        return $this->request('GET', "/api/v1/queues/{$queueId}", $params);
    }

    public function editarFila($queueId, $data) {
        return $this->request('PUT', "/api/v1/queues/{$queueId}", $data);
    }

    public function arquivarFila($queueId) {
        return $this->request('DELETE', "/api/v1/queues/{$queueId}");
    }

    public function listarPausasFila($queueId, $params = []) {
        return $this->request('GET', "/api/v1/queues/{$queueId}/pauses", $params);
    }

    /* ============================================================
       ================= PBX - RAMAIS =============================
    ============================================================ */

    public function listarRamais($filtros = []) {
        $limit = isset($filtros['limit']) ? (int)$filtros['limit'] : 100;
        $page  = isset($filtros['page']) ? (int)$filtros['page'] : 1;

        if ($limit <= 0) $limit = 100;
        if ($page  <= 0) $page  = 1;

        $query = http_build_query([
            'limit' => $limit,
            'page'  => $page
        ]);

        return $this->request('GET', "/api/v1/extensions?{$query}");
    }

   /* ============================================================
   ================= REALTIME EVOLUX ==========================
   ============================================================ */

/**
 * Realtime - Uma campanha específica
 */
public function realtimeCampanha($campaignId)
{
    return $this->request(
        'GET',
        "/api/realtime/v1/campaign/{$campaignId}"
    );
}

/**
 * Realtime - Várias campanhas
 * $campaignIds pode ser string "1,2,3" ou array [1,2,3]
 */
public function realtimeCampanhas($campaignIds)
{
    if (is_array($campaignIds)) {
        $campaignIds = implode(',', $campaignIds);
    }

    return $this->request(
        'GET',
        "/api/realtime/v1/campaign",
        ['campaign_ids' => $campaignIds]
    );
}

/**
 * Realtime - Uma fila específica
 */
public function realtimeFila($queueId)
{
    return $this->request(
        'GET',
        "/api/realtime/v1/queue/{$queueId}"
    );
}

/**
 * Realtime - Várias filas
 * $queueIds pode ser string "1,2,3" ou array [1,2,3]
 */
public function realtimeFilas($queueIds)
{
    if (is_array($queueIds)) {
        $queueIds = implode(',', $queueIds);
    }

    return $this->request(
        'GET',
        "/api/realtime/v1/queue",
        ['queue_ids' => $queueIds]
    );
}

/**
 * Realtime - Status geral do sistema
 */
public function getRealtimeStatus()
{
    return $this->request(
        'GET',
        "/api/realtime/v1/status"
    );
}



}
