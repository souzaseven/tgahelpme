<?php
/**
 * TGA Carreiras — Perfil (Admin)
 * Arquivo: adm/perfil.php
 */

require_once __DIR__ . '/../backend/verifica_admin.php';
require_once __DIR__ . '/../backend/conexao.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* ============================================================
   BLOCO 1 — CSRF
============================================================ */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

/* ============================================================
   BLOCO 2 — ID DO ADMIN LOGADO
============================================================ */
$adminId = (int)($GLOBALS['__ADMIN_ID__'] ?? 0);
if ($adminId <= 0) $adminId = (int)($_SESSION['admin_id'] ?? 0);
if ($adminId <= 0) $adminId = (int)($_SESSION['usuario_id'] ?? 0);
if ($adminId <= 0) {
    header('Location: login.php?msg=' . urlencode('Faça login para continuar.') . '&tipo=error');
    exit;
}

/* ============================================================
   BLOCO 3 — HELPERS
============================================================ */
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function formatPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 11) {
        return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7);
    } elseif (strlen($phone) === 10) {
        return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 4) . '-' . substr($phone, 6);
    }
    return $phone;
}

function formatDate($date) {
    if (empty($date) || $date === '0000-00-00 00:00:00') return '-';
    return date('d/m/Y H:i', strtotime($date));
}

/* ============================================================
   BLOCO 4 — BUSCAR DADOS DO ADMIN (usuarios_carreiras)
============================================================ */
$admin = null;

try {
    // Primeiro, vamos verificar a estrutura da tabela
    $columns = [];
    $stmtCols = $pdo->query("DESCRIBE usuarios_carreiras");
    while ($col = $stmtCols->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $col['Field'];
    }
    
    // Mapeamento de campos possíveis
    $fieldMap = [
        'id' => in_array('id', $columns) ? 'id' : (in_array('usuario_id', $columns) ? 'usuario_id' : null),
        'nome' => in_array('nome', $columns) ? 'nome' : (in_array('name', $columns) ? 'name' : 'nome'),
        'email' => in_array('email', $columns) ? 'email' : null,
        'senha' => in_array('senha', $columns) ? 'senha' : (in_array('password', $columns) ? 'password' : (in_array('senha_hash', $columns) ? 'senha_hash' : 'senha')),
        'tipo' => in_array('tipo', $columns) ? 'tipo' : (in_array('permissao', $columns) ? 'permissao' : (in_array('role', $columns) ? 'role' : 'tipo')),
        'ativo' => in_array('ativo', $columns) ? 'ativo' : (in_array('status', $columns) ? 'status' : null),
        'is_admin' => in_array('is_admin', $columns) ? 'is_admin' : (in_array('admin', $columns) ? 'admin' : null),
        'telefone' => in_array('telefone', $columns) ? 'telefone' : (in_array('phone', $columns) ? 'phone' : null),
        'estado' => in_array('estado', $columns) ? 'estado' : (in_array('uf', $columns) ? 'uf' : null),
        'cidade' => in_array('cidade', $columns) ? 'cidade' : (in_array('city', $columns) ? 'city' : null),
        'data_cadastro' => in_array('data_cadastro', $columns) ? 'data_cadastro' : (in_array('created_at', $columns) ? 'created_at' : null),
        'ultimo_login' => in_array('ultimo_login', $columns) ? 'ultimo_login' : (in_array('last_login', $columns) ? 'last_login' : null)
    ];
    
    // Monta a query dinamicamente
    $selectFields = [];
    foreach ($fieldMap as $key => $field) {
        if ($field) {
            $selectFields[] = "`$field` as `$key`";
        }
    }
    
    $sql = "SELECT " . implode(', ', $selectFields) . " 
            FROM usuarios_carreiras 
            WHERE `{$fieldMap['id']}` = :id 
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $adminId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        header('Location: login.php?msg=' . urlencode('Usuário admin não encontrado.') . '&tipo=error');
        exit;
    }
    
    // Se não encontrou alguns campos, define valores padrão
    if (!isset($admin['tipo']) || $admin['tipo'] === null) $admin['tipo'] = 'admin';
    if (!isset($admin['ativo']) || $admin['ativo'] === null) $admin['ativo'] = 1;
    if (!isset($admin['is_admin']) || $admin['is_admin'] === null) $admin['is_admin'] = 1;
    
} catch (Throwable $e) {
    error_log("Erro ao carregar perfil: " . $e->getMessage());
    header('Location: login.php?msg=' . urlencode('Erro ao carregar perfil.') . '&tipo=error');
    exit;
}

