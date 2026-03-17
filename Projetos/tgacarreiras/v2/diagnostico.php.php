<?php
/* ============================================================
   diagnostico.php — TGA Carreiras
   Diagnóstico completo do projeto:
   ─ Árvore de pastas e arquivos
   ─ Informações do servidor
   ─ Verificação de banco de dados
   ─ Status de saúde do sistema
   ─ Permissões de pastas críticas
============================================================ */
$startTime = microtime(true);

/* ── Configuração ──────────────────────────────────────── */
$raizProjeto   = realpath(__DIR__ . '/..') ?: __DIR__;
$nomeProjeto   = basename($raizProjeto);

/* Pastas/arquivos a ignorar */
$ignorar = ['.', '..', '.git', '.svn', 'node_modules', '.DS_Store', 'Thumbs.db', '.env', '.htaccess'];

/* Extensões e seus ícones/cores */
$extMap = [
    'php'  => ['🐘', '#8892BF', 'PHP'],
    'html' => ['🌐', '#E44D26', 'HTML'],
    'htm'  => ['🌐', '#E44D26', 'HTML'],
    'css'  => ['🎨', '#1572B6', 'CSS'],
    'js'   => ['⚡', '#F7DF1E', 'JavaScript'],
    'json' => ['📋', '#A8B9CC', 'JSON'],
    'sql'  => ['🗄️', '#00758F', 'SQL'],
    'md'   => ['📝', '#083FA1', 'Markdown'],
    'txt'  => ['📄', '#6B7280', 'Texto'],
    'xml'  => ['📰', '#FF6600', 'XML'],
    'jpg'  => ['🖼️', '#10B981', 'Imagem'],
    'jpeg' => ['🖼️', '#10B981', 'Imagem'],
    'png'  => ['🖼️', '#10B981', 'Imagem'],
    'gif'  => ['🖼️', '#10B981', 'Imagem'],
    'webp' => ['🖼️', '#10B981', 'Imagem'],
    'svg'  => ['🖼️', '#FFB13B', 'SVG'],
    'ico'  => ['🖼️', '#10B981', 'Ícone'],
    'pdf'  => ['📕', '#DC2626', 'PDF'],
    'zip'  => ['📦', '#F59E0B', 'Arquivo'],
    'rar'  => ['📦', '#F59E0B', 'Arquivo'],
    'env'  => ['🔒', '#EF4444', 'Config'],
    'log'  => ['📜', '#8B5CF6', 'Log'],
    'htaccess' => ['⚙️', '#6B7280', 'Config'],
];

/* ── Funções auxiliares ────────────────────────────────── */
function tamanhoHumano($bytes) {
    if ($bytes == 0) return '0 B';
    $unidades = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $unidades[$i];
}

function contarConteudo($dir, $ignorar) {
    $pastas = 0; $arquivos = 0; $tamanho = 0;
    if (!is_dir($dir)) return [0, 0, 0];
    $items = @scandir($dir);
    if (!$items) return [0, 0, 0];
    foreach ($items as $item) {
        if (in_array($item, $ignorar)) continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            $pastas++;
            [$sp, $sa, $st] = contarConteudo($path, $ignorar);
            $pastas += $sp;
            $arquivos += $sa;
            $tamanho += $st;
        } else {
            $arquivos++;
            $tamanho += filesize($path);
        }
    }
    return [$pastas, $arquivos, $tamanho];
}

function listarArvore($dir, $ignorar, $extMap, $nivel = 0, $prefixo = '') {
    $items = @scandir($dir);
    if (!$items) return [];
    $resultado = [];

    // Separar pastas e arquivos
    $pastas = []; $arquivos = [];
    foreach ($items as $item) {
        if (in_array($item, $ignorar)) continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) $pastas[] = $item;
        else $arquivos[] = $item;
    }

    sort($pastas);
    sort($arquivos);

    // Pastas primeiro
    foreach ($pastas as $pasta) {
        $path = $dir . DIRECTORY_SEPARATOR . $pasta;
        [$sp, $sa, $st] = contarConteudo($path, $ignorar);
        $resultado[] = [
            'tipo'     => 'pasta',
            'nome'     => $pasta,
            'path'     => $path,
            'nivel'    => $nivel,
            'arquivos' => $sa,
            'pastas'   => $sp,
            'tamanho'  => $st,
            'permissao'=> substr(sprintf('%o', fileperms($path)), -4),
            'modificado'=> filemtime($path),
        ];
        $resultado = array_merge($resultado, listarArvore($path, $ignorar, $extMap, $nivel + 1));
    }

    // Depois arquivos
    foreach ($arquivos as $arquivo) {
        $path = $dir . DIRECTORY_SEPARATOR . $arquivo;
        $ext = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION));
        $info = $extMap[$ext] ?? ['📄', '#6B7280', 'Arquivo'];
        $resultado[] = [
            'tipo'      => 'arquivo',
            'nome'      => $arquivo,
            'path'      => $path,
            'nivel'     => $nivel,
            'tamanho'   => @filesize($path) ?: 0,
            'extensao'  => $ext,
            'icone'     => $info[0],
            'cor'       => $info[1],
            'tipoLabel' => $info[2],
            'permissao' => substr(sprintf('%o', @fileperms($path)), -4),
            'modificado'=> @filemtime($path) ?: 0,
            'linhas'    => in_array($ext, ['php','html','htm','css','js','json','sql','md','txt','xml','env','htaccess','log'])
                            ? @count(file($path)) : null,
        ];
    }

    return $resultado;
}

