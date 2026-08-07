<?php
// ============================================================
// despesas.php — O maior e mais movimentado dos módulos: lista com
// todos os filtros da tela, e o "salvar" que sabe lidar com três
// jeitos diferentes de criar uma despesa a partir do mesmo formulário:
//   1) normal — uma transação só;
//   2) parcelada — gera N transações (parcela_atual/parcela_total),
//      cada uma com sua própria fatura de cartão calculada por
//      Calculos::mesFatura() quando aplicável;
//   3) dividida entre responsáveis — gera uma transação por pessoa,
//      todas com o mesmo grupo_id, com ajuste de centavos na primeira
//      pra fechar o valor total exato.
// Editar usa auditoria (registrarAudit) pra manter histórico de
// alterações; criar e parcelar não guardam auditoria (só o estado final).
//
// GET  (sem ação)            — lista filtrada + KPIs do período
// POST acao=excluir_bulk / editar_bulk / marcar_pago
// POST (sem ação, com id)    — edita uma despesa existente
// POST (sem ação, sem id)    — cria (normal, parcelada ou dividida)
// DELETE ?id=X               — exclui e desfaz o impacto no saldo/limite
// ============================================================
require_once __DIR__ . '/../../backend/banco/conexao.php';
require_once __DIR__ . '/../helpers/audit.php';
require_once __DIR__ . '/../../src/Financeiro/Calculos.php';

