<?php
session_start();

if (!isset($_SESSION['empresa_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../backend/conexao.php';

$empresaId   = (int) $_SESSION['empresa_id'];
$empresaNome = $_SESSION['empresa_nome'] ?? 'Empresa';

/* ── Dados da empresa ───────────────────────────────────── */
$stmtEmp = $pdo->prepare("SELECT * FROM empresas_carreiras WHERE id = ? LIMIT 1");
$stmtEmp->execute([$empresaId]);
$empresaData = $stmtEmp->fetch(PDO::FETCH_ASSOC);

/* ── Contadores sidebar ─────────────────────────────────── */
$stmtC = $pdo->prepare("SELECT COUNT(*) FROM vagas WHERE empresa_id = ?");
$stmtC->execute([$empresaId]);
$totalVagas = (int) $stmtC->fetchColumn();

$stmtP = $pdo->prepare("SELECT COUNT(*) FROM candidaturas c INNER JOIN vagas v ON c.vaga_id = v.id WHERE v.empresa_id = ? AND c.status = 'pendente'");
$stmtP->execute([$empresaId]);
$candPendentes = (int) $stmtP->fetchColumn();

/* ── Processar POST ─────────────────────────────────────── */
$msg = '';
$msgTipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publicar'])) {

    $titulo          = trim($_POST['titulo'] ?? '');
    $descricao       = trim($_POST['descricao'] ?? '');
    $requisitos      = trim($_POST['requisitos'] ?? '');
    $salario         = trim($_POST['salario'] ?? '');
    $setor           = trim($_POST['setor'] ?? '');
    $localizacao     = trim($_POST['localizacao'] ?? '');
    $estado          = trim($_POST['estado'] ?? '');
    $cidade          = trim($_POST['cidade'] ?? '');
    $quantidade      = max(1, (int) ($_POST['quantidade_vagas'] ?? 1));
    $disponibilidade = trim($_POST['disponibilidade'] ?? '');
    $escolaridade    = trim($_POST['escolaridade'] ?? '');
    $experiencia     = trim($_POST['experiencia'] ?? '');
    $tipo_contrato   = trim($_POST['tipo_contrato'] ?? '');

    // Montar localização completa
    if (!empty($cidade) && !empty($estado)) {
        $localizacao = $cidade . ', ' . $estado;
        if (!empty($_POST['localizacao_complemento'])) {
            $localizacao .= ' - ' . trim($_POST['localizacao_complemento']);
        }
    }

    // Validação
    if (empty($titulo)) {
        $msg = 'Informe o título da vaga.'; $msgTipo = 'danger';
    } elseif (empty($descricao)) {
        $msg = 'Informe a descrição da vaga.'; $msgTipo = 'danger';
    } elseif (empty($estado) || empty($cidade)) {
        $msg = 'Selecione o estado e a cidade.'; $msgTipo = 'danger';
    } else {
        // Upload imagem
        $nomeImagem = null;
        if (!empty($_FILES['imagem']['name']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
            $extPermitidas = ['jpg','jpeg','png','webp'];
            $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
            $maxSize = 5 * 1024 * 1024;
            if (!in_array($ext, $extPermitidas)) {
                $msg = 'Formato inválido. Use JPG, PNG ou WEBP.'; $msgTipo = 'danger';
            } elseif ($_FILES['imagem']['size'] > $maxSize) {
                $msg = 'Imagem muito grande. Máximo: 5MB.'; $msgTipo = 'danger';
            } else {
                $nomeImagem = 'vaga_' . $empresaId . '_' . time() . '.' . $ext;
                $destino = __DIR__ . '/../uploads/vagas/' . $nomeImagem;
                if (!is_dir(dirname($destino))) mkdir(dirname($destino), 0755, true);
                if (!move_uploaded_file($_FILES['imagem']['tmp_name'], $destino)) {
                    $msg = 'Erro no upload.'; $msgTipo = 'danger'; $nomeImagem = null;
                }
            }
        }

        if (empty($msg)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO vagas 
                        (titulo, empresa, empresa_id, localizacao, salario, setor,
                         descricao, requisitos, quantidade_vagas, disponibilidade,
                         escolaridade, experiencia, imagem, status, data_publicacao)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ativa', NOW())
                ");
                $stmt->execute([
                    $titulo, $empresaNome, $empresaId, $localizacao,
                    $salario ?: 'A combinar', $setor, $descricao, $requisitos,
                    $quantidade, $disponibilidade, $escolaridade, $experiencia, $nomeImagem
                ]);
                header("Location: nova-vaga.php?msg=" . urlencode("Vaga publicada com sucesso!") . "&tipo=success");
                exit;
            } catch (PDOException $e) {
                error_log('[nova-vaga] Erro: ' . $e->getMessage());
                $msg = 'Erro ao publicar. Tente novamente.'; $msgTipo = 'danger';
            }
        }
    }
}

if (isset($_GET['msg'])) { $msg = $_GET['msg']; $msgTipo = $_GET['tipo'] ?? 'success'; }
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Publicar Vaga — TGA Carreiras</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="http://tgameajuda.com/Projetos/tgacarreiras/img/icone_logo.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044" crossorigin="anonymous"></script>
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-S8EC5C2WTG"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','G-S8EC5C2WTG');</script>
    <style>
        :root{--primary:#0066FF;--primary-dark:#0052CC;--primary-light:#3385FF;--bg:#0F172A;--bg-card:#1E293B;--surface:#334155;--text:#F1F5F9;--text-sec:#CBD5E1;--text-muted:#64748B;--border:#334155;--success:#10B981;--warning:#F59E0B;--danger:#EF4444;--info:#3B82F6;--shadow:0 4px 12px rgba(0,0,0,.3);--shadow-lg:0 10px 30px rgba(0,0,0,.4);--radius:16px;--radius-sm:10px;--transition:all .3s ease;--sidebar-w:270px}
        [data-theme="light"]{--bg:#F1F5F9;--bg-card:#FFFFFF;--surface:#EDF0F5;--text:#1A202C;--text-sec:#4A5568;--text-muted:#718096;--border:#E2E8F0;--shadow:0 4px 12px rgba(0,0,0,.07);--shadow-lg:0 10px 30px rgba(0,0,0,.08)}
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}html,body{overflow-x:hidden}
        body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);line-height:1.6;transition:background .3s,color .3s}
        .layout{display:flex;min-height:100vh}

        /* SIDEBAR */
        .sidebar{width:var(--sidebar-w);background:var(--bg-card);border-right:1px solid var(--border);position:fixed;top:0;left:0;height:100vh;overflow-y:auto;z-index:1000;display:flex;flex-direction:column;transition:transform .3s ease}
        .sidebar-header{padding:1.5rem 1.25rem;border-bottom:1px solid var(--border)}
        .sidebar-brand{display:flex;align-items:center;gap:.75rem;text-decoration:none;color:var(--text);margin-bottom:1.25rem}
        .sidebar-brand-icon{width:38px;height:38px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0}
        .sidebar-brand-text{font-size:1.15rem;font-weight:800;letter-spacing:-.3px}
        .user-profile{display:flex;align-items:center;gap:.75rem}
        .user-avatar{width:48px;height:48px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;box-shadow:var(--shadow)}
        .user-info{min-width:0}.user-info h2{font-size:.92rem;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .user-info p{font-size:.75rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .user-badge{display:inline-flex;align-items:center;gap:.25rem;background:rgba(0,102,255,.1);border:1px solid rgba(0,102,255,.2);color:var(--primary);font-size:.6rem;font-weight:700;padding:.15rem .5rem;border-radius:12px;margin-top:.25rem;text-transform:uppercase;letter-spacing:.4px}
        .sidebar-nav{padding:1rem 0;flex:1}.nav-section{margin-bottom:1.25rem}
        .nav-section-title{padding:0 1.25rem;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);margin-bottom:.5rem}
        .nav-item{display:flex;align-items:center;gap:.75rem;padding:.7rem 1.25rem;color:var(--text-sec);text-decoration:none;font-weight:500;font-size:.88rem;transition:var(--transition);border-left:3px solid transparent}
        .nav-item:hover{background:var(--surface);color:var(--text);border-left-color:var(--primary)}
        .nav-item.active{background:rgba(0,102,255,.08);color:var(--primary);border-left-color:var(--primary);font-weight:600}
        .nav-icon{font-size:1.1rem;width:22px;text-align:center;flex-shrink:0}
        .nav-count{margin-left:auto;background:var(--primary);color:#fff;font-size:.65rem;font-weight:700;padding:.15rem .45rem;border-radius:10px;min-width:20px;text-align:center}
        .nav-item.logout{color:var(--danger)}.nav-item.logout:hover{background:rgba(239,68,68,.08);border-left-color:var(--danger)}
        .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:999}.sidebar-overlay.open{display:block}
        .sidebar-close{display:none;width:32px;height:32px;background:var(--surface);border:1px solid var(--border);border-radius:8px;cursor:pointer;color:var(--text-muted);font-size:.95rem;align-items:center;justify-content:center;transition:var(--transition)}.sidebar-close:hover{background:var(--danger);border-color:var(--danger);color:#fff}

        /* MAIN */
        .main-content{flex:1;margin-left:var(--sidebar-w);padding:2rem;min-width:0}
        .mobile-topbar{display:none;align-items:center;justify-content:space-between;padding:.875rem 1rem;background:var(--bg-card);border-bottom:2px solid var(--primary);position:sticky;top:0;z-index:500;box-shadow:var(--shadow)}
        .mobile-topbar-left{display:flex;align-items:center;gap:.75rem}
        .hamburger{width:38px;height:38px;background:var(--surface);border:1px solid var(--border);border-radius:9px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.2rem;transition:var(--transition)}.hamburger:hover{background:var(--primary);border-color:var(--primary)}
        .mobile-topbar-brand{display:flex;align-items:center;gap:.5rem;font-size:1.1rem;font-weight:800;color:var(--text)}
        .mobile-topbar-brand-icon{width:32px;height:32px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.95rem}

        /* PAGE */
        .breadcrumb{display:flex;align-items:center;gap:.5rem;margin-bottom:1rem;font-size:.85rem;color:var(--text-muted);flex-wrap:wrap}
        .breadcrumb a{color:var(--text-sec);text-decoration:none;transition:color .2s}.breadcrumb a:hover{color:var(--primary)}
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.75rem;flex-wrap:wrap;gap:1rem}
        .page-title h1{font-size:1.65rem;font-weight:800;color:var(--text);margin-bottom:.2rem;letter-spacing:-.3px}
        .page-title p{color:var(--text-muted);font-size:.88rem}
        .header-actions{display:flex;gap:.75rem;align-items:center}
        .theme-toggle{width:40px;height:40px;border:1px solid var(--border);border-radius:9px;background:var(--surface);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.1rem;transition:var(--transition)}.theme-toggle:hover{background:var(--primary);border-color:var(--primary)}

        /* ALERTS */
        .alert{padding:.875rem 1.1rem;border-radius:var(--radius-sm);margin-bottom:1.25rem;display:flex;align-items:center;gap:.75rem;animation:slideDown .3s;font-size:.88rem}
        .alert-success{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);color:var(--success)}
        .alert-danger{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:var(--danger)}

        /* CARD */
        .card{background:var(--bg-card);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);margin-bottom:1.75rem;overflow:hidden}
        .card-body{padding:1.5rem}

        /* STEPS */
        .steps-bar{display:flex;align-items:center;justify-content:center;gap:.5rem;padding:1rem 1.5rem;border-bottom:1px solid var(--border);flex-wrap:wrap}
        .step{display:flex;align-items:center;gap:.4rem;padding:.4rem .85rem;border-radius:20px;font-size:.78rem;font-weight:600;color:var(--text-muted);background:var(--surface);border:1px solid var(--border);transition:var(--transition);cursor:pointer}
        .step.active{background:var(--primary);color:#fff;border-color:var(--primary)}
        .step.done{background:rgba(16,185,129,.1);color:var(--success);border-color:rgba(16,185,129,.3)}
        .step-num{width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:800;background:rgba(255,255,255,.15)}
        .step.active .step-num{background:rgba(255,255,255,.25)}
        .step-connector{width:30px;height:2px;background:var(--border);border-radius:1px}

        /* FORM */
        .form-section{display:none;animation:fadeUp .4s ease}.form-section.active{display:block}
        .form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1.25rem}
        .form-group{display:flex;flex-direction:column;gap:.4rem}.form-group.full-width{grid-column:1/-1}
        .form-label{font-weight:600;font-size:.84rem;color:var(--text-sec);display:flex;align-items:center;gap:.35rem}
        .form-label .req{color:var(--danger);font-size:.9rem}.form-label .opt{color:var(--text-muted);font-weight:400;font-size:.75rem}
        .form-input,.form-textarea,.form-select{width:100%;padding:.75rem 1rem;border-radius:var(--radius-sm);border:1.5px solid var(--border);background:var(--surface);color:var(--text);font-size:.88rem;font-family:inherit;transition:var(--transition);outline:none}
        .form-input:focus,.form-textarea:focus,.form-select:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(0,102,255,.12)}
        .form-input::placeholder,.form-textarea::placeholder{color:var(--text-muted)}
        .form-textarea{min-height:120px;resize:vertical}
        .form-select{cursor:pointer;appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748B' d='M6 8L1 3h10z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .875rem center;padding-right:2.25rem}
        .form-hint{font-size:.73rem;color:var(--text-muted);margin-top:.15rem}
        .checkbox-label{display:flex;align-items:center;gap:.5rem;font-size:.84rem;color:var(--text-sec);cursor:pointer;margin-top:.25rem}
        .checkbox-label input{width:16px;height:16px;accent-color:var(--primary);cursor:pointer}
        .form-section-title{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--primary);margin-bottom:.35rem;padding-bottom:.4rem;border-bottom:1px dashed var(--border);grid-column:1/-1;display:flex;align-items:center;gap:.5rem;margin-top:.25rem}

        /* UPLOAD */
        .upload-area{border:2px dashed var(--border);border-radius:var(--radius-sm);padding:2rem;text-align:center;cursor:pointer;transition:var(--transition);position:relative}
        .upload-area:hover{border-color:var(--primary);background:rgba(0,102,255,.03)}
        .upload-area.has-file{border-color:var(--success);background:rgba(16,185,129,.03)}
        .upload-area input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer}
        .upload-icon{font-size:2.5rem;margin-bottom:.5rem;opacity:.5}
        .upload-text{font-size:.85rem;color:var(--text-muted);font-weight:500}
        .upload-hint{font-size:.73rem;color:var(--text-muted);margin-top:.25rem}
        .preview-container{margin-top:.75rem;display:none}
        .preview-img{max-width:200px;border-radius:var(--radius-sm);border:1px solid var(--border)}
        .preview-name{font-size:.78rem;color:var(--success);font-weight:600;margin-top:.35rem}

        /* FORM NAV */
        .form-nav{display:flex;gap:1rem;margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--border);justify-content:space-between;flex-wrap:wrap}
        .form-nav-left,.form-nav-right{display:flex;gap:.75rem}

        /* BUTTONS */
        .btn{padding:.7rem 1.25rem;border-radius:var(--radius-sm);text-decoration:none;font-weight:600;font-size:.88rem;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem;transition:var(--transition);white-space:nowrap;font-family:inherit}
        .btn-primary{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;box-shadow:0 4px 12px rgba(0,102,255,.2)}.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(0,102,255,.3)}
        .btn-success{background:linear-gradient(135deg,var(--success),#059669);color:#fff;box-shadow:0 4px 12px rgba(16,185,129,.2)}.btn-success:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(16,185,129,.3)}
        .btn-ghost{background:var(--surface);color:var(--text-sec);border:1px solid var(--border)}.btn-ghost:hover{border-color:var(--primary);color:var(--primary)}

        @keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
        @keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}

        @media(max-width:1024px){:root{--sidebar-w:240px}.main-content{padding:1.5rem}}
        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);width:280px;box-shadow:4px 0 20px rgba(0,0,0,.4)}.sidebar.open{transform:translateX(0)}
            .main-content{margin-left:0;padding:0}.mobile-topbar{display:flex}.sidebar-close{display:flex}
            .main-inner{padding:1.25rem 1rem}.page-title h1{font-size:1.25rem}.form-grid{grid-template-columns:1fr}
            .card-body{padding:1rem 1.15rem}.form-nav{flex-direction:column}.form-nav-left,.form-nav-right{width:100%}.form-nav .btn{flex:1;justify-content:center}
            .steps-bar{gap:.35rem}.step{font-size:.7rem;padding:.35rem .65rem}.step-connector{width:16px}
        }
        @media(max-width:480px){.mobile-topbar{padding:.75rem}.main-inner{padding:1rem .75rem}.page-title h1{font-size:1.1rem}.card-body{padding:.875rem 1rem}.form-input,.form-textarea,.form-select{font-size:.84rem;padding:.65rem .85rem}.steps-bar{padding:.75rem .5rem}}
    </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="layout">
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem">
                <a href="../public/index.php" class="sidebar-brand" style="margin-bottom:0">
                    <div class="sidebar-brand-icon">🚀</div><span class="sidebar-brand-text">TGA Carreiras</span>
                </a>
                <button class="sidebar-close" onclick="closeSidebar()" title="Fechar menu">✕</button>
            </div>
            <div class="user-profile">
                <div class="user-avatar">🏢</div>
                <div class="user-info">
                    <h2><?= htmlspecialchars($empresaNome) ?></h2>
                    <p><?= htmlspecialchars($empresaData['email'] ?? '') ?></p>
                    <div class="user-badge">🏷️ Empresa</div>
                </div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Menu Principal</div>
                <a href="dashboard.php" class="nav-item"><span class="nav-icon">📊</span><span>Dashboard</span></a>
                <a href="vagas.php" class="nav-item"><span class="nav-icon">📋</span><span>Minhas Vagas</span><?php if($totalVagas>0):?><span class="nav-count"><?=$totalVagas?></span><?php endif;?></a>
                <a href="nova-vaga.php" class="nav-item active"><span class="nav-icon">➕</span><span>Publicar Vaga</span></a>
                <a href="candidatos.php" class="nav-item"><span class="nav-icon">👥</span><span>Candidatos</span><?php if($candPendentes>0):?><span class="nav-count"><?=$candPendentes?></span><?php endif;?></a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Conta</div>
                <a href="perfil.php" class="nav-item"><span class="nav-icon">🏢</span><span>Perfil da Empresa</span></a>
                <a href="configuracoes.php" class="nav-item"><span class="nav-icon">⚙️</span><span>Configurações</span></a>
                <a href="logout.php" class="nav-item logout"><span class="nav-icon">🚪</span><span>Sair da Conta</span></a>
            </div>
        </nav>
    </aside>

    <!-- MAIN -->
    <main class="main-content">
        <div class="mobile-topbar">
            <div class="mobile-topbar-left">
                <button class="hamburger" onclick="toggleSidebar()" title="Menu">☰</button>
                <div class="mobile-topbar-brand"><div class="mobile-topbar-brand-icon">🚀</div><span>TGA Carreiras</span></div>
            </div>
            <button class="theme-toggle" onclick="toggleTheme()"><span id="theme-icon-mobile">🌙</span></button>
        </div>

        <div class="main-inner">
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a><span>›</span><a href="vagas.php">Minhas Vagas</a><span>›</span>
                <span style="color:var(--primary);font-weight:600">Nova Vaga</span>
            </div>
            <div class="page-header">
                <div class="page-title"><h1>➕ Publicar Nova Vaga</h1><p>Preencha os detalhes para atrair os melhores candidatos</p></div>
                <div class="header-actions">
                    <button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema"><span id="theme-icon">🌙</span></button>
                    <a href="dashboard.php" class="btn btn-ghost">← Voltar</a>
                </div>
            </div>

            <?php if(!empty($msg)):?>
            <div class="alert alert-<?=htmlspecialchars($msgTipo)?>">
                <span style="font-size:1.2rem"><?=$msgTipo==='success'?'✅':'❌'?></span>
                <span><?=htmlspecialchars($msg)?></span>
            </div>
            <?php endif;?>

            <!-- FORM -->
            <form method="POST" action="" enctype="multipart/form-data" id="formVaga">
            <div class="card">
                <div class="steps-bar">
                    <div class="step active" onclick="goStep(1)" id="step1"><span class="step-num">1</span> Dados da Vaga</div>
                    <div class="step-connector"></div>
                    <div class="step" onclick="goStep(2)" id="step2"><span class="step-num">2</span> Requisitos</div>
                    <div class="step-connector"></div>
                    <div class="step" onclick="goStep(3)" id="step3"><span class="step-num">3</span> Localização</div>
                    <div class="step-connector"></div>
                    <div class="step" onclick="goStep(4)" id="step4"><span class="step-num">4</span> Detalhes e Imagem</div>
                </div>
                <div class="card-body">

                    <!-- STEP 1 -->
                    <div class="form-section active" id="section1">
                        <div class="form-grid">
                            <div class="form-section-title">💼 Informações Principais</div>
                            <div class="form-group full-width">
                                <label class="form-label">Título da Vaga <span class="req">*</span></label>
                                <input type="text" name="titulo" class="form-input" id="inputTitulo" placeholder="Ex: Desenvolvedor Full Stack Pleno" required maxlength="200" value="<?=htmlspecialchars($_POST['titulo']??'')?>">
                                <span class="form-hint">Seja específico para atrair candidatos qualificados</span>
                            </div>
                            <div class="form-group">
                                <label class="form-label">💰 Salário</label>
                                <input type="text" name="salario" id="salarioInput" class="form-input money-input" placeholder="R$ 0,00" value="<?=htmlspecialchars($_POST['salario']??'')?>">
                                <label class="checkbox-label"><input type="checkbox" id="aCombinar" onchange="if(this.checked){document.getElementById('salarioInput').value='';document.getElementById('salarioInput').disabled=true}else{document.getElementById('salarioInput').disabled=false}"> A combinar</label>
                            </div>
                            <div class="form-group">
                                <label class="form-label">📁 Setor / Área</label>
                                <select name="setor" class="form-select">
                                    <option value="">Selecione o setor</option>
                                    <?php $setores=['Tecnologia'=>'💻','Saúde'=>'🏥','Educação'=>'📚','Comércio'=>'🛒','Indústria'=>'🏭','Serviços'=>'🔧','Agronegócio'=>'🌾','Construção Civil'=>'🏗️','Financeiro'=>'💰','Logística'=>'🚛','Alimentação'=>'🍽️','Marketing'=>'📢','Jurídico'=>'⚖️','Administrativo'=>'🗂️','Outro'=>'📁'];foreach($setores as $s=>$icon):?>
                                    <option value="<?=$s?>" <?=($_POST['setor']??'')===$s?'selected':''?>><?=$icon?> <?=$s?></option>
                                    <?php endforeach;?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">📝 Tipo de Contrato</label>
                                <select name="tipo_contrato" class="form-select">
                                    <option value="">Selecione</option>
                                    <option value="CLT" <?=($_POST['tipo_contrato']??'')==='CLT'?'selected':''?>>📋 CLT</option>
                                    <option value="PJ" <?=($_POST['tipo_contrato']??'')==='PJ'?'selected':''?>>🏷️ PJ</option>
                                    <option value="Estágio" <?=($_POST['tipo_contrato']??'')==='Estágio'?'selected':''?>>🎓 Estágio</option>
                                    <option value="Temporário" <?=($_POST['tipo_contrato']??'')==='Temporário'?'selected':''?>>⏰ Temporário</option>
                                    <option value="Freelancer" <?=($_POST['tipo_contrato']??'')==='Freelancer'?'selected':''?>>💡 Freelancer</option>
                                    <option value="Jovem Aprendiz" <?=($_POST['tipo_contrato']??'')==='Jovem Aprendiz'?'selected':''?>>🌱 Jovem Aprendiz</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">🔢 Quantidade de Vagas</label>
                                <input type="number" name="quantidade_vagas" class="form-input" placeholder="1" min="1" max="999" value="<?=htmlspecialchars($_POST['quantidade_vagas']??'1')?>">
                            </div>
                        </div>
                        <div class="form-nav"><div class="form-nav-left"></div><div class="form-nav-right"><button type="button" class="btn btn-primary" onclick="goStep(2)">Próximo ➡️</button></div></div>
                    </div>

                    <!-- STEP 2 -->
                    <div class="form-section" id="section2">
                        <div class="form-grid">
                            <div class="form-section-title">📋 Requisitos e Perfil</div>
                            <div class="form-group">
                                <label class="form-label">⏰ Disponibilidade</label>
                                <select name="disponibilidade" class="form-select">
                                    <option value="">Selecione</option>
                                    <option value="Integral" <?=($_POST['disponibilidade']??'')==='Integral'?'selected':''?>>⏰ Integral</option>
                                    <option value="Meio Período" <?=($_POST['disponibilidade']??'')==='Meio Período'?'selected':''?>>🕐 Meio Período</option>
                                    <option value="Noturno" <?=($_POST['disponibilidade']??'')==='Noturno'?'selected':''?>>🌙 Noturno</option>
                                    <option value="Flexível" <?=($_POST['disponibilidade']??'')==='Flexível'?'selected':''?>>🔄 Flexível</option>
                                    <option value="Remoto" <?=($_POST['disponibilidade']??'')==='Remoto'?'selected':''?>>🏠 Remoto</option>
                                    <option value="Híbrido" <?=($_POST['disponibilidade']??'')==='Híbrido'?'selected':''?>>🔀 Híbrido</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">🎓 Escolaridade Mínima</label>
                                <select name="escolaridade" class="form-select">
                                    <option value="">Selecione</option>
                                    <option value="Fundamental" <?=($_POST['escolaridade']??'')==='Fundamental'?'selected':''?>>📖 Fundamental</option>
                                    <option value="Médio" <?=($_POST['escolaridade']??'')==='Médio'?'selected':''?>>📗 Médio</option>
                                    <option value="Técnico" <?=($_POST['escolaridade']??'')==='Técnico'?'selected':''?>>📘 Técnico</option>
                                    <option value="Superior" <?=($_POST['escolaridade']??'')==='Superior'?'selected':''?>>🎓 Superior</option>
                                    <option value="Pós-graduação" <?=($_POST['escolaridade']??'')==='Pós-graduação'?'selected':''?>>🎯 Pós-graduação</option>
                                    <option value="Indiferente" <?=($_POST['escolaridade']??'')==='Indiferente'?'selected':''?>>✅ Indiferente</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">🏆 Experiência</label>
                                <select name="experiencia" class="form-select">
                                    <option value="">Selecione</option>
                                    <option value="Sem experiência" <?=($_POST['experiencia']??'')==='Sem experiência'?'selected':''?>>🆕 Sem experiência</option>
                                    <option value="1-2 anos" <?=($_POST['experiencia']??'')==='1-2 anos'?'selected':''?>>📅 1-2 anos</option>
                                    <option value="3-5 anos" <?=($_POST['experiencia']??'')==='3-5 anos'?'selected':''?>>📆 3-5 anos</option>
                                    <option value="5+ anos" <?=($_POST['experiencia']??'')==='5+ anos'?'selected':''?>>🏆 5+ anos</option>
                                </select>
                            </div>
                            <div class="form-group full-width">
                                <label class="form-label">📝 Requisitos Detalhados <span class="opt">(opcional)</span></label>
                                <textarea name="requisitos" class="form-textarea" placeholder="Liste requisitos: formação, habilidades, idiomas, certificações..." style="min-height:130px"><?=htmlspecialchars($_POST['requisitos']??'')?></textarea>
                            </div>
                        </div>
                        <div class="form-nav"><div class="form-nav-left"><button type="button" class="btn btn-ghost" onclick="goStep(1)">⬅️ Voltar</button></div><div class="form-nav-right"><button type="button" class="btn btn-primary" onclick="goStep(3)">Próximo ➡️</button></div></div>
                    </div>

                    <!-- STEP 3 -->
                    <div class="form-section" id="section3">
                        <div class="form-grid">
                            <div class="form-section-title">📍 Localização da Vaga</div>
                            <div class="form-group">
                                <label class="form-label">🗺️ Estado <span class="req">*</span></label>
                                <select name="estado" id="selectEstado" class="form-select" required><option value="">Carregando estados...</option></select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">🏙️ Cidade <span class="req">*</span></label>
                                <select name="cidade" id="selectCidade" class="form-select" required disabled><option value="">Selecione o estado primeiro</option></select>
                            </div>
                            <div class="form-group full-width">
                                <label class="form-label">📌 Complemento <span class="opt">(opcional)</span></label>
                                <input type="text" name="localizacao_complemento" class="form-input" placeholder="Ex: Remoto, Híbrido, Bairro Centro..." value="<?=htmlspecialchars($_POST['localizacao_complemento']??'')?>">
                                <span class="form-hint">Será exibido como: Cidade, UF - Complemento</span>
                            </div>
                            <input type="hidden" name="localizacao" id="localizacaoFinal">
                        </div>
                        <div class="form-nav"><div class="form-nav-left"><button type="button" class="btn btn-ghost" onclick="goStep(2)">⬅️ Voltar</button></div><div class="form-nav-right"><button type="button" class="btn btn-primary" onclick="goStep(4)">Próximo ➡️</button></div></div>
                    </div>

                    <!-- STEP 4 -->
                    <div class="form-section" id="section4">
                        <div class="form-grid">
                            <div class="form-section-title">📄 Descrição da Vaga</div>
                            <div class="form-group full-width">
                                <label class="form-label">Descrição <span class="req">*</span></label>
                                <textarea name="descricao" class="form-textarea" required id="inputDescricao" placeholder="Descreva as responsabilidades, rotina de trabalho, benefícios oferecidos..." style="min-height:180px"><?=htmlspecialchars($_POST['descricao']??'')?></textarea>
                                <span class="form-hint">Quanto mais detalhes, mais candidatos qualificados você atrai</span>
                            </div>
                            <div class="form-section-title">🖼️ Imagem da Vaga</div>
                            <div class="form-group full-width">
                                <div class="upload-area" id="uploadArea">
                                    <input type="file" name="imagem" accept=".jpg,.jpeg,.png,.webp" onchange="previewImage(this)">
                                    <div class="upload-icon">🖼️</div>
                                    <div class="upload-text">Clique ou arraste uma imagem aqui</div>
                                    <div class="upload-hint">JPG, PNG ou WEBP · Máximo 5MB</div>
                                </div>
                                <div class="preview-container" id="previewContainer">
                                    <img src="" class="preview-img" id="previewImg" alt="Preview">
                                    <div class="preview-name" id="previewName"></div>
                                </div>
                            </div>
                        </div>
                        <div class="form-nav">
                            <div class="form-nav-left"><button type="button" class="btn btn-ghost" onclick="goStep(3)">⬅️ Voltar</button></div>
                            <div class="form-nav-right">
                                <a href="dashboard.php" class="btn btn-ghost">❌ Cancelar</a>
                                <button type="submit" name="publicar" class="btn btn-success">🚀 Publicar Vaga</button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            </form>
        </div>
    </main>
