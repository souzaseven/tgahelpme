<?php
require_once __DIR__ . '/../backend/conexao.php';

/* ============================================================
   BLOCO 1 — AÇÕES POST
   Processa alteração de status e exclusão de candidaturas.
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['alterar_status'])) {
        $id         = (int) $_POST['id'];
        $status     = strtolower(trim($_POST['status']));
        $permitidos = ['pendente', 'em_analise', 'aprovado', 'reprovado'];

        if (!in_array($status, $permitidos)) {
            header("Location: candidaturas.php?msg=Status inválido!&tipo=error");
            exit;
        }

        $stmt = $pdo->prepare("UPDATE candidaturas SET status=? WHERE id=?");
        $stmt->execute([$status, $id]);
        header("Location: candidaturas.php?msg=Status atualizado com sucesso!&tipo=success");
        exit;
    }

    if (isset($_POST['excluir'])) {
        $id   = (int) $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM candidaturas WHERE id=?");
        $stmt->execute([$id]);
        header("Location: candidaturas.php?msg=Candidatura excluída com sucesso!&tipo=success");
        exit;
    }
}

/* ============================================================
   BLOCO 2 — DADOS E FILTROS
   Corrige status nulo/vazio, calcula estatísticas e monta
   a query filtrada para a tabela.
============================================================ */

// Garante consistência: status vazio vira 'pendente'
$pdo->exec("UPDATE candidaturas SET status='pendente' WHERE status IS NULL OR TRIM(status) = ''");

$filtroStatus = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : null;

// Query de estatísticas (independe do filtro da tabela)
$stmtStats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN LOWER(TRIM(COALESCE(status,''))) IN ('pendente','') THEN 1 ELSE 0 END) AS pendentes,
        SUM(CASE WHEN LOWER(TRIM(status)) = 'em_analise' THEN 1 ELSE 0 END) AS analise,
        SUM(CASE WHEN LOWER(TRIM(status)) = 'aprovado'   THEN 1 ELSE 0 END) AS aprovados,
        SUM(CASE WHEN LOWER(TRIM(status)) = 'reprovado'  THEN 1 ELSE 0 END) AS reprovados
    FROM candidaturas
");
$stats      = $stmtStats->fetch();
$total      = $stats['total'];
$pendentes  = $stats['pendentes'];
$analise    = $stats['analise'];
$aprovados  = $stats['aprovados'];
$reprovados = $stats['reprovados'];

// Query da tabela — respeita o filtro selecionado
$sql    = "SELECT c.*, v.titulo FROM candidaturas c LEFT JOIN vagas v ON c.vaga_id = v.id";
$params = [];

if ($filtroStatus) {
    if ($filtroStatus === 'pendente') {
        $sql .= " WHERE (LOWER(TRIM(c.status)) = 'pendente' OR c.status IS NULL OR TRIM(c.status) = '')";
    } else {
        $sql .= " WHERE LOWER(TRIM(c.status)) = ?";
        $params[] = $filtroStatus;
    }
}

