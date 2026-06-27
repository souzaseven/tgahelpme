<?php
require_once __DIR__ . '/../../backend/banco/conexao.php';

$p      = TABLE_PREFIX;
$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET: listar ───────────────────────────────────────────────
if ($metodo === 'GET') {
    // Resolve período (de/ate tem prioridade; fallback para mes/ano)
    if (!empty($_GET['de']) && !empty($_GET['ate'])) {
        $de  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['de'])  ? $_GET['de']  : date('Y-m-01');
        $ate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['ate']) ? $_GET['ate'] : date('Y-m-t');
    } else {
        $mes = (int)($_GET['mes'] ?? date('m'));
        $ano = (int)($_GET['ano'] ?? date('Y'));
        $de  = sprintf('%04d-%02d-01', $ano, $mes);
        $ate = date('Y-m-t', mktime(0, 0, 0, $mes, 1, $ano));
    }
    $catRaw = trim($_GET['categoria_id'] ?? '');
    $semCat = $catRaw === 'nenhuma';
    $catId  = $semCat ? 0 : (int)$catRaw;
    $status = trim($_GET['status']        ?? '');
    $busca  = trim($_GET['busca']         ?? '');

    $where  = ["t.tipo = 'receita'", "t.data BETWEEN ? AND ?"];
    $params = [$de, $ate];

    // Multi-seleção: "1,2,-1" = responsáveis; "tcr_3" = terceiro ID 3
    $respRaw     = trim($_GET['responsaveis'] ?? '');
    $allVals     = $respRaw !== '' ? array_map('trim', explode(',', $respRaw)) : [];
    $respIds     = []; $respNull = false; $terceiroIds = [];
    foreach ($allVals as $v) {
        if ($v === '-1')                { $respNull = true; }
        elseif (str_starts_with($v, 'tcr_')) { $tid = (int)substr($v, 4); if ($tid > 0) $terceiroIds[] = $tid; }
        else                            { $id = (int)$v; if ($id > 0) $respIds[] = $id; }
    }
    $hasResp = !empty($respIds) || $respNull;
    $hasTer  = !empty($terceiroIds);

    if (!$hasResp && !$hasTer) {
        $where[] = 't.terceiro_id IS NULL';
    } elseif ($hasResp && !$hasTer) {
        $where[] = 't.terceiro_id IS NULL';
        $rP = [];
        if (!empty($respIds)) { $ph = implode(',', array_fill(0, count($respIds), '?')); $rP[] = "t.responsavel_id IN ($ph)"; array_push($params, ...$respIds); }
        if ($respNull) $rP[] = 't.responsavel_id IS NULL';
        $where[] = '(' . implode(' OR ', $rP) . ')';
    } elseif (!$hasResp && $hasTer) {
        $ph = implode(',', array_fill(0, count($terceiroIds), '?'));
        $where[] = "t.terceiro_id IN ($ph)";
        array_push($params, ...$terceiroIds);
    } else {
        $rP = [];
        if (!empty($respIds)) { $ph = implode(',', array_fill(0, count($respIds), '?')); $rP[] = "t.responsavel_id IN ($ph)"; array_push($params, ...$respIds); }
        if ($respNull) $rP[] = 't.responsavel_id IS NULL';
        $pessoal = !empty($rP) ? '(t.terceiro_id IS NULL AND (' . implode(' OR ', $rP) . '))' : 't.terceiro_id IS NULL';
        $ph = implode(',', array_fill(0, count($terceiroIds), '?'));
        $where[] = "($pessoal OR t.terceiro_id IN ($ph))";
        array_push($params, ...$terceiroIds);
    }

    $contaId2 = (int)($_GET['conta_id']  ?? 0);
    $valorMin = strlen($_GET['valor_min'] ?? '') ? (float)$_GET['valor_min'] : null;
    $valorMax = strlen($_GET['valor_max'] ?? '') ? (float)$_GET['valor_max'] : null;

    if ($semCat)           { $where[] = 't.categoria_id IS NULL'; }
    elseif ($catId > 0)    { $where[] = 't.categoria_id = ?';   $params[] = $catId; }
    if ($status !== '')    { $where[] = 't.status = ?';          $params[] = $status; }
    if ($busca  !== '')    { $where[] = 't.descricao LIKE ?';    $params[] = "%$busca%"; }
    if ($contaId2 > 0)     { $where[] = 't.conta_id = ?';        $params[] = $contaId2; }
    if ($valorMin !== null) { $where[] = 't.valor >= ?';          $params[] = $valorMin; }
    if ($valorMax !== null) { $where[] = 't.valor <= ?';          $params[] = $valorMax; }

    $wSql = implode(' AND ', $where);

    $pagina    = max(1, (int)($_GET['pagina']    ?? 1));
    $porPagina = max(10, min(200, (int)($_GET['por_pagina'] ?? 50)));
    $offset    = ($pagina - 1) * $porPagina;

    try {
        $stmt = $pdo->prepare(
            "SELECT t.*, c.nome cat_nome, c.cor cat_cor, c.icone cat_icone,
                    cp.nome cat_pai_nome,
                    ct.nome conta_nome,
                    r.nome resp_nome, r.cor resp_cor, r.icone resp_icone,
                    tc.nome terceiro_nome, tc.cor terceiro_cor, tc.icone terceiro_icone
             FROM `{$p}transacoes` t
             LEFT JOIN `{$p}categorias`   c  ON c.id  = t.categoria_id
             LEFT JOIN `{$p}categorias`   cp ON cp.id = c.categoria_pai
             LEFT JOIN `{$p}contas`       ct ON ct.id = t.conta_id
             LEFT JOIN `{$p}responsaveis` r  ON r.id  = t.responsavel_id
             LEFT JOIN `{$p}terceiros`    tc ON tc.id = t.terceiro_id
             WHERE $wSql
             ORDER BY t.data DESC, t.id DESC LIMIT $porPagina OFFSET $offset"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Total de registros (para paginação)
        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM `{$p}transacoes` t WHERE $wSql"
        );
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
    } catch (Throwable $ex) {
        erroInterno($ex, 'Erro ao carregar dados.');
    }

    // KPI usa os mesmos filtros da listagem (exceto busca textual)
    $kpiWhere  = ["tipo='receita'", "data BETWEEN ? AND ?"];
    $kpiParams = [$de, $ate];
    if (!$hasResp && !$hasTer) {
        $kpiWhere[] = 'terceiro_id IS NULL';
    } elseif ($hasResp && !$hasTer) {
        $kpiWhere[] = 'terceiro_id IS NULL';
        $kP = [];
        if (!empty($respIds)) { $ph = implode(',', array_fill(0, count($respIds), '?')); $kP[] = "responsavel_id IN ($ph)"; array_push($kpiParams, ...$respIds); }
        if ($respNull) $kP[] = 'responsavel_id IS NULL';
        $kpiWhere[] = '(' . implode(' OR ', $kP) . ')';
    } elseif (!$hasResp && $hasTer) {
        $ph = implode(',', array_fill(0, count($terceiroIds), '?'));
        $kpiWhere[] = "terceiro_id IN ($ph)";
        array_push($kpiParams, ...$terceiroIds);
    } else {
        $kP = [];
        if (!empty($respIds)) { $ph = implode(',', array_fill(0, count($respIds), '?')); $kP[] = "responsavel_id IN ($ph)"; array_push($kpiParams, ...$respIds); }
        if ($respNull) $kP[] = 'responsavel_id IS NULL';
        $pessoalK = !empty($kP) ? '(terceiro_id IS NULL AND (' . implode(' OR ', $kP) . '))' : 'terceiro_id IS NULL';
        $ph = implode(',', array_fill(0, count($terceiroIds), '?'));
        $kpiWhere[] = "($pessoalK OR terceiro_id IN ($ph))";
        array_push($kpiParams, ...$terceiroIds);
    }
    if ($semCat)           { $kpiWhere[] = "categoria_id IS NULL"; }
    elseif ($catId > 0)    { $kpiWhere[] = "categoria_id=?";   $kpiParams[] = $catId; }
    if ($contaId2 > 0)     { $kpiWhere[] = "conta_id=?";       $kpiParams[] = $contaId2; }
    if ($valorMin !== null) { $kpiWhere[] = "valor >= ?";       $kpiParams[] = $valorMin; }
    if ($valorMax !== null) { $kpiWhere[] = "valor <= ?";       $kpiParams[] = $valorMax; }

    $kpi = $pdo->prepare(
        "SELECT COALESCE(SUM(valor),0) total,
                COALESCE(SUM(CASE WHEN status='pago'     THEN valor END),0) pago,
                COALESCE(SUM(CASE WHEN status='pendente' THEN valor END),0) pendente,
                COUNT(*) qtd
         FROM `{$p}transacoes`
         WHERE " . implode(' AND ', $kpiWhere)
    );
    $kpi->execute($kpiParams);

    respostaJSON([
        'success'       => true,
        'dados'         => $rows,
        'kpi'           => $kpi->fetch(),
        'total'         => $total,
        'pagina'        => $pagina,
        'por_pagina'    => $porPagina,
        'total_paginas' => (int)ceil($total / $porPagina),
    ]);
}

