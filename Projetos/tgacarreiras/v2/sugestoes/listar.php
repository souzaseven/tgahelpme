<?php
// ============================================================
//  listar.php — TGA Carreiras · Sugestões
//  Dashboard + Listagem com filtros, badges e paginação
// ============================================================
session_start();
require_once __DIR__ . '/backend/conexao.php';

if (empty($_SESSION['usuario_id'])) { header('Location: login.php?redirect=listar.php'); exit; }
if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) { header('Location: index.php'); exit; }

$nomeUsuario    = $_SESSION['usuario_nome'] ?? 'Usuário';
$iniciaisAvatar = strtoupper(substr($nomeUsuario, 0, 2));

$statusMap = [
    'aberto'             => ['label'=>'Aberto',            'cor'=>'c-aberto',  'badge'=>'status-aberto',            'icon'=>'🔵'],
    'em_analise'         => ['label'=>'Em Análise',         'cor'=>'c-analise', 'badge'=>'status-em_analise',         'icon'=>'🟡'],
    'em_desenvolvimento' => ['label'=>'Em Desenvolvimento', 'cor'=>'c-dev',     'badge'=>'status-em_desenvolvimento', 'icon'=>'🟣'],
    'implementado'       => ['label'=>'Implementado',       'cor'=>'c-impl',    'badge'=>'status-implementado',       'icon'=>'🟢'],
    'rejeitado'          => ['label'=>'Rejeitado',          'cor'=>'c-rej',     'badge'=>'status-rejeitado',          'icon'=>'🔴'],
];
$prioMap = [
    'baixa'=>['label'=>'Baixa','badge'=>'prio-baixa'],'media'=>['label'=>'Média','badge'=>'prio-media'],
    'alta'=>['label'=>'Alta','badge'=>'prio-alta'],'urgente'=>['label'=>'Urgente','badge'=>'prio-urgente'],
];
$tipoMap = ['interno'=>'Interno','cliente'=>'Cliente','empresa'=>'Empresa'];

$status     = isset($statusMap[$_GET['status']??''])   ? $_GET['status']     : '';
$prioridade = isset($prioMap[$_GET['prioridade']??'']) ? $_GET['prioridade'] : '';
$tipo       = isset($tipoMap[$_GET['tipo']??''])       ? $_GET['tipo']       : '';
$busca      = trim($_GET['busca'] ?? '');

$sql = "SELECT * FROM sugestoes_tgacarreiras WHERE 1=1"; $params = [];
if ($status)     { $sql .= " AND status = :s";     $params[':s'] = $status; }
if ($prioridade) { $sql .= " AND prioridade = :p";  $params[':p'] = $prioridade; }
if ($tipo)       { $sql .= " AND tipo = :t";        $params[':t'] = $tipo; }
if ($busca)      { $sql .= " AND (titulo LIKE :b1 OR descricao LIKE :b2)"; $params[':b1'] = $params[':b2'] = "%$busca%"; }
$sql .= " ORDER BY data_criacao DESC";

$stmt = $pdo->prepare($sql); $stmt->execute($params);
$sugestoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($sugestoes);

$stC = $pdo->query("SELECT status, COUNT(*) as t FROM sugestoes_tgacarreiras GROUP BY status");
$contagens = [];
foreach ($stC->fetchAll(PDO::FETCH_ASSOC) as $r) $contagens[$r['status']] = (int)$r['t'];