$sql .= " ORDER BY c.data_candidatura DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$candidaturas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Candidaturas — TGA Admin</title>
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

        /* ── Variáveis globais ──────────────────────────────── */
        :root {
            --primary:       #0066FF;
            --primary-dark:  #0052CC;
            --bg:            #0F172A;
            --bg-card:       #1E293B;
            --bg-input:      #0F172A;
            --surface:       #334155;
            --text:          #F1F5F9;
            --text-sec:      #CBD5E1;
            --text-muted:    #64748B;
            --border:        #334155;
            --success:       #10B981;
            --warning:       #F59E0B;
            --danger:        #EF4444;
            --info:          #3B82F6;
            --radius:        14px;
            --radius-sm:     8px;
            --shadow:        0 4px 12px rgba(0,0,0,.3);
            --shadow-xl:     0 20px 40px rgba(0,102,255,.25);
            --transition:    all .2s ease;
        }

        [data-theme="light"] {
            --bg:        #FAFBFC;
            --bg-card:   #F5F7FA;
            --bg-input:  #FFFFFF;
            --surface:   #EDF0F5;
            --text:      #1A202C;
            --text-sec:  #4A5568;
            --text-muted:#718096;
            --border:    #E2E8F0;
            --shadow:    0 4px 12px rgba(0,0,0,.07);
            --shadow-xl: 0 20px 40px rgba(0,102,255,.12);
        }

        /* ── Reset ──────────────────────────────────────────── */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { overflow-x: hidden; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
        }

        /* ── Layout ─────────────────────────────────────────── */
        .admin-layout { display: flex; min-height: 100vh; }

        /* ── Sidebar ────────────────────────────────────────── */
        .sidebar {
            width: 280px;
            background: var(--bg-card);
            border-right: 1px solid var(--border);
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header { padding: 0 1.5rem 2rem; border-bottom: 1px solid var(--border); }

        .logo { display: flex; align-items: center; gap: .75rem; }

        .logo-icon {
            width: 45px; height: 45px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
        }

        .logo-text h1 { font-size: 1.1rem; font-weight: 800; color: var(--text); line-height: 1.2; }
        .logo-text p  { font-size: .7rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }

        .sidebar-nav { padding: 1.5rem 0; }
        .nav-section { margin-bottom: 1.5rem; }
        .nav-section-title {
            padding: 0 1.5rem;
            font-size: .7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 1px; color: var(--text-muted); margin-bottom: .75rem;
        }

        .nav-item {
            display: flex; align-items: center; gap: 1rem;
            padding: .875rem 1.5rem;
            color: var(--text-sec); text-decoration: none;
            font-weight: 500; font-size: .95rem;
            transition: var(--transition);
            border-left: 3px solid transparent;
        }

        .nav-item:hover  { background: var(--surface); color: var(--text); border-left-color: var(--primary); }
        .nav-item.active { background: var(--surface); color: var(--primary); border-left-color: var(--primary); font-weight: 600; }
        .nav-icon { font-size: 1.25rem; width: 24px; text-align: center; }

        /* ── Conteúdo principal ─────────────────────────────── */
        .main-content { flex: 1; margin-left: 280px; padding: 2rem; }

        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;
        }

        .page-title h2 { font-size: 2rem; font-weight: 800; color: var(--text); margin-bottom: .25rem; }
        .page-title p  { color: var(--text-sec); font-size: .95rem; }

        .theme-toggle {
            width: 44px; height: 44px;
            border: 2px solid var(--border);
            border-radius: 10px;
            background: var(--bg-card);
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem;
            transition: var(--transition);
        }

        .theme-toggle:hover { background: var(--primary); border-color: var(--primary); transform: scale(1.05); }

        /* ── Botões ─────────────────────────────────────────── */
        .btn {
            padding: .75rem 1.5rem;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-weight: 600; font-size: .9rem;
            border: none; cursor: pointer;
            display: inline-flex; align-items: center; gap: .5rem;
            transition: var(--transition);
            white-space: nowrap; text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            box-shadow: 0 4px 12px rgba(0,102,255,.25);
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(0,102,255,.35); }

        .btn-danger  { background: var(--danger);  color: #fff; }
        .btn-info    { background: var(--info);    color: #fff; }
        .btn-success { background: var(--success); color: #fff; }
        .btn-ghost {
            background: var(--surface);
            color: var(--text-sec);
            border: 1px solid var(--border);
        }
        .btn-ghost:hover { background: var(--border); color: var(--text); }
        .btn-sm { padding: .45rem .9rem; font-size: .82rem; }

        /* ── Estatísticas ───────────────────────────────────── */
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-mini {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 1.25rem;
            border: 1px solid var(--border);
            display: flex; align-items: center; gap: 1rem;
        }

        .stat-mini-icon {
            width: 45px; height: 45px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; flex-shrink: 0;
        }

        .stat-mini-icon.blue   { background: rgba(0,102,255,.1);   color: var(--primary); }
        .stat-mini-icon.yellow { background: rgba(245,158,11,.1);  color: var(--warning); }
        .stat-mini-icon.cyan   { background: rgba(59,130,246,.1);  color: var(--info); }
        .stat-mini-icon.green  { background: rgba(16,185,129,.1);  color: var(--success); }
        .stat-mini-icon.red    { background: rgba(239,68,68,.1);   color: var(--danger); }

        .stat-mini-label { font-size: .75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }
        .stat-mini-value { font-size: 1.75rem; font-weight: 800; color: var(--text); }

        /* ── Card ───────────────────────────────────────────── */
        .card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 1rem;
        }

        .card-title { font-size: 1.25rem; font-weight: 700; color: var(--text); }

        .filters { display: flex; gap: .5rem; flex-wrap: wrap; }

        .filter-btn {
            padding: .45rem 1rem; border-radius: 8px;
            background: var(--surface); color: var(--text-sec);
            text-decoration: none; font-size: .82rem; font-weight: 600;
            border: 1px solid var(--border); transition: var(--transition);
        }

        .filter-btn:hover  { background: var(--border); color: var(--text); }
        .filter-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }

        /* ── Tabela ─────────────────────────────────────────── */
        .table-container { overflow-x: auto; }

        .modern-table { width: 100%; border-collapse: collapse; }

        .modern-table thead { background: var(--surface); }

        .modern-table th {
            padding: 1rem 1.5rem; text-align: left;
            font-size: .75rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .5px; color: var(--text-muted);
            border-bottom: 1px solid var(--border);
            cursor: pointer; user-select: none; transition: var(--transition);
        }

        .modern-table th:hover { color: var(--text); }
        .modern-table th.sortable::after     { content: '⇅'; margin-left: .4rem; opacity: .3; }
        .modern-table th.sorted-asc::after  { content: '↑'; opacity: 1; color: var(--primary); }
        .modern-table th.sorted-desc::after { content: '↓'; opacity: 1; color: var(--primary); }

        .modern-table td {
            padding: 1.1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            color: var(--text-sec); font-size: .9rem;
        }

        .modern-table tbody tr { transition: background .15s; }
        .modern-table tbody tr:hover { background: var(--surface); }
        .modern-table tbody tr:last-child td { border-bottom: none; }

        .table-id   { font-weight: 700; color: var(--text-muted); font-size: .82rem; }
        .table-name { font-weight: 600; color: var(--text); }

        /* ── Status badges ──────────────────────────────────── */
        .status-badge {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .3rem .8rem; border-radius: 20px;
            font-size: .78rem; font-weight: 600;
        }

        .status-badge::before {
            content: ''; width: 6px; height: 6px;
            border-radius: 50%; background: currentColor;
        }

        .status-badge.pendente   { background: rgba(245,158,11,.1); color: var(--warning); }
        .status-badge.em_analise { background: rgba(59,130,246,.1); color: var(--info); }
        .status-badge.aprovado   { background: rgba(16,185,129,.1); color: var(--success); }
        .status-badge.reprovado  { background: rgba(239,68,68,.1);  color: var(--danger); }

        /* ── Ações da tabela ────────────────────────────────── */
        .action-buttons { display: flex; gap: .4rem; flex-wrap: wrap; align-items: center; }

        .status-select {
            padding: .45rem .6rem;
            border-radius: var(--radius-sm);
            background: var(--surface);
            color: var(--text);
            border: 1px solid var(--border);
            font-size: .82rem; font-family: inherit;
            cursor: pointer;
        }

        /* ── Alert ──────────────────────────────────────────── */
        .alert {
            padding: 1rem 1.5rem; border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: 1rem;
            animation: slideDown .3s;
        }

        .alert-success { background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.3); color: var(--success); }
        .alert-error   { background: rgba(239,68,68,.1);  border: 1px solid rgba(239,68,68,.3);  color: var(--danger); }
        .alert-icon    { font-size: 1.3rem; }

        /* ── Empty state ────────────────────────────────────── */
        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
        .empty-state-icon { font-size: 4rem; margin-bottom: 1rem; opacity: .5; }
        .empty-state h3   { font-size: 1.25rem; color: var(--text); margin-bottom: .5rem; }

        /* ── Breadcrumb ─────────────────────────────────────── */
        .breadcrumb {
            display: flex; align-items: center; gap: .5rem;
            margin-bottom: 1.5rem; font-size: .88rem;
        }

        .breadcrumb a    { color: var(--text-sec); text-decoration: none; transition: var(--transition); }
        .breadcrumb a:hover { color: var(--primary); }
        .breadcrumb span { color: var(--text-muted); }

        /* ── Modal base ─────────────────────────────────────── */
        .modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,.75);
            backdrop-filter: blur(5px);
            z-index: 2000;
            align-items: center; justify-content: center;
            padding: 1.5rem;
        }

        .modal-overlay.active { display: flex; }

        .modal {
            background: var(--bg-card);
            border-radius: 20px;
            box-shadow: var(--shadow-xl);
            width: 100%; max-width: 700px; max-height: 90vh;
            border: 1px solid var(--border);
            display: flex; flex-direction: column;
            overflow: hidden;
            animation: slideUp .28s ease;
        }

        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
            flex-shrink: 0;
        }

        .modal-title {
            font-size: 1.25rem; font-weight: 700; color: var(--text);
            display: flex; align-items: center; gap: .75rem;
        }

        .modal-close {
            width: 36px; height: 36px;
            border-radius: var(--radius-sm);
            background: var(--surface);
            border: 1px solid var(--border);
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
            color: var(--text-sec);
            transition: var(--transition);
            flex-shrink: 0;
        }

        .modal-close:hover {
            background: var(--danger); border-color: var(--danger);
            color: #fff; transform: rotate(90deg);
        }

        .modal-body {
            padding: 2rem;
            overflow-y: auto;
            flex: 1;
        }

        /* ── Modal Detalhes ─────────────────────────────────── */
        .detail-group {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        .detail-group:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }

        .detail-label {
            font-size: .72rem; color: var(--text-muted);
            font-weight: 700; text-transform: uppercase;
            letter-spacing: .6px; margin-bottom: .4rem;
        }

        .detail-value { font-size: 1rem; color: var(--text); font-weight: 500; }

        /* ══════════════════════════════════════════════════════
           Modal Agendar Entrevista — estilos dedicados
           Organizado em 4 seções visuais:
           1. Candidato / Vaga
           2. Agendamento (data, hora, local)
           3. WhatsApp + mensagem
           4. Ações
        ══════════════════════════════════════════════════════ */
        .modal-agendar { max-width: 780px; }

        /* Seção interna (card leve) */
        .agendar-section {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1rem;
        }

        .agendar-section:last-child { margin-bottom: 0; }

        /* Cabeçalho de seção */
        .agendar-section-title {
            font-size: .7rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .7px;
            color: var(--text-muted);
            margin-bottom: 1rem;
            display: flex; align-items: center; gap: .5rem;
        }

        /* Nome e vaga dentro da seção de candidato */
        .agendar-person      { font-size: 1.15rem; font-weight: 700; color: var(--text); line-height: 1.2; }
        .agendar-vaga-label  { font-size: .82rem; color: var(--text-muted); margin-top: .2rem; }
        .agendar-vaga-value  { font-size: .95rem; font-weight: 600; color: var(--text-sec); }

        /* Grid data / hora / local */
        .agendar-fields {
            display: grid;
            grid-template-columns: 1fr 1fr 1.6fr;
            gap: .75rem;
        }

        /* Label + input empilhados */
        .agendar-field-group { display: flex; flex-direction: column; gap: .35rem; }

        .agendar-field-label {
            font-size: .7rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .6px;
            color: var(--text-muted);
            display: flex; align-items: center; gap: .35rem;
        }

        .agendar-input {
            width: 100%;
            padding: .6rem .85rem;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-family: inherit;
            font-size: .88rem;
            transition: var(--transition);
            outline: none;
        }

        .agendar-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,102,255,.12);
        }

        /* Telefone destacado */
        .agendar-phone {
            font-size: 1.35rem; font-weight: 800;
            color: var(--text);
            letter-spacing: .02em;
        }

        /* Textarea da mensagem */
        .agendar-textarea {
            width: 100%;
            min-height: 120px;
            padding: .75rem 1rem;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-family: inherit;
            font-size: .88rem;
            line-height: 1.6;
            resize: none;
            outline: none;
            transition: var(--transition);
        }

        .agendar-textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,102,255,.12);
        }

        /* Footer de ações dentro do modal */
        .modal-footer {
            padding: 1.25rem 2rem;
            border-top: 1px solid var(--border);
            display: flex; gap: .75rem; justify-content: flex-end;
            flex-shrink: 0;
        }

        /* ── Animações ──────────────────────────────────────── */
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideUp   { from { opacity: 0; transform: translateY(28px) scale(.97); } to { opacity: 1; transform: translateY(0) scale(1); } }

        /* ── Responsivo ─────────────────────────────────────── */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
        }

        @media (max-width: 768px) {
            .main-content   { padding: 1rem; }
            .stats-mini     { grid-template-columns: 1fr 1fr; }
            .agendar-fields { grid-template-columns: 1fr; }
            .modal          { border-radius: 14px; max-height: 95vh; }
            .modal-footer   { flex-direction: column; }
            .modal-footer .btn { justify-content: center; }
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

    <!-- ===== SIDEBAR ===== -->
    
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

    <!-- ===== CONTEÚDO PRINCIPAL ===== -->
    <main class="main-content">

        <div class="breadcrumb">
            <a href="index.php">Dashboard</a>
            <span>›</span>
            <span>Candidaturas</span>
        </div>

        <div class="page-header">
            <div class="page-title">
                <h2>👥 Gerenciar Candidaturas</h2>
                <p>Analise e gerencie todas as candidaturas recebidas</p>
            </div>
            <button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema">
                <span id="theme-icon">☀️</span>
            </button>
        </div>

        <!-- Alert de retorno de ação -->
        <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_GET['tipo'] ?? 'success') ?>">
            <span class="alert-icon"><?= ($_GET['tipo'] ?? '') === 'success' ? '✅' : '❌' ?></span>
            <span><?= htmlspecialchars($_GET['msg']) ?></span>
        </div>
        <?php endif; ?>

        <!-- Estatísticas resumidas -->
        <div class="stats-mini">
            <div class="stat-mini">
                <div class="stat-mini-icon blue">📊</div>
                <div><div class="stat-mini-label">Total</div><div class="stat-mini-value"><?= $total ?></div></div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon yellow">⏳</div>
                <div><div class="stat-mini-label">Pendentes</div><div class="stat-mini-value"><?= $pendentes ?></div></div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon cyan">🔍</div>
                <div><div class="stat-mini-label">Em Análise</div><div class="stat-mini-value"><?= $analise ?></div></div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon green">✅</div>
                <div><div class="stat-mini-label">Aprovados</div><div class="stat-mini-value"><?= $aprovados ?></div></div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon red">❌</div>
                <div><div class="stat-mini-label">Reprovados</div><div class="stat-mini-value"><?= $reprovados ?></div></div>
            </div>
        </div>

        <!-- Tabela de candidaturas -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📋 Lista de Candidatos</h3>
                <div class="filters">
                    <a href="candidaturas.php"   class="filter-btn <?= !$filtroStatus ? 'active' : '' ?>">Todos</a>
                    <a href="?status=pendente"    class="filter-btn <?= $filtroStatus === 'pendente'   ? 'active' : '' ?>">Pendentes</a>
                    <a href="?status=em_analise"  class="filter-btn <?= $filtroStatus === 'em_analise' ? 'active' : '' ?>">Em Análise</a>
                    <a href="?status=aprovado"    class="filter-btn <?= $filtroStatus === 'aprovado'   ? 'active' : '' ?>">Aprovados</a>
                    <a href="?status=reprovado"   class="filter-btn <?= $filtroStatus === 'reprovado'  ? 'active' : '' ?>">Reprovados</a>
                </div>
            </div>

            <?php if (count($candidaturas) > 0): ?>
            <div class="table-container">
                <table class="modern-table" id="candidaturasTable">
                    <thead>
                        <tr>
                            <th class="sortable" data-column="id">ID</th>
                            <th class="sortable" data-column="nome">Nome</th>
                            <th class="sortable" data-column="email">Email</th>
                            <th class="sortable" data-column="vaga">Vaga</th>
                            <th class="sortable" data-column="status">Status</th>
                            <th class="sortable" data-column="data">Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($candidaturas as $c):
                            $statusRaw   = $c['status'] ?? '';
                            $statusLimpo = strtolower(trim($statusRaw));
                            if (empty($statusLimpo)) $statusLimpo = 'pendente';
                            $statusTexto = ucfirst(str_replace('_', ' ', $statusLimpo));
                        ?>
                        <tr data-candidato-id="<?= $c['id'] ?>"
                            data-nome="<?= htmlspecialchars($c['nome']) ?>"
                            data-email="<?= htmlspecialchars($c['email']) ?>"
                            data-telefone="<?= htmlspecialchars($c['telefone'] ?? '') ?>"
                            data-vaga="<?= htmlspecialchars($c['titulo'] ?? '') ?>"
                            data-curriculo="<?= htmlspecialchars($c['curriculo'] ?? '') ?>"
                            data-mensagem="<?= htmlspecialchars($c['mensagem'] ?? '') ?>"
                            data-status="<?= $statusLimpo ?>">
                            <td><span class="table-id">#<?= str_pad($c['id'], 4, '0', STR_PAD_LEFT) ?></span></td>
                            <td><span class="table-name"><?= htmlspecialchars($c['nome']) ?></span></td>
                            <td><?= htmlspecialchars($c['email']) ?></td>
                            <td><?= htmlspecialchars($c['titulo'] ?? '—') ?></td>
                            <td>
                                <span class="status-badge <?= $statusLimpo ?>">
                                    <?= $statusTexto ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($c['data_candidatura'])) ?></td>
                            <td>
                                <div style="display:flex;flex-direction:column;gap:.4rem;min-width:220px">

                                    <!-- Linha 1: Detalhes | Select status + Salvar (mesmo form) -->
                                    <form method="POST" style="display:flex;gap:.4rem;align-items:center;margin:0">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button type="button" onclick="openDetailsModal(this.closest('tr'))" class="btn btn-info btn-sm">
                                            <span>👁️</span><span>Detalhes</span>
                                        </button>
                                        <select name="status" class="status-select" style="flex:1">
                                            <option value="pendente"   <?= $statusLimpo === 'pendente'   ? 'selected' : '' ?>>⏳ Pendente</option>
                                            <option value="em_analise" <?= $statusLimpo === 'em_analise' ? 'selected' : '' ?>>🔍 Em análise</option>
                                            <option value="aprovado"   <?= $statusLimpo === 'aprovado'   ? 'selected' : '' ?>>✅ Aprovado</option>
                                            <option value="reprovado"  <?= $statusLimpo === 'reprovado'  ? 'selected' : '' ?>>❌ Reprovado</option>
                                        </select>
                                        <button name="alterar_status" class="btn btn-primary btn-sm" title="Salvar status">
                                            <span>💾</span>
                                        </button>
                                    </form>

                                    <!-- Linha 2: Agendar + Excluir -->
                                    <div style="display:flex;gap:.4rem">
                                        <button onclick="openAgendarModal(this.closest('tr'))" class="btn btn-success btn-sm" style="flex:1;justify-content:center">
                                            <span>📅</span><span>Agendar</span>
                                        </button>
                                        <form method="POST" style="margin:0;flex:1" onsubmit="return confirm('⚠️ Excluir esta candidatura?\n\nEsta ação não pode ser desfeita!')">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button name="excluir" class="btn btn-danger btn-sm" style="width:100%;justify-content:center">
                                                <span>🗑️</span><span>Excluir</span>
                                            </button>
                                        </form>
                                    </div>

                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h3>Nenhuma candidatura encontrada</h3>
                <p>Não há candidaturas <?= $filtroStatus ? 'com este status' : 'no momento' ?></p>
            </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL — Detalhes da Candidatura
