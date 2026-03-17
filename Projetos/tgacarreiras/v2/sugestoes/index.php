<?php
// ============================================================
//  index.php — TGA Carreiras · Nova Sugestão
//  Formulário wizard 4 etapas com backend completo
// ============================================================
session_start();
require_once __DIR__ . '/backend/conexao.php';

if (empty($_SESSION['usuario_id'])) { header('Location: login.php?redirect=index.php'); exit; }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$nomeUsuario    = $_SESSION['usuario_nome'] ?? 'Usuário';
$iniciaisAvatar = strtoupper(substr($nomeUsuario, 0, 2));
$isAdmin        = !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
$msgOk = $msgErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $msgErr = 'Token inválido. Recarregue a página.';
    } else {
        $tipo       = $_POST['tipo'] ?? 'interno';
        $prioridade = $_POST['prioridade'] ?? 'media';
        $modulo     = trim($_POST['modulo'] ?? '');
        $titulo     = trim($_POST['titulo'] ?? '');
        $descricao  = trim($_POST['descricao'] ?? '');
        $nome       = trim($_POST['nome_solicitante'] ?? $nomeUsuario);
        $link       = trim($_POST['link_externo'] ?? '');

        if (!$titulo || !$descricao) {
            $msgErr = 'Título e descrição são obrigatórios.';
        } else {
            try {
                $uploadDir = __DIR__ . '/uploads/anexos/';
                if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
                $uploaded = [];
                if (!empty($_FILES['anexos']['name'][0])) {
                    foreach ($_FILES['anexos']['name'] as $i => $nomeArq) {
                        if ($_FILES['anexos']['error'][$i] !== UPLOAD_ERR_OK) continue;
                        if ($_FILES['anexos']['size'][$i] > 10 * 1024 * 1024) continue;
                        $ext = strtolower(pathinfo($nomeArq, PATHINFO_EXTENSION));
                        if (!in_array($ext, ['jpg','jpeg','png','gif','webp','pdf'])) continue;
                        $novoNome = uniqid('sug_') . '.' . $ext;
                        if (move_uploaded_file($_FILES['anexos']['tmp_name'][$i], $uploadDir . $novoNome))
                            $uploaded[] = $novoNome;
                    }
                }
                $anexosJson = $uploaded ? json_encode($uploaded) : '[]';

                $stmt = $pdo->prepare("INSERT INTO sugestoes_tgacarreiras (tipo, prioridade, modulo, titulo, descricao, nome_solicitante, link_externo, anexos, status, data_criacao) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'aberto', NOW())");
                $stmt->execute([$tipo, $prioridade, $modulo, $titulo, $descricao, $nome, $link, $anexosJson]);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header("Location: view.php?id=" . $pdo->lastInsertId() . "&msg=criada"); exit;
            } catch (Throwable $e) { $msgErr = 'Erro ao salvar: ' . $e->getMessage(); }
        }
    }
}

