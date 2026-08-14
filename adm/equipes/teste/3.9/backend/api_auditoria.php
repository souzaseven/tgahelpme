<?php
require_once __DIR__ . '/conexao.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!validateCSRF()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF inválido']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $body['action'] ?? '';

function nt($v) { return trim((string)$v); }

try {

    /* =====================================================
       LIST — histórico completo paginado com filtros
    ===================================================== */
    if ($action === 'list') {

        $page  = max(1, (int)($body['page']  ?? 1));
        $limit = min(200, max(5, (int)($body['limit'] ?? 20)));

        $q       = nt($body['q']       ?? '');
        $modulo  = nt($body['modulo']  ?? '');
        $acao    = strtoupper(nt($body['acao'] ?? ''));
        $usuario = nt($body['usuario'] ?? '');
        $de      = nt($body['de']      ?? '');
        $ate     = nt($body['ate']     ?? '');

        $where  = [];
        $params = [];

        if ($q !== '') {
            $where[] = "(descricao LIKE ? OR usuario LIKE ? OR modulo LIKE ?)";
            $like = "%{$q}%";
            array_push($params, $like, $like, $like);
        }

        if ($modulo !== '') {
            $where[]  = "modulo = ?";
            $params[] = $modulo;
        }

        if (in_array($acao, ['CREATE', 'UPDATE', 'DELETE'], true)) {
            $where[]  = "acao = ?";
            $params[] = $acao;
        }

        if ($usuario !== '') {
            $where[]  = "usuario LIKE ?";
            $params[] = "%{$usuario}%";
        }

        if ($de !== '') {
            $where[]  = "DATE(criado_em) >= ?";
            $params[] = $de;
        }

        if ($ate !== '') {
            $where[]  = "DATE(criado_em) <= ?";
            $params[] = $ate;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset   = ($page - 1) * $limit;

        $stmtT = $pdo->prepare("SELECT COUNT(*) c FROM auditoria {$whereSql}");
        $stmtT->execute($params);
        $total = (int)$stmtT->fetch()['c'];
        $pages = max(1, (int)ceil($total / $limit));

        $stmt = $pdo->prepare("
            SELECT
                id,
                usuario,
                acao,
                modulo,
                registro_id,
                descricao,
                dados_antes,
                dados_depois,
                ip,
                DATE_FORMAT(criado_em, '%d/%m/%Y %H:%i:%s') AS criado_em
            FROM auditoria
            {$whereSql}
            ORDER BY id DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decodifica JSON armazenado
        foreach ($rows as &$row) {
            $row['dados_antes']  = $row['dados_antes']  ? json_decode($row['dados_antes'],  true) : null;
            $row['dados_depois'] = $row['dados_depois'] ? json_decode($row['dados_depois'], true) : null;
        }
        unset($row);

        // Módulos distintos para o filtro
        $modulos = $pdo->query("SELECT DISTINCT modulo FROM auditoria ORDER BY modulo ASC")
                       ->fetchAll(PDO::FETCH_COLUMN);

        // Usuários distintos para o filtro
        $usuarios = $pdo->query("SELECT DISTINCT usuario FROM auditoria ORDER BY usuario ASC")
                        ->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode([
            'success'  => true,
            'page'     => $page,
            'pages'    => $pages,
            'total'    => $total,
            'rows'     => $rows,
            'modulos'  => $modulos,
            'usuarios' => $usuarios,
        ]);
        exit;
    }

    /* =====================================================
       NOTIFICATIONS — últimas N ações após timestamp
    ===================================================== */
    if ($action === 'notifications') {

        $since = nt($body['since'] ?? '');
        $limit = min(50, max(1, (int)($body['limit'] ?? 20)));

        $where  = [];
        $params = [];

        if ($since !== '') {
            $where[]  = "criado_em > ?";
            $params[] = $since;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare("
            SELECT
                id,
                usuario,
                acao,
                modulo,
                registro_id,
                descricao,
                ip,
                DATE_FORMAT(criado_em, '%d/%m/%Y %H:%i:%s') AS criado_em,
                criado_em AS criado_em_raw
            FROM auditoria
            {$whereSql}
            ORDER BY id DESC
            LIMIT {$limit}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtN = $pdo->prepare("SELECT COUNT(*) c FROM auditoria {$whereSql}");
        $stmtN->execute($params);
        $total_novas = (int)$stmtN->fetch()['c'];

        echo json_encode([
            'success'     => true,
            'rows'        => $rows,
            'total_novas' => $total_novas,
        ]);
        exit;
    }

    throw new Exception('Ação inválida');

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