/* ============================================================
   BLOCO 5 — ALERTAS (GET)
============================================================ */
$tipo = strtolower(trim($_GET['tipo'] ?? ''));
$msg  = trim($_GET['msg'] ?? '');

$alert = null;
if ($msg !== '') {
    if (!in_array($tipo, ['success', 'error', 'warning'], true)) $tipo = 'warning';
    $alert = ['type' => $tipo, 'msg' => $msg];
}

/* ============================================================
   BLOCO 6 — POST (Atualizar Perfil / Senha) + Redirect (PRG)
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedCsrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf, $postedCsrf)) {
        header('Location: perfil.php?msg=' . urlencode('Falha CSRF. Recarregue a página e tente novamente.') . '&tipo=error');
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 6.1 Atualizar dados do perfil
    if ($action === 'update_profile') {

        $nome     = trim((string)($_POST['nome'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $telefone = trim((string)($_POST['telefone'] ?? ''));
        $estado   = trim((string)($_POST['estado'] ?? ''));
        $cidade   = trim((string)($_POST['cidade'] ?? ''));

        if ($nome === '') {
            header('Location: perfil.php?msg=' . urlencode('Informe o nome.') . '&tipo=warning');
            exit;
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: perfil.php?msg=' . urlencode('Informe um e-mail válido.') . '&tipo=warning');
            exit;
        }

        // Verifica se o email já existe para outro usuário
        try {
            $checkEmail = $pdo->prepare("SELECT id FROM usuarios_carreiras WHERE email = :email AND id != :id LIMIT 1");
            $checkEmail->execute([':email' => $email, ':id' => $adminId]);
            if ($checkEmail->fetch()) {
                header('Location: perfil.php?msg=' . urlencode('Este e-mail já está em uso por outro usuário.') . '&tipo=warning');
                exit;
            }
        } catch (Throwable $e) {
            // Ignora erro na verificação
        }

        try {
            $up = $pdo->prepare("
                UPDATE usuarios_carreiras
                SET `{$fieldMap['nome']}` = :nome,
                    `{$fieldMap['email']}` = :email
                " . ($fieldMap['telefone'] ? ", `{$fieldMap['telefone']}` = :telefone" : "") . "
                " . ($fieldMap['estado'] ? ", `{$fieldMap['estado']}` = :estado" : "") . "
                " . ($fieldMap['cidade'] ? ", `{$fieldMap['cidade']}` = :cidade" : "") . "
                WHERE `{$fieldMap['id']}` = :id
                LIMIT 1
            ");
            
            $params = [
                ':nome'     => $nome,
                ':email'    => $email,
                ':id'       => $adminId,
            ];
            
            if ($fieldMap['telefone']) $params[':telefone'] = ($telefone !== '' ? $telefone : null);
            if ($fieldMap['estado']) $params[':estado'] = ($estado !== '' ? $estado : null);
            if ($fieldMap['cidade']) $params[':cidade'] = ($cidade !== '' ? $cidade : null);
            
            $up->execute($params);

            // Atualiza a sessão com o novo nome/email
            $_SESSION['admin_nome'] = $nome;
            $_SESSION['admin_email'] = $email;

            header('Location: perfil.php?msg=' . urlencode('Perfil atualizado com sucesso!') . '&tipo=success');
            exit;

        } catch (Throwable $e) {
            error_log("Erro ao atualizar perfil: " . $e->getMessage());
            header('Location: perfil.php?msg=' . urlencode('Erro ao atualizar perfil.') . '&tipo=error');
            exit;
        }
    }

    // 6.2 Alterar senha
    if ($action === 'update_password') {

        $senhaAtual = (string)($_POST['senha_atual'] ?? '');
        $senhaNova  = (string)($_POST['senha_nova'] ?? '');
        $senhaNova2 = (string)($_POST['senha_nova2'] ?? '');

        if ($senhaAtual === '' || $senhaNova === '' || $senhaNova2 === '') {
            header('Location: perfil.php?msg=' . urlencode('Preencha todos os campos da senha.') . '&tipo=warning');
            exit;
        }

        if ($senhaNova !== $senhaNova2) {
            header('Location: perfil.php?msg=' . urlencode('A confirmação da nova senha não confere.') . '&tipo=warning');
            exit;
        }

        if (strlen($senhaNova) < 8) {
            header('Location: perfil.php?msg=' . urlencode('A nova senha deve ter no mínimo 8 caracteres.') . '&tipo=warning');
            exit;
        }

        // Força a senha a ter pelo menos uma letra e um número
        if (!preg_match('/[A-Za-z]/', $senhaNova) || !preg_match('/[0-9]/', $senhaNova)) {
            header('Location: perfil.php?msg=' . urlencode('A senha deve conter letras e números.') . '&tipo=warning');
            exit;
        }

        try {
            // Recarrega a senha do banco
            $stmtPwd = $pdo->prepare("SELECT `{$fieldMap['senha']}` FROM usuarios_carreiras WHERE `{$fieldMap['id']}` = :id LIMIT 1");
            $stmtPwd->execute([':id' => $adminId]);
            $hashBanco = (string)($stmtPwd->fetchColumn() ?: '');

            // Verifica a senha atual usando password_verify
            $okAtual = false;
            
            if ($hashBanco !== '') {
                $okAtual = password_verify($senhaAtual, $hashBanco);
            }

            if (!$okAtual) {
                header('Location: perfil.php?msg=' . urlencode('Senha atual incorreta.') . '&tipo=error');
                exit;
            }

            // Gera novo hash para a nova senha
            $novoHash = password_hash($senhaNova, PASSWORD_DEFAULT);

            $up = $pdo->prepare("
                UPDATE usuarios_carreiras
                SET `{$fieldMap['senha']}` = :senha
                WHERE `{$fieldMap['id']}` = :id
                LIMIT 1
            ");
            $up->execute([
                ':senha' => $novoHash,
                ':id'    => $adminId
            ]);

            header('Location: perfil.php?msg=' . urlencode('Senha alterada com sucesso!') . '&tipo=success');
            exit;

        } catch (Throwable $e) {
            error_log("Erro ao alterar senha: " . $e->getMessage());
            header('Location: perfil.php?msg=' . urlencode('Erro ao alterar senha.') . '&tipo=error');
            exit;
        }
    }

    // Ação inválida
    header('Location: perfil.php?msg=' . urlencode('Ação inválida.') . '&tipo=error');
    exit;
}

/* ============================================================
   BLOCO 7 — UI DADOS
============================================================ */
$nome     = (string)($admin['nome'] ?? '');
$email    = (string)($admin['email'] ?? '');
$telefone = (string)($admin['telefone'] ?? '');
$estado   = (string)($admin['estado'] ?? '');
$cidade   = (string)($admin['cidade'] ?? '');
$tipoUser = (string)($admin['tipo'] ?? 'admin');
$ativo    = (int)($admin['ativo'] ?? 1);
$isAdmin  = (int)($admin['is_admin'] ?? 1);
$ultimoLogin = (string)($admin['ultimo_login'] ?? '');
$dataCadastro = (string)($admin['data_cadastro'] ?? '');