</div>

<script>
"use strict";

/* STEPS */
let currentStep=1;
function goStep(n){
    if(n>currentStep){
        if(currentStep===1&&!document.getElementById('inputTitulo').value.trim()){
            const el=document.getElementById('inputTitulo');el.focus();el.style.borderColor='var(--danger)';
            setTimeout(()=>el.style.borderColor='',2000);return;
        }
        if(currentStep===3&&n===4){
            if(!document.getElementById('selectEstado').value||!document.getElementById('selectCidade').value){alert('Selecione o estado e a cidade.');return;}
        }
    }
    currentStep=n;
    document.querySelectorAll('.form-section').forEach(s=>s.classList.remove('active'));
    document.getElementById('section'+n).classList.add('active');
    for(let i=1;i<=4;i++){const el=document.getElementById('step'+i);el.classList.remove('active','done');if(i===n)el.classList.add('active');else if(i<n)el.classList.add('done')}
    document.querySelector('.steps-bar').scrollIntoView({behavior:'smooth',block:'nearest'});
}

/* IBGE */
const selectEstado=document.getElementById('selectEstado'),selectCidade=document.getElementById('selectCidade');
async function carregarEstados(){
    try{const r=await fetch('https://servicodados.ibge.gov.br/api/v1/localidades/estados?orderBy=nome');const d=await r.json();
    selectEstado.innerHTML='<option value="">Selecione o estado</option>';
    d.forEach(u=>{const o=document.createElement('option');o.value=u.sigla;o.textContent=u.nome+' ('+u.sigla+')';selectEstado.appendChild(o)});
    const s='<?=addslashes($_POST['estado']??'')?>';if(s){selectEstado.value=s;await carregarCidades(s);const sc='<?=addslashes($_POST['cidade']??'')?>';if(sc)selectCidade.value=sc}
    }catch(e){selectEstado.innerHTML='<option value="">Erro ao carregar</option>'}
}
async function carregarCidades(uf){
    selectCidade.disabled=true;selectCidade.innerHTML='<option value="">Carregando...</option>';
    try{const r=await fetch('https://servicodados.ibge.gov.br/api/v1/localidades/estados/'+uf+'/municipios?orderBy=nome');const d=await r.json();
    selectCidade.innerHTML='<option value="">Selecione a cidade</option>';
    d.forEach(c=>{const o=document.createElement('option');o.value=c.nome;o.textContent=c.nome;selectCidade.appendChild(o)});selectCidade.disabled=false;
    }catch(e){selectCidade.innerHTML='<option value="">Erro</option>'}
}
selectEstado.addEventListener('change',function(){if(this.value)carregarCidades(this.value);else{selectCidade.innerHTML='<option value="">Selecione o estado primeiro</option>';selectCidade.disabled=true}});

