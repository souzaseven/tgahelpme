<?php
require_once __DIR__ . '/../backend/conexao.php';
/* =========================
   AÇÕES (CRIAR / EDITAR / EXCLUIR / STATUS)
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
/* =====================================================
   CRIAR OU EDITAR VAGA (COM UPLOAD DE IMAGEM)
===================================================== */
if (isset($_POST['salvar'])) {

    /* ---------- DADOS DO FORM ---------- */
    $id           = $_POST['id'] ?? null;
    $titulo       = trim($_POST['titulo'] ?? '');
    $descricao    = trim($_POST['descricao'] ?? '');
    $requisitos   = trim($_POST['requisitos'] ?? '');
    $localizacao  = trim($_POST['localizacao'] ?? '');
    $status       = $_POST['status'] ?? 'ativa';

    /* =====================================================
       TRATAMENTO DO SALÁRIO
    ===================================================== */
    $salarioRaw = $_POST['salario'] ?? '';

    $salarioLimpo = str_replace(['R$', ' ', '.'], '', $salarioRaw);
    $salarioLimpo = str_replace(',', '.', $salarioLimpo);

    if ($salarioLimpo === '' || $salarioLimpo == 0) {
        $salario = null; // A combinar
    } else {
        $salario = (float)$salarioLimpo;
    }

    /* =====================================================
       UPLOAD DE IMAGEM
    ===================================================== */
    $nomeImagem = null;

    if (!empty($_FILES['imagem']['name'])) {

        $uploadDir = __DIR__ . '/../uploads/vagas/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg','jpeg','png','webp'];

        if (!in_array($ext, $permitidas)) {
            header("Location: vagas.php?msg=Formato de imagem inválido!&tipo=danger");
            exit;
        }

        $nomeImagem = uniqid('vaga_') . '.' . $ext;
        $caminhoFinal = $uploadDir . $nomeImagem;

        move_uploaded_file($_FILES['imagem']['tmp_name'], $caminhoFinal);
    }

    /* =====================================================
       UPDATE
    ===================================================== */
    if ($id) {

        if ($nomeImagem) {

            $stmt = $pdo->prepare("
                UPDATE vagas SET 
                    titulo = ?,
                    descricao = ?,
                    requisitos = ?,
                    salario = ?,
                    localizacao = ?,
                    status = ?,
                    imagem = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $titulo,
                $descricao,
                $requisitos,
                $salario,
                $localizacao,
                $status,
                $nomeImagem,
                $id
            ]);

        } else {

            $stmt = $pdo->prepare("
                UPDATE vagas SET 
                    titulo = ?,
                    descricao = ?,
                    requisitos = ?,
                    salario = ?,
                    localizacao = ?,
                    status = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $titulo,
                $descricao,
                $requisitos,
                $salario,
                $localizacao,
                $status,
                $id
            ]);
        }

        $mensagem = "Vaga atualizada com sucesso!";
        $tipoMensagem = "success";
    }

    /* =====================================================
       INSERT
    ===================================================== */
    else {

        $stmt = $pdo->prepare("
            INSERT INTO vagas
            (titulo, descricao, requisitos, salario, localizacao, status, imagem)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $titulo,
            $descricao,
            $requisitos,
            $salario,
            $localizacao,
            $status,
            $nomeImagem
        ]);

        $mensagem = "Vaga criada com sucesso!";
        $tipoMensagem = "success";
    }

    header("Location: vagas.php?msg=" . urlencode($mensagem) . "&tipo=" . $tipoMensagem);
    exit;
}



    /* =====================================================
       EXCLUIR VAGA
    ===================================================== */
    if (isset($_POST['excluir'])) {

        $stmt = $pdo->prepare("DELETE FROM vagas WHERE id = ?");
        $stmt->execute([$_POST['id']]);

        header("Location: vagas.php?msg=Vaga excluída com sucesso!&tipo=success");
        exit;
    }


    /* =====================================================
       ALTERAR STATUS (ATIVAR / DESATIVAR)
    ===================================================== */
    if (isset($_POST['toggle'])) {

        $stmt = $pdo->prepare("UPDATE vagas SET status = ? WHERE id = ?");
        $stmt->execute([$_POST['status'], $_POST['id']]);

        $novoStatus = $_POST['status'] == 'ativa' ? 'ativada' : 'desativada';

        header("Location: vagas.php?msg=Vaga {$novoStatus} com sucesso!&tipo=success");
        exit;
    }
}


