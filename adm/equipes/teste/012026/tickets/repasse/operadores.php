<?php
require_once __DIR__ . '/includes/session.php';

if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$nomeUsuario = htmlspecialchars($_SESSION['nome']);
$isAdmin     = (int) $_SESSION['is_admin'];

require_once __DIR__ . '/../backend/conexao.php';
require_once __DIR__ . '/includes/config.php';

function tabelaHabilidadesDisponivel(PDO $pdo): bool
{
    try {
        $pdo->query("SELECT 1 FROM repasse_operador_habilidades LIMIT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

$usaTabelaHabilidades = tabelaHabilidadesDisponivel($pdo);

function calcularTempoEmpresaMeses(?string $dataContratacao): ?int
{
    if (empty($dataContratacao)) {
        return null;
    }

    try {
        $contratacao = new DateTimeImmutable($dataContratacao);
        $hoje = new DateTimeImmutable('today');

        if ($contratacao > $hoje) {
            return 0;
        }

        $diff = $contratacao->diff($hoje);
        return ($diff->y * 12) + $diff->m;
    } catch (Exception $e) {
        return null;
    }
}

function formatarTempoEmpresa(?string $dataContratacao): string
{
    $meses = calcularTempoEmpresaMeses($dataContratacao);
    if ($meses === null) {
        return 'Sem data de contratacao';
    }

    if ($meses === 0) {
        return 'Menos de 1 mes';
    }

    $anos = intdiv($meses, 12);
    $mesesRestantes = $meses % 12;
    $partes = [];

    if ($anos > 0) {
        $partes[] = $anos . ' ' . ($anos === 1 ? 'ano' : 'anos');
    }

    if ($mesesRestantes > 0) {
        $partes[] = $mesesRestantes . ' ' . ($mesesRestantes === 1 ? 'mes' : 'meses');
    }

    return implode(' e ', $partes);
}

function formatarDataBr(?string $data): string
{
    if (empty($data)) {
        return 'Nao informada';
    }

    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $data);
    return $dt ? $dt->format('d/m/Y') : 'Nao informada';
}

// Busca todos os operadores com lider definido
$queryOperadores = $usaTabelaHabilidades
     ? "SELECT
                o.evolux_agent_id,
                o.nome,
                o.lider,
                o.fila,
                o.evolux_queue_id,
                o.data_contratacao,
                h.eh_tef AS habilidade_tef,
                h.troca_regime AS habilidade_troca_regime
         FROM operadores o
         LEFT JOIN repasse_operador_habilidades h
             ON h.operador_id = o.evolux_agent_id
         WHERE lider IS NOT NULL AND lider != ''
         ORDER BY lider, nome"
     : "SELECT
                evolux_agent_id,
                nome,
                lider,
                fila,
                evolux_queue_id,
                data_contratacao,
                NULL AS habilidade_tef,
                NULL AS habilidade_troca_regime
         FROM operadores
         WHERE lider IS NOT NULL AND lider != ''
         ORDER BY lider, nome";
$stmt = $pdo->query($queryOperadores);
$todos = $stmt->fetchAll();

// Agrupa por líder
$grupos = [];
$operadoresEditaveis = [];
foreach ($todos as $op) {
    $lider = $op['lider'];
    $id    = (int) $op['evolux_agent_id'];
    $tef = $op['habilidade_tef'] !== null
        ? ((int) $op['habilidade_tef'] === 1)
        : in_array($id, TEF_IDS, true);
    $trocaRegime = $op['habilidade_troca_regime'] !== null
        ? ((int) $op['habilidade_troca_regime'] === 1)
        : in_array($id, TROCA_REGIME_IDS, true);
    $tempoMeses = calcularTempoEmpresaMeses($op['data_contratacao']);
    $grupos[$lider][] = [
        'id'       => $id,
        'nome'     => $op['nome'],
        'fila'     => $op['fila'],
        'fila_id'  => (int) $op['evolux_queue_id'],
        'tef'      => $tef,
        'troca_regime' => $trocaRegime,
        'data_contratacao' => $op['data_contratacao'],
        'data_contratacao_br' => formatarDataBr($op['data_contratacao']),
        'tempo_empresa_meses' => $tempoMeses,
        'tempo_empresa_texto' => formatarTempoEmpresa($op['data_contratacao']),
    ];

    $operadoresEditaveis[$id] = [
        'id' => $id,
        'nome' => $op['nome'],
        'data_contratacao' => $op['data_contratacao'],
        'tef' => $tef,
        'troca_regime' => $trocaRegime,
    ];
}

// Ordena operadores dentro de cada equipe conforme ORDEM_EQUIPES
foreach ($grupos as $lider => &$lista) {
    $ordemLider = ORDEM_EQUIPES[$lider] ?? [];
    usort($lista, function ($a, $b) use ($ordemLider) {
        $posA = array_search($a['id'], $ordemLider);
        $posB = array_search($b['id'], $ordemLider);
        $posA = $posA === false ? PHP_INT_MAX : $posA;
        $posB = $posB === false ? PHP_INT_MAX : $posB;
        return $posA <=> $posB;
    });
}
unset($lista);

// Ordena as equipes conforme a ordem definida em ORDEM_EQUIPES
$lideresDef = array_keys(ORDEM_EQUIPES);
uksort($grupos, function ($a, $b) use ($lideresDef) {
    $pa = array_search($a, $lideresDef);
    $pb = array_search($b, $lideresDef);
    $pa = $pa === false ? PHP_INT_MAX : $pa;
    $pb = $pb === false ? PHP_INT_MAX : $pb;
    return $pa <=> $pb;
});

$totalGeral = array_sum(array_map('count', $grupos));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operadores por Equipe</title>
    <link rel="icon" href="https://tgameajuda.com/img/principal/bot-tga.webp" type="image/x-icon">
    <link rel="stylesheet" href="assets/style.css">
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-E7ZNTJSRYR"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-E7ZNTJSRYR');
    </script>
    <style>
        /* ── Operadores page extras ── */
        .ops-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 24px;
            padding: 24px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .ops-card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .ops-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            background: #1e3a5f;
            color: #fff;
        }

        .ops-card-header h2 {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .ops-card-header p {
            font-size: 11px;
            color: #94a3b8;
        }

        .ops-count-badge {
            background: rgba(255,255,255,.15);
            color: #e2e8f0;
            font-size: 12px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 999px;
            white-space: nowrap;
        }

        .ops-list {
            list-style: none;
        }

        .ops-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 18px;
            border-bottom: 1px solid var(--border);
            transition: background .12s;
        }

        .ops-item:last-child { border-bottom: none; }
        .ops-item:hover { background: var(--bg); }

        .ops-pos {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--border);
            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .ops-info { flex: 1; min-width: 0; }
        .ops-nome {
            font-weight: 600;
            font-size: 13px;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ops-fila {
            font-size: 11px;
            color: var(--muted);
            margin-top: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ops-meta {
            font-size: 11px;
            color: var(--muted);
            margin-top: 2px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .ops-badges { display: flex; gap: 4px; flex-shrink: 0; }

        .badge-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .3px;
            white-space: nowrap;
        }

        .badge-tef {
            background: var(--tef-bg);
            color: var(--tef-color);
        }

        .badge-regime {
            background: #dcfce7;
            color: #166534;
        }

        body.dark .badge-regime {
            background: #153a2a;
            color: #86efac;
        }

        .ops-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }

        .ops-id {
            font-size: 10px;
            color: var(--muted);
            font-family: monospace;
            white-space: nowrap;
        }

        .ops-edit-btn {
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }

        .ops-edit-btn:hover { border-color: var(--primary); color: var(--primary); }

        /* Resumo header */
        .page-summary {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 12px 24px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            color: var(--muted);
        }

        .page-summary strong { color: var(--text); }

        .summary-chips { display: flex; gap: 8px; flex-wrap: wrap; }

        .chip {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 2px 10px;
            font-size: 11px;
            font-weight: 600;
            color: var(--text);
            white-space: nowrap;
        }

        body.dark .ops-item:hover { background: #1a2a3a; }
        body.dark .ops-pos { background: #334155; }
        body.dark .chip { background: #1e293b; }
        body.dark .ops-edit-btn { background: #1e293b; }

        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .65);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 200;
        }

        .modal-backdrop.is-open { display: flex; }
        .modal-backdrop[hidden] { display: none !important; }

        .modal-card {
            width: 100%;
            max-width: 420px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            padding: 22px;
        }

        .modal-card h3 { font-size: 18px; margin-bottom: 6px; }
        .modal-card p { color: var(--muted); font-size: 13px; margin-bottom: 18px; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }
        .modal-card label { display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: var(--muted); }
        .modal-card input[type="date"] { width: 100%; }

        .modal-skill-grid {
            display: grid;
            gap: 8px;
            margin-top: 14px;
        }

        .modal-skill-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text);
        }

        .modal-skill-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
        }
    </style>
</head>
<body>
<script src="assets/common.js?v=1"></script>

<!-- ── HEADER ── -->
<header class="topbar">
    <div class="topbar-left">
        <span class="topbar-icon">👥</span>
        <h1 class="topbar-title">Operadores por Equipe</h1>
    </div>
    <div class="topbar-right">
        <?php if ($isAdmin): ?>
            <span class="badge-admin">Admin</span>
        <?php endif; ?>
        <a href="index.php" class="btn btn-outline btn-sm">🎫 Repasse</a>
        <?php if ($isAdmin): ?>
            <a href="usuarios.php"  class="btn btn-outline btn-sm">🔑 Usuários</a>
            <a href="historico.php" class="btn btn-outline btn-sm">📋 Histórico</a>
        <?php endif; ?>
        <span class="topbar-user">👤 <?= $nomeUsuario ?></span>
        <button id="btn-tema" class="btn btn-outline btn-sm" title="Alternar modo claro/escuro">🌙</button>
        <a href="logout.php" class="btn btn-outline btn-sm">Sair</a>
    </div>
</header>

<!-- ── RESUMO ── -->
<div class="page-summary">
    <span>Total: <strong><?= $totalGeral ?> operadores</strong> em <strong><?= count($grupos) ?> equipes</strong></span>
    <div class="summary-chips">
        <?php foreach ($grupos as $lider => $ops): ?>
            <span class="chip"><?= htmlspecialchars($lider) ?> — <?= count($ops) ?></span>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── GRID ── -->
<div class="ops-grid">
    <?php foreach ($grupos as $lider => $operadores):
        $fila = $operadores[0]['fila'] ?? '';
        $totalEquipe = count($operadores);
        $totalTef    = count(array_filter($operadores, fn($o) => $o['tef']));
        $totalTrocaRegime = count(array_filter($operadores, fn($o) => $o['troca_regime']));
    ?>
    <div class="ops-card">
        <div class="ops-card-header">
            <div>
                <h2><?= htmlspecialchars($lider) ?></h2>
                <p>
                    <?= htmlspecialchars($fila) ?>
                    <?php if ($totalTef): ?> · <span style="color:#93c5fd"><?= $totalTef ?> TEF</span><?php endif; ?>
                    <?php if ($totalTrocaRegime): ?> · <span style="color:#86efac"><?= $totalTrocaRegime ?> Troca de Regime</span><?php endif; ?>
                </p>
            </div>
            <span class="ops-count-badge"><?= $totalEquipe ?></span>
        </div>
        <ul class="ops-list">
            <?php foreach ($operadores as $pos => $op): ?>
            <li class="ops-item">
                <span class="ops-pos"><?= $pos + 1 ?></span>
                <div class="ops-info">
                    <div class="ops-nome"><?= htmlspecialchars($op['nome']) ?></div>
                    <div class="ops-fila"><?= htmlspecialchars($op['fila']) ?></div>
                    <div class="ops-meta">
                        <span>Contratação: <?= htmlspecialchars($op['data_contratacao_br']) ?></span>
                        <span>Tempo: <?= htmlspecialchars($op['tempo_empresa_texto']) ?></span>
                    </div>
                </div>
                <div class="ops-actions">
                    <div class="ops-badges">
                    <?php if ($op['tef']): ?>
                        <span class="badge-pill badge-tef">TEF</span>
                    <?php endif; ?>
                    <?php if ($op['troca_regime']): ?>
                        <span class="badge-pill badge-regime">Troca de Regime</span>
                    <?php endif; ?>
                    </div>
                    <?php if ($isAdmin): ?>
                        <button type="button" class="ops-edit-btn" data-op-id="<?= $op['id'] ?>">Editar</button>
                    <?php endif; ?>
                    <span class="ops-id">#<?= $op['id'] ?></span>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($isAdmin): ?>
<div id="modal-operador" class="modal-backdrop" hidden>
    <div class="modal-card">
        <h3>Editar operador</h3>
        <p id="modal-operador-nome"></p>
        <label for="modal-data-contratacao">Data de contratação</label>
        <input type="date" id="modal-data-contratacao" class="form-control">
        <div class="modal-skill-grid">
            <label class="modal-skill-item" for="modal-skill-tef">
                <input type="checkbox" id="modal-skill-tef">
                <span>Habilidade TEF</span>
            </label>
            <label class="modal-skill-item" for="modal-skill-troca-regime">
                <input type="checkbox" id="modal-skill-troca-regime">
                <span>Habilidade Troca de Regime</span>
            </label>
        </div>
        <div class="modal-actions">
            <button type="button" id="modal-cancelar" class="btn btn-outline">Cancelar</button>
            <button type="button" id="modal-salvar" class="btn btn-primary">Salvar</button>
        </div>
    </div>
</div>
<div id="toast" class="toast" hidden></div>
<?php endif; ?>

<script>
(function () {
<?php if ($isAdmin): ?>
    const operadores = <?= json_encode(array_values($operadoresEditaveis), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const operadoresMap = new Map(operadores.map(op => [Number(op.id), op]));
    const modal = document.getElementById('modal-operador');
    const modalNome = document.getElementById('modal-operador-nome');
    const modalData = document.getElementById('modal-data-contratacao');
    const modalSkillTef = document.getElementById('modal-skill-tef');
    const modalSkillTrocaRegime = document.getElementById('modal-skill-troca-regime');
    const btnSalvar = document.getElementById('modal-salvar');
    let operadorAtual = null;

    function abrirModal(operadorId) {
        operadorAtual = operadoresMap.get(Number(operadorId)) || null;
        if (!operadorAtual) return;
        modalNome.textContent = operadorAtual.nome;
        modalData.value = operadorAtual.data_contratacao || '';
        modalSkillTef.checked = !!operadorAtual.tef;
        modalSkillTrocaRegime.checked = !!operadorAtual.troca_regime;
        modal.hidden = false;
        modal.classList.add('is-open');
    }

    function fecharModal() {
        modal.classList.remove('is-open');
        modal.hidden = true;
        operadorAtual = null;
    }

    document.querySelectorAll('.ops-edit-btn').forEach(btnEdit => {
        btnEdit.addEventListener('click', () => abrirModal(btnEdit.dataset.opId));
    });

    document.getElementById('modal-cancelar').addEventListener('click', fecharModal);
    modal.addEventListener('click', event => {
        if (event.target === modal) fecharModal();
    });

    document.querySelector('.modal-card').addEventListener('click', event => {
        event.stopPropagation();
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            fecharModal();
        }
    });

    btnSalvar.addEventListener('click', async () => {
        if (!operadorAtual) return;

        btnSalvar.disabled = true;
        btnSalvar.textContent = 'Salvando...';

        try {
            const res = await fetch('api/operadores.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    operador_id: operadorAtual.id,
                    data_contratacao: modalData.value,
                    tef: modalSkillTef.checked,
                    troca_regime: modalSkillTrocaRegime.checked,
                }),
            });
            const json = await res.json();

            if (!res.ok || json.erro) {
                mostrarToast(json.erro || 'Erro ao salvar operador.', 'error');
                return;
            }

            mostrarToast('Operador atualizado.', 'success');
            setTimeout(() => window.location.reload(), 500);
        } catch (error) {
            mostrarToast('Falha ao comunicar com o servidor.', 'error');
        } finally {
            btnSalvar.disabled = false;
            btnSalvar.textContent = 'Salvar';
        }
    });
<?php endif; ?>
})();
</script>
</body>
</html>
