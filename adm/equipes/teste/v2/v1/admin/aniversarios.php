<?php
// admin/aniversarios.php — Calendário anual de aniversariantes
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] !== 'maiara') {
    header('Location: login.php');
    exit;
}
require_once '../conexao.php';

$stmt = $pdo->query(
    "SELECT nome_completo,
            MONTH(data_nascimento) AS mes,
            DAY(data_nascimento)   AS dia
     FROM renascer_menbros
     ORDER BY MONTH(data_nascimento), DAY(data_nascimento)"
);
$todos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Agrupa por mês */
$por_mes = array_fill(1, 12, []);
foreach ($todos as $m) {
    $por_mes[(int)$m['mes']][] = $m;
}

$mes_atual   = (int)date('n');
$dia_atual   = (int)date('j');
$nomes_meses = [
    1  => 'Janeiro',   2  => 'Fevereiro', 3  => 'Março',
    4  => 'Abril',     5  => 'Maio',       6  => 'Junho',
    7  => 'Julho',     8  => 'Agosto',     9  => 'Setembro',
    10 => 'Outubro',  11 => 'Novembro',   12 => 'Dezembro',
];

/* Total de aniversariantes no mês atual */
$total_mes = count($por_mes[$mes_atual]);

$titulo_pagina = 'Calendário de Aniversários - Igreja Renascer';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<main class="dashboard">
    <div class="topbar">
        <h1>&#127881; Calendário de Aniversários</h1>
        <div class="topbar-actions">
            <a href="whatsapp.php" class="btn btn-green btn-sm">&#128242; Avisar por WhatsApp</a>
        </div>
    </div>

    <!-- Resumo do mês atual -->
    <?php if ($total_mes > 0): ?>
    <div class="alert alert-sucesso" style="margin-bottom:20px;">
        &#127874; <strong><?= $nomes_meses[$mes_atual] ?></strong> tem
        <strong><?= $total_mes ?></strong> aniversariante<?= $total_mes !== 1 ? 's' : '' ?> este mês.
    </div>
    <?php endif; ?>

    <div class="cal-grid">
        <?php for ($mes = 1; $mes <= 12; $mes++):
            $atual     = ($mes === $mes_atual);
            $membros_m = $por_mes[$mes];
            $qtd       = count($membros_m);
        ?>
        <div class="cal-card <?= $atual ? 'cal-atual' : '' ?>">
            <div class="cal-header">
                <span class="cal-mes-nome"><?= $nomes_meses[$mes] ?></span>
                <span class="cal-count"><?= $qtd ?></span>
            </div>
            <div class="cal-body">
                <?php if (empty($membros_m)): ?>
                    <div class="cal-vazio">Nenhum aniversariante</div>
                <?php else: ?>
                    <?php foreach ($membros_m as $m):
                        $ehHoje = $atual && ((int)$m['dia'] === $dia_atual);
                    ?>
                    <div class="cal-item" style="<?= $ehHoje ? 'font-weight:700;' : '' ?>">
                        <span class="cal-dia"><?= str_pad($m['dia'], 2, '0', STR_PAD_LEFT) . '/' . str_pad($mes, 2, '0', STR_PAD_LEFT) ?></span>
                        <span class="cal-nome" title="<?= htmlspecialchars($m['nome_completo']) ?>">
                            <?= htmlspecialchars($m['nome_completo']) ?>
                            <?php if ($ehHoje): ?><span style="color:#f59e0b;margin-left:4px;">&#127874;</span><?php endif; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <p style="color:var(--text-muted);font-size:.82em;margin-top:20px;text-align:center;">
        O mês atual (<?= $nomes_meses[$mes_atual] ?>) aparece destacado em azul.
        &#127874; indica aniversariante do dia de hoje.
    </p>
</main>
<?php require_once '../includes/footer.php'; ?>
