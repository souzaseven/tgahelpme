<?php
// ============================================================
// relatorios.php — Todas as visões da tela de Relatórios & BI,
// uma ação por aba. É o maior arquivo do backend porque cada
// relatório tem sua própria agregação (não dá pra reaproveitar
// muito entre eles sem forçar a mão).
//
// GET ?acao=resumo           — KPIs do mês atual (padrão)
// GET ?acao=fluxo            — fluxo de caixa detalhado, dia a dia
// GET ?acao=categorias       — gastos agrupados por categoria
// GET ?acao=curva_abc        — curva ABC/Pareto: categorias por % cumulativo de gasto
// GET ?acao=patrimonio       — patrimônio consolidado (contas + investimentos)
// GET ?acao=anual            — resumo em tabela, 12 meses
// GET ?acao=ranking          — ranking de gasto por pessoa (responsável/terceiro)
// GET ?acao=previsao         — previsto vs. realizado do mês corrente
// GET ?acao=por_responsavel  — balanço detalhado por pessoa
// GET ?acao=ir               — relatório para declaração de Imposto de Renda
// ============================================================
require_once __DIR__ . '/../../backend/banco/conexao.php';
require_once __DIR__ . '/../../src/Financeiro/Calculos.php';

use FinanceOS\Financeiro\Calculos;

$p    = TABLE_PREFIX;
$acao = trim($_GET['acao'] ?? 'resumo');

// ── Resumo do mês atual ───────────────────────────────────────
if ($acao === 'resumo') {
    $mes    = (int)($_GET['mes'] ?? date('m'));
    $ano    = (int)($_GET['ano'] ?? date('Y'));
    $mesAnt = $mes === 1 ? 12 : $mes - 1;
    $anoAnt = $mes === 1 ? $ano - 1 : $ano;

    $kpi = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN tipo='receita' AND status='pago' THEN valor END),0) receitas,
            COALESCE(SUM(CASE WHEN tipo='despesa' AND status='pago' THEN valor END),0) despesas,
            COALESCE(SUM(CASE WHEN tipo='receita' AND status != 'cancelado' THEN valor END),0) receitas_total,
            COALESCE(SUM(CASE WHEN tipo='despesa' AND status != 'cancelado' THEN valor END),0) despesas_total,
            COALESCE(SUM(CASE WHEN tipo='despesa' AND status='pendente' THEN valor END),0) a_pagar
         FROM `{$p}transacoes`
         WHERE MONTH(data)=? AND YEAR(data)=?"
    );
    $kpi->execute([$mes, $ano]);
    $atual = $kpi->fetch();

    $kpi->execute([$mesAnt, $anoAnt]);
    $ant = $kpi->fetch();

    // Patrimônio snapshot
    $saldoContas = (float) $pdo->query(
        "SELECT COALESCE(SUM(saldo_atual),0) FROM `{$p}contas` WHERE ativo=1 AND incluir_total=1"
    )->fetchColumn();
    $valInvest = (float) $pdo->query(
        "SELECT COALESCE(SUM(valor_atual),0) FROM `{$p}investimentos` WHERE ativo=1"
    )->fetchColumn();
    $totalDivida = (float) $pdo->query(
        "SELECT COALESCE(SUM(saldo_devedor),0) FROM `{$p}emprestimos` WHERE status NOT IN ('quitado')"
    )->fetchColumn();

    // Fluxo dos últimos 6 meses (1 query)
    $fStmt = $pdo->prepare(
        "SELECT YEAR(data) ano, MONTH(data) mes,
                COALESCE(SUM(CASE WHEN tipo='receita' AND status='pago' THEN valor END),0) receitas,
                COALESCE(SUM(CASE WHEN tipo='despesa' AND status='pago' THEN valor END),0) despesas
         FROM `{$p}transacoes`
         WHERE data >= DATE_FORMAT(DATE_SUB(CONCAT(?,'-',LPAD(?,2,0),'-01'), INTERVAL 5 MONTH),'%Y-%m-01')
           AND data <= LAST_DAY(CONCAT(?,'-',LPAD(?,2,0),'-01'))
         GROUP BY YEAR(data), MONTH(data)"
    );
    $fStmt->execute([$ano, $mes, $ano, $mes]);
    $fRows = $fStmt->fetchAll();
    $fIdx  = [];
    foreach ($fRows as $r) $fIdx["{$r['ano']}-{$r['mes']}"] = $r;

    $fluxo = [];
    for ($i = 5; $i >= 0; $i--) {
        $dt = new DateTime(sprintf('%04d-%02d-01', $ano, $mes));
        $dt->modify("-$i months");
        $m = (int)$dt->format('m'); $y = (int)$dt->format('Y');
        $r = $fIdx["$y-$m"] ?? ['receitas' => 0, 'despesas' => 0];
        $fluxo[] = [
            'label'    => $dt->format('M/y'),
            'receitas' => (float)$r['receitas'],
            'despesas' => (float)$r['despesas'],
            'saldo'    => (float)$r['receitas'] - (float)$r['despesas'],
        ];
    }

    // Top 5 categorias de despesa do mês
    $topCat = $pdo->prepare(
        "SELECT COALESCE(c.nome,'Sem categoria') nome, COALESCE(c.cor,'#64748b') cor,
                COALESCE(SUM(t.valor),0) total
         FROM `{$p}transacoes` t
         LEFT JOIN `{$p}categorias` c ON c.id = t.categoria_id
         WHERE t.tipo='despesa' AND t.status='pago'
           AND MONTH(t.data)=? AND YEAR(t.data)=?
         GROUP BY t.categoria_id ORDER BY total DESC LIMIT 6"
    );
    $topCat->execute([$mes, $ano]);

    respostaJSON([
        'success'        => true,
        'atual'          => $atual,
        'anterior'       => $ant,
        'fluxo'          => $fluxo,
        'top_categorias' => $topCat->fetchAll(),
        'patrimonio'     => [
            'contas'        => $saldoContas,
            'investimentos' => $valInvest,
            'dividas'       => $totalDivida,
            'liquido'       => $saldoContas + $valInvest - $totalDivida,
        ],
    ]);
}

