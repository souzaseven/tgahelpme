<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['empresa_id'])) {
    header('Location: login.php?msg=' . urlencode('Faca login para continuar.') . '&tipo=error');
    exit;
}

require_once __DIR__ . '/../backend/conexao.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$empresaId = (int)$_SESSION['empresa_id'];

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function fPhone($p) {
    $p = preg_replace('/[^0-9]/', '', (string)$p);
    if (strlen($p) === 11) return '(' . substr($p,0,2) . ') ' . substr($p,2,5) . '-' . substr($p,7);
    if (strlen($p) === 10) return '(' . substr($p,0,2) . ') ' . substr($p,2,4) . '-' . substr($p,6);
    return $p;
}
function fDate($d) {
    if (empty($d) || $d === '0000-00-00 00:00:00') return '-';
    $t = strtotime($d); return $t ? date('d/m/Y H:i', $t) : '-';
}
function fCNPJ($c) {
    $c = preg_replace('/[^0-9]/', '', (string)$c);
    if (strlen($c) === 14)
        return substr($c,0,2).'.'.substr($c,2,3).'.'.substr($c,5,3).'/'.substr($c,8,4).'-'.substr($c,12);
    return $c;
}

$empresa = null;
try {
    $st = $pdo->prepare("
        SELECT id, nome_empresa, razao_social, cnpj, setor, porte, responsavel,
               email, telefone, estado, cidade, senha, descricao, site, logo,
               status, data_cadastro
        FROM empresas_carreiras
        WHERE id = ? AND status = 'ativa'
        LIMIT 1
    ");
    $st->execute([$empresaId]);
    $empresa = $st->fetch(PDO::FETCH_ASSOC);
    if (!$empresa) {
        session_destroy();
        header('Location: login.php?msg=' . urlencode('Empresa nao encontrada. Faca login novamente.') . '&tipo=error');
        exit;
    }
} catch (Throwable $e) {
    error_log('perfil empresa: ' . $e->getMessage());
    die('Erro ao carregar perfil. Contate o suporte.');
}

$tipoAlert = strtolower(trim($_GET['tipo'] ?? ''));
$msgAlert  = trim($_GET['msg'] ?? '');
$alert = null;
if ($msgAlert !== '') {
    if (!in_array($tipoAlert, ['success','error','warning'], true)) $tipoAlert = 'warning';
    $alert = ['type' => $tipoAlert, 'msg' => $msgAlert];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf, $postedCsrf)) {
        header('Location: perfil.php?msg=Falha+CSRF&tipo=error'); exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $nome_empresa = trim($_POST['nome_empresa'] ?? '');
        $razao_social = trim($_POST['razao_social'] ?? '');
        $cnpj         = preg_replace('/\D/', '', $_POST['cnpj'] ?? '');
        $setor        = trim($_POST['setor']        ?? '');
        $porte        = trim($_POST['porte']        ?? '');
        $responsavel  = trim($_POST['responsavel']  ?? '');
        $email        = trim($_POST['email']        ?? '');
        $telefone     = trim($_POST['telefone']     ?? '');
        $estado       = trim($_POST['estado']       ?? '');
        $cidade       = trim($_POST['cidade']       ?? '');
        $site         = trim($_POST['site']         ?? '');
        $logo         = trim($_POST['logo']         ?? '');
        $descricao    = trim($_POST['descricao']    ?? '');

        if ($nome_empresa === '') { header('Location: perfil.php?msg=' . urlencode('Informe o nome da empresa.') . '&tipo=warning'); exit; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { header('Location: perfil.php?msg=' . urlencode('E-mail invalido.') . '&tipo=warning'); exit; }
        if ($cnpj !== '' && strlen($cnpj) !== 14) { header('Location: perfil.php?msg=' . urlencode('CNPJ invalido.') . '&tipo=warning'); exit; }

        try {
            $chk = $pdo->prepare("SELECT id FROM empresas_carreiras WHERE email = ? AND id != ? LIMIT 1");
            $chk->execute([$email, $empresaId]);
            if ($chk->fetch()) { header('Location: perfil.php?msg=' . urlencode('E-mail ja em uso.') . '&tipo=warning'); exit; }

            $up = $pdo->prepare("UPDATE empresas_carreiras SET
                nome_empresa=:ne, razao_social=:rs, cnpj=:cnpj, setor=:st, porte=:po,
                responsavel=:re, email=:em, telefone=:te, estado=:es, cidade=:ci,
                site=:si, logo=:lo, descricao=:de
                WHERE id=:id LIMIT 1");
            $up->execute([
                ':ne'=>$nome_empresa,':rs'=>$razao_social?:null,':cnpj'=>$cnpj?:null,
                ':st'=>$setor?:null,':po'=>$porte?:null,':re'=>$responsavel?:null,
                ':em'=>$email,':te'=>$telefone?:null,':es'=>$estado?:null,
                ':ci'=>$cidade?:null,':si'=>$site?:null,':lo'=>$logo?:null,
                ':de'=>$descricao?:null,':id'=>$empresaId,
            ]);
            $_SESSION['empresa_nome'] = $nome_empresa;
            header('Location: perfil.php?msg=' . urlencode('Perfil atualizado com sucesso!') . '&tipo=success'); exit;
        } catch (Throwable $e) {
            error_log($e->getMessage());
            header('Location: perfil.php?msg=' . urlencode('Erro ao salvar.') . '&tipo=error'); exit;
        }
    }

    if ($action === 'update_password') {
        $sa = (string)($_POST['senha_atual'] ?? '');
        $sn = (string)($_POST['senha_nova']  ?? '');
        $s2 = (string)($_POST['senha_nova2'] ?? '');
        if ($sa===''||$sn===''||$s2==='') { header('Location: perfil.php?msg=' . urlencode('Preencha todos os campos de senha.') . '&tipo=warning'); exit; }
        if ($sn !== $s2)                  { header('Location: perfil.php?msg=' . urlencode('Confirmacao nao confere.') . '&tipo=warning'); exit; }
        if (strlen($sn) < 6)             { header('Location: perfil.php?msg=' . urlencode('Senha minimo 6 caracteres.') . '&tipo=warning'); exit; }
        try {
            $sp = $pdo->prepare("SELECT senha FROM empresas_carreiras WHERE id=? LIMIT 1");
            $sp->execute([$empresaId]);
            $hash = (string)($sp->fetchColumn() ?: '');
            if (!password_verify($sa, $hash)) { header('Location: perfil.php?msg=' . urlencode('Senha atual incorreta.') . '&tipo=error'); exit; }
            $pdo->prepare("UPDATE empresas_carreiras SET senha=? WHERE id=? LIMIT 1")->execute([password_hash($sn, PASSWORD_DEFAULT), $empresaId]);
            header('Location: perfil.php?msg=' . urlencode('Senha alterada com sucesso!') . '&tipo=success'); exit;
        } catch (Throwable $e) {
            error_log($e->getMessage());
            header('Location: perfil.php?msg=' . urlencode('Erro ao alterar senha.') . '&tipo=error'); exit;
        }
    }
    header('Location: perfil.php?msg=Acao+invalida&tipo=error'); exit;
}

$nome_empresa = (string)($empresa['nome_empresa'] ?? '');
$razao_social = (string)($empresa['razao_social'] ?? '');
$cnpj         = (string)($empresa['cnpj']         ?? '');
$setor        = (string)($empresa['setor']        ?? '');
$porte        = (string)($empresa['porte']        ?? '');
$responsavel  = (string)($empresa['responsavel']  ?? '');
$email        = (string)($empresa['email']        ?? '');
$telefone     = (string)($empresa['telefone']     ?? '');
$estado       = (string)($empresa['estado']       ?? '');
$cidade       = (string)($empresa['cidade']       ?? '');
$site         = (string)($empresa['site']         ?? '');
$logo         = (string)($empresa['logo']         ?? '');
$descricao    = (string)($empresa['descricao']    ?? '');
$dataCad      = (string)($empresa['data_cadastro']?? '');

$cnpjFmt = fCNPJ($cnpj);
$telFmt  = fPhone($telefone);
$datFmt  = fDate($dataCad);

$partes = preg_split('/\s+/', trim($nome_empresa ?: $email));
$iniciais = mb_strtoupper(mb_substr($partes[0]??'E',0,1,'UTF-8'),'UTF-8');
if (count($partes)>1) $iniciais .= mb_strtoupper(mb_substr(end($partes),0,1,'UTF-8'),'UTF-8');

$portes = ['MEI'=>'MEI','ME'=>'Micro Empresa','EPP'=>'Pequena Empresa','MEDIO'=>'Medio Porte','GRANDE'=>'Grande Porte','MULTINACIONAL'=>'Multinacional'];
$estados = ['AC'=>'Acre','AL'=>'Alagoas','AP'=>'Amapa','AM'=>'Amazonas','BA'=>'Bahia','CE'=>'Ceara','DF'=>'Distrito Federal','ES'=>'Espirito Santo','GO'=>'Goias','MA'=>'Maranhao','MT'=>'Mato Grosso','MS'=>'Mato Grosso do Sul','MG'=>'Minas Gerais','PA'=>'Para','PB'=>'Paraiba','PR'=>'Parana','PE'=>'Pernambuco','PI'=>'Piaui','RJ'=>'Rio de Janeiro','RN'=>'Rio Grande do Norte','RS'=>'Rio Grande do Sul','RO'=>'Rondonia','RR'=>'Roraima','SC'=>'Santa Catarina','SP'=>'Sao Paulo','SE'=>'Sergipe','TO'=>'Tocantins'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Perfil da Empresa — TGA Carreiras</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','GTM-K2XFNTVZ');</script>
<link rel="icon" href="http://tgameajuda.com/Projetos/tgacarreiras/img/icone_logo.ico" type="image/x-icon">
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044" crossorigin="anonymous"></script>
<script async src="https://www.googletagmanager.com/gtag/js?id=G-S8EC5C2WTG"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-S8EC5C2WTG');</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--p:#7C3AED;--pd:#6D28D9;--bg:#0F172A;--bgc:#1E293B;--bgi:#0F172A;--sur:#334155;--tx:#F1F5F9;--txs:#CBD5E1;--txm:#64748B;--brd:#334155;--ok:#10B981;--wa:#F59E0B;--er:#EF4444;--sh:0 4px 12px rgba(0,0,0,.3);--tr:all .2s ease;--sw:280px;--sc:84px;}
[data-theme=light]{--bg:#FAFBFC;--bgc:#F5F7FA;--bgi:#fff;--sur:#EDF0F5;--tx:#1A202C;--txs:#4A5568;--txm:#718096;--brd:#E2E8F0;--sh:0 4px 12px rgba(0,0,0,.07);}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}html,body{overflow-x:hidden;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--tx);line-height:1.6;}
.lay{display:flex;min-height:100vh;}
.sb{width:var(--sw);background:var(--bgc);border-right:1px solid var(--brd);padding:2rem 0;position:fixed;height:100vh;overflow-y:auto;z-index:1000;transition:width .25s;}
.sb-hd{padding:0 1.5rem 2rem;border-bottom:1px solid var(--brd);}
.logo{display:flex;align-items:center;gap:.75rem;}
.logo-ico{width:45px;height:45px;background:linear-gradient(135deg,var(--p),var(--pd));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;}
.logo-txt h1{font-size:1.05rem;font-weight:800;color:var(--tx);line-height:1.2;}
.logo-txt p{font-size:.68rem;color:var(--txm);font-weight:700;text-transform:uppercase;letter-spacing:.5px;}
.sbtgl{margin-left:auto;width:36px;height:36px;border-radius:10px;border:1px solid var(--brd);background:var(--bgc);color:var(--tx);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.2s;}
.sbtgl:hover{background:var(--p);border-color:var(--p);}
.nav-sec{padding:1rem 0;border-bottom:1px solid var(--brd);}
.nav-sec:last-child{border-bottom:none;}
.nav-lbl{padding:0 1.5rem;font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--txm);margin-bottom:.4rem;display:block;}
.na{display:flex;align-items:center;gap:1rem;padding:.8rem 1.5rem;color:var(--txs);text-decoration:none;font-weight:500;font-size:.9rem;border-left:3px solid transparent;transition:var(--tr);position:relative;}
.na:hover{background:var(--sur);color:var(--tx);border-left-color:var(--p);}
.na.active{background:var(--sur);color:var(--p);border-left-color:var(--p);font-weight:700;}
.na.danger{color:#fff;background:rgba(239,68,68,.1);border-left-color:rgba(239,68,68,.3);}
.na.danger:hover{background:rgba(239,68,68,.2);border-left-color:var(--er);}
.ni{font-size:1.1rem;width:22px;text-align:center;}
.main{flex:1;margin-left:var(--sw);padding:2rem;transition:margin-left .25s;}
.phd{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;}
.phd h2{font-size:1.8rem;font-weight:800;margin-bottom:.2rem;}
.phd p{color:var(--txs);font-size:.9rem;}
.btnth{width:42px;height:42px;border:2px solid var(--brd);border-radius:10px;background:var(--bgc);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.2rem;transition:var(--tr);}
.btnth:hover{background:var(--p);border-color:var(--p);}
.alert{padding:1rem 1.5rem;border-radius:12px;margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;border:1px solid transparent;animation:si .3s;}
.alert-success{background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:var(--ok);}
.alert-error{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.3);color:var(--er);}
.alert-warning{background:rgba(245,158,11,.1);border-color:rgba(245,158,11,.3);color:var(--wa);}
.card{background:var(--bgc);border-radius:16px;border:1px solid var(--brd);box-shadow:var(--sh);margin-bottom:1.25rem;overflow:hidden;}
.chd{padding:1.2rem 1.75rem;border-bottom:1px solid var(--brd);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.6rem;}
.ctit{font-size:1.05rem;font-weight:800;}
.cbd{padding:1.5rem 1.75rem;}
.pstrip{display:flex;gap:1rem;align-items:center;padding:1.2rem;background:var(--sur);border-radius:14px;border:1px solid var(--brd);margin-bottom:1.5rem;}
.av{width:68px;height:68px;border-radius:14px;background:linear-gradient(135deg,var(--p),var(--pd));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;font-size:1.3rem;flex:0 0 auto;overflow:hidden;}
.av img{width:100%;height:100%;object-fit:cover;border-radius:14px;}
.pname{font-size:1.05rem;font-weight:800;}
.psub{color:var(--txs);font-size:.86rem;margin:.2rem 0;}
.pbdg{display:flex;flex-wrap:wrap;gap:.3rem;margin-top:.4rem;}
.g2{display:grid;grid-template-columns:1.1fr .9fr;gap:1.25rem;}
.form{display:grid;gap:14px;}
.r2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.field label{display:block;font-size:.71rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--txm);margin-bottom:.3rem;}
.inp,.sel,.txa{width:100%;padding:.72rem .9rem;border-radius:10px;border:1px solid var(--brd);background:var(--bgi);color:var(--tx);font-family:inherit;font-size:.92rem;outline:none;transition:var(--tr);}
.txa{min-height:120px;resize:vertical;}
.sel{cursor:pointer;-webkit-appearance:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .9rem center;background-size:1em;}
.inp:focus,.sel:focus,.txa:focus{border-color:var(--p);box-shadow:0 0 0 3px rgba(124,58,237,.14);}
.hint{font-size:.81rem;color:var(--txm);margin-top:5px;}
.btn{padding:.72rem 1.2rem;border-radius:8px;font-weight:800;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem;transition:var(--tr);text-decoration:none;white-space:nowrap;}
.btnp{background:linear-gradient(135deg,var(--p),var(--pd));color:#fff;box-shadow:0 4px 14px rgba(124,58,237,.3);}
.btnp:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(124,58,237,.4);}
.btng{background:var(--sur);color:var(--txs);border:1px solid var(--brd);}
.btng:hover{background:var(--brd);color:var(--tx);}
.acts{display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.5rem;}
.chip{display:inline-flex;align-items:center;gap:.35rem;padding:.3rem .65rem;border-radius:999px;border:1px solid var(--brd);background:rgba(255,255,255,.05);color:var(--txs);font-size:.8rem;font-weight:700;}
.chipv{border-color:rgba(124,58,237,.4);background:rgba(124,58,237,.1);color:#a78bfa;}
.sbar{height:4px;border-radius:4px;background:var(--brd);margin-top:6px;overflow:hidden;}
.sfill{height:100%;width:0;border-radius:4px;transition:.3s;}
.ld{display:inline-block;width:13px;height:13px;border:2px solid var(--brd);border-radius:50%;border-top-color:var(--p);animation:sp 1s linear infinite;}
@keyframes sp{to{transform:rotate(360deg);}}
@keyframes si{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);}}
.lay.col .sb{width:var(--sc);}
.lay.col .main{margin-left:var(--sc);}
.lay.col .logo-txt,.lay.col .nav-lbl,.lay.col .na span:not(.ni){display:none;}
.lay.col .na{justify-content:center;padding:.9rem 0;}
.lay.col .na::after{content:attr(data-tip);position:absolute;left:calc(100% + 8px);top:50%;transform:translateY(-50%);background:#111827;color:#fff;padding:5px 10px;border-radius:8px;font-size:.77rem;white-space:nowrap;opacity:0;pointer-events:none;transition:.2s;}
.lay.col .na:hover::after{opacity:1;}
@media(max-width:960px){.sb{transform:translateX(-100%);}.main{margin-left:0;}}
@media(max-width:820px){.g2{grid-template-columns:1fr;}}
@media(max-width:640px){.main{padding:1rem;}.r2{grid-template-columns:1fr;}}
</style>
</head>
<body>
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-K2XFNTVZ" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>

<div class="lay" id="appLayout">
<aside class="sb">
  <div class="sb-hd">
    <div class="logo">
      <div class="logo-ico">🏢</div>
      <div class="logo-txt"><h1>TGA Empresa</h1><p>Painel Empresarial</p></div>
      <button class="sbtgl" id="sbToggle" type="button">☰</button>
    </div>
  </div>
  <div class="nav-sec">
    <span class="nav-lbl">Principal</span>
    <a href="dashboard.php"  class="na" data-tip="Dashboard">  <span class="ni">📊</span><span>Dashboard</span></a>
    <a href="vagas.php"      class="na" data-tip="Vagas">       <span class="ni">💼</span><span>Minhas Vagas</span></a>
    <a href="candidatos.php" class="na" data-tip="Candidatos">  <span class="ni">👥</span><span>Candidatos</span></a>
  </div>
  <div class="nav-sec">
    <span class="nav-lbl">Configurações</span>
    <a href="perfil.php" class="na active" data-tip="Perfil"><span class="ni">🏢</span><span>Perfil da Empresa</span></a>
    <a href="logout.php" class="na danger"  data-tip="Sair">  <span class="ni">🚪</span><span>Sair</span></a>
  </div>
</aside>

<main class="main">
  <div class="phd">
    <div>
      <h2>Perfil da Empresa</h2>
      <p>Mantenha seus dados atualizados para atrair os melhores candidatos.</p>
    </div>
    <div style="display:flex;gap:.6rem;align-items:center;">
      <span class="chip chipv">🏢 <?php echo h($nome_empresa ?: $email); ?></span>
      <button class="btnth" onclick="toggleTheme()" title="Alternar tema"><span id="themeIcon">☀️</span></button>
    </div>
  </div>

<?php if ($alert): ?>
  <div class="alert alert-<?php echo h($alert['type']); ?>">
    <span><?php echo $alert['type']==='success'?'✅':($alert['type']==='error'?'❌':'⚠️'); ?></span>
    <span><?php echo h($alert['msg']); ?></span>
  </div>
<?php endif; ?>

  <div class="card">
    <div class="chd">
      <span class="ctit">Identidade da Empresa</span>
      <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
        <span class="chip">🆔 #<?php echo (int)$empresaId; ?></span>
        <?php if ($cnpj): ?><span class="chip">📋 <?php echo h($cnpjFmt); ?></span><?php endif; ?>
        <span class="chip chipv">✅ Ativa</span>
      </div>
    </div>
    <div class="cbd">

      <!-- Preview -->
      <div class="pstrip">
        <div class="av" id="avatarBox">
          <?php if ($logo): ?>
            <img src="<?php echo h($logo); ?>" alt="Logo" id="logoImg">
          <?php else: ?>
            <span id="logoIni"><?php echo h($iniciais); ?></span>
          <?php endif; ?>
        </div>
        <div>
          <div class="pname"><?php echo h($nome_empresa ?: 'Nome da Empresa'); ?></div>
          <?php if ($razao_social && $razao_social !== $nome_empresa): ?>
            <div class="psub" style="font-size:.78rem;opacity:.7;"><?php echo h($razao_social); ?></div>
          <?php endif; ?>
          <div class="psub">
            <?php echo h($email); ?>
            <?php if ($telefone): ?> &nbsp;•&nbsp; <?php echo h($telFmt); ?><?php endif; ?>
            <?php if ($cidade||$estado): ?> &nbsp;•&nbsp; <?php echo h(trim($cidade.($cidade&&$estado?' / ':'').$estado)); ?><?php endif; ?>
          </div>
          <div class="pbdg">
            <?php if ($setor):       ?><span class="chip" style="padding:.22rem .55rem;font-size:.76rem;">🏭 <?php echo h($setor); ?></span><?php endif; ?>
            <?php if ($porte):       ?><span class="chip" style="padding:.22rem .55rem;font-size:.76rem;">📏 <?php echo h($portes[$porte]??$porte); ?></span><?php endif; ?>
            <?php if ($responsavel): ?><span class="chip" style="padding:.22rem .55rem;font-size:.76rem;">👤 <?php echo h($responsavel); ?></span><?php endif; ?>
            <?php if ($site):        ?><a href="<?php echo h($site); ?>" target="_blank" class="chip" style="padding:.22rem .55rem;font-size:.76rem;text-decoration:none;">🌐 Site</a><?php endif; ?>
          </div>
          <div class="hint" style="margin-top:.35rem;">Cadastro: <?php echo h($datFmt); ?></div>
        </div>
      </div>

      <div class="g2">

        <!-- FORMULÁRIO -->
        <div class="card" style="margin-bottom:0;">
          <div class="chd"><span class="ctit">Dados da Empresa</span></div>
          <div class="cbd">
            <form class="form" method="post" autocomplete="off">
              <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
              <input type="hidden" name="action"     value="update_profile">

              <div class="r2">
                <div class="field">
                  <label>Nome Fantasia *</label>
                  <input class="inp" type="text" name="nome_empresa" value="<?php echo h($nome_empresa); ?>" placeholder="Nome da empresa" required>
                </div>
                <div class="field">
                  <label>Razão Social</label>
                  <input class="inp" type="text" name="razao_social" value="<?php echo h($razao_social); ?>" placeholder="Razão Social Ltda.">
                </div>
              </div>

              <div class="r2">
                <div class="field">
                  <label>E-mail (login) *</label>
                  <input class="inp" type="email" name="email" value="<?php echo h($email); ?>" placeholder="empresa@dominio.com" required>
                  <div class="hint">Usado para acessar o sistema.</div>
                </div>
                <div class="field">
                  <label>Responsável</label>
                  <input class="inp" type="text" name="responsavel" value="<?php echo h($responsavel); ?>" placeholder="Nome do responsável">
                </div>
              </div>

              <div class="r2">
                <div class="field">
                  <label>CNPJ</label>
                  <input class="inp" type="text" name="cnpj" id="cnpj" value="<?php echo h($cnpjFmt); ?>" placeholder="00.000.000/0001-00">
                </div>
                <div class="field">
                  <label>Telefone</label>
                  <input class="inp" type="text" name="telefone" id="telefone" value="<?php echo h($telefone); ?>" placeholder="(DD) 9xxxx-xxxx">
                </div>
              </div>

              <div class="r2">
                <div class="field">
                  <label>Setor / Segmento</label>
                  <input class="inp" type="text" name="setor" value="<?php echo h($setor); ?>" placeholder="Ex: Tecnologia, Saúde...">
                </div>
                <div class="field">
                  <label>Porte</label>
                  <select class="sel" name="porte">
                    <option value="">Selecione o porte</option>
                    <?php foreach ($portes as $k=>$lbl): ?>
                      <option value="<?php echo h($k); ?>" <?php echo $porte===$k?'selected':''; ?>><?php echo h($lbl); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div class="r2">
                <div class="field">
                  <label>Estado</label>
                  <select class="sel" name="estado" id="estado">
                    <option value="">Selecione</option>
                    <?php foreach ($estados as $uf=>$nm): ?>
                      <option value="<?php echo h($uf); ?>" <?php echo $estado===$uf?'selected':''; ?>><?php echo h($uf); ?> – <?php echo h($nm); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label>Cidade</label>
                  <select class="sel" name="cidade" id="cidade" <?php echo empty($estado)?'disabled':''; ?>>
                    <option value=""><?php echo empty($estado)?'Selecione o estado primeiro':'Carregando...'; ?></option>
                    <?php if (!empty($cidade)): ?>
                      <option value="<?php echo h($cidade); ?>" selected><?php echo h($cidade); ?></option>
                    <?php endif; ?>
                  </select>
                  <div class="hint" id="cidadeHint"></div>
                </div>
              </div>

              <div class="r2">
                <div class="field">
                  <label>Site / Website</label>
                  <input class="inp" type="text" name="site" value="<?php echo h($site); ?>" placeholder="https://suaempresa.com.br">
                </div>
                <div class="field">
                  <label>URL do Logo</label>
                  <input class="inp" type="text" name="logo" id="logoInput" value="<?php echo h($logo); ?>" placeholder="https://suaempresa.com/logo.png">
                  <div class="hint">Aparece no avatar ao lado.</div>
                </div>
              </div>

              <div class="field">
                <label>Sobre a Empresa</label>
                <textarea class="txa" name="descricao" placeholder="Missão, cultura, diferenciais..."><?php echo h($descricao); ?></textarea>
                <div class="hint">Exibido para candidatos nas suas vagas.</div>
              </div>

              <div class="acts">
                <button class="btn btnp" type="submit">💾 Salvar dados</button>
                <button class="btn btng" type="button" onclick="location.reload()">↻ Recarregar</button>
              </div>
            </form>
          </div>
        </div>

        <!-- SEGURANÇA -->
        <div class="card" style="margin-bottom:0;">
          <div class="chd"><span class="ctit">Segurança</span><span class="chip">🛡️ bcrypt</span></div>
          <div class="cbd">
            <form class="form" method="post" autocomplete="off">
              <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
              <input type="hidden" name="action"     value="update_password">
              <div class="field">
                <label>Senha atual</label>
                <input class="inp" type="password" name="senha_atual" placeholder="Sua senha atual" required>
              </div>
              <div class="field">
                <label>Nova senha</label>
                <input class="inp" type="password" name="senha_nova" id="senhaNova" placeholder="Mínimo 6 caracteres" minlength="6" required>
                <div class="sbar"><div class="sfill" id="sfill"></div></div>
                <div class="hint" id="stxt">Digite a nova senha</div>
              </div>
              <div class="field">
                <label>Confirmar nova senha</label>
                <input class="inp" type="password" name="senha_nova2" id="senhaNova2" placeholder="Repita a nova senha" minlength="6" required>
                <div class="hint" id="mtxt"></div>
              </div>
              <div class="hint">Dica: maiúsculas, minúsculas, números e símbolos.</div>
              <div class="acts">
                <button class="btn btnp" type="submit">🔒 Alterar senha</button>
              </div>
              <div class="hint" style="margin-top:.75rem;"><small>✓ Armazenada com hash bcrypt</small></div>
            </form>
          </div>
        </div>

      </div><!-- /g2 -->
    </div>
  </div>

  <div style="color:var(--txm);font-size:.82rem;margin-top:.5rem;">
    © <?php echo date('Y'); ?> • TGA Carreiras — Perfil da Empresa
  </div>
</main>
</div>

<script>
"use strict";
function toggleTheme(){
  var n=(document.documentElement.getAttribute('data-theme')||'dark')==='dark'?'light':'dark';
  document.documentElement.setAttribute('data-theme',n);
  document.getElementById('themeIcon').textContent=n==='light'?'🌙':'☀️';
  localStorage.setItem('tga_theme',n);
}
(function(){
  var l=document.getElementById('appLayout'),b=document.getElementById('sbToggle'),K='tga_esb';
  if(localStorage.getItem(K)==='col')l.classList.add('col');
  b.addEventListener('click',function(){l.classList.toggle('col');localStorage.setItem(K,l.classList.contains('col')?'col':'open');});
})();
function maskPhone(v){
  v=v.replace(/\D/g,'').slice(0,11);
  return v.length<=10?v.replace(/(\d{2})(\d{4})(\d{0,4})/,'($1) $2-$3'):v.replace(/(\d{2})(\d{5})(\d{0,4})/,'($1) $2-$3');
}
function maskCNPJ(v){
  v=v.replace(/\D/g,'').slice(0,14);
  v=v.replace(/^(\d{2})(\d)/,'$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3').replace(/\.(\d{3})(\d)/,'.$1/$2').replace(/(\d{4})(\d)/,'$1-$2');
  return v;
}
function calcForca(s){var p=0;if(s.length>=6)p++;if(s.length>=10)p++;if(/[A-Z]/.test(s))p++;if(/[0-9]/.test(s))p++;if(/[^A-Za-z0-9]/.test(s))p++;return p;}
function updForca(s){
  var f=document.getElementById('sfill'),t=document.getElementById('stxt');
  var lvl=[{c:'#EF4444',l:'Muito fraca',p:15},{c:'#F59E0B',l:'Fraca',p:30},{c:'#F59E0B',l:'Razoavel',p:55},{c:'#10B981',l:'Boa',p:78},{c:'#10B981',l:'Muito forte',p:100}];
  var pts=calcForca(s),n=lvl[Math.min(pts,4)];
  f.style.width=s.length===0?'0':n.p+'%';f.style.background=n.c;
  t.textContent=s.length===0?'Digite a nova senha':n.l;t.style.color=s.length===0?'':n.c;
}
var _cc='<?php echo h($cidade); ?>';
async function loadCidades(uf){
  var sel=document.getElementById('cidade'),hint=document.getElementById('cidadeHint');
  if(!uf){sel.innerHTML='<option value="">Selecione o estado primeiro</option>';sel.disabled=true;hint.textContent='';return;}
  sel.disabled=true;sel.innerHTML='<option value="">Carregando...</option>';hint.innerHTML='<span class="ld"></span>';
  try{
    var r=await fetch('https://servicodados.ibge.gov.br/api/v1/localidades/estados/'+uf+'/municipios');
    var d=await r.json();d.sort(function(a,b){return a.nome.localeCompare(b.nome);});
    var o='<option value="">Selecione a cidade</option>';
    d.forEach(function(c){o+='<option value="'+c.nome+'"'+(_cc&&c.nome===_cc?' selected':'')+'>'+c.nome+'</option>';});
    sel.innerHTML=o;sel.disabled=false;hint.textContent='✅ '+d.length+' cidades';
  }catch(e){
    sel.innerHTML='<option value="'+_cc+'"'+(_cc?' selected':'')+'>'+(_cc||'Digite manualmente')+'</option>';
    sel.disabled=false;hint.textContent='⚠️ API indisponivel';
  }
}
window.addEventListener('DOMContentLoaded',function(){
  var th=localStorage.getItem('tga_theme')||'dark';
  document.documentElement.setAttribute('data-theme',th);
  document.getElementById('themeIcon').textContent=th==='light'?'🌙':'☀️';
  setTimeout(function(){document.querySelectorAll('.alert').forEach(function(e){e.style.transition='opacity .3s';e.style.opacity='0';setTimeout(function(){e.remove();},320);});},5000);
  var tel=document.getElementById('telefone'),cnpj=document.getElementById('cnpj');
  if(tel)tel.addEventListener('input',function(e){e.target.value=maskPhone(e.target.value);});
  if(cnpj)cnpj.addEventListener('input',function(e){e.target.value=maskCNPJ(e.target.value);});
  var est=document.getElementById('estado');
  if(est){
    est.addEventListener('change',function(){loadCidades(this.value);});
    <?php if(!empty($estado)):?>loadCidades('<?php echo h($estado);?>');<?php endif;?>
  }
  var sn=document.getElementById('senhaNova'),sn2=document.getElementById('senhaNova2'),mt=document.getElementById('mtxt');
  if(sn)sn.addEventListener('input',function(){updForca(sn.value);});
  if(sn2&&sn)sn2.addEventListener('input',function(){
    if(!sn2.value){mt.textContent='';return;}
    mt.textContent=sn2.value===sn.value?'✅ Senhas conferem':'❌ Senhas nao conferem';
    mt.style.color=sn2.value===sn.value?'#10B981':'#EF4444';
  });
  var li=document.getElementById('logoInput'),av=document.getElementById('avatarBox');
  if(li&&av){
    li.addEventListener('input',function(){
      var u=li.value.trim();
      av.innerHTML=u?'<img src="'+u+'" alt="Logo" onerror="this.parentNode.innerHTML=\'<?php echo h($iniciais);?>\';">':'<?php echo h($iniciais);?>';
    });
  }
});
</script>
</body>
</html>