<?php
/* ============================================================
   index.php — TGA Carreiras (área pública)
   Melhorias nesta versão:
   ─ Setor exibido na tabela e nos cards
   ─ Badge "Nova" inteligente (vagas dos últimos 7 dias)
   ─ Número de candidatos por vaga
   ─ Ordenação dinâmica (recentes, antigas, A–Z, salário)
   ─ Busca no servidor (GET) — SEO friendly
   ─ Paginação real (LIMIT 12 OFFSET)
   ─ Filtro por setor (GET)
   ─ Toggle Lista / Cards persistido no localStorage
   ─ Tema dark padrão
   ─ RESPONSIVIDADE COMPLETA para mobile/tablet
   ─ Salário com badge colorido (verde/laranja/vermelho)
   ─ Botão "Ver vaga" pulsante e destacado
============================================================ */
require_once __DIR__ . '/../backend/conexao.php';

/* ============================================================
   BLOCO 1 — Setores canônicos
============================================================ */
$setores = [
    'Administrativo e Financeiro',
    'Análise e Gestão de Dados',
    'Atendimento Publicitário',
    'Comercial',
    'Criação',
    'Customer Success',
    'Design',
    'Diversidade, Inclusão e Acessibilidade',
    'Educação e Treinamento',
    'Jornalismo',
    'Marketing',
    'Mídia',
    'Planejamento',
    'Produção',
    'Programação',
    'Projetos e Operações',
    'Recursos Humanos',
    'Relações Públicas',
    'Rádio e TV',
    'Tecnologia',
    'Outros',
];

/* ============================================================
   BLOCO 2 — Parâmetros GET (filtros, ordenação, paginação)
============================================================ */
$filtroSetor  = trim($_GET['setor']   ?? '');
$filtroBusca  = trim($_GET['busca']   ?? '');
$ordenacao    = $_GET['ordem']        ?? 'recentes';
$filtroCargo   = trim($_GET['cargo']   ?? '');
$filtroEmpresa = trim($_GET['empresa'] ?? '');
$filtroCidade  = trim($_GET['cidade']  ?? '');

$paginaAtual  = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina    = 12;
$offset       = ($paginaAtual - 1) * $porPagina;

$ordens = [
    'recentes' => 'v.data_publicacao DESC',
    'antigas'  => 'v.data_publicacao ASC',
    'az'       => 'v.titulo ASC',
    'salario'  => 'CAST(REPLACE(REPLACE(v.salario, "R$", ""), ".", "") AS UNSIGNED) DESC',
];

$orderSql = $ordens[$ordenacao] ?? $ordens['recentes'];

/* ============================================================
   BLOCO 3 — Query principal com filtros + contagem
============================================================ */
$where  = ["v.status = 'ativa'"];
$params = [];

if ($filtroSetor) {
    $where[]  = "v.setor = ?";
    $params[] = $filtroSetor;
}

if ($filtroBusca) {
    $where[]  = "(v.titulo LIKE ? OR v.localizacao LIKE ? OR v.descricao LIKE ?)";
    $params[] = "%$filtroBusca%";
    $params[] = "%$filtroBusca%";
    $params[] = "%$filtroBusca%";
}
if ($filtroCargo) {
    $where[]  = "v.titulo = ?";
    $params[] = $filtroCargo;
}

if ($filtroEmpresa) {
    $where[]  = "v.empresa = ?";
    $params[] = $filtroEmpresa;
}

