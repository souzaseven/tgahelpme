<?php
/* ============================================================
   vaga.php — TGA Carreiras (área pública)
   ─ Imagem compacta (thumbnail) com lightbox ao clicar
   ─ Todos os campos: empresa, qtd vagas, disponibilidade,
     escolaridade, experiência, localização, salário, data
   ─ Responsividade completa (mobile-first)
   ─ CSRF, validação MIME, anti-spam, anti-duplicidade
============================================================ */
session_start();
require_once __DIR__ . '/../backend/conexao.php';

/* ── CSRF Token ─────────────────────────────────────────── */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ── PROCESSAR CANDIDATURA (POST) ───────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vaga_id'])) {

    if (empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('Requisição inválida. Recarregue a página e tente novamente.');
    }

    //  Termos obrigatórios (backend)
    if (empty($_POST['termos'])) {
        $erro = "Você precisa aceitar os Termos de Uso para enviar a candidatura.";
    }

    $vaga_id  = (int)  $_POST['vaga_id'];
    $nome     = trim($_POST['nome']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $mensagem = trim($_POST['mensagem'] ?? '');
    $erro     = null;
    $curriculoPath = null;

    if (empty($nome) || empty($email))
        $erro = "Preencha todos os campos obrigatórios.";

    if (!$erro && !filter_var($email, FILTER_VALIDATE_EMAIL))
        $erro = "Informe um e-mail válido.";

    if (!$erro) {
        $dup = $pdo->prepare("SELECT id FROM candidaturas WHERE vaga_id=? AND email=? LIMIT 1");
        $dup->execute([$vaga_id, $email]);
        if ($dup->fetch()) $erro = "Você já se candidatou a esta vaga com este e-mail.";
    }

    if (!$erro) {
        $_SESSION['candidaturas_enviadas'] ??= 0;
        if ($_SESSION['candidaturas_enviadas'] >= 5)
            $erro = "Muitas tentativas. Tente novamente mais tarde.";
    }

    if (!$erro) {
        $codigosErro = [
            UPLOAD_ERR_INI_SIZE   => 'O arquivo excede o limite do servidor.',
            UPLOAD_ERR_FORM_SIZE  => 'O arquivo excede o limite do formulário.',
            UPLOAD_ERR_PARTIAL    => 'Upload incompleto.',
            UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo selecionado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária ausente.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar no disco.',
        ];

        if (!isset($_FILES['curriculo']) || $_FILES['curriculo']['error'] !== UPLOAD_ERR_OK) {
            $codigo = $_FILES['curriculo']['error'] ?? UPLOAD_ERR_NO_FILE;
            $erro   = $codigosErro[$codigo] ?? 'Erro desconhecido no upload.';
        } else {
            $ext = strtolower(pathinfo($_FILES['curriculo']['name'], PATHINFO_EXTENSION));
            if ($ext !== 'pdf') $erro = "Apenas PDFs são permitidos.";

            if (!$erro && $_FILES['curriculo']['size'] > 5 * 1024 * 1024)
                $erro = "Arquivo maior que 5 MB.";

            if (!$erro) {
                $finfo    = new finfo(FILEINFO_MIME_TYPE);
                $mimeReal = $finfo->file($_FILES['curriculo']['tmp_name']);
                if ($mimeReal !== 'application/pdf')
                    $erro = "O arquivo não é um PDF válido (MIME: $mimeReal).";
            }

            if (!$erro) {
                $dir = __DIR__ . '/../uploads/curriculos/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $filename = uniqid('cv_', true) . '.pdf';
                if (move_uploaded_file($_FILES['curriculo']['tmp_name'], $dir . $filename))
                    $curriculoPath = 'uploads/curriculos/' . $filename;
                else
                    $erro = "Falha ao salvar o arquivo.";
            }
        }
    }

    if (!$erro) {
        try {
         // Pega o candidato_id da sessão (se estiver logado)
$candidatoIdSessao = isset($_SESSION['candidato_id']) ? (int)$_SESSION['candidato_id'] : null;

$pdo->prepare("
    INSERT INTO candidaturas
        (vaga_id, candidato_id, nome, email, telefone, mensagem, curriculo, status, data_candidatura)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente', NOW())
")->execute([$vaga_id, $candidatoIdSessao, $nome, $email, $telefone, $mensagem, $curriculoPath]);

            $_SESSION['candidaturas_enviadas']++;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header("Location: vaga.php?id=$vaga_id&success=1");
            exit;
        } catch (PDOException $e) {
            if ($curriculoPath && file_exists(__DIR__ . '/../' . $curriculoPath))
                unlink(__DIR__ . '/../' . $curriculoPath);
            $erro = "Erro ao salvar. Tente novamente.";
            error_log('[vaga.php] ' . $e->getMessage());
        }
    }
}

/* ── CARREGAR VAGA ──────────────────────────────────────── */
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id && isset($_POST['vaga_id'])) $id = (int) $_POST['vaga_id'];
if (!$id) { header("Location: index.php"); exit; }

$stmtVaga = $pdo->prepare("SELECT * FROM vagas WHERE id=? AND status='ativa'");
$stmtVaga->execute([$id]);
$vaga = $stmtVaga->fetch();
if (!$vaga) { header("Location: index.php"); exit; }

/* ── Contador de views ──────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $pdo->prepare("UPDATE vagas SET views = views + 1 WHERE id=?")->execute([$id]);
        $vaga['views'] = ($vaga['views'] ?? 0) + 1;
    } catch (PDOException $e) { /* coluna ainda não criada */ }
}