/* MONEY */
function formatMoney(i){let v=i.value.replace(/\D/g,'');if(!v){i.value='';return}v=(parseInt(v)/100).toFixed(2).replace('.',',').replace(/(\d)(?=(\d{3})+(?!\d))/g,'$1.');i.value='R$ '+v}
document.querySelectorAll('.money-input').forEach(el=>{el.addEventListener('input',function(){formatMoney(this)});el.addEventListener('focus',function(){if(!this.value)this.value='R$ '});el.addEventListener('blur',function(){if(this.value==='R$ '||this.value==='R$ 0,00')this.value=''})});

/* PREVIEW */
function previewImage(input){const c=document.getElementById('previewContainer'),p=document.getElementById('previewImg'),n=document.getElementById('previewName'),a=document.getElementById('uploadArea');
if(input.files&&input.files[0]){const f=input.files[0];if(f.size>5*1024*1024){alert('Máximo: 5MB');input.value='';return}const r=new FileReader();r.onload=function(e){p.src=e.target.result;n.textContent='✅ '+f.name;c.style.display='block';a.classList.add('has-file')};r.readAsDataURL(f)}}

/* SUBMIT */
document.getElementById('formVaga').addEventListener('submit',function(){const e=selectEstado.value,c=selectCidade.value,co=document.querySelector('[name="localizacao_complemento"]').value.trim();let l='';if(c&&e){l=c+', '+e;if(co)l+=' - '+co}document.getElementById('localizacaoFinal').value=l});

/* SIDEBAR/THEME */
function toggleTheme(){const h=document.documentElement,n=h.getAttribute('data-theme')==='dark'?'light':'dark';h.setAttribute('data-theme',n);const e=n==='dark'?'🌙':'☀️';document.querySelectorAll('[id^="theme-icon"]').forEach(el=>el.textContent=e);localStorage.setItem('theme',n)}
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open')}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open')}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeSidebar()});

window.addEventListener('DOMContentLoaded',()=>{
    const s=localStorage.getItem('theme')||'dark';document.documentElement.setAttribute('data-theme',s);
    const e=s==='dark'?'🌙':'☀️';document.querySelectorAll('[id^="theme-icon"]').forEach(el=>el.textContent=e);
    carregarEstados();
    setTimeout(()=>{document.querySelectorAll('.alert').forEach(el=>{el.style.transition='opacity .4s,transform .4s';el.style.opacity='0';el.style.transform='translateY(-10px)';setTimeout(()=>el.remove(),400)})},5000);
});
</script>
</body>
</html>