if ($filtroCidade) {
    $where[]  = "v.localizacao LIKE ?";
    $params[] = "%$filtroCidade%";
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM vagas v $whereSql");
$stmtTotal->execute($params);
$totalRegistros = (int) $stmtTotal->fetchColumn();
$totalPaginas   = max(1, (int) ceil($totalRegistros / $porPagina));
$paginaAtual    = min($paginaAtual, $totalPaginas);


$cargos = $pdo->query("
    SELECT DISTINCT titulo 
    FROM vagas 
    WHERE status='ativa' 
    ORDER BY titulo ASC
")->fetchAll(PDO::FETCH_COLUMN);

$empresas = $pdo->query("
    SELECT DISTINCT empresa 
    FROM vagas 
    WHERE status='ativa' 
      AND empresa IS NOT NULL 
      AND empresa <> ''
    ORDER BY empresa ASC
")->fetchAll(PDO::FETCH_COLUMN);

$cidades = $pdo->query("
    SELECT DISTINCT localizacao 
    FROM vagas 
    WHERE status='ativa' 
      AND localizacao IS NOT NULL 
      AND localizacao <> ''
    ORDER BY localizacao ASC
")->fetchAll(PDO::FETCH_COLUMN);

$sql = "
    SELECT v.*,
           COUNT(c.id) AS total_candidatos
    FROM vagas v
    LEFT JOIN candidaturas c ON c.vaga_id = v.id
    $whereSql
    GROUP BY v.id
    ORDER BY $orderSql
    LIMIT $porPagina OFFSET $offset
";

$stmtVagas = $pdo->prepare($sql);
$stmtVagas->execute($params);
$vagas = $stmtVagas->fetchAll();

$totalVagas = $totalRegistros;

function paginaUrl(int $p): string {
    $params = $_GET;
    $params['pagina'] = $p;
    return '?' . http_build_query($params);
}

/* ============================================================
   HELPER — Classe de cor do salário
============================================================ */
/* ── Converte qualquer formato de salário para float ────── */
function salarioParaFloat(string $salario): float {
    $limpo = str_replace(['R$', 'r$', ' '], '', trim($salario));
    if (strpos($limpo, ',') !== false) {
        $limpo = str_replace('.', '', $limpo);
        $limpo = str_replace(',', '.', $limpo);
    }
    return (float) $limpo;
}

/* ── Classe de cor do badge ────────────────────────────── */
function classeSalario(string $salario): string {
    if (!$salario || strtolower(trim($salario)) === 'a combinar') return 'salario-neutro';
    $num = salarioParaFloat($salario);
    if ($num <= 0) return 'salario-neutro';
    if ($num >= 9000) return 'salario-vermelho';
    if ($num >= 5000) return 'salario-laranja';
    return 'salario-verde';
}

/* ── Formata salário para exibição BR: R$ 6.000,00 ─────── */
function formatarSalario(string $salario): string {
    if (!$salario || strtolower(trim($salario)) === 'a combinar') return 'A combinar';
    $num = salarioParaFloat($salario);
    if ($num <= 0) return 'A combinar';
    return 'R$ ' . number_format($num, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Explore vagas exclusivas da TGA Carreiras e dê o próximo passo na sua carreira.">
    <title>TGA Carreiras — Oportunidades de Emprego</title>

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
        /* ── Variáveis ──────────────────────────────────────── */
        :root {
            --primary:       #0066FF;
            --primary-dark:  #0052CC;
            --primary-light: #3385FF;
            --bg:            #0F172A;
            --bg-card:       #1E293B;
            --surface:       #334155;
            --text:          #F1F5F9;
            --text-sec:      #CBD5E1;
            --text-muted:    #64748B;
            --border:        #334155;
            --success:       #10B981;
            --warning:       #F59E0B;
            --shadow:        0 4px 12px rgba(0,0,0,.3);
            --shadow-xl:     0 20px 40px rgba(0,102,255,.3);
            --radius:        16px;
            --radius-sm:     10px;
            --transition:    all .3s ease;
        }

        [data-theme="light"] {
            --bg:        #FAFBFC;
            --bg-card:   #FFFFFF;
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
            min-height: 100vh;
            transition: background .3s, color .3s;
        }

        /* ── Header ─────────────────────────────────────────── */
        header {
            background: var(--bg-card);
            padding: 1.1rem 0;
            box-shadow: var(--shadow);
            position: sticky; top: 0; z-index: 100;
            border-bottom: 2px solid var(--primary);
        }

        .header-content {
            max-width: 1600px; margin: 0 auto; padding: 0 2rem;
            display: flex; align-items: center; justify-content: space-between; gap: 1.5rem;
        }

        .logo-section { display: flex; align-items: center; gap: 1rem; }

        .logo-icon {
            width: 46px; height: 46px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
            flex-shrink: 0;
        }

        .logo-text h1 { font-size: 1.55rem; font-weight: 800; color: var(--text); letter-spacing: -.5px; }
        .logo-text p  { color: var(--text-muted); font-size: .8rem; font-weight: 500; }

        .header-actions { display: flex; align-items: center; gap: .875rem; flex-wrap: wrap; }

        .stat-badge {
            background: var(--surface); padding: .45rem 1rem;
            border-radius: 30px; border: 1px solid var(--border);
            display: flex; align-items: center; gap: .5rem;
        }

        .stat-badge-number { font-weight: 800; font-size: 1.1rem; color: var(--primary); }
        .stat-badge-label  { color: var(--text-muted); font-size: .8rem; font-weight: 500; }

        .theme-toggle {
            width: 40px; height: 40px;
            border: 1px solid var(--border); border-radius: 9px;
            background: var(--surface); cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; transition: var(--transition);
        }

        .theme-toggle:hover { background: var(--primary); border-color: var(--primary); }

        /* ── Container ──────────────────────────────────────── */
        .container { max-width: 1600px; margin: 0 auto; padding: 2.5rem 2rem; }

        /* ── Hero ───────────────────────────────────────────── */
        .hero-section {
            text-align: center; margin-bottom: 2rem;
            padding: 2rem 2rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 22px; color: #fff;
            box-shadow: var(--shadow-xl);
            position: relative; overflow: hidden;
        }

        .hero-section::before,
        .hero-section::after {
            content: ''; position: absolute; border-radius: 50%;
            filter: blur(55px); pointer-events: none;
        }

        .hero-section::before { top: -40%; right: -8%; width: 320px; height: 320px; background: rgba(255,255,255,.1); }
        .hero-section::after  { bottom: -40%; left: -8%; width: 260px; height: 260px; background: rgba(255,255,255,.07); }

        .hero-content { position: relative; z-index: 1; }
        .hero-section h2 { font-size: 2.4rem; font-weight: 800; margin-bottom: .6rem; letter-spacing: -1px; }
        .hero-section p  { font-size: 1.05rem; opacity: .92; max-width: 540px; margin: 0 auto; }

        /* ── Hero Buttons ───────────────────────────────────── */
        .hero-buttons {
            display: flex; align-items: center; justify-content: center;
            gap: 1rem; margin-top: 1.5rem; flex-wrap: wrap;
        }

        .hero-btn {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .85rem 2rem;
            border-radius: 12px;
            font-family: inherit; font-size: .95rem; font-weight: 700;
            text-decoration: none; cursor: pointer;
            transition: all .3s ease;
            position: relative; overflow: hidden;
            letter-spacing: .2px;
        }

        .hero-btn .arrow {
            font-size: 1.2rem; font-weight: 800;
            transition: transform .3s ease;
        }

        .hero-btn:hover .arrow { transform: translateX(4px); }

        /* Botão branco sólido */
        .hero-btn-white {
            background: #fff;
            color: #0052CC;
            border: 2px solid #fff;
            box-shadow: 0 4px 18px rgba(0,0,0,.15);
        }

        .hero-btn-white:hover {
            background: #f0f4ff;
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(0,0,0,.2);
        }

        /* Botão outline transparente */
        .hero-btn-outline {
            background: rgba(255,255,255,.15);
            color: #fff;
            border: 2px solid rgba(255,255,255,.5);
            backdrop-filter: blur(4px);
        }

        .hero-btn-outline:hover {
            background: rgba(255,255,255,.25);
            border-color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(255,255,255,.15);
        }

        /* ── Barra de controles ─────────────────────────────── */
        .controls-bar {
            display: flex; align-items: center; gap: .75rem; flex-wrap: wrap;
            margin-bottom: 1.5rem;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: .875rem 1.25rem;
        }

        .search-box {
            display: flex; align-items: center;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: .5rem 1rem; gap: .5rem;
            flex: 1; min-width: 200px; transition: var(--transition);
        }

        .search-box:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,102,255,.12);
        }

        .search-input {
            border: none; outline: none;
            background: transparent; color: var(--text);
            font-family: inherit; font-size: .88rem; font-weight: 500; width: 100%;
        }

        .search-input::placeholder { color: var(--text-muted); }

        .filter-select {
            padding: .52rem .85rem;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 8px; color: var(--text);
            font-family: inherit; font-size: .85rem;
            cursor: pointer; outline: none; transition: var(--transition);
            max-width: 100%;
        }

        .filter-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,102,255,.12);
        }

        .btn-search {
            padding: .52rem 1.1rem;
            background: var(--primary); color: #fff;
            border: none; border-radius: 8px;
            font-family: inherit; font-size: .85rem; font-weight: 600;
            cursor: pointer; transition: var(--transition); white-space: nowrap;
        }

        .btn-search:hover { background: var(--primary-dark); transform: translateY(-1px); }

        .btn-clear {
            padding: .52rem .9rem;
            background: transparent; border: 1px solid var(--border);
            border-radius: 8px; color: var(--text-muted);
            font-family: inherit; font-size: .82rem;
            cursor: pointer; transition: var(--transition); text-decoration: none;
            display: inline-flex; align-items: center;
        }

        .btn-clear:hover { border-color: var(--primary); color: var(--primary); }

        .view-toggle {
            display: flex;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 8px; padding: 3px; gap: 2px;
            margin-left: auto;
            flex-shrink: 0;
        }

        .view-toggle-btn {
            padding: .4rem .8rem; border-radius: 6px;
            border: none; cursor: pointer;
            font-family: inherit; font-size: .8rem; font-weight: 600;
            display: flex; align-items: center; gap: .35rem;
            transition: var(--transition);
            background: transparent; color: var(--text-muted);
        }

        .view-toggle-btn.active { transform: translateY(-1px); }

        #btnLista.view-toggle-btn.active {
            background: #fff !important;
            color: #000 !important;
            box-shadow: 0 4px 14px rgba(255,255,255,.2);
        }

        #btnCards.view-toggle-btn.active {
            background: #fff !important;
            color: #000 !important;
            box-shadow: 0 4px 14px rgba(255,255,255,.2);
        }

        .filter-indicator {
            display: flex; align-items: center; gap: .75rem; flex-wrap: wrap;
            margin-bottom: 1.25rem; font-size: .82rem; color: var(--text-muted);
        }

        .filter-tag {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .25rem .75rem; border-radius: 20px;
            background: rgba(0,102,255,.1); border: 1px solid rgba(0,102,255,.2);
            color: var(--primary); font-weight: 600; font-size: .78rem;
        }

        /* ════════════════════════════════════════════════════
           BADGE SALÁRIO DESTACADO (todas as views)
        ════════════════════════════════════════════════════ */
        .salario-badge {
            padding: 5px 14px;
            border-radius: 8px;
            font-weight: 700;
            font-size: .8rem;
            color: #fff;
            display: inline-block;
            white-space: nowrap;
            letter-spacing: .2px;
        }

        .salario-verde {
            background: linear-gradient(135deg, #16a34a, #15803d);
            box-shadow: 0 2px 8px rgba(22,163,74,.35);
        }

        .salario-laranja {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            box-shadow: 0 2px 8px rgba(245,158,11,.35);
        }

        .salario-vermelho {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            box-shadow: 0 2px 8px rgba(220,38,38,.35);
        }

        .salario-neutro {
            background: var(--surface);
            color: var(--text-muted);
            border: 1px solid var(--border);
            box-shadow: none;
        }

        /* ════════════════════════════════════════════════════
           ANIMAÇÃO PULSANTE PARA BOTÕES "VER VAGA"
        ════════════════════════════════════════════════════ */
        @keyframes pulseGlow {
            0%   { transform: scale(1);    box-shadow: 0 4px 16px rgba(0,114,255,.25); }
            50%  { transform: scale(1.04); box-shadow: 0 6px 28px rgba(0,198,255,.45); }
            100% { transform: scale(1);    box-shadow: 0 4px 16px rgba(0,114,255,.25); }
        }

        @keyframes shimmer {
            0%   { left: -100%; }
            100% { left: 100%; }
        }

        /* ════════════════════════════════════════════════════
           VISUALIZAÇÃO 1 — LISTA (tabela desktop)
        ════════════════════════════════════════════════════ */
        #viewLista { display: none; }

        .table-wrap {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow-x: auto;
            overflow-y: hidden;
            box-shadow: var(--shadow);
        }

        .table-list { width: 100%; border-collapse: collapse; }
        .table-list thead { background: var(--surface); }

        .table-list th {
            padding: .55rem .65rem;
            text-align: left;
            font-size: .62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .table-list td {
            padding: .55rem .65rem;
            border-bottom: 1px solid var(--border);
            color: var(--text-sec);
            font-size: .78rem;
            vertical-align: middle;
        }

        .table-list tbody tr { transition: background .15s; }
        .table-list tbody tr:hover { background: var(--surface); }
        .table-list tbody tr:last-child td { border-bottom: none; }

        .tl-thumb {
            width: 50px; height: 50px; object-fit: cover;
            border-radius: 9px; border: 1px solid var(--border); display: block;
        }

        .tl-thumb-ph {
            width: 50px; height: 50px;
            background: var(--surface); border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; border: 1px solid var(--border);
        }

        .tl-title { font-weight: 700; color: var(--text); font-size: .9rem; }
        .tl-sub   { font-size: .75rem; color: var(--text-muted); margin-top: .15rem; }

        .badge-nova {
            display: inline-flex; align-items: center; gap: .25rem;
            padding: .18rem .55rem; border-radius: 20px;
            background: rgba(245,158,11,.12); color: var(--warning);
            font-size: .68rem; font-weight: 700; text-transform: uppercase;
            border: 1px solid rgba(245,158,11,.25);
            vertical-align: middle; margin-left: .35rem;
        }

        .badge-candidatos {
            display: inline-flex; align-items: center; gap: .3rem;
            padding: .2rem .6rem; border-radius: 20px;
            background: rgba(0,102,255,.08); color: var(--primary);
            font-size: .72rem; font-weight: 700;
            border: 1px solid rgba(0,102,255,.15);
        }

        .badge-setor {
            display: inline-flex; align-items: center; gap: .28rem;
            padding: .2rem .6rem; border-radius: 20px;
            background: var(--surface); color: var(--text-muted);
            font-size: .72rem; font-weight: 600;
            border: 1px solid var(--border);
        }

        /* ── Botão tabela — "Ver vaga" destacado ──────────── */
        .btn-candidatar {
            display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
            padding: .6rem 1.4rem;
            background: linear-gradient(135deg, #00c6ff, #0072ff);
            color: #fff; text-decoration: none;
            border-radius: 10px; font-size: .82rem; font-weight: 700;
            transition: all .3s ease; white-space: nowrap;
            animation: pulseGlow 2s ease-in-out infinite;
            position: relative; overflow: hidden;
        }

        .btn-candidatar::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.25), transparent);
            animation: shimmer 2.5s ease-in-out infinite;
        }

        .btn-candidatar:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 8px 28px rgba(0,114,255,.5);
            animation: none;
        }

        /* ════════════════════════════════════════════════════
           MOBILE LISTA — Cards empilhados
        ════════════════════════════════════════════════════ */
        .mobile-lista { display: none; }

        .ml-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: .75rem;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .ml-card:hover { border-color: var(--primary); }

        .ml-card-header {
            display: flex; align-items: center;
            gap: .75rem; margin-bottom: .75rem;
        }

        .ml-card-thumb {
            width: 48px; height: 48px; object-fit: cover;
            border-radius: 10px; border: 1px solid var(--border);
            flex-shrink: 0;
        }

        .ml-card-thumb-ph {
            width: 48px; height: 48px;
            background: var(--surface); border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; border: 1px solid var(--border);
            flex-shrink: 0;
        }

        .ml-card-title-area { flex: 1; min-width: 0; }

        .ml-card-empresa {
            font-size: .78rem; font-weight: 600;
            color: var(--primary); margin-bottom: .15rem;
        }

        .ml-card-titulo {
            font-size: .95rem; font-weight: 700;
            color: var(--text); line-height: 1.3;
            display: flex; align-items: center; gap: .35rem; flex-wrap: wrap;
        }

        .ml-card-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .4rem .75rem;
            margin-bottom: .75rem;
        }

        .ml-card-field {
            display: flex; flex-direction: column; gap: .1rem;
        }

        .ml-card-label {
            font-size: .62rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .4px;
            color: var(--text-muted);
        }

        .ml-card-value {
            font-size: .8rem; font-weight: 600;
            color: var(--text-sec);
            overflow: hidden; text-overflow: ellipsis;
        }

        .ml-card-footer {
            display: flex; align-items: center;
            justify-content: space-between;
            gap: .5rem; flex-wrap: wrap;
            padding-top: .65rem;
            border-top: 1px solid var(--border);
        }

        .ml-card-badges {
            display: flex; gap: .35rem; flex-wrap: wrap;
        }

        /* ── Botão mobile lista — "Ver vaga" pulsante ──────── */
        .ml-card-cta {
            display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
            padding: .6rem 1.4rem;
            background: linear-gradient(135deg, #00c6ff, #0072ff);
            color: #fff; text-decoration: none;
            border-radius: 10px; font-size: .82rem; font-weight: 700;
            transition: all .3s ease; white-space: nowrap;
            margin-left: auto;
            animation: pulseGlow 2s ease-in-out infinite;
            position: relative; overflow: hidden;
        }

        .ml-card-cta::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.25), transparent);
            animation: shimmer 2.5s ease-in-out infinite;
        }

        .ml-card-cta:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 8px 28px rgba(0,114,255,.5);
            animation: none;
        }

        /* ════════════════════════════════════════════════════
           VISUALIZAÇÃO 2 — CARDS (grid)
        ════════════════════════════════════════════════════ */
        #viewCards { display: block; }

        .vagas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 1.4rem;
        }

        .vaga-card {
            background: var(--bg-card);
            border-radius: var(--radius); border: 1px solid var(--border);
            box-shadow: var(--shadow);
            transition: all .35s cubic-bezier(.4,0,.2,1);
            position: relative; overflow: hidden;
            display: flex; flex-direction: column;
            animation: fadeInUp .45s ease-out backwards;
        }

        .vaga-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            transform: scaleX(0); transform-origin: left; transition: transform .4s ease;
        }

        .vaga-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-xl); border-color: var(--primary); }
        .vaga-card:hover::before { transform: scaleX(1); }

        .vaga-card-img {
            width: 100%; height: 170px; object-fit: cover;
            display: block; border-bottom: 1px solid var(--border);
        }

        .vaga-card-img-ph {
            width: 100%; height: 90px;
            background: linear-gradient(135deg, var(--surface), var(--border));
            display: flex; align-items: center; justify-content: center;
            font-size: 2.25rem; color: var(--text-muted);
        }

        .vaga-card-body { padding: 1.25rem; flex: 1; display: flex; flex-direction: column; }

        .vaga-card-title-row {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: .5rem; margin-bottom: .4rem;
        }

        .vaga-titulo { font-size: 1.1rem; font-weight: 700; color: var(--text); line-height: 1.3; flex: 1; }

        .vaga-meta-row {
            display: flex; align-items: center; gap: .4rem;
            flex-wrap: wrap; margin-bottom: .875rem;
        }

        .vaga-info { display: flex; flex-direction: column; gap: .6rem; margin-bottom: 1.1rem; }

        .info-item { display: flex; align-items: center; gap: .75rem; }

        .info-icon {
            width: 38px; height: 38px;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; flex-shrink: 0; transition: var(--transition);
        }

        .vaga-card:hover .info-icon { background: var(--primary); border-color: var(--primary); }

        .info-label { font-size: .67rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }
        .info-text  { color: var(--text-sec); font-weight: 600; font-size: .85rem; }

        /* ── Botão card — "Ver detalhes" pulsante ─────────── */
        .btn-card {
            display: flex; align-items: center; justify-content: center; gap: .5rem;
            width: 100%;
            padding: 1rem 1.4rem;
            background: linear-gradient(135deg, #00c6ff, #0072ff);
            color: #fff; text-decoration: none;
            border-radius: 12px;
            font-weight: 800; font-size: .95rem;
            transition: all .3s ease;
            animation: pulseGlow 2s ease-in-out infinite;
            border: none; cursor: pointer;
            position: relative; overflow: hidden; margin-top: auto;
        }

        .btn-card::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.25), transparent);
            animation: shimmer 2.5s ease-in-out infinite;
        }

        .btn-card:hover {
            transform: translateY(-3px) scale(1.03);
            box-shadow: 0 10px 32px rgba(0,114,255,.5);
            animation: none;
        }

        .btn-card:hover::before { animation: none; left: 100%; }
        .btn-card .arrow { transition: transform .3s; }
        .btn-card:hover .arrow { transform: translateX(5px); }

        /* ── Paginação ───────────────────────────────────────── */
        .pagination {
            display: flex; align-items: center; justify-content: center;
            gap: .4rem; margin-top: 2.5rem; flex-wrap: wrap;
        }

        .page-btn {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 38px; height: 38px; padding: 0 .6rem;
            border: 1px solid var(--border); border-radius: 8px;
            background: var(--bg-card); color: var(--text-sec);
            font-family: inherit; font-size: .85rem; font-weight: 600;
            text-decoration: none; cursor: pointer; transition: var(--transition);
        }

        .page-btn:hover  { background: var(--surface); border-color: var(--primary); color: var(--primary); }
        .page-btn.active { background: var(--primary); border-color: var(--primary); color: #fff; cursor: default; }
        .page-btn.disabled { opacity: .35; pointer-events: none; }

        .page-info { font-size: .8rem; color: var(--text-muted); margin-left: .5rem; }

        /* ── Empty state ────────────────────────────────────── */
        .empty-state {
            background: var(--bg-card); border-radius: 22px;
            padding: 4.5rem 2rem; text-align: center;
            box-shadow: var(--shadow); border: 1px solid var(--border);
        }

        .empty-state-icon { font-size: 4rem; margin-bottom: 1.25rem; opacity: .45; }
        .empty-state h3   { font-size: 1.5rem; color: var(--text); margin-bottom: .6rem; font-weight: 700; }
        .empty-state p    { color: var(--text-muted); font-size: .95rem; max-width: 460px; margin: 0 auto; }
        .empty-state a    { color: var(--primary); text-decoration: none; }

        /* ── Footer ─────────────────────────────────────────── */
        footer {
            background: var(--bg-card);
            margin-top: 4rem; padding: 2.25rem 2rem;
            border-top: 1px solid var(--border);
        }

        .footer-content {
            max-width: 1600px; margin: 0 auto;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 1.5rem;
        }

        .footer-logo { display: flex; align-items: center; gap: .75rem; }

        .footer-logo-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center; font-size: 1rem;
        }

        .footer-logo-text { font-weight: 700; font-size: 1.05rem; color: var(--text); }

        .footer-links { display: flex; gap: 1.5rem; flex-wrap: wrap; }

        .footer-link {
            color: var(--text-muted); text-decoration: none;
            font-weight: 500; font-size: .85rem; transition: var(--transition);
        }

        .footer-link:hover { color: var(--primary); }

        .footer-social { display: flex; gap: .6rem; }

        .social-icon {
            width: 36px; height: 36px;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: .95rem; transition: var(--transition); text-decoration: none;
        }

        .social-icon:hover { background: var(--primary); border-color: var(--primary); transform: translateY(-2px); }

        .vaga-empresa {
            font-size: .85rem; font-weight: 600;
            color: var(--primary); margin-bottom: .4rem;
        }

        .tl-company-col {
            font-weight: 600;
            color: var(--primary);
        }

        /* ── Animações ──────────────────────────────────────── */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .vaga-card:nth-child(1) { animation-delay: .04s; }
        .vaga-card:nth-child(2) { animation-delay: .08s; }
        .vaga-card:nth-child(3) { animation-delay: .12s; }
        .vaga-card:nth-child(4) { animation-delay: .16s; }
        .vaga-card:nth-child(5) { animation-delay: .2s;  }
        .vaga-card:nth-child(6) { animation-delay: .24s; }

        /* ════════════════════════════════════════════════════
           RESPONSIVO — TABLET (≤ 1024px)
        ════════════════════════════════════════════════════ */
        @media (max-width: 1024px) {
            .vagas-grid {
                grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            }
            .container { padding: 2rem 1.25rem; }
            .hero-section { padding: 1.75rem 1.5rem; }
            .hero-section h2 { font-size: 2rem; }
        }

        /* ════════════════════════════════════════════════════
           RESPONSIVO — MOBILE (≤ 768px)
        ════════════════════════════════════════════════════ */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column; gap: .75rem; padding: 0 1rem;
            }

            .header-actions { width: 100%; justify-content: center; }
            .logo-text h1 { font-size: 1.3rem; }

            .hero-section {
                padding: 1.5rem 1.25rem; border-radius: 16px; margin-bottom: 1.25rem;
            }
            .hero-section h2 { font-size: 1.6rem; }
            .hero-section p  { font-size: .9rem; }

            .hero-buttons { gap: .6rem; margin-top: 1.1rem; }
            .hero-btn { padding: .7rem 1.4rem; font-size: .85rem; width: 100%; justify-content: center; }

            .container { padding: 1.5rem 1rem; }

            .controls-bar {
                flex-direction: column; align-items: stretch;
                gap: .5rem; padding: .75rem;
            }

            .search-box { min-width: 0; width: 100%; }
            .filter-select { width: 100%; font-size: .82rem; }
            .view-toggle { margin-left: 0; align-self: center; }
            .btn-clear { align-self: center; }

            .table-wrap { display: none !important; }
            .mobile-lista { display: block !important; }

            .vagas-grid { grid-template-columns: 1fr; gap: 1rem; }
            .vaga-card-img { height: 140px; }
            .vaga-card-body { padding: 1rem; }
            .vaga-titulo { font-size: 1rem; }
            .info-icon { width: 34px; height: 34px; font-size: .9rem; }

            .pagination { gap: .25rem; margin-top: 1.75rem; }
            .page-btn { min-width: 34px; height: 34px; font-size: .78rem; padding: 0 .4rem; }
            .page-info { font-size: .72rem; width: 100%; text-align: center; margin: .5rem 0 0 0; }

            footer { padding: 1.5rem 1rem; margin-top: 2.5rem; }
            .footer-content { flex-direction: column; text-align: center; }
            .footer-links { justify-content: center; gap: 1rem; }
            .footer-social { justify-content: center; }
        }

        /* ════════════════════════════════════════════════════
           RESPONSIVO — MOBILE PEQUENO (≤ 480px)
        ════════════════════════════════════════════════════ */
        @media (max-width: 480px) {
            .header-content { padding: 0 .75rem; }

            .logo-icon { width: 38px; height: 38px; font-size: 1.1rem; }
            .logo-text h1 { font-size: 1.15rem; }
            .logo-text p  { font-size: .72rem; }

            .stat-badge { padding: .35rem .7rem; }
            .stat-badge-number { font-size: .95rem; }
            .stat-badge-label  { font-size: .7rem; }

            .hero-section { padding: 1.25rem 1rem; border-radius: 14px; }
            .hero-section h2 { font-size: 1.35rem; letter-spacing: -.5px; }
            .hero-section p  { font-size: .82rem; }

            .hero-btn { padding: .65rem 1.1rem; font-size: .8rem; border-radius: 10px; }

            .container { padding: 1rem .75rem; }
            .controls-bar { padding: .6rem; }

            .ml-card { padding: .85rem; }
            .ml-card-grid { grid-template-columns: 1fr; gap: .35rem; }
            .ml-card-titulo { font-size: .88rem; }
            .ml-card-empresa { font-size: .72rem; }

            .ml-card-footer { flex-direction: column; align-items: stretch; }
            .ml-card-cta { margin-left: 0; justify-content: center; width: 100%; }

            .vaga-card-img { height: 120px; }
            .vaga-card-img-ph { height: 70px; font-size: 1.8rem; }
            .vaga-card-body { padding: .875rem; }
            .vaga-titulo { font-size: .95rem; }
            .vaga-empresa { font-size: .78rem; }

            .vaga-meta-row { gap: .3rem; }
            .badge-setor, .badge-candidatos { font-size: .65rem; padding: .15rem .45rem; }

            .info-icon { width: 32px; height: 32px; font-size: .85rem; }
            .info-label { font-size: .6rem; }
            .info-text  { font-size: .78rem; }

            .btn-card { padding: .7rem 1rem; font-size: .82rem; }
            .page-btn { min-width: 30px; height: 30px; font-size: .72rem; }

            .empty-state { padding: 3rem 1.25rem; border-radius: 16px; }
            .empty-state-icon { font-size: 3rem; }
            .empty-state h3 { font-size: 1.2rem; }
            .empty-state p { font-size: .85rem; }
        }

        /* ════════════════════════════════════════════════════
           RESPONSIVO — TELA MUITO PEQUENA (≤ 360px)
        ════════════════════════════════════════════════════ */
        @media (max-width: 360px) {
            .container { padding: .75rem .5rem; }
            .header-content { padding: 0 .5rem; }
            .hero-section h2 { font-size: 1.2rem; }
            .hero-section p  { font-size: .78rem; }
            .vaga-titulo { font-size: .88rem; }
            .ml-card-thumb, .ml-card-thumb-ph { width: 40px; height: 40px; }
        }
    </style>