// ── Fluxo de caixa detalhado ──────────────────────────────────
if ($acao === 'fluxo') {
    $meses  = max(3, min(24, (int)($_GET['meses']   ?? 12)));
    $ateMes = (int)($_GET['ate_mes'] ?? date('m'));
    $ateAno = (int)($_GET['ate_ano'] ?? date('Y'));

    // Monta a data de referência (último dia do mês de referência)
    $dtFim = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $ateAno, $ateMes));
    $dtFim->modify('last day of this month');
    $dtIni = clone $dtFim;
    $dtIni->modify('first day of this month');
    $dtIni->modify('-' . ($meses - 1) . ' months');

    $stmt = $pdo->prepare(
        "SELECT YEAR(data) ano, MONTH(data) mes,
                COALESCE(SUM(CASE WHEN tipo='receita' AND status='pago' THEN valor END),0) receitas,
                COALESCE(SUM(CASE WHEN tipo='despesa' AND status='pago' THEN valor END),0) despesas
         FROM `{$p}transacoes`
         WHERE data >= ? AND data <= ?
         GROUP BY YEAR(data), MONTH(data)"
    );
    $stmt->execute([$dtIni->format('Y-m-d'), $dtFim->format('Y-m-d')]);
    $idx = [];
    foreach ($stmt->fetchAll() as $r) $idx["{$r['ano']}-{$r['mes']}"] = $r;

    $rows    = [];
    $cursor  = clone $dtIni;
    for ($i = 0; $i < $meses; $i++) {
        $m = (int)$cursor->format('m');
        $y = (int)$cursor->format('Y');
        $r = $idx["$y-$m"] ?? ['receitas' => 0, 'despesas' => 0];
        $rows[] = [
            'label'    => $cursor->format('M/y'),
            'mes'      => $m, 'ano' => $y,
            'receitas' => (float)$r['receitas'],
            'despesas' => (float)$r['despesas'],
            'saldo'    => (float)$r['receitas'] - (float)$r['despesas'],
        ];
        $cursor->modify('+1 month');
    }

    $totRec = array_sum(array_column($rows, 'receitas'));
    $totDes = array_sum(array_column($rows, 'despesas'));

    respostaJSON([
        'success' => true,
        'dados'   => $rows,
        'totais'  => ['receitas' => $totRec, 'despesas' => $totDes, 'saldo' => $totRec - $totDes],
    ]);
}

// ── Gastos por categoria ──────────────────────────────────────
if ($acao === 'categorias') {
    $mes  = (int)($_GET['mes']  ?? date('m'));
    $ano  = (int)($_GET['ano']  ?? date('Y'));
    $tipo = in_array($_GET['tipo'] ?? '', ['receita','despesa']) ? $_GET['tipo'] : 'despesa';

    $stmt = $pdo->prepare(
        "SELECT COALESCE(c.nome,'Sem categoria') nome,
                COALESCE(c.cor,'#64748b') cor,
                COALESCE(c.icone,'tag') icone,
                COALESCE(SUM(t.valor),0) total, COUNT(*) qtd
         FROM `{$p}transacoes` t
         LEFT JOIN `{$p}categorias` c ON c.id = t.categoria_id
         WHERE t.tipo=? AND t.status='pago'
           AND MONTH(t.data)=? AND YEAR(t.data)=?
         GROUP BY t.categoria_id
         ORDER BY total DESC"
    );
    $stmt->execute([$tipo, $mes, $ano]);
    $cats = $stmt->fetchAll();

    $totalGeral = array_sum(array_column($cats, 'total'));
    foreach ($cats as &$c) {
        $c['pct'] = $totalGeral > 0 ? round((float)$c['total'] / $totalGeral * 100, 1) : 0;
    }

    respostaJSON(['success' => true, 'dados' => $cats, 'total' => $totalGeral]);
}

