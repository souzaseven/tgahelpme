<?php
// =============================================================
// login_operador.php (v1.0)
// Autenticação de operadores e admin (Anderson)
// =============================================================
header('Content-Type: application/json; charset=utf-8');
require_once 'conexao.php';
session_start();

$nome = trim($_POST['nome'] ?? '');
$senha = trim($_POST['senha'] ?? '');

if ($nome === '') {
    echo json_encode(['success' => false, 'error' => 'Informe o nome.']);
    exit;
}

try {
    // Caso seja o admin "anderson"
    if (strtolower($nome) === 'anderson') {
        $stmt = $pdo->prepare("SELECT id, nome, senha FROM usuarios WHERE id = 6 AND LOWER(nome) = 'anderson' LIMIT 1");
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || $user['senha'] !== $senha) {
            echo json_encode(['success' => false, 'error' => 'Senha incorreta.']);
            exit;
        }

        $_SESSION['admin_autenticado'] = true;
        $_SESSION['admin_user'] = 'anderson';
        $_SESSION['operador_nome'] = 'Anderson';
        echo json_encode(['success' => true, 'admin' => true, 'msg' => 'Login de administrador realizado.']);
        exit;
    }

    // Operadores comuns
    $stmt = $pdo->prepare("SELECT nome FROM tgamea80_SUPORTE.operadores WHERE LOWER(nome) LIKE :n LIMIT 1");
    $stmt->execute([':n' => '%' . strtolower($nome) . '%']);
    $op = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$op) {
        echo json_encode(['success' => false, 'error' => 'Operador não encontrado.']);
        exit;
    }

    $_SESSION['operador_nome'] = $op['nome'];
    $_SESSION['admin_autenticado'] = false;

    echo json_encode(['success' => true, 'admin' => false, 'msg' => "Bem-vindo, {$op['nome']}!"]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
?>