</head>
<body>

<!-- ===== HEADER ===== -->
<header>
    <div class="header-content">
        <div class="logo-section">
            <div class="logo-icon">🚀</div>
            <div class="logo-text">
                <h1>TGA Carreiras</h1>
                <p>Conectando talentos a oportunidades</p>
            </div>
        </div>
        <div class="header-actions">
            <div class="stat-badge">
                <span class="stat-badge-number"><?= $totalVagas ?></span>
                <span class="stat-badge-label"><?= $totalVagas === 1 ? 'Vaga Disponível' : 'Vagas Disponíveis' ?></span>
            </div>
            <button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema">
                <span id="theme-icon">🌙</span>
            </button>
        </div>
    </div>
</header>

<!-- ===== CONTEÚDO ===== -->
<div class="container">

    <!-- Hero -->
    <div class="hero-section">
        <div class="hero-content">
            <h2>Encontre sua próxima oportunidade 🎒</h2>
            <p>Explore nossas vagas exclusivas e dê o próximo passo na sua carreira profissional</p>
            <div class="hero-buttons">
                <a href="../candidato/cadastro.php" class="hero-btn hero-btn-white">
                    🚀 Criar conta grátis <span class="arrow">›</span>
                </a>
                <a href="../candidato/login.php" class="hero-btn hero-btn-outline">
                    💼 Cadastrar currículo <span class="arrow">›</span>
                </a>
            </div>
        </div>
    </div>

    <!-- ═══ Barra de controles ════════════════════════════ -->
    <form method="GET" action="index.php" id="filtrosForm">
        <div class="controls-bar">
            <div class="view-toggle" onclick="event.stopPropagation()">
                <button type="button" class="view-toggle-btn" id="btnLista" onclick="setView('lista')">☰ Lista</button>
                <button type="button" class="view-toggle-btn" id="btnCards" onclick="setView('cards')">⊞ Cards</button>
            </div>

            <div class="search-box">
                <span>🔍</span>
                <input type="text" class="search-input" name="busca" id="searchInput"
                       placeholder="Cargo, cidade ou palavra-chave…"
                       value="<?= htmlspecialchars($filtroBusca) ?>" autocomplete="off">
            </div>

            <select name="empresa" class="filter-select" onchange="this.form.submit()">
                <option value="">Todas as empresas</option>
                <?php foreach ($empresas as $empresa): ?>
                <option value="<?= htmlspecialchars($empresa) ?>"
                    <?= $filtroEmpresa === $empresa ? 'selected' : '' ?>>
                    <?= htmlspecialchars($empresa) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <select name="cargo" class="filter-select" onchange="this.form.submit()">
                <option value="">Todas as vagas</option>
                <?php foreach ($cargos as $cargo): ?>
                <option value="<?= htmlspecialchars($cargo) ?>"
                    <?= $filtroCargo === $cargo ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cargo) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <select name="cidade" class="filter-select" onchange="this.form.submit()">
                <option value="">Todas as cidades</option>
                <?php foreach ($cidades as $cidade): ?>
                <option value="<?= htmlspecialchars($cidade) ?>"
                    <?= $filtroCidade === $cidade ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cidade) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <select name="ordem" class="filter-select" onchange="this.form.submit()">
                <option value="recentes" <?= $ordenacao === 'recentes' ? 'selected' : '' ?>>📅 Mais recentes</option>
                <option value="antigas"  <?= $ordenacao === 'antigas'  ? 'selected' : '' ?>>🕰️ Mais antigas</option>
                <option value="az"       <?= $ordenacao === 'az'       ? 'selected' : '' ?>>🔤 A – Z</option>
                <option value="salario"  <?= $ordenacao === 'salario'  ? 'selected' : '' ?>>💰 Maior salário</option>
            </select>

            <?php if ($filtroSetor || $filtroBusca || $filtroCargo || $filtroEmpresa || $filtroCidade || $ordenacao !== 'recentes'): ?>
            <a href="index.php" class="btn-clear">✕ Limpar</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($filtroSetor || $filtroBusca): ?>
    <div class="filter-indicator">
        <span>Filtrando por:</span>
        <?php if ($filtroBusca): ?>
        <span class="filter-tag">🔍 "<?= htmlspecialchars($filtroBusca) ?>"</span>
        <?php endif; ?>
        <?php if ($filtroSetor): ?>
        <span class="filter-tag">🏷️ <?= htmlspecialchars($filtroSetor) ?></span>
        <?php endif; ?>
        <span>— <?= $totalRegistros ?> resultado(s)</span>
    </div>
    <?php endif; ?>

    <?php if (count($vagas) > 0): ?>

    <!-- ═══════════════════════════════════════════════════
         VISUALIZAÇÃO 1 — LISTA (tabela desktop)
    ═══════════════════════════════════════════════════ -->
    <div id="viewLista">
        <div class="table-wrap">
            <table class="table-list">
                <thead>
                    <tr>
                        <th>Imagem</th>
                        <th>Empresa</th>
                        <th>Cargo</th>
                        <th>Qtd</th>
                        <th>Disponibilidade</th>
                        <th>Escolaridade</th>
                        <th>Experiência</th>
                        <th>Localização</th>
                        <th>Salário</th>
                        <th>Candidatos</th>
                        <th>Publicado</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vagas as $v):
                        $ehNova = strtotime($v['data_publicacao']) >= strtotime('-7 days');
                        $salTxt = formatarSalario($v['salario'] ?? '');
                        $salCls = classeSalario($v['salario'] ?? '');
                    ?>
                    <tr>
                        <td>
                            <?php if (!empty($v['imagem'])): ?>
                            <img src="../uploads/vagas/<?= htmlspecialchars($v['imagem']) ?>"
                                 class="tl-thumb" alt="<?= htmlspecialchars($v['titulo']) ?>">
                            <?php else: ?>
                            <div class="tl-thumb-ph">💼</div>
                            <?php endif; ?>
                        </td>
                        <td class="tl-company-col">
                            <?= htmlspecialchars($v['empresa'] ?: 'Não informado') ?>
                        </td>
                        <td>
                            <div class="tl-title">
                                <?= htmlspecialchars($v['titulo']) ?>
                                <?php if ($ehNova): ?>
                                    <span class="badge-nova">✨ Nova</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= (int)($v['quantidade_vagas'] ?? 1) ?></td>
                        <td><?= htmlspecialchars($v['disponibilidade'] ?: 'Não informado') ?></td>
                        <td><?= htmlspecialchars($v['escolaridade'] ?: 'Não informado') ?></td>
                        <td><?= htmlspecialchars($v['experiencia'] ?: 'Não informado') ?></td>
                        <td>📍 <?= htmlspecialchars($v['localizacao'] ?: 'Não informado') ?></td>
                        <td>
                            <span class="salario-badge <?= $salCls ?>">
                                💰 <?= htmlspecialchars($salTxt) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge-candidatos">👥 <?= $v['total_candidatos'] ?></span>
                        </td>
                        <td><?= date('d/m/Y', strtotime($v['data_publicacao'])) ?></td>
                        <td>
                            <a href="vaga.php?id=<?= $v['id'] ?>" class="btn-candidatar">
                                🔥 Ver vaga →
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- MOBILE LISTA -->
        <div class="mobile-lista">
            <?php foreach ($vagas as $v):
                $ehNova = strtotime($v['data_publicacao']) >= strtotime('-7 days');
                $salTxt = formatarSalario($v['salario'] ?? '');
                $salCls = classeSalario($v['salario'] ?? '');
            ?>
            <div class="ml-card">
                <div class="ml-card-header">
                    <?php if (!empty($v['imagem'])): ?>
                    <img src="../uploads/vagas/<?= htmlspecialchars($v['imagem']) ?>"
                         class="ml-card-thumb" alt="<?= htmlspecialchars($v['titulo']) ?>">
                    <?php else: ?>
                    <div class="ml-card-thumb-ph">💼</div>
                    <?php endif; ?>

                    <div class="ml-card-title-area">
                        <div class="ml-card-empresa">
                            🏢 <?= htmlspecialchars($v['empresa'] ?: 'Não informado') ?>
                        </div>
                        <div class="ml-card-titulo">
                            <?= htmlspecialchars($v['titulo']) ?>
                            <?php if ($ehNova): ?>
                                <span class="badge-nova">✨ Nova</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="ml-card-grid">
                    <div class="ml-card-field">
                        <span class="ml-card-label">📍 Localização</span>
                        <span class="ml-card-value"><?= htmlspecialchars($v['localizacao'] ?: 'Não informado') ?></span>
                    </div>
                    <div class="ml-card-field">
                        <span class="ml-card-label">💰 Salário</span>
                        <span class="salario-badge <?= $salCls ?>" style="font-size:.78rem; padding:4px 10px;">
                            <?= htmlspecialchars($salTxt) ?>
                        </span>
                    </div>
                    <div class="ml-card-field">
                        <span class="ml-card-label">📌 Vagas</span>
                        <span class="ml-card-value"><?= (int)($v['quantidade_vagas'] ?? 1) ?></span>
                    </div>
                    <div class="ml-card-field">
                        <span class="ml-card-label">⏰ Disponibilidade</span>
                        <span class="ml-card-value"><?= htmlspecialchars($v['disponibilidade'] ?: 'Não informado') ?></span>
                    </div>
                    <div class="ml-card-field">
                        <span class="ml-card-label">🎓 Escolaridade</span>
                        <span class="ml-card-value"><?= htmlspecialchars($v['escolaridade'] ?: 'Não informado') ?></span>
                    </div>
                    <div class="ml-card-field">
                        <span class="ml-card-label">💼 Experiência</span>
                        <span class="ml-card-value"><?= htmlspecialchars($v['experiencia'] ?: 'Não informado') ?></span>
                    </div>
                </div>

                <div class="ml-card-footer">
                    <div class="ml-card-badges">
                        <span class="badge-candidatos">👥 <?= $v['total_candidatos'] ?></span>
                        <span class="badge-setor">📅 <?= date('d/m/Y', strtotime($v['data_publicacao'])) ?></span>
                    </div>
                    <a href="vaga.php?id=<?= $v['id'] ?>" class="ml-card-cta">🔥 Ver vaga →</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════
         VISUALIZAÇÃO 2 — CARDS (grid)
    ═══════════════════════════════════════════════════ -->
    <div id="viewCards">
        <div class="vagas-grid">
            <?php foreach ($vagas as $v):
                $ehNova = strtotime($v['data_publicacao']) >= strtotime('-7 days');
                $salTxt = formatarSalario($v['salario'] ?? '');
                $salCls = classeSalario($v['salario'] ?? '');
            ?>
            <div class="vaga-card">
                <?php if (!empty($v['imagem'])): ?>
                <img src="../uploads/vagas/<?= htmlspecialchars($v['imagem']) ?>"
                     class="vaga-card-img" alt="<?= htmlspecialchars($v['titulo']) ?>">
                <?php else: ?>
                <div class="vaga-card-img-ph">💼</div>
                <?php endif; ?>

                <div class="vaga-card-body">
                    <?php if (!empty($v['empresa'])): ?>
                    <div class="vaga-empresa">🏢 <?= htmlspecialchars($v['empresa']) ?></div>
                    <?php endif; ?>

                    <div class="vaga-card-title-row">
                        <h3 class="vaga-titulo"><?= htmlspecialchars($v['titulo']) ?></h3>
                        <?php if ($ehNova): ?>
                            <span class="badge-nova">✨ Nova</span>
                        <?php endif; ?>
                    </div>

                    <div class="vaga-meta-row">
                        <?php if (!empty($v['setor'])): ?>
                        <span class="badge-setor">🏷️ <?= htmlspecialchars($v['setor']) ?></span>
                        <?php endif; ?>
                        <span class="badge-setor">📌 <?= (int)($v['quantidade_vagas'] ?? 1) ?> vaga(s)</span>
                        <?php if (!empty($v['disponibilidade'])): ?>
                        <span class="badge-setor">⏰ <?= htmlspecialchars($v['disponibilidade']) ?></span>
                        <?php endif; ?>
                        <span class="badge-candidatos">👥 <?= $v['total_candidatos'] ?> candidato(s)</span>
                    </div>

                    <div class="vaga-info">
                        <div class="info-item">
                            <div class="info-icon">📍</div>
                            <div>
                                <div class="info-label">Localização</div>
                                <div class="info-text"><?= htmlspecialchars($v['localizacao'] ?: 'Não informado') ?></div>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">💰</div>
                            <div>
                                <div class="info-label">Remuneração</div>
                                <div class="info-text">
                                    <span class="salario-badge <?= $salCls ?>">
                                        <?= htmlspecialchars($salTxt) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">🎓</div>
                            <div>
                                <div class="info-label">Escolaridade</div>
                                <div class="info-text"><?= htmlspecialchars($v['escolaridade'] ?: 'Não informado') ?></div>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">💼</div>
                            <div>
                                <div class="info-label">Experiência</div>
                                <div class="info-text"><?= htmlspecialchars($v['experiencia'] ?: 'Não informado') ?></div>
                            </div>
                        </div>
                    </div>

                    <a href="vaga.php?id=<?= $v['id'] ?>" class="btn-card">
                        <span>🔥 Ver detalhes completos</span>
                        <span class="arrow">→</span>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ═══ PAGINAÇÃO ═══════════════════════════════════ -->
    <?php if ($totalPaginas > 1): ?>
    <nav class="pagination" aria-label="Paginação de vagas">
        <?php if ($paginaAtual > 1): ?>
        <a href="<?= paginaUrl($paginaAtual - 1) ?>" class="page-btn" title="Página anterior">‹</a>
        <?php else: ?>
        <span class="page-btn disabled">‹</span>
        <?php endif; ?>

        <?php
        $inicio = max(1, $paginaAtual - 3);
        $fim    = min($totalPaginas, $paginaAtual + 3);

        if ($inicio > 1): ?>
            <a href="<?= paginaUrl(1) ?>" class="page-btn">1</a>
            <?php if ($inicio > 2): ?><span class="page-btn disabled" style="border:none;padding:0 .25rem">…</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($p = $inicio; $p <= $fim; $p++): ?>
        <?php if ($p === $paginaAtual): ?>
        <span class="page-btn active"><?= $p ?></span>
        <?php else: ?>
        <a href="<?= paginaUrl($p) ?>" class="page-btn"><?= $p ?></a>
        <?php endif; ?>
        <?php endfor; ?>

        <?php if ($fim < $totalPaginas): ?>
            <?php if ($fim < $totalPaginas - 1): ?><span class="page-btn disabled" style="border:none;padding:0 .25rem">…</span><?php endif; ?>
            <a href="<?= paginaUrl($totalPaginas) ?>" class="page-btn"><?= $totalPaginas ?></a>
        <?php endif; ?>

        <?php if ($paginaAtual < $totalPaginas): ?>
        <a href="<?= paginaUrl($paginaAtual + 1) ?>" class="page-btn" title="Próxima página">›</a>
        <?php else: ?>
        <span class="page-btn disabled">›</span>
        <?php endif; ?>

        <span class="page-info">
            Página <?= $paginaAtual ?> de <?= $totalPaginas ?>
            &nbsp;·&nbsp; <?= $totalRegistros ?> vaga(s)
        </span>
    </nav>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty-state">
        <div class="empty-state-icon">🔍</div>
        <h3>Nenhuma vaga encontrada</h3>
        <p>
            <?php if ($filtroSetor || $filtroBusca): ?>
                Tente ajustar os filtros ou <a href="index.php">ver todas as vagas</a>.
            <?php else: ?>
                Estamos sempre em busca de novos talentos. Volte em breve!
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>