use FinanceOS\Financeiro\Calculos;

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

    $where  = ["t.tipo = 'despesa'", "COALESCE(t.data_vencimento, t.data) BETWEEN ? AND ?"];
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

    // Filtro de pessoa: quando nenhum selecionado → só pessoal; ao selecionar terceiros → mostra os deles
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
        // Ambos: pessoal filtrado + terceiros
        $rP = [];
        if (!empty($respIds)) { $ph = implode(',', array_fill(0, count($respIds), '?')); $rP[] = "t.responsavel_id IN ($ph)"; array_push($params, ...$respIds); }
        if ($respNull) $rP[] = 't.responsavel_id IS NULL';
        $pessoal = !empty($rP) ? '(t.terceiro_id IS NULL AND (' . implode(' OR ', $rP) . '))' : 't.terceiro_id IS NULL';
        $ph = implode(',', array_fill(0, count($terceiroIds), '?'));
        $where[] = "($pessoal OR t.terceiro_id IN ($ph))";
        array_push($params, ...$terceiroIds);
    }

    $contaId2 = (int)($_GET['conta_id']  ?? 0);
    $cartaoId2= (int)($_GET['cartao_id'] ?? 0);
    $valorMin = strlen($_GET['valor_min'] ?? '') ? (float)$_GET['valor_min'] : null;
    $valorMax = strlen($_GET['valor_max'] ?? '') ? (float)$_GET['valor_max'] : null;

    $tag = trim($_GET['tag'] ?? '');

    if ($semCat)           { $where[] = 't.categoria_id IS NULL'; }
    elseif ($catId > 0)    { $where[] = 't.categoria_id = ?';   $params[] = $catId; }
    if ($status !== '')    { $where[] = 't.status = ?';          $params[] = $status; }
    if ($busca  !== '')    { $where[] = 't.descricao LIKE ?';    $params[] = "%$busca%"; }
    if ($tag    !== '')    { $where[] = 't.tags LIKE ?';         $params[] = "%$tag%"; }
    if ($contaId2 > 0)     { $where[] = 't.conta_id = ?';        $params[] = $contaId2; }
    if ($cartaoId2 > 0)    { $where[] = 't.cartao_id = ?';       $params[] = $cartaoId2; }
    if ($valorMin !== null) { $where[] = 't.valor >= ?';          $params[] = $valorMin; }
    if ($valorMax !== null) { $where[] = 't.valor <= ?';          $params[] = $valorMax; }

    $wSql = implode(' AND ', $where);

    $pagina    = max(1, (int)($_GET['pagina']    ?? 1));
    $porPagina = max(10, min(200, (int)($_GET['por_pagina'] ?? 50)));
    $offset    = ($pagina - 1) * $porPagina;

    try {
        $stmt = $pdo->prepare(
            "SELECT t.*, c.nome cat_nome, c.cor cat_cor, c.icone cat_icone,
                    ct.nome conta_nome, cc.nome cartao_nome,
                    r.nome resp_nome, r.cor resp_cor, r.icone resp_icone,
                    cp.nome cat_pai_nome,
                    tc.nome terceiro_nome, tc.cor terceiro_cor, tc.icone terceiro_icone,
                    ep.id parcela_id, ep.numero parcela_numero,
                    ep.valor_parcela valor_original_parcela,
                    ep.status parcela_status,
                    ep.data_vencimento parcela_vencimento,
                    e.id emprestimo_id, e.nome emp_nome,
                    e.total_parcelas emp_total_parcelas,
                    e.saldo_devedor emp_saldo_devedor,
                    e.conta_debito_id emp_conta_id,
                    cf.mes_referencia fat_mes, cf.ano_referencia fat_ano,
                    cf.data_vencimento fat_vencimento
             FROM `{$p}transacoes` t
             LEFT JOIN `{$p}categorias`          c  ON c.id  = t.categoria_id
             LEFT JOIN `{$p}categorias`          cp ON cp.id = c.categoria_pai
             LEFT JOIN `{$p}contas`              ct ON ct.id = t.conta_id
             LEFT JOIN `{$p}cartoes`             cc ON cc.id = t.cartao_id
             LEFT JOIN `{$p}responsaveis`        r  ON r.id  = t.responsavel_id
             LEFT JOIN `{$p}terceiros`           tc ON tc.id = t.terceiro_id
             LEFT JOIN `{$p}emprestimos_parcelas` ep ON ep.transacao_id = t.id
             LEFT JOIN `{$p}emprestimos`          e  ON e.id = ep.emprestimo_id
             LEFT JOIN `{$p}cartoes_faturas`      cf ON cf.id = t.fatura_id
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

    // KPI usa os mesmos filtros da listagem (exceto busca textual e tag)
    $kpiWhere  = ["tipo='despesa'", "COALESCE(data_vencimento, data) BETWEEN ? AND ?"];
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
    if ($cartaoId2 > 0)    { $kpiWhere[] = "cartao_id=?";      $kpiParams[] = $cartaoId2; }
    if ($valorMin !== null) { $kpiWhere[] = "valor >= ?";       $kpiParams[] = $valorMin; }
    if ($valorMax !== null) { $kpiWhere[] = "valor <= ?";       $kpiParams[] = $valorMax; }

    $kpi = $pdo->prepare(
        "SELECT COALESCE(SUM(valor),0) total,
                COALESCE(SUM(CASE WHEN status='pendente' THEN valor END),0) pendente,
                COALESCE(SUM(CASE WHEN status='pago'     THEN valor END),0) pago,
                COUNT(*) qtd
         FROM `{$p}transacoes`
         WHERE " . implode(' AND ', $kpiWhere)
    );
    $kpi->execute($kpiParams);
    // $kpiWhere não inclui o filtro de status de propósito (Pago/Pendente/Total
    // são a quebra por status em si — filtrar por status zeraria um dos dois).
    // Mas "qtd" representa contagem de lançamentos, então precisa bater com o
    // que a tabela está de fato exibindo: reusa $total, que já respeita status.
    $kpiRow = $kpi->fetch();
    $kpiRow['qtd'] = $total;

    // Comparação com o período imediatamente anterior, de mesma duração
    // (mês corrido → mês anterior; período custom → janela igual antes dele).
    // Usa o mesmo $kpiWhere (todos os filtros, exceto status/busca/tag) só
    // trocando as datas — por isso reaproveita os índices 0/1 de $kpiParams,
    // que são sempre [$de, $ate] por construção.
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
        $pdo->prepare("DELETE FROM `{$p}transacoes` WHERE id IN ($ph) AND tipo='despesa'")->execute($ids);
        respostaJSON(['success' => true, 'msg' => count($ids) . ' lançamento(s) excluído(s).']);
    }

    // ── Editar vários campos em lote ──────────────────────────
    // $campos só contém as chaves que o usuário marcou pra alterar no modal;
    // presença da chave (array_key_exists) decide se o campo é tocado, não o
    // valor — assim dá pra "limpar" categoria/conta/cartão/responsável (null).
    if (($d['acao'] ?? '') === 'editar_bulk') {
        $ids = array_values(array_filter(array_map('intval', $d['ids'] ?? []), fn($i) => $i > 0));
        if (!$ids) respostaJSON(['success' => false, 'erro' => 'Nenhum ID informado.']);
        $campos = is_array($d['campos'] ?? null) ? $d['campos'] : [];
        if (!$campos) respostaJSON(['success' => false, 'erro' => 'Nenhum campo para alterar.']);

        $ph    = implode(',', array_fill(0, count($ids), '?'));
        $sRows = $pdo->prepare("SELECT id, valor, cartao_id FROM `{$p}transacoes` WHERE id IN ($ph) AND tipo='despesa'");
        $sRows->execute($ids);
        $rows = $sRows->fetchAll();
        if (!$rows) respostaJSON(['success' => false, 'erro' => 'Nenhum lançamento encontrado.']);
        $idsValidos = array_column($rows, 'id');
        $phValidos  = implode(',', array_fill(0, count($idsValidos), '?'));

        $sets   = [];
        $params = [];
        if (array_key_exists('status', $campos) && in_array($campos['status'], ['pago', 'pendente', 'cancelado'], true)) {
            $sets[] = 'status=?'; $params[] = $campos['status'];
        }
        if (array_key_exists('categoria_id', $campos))      { $sets[] = 'categoria_id=?';    $params[] = (int)$campos['categoria_id'] ?: null; }
        if (array_key_exists('conta_id', $campos))           { $sets[] = 'conta_id=?';        $params[] = (int)$campos['conta_id'] ?: null; }
        if (array_key_exists('responsavel_id', $campos))     { $sets[] = 'responsavel_id=?';  $params[] = (int)$campos['responsavel_id'] ?: null; }
        if (array_key_exists('terceiro_id', $campos))        { $sets[] = 'terceiro_id=?';     $params[] = (int)$campos['terceiro_id'] ?: null; }
        if (array_key_exists('data_vencimento', $campos))    { $dv = trim((string)($campos['data_vencimento'] ?? '')); $sets[] = 'data_vencimento=?'; $params[] = $dv !== '' ? $dv : null; }
        if (array_key_exists('observacao', $campos))         { $sets[] = 'observacao=?';      $params[] = trim((string)$campos['observacao']); }

        $alterandoCartao = array_key_exists('cartao_id', $campos);
        $novoCartaoId    = $alterandoCartao ? ((int)$campos['cartao_id'] ?: null) : null;
        $tagsCfg         = is_array($campos['tags'] ?? null) ? $campos['tags'] : null;

        if (!$sets && !$alterandoCartao && !$tagsCfg) {
            respostaJSON(['success' => false, 'erro' => 'Nenhum campo válido para alterar.']);
        }

        $pdo->beginTransaction();
        try {
            if ($sets) {
                $sql = "UPDATE `{$p}transacoes` SET " . implode(',', $sets) . ", atualizado_em=NOW() WHERE id IN ($phValidos)";
                $pdo->prepare($sql)->execute([...$params, ...$idsValidos]);
            }

            // Cartão precisa de reajuste linha a linha do limite_usado: cada
            // transação tem seu próprio valor e pode estar num cartão diferente
            // antes da troca — mesma lógica usada na edição individual.
            if ($alterandoCartao) {
                foreach ($rows as $row) {
                    $oldCartao = (int)($row['cartao_id'] ?? 0) ?: null;
                    if ($oldCartao === $novoCartaoId) continue;
                    $valorRow = (float)$row['valor'];
                    if ($oldCartao) {
                        $pdo->prepare("UPDATE `{$p}cartoes` SET limite_usado = GREATEST(0, limite_usado - ?), atualizado_em=NOW() WHERE id=?")
                            ->execute([$valorRow, $oldCartao]);
                    }
                    if ($novoCartaoId) {
                        $pdo->prepare("UPDATE `{$p}cartoes` SET limite_usado = limite_usado + ?, atualizado_em=NOW() WHERE id=?")
                            ->execute([$valorRow, $novoCartaoId]);
                    }
                }
                $pdo->prepare("UPDATE `{$p}transacoes` SET cartao_id=?, atualizado_em=NOW() WHERE id IN ($phValidos)")
                    ->execute([$novoCartaoId, ...$idsValidos]);
            }

            // Tags: "substituir" troca tudo; "adicionar" mescla sem duplicar,
            // preservando as tags que cada lançamento já tinha.
            if ($tagsCfg) {
                $modo      = ($tagsCfg['modo'] ?? '') === 'substituir' ? 'substituir' : 'adicionar';
                $novasTags = array_values(array_filter(array_map('trim', explode(',', (string)($tagsCfg['valor'] ?? '')))));
                if ($modo === 'substituir') {
                    $valorFinal = $novasTags ? implode(', ', $novasTags) : null;
                    $pdo->prepare("UPDATE `{$p}transacoes` SET tags=?, atualizado_em=NOW() WHERE id IN ($phValidos)")
                        ->execute([$valorFinal, ...$idsValidos]);
                } elseif ($novasTags) {
                    $sTags = $pdo->prepare("SELECT id, tags FROM `{$p}transacoes` WHERE id IN ($phValidos)");
                    $sTags->execute($idsValidos);
                    $upd = $pdo->prepare("UPDATE `{$p}transacoes` SET tags=?, atualizado_em=NOW() WHERE id=?");
                    foreach ($sTags->fetchAll() as $tRow) {
                        $atuais = array_values(array_filter(array_map('trim', explode(',', (string)$tRow['tags']))));
                        $merge  = array_values(array_unique(array_merge($atuais, $novasTags)));
                        $upd->execute([implode(', ', $merge), $tRow['id']]);
                    }
                }
            }

            $pdo->commit();
            respostaJSON(['success' => true, 'msg' => count($idsValidos) . ' lançamento(s) atualizado(s).']);
        } catch (Throwable $e) {
            $pdo->rollBack();
            erroInterno($e, 'Erro ao alterar em lote.');
        }
    }

    // ── Ação rápida: marcar como pago ─────────────────────────
    if (($d['acao'] ?? '') === 'marcar_pago') {
        $id = (int)($d['id'] ?? 0);
        if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);

        $s = $pdo->prepare("SELECT * FROM `{$p}transacoes` WHERE id=? AND tipo='despesa'");
        $s->execute([$id]);
        $row = $s->fetch();
        if (!$row) respostaJSON(['success' => false, 'erro' => 'Despesa não encontrada.']);
        if ($row['status'] === 'pago') respostaJSON(['success' => true, 'msg' => 'Já estava paga.']);

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE `{$p}transacoes` SET status='pago', atualizado_em=NOW() WHERE id=?")
                ->execute([$id]);

            if ($row['conta_id']) {
                $pdo->prepare("UPDATE `{$p}contas` SET saldo_atual = saldo_atual - ? WHERE id=?")
                    ->execute([$row['valor'], $row['conta_id']]);
            }

            $pdo->commit();
            respostaJSON(['success' => true, 'msg' => 'Despesa marcada como paga.']);
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
    $status    = in_array($d['status'] ?? '', ['pendente','pago','cancelado']) ? $d['status'] : 'pago';
    $obs       = trim($d['observacao']     ?? '');
    $tags      = substr(trim($d['tags']    ?? ''), 0, 255) ?: null;
    $parcelas  = max(1, (int)($d['parcela_total'] ?? 1));
    $id        = (int)($d['id']            ?? 0);
    $mesFatura  = (int)($d['mes_fatura']     ?? 0);
    $anoFatura  = (int)($d['ano_fatura']     ?? 0);
    $dataVenc   = trim($d['data_vencimento'] ?? '') ?: null;
    $comprovantePath = trim($d['comprovante_path'] ?? '') ?: null;

    // Calcula mês/ano da fatura (mês de PAGAMENTO): toda compra no cartão
    // vence sempre no mês seguinte ao da compra — ver Calculos::mesFatura().
    $calcMesFatura = static fn (string $dataCompra, int $diaFech): array => Calculos::mesFatura($dataCompra, $diaFech);

    // Encontra ou cria registro de fatura; retorna ID
    // $mes/$ano são o mês/ano de PAGAMENTO (convenção brasileira)
    $upsertFatura = function(int $cId, int $mes, int $ano) use ($pdo, $p): int {
        $s = $pdo->prepare("SELECT id FROM `{$p}cartoes_faturas` WHERE cartao_id=? AND mes_referencia=? AND ano_referencia=?");
        $s->execute([$cId, $mes, $ano]);
        $row = $s->fetch();
        if ($row) return (int)$row['id'];

        $cc = $pdo->prepare("SELECT dia_fechamento, dia_vencimento FROM `{$p}cartoes` WHERE id=?");
        $cc->execute([$cId]);
        $dias = $cc->fetch();
        $dF   = $dias ? min((int)$dias['dia_fechamento'], 28) : 1;
        $dV   = $dias ? min((int)$dias['dia_vencimento'],  28) : 10;
        // Mês de fechamento = mês de pagamento - 1
        $mClose = $mes === 1 ? 12 : $mes - 1;
        $aClose = $mes === 1 ? $ano - 1 : $ano;

        $pdo->prepare(
            "INSERT INTO `{$p}cartoes_faturas`
             (cartao_id, mes_referencia, ano_referencia, data_fechamento, data_vencimento, valor_total, valor_pago, status)
             VALUES (?,?,?,?,?,0,0,'aberta')"
        )->execute([
            $cId, $mes, $ano,
            sprintf('%04d-%02d-%02d', $aClose, $mClose, $dF),  // fecha no mês anterior
            sprintf('%04d-%02d-%02d', $ano,    $mes,    $dV),  // paga no mês da fatura
        ]);
        return (int)$pdo->lastInsertId();
    };

    if ($id > 0) {
        // Lê registro antigo para ajustar limite do cartão
        $sOld = $pdo->prepare("SELECT cartao_id, valor FROM `{$p}transacoes` WHERE id=? AND tipo='despesa'");
        $sOld->execute([$id]);
        $old = $sOld->fetch();

        // Snapshot antes da atualização (auditoria)
        $stSnap = $pdo->prepare("SELECT * FROM `{$p}transacoes` WHERE id=?");
        $stSnap->execute([$id]);
        $snapshotAntes = $stSnap->fetch() ?: null;

        $stmt = $pdo->prepare(
            "UPDATE `{$p}transacoes`
             SET descricao=?, valor=?, data=?, categoria_id=?, conta_id=?,
                 cartao_id=?, responsavel_id=?, terceiro_id=?, status=?, observacao=?, tags=?, data_vencimento=?, comprovante_path=?, atualizado_em=NOW()
             WHERE id=? AND tipo='despesa'"
        );
        $stmt->execute([$descricao, $valor, $data, $catId, $contaId, $cartaoId, $respId, $terceiroId, $status, $obs, $tags, $dataVenc, $comprovantePath, $id]);

        // Se a foto foi trocada ou removida, apaga o arquivo antigo do disco.
        $comprovanteAntigo = $snapshotAntes['comprovante_path'] ?? null;
        if ($comprovanteAntigo && $comprovanteAntigo !== $comprovantePath) {
            @unlink(__DIR__ . '/../../' . $comprovanteAntigo);
        }

        // Snapshot depois + registro de auditoria
        $stSnap2 = $pdo->prepare("SELECT * FROM `{$p}transacoes` WHERE id=?");
        $stSnap2->execute([$id]);
        registrarAudit($pdo, $p, 'transacoes', $id, 'update', $snapshotAntes, $stSnap2->fetch() ?: null);

        // Ajusta limite_usado: reverte efeito antigo, aplica novo
        if ($old) {
            $oldCartao = (int)($old['cartao_id'] ?? 0) ?: null;
            $oldValor  = (float)$old['valor'];
            if ($oldCartao !== $cartaoId) {
                if ($oldCartao)
                    $pdo->prepare("UPDATE `{$p}cartoes` SET limite_usado = GREATEST(0, limite_usado - ?), atualizado_em=NOW() WHERE id=?")->execute([$oldValor, $oldCartao]);
                if ($cartaoId)
                    $pdo->prepare("UPDATE `{$p}cartoes` SET limite_usado = limite_usado + ?, atualizado_em=NOW() WHERE id=?")->execute([$valor, $cartaoId]);
            } elseif ($cartaoId && abs($oldValor - $valor) > 0.001) {
                $pdo->prepare("UPDATE `{$p}cartoes` SET limite_usado = GREATEST(0, limite_usado + ?), atualizado_em=NOW() WHERE id=?")->execute([$valor - $oldValor, $cartaoId]);
            }
        }

        respostaJSON(['success' => true, 'msg' => 'Despesa atualizada.']);
    }

    // ── Divisão entre responsáveis ────────────────────────────
    $divisao     = $d['divisao'] ?? [];   // array de responsavel_id (0 = sem resp.)
    $isDivisao   = is_array($divisao) && count($divisao) >= 2;

    $pdo->beginTransaction();
    try {
        if ($isDivisao) {
            // Divisão: cria uma transação por responsável, cada uma com valor/N
            // (Calculos::dividirValor cuida do ajuste de centavos na primeira parte)
            $n      = count($divisao);
            $partes = Calculos::dividirValor($valor, $n);

            // Fatura do cartão para divisão (todas as sub-transações têm a mesma data)
            $fatIdDiv = null;
            $dvDiv    = $dataVenc; // data_vencimento para divisão
            if ($cartaoId) {
                $ccDiv = $pdo->prepare("SELECT dia_fechamento, dia_vencimento FROM `{$p}cartoes` WHERE id=?");
                $ccDiv->execute([$cartaoId]);
                $ccDivRow = $ccDiv->fetch();
                $dFDiv = (int)($ccDivRow['dia_fechamento'] ?? 0);
                $dVDiv = min((int)($ccDivRow['dia_vencimento'] ?? 10), 28);

                if ($mesFatura && $anoFatura) {
                    $fatIdDiv = $upsertFatura($cartaoId, $mesFatura, $anoFatura);
                    $dvDiv = sprintf('%04d-%02d-%02d', $anoFatura, $mesFatura, $dVDiv);
                } elseif ($dFDiv) {
                    [$mFD, $aFD] = $calcMesFatura($data, $dFDiv);
                    $fatIdDiv = $upsertFatura($cartaoId, $mFD, $aFD);
                    $dvDiv = sprintf('%04d-%02d-%02d', $aFD, $mFD, $dVDiv);
                }
            }

            $stmtDiv = $pdo->prepare(
                "INSERT INTO `{$p}transacoes`
                 (tipo, descricao, valor, data, categoria_id, conta_id, cartao_id,
                  responsavel_id, status, observacao, tags, grupo_id, fatura_id, data_vencimento, comprovante_path)
                 VALUES ('despesa',?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );

            $grupoId  = null;

            foreach ($divisao as $idx => $rIdDiv) {
                $rIdDiv  = ((int)$rIdDiv) ?: null;
                $vlAtual = $partes[$idx];
                $obsAtual = $obs ? $obs . " [divisão " . ($idx+1) . "/$n]" : "Divisão " . ($idx+1) . "/$n";

                $stmtDiv->execute([
                    $descricao, $vlAtual, $data, $catId, $contaId, $cartaoId,
                    $rIdDiv, $status, $obsAtual, $tags, $grupoId, $fatIdDiv, $dvDiv, $comprovantePath,
                ]);

                if ($grupoId === null) {
                    $grupoId = (int)$pdo->lastInsertId();
                    // Retroativamente define grupo_id = próprio id na 1ª linha
                    $pdo->prepare("UPDATE `{$p}transacoes` SET grupo_id=? WHERE id=?")->execute([$grupoId, $grupoId]);
                }
            }

            if ($contaId && $status === 'pago') {
                $pdo->prepare("UPDATE `{$p}contas` SET saldo_atual = saldo_atual - ? WHERE id=?")
                    ->execute([$valor, $contaId]);
            }
            if ($cartaoId) {
                $pdo->prepare("UPDATE `{$p}cartoes` SET limite_usado = limite_usado + ?, atualizado_em=NOW() WHERE id=?")
                    ->execute([$valor, $cartaoId]);
            }

            $pdo->commit();
            respostaJSON(['success' => true, 'msg' => "$n despesas criadas (divisão entre responsáveis).", 'grupo_id' => $grupoId]);
        }

        // ── Criação normal (sem divisão) ──────────────────────
        // Pré-busca dia de fechamento e vencimento para cálculo de fatura por parcela
        $cardDiaFech = 0;
        $cardDiaVenc = 10;
        if ($cartaoId) {
            $ccS = $pdo->prepare("SELECT dia_fechamento, dia_vencimento FROM `{$p}cartoes` WHERE id=?");
            $ccS->execute([$cartaoId]);
            $ccRow = $ccS->fetch();
            $cardDiaFech = (int)($ccRow['dia_fechamento'] ?? 0);
            $cardDiaVenc = min((int)($ccRow['dia_vencimento'] ?? 10), 28);
        }

        $sql  = "INSERT INTO `{$p}transacoes`
                 (tipo, descricao, valor, data, categoria_id, conta_id, cartao_id,
                  responsavel_id, terceiro_id, status, observacao, tags, parcela_atual, parcela_total, fatura_id, data_vencimento, comprovante_path)
                 VALUES ('despesa',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $pdo->prepare($sql);

        for ($i = 1; $i <= $parcelas; $i++) {
            $desc = $parcelas > 1 ? "$descricao ($i/$parcelas)" : $descricao;
            $dt   = $i === 1 ? $data : date('Y-m-d', strtotime("$data +" . ($i - 1) . ' months'));
            $st   = ($i === 1) ? $status : 'pendente';

            // Fatura por parcela: 1ª usa o valor informado pelo usuário; demais calculado auto
            $fatIdParc = null;
            $dvParc    = null;
            if ($cartaoId) {
                if ($i === 1 && $mesFatura && $anoFatura) {
                    $fatIdParc = $upsertFatura($cartaoId, $mesFatura, $anoFatura);
                    $dvParc    = sprintf('%04d-%02d-%02d', $anoFatura, $mesFatura, $cardDiaVenc);
                } elseif ($cardDiaFech) {
                    [$mFP, $aFP] = $calcMesFatura($dt, $cardDiaFech);
                    $fatIdParc   = $upsertFatura($cartaoId, $mFP, $aFP);
                    $dvParc      = sprintf('%04d-%02d-%02d', $aFP, $mFP, $cardDiaVenc);
                }
            } elseif ($i === 1) {
                // Para despesas comuns, usa o valor fornecido pelo usuário somente na 1ª parcela
                $dvParc = $dataVenc;
            }

            $stmt->execute([$desc, $valor, $dt, $catId, $contaId, $cartaoId,
                            $respId, $terceiroId, $st, $obs, $tags,
                            $parcelas > 1 ? $i : null,
                            $parcelas > 1 ? $parcelas : null,
                            $fatIdParc, $dvParc, $comprovantePath]);
        }

        if ($contaId && $status === 'pago') {
            $pdo->prepare("UPDATE `{$p}contas` SET saldo_atual = saldo_atual - ? WHERE id=?")
                ->execute([$valor, $contaId]);
        }
        if ($cartaoId) {
            $pdo->prepare("UPDATE `{$p}cartoes` SET limite_usado = limite_usado + ?, atualizado_em=NOW() WHERE id=?")
                ->execute([$valor * $parcelas, $cartaoId]);
        }

        $pdo->commit();
        $ultimoId = (int)$pdo->lastInsertId();
        if ($ultimoId) registrarAudit($pdo, $p, 'transacoes', $ultimoId, 'insert', null, ['descricao' => $descricao, 'valor' => $valor, 'parcelas' => $parcelas]);
        $msg = $parcelas > 1 ? "$parcelas parcelas criadas com sucesso." : 'Despesa criada com sucesso.';
        respostaJSON(['success' => true, 'msg' => $msg]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        erroInterno($e, 'Erro ao salvar.');
    }
}

// ── DELETE: excluir ───────────────────────────────────────────
if ($metodo === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);

    $tx = $pdo->prepare("SELECT * FROM `{$p}transacoes` WHERE id=? AND tipo='despesa'");
    $tx->execute([$id]);
    $row = $tx->fetch();
    if (!$row) respostaJSON(['success' => false, 'erro' => 'Despesa não encontrada.']);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM `{$p}transacoes` WHERE id=? AND tipo='despesa'")->execute([$id]);
        registrarAudit($pdo, $p, 'transacoes', $id, 'delete', $row ?: null, null);

        if (!empty($row['comprovante_path'])) {
            @unlink(__DIR__ . '/../../' . $row['comprovante_path']);
        }

        if ($row['conta_id'] && $row['status'] === 'pago') {
            $pdo->prepare("UPDATE `{$p}contas` SET saldo_atual = saldo_atual + ? WHERE id=?")
                ->execute([$row['valor'], $row['conta_id']]);
        }

        if ($row['cartao_id'] && $row['status'] !== 'pago') {
            $pdo->prepare(
                "UPDATE `{$p}cartoes` SET limite_usado = GREATEST(0, limite_usado - ?), atualizado_em=NOW() WHERE id=?"
            )->execute([$row['valor'], $row['cartao_id']]);
        }

        $pdo->commit();
        respostaJSON(['success' => true, 'msg' => 'Despesa excluída.']);
    } catch (Throwable $e) {
        $pdo->rollBack();
        respostaJSON(['success' => false, 'erro' => 'Erro ao excluir.']);
    }
}

respostaJSON(['success' => false, 'erro' => 'Método não permitido.']);