/* =========================
   LISTAR COM ESTATÍSTICAS
========================= */
$vagas = $pdo->query("
    SELECT v.*, COUNT(c.id) as total_candidatos
    FROM vagas v
    LEFT JOIN candidaturas c ON v.id = c.vaga_id
    GROUP BY v.id
    ORDER BY v.data_publicacao DESC
")->fetchAll();

// Estatísticas
$totalVagas = count($vagas);
$vagasAtivas = count(array_filter($vagas, fn($v) => $v['status'] === 'ativa'));
$vagasInativas = $totalVagas - $vagasAtivas;
$totalCandidaturas = array_sum(array_column($vagas, 'total_candidatos'));
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Vagas - TGA Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">


<!--icone da pagina-->
<link rel="icon" href="http://tgameajuda.com/Projetos/tgacarreiras/img/icone_logo.ico" type="image/x-icon">


<!-- Google AdSense -->
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044" crossorigin="anonymous"></script>

    <!-- Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-S8EC5C2WTG"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-S8EC5C2WTG');
    </script>
    <style>
        :root {
            --primary-blue: #0066FF;
            --primary-dark: #0052CC;
            --primary-light: #3385FF;
            --bg-primary: #0F172A;
            --bg-secondary: #1E293B;
            --bg-tertiary: #334155;
            --text-primary: #F1F5F9;
            --text-secondary: #CBD5E1;
            --text-tertiary: #64748B;
            --border-color: #334155;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.4);
            --shadow-xl: 0 20px 40px rgba(0, 102, 255, 0.3);
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
        }

        [data-theme="light"] {
            --bg-primary: #FAFBFC;
            --bg-secondary: #F5F7FA;
            --bg-tertiary: #EDF0F5;
            --text-primary: #1A202C;
            --text-secondary: #4A5568;
            --text-tertiary: #718096;
            --border-color: #E2E8F0;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.07);
            --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.08);
            --shadow-xl: 0 20px 40px rgba(0, 102, 255, 0.12);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            line-height: 1.6;
        }
        .admin-layout { display: flex; min-height: 100vh; }
        .sidebar {
            width: 280px; background: var(--bg-primary);
            border-right: 1px solid var(--border-color);
            padding: 2rem 0; position: fixed;
            height: 100vh; overflow-y: auto; z-index: 1000;
        }
        .sidebar-header { padding: 0 1.5rem 2rem; border-bottom: 1px solid var(--border-color); }
        .logo { display: flex; align-items: center; gap: 0.75rem; }
        .logo-icon {
            width: 45px; height: 45px;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-dark));
            border-radius: 12px; display: flex;
            align-items: center; justify-content: center; font-size: 1.5rem;
        }
        .logo-text h1 { font-size: 1.4rem; font-weight: 800; color: var(--text-primary); }
        .logo-text p {
            font-size: 0.75rem; color: var(--text-tertiary);
            font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .sidebar-nav { padding: 1.5rem 0; }
        .nav-section { margin-bottom: 1.5rem; }
        .nav-section-title {
            padding: 0 1.5rem; font-size: 0.7rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1px;
            color: var(--text-tertiary); margin-bottom: 0.75rem;
        }
        .nav-item {
            display: flex; align-items: center; gap: 1rem;
            padding: 0.875rem 1.5rem; color: var(--text-secondary);
            text-decoration: none; font-weight: 500; font-size: 0.95rem;
            transition: all 0.2s; border-left: 3px solid transparent;
        }
        .nav-item:hover {
            background: var(--bg-secondary); color: var(--text-primary);
            border-left-color: var(--primary-blue);
        }
        .nav-item.active {
            background: var(--bg-secondary); color: var(--primary-blue);
            border-left-color: var(--primary-blue); font-weight: 600;
        }
        .nav-icon { font-size: 1.25rem; width: 24px; text-align: center; }
        .main-content {
            flex: 1; margin-left: 280px; padding: 2rem;
        }
        .page-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;
        }
        .page-title h2 {
            font-size: 2rem; font-weight: 800;
            color: var(--text-primary); margin-bottom: 0.25rem;
        }
        .page-title p { color: var(--text-secondary); font-size: 0.95rem; }
        .header-actions { display: flex; gap: 1rem; align-items: center; }
        .theme-toggle {
            width: 44px; height: 44px; border: 2px solid var(--border-color);
            border-radius: 10px; background: var(--bg-primary);
            cursor: pointer; display: flex; align-items: center;
            justify-content: center; font-size: 1.25rem; transition: all 0.3s;
        }
        .theme-toggle:hover {
            background: var(--primary-blue); border-color: var(--primary-blue);
            transform: scale(1.05);
        }
        .btn {
            padding: 0.75rem 1.5rem; border-radius: 10px; text-decoration: none;
            font-weight: 600; font-size: 0.95rem; border: none; cursor: pointer;
            display: inline-flex; align-items: center; gap: 0.5rem;
            transition: all 0.3s; white-space: nowrap;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-dark));
            color: white; box-shadow: 0 4px 12px rgba(0, 102, 255, 0.25);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0, 102, 255, 0.35); }
        .btn-secondary { background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-color); }
        .btn-warning { background: var(--warning); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.85rem; }
        .stats-mini {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem; margin-bottom: 2rem;
        }
        .stat-mini {
            background: var(--bg-primary); border-radius: 12px;
            padding: 1.25rem; border: 1px solid var(--border-color);
            display: flex; align-items: center; gap: 1rem;
        }
        .stat-mini-icon {
            width: 45px; height: 45px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; flex-shrink: 0;
        }
        .stat-mini-icon.blue { background: rgba(0, 102, 255, 0.1); color: var(--primary-blue); }
        .stat-mini-icon.green { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .stat-mini-icon.orange { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .stat-mini-icon.purple { background: rgba(139, 92, 246, 0.1); color: #8B5CF6; }
        .stat-mini-label {
            font-size: 0.8rem; color: var(--text-tertiary);
            font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .stat-mini-value { font-size: 1.75rem; font-weight: 800; color: var(--text-primary); }
        .card {
            background: var(--bg-primary); border-radius: 16px;
            border: 1px solid var(--border-color); box-shadow: var(--shadow-md);
            margin-bottom: 2rem; overflow: hidden;
        }
        .card-header {
            padding: 1.5rem 2rem; border-bottom: 1px solid var(--border-color);
            display: flex; justify-content: space-between; align-items: center;
        }
        .card-title { font-size: 1.25rem; font-weight: 700; color: var(--text-primary); }
        .form-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-label { font-weight: 600; font-size: 0.9rem; color: var(--text-primary); }
        .form-label .required { color: var(--danger); }
        .form-input, .form-textarea, .form-select {
            padding: 0.875rem 1rem; border-radius: 10px;
            border: 2px solid var(--border-color); background: var(--bg-secondary);
            color: var(--text-primary); font-size: 0.95rem; font-family: inherit;
            transition: all 0.3s;
        }
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none; border-color: var(--primary-blue);
            box-shadow: 0 0 0 4px rgba(0, 102, 255, 0.1);
        }
        .form-textarea { min-height: 120px; resize: vertical; }
        .form-actions {
            display: flex; gap: 1rem; margin-top: 2rem;
            padding-top: 2rem; border-top: 1px solid var(--border-color);
        }
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px);
            z-index: 2000; align-items: center; justify-content: center;
            padding: 1rem; animation: fadeIn 0.3s;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: var(--bg-primary); border-radius: 20px;
            box-shadow: var(--shadow-xl); width: 100%; max-width: 900px;
            max-height: 90vh; overflow: hidden; animation: slideUp 0.3s;
            border: 1px solid var(--border-color);
        }
        .modal-header {
            padding: 1.75rem 2rem; border-bottom: 1px solid var(--border-color);
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-title {
            font-size: 1.5rem; font-weight: 700; color: var(--text-primary);
            display: flex; align-items: center; gap: 0.75rem;
        }
        .modal-close {
            width: 40px; height: 40px; border-radius: 10px;
            background: var(--bg-secondary); border: 1px solid var(--border-color);
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; transition: all 0.3s; color: var(--text-secondary);
        }
        .modal-close:hover {
            background: var(--danger); border-color: var(--danger);
            color: white; transform: rotate(90deg);
        }
        .modal-body { padding: 2rem; overflow-y: auto; max-height: calc(90vh - 140px); }
        .table-container { overflow-x: auto; }
        .modern-table { width: 100%; border-collapse: collapse; }
        .modern-table thead { background: var(--bg-secondary); }
        .modern-table th {
            padding: 1rem 1.5rem; text-align: left; font-size: 0.8rem;
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
            color: var(--text-tertiary); border-bottom: 1px solid var(--border-color);
            cursor: pointer; user-select: none; transition: all 0.2s;
        }
        .modern-table th:hover { background: var(--bg-tertiary); color: var(--text-primary); }
        .modern-table th.sortable::after {
            content: '⇅'; margin-left: 0.5rem; opacity: 0.3; font-size: 0.9rem;
        }
        .modern-table th.sorted-asc::after {
            content: '↑'; opacity: 1; color: var(--primary-blue);
        }
        .modern-table th.sorted-desc::after {
            content: '↓'; opacity: 1; color: var(--primary-blue);
        }
        .modern-table td {
            padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary); font-size: 0.95rem;
        }
        .modern-table tbody tr { transition: background 0.2s; }
        .modern-table tbody tr:hover { background: var(--bg-secondary); }
        .table-id { font-weight: 700; color: var(--text-tertiary); font-size: 0.85rem; }
        .table-title { font-weight: 600; color: var(--text-primary); }
        .status-badge {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.375rem 0.875rem; border-radius: 20px;
            font-size: 0.8rem; font-weight: 600; text-transform: capitalize;
        }

        .status-badge.ativa { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .status-badge.inativa { background: rgba(100, 116, 139, 0.1); color: var(--text-tertiary); }
     .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    white-space: nowrap;
}

/* Remove problema de renderização */
.status-badge {
    position: relative;
    padding-left: 20px;
}

.status-badge::before {
    content: "";
    position: absolute;
    left: 8px;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}


        .candidate-badge {
            display: inline-flex; align-items: center; gap: 0.5rem;
            background: var(--bg-secondary); padding: 0.375rem 0.875rem;
            border-radius: 20px; font-weight: 600; color: var(--text-primary);
            font-size: 0.85rem;
        }
        .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .action-buttons form { display: inline; }
        .alert {
            padding: 1rem 1.5rem; border-radius: 12px; margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: 1rem; animation: slideDown 0.3s;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--success);
        }
        .alert-icon { font-size: 1.5rem; }
        .empty-state {
            text-align: center; padding: 4rem 2rem; color: var(--text-tertiary);
        }
        .empty-state-icon { font-size: 4rem; margin-bottom: 1rem; opacity: 0.5; }
        .empty-state h3 {
            font-size: 1.25rem; color: var(--text-primary); margin-bottom: 0.5rem;
        }
        .breadcrumb {
            display: flex; align-items: center; gap: 0.5rem;
            margin-bottom: 1.5rem; font-size: 0.9rem;
        }
        .breadcrumb a {
            color: var(--text-secondary); text-decoration: none; transition: color 0.2s;
        }
        .breadcrumb a:hover { color: var(--primary-blue); }
        .breadcrumb span { color: var(--text-tertiary); }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .form-grid { grid-template-columns: 1fr; }
            .modal { max-width: 100%; border-radius: 20px 20px 0 0; max-height: 95vh; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 1rem; }
            .stats-mini { grid-template-columns: 1fr; }
            .action-buttons { flex-direction: column; }
            .action-buttons .btn { width: 100%; justify-content: center; }
        }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }


:root{
  --sidebar-open: 280px;
  --sidebar-collapsed: 84px;
}

.sidebar{
  width: var(--sidebar-open);
  transition: width .25s ease;
}

.main-content{
  margin-left: var(--sidebar-open);
  transition: margin-left .25s ease;
}

/* Botão recolher */
.sidebar-toggle{
  margin-left: auto;
  width: 40px; height: 40px;
  border-radius: 10px;
  border: 1px solid var(--border-color);
  background: var(--bg-secondary);
  color: var(--text-primary);
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: .2s ease;
}
.sidebar-toggle:hover{
  background: var(--primary-blue);
  border-color: var(--primary-blue);
  transform: scale(1.05);
}

/* Estado recolhido */
.admin-layout.sidebar-collapsed .sidebar{ width: var(--sidebar-collapsed); }
.admin-layout.sidebar-collapsed .main-content{ margin-left: var(--sidebar-collapsed); }

.admin-layout.sidebar-collapsed .logo-text,
.admin-layout.sidebar-collapsed .nav-section-title,
.admin-layout.sidebar-collapsed .nav-item span:not(.nav-icon){
  display: none;
}

.admin-layout.sidebar-collapsed .nav-item{
  justify-content: center;
  padding: 0.9rem 0;
  gap: 0;
}