// ── Curva ABC / Pareto: categorias por % cumulativo de gasto ──
// Regra clássica 80/15/5: classe A = categorias que, somadas em ordem
// decrescente de gasto, respondem pelos primeiros 80% do total; B = até
// 95%; C = o restante. Ajuda a enxergar em quais categorias concentrar
// esforço de corte de gasto (poucas categorias A costumam explicar a
// maior parte do total — princípio de Pareto).
if ($acao === 'curva_abc') {
    $mes  = (int)($_GET['mes']  ?? date('m'));
    $ano  = (int)($_GET['ano']  ?? date('Y'));
    $tipo = in_array($_GET['tipo'] ?? '', ['receita','despesa']) ? $_GET['tipo'] : 'despesa';

    $stmt = $pdo->prepare(
        "SELECT COALESCE(c.nome,'Sem categoria') nome,
                COALESCE(c.cor,'#64748b') cor,
                COALESCE(c.icone,'tag') icone,
                COALESCE(SUM(t.valor),0) total, COUNT(*) qtd
         FROM `{$p}transacoes` t
         LEFT JOIN `{$p}categorias` c ON c.id = t.categoria_id
         WHERE t.tipo=? AND t.status='pago'
           AND MONTH(t.data)=? AND YEAR(t.data)=?
         GROUP BY t.categoria_id
         ORDER BY total DESC"
    );
    $stmt->execute([$tipo, $mes, $ano]);
    $cats = $stmt->fetchAll();

    $totalGeral = array_sum(array_column($cats, 'total'));
    $acumulado  = 0.0;
    foreach ($cats as &$c) {
        $total          = (float)$c['total'];
        $acumulado     += $total;
        $c['pct']       = $totalGeral > 0 ? round($total / $totalGeral * 100, 1) : 0;
        $c['pct_acum']  = $totalGeral > 0 ? round($acumulado / $totalGeral * 100, 1) : 0;
        $c['classe']    = $c['pct_acum'] <= 80 ? 'A' : ($c['pct_acum'] <= 95 ? 'B' : 'C');
    }
    unset($c);

    // Primeira categoria sempre entra na classe A, mesmo que sozinha já
    // ultrapasse 80% (evita o caso "1 categoria = 100% do gasto = classe C").
    if (!empty($cats)) $cats[0]['classe'] = 'A';

    $porClasse = ['A' => 0, 'B' => 0, 'C' => 0];
    foreach ($cats as $c) $porClasse[$c['classe']]++;

    respostaJSON([
        'success'     => true,
        'dados'       => $cats,
        'total'       => $totalGeral,
        'qtd_por_classe' => $porClasse,
    ]);
}

// ── Patrimônio consolidado ────────────────────────────────────
if ($acao === 'patrimonio') {
    $col = $pdo->query("SHOW COLUMNS FROM `{$p}contas` LIKE 'titular'")->fetch();

    $titularSql = $col ? 'titular' : "'' titular";
    $contas  = $pdo->query(
        "SELECT nome, tipo, $titularSql, saldo_atual, cor, incluir_total FROM `{$p}contas` WHERE ativo=1 ORDER BY $titularSql, nome"
    )->fetchAll();
    $invs = $pdo->query(
        "SELECT nome, tipo, valor_aplicado, valor_atual FROM `{$p}investimentos` WHERE ativo=1 ORDER BY valor_atual DESC"
    )->fetchAll();
    $emps = $pdo->query(
        "SELECT nome, tipo, saldo_devedor, valor_parcela FROM `{$p}emprestimos` WHERE status NOT IN ('quitado')"
    )->fetchAll();
    $metas = $pdo->query(
        "SELECT nome, valor_alvo, valor_atual, cor FROM `{$p}metas` WHERE status='ativa' ORDER BY prioridade"
    )->fetchAll();

    // Totais por titular
    $porTitular = [];
    foreach ($contas as $c) {
        if (!$c['incluir_total']) continue;
        $t = trim($c['titular'] ?? '') ?: 'Sem titular';
        $porTitular[$t] = ($porTitular[$t] ?? 0) + (float)$c['saldo_atual'];
    }

    // Por tipo de conta
    $porTipo = [];
    foreach ($contas as $c) {
        $porTipo[$c['tipo']] = ($porTipo[$c['tipo']] ?? 0) + (float)$c['saldo_atual'];
    }

    // Por tipo de investimento
    $invPorTipo = [];
    foreach ($invs as $i) {
        $invPorTipo[$i['tipo']] = ($invPorTipo[$i['tipo']] ?? 0) + (float)$i['valor_atual'];
    }

    $totContas = array_sum(array_map(fn($c) => $c['incluir_total'] ? (float)$c['saldo_atual'] : 0, $contas));
    $totInvest = array_sum(array_column($invs, 'valor_atual'));
    $totDivida = array_sum(array_column($emps, 'saldo_devedor'));

    respostaJSON([
        'success'        => true,
        'contas'         => $contas,
        'investimentos'  => $invs,
        'emprestimos'    => $emps,
        'metas'          => $metas,
        'por_titular'    => $porTitular,
        'por_tipo_conta' => $porTipo,
        'inv_por_tipo'   => $invPorTipo,
        'totais'         => [
            'contas'        => $totContas,
            'investimentos' => $totInvest,
            'dividas'       => $totDivida,
            'liquido'       => $totContas + $totInvest - $totDivida,
        ],
    ]);
}

