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

/* ══════════════════════════════════════════════════════════
   AÇÕES POST (EDITAR / EXCLUIR / TOGGLE STATUS)
══════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ── EDITAR VAGA ─────────────────────────────────────── */
    if (isset($_POST['salvar'])) {
        $id          = (int) ($_POST['id'] ?? 0);
        $titulo      = trim($_POST['titulo'] ?? '');
        $descricao   = trim($_POST['descricao'] ?? '');
        $requisitos  = trim($_POST['requisitos'] ?? '');
        $localizacao = trim($_POST['localizacao'] ?? '');
        $salarioRaw  = $_POST['salario'] ?? '';
        $setor       = trim($_POST['setor'] ?? '');
        $disponibilidade = trim($_POST['disponibilidade'] ?? '');
        $escolaridade    = trim($_POST['escolaridade'] ?? '');
        $experiencia     = trim($_POST['experiencia'] ?? '');
        $quantidade      = max(1, (int) ($_POST['quantidade_vagas'] ?? 1));
        $status      = $_POST['status'] ?? 'ativa';

        // Tratar salário
        $salarioLimpo = str_replace(['R$', ' ', '.'], '', $salarioRaw);
        $salarioLimpo = str_replace(',', '.', $salarioLimpo);
        $salario = ($salarioLimpo === '' || $salarioLimpo == 0) ? 'A combinar' : 'R$ ' . number_format((float)$salarioLimpo, 2, ',', '.');

        // Verificar se a vaga pertence à empresa
        $stmtCheck = $pdo->prepare("SELECT id FROM vagas WHERE id = ? AND empresa_id = ?");
        $stmtCheck->execute([$id, $empresaId]);
        if (!$stmtCheck->fetch()) {
            header("Location: vagas.php?msg=" . urlencode("Vaga não encontrada.") . "&tipo=danger");
            exit;
        }

        // Upload imagem
        $nomeImagem = null;
        if (!empty($_FILES['imagem']['name']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/vagas/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
            $permitidas = ['jpg','jpeg','png','webp'];
            if (!in_array($ext, $permitidas)) {
                header("Location: vagas.php?msg=" . urlencode("Formato de imagem inválido!") . "&tipo=danger");
                exit;
            }
            if ($_FILES['imagem']['size'] > 5 * 1024 * 1024) {
                header("Location: vagas.php?msg=" . urlencode("Imagem muito grande! Máximo 5MB.") . "&tipo=danger");
                exit;
            }
            $nomeImagem = 'vaga_' . $empresaId . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['imagem']['tmp_name'], $uploadDir . $nomeImagem);
        }

        // Remover imagem?
        if (isset($_POST['remover_imagem']) && $_POST['remover_imagem'] == '1' && !$nomeImagem) {
            $nomeImagem = '';
        }

        // UPDATE
        if ($nomeImagem !== null) {
            $stmt = $pdo->prepare("UPDATE vagas SET titulo=?, descricao=?, requisitos=?, salario=?, localizacao=?, setor=?, disponibilidade=?, escolaridade=?, experiencia=?, quantidade_vagas=?, status=?, imagem=? WHERE id=? AND empresa_id=?");
            $stmt->execute([$titulo, $descricao, $requisitos, $salario, $localizacao, $setor, $disponibilidade, $escolaridade, $experiencia, $quantidade, $status, $nomeImagem ?: null, $id, $empresaId]);
        } else {
            $stmt = $pdo->prepare("UPDATE vagas SET titulo=?, descricao=?, requisitos=?, salario=?, localizacao=?, setor=?, disponibilidade=?, escolaridade=?, experiencia=?, quantidade_vagas=?, status=? WHERE id=? AND empresa_id=?");
            $stmt->execute([$titulo, $descricao, $requisitos, $salario, $localizacao, $setor, $disponibilidade, $escolaridade, $experiencia, $quantidade, $status, $id, $empresaId]);
        }

        header("Location: vagas.php?msg=" . urlencode("Vaga atualizada com sucesso!") . "&tipo=success");
        exit;
    }

    /* ── EXCLUIR VAGA ────────────────────────────────────── */
    if (isset($_POST['excluir'])) {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM vagas WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$id, $empresaId]);
        header("Location: vagas.php?msg=" . urlencode("Vaga excluída com sucesso!") . "&tipo=success");
        exit;
    }

    /* ── TOGGLE STATUS ───────────────────────────────────── */
    if (isset($_POST['toggle'])) {
        $id = (int) ($_POST['id'] ?? 0);
        $novoStatus = $_POST['status'] ?? 'ativa';
        $stmt = $pdo->prepare("UPDATE vagas SET status = ? WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$novoStatus, $id, $empresaId]);
        $label = $novoStatus === 'ativa' ? 'ativada' : 'desativada';
        header("Location: vagas.php?msg=" . urlencode("Vaga {$label} com sucesso!") . "&tipo=success");
        exit;
    }
}