/* ── Coletar dados ─────────────────────────────────────── */
$arvore = listarArvore($raizProjeto, $ignorar, $extMap);
[$totalPastas, $totalArquivos, $totalTamanho] = contarConteudo($raizProjeto, $ignorar);

/* Contagem por tipo de arquivo */
$contagemExt = [];
foreach ($arvore as $item) {
    if ($item['tipo'] === 'arquivo') {
        $ext = $item['extensao'] ?: 'outro';
        if (!isset($contagemExt[$ext])) $contagemExt[$ext] = ['qtd' => 0, 'tamanho' => 0];
        $contagemExt[$ext]['qtd']++;
        $contagemExt[$ext]['tamanho'] += $item['tamanho'];
    }
}
arsort($contagemExt);

/* Pastas críticas para verificação */
$pastasCriticas = [
    'uploads'          => $raizProjeto . '/uploads',
    'uploads/vagas'    => $raizProjeto . '/uploads/vagas',
    'uploads/curriculos'=> $raizProjeto . '/uploads/curriculos',
    'backend'          => $raizProjeto . '/backend',
    'public'           => $raizProjeto . '/public',
    'empresa'          => $raizProjeto . '/empresa',
    'candidato'        => $raizProjeto . '/candidato',
    'adm'              => $raizProjeto . '/adm',
    'assets'           => $raizProjeto . '/assets',
];

/* Verificação de banco de dados */
$dbStatus = 'desconhecido';
$dbInfo = '';
$dbTabelas = [];
try {
    if (file_exists($raizProjeto . '/backend/conexao.php')) {
        @include_once $raizProjeto . '/backend/conexao.php';
        if (isset($pdo)) {
            $dbStatus = 'conectado';
            $dbInfo = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            $stmt = $pdo->query("SHOW TABLES");
            $dbTabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    }
} catch (Exception $e) {
    $dbStatus = 'erro';
    $dbInfo = $e->getMessage();
}

/* Verificação de extensões PHP */
$extensoesNecessarias = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'fileinfo', 'gd', 'curl', 'openssl'];
$extensoesStatus = [];
foreach ($extensoesNecessarias as $ext) {
    $extensoesStatus[$ext] = extension_loaded($ext);
}