// ── Resumo anual (tabela 12 meses) ────────────────────────────
if ($acao === 'anual') {
    $ano = (int)($_GET['ano'] ?? date('Y'));

    $stmt = $pdo->prepare(
        "SELECT MONTH(data) mes,
                COALESCE(SUM(CASE WHEN tipo='receita' AND status='pago' THEN valor END),0) receitas,
                COALESCE(SUM(CASE WHEN tipo='despesa' AND status='pago' THEN valor END),0) despesas
         FROM `{$p}transacoes`
         WHERE YEAR(data) = ?
         GROUP BY MONTH(data)"
    );
    $stmt->execute([$ano]);
    $idx = [];
    foreach ($stmt->fetchAll() as $r) $idx[(int)$r['mes']] = $r;

    $mesesPtBr = ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    $rows = []; $acumulado = 0;
    for ($m = 1; $m <= 12; $m++) {
        $r     = $idx[$m] ?? ['receitas' => 0, 'despesas' => 0];
        $saldo = (float)$r['receitas'] - (float)$r['despesas'];
        $acumulado += $saldo;
        $rows[] = [
            'mes'        => $mesesPtBr[$m],
            'numero'     => $m,
            'receitas'   => (float)$r['receitas'],
            'despesas'   => (float)$r['despesas'],
            'saldo'      => $saldo,
            'acumulado'  => $acumulado,
        ];
    }

    $totRec = array_sum(array_column($rows, 'receitas'));
    $totDes = array_sum(array_column($rows, 'despesas'));

    respostaJSON([
        'success' => true,
        'dados'   => $rows,
        'ano'     => $ano,
        'totais'  => ['receitas' => $totRec, 'despesas' => $totDes, 'saldo' => $totRec - $totDes],
    ]);
}

// ── Ranking por pessoa (responsável + terceiros) ─────────────
if ($acao === 'ranking') {
    $mes = (int)($_GET['mes'] ?? 0);
    $ano = (int)($_GET['ano'] ?? date('Y'));

    $periodoWhere = $mes > 0
        ? "AND MONTH(t.data) = $mes AND YEAR(t.data) = $ano"
        : "AND YEAR(t.data) = $ano";

    // Ranking de despesas — responsáveis
    $rankingDes = $pdo->query(
        "SELECT r.id, r.nome, r.cor, r.icone, 'responsavel' tipo,
                COALESCE(SUM(t.valor), 0) total, COUNT(t.id) qtd
         FROM `{$p}responsaveis` r
         JOIN `{$p}transacoes` t ON t.responsavel_id = r.id
             AND t.tipo = 'despesa' AND t.status != 'cancelado' $periodoWhere
         WHERE r.ativo = 1
         GROUP BY r.id, r.nome, r.cor, r.icone ORDER BY total DESC LIMIT 10"
    )->fetchAll();

    // Ranking de despesas — terceiros
    $rankingDesTcr = $pdo->query(
        "SELECT r.id, r.nome, r.cor, r.icone, 'terceiro' tipo,
                COALESCE(SUM(t.valor), 0) total, COUNT(t.id) qtd
         FROM `{$p}terceiros` r
         JOIN `{$p}transacoes` t ON t.terceiro_id = r.id
             AND t.tipo = 'despesa' AND t.status != 'cancelado' $periodoWhere
         WHERE r.ativo = 1
         GROUP BY r.id, r.nome, r.cor, r.icone ORDER BY total DESC LIMIT 10"
    )->fetchAll();

    // Merge e reordena por total
    $rankingDesMerge = array_merge($rankingDes, $rankingDesTcr);
    usort($rankingDesMerge, fn($a, $b) => (float)$b['total'] <=> (float)$a['total']);
    $rankingDesMerge = array_slice($rankingDesMerge, 0, 10);

    // Ranking de receitas — responsáveis
    $rankingRec = $pdo->query(
        "SELECT r.id, r.nome, r.cor, r.icone, 'responsavel' tipo,
                COALESCE(SUM(t.valor), 0) total, COUNT(t.id) qtd
         FROM `{$p}responsaveis` r
         JOIN `{$p}transacoes` t ON t.responsavel_id = r.id
             AND t.tipo = 'receita' AND t.status != 'cancelado' $periodoWhere
         WHERE r.ativo = 1
         GROUP BY r.id, r.nome, r.cor, r.icone ORDER BY total DESC LIMIT 10"
    )->fetchAll();

    // Ranking de receitas — terceiros
    $rankingRecTcr = $pdo->query(
        "SELECT r.id, r.nome, r.cor, r.icone, 'terceiro' tipo,
                COALESCE(SUM(t.valor), 0) total, COUNT(t.id) qtd
         FROM `{$p}terceiros` r
         JOIN `{$p}transacoes` t ON t.terceiro_id = r.id
             AND t.tipo = 'receita' AND t.status != 'cancelado' $periodoWhere
         WHERE r.ativo = 1
         GROUP BY r.id, r.nome, r.cor, r.icone ORDER BY total DESC LIMIT 10"
    )->fetchAll();

    $rankingRecMerge = array_merge($rankingRec, $rankingRecTcr);
    usort($rankingRecMerge, fn($a, $b) => (float)$b['total'] <=> (float)$a['total']);
    $rankingRecMerge = array_slice($rankingRecMerge, 0, 10);

    // Categorias de despesa por responsável
    $catsRespRaw = $pdo->query(
        "SELECT r.id resp_id, r.nome resp_nome, r.cor resp_cor, r.icone resp_icone,
                'responsavel' resp_tipo,
                COALESCE(c.nome, 'Sem categoria') cat_nome,
                COALESCE(c.cor, '#64748b') cor,
                COALESCE(SUM(t.valor), 0) total
         FROM `{$p}responsaveis` r
         JOIN `{$p}transacoes` t ON t.responsavel_id = r.id
             AND t.tipo = 'despesa' AND t.status != 'cancelado' $periodoWhere
         LEFT JOIN `{$p}categorias` c ON c.id = t.categoria_id
         WHERE r.ativo = 1
         GROUP BY r.id, r.nome, r.cor, r.icone, t.categoria_id, c.nome, c.cor
         ORDER BY r.id, total DESC"
    )->fetchAll();

    // Categorias de despesa por terceiro
    $catsTcrRaw = $pdo->query(
        "SELECT r.id resp_id, r.nome resp_nome, r.cor resp_cor, r.icone resp_icone,
                'terceiro' resp_tipo,
                COALESCE(c.nome, 'Sem categoria') cat_nome,
                COALESCE(c.cor, '#64748b') cor,
                COALESCE(SUM(t.valor), 0) total
         FROM `{$p}terceiros` r
         JOIN `{$p}transacoes` t ON t.terceiro_id = r.id
             AND t.tipo = 'despesa' AND t.status != 'cancelado' $periodoWhere
         LEFT JOIN `{$p}categorias` c ON c.id = t.categoria_id
         WHERE r.ativo = 1
         GROUP BY r.id, r.nome, r.cor, r.icone, t.categoria_id, c.nome, c.cor
         ORDER BY r.id, total DESC"
    )->fetchAll();

    // Agrupa categorias (responsáveis e terceiros juntos, chave única por tipo+id)
    $catsPorPessoa = [];
    foreach (array_merge($catsRespRaw, $catsTcrRaw) as $row) {
        $chave = $row['resp_tipo'] . '_' . $row['resp_id'];
        if (!isset($catsPorPessoa[$chave])) {
            $catsPorPessoa[$chave] = [
                'resp_id'    => $row['resp_id'],
                'resp_nome'  => $row['resp_nome'],
                'resp_cor'   => $row['resp_cor'],
                'resp_icone' => $row['resp_icone'],
                'resp_tipo'  => $row['resp_tipo'],
                'total_resp' => 0,
                'categorias' => [],
            ];
        }
        if (count($catsPorPessoa[$chave]['categorias']) < 5) {
            $catsPorPessoa[$chave]['categorias'][] = [
                'cat_nome' => $row['cat_nome'],
                'cor'      => $row['cor'],
                'total'    => (float)$row['total'],
            ];
        }
        $catsPorPessoa[$chave]['total_resp'] += (float)$row['total'];
    }
    usort($catsPorPessoa, fn($a, $b) => $b['total_resp'] <=> $a['total_resp']);

    respostaJSON([
        'success'       => true,
        'despesas'      => $rankingDesMerge,
        'receitas'      => $rankingRecMerge,
        'cats_por_resp' => array_values($catsPorPessoa),
        'mes'           => $mes,
        'ano'           => $ano,
    ]);
}

