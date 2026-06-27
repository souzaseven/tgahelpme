<?php
require_once __DIR__ . '/../../backend/banco/conexao.php';
$p    = TABLE_PREFIX;
$hoje = new DateTime();
$meses = [];

// Busca saldo atual total das contas
$saldoAtual = (float) $pdo->query(
    "SELECT COALESCE(SUM(saldo_atual),0) FROM `{$p}contas` WHERE ativo=1"
)->fetchColumn();

// Busca recorrências ativas
$recorrencias = $pdo->query(
    "SELECT tipo, valor, frequencia, dia_vencimento, data_inicio, data_fim
     FROM `{$p}recorrencias`
     WHERE ativo=1 AND (data_fim IS NULL OR data_fim >= CURDATE())"
)->fetchAll();

// Busca transações pendentes futuras
$pendentes = $pdo->query(
    "SELECT tipo, valor, data
     FROM `{$p}transacoes`
     WHERE status='pendente' AND terceiro_id IS NULL
       AND data > CURDATE()
     ORDER BY data"
)->fetchAll();

$saldoProjetado = $saldoAtual;

for ($i = 0; $i < 6; $i++) {
    $dt = clone $hoje;
    $dt->modify("+{$i} months");
    $mes = (int)$dt->format('m');
    $ano = (int)$dt->format('Y');
    $label = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'][$mes-1] . '/' . $dt->format('y');

    $entradas  = 0.0;
    $saidas    = 0.0;

    // Recorrências
    foreach ($recorrencias as $r) {
        if ($r['data_fim'] && $r['data_fim'] < $dt->format('Y-m-') . '01') continue;
        if ($r['data_inicio'] > $dt->format('Y-m-') . '31') continue;

        $freq = $r['frequencia'];
        $val  = (float)$r['valor'];

        $mult = match($freq) {
            'diaria'      => 30,
            'semanal'     => 4,
            'quinzenal'   => 2,
            'mensal'      => 1,
            'bimestral'   => ($i % 2 === 0 ? 1 : 0),
            'trimestral'  => ($i % 3 === 0 ? 1 : 0),
            'semestral'   => ($i % 6 === 0 ? 1 : 0),
            'anual'       => ($i % 12 === 0 ? 1 : 0),
            default       => 0,
        };

        if ($r['tipo'] === 'receita') $entradas += $val * $mult;
        else                           $saidas   += $val * $mult;
    }

    // Pendentes do mês
    foreach ($pendentes as $tx) {
        $txMes = (int)substr($tx['data'], 5, 2);
        $txAno = (int)substr($tx['data'], 0, 4);
        if ($txMes === $mes && $txAno === $ano) {
            if ($tx['tipo'] === 'receita') $entradas += (float)$tx['valor'];
            else                            $saidas   += (float)$tx['valor'];
        }
    }

    $saldoProjetado += $entradas - $saidas;

    $meses[] = [
        'label'    => $label,
        'entradas' => round($entradas, 2),
        'saidas'   => round($saidas, 2),
        'saldo'    => round($saldoProjetado, 2),
    ];
}

respostaJSON([
    'success'       => true,
    'saldo_atual'   => round($saldoAtual, 2),
    'meses'         => $meses,
]);