</div>

<!-- ===== FOOTER ===== -->
<footer>
    <div class="footer-content">
        <div class="footer-logo">
            <div class="footer-logo-icon">🚀</div>
            <span class="footer-logo-text">TGA Carreiras</span>
        </div>
        <div class="footer-links">
            <a href="#" class="footer-link">Sobre nós</a>
            <a href="#" class="footer-link">Contato</a>
            <a href="#" class="footer-link">Privacidade</a>
            <a href="#" class="footer-link">Termos de Uso</a>
        </div>
        <div class="footer-social">
            <a href="#" class="social-icon" title="LinkedIn">💼</a>
            <a href="#" class="social-icon" title="Instagram">📷</a>
            <a href="#" class="social-icon" title="WhatsApp">💬</a>
        </div>
    </div>
</footer>

<script>
"use strict";

function setView(view) {
    const elLista = document.getElementById('viewLista');
    const elCards = document.getElementById('viewCards');
    const btnL    = document.getElementById('btnLista');
    const btnC    = document.getElementById('btnCards');

    if (view === 'lista') {
        if (elLista) elLista.style.display = 'block';
        if (elCards) elCards.style.display = 'none';
        btnL?.classList.add('active');
        btnC?.classList.remove('active');
    } else {
        if (elLista) elLista.style.display = 'none';
        if (elCards) elCards.style.display = 'block';
        btnC?.classList.add('active');
        btnL?.classList.remove('active');
    }
    localStorage.setItem('vagasView', view);
}

function toggleTheme() {
    const html    = document.documentElement;
    const icon    = document.getElementById('theme-icon');
    const current = html.getAttribute('data-theme') || 'dark';
    const next    = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    icon.textContent = next === 'light' ? '☀️' : '🌙';
    localStorage.setItem('theme', next);
}

window.addEventListener('DOMContentLoaded', () => {
    const savedTheme = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
    const icon = document.getElementById('theme-icon');
    if (icon) icon.textContent = savedTheme === 'light' ? '☀️' : '🌙';

    const savedView = localStorage.getItem('vagasView') || 'cards';
    setView(savedView);
});
</script>

</body>
</html>