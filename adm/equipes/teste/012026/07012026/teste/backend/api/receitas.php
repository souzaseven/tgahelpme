<?php
// ============================================================
// receitas.php — Espelho de despesas.php, mas mais simples: não
// tem parcelamento nem divisão entre responsáveis (uma receita é
// sempre um lançamento só). Ao marcar como "pago" (recebido),
// credita o valor na conta vinculada; ao editar/excluir, desfaz
// esse crédito antes de aplicar a mudança. Se vier com cartao_id
// (recarga de cartão vale-alimentação/refeição), o mesmo crédito
// é aplicado no limite_total do cartão em vez de uma conta.
//
// GET  (sem ação)            — lista filtrada + KPIs do período
// POST acao=excluir_bulk / editar_bulk / marcar_pago
// POST (sem ação, com id)    — edita uma receita existente
// POST (sem ação, sem id)    — cria uma receita
// DELETE ?id=X               — exclui e desfaz o crédito na conta
// ============================================================
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
    // Ver comentário equivalente em backend/api/despesas.php: "qtd" precisa
    // bater com a tabela (respeitar o filtro de status), Total/Pago/Pendente não.
    $kpiRow = $kpi->fetch();
    $kpiRow['qtd'] = $total;

    // Ver comentário equivalente em backend/api/despesas.php.
    $duracaoDias  = (int) round((strtotime($ate) - strtotime($de)) / 86400) + 1;
    $ateAnterior  = date('Y-m-d', strtotime($de) - 86400);
    $deAnterior   = date('Y-m-d', strtotime($ateAnterior) - (($duracaoDias - 1) * 86400));
    $kpiParamsAnt = $kpiParams;
    $kpiParamsAnt[0] = $deAnterior;
    $kpiParamsAnt[1] = $ateAnterior;
    $kpiAnt = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM `{$p}transacoes` WHERE " . implode(' AND ', $kpiWhere));
    $kpiAnt->execute($kpiParamsAnt);
    $kpiRow['total_anterior'] = (float)$kpiAnt->fetchColumn();

    respostaJSON([
        'success'       => true,
        'dados'         => $rows,
        'kpi'           => $kpiRow,
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

    // ── Editar vários campos em lote ──────────────────────────
    // Cartão (recarga de vale) fica de fora do lote de propósito: mexe em
    // limite_total de forma exclusiva com conta_id e exige validar o tipo do
    // cartão por linha — edição em massa desse campo continua pela tela
    // individual pra não arriscar inconsistência de saldo.
    if (($d['acao'] ?? '') === 'editar_bulk') {
        $ids = array_values(array_filter(array_map('intval', $d['ids'] ?? []), fn($i) => $i > 0));
        if (!$ids) respostaJSON(['success' => false, 'erro' => 'Nenhum ID informado.']);
        $campos = is_array($d['campos'] ?? null) ? $d['campos'] : [];
        if (!$campos) respostaJSON(['success' => false, 'erro' => 'Nenhum campo para alterar.']);

        $ph    = implode(',', array_fill(0, count($ids), '?'));
        $sRows = $pdo->prepare("SELECT id FROM `{$p}transacoes` WHERE id IN ($ph) AND tipo='receita'");
        $sRows->execute($ids);
        $idsValidos = array_column($sRows->fetchAll(), 'id');
        if (!$idsValidos) respostaJSON(['success' => false, 'erro' => 'Nenhum lançamento encontrado.']);
        $phValidos = implode(',', array_fill(0, count($idsValidos), '?'));

        $sets   = [];
        $params = [];
        if (array_key_exists('status', $campos) && in_array($campos['status'], ['pago', 'pendente', 'cancelado'], true)) {
            $sets[] = 'status=?'; $params[] = $campos['status'];
        }
        if (array_key_exists('categoria_id', $campos))  { $sets[] = 'categoria_id=?';   $params[] = (int)$campos['categoria_id'] ?: null; }
        if (array_key_exists('conta_id', $campos))       { $sets[] = 'conta_id=?';       $params[] = (int)$campos['conta_id'] ?: null; }
        if (array_key_exists('responsavel_id', $campos)) { $sets[] = 'responsavel_id=?'; $params[] = (int)$campos['responsavel_id'] ?: null; }
        if (array_key_exists('terceiro_id', $campos))    { $sets[] = 'terceiro_id=?';    $params[] = (int)$campos['terceiro_id'] ?: null; }
        if (array_key_exists('observacao', $campos))     { $sets[] = 'observacao=?';     $params[] = trim((string)$campos['observacao']); }

        if (!$sets) respostaJSON(['success' => false, 'erro' => 'Nenhum campo válido para alterar.']);

        $sql = "UPDATE `{$p}transacoes` SET " . implode(',', $sets) . ", atualizado_em=NOW() WHERE id IN ($phValidos)";
        $pdo->prepare($sql)->execute([...$params, ...$idsValidos]);
        respostaJSON(['success' => true, 'msg' => count($idsValidos) . ' lançamento(s) atualizado(s).']);
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

            if ($row['cartao_id']) {
                $pdo->prepare("UPDATE `{$p}cartoes` SET limite_total = limite_total + ?, atualizado_em=NOW() WHERE id=?")
                    ->execute([$row['valor'], $row['cartao_id']]);
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
    $cartaoId   = (int)($d['cartao_id']     ?? 0) ?: null;
    $respId     = (int)($d['responsavel_id']?? 0) ?: null;
    $terceiroId = (int)($d['terceiro_id']   ?? 0) ?: null;
    $status  = in_array($d['status'] ?? '', ['pendente','pago','cancelado']) ? $d['status'] : 'pago';
    $obs     = trim($d['observacao'] ?? '');
    $id      = (int)($d['id']        ?? 0);
    $comprovantePath = trim($d['comprovante_path'] ?? '') ?: null;

    // Recarga de cartão pré-pago (vale-alimentação/refeição) — cartões de
    // crédito não recebem receita diretamente, só abatem fatura.
    if ($cartaoId) {
        $ccStmt = $pdo->prepare("SELECT tipo FROM `{$p}cartoes` WHERE id=?");
        $ccStmt->execute([$cartaoId]);
        $ccTipo = $ccStmt->fetchColumn();
        if (!$ccTipo) respostaJSON(['success' => false, 'erro' => 'Cartão não encontrado.']);
        if ($ccTipo === 'credito') respostaJSON(['success' => false, 'erro' => 'Recarga só é permitida em cartões vale-alimentação/refeição.']);
        // Recarga é exclusiva: o valor vira saldo no cartão, não entra em conta.
        $contaId = null;
    }

    if ($id > 0) {
        $sOld = $pdo->prepare("SELECT * FROM `{$p}transacoes` WHERE id=? AND tipo='receita'");
        $sOld->execute([$id]);
        $old = $sOld->fetch();
        if (!$old) respostaJSON(['success' => false, 'erro' => 'Receita não encontrada.']);
        $comprovanteAntigo = $old['comprovante_path'] ?: null;

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "UPDATE `{$p}transacoes`
                 SET descricao=?, valor=?, data=?, categoria_id=?, conta_id=?, cartao_id=?,
                     responsavel_id=?, terceiro_id=?, status=?, observacao=?, comprovante_path=?, atualizado_em=NOW()
                 WHERE id=? AND tipo='receita'"
            );
            $stmt->execute([$descricao, $valor, $data, $catId, $contaId, $cartaoId, $respId, $terceiroId, $status, $obs, $comprovantePath, $id]);

            // Desfaz efeito antigo e aplica o novo (conta e/ou cartão pré-pago)
            $oldCartao = (int)($old['cartao_id'] ?? 0) ?: null;
            if ($oldCartao && $old['status'] === 'pago') {
                $pdo->prepare("UPDATE `{$p}cartoes` SET limite_total = GREATEST(0, limite_total - ?), atualizado_em=NOW() WHERE id=?")
                    ->execute([(float)$old['valor'], $oldCartao]);
            }
            if ($cartaoId && $status === 'pago') {
                $pdo->prepare("UPDATE `{$p}cartoes` SET limite_total = limite_total + ?, atualizado_em=NOW() WHERE id=?")
                    ->execute([$valor, $cartaoId]);
            }

            if ($comprovanteAntigo && $comprovanteAntigo !== $comprovantePath) {
                @unlink(__DIR__ . '/../../' . $comprovanteAntigo);
            }

            $pdo->commit();
            respostaJSON(['success' => true, 'msg' => 'Receita atualizada.']);
        } catch (Throwable $e) {
            $pdo->rollBack();
            erroInterno($e, 'Erro ao salvar.');
        }
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO `{$p}transacoes`
             (tipo, descricao, valor, data, categoria_id, conta_id, cartao_id, responsavel_id, terceiro_id, status, observacao, comprovante_path)
             VALUES ('receita',?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([$descricao, $valor, $data, $catId, $contaId, $cartaoId, $respId, $terceiroId, $status, $obs, $comprovantePath]);

        // Credita na conta se pago
        if ($contaId && $status === 'pago') {
            $pdo->prepare("UPDATE `{$p}contas` SET saldo_atual = saldo_atual + ? WHERE id=?")
                ->execute([$valor, $contaId]);
        }

        // Recarga: aumenta o limite do cartão pré-pago
        if ($cartaoId && $status === 'pago') {
            $pdo->prepare("UPDATE `{$p}cartoes` SET limite_total = limite_total + ?, atualizado_em=NOW() WHERE id=?")
                ->execute([$valor, $cartaoId]);
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

        if (!empty($row['comprovante_path'])) {
            @unlink(__DIR__ . '/../../' . $row['comprovante_path']);
        }

        if ($row['conta_id'] && $row['status'] === 'pago') {
            $pdo->prepare("UPDATE `{$p}contas` SET saldo_atual = saldo_atual - ? WHERE id=?")
                ->execute([$row['valor'], $row['conta_id']]);
        }

        if ($row['cartao_id'] && $row['status'] === 'pago') {
            $pdo->prepare("UPDATE `{$p}cartoes` SET limite_total = GREATEST(0, limite_total - ?), atualizado_em=NOW() WHERE id=?")
                ->execute([$row['valor'], $row['cartao_id']]);
        }

        $pdo->commit();
        respostaJSON(['success' => true, 'msg' => 'Receita excluída.']);
    } catch (Throwable $e) {
        $pdo->rollBack();
        respostaJSON(['success' => false, 'erro' => 'Erro ao excluir.']);
    }
}

respostaJSON(['success' => false, 'erro' => 'Método não permitido.']);
