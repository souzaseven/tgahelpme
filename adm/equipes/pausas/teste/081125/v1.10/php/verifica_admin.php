<?php
// php/verifica_admin.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once 'conexao.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $senha = trim($input['senha'] ?? '');

    if ($senha === '') {
        echo json_encode(['sucesso' => false, 'erro' => 'Senha não informada.']);
        exit;
    }

    // Busca o usuário admin fixo: id=6 e nome 'anderson'
    $stmt = $pdo->prepare("SELECT id, nome, senha FROM usuarios WHERE id = 6 AND LOWER(nome) = 'anderson' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['sucesso' => false, 'erro' => 'Usuário admin não encontrado.']);
        exit;
    }

    $senhaBanco = $row['senha'] ?? '';

    // Se a senha armazenada parece hash (tamanho típico >= 50), usa password_verify; senão, compara texto
    $ok = (strlen($senhaBanco) >= 50) ? password_verify($senha, $senhaBanco)
                                      : hash_equals($senhaBanco, $senha);

    if ($ok) {
        session_regenerate_id(true);
        $_SESSION['admin_autenticado'] = true;
        $_SESSION['admin_user'] = 'anderson';
        $_SESSION['login_time'] = time();

        echo json_encode(['sucesso' => true]);
    } else {
        echo json_encode(['sucesso' => false, 'erro' => 'Senha incorreta.']);
    }

} catch (Throwable $e) {
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno.']);
}
