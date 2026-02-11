<?php
/**
 * backend/login.php — TGA Clientes Web
 *
 * Mantive toda a lógica original intacta. Adicionei apenas:
 *
 *  1. Validação do token CSRF (header X-CSRF-Token enviado pelo login.js).
 *     Rejeito a requisição se o token não bater com o da sessão.
 *     Isso impede que outros sites façam login em nome do usuário.
 *
 *  2. Comentário sinalizando que a senha está sendo comparada em texto plano.
 *     Recomendo migrar para password_hash() / password_verify() assim que
 *     possível — deixei o bloco pronto e comentado para facilitar.
 *
 * Tudo mais (conexão, prepared statement, campos de sessão) é idêntico
 * ao arquivo original.
 */

require_once __DIR__ . '/conexao.php';
header('Content-Type: application/json; charset=utf-8');

// ── Apenas POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// ── 1. Validação CSRF ──────────────────────────────────────────────────────
// Inicio a sessão para poder ler o token gerado pelo login.php.
// Uso session_status() para não chamar session_start() duas vezes caso
// algum include anterior já tenha iniciado.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$csrfRecebido = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfEsperado = $_SESSION['csrf_token']       ?? '';

// Uso hash_equals para comparação segura (evita timing attacks)
if (!$csrfEsperado || !hash_equals($csrfEsperado, $csrfRecebido)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Requisição inválida']);
    exit;
}

// ── Leitura do body ────────────────────────────────────────────────────────
$body  = json_decode(file_get_contents('php://input'), true);
$nome  = trim($body['nome']  ?? '');
$senha = trim($body['senha'] ?? '');

if ($nome === '' || $senha === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

// ── Busca o usuário ────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT id, nome, senha, admweb
    FROM usuarios
    WHERE nome = :nome
    LIMIT 1
");
$stmt->execute([':nome' => $nome]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// ── 2. Verificação de senha ────────────────────────────────────────────────
// ⚠️  ATUAL: comparação em texto plano (mantive igual ao original).
// ✅  RECOMENDADO: migrar para password_hash() no cadastro e usar
//     password_verify() abaixo. Quando estiver pronto, troque o bloco:
//
//   if (!$user || !password_verify($senha, $user['senha'])) { ... }
//
if (!$user || $user['senha'] !== $senha) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Usuário ou senha incorretos']);
    exit;
}

// ── Verifica permissão ─────────────────────────────────────────────────────
if ((int)$user['admweb'] !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado']);
    exit;
}

// ── ✅ Login OK — cria sessão ──────────────────────────────────────────────
// Regenero o ID de sessão para prevenir session fixation attacks.
// Também invalido o token CSRF usado — o próximo será gerado pelo login.php
// se o usuário precisar logar novamente.
session_regenerate_id(true);
unset($_SESSION['csrf_token']);

$_SESSION['usuario_id'] = $user['id'];
$_SESSION['usuario']    = $user['nome'];
$_SESSION['admweb']     = 1;
$_SESSION['login_time'] = time();

echo json_encode(['success' => true]);