$tempoExecucao = round((microtime(true) - $startTime) * 1000, 2);
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico — TGA Carreiras</title>
    <link rel="icon" href="http://tgameajuda.com/Projetos/tgacarreiras/img/icone_logo.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ── Variáveis ──────────────────────────────────────── */
        :root {
            --primary: #0066FF;
            --primary-dark: #0052CC;
            --primary-light: #3385FF;
            --bg: #0B0F1A;
            --bg-card: #111827;
            --bg-card-alt: #1a2236;
            --surface: #1F2937;
            --surface-2: #283347;
            --text: #F1F5F9;
            --text-sec: #94A3B8;
            --text-muted: #64748B;
            --border: #1E293B;
            --border-light: #334155;
            --success: #10B981;
            --success-bg: rgba(16,185,129,.1);
            --warning: #F59E0B;
            --warning-bg: rgba(245,158,11,.1);
            --danger: #EF4444;
            --danger-bg: rgba(239,68,68,.1);
            --info: #3B82F6;
            --info-bg: rgba(59,130,246,.1);
            --purple: #8B5CF6;
            --purple-bg: rgba(139,92,246,.1);
            --shadow: 0 4px 24px rgba(0,0,0,.4);
            --shadow-lg: 0 12px 40px rgba(0,0,0,.5);
            --radius: 14px;
            --radius-sm: 8px;
            --mono: 'JetBrains Mono', 'Fira Code', monospace;
            --sans: 'Inter', -apple-system, sans-serif;
        }

        [data-theme="light"] {
            --bg: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-card-alt: #F8FAFC;
            --surface: #E2E8F0;
            --surface-2: #EDF0F5;
            --text: #0F172A;
            --text-sec: #475569;
            --text-muted: #64748B;
            --border: #E2E8F0;
            --border-light: #CBD5E1;
            --shadow: 0 4px 24px rgba(0,0,0,.06);
            --shadow-lg: 0 12px 40px rgba(0,0,0,.1);
        }

        /* ── Reset ──────────────────────────────────────────── */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--sans);
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* ── Header ─────────────────────────────────────────── */
        .top-header {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            position: sticky; top: 0; z-index: 100;
            backdrop-filter: blur(12px);
        }

        .top-header-inner {
            max-width: 1500px; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 1rem;
        }

        .top-header-left {
            display: flex; align-items: center; gap: 1rem;
        }

        .logo-badge {
            width: 42px; height: 42px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
        }

        .top-title { font-size: 1.3rem; font-weight: 800; color: var(--text); }
        .top-subtitle { font-size: .75rem; color: var(--text-muted); font-weight: 500; }

        .top-header-right {
            display: flex; align-items: center; gap: .75rem; flex-wrap: wrap;
        }

        .header-chip {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .35rem .85rem; border-radius: 20px;
            font-size: .72rem; font-weight: 600;
            border: 1px solid var(--border-light);
            background: var(--surface);
            color: var(--text-sec);
        }

        .header-chip span { font-weight: 700; }

        .theme-btn {
            width: 36px; height: 36px;
            border: 1px solid var(--border-light); border-radius: 8px;
            background: var(--surface); cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; transition: all .2s;
        }
        .theme-btn:hover { background: var(--primary); border-color: var(--primary); }

        /* ── Container ──────────────────────────────────────── */
        .container {
            max-width: 1500px; margin: 0 auto;
            padding: 2rem;
        }

        /* ── Stats Grid ─────────────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem; margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.25rem;
            display: flex; align-items: center; gap: 1rem;
            transition: all .25s;
        }

        .stat-card:hover { border-color: var(--primary); transform: translateY(-2px); box-shadow: var(--shadow); }

        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; flex-shrink: 0;
        }

        .stat-icon.blue   { background: var(--info-bg);    border: 1px solid rgba(59,130,246,.2); }
        .stat-icon.green  { background: var(--success-bg);  border: 1px solid rgba(16,185,129,.2); }
        .stat-icon.purple { background: var(--purple-bg);   border: 1px solid rgba(139,92,246,.2); }
        .stat-icon.orange { background: var(--warning-bg);  border: 1px solid rgba(245,158,11,.2); }
        .stat-icon.red    { background: var(--danger-bg);   border: 1px solid rgba(239,68,68,.2); }

        .stat-info { flex: 1; }
        .stat-value { font-size: 1.6rem; font-weight: 800; color: var(--text); line-height: 1.2; }
        .stat-label { font-size: .72rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }

        /* ── Section ────────────────────────────────────────── */
        .section {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .section-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1rem 1.25rem;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            cursor: pointer; user-select: none;
            transition: background .2s;
        }

        .section-header:hover { background: var(--surface-2); }

        .section-header-left {
            display: flex; align-items: center; gap: .65rem;
        }

        .section-header-icon {
            width: 32px; height: 32px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: .95rem;
        }

        .section-title {
            font-size: .95rem; font-weight: 700; color: var(--text);
        }

        .section-badge {
            font-size: .68rem; font-weight: 700; padding: .2rem .6rem;
            border-radius: 12px; color: #fff;
        }

        .section-arrow {
            font-size: .9rem; color: var(--text-muted);
            transition: transform .3s;
        }

        .section.collapsed .section-arrow { transform: rotate(-90deg); }
        .section.collapsed .section-body { display: none; }

        .section-body { padding: 0; }

        /* ── Árvore de arquivos ─────────────────────────────── */
        .tree-container {
            max-height: 70vh; overflow-y: auto; overflow-x: auto;
        }

        .tree-container::-webkit-scrollbar { width: 6px; height: 6px; }
        .tree-container::-webkit-scrollbar-track { background: var(--bg-card); }
        .tree-container::-webkit-scrollbar-thumb { background: var(--border-light); border-radius: 3px; }

        .tree-item {
            display: flex; align-items: center; gap: .5rem;
            padding: .45rem 1rem;
            border-bottom: 1px solid var(--border);
            font-family: var(--mono);
            font-size: .78rem;
            transition: background .15s;
            white-space: nowrap;
        }

        .tree-item:hover { background: var(--surface); }
        .tree-item:last-child { border-bottom: none; }

        .tree-indent {
            display: inline-flex;
            color: var(--border-light);
            user-select: none;
        }

        .tree-indent-pipe {
            display: inline-block;
            width: 20px; text-align: center;
            color: var(--border-light);
            font-size: .7rem;
        }

        .tree-icon {
            width: 26px; height: 26px; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            font-size: .8rem; flex-shrink: 0;
        }

        .tree-icon.folder {
            background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.2);
        }

        .tree-name { font-weight: 600; color: var(--text); flex-shrink: 0; }
        .tree-name.folder-name { color: var(--warning); font-weight: 700; cursor: pointer; }
        .tree-name.folder-name:hover { text-decoration: underline; }

        .tree-meta {
            display: flex; align-items: center; gap: .75rem;
            margin-left: auto; padding-left: 1.5rem;
            flex-shrink: 0;
        }

        .tree-meta-item {
            display: inline-flex; align-items: center; gap: .3rem;
            font-size: .68rem; color: var(--text-muted); font-weight: 500;
        }

        .tree-meta-item.size { color: var(--info); min-width: 65px; text-align: right; }
        .tree-meta-item.date { color: var(--text-muted); min-width: 100px; }
        .tree-meta-item.perm { color: var(--purple); font-family: var(--mono); min-width: 45px; }
        .tree-meta-item.lines { color: var(--success); min-width: 55px; }

        .file-type-tag {
            display: inline-flex; align-items: center; gap: .25rem;
            padding: .1rem .45rem; border-radius: 4px;
            font-size: .62rem; font-weight: 700;
            color: #fff; min-width: 50px; justify-content: center;
        }

        .folder-summary {
            font-size: .65rem; color: var(--text-muted); font-weight: 500;
            padding: .1rem .5rem; border-radius: 10px;
            background: var(--surface); border: 1px solid var(--border);
        }

        /* ── Tabela de checagem ─────────────────────────────── */
        .check-grid {
            display: grid; gap: 0;
        }

        .check-row {
            display: flex; align-items: center; gap: .75rem;
            padding: .65rem 1.25rem;
            border-bottom: 1px solid var(--border);
            transition: background .15s;
        }

        .check-row:hover { background: var(--surface); }
        .check-row:last-child { border-bottom: none; }

        .check-status {
            width: 28px; height: 28px; border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            font-size: .85rem; flex-shrink: 0;
        }

        .check-status.ok     { background: var(--success-bg); }
        .check-status.warn   { background: var(--warning-bg); }
        .check-status.fail   { background: var(--danger-bg); }

        .check-label {
            font-family: var(--mono); font-size: .8rem;
            font-weight: 600; color: var(--text); flex: 1;
        }

        .check-value {
            font-size: .75rem; font-weight: 600;
            color: var(--text-sec); text-align: right;
        }

        .check-value.ok   { color: var(--success); }
        .check-value.warn { color: var(--warning); }
        .check-value.fail { color: var(--danger); }

        /* ── Distribuição de arquivos ───────────────────────── */
        .dist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: .5rem; padding: 1rem 1.25rem;
        }

        .dist-item {
            display: flex; align-items: center; gap: .6rem;
            padding: .6rem .8rem; border-radius: var(--radius-sm);
            background: var(--surface);
            border: 1px solid var(--border);
            transition: all .2s;
        }

        .dist-item:hover { border-color: var(--primary); transform: translateY(-1px); }

        .dist-ext {
            font-family: var(--mono); font-size: .75rem;
            font-weight: 700; color: var(--text);
            min-width: 42px;
        }

        .dist-bar-wrap { flex: 1; height: 6px; background: var(--border); border-radius: 3px; overflow: hidden; }
        .dist-bar { height: 100%; border-radius: 3px; transition: width .5s ease; }

        .dist-count { font-size: .7rem; font-weight: 700; color: var(--text-sec); min-width: 50px; text-align: right; }

        /* ── Busca ──────────────────────────────────────────── */
        .tree-search {
            display: flex; align-items: center; gap: .5rem;
            padding: .75rem 1.25rem;
            background: var(--bg-card-alt);
            border-bottom: 1px solid var(--border);
        }

        .tree-search-input {
            flex: 1; border: 1px solid var(--border-light);
            background: var(--surface); color: var(--text);
            padding: .5rem .85rem; border-radius: var(--radius-sm);
            font-family: var(--mono); font-size: .8rem;
            outline: none; transition: all .2s;
        }

        .tree-search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,102,255,.15);
        }

        .tree-search-input::placeholder { color: var(--text-muted); }

        .tree-search-count {
            font-size: .72rem; font-weight: 600;
            color: var(--text-muted); white-space: nowrap;
        }

        .tree-actions {
            display: flex; gap: .4rem;
        }

        .tree-action-btn {
            padding: .4rem .7rem; border-radius: 6px;
            border: 1px solid var(--border-light);
            background: var(--surface); color: var(--text-sec);
            font-size: .72rem; font-weight: 600;
            cursor: pointer; transition: all .2s;
            font-family: var(--sans);
        }

        .tree-action-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }

        /* ── Footer ─────────────────────────────────────────── */
        .diag-footer {
            text-align: center; padding: 2rem;
            color: var(--text-muted); font-size: .78rem;
        }

        /* ── Animações ──────────────────────────────────────── */
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

        .stat-card { animation: fadeIn .35s ease backwards; }
        .stat-card:nth-child(1) { animation-delay: 0s; }
        .stat-card:nth-child(2) { animation-delay: .05s; }
        .stat-card:nth-child(3) { animation-delay: .1s; }
        .stat-card:nth-child(4) { animation-delay: .15s; }
        .stat-card:nth-child(5) { animation-delay: .2s; }
        .stat-card:nth-child(6) { animation-delay: .25s; }

        .tree-item.highlight { background: rgba(0,102,255,.12) !important; }

        /* ── Responsivo ─────────────────────────────────────── */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .top-header { padding: .75rem 1rem; }
            .top-header-inner { gap: .5rem; }
            .top-title { font-size: 1.1rem; }
            .header-chip { font-size: .65rem; padding: .25rem .6rem; }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .6rem; }
            .stat-card { padding: .85rem; }
            .stat-icon { width: 40px; height: 40px; font-size: 1.1rem; }
            .stat-value { font-size: 1.3rem; }
            .tree-item { padding: .4rem .75rem; font-size: .72rem; }
            .tree-meta { display: none; }
            .dist-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
            .tree-search { flex-wrap: wrap; }
            .tree-actions { width: 100%; justify-content: flex-end; }
        }

        @media (max-width: 480px) {
            .container { padding: .75rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .header-chip { display: none; }
        }
    </style>
</head>
<body>

<!-- ===== HEADER ===== -->
<div class="top-header">
    <div class="top-header-inner">
        <div class="top-header-left">
            <div class="logo-badge">🔧</div>
            <div>
                <div class="top-title">Diagnóstico do Sistema</div>
                <div class="top-subtitle">TGA Carreiras — Gerenciador de Arquivos</div>
            </div>
        </div>
        <div class="top-header-right">
            <div class="header-chip">📂 <span><?= $totalPastas ?></span> pastas</div>
            <div class="header-chip">📄 <span><?= $totalArquivos ?></span> arquivos</div>
            <div class="header-chip">💾 <span><?= tamanhoHumano($totalTamanho) ?></span></div>
            <div class="header-chip">⚡ <span><?= $tempoExecucao ?>ms</span></div>
            <button class="theme-btn" onclick="toggleTheme()" title="Alternar tema">
                <span id="themeIcon">🌙</span>
            </button>
        </div>
    </div>
</div>

<!-- ===== CONTEÚDO ===== -->
<div class="container">

    <!-- ═══ ESTATÍSTICAS ════════════════════════════════ -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">📂</div>
            <div class="stat-info">
                <div class="stat-value"><?= $totalPastas ?></div>
                <div class="stat-label">Pastas</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">📄</div>
            <div class="stat-info">
                <div class="stat-value"><?= $totalArquivos ?></div>
                <div class="stat-label">Arquivos</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">💾</div>
            <div class="stat-info">
                <div class="stat-value"><?= tamanhoHumano($totalTamanho) ?></div>
                <div class="stat-label">Tamanho total</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange">🐘</div>
            <div class="stat-info">
                <div class="stat-value"><?= phpversion() ?></div>
                <div class="stat-label">PHP Version</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon <?= $dbStatus === 'conectado' ? 'green' : 'red' ?>">🗄️</div>
            <div class="stat-info">
                <div class="stat-value"><?= $dbStatus === 'conectado' ? 'MySQL ' . $dbInfo : ucfirst($dbStatus) ?></div>
                <div class="stat-label">Banco de dados</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue">🌐</div>
            <div class="stat-info">
                <div class="stat-value"><?= $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' ?></div>
                <div class="stat-label">Servidor</div>
            </div>
        </div>
    </div>

    <!-- ═══ ÁRVORE DE ARQUIVOS ══════════════════════════ -->
    <div class="section" id="secaoArvore">
        <div class="section-header" onclick="toggleSection('secaoArvore')">
            <div class="section-header-left">
                <div class="section-header-icon" style="background:var(--warning-bg);border:1px solid rgba(245,158,11,.2)">📁</div>
                <span class="section-title">Árvore de Arquivos</span>
                <span class="section-badge" style="background:var(--primary)"><?= $totalPastas + $totalArquivos ?> itens</span>
            </div>
            <span class="section-arrow">▼</span>
        </div>

        <!-- Busca + Ações -->
        <div class="tree-search">
            <input type="text" class="tree-search-input" id="treeBusca"
                   placeholder="🔍 Buscar arquivo ou pasta…" oninput="filtrarArvore()">
            <span class="tree-search-count" id="treeCount"><?= $totalPastas + $totalArquivos ?> itens</span>
            <div class="tree-actions">
                <button class="tree-action-btn" onclick="expandirTudo()">📂 Expandir</button>
                <button class="tree-action-btn" onclick="recolherTudo()">📁 Recolher</button>
            </div>
        </div>

        <div class="section-body">
            <div class="tree-container" id="treeContainer">
                <!-- Raiz -->
                <div class="tree-item" style="background:var(--surface)">
                    <div class="tree-icon folder">📁</div>
                    <span class="tree-name folder-name" style="font-size:.85rem">/<?= htmlspecialchars($nomeProjeto) ?></span>
                    <span class="folder-summary"><?= $totalPastas ?> pastas · <?= $totalArquivos ?> arquivos · <?= tamanhoHumano($totalTamanho) ?></span>
                </div>

                <?php foreach ($arvore as $item): ?>
                <div class="tree-item"
                     data-nivel="<?= $item['nivel'] ?>"
                     data-tipo="<?= $item['tipo'] ?>"
                     data-nome="<?= htmlspecialchars(strtolower($item['nome'])) ?>"
                     data-visivel="1">

                    <!-- Indentação -->
                    <div class="tree-indent">
                        <?php for ($i = 0; $i < $item['nivel']; $i++): ?>
                        <span class="tree-indent-pipe">│</span>
                        <?php endfor; ?>
                        <span class="tree-indent-pipe"><?= $item['tipo'] === 'pasta' ? '├─' : '├─' ?></span>
                    </div>

                    <?php if ($item['tipo'] === 'pasta'): ?>
                        <div class="tree-icon folder">📁</div>
                        <span class="tree-name folder-name"><?= htmlspecialchars($item['nome']) ?></span>
                        <span class="folder-summary"><?= $item['arquivos'] ?> arq · <?= tamanhoHumano($item['tamanho']) ?></span>
                        <div class="tree-meta">
                            <span class="tree-meta-item perm"><?= $item['permissao'] ?></span>
                            <span class="tree-meta-item date"><?= date('d/m/Y H:i', $item['modificado']) ?></span>
                        </div>

                    <?php else: ?>
                        <div class="tree-icon" style="background:<?= $item['cor'] ?>20; border:1px solid <?= $item['cor'] ?>35">
                            <?= $item['icone'] ?>
                        </div>
                        <span class="tree-name"><?= htmlspecialchars($item['nome']) ?></span>
                        <span class="file-type-tag" style="background:<?= $item['cor'] ?>"><?= strtoupper($item['extensao'] ?: '?') ?></span>

                        <div class="tree-meta">
                            <?php if ($item['linhas'] !== null): ?>
                            <span class="tree-meta-item lines">📝 <?= number_format($item['linhas']) ?> linhas</span>
                            <?php endif; ?>
                            <span class="tree-meta-item size"><?= tamanhoHumano($item['tamanho']) ?></span>
                            <span class="tree-meta-item perm"><?= $item['permissao'] ?></span>
                            <span class="tree-meta-item date"><?= date('d/m/Y H:i', $item['modificado']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ═══ DISTRIBUIÇÃO POR TIPO ═══════════════════════ -->
    <div class="section" id="secaoDist">
        <div class="section-header" onclick="toggleSection('secaoDist')">
            <div class="section-header-left">
                <div class="section-header-icon" style="background:var(--purple-bg);border:1px solid rgba(139,92,246,.2)">📊</div>
                <span class="section-title">Distribuição por Tipo de Arquivo</span>
                <span class="section-badge" style="background:var(--purple)"><?= count($contagemExt) ?> tipos</span>
            </div>
            <span class="section-arrow">▼</span>
        </div>
        <div class="section-body">
            <div class="dist-grid">
                <?php
                $maxQtd = max(array_column($contagemExt, 'qtd'));
                foreach ($contagemExt as $ext => $dados):
                    $info = $extMap[$ext] ?? ['📄', '#6B7280', 'Arquivo'];
                    $pct = ($dados['qtd'] / $maxQtd) * 100;
                ?>
                <div class="dist-item">
                    <span style="font-size:.9rem"><?= $info[0] ?></span>
                    <span class="dist-ext">.<?= $ext ?></span>
                    <div class="dist-bar-wrap">
                        <div class="dist-bar" style="width:<?= $pct ?>%; background:<?= $info[1] ?>"></div>
                    </div>
                    <span class="dist-count"><?= $dados['qtd'] ?> (<?= tamanhoHumano($dados['tamanho']) ?>)</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ═══ PASTAS CRÍTICAS ═════════════════════════════ -->
    <div class="section" id="secaoPastas">
        <div class="section-header" onclick="toggleSection('secaoPastas')">
            <div class="section-header-left">
                <div class="section-header-icon" style="background:var(--success-bg);border:1px solid rgba(16,185,129,.2)">🛡️</div>
                <span class="section-title">Pastas Críticas do Projeto</span>
            </div>
            <span class="section-arrow">▼</span>
        </div>
        <div class="section-body">
            <div class="check-grid">
                <?php foreach ($pastasCriticas as $label => $path): ?>
                <?php
                    $existe = is_dir($path);
                    $gravavel = $existe ? is_writable($path) : false;
                    $perm = $existe ? substr(sprintf('%o', fileperms($path)), -4) : '----';
                ?>
                <div class="check-row">
                    <div class="check-status <?= $existe ? 'ok' : 'fail' ?>">
                        <?= $existe ? '✅' : '❌' ?>
                    </div>
                    <span class="check-label">/<?= htmlspecialchars($label) ?>/</span>
                    <span class="check-value <?= $existe ? 'ok' : 'fail' ?>">
                        <?= $existe ? 'Existe' : 'Não encontrada' ?>
                    </span>
                    <span class="check-value <?= $gravavel ? 'ok' : 'warn' ?>">
                        <?= $existe ? ($gravavel ? '✍️ Gravável' : '🔒 Somente leitura') : '' ?>
                    </span>
                    <span class="check-value" style="font-family:var(--mono); font-size:.72rem; color:var(--purple)">
                        <?= $perm ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ═══ EXTENSÕES PHP ═══════════════════════════════ -->
    <div class="section" id="secaoPhp">
        <div class="section-header" onclick="toggleSection('secaoPhp')">
            <div class="section-header-left">
                <div class="section-header-icon" style="background:rgba(136,146,191,.12);border:1px solid rgba(136,146,191,.2)">🐘</div>
                <span class="section-title">Extensões PHP Necessárias</span>
                <span class="section-badge" style="background:<?= in_array(false, $extensoesStatus) ? 'var(--danger)' : 'var(--success)' ?>">
                    <?= count(array_filter($extensoesStatus)) ?>/<?= count($extensoesStatus) ?> ativas
                </span>
            </div>
            <span class="section-arrow">▼</span>
        </div>
        <div class="section-body">
            <div class="check-grid">
                <?php foreach ($extensoesStatus as $ext => $ativo): ?>
                <div class="check-row">
                    <div class="check-status <?= $ativo ? 'ok' : 'fail' ?>">
                        <?= $ativo ? '✅' : '❌' ?>
                    </div>
                    <span class="check-label">ext-<?= $ext ?></span>
                    <span class="check-value <?= $ativo ? 'ok' : 'fail' ?>">
                        <?= $ativo ? 'Instalada' : 'Não encontrada' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ═══ TABELAS DO BANCO ════════════════════════════ -->
    <?php if ($dbStatus === 'conectado' && !empty($dbTabelas)): ?>
    <div class="section" id="secaoDB">
        <div class="section-header" onclick="toggleSection('secaoDB')">
            <div class="section-header-left">
                <div class="section-header-icon" style="background:var(--info-bg);border:1px solid rgba(59,130,246,.2)">🗄️</div>
                <span class="section-title">Tabelas do Banco de Dados</span>
                <span class="section-badge" style="background:var(--info)"><?= count($dbTabelas) ?> tabelas</span>
            </div>
            <span class="section-arrow">▼</span>
        </div>
        <div class="section-body">
            <div class="check-grid">
                <?php foreach ($dbTabelas as $tabela): ?>
                <?php
                    $stmtCount = $pdo->query("SELECT COUNT(*) FROM `$tabela`");
                    $qtdRegistros = $stmtCount ? $stmtCount->fetchColumn() : '?';
                ?>
                <div class="check-row">
                    <div class="check-status ok">🗃️</div>
                    <span class="check-label"><?= htmlspecialchars($tabela) ?></span>
                    <span class="check-value" style="color:var(--info); font-weight:700">
                        <?= number_format((int)$qtdRegistros) ?> registros
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══ INFO DO SERVIDOR ════════════════════════════ -->
    <div class="section collapsed" id="secaoServidor">
        <div class="section-header" onclick="toggleSection('secaoServidor')">
            <div class="section-header-left">
                <div class="section-header-icon" style="background:var(--info-bg);border:1px solid rgba(59,130,246,.2)">🖥️</div>
                <span class="section-title">Informações do Servidor</span>
            </div>
            <span class="section-arrow">▼</span>
        </div>
        <div class="section-body">
            <div class="check-grid">
                <?php
                $serverInfo = [
                    ['🌐', 'Servidor Web',     $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'],
                    ['🐘', 'PHP Version',      phpversion()],
                    ['💻', 'Sistema Operacional', PHP_OS . ' (' . php_uname('r') . ')'],
                    ['📂', 'Document Root',    $_SERVER['DOCUMENT_ROOT'] ?? 'N/A'],
                    ['📁', 'Raiz do Projeto',  $raizProjeto],
                    ['💾', 'Memory Limit',     ini_get('memory_limit')],
                    ['📤', 'Upload Max',       ini_get('upload_max_filesize')],
                    ['📨', 'Post Max Size',    ini_get('post_max_size')],
                    ['⏱️', 'Max Execution',    ini_get('max_execution_time') . 's'],
                    ['📝', 'Error Reporting',  ini_get('display_errors') ? 'Ativado' : 'Desativado'],
                    ['🔑', 'Session Path',     session_save_path() ?: 'Padrão'],
                    ['🕐', 'Timezone',         date_default_timezone_get()],
                    ['🌍', 'Server IP',        $_SERVER['SERVER_ADDR'] ?? 'N/A'],
                    ['📅', 'Data/Hora',        date('d/m/Y H:i:s')],
                ];
                foreach ($serverInfo as [$icon, $label, $value]):
                ?>
                <div class="check-row">
                    <div class="check-status ok"><?= $icon ?></div>
                    <span class="check-label"><?= $label ?></span>
                    <span class="check-value" style="color:var(--text-sec)"><?= htmlspecialchars($value) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div><!-- /container -->

<!-- ===== FOOTER ===== -->
<div class="diag-footer">
    🔧 TGA Carreiras — Diagnóstico gerado em <?= date('d/m/Y H:i:s') ?> · <?= $tempoExecucao ?>ms · PHP <?= phpversion() ?>
</div>

<script>
"use strict";

/* ── Toggle de seções ────────────────────────────────────── */
function toggleSection(id) {
    document.getElementById(id)?.classList.toggle('collapsed');
}

/* ── Tema ────────────────────────────────────────────────── */
function toggleTheme() {
    const html = document.documentElement;
    const icon = document.getElementById('themeIcon');
    const next = (html.getAttribute('data-theme') || 'dark') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    icon.textContent = next === 'light' ? '☀️' : '🌙';
    localStorage.setItem('diag_theme', next);
}

/* ── Busca na árvore ─────────────────────────────────────── */
function filtrarArvore() {
    const termo = document.getElementById('treeBusca').value.toLowerCase().trim();
    const items = document.querySelectorAll('#treeContainer .tree-item[data-nome]');
    let visivel = 0;

    items.forEach(el => {
        const nome = el.getAttribute('data-nome');
        const match = !termo || nome.includes(termo);
        el.style.display = match ? '' : 'none';
        el.classList.toggle('highlight', match && termo.length > 0);
        if (match) visivel++;
    });

    document.getElementById('treeCount').textContent = visivel + ' itens';
}

/* ── Expandir / Recolher tudo ────────────────────────────── */
function expandirTudo() {
    document.querySelectorAll('#treeContainer .tree-item[data-nome]').forEach(el => {
        el.style.display = '';
    });
    document.getElementById('treeBusca').value = '';
    document.getElementById('treeCount').textContent = document.querySelectorAll('#treeContainer .tree-item[data-nome]').length + ' itens';
}

function recolherTudo() {
    document.querySelectorAll('#treeContainer .tree-item[data-nome]').forEach(el => {
        const nivel = parseInt(el.getAttribute('data-nivel'));
        el.style.display = nivel > 0 ? 'none' : '';
    });
    document.getElementById('treeBusca').value = '';
    let count = document.querySelectorAll('#treeContainer .tree-item[data-nivel="0"]').length;
    document.getElementById('treeCount').textContent = count + ' itens (nível raiz)';
}

/* ── Init ────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    const saved = localStorage.getItem('diag_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
    document.getElementById('themeIcon').textContent = saved === 'light' ? '☀️' : '🌙';
});
</script>

</body>
</html>