// ── Previsão do mês ───────────────────────────────────────────
if ($acao === 'previsao') {
    $mes = (int)($_GET['mes'] ?? date('m'));
    $ano = (int)($_GET['ano'] ?? date('Y'));

    // Busca todas as recorrências ativas com joins
    $recs = $pdo->query(
        "SELECT r.*,
                c.nome  cat_nome,   c.cor   cat_cor,
                ct.nome conta_nome, cc.nome cartao_nome,
                resp.nome resp_nome, resp.cor resp_cor
         FROM `{$p}recorrencias` r
         LEFT JOIN `{$p}categorias`   c    ON c.id    = r.categoria_id
         LEFT JOIN `{$p}contas`       ct   ON ct.id   = r.conta_id
         LEFT JOIN `{$p}cartoes`      cc   ON cc.id   = r.cartao_id
         LEFT JOIN `{$p}responsaveis` resp ON resp.id = r.responsavel_id
         WHERE r.ativo = 1
         ORDER BY r.dia_vencimento, r.tipo DESC, r.descricao"
    )->fetchAll();

    $dtAlvo    = new DateTime(sprintf('%04d-%02d-01', $ano, $mes));
    $diasNoMes = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
    $previsao  = [];

    // Filtra antes de buscar transações — evita 1 query por recorrência
    // (N+1): busca de uma vez só as transações de todas as recorrências
    // que realmente caem neste mês.
    $recsDoMes = array_values(array_filter(
        $recs,
        fn($rec) => Calculos::recorrenciaDeveGerarNoMes($rec, $mes, $ano)
    ));

    $txPorRecorrencia = [];
    if ($recsDoMes) {
        $ids = array_map(fn($rec) => (int)$rec['id'], $recsDoMes);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $stmtTx = $pdo->prepare(
            "SELECT id, recorrencia_id, status, data, valor
             FROM `{$p}transacoes`
             WHERE recorrencia_id IN ($ph) AND MONTH(data)=? AND YEAR(data)=?
             ORDER BY recorrencia_id, id DESC"
        );
        $stmtTx->execute([...$ids, $mes, $ano]);
        foreach ($stmtTx->fetchAll() as $row) {
            $rid = (int)$row['recorrencia_id'];
            // Ordenado por id DESC — a primeira linha de cada recorrência é a mais recente
            if (!isset($txPorRecorrencia[$rid])) $txPorRecorrencia[$rid] = $row;
        }
    }

    foreach ($recsDoMes as $rec) {
        $tx = $txPorRecorrencia[(int)$rec['id']] ?? null;

        $dia = min((int)$rec['dia_vencimento'], $diasNoMes);

        $previsao[] = [
            'rec_id'        => (int)$rec['id'],
            'descricao'     => $rec['descricao'],
            'tipo'          => $rec['tipo'],
            'valor'         => (float)$rec['valor'],
            'frequencia'    => $rec['frequencia'],
            'dia_venc'      => $dia,
            'data_prevista' => sprintf('%04d-%02d-%02d', $ano, $mes, $dia),
            'cat_nome'      => $rec['cat_nome'],
            'cat_cor'       => $rec['cat_cor'],
            'conta_nome'    => $rec['conta_nome'] ?? $rec['cartao_nome'],
            'resp_nome'     => $rec['resp_nome'],
            'resp_cor'      => $rec['resp_cor'],
            'tx_id'         => $tx ? (int)$tx['id']    : null,
            'tx_status'     => $tx ? $tx['status']     : 'nao_gerado',
            'tx_data'       => $tx ? $tx['data']       : null,
        ];
    }

    // KPIs agregados
    $soma = fn($tipo, $status) => array_sum(array_map(
        fn($r) => $r['tipo'] === $tipo && $r['tx_status'] === $status ? $r['valor'] : 0,
        $previsao
    ));
    $total = fn($tipo) => array_sum(array_map(
        fn($r) => $r['tipo'] === $tipo ? $r['valor'] : 0, $previsao
    ));

    $kpi = [
        'prev_receitas'   => $total('receita'),
        'prev_despesas'   => $total('despesa'),
        'pago_receitas'   => $soma('receita', 'pago'),
        'pago_despesas'   => $soma('despesa', 'pago'),
        'pend_receitas'   => $soma('receita', 'pendente'),
        'pend_despesas'   => $soma('despesa', 'pendente'),
        'nao_rec'         => $soma('receita', 'nao_gerado'),
        'nao_des'         => $soma('despesa', 'nao_gerado'),
    ];
    $kpi['saldo_previsto']   = $kpi['prev_receitas']  - $kpi['prev_despesas'];
    $kpi['saldo_realizado']  = $kpi['pago_receitas']  - $kpi['pago_despesas'];
    $kpi['saldo_pendente']   = $kpi['pend_receitas']  - $kpi['pend_despesas'];
    $kpi['pct_realizado_des']= $kpi['prev_despesas'] > 0
        ? round(($kpi['pago_despesas'] / $kpi['prev_despesas']) * 100) : 0;
    $kpi['pct_realizado_rec']= $kpi['prev_receitas'] > 0
        ? round(($kpi['pago_receitas'] / $kpi['prev_receitas']) * 100) : 0;

    respostaJSON([
        'success'  => true,
        'previsao' => $previsao,
        'kpi'      => $kpi,
        'mes'      => $mes,
        'ano'      => $ano,
        'total'    => count($previsao),
    ]);
}