.admin-layout.sidebar-collapsed .nav-item{
  position: relative;
}
.admin-layout.sidebar-collapsed .nav-item::after{
  content: attr(data-label);
  position: absolute;
  left: calc(100% + 10px);
  top: 50%;
  transform: translateY(-50%);
  background: #111827;
  color: #fff;
  padding: 6px 10px;
  border-radius: 8px;
  font-size: .78rem;
  white-space: nowrap;
  opacity: 0;
  pointer-events: none;
  transition: .2s ease;
  box-shadow: 0 10px 30px rgba(0,0,0,.35);
}
.admin-layout.sidebar-collapsed .nav-item:hover::after{
  opacity: 1;
}

    </style>
</head>
<body>

<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">⚙️</div>
                <div class="logo-text">
                    <h1>TGA Admin</h1>
                    <p>Developer Panel</p>
                </div>

                <!-- Botão recolher -->
                <button class="sidebar-toggle" id="sidebarToggle" type="button" title="Ocultar/Mostrar menu">
                    ☰
                </button>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Principal</div>

                <a href="index.php" class="nav-item" data-label="Dashboard">
                    <span class="nav-icon">📊</span>
                    <span>Dashboard</span>
                </a>

                <a href="vagas.php" class="nav-item active" data-label="Vagas">
                    <span class="nav-icon">💼</span>
                    <span>Vagas</span>
                </a>

                <a href="candidaturas.php" class="nav-item" data-label="Candidaturas">
                    <span class="nav-icon">👥</span>
                    <span>Candidaturas</span>
                </a>
            </div>

           
             <div class="nav-section">
                <div class="nav-section-title">Configurações</div>

                <a href="#" class="nav-item">
                    <span class="nav-icon">⚙️</span>
                    <span>Configurações</span>
                </a>

                
                 <a href="perfil.php" class="nav-item" data-label="Perfil">
                    <span class="nav-icon">👤</span>
                    <span>Perfil</span>
                </a>

                <!-- Logout corrigido: antes apontava para "#" -->
                <a href="logout.php" class="nav-item danger">
                    <span class="nav-icon">🚪</span>
                    <span>Sair</span>
                </a>
            </div>
            
        </nav>
    </aside>


    <main class="main-content">
        <div class="breadcrumb">
            <a href="index.php">Dashboard</a><span>›</span><span>Gerenciar Vagas</span>
        </div>

        <div class="page-header">
            <div class="page-title">
                <h2>💼 Gerenciar Vagas</h2>
                <p>Crie, edite e organize todas as vagas disponíveis</p>
            </div>
            <div class="header-actions">
                <button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema">
                    <span id="theme-icon">☀️</span>
                </button>
                <button class="btn btn-primary" onclick="openCreateModal()">
                    <span>➕</span><span>Nova Vaga</span>
                </button>
            </div>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-<?= $_GET['tipo'] ?? 'success' ?>">
                <span class="alert-icon"><?= $_GET['tipo'] === 'success' ? '✅' : '❌' ?></span>
                <span><?= htmlspecialchars($_GET['msg']) ?></span>
            </div>
        <?php endif; ?>

        <div class="stats-mini">
            <div class="stat-mini">
                <div class="stat-mini-icon blue">💼</div>
                <div class="stat-mini-content">
                    <div class="stat-mini-label">Total</div>
                    <div class="stat-mini-value"><?= $totalVagas ?></div>
                </div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon green">✅</div>
                <div class="stat-mini-content">
                    <div class="stat-mini-label">Ativas</div>
                    <div class="stat-mini-value"><?= $vagasAtivas ?></div>
                </div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon orange">⏸️</div>
                <div class="stat-mini-content">
                    <div class="stat-mini-label">Inativas</div>
                    <div class="stat-mini-value"><?= $vagasInativas ?></div>
                </div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon purple">👥</div>
                <div class="stat-mini-content">
                    <div class="stat-mini-label">Candidatos</div>
                    <div class="stat-mini-value"><?= $totalCandidaturas ?></div>
                </div>
            </div>
        </div><div class="card">

    <div class="card-header card-header-flex">

        <h3 class="card-title">📋 Todas as Vagas</h3>

        <div class="filtros-container">

            <!-- BUSCA GLOBAL -->
            <input type="text"
                   id="buscaGlobal"
                   placeholder="Buscar por título, localização, status, candidatos ou data..."
                   class="form-input busca-input">

            <!-- FILTRO STATUS -->
            <select id="filtroStatus"
                    class="form-select filtro-select">
                <option value="">Todos os status</option>
                <option value="ativa">Ativa</option>
                <option value="inativa">Inativa</option>
            </select>

        </div>

    </div>
