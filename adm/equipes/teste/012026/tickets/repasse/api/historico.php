<?php
require_once __DIR__ . '/../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autenticado.'], JSON_UNESCAPED_UNICODE);
    exit;
}



// Permite admin ver tudo, líder vê alterações de líderes, mas não de admin
$isAdmin = !empty($_SESSION['is_admin']);
$nomeUsuario = $_SESSION['nome'] ?? '';

if (!$isAdmin && empty($nomeUsuario)) {
    http_response_code(403);
    echo json_encode(['erro' => 'Acesso restrito.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Lista de nomes de admin para filtrar (ajuste conforme necessário)
$nomesAdmin = ['admin', 'Administrador', 'root', 'anderson'];
if ($isAdmin && isset($_SESSION['nome']) && !in_array($_SESSION['nome'], $nomesAdmin, true)) {
    $nomesAdmin[] = $_SESSION['nome'];
}

require_once __DIR__ . '/../../backend/conexao.php';


$hoje = new DateTimeImmutable();
$diaSemana = (int) $hoje->format('w'); // 0=domingo, 6=sábado
$inicioSemana = $hoje->modify('-' . $diaSemana . ' days');
$fimSemana = $inicioSemana->modify('+6 days');
$de = $_GET['de'] ?? $inicioSemana->format('Y-m-d');
$ate = $_GET['ate'] ?? $fimSemana->format('Y-m-d');
$limit = min(500, max(1, (int) ($_GET['limit'] ?? 200)));
$ordem = $_GET['ordem'] ?? 'alterado_em';
$direcao = strtolower($_GET['direcao'] ?? 'desc');
$alterador = $_GET['alterador'] ?? '';
$operador = $_GET['operador'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $de) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Datas inválidas.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo->query("SELECT 1 FROM repasse_historico LIMIT 1");
} catch (PDOException $e) {
    echo json_encode(['registros' => []], JSON_UNESCAPED_UNICODE);
    exit;
}




$sql = "SELECT id, operador_id, nome_operador, lider, semana,
           seg, ter, qua, qui, sex, sab,
           total_novo, total_anterior,
           alterado_por, nome_alterador, alterado_em
    FROM repasse_historico
    WHERE DATE(alterado_em) BETWEEN :de AND :ate";

if (!$isAdmin) {
    // Não mostrar alterações feitas por admin
    $adminPlaceholders = [];
    foreach ($nomesAdmin as $idx => $nomeAdm) {
        $adminPlaceholders[] = ":admin_nome_$idx";
    }
    $sql .= " AND nome_alterador NOT IN (" . implode(',', $adminPlaceholders) . ")";
    if ($alterador !== '') {
        $sql .= " AND nome_alterador = :alterador";
    }
} else {
    if ($alterador !== '') {
        $sql .= " AND nome_alterador = :alterador";
    }
}
if ($operador !== '') {
    $sql .= " AND nome_operador LIKE :operador";
}
$sql .= " ORDER BY $ordem $direcao LIMIT :limit";



$stmt = $pdo->prepare($sql);
$stmt->bindValue(':de', $de);
$stmt->bindValue(':ate', $ate);
// Se não for admin, faz o bind dos nomes de admin para o NOT IN
if (!$isAdmin) {
    foreach ($nomesAdmin as $idx => $nomeAdm) {
        $stmt->bindValue(":admin_nome_$idx", $nomeAdm);
    }
    if ($alterador !== '') {
        $stmt->bindValue(':alterador', $alterador);
    }
} else {
    if ($alterador !== '') {
        $stmt->bindValue(':alterador', $alterador);
    }
}
if ($operador !== '') {
    $stmt->bindValue(':operador', "%$operador%", PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();

echo json_encode(['registros' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