$sucesso         = isset($_GET['success']) && $_GET['success'] == 1;
$pageTitle       = htmlspecialchars($vaga['titulo']) . ' — TGA Carreiras';
$pageDescription = substr(strip_tags($vaga['descricao'] ?? ''), 0, 160);
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:title" content="<?= $pageTitle ?>">
    <title><?= $pageTitle ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>


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
        /* ── Variáveis ───────────────────────────────────── */
        :root {
            --primary:      #0066FF;
            --primary-dark: #0052CC;
            --primary-light:#3385FF;
            --bg:           #0F172A;
            --bg-card:      #1E293B;
            --surface:      #334155;
            --text:         #F1F5F9;
            --text-sec:     #CBD5E1;
            --text-muted:   #64748B;
            --border:       #334155;
            --success:      #10B981;
            --danger:       #EF4444;
            --warning:      #F59E0B;
            --shadow:       0 4px 12px rgba(0,0,0,.3);
            --shadow-xl:    0 20px 40px rgba(0,102,255,.25);
            --radius:       16px;
            --radius-sm:    10px;
            --transition:   all .3s ease;
        }
        [data-theme="light"] {
            --bg:        #F1F5F9;
            --bg-card:   #FFFFFF;
            --surface:   #EDF0F5;
            --text:      #1A202C;
            --text-sec:  #4A5568;
            --text-muted:#718096;
            --border:    #E2E8F0;
            --shadow:    0 4px 12px rgba(0,0,0,.07);
            --shadow-xl: 0 20px 40px rgba(0,102,255,.12);
        }

        /* ── Reset ──────────────────────────────────────── */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { overflow-x: hidden; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg); color: var(--text);
            line-height: 1.6; min-height: 100vh; transition: var(--transition);
        }

        /* ── Header ─────────────────────────────────────── */
        header {
            background: var(--bg-card); padding: 1rem 0;
            box-shadow: var(--shadow); position: sticky; top: 0; z-index: 200;
            border-bottom: 2px solid var(--primary);
        }
        .header-inner {
            max-width: 1000px; margin: 0 auto; padding: 0 1.5rem;
            display: flex; align-items: center; justify-content: space-between;
        }
        .logo-link {
            display: flex; align-items: center; gap: .75rem;
            text-decoration: none; color: var(--text);
        }
        .logo-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
            flex-shrink: 0;
        }
        .logo-name { font-size: 1.3rem; font-weight: 800; }
        .header-right { display: flex; align-items: center; gap: 1rem; }
        .back-link {
            color: var(--text-muted); text-decoration: none; font-size: .88rem;
            font-weight: 500; transition: var(--transition);
        }
        .back-link:hover { color: var(--primary); }
        .theme-toggle {
            width: 38px; height: 38px; border: 1px solid var(--border);
            border-radius: 8px; background: var(--surface); cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; transition: var(--transition);
        }
        .theme-toggle:hover { background: var(--primary); border-color: var(--primary); }

        /* ── Page ────────────────────────────────────────── */
        .page-wrap {
            max-width: 1000px; margin: 0 auto; padding: 2rem 1.5rem;
            display: flex; flex-direction: column; gap: 1.75rem;
        }

        /* ── Alertas ─────────────────────────────────────── */
        .alert {
            padding: 1rem 1.25rem; border-radius: var(--radius-sm);
            display: flex; align-items: flex-start; gap: .875rem;
        }
        .alert-success { background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.3); color: var(--success); }
        .alert-error   { background: rgba(239,68,68,.1);  border: 1px solid rgba(239,68,68,.3);  color: var(--danger); }
        .alert strong  { display: block; margin-bottom: .2rem; }
        .alert span    { font-size: .88rem; }

        /* ── Card base ───────────────────────────────────── */
        .card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden;
        }

        /* ════════════════════════════════════════════════════
           TOPO DA VAGA — Imagem thumbnail + Infos
        ════════════════════════════════════════════════════ */
        .vaga-top {
            padding: 1.75rem 2rem;
            display: flex; gap: 1.75rem;
            align-items: flex-start;
        }

        /* Thumbnail da imagem */
        .vaga-thumb-wrap {
            flex-shrink: 0;
            width: 140px; height: 140px;
            border-radius: 14px;
            border: 2px solid var(--border);
            overflow: hidden;
            cursor: pointer;
            position: relative;
            transition: var(--transition);
            background: var(--surface);
        }
        .vaga-thumb-wrap:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(0,102,255,.2);
        }
        .vaga-thumb-wrap img {
            width: 100%; height: 100%;
            object-fit: cover; display: block;
            transition: transform .4s ease;
        }
        .vaga-thumb-wrap:hover img { transform: scale(1.08); }

        .vaga-thumb-overlay {
            position: absolute; inset: 0;
            background: rgba(0,0,0,0);
            display: flex; align-items: center; justify-content: center;
            transition: var(--transition);
        }
        .vaga-thumb-wrap:hover .vaga-thumb-overlay { background: rgba(0,0,0,.35); }
        .vaga-thumb-icon {
            opacity: 0; color: #fff; font-size: 1.5rem;
            transition: var(--transition); transform: scale(.7);
        }
        .vaga-thumb-wrap:hover .vaga-thumb-icon { opacity: 1; transform: scale(1); }

        .vaga-thumb-ph {
            width: 100%; height: 100%;
            display: flex; align-items: center; justify-content: center;
            font-size: 3rem; color: var(--text-muted);
            background: linear-gradient(135deg, var(--surface), var(--border));
        }

        /* Infos ao lado da thumb */
        .vaga-header-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .6rem; }

        .vaga-status-row { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
        .badge-ativa {
            display: inline-flex; align-items: center; gap: .35rem;
            padding: .25rem .75rem; border-radius: 20px;
            background: rgba(16,185,129,.12); color: var(--success);
            font-size: .72rem; font-weight: 700;
        }
        .badge-ativa::before {
            content: ''; width: 7px; height: 7px;
            border-radius: 50%; background: var(--success);
            animation: pulse 2s infinite;
        }
        .badge-views {
            display: inline-flex; align-items: center; gap: .35rem;
            padding: .25rem .7rem; border-radius: 20px;
            background: var(--surface); color: var(--text-muted);
            font-size: .7rem; font-weight: 600; border: 1px solid var(--border);
        }
        .badge-nova-vaga {
            display: inline-flex; align-items: center; gap: .25rem;
            padding: .25rem .7rem; border-radius: 20px;
            background: rgba(245,158,11,.12); color: var(--warning);
            font-size: .7rem; font-weight: 700; border: 1px solid rgba(245,158,11,.25);
        }

        .vaga-titulo { font-size: 1.65rem; font-weight: 800; color: var(--text); line-height: 1.25; }

        .vaga-empresa-row {
            display: flex; align-items: center; gap: .5rem;
            font-size: .9rem; font-weight: 600; color: var(--primary);
        }

        .vaga-setor {
            display: inline-flex; align-items: center; gap: .35rem;
            font-size: .78rem; font-weight: 600; color: var(--text-muted);
        }

        /* ── Grid de meta infos ─────────────────────────── */
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: .6rem;
        }
        .meta-item {
            display: flex; align-items: center; gap: .7rem;
            padding: .55rem .8rem;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }
        .meta-item:hover { border-color: var(--primary); }
        .meta-item-icon {
            width: 32px; height: 32px;
            background: rgba(0,102,255,.1); border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: .85rem; flex-shrink: 0;
        }
        .meta-item-label { font-size: .62rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
        .meta-item-value { font-size: .84rem; font-weight: 600; color: var(--text); }

        /* ── Conteúdo: Descrição | Requisitos ─────────────── */
        .vaga-content { padding: 1.75rem 2rem; }
        .content-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
        .content-col  { padding-right: 1.5rem; }
        .content-col + .content-col {
            padding-right: 0; padding-left: 1.5rem;
            border-left: 1px solid var(--border);
        }
        .section-title {
            display: flex; align-items: center; gap: .6rem;
            font-size: 1rem; font-weight: 700; color: var(--text);
            margin-bottom: .8rem; padding-bottom: .65rem;
            border-bottom: 1px solid var(--border);
        }
        .section-title-icon {
            width: 28px; height: 28px;
            background: rgba(0,102,255,.1); border-radius: 8px;
            display: flex; align-items: center; justify-content: center; font-size: .85rem;
        }
        .vaga-text { font-size: .88rem; line-height: 1.8; color: var(--text-sec); white-space: pre-wrap; word-break: break-word; }

        /* ════════════════════════════════════════════════════
           ÁREA DE CANDIDATURA
        ════════════════════════════════════════════════════ */
        .candidatura-card { overflow: visible; }
        .candidatura-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 1.25rem 2rem;
        }
        .candidatura-header h2 {
            font-size: 1.25rem; font-weight: 800; color: #fff;
            display: flex; align-items: center; gap: .6rem;
        }
        .candidatura-header p { color: rgba(255,255,255,.85); font-size: .82rem; margin-top: .2rem; }
        .candidatura-body { padding: 1.5rem 2rem; }

        .candidatura-opcoes {
            display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;
        }
        .opcao-btn {
            display: flex; flex-direction: column; align-items: center;
            gap: .875rem; padding: 1.75rem 1.25rem;
            background: var(--surface); border: 2px solid var(--border);
            border-radius: var(--radius); cursor: pointer;
            transition: var(--transition); text-align: center;
            position: relative; overflow: hidden;
        }
        .opcao-btn::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            opacity: 0; transition: opacity .3s;
        }
        .opcao-btn:hover { border-color: var(--primary); transform: translateY(-3px); box-shadow: var(--shadow-xl); }
        .opcao-btn:hover::before { opacity: .06; }
        .opcao-btn > * { position: relative; z-index: 1; }

        .opcao-icon {
            width: 56px; height: 56px; border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.7rem;
        }
        .opcao-icon.blue  { background: rgba(0,102,255,.15); border: 2px solid rgba(0,102,255,.25); }
        .opcao-icon.green { background: rgba(16,185,129,.15); border: 2px solid rgba(16,185,129,.25); }
        .opcao-title { font-size: .98rem; font-weight: 700; color: var(--text); line-height: 1.3; }
        .opcao-desc  { font-size: .78rem; color: var(--text-muted); line-height: 1.5; }
        .opcao-cta {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .5rem 1.1rem; border-radius: 8px;
            font-size: .8rem; font-weight: 700;
            border: none; cursor: pointer; transition: var(--transition);
            font-family: inherit;
        }
        .opcao-cta.blue  { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: #fff; }
        .opcao-cta.green { background: linear-gradient(135deg, var(--success), #059669); color: #fff; }
        .opcao-cta:hover { transform: translateY(-1px); opacity: .9; }

        /* ════════════════════════════════════════════════════
           MODAIS
        ════════════════════════════════════════════════════ */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,.75); backdrop-filter: blur(6px);
            z-index: 1000;
            display: flex; align-items: flex-start; justify-content: center;
            padding: 1.5rem; overflow-y: auto;
            opacity: 0; pointer-events: none;
            transition: opacity .25s ease;
        }
        .modal-overlay.open { opacity: 1; pointer-events: auto; }
        .modal-box {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius); width: 100%; max-width: 720px;
            box-shadow: 0 24px 60px rgba(0,0,0,.6);
            transform: translateY(20px); transition: transform .3s ease;
            margin: auto;
        }
        .modal-overlay.open .modal-box { transform: translateY(0); }
        .modal-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1.15rem 1.5rem; border-bottom: 1px solid var(--border);
        }
        .modal-header h3 { font-size: 1.1rem; font-weight: 700; color: var(--text); }
        .modal-close {
            width: 34px; height: 34px; border-radius: 8px;
            background: var(--surface); border: 1px solid var(--border);
            color: var(--text-muted); cursor: pointer; font-size: 1rem;
            display: flex; align-items: center; justify-content: center;
            transition: var(--transition);
        }
        .modal-close:hover { background: var(--danger); border-color: var(--danger); color: #fff; }
        .modal-body   { padding: 1.5rem; }
        .modal-footer {
            padding: 1.15rem 1.5rem; border-top: 1px solid var(--border);
            display: flex; justify-content: flex-end; gap: .75rem; flex-wrap: wrap;
        }

        /* ── Formulário ─────────────────────────────────── */
        .form-grid-2  { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-grid-3  { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
        .form-group   { display: flex; flex-direction: column; gap: .35rem; }
        .form-label   { font-size: .78rem; font-weight: 700; color: var(--text-sec); }
        .form-label .req { color: var(--danger); }
        .form-label .opt { color: var(--text-muted); font-weight: 400; }
        .form-input, .form-textarea, .form-select {
            width: 100%; padding: .65rem .9rem;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius-sm); color: var(--text);
            font-family: inherit; font-size: .86rem; outline: none;
            transition: var(--transition);
        }
        .form-input::placeholder,
        .form-textarea::placeholder { color: var(--text-muted); }
        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,102,255,.12);
        }
        .form-textarea { resize: vertical; min-height: 80px; line-height: 1.6; }
        .form-select   { cursor: pointer; }
        .form-section { margin-bottom: 1.5rem; }
        .form-section:last-child { margin-bottom: 0; }
        .form-section-title {
            font-size: .72rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .6px; color: var(--primary);
            margin-bottom: .875rem; padding-bottom: .5rem;
            border-bottom: 1px solid var(--border);
        }

        /* ── Blocks dinâmicos ───────────────────────────── */
        .btn-add-item {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .45rem .9rem;
            background: rgba(0,102,255,.08); border: 1px dashed rgba(0,102,255,.3);
            border-radius: 8px; color: var(--primary);
            font-family: inherit; font-size: .8rem; font-weight: 600;
            cursor: pointer; transition: var(--transition); margin-top: .5rem;
        }
        .btn-add-item:hover { background: rgba(0,102,255,.15); }
        .item-block {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius-sm); padding: 1rem;
            margin-top: .75rem; position: relative;
        }
        .item-block + .item-block { margin-top: .5rem; }
        .btn-remove-item {
            position: absolute; top: .6rem; right: .6rem;
            width: 26px; height: 26px;
            background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.2);
            border-radius: 6px; color: var(--danger);
            cursor: pointer; font-size: .9rem;
            display: flex; align-items: center; justify-content: center;
            transition: var(--transition);
        }
        .btn-remove-item:hover { background: var(--danger); color: #fff; }

        /* ── Drop zone ──────────────────────────────────── */
        .drop-zone {
            border: 2px dashed var(--border); border-radius: var(--radius-sm);
            padding: 1.5rem 1rem; text-align: center; cursor: pointer;
            transition: var(--transition);
        }
        .drop-zone:hover { border-color: var(--primary); background: rgba(0,102,255,.04); }
        .drop-zone.has-file { border-color: var(--success); background: rgba(16,185,129,.04); }
        .drop-zone-icon  { font-size: 2rem; margin-bottom: .4rem; }
        .drop-zone-title { font-size: .88rem; font-weight: 600; color: var(--text-sec); }
        .drop-zone-title span { color: var(--primary); }
        .drop-zone-hint  { font-size: .73rem; color: var(--text-muted); margin-top: .2rem; }
        .file-preview {
            display: none; margin-top: .65rem; padding: .7rem 1rem;
            background: rgba(16,185,129,.08); border: 1px solid rgba(16,185,129,.2);
            border-radius: var(--radius-sm);
            align-items: center; justify-content: space-between;
        }
        .file-preview.show { display: flex; }
        .file-preview-left { display: flex; align-items: center; gap: .7rem; }
        .file-remove {
            background: transparent; border: none; cursor: pointer;
            color: var(--text-muted); font-size: 1rem; transition: var(--transition);
        }
        .file-remove:hover { color: var(--danger); }
        .form-check { display: flex; align-items: center; gap: .5rem; }
        .form-check input { width: 15px; height: 15px; cursor: pointer; accent-color: var(--primary); }
        .form-check label { font-size: .83rem; color: var(--text-sec); cursor: pointer; }
        .form-check a { color: var(--primary); text-decoration: none; }

        /* ── Steps ──────────────────────────────────────── */
        .steps-nav {
            display: flex; gap: 0; margin-bottom: 1.5rem;
            background: var(--surface); border-radius: var(--radius-sm);
            padding: .25rem; overflow-x: auto;
        }
        .step-btn {
            flex: 1; min-width: 70px; padding: .5rem .4rem;
            border: none; background: transparent; cursor: pointer;
            font-family: inherit; font-size: .7rem; font-weight: 600;
            color: var(--text-muted); border-radius: 8px; transition: var(--transition);
            text-align: center; white-space: nowrap;
        }
        .step-btn.active { background: var(--primary); color: #fff; }
        .step-btn.done   { color: var(--success); }
        .step-pane { display: none; }
        .step-pane.active { display: block; }
        .progress-bar-wrap {
            height: 4px; background: var(--surface); border-radius: 2px;
            margin-bottom: 1.25rem; overflow: hidden;
        }
        .progress-bar { height: 100%; background: var(--primary); border-radius: 2px; transition: width .4s ease; }

        /* ── Skills tags ────────────────────────────────── */
        .skills-tags { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .5rem; }
        .skill-tag {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .3rem .75rem; border-radius: 20px;
            background: rgba(0,102,255,.1); border: 1px solid rgba(0,102,255,.2);
            color: var(--primary); font-size: .78rem; font-weight: 600;
        }
        .skill-tag-remove {
            background: transparent; border: none; cursor: pointer;
            color: var(--primary); font-size: .85rem; padding: 0;
            line-height: 1; transition: var(--transition);
        }
        .skill-tag-remove:hover { color: var(--danger); }
        .skill-input-row { display: flex; gap: .5rem; margin-top: .5rem; }
        .skill-input-row .form-input { flex: 1; }
        .btn-add-skill {
            padding: .65rem 1rem;
            background: var(--primary); color: #fff;
            border: none; border-radius: var(--radius-sm);
            cursor: pointer; font-size: .85rem; transition: var(--transition);
            white-space: nowrap;
        }
        .btn-add-skill:hover { background: var(--primary-dark); }

        /* ── Preview do currículo ────────────────────────── */
        #curriculoPreview {
            font-family: 'Inter', sans-serif;
            color: #1a202c; background: #fff;
            padding: 2rem 2.5rem; font-size: .88rem; line-height: 1.7;
            border-radius: var(--radius-sm); border: 1px solid var(--border);
            margin-top: 1.25rem;
        }
        .cv-header { border-bottom: 3px solid #0066FF; padding-bottom: 1rem; margin-bottom: 1.25rem; }
        .cv-name   { font-size: 1.6rem; font-weight: 800; color: #1a202c; letter-spacing: -.5px; }
        .cv-contato { display: flex; flex-wrap: wrap; gap: .5rem 1.5rem; margin-top: .4rem; font-size: .82rem; color: #4a5568; }
        .cv-section { margin-bottom: 1.1rem; }
        .cv-section-title {
            font-size: .72rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: 1px; color: #0066FF;
            border-bottom: 1px solid #e2e8f0; padding-bottom: .3rem; margin-bottom: .6rem;
        }
        .cv-body { font-size: .85rem; color: #4a5568; }
        .cv-item { margin-bottom: .75rem; }
        .cv-item-title  { font-weight: 700; color: #1a202c; font-size: .88rem; }
        .cv-item-sub    { color: #718096; font-size: .8rem; margin-bottom: .2rem; }
        .cv-item-desc   { font-size: .82rem; color: #4a5568; }
        .cv-skills-grid { display: flex; flex-wrap: wrap; gap: .3rem; }
        .cv-skill-badge {
            padding: .2rem .65rem; border-radius: 20px;
            background: #EDF2FF; color: #2B6CB0;
            font-size: .75rem; font-weight: 600; border: 1px solid #BEE3F8;
        }

        /* ── Botões ──────────────────────────────────────── */
        .btn {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .6rem 1.2rem; border-radius: 8px;
            font-family: inherit; font-size: .86rem; font-weight: 600;
            border: none; cursor: pointer; transition: var(--transition);
            text-decoration: none;
        }
        .btn-primary  { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: #fff; }
        .btn-success  { background: linear-gradient(135deg, var(--success), #059669); color: #fff; }
        .btn-ghost    { background: var(--surface); color: var(--text-sec); border: 1px solid var(--border); }
        .btn:hover    { transform: translateY(-1px); opacity: .9; }
        .btn:disabled { opacity: .5; pointer-events: none; }

        /* ── Lightbox ────────────────────────────────────── */
        .lightbox {
            position: fixed; inset: 0; background: rgba(0,0,0,.9);
            backdrop-filter: blur(8px); z-index: 2000;
            display: flex; align-items: center; justify-content: center;
            padding: 1.5rem; opacity: 0; pointer-events: none;
            transition: opacity .25s;
        }
        .lightbox.open { opacity: 1; pointer-events: auto; }
        .lightbox img  { max-width: 92vw; max-height: 90vh; object-fit: contain; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,.5); }
        .lightbox-close {
            position: fixed; top: 1rem; right: 1rem;
            width: 40px; height: 40px; border-radius: 50%;
            background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2);
            color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; transition: var(--transition); z-index: 2001;
        }
        .lightbox-close:hover { background: var(--danger); }

        /* ── Footer ──────────────────────────────────────── */
        footer { text-align: center; padding: 2rem 1rem; color: var(--text-muted); font-size: .8rem; border-top: 1px solid var(--border); }
        footer a { color: var(--primary); text-decoration: none; }

        /* ── Animações ───────────────────────────────────── */
        @keyframes pulse { 0%,100%{ opacity:1; } 50%{ opacity:.5; } }
        @keyframes fadeUp { from{ opacity:0; transform:translateY(20px); } to{ opacity:1; transform:translateY(0); } }
        .page-wrap > * { animation: fadeUp .4s ease both; }
        .page-wrap > *:nth-child(2) { animation-delay: .07s; }
        .page-wrap > *:nth-child(3) { animation-delay: .13s; }
        .page-wrap > *:nth-child(4) { animation-delay: .19s; }

        /* ════════════════════════════════════════════════════
           RESPONSIVO — TABLET (≤ 768px)
        ════════════════════════════════════════════════════ */
        @media (max-width: 768px) {
            .header-inner { padding: 0 1rem; }
            .logo-name { font-size: 1.1rem; }
            .back-link { font-size: .8rem; }

            .page-wrap { padding: 1.25rem 1rem; gap: 1.25rem; }

            /* Topo da vaga */
            .vaga-top {
                flex-direction: column;
                align-items: center;
                padding: 1.25rem;
                gap: 1rem;
            }
            .vaga-thumb-wrap {
                width: 100px; height: 100px;
                border-radius: 12px;
            }
            .vaga-header-info { align-items: center; text-align: center; }
            .vaga-titulo { font-size: 1.35rem; }
            .vaga-status-row { justify-content: center; }
            .vaga-empresa-row { justify-content: center; }
            .vaga-setor { justify-content: center; }

            .meta-grid {
                grid-template-columns: 1fr 1fr;
                gap: .5rem;
            }

            /* Conteúdo */
            .vaga-content { padding: 1.25rem; }
            .content-grid { grid-template-columns: 1fr; }
            .content-col  { padding-right: 0; }
            .content-col + .content-col {
                padding-left: 0; border-left: none;
                border-top: 1px solid var(--border);
                padding-top: 1.25rem; margin-top: 1.25rem;
            }

            /* Candidatura */
            .candidatura-header { padding: 1rem 1.25rem; }
            .candidatura-header h2 { font-size: 1.1rem; }
            .candidatura-body { padding: 1.25rem; }
            .candidatura-opcoes { grid-template-columns: 1fr; gap: 1rem; }
            .opcao-btn { padding: 1.25rem 1rem; }

            /* Modais */
            .modal-overlay { padding: .75rem; }
            .modal-body { padding: 1.25rem; }
            .modal-header { padding: 1rem 1.25rem; }
            .modal-footer { padding: 1rem 1.25rem; flex-direction: column; }
            .modal-footer .btn { width: 100%; justify-content: center; }
            .form-grid-2 { grid-template-columns: 1fr; }
            .form-grid-3 { grid-template-columns: 1fr; }

            /* CV Preview */
            #curriculoPreview { padding: 1.25rem; }
            .cv-name { font-size: 1.3rem; }
        }

        /* ════════════════════════════════════════════════════
           RESPONSIVO — MOBILE PEQUENO (≤ 480px)
        ════════════════════════════════════════════════════ */
        @media (max-width: 480px) {
            .header-inner { padding: 0 .75rem; gap: .5rem; }
            .logo-icon { width: 34px; height: 34px; font-size: 1rem; }
            .logo-name { font-size: 1rem; }
            .theme-toggle { width: 34px; height: 34px; }
            .back-link { font-size: .75rem; }

            .page-wrap { padding: 1rem .75rem; gap: 1rem; }

            .vaga-top { padding: 1rem; gap: .75rem; }
            .vaga-thumb-wrap { width: 80px; height: 80px; border-radius: 10px; }
            .vaga-titulo { font-size: 1.15rem; }
            .vaga-empresa-row { font-size: .82rem; }

            .meta-grid {
                grid-template-columns: 1fr;
                gap: .4rem;
            }
            .meta-item { padding: .45rem .65rem; }
            .meta-item-icon { width: 28px; height: 28px; font-size: .78rem; }
            .meta-item-value { font-size: .8rem; }
            .meta-item-label { font-size: .58rem; }

            .vaga-content { padding: 1rem; }
            .section-title { font-size: .9rem; }
            .vaga-text { font-size: .82rem; }

            .candidatura-header { padding: .875rem 1rem; }
            .candidatura-header h2 { font-size: 1rem; }
            .candidatura-body { padding: 1rem; }
            .opcao-btn { padding: 1rem .875rem; }
            .opcao-icon { width: 48px; height: 48px; font-size: 1.4rem; }
            .opcao-title { font-size: .9rem; }
            .opcao-desc  { font-size: .75rem; }

            .modal-body { padding: 1rem; }
            .steps-nav { padding: .2rem; }
            .step-btn { font-size: .6rem; min-width: 55px; padding: .4rem .25rem; }

            #curriculoPreview { padding: 1rem; font-size: .82rem; }
            .cv-name { font-size: 1.15rem; }
            .cv-contato { font-size: .75rem; gap: .3rem .75rem; }

            footer { padding: 1.5rem .75rem; font-size: .75rem; }
        }

        /* ════════════════════════════════════════════════════
           RESPONSIVO — TELAS MUITO PEQUENAS (≤ 360px)
        ════════════════════════════════════════════════════ */
        @media (max-width: 360px) {
            .page-wrap { padding: .75rem .5rem; }
            .vaga-top { padding: .75rem; }
            .vaga-thumb-wrap { width: 64px; height: 64px; }
            .vaga-titulo { font-size: 1.05rem; }
            .meta-grid { gap: .3rem; }
            .vaga-content { padding: .75rem; }
            .candidatura-body { padding: .75rem; }
        }
    </style>
</head>
<body>

<!-- ===== HEADER ===== -->
<header>
    <div class="header-inner">
        <a href="index.php" class="logo-link">
            <div class="logo-icon">🚀</div>
            <span class="logo-name">TGA Carreiras</span>
        </a>
        <div class="header-right">
            <a href="index.php" class="back-link">← Voltar para vagas</a>
            <button class="theme-toggle" onclick="toggleTheme()"><span id="theme-icon">🌙</span></button>
        </div>
    </div>
</header>

<div class="page-wrap">

    <!-- Alertas -->
    <?php if ($sucesso): ?>
    <div class="alert alert-success">
        <span style="font-size:1.2rem">✅</span>
        <div><strong>Candidatura enviada com sucesso! 🎉</strong><span>Entraremos em contato em breve. Boa sorte!</span></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($erro)): ?>
    <div class="alert alert-error">
        <span style="font-size:1.2rem">❌</span>
        <div><strong>Erro ao enviar candidatura</strong><span><?= htmlspecialchars($erro) ?></span></div>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════
         CARD DA VAGA
    ═══════════════════════════════════════════════════ -->
    <div class="card">
        <!-- Topo: Thumb + Info -->
        <div class="vaga-top">
            <!-- Thumbnail pequena -->
            <div class="vaga-thumb-wrap"
                 <?php if (!empty($vaga['imagem'])): ?>
                 onclick="openLightbox('../uploads/vagas/<?= htmlspecialchars($vaga['imagem']) ?>')"
                 title="Clique para ampliar"
                 <?php endif; ?>>
                <?php if (!empty($vaga['imagem'])): ?>
                <img src="../uploads/vagas/<?= htmlspecialchars($vaga['imagem']) ?>"
                     alt="<?= htmlspecialchars($vaga['titulo']) ?>">
                <div class="vaga-thumb-overlay">
                    <span class="vaga-thumb-icon">🔍</span>
                </div>
                <?php else: ?>
                <div class="vaga-thumb-ph">💼</div>
                <?php endif; ?>
            </div>

            <!-- Informações da vaga -->
            <div class="vaga-header-info">
                <div class="vaga-status-row">
                    <span class="badge-ativa">Vaga Ativa</span>
                    <?php
                    $ehNova = strtotime($vaga['data_publicacao']) >= strtotime('-7 days');
                    if ($ehNova): ?>
                    <span class="badge-nova-vaga">✨ Nova</span>
                    <?php endif; ?>
                    <?php if (isset($vaga['views'])): ?>
                    <span class="badge-views">👁️ <?= number_format($vaga['views']) ?> visualizações</span>
                    <?php endif; ?>
                </div>

                <h1 class="vaga-titulo"><?= htmlspecialchars($vaga['titulo']) ?></h1>

                <?php if (!empty($vaga['empresa'])): ?>
                <div class="vaga-empresa-row">
                    🏢 <?= htmlspecialchars($vaga['empresa']) ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($vaga['setor'])): ?>
                <div class="vaga-setor">🏷️ <?= htmlspecialchars($vaga['setor']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Grid de meta informações completas -->
        <div style="padding: 0 2rem 1.5rem;">
            <div class="meta-grid">
                <div class="meta-item">
                    <div class="meta-item-icon">🏢</div>
                    <div>
                        <div class="meta-item-label">Empresa</div>
                        <div class="meta-item-value"><?= htmlspecialchars($vaga['empresa'] ?: 'Não informado') ?></div>
                    </div>
                </div>
                <div class="meta-item">
                    <div class="meta-item-icon">📌</div>
                    <div>
                        <div class="meta-item-label">Vagas Disponíveis</div>
                        <div class="meta-item-value"><?= (int)($vaga['quantidade_vagas'] ?? 1) ?> vaga(s)</div>
                    </div>
                </div>
                <div class="meta-item">
                    <div class="meta-item-icon">⏰</div>
                    <div>
                        <div class="meta-item-label">Disponibilidade</div>
                        <div class="meta-item-value"><?= htmlspecialchars($vaga['disponibilidade'] ?: 'Não informado') ?></div>
                    </div>
                </div>
                <div class="meta-item">
                    <div class="meta-item-icon">🎓</div>
                    <div>
                        <div class="meta-item-label">Escolaridade</div>
                        <div class="meta-item-value"><?= htmlspecialchars($vaga['escolaridade'] ?: 'Não informado') ?></div>
                    </div>
                </div>
                <div class="meta-item">
                    <div class="meta-item-icon">💼</div>
                    <div>
                        <div class="meta-item-label">Experiência</div>
                        <div class="meta-item-value"><?= htmlspecialchars($vaga['experiencia'] ?: 'Não informado') ?></div>
                    </div>
                </div>
                <div class="meta-item">
                    <div class="meta-item-icon">📍</div>
                    <div>
                        <div class="meta-item-label">Localização</div>
                        <div class="meta-item-value"><?= htmlspecialchars($vaga['localizacao'] ?: 'Não informado') ?></div>
                    </div>
                </div>
                <div class="meta-item">
                    <div class="meta-item-icon">💰</div>
                    <div>
                        <div class="meta-item-label">Salário</div>
                        <div class="meta-item-value"><?= htmlspecialchars($vaga['salario'] ?: 'A combinar') ?></div>
                    </div>
                </div>
                <div class="meta-item">
                    <div class="meta-item-icon">📅</div>
                    <div>
                        <div class="meta-item-label">Publicado em</div>
                        <div class="meta-item-value"><?= date('d/m/Y', strtotime($vaga['data_publicacao'])) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Descrição e Requisitos -->
        <div class="vaga-content" style="border-top: 1px solid var(--border);">
            <div class="content-grid">
                <div class="content-col">
                    <div class="section-title">
                        <div class="section-title-icon">📝</div> Descrição da Vaga
                    </div>
                    <div class="vaga-text"><?= nl2br(htmlspecialchars($vaga['descricao'] ?? 'Sem descrição disponível.')) ?></div>
                </div>
                <?php if (!empty($vaga['requisitos'])): ?>
                <div class="content-col">
                    <div class="section-title">
                        <div class="section-title-icon">✅</div> Requisitos
                    </div>
                    <div class="vaga-text"><?= nl2br(htmlspecialchars($vaga['requisitos'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════
         ÁREA DE CANDIDATURA
    ═══════════════════════════════════════════════════ -->
    <div class="card candidatura-card">
        <div class="candidatura-header">
            <h2>✉️ Candidate-se para esta vaga</h2>
            <p>Escolha como deseja enviar sua candidatura</p>
        </div>
        <div class="candidatura-body">
            <div class="candidatura-opcoes">
                <button class="opcao-btn" onclick="openModal('modalCurriculo')">
                    <div class="opcao-icon blue">📝</div>
                    <div class="opcao-title">Montar Currículo<br>e Enviar</div>
                    <div class="opcao-desc">Preencha as informações passo a passo e gere um currículo profissional.</div>
                    <span class="opcao-cta blue">✏️ Montar currículo</span>
                </button>
                <button class="opcao-btn" onclick="openModal('modalPdf')">
                    <div class="opcao-icon green">📄</div>
                    <div class="opcao-title">Enviar Currículo<br>em PDF Pronto</div>
                    <div class="opcao-desc">Já tem seu currículo pronto? Faça o upload do arquivo PDF.</div>
                    <span class="opcao-cta green">📤 Enviar PDF</span>
                </button>
            </div>
        </div>
    </div>

</div><!-- /page-wrap -->

<footer>
    <p>&copy; <?= date('Y') ?> TGA Carreiras · <a href="index.php">Ver todas as vagas</a></p>
</footer>

<!-- ═══════════════════════════════════════════════════════════
     MODAL 1 — CURRICULUM BUILDER (multi-step)
═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalCurriculo">
    <div class="modal-box" style="max-width:820px">
        <div class="modal-header">
            <h3>📝 Montar Currículo Profissional</h3>
            <button class="modal-close" onclick="closeModal('modalCurriculo')">✕</button>
        </div>
        <div class="modal-body" id="curriculoBuilderBody">

            <div class="progress-bar-wrap">
                <div class="progress-bar" id="progressBar" style="width:12.5%"></div>
            </div>

            <div class="steps-nav" id="stepsNav">
                <button class="step-btn active" onclick="goStep(1)">1. Dados</button>
                <button class="step-btn" onclick="goStep(2)">2. Objetivo</button>
                <button class="step-btn" onclick="goStep(3)">3. Formação</button>
                <button class="step-btn" onclick="goStep(4)">4. Experiência</button>
                <button class="step-btn" onclick="goStep(5)">5. Habilidades</button>
                <button class="step-btn" onclick="goStep(6)">6. Cursos</button>
                <button class="step-btn" onclick="goStep(7)">7. Projetos</button>
                <button class="step-btn" onclick="goStep(8)">8. Preview</button>
            </div>

            <!-- STEP 1 — Dados Pessoais -->
            <div class="step-pane active" id="step1">
                <div class="form-section">
                    <div class="form-section-title">👤 Dados Pessoais</div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Nome completo <span class="req">*</span></label>
                            <input type="text" class="form-input" id="cv_nome" placeholder="Anderson de Souza">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Telefone (WhatsApp) <span class="req">*</span></label>
                            <input type="tel" class="form-input" id="cv_telefone" placeholder="(65) 99999-9999">
                        </div>
                        <div class="form-group">
                            <label class="form-label">E-mail profissional <span class="req">*</span></label>
                            <input type="email" class="form-input" id="cv_email" placeholder="seu@email.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Estado <span class="req">*</span></label>
                            <select class="form-select" id="cv_estado" required>
                                <option value="">Selecione o estado</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Cidade <span class="req">*</span></label>
                            <select class="form-select" id="cv_cidade" required disabled>
                                <option value="">Selecione a cidade</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-section">
                    <div class="form-section-title">🔗 Links (opcional)</div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">LinkedIn <span class="opt">(opcional)</span></label>
                            <input type="url" class="form-input" id="cv_linkedin" placeholder="linkedin.com/in/seu-perfil">
                        </div>
                        <div class="form-group">
                            <label class="form-label">GitHub <span class="opt">(opcional)</span></label>
                            <input type="url" class="form-input" id="cv_github" placeholder="github.com/seu-usuario">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Facebook <span class="opt">(opcional)</span></label>
                            <input type="url" class="form-input" id="cv_facebook" placeholder="facebook.com/perfil">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Instagram <span class="opt">(opcional)</span></label>
                            <input type="url" class="form-input" id="cv_instagram" placeholder="instagram.com/perfil">
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 2 — Objetivo + Resumo -->
            <div class="step-pane" id="step2">
                <div class="form-section">
                    <div class="form-section-title">🎯 Objetivo Profissional</div>
                    <div class="form-group">
                        <label class="form-label">Objetivo (1 a 3 linhas)</label>
                        <textarea class="form-textarea" id="cv_objetivo" rows="3"
                            placeholder="Busco oportunidade como Desenvolvedor Full Stack, contribuindo com soluções eficientes e escaláveis..."></textarea>
                    </div>
                </div>
                <div class="form-section">
                    <div class="form-section-title">📋 Resumo Profissional</div>
                    <div class="form-group">
                        <label class="form-label">Resumo estratégico (3 a 5 linhas)</label>
                        <textarea class="form-textarea" id="cv_resumo" rows="5"
                            placeholder="Estudante de Ciência da Computação com experiência prática no desenvolvimento de sistemas web..."></textarea>
                    </div>
                </div>
                <div class="form-section">
                    <div class="form-section-title">🌍 Idiomas + Competências</div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Idiomas</label>
                            <textarea class="form-textarea" id="cv_idiomas" rows="3"
                                placeholder="Português – Nativo&#10;Inglês – Intermediário"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Competências comportamentais</label>
                            <textarea class="form-textarea" id="cv_soft" rows="3"
                                placeholder="Trabalho em equipe&#10;Proatividade&#10;Resolução de problemas"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 3 — Formação -->
            <div class="step-pane" id="step3">
                <div class="form-section">
                    <div class="form-section-title">🎓 Formação Acadêmica</div>
                    <div id="formacaoList"></div>
                    <button type="button" class="btn-add-item" onclick="addFormacao()">+ Adicionar formação</button>
                </div>
            </div>

            <!-- STEP 4 — Experiência -->
            <div class="step-pane" id="step4">
                <div class="form-section">
                    <div class="form-section-title">💼 Experiência Profissional</div>
                    <div id="experienciaList"></div>
                    <button type="button" class="btn-add-item" onclick="addExperiencia()">+ Adicionar experiência</button>
                </div>
            </div>

            <!-- STEP 5 — Habilidades -->
            <div class="step-pane" id="step5">
                <div class="form-section">
                    <div class="form-section-title">⚙️ Habilidades Técnicas</div>
                    <div class="form-grid-2">
                        <div>
                            <div class="form-label" style="margin-bottom:.4rem">Linguagens</div>
                            <div class="skills-tags" id="tags_linguagens"></div>
                            <div class="skill-input-row">
                                <input type="text" class="form-input" id="inp_linguagens" placeholder="Ex: PHP">
                                <button type="button" class="btn-add-skill" onclick="addSkill('linguagens')">+</button>
                            </div>
                        </div>
                        <div>
                            <div class="form-label" style="margin-bottom:.4rem">Banco de Dados</div>
                            <div class="skills-tags" id="tags_banco"></div>
                            <div class="skill-input-row">
                                <input type="text" class="form-input" id="inp_banco" placeholder="Ex: MySQL">
                                <button type="button" class="btn-add-skill" onclick="addSkill('banco')">+</button>
                            </div>
                        </div>
                        <div>
                            <div class="form-label" style="margin-bottom:.4rem">Ferramentas</div>
                            <div class="skills-tags" id="tags_ferramentas"></div>
                            <div class="skill-input-row">
                                <input type="text" class="form-input" id="inp_ferramentas" placeholder="Ex: Git">
                                <button type="button" class="btn-add-skill" onclick="addSkill('ferramentas')">+</button>
                            </div>
                        </div>
                        <div>
                            <div class="form-label" style="margin-bottom:.4rem">Integrações / Outros</div>
                            <div class="skills-tags" id="tags_outros"></div>
                            <div class="skill-input-row">
                                <input type="text" class="form-input" id="inp_outros" placeholder="Ex: APIs REST">
                                <button type="button" class="btn-add-skill" onclick="addSkill('outros')">+</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 6 — Cursos -->
            <div class="step-pane" id="step6">
                <div class="form-section">
                    <div class="form-section-title">📜 Cursos e Certificações</div>
                    <div id="cursosList"></div>
                    <button type="button" class="btn-add-item" onclick="addCurso()">+ Adicionar curso</button>
                </div>
            </div>

            <!-- STEP 7 — Projetos -->
            <div class="step-pane" id="step7">
                <div class="form-section">
                    <div class="form-section-title">🚀 Projetos (diferencial)</div>
                    <div id="projetosList"></div>
                    <button type="button" class="btn-add-item" onclick="addProjeto()">+ Adicionar projeto</button>
                </div>
            </div>

            <!-- STEP 8 — Preview -->
            <div class="step-pane" id="step8">
                <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:.75rem">
                    Revise seu currículo abaixo. Use <strong>Baixar PDF</strong> para salvar
                    ou <strong>Enviar Candidatura</strong> para aplicar à vaga diretamente.
                </p>
                <div id="curriculoPreview"></div>
            </div>

        </div>
        <div class="modal-footer" id="curriculoFooter">
            <button class="btn btn-ghost" id="btnPrev" onclick="prevStep()" style="display:none">← Anterior</button>
            <button class="btn btn-ghost" onclick="closeModal('modalCurriculo')" style="margin-right:auto">Cancelar</button>
            <button class="btn btn-success" id="btnDownloadPdf" onclick="downloadCurriculoPdf()" style="display:none">⬇️ Baixar PDF</button>
            <button class="btn btn-primary" id="btnEnviarCurriculo" onclick="enviarCurriculoComoCandidata()" style="display:none">✉️ Enviar Candidatura</button>
            <button class="btn btn-primary" id="btnNext" onclick="nextStep()">Próximo →</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL 2 — UPLOAD PDF PRONTO
═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalPdf">
    <div class="modal-box">
        <div class="modal-header">
            <h3>📄 Enviar Currículo em PDF</h3>
            <button class="modal-close" onclick="closeModal('modalPdf')">✕</button>
        </div>
        <div class="modal-body">
            <form action="vaga.php?id=<?= (int)$vaga['id'] ?>"
                  method="POST" enctype="multipart/form-data" id="formPdf" novalidate>

                <input type="hidden" name="vaga_id"    value="<?= (int)$vaga['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="form-section">
                    <div class="form-section-title">👤 Seus Dados</div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Nome completo <span class="req">*</span></label>
                            <input type="text" name="nome" class="form-input" required
                                   value="<?= isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : '' ?>"
                                   placeholder="Digite seu nome completo">
                        </div>
                        <div class="form-group">
                            <label class="form-label">E-mail <span class="req">*</span></label>
                            <input type="email" name="email" class="form-input" required
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                                   placeholder="seu@email.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Telefone <span class="opt">(opcional)</span></label>
                            <input type="tel" name="telefone" class="form-input" id="telefone"
                                   value="<?= isset($_POST['telefone']) ? htmlspecialchars($_POST['telefone']) : '' ?>"
                                   placeholder="(65) 99999-9999">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-group">
                        <label class="form-label">Mensagem <span class="opt">(opcional)</span></label>
                        <textarea name="mensagem" class="form-textarea" rows="3"
                                  placeholder="Conte um pouco sobre você e por que se interessa pela vaga…"><?= isset($_POST['mensagem']) ? htmlspecialchars($_POST['mensagem']) : '' ?></textarea>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">📄 Currículo em PDF</div>
                    <div class="drop-zone" id="dropZone"
                         onclick="document.getElementById('curriculo').click()">
                        <div class="drop-zone-icon">☁️</div>
                        <div class="drop-zone-title"><span>Clique para selecionar</span> ou arraste aqui</div>
                        <div class="drop-zone-hint">Apenas PDF · Máximo 5 MB</div>
                    </div>
                    <input id="curriculo" name="curriculo" type="file" accept=".pdf" required style="display:none">
                    <div class="file-preview" id="filePreview">
                        <div class="file-preview-left">
                            <span style="font-size:1.6rem">📕</span>
                            <div>
                                <div style="font-size:.85rem;font-weight:600;color:var(--text)" id="fileName">—</div>
                                <div style="font-size:.75rem;color:var(--text-muted)" id="fileSize">—</div>
                            </div>
                        </div>
                        <button type="button" class="file-remove" onclick="removeFile()">✕</button>
                    </div>
                </div>

                <div class="form-check" style="margin-bottom:.5rem">
                    <input type="checkbox" id="termos" name="termos" required>
                    <label for="termos">Li e aceito os <a href="#">Termos de Uso</a> <span style="color:var(--danger)">*</span></label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('modalPdf')">Cancelar</button>
            <button class="btn btn-success" id="btnSubmitPdf" onclick="submitPdfForm()">✉️ Enviar Candidatura</button>
        </div>
    </div>
</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()">✕</button>
    <img id="lightboxImg" src="" alt="">
</div>

<!-- ═══════════════════════════════════════════════════════════
     SCRIPTS
═══════════════════════════════════════════════════════════ -->
<script>
"use strict";

/* ── TEMA ────────────────────────────────────────────────── */
function toggleTheme() {
    const html = document.documentElement;
    const icon = document.getElementById('theme-icon');
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    icon.textContent = next === 'dark' ? '🌙' : '☀️';
    localStorage.setItem('theme', next);
}

/* ── MODAIS ──────────────────────────────────────────────── */
function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
}
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); });
});

/* ── LIGHTBOX ────────────────────────────────────────────── */
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
    document.body.style.overflow = '';
}

/* ── CURRICULUM BUILDER — Steps ──────────────────────────── */
const TOTAL_STEPS = 8;
let currentStep = 1;

function goStep(n) {
    document.getElementById(`step${currentStep}`).classList.remove('active');
    document.querySelectorAll('.step-btn')[currentStep - 1].classList.remove('active');
    if (n > currentStep) document.querySelectorAll('.step-btn')[currentStep - 1].classList.add('done');

    currentStep = n;
    document.getElementById(`step${currentStep}`).classList.add('active');
    document.querySelectorAll('.step-btn')[currentStep - 1].classList.add('active');
    document.querySelectorAll('.step-btn')[currentStep - 1].classList.remove('done');

    document.getElementById('progressBar').style.width = ((currentStep / TOTAL_STEPS) * 100) + '%';
    document.getElementById('btnPrev').style.display = currentStep > 1 ? '' : 'none';
    document.getElementById('btnNext').style.display = currentStep < TOTAL_STEPS ? '' : 'none';
    document.getElementById('btnDownloadPdf').style.display    = currentStep === TOTAL_STEPS ? '' : 'none';
    document.getElementById('btnEnviarCurriculo').style.display = currentStep === TOTAL_STEPS ? '' : 'none';

    if (currentStep === TOTAL_STEPS) renderPreview();
}

function nextStep() { if (currentStep < TOTAL_STEPS) goStep(currentStep + 1); }
function prevStep() { if (currentStep > 1)           goStep(currentStep - 1); }

/* ── BLOCKS DINÂMICOS ────────────────────────────────────── */
let formacaoCount = 0, expCount = 0, cursoCount = 0, projetoCount = 0;

function addFormacao() {
    const id = ++formacaoCount;
    document.getElementById('formacaoList').insertAdjacentHTML('beforeend', `
    <div class="item-block" id="formacao_${id}">
        <button type="button" class="btn-remove-item" onclick="removeBlock('formacao_${id}')">✕</button>
        <div class="form-grid-2" style="padding-right:2rem">
            <div class="form-group">
                <label class="form-label">Curso</label>
                <input type="text" class="form-input" data-cv="f_curso_${id}" placeholder="Bacharelado em Ciência da Computação">
            </div>
            <div class="form-group">
                <label class="form-label">Instituição</label>
                <input type="text" class="form-input" data-cv="f_inst_${id}" placeholder="Faculdade XYZ">
            </div>
            <div class="form-group">
                <label class="form-label">Período</label>
                <input type="text" class="form-input" data-cv="f_periodo_${id}" placeholder="2023 – 2027 (em andamento)">
            </div>
        </div>
    </div>`);
}

function addExperiencia() {
    const id = ++expCount;
    document.getElementById('experienciaList').insertAdjacentHTML('beforeend', `
    <div class="item-block" id="exp_${id}">
        <button type="button" class="btn-remove-item" onclick="removeBlock('exp_${id}')">✕</button>
        <div style="padding-right:2rem">
            <div class="form-grid-3">
                <div class="form-group">
                    <label class="form-label">Cargo</label>
                    <input type="text" class="form-input" data-cv="e_cargo_${id}" placeholder="Analista de Sistemas">
                </div>
                <div class="form-group">
                    <label class="form-label">Empresa</label>
                    <input type="text" class="form-input" data-cv="e_empresa_${id}" placeholder="TGA Sistemas">
                </div>
                <div class="form-group">
                    <label class="form-label">Período</label>
                    <input type="text" class="form-input" data-cv="e_periodo_${id}" placeholder="2024 – Atual">
                </div>
            </div>
            <div class="form-group" style="margin-top:.75rem">
                <label class="form-label">Descrição das atividades</label>
                <textarea class="form-textarea" data-cv="e_desc_${id}" rows="3"
                    placeholder="• Desenvolvimento e manutenção de sistemas web"></textarea>
            </div>
        </div>
    </div>`);
}

function addCurso() {
    const id = ++cursoCount;
    document.getElementById('cursosList').insertAdjacentHTML('beforeend', `
    <div class="item-block" id="curso_${id}">
        <button type="button" class="btn-remove-item" onclick="removeBlock('curso_${id}')">✕</button>
        <div class="form-grid-3" style="padding-right:2rem">
            <div class="form-group" style="grid-column:span 2">
                <label class="form-label">Nome do curso</label>
                <input type="text" class="form-input" data-cv="c_nome_${id}" placeholder="Curso de Pentest">
            </div>
            <div class="form-group">
                <label class="form-label">Instituição / Ano</label>
                <input type="text" class="form-input" data-cv="c_inst_${id}" placeholder="SENAI – 2025">
            </div>
        </div>
    </div>`);
}

function addProjeto() {
    const id = ++projetoCount;
    document.getElementById('projetosList').insertAdjacentHTML('beforeend', `
    <div class="item-block" id="projeto_${id}">
        <button type="button" class="btn-remove-item" onclick="removeBlock('projeto_${id}')">✕</button>
        <div style="padding-right:2rem">
            <div class="form-group">
                <label class="form-label">Nome do projeto</label>
                <input type="text" class="form-input" data-cv="p_nome_${id}" placeholder="TGA Carreiras">
            </div>
            <div class="form-group" style="margin-top:.65rem">
                <label class="form-label">Descrição</label>
                <textarea class="form-textarea" data-cv="p_desc_${id}" rows="3"
                    placeholder="• Sistema completo de cadastro de vagas e candidaturas"></textarea>
            </div>
        </div>
    </div>`);
}

function removeBlock(id) { document.getElementById(id)?.remove(); }

/* ── SKILLS ──────────────────────────────────────────────── */
const skills = { linguagens: [], banco: [], ferramentas: [], outros: [] };

function addSkill(cat) {
    const inp = document.getElementById(`inp_${cat}`);
    const val = inp.value.trim();
    if (!val || skills[cat].includes(val)) { inp.value = ''; return; }
    skills[cat].push(val);
    renderSkillTags(cat);
    inp.value = '';
}
function removeSkill(cat, val) {
    skills[cat] = skills[cat].filter(s => s !== val);
    renderSkillTags(cat);
}
function renderSkillTags(cat) {
    document.getElementById(`tags_${cat}`).innerHTML = skills[cat].map(s =>
        `<span class="skill-tag">${s} <button type="button" class="skill-tag-remove" onclick="removeSkill('${cat}','${s}')">×</button></span>`
    ).join('');
}
['linguagens','banco','ferramentas','outros'].forEach(cat => {
    document.getElementById(`inp_${cat}`)?.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); addSkill(cat); }
    });
});

