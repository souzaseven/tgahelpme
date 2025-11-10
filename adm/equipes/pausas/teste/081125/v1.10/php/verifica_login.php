<?php
// =============================================
// verifica_login.php (v1.1 seguro)
// =============================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'conexao.php';

$input = json_decode(file_get_contents('php://input'), true);
$nome = trim($input['nome'] ?? '');
$senha = trim($input['senha'] ?? '');

// Verificações básicas
if ($nome === '') {
    echo json_encode(['success' => false, 'error' => 'Nome não informado.']);
    exit;
}

try {
    // ===============================
    // 1. Verifica se o nome existe na tabela operadores
    // ===============================
    $stmt = $pdo->prepare("SELECT nome FROM operadores WHERE nome = :nome LIMIT 1");
    $stmt->execute([':nome' => $nome]);
    $operador = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$operador) {
        echo json_encode(['success' => false, 'error' => 'Usuário não encontrado na lista de operadores.']);
        exit;
    }

    // ===============================
    // 2. Caso seja 'anderson' (minúsculo) → valida senha como admin
    // ===============================
    if ($nome === 'anderson') {
        if ($senha === '') {
            echo json_encode(['success' => false, 'error' => 'Senha obrigatória para administrador.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, nome, senha, senha_hash FROM usuarios WHERE id = 6 LIMIT 1");
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            echo json_encode(['success' => false, 'error' => 'Administrador não encontrado.']);
            exit;
        }

        $dbSenha = $usuario['senha'] ?? null;
        $dbHash = $usuario['senha_hash'] ?? null;

        $senhaValida = false;
        if ($dbHash) {
            $senhaValida = password_verify($senha, $dbHash);
        } elseif ($dbSenha) {
            $senhaValida = hash_equals($dbSenha, $senha);
        }

        if (!$senhaValida) {
            echo json_encode(['success' => false, 'error' => 'Senha incorreta.']);
            exit;
        }

        echo json_encode(['success' => true, 'tipo' => 'admin']);
        exit;
    }

    // ===============================
    // 3. Caso seja 'Anderson' (A maiúsculo) → operador comum
    // ===============================
    if ($nome === 'Anderson') {
        echo json_encode(['success' => true, 'tipo' => 'operador']);
        exit;
    }

    // ===============================
    // 4. Qualquer outro nome válido da tabela → operador comum
    // ===============================
    echo json_encode(['success' => true, 'tipo' => 'operador']);
    exit;

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