// ── Balanço por pessoa (responsável + terceiros) ─────────────
if ($acao === 'por_responsavel') {
    $mes = (int)($_GET['mes'] ?? 0);
    $ano = (int)($_GET['ano'] ?? date('Y'));

    $periodoWhere = $mes > 0
        ? "AND MONTH(t.data) = $mes AND YEAR(t.data) = $ano"
        : "AND YEAR(t.data) = $ano";

    $mesesPtBr = ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];

    // Balanço por responsável
    $resps = $pdo->query(
        "SELECT r.id, r.nome, r.cor, r.icone, 'responsavel' tipo,
                COALESCE(SUM(CASE WHEN t.tipo='receita' AND t.status!='cancelado' THEN t.valor END),0) receitas,
                COALESCE(SUM(CASE WHEN t.tipo='despesa' AND t.status!='cancelado' THEN t.valor END),0) despesas,
                COUNT(t.id) qtd
         FROM `{$p}responsaveis` r
         JOIN `{$p}transacoes` t ON t.responsavel_id = r.id $periodoWhere
         WHERE r.ativo = 1
         GROUP BY r.id, r.nome, r.cor, r.icone ORDER BY despesas DESC"
    )->fetchAll();

    // Balanço por terceiro
    $terceiros = $pdo->query(
        "SELECT r.id, r.nome, r.cor, r.icone, 'terceiro' tipo,
                COALESCE(SUM(CASE WHEN t.tipo='receita' AND t.status!='cancelado' THEN t.valor END),0) receitas,
                COALESCE(SUM(CASE WHEN t.tipo='despesa' AND t.status!='cancelado' THEN t.valor END),0) despesas,
                COUNT(t.id) qtd
         FROM `{$p}terceiros` r
         JOIN `{$p}transacoes` t ON t.terceiro_id = r.id $periodoWhere
         WHERE r.ativo = 1
         GROUP BY r.id, r.nome, r.cor, r.icone ORDER BY despesas DESC"
    )->fetchAll();

    // Série mensal
    if ($mes === 0) {
        $seriesR = [];
        foreach ($pdo->query(
            "SELECT t.responsavel_id, MONTH(t.data) m,
                    COALESCE(SUM(CASE WHEN t.tipo='receita' THEN t.valor END),0) rec,
                    COALESCE(SUM(CASE WHEN t.tipo='despesa' THEN t.valor END),0) des
             FROM `{$p}transacoes` t
             WHERE t.responsavel_id IS NOT NULL AND t.status!='cancelado' AND YEAR(t.data)=$ano
             GROUP BY t.responsavel_id, MONTH(t.data)"
        )->fetchAll() as $row) {
            $seriesR[$row['responsavel_id']][$row['m']] = ['rec' => (float)$row['rec'], 'des' => (float)$row['des']];
        }

        $seriesT = [];
        foreach ($pdo->query(
            "SELECT t.terceiro_id, MONTH(t.data) m,
                    COALESCE(SUM(CASE WHEN t.tipo='receita' THEN t.valor END),0) rec,
                    COALESCE(SUM(CASE WHEN t.tipo='despesa' THEN t.valor END),0) des
             FROM `{$p}transacoes` t
             WHERE t.terceiro_id IS NOT NULL AND t.status!='cancelado' AND YEAR(t.data)=$ano
             GROUP BY t.terceiro_id, MONTH(t.data)"
        )->fetchAll() as $row) {
            $seriesT[$row['terceiro_id']][$row['m']] = ['rec' => (float)$row['rec'], 'des' => (float)$row['des']];
        }

        foreach ($resps as &$r) {
            $r['mensal'] = [];
            for ($m = 1; $m <= 12; $m++) {
                $d = $seriesR[$r['id']][$m] ?? ['rec' => 0, 'des' => 0];
                $r['mensal'][] = ['label' => $mesesPtBr[$m], 'rec' => $d['rec'], 'des' => $d['des']];
            }
        }
        foreach ($terceiros as &$r) {
            $r['mensal'] = [];
            for ($m = 1; $m <= 12; $m++) {
                $d = $seriesT[$r['id']][$m] ?? ['rec' => 0, 'des' => 0];
                $r['mensal'][] = ['label' => $mesesPtBr[$m], 'rec' => $d['rec'], 'des' => $d['des']];
            }
        }
    } else {
        foreach ($resps    as &$r) { $r['mensal'] = []; }
        foreach ($terceiros as &$r) { $r['mensal'] = []; }
    }
    unset($r);

    // Top categorias por responsável
    $catsPorResp = [];
    foreach ($pdo->query(
        "SELECT t.responsavel_id,
                COALESCE(c.nome,'Sem categoria') cat_nome, COALESCE(c.cor,'#64748b') cat_cor,
                COALESCE(SUM(t.valor),0) total, COUNT(*) qtd
         FROM `{$p}transacoes` t
         LEFT JOIN `{$p}categorias` c ON c.id = t.categoria_id
         WHERE t.responsavel_id IS NOT NULL AND t.tipo='despesa'
           AND t.status!='cancelado' $periodoWhere
         GROUP BY t.responsavel_id, t.categoria_id ORDER BY t.responsavel_id, total DESC"
    )->fetchAll() as $row) {
        $rid = $row['responsavel_id'];
        if (count($catsPorResp[$rid] ?? []) < 5) {
            $catsPorResp[$rid][] = ['nome' => $row['cat_nome'], 'cor' => $row['cat_cor'],
                                    'total' => (float)$row['total'], 'qtd' => (int)$row['qtd']];
        }
    }

    // Top categorias por terceiro
    $catsPorTcr = [];
    foreach ($pdo->query(
        "SELECT t.terceiro_id,
                COALESCE(c.nome,'Sem categoria') cat_nome, COALESCE(c.cor,'#64748b') cat_cor,
                COALESCE(SUM(t.valor),0) total, COUNT(*) qtd
         FROM `{$p}transacoes` t
         LEFT JOIN `{$p}categorias` c ON c.id = t.categoria_id
         WHERE t.terceiro_id IS NOT NULL AND t.tipo='despesa'
           AND t.status!='cancelado' $periodoWhere
         GROUP BY t.terceiro_id, t.categoria_id ORDER BY t.terceiro_id, total DESC"
    )->fetchAll() as $row) {
        $rid = $row['terceiro_id'];
        if (count($catsPorTcr[$rid] ?? []) < 5) {
            $catsPorTcr[$rid][] = ['nome' => $row['cat_nome'], 'cor' => $row['cat_cor'],
                                   'total' => (float)$row['total'], 'qtd' => (int)$row['qtd']];
        }
    }

    foreach ($resps     as &$r) { $r['categorias'] = $catsPorResp[$r['id']] ?? []; $r['saldo'] = (float)$r['receitas'] - (float)$r['despesas']; }
    foreach ($terceiros as &$r) { $r['categorias'] = $catsPorTcr[$r['id']]  ?? []; $r['saldo'] = (float)$r['receitas'] - (float)$r['despesas']; }
    unset($r);

    // Merge responsáveis + terceiros, ordenado por despesas desc
    $todos = array_merge($resps, $terceiros);
    usort($todos, fn($a, $b) => (float)$b['despesas'] <=> (float)$a['despesas']);

    $totRec = array_sum(array_column($todos, 'receitas'));
    $totDes = array_sum(array_column($todos, 'despesas'));

    respostaJSON([
        'success'    => true,
        'dados'      => $todos,
        'totais'     => ['receitas' => $totRec, 'despesas' => $totDes, 'saldo' => $totRec - $totDes],
        'mes'        => $mes,
        'ano'        => $ano,
        'label_ano'  => $mes === 0 ? "Ano $ano" : $mesesPtBr[$mes] . "/" . $ano,
    ]);
}

