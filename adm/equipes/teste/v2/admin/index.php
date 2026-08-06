<?php
// admin/index.php — Dashboard
session_start();
require_once '../includes/auth.php';
auth_guard();
require_once '../conexao.php';

/* ── Métricas gerais ── */
$total_membros        = $pdo->query("SELECT COUNT(*) FROM renascer_menbros")->fetchColumn();
$total_ativos         = $pdo->query("SELECT COUNT(*) FROM renascer_menbros WHERE status = 'ativo'")->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM renascer_menbros WHERE YEAR(data_conversao) = YEAR(CURDATE())");
$stmt->execute();
$total_convertidos = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM renascer_menbros WHERE MONTH(data_nascimento) = MONTH(CURDATE())");
$stmt->execute();
$total_aniversariantes = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM renascer_menbros WHERE data_cadastro >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt->execute();
$total_recentes = $stmt->fetchColumn();

/* ── Últimos 5 cadastrados ── */
$stmt = $pdo->prepare("SELECT id, nome_completo, foto, data_cadastro, status FROM renascer_menbros ORDER BY data_cadastro DESC LIMIT 5");
$stmt->execute();
$ultimos = $stmt->fetchAll();

/* ── Aniversários — próximos 7 dias ── */
$todos_nasc = $pdo->query("SELECT nome_completo, data_nascimento, telefone, foto FROM renascer_menbros")->fetchAll();
$hoje = new DateTime('today');
$aniv_7dias = [];
$proximo_nome = null;
$proximo_dias = PHP_INT_MAX;
$proximo_data = null;

foreach ($todos_nasc as $t) {
    if (!$t['data_nascimento']) continue;
    try {
        $nasc = new DateTime($t['data_nascimento']);
        $aniv = DateTime::createFromFormat('Y-m-d', $hoje->format('Y') . '-' . $nasc->format('m-d'));
        if (!$aniv) continue;
        if ($aniv < $hoje) $aniv->modify('+1 year');
        $dias = (int)$hoje->diff($aniv)->days;

        if ($dias < $proximo_dias) {
            $proximo_dias = $dias;
            $proximo_nome = $t['nome_completo'];
            $proximo_data = $aniv->format('d/m');
        }
        if ($dias <= 7) {
            $aniv_7dias[] = [
                'nome'     => $t['nome_completo'],
                'foto'     => $t['foto'],
                'telefone' => $t['telefone'],
                'data'     => $aniv->format('d/m'),
                'dias'     => $dias,
            ];
        }
    } catch (Exception $e) {}
}
usort($aniv_7dias, fn($a, $b) => $a['dias'] <=> $b['dias']);

/* ── Percentual de membros ativos ── */
$pct_ativos = $total_membros > 0 ? round(($total_ativos / $total_membros) * 100) : 0;