═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="detailsModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><span>👁️</span><span>Detalhes da Candidatura</span></h3>
            <button class="modal-close" onclick="closeDetailsModal()">✕</button>
        </div>
        <div class="modal-body">
            <div class="detail-group">
                <div class="detail-label">👤 Candidato</div>
                <div class="detail-value" id="detail_nome"></div>
            </div>
            <div class="detail-group">
                <div class="detail-label">📧 Email</div>
                <div class="detail-value" id="detail_email"></div>
            </div>
            <div class="detail-group">
                <div class="detail-label">📱 Telefone</div>
                <div class="detail-value" id="detail_telefone"></div>
            </div>
            <div class="detail-group">
                <div class="detail-label">💼 Vaga</div>
                <div class="detail-value" id="detail_vaga"></div>
            </div>
            <div class="detail-group">
                <div class="detail-label">📝 Status</div>
                <div class="detail-value" id="detail_status"></div>
            </div>
            <div class="detail-group">
                <div class="detail-label">📄 Currículo</div>
                <div class="detail-value" id="detail_curriculo"></div>
            </div>
            <div class="detail-group">
                <div class="detail-label">💬 Mensagem</div>
                <div class="detail-value" id="detail_mensagem"></div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL — Agendar Entrevista
     Seções:
       1. Candidato / Vaga     — quem está sendo agendado
       2. Agendamento          — data, hora, local (grid 3 colunas)
       3. WhatsApp + Mensagem  — número e texto automático
     Footer: Enviar WhatsApp | Confirmar