<style>
.card-header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.filtros-container {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.busca-input {
    width: 640px;
    max-width: 1080px;
}

.filtro-select {
    width: 200px;
}

</style>
    <?php if (count($vagas) > 0): ?>
        <div class="table-container">
            <table class="modern-table" id="vagasTable">
                <thead>
                    <tr>
                        <th class="sortable" data-column="id">ID</th>
                        <th>Imagem</th>
                        <th class="sortable" data-column="titulo">Título</th>
                        <th class="sortable" data-column="localizacao">Localização</th>
                        <th class="sortable" data-column="status">Status</th>
                        <th class="sortable" data-column="candidatos">Candidatos</th>
                        <th class="sortable" data-column="data">Publicação</th>
                        <th>Ações</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($vagas as $vaga): ?>
                    <tr 
                        data-vaga-id="<?= $vaga['id'] ?>"
                        data-titulo="<?= htmlspecialchars($vaga['titulo']) ?>"
                        data-descricao="<?= htmlspecialchars($vaga['descricao']) ?>"
                        data-requisitos="<?= htmlspecialchars($vaga['requisitos']) ?>"
                        data-salario="<?= htmlspecialchars($vaga['salario']) ?>"
                        data-localizacao="<?= htmlspecialchars($vaga['localizacao']) ?>"
                        data-status="<?= strtolower(trim($vaga['status'])) ?>"

                        data-imagem="<?= htmlspecialchars($vaga['imagem'] ?? '') ?>"
                    >

                        <!-- ID -->
                        <td>
                            <span class="table-id">
                                #<?= str_pad($vaga['id'], 4, '0', STR_PAD_LEFT) ?>
                            </span>
                        </td>

                        <!-- IMAGEM -->
                        <td>
                            <?php if (!empty($vaga['imagem'])): ?>
                        <img 
    src="../uploads/vagas/<?= htmlspecialchars($vaga['imagem']) ?>"
    class="thumb-vaga"
    onclick="abrirPreviewImagem(this.src)"
    style="
        width:60px;
        height:60px;
        object-fit:cover;
        border-radius:10px;
        border:1px solid #334155;
        cursor:pointer;
        transition:transform .2s;
    ">

                            <?php else: ?>
                                <span style="opacity:.4;">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- TÍTULO -->
                        <td>
                            <span class="table-title">
                                <?= htmlspecialchars($vaga['titulo']) ?>
                            </span>
                        </td>

                        <!-- LOCALIZAÇÃO -->
                        <td><?= htmlspecialchars($vaga['localizacao']) ?></td>

                        <!-- STATUS -->
                  <?php 
$status = strtolower(trim($vaga['status'] ?? 'inativa'));

if (!in_array($status, ['ativa','inativa'])) {
    $status = 'inativa';
}
?>

<td>
    <span class="status-badge <?= $status ?>">
        <?= ucfirst($status) ?>
    </span>
</td>



                        <!-- CANDIDATOS -->
                        <td>
                            <span class="candidate-badge">
                                <span>👥</span>
                                <span><?= $vaga['total_candidatos'] ?></span>
                            </span>
                        </td>

                        <!-- DATA -->
                        <td>
                            <?= date('d/m/Y', strtotime($vaga['data_publicacao'])) ?>
                        </td>
<!-- AÇÕES -->
<td>
    <div class="action-buttons">

        <!-- EDITAR -->
        <button 
            onclick="openEditModal(this.closest('tr'))" 
            class="btn btn-warning btn-sm btn-icon"
            data-tooltip="Editar vaga">
            ✏️
        </button>

        <!-- ATIVAR / DESATIVAR -->
        <form method="POST">
            <input type="hidden" name="id" value="<?= $vaga['id'] ?>">
            <input type="hidden" name="status"
                   value="<?= $vaga['status'] == 'ativa' ? 'inativa' : 'ativa' ?>">

            <button name="toggle"
                    class="btn btn-secondary btn-sm btn-icon"
                    data-tooltip="<?= $vaga['status'] == 'ativa' ? 'Desativar vaga' : 'Ativar vaga' ?>">
                <?= $vaga['status'] == 'ativa' ? '⏸️' : '▶️' ?>
            </button>
        </form>

        <!-- EXCLUIR -->
        <form method="POST"
              onsubmit="return confirm('⚠️ Tem certeza que deseja excluir esta vaga?\n\nTodos os dados serão perdidos permanentemente!')">
            <input type="hidden" name="id" value="<?= $vaga['id'] ?>">

            <button name="excluir"
                    class="btn btn-danger btn-sm btn-icon"
                    data-tooltip="Excluir vaga">
                🗑️
            </button>
        </form>

    </div>
</td>
<style>
.btn-icon {
    width: 40px;
    height: 40px;
    padding: 0;
    justify-content: center;
    position: relative;
}

/* TOOLTIP */
.btn-icon::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 120%;
    left: 50%;
    transform: translateX(-50%);
    background: #111827;
    color: #fff;
    font-size: 0.75rem;
    padding: 6px 10px;
    border-radius: 6px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: 0.2s ease;
    box-shadow: 0 5px 20px rgba(0,0,0,.4);
}