$titulo_pagina = 'Dashboard - Igreja Renascer';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<main class="dashboard">

    <!-- ── TOPBAR ── -->
    <div class="topbar" style="margin-bottom:24px;">
        <div>
            <h1 style="margin:0;font-size:1.25em;">Painel Administrativo</h1>
            <p style="margin:4px 0 0;font-size:.82em;color:var(--text-2);">
                <?= date('l, d \d\e F \d\e Y') ?> &nbsp;·&nbsp;
                Olá, <strong><?= htmlspecialchars($_SESSION['usuario']) ?></strong> 👋
            </p>
        </div>
        <div class="topbar-actions">
            <a href="membros.php" class="btn btn-sm">+ Novo Membro</a>
        </div>
    </div>

    <!-- ── ACESSO RÁPIDO (topo) ── -->
    <div class="dash-atalhos-topo">
        <a href="membros.php" class="dash-atalho-topo">
            <div class="dash-atalho-icon" style="background:rgba(61,122,181,.15);color:#4d8fc9;">&#128101;</div>
            <span class="dash-atalho-label">Membros</span>
        </a>
        <a href="aniversarios.php" class="dash-atalho-topo">
            <div class="dash-atalho-icon" style="background:rgba(245,158,11,.15);color:#d97706;">&#127881;</div>
            <span class="dash-atalho-label">Calendário</span>
        </a>
        <a href="whatsapp.php" class="dash-atalho-topo">
            <div class="dash-atalho-icon" style="background:rgba(37,211,102,.15);color:#25d366;">&#128242;</div>
            <span class="dash-atalho-label">WhatsApp</span>
        </a>
        <a href="membros.php" class="dash-atalho-topo" onclick="event.preventDefault();window.location='membros.php';setTimeout(()=>{if(typeof abrirModalNovo==='function')abrirModalNovo();},200)">
            <div class="dash-atalho-icon" style="background:rgba(124,58,237,.15);color:#7c3aed;">&#43;</div>
            <span class="dash-atalho-label">Novo Membro</span>
        </a>
    </div>

    <!-- ── ALERTA: aniversário HOJE ── -->
    <?php
    $hoje_aniv = array_filter($aniv_7dias, fn($a) => $a['dias'] === 0);
    if (!empty($hoje_aniv)):
    ?>
    <div class="dash-alert-hoje">
        <span class="dash-alert-icon">🎂</span>
        <div class="dash-alert-texto">
            <?php if (count($hoje_aniv) === 1):
                $a = array_values($hoje_aniv)[0]; ?>
                <strong><?= htmlspecialchars($a['nome']) ?></strong> faz aniversário <strong>hoje</strong>!
            <?php else: ?>
                <strong><?= count($hoje_aniv) ?> membros</strong> fazem aniversário <strong>hoje</strong>!
            <?php endif; ?>
        </div>
        <a href="whatsapp.php" class="btn btn-green btn-sm">&#128242; Parabenizar</a>
    </div>
    <?php endif; ?>

    <!-- ── CARDS DE MÉTRICAS ── -->
    <div class="dash-metrics">

        <div class="dash-metric-card">
            <div class="dash-metric-icon" style="background:rgba(61,122,181,.15);color:#4d8fc9;">&#128101;</div>
            <div class="dash-metric-body">
                <span class="dash-metric-label">Total de Membros</span>
                <span class="dash-metric-valor"><?= $total_membros ?></span>
                <span class="dash-metric-sub"><?= $pct_ativos ?>% ativos</span>
            </div>
        </div>

        <div class="dash-metric-card">
            <div class="dash-metric-icon" style="background:rgba(22,163,74,.15);color:#16a34a;">&#9989;</div>
            <div class="dash-metric-body">
                <span class="dash-metric-label">Membros Ativos</span>
                <span class="dash-metric-valor"><?= $total_ativos ?></span>
                <span class="dash-metric-sub">de <?= $total_membros ?> cadastrados</span>
            </div>
        </div>

        <div class="dash-metric-card">
            <div class="dash-metric-icon" style="background:rgba(245,158,11,.15);color:#d97706;">&#127874;</div>
            <div class="dash-metric-body">
                <span class="dash-metric-label">Aniversários do Mês</span>
                <span class="dash-metric-valor"><?= $total_aniversariantes ?></span>
                <span class="dash-metric-sub"><?= date('F') ?></span>
            </div>
        </div>

        <div class="dash-metric-card">
            <div class="dash-metric-icon" style="background:rgba(124,58,237,.15);color:#7c3aed;">&#128640;</div>
            <div class="dash-metric-body">
                <span class="dash-metric-label">Convertidos este ano</span>
                <span class="dash-metric-valor"><?= $total_convertidos ?></span>
                <span class="dash-metric-sub"><?= date('Y') ?></span>
            </div>
        </div>

        <div class="dash-metric-card">
            <div class="dash-metric-icon" style="background:rgba(37,211,102,.15);color:#25d366;">&#128197;</div>
            <div class="dash-metric-body">
                <span class="dash-metric-label">Cadastros Recentes</span>
                <span class="dash-metric-valor"><?= $total_recentes ?></span>
                <span class="dash-metric-sub">últimos 30 dias</span>
            </div>
        </div>

        <?php if ($proximo_nome && $proximo_dias > 0): ?>
        <div class="dash-metric-card dash-metric-card-destaque">
            <div class="dash-metric-icon" style="background:rgba(245,158,11,.18);color:#f59e0b;">&#9200;</div>
            <div class="dash-metric-body">
                <span class="dash-metric-label">Próximo Aniversário</span>
                <span class="dash-metric-valor" style="font-size:1.1em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    <?= htmlspecialchars($proximo_nome) ?>
                </span>
                <span class="dash-metric-sub">
                    <?= $proximo_data ?> &mdash;
                    <?php if ($proximo_dias === 1): ?>
                        amanhã
                    <?php else: ?>
                        em <?= $proximo_dias ?> dias
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- ── CONTEÚDO PRINCIPAL (2 colunas) ── -->
    <div class="dash-grid-2">

        <!-- ── COLUNA ESQUERDA: Aniversários próximos 7 dias ── -->
        <div class="dash-panel">
            <div class="dash-panel-header">
                <span class="dash-panel-title">&#127874; Aniversários — próximos 7 dias</span>
                <a href="aniversarios.php" class="dash-panel-link">Ver calendário →</a>
            </div>
            <?php if (empty($aniv_7dias)): ?>
                <div class="dash-empty">Nenhum aniversariante nos próximos 7 dias.</div>
            <?php else: ?>
                <div class="dash-aniv-lista">
                    <?php foreach ($aniv_7dias as $a):
                        $fotoUrl = $a['foto'] ? '../assets/uploads/fotos/' . htmlspecialchars($a['foto']) : null;
                        $letra   = mb_strtoupper(mb_substr($a['nome'], 0, 1));
                        $temTel  = !empty($a['telefone']);
                        $waNum   = $temTel ? preg_replace('/\D/', '', $a['telefone']) : null;
                        if ($waNum && (strlen($waNum) === 10 || strlen($waNum) === 11)) $waNum = "55{$waNum}";
                        $msgWa  = "🎂 {$a['nome']} faz aniversário dia " . ($a['dias'] === 0 ? 'hoje' : $a['data']) . "!";
                        $waLink = $waNum ? "https://wa.me/{$waNum}?text=" . rawurlencode($msgWa) : null;
                    ?>
                    <div class="dash-aniv-item <?= $a['dias'] === 0 ? 'dash-aniv-hoje' : '' ?>">
                        <div class="dash-aniv-avatar">
                            <?php if ($fotoUrl): ?>
                                <img src="<?= htmlspecialchars($fotoUrl) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <span><?= htmlspecialchars($letra) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="dash-aniv-info">
                            <div class="dash-aniv-nome"><?= htmlspecialchars($a['nome']) ?></div>
                            <div class="dash-aniv-data">
                                &#127874; <?= $a['data'] ?>
                                <?php if ($a['dias'] === 0): ?>
                                    <span class="dash-badge-hoje">Hoje!</span>
                                <?php elseif ($a['dias'] === 1): ?>
                                    <span class="dash-badge-amanha">Amanhã</span>
                                <?php else: ?>
                                    &mdash; em <?= $a['dias'] ?> dias
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($waLink): ?>
                        <a href="<?= htmlspecialchars($waLink) ?>" target="_blank" rel="noopener" class="dash-aniv-wa" title="Enviar pelo WhatsApp">
                            <svg width="16" height="16" viewBox="0 0 32 32" fill="currentColor"><path d="M16 0C7.163 0 0 7.163 0 16c0 2.833.74 5.494 2.034 7.81L.054 31.946l8.344-2.13A15.94 15.94 0 0016 32c8.837 0 16-7.163 16-16S24.837 0 16 0zm0 29.5a13.45 13.45 0 01-6.854-1.878l-.49-.291-5.087 1.297 1.32-4.822-.32-.495A13.5 13.5 0 1116 29.5z"/><path d="M23.472 19.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.174.199-.347.223-.645.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.652-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.447-.52.148-.173.197-.297.297-.497.1-.198.05-.372-.025-.52-.074-.149-.669-1.611-.916-2.207-.242-.579-.487-.5-.669-.51-.174-.008-.372-.01-.57-.01-.198 0-.52.074-.793.372-.272.298-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.263.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.412.249-.694.249-1.29.174-1.414-.074-.124-.273-.198-.57-.347z"/></svg>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="text-align:center;margin-top:12px;">
                    <a href="whatsapp.php" class="btn btn-green btn-sm">&#128640; Enviar para todos</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── COLUNA DIREITA: Últimos cadastrados ── -->
        <div>

            <!-- Últimos cadastrados -->
            <div class="dash-panel">
                <div class="dash-panel-header">
                    <span class="dash-panel-title">&#128101; Últimos Cadastrados</span>
                    <a href="membros.php" class="dash-panel-link">Ver todos →</a>
                </div>
                <?php if (empty($ultimos)): ?>
                    <div class="dash-empty">Nenhum membro cadastrado ainda.</div>
                <?php else: ?>
                    <ul class="dash-membros-lista">
                        <?php foreach ($ultimos as $m):
                            $fotoUrl = $m['foto'] ? '../assets/uploads/fotos/' . htmlspecialchars($m['foto']) : null;
                            $letra   = mb_strtoupper(mb_substr($m['nome_completo'], 0, 1));
                        ?>
                        <li class="dash-membro-item">
                            <div class="dash-membro-avatar">
                                <?php if ($fotoUrl): ?>
                                    <img src="<?= htmlspecialchars($fotoUrl) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <span><?= htmlspecialchars($letra) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="dash-membro-info">
                                <span class="dash-membro-nome"><?= htmlspecialchars($m['nome_completo']) ?></span>
                                <span class="dash-membro-data"><?= date('d/m/Y', strtotime($m['data_cadastro'])) ?></span>
                            </div>
                            <span class="badge badge-<?= $m['status'] === 'ativo' ? 'ativo' : 'inativo' ?>" style="font-size:.72em;">
                                <?= ucfirst($m['status']) ?>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

        </div>
    </div>

</main>

<?php require_once '../includes/footer.php'; ?>