/* ── HELPERS ─────────────────────────────────────────────── */
function cv(key) {
    const el = document.querySelector(`[data-cv="${key}"], #${key}`);
    return el ? el.value.trim() : '';
}

/* ── RENDER PREVIEW ──────────────────────────────────────── */
function renderPreview() {
    const nome     = cv('cv_nome');
    const telefone = cv('cv_telefone');
    const email    = cv('cv_email');
    const estado   = document.getElementById('cv_estado')?.value || '';
    const cidade   = document.getElementById('cv_cidade')?.value || '';
    const cidadeEstado = cidade && estado ? `${cidade} - ${estado}` : '';
    const linkedin = cv('cv_linkedin');
    const github   = cv('cv_github');
    const objetivo = cv('cv_objetivo');
    const resumo   = cv('cv_resumo');
    const idiomas  = cv('cv_idiomas');
    const soft     = cv('cv_soft');

    let formacaoHTML = '';
    for (let i = 1; i <= formacaoCount; i++) {
        const curso = cv(`f_curso_${i}`), inst = cv(`f_inst_${i}`), periodo = cv(`f_periodo_${i}`);
        if (!curso && !inst) continue;
        formacaoHTML += `<div class="cv-item"><div class="cv-item-title">${curso}</div><div class="cv-item-sub">${inst}${periodo ? ' · ' + periodo : ''}</div></div>`;
    }

    let expHTML = '';
    for (let i = 1; i <= expCount; i++) {
        const cargo = cv(`e_cargo_${i}`), empresa = cv(`e_empresa_${i}`), periodo = cv(`e_periodo_${i}`), desc = cv(`e_desc_${i}`);
        if (!cargo) continue;
        expHTML += `<div class="cv-item"><div class="cv-item-title">${cargo} — ${empresa}</div><div class="cv-item-sub">${periodo}</div><div class="cv-item-desc">${desc.replace(/\n/g,'<br>')}</div></div>`;
    }

    const allSkills = [...skills.linguagens, ...skills.banco, ...skills.ferramentas, ...skills.outros];
    const skillsHTML = allSkills.length
        ? `<div class="cv-skills-grid">${allSkills.map(s => `<span class="cv-skill-badge">${s}</span>`).join('')}</div>`
        : '';

    let cursosHTML = '';
    for (let i = 1; i <= cursoCount; i++) {
        const n = cv(`c_nome_${i}`), inst = cv(`c_inst_${i}`);
        if (!n) continue;
        cursosHTML += `<div class="cv-item"><div class="cv-item-title">${n}</div><div class="cv-item-sub">${inst}</div></div>`;
    }

    let projHTML = '';
    for (let i = 1; i <= projetoCount; i++) {
        const pn = cv(`p_nome_${i}`), pd = cv(`p_desc_${i}`);
        if (!pn) continue;
        projHTML += `<div class="cv-item"><div class="cv-item-title">${pn}</div><div class="cv-item-desc">${pd.replace(/\n/g,'<br>')}</div></div>`;
    }

    let contato = [telefone, email, cidadeEstado];
    if (linkedin) contato.push(`<a href="${linkedin}" style="color:#0066FF">${linkedin}</a>`);
    if (github)   contato.push(`<a href="${github}" style="color:#0066FF">${github}</a>`);

    document.getElementById('curriculoPreview').innerHTML = `
        <div class="cv-header">
            <div class="cv-name">${nome || '(Nome não preenchido)'}</div>
            <div class="cv-contato">${contato.filter(Boolean).join(' &nbsp;|&nbsp; ')}</div>
        </div>
        ${objetivo ? `<div class="cv-section"><div class="cv-section-title">Objetivo Profissional</div><div class="cv-body">${objetivo}</div></div>` : ''}
        ${resumo ? `<div class="cv-section"><div class="cv-section-title">Resumo Profissional</div><div class="cv-body">${resumo}</div></div>` : ''}
        ${expHTML ? `<div class="cv-section"><div class="cv-section-title">Experiência Profissional</div>${expHTML}</div>` : ''}
        ${formacaoHTML ? `<div class="cv-section"><div class="cv-section-title">Formação Acadêmica</div>${formacaoHTML}</div>` : ''}
        ${allSkills.length ? `<div class="cv-section"><div class="cv-section-title">Habilidades Técnicas</div>${skillsHTML}</div>` : ''}
        ${projHTML ? `<div class="cv-section"><div class="cv-section-title">Projetos</div>${projHTML}</div>` : ''}
        ${cursosHTML ? `<div class="cv-section"><div class="cv-section-title">Cursos e Certificações</div>${cursosHTML}</div>` : ''}
        ${idiomas ? `<div class="cv-section"><div class="cv-section-title">Idiomas</div><div class="cv-body">${idiomas.replace(/\n/g,'<br>')}</div></div>` : ''}
        ${soft ? `<div class="cv-section"><div class="cv-section-title">Competências Comportamentais</div><div class="cv-body">${soft.replace(/\n/g,'<br>')}</div></div>` : ''}
    `;
}

