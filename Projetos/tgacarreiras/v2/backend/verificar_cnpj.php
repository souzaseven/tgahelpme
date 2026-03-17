<?php
/**
 * API — Verificar CNPJ duplicado no banco
 * Endpoint: POST /backend/verificar_cnpj.php
 * Body JSON: { "cnpj": "00000000000000" }
 * Retorno:  { "disponivel": true/false, "mensagem": "..." }
 * 
 * Compatível com conexão mysqli ($conexao) existente no projeto
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
    exit;
}

// Rate limit simples por IP (máx 10 consultas por minuto)
session_start();
$ip = $_SERVER['REMOTE_ADDR'];
$rateKey = 'cnpj_rate_' . md5($ip);

if (!isset($_SESSION[$rateKey])) {
    $_SESSION[$rateKey] = ['count' => 0, 'reset' => time() + 60];
}

if (time() > $_SESSION[$rateKey]['reset']) {
    $_SESSION[$rateKey] = ['count' => 0, 'reset' => time() + 60];
}

$_SESSION[$rateKey]['count']++;

if ($_SESSION[$rateKey]['count'] > 10) {
    http_response_code(429);
    echo json_encode(['erro' => 'Muitas tentativas. Aguarde 1 minuto.']);
    exit;
}

// Ler JSON do body
$input = json_decode(file_get_contents('php://input'), true);
$cnpj = preg_replace('/\D/', '', $input['cnpj'] ?? '');

// Validar formato (14 dígitos)
if (strlen($cnpj) !== 14) {
    http_response_code(400);
    echo json_encode(['erro' => 'CNPJ inválido. Informe 14 dígitos.']);
    exit;
}

// Validação matemática do CNPJ
function validarCNPJ(string $cnpj): bool {
    if (preg_match('/^(\d)\1{13}$/', $cnpj)) return false;

    $pesos1 = [5,4,3,2,9,8,7,6,5,4,3,2];
    $soma = 0;
    for ($i = 0; $i < 12; $i++) $soma += (int)$cnpj[$i] * $pesos1[$i];
    $resto = $soma % 11;
    $d1 = ($resto < 2) ? 0 : 11 - $resto;
    if ((int)$cnpj[12] !== $d1) return false;

    $pesos2 = [6,5,4,3,2,9,8,7,6,5,4,3,2];
    $soma = 0;
    for ($i = 0; $i < 13; $i++) $soma += (int)$cnpj[$i] * $pesos2[$i];
    $resto = $soma % 11;
    $d2 = ($resto < 2) ? 0 : 11 - $resto;
    return (int)$cnpj[13] === $d2;
}

if (!validarCNPJ($cnpj)) {
    http_response_code(400);
    echo json_encode([
        'erro'       => 'CNPJ inválido (dígitos verificadores incorretos).',
        'disponivel' => false
    ]);
    exit;
}

// ── Conexão com o banco ─────────────────────────────────────
try {
    require_once __DIR__ . '/conexao.php';

    // ── mysqli ($conexao) ───────────────────────────────────
    if (isset($conexao) && $conexao instanceof mysqli) {

        $stmt = $conexao->prepare("SELECT id, nome_empresa FROM empresas_carreiras WHERE cnpj = ? LIMIT 1");

        if (!$stmt) {
            throw new Exception('Erro ao preparar consulta: ' . $conexao->error);
        }

        $stmt->bind_param('s', $cnpj);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $empresa = $resultado->fetch_assoc();
        $stmt->close();

    // ── Fallback PDO ($pdo) ─────────────────────────────────
    } elseif (isset($pdo) && $pdo instanceof PDO) {

        $stmt = $pdo->prepare("SELECT id, nome_empresa FROM empresas_carreiras WHERE cnpj = :cnpj LIMIT 1");
        $stmt->execute([':cnpj' => $cnpj]);
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    } else {
        throw new Exception('Nenhuma conexão válida encontrada ($conexao ou $pdo).');
    }

    // ── Retorno ─────────────────────────────────────────────
    if ($empresa) {
        echo json_encode([
            'disponivel' => false,
            'mensagem'   => 'Este CNPJ já está cadastrado na plataforma.',
            'empresa'    => $empresa['nome_empresa'] ?? null
        ]);
    } else {
        echo json_encode([
            'disponivel' => true,
            'mensagem'   => 'CNPJ disponível para cadastro.'
        ]);
    }

} catch (Exception $e) {
    error_log('[verificar_cnpj] Erro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro interno ao verificar CNPJ.']);
}