$msgParam = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><title>Sugestões — TGA Carreiras</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="http://tgameajuda.com/Projetos/tgacarreiras/img/icone_logo.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@500;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;white-space:nowrap}
        .badge::before{content:'';width:6px;height:6px;border-radius:50%;flex-shrink:0}
        .prio-baixa{background:var(--success-dim);color:var(--success)}.prio-baixa::before{background:var(--success)}
        .prio-media{background:var(--warn-dim);color:var(--warn)}.prio-media::before{background:var(--warn)}
        .prio-alta{background:rgba(249,115,22,.12);color:#f97316}.prio-alta::before{background:#f97316}
        .prio-urgente{background:var(--danger-dim);color:var(--danger)}.prio-urgente::before{background:var(--danger)}
        .status-aberto{background:var(--accent-dim);color:var(--accent-bright)}.status-aberto::before{background:var(--accent)}
        .status-em_analise{background:var(--warn-dim);color:var(--warn)}.status-em_analise::before{background:var(--warn)}
        .status-em_desenvolvimento{background:rgba(99,102,241,.12);color:#818cf8}.status-em_desenvolvimento::before{background:#818cf8}
        .status-implementado{background:var(--success-dim);color:var(--success)}.status-implementado::before{background:var(--success)}
        .status-rejeitado{background:var(--danger-dim);color:var(--danger)}.status-rejeitado::before{background:var(--danger)}

        .summary-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:24px;animation:fadeDown .4s ease both}
        .summary-card{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius);padding:16px;display:flex;flex-direction:column;gap:6px;transition:var(--transition);cursor:pointer;text-decoration:none;position:relative;overflow:hidden}
        .summary-card::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;opacity:0;transition:var(--transition)}
        .summary-card:hover{border-color:var(--border-h);transform:translateY(-2px)}.summary-card:hover::after{opacity:1}
        .summary-card.active-filter{border-color:var(--accent);background:var(--accent-dim)}
        .summary-label{font-size:10.5px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--muted)}
        .summary-value{font-family:var(--font-head);font-size:28px;font-weight:700;line-height:1}
        .c-aberto .summary-value{color:var(--accent-bright)}.c-aberto::after{background:var(--accent)}
        .c-analise .summary-value{color:var(--warn)}.c-analise::after{background:var(--warn)}
        .c-dev .summary-value{color:#3b82f6}.c-dev::after{background:#3b82f6}
        .c-impl .summary-value{color:var(--success)}.c-impl::after{background:var(--success)}
        .c-rej .summary-value{color:var(--danger)}.c-rej::after{background:var(--danger)}

        .filter-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:18px;animation:fadeDown .4s ease both .05s}
        .filter-bar .select-wrapper{min-width:140px;flex:1}
        .search-wrap{position:relative;flex:2;min-width:200px}
        .search-wrap svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);width:14px;height:14px;color:var(--muted);pointer-events:none;transition:var(--transition)}
        .search-wrap:focus-within svg{color:var(--accent)}
        .search-wrap input{padding-left:36px}
        .filter-actions{display:flex;gap:8px;flex-shrink:0}

        .table-card{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;animation:fadeUp .45s ease both .1s}
        .table-header{display:flex;align-items:center;justify-content:space-between;padding:14px 22px;border-bottom:1px solid var(--border);gap:12px;flex-wrap:wrap}
        .table-title{font-family:var(--font-head);font-size:14px;font-weight:600;color:var(--text);display:flex;align-items:center;gap:8px}
        .table-count{font-size:12px;color:var(--muted);background:var(--surface);border:1px solid var(--border);padding:2px 9px;border-radius:20px}
        .table-wrapper{overflow-x:auto}
        table{width:100%;border-collapse:collapse;min-width:620px}
        thead tr{background:var(--surface)}
        th{padding:10px 16px;text-align:left;font-size:10.5px;font-weight:600;letter-spacing:.09em;text-transform:uppercase;color:var(--muted);white-space:nowrap;border-bottom:1px solid var(--border)}
        th:first-child{padding-left:22px}th:last-child{padding-right:22px;text-align:center}
        tbody tr{border-bottom:1px solid var(--border);transition:var(--transition);cursor:pointer}
        tbody tr:last-child{border-bottom:none}tbody tr:hover{background:var(--surface)}
        td{padding:12px 16px;font-size:13.5px;color:var(--text);vertical-align:middle}
        td:first-child{padding-left:22px}td:last-child{padding-right:22px;text-align:center}
        .td-id{font-family:var(--font-head);font-size:12px;font-weight:600;color:var(--muted);white-space:nowrap}
        .td-titulo{font-weight:500;max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .td-tipo{font-size:12px;color:var(--text-sec)}
        .td-data{font-size:12px;color:var(--muted);white-space:nowrap;font-variant-numeric:tabular-nums}
        .btn-icon{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:var(--radius-xs);background:var(--surface);border:1px solid var(--border);color:var(--muted);cursor:pointer;transition:var(--transition);text-decoration:none}
        .btn-icon:hover{background:var(--surface-2);border-color:var(--border-h);color:var(--text)}.btn-icon svg{width:13px;height:13px}
        .empty-state{display:flex;flex-direction:column;align-items:center;gap:10px;padding:60px 20px;color:var(--muted);text-align:center}
        .empty-state svg{width:40px;height:40px;opacity:.25}.empty-state p{font-size:14px}.empty-state small{font-size:12px}
        .alert{padding:12px 16px;border-radius:var(--radius-sm);font-size:13px;font-weight:500;margin-bottom:18px;display:flex;align-items:center;gap:8px;animation:fadeDown .3s ease}
        .alert-ok{background:var(--success-dim);border:1px solid rgba(16,185,129,.25);color:var(--success)}
        @media(max-width:1100px){.summary-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(max-width:700px){.summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.filter-bar .select-wrapper{min-width:100%;flex:unset;width:100%}.search-wrap{min-width:100%}}
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand"><div class="brand-logo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5M2 12l10 5 10-5" stroke-opacity=".6"/></svg></div><span class="brand-name">TGA<em><br>Carreiras</em></span></div>
        <div class="nav-section"><p class="nav-label">Menu</p><nav>
            <a href="index.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>Nova Sugestão</a>
            <a href="listar.php" class="nav-item active"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>Sugestões<?php if($total):?><span class="nav-badge count"><?=$total?></span><?php endif;?></a>
        </nav></div>
        <div class="sidebar-footer"><div class="user-chip"><div class="avatar"><?=htmlspecialchars($iniciaisAvatar)?></div><div class="user-info"><p class="user-name"><?=htmlspecialchars($nomeUsuario)?></p><p class="user-role">Administrador</p></div></div>
        <a href="logout.php" class="nav-item" style="margin-top:8px;color:var(--danger)"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>Sair</a></div>
    </aside>

    <main class="content">
        <header class="page-header">
            <div class="page-title-group"><span class="page-eyebrow">Gestão · Admin</span><h1 class="page-title">Sugestões Registradas</h1><p class="page-subtitle">Acompanhe e gerencie todas as sugestões</p></div>
            <a href="index.php" class="btn-primary"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg><span>Nova Sugestão</span></a>
        </header>

        <?php if($msgParam==='criada'):?><div class="alert alert-ok">✅ Sugestão criada com sucesso!</div><?php endif;?>

        <!-- KPIs -->
        <div class="summary-grid">
            <?php foreach($statusMap as $key=>$info):?>
            <a href="?status=<?=urlencode($key)?><?=$prioridade?"&prioridade=$prioridade":''?><?=$tipo?"&tipo=$tipo":''?><?=$busca?"&busca=".urlencode($busca):''?>" class="summary-card <?=$info['cor']?><?=$status===$key?' active-filter':''?>">
                <span class="summary-label"><?=$info['icon']?> <?=$info['label']?></span>
                <span class="summary-value"><?=$contagens[$key]??0?></span>
            </a>
            <?php endforeach;?>
        </div>

        <!-- Filters -->
        <form method="GET" class="filter-bar" id="filterForm">
            <div class="search-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" name="busca" value="<?=htmlspecialchars($busca)?>" placeholder="Buscar por título ou descrição…" autocomplete="off"></div>
            <div class="select-wrapper"><select name="status"><option value="">Todos os status</option><?php foreach($statusMap as $k=>$i):?><option value="<?=$k?>" <?=$status===$k?'selected':''?>><?=$i['icon']?> <?=$i['label']?></option><?php endforeach;?></select><svg class="select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg></div>
            <div class="select-wrapper"><select name="prioridade"><option value="">Prioridades</option><?php foreach($prioMap as $k=>$i):?><option value="<?=$k?>" <?=$prioridade===$k?'selected':''?>><?=$i['label']?></option><?php endforeach;?></select><svg class="select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg></div>
            <div class="select-wrapper"><select name="tipo"><option value="">Tipos</option><?php foreach($tipoMap as $k=>$l):?><option value="<?=$k?>" <?=$tipo===$k?'selected':''?>><?=$l?></option><?php endforeach;?></select><svg class="select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg></div>
            <div class="filter-actions">
                <button class="btn-primary" type="submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>Filtrar</button>
                <?php if($status||$prioridade||$tipo||$busca):?><a href="listar.php" class="btn-ghost" title="Limpar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></a><?php endif;?>
            </div>
        </form>

        <!-- Table -->
        <div class="table-card">
            <div class="table-header"><span class="table-title">Resultados <span class="table-count"><?=$total?> <?=$total===1?'registro':'registros'?></span></span></div>
            <div class="table-wrapper">
                <table><thead><tr><th>ID</th><th>Título</th><th>Tipo</th><th>Prioridade</th><th>Status</th><th>Data</th><th></th></tr></thead>
                <tbody>
                <?php if($sugestoes): foreach($sugestoes as $s):
                    $sP=$prioMap[$s['prioridade']]??$prioMap['media'];
                    $sS=$statusMap[$s['status']]??$statusMap['aberto'];
                    $sT=$tipoMap[$s['tipo']]??ucfirst($s['tipo']);
                ?>
                <tr onclick="window.location='view.php?id=<?=(int)$s['id']?>'">
                    <td class="td-id">#<?=str_pad($s['id'],4,'0',STR_PAD_LEFT)?></td>
                    <td class="td-titulo"><?=htmlspecialchars($s['titulo'])?></td>
                    <td class="td-tipo"><?=htmlspecialchars($sT)?></td>
                    <td><span class="badge <?=$sP['badge']?>"><?=$sP['label']?></span></td>
                    <td><span class="badge <?=$sS['badge']?>"><?=$sS['label']?></span></td>
                    <td class="td-data"><?=date('d/m/Y',strtotime($s['data_criacao']))?><br><small style="font-size:10px;color:var(--muted)"><?=date('H:i',strtotime($s['data_criacao']))?></small></td>
                    <td onclick="event.stopPropagation()"><a href="view.php?id=<?=(int)$s['id']?>" class="btn-icon" title="Ver"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a></td>
                </tr>
                <?php endforeach; else:?>
                <tr><td colspan="7"><div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><p>Nenhuma sugestão encontrada</p><small>Ajuste os filtros ou <a href="index.php" style="color:var(--accent)">crie uma nova</a></small></div></td></tr>
                <?php endif;?>
                </tbody></table>
            </div>
        </div>
    </main>
</div>
<div id="toast" class="toast" aria-live="polite"></div>
<script>
document.querySelectorAll(".filter-bar select").forEach(s=>s.addEventListener("change",()=>document.getElementById("filterForm").submit()));
let st;document.querySelector("input[name='busca']")?.addEventListener("input",()=>{clearTimeout(st);st=setTimeout(()=>document.getElementById("filterForm").submit(),600)});
</script>
</body>
</html>