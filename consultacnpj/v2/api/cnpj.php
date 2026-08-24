<?php
/**
 * api/cnpj.php
 * Proxy PHP para a BrasilAPI (endpoint de CNPJ) — alternativa ao server.js
 * (Node/Express) para rodar em hospedagem compartilhada comum (ex.: HostGator),
 * sem precisar de "Setup Node.js App" nem de processo próprio.
 *
 * Uso: GET /api/cnpj.php?cnpj=45179602000196
 *
 * Por quê: consultar https://brasilapi.com.br diretamente do navegador faz
 * cada visitante bater na API pública com o próprio IP. Quando a BrasilAPI
 * responde 429 (limite excedido), a resposta de erro não traz o header
 * Access-Control-Allow-Origin — o navegador bloqueia a leitura por CORS e
 * mascara o problema real (rate limit) como se fosse falha de CORS.
 *
 * Passando por este proxy, o navegador só fala com a própria origem (sem
 * CORS), e um cache em arquivo (pasta api/cache/) reduz o número de idas
 * à BrasilAPI quando o mesmo CNPJ é consultado de novo em pouco tempo.
 *
 * Documentação oficial usada como base (nenhum endpoint inventado):
 *   GET https://brasilapi.com.br/api/cnpj/v1/{cnpj}
 */

header('Content-Type: application/json; charset=utf-8');

const BRASILAPI_BASE_URL = 'https://brasilapi.com.br/api/cnpj/v1';
const REQUEST_TIMEOUT_S = 15;
const CACHE_DIR = __DIR__ . '/cache';
const CACHE_TTL_S = 10 * 60; // 10 minutos — mesmo TTL do cache local do frontend

function respondError(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

$cnpj = preg_replace('/\D/', '', $_GET['cnpj'] ?? '');

if (strlen($cnpj) !== 14) {
    respondError(400, 'CNPJ inválido.');
}

// ---------- CACHE EM ARQUIVO ----------

if (!is_dir(CACHE_DIR)) {
    @mkdir(CACHE_DIR, 0755, true);
}

$cacheFile = CACHE_DIR . '/' . $cnpj . '.json';

if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < CACHE_TTL_S) {
    readfile($cacheFile);
    exit;
}

// ---------- CHAMADA À BRASILAPI ----------

$ch = curl_init(BRASILAPI_BASE_URL . '/' . $cnpj);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => REQUEST_TIMEOUT_S,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        // Sem um User-Agent "de navegador", a Cloudflare na frente da
        // BrasilAPI pode bloquear a chamada — o valor em si não importa,
        // só precisa parecer tráfego de navegador.
        'User-Agent: Mozilla/5.0 (compatible; ConsultaCNPJ-Proxy/1.0; +https://tgameajuda.com)',
    ],
]);

$body = curl_exec($ch);
$curlErrno = curl_errno($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlErrno === CURLE_OPERATION_TIMEDOUT) {
    respondError(504, 'A consulta à BrasilAPI demorou demais para responder.');
}

if ($curlErrno !== 0) {
    respondError(502, 'Não foi possível consultar o CNPJ neste momento.');
}

if ($httpStatus === 404) {
    respondError(404, 'Empresa não encontrada.');
}

if ($httpStatus === 400) {
    respondError(400, 'CNPJ inválido.');
}

if ($httpStatus === 429) {
    respondError(429, 'Limite de consultas à BrasilAPI foi atingido. Tente novamente em instantes.');
}

if ($httpStatus < 200 || $httpStatus >= 300) {
    respondError(502, 'A BrasilAPI está indisponível no momento.');
}

// Resposta válida: grava no cache e devolve como veio (já é JSON).
@file_put_contents($cacheFile, $body);
echo $body;