// ── Relatório Imposto de Renda ────────────────────────────────
if ($acao === 'ir') {
    $ano = (int)($_GET['ano'] ?? (int)date('Y') - 1);

    $mesesPtBr = ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];

    // Verifica se a coluna tipo_ir já existe (requer migrate.php)
    $colunaExiste = $pdo->query(
        "SHOW COLUMNS FROM `{$p}categorias` LIKE 'tipo_ir'"
    )->rowCount() > 0;

    if (!$colunaExiste) {
        respostaJSON([
            'success'             => true,
            'ano'                 => $ano,
            'grupos'              => [],
            'meses'               => $mesesPtBr,
            'total_classificadas' => 0,
            'aviso_migracao'      => true,
        ]);
    }

    try {
        // Busca todas as transações do ano com categorias classificadas para IR
        $stmt = $pdo->prepare(
            "SELECT t.id, t.tipo, t.descricao, t.valor, t.data, t.status,
                    MONTH(t.data) mes,
                    c.id cat_id, c.nome cat_nome, c.cor cat_cor, c.icone cat_icone, c.tipo_ir
             FROM `{$p}transacoes` t
             JOIN `{$p}categorias` c ON c.id = t.categoria_id
             WHERE c.tipo_ir IS NOT NULL
               AND t.status != 'cancelado'
               AND YEAR(t.data) = ?
             ORDER BY c.tipo_ir, c.nome, t.data"
        );
        $stmt->execute([$ano]);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        erroInterno($e, 'Erro ao consultar dados.');
    }

    // Estrutura de grupos IR
    $defs = [
        'tributavel'       => ['label'=>'Rendimentos Tributáveis',       'tipo'=>'receita', 'limite'=>null,    'icone'=>'briefcase',            'cor'=>'#f59e0b'],
        'isento'           => ['label'=>'Rendimentos Isentos / Não Trib.','tipo'=>'receita', 'limite'=>null,    'icone'=>'circle-check',         'cor'=>'#10b981'],
        'deducao_saude'    => ['label'=>'Deduções — Saúde',              'tipo'=>'despesa', 'limite'=>null,    'icone'=>'heart-pulse',          'cor'=>'#f43f5e'],
        'deducao_educacao' => ['label'=>'Deduções — Educação',           'tipo'=>'despesa', 'limite'=>3561.50, 'icone'=>'graduation-cap',       'cor'=>'#6366f1'],
        'deducao_previd'   => ['label'=>'Deduções — Previdência',        'tipo'=>'despesa', 'limite'=>null,    'icone'=>'shield-halved',        'cor'=>'#8b5cf6'],
        'deducao_outro'    => ['label'=>'Outras Deduções',               'tipo'=>'despesa', 'limite'=>null,    'icone'=>'file-invoice-dollar',  'cor'=>'#06b6d4'],
    ];

    $grupos = [];
    foreach ($defs as $chave => $def) {
        $grupos[$chave] = array_merge($def, ['categorias' => [], 'total' => 0.0]);
    }

    foreach ($rows as $row) {
        $chave   = $row['tipo_ir'];
        $catNome = $row['cat_nome'];
        $valor   = (float)$row['valor'];
        $mes     = (int)$row['mes'];

        if (!isset($grupos[$chave])) continue;

        if (!isset($grupos[$chave]['categorias'][$catNome])) {
            $grupos[$chave]['categorias'][$catNome] = [
                'nome'    => $catNome,
                'cor'     => $row['cat_cor'],
                'icone'   => $row['cat_icone'],
                'mensal'  => array_fill(1, 12, 0.0),
                'total'   => 0.0,
                'qtd'     => 0,
                'txs'     => [],
            ];
        }

        $grupos[$chave]['categorias'][$catNome]['mensal'][$mes] += $valor;
        $grupos[$chave]['categorias'][$catNome]['total']        += $valor;
        $grupos[$chave]['categorias'][$catNome]['qtd']++;
        $grupos[$chave]['categorias'][$catNome]['txs'][] = [
            'id'        => (int)$row['id'],
            'descricao' => $row['descricao'],
            'valor'     => $valor,
            'data'      => $row['data'],
        ];
        $grupos[$chave]['total'] += $valor;
    }

    // Converte para array indexado e ordena por valor desc
    foreach ($grupos as &$g) {
        usort($g['categorias'], fn($a, $b) => $b['total'] <=> $a['total']);
        $g['categorias'] = array_values($g['categorias']);
    }

    // Verifica se há categorias classificadas para IR no sistema
    $totalClassificadas = (int)$pdo->query(
        "SELECT COUNT(*) FROM `{$p}categorias` WHERE tipo_ir IS NOT NULL"
    )->fetchColumn();

    respostaJSON([
        'success'              => true,
        'ano'                  => $ano,
        'grupos'               => $grupos,
        'meses'                => $mesesPtBr,
        'total_classificadas'  => $totalClassificadas,
    ]);
}

respostaJSON(['success' => false, 'erro' => 'Ação não reconhecida.']);
