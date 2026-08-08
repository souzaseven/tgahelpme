<?php
require_once __DIR__ . '/../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['usuario_id']) || empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo json_encode(['erro' => 'Acesso restrito a administradores.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../backend/conexao.php';

// Busca tickets criados nos últimos 10 minutos (ajuste conforme necessário)
$minutos = 10;
$agora = date('Y-m-d H:i:s');
$inicio = date('Y-m-d H:i:s', strtotime("-$minutos minutes"));

$sql = "SELECT id, operador_id, nome_operador, lider, semana, alterado_em FROM repasse_historico WHERE alterado_em >= :inicio ORDER BY alterado_em DESC LIMIT 20";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':inicio', $inicio);
$stmt->execute();

$novos = $stmt->fetchAll();

echo json_encode(['novos' => $novos], JSON_UNESCAPED_UNICODE);