.btn-icon:hover::after {
    opacity: 1;
}
</style>

                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">📭</div>
            <h3>Nenhuma vaga cadastrada</h3>
            <p>Comece criando sua primeira vaga de emprego</p>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de Criação -->
<div class="modal-overlay" id="createModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><span>➕</span><span>Nova Vaga</span></h3>
            <button class="modal-close" onclick="closeCreateModal()">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">

                    <!-- TÍTULO -->
                    <div class="form-group">
                        <label class="form-label">Título da Vaga <span class="required">*</span></label>
                        <input type="text" name="titulo" class="form-input"
                               placeholder="Ex: Desenvolvedor Full Stack" required>
                    </div>

                    <!-- LOCALIZAÇÃO -->
                    <div class="form-group">
                        <label class="form-label">Localização <span class="required">*</span></label>
                        <input type="text" name="localizacao" class="form-input"
                               placeholder="Ex: São Paulo, SP - Remoto" required>
                    </div>

                    <!-- SALÁRIO -->
                    <div class="form-group">
                        <label class="form-label">Salário</label>
                        <input type="text" name="salario"
                               class="form-input money-input"
                               placeholder="R$ 0,00">

                        <div style="margin-top:6px;">
                            <label style="font-size: 0.85rem; color: var(--text-secondary);">
                                <input type="checkbox"
                                       onclick="document.querySelector('input[name=salario]').value=''">
                                A combinar
                            </label>
                        </div>
                    </div>

                    <!-- STATUS -->
                    <div class="form-group">
                        <label class="form-label">Status <span class="required">*</span></label>
                        <select name="status" class="form-select" required>
                            <option value="ativa">✅ Ativa</option>
                            <option value="inativa">⏸️ Inativa</option>
                        </select>
                    </div>

                    <!-- IMAGEM -->
                    <div class="form-group full-width">
                        <label class="form-label">Imagem da Vaga</label>
                        <input type="file"
                               name="imagem"
                               class="form-input"
                               accept=".jpg,.jpeg,.png,.webp">

                        <small style="color: var(--text-tertiary); font-size:0.8rem;">
                            Formatos permitidos: JPG, PNG ou WEBP
                        </small>
                    </div>

                    <!-- DESCRIÇÃO -->
                    <div class="form-group full-width">
                        <label class="form-label">Descrição da Vaga <span class="required">*</span></label>
                        <textarea name="descricao"
                                  class="form-textarea"
                                  placeholder="Descreva as responsabilidades, benefícios e informações sobre a vaga..."
                                  required></textarea>
                    </div>

                    <!-- REQUISITOS -->
                    <div class="form-group full-width">
                        <label class="form-label">Requisitos</label>
                        <textarea name="requisitos"
                                  class="form-textarea"
                                  placeholder="Liste os requisitos necessários para a vaga (formação, experiência, habilidades)..."></textarea>
                    </div>

                </div>

                <div class="form-actions">
                    <button type="submit" name="salvar" class="btn btn-primary">
                        <span>➕</span><span>Criar Vaga</span>
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">
                        <span>❌</span><span>Cancelar</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Edição -->
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

                    <!-- TÍTULO -->
                    <div class="form-group">
                        <label class="form-label">Título da Vaga <span class="required">*</span></label>
                        <input type="text" name="titulo" id="edit_titulo" class="form-input" required>
                    </div>

                    <!-- LOCALIZAÇÃO -->
                    <div class="form-group">
                        <label class="form-label">Localização <span class="required">*</span></label>
                        <input type="text" name="localizacao" id="edit_localizacao" class="form-input" required>
                    </div>

                    <!-- SALÁRIO -->
                    <div class="form-group">
                        <label class="form-label">Salário</label>
                        <input type="text" name="salario" id="edit_salario"
                               class="form-input money-input"
                               placeholder="R$ 0,00">

                        <div style="margin-top:6px;">
                            <label style="font-size: 0.85rem; color: var(--text-secondary);">
                                <input type="checkbox"
                                       onclick="document.getElementById('edit_salario').value=''">
                                A combinar
                            </label>
                        </div>
                    </div>

                    <!-- STATUS -->
                    <div class="form-group">
                        <label class="form-label">Status <span class="required">*</span></label>
                        <select name="status" id="edit_status" class="form-select" required>
                            <option value="ativa">✅ Ativa</option>
                            <option value="inativa">⏸️ Inativa</option>
                        </select>
                    </div>

                    <!-- IMAGEM -->
                    <div class="form-group full-width">
                        <label class="form-label">Imagem da Vaga</label>

                        <!-- Preview -->
                        <div id="preview_imagem_container" style="margin-bottom:10px; display:none;">
                            <img id="preview_imagem"
                                 src=""
                                 style="max-width:200px; border-radius:10px; border:1px solid var(--border-color);">
                        </div>

                        <input type="file"
                               name="imagem"
                               class="form-input"
                               accept=".jpg,.jpeg,.png,.webp">

                        <div style="margin-top:6px;">
                            <label style="font-size: 0.85rem; color: var(--danger);">
                                <input type="checkbox" name="remover_imagem" value="1">
                                Remover imagem atual
                            </label>
                        </div>
                    </div>

                    <!-- DESCRIÇÃO -->
                    <div class="form-group full-width">
                        <label class="form-label">Descrição da Vaga <span class="required">*</span></label>
                        <textarea name="descricao" id="edit_descricao" class="form-textarea" required></textarea>
                    </div>

                    <!-- REQUISITOS -->
                    <div class="form-group full-width">
                        <label class="form-label">Requisitos</label>
                        <textarea name="requisitos" id="edit_requisitos" class="form-textarea"></textarea>
                    </div>

                </div>

                <div class="form-actions">
                    <button type="submit" name="salvar" class="btn btn-primary">
                        <span>💾</span><span>Salvar Alterações</span>
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                        <span>❌</span><span>Cancelar</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleTheme(){
    const e=document.documentElement,
          t=document.getElementById("theme-icon"),
          a=e.getAttribute("data-theme")||"dark";
    "dark"===a
        ?(e.setAttribute("data-theme","light"),t.textContent="🌙",localStorage.setItem("theme","light"))
        :(e.setAttribute("data-theme","dark"),t.textContent="☀️",localStorage.setItem("theme","dark"))
}

