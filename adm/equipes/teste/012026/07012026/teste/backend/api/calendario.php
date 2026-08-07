<?php
// ============================================================
// calendario.php — Lançamentos de um mês agrupados por dia, para
// a visão de calendário. Devolve também um resumo (recebido/a
// receber/pago/a pagar) do mês inteiro.
//
// GET ?mes=M&ano=AAAA
// ============================================================
require_once __DIR__ . '/../../backend/banco/conexao.php';

$p   = TABLE_PREFIX;
$mes = max(1, min(12, (int)($_GET['mes'] ?? date('m'))));
$ano = (int)($_GET['ano'] ?? date('Y'));

try {
    $stmt = $pdo->prepare(
        "SELECT t.id, t.tipo, t.descricao, t.valor, t.data, t.status,
                c.nome cat_nome, c.cor cat_cor, ct.nome conta_nome
         FROM `{$p}transacoes` t
         LEFT JOIN `{$p}categorias` c  ON c.id  = t.categoria_id
         LEFT JOIN `{$p}contas`     ct ON ct.id = t.conta_id
         WHERE MONTH(t.data) = ? AND YEAR(t.data) = ?
           AND t.status != 'cancelado'
         ORDER BY t.data, t.tipo DESC, t.valor DESC"
    );
    $stmt->execute([$mes, $ano]);
    $events = $stmt->fetchAll();
} catch (Throwable $ex) {
    erroInterno($ex);
}

$sumario = ['a_receber' => 0.0, 'a_pagar' => 0.0, 'recebido' => 0.0, 'pago' => 0.0];
foreach ($events as $e) {
    $v = (float)$e['valor'];
    if ($e['tipo'] === 'receita') {
        if ($e['status'] === 'pago') $sumario['recebido']  += $v;
        else                         $sumario['a_receber'] += $v;
    } else {
        if ($e['status'] === 'pago') $sumario['pago']      += $v;
        else                         $sumario['a_pagar']   += $v;
    }
}

respostaJSON(['success' => true, 'events' => $events, 'sumario' => $sumario, 'mes' => $mes, 'ano' => $ano]);