$iniciais = '';
if ($nome !== '') {
    $partes = explode(' ', $nome);
    $iniciais = mb_strtoupper(mb_substr($partes[0], 0, 1));
    if (count($partes) > 1) {
        $iniciais .= mb_strtoupper(mb_substr(end($partes), 0, 1));
    }
} else {
    $iniciais = mb_strtoupper(mb_substr($email, 0, 1));
}

$telefoneFormatado = formatPhone($telefone);
$dataCadastroFormatada = formatDate($dataCadastro);
$ultimoLoginFormatado = formatDate($ultimoLogin);

// Lista de estados brasileiros para fallback
$estadosBrasileiros = [
    'AC' => 'Acre',
    'AL' => 'Alagoas',
    'AP' => 'Amapá',
    'AM' => 'Amazonas',
    'BA' => 'Bahia',
    'CE' => 'Ceará',
    'DF' => 'Distrito Federal',
    'ES' => 'Espírito Santo',
    'GO' => 'Goiás',
    'MA' => 'Maranhão',
    'MT' => 'Mato Grosso',
    'MS' => 'Mato Grosso do Sul',
    'MG' => 'Minas Gerais',
    'PA' => 'Pará',
    'PB' => 'Paraíba',
    'PR' => 'Paraná',
    'PE' => 'Pernambuco',
    'PI' => 'Piauí',
    'RJ' => 'Rio de Janeiro',
    'RN' => 'Rio Grande do Norte',
    'RS' => 'Rio Grande do Sul',
    'RO' => 'Rondônia',
    'RR' => 'Roraima',
    'SC' => 'Santa Catarina',
    'SP' => 'São Paulo',
    'SE' => 'Sergipe',
    'TO' => 'Tocantins'
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Perfil — TGA Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Google Tag Manager -->
    <script>
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','GTM-K2XFNTVZ');
    </script>
    <!-- End Google Tag Manager -->


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

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
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

            --sidebar-open: 280px;
            --sidebar-collapsed: 84px;
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

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { overflow-x: hidden; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
        }

        .admin-layout { display: flex; min-height: 100vh; }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-open);
            background: var(--bg-card);
            border-right: 1px solid var(--border);
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: width .25s ease;
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

        .sidebar-toggle{
            margin-left: auto;
            width: 40px; height: 40px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text);
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: .2s ease;
        }
        .sidebar-toggle:hover{
            background: var(--primary);
            border-color: var(--primary);
            transform: scale(1.05);
        }

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
            color: var(--text-sec);
            text-decoration: none;
            font-weight: 500; font-size: .95rem;
            transition: var(--transition);
            border-left: 3px solid transparent;
            position: relative;
        }
        .nav-item:hover  { background: var(--surface); color: var(--text); border-left-color: var(--primary); }
        .nav-item.active { background: var(--surface); color: var(--primary); border-left-color: var(--primary); font-weight: 700; }

        .nav-icon { font-size: 1.25rem; width: 24px; text-align: center; }
        .nav-item.danger { color: #fff; background: rgba(239,68,68,.12); border-left-color: rgba(239,68,68,.35); }
        .nav-item.danger:hover { background: rgba(239,68,68,.22); border-left-color: var(--danger); }

        /* Conteúdo */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-open);
            padding: 2rem;
            transition: margin-left .25s ease;
        }

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

        /* Card */
        .card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            margin-bottom: 1.25rem;
            overflow: hidden;
        }
        .card-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 1rem;
        }
        .card-title { font-size: 1.25rem; font-weight: 800; color: var(--text); }

        .card-body { padding: 1.5rem 2rem; }

        /* Alert */
        .alert {
            padding: 1rem 1.5rem; border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: 1rem;
            animation: slideDown .3s;
            border: 1px solid transparent;
        }
        .alert-success { background: rgba(16,185,129,.12); border-color: rgba(16,185,129,.30); color: var(--success); }
        .alert-error   { background: rgba(239,68,68,.12);  border-color: rgba(239,68,68,.30);  color: var(--danger); }
        .alert-warning { background: rgba(245,158,11,.12); border-color: rgba(245,158,11,.30); color: var(--warning); }
        .alert-icon    { font-size: 1.3rem; }

        /* Perfil header */
        .profile-head{
            display:flex;
            gap: 1rem;
            align-items:center;
            padding: 1.25rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
        }
        .avatar{
            width: 56px; height: 56px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display:flex;
            align-items:center;
            justify-content:center;
            color:#fff;
            font-weight: 900;
            font-size: 1.1rem;
            flex: 0 0 auto;
        }
        .profile-meta{ min-width:0; }
        .profile-name{
            font-size: 1.1rem;
            font-weight: 900;
            color: var(--text);
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .profile-sub{
            margin-top: .2rem;
            color: var(--text-sec);
            font-size: .9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Grid */
        .grid{
            display:grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 1.25rem;
            margin-top: 1.25rem;
        }

        /* Form */
        .form{
            display:grid;
            gap: 12px;
        }
        .row{
            display:grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .field label{
            display:block;
            font-size: .75rem;
            color: var(--text-muted);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .6px;
            margin-bottom: .35rem;
        }
        .input, .select{
            width:100%;
            padding: 0.75rem 0.9rem;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--bg-input);
            color: var(--text);
            outline: none;
            transition: var(--transition);
            font-family: inherit;
            font-size: 0.95rem;
        }
        .select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
        }
        .input:focus, .select:focus{
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,102,255,.12);
        }
        .input:disabled, .select:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .hint{
            margin-top: 6px;
            color: var(--text-muted);
            font-size: .85rem;
            line-height: 1.35;
        }

        /* Buttons */
        .btn{
            padding: .75rem 1.2rem;
            border-radius: var(--radius-sm);
            font-weight: 800;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            transition: var(--transition);
            text-decoration: none;
            justify-content: center;
            white-space: nowrap;
        }
        .btn-primary{
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            box-shadow: 0 4px 12px rgba(0,102,255,.25);
        }
        .btn-primary:hover{ transform: translateY(-1px); box-shadow: 0 8px 20px rgba(0,102,255,.35); }

        .btn-ghost{
            background: var(--surface);
            color: var(--text-sec);
            border: 1px solid var(--border);
        }
        .btn-ghost:hover{ background: var(--border); color: var(--text); }

        .actions{ display:flex; gap: .6rem; flex-wrap: wrap; margin-top: .35rem; }

        /* Chips */
        .chip{
            display:inline-flex;
            align-items:center;
            gap: .5rem;
            padding: .5rem .8rem;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,.06);
            color: var(--text-sec);
            font-size: .85rem;
            font-weight: 700;
        }

        /* Loading */
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid var(--text-muted);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Sidebar colapsada */
        .admin-layout.sidebar-collapsed .sidebar { width: var(--sidebar-collapsed); }
        .admin-layout.sidebar-collapsed .main-content { margin-left: var(--sidebar-collapsed); }
        .admin-layout.sidebar-collapsed .logo-text,
        .admin-layout.sidebar-collapsed .nav-section-title,
        .admin-layout.sidebar-collapsed .nav-item span:not(.nav-icon) { display: none; }

        .admin-layout.sidebar-collapsed .nav-item{
            justify-content: center;
            padding: 0.95rem 0;
            gap: 0;
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
        .admin-layout.sidebar-collapsed .nav-item:hover::after{ opacity: 1; }

        /* Responsivo */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
        }
        @media (max-width: 880px) {
            .grid{ grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .main-content{ padding: 1rem; }
            .row{ grid-template-columns: 1fr; }
        }

        @keyframes slideDown { from { opacity:0; transform: translateY(-10px);} to { opacity:1; transform: translateY(0);} }
    </style>
</head>

<body>
    <!-- Google Tag Manager (noscript) -->
    <noscript>
        <iframe src="https://www.googletagmanager.com/ns.html?id=GTM-K2XFNTVZ" height="0" width="0" style="display:none;visibility:hidden"></iframe>
    </noscript>
    <!-- End Google Tag Manager (noscript) -->

    <div class="admin-layout" id="adminLayout">

        <!-- ===== SIDEBAR ===== -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">⚙️</div>
                    <div class="logo-text">
                        <h1>TGA Admin</h1>
                        <p>Developer Panel</p>
                    </div>

                    <button class="sidebar-toggle" id="sidebarToggle" type="button" title="Ocultar/Mostrar menu">☰</button>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Principal</div>

                    <a href="index.php" class="nav-item" data-label="Dashboard">
                        <span class="nav-icon">📊</span>
                        <span>Dashboard</span>
                    </a>

                    <a href="vagas.php" class="nav-item" data-label="Vagas">
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

                    <a href="perfil.php" class="nav-item active" data-label="Perfil">
                        <span class="nav-icon">👤</span>
                        <span>Perfil</span>
                    </a>

                    <a href="logout.php" class="nav-item danger" data-label="Sair">
                        <span class="nav-icon">🚪</span>
                        <span>Sair</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- ===== MAIN ===== -->
        <main class="main-content">

            <div class="page-header">
                <div class="page-title">
                    <h2>Meu Perfil</h2>
                    <p>Atualize seus dados e altere sua senha quando necessário.</p>
                </div>

                <div style="display:flex; gap:.6rem; align-items:center;">
                    <span class="chip" title="Usuário logado">
                        🔐 <strong><?= h($email ?: 'admin') ?></strong>
                    </span>
                    <button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema">
                        <span id="theme-icon">☀️</span>
                    </button>
                </div>
            </div>

            <?php if ($alert): ?>
                <div class="alert <?= $alert['type'] === 'success' ? 'alert-success' : ($alert['type'] === 'error' ? 'alert-error' : 'alert-warning') ?>">
                    <span class="alert-icon">
                        <?= $alert['type'] === 'success' ? '✅' : ($alert['type'] === 'error' ? '❌' : '⚠️') ?>
                    </span>
                    <span><?= h($alert['msg']) ?></span>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Identidade</div>
                    </div>
                    <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
                        <span class="chip">🆔 #<?= (int)$adminId ?></span>
                        <span class="chip">👤 <?= h($tipoUser) ?></span>
                        <span class="chip"><?= $ativo ? '🟢 Ativo' : '🔴 Inativo' ?></span>
                        <span class="chip"><?= $isAdmin ? '⭐ Admin' : '👀 Usuário' ?></span>
                    </div>
                </div>

                <div class="card-body">
                    <div class="profile-head">
                        <div class="avatar"><?= h($iniciais) ?></div>
                        <div class="profile-meta">
                            <p class="profile-name"><?= h($nome) ?></p>
                            <p class="profile-sub">
                                <?= h($email) ?>
                                <?php if ($telefone): ?> • <?= h($telefoneFormatado) ?><?php endif; ?>
                                <?php if ($cidade || $estado): ?> • <?= h(trim($cidade . ($cidade && $estado ? ' / ' : '') . $estado)) ?><?php endif; ?>
                            </p>
                            <p class="hint" style="margin-top:.5rem;">
                                Cadastro: <?= h($dataCadastroFormatada) ?> • Último login: <?= h($ultimoLoginFormatado) ?>
                            </p>
                        </div>
                    </div>

                    <div class="grid">

                        <!-- DADOS DO PERFIL -->
                        <div class="card" style="margin-bottom:0;">
                            <div class="card-header">
                                <div class="card-title">Dados do Perfil</div>
                            </div>
                            <div class="card-body">
                                <form class="form" method="post" autocomplete="off">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="action" value="update_profile">

                                    <div class="row">
                                        <div class="field">
                                            <label>Nome</label>
                                            <input class="input" type="text" name="nome" value="<?= h($nome) ?>" placeholder="Seu nome completo" required>
                                        </div>
                                        <div class="field">
                                            <label>E-mail (login)</label>
                                            <input class="input" type="email" name="email" value="<?= h($email) ?>" placeholder="email@dominio.com" required>
                                            <div class="hint">O e-mail é usado como login.</div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="field">
                                            <label>Telefone</label>
                                            <input class="input" type="text" name="telefone" id="telefone" value="<?= h($telefone) ?>" placeholder="(DD) 9xxxx-xxxx">
                                        </div>
                                        <div class="field">
                                            <label>Estado</label>
                                            <select class="select" name="estado" id="estado">
                                                <option value="">Selecione um estado</option>
                                                <?php foreach ($estadosBrasileiros as $uf => $nomeEstado): ?>
                                                    <option value="<?= h($uf) ?>" <?= $estado === $uf ? 'selected' : '' ?>>
                                                        <?= h($uf) ?> - <?= h($nomeEstado) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="hint">Selecione o estado</div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="field">
                                            <label>Cidade</label>
                                            <select class="select" name="cidade" id="cidade" <?= empty($estado) ? 'disabled' : '' ?>>
                                                <option value=""><?= empty($estado) ? 'Selecione um estado primeiro' : 'Carregando cidades...' ?></option>
                                                <?php if (!empty($cidade)): ?>
                                                    <option value="<?= h($cidade) ?>" selected><?= h($cidade) ?></option>
                                                <?php endif; ?>
                                            </select>
                                            <div class="hint" id="cidadeHint">Selecione o estado para carregar as cidades</div>
                                        </div>
                                        <div class="field">
                                            <label>&nbsp;</label>
                                            <div class="hint" style="padding: 0.75rem 0;">
                                                <span id="cidadeStatus"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="actions">
                                        <button class="btn btn-primary" type="submit">💾 Salvar dados</button>
                                        <button class="btn btn-ghost" type="button" onclick="location.reload()">↻ Recarregar</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- SEGURANÇA -->
                        <div class="card" style="margin-bottom:0;">
                            <div class="card-header">
                                <div class="card-title">Segurança</div>
                                <span class="chip" title="Recomendação">🛡️ Senha forte</span>
                            </div>
                            <div class="card-body">
                                <form class="form" method="post" autocomplete="off">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="action" value="update_password">

                                    <div class="field">
                                        <label>Senha atual</label>
                                        <input class="input" type="password" name="senha_atual" placeholder="Digite sua senha atual" required>
                                    </div>

                                    <div class="field">
                                        <label>Nova senha</label>
                                        <input class="input" type="password" name="senha_nova" placeholder="Mínimo 8 caracteres" minlength="8" required>
                                    </div>

                                    <div class="field">
                                        <label>Confirmar nova senha</label>
                                        <input class="input" type="password" name="senha_nova2" placeholder="Repita a nova senha" minlength="8" required>
                                    </div>

                                    <div class="hint">
                                        Dica: use letras maiúsculas, minúsculas, números e símbolos.
                                    </div>

                                    <div class="actions">
                                        <button class="btn btn-primary" type="submit">🔒 Alterar senha</button>
                                    </div>

                                    <div class="hint" style="margin-top:.8rem;">
                                        <small>✓ Senhas são armazenadas com hash seguro (bcrypt)</small>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div><!-- /grid -->
                </div>
            </div>

            <div style="margin-top:.75rem; color: var(--text-muted); font-size:.85rem;">
                © <?= date('Y') ?> • TGA Carreiras — Perfil do Admin
            </div>

        </main>
    </div>

    <script>
        "use strict";

        // Tema (dark/light)
        function toggleTheme() {
            const html = document.documentElement;
            const icon = document.getElementById('theme-icon');
            const current = html.getAttribute('data-theme') || 'dark';
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            icon.textContent = next === 'light' ? '🌙' : '☀️';
            localStorage.setItem('theme', next);
        }

        // Carregar cidades via API do IBGE
        async function carregarCidades(uf) {
            const cidadeSelect = document.getElementById('cidade');
            const cidadeHint = document.getElementById('cidadeHint');
            const cidadeStatus = document.getElementById('cidadeStatus');
            
            if (!uf) {
                cidadeSelect.innerHTML = '<option value="">Selecione um estado primeiro</option>';
                cidadeSelect.disabled = true;
                cidadeHint.textContent = 'Selecione o estado para carregar as cidades';
                cidadeStatus.innerHTML = '';
                return;
            }

            cidadeSelect.disabled = true;
            cidadeSelect.innerHTML = '<option value="">Carregando cidades...</option>';
            cidadeStatus.innerHTML = '<span class="loading"></span> Carregando...';

            try {
                // Usando a API do IBGE (versão mais estável)
                const response = await fetch(`https://servicodados.ibge.gov.br/api/v1/localidades/estados/${uf}/municipios`);
                
                if (!response.ok) {
                    throw new Error('Erro ao carregar cidades');
                }
                
                const cidades = await response.json();
                
                // Ordena cidades por nome
                cidades.sort((a, b) => a.nome.localeCompare(b.nome));
                
                // Monta o select
                let options = '<option value="">Selecione uma cidade</option>';
                
                <?php if (!empty($cidade)): ?>
                const cidadeAtual = '<?= h($cidade) ?>';
                <?php else: ?>
                const cidadeAtual = '';
                <?php endif; ?>
                
                cidades.forEach(cidade => {
                    const selected = (cidadeAtual && cidade.nome === cidadeAtual) ? 'selected' : '';
                    options += `<option value="${cidade.nome}" ${selected}>${cidade.nome}</option>`;
                });
                
                cidadeSelect.innerHTML = options;
                cidadeSelect.disabled = false;
                cidadeHint.textContent = 'Selecione sua cidade';
                cidadeStatus.innerHTML = '✅ ' + cidades.length + ' cidades carregadas';
                
            } catch (error) {
                console.error('Erro ao carregar cidades:', error);
                cidadeSelect.innerHTML = `
                    <option value="">Erro ao carregar cidades</option>
                    <option value="${cidadeAtual || ''}" ${cidadeAtual ? 'selected' : ''}>${cidadeAtual || 'Digite manualmente'}</option>
                `;
                cidadeSelect.disabled = false;
                cidadeHint.innerHTML = '⚠️ API indisponível. <a href="#" onclick="ativarInputCidade(); return false;">Clique aqui para digitar manualmente</a>';
                cidadeStatus.innerHTML = '❌ Erro na API';
            }
        }

        // Fallback para input manual caso a API falhe
        function ativarInputCidade() {
            const cidadeSelect = document.getElementById('cidade');
            const cidadeAtual = '<?= h($cidade) ?>';
            
            // Substitui select por input
            const container = cidadeSelect.parentNode;
            const inputHtml = `
                <input type="text" class="input" name="cidade" id="cidadeInput" value="${cidadeAtual}" placeholder="Digite sua cidade">
                <div class="hint">Digite manualmente o nome da sua cidade</div>
            `;
            
            // Remove o select e adiciona input
            cidadeSelect.style.display = 'none';
            container.innerHTML += inputHtml;
            
            // Remove o hint antigo
            const oldHint = document.getElementById('cidadeHint');
            if (oldHint) oldHint.style.display = 'none';
        }

        // Inicialização
        window.addEventListener('DOMContentLoaded', () => {
            // Tema
            const saved = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', saved);
            const icon = document.getElementById('theme-icon');
            if (icon) icon.textContent = saved === 'light' ? '🌙' : '☀️';

            // Sidebar persistente
            const layout = document.getElementById('adminLayout');
            const btn = document.getElementById('sidebarToggle');
            const storageKey = 'admin_sidebar';

            if (layout && btn) {
                const savedSidebar = localStorage.getItem(storageKey) || 'open';
                if (savedSidebar === 'collapsed') layout.classList.add('sidebar-collapsed');

                btn.addEventListener('click', () => {
                    layout.classList.toggle('sidebar-collapsed');
                    localStorage.setItem(storageKey, layout.classList.contains('sidebar-collapsed') ? 'collapsed' : 'open');
                });
            }

            // Auto-hide alert
            setTimeout(() => {
                document.querySelectorAll('.alert').forEach(el => {
                    el.style.transition = 'opacity .3s';
                    el.style.opacity = '0';
                    setTimeout(() => el.remove(), 320);
                });
            }, 5000);

            // Telefone mask
            const telefoneInput = document.getElementById('telefone');
            if (telefoneInput) {
                telefoneInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 11) value = value.slice(0, 11);
                    
                    if (value.length > 0) {
                        if (value.length <= 10) {
                            value = value.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
                        } else {
                            value = value.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
                        }
                    }
                    e.target.value = value;
                });
            }

            // Estado select change - carrega cidades
            const estadoSelect = document.getElementById('estado');
            if (estadoSelect) {
                estadoSelect.addEventListener('change', function(e) {
                    carregarCidades(e.target.value);
                });
                
                // Se já tem estado selecionado, carrega cidades
                <?php if (!empty($estado)): ?>
                carregarCidades('<?= h($estado) ?>');
                <?php endif; ?>
            }
        });
    </script>

</body>
</html>