/* ══════════════════════════════════════════════════════════
   LISTAR VAGAS DA EMPRESA
══════════════════════════════════════════════════════════ */
$stmtVagas = $pdo->prepare("
    SELECT v.*, COUNT(c.id) as total_candidatos
    FROM vagas v
    LEFT JOIN candidaturas c ON v.id = c.vaga_id
    WHERE v.empresa_id = ?
    GROUP BY v.id
    ORDER BY v.data_publicacao DESC
");
$stmtVagas->execute([$empresaId]);
$vagas = $stmtVagas->fetchAll(PDO::FETCH_ASSOC);

$totalVagas        = count($vagas);
$vagasAtivas       = count(array_filter($vagas, fn($v) => ($v['status'] ?? '') === 'ativa'));
$vagasInativas     = $totalVagas - $vagasAtivas;
$totalCandidaturas = array_sum(array_column($vagas, 'total_candidatos'));

/* ── Pendentes sidebar ──────────────────────────────────── */
$stmtP = $pdo->prepare("SELECT COUNT(*) FROM candidaturas c INNER JOIN vagas v ON c.vaga_id = v.id WHERE v.empresa_id = ? AND c.status = 'pendente'");
$stmtP->execute([$empresaId]);
$candPendentes = (int) $stmtP->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Minhas Vagas — TGA Carreiras</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="http://tgameajuda.com/Projetos/tgacarreiras/img/icone_logo.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044" crossorigin="anonymous"></script>
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-S8EC5C2WTG"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','G-S8EC5C2WTG');</script>
    <style>
        /* ══════════════ VARIÁVEIS ══════════════ */
        :root {
            --primary-blue:#0066FF;--primary-dark:#0052CC;--primary-light:#3385FF;
            --bg-primary:#0F172A;--bg-secondary:#1E293B;--bg-tertiary:#334155;
            --text-primary:#F1F5F9;--text-secondary:#CBD5E1;--text-tertiary:#64748B;
            --border-color:#334155;
            --shadow-sm:0 1px 3px rgba(0,0,0,.3);--shadow-md:0 4px 12px rgba(0,0,0,.3);
            --shadow-lg:0 10px 30px rgba(0,0,0,.4);--shadow-xl:0 20px 40px rgba(0,102,255,.3);
            --success:#10B981;--warning:#F59E0B;--danger:#EF4444;
            --sidebar-open:270px;--sidebar-collapsed:78px;
        }
        [data-theme="light"] {
            --bg-primary:#FAFBFC;--bg-secondary:#F5F7FA;--bg-tertiary:#EDF0F5;
            --text-primary:#1A202C;--text-secondary:#4A5568;--text-tertiary:#718096;
            --border-color:#E2E8F0;
            --shadow-sm:0 1px 3px rgba(0,0,0,.06);--shadow-md:0 4px 12px rgba(0,0,0,.07);
            --shadow-lg:0 10px 30px rgba(0,0,0,.08);--shadow-xl:0 20px 40px rgba(0,102,255,.12);
        }

        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:var(--bg-secondary);color:var(--text-primary);line-height:1.6}
        .admin-layout{display:flex;min-height:100vh}

        /* ══════════════ SIDEBAR ══════════════ */
        .sidebar{width:var(--sidebar-open);background:var(--bg-primary);border-right:1px solid var(--border-color);padding:2rem 0;position:fixed;height:100vh;overflow-y:auto;z-index:1000;transition:width .25s ease}
        .sidebar-header{padding:0 1.5rem 2rem;border-bottom:1px solid var(--border-color)}
        .logo{display:flex;align-items:center;gap:.75rem}
        .logo-icon{width:42px;height:42px;background:linear-gradient(135deg,var(--primary-blue),var(--primary-dark));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0}
        .logo-text h1{font-size:1.25rem;font-weight:800;color:var(--text-primary)}
        .logo-text p{font-size:.7rem;color:var(--text-tertiary);font-weight:600;text-transform:uppercase;letter-spacing:.5px}

        .user-block{display:flex;align-items:center;gap:.75rem;margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--border-color)}
        .user-avatar{width:42px;height:42px;background:linear-gradient(135deg,var(--primary-blue),var(--primary-dark));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0}
        .user-block-info{min-width:0}
        .user-block-name{font-size:.85rem;font-weight:700;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .user-block-role{display:inline-flex;align-items:center;gap:.25rem;background:rgba(0,102,255,.1);border:1px solid rgba(0,102,255,.2);color:var(--primary-blue);font-size:.58rem;font-weight:700;padding:.1rem .4rem;border-radius:10px;text-transform:uppercase;letter-spacing:.3px;margin-top:.2rem}

        .sidebar-toggle{margin-left:auto;width:36px;height:36px;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-primary);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.2s ease;flex-shrink:0}
        .sidebar-toggle:hover{background:var(--primary-blue);border-color:var(--primary-blue);transform:scale(1.05)}

        .sidebar-nav{padding:1.5rem 0}
        .nav-section{margin-bottom:1.5rem}
        .nav-section-title{padding:0 1.5rem;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-tertiary);margin-bottom:.75rem}
        .nav-item{display:flex;align-items:center;gap:.85rem;padding:.75rem 1.5rem;color:var(--text-secondary);text-decoration:none;font-weight:500;font-size:.9rem;transition:all .2s;border-left:3px solid transparent;position:relative}
        .nav-item:hover{background:var(--bg-secondary);color:var(--text-primary);border-left-color:var(--primary-blue)}
        .nav-item.active{background:var(--bg-secondary);color:var(--primary-blue);border-left-color:var(--primary-blue);font-weight:600}
        .nav-icon{font-size:1.15rem;width:24px;text-align:center;flex-shrink:0}
        .nav-badge{margin-left:auto;background:var(--primary-blue);color:#fff;font-size:.62rem;font-weight:700;padding:.15rem .45rem;border-radius:10px;min-width:20px;text-align:center}
        .nav-item.danger{color:var(--danger)}.nav-item.danger:hover{border-left-color:var(--danger);background:rgba(239,68,68,.06)}

        /* Sidebar collapsed */
        .admin-layout.sidebar-collapsed .sidebar{width:var(--sidebar-collapsed)}
        .admin-layout.sidebar-collapsed .main-content{margin-left:var(--sidebar-collapsed)}
        .admin-layout.sidebar-collapsed .logo-text,
        .admin-layout.sidebar-collapsed .nav-section-title,
        .admin-layout.sidebar-collapsed .user-block,
        .admin-layout.sidebar-collapsed .nav-item span:not(.nav-icon){display:none}
        .admin-layout.sidebar-collapsed .nav-item{justify-content:center;padding:.85rem 0;gap:0}
        .admin-layout.sidebar-collapsed .nav-item::after{content:attr(data-label);position:absolute;left:calc(100% + 10px);top:50%;transform:translateY(-50%);background:#111827;color:#fff;padding:6px 10px;border-radius:8px;font-size:.75rem;white-space:nowrap;opacity:0;pointer-events:none;transition:.2s ease;box-shadow:0 10px 30px rgba(0,0,0,.35);z-index:9999}
        .admin-layout.sidebar-collapsed .nav-item:hover::after{opacity:1}

        /* ══════════════ MAIN ══════════════ */
        .main-content{flex:1;margin-left:var(--sidebar-open);padding:2rem;transition:margin-left .25s ease}

        .breadcrumb{display:flex;align-items:center;gap:.5rem;margin-bottom:1.5rem;font-size:.85rem}
        .breadcrumb a{color:var(--text-secondary);text-decoration:none;transition:color .2s}.breadcrumb a:hover{color:var(--primary-blue)}
        .breadcrumb span{color:var(--text-tertiary)}

        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
        .page-title h2{font-size:1.85rem;font-weight:800;color:var(--text-primary);margin-bottom:.25rem}
        .page-title p{color:var(--text-secondary);font-size:.9rem}
        .header-actions{display:flex;gap:1rem;align-items:center}

        .theme-toggle{width:44px;height:44px;border:2px solid var(--border-color);border-radius:10px;background:var(--bg-primary);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.25rem;transition:all .3s}
        .theme-toggle:hover{background:var(--primary-blue);border-color:var(--primary-blue);transform:scale(1.05)}

        /* ══════════════ BUTTONS ══════════════ */
        .btn{padding:.75rem 1.5rem;border-radius:10px;text-decoration:none;font-weight:600;font-size:.9rem;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem;transition:all .3s;white-space:nowrap;font-family:inherit}
        .btn-primary{background:linear-gradient(135deg,var(--primary-blue),var(--primary-dark));color:#fff;box-shadow:0 4px 12px rgba(0,102,255,.25)}.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(0,102,255,.35)}
        .btn-secondary{background:var(--bg-tertiary);color:var(--text-primary);border:1px solid var(--border-color)}
        .btn-warning{background:var(--warning);color:#fff}.btn-danger{background:var(--danger);color:#fff}
        .btn-sm{padding:.5rem 1rem;font-size:.82rem}
        .btn-icon{width:40px;height:40px;padding:0;justify-content:center;position:relative}
        .btn-icon::after{content:attr(data-tooltip);position:absolute;bottom:120%;left:50%;transform:translateX(-50%);background:#111827;color:#fff;font-size:.72rem;padding:6px 10px;border-radius:6px;white-space:nowrap;opacity:0;pointer-events:none;transition:.2s ease;box-shadow:0 5px 20px rgba(0,0,0,.4)}
        .btn-icon:hover::after{opacity:1}

        /* ══════════════ ALERTS ══════════════ */
        .alert{padding:1rem 1.5rem;border-radius:12px;margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;animation:slideDown .3s}
        .alert-success{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);color:var(--success)}
        .alert-danger{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:var(--danger)}
        .alert-icon{font-size:1.5rem}

        /* ══════════════ STATS ══════════════ */
        .stats-mini{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:2rem}
        .stat-mini{background:var(--bg-primary);border-radius:12px;padding:1.25rem;border:1px solid var(--border-color);display:flex;align-items:center;gap:1rem;transition:all .3s;animation:fadeUp .4s ease both}
        .stat-mini:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg)}
        .stat-mini:nth-child(2){animation-delay:.05s}.stat-mini:nth-child(3){animation-delay:.1s}.stat-mini:nth-child(4){animation-delay:.15s}
        .stat-mini-icon{width:45px;height:45px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0}
        .stat-mini-icon.blue{background:rgba(0,102,255,.1);color:var(--primary-blue)}
        .stat-mini-icon.green{background:rgba(16,185,129,.1);color:var(--success)}
        .stat-mini-icon.orange{background:rgba(245,158,11,.1);color:var(--warning)}
        .stat-mini-icon.purple{background:rgba(139,92,246,.1);color:#8B5CF6}
        .stat-mini-label{font-size:.75rem;color:var(--text-tertiary);font-weight:600;text-transform:uppercase;letter-spacing:.5px}
        .stat-mini-value{font-size:1.75rem;font-weight:800;color:var(--text-primary)}

        /* ══════════════ CARD ══════════════ */
        .card{background:var(--bg-primary);border-radius:16px;border:1px solid var(--border-color);box-shadow:var(--shadow-md);margin-bottom:2rem;overflow:hidden}
        .card-header{padding:1.5rem 2rem;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;gap:1.5rem;flex-wrap:wrap}
        .card-title{font-size:1.2rem;font-weight:700;color:var(--text-primary)}
        .filtros-container{display:flex;gap:1rem;align-items:center;flex-wrap:wrap}
        .busca-input{width:400px;max-width:100%}
        .filtro-select{width:180px}

        /* ══════════════ TABLE ══════════════ */
        .table-container{overflow-x:auto}
        .modern-table{width:100%;border-collapse:collapse}
        .modern-table thead{background:var(--bg-secondary)}
        .modern-table th{padding:1rem 1.5rem;text-align:left;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-tertiary);border-bottom:1px solid var(--border-color);cursor:pointer;user-select:none;transition:all .2s;white-space:nowrap}
        .modern-table th:hover{background:var(--bg-tertiary);color:var(--text-primary)}
        .modern-table th.sortable::after{content:'⇅';margin-left:.5rem;opacity:.3;font-size:.85rem}
        .modern-table th.sorted-asc::after{content:'↑';opacity:1;color:var(--primary-blue)}
        .modern-table th.sorted-desc::after{content:'↓';opacity:1;color:var(--primary-blue)}
        .modern-table td{padding:1.15rem 1.5rem;border-bottom:1px solid var(--border-color);color:var(--text-secondary);font-size:.9rem;vertical-align:middle}
        .modern-table tbody tr{transition:background .2s}
        .modern-table tbody tr:hover{background:var(--bg-secondary)}
        .table-id{font-weight:700;color:var(--text-tertiary);font-size:.82rem}
        .table-title{font-weight:600;color:var(--text-primary)}
        .table-meta{font-size:.75rem;color:var(--text-tertiary);margin-top:.15rem}

        .thumb-vaga{width:55px;height:55px;object-fit:cover;border-radius:10px;border:1px solid var(--border-color);cursor:pointer;transition:transform .2s}
        .thumb-vaga:hover{transform:scale(1.08)}

        .status-badge{display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .85rem;border-radius:20px;font-size:.8rem;font-weight:600;white-space:nowrap;position:relative;padding-left:22px}
        .status-badge::before{content:"";position:absolute;left:8px;width:6px;height:6px;border-radius:50%;background:currentColor}
        .status-badge.ativa{background:rgba(16,185,129,.1);color:var(--success)}
        .status-badge.pausada{background:rgba(245,158,11,.1);color:var(--warning)}
        .status-badge.encerrada{background:rgba(239,68,68,.1);color:var(--danger)}
        .status-badge.inativa{background:rgba(100,116,139,.1);color:var(--text-tertiary)}

        .candidate-badge{display:inline-flex;align-items:center;gap:.5rem;background:var(--bg-secondary);padding:.375rem .875rem;border-radius:20px;font-weight:600;color:var(--text-primary);font-size:.82rem}

        .action-buttons{display:flex;gap:.5rem;flex-wrap:wrap}
        .action-buttons form{display:inline}

        /* ══════════════ MOBILE CARDS ══════════════ */
        .mobile-vagas{display:none}
        .mv-card{background:var(--bg-tertiary);border:1px solid var(--border-color);border-radius:12px;padding:1rem;margin-bottom:.65rem;transition:all .3s}
        .mv-card:hover{border-color:var(--primary-blue)}
        .mv-header{display:flex;align-items:center;gap:.75rem;margin-bottom:.65rem}
        .mv-thumb{width:48px;height:48px;object-fit:cover;border-radius:10px;border:1px solid var(--border-color);flex-shrink:0}
        .mv-thumb-ph{width:48px;height:48px;background:var(--bg-secondary);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;border:1px solid var(--border-color);flex-shrink:0}
        .mv-title-area{flex:1;min-width:0}
        .mv-titulo{font-size:.88rem;font-weight:700;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .mv-meta-line{font-size:.72rem;color:var(--text-tertiary);display:flex;gap:.5rem;flex-wrap:wrap}
        .mv-grid{display:grid;grid-template-columns:1fr 1fr;gap:.3rem .6rem;margin-bottom:.55rem}
        .mv-field{display:flex;flex-direction:column}.mv-label{font-size:.56rem;font-weight:700;text-transform:uppercase;letter-spacing:.3px;color:var(--text-tertiary)}.mv-value{font-size:.76rem;font-weight:600;color:var(--text-secondary)}
        .mv-footer{display:flex;align-items:center;justify-content:space-between;padding-top:.5rem;border-top:1px solid var(--border-color);gap:.5rem;flex-wrap:wrap}

        /* ══════════════ MODAL ══════════════ */
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(4px);z-index:2000;align-items:center;justify-content:center;padding:1rem;animation:fadeIn .3s}
        .modal-overlay.active{display:flex}
        .modal{background:var(--bg-primary);border-radius:20px;box-shadow:var(--shadow-xl);width:100%;max-width:900px;max-height:90vh;overflow:hidden;animation:slideUp .3s;border:1px solid var(--border-color)}
        .modal-header{padding:1.75rem 2rem;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center}
        .modal-title{font-size:1.4rem;font-weight:700;color:var(--text-primary);display:flex;align-items:center;gap:.75rem}
        .modal-close{width:40px;height:40px;border-radius:10px;background:var(--bg-secondary);border:1px solid var(--border-color);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.4rem;transition:all .3s;color:var(--text-secondary)}
        .modal-close:hover{background:var(--danger);border-color:var(--danger);color:#fff;transform:rotate(90deg)}
        .modal-body{padding:2rem;overflow-y:auto;max-height:calc(90vh - 140px)}

        .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem}
        .form-group{display:flex;flex-direction:column;gap:.5rem}.form-group.full-width{grid-column:1/-1}
        .form-label{font-weight:600;font-size:.88rem;color:var(--text-primary)}.form-label .required{color:var(--danger)}
        .form-input,.form-textarea,.form-select{padding:.875rem 1rem;border-radius:10px;border:2px solid var(--border-color);background:var(--bg-secondary);color:var(--text-primary);font-size:.9rem;font-family:inherit;transition:all .3s}
        .form-input:focus,.form-textarea:focus,.form-select:focus{outline:none;border-color:var(--primary-blue);box-shadow:0 0 0 4px rgba(0,102,255,.1)}
        .form-textarea{min-height:120px;resize:vertical}
        .form-select{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748B' d='M6 8L1 3h10z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 1rem center;padding-right:2.25rem}
        .form-actions{display:flex;gap:1rem;margin-top:2rem;padding-top:2rem;border-top:1px solid var(--border-color)}

        /* ══════════════ EMPTY ══════════════ */
        .empty-state{text-align:center;padding:4rem 2rem;color:var(--text-tertiary)}
        .empty-state-icon{font-size:4rem;margin-bottom:1rem;opacity:.5}
        .empty-state h3{font-size:1.25rem;color:var(--text-primary);margin-bottom:.5rem}

        /* ══════════════ FULLSCREEN ══════════════ */
        #overlayImagem{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);backdrop-filter:blur(5px);z-index:5000;align-items:center;justify-content:center;cursor:pointer}
        #overlayImagem img{max-width:90%;max-height:90%;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.6)}

        @keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
        @keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
        @keyframes fadeIn{from{opacity:0}to{opacity:1}}
        @keyframes slideUp{from{opacity:0;transform:translateY(30px) scale(.95)}to{opacity:1;transform:translateY(0) scale(1)}}

        @media(max-width:992px){
            .sidebar{transform:translateX(-100%);width:280px;box-shadow:4px 0 20px rgba(0,0,0,.4)}
            .sidebar.open{transform:translateX(0)}
            .main-content{margin-left:0}
            .form-grid{grid-template-columns:1fr}
            .modal{max-width:100%;border-radius:20px 20px 0 0;max-height:95vh}
            .mobile-topbar{display:flex!important}
            .sidebar-close{display:flex!important}
        }
        @media(max-width:768px){
            .main-content{padding:1rem}
            .stats-mini{grid-template-columns:repeat(2,1fr)}
            .table-container{display:none}
            .mobile-vagas{display:block}
            .card-header{flex-direction:column;align-items:flex-start;padding:1.15rem}
            .filtros-container{width:100%}.busca-input{width:100%}.filtro-select{width:100%}
            .action-buttons{flex-direction:column}.action-buttons .btn{width:100%;justify-content:center}
            .form-actions{flex-direction:column}.form-actions .btn{width:100%;justify-content:center}
        }
        @media(max-width:480px){.stats-mini{grid-template-columns:1fr}.stat-mini-value{font-size:1.4rem}}

        /* Mobile topbar */
        .mobile-topbar{display:none;align-items:center;justify-content:space-between;padding:.875rem 1rem;background:var(--bg-primary);border-bottom:2px solid var(--primary-blue);position:sticky;top:0;z-index:500;box-shadow:var(--shadow-md)}
        .mobile-topbar-left{display:flex;align-items:center;gap:.75rem}
        .hamburger{width:38px;height:38px;background:var(--bg-tertiary);border:1px solid var(--border-color);border-radius:9px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.2rem;transition:all .3s}.hamburger:hover{background:var(--primary-blue);border-color:var(--primary-blue)}
        .mobile-brand{display:flex;align-items:center;gap:.5rem;font-size:1.1rem;font-weight:800;color:var(--text-primary)}
        .mobile-brand-icon{width:32px;height:32px;background:linear-gradient(135deg,var(--primary-blue),var(--primary-dark));border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.95rem}
        .sidebar-close{display:none;width:32px;height:32px;background:var(--bg-tertiary);border:1px solid var(--border-color);border-radius:8px;cursor:pointer;color:var(--text-tertiary);font-size:.95rem;align-items:center;justify-content:center;transition:all .3s;position:absolute;top:1.5rem;right:1rem}
        .sidebar-close:hover{background:var(--danger);border-color:var(--danger);color:#fff}
        .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:999}.sidebar-overlay.open{display:block}
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="admin-layout" id="adminLayout">
    <!-- ═══ SIDEBAR ══════════════════════════════════════ -->
    <aside class="sidebar" id="sidebar">
        <button class="sidebar-close" onclick="closeSidebar()">✕</button>
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">🚀</div>
                <div class="logo-text">
                    <h1>TGA Carreiras</h1>
                    <p>Painel Empresa</p>
                </div>
                <button class="sidebar-toggle" id="sidebarToggle" type="button" title="Recolher menu">☰</button>
            </div>
            <div class="user-block">
                <div class="user-avatar">🏢</div>
                <div class="user-block-info">
                    <div class="user-block-name"><?= htmlspecialchars($empresaNome) ?></div>
                    <div class="user-block-role">🏷️ Empresa</div>
                </div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Principal</div>
                <a href="dashboard.php" class="nav-item" data-label="Dashboard"><span class="nav-icon">📊</span><span>Dashboard</span></a>
                <a href="vagas.php" class="nav-item active" data-label="Minhas Vagas"><span class="nav-icon">📋</span><span>Minhas Vagas</span><?php if($totalVagas>0):?><span class="nav-badge"><?=$totalVagas?></span><?php endif;?></a>
                <a href="nova-vaga.php" class="nav-item" data-label="Publicar Vaga"><span class="nav-icon">➕</span><span>Publicar Vaga</span></a>
                <a href="candidatos.php" class="nav-item" data-label="Candidatos"><span class="nav-icon">👥</span><span>Candidatos</span><?php if($candPendentes>0):?><span class="nav-badge"><?=$candPendentes?></span><?php endif;?></a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Configurações</div>
                <a href="perfil.php" class="nav-item" data-label="Perfil"><span class="nav-icon">🏢</span><span>Perfil da Empresa</span></a>
                <a href="configuracoes.php" class="nav-item" data-label="Configurações"><span class="nav-icon">⚙️</span><span>Configurações</span></a>
                <a href="logout.php" class="nav-item danger" data-label="Sair"><span class="nav-icon">🚪</span><span>Sair</span></a>
            </div>
        </nav>
    </aside>

    <!-- ═══ MAIN ═════════════════════════════════════════ -->
    <main class="main-content">
        <!-- Mobile topbar -->
        <div class="mobile-topbar">
            <div class="mobile-topbar-left">
                <button class="hamburger" onclick="toggleSidebar()">☰</button>
                <div class="mobile-brand"><div class="mobile-brand-icon">🚀</div><span>TGA Carreiras</span></div>
            </div>
            <button class="theme-toggle" onclick="toggleTheme()" style="width:38px;height:38px;font-size:1.1rem"><span id="theme-icon-mobile">🌙</span></button>
        </div>

        <div style="padding:0">
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a><span>›</span><span>Minhas Vagas</span>
            </div>

            <div class="page-header">
                <div class="page-title">
                    <h2>📋 Minhas Vagas</h2>
                    <p>Gerencie, edite e acompanhe todas as suas vagas publicadas</p>
                </div>
                <div class="header-actions">
                    <button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema"><span id="theme-icon">🌙</span></button>
                    <a href="nova-vaga.php" class="btn btn-primary"><span>➕</span><span>Nova Vaga</span></a>
                </div>
            </div>

            <?php if(isset($_GET['msg'])):?>
            <div class="alert alert-<?=htmlspecialchars($_GET['tipo']??'success')?>">
                <span class="alert-icon"><?=($_GET['tipo']??'success')==='success'?'✅':'❌'?></span>
                <span><?=htmlspecialchars($_GET['msg'])?></span>
            </div>
            <?php endif;?>

            <!-- STATS -->
            <div class="stats-mini">
                <div class="stat-mini"><div class="stat-mini-icon blue">💼</div><div><div class="stat-mini-label">Total</div><div class="stat-mini-value"><?=$totalVagas?></div></div></div>
                <div class="stat-mini"><div class="stat-mini-icon green">✅</div><div><div class="stat-mini-label">Ativas</div><div class="stat-mini-value"><?=$vagasAtivas?></div></div></div>
                <div class="stat-mini"><div class="stat-mini-icon orange">⏸️</div><div><div class="stat-mini-label">Inativas</div><div class="stat-mini-value"><?=$vagasInativas?></div></div></div>
                <div class="stat-mini"><div class="stat-mini-icon purple">👥</div><div><div class="stat-mini-label">Candidatos</div><div class="stat-mini-value"><?=$totalCandidaturas?></div></div></div>
            </div>

            <!-- VAGAS CARD -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📋 Todas as Vagas</h3>
                    <div class="filtros-container">
                        <input type="text" id="buscaGlobal" placeholder="🔍 Buscar por título, localização..." class="form-input busca-input">
                        <select id="filtroStatus" class="form-select filtro-select">
                            <option value="">Todos os status</option>
                            <option value="ativa">✅ Ativa</option>
                            <option value="inativa">⏸️ Inativa</option>
                            <option value="pausada">🟡 Pausada</option>
                            <option value="encerrada">🔴 Encerrada</option>
                        </select>
                    </div>
                </div>

                <?php if($totalVagas > 0):?>
                <!-- DESKTOP TABLE -->
                <div class="table-container">
                    <table class="modern-table" id="vagasTable">
                        <thead><tr>
                            <th class="sortable" data-column="id">ID</th>
                            <th>Imagem</th>
                            <th class="sortable" data-column="titulo">Título</th>
                            <th class="sortable" data-column="localizacao">Localização</th>
                            <th class="sortable" data-column="status">Status</th>
                            <th class="sortable" data-column="candidatos">Candidatos</th>
                            <th class="sortable" data-column="data">Publicação</th>
                            <th>Ações</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach($vagas as $vaga):
                            $st = strtolower(trim($vaga['status'] ?? 'inativa'));
                            if(!in_array($st,['ativa','inativa','pausada','encerrada'])) $st='inativa';
                        ?>
                        <tr data-vaga-id="<?=$vaga['id']?>"
                            data-titulo="<?=htmlspecialchars($vaga['titulo'])?>"
                            data-descricao="<?=htmlspecialchars($vaga['descricao']??'')?>"
                            data-requisitos="<?=htmlspecialchars($vaga['requisitos']??'')?>"
                            data-salario="<?=htmlspecialchars($vaga['salario']??'')?>"
                            data-localizacao="<?=htmlspecialchars($vaga['localizacao']??'')?>"
                            data-setor="<?=htmlspecialchars($vaga['setor']??'')?>"
                            data-disponibilidade="<?=htmlspecialchars($vaga['disponibilidade']??'')?>"
                            data-escolaridade="<?=htmlspecialchars($vaga['escolaridade']??'')?>"
                            data-experiencia="<?=htmlspecialchars($vaga['experiencia']??'')?>"
                            data-quantidade="<?=htmlspecialchars($vaga['quantidade_vagas']??1)?>"
                            data-status="<?=$st?>"
                            data-imagem="<?=htmlspecialchars($vaga['imagem']??'')?>">
                            <td><span class="table-id">#<?=str_pad($vaga['id'],4,'0',STR_PAD_LEFT)?></span></td>
                            <td><?php if(!empty($vaga['imagem'])):?><img src="../uploads/vagas/<?=htmlspecialchars($vaga['imagem'])?>" class="thumb-vaga" onclick="abrirPreviewImagem(this.src)"><?php else:?><span style="opacity:.4">—</span><?php endif;?></td>
                            <td>
                                <span class="table-title"><?=htmlspecialchars($vaga['titulo'])?></span>
                                <?php if(!empty($vaga['setor'])):?><div class="table-meta">🏷️ <?=htmlspecialchars($vaga['setor'])?></div><?php endif;?>
                            </td>
                            <td><?=htmlspecialchars($vaga['localizacao']??'')?></td>
                            <td><span class="status-badge <?=$st?>"><?=ucfirst($st)?></span></td>
                            <td><span class="candidate-badge"><span>👥</span><span><?=$vaga['total_candidatos']?></span></span></td>
                            <td><?=date('d/m/Y',strtotime($vaga['data_publicacao']))?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="candidatos.php?vaga_id=<?=$vaga['id']?>" class="btn btn-secondary btn-sm btn-icon" data-tooltip="Ver candidatos">👥</a>
                                    <button onclick="openEditModal(this.closest('tr'))" class="btn btn-warning btn-sm btn-icon" data-tooltip="Editar vaga">✏️</button>
                                    <form method="POST"><input type="hidden" name="id" value="<?=$vaga['id']?>"><input type="hidden" name="status" value="<?=$st==='ativa'?'inativa':'ativa'?>"><button name="toggle" class="btn btn-secondary btn-sm btn-icon" data-tooltip="<?=$st==='ativa'?'Desativar':'Ativar'?>"><?=$st==='ativa'?'⏸️':'▶️'?></button></form>
                                    <form method="POST" onsubmit="return confirm('⚠️ Tem certeza que deseja excluir esta vaga?\n\nTodos os dados serão perdidos permanentemente!')"><input type="hidden" name="id" value="<?=$vaga['id']?>"><button name="excluir" class="btn btn-danger btn-sm btn-icon" data-tooltip="Excluir vaga">🗑️</button></form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach;?>
                        </tbody>
                    </table>
                </div>

                <!-- MOBILE CARDS -->
                <div class="mobile-vagas" style="padding:1rem">
                    <?php foreach($vagas as $vaga):
                        $st = strtolower(trim($vaga['status']??'inativa'));
                    ?>
                    <div class="mv-card" data-status="<?=$st?>" data-titulo="<?=htmlspecialchars($vaga['titulo'])?>" data-localizacao="<?=htmlspecialchars($vaga['localizacao']??'')?>">
                        <div class="mv-header">
                            <?php if(!empty($vaga['imagem'])):?><img src="../uploads/vagas/<?=htmlspecialchars($vaga['imagem'])?>" class="mv-thumb"><?php else:?><div class="mv-thumb-ph">💼</div><?php endif;?>
                            <div class="mv-title-area">
                                <div class="mv-titulo"><?=htmlspecialchars($vaga['titulo'])?></div>
                                <div class="mv-meta-line"><span>📍 <?=htmlspecialchars($vaga['localizacao']??'—')?></span><span>👥 <?=$vaga['total_candidatos']?></span></div>
                            </div>
                        </div>
                        <div class="mv-grid">
                            <div class="mv-field"><span class="mv-label">💰 Salário</span><span class="mv-value"><?=htmlspecialchars($vaga['salario']??'A combinar')?></span></div>
                            <div class="mv-field"><span class="mv-label">📅 Publicação</span><span class="mv-value"><?=date('d/m/Y',strtotime($vaga['data_publicacao']))?></span></div>
                            <?php if(!empty($vaga['setor'])):?><div class="mv-field"><span class="mv-label">🏷️ Setor</span><span class="mv-value"><?=htmlspecialchars($vaga['setor'])?></span></div><?php endif;?>
                            <?php if(!empty($vaga['disponibilidade'])):?><div class="mv-field"><span class="mv-label">⏰ Horário</span><span class="mv-value"><?=htmlspecialchars($vaga['disponibilidade'])?></span></div><?php endif;?>
                        </div>
                        <div class="mv-footer">
                            <span class="status-badge <?=$st?>"><?=ucfirst($st)?></span>
                            <div style="display:flex;gap:.35rem">
                                <a href="candidatos.php?vaga_id=<?=$vaga['id']?>" class="btn btn-secondary btn-sm">👥</a>
                                <form method="POST"><input type="hidden" name="id" value="<?=$vaga['id']?>"><input type="hidden" name="status" value="<?=$st==='ativa'?'inativa':'ativa'?>"><button name="toggle" class="btn btn-secondary btn-sm"><?=$st==='ativa'?'⏸️':'▶️'?></button></form>
                                <form method="POST" onsubmit="return confirm('Excluir vaga?')"><input type="hidden" name="id" value="<?=$vaga['id']?>"><button name="excluir" class="btn btn-danger btn-sm">🗑️</button></form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach;?>
                </div>

                <?php else:?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>Nenhuma vaga publicada ainda</h3>
                    <p>Publique sua primeira vaga e comece a receber candidatos!</p>
                    <a href="nova-vaga.php" class="btn btn-primary" style="margin-top:1rem">➕ Publicar Vaga</a>
                </div>
                <?php endif;?>
            </div>
        </div>
    </main>
</div>

<!-- ═══ MODAL DE EDIÇÃO ══════════════════════════════════ -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><span>✏️</span><span>Editar Vaga</span></h3>
            <button class="modal-close" onclick="closeEditModal()">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="imagem_atual" id="edit_imagem_atual">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Título da Vaga <span class="required">*</span></label>
                        <input type="text" name="titulo" id="edit_titulo" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Localização <span class="required">*</span></label>
                        <input type="text" name="localizacao" id="edit_localizacao" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Salário</label>
                        <input type="text" name="salario" id="edit_salario" class="form-input money-input" placeholder="R$ 0,00">
                        <div style="margin-top:6px"><label style="font-size:.85rem;color:var(--text-secondary)"><input type="checkbox" onclick="document.getElementById('edit_salario').value=''"> A combinar</label></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status <span class="required">*</span></label>
                        <select name="status" id="edit_status" class="form-select" required>
                            <option value="ativa">✅ Ativa</option>
                            <option value="inativa">⏸️ Inativa</option>
                            <option value="pausada">🟡 Pausada</option>
                            <option value="encerrada">🔴 Encerrada</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Setor / Área</label>
                        <input type="text" name="setor" id="edit_setor" class="form-input" placeholder="Ex: Tecnologia">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Disponibilidade</label>
                        <input type="text" name="disponibilidade" id="edit_disponibilidade" class="form-input" placeholder="Ex: Integral">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Escolaridade</label>
                        <input type="text" name="escolaridade" id="edit_escolaridade" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Experiência</label>
                        <input type="text" name="experiencia" id="edit_experiencia" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Qtd. Vagas</label>
                        <input type="number" name="quantidade_vagas" id="edit_quantidade" class="form-input" min="1" max="999">
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label">Imagem da Vaga</label>
                        <div id="preview_imagem_container" style="margin-bottom:10px;display:none"><img id="preview_imagem" src="" style="max-width:200px;border-radius:10px;border:1px solid var(--border-color)"></div>
                        <input type="file" name="imagem" class="form-input" accept=".jpg,.jpeg,.png,.webp">
                        <div style="margin-top:6px"><label style="font-size:.85rem;color:var(--danger)"><input type="checkbox" name="remover_imagem" value="1"> Remover imagem atual</label></div>
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label">Descrição da Vaga <span class="required">*</span></label>
                        <textarea name="descricao" id="edit_descricao" class="form-textarea" required></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label">Requisitos</label>
                        <textarea name="requisitos" id="edit_requisitos" class="form-textarea"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" name="salvar" class="btn btn-primary"><span>💾</span><span>Salvar Alterações</span></button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()"><span>❌</span><span>Cancelar</span></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- FULLSCREEN PREVIEW -->
<div id="overlayImagem"><img id="imagemGrande" src=""></div>

<script>
"use strict";

/* ══════════════ THEME ══════════════ */
function toggleTheme(){
    const e=document.documentElement,t=document.getElementById("theme-icon"),tm=document.getElementById("theme-icon-mobile"),a=e.getAttribute("data-theme")||"dark";
    if(a==="dark"){e.setAttribute("data-theme","light");if(t)t.textContent="🌙";if(tm)tm.textContent="🌙";localStorage.setItem("theme","light")}
    else{e.setAttribute("data-theme","dark");if(t)t.textContent="☀️";if(tm)tm.textContent="☀️";localStorage.setItem("theme","dark")}
}

/* ══════════════ SIDEBAR ══════════════ */
function toggleSidebar(){document.getElementById("sidebar").classList.toggle("open");document.getElementById("sidebarOverlay").classList.toggle("open")}
function closeSidebar(){document.getElementById("sidebar").classList.remove("open");document.getElementById("sidebarOverlay").classList.remove("open")}

/* ══════════════ EDIT MODAL ══════════════ */
function openEditModal(tr){
    const d=tr.dataset,modal=document.getElementById("editModal");
    document.getElementById("edit_id").value=d.vagaId;
    document.getElementById("edit_titulo").value=d.titulo;
    document.getElementById("edit_descricao").value=d.descricao;
    document.getElementById("edit_requisitos").value=d.requisitos;
    document.getElementById("edit_salario").value=d.salario;
    document.getElementById("edit_localizacao").value=d.localizacao;
    document.getElementById("edit_status").value=d.status;
    document.getElementById("edit_setor").value=d.setor||'';
    document.getElementById("edit_disponibilidade").value=d.disponibilidade||'';
    document.getElementById("edit_escolaridade").value=d.escolaridade||'';
    document.getElementById("edit_experiencia").value=d.experiencia||'';
    document.getElementById("edit_quantidade").value=d.quantidade||1;
    document.getElementById("edit_imagem_atual").value=d.imagem||'';
    if(d.salario) formatMoneyInput(document.getElementById("edit_salario"));
    const img=d.imagem||'',pc=document.getElementById("preview_imagem_container"),pi=document.getElementById("preview_imagem");
    if(img){pi.src="../uploads/vagas/"+img;pc.style.display="block"}else{pc.style.display="none"}
    modal.classList.add("active");document.body.style.overflow="hidden";
}
function closeEditModal(){document.getElementById("editModal").classList.remove("active");document.body.style.overflow=""}

/* ══════════════ MONEY FORMAT ══════════════ */
function formatMoneyInput(e){let t=e.value.replace(/\D/g,"");if(""===t){e.value="";return}t=(parseInt(t)/100).toFixed(2).replace(".",",").replace(/(\d)(?=(\d{3})+(?!\d))/g,"$1.");e.value="R$ "+t}
function initMoneyInputs(){document.querySelectorAll(".money-input").forEach(e=>{if(e.value&&!e.value.startsWith("R$"))formatMoneyInput(e);e.addEventListener("input",function(){formatMoneyInput(this)});e.addEventListener("focus",function(){if(!this.value)this.value="R$ "});e.addEventListener("blur",function(){if(this.value==="R$ "||this.value==="R$ 0,00")this.value=""})})}

/* ══════════════ PREVIEW IMAGE ══════════════ */
function abrirPreviewImagem(src){const o=document.getElementById("overlayImagem");document.getElementById("imagemGrande").src=src;o.style.display="flex";document.body.style.overflow="hidden"}
document.addEventListener("change",function(e){if(e.target.name==="imagem"&&e.target.files[0]){const r=new FileReader();r.onload=function(ev){const pi=document.getElementById("preview_imagem"),pc=document.getElementById("preview_imagem_container");pi.src=ev.target.result;pc.style.display="block"};r.readAsDataURL(e.target.files[0])}});

/* ══════════════ FILTERS ══════════════ */
function aplicarFiltros(){
    const busca=document.getElementById("buscaGlobal").value.toLowerCase();
    const statusSel=document.getElementById("filtroStatus").value;
    // Desktop rows
    document.querySelectorAll("#vagasTable tbody tr").forEach(row=>{
        const t=(row.dataset.titulo||'').toLowerCase(),l=(row.dataset.localizacao||'').toLowerCase(),s=(row.dataset.status||'');
        const candidatos=row.querySelector(".candidate-badge span:last-child")?.textContent||'';
        const data=row.querySelector("td:nth-child(7)")?.textContent||'';
        const txt=t+" "+l+" "+s+" "+candidatos+" "+data;
        let ok=true;
        if(busca&&!txt.includes(busca))ok=false;
        if(statusSel&&s!==statusSel)ok=false;
        row.style.display=ok?"":"none";
    });
    // Mobile cards
    document.querySelectorAll(".mv-card").forEach(c=>{
        const t=(c.dataset.titulo||'').toLowerCase(),l=(c.dataset.localizacao||'').toLowerCase(),s=(c.dataset.status||'');
        let ok=true;
        if(busca&&!(t+" "+l).includes(busca))ok=false;
        if(statusSel&&s!==statusSel)ok=false;
        c.style.display=ok?"":"none";
    });
}

/* ══════════════ SORT ══════════════ */
let sortDirection={},currentSortColumn=null;
document.querySelectorAll(".sortable").forEach(th=>{
    th.addEventListener("click",function(){
        const col=this.dataset.column,tbody=document.querySelector("#vagasTable tbody"),rows=Array.from(tbody.querySelectorAll("tr"));
        if(currentSortColumn===col)sortDirection[col]=sortDirection[col]==="asc"?"desc":"asc";else sortDirection[col]="asc";
        currentSortColumn=col;
        document.querySelectorAll(".sortable").forEach(e=>e.classList.remove("sorted-asc","sorted-desc"));
        this.classList.add(sortDirection[col]==="asc"?"sorted-asc":"sorted-desc");
        rows.sort((a,b)=>{
            let A,B;
            switch(col){
                case"id":A=parseInt(a.dataset.vagaId);B=parseInt(b.dataset.vagaId);break;
                case"titulo":A=(a.dataset.titulo||'').toLowerCase();B=(b.dataset.titulo||'').toLowerCase();break;
                case"localizacao":A=(a.dataset.localizacao||'').toLowerCase();B=(b.dataset.localizacao||'').toLowerCase();break;
                case"status":A=a.dataset.status;B=b.dataset.status;break;
                case"candidatos":A=parseInt(a.querySelector(".candidate-badge span:last-child").textContent);B=parseInt(b.querySelector(".candidate-badge span:last-child").textContent);break;
                case"data":A=a.querySelector("td:nth-child(7)").textContent.split("/").reverse().join("");B=b.querySelector("td:nth-child(7)").textContent.split("/").reverse().join("");break;
            }
            return sortDirection[col]==="asc"?(A>B?1:A<B?-1:0):(A<B?1:A>B?-1:0);
        });
        rows.forEach(r=>tbody.appendChild(r));
    });
});

/* ══════════════ SIDEBAR TOGGLE (COLLAPSE) ══════════════ */
(function(){
    const layout=document.getElementById('adminLayout'),btn=document.getElementById('sidebarToggle');
    if(!layout||!btn)return;
    const saved=localStorage.getItem('empresa_sidebar')||'open';
    if(saved==='collapsed')layout.classList.add('sidebar-collapsed');
    btn.addEventListener('click',()=>{
        layout.classList.toggle('sidebar-collapsed');
        localStorage.setItem('empresa_sidebar',layout.classList.contains('sidebar-collapsed')?'collapsed':'open');
    });
})();

/* ══════════════ INIT ══════════════ */
window.addEventListener("DOMContentLoaded",()=>{
    const theme=localStorage.getItem("theme")||"dark";
    document.documentElement.setAttribute("data-theme",theme);
    const icon=theme==="light"?"🌙":"☀️";
    document.querySelectorAll('[id^="theme-icon"]').forEach(el=>el.textContent=icon);
    initMoneyInputs();

    // Overlay click
    const ov=document.getElementById("overlayImagem");
    if(ov)ov.addEventListener("click",function(){ov.style.display="none";document.body.style.overflow=""});

    // Filters
    document.getElementById("buscaGlobal")?.addEventListener("keyup",aplicarFiltros);
    document.getElementById("filtroStatus")?.addEventListener("change",aplicarFiltros);
});

/* ══════════════ CLOSE MODALS ══════════════ */
document.getElementById("editModal").addEventListener("click",function(e){if(e.target===this)closeEditModal()});
document.addEventListener("keydown",function(e){if(e.key==="Escape"){closeEditModal();closeSidebar()}});

/* ══════════════ AUTO HIDE ALERTS ══════════════ */
setTimeout(()=>{document.querySelectorAll(".alert").forEach(e=>{e.style.transition="opacity .3s";e.style.opacity="0";setTimeout(()=>e.remove(),300)})},5000);
</script>
</body>
</html>