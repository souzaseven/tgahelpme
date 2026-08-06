<?php
// admin/index.php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] !== 'maiara') {
    header('Location: login.php');
    exit;
}
require_once '../conexao.php';

$stmt = $pdo->query("SELECT COUNT(*) FROM renascer_menbros");
$total_membros = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM renascer_menbros WHERE YEAR(data_conversao) = YEAR(CURDATE())");
$stmt->execute();
$total_convertidos = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM renascer_menbros WHERE MONTH(data_nascimento) = MONTH(CURDATE())");
$stmt->execute();
$total_aniversariantes = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM renascer_menbros WHERE data_cadastro >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute();
$total_recentes = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT nome_completo, data_cadastro FROM renascer_menbros ORDER BY data_cadastro DESC LIMIT 5");
$stmt->execute();
$ultimos = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT nome_completo, data_nascimento FROM renascer_menbros
     WHERE MONTH(data_nascimento) = MONTH(CURDATE())
     ORDER BY DAY(data_nascimento) ASC LIMIT 5"
);
$stmt->execute();
$aniversariantes_mes = $stmt->fetchAll();

/* Próximo aniversariante (para destaque no dashboard) */
$proximo_nome = null;
$proximo_dias = null;
$todos_nasc = $pdo->query("SELECT nome_completo, data_nascimento FROM renascer_menbros")->fetchAll();
$hoje = new DateTime('today');
$menor_dias = PHP_INT_MAX;
foreach ($todos_nasc as $t) {
    if (!$t['data_nascimento']) continue;
    try {
        $nasc = new DateTime($t['data_nascimento']);
        $aniv = DateTime::createFromFormat('Y-m-d', $hoje->format('Y') . '-' . $nasc->format('m-d'));
        if (!$aniv) continue;
        if ($aniv < $hoje) $aniv->modify('+1 year');
        $dias = (int)$hoje->diff($aniv)->days;
        if ($dias < $menor_dias) { $menor_dias = $dias; $proximo_nome = $t['nome_completo']; $proximo_dias = $dias; }
    } catch (Exception $e) {}
}

$titulo_pagina = 'Dashboard - Igreja Renascer';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<main class="dashboard">
    <div class="topbar">
        <h1>Painel Administrativo</h1>
        <div class="topbar-actions">
            <a href="membros.php" class="btn btn-secondary btn-sm">Ver Membros</a>
        </div>
    </div>

    <!-- Destaque: próximo aniversariante -->
    <?php if ($proximo_nome): ?>
    <div class="alert alert-<?= $proximo_dias === 0 ? 'erro' : 'sucesso' ?>" style="display:flex;align-items:center;gap:10px;margin-bottom:18px;">
        <span style="font-size:1.4em;">&#127874;</span>
        <span>
            <?php if ($proximo_dias === 0): ?>
                <strong><?= htmlspecialchars($proximo_nome) ?></strong> faz aniversário <strong>hoje</strong>!
                <a href="whatsapp.php" class="btn btn-green btn-sm" style="margin-left:10px;">&#128242; Parabenizar</a>
            <?php elseif ($proximo_dias === 1): ?>
                <strong><?= htmlspecialchars($proximo_nome) ?></strong> faz aniversário <strong>amanhã</strong>.
                <a href="whatsapp.php" class="btn btn-secondary btn-sm" style="margin-left:10px;">&#128242; Preparar</a>
            <?php else: ?>
                Próximo aniversário: <strong><?= htmlspecialchars($proximo_nome) ?></strong>
                em <strong><?= $proximo_dias ?> dias</strong>.
                <a href="whatsapp.php" class="btn btn-secondary btn-sm" style="margin-left:10px;">Ver todos</a>
            <?php endif; ?>
        </span>
    </div>
    <?php endif; ?>

    <!-- Cards de resumo -->
    <div class="cards">
        <div class="card">
            <h3>Total de Membros</h3>
            <p><?= $total_membros ?></p>
        </div>
        <div class="card card-verde">
            <h3>Convertidos (Ano)</h3>
            <p><?= $total_convertidos ?></p>
        </div>
        <div class="card card-laranja">
            <h3>Aniversários do Mês</h3>
            <p><?= $total_aniversariantes ?></p>
        </div>
        <div class="card card-roxo">
            <h3>Cadastros Recentes</h3>
            <p><?= $total_recentes ?></p>
        </div>
    </div>

    <!-- Listas rápidas -->
    <div class="listas-rapidas">
        <div class="lista">
            <h4>Últimos Cadastrados</h4>
            <ul>
                <?php foreach ($ultimos as $m): ?>
                <li>
                    <?= htmlspecialchars($m['nome_completo']) ?>
                    <span><?= date('d/m/Y', strtotime($m['data_cadastro'])) ?></span>
                </li>
                <?php endforeach; ?>
                <?php if (empty($ultimos)): ?>
                <li style="color:var(--text-muted);justify-content:center;">Nenhum membro ainda.</li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="lista">
            <h4>Aniversariantes do Mês</h4>
            <ul>
                <?php foreach ($aniversariantes_mes as $a): ?>
                <li>
                    <?= htmlspecialchars($a['nome_completo']) ?>
                    <span><?= date('d/m', strtotime($a['data_nascimento'])) ?></span>
                </li>
                <?php endforeach; ?>
                <?php if (empty($aniversariantes_mes)): ?>
                <li style="color:var(--text-muted);justify-content:center;">Nenhum este mês.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- Acesso rápido às novas funcionalidades -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;margin-bottom:24px;">
        <a href="membros.php" class="card" style="text-decoration:none;border-left-color:#3d7ab5;display:block;">
            <h3 style="font-size:.9em;">&#128101; Membros</h3>
            <p style="font-size:1em;font-weight:600;color:var(--text-2);">Cadastrar &amp; editar</p>
        </a>
        <a href="aniversarios.php" class="card" style="text-decoration:none;border-left-color:#d97706;display:block;">
            <h3 style="font-size:.9em;">&#127881; Aniversários</h3>
            <p style="font-size:1em;font-weight:600;color:var(--text-2);">Calendário anual</p>
        </a>
        <a href="whatsapp.php" class="card" style="text-decoration:none;border-left-color:#16a34a;display:block;">
            <h3 style="font-size:.9em;">&#128242; WhatsApp</h3>
            <p style="font-size:1em;font-weight:600;color:var(--text-2);">Avisar aniversariantes</p>
        </a>
    </div>

    <div class="acoes-rapidas">
        <a href="membros.php" class="btn" onclick="event.preventDefault();window.location='membros.php';setTimeout(()=>{if(typeof abrirModalNovo==='function')abrirModalNovo();},200)">+ Novo Cadastro</a>
        <a href="membros.php" class="btn btn-secondary">Buscar Membro</a>
    </div>
</main>
<?php require_once '../includes/footer.php'; ?>