═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="agendarModal">
    <div class="modal modal-agendar">

        <!-- Cabeçalho -->
        <div class="modal-header">
            <h3 class="modal-title"><span>📅</span><span>Agendar Entrevista</span></h3>
            <button class="modal-close" onclick="closeAgendarModal()">✕</button>
        </div>

        <!-- Corpo -->
        <div class="modal-body">

            <!-- ── Seção 1: Candidato / Vaga (lado a lado) ─── -->
            <div class="agendar-section">
                <div style="display:flex;gap:1.5rem;align-items:stretch;flex-wrap:wrap">

                    <!-- Candidato -->
                    <div style="flex:1;min-width:150px">
                        <div class="agendar-section-title">👤 Candidato</div>
                        <div class="agendar-person" id="agendar_nome">—</div>
                    </div>

                    <!-- Divisor vertical -->
                    <div style="width:1px;background:var(--border);flex-shrink:0;border-radius:2px"></div>

                    <!-- Vaga -->
                    <div style="flex:1;min-width:150px">
                        <div class="agendar-section-title">💼 Vaga</div>
                        <div class="agendar-vaga-value" id="agendar_vaga">—</div>
                    </div>

                </div>
            </div>

            <!-- ── Seção 2: Data, Hora e Local ─────────────── -->
            <div class="agendar-section">
                <div class="agendar-section-title">📅 Agendamento</div>
                <div class="agendar-fields">

                    <div class="agendar-field-group">
                        <label class="agendar-field-label" for="agendar_data">
                            📅 Data
                        </label>
                        <input type="date" id="agendar_data" class="agendar-input">
                    </div>

                    <div class="agendar-field-group">
                        <label class="agendar-field-label" for="agendar_hora">
                            ⏰ Hora
                        </label>
                        <input type="time" id="agendar_hora" class="agendar-input">
                    </div>

                    <div class="agendar-field-group">
                        <label class="agendar-field-label" for="agendar_local">
                            📍 Local
                        </label>
                        <input type="text" id="agendar_local" class="agendar-input"
                               placeholder="Ex: Sede TGA — Sala 02">
                    </div>

                </div>
            </div>

            <!-- ── Seção 3: WhatsApp + Mensagem ────────────── -->
            <div class="agendar-section">
                <div class="agendar-section-title">📱 WhatsApp</div>
                <div class="agendar-phone" id="agendar_whatsapp">—</div>

                <div class="agendar-field-label" style="margin-top:1rem;margin-bottom:.4rem">
                    💬 Mensagem (WhatsApp)
                </div>
                <textarea id="agendar_mensagem" class="agendar-textarea"
                          placeholder="Preencha data e hora para gerar a mensagem automaticamente…"></textarea>
            </div>

        </div><!-- /modal-body -->

        <!-- Rodapé com ações -->
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeAgendarModal()">
                Cancelar
            </button>
            <button class="btn btn-success" onclick="enviarWhats()">
                📲 Enviar WhatsApp
            </button>
            <button class="btn btn-primary" onclick="confirmarAgendamento()">
                💾 Confirmar
            </button>
        </div>

    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     SCRIPTS
     1. toggleTheme     — tema claro / escuro
     2. Modais          — open/close detalhes e agendamento
     3. atualizarMensagem — gera texto do WhatsApp automaticamente
     4. enviarWhats     — abre wa.me com a mensagem
     5. confirmarAgendamento — ação de confirmação
     6. Ordenação da tabela
     7. Alert auto-hide
