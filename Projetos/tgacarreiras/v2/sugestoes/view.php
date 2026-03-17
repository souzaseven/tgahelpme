<?php
// ============================================================
//  view.php — TGA Carreiras · Visualização de Sugestão
//  Detalhes completos + gestão de status + galeria + navegação
// ============================================================
session_start();
require_once __DIR__ . '/backend/conexao.php';

if (empty($_SESSION['usuario_id'])) { header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit; }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$usuario_id = (int)$_SESSION['usuario_id'];
$stU = $pdo->prepare("SELECT tipo,ativo,nome FROM usuarios_carreiras WHERE id=:id LIMIT 1");
$stU->execute([':id'=>$usuario_id]);
$usuario = $stU->fetch(PDO::FETCH_ASSOC);
if (!$usuario || (int)$usuario['ativo'] !== 1) { session_destroy(); header('Location: login.php'); exit; }

$nomeUsuario    = $usuario['nome'] ?? $_SESSION['usuario_nome'] ?? 'Usuário';
$iniciaisAvatar = strtoupper(substr($nomeUsuario, 0, 2));
$isAdmin        = !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
$podeAlterar    = ($usuario['tipo'] === 'administrador' || $isAdmin);

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: listar.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM sugestoes_tgacarreiras WHERE id=:id");
$stmt->execute([':id'=>$id]);
$sug = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sug) { header('Location: listar.php'); exit; }

// Navegação prev/next
$prev = $pdo->prepare("SELECT id FROM sugestoes_tgacarreiras WHERE id<:id ORDER BY id DESC LIMIT 1");
$prev->execute([':id'=>$id]); $prevId = $prev->fetchColumn();
$next = $pdo->prepare("SELECT id FROM sugestoes_tgacarreiras WHERE id>:id ORDER BY id ASC LIMIT 1");
$next->execute([':id'=>$id]); $nextId = $next->fetchColumn();

$statusMap = [
    'aberto'             => ['label'=>'Aberto',            'badge'=>'status-aberto',            'icon'=>'🔵'],
    'em_analise'         => ['label'=>'Em Análise',         'badge'=>'status-em_analise',         'icon'=>'🟡'],
    'em_desenvolvimento' => ['label'=>'Em Desenvolvimento', 'badge'=>'status-em_desenvolvimento', 'icon'=>'🟣'],
    'implementado'       => ['label'=>'Implementado',       'badge'=>'status-implementado',       'icon'=>'🟢'],
    'rejeitado'          => ['label'=>'Rejeitado',          'badge'=>'status-rejeitado',          'icon'=>'🔴'],
];
$prioMap = [
    'baixa'=>['label'=>'Baixa','badge'=>'prio-baixa','icon'=>'🟢'],
    'media'=>['label'=>'Média','badge'=>'prio-media','icon'=>'🟡'],
    'alta'=>['label'=>'Alta','badge'=>'prio-alta','icon'=>'🟠'],
    'urgente'=>['label'=>'Urgente','badge'=>'prio-urgente','icon'=>'🔴'],
];
$tipoMap = ['interno'=>'Interno','cliente'=>'Cliente','empresa'=>'Empresa'];

$prio   = $prioMap[$sug['prioridade']] ?? $prioMap['media'];
$st     = $statusMap[$sug['status']] ?? $statusMap['aberto'];
$stKey  = array_key_exists($sug['status'], $statusMap) ? $sug['status'] : 'aberto';
$idFmt  = str_pad($sug['id'], 4, '0', STR_PAD_LEFT);
$titulo = htmlspecialchars($sug['titulo']);
$tipoL  = $tipoMap[$sug['tipo']] ?? ucfirst($sug['tipo']);
$moduloL= !empty($sug['modulo']) ? htmlspecialchars($sug['modulo']) : 'Geral';