/* ── DOWNLOAD PDF ────────────────────────────────────────── */
function downloadCurriculoPdf() {
    const element = document.getElementById('curriculoPreview');
    const nomeArquivo = (cv('cv_nome') || 'curriculo').toLowerCase().replace(/\s+/g, '_');
    html2pdf().set({
        margin: [10, 15, 10, 15],
        filename: `curriculo_${nomeArquivo}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    }).from(element).save();
}

/* ── ENVIAR CANDIDATURA (builder) ────────────────────────── */
async function enviarCurriculoComoCandidata() {
    const btn = document.getElementById('btnEnviarCurriculo');
    const nomeVal  = cv('cv_nome');
    const emailVal = cv('cv_email');

    if (!nomeVal || !emailVal) {
        alert('Preencha pelo menos Nome e E-mail na etapa 1 antes de enviar.');
        return;
    }

    btn.disabled = true;
    btn.textContent = '⏳ Gerando PDF…';

    try {
        const blob = await html2pdf().set({
            margin: [10, 15, 10, 15],
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        }).from(document.getElementById('curriculoPreview')).outputPdf('blob');

        const nomeArquivo = `curriculo_${nomeVal.toLowerCase().replace(/\s+/g,'_')}.pdf`;
        const file = new File([blob], nomeArquivo, { type: 'application/pdf' });

        const fd = new FormData();
        fd.append('vaga_id',    '<?= (int)$vaga['id'] ?>');
        fd.append('csrf_token', '<?= htmlspecialchars($_SESSION['csrf_token']) ?>');
        fd.append('nome',       nomeVal);
        fd.append('email',      emailVal);
        fd.append('telefone',   cv('cv_telefone'));
        fd.append('mensagem',   cv('cv_objetivo'));
        fd.append('curriculo',  file, nomeArquivo);
        fd.append('termos',     'on');

        btn.textContent = '⏳ Enviando…';

        const resp = await fetch('vaga.php?id=<?= (int)$vaga['id'] ?>', { method: 'POST', body: fd });

        if (resp.redirected) {
            window.location.href = resp.url;
        } else {
            window.location.reload();
        }
    } catch(err) {
        console.error(err);
        alert('Erro ao gerar ou enviar o PDF. Tente novamente.');
        btn.disabled = false;
        btn.textContent = '✉️ Enviar Candidatura';
    }
}

/* ── MODAL PDF — upload + submit ─────────────────────────── */
document.getElementById('curriculo')?.addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    if (!file.name.toLowerCase().endsWith('.pdf')) { alert('Apenas PDFs.'); this.value=''; return; }
    if (file.size > 5 * 1024 * 1024) { alert('Arquivo maior que 5 MB.'); this.value=''; return; }
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = (file.size / (1024*1024)).toFixed(2) + ' MB';
    document.getElementById('filePreview').classList.add('show');
    document.getElementById('dropZone').classList.add('has-file');
});

function removeFile() {
    document.getElementById('curriculo').value = '';
    document.getElementById('filePreview').classList.remove('show');
    document.getElementById('dropZone').classList.remove('has-file');
}
/*
function submitPdfForm() {
    if (!document.getElementById('curriculo').files[0]) { alert('Selecione seu currículo em PDF.'); return; }
    const btn = document.getElementById('btnSubmitPdf');
    btn.disabled = true;
    btn.textContent = '⏳ Enviando…';
    document.getElementById('formPdf').submit();
}*/
function submitPdfForm() {
    const form = document.getElementById('formPdf');
    const fileInput = document.getElementById('curriculo');

    // valida arquivo rapidamente
    if (!fileInput.files || !fileInput.files[0]) {
        alert('Selecione seu currículo em PDF.');
        return;
    }

    //  NÃO use form.submit() (bypassa required/HTML5)
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const btn = document.getElementById('btnSubmitPdf');
    btn.disabled = true;
    btn.textContent = '⏳ Enviando…';

    // requestSubmit respeita validação e dispara submit normal
    if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
    } else {
        // fallback antigo (ainda evita bypass porque já validamos acima)
        form.submit();
    }
}

/* ── Máscara telefone ────────────────────────────────────── */
document.getElementById('telefone')?.addEventListener('input', function() {
    let v = this.value.replace(/\D/g, '');
    v = v.length <= 10
        ? v.replace(/^(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3')
        : v.replace(/^(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
    this.value = v.trim();
});

/* ── IBGE — Estados e Cidades ────────────────────────────── */
const selectEstado = document.getElementById('cv_estado');
const selectCidade = document.getElementById('cv_cidade');

async function carregarEstados() {
    try {
        const resp = await fetch('https://servicodados.ibge.gov.br/api/v1/localidades/estados?orderBy=nome');
        const estados = await resp.json();
        estados.forEach(uf => {
            const opt = document.createElement('option');
            opt.value = uf.sigla;
            opt.textContent = `${uf.nome} (${uf.sigla})`;
            selectEstado.appendChild(opt);
        });
    } catch (err) { console.error('Erro ao carregar estados:', err); }
}

selectEstado?.addEventListener('change', async function() {
    const uf = this.value;
    selectCidade.innerHTML = '<option value="">Carregando...</option>';
    selectCidade.disabled = true;
    if (!uf) { selectCidade.innerHTML = '<option value="">Selecione a cidade</option>'; return; }
    try {
        const resp = await fetch(`https://servicodados.ibge.gov.br/api/v1/localidades/estados/${uf}/municipios`);
        const cidades = await resp.json();
        selectCidade.innerHTML = '<option value="">Selecione a cidade</option>';
        cidades.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.nome;
            opt.textContent = c.nome;
            selectCidade.appendChild(opt);
        });
        selectCidade.disabled = false;
    } catch (err) {
        console.error('Erro ao carregar cidades:', err);
        selectCidade.innerHTML = '<option value="">Erro ao carregar</option>';
    }
});

/* ── INIT ────────────────────────────────────────────────── */
window.addEventListener('DOMContentLoaded', () => {
    const saved = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
    document.getElementById('theme-icon').textContent = saved === 'dark' ? '🌙' : '☀️';

    addFormacao();
    addExperiencia();
    carregarEstados();

    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(el => {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 420);
        });
    }, 8000);
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeModal('modalCurriculo');
        closeModal('modalPdf');
        closeLightbox();
    }
});
</script>
</body>
</html>