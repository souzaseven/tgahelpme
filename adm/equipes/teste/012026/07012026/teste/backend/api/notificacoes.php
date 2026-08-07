<?php
// ============================================================
// notificacoes.php — Alertas proativos exibidos no sino do topo
// e como notificação nativa do navegador (ver app.js, verificarVencimentos()).
//
// GET (sem ação) — varre os próximos 7 dias e devolve até 3 tipos
// de alerta: faturas de cartão a vencer, despesas pendentes a
// vencer e metas perto do prazo sem ter sido atingidas.
// ============================================================
require_once __DIR__ . '/../../backend/banco/conexao.php';
$p   = TABLE_PREFIX;
$hoje = date('Y-m-d');
$em7  = date('Y-m-d', strtotime('+7 days'));

$alertas = [];

// ── Faturas de cartão vencendo nos próximos 7 dias ──────────
$stmt = $pdo->prepare(
    "SELECT cf.id, cc.nome cartao_nome, cf.data_vencimento,
            cf.valor_total, cf.status
     FROM `{$p}cartoes_faturas` cf
     JOIN `{$p}cartoes` cc ON cc.id = cf.cartao_id
     WHERE cf.status = 'aberta'
       AND cf.data_vencimento BETWEEN ? AND ?
     ORDER BY cf.data_vencimento"
);
$stmt->execute([$hoje, $em7]);
foreach ($stmt->fetchAll() as $f) {
    $dias = (int)((strtotime($f['data_vencimento']) - time()) / 86400);
    $alertas[] = [
        'tipo'     => 'fatura',
        'titulo'   => 'Fatura vence em ' . ($dias === 0 ? 'hoje' : ($dias === 1 ? '1 dia' : "{$dias} dias")),
        'corpo'    => $f['cartao_nome'] . ' · R$ ' . number_format($f['valor_total'], 2, ',', '.'),
        'icone'    => 'fa-credit-card',
        'cor'      => $dias <= 2 ? '#ef4444' : '#f59e0b',
        'urgente'  => $dias <= 2,
        'link'     => '?p=cartoes',
    ];
}

// ── Despesas pendentes vencendo nos próximos 7 dias ─────────
$stmt = $pdo->prepare(
    "SELECT descricao, valor, data
     FROM `{$p}transacoes`
     WHERE tipo='despesa' AND status='pendente'
       AND terceiro_id IS NULL
       AND data BETWEEN ? AND ?
     ORDER BY data LIMIT 5"
);
$stmt->execute([$hoje, $em7]);
foreach ($stmt->fetchAll() as $t) {
    $dias = (int)((strtotime($t['data']) - time()) / 86400);
    $alertas[] = [
        'tipo'    => 'despesa',
        'titulo'  => 'Despesa pendente' . ($dias === 0 ? ' (hoje)' : " em {$dias}d"),
        'corpo'   => $t['descricao'] . ' · R$ ' . number_format($t['valor'], 2, ',', '.'),
        'icone'   => 'fa-circle-exclamation',
        'cor'     => '#f59e0b',
        'urgente' => $dias <= 1,
        'link'    => '?p=despesas',
    ];
}

// ── Metas com prazo entrando nos próximos 7 dias, ainda não batidas ──
$stmt = $pdo->prepare(
    "SELECT nome, valor_alvo, valor_atual, data_alvo
     FROM `{$p}metas`
     WHERE status = 'ativa'
       AND data_alvo BETWEEN ? AND ?
       AND valor_atual < valor_alvo
     LIMIT 3"
);
$stmt->execute([$hoje, $em7]);
foreach ($stmt->fetchAll() as $m) {
    $pct = $m['valor_alvo'] > 0 ? round($m['valor_atual']/$m['valor_alvo']*100) : 0;
    $alertas[] = [
        'tipo'    => 'meta',
        'titulo'  => 'Meta prestes a vencer',
        'corpo'   => $m['nome'] . " · {$pct}% atingida",
        'icone'   => 'fa-bullseye',
        'cor'     => '#8b5cf6',
        'urgente' => false,
        'link'    => '?p=metas',
    ];
}

respostaJSON(['success' => true, 'alertas' => $alertas]);