═══════════════════════════════════════════════════════════ -->
<script>
"use strict";

/* ── 1. Tema ─────────────────────────────────────────────── */
function toggleTheme() {
    const html    = document.documentElement;
    const icon    = document.getElementById('theme-icon');
    const current = html.getAttribute('data-theme') || 'dark';
    const next    = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    icon.textContent = next === 'light' ? '🌙' : '☀️';
    localStorage.setItem('theme', next);
}

window.addEventListener('DOMContentLoaded', () => {
    const saved = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
    document.getElementById('theme-icon').textContent = saved === 'light' ? '🌙' : '☀️';
});

/* ── 2. Modal Detalhes ───────────────────────────────────── */
const statusLabelMap = {
    pendente:   '⏳ Pendente',
    em_analise: '🔍 Em Análise',
    aprovado:   '✅ Aprovado',
    reprovado:  '❌ Reprovado',
};

function openDetailsModal(row) {
    const statusLimpo = (row.dataset.status || '').toLowerCase().trim();

    document.getElementById('detail_nome').textContent     = row.dataset.nome;
    document.getElementById('detail_email').textContent    = row.dataset.email;
    document.getElementById('detail_telefone').textContent = row.dataset.telefone || 'Não informado';
    document.getElementById('detail_vaga').textContent     = row.dataset.vaga;
    document.getElementById('detail_status').textContent   = statusLabelMap[statusLimpo] || statusLimpo;
    document.getElementById('detail_mensagem').textContent = row.dataset.mensagem || 'Nenhuma mensagem adicional';

    const curriculo = row.dataset.curriculo;
    if (curriculo && curriculo.trim()) {
        document.getElementById('detail_curriculo').innerHTML =
            `<a href="../${curriculo}" target="_blank"
               style="color:var(--primary);font-weight:600;text-decoration:none;">
               📎 Baixar Currículo (PDF)
             </a>`;
    } else {
        document.getElementById('detail_curriculo').textContent = 'Não anexado';
    }

    document.getElementById('detailsModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDetailsModal() {
    document.getElementById('detailsModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Fecha ao clicar no fundo ou pressionar Escape
document.getElementById('detailsModal').addEventListener('click', function(e) {
    if (e.target === this) closeDetailsModal();
});

/* ── 3. Modal Agendar ────────────────────────────────────── */
let nomeAtual = '', telefoneAtual = '', vagaAtual = '';

function openAgendarModal(row) {
    nomeAtual     = row.dataset.nome     || '';
    telefoneAtual = row.dataset.telefone || '';
    vagaAtual     = row.dataset.vaga     || '';

    // Preenche campos de identidade
    document.getElementById('agendar_nome').textContent     = nomeAtual;
    document.getElementById('agendar_vaga').textContent     = vagaAtual;
    document.getElementById('agendar_whatsapp').textContent = telefoneAtual || 'Não informado';

    // Limpa campos de agendamento
    document.getElementById('agendar_data').value      = '';
    document.getElementById('agendar_hora').value      = '';
    document.getElementById('agendar_local').value     = '';
    document.getElementById('agendar_mensagem').value  = '';
    autoResize(document.getElementById('agendar_mensagem'));

    document.getElementById('agendarModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeAgendarModal() {
    document.getElementById('agendarModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.getElementById('agendarModal').addEventListener('click', function(e) {
    if (e.target === this) closeAgendarModal();
});

/* ── 4. Geração automática da mensagem ───────────────────── */
function atualizarMensagem() {
    const data  = document.getElementById('agendar_data').value;
    const hora  = document.getElementById('agendar_hora').value;
    const local = document.getElementById('agendar_local').value.trim();
    const box   = document.getElementById('agendar_mensagem');

    if (!data || !hora) { box.value = ''; autoResize(box); return; }

    const dataFormatada = new Date(data + 'T00:00:00')
        .toLocaleDateString('pt-BR', {
            weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric'
        });

    box.value =
`Olá ${nomeAtual}, tudo bem?

Você foi selecionado(a) para a próxima etapa do processo seletivo.

💼 Vaga: ${vagaAtual}

📅 Data: ${dataFormatada}
⏰ Horário: ${hora}${local ? `\n📍 Local: ${local}` : ''}

Pedimos que chegue com 10 minutos de antecedência.

Aguardamos você!
Equipe TGA Carreiras.`;

    autoResize(box);
}

// Escuta alterações nos campos de agendamento
['agendar_data', 'agendar_hora'].forEach(id =>
    document.getElementById(id).addEventListener('change', atualizarMensagem)
);
document.getElementById('agendar_local').addEventListener('input', atualizarMensagem);

/* ── 5. Enviar WhatsApp ──────────────────────────────────── */
function enviarWhats() {
    const mensagem = document.getElementById('agendar_mensagem').value.trim();

    if (!mensagem) { alert('Preencha os dados do agendamento para gerar a mensagem.'); return; }
    if (!telefoneAtual) { alert('Telefone não informado para este candidato.'); return; }

    const tel = telefoneAtual.replace(/\D/g, '');
    window.open(`https://wa.me/55${tel}?text=${encodeURIComponent(mensagem)}`, '_blank');
}

/* ── 6. Confirmar agendamento ────────────────────────────── */
function confirmarAgendamento() {
    const data  = document.getElementById('agendar_data').value;
    const hora  = document.getElementById('agendar_hora').value;

    if (!data || !hora) { alert('Informe a data e o horário da entrevista.'); return; }

    // Aqui pode-se enviar via fetch() para um endpoint de agendamento
    alert(`✅ Entrevista agendada!\n\n${nomeAtual}\n${data} às ${hora}`);
    closeAgendarModal();
}

/* ── 7. Ordenação da tabela ──────────────────────────────── */
let sortDir = {}, currentCol = null;

document.querySelectorAll('.sortable').forEach(th => {
    th.addEventListener('click', function () {
        const col   = this.dataset.column;
        const tbody = document.querySelector('#candidaturasTable tbody');
        const rows  = Array.from(tbody.querySelectorAll('tr'));

        sortDir[col] = (currentCol === col && sortDir[col] === 'asc') ? 'desc' : 'asc';
        currentCol   = col;

        document.querySelectorAll('.sortable')
            .forEach(el => el.classList.remove('sorted-asc', 'sorted-desc'));
        this.classList.add(sortDir[col] === 'asc' ? 'sorted-asc' : 'sorted-desc');

        rows.sort((a, b) => {
            let va, vb;
            switch (col) {
                case 'id':     va = +a.dataset.candidatoId;          vb = +b.dataset.candidatoId; break;
                case 'nome':   va = a.dataset.nome.toLowerCase();    vb = b.dataset.nome.toLowerCase(); break;
                case 'email':  va = a.dataset.email.toLowerCase();   vb = b.dataset.email.toLowerCase(); break;
                case 'vaga':   va = a.dataset.vaga.toLowerCase();    vb = b.dataset.vaga.toLowerCase(); break;
                case 'status': va = a.dataset.status;                vb = b.dataset.status; break;
                case 'data':   va = a.querySelector('td:nth-child(6)').textContent;
                               vb = b.querySelector('td:nth-child(6)').textContent; break;
            }
            if (sortDir[col] === 'asc') return va > vb ? 1 : va < vb ? -1 : 0;
            else                        return va < vb ? 1 : va > vb ? -1 : 0;
        });

        rows.forEach(r => tbody.appendChild(r));
    });
});

/* ── 8. Auto-resize do textarea ──────────────────────────── */
function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
}

document.getElementById('agendar_mensagem')
    .addEventListener('input', function () { autoResize(this); });

/* ── 9. Fecha com Escape ─────────────────────────────────── */
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeDetailsModal();
        closeAgendarModal();
    }
});

/* ── 10. Alert auto-hide após 5s ─────────────────────────── */
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(el => {
        el.style.transition = 'opacity .3s';
        el.style.opacity    = '0';
        setTimeout(() => el.remove(), 320);
    });
}, 5000);

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

</body>
</html>