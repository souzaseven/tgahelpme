<?php
require_once __DIR__ . '/../includes/session.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autenticado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo json_encode(['erro' => 'Acesso restrito a administradores.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../backend/conexao.php';

$acao   = $_GET['acao'] ?? '';
$metodo = $_SERVER['REQUEST_METHOD'];

// ──────────────────────────────────────────────
// GET listar
// ──────────────────────────────────────────────
if ($metodo === 'GET' && $acao === 'listar') {
    try {
        $rows = $pdo->query(
            "SELECT id, nome, email, telefone, sexo, is_admin, admweb,
                    editafila, edita_fila_telefone, ligacao_evolux, editaticket,
                    data_cadastro
             FROM usuarios
             ORDER BY nome"
        )->fetchAll();
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ──────────────────────────────────────────────
// POST criar
// ──────────────────────────────────────────────
if ($metodo === 'POST' && $acao === 'criar') {
    $nome  = trim($body['nome']  ?? '');
    $email = trim($body['email'] ?? '');
    $senha = trim($body['senha'] ?? '');

    if ($nome === '') {
        http_response_code(400);
        echo json_encode(['erro' => 'O campo Nome é obrigatório.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($senha === '') {
        http_response_code(400);
        echo json_encode(['erro' => 'A senha é obrigatória para novos usuários.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Verifica nome duplicado
    $dup = $pdo->prepare("SELECT id FROM usuarios WHERE nome = :nome LIMIT 1");
    $dup->execute([':nome' => $nome]);
    if ($dup->fetch()) {
        http_response_code(400);
        echo json_encode(['erro' => "Já existe um usuário com o nome \"$nome\"."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $pdo->prepare("
            INSERT INTO usuarios
                (nome, email, telefone, sexo, senha,
                 editaticket, is_admin, admweb, editafila, edita_fila_telefone, ligacao_evolux,
                 data_cadastro)
            VALUES
                (:nome, :email, :telefone, :sexo, :senha,
                 :editaticket, :is_admin, :admweb, :editafila, :edita_fila_telefone, :ligacao_evolux,
                 NOW())
        ")->execute([
            ':nome'               => $nome,
            ':email'              => $email,
            ':telefone'           => trim($body['telefone'] ?? ''),
            ':sexo'               => in_array($body['sexo'] ?? '', ['M','F']) ? $body['sexo'] : null,
            ':senha'              => password_hash($senha, PASSWORD_BCRYPT),
            ':editaticket'        => (int) !empty($body['editaticket']),
            ':is_admin'           => (int) !empty($body['is_admin']),
            ':admweb'             => (int) !empty($body['admweb']),
            ':editafila'          => (int) !empty($body['editafila']),
            ':edita_fila_telefone'=> (int) !empty($body['edita_fila_telefone']),
            ':ligacao_evolux'     => (int) !empty($body['ligacao_evolux']),
        ]);
        echo json_encode(['success' => true, 'mensagem' => 'Usuário criado com sucesso.', 'id' => (int) $pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ──────────────────────────────────────────────
// POST salvar (editar usuário existente)
// ──────────────────────────────────────────────
if ($metodo === 'POST' && $acao === 'salvar') {
    $id = (int) ($body['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['erro' => 'ID inválido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $nome = trim($body['nome'] ?? '');
    if ($nome === '') {
        http_response_code(400);
        echo json_encode(['erro' => 'O campo Nome é obrigatório.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Impede o admin de remover seu próprio acesso
    if ($id === (int) $_SESSION['usuario_id'] && empty($body['editaticket'])) {
        http_response_code(400);
        echo json_encode(['erro' => 'Você não pode remover seu próprio acesso.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Verifica nome duplicado em outro usuário
    $dup = $pdo->prepare("SELECT id FROM usuarios WHERE nome = :nome AND id != :id LIMIT 1");
    $dup->execute([':nome' => $nome, ':id' => $id]);
    if ($dup->fetch()) {
        http_response_code(400);
        echo json_encode(['erro' => "Já existe outro usuário com o nome \"$nome\"."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $campos = [
        'nome'               => $nome,
        'email'              => trim($body['email']    ?? ''),
        'telefone'           => trim($body['telefone'] ?? ''),
        'sexo'               => in_array($body['sexo'] ?? '', ['M','F']) ? $body['sexo'] : null,
        'editaticket'        => (int) !empty($body['editaticket']),
        'is_admin'           => (int) !empty($body['is_admin']),
        'admweb'             => (int) !empty($body['admweb']),
        'editafila'          => (int) !empty($body['editafila']),
        'edita_fila_telefone'=> (int) !empty($body['edita_fila_telefone']),
        'ligacao_evolux'     => (int) !empty($body['ligacao_evolux']),
    ];

    $novaSenha = trim($body['nova_senha'] ?? '');
    if ($novaSenha !== '') {
        $campos['senha'] = password_hash($novaSenha, PASSWORD_BCRYPT);
    }

    $sets   = implode(', ', array_map(fn($c) => "`$c` = :$c", array_keys($campos)));
    $params = array_merge($campos, ['id' => $id]);

    try {
        $pdo->prepare("UPDATE usuarios SET $sets WHERE id = :id")->execute($params);
        echo json_encode(['success' => true, 'mensagem' => 'Usuário atualizado com sucesso.'], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ──────────────────────────────────────────────
// POST excluir
// ──────────────────────────────────────────────
if ($metodo === 'POST' && $acao === 'excluir') {
    $id = (int) ($body['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['erro' => 'ID inválido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($id === (int) $_SESSION['usuario_id']) {
        http_response_code(400);
        echo json_encode(['erro' => 'Você não pode excluir seu próprio usuário.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT id, nome, is_admin, admweb
         FROM usuarios
         WHERE id = :id
         LIMIT 1"
    );
    $stmt->execute([':id' => $id]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        http_response_code(404);
        echo json_encode(['erro' => 'Usuário não encontrado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ((int) $usuario['is_admin'] === 1 || (int) $usuario['admweb'] === 1) {
        http_response_code(400);
        echo json_encode(['erro' => 'Usuário administrador não pode ser excluído.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $pdo->prepare("DELETE FROM usuarios WHERE id = :id")->execute([':id' => $id]);
        echo json_encode([
            'success' => true,
            'mensagem' => 'Usuário excluído com sucesso.',
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

http_response_code(400);
echo json_encode(['erro' => 'Ação inválida.'], JSON_UNESCAPED_UNICODE);
