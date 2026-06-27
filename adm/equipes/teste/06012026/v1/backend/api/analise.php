<?php
require_once __DIR__ . '/../../backend/banco/conexao.php';

$p    = TABLE_PREFIX;
$tipo = trim($_GET['tipo'] ?? 'inteligente');
$mes  = max(1, min(12, (int)($_GET['mes'] ?? date('m'))));
$ano  = (int)($_GET['ano'] ?? date('Y'));

function somaDesps(PDO $pdo, string $p, ?int $catId, int $mes, int $ano): float {
    $filtro = $catId ? 'AND categoria_id = ?' : 'AND categoria_id IS NOT NULL';
    $sql    = "SELECT COALESCE(SUM(valor),0) FROM `{$p}transacoes`
               WHERE tipo='despesa' AND status != 'cancelado'
                 AND MONTH(data)=? AND YEAR(data)=? $filtro";
    $args   = $catId ? [$mes, $ano, $catId] : [$mes, $ano];
    $s      = $pdo->prepare($sql);
    $s->execute($args);
    return (float)$s->fetchColumn();
}

function somaTotal(PDO $pdo, string $p, int $mes, int $ano): float {
    $s = $pdo->prepare(
        "SELECT COALESCE(SUM(valor),0) FROM `{$p}transacoes`
         WHERE tipo='despesa' AND status!='cancelado' AND MONTH(data)=? AND YEAR(data)=?"
    );
    $s->execute([$mes, $ano]);
    return (float)$s->fetchColumn();
}

// ── Comparativo ano a ano ────────────────────────────────────
if ($tipo === 'comparativo') {
    $anoAnt = $ano - 1;

    try {
        $stmt = $pdo->prepare(
            "SELECT c.id, c.nome cat_nome, c.cor cat_cor,
                    COALESCE(SUM(CASE WHEN YEAR(t.data)=? THEN t.valor ELSE 0 END),0) atual,
                    COALESCE(SUM(CASE WHEN YEAR(t.data)=? THEN t.valor ELSE 0 END),0) anterior
             FROM `{$p}categorias` c
             LEFT JOIN `{$p}transacoes` t ON t.categoria_id = c.id
               AND t.tipo='despesa' AND t.status!='cancelado' AND MONTH(t.data)=?
               AND YEAR(t.data) IN (?,?)
             WHERE c.tipo IN ('despesa','ambos') AND c.ativo=1
             GROUP BY c.id, c.nome, c.cor
             HAVING (atual + anterior) > 0
             ORDER BY (atual + anterior) DESC LIMIT 12"
        );
        $stmt->execute([$ano, $anoAnt, $mes, $ano, $anoAnt]);
        $categorias = $stmt->fetchAll();
    } catch (Throwable $e) {
        $categorias = [];
    }

    // Tendência dos últimos 12 meses
    $tendencia = [];
    for ($i = 11; $i >= 0; $i--) {
        $dt   = (new DateTime("first day of -{$i} months"));
        $mRef = (int)$dt->format('m');
        $yRef = (int)$dt->format('Y');
        $tendencia[] = [
            'label' => strftime('%b/%y', $dt->getTimestamp()) ?: $dt->format('M/y'),
            'mes'   => $mRef, 'ano' => $yRef,
            'total' => somaTotal($pdo, $p, $mRef, $yRef),
        ];
    }

    $totAtual    = somaTotal($pdo, $p, $mes, $ano);
    $totAnterior = somaTotal($pdo, $p, $mes, $anoAnt);

    respostaJSON([
        'success'     => true,
        'categorias'  => $categorias,
        'tendencia'   => $tendencia,
        'totAtual'    => $totAtual,
        'totAnterior' => $totAnterior,
        'mes' => $mes, 'ano' => $ano, 'anoAnt' => $anoAnt,
    ]);
}

// ── Análise inteligente (média 3 meses) ──────────────────────
if ($tipo === 'inteligente') {
    $cats = $pdo->query(
        "SELECT id, nome, cor FROM `{$p}categorias`
         WHERE tipo IN ('despesa','ambos') AND ativo=1 ORDER BY nome"
    )->fetchAll();

    $dados      = [];
    $baseDate   = new DateTime("{$ano}-{$mes}-01");

    foreach ($cats as $cat) {
        $atual = somaDesps($pdo, $p, (int)$cat['id'], $mes, $ano);

        $soma3m = 0.0; $count = 0;
        for ($i = 1; $i <= 3; $i++) {
            $dt  = (clone $baseDate)->modify("-{$i} months");
            $v   = somaDesps($pdo, $p, (int)$cat['id'], (int)$dt->format('m'), (int)$dt->format('Y'));
            if ($v > 0) { $soma3m += $v; $count++; }
        }
        $media = $count > 0 ? $soma3m / $count : 0.0;
        if ($media < 0.01 && $atual < 0.01) continue;

        $dados[] = [
            'cat_nome'   => $cat['nome'],
            'cat_cor'    => $cat['cor'],
            'media3m'    => round($media, 2),
            'atual'      => round($atual, 2),
            'desvio_pct' => $media > 0.01
                            ? round(($atual - $media) / $media * 100, 1)
                            : null,
        ];
    }
    usort($dados, fn($a, $b) => $b['atual'] <=> $a['atual']);

    // Totais globais
    $totalAtual = somaTotal($pdo, $p, $mes, $ano);
    $soma3Tot   = 0.0;
    for ($i = 1; $i <= 3; $i++) {
        $dt = (clone $baseDate)->modify("-{$i} months");
        $soma3Tot += somaTotal($pdo, $p, (int)$dt->format('m'), (int)$dt->format('Y'));
    }
    $media3Tot = round($soma3Tot / 3, 2);

    respostaJSON([
        'success'      => true,
        'dados'        => $dados,
        'totalAtual'   => $totalAtual,
        'media3mTotal' => $media3Tot,
        'mes' => $mes, 'ano' => $ano,
    ]);
}

respostaJSON(['success' => false, 'erro' => 'Tipo inválido.']);