// Anexos
$anexos = [];
if (!empty($sug['anexos'])) {
    $decoded = json_decode($sug['anexos'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $arq) {
            $ext = strtolower(pathinfo($arq, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','gif','pdf']))
                $anexos[] = ['path'=>'uploads/anexos/'.basename($arq), 'ext'=>$ext, 'name'=>basename($arq)];
        }
    }
}

// Link
$linkExt = '';
if (!empty($sug['link_externo'])) {
    $url = filter_var($sug['link_externo'], FILTER_VALIDATE_URL);
    if ($url && preg_match('/^https?:\/\//i', $url)) $linkExt = $url;
}

$msgParam = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><title>Sugestão #<?=$idFmt?> — TGA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="http://tgameajuda.com/Projetos/tgacarreiras/img/icone_logo.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@500;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .badge{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:20px;font-size:11.5px;font-weight:600;white-space:nowrap}
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
        .badge-tipo{background:var(--surface);color:var(--text-sec);border:1px solid var(--border)}

        .view-grid{display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start;animation:fadeUp .4s ease both .1s}
        .vcard{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
        .vcard-bar{height:3px;background:linear-gradient(90deg,var(--accent),#6366f1,#38bdf8)}
        .vcard-body{padding:26px 28px}
        .view-eyebrow{font-family:var(--font-head);font-size:11px;font-weight:700;letter-spacing:.12em;color:var(--accent);text-transform:uppercase;margin-bottom:8px;display:block}
        .view-title{font-family:var(--font-head);font-size:20px;font-weight:700;color:var(--text);line-height:1.3;margin-bottom:14px}
        .view-badges{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:24px}
        .section-label{display:flex;align-items:center;gap:7px;font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid var(--border)}
        .section-label svg{width:13px;height:13px;color:var(--accent);flex-shrink:0}
        .view-desc{font-size:14px;line-height:1.75;color:var(--text-sec);white-space:pre-wrap;word-break:break-word}
        .link-ext{display:inline-flex;align-items:center;gap:6px;margin-top:18px;padding:8px 14px;border-radius:var(--radius-sm);background:var(--surface);border:1px solid var(--border);color:var(--accent-bright);font-size:12.5px;transition:var(--transition);word-break:break-all;max-width:100%}
        .link-ext:hover{background:var(--surface-2);border-color:var(--border-h)}
        .link-ext svg{width:13px;height:13px;flex-shrink:0}

        .gallery-grid{display:flex;flex-wrap:wrap;gap:10px}
        .gallery-item{position:relative;width:80px;height:80px;border-radius:var(--radius-sm);overflow:hidden;border:1px solid var(--border);cursor:pointer;transition:var(--transition);flex-shrink:0}
        .gallery-item:hover{border-color:var(--accent);transform:scale(1.04);box-shadow:0 0 12px rgba(46,124,246,.25)}
        .gallery-item img{width:100%;height:100%;object-fit:cover;display:block}
        .gallery-item.pdf-thumb{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;background:var(--surface);font-size:11px;color:var(--text-sec);text-decoration:none}
        .gallery-zoom{position:absolute;inset:0;background:rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;opacity:0;transition:var(--transition)}
        .gallery-zoom svg{width:18px;height:18px;color:#fff}
        .gallery-item:hover .gallery-zoom{opacity:1}

        .side-stack{display:flex;flex-direction:column;gap:12px}
        .side-block{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
        .side-block-header{padding:10px 16px;background:var(--surface);border-bottom:1px solid var(--border);font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--muted)}
        .side-rows{padding:4px 0}
        .side-row{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:9px 16px;border-bottom:1px solid var(--border)}
        .side-row:last-child{border-bottom:none}
        .side-row-label{font-size:11.5px;color:var(--muted);flex-shrink:0}
        .side-row-value{font-size:13px;font-weight:500;color:var(--text);text-align:right;word-break:break-word}
        .status-form{padding:14px 16px}
        .status-form>label{font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:8px;display:block}
        .status-form .select-wrapper{margin-bottom:10px}
        .btn-full{width:100%;justify-content:center}

        .nav-pag{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:20px;animation:fadeUp .4s ease both .2s}
        .nav-pag a,.nav-pag span{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:var(--radius-sm);font-size:13px;font-weight:500;transition:var(--transition);text-decoration:none}
        .nav-pag a{background:var(--card-bg);border:1px solid var(--border);color:var(--text-sec)}
        .nav-pag a:hover{background:var(--surface);border-color:var(--border-h);color:var(--text)}
        .nav-pag>span{color:var(--muted);font-size:12px}
        .nav-pag svg{width:14px;height:14px}

        .btn-danger{background:var(--danger-dim);border:1px solid rgba(239,68,68,.4);color:var(--danger);display:inline-flex;align-items:center;gap:6px;padding:9px 14px;border-radius:var(--radius-sm);font-weight:600;cursor:pointer;transition:var(--transition);font-family:var(--font-body);font-size:13px}
        .btn-danger:hover{background:rgba(239,68,68,.25);transform:translateY(-2px)}

        .lightbox{position:fixed;inset:0;background:rgba(0,0,0,.88);backdrop-filter:blur(6px);z-index:9999;display:flex;align-items:center;justify-content:center;padding:24px;opacity:0;pointer-events:none;transition:opacity .25s}
        .lightbox.open{opacity:1;pointer-events:auto}
        .lightbox-inner{position:relative;max-width:90vw;max-height:88vh}
        .lightbox-inner img{max-width:100%;max-height:88vh;border-radius:var(--radius);box-shadow:0 24px 60px rgba(0,0,0,.8);object-fit:contain;display:block}
        .lightbox-close{position:fixed;top:20px;right:20px;width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;transition:var(--transition);z-index:10000}
        .lightbox-close:hover{background:rgba(255,255,255,.2)}
        .lightbox-caption{position:fixed;bottom:16px;left:50%;transform:translateX(-50%);font-size:12px;color:rgba(255,255,255,.5)}

        .tga-toast{position:fixed;bottom:28px;right:28px;z-index:99999;display:flex;align-items:center;gap:10px;padding:13px 18px;border-radius:10px;font-size:13.5px;font-weight:500;color:#fff;box-shadow:var(--shadow-lg);border:1px solid transparent;pointer-events:none;opacity:0;transform:translateY(12px);transition:opacity .3s,transform .3s;min-width:220px;max-width:380px}
        .tga-toast.show{opacity:1;transform:translateY(0)}
        .tga-toast.success{background:rgba(16,185,129,.15);border-color:rgba(16,185,129,.32)}
        .tga-toast.error{background:rgba(239,68,68,.15);border-color:rgba(239,68,68,.32)}

        .alert{padding:12px 16px;border-radius:var(--radius-sm);font-size:13px;font-weight:500;margin-bottom:18px;display:flex;align-items:center;gap:8px;animation:fadeDown .3s ease}
        .alert-ok{background:var(--success-dim);border:1px solid rgba(16,185,129,.25);color:var(--success)}

        @media(max-width:860px){.view-grid{grid-template-columns:1fr}.tga-toast{bottom:16px;right:16px;left:16px;max-width:unset}}
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand"><div class="brand-logo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5M2 12l10 5 10-5" stroke-opacity=".6"/></svg></div><span class="brand-name">TGA<em><br>Carreiras</em></span></div>
        <div class="nav-section"><p class="nav-label">Menu</p><nav>
            <a href="index.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>Nova Sugestão</a>
            <a href="listar.php" class="nav-item active"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>Sugestões</a>
        </nav></div>
        <div class="sidebar-footer"><div class="user-chip"><div class="avatar"><?=htmlspecialchars($iniciaisAvatar)?></div><div class="user-info"><p class="user-name"><?=htmlspecialchars($nomeUsuario)?></p><p class="user-role"><?=$podeAlterar?'Admin':'Colaborador'?></p></div></div>
        <a href="logout.php" class="nav-item" style="margin-top:8px;color:var(--danger)"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>Sair</a></div>
    </aside>

    <main class="content">
        <header class="page-header" style="animation:fadeDown .4s ease both">
            <div class="page-title-group">
                <span class="page-eyebrow">Detalhes da Sugestão</span>
                <h1 class="page-title">Sugestão <span style="color:var(--accent)">#<?=$idFmt?></span></h1>
                <p class="page-subtitle">Registro completo e acompanhamento</p>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-shrink:0;flex-wrap:wrap">
                <?php if($prevId):?><a href="view.php?id=<?=$prevId?>" class="btn-ghost" title="Anterior"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></a><?php endif;?>
                <?php if($nextId):?><a href="view.php?id=<?=$nextId?>" class="btn-ghost" title="Próxima"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></a><?php endif;?>
                <a href="listar.php" class="btn-ghost"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>Voltar</a>
            </div>
        </header>

        <?php if($msgParam==='criada'):?><div class="alert alert-ok">✅ Sugestão criada com sucesso!</div><?php endif;?>

        <div class="view-grid">
            <!-- COLUNA PRINCIPAL -->
            <div style="display:flex;flex-direction:column;gap:16px">
                <div class="vcard"><div class="vcard-bar"></div><div class="vcard-body">
                    <span class="view-eyebrow"><?=$prio['icon']?> <?=$prio['label']?> · <?=htmlspecialchars($tipoL)?> · <?=$moduloL?></span>
                    <h2 class="view-title"><?=$titulo?></h2>
                    <div class="view-badges">
                        <span class="badge <?=$prio['badge']?>"><?=$prio['label']?></span>
                        <span class="badge <?=$st['badge']?>" id="badgeStatus"><?=$st['label']?></span>
                        <span class="badge badge-tipo"><?=htmlspecialchars($tipoL)?></span>
                    </div>
                    <div class="section-label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/></svg>Descrição</div>
                    <div class="view-desc"><?=nl2br(htmlspecialchars($sug['descricao']))?></div>
                    <?php if($linkExt):?>
                    <a href="<?=htmlspecialchars($linkExt)?>" class="link-ext" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg><?=htmlspecialchars($linkExt)?></a>
                    <?php endif;?>
                </div></div>

                <?php if($anexos):?>
                <div class="vcard"><div class="vcard-body">
                    <div class="section-label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>Anexos <span style="color:var(--muted);font-weight:400">(<?=count($anexos)?>)</span></div>
                    <div class="gallery-grid">
                        <?php foreach($anexos as $a):?>
                        <?php if($a['ext']==='pdf'):?>
                        <a href="<?=htmlspecialchars($a['path'])?>" target="_blank" class="gallery-item pdf-thumb" title="<?=htmlspecialchars($a['name'])?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:24px;height:24px;color:var(--danger)"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>PDF</a>
                        <?php else:?>
                        <div class="gallery-item" onclick="openLB('<?=htmlspecialchars($a['path'])?>','<?=htmlspecialchars($a['name'])?>')" title="Ampliar">
                            <img src="<?=htmlspecialchars($a['path'])?>" alt="<?=htmlspecialchars($a['name'])?>" loading="lazy">
                            <div class="gallery-zoom"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></div>
                        </div>
                        <?php endif; endforeach;?>
                    </div>
                </div></div>
                <?php endif;?>
            </div>

            <!-- SIDEBAR -->
            <div class="side-stack">
                <div class="side-block"><div class="side-block-header">Informações</div><div class="side-rows">
                    <div class="side-row"><span class="side-row-label">ID</span><span class="side-row-value" style="font-family:var(--font-head);color:var(--accent)">#<?=$idFmt?></span></div>
                    <div class="side-row"><span class="side-row-label">Solicitante</span><span class="side-row-value"><?=htmlspecialchars($sug['nome_solicitante']?:'Não informado')?></span></div>
                    <div class="side-row"><span class="side-row-label">Módulo</span><span class="side-row-value"><?=$moduloL?></span></div>
                    <div class="side-row"><span class="side-row-label">Tipo</span><span class="side-row-value"><?=htmlspecialchars($tipoL)?></span></div>
                    <div class="side-row"><span class="side-row-label">Criado em</span><span class="side-row-value" style="font-size:12px"><?=date('d/m/Y',strtotime($sug['data_criacao']))?><br><span style="color:var(--muted);font-size:11px"><?=date('H:i',strtotime($sug['data_criacao']))?></span></span></div>
                    <?php $att=$sug['atualizado_em']??$sug['data_atualizacao']??''; if($att && strtotime($att)>strtotime($sug['data_criacao'])):?>
                    <div class="side-row"><span class="side-row-label">Atualizado</span><span class="side-row-value" style="font-size:12px"><?=date('d/m/Y H:i',strtotime($att))?></span></div>
                    <?php endif;?>
                </div></div>

                <?php if($podeAlterar):?>
                <div class="side-block"><div class="side-block-header">Alterar Status</div>
                    <form class="status-form" id="statusForm" method="POST" action="./backend/atualizar_status.php">
                        <input type="hidden" name="id" value="<?=(int)$sug['id']?>">
                        <input type="hidden" name="csrf_token" id="csrfToken" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                        <label for="novo_status">Novo status</label>
                        <div class="select-wrapper">
                            <select id="novo_status" name="status">
                                <?php foreach($statusMap as $k=>$i):?><option value="<?=$k?>" <?=$stKey===$k?'selected':''?>><?=$i['icon']?> <?=$i['label']?></option><?php endforeach;?>
                            </select>
                            <svg class="select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                        <button type="submit" class="btn-primary btn-full" id="btnSave"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg><span id="btnLbl">Salvar Status</span></button>
                    </form>
                </div>
                <?php endif;?>

                <div class="side-block"><div class="side-block-header">Ações</div>
                    <div style="padding:12px 16px;display:flex;flex-direction:column;gap:8px">
                        <a href="index.php?clone=<?=(int)$sug['id']?>" class="btn-ghost btn-full" style="justify-content:center;font-size:13px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>Duplicar</a>
                        <a href="listar.php" class="btn-ghost btn-full" style="justify-content:center;font-size:13px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>Ver todas</a>
                        <?php if($podeAlterar):?>
                        <button id="btnExcluir" class="btn-danger btn-full" style="justify-content:center;font-size:13px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>Excluir</button>
                        <?php endif;?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Paginação -->
        <div class="nav-pag">
            <?php if($prevId):?><a href="view.php?id=<?=$prevId?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>Anterior</a><?php else:?><span></span><?php endif;?>
            <span>#<?=$idFmt?></span>
            <?php if($nextId):?><a href="view.php?id=<?=$nextId?>">Próximo<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></a><?php else:?><span></span><?php endif;?>
        </div>
    </main>
</div>

<!-- Lightbox -->
<div class="lightbox" id="lb"><button class="lightbox-close" onclick="closeLB()">✕</button><div class="lightbox-inner"><img id="lbImg" src="" alt=""></div><span class="lightbox-caption" id="lbCap"></span></div>

<!-- Toast -->
<div class="tga-toast" id="tgaToast"></div>

<script>
"use strict";
const toastEl=document.getElementById("tgaToast");
const ICONS={success:'✅',error:'❌',info:'ℹ️'};
function showToast(m,t="success",d=4500){if(!toastEl)return;toastEl.className=`tga-toast ${t}`;toastEl.innerHTML=`<span>${ICONS[t]||''}</span><span>${m}</span>`;void toastEl.offsetWidth;toastEl.classList.add("show");clearTimeout(toastEl._t);toastEl._t=setTimeout(()=>toastEl.classList.remove("show"),d)}

const STATUS_LABELS=<?=json_encode(array_map(fn($s)=>$s['label'],$statusMap))?>;
const STATUS_BADGE=<?=json_encode(array_map(fn($s)=>$s['badge'],$statusMap))?>;

const statusForm=document.getElementById("statusForm");
const csrfInput=document.getElementById("csrfToken");
const badgeStatus=document.getElementById("badgeStatus");

statusForm?.addEventListener("submit",async function(e){
    e.preventDefault();
    const ns=document.getElementById("novo_status")?.value;
    const btn=document.getElementById("btnSave");const lbl=document.getElementById("btnLbl");
    btn.disabled=true;btn.classList.add("loading");lbl.textContent="Salvando…";
    try{
        const res=await fetch(this.action,{method:"POST",body:new FormData(this)});
        let data;try{data=await res.json()}catch{throw new Error("Resposta inválida")}
        if(data.success){
            if(badgeStatus&&ns){[...badgeStatus.classList].filter(c=>c.startsWith("status-")).forEach(c=>badgeStatus.classList.remove(c));badgeStatus.classList.add(STATUS_BADGE[ns]||"");badgeStatus.textContent=STATUS_LABELS[ns]||ns}
            if(data.novo_csrf&&csrfInput)csrfInput.value=data.novo_csrf;
            showToast(data.message||"Status atualizado!","success");
        }else showToast(data.message||"Erro.","error");
    }catch(err){showToast(err.message||"Falha de conexão.","error")}
    finally{btn.disabled=false;btn.classList.remove("loading");lbl.textContent="Salvar Status"}
});

document.getElementById("btnExcluir")?.addEventListener("click",async function(){
    if(!confirm("⚠️ Excluir esta sugestão?\nEssa ação não pode ser desfeita."))return;
    try{
        const fd=new FormData();fd.append("id","<?=(int)$sug['id']?>");fd.append("csrf_token",csrfInput.value);
        const res=await fetch("./backend/excluir_sugestao.php",{method:"POST",body:fd});
        const data=await res.json();
        if(data.success){showToast("Excluída!","success");setTimeout(()=>window.location.href="listar.php",1500)}
        else showToast(data.message||"Erro.","error");
    }catch{showToast("Falha de conexão.","error")}
});

// Lightbox
const lb=document.getElementById("lb"),lbImg=document.getElementById("lbImg"),lbCap=document.getElementById("lbCap");
function openLB(s,c){lbImg.src=s;lbImg.alt=c;lbCap.textContent=c;lb.classList.add("open");document.body.style.overflow="hidden"}
function closeLB(){lb.classList.remove("open");document.body.style.overflow="";setTimeout(()=>lbImg.src="",260)}
lb.addEventListener("click",e=>{if(e.target===lb)closeLB()});
document.addEventListener("keydown",e=>{if(e.key==="Escape"&&lb.classList.contains("open"))closeLB()});
</script>
</body>
</html>