// ── POST: criar / atualizar ───────────────────────────────────
if ($metodo === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true) ?? [];

    // ── Excluir em lote ──────────────────────────────────────
    if (($d['acao'] ?? '') === 'excluir_bulk') {
        $ids = array_values(array_filter(array_map('intval', $d['ids'] ?? []), fn($i) => $i > 0));
        if (!$ids) respostaJSON(['success' => false, 'erro' => 'Nenhum ID informado.']);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM `{$p}transacoes` WHERE id IN ($ph) AND tipo='receita'")->execute($ids);
        respostaJSON(['success' => true, 'msg' => count($ids) . ' lançamento(s) excluído(s).']);
    }

    // ── Alterar status em lote ────────────────────────────────
    if (($d['acao'] ?? '') === 'alterar_status_bulk') {
        $ids    = array_values(array_filter(array_map('intval', $d['ids'] ?? []), fn($i) => $i > 0));
        $status = in_array($d['status'] ?? '', ['pago','pendente','cancelado']) ? $d['status'] : null;
        if (!$ids || !$status) respostaJSON(['success' => false, 'erro' => 'Dados inválidos.']);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE `{$p}transacoes` SET status=?, atualizado_em=NOW() WHERE id IN ($ph) AND tipo='receita'")
            ->execute([$status, ...$ids]);
        respostaJSON(['success' => true, 'msg' => count($ids) . ' lançamento(s) atualizado(s).']);
    }

    // ── Ação rápida: marcar como recebido ─────────────────────
    if (($d['acao'] ?? '') === 'marcar_pago') {
        $id = (int)($d['id'] ?? 0);
        if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);

        $s = $pdo->prepare("SELECT * FROM `{$p}transacoes` WHERE id=? AND tipo='receita'");
        $s->execute([$id]);
        $row = $s->fetch();
        if (!$row) respostaJSON(['success' => false, 'erro' => 'Receita não encontrada.']);
        if ($row['status'] === 'pago') respostaJSON(['success' => true, 'msg' => 'Já estava recebida.']);

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE `{$p}transacoes` SET status='pago', atualizado_em=NOW() WHERE id=?")
                ->execute([$id]);

            if ($row['conta_id']) {
                $pdo->prepare("UPDATE `{$p}contas` SET saldo_atual = saldo_atual + ? WHERE id=?")
                    ->execute([$row['valor'], $row['conta_id']]);
            }

            $pdo->commit();
            respostaJSON(['success' => true, 'msg' => 'Receita marcada como recebida.']);
        } catch (Throwable $e) {
            $pdo->rollBack();
            respostaJSON(['success' => false, 'erro' => 'Erro ao atualizar status.']);
        }
    }

    $descricao = trim($d['descricao'] ?? '');
    $valor     = round(abs((float)($d['valor'] ?? 0)), 2);
    $data      = $d['data'] ?? '';

    if (!$descricao || !$valor || !$data) {
        respostaJSON(['success' => false, 'erro' => 'Descrição, valor e data são obrigatórios.']);
    }

    $catId      = (int)($d['categoria_id']  ?? 0) ?: null;
    $contaId    = (int)($d['conta_id']      ?? 0) ?: null;
    $respId     = (int)($d['responsavel_id']?? 0) ?: null;
    $terceiroId = (int)($d['terceiro_id']   ?? 0) ?: null;
    $status  = in_array($d['status'] ?? '', ['pendente','pago','cancelado']) ? $d['status'] : 'pago';
    $obs     = trim($d['observacao'] ?? '');
    $id      = (int)($d['id']        ?? 0);

    if ($id > 0) {
        $stmt = $pdo->prepare(
            "UPDATE `{$p}transacoes`
             SET descricao=?, valor=?, data=?, categoria_id=?, conta_id=?,
                 responsavel_id=?, terceiro_id=?, status=?, observacao=?, atualizado_em=NOW()
             WHERE id=? AND tipo='receita'"
        );
        $stmt->execute([$descricao, $valor, $data, $catId, $contaId, $respId, $terceiroId, $status, $obs, $id]);
        respostaJSON(['success' => true, 'msg' => 'Receita atualizada.']);
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO `{$p}transacoes`
             (tipo, descricao, valor, data, categoria_id, conta_id, responsavel_id, terceiro_id, status, observacao)
             VALUES ('receita',?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([$descricao, $valor, $data, $catId, $contaId, $respId, $terceiroId, $status, $obs]);

        // Credita na conta se pago
        if ($contaId && $status === 'pago') {
            $pdo->prepare("UPDATE `{$p}contas` SET saldo_atual = saldo_atual + ? WHERE id=?")
                ->execute([$valor, $contaId]);
        }

        $pdo->commit();
        respostaJSON(['success' => true, 'msg' => 'Receita criada com sucesso.']);
    } catch (Throwable $e) {
        $pdo->rollBack();
        erroInterno($e, 'Erro ao salvar.');
    }
}

// ── DELETE: excluir ───────────────────────────────────────────
if ($metodo === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);

    $tx = $pdo->prepare("SELECT * FROM `{$p}transacoes` WHERE id=? AND tipo='receita'");
    $tx->execute([$id]);
    $row = $tx->fetch();
    if (!$row) respostaJSON(['success' => false, 'erro' => 'Receita não encontrada.']);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM `{$p}transacoes` WHERE id=? AND tipo='receita'")->execute([$id]);

        if ($row['conta_id'] && $row['status'] === 'pago') {
            $pdo->prepare("UPDATE `{$p}contas` SET saldo_atual = saldo_atual - ? WHERE id=?")
                ->execute([$row['valor'], $row['conta_id']]);
        }

        $pdo->commit();
        respostaJSON(['success' => true, 'msg' => 'Receita excluída.']);
    } catch (Throwable $e) {
        $pdo->rollBack();
        respostaJSON(['success' => false, 'erro' => 'Erro ao excluir.']);
    }
}

respostaJSON(['success' => false, 'erro' => 'Método não permitido.']);
