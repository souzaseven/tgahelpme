<?php
session_start();
require_once __DIR__ . '/conexao.php';

header('Content-Type: application/json');

if (empty($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

/* CSRF */
if (
    empty($_POST['csrf_token']) ||
    $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')
) {
    echo json_encode(['success' => false, 'message' => 'Token inválido.']);
    exit;
}

/* Permissão */
$isAdmin = !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

$stmtUser = $pdo->prepare("SELECT tipo FROM usuarios_carreiras WHERE id = ?");
$stmtUser->execute([$_SESSION['usuario_id']]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$isAdmin && $user['tipo'] !== 'administrador') {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

/* Buscar anexos para deletar do servidor */
$stmt = $pdo->prepare("SELECT anexos FROM sugestoes_tgacarreiras WHERE id = ?");
$stmt->execute([$id]);
$sug = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sug) {
    echo json_encode(['success' => false, 'message' => 'Registro não encontrado.']);
    exit;
}

if (!empty($sug['anexos'])) {
    $anexos = json_decode($sug['anexos'], true);
    if (is_array($anexos)) {
        foreach ($anexos as $arquivo) {
            $path = __DIR__ . '/../uploads/anexos/' . basename($arquivo);
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}

/* Excluir do banco */
$del = $pdo->prepare("DELETE FROM sugestoes_tgacarreiras WHERE id = ?");
$del->execute([$id]);

echo json_encode(['success' => true]);