$clone = null;
if (!empty($_GET['clone'])) {
    $stC = $pdo->prepare("SELECT * FROM sugestoes_tgacarreiras WHERE id = ?");
    $stC->execute([(int)$_GET['clone']]);
    $clone = $stC->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"><title>Nova Sugestão — TGA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="http://tgameajuda.com/Projetos/tgacarreiras/img/icone_logo.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@500;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .content{max-width:820px}
        .card-inner{padding:28px 30px}
        .type-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
        .type-card{padding:16px;border-radius:var(--radius);border:1.5px solid var(--border);background:var(--surface);text-align:center;cursor:pointer;transition:var(--transition)}
        .type-card:hover{border-color:var(--border-h);transform:translateY(-2px)}
        .type-card.sel{border-color:var(--accent);background:var(--accent-dim);box-shadow:0 0 20px rgba(46,124,246,.1)}
        .type-card input{display:none}
        .type-icon{font-size:1.5rem;display:block;margin-bottom:6px}
        .type-label{font-size:12.5px;font-weight:600;color:var(--text)}
        .type-desc{font-size:10.5px;color:var(--muted);margin-top:2px}
        .prio-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
        .prio-card{padding:10px;border-radius:var(--radius-sm);border:1.5px solid var(--border);background:var(--surface);text-align:center;cursor:pointer;transition:var(--transition)}
        .prio-card:hover{border-color:var(--border-h);transform:translateY(-1px)}
        .prio-card.sel{border-color:var(--sel-c);background:var(--sel-bg);transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,.3)}
        .prio-card input{display:none}
        .prio-dot{font-size:1.15rem;display:block;margin-bottom:3px}
        .prio-label{font-size:11.5px;font-weight:600;color:var(--text-sec)}
        .rev-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px}
        .rev-item{padding:12px;background:var(--surface);border-radius:var(--radius-sm);border:1px solid var(--border)}
        .rev-lbl{font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--muted)}
        .rev-val{font-size:13px;font-weight:500;color:var(--text);margin-top:3px}
        .rev-full{grid-column:1/-1}
        .rev-desc{padding:14px;background:var(--surface);border-radius:var(--radius-sm);border:1px solid var(--border);font-size:13px;color:var(--text-sec);white-space:pre-wrap;line-height:1.7;max-height:200px;overflow-y:auto}
        .step-panel{display:none;animation:fadeUp .3s ease}
        .step-panel.active{display:block}
        .tips{margin-top:24px;padding:18px;background:var(--card-bg);border:1px solid var(--border);border-radius:var(--radius)}
        .tips-title{font-family:var(--font-head);font-size:13px;font-weight:700;color:var(--text);margin-bottom:10px;display:flex;align-items:center;gap:6px}
        .tip{display:flex;gap:8px;padding:4px 0;font-size:12.5px;color:var(--text-sec)}
        .tip-i{flex-shrink:0;font-size:14px;margin-top:1px}
        .alert{padding:12px 16px;border-radius:var(--radius-sm);font-size:13px;font-weight:500;margin-bottom:18px;display:flex;align-items:center;gap:8px;animation:fadeDown .3s ease}
        .alert-ok{background:var(--success-dim);border:1px solid rgba(16,185,129,.25);color:var(--success)}
        .alert-err{background:var(--danger-dim);border:1px solid rgba(239,68,68,.25);color:var(--danger)}
        @media(max-width:600px){.type-grid{grid-template-columns:1fr}.prio-grid{grid-template-columns:1fr 1fr}.rev-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar" id="sidebar">
        <div class="brand"><div class="brand-logo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5M2 12l10 5 10-5" stroke-opacity=".6"/></svg></div><span class="brand-name">TGA<em><br>Carreiras</em></span></div>
        <div class="nav-section"><p class="nav-label">Menu</p><nav>
            <a href="index.php" class="nav-item active"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>Nova Sugestão<span class="nav-badge">+</span></a>
            <a href="listar.php" class="nav-item"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>Sugestões</a>
        </nav></div>
        <div class="sidebar-footer"><div class="user-chip"><div class="avatar"><?= htmlspecialchars($iniciaisAvatar) ?></div><div class="user-info"><p class="user-name"><?= htmlspecialchars($nomeUsuario) ?></p><p class="user-role"><?= $isAdmin ? 'Admin' : 'Colaborador' ?></p></div></div>
        <a href="logout.php" class="nav-item" style="margin-top:8px;color:var(--danger)"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>Sair</a></div>
    </aside>
    <main class="content">
        <header class="page-header"><div class="page-title-group"><span class="page-eyebrow">Criação</span><h1 class="page-title">Nova Sugestão</h1><p class="page-subtitle">Registre sua ideia ou melhoria</p></div><div class="status-pill"><span class="status-dot"></span>RASCUNHO</div></header>
        <?php if ($msgErr): ?><div class="alert alert-err">❌ <?= htmlspecialchars($msgErr) ?></div><?php endif; ?>

        <div class="steps-bar">
            <div class="step active" data-s="1" onclick="goStep(1)"><div class="step-num">1</div><span>Classificação</span></div><div class="step-line"></div>
            <div class="step" data-s="2" onclick="goStep(2)"><div class="step-num">2</div><span>Detalhes</span></div><div class="step-line"></div>
            <div class="step" data-s="3" onclick="goStep(3)"><div class="step-num">3</div><span>Anexos</span></div><div class="step-line"></div>
            <div class="step" data-s="4" onclick="goStep(4)"><div class="step-num">4</div><span>Revisão</span></div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="mainForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <div class="card"><div class="card-accent-bar"></div><div class="card-inner">
            <!-- STEP 1 -->
            <div class="step-panel active" data-s="1"><fieldset class="form-section"><legend class="section-legend"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>Classificação</legend>
                <div class="form-group"><label>Tipo <span class="required">*</span></label><div class="type-grid" id="tG">
                    <label class="type-card sel"><input type="radio" name="tipo" value="interno" <?= (!$clone||($clone['tipo']??'')==='interno')?'checked':'' ?>><span class="type-icon">🏢</span><span class="type-label">Interno</span><span class="type-desc">Melhoria interna</span></label>
                    <label class="type-card"><input type="radio" name="tipo" value="cliente" <?= ($clone['tipo']??'')==='cliente'?'checked':'' ?>><span class="type-icon">👤</span><span class="type-label">Cliente</span><span class="type-desc">Pedido de cliente</span></label>
                    <label class="type-card"><input type="radio" name="tipo" value="empresa" <?= ($clone['tipo']??'')==='empresa'?'checked':'' ?>><span class="type-icon">🏛️</span><span class="type-label">Empresa</span><span class="type-desc">Demanda empresarial</span></label>
                </div></div>
                <div class="form-group" style="margin-top:18px"><label>Prioridade</label><div class="prio-grid" id="pG">
                    <label class="prio-card" style="--sel-c:var(--success);--sel-bg:var(--success-dim)"><input type="radio" name="prioridade" value="baixa" <?= ($clone['prioridade']??'')==='baixa'?'checked':'' ?>><span class="prio-dot">🟢</span><span class="prio-label">Baixa</span></label>
                    <label class="prio-card sel" style="--sel-c:var(--warn);--sel-bg:var(--warn-dim)"><input type="radio" name="prioridade" value="media" <?= (!$clone||($clone['prioridade']??'')==='media')?'checked':'' ?>><span class="prio-dot">🟡</span><span class="prio-label">Média</span></label>
                    <label class="prio-card" style="--sel-c:#f97316;--sel-bg:rgba(249,115,22,.1)"><input type="radio" name="prioridade" value="alta" <?= ($clone['prioridade']??'')==='alta'?'checked':'' ?>><span class="prio-dot">🟠</span><span class="prio-label">Alta</span></label>
                    <label class="prio-card" style="--sel-c:var(--danger);--sel-bg:var(--danger-dim)"><input type="radio" name="prioridade" value="urgente" <?= ($clone['prioridade']??'')==='urgente'?'checked':'' ?>><span class="prio-dot">🔴</span><span class="prio-label">Urgente</span></label>
                </div></div>
                <div class="form-group" style="margin-top:18px"><label for="modulo">Módulo</label><input type="text" id="modulo" name="modulo" placeholder="Ex: Login, Dashboard…" value="<?= htmlspecialchars($clone['modulo']??'') ?>"></div>
            </fieldset></div>
            <!-- STEP 2 -->
            <div class="step-panel" data-s="2"><fieldset class="form-section"><legend class="section-legend"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/></svg>Detalhes</legend>
                <div class="form-group"><label for="titulo">Título <span class="required">*</span><span class="char-hint" id="cT">0/100</span></label><input type="text" id="titulo" name="titulo" maxlength="100" placeholder="Descreva de forma objetiva…" value="<?= htmlspecialchars($clone['titulo']??'') ?>" required></div>
                <div class="form-group"><label for="descricao">Descrição <span class="required">*</span><span class="char-hint" id="cD">0/2000</span></label><textarea id="descricao" name="descricao" rows="8" maxlength="2000" placeholder="Problema, contexto e benefício esperado…" required><?= htmlspecialchars($clone['descricao']??'') ?></textarea></div>
                <div class="form-group"><label for="nome_solicitante">Seu nome</label><input type="text" id="nome_solicitante" name="nome_solicitante" value="<?= htmlspecialchars($clone['nome_solicitante']??$nomeUsuario) ?>"></div>
            </fieldset></div>
            <!-- STEP 3 -->
            <div class="step-panel" data-s="3"><fieldset class="form-section"><legend class="section-legend"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>Anexos</legend>
                <div class="row-2"><div class="form-group"><label for="link_externo">Link</label><div class="input-icon-wrap"><svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><input type="url" id="link_externo" name="link_externo" placeholder="https://…" value="<?= htmlspecialchars($clone['link_externo']??'') ?>"></div></div>
                <div class="form-group"><label>Imagens</label><label class="file-drop" id="fD" for="anexos"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg><span id="fL">Arraste ou clique</span><small>PNG, JPG, PDF — máx. 10MB</small><input type="file" id="anexos" name="anexos[]" multiple accept="image/*,.pdf" hidden></label><div id="fP" class="file-preview"></div></div></div>
            </fieldset></div>
            <!-- STEP 4 -->
            <div class="step-panel" data-s="4"><fieldset class="form-section"><legend class="section-legend"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Revisão Final</legend><div class="rev-grid" id="rG"></div><div id="rD"></div></fieldset></div>
        </div>
        <div class="form-footer">
            <button type="button" class="btn-ghost" id="bP" onclick="prevStep()" style="display:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><polyline points="15 18 9 12 15 6"/></svg>Voltar</button>
            <div style="display:flex;gap:8px;margin-left:auto">
                <button type="button" class="btn-primary" id="bN" onclick="nextStep()">Próximo<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><polyline points="9 18 15 12 9 6"/></svg></button>
                <button type="submit" class="btn-primary" id="bS" style="display:none;background:var(--success);box-shadow:0 4px 16px rgba(16,185,129,.3)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="width:14px;height:14px"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg><span>Enviar Sugestão</span></button>
            </div>
        </div></div>
        </form>
        <div class="tips"><div class="tips-title">💡 Dicas</div><div class="tip"><span class="tip-i">🎯</span>Seja específico no título — use verbos de ação</div><div class="tip"><span class="tip-i">📋</span>Na descrição: problema → solução → benefício</div><div class="tip"><span class="tip-i">📸</span>Anexe screenshots como referência</div><div class="tip"><span class="tip-i">🏷️</span>Urgente = apenas itens que impedem o trabalho</div></div>
    </main>
</div>
<script>
"use strict";let cur=1;const tot=4;
function goStep(n){if(n<1||n>tot)return;if(n>cur&&!val(cur))return;document.querySelectorAll('.steps-bar .step').forEach(s=>{const sn=+s.dataset.s;s.classList.remove('active');s.classList.toggle('done',sn<n);if(sn===n)s.classList.add('active')});document.querySelectorAll('.step-panel').forEach(p=>p.classList.toggle('active',+p.dataset.s===n));cur=n;upd();if(n===4)rev();document.querySelector('.card').scrollIntoView({behavior:'smooth',block:'start'})}
function nextStep(){goStep(cur+1)}function prevStep(){goStep(cur-1)}
function upd(){document.getElementById('bP').style.display=cur>1?'':'none';document.getElementById('bN').style.display=cur<tot?'':'none';document.getElementById('bS').style.display=cur===tot?'':'none'}
function val(n){if(n===2){const t=document.getElementById('titulo'),d=document.getElementById('descricao');if(!t.value.trim()){t.focus();t.style.borderColor='var(--danger)';t.style.animation='shake .4s';setTimeout(()=>{t.style.borderColor='';t.style.animation=''},500);return false}if(!d.value.trim()){d.focus();d.style.borderColor='var(--danger)';d.style.animation='shake .4s';setTimeout(()=>{d.style.borderColor='';d.style.animation=''},500);return false}}return true}
function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML}
function rev(){const t=document.querySelector('input[name="tipo"]:checked')?.value||'interno',p=document.querySelector('input[name="prioridade"]:checked')?.value||'media',tL={interno:'🏢 Interno',cliente:'👤 Cliente',empresa:'🏛️ Empresa'},pL={baixa:'🟢 Baixa',media:'🟡 Média',alta:'🟠 Alta',urgente:'🔴 Urgente'},m=document.getElementById('modulo').value.trim()||'—',ti=document.getElementById('titulo').value.trim(),de=document.getElementById('descricao').value.trim(),no=document.getElementById('nome_solicitante').value.trim()||'Anônimo',li=document.getElementById('link_externo').value.trim();document.getElementById('rG').innerHTML=`<div class="rev-item"><div class="rev-lbl">Tipo</div><div class="rev-val">${tL[t]}</div></div><div class="rev-item"><div class="rev-lbl">Prioridade</div><div class="rev-val">${pL[p]}</div></div><div class="rev-item"><div class="rev-lbl">Módulo</div><div class="rev-val">${esc(m)}</div></div><div class="rev-item"><div class="rev-lbl">Solicitante</div><div class="rev-val">${esc(no)}</div></div><div class="rev-item rev-full"><div class="rev-lbl">Título</div><div class="rev-val" style="font-size:15px">${esc(ti)}</div></div>${li?`<div class="rev-item rev-full"><div class="rev-lbl">Link</div><div class="rev-val"><a href="${esc(li)}" target="_blank" style="color:var(--accent-bright);word-break:break-all">${esc(li)}</a></div></div>`:''}`;document.getElementById('rD').innerHTML=`<div style="margin-top:8px"><label style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:6px;display:block">Descrição</label><div class="rev-desc">${esc(de)}</div></div>`}
document.querySelectorAll('#tG .type-card').forEach(c=>c.addEventListener('click',()=>{document.querySelectorAll('#tG .type-card').forEach(x=>x.classList.remove('sel'));c.classList.add('sel')}));
document.querySelectorAll('#pG .prio-card').forEach(c=>c.addEventListener('click',()=>{document.querySelectorAll('#pG .prio-card').forEach(x=>x.classList.remove('sel'));c.classList.add('sel')}));
document.getElementById('titulo')?.addEventListener('input',function(){document.getElementById('cT').textContent=this.value.length+'/100'});
document.getElementById('descricao')?.addEventListener('input',function(){document.getElementById('cD').textContent=this.value.length+'/2000'});
const fd=document.getElementById('fD'),fi=document.getElementById('anexos'),fp=document.getElementById('fP');
fd?.addEventListener('dragover',e=>{e.preventDefault();fd.classList.add('dragover')});fd?.addEventListener('dragleave',()=>fd.classList.remove('dragover'));
fd?.addEventListener('drop',e=>{e.preventDefault();fd.classList.remove('dragover');fi.files=e.dataTransfer.files;uf()});fi?.addEventListener('change',uf);
function uf(){fp.innerHTML='';if(!fi.files.length){document.getElementById('fL').textContent='Arraste ou clique';return}document.getElementById('fL').textContent=fi.files.length+' arquivo(s)';Array.from(fi.files).forEach(f=>{const r=new FileReader();r.onload=e=>{const d=document.createElement('div');d.style.cssText='width:64px;height:64px;border-radius:8px;overflow:hidden;border:1px solid var(--border)';d.innerHTML=`<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover">`;fp.appendChild(d)};r.readAsDataURL(f)})}
document.querySelectorAll('#tG .type-card').forEach(c=>{c.classList.toggle('sel',c.querySelector('input').checked)});
document.querySelectorAll('#pG .prio-card').forEach(c=>{c.classList.toggle('sel',c.querySelector('input').checked)});
</script>
</body></html>