function openCreateModal(){
    document.getElementById("createModal").classList.add("active");
    document.body.style.overflow="hidden";
}

function closeCreateModal(){
    document.getElementById("createModal").classList.remove("active");
    document.body.style.overflow="";
}

function openEditModal(tr){
    const modal=document.getElementById("editModal");

    document.getElementById("edit_id").value=tr.dataset.vagaId;
    document.getElementById("edit_titulo").value=tr.dataset.titulo;
    document.getElementById("edit_descricao").value=tr.dataset.descricao;
    document.getElementById("edit_requisitos").value=tr.dataset.requisitos;
    document.getElementById("edit_salario").value=tr.dataset.salario;
    document.getElementById("edit_localizacao").value=tr.dataset.localizacao;
    document.getElementById("edit_status").value=tr.dataset.status;

    if(tr.dataset.salario){
        formatMoneyInput(document.getElementById("edit_salario"));
    }

    /* ========= IMAGEM ========= */
    const imagem = tr.dataset.imagem || '';
    document.getElementById("edit_imagem_atual").value = imagem;

    const previewContainer = document.getElementById("preview_imagem_container");
    const preview = document.getElementById("preview_imagem");

    if(imagem){
        preview.src = "../uploads/vagas/" + imagem;
        previewContainer.style.display = "block";
    }else{
        previewContainer.style.display = "none";
    }

    modal.classList.add("active");
    document.body.style.overflow="hidden";
}

function closeEditModal(){
    document.getElementById("editModal").classList.remove("active");
    document.body.style.overflow="";
}

function formatMoneyInput(e){
    let t=e.value.replace(/\D/g,"");
    if(""===t){ e.value=""; return; }
    t=(parseInt(t)/100).toFixed(2);
    t=t.replace(".",",");
    t=t.replace(/(\d)(?=(\d{3})+(?!\d))/g,"$1.");
    e.value="R$ "+t;
}

function initMoneyInputs(){
    document.querySelectorAll(".money-input").forEach(e=>{
        if(e.value && !e.value.startsWith("R$")){
            formatMoneyInput(e);
        }
        e.addEventListener("input",function(){formatMoneyInput(this)});
        e.addEventListener("focus",function(){if(!this.value)this.value="R$ "});
        e.addEventListener("blur",function(){
            if(this.value==="R$ "||this.value==="R$ 0,00"){
                this.value="";
            }
        });
    });
}

/* ========= PREVIEW AO SELECIONAR NOVA IMAGEM ========= */
document.addEventListener("change",function(e){
    if(e.target.name==="imagem"){
        const file=e.target.files[0];
        if(file){
            const reader=new FileReader();
            reader.onload=function(ev){
                const preview=document.getElementById("preview_imagem");
                const container=document.getElementById("preview_imagem_container");
                preview.src=ev.target.result;
                container.style.display="block";
            };
            reader.readAsDataURL(file);
        }
    }
});

window.addEventListener("DOMContentLoaded",()=>{
    const theme=localStorage.getItem("theme")||"dark";
    const html=document.documentElement;
    const icon=document.getElementById("theme-icon");
    html.setAttribute("data-theme",theme);
    icon.textContent=theme==="light"?"🌙":"☀️";
    initMoneyInputs();
});

/* ========= ALERT AUTO HIDE ========= */
setTimeout(()=>{
    document.querySelectorAll(".alert").forEach(e=>{
        e.style.opacity="0";
        setTimeout(()=>e.remove(),300);
    });
},5000);

/* ========= FECHAR MODAIS ========= */
document.getElementById("editModal").addEventListener("click",function(e){
    if(e.target===this) closeEditModal();
});
document.getElementById("createModal").addEventListener("click",function(e){
    if(e.target===this) closeCreateModal();
});
document.addEventListener("keydown",function(e){
    if(e.key==="Escape"){
        closeEditModal();
        closeCreateModal();
    }
});

/* ========= ORDENAÇÃO ========= */
let sortDirection={},currentSortColumn=null;

document.querySelectorAll(".sortable").forEach(th=>{
    th.addEventListener("click",function(){
        const column=this.dataset.column;
        const table=document.getElementById("vagasTable");
        const tbody=table.querySelector("tbody");
        const rows=Array.from(tbody.querySelectorAll("tr"));

        if(currentSortColumn===column){
            sortDirection[column]=sortDirection[column]==="asc"?"desc":"asc";
        }else{
            sortDirection[column]="asc";
        }
        currentSortColumn=column;

        document.querySelectorAll(".sortable").forEach(e=>{
            e.classList.remove("sorted-asc","sorted-desc");
        });

        this.classList.add(sortDirection[column]==="asc"?"sorted-asc":"sorted-desc");

        rows.sort((a,b)=>{
            let A,B;

            switch(column){
                case "id":
                    A=parseInt(a.dataset.vagaId);
                    B=parseInt(b.dataset.vagaId);
                    break;
                case "titulo":
                    A=a.dataset.titulo.toLowerCase();
                    B=b.dataset.titulo.toLowerCase();
                    break;
                case "localizacao":
                    A=a.dataset.localizacao.toLowerCase();
                    B=b.dataset.localizacao.toLowerCase();
                    break;
                case "status":
                    A=a.dataset.status;
                    B=b.dataset.status;
                    break;
                case "candidatos":
                    A=parseInt(a.querySelector(".candidate-badge span:last-child").textContent);
                    B=parseInt(b.querySelector(".candidate-badge span:last-child").textContent);
                    break;
                case "data":
                    A=a.querySelector("td:nth-child(6)").textContent.split("/").reverse().join("");
                    B=b.querySelector("td:nth-child(6)").textContent.split("/").reverse().join("");
                    break;
            }

            if(sortDirection[column]==="asc"){
                return A>B?1:A<B?-1:0;
            }else{
                return A<B?1:A>B?-1:0;
            }
        });

        rows.forEach(row=>tbody.appendChild(row));
    });
});

/* =========================================
   PREVIEW IMAGEM FULLSCREEN
========================================= */

function abrirPreviewImagem(src){
    const overlay = document.getElementById("overlayImagem");
    const img = document.getElementById("imagemGrande");

    img.src = src;
    overlay.style.display = "flex";
    document.body.style.overflow = "hidden";
}

/* Aguarda DOM carregar */
document.addEventListener("DOMContentLoaded", function(){

    const overlay = document.getElementById("overlayImagem");

    if(overlay){
        overlay.addEventListener("click", function(){
            overlay.style.display = "none";
            document.body.style.overflow = "";
        });
    }

});


/* =========================================
   FILTRO GLOBAL + STATUS (SEGURO)
========================================= */

document.addEventListener("DOMContentLoaded", function(){

    const buscaInput = document.getElementById("buscaGlobal");
    const filtroStatus = document.getElementById("filtroStatus");

    if(!buscaInput || !filtroStatus) return;

    function aplicarFiltros(){

        const busca = buscaInput.value.toLowerCase();
        const statusSelecionado = filtroStatus.value;

        const rows = document.querySelectorAll("#vagasTable tbody tr");

        rows.forEach(row => {

            const titulo = row.dataset.titulo.toLowerCase();
            const localizacao = row.dataset.localizacao.toLowerCase();
            const status = (row.dataset.status || '').trim().toLowerCase();

            const candidatos = row.querySelector(".candidate-badge span:last-child").textContent;
            const data = row.querySelector("td:nth-child(7)").textContent;

            const textoCompleto = titulo + " " + localizacao + " " + status + " " + candidatos + " " + data;

            let mostrar = true;

            if(busca && !textoCompleto.includes(busca)){
                mostrar = false;
            }

           if(statusSelecionado && status !== statusSelecionado.toLowerCase()){
    mostrar = false;
}


            row.style.display = mostrar ? "" : "none";
        });
    }

    buscaInput.addEventListener("keyup", aplicarFiltros);
    filtroStatus.addEventListener("change", aplicarFiltros);

});

(function sidebarToggleInit(){
  const layout = document.querySelector('.admin-layout');
  const btn = document.getElementById('sidebarToggle');
  if (!layout || !btn) return;

  const saved = localStorage.getItem('admin_sidebar') || 'open';
  if (saved === 'collapsed') layout.classList.add('sidebar-collapsed');

  btn.addEventListener('click', () => {
    layout.classList.toggle('sidebar-collapsed');
    localStorage.setItem(
      'admin_sidebar',
      layout.classList.contains('sidebar-collapsed') ? 'collapsed' : 'open'
    );
  });
})();

</script>
<!-- PREVIEW IMAGEM FULLSCREEN -->
<div id="overlayImagem" style="
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.85);
    backdrop-filter:blur(5px);
    z-index:5000;
    align-items:center;
    justify-content:center;
    cursor:pointer;
">
    <img id="imagemGrande"
         style="
            max-width:90%;
            max-height:90%;
            border-radius:16px;
            box-shadow:0 20px 60px rgba(0,0,0,0.6);
         ">
</div>


</body>
</html>