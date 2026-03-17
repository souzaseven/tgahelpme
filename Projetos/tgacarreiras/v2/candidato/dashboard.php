<?php
session_start();
if (!isset($_SESSION['candidato_id'])) { header("Location: login.php"); exit; }
require_once __DIR__ . '/../backend/conexao.php';

/* ── Dados do candidato ─────────────────────────────────── */
$candidatoId    = (int)($_SESSION['candidato_id'] ?? 0);
$candidatoEmail = trim((string)($_SESSION['candidato_email'] ?? ''));
$candidatoNome  = $_SESSION['candidato_nome'] ?? 'Candidato';
$primeiroNome   = explode(' ', $candidatoNome)[0];
$iniciais       = strtoupper(mb_substr($primeiroNome, 0, 1) . mb_substr(explode(' ', $candidatoNome)[1] ?? '', 0, 1));
if (strlen($iniciais) < 2) $iniciais = strtoupper(mb_substr($primeiroNome, 0, 2));

/* ── Detectar coluna candidato_id ───────────────────────── */
$temColCandidatoId = false;
try {
    $cols = $pdo->query("DESCRIBE candidaturas")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) { if ($col['Field'] === 'candidato_id') { $temColCandidatoId = true; break; } }
} catch (Throwable $e) {}

/* ── Buscar candidaturas ────────────────────────────────── */
$camposSELECT = "SELECT c.id AS candidatura_id, c.nome AS candidato_nome, c.email AS candidato_email,
        c.telefone, c.mensagem, c.curriculo, c.status, c.data_candidatura,
        v.id AS vaga_id, v.titulo, v.empresa, v.localizacao, v.salario, v.setor,
        v.quantidade_vagas, v.disponibilidade, v.escolaridade, v.experiencia,
        v.data_publicacao, v.imagem
        FROM candidaturas c LEFT JOIN vagas v ON c.vaga_id = v.id";

if ($candidatoId > 0) {
    $sql  = "$camposSELECT WHERE (c.candidato_id = ? OR LOWER(TRIM(c.email)) = LOWER(TRIM(?))) ORDER BY c.data_candidatura DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$candidatoId, $candidatoEmail]);
} else {
    $sql  = "$camposSELECT WHERE LOWER(TRIM(c.email)) = LOWER(TRIM(?)) ORDER BY c.data_candidatura DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$candidatoEmail]);
}
$candidaturas = $stmt->fetchAll();

foreach ($candidaturas as &$c) {
    if (empty($c['status'])) $c['status'] = 'pendente';
}
unset($c);

/* ── Estatísticas ───────────────────────────────────────── */
$total      = count($candidaturas);
$pendentes  = count(array_filter($candidaturas, fn($c) => $c['status'] === 'pendente'));
$emAnalise  = count(array_filter($candidaturas, fn($c) => $c['status'] === 'em_analise'));
$aprovados  = count(array_filter($candidaturas, fn($c) => $c['status'] === 'aprovado'));
$reprovados = count(array_filter($candidaturas, fn($c) => $c['status'] === 'reprovado'));

/* ── Vagas recomendadas ─────────────────────────────────── */
$vagasIds = array_column($candidaturas, 'vaga_id');
$ph = !empty($vagasIds) ? implode(',', array_fill(0, count($vagasIds), '?')) : '0';
$stmtRec = $pdo->prepare("SELECT * FROM vagas WHERE status='ativa'" . (!empty($vagasIds) ? " AND id NOT IN ($ph)" : "") . " ORDER BY data_publicacao DESC LIMIT 3");
$stmtRec->execute(!empty($vagasIds) ? $vagasIds : []);
$vagasRecomendadas = $stmtRec->fetchAll();

/* ── Helpers ─────────────────────────────────────────────── */
$statusMap = [
    'pendente'   => ['label'=>'Pendente',  'icon'=>'⏳', 'cls'=>'st-pendente',  'color'=>'#F59E0B'],
    'em_analise' => ['label'=>'Em Análise','icon'=>'🔍', 'cls'=>'st-analise',   'color'=>'#3B82F6'],
    'aprovado'   => ['label'=>'Aprovado',  'icon'=>'✅', 'cls'=>'st-aprovado',  'color'=>'#10B981'],
    'reprovado'  => ['label'=>'Reprovado', 'icon'=>'❌', 'cls'=>'st-reprovado', 'color'=>'#EF4444'],
];

function formatarSalarioDash(string $sal): string {
    if (!$sal || strtolower(trim($sal)) === 'a combinar') return 'A combinar';
    $limpo = str_replace(['R$','r$',' '], '', trim($sal));
    if (strpos($limpo, ',') !== false) { $limpo = str_replace('.', '', $limpo); $limpo = str_replace(',', '.', $limpo); }
    $num = (float)$limpo;
    return $num <= 0 ? 'A combinar' : 'R$ ' . number_format($num, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Dashboard — TGA Carreiras</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="http://tgameajuda.com/Projetos/tgacarreiras/img/icone_logo.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700;9..40,800&display=swap" rel="stylesheet">
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044" crossorigin="anonymous"></script>
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-S8EC5C2WTG"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','G-S8EC5C2WTG');</script>
    <style>
    /* ═══ VARIABLES ═══════════════════════════════════════ */
    :root {
        --primary:#2563EB;--primary-d:#1D4ED8;--primary-l:#60A5FA;
        --bg:#0B101B;--bg-card:#111827;--bg-raised:#1a2236;--surface:#1F2937;--surface-2:#283347;
        --text:#F0F6FC;--text-sec:#94A3B8;--text-muted:#64748B;
        --border:rgba(56,100,168,.16);--border-h:rgba(56,100,168,.35);
        --green:#10B981;--yellow:#F59E0B;--red:#EF4444;--blue:#3B82F6;--purple:#8B5CF6;--cyan:#06B6D4;
        --shadow:0 4px 20px rgba(0,0,0,.35);--shadow-lg:0 12px 40px rgba(0,0,0,.5);
        --radius:14px;--radius-sm:10px;--radius-xs:6px;
        --sidebar-w:260px;--sidebar-cw:72px;
        --font:'DM Sans',system-ui,sans-serif;
    }
    [data-theme="light"]{
        --bg:#F1F5F9;--bg-card:#FFFFFF;--bg-raised:#F8FAFC;--surface:#E2E8F0;--surface-2:#EDF0F5;
        --text:#0F172A;--text-sec:#475569;--text-muted:#64748B;
        --border:rgba(0,0,0,.08);--border-h:rgba(0,0,0,.15);
        --shadow:0 4px 20px rgba(0,0,0,.06);--shadow-lg:0 12px 40px rgba(0,0,0,.1);
    }
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
    body{font-family:var(--font);background:var(--bg);color:var(--text);line-height:1.6;min-height:100vh;overflow-x:hidden}
    ::-webkit-scrollbar{width:5px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--border);border-radius:10px}

    /* ═══ LAYOUT ══════════════════════════════════════════ */
    .layout{display:flex;min-height:100vh}

    /* ═══ SIDEBAR ═════════════════════════════════════════ */
    .sidebar{
        width:var(--sidebar-w);background:var(--bg-card);
        border-right:1px solid var(--border);
        position:fixed;top:0;left:0;bottom:0;z-index:200;
        display:flex;flex-direction:column;
        transition:width .25s ease,transform .3s ease;
        overflow:hidden;
    }
    .sidebar.collapsed{width:var(--sidebar-cw)}
    .sidebar.collapsed .sidebar-text{display:none}
    .sidebar.collapsed .sidebar-user-info{display:none}
    .sidebar.collapsed .nav-badge{display:none}
    .sidebar.collapsed .sidebar-brand-text{display:none}
    .sidebar.collapsed .sidebar-section-title{display:none}
    .sidebar.collapsed .nav-link{justify-content:center;padding:.65rem .5rem}
    .sidebar.collapsed .nav-link .nav-icon{margin:0}
    .sidebar.collapsed .sidebar-header{padding:1rem .5rem}
    .sidebar.collapsed .sidebar-brand{justify-content:center}
    .sidebar.collapsed .sidebar-footer{padding:.75rem .5rem}
    .sidebar.collapsed .user-chip{justify-content:center}
    .sidebar.collapsed .sidebar-toggle{right:auto;left:50%;transform:translateX(-50%) translateY(-50%)}

    .sidebar-header{padding:1.25rem 1.1rem;border-bottom:1px solid var(--border);position:relative}
    .sidebar-brand{display:flex;align-items:center;gap:.65rem;text-decoration:none;color:var(--text);margin-bottom:1rem}
    .sidebar-brand-icon{
        width:36px;height:36px;border-radius:10px;
        background:linear-gradient(135deg,var(--primary),var(--primary-d));
        display:flex;align-items:center;justify-content:center;
        font-size:1.05rem;flex-shrink:0;box-shadow:0 4px 12px rgba(37,99,235,.3);
    }
    .sidebar-brand-text{font-size:1.05rem;font-weight:800;letter-spacing:-.3px}
    .sidebar-toggle{
        position:absolute;right:-14px;top:50%;transform:translateY(-50%);
        width:28px;height:28px;border-radius:50%;
        background:var(--bg-card);border:1px solid var(--border);
        display:flex;align-items:center;justify-content:center;
        cursor:pointer;font-size:.75rem;color:var(--text-muted);
        transition:all .2s;z-index:10;
    }
    .sidebar-toggle:hover{background:var(--primary);color:#fff;border-color:var(--primary)}
    .sidebar.collapsed .sidebar-toggle{transform:translateX(-50%) translateY(-50%) rotate(180deg)}

    .sidebar-user{
        display:flex;align-items:center;gap:.65rem;
        padding:.65rem;border-radius:var(--radius-sm);
        background:var(--surface);border:1px solid var(--border);
    }
    .user-avatar{
        width:38px;height:38px;border-radius:50%;
        background:linear-gradient(135deg,var(--primary),var(--purple));
        display:flex;align-items:center;justify-content:center;
        font-size:.8rem;font-weight:700;color:#fff;flex-shrink:0;
        box-shadow:0 2px 8px rgba(37,99,235,.2);
    }
    .sidebar-user-info h3{font-size:.82rem;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .sidebar-user-info p{font-size:.68rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

    .sidebar-nav{flex:1;padding:.75rem 0;overflow-y:auto}
    .sidebar-section-title{
        padding:.4rem 1.1rem;font-size:.6rem;font-weight:700;
        text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);
    }
    .nav-link{
        display:flex;align-items:center;gap:.7rem;
        padding:.6rem 1.1rem;color:var(--text-sec);
        text-decoration:none;font-size:.82rem;font-weight:500;
        transition:all .2s;border-left:3px solid transparent;
        white-space:nowrap;
    }
    .nav-link:hover{color:var(--text);background:var(--surface);border-left-color:var(--primary)}
    .nav-link.active{color:var(--primary-l);background:rgba(37,99,235,.06);border-left-color:var(--primary);font-weight:600}
    .nav-link.danger{color:var(--red)}
    .nav-link.danger:hover{background:rgba(239,68,68,.06);border-left-color:var(--red)}
    .nav-icon{font-size:1rem;width:20px;text-align:center;flex-shrink:0}
    .nav-badge{margin-left:auto;padding:1px 7px;border-radius:10px;font-size:.65rem;font-weight:700;background:var(--primary);color:#fff;min-width:20px;text-align:center}

    .sidebar-footer{padding:.75rem 1.1rem;border-top:1px solid var(--border)}

    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:199}
    .sidebar-overlay.open{display:block}

    /* ═══ MAIN ════════════════════════════════════════════ */
    .main{flex:1;margin-left:var(--sidebar-w);min-width:0;transition:margin-left .25s ease}
    .main.sidebar-collapsed{margin-left:var(--sidebar-cw)}

    /* ── Topbar ─────────────────────────────────────────── */
    .topbar{
        background:var(--bg-card);border-bottom:1px solid var(--border);
        padding:.65rem 1.75rem;display:flex;align-items:center;
        justify-content:space-between;position:sticky;top:0;z-index:100;
        gap:1rem;
    }
    .topbar-left{display:flex;align-items:center;gap:.75rem}
    .mobile-menu{
        display:none;width:36px;height:36px;border-radius:var(--radius-xs);
        background:var(--surface);border:1px solid var(--border);cursor:pointer;
        align-items:center;justify-content:center;font-size:1.1rem;color:var(--text);transition:all .2s;
    }
    .mobile-menu:hover{background:var(--primary);border-color:var(--primary)}
    .topbar-title{font-size:1.05rem;font-weight:700;color:var(--text)}
    .topbar-right{display:flex;align-items:center;gap:.5rem}
    .topbar-btn{
        width:34px;height:34px;border-radius:var(--radius-xs);
        background:var(--surface);border:1px solid var(--border);
        cursor:pointer;display:flex;align-items:center;justify-content:center;
        font-size:.9rem;color:var(--text-sec);transition:all .2s;
    }
    .topbar-btn:hover{background:var(--primary);border-color:var(--primary);color:#fff}
    .btn-cta{
        display:inline-flex;align-items:center;gap:.4rem;
        padding:.5rem 1rem;border-radius:var(--radius-xs);
        background:linear-gradient(135deg,var(--primary),var(--primary-d));
        color:#fff;border:none;font-family:var(--font);font-size:.8rem;font-weight:600;
        cursor:pointer;transition:all .25s;text-decoration:none;white-space:nowrap;
        box-shadow:0 4px 12px rgba(37,99,235,.25);
    }
    .btn-cta:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(37,99,235,.4)}

    /* ═══ CONTENT ═════════════════════════════════════════ */
    .content{padding:1.75rem}

    /* ── Welcome Banner ────────────────────────────────── */
    .welcome{
        background:linear-gradient(135deg,var(--primary) 0%,var(--primary-d) 60%,var(--purple) 100%);
        border-radius:var(--radius);padding:1.75rem 2rem;color:#fff;
        margin-bottom:1.75rem;position:relative;overflow:hidden;
        box-shadow:0 8px 30px rgba(37,99,235,.3);
    }
    .welcome::before{content:'';position:absolute;top:-30%;right:-5%;width:250px;height:250px;border-radius:50%;background:rgba(255,255,255,.06);pointer-events:none}
    .welcome::after{content:'';position:absolute;bottom:-40%;left:-5%;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.04);pointer-events:none}
    .welcome-inner{position:relative;z-index:1}
    .welcome h1{font-size:1.6rem;font-weight:800;margin-bottom:.3rem;letter-spacing:-.3px}
    .welcome p{font-size:.88rem;opacity:.85;max-width:500px}
    .welcome-actions{display:flex;gap:.6rem;margin-top:1rem;flex-wrap:wrap}
    .welcome-btn{
        display:inline-flex;align-items:center;gap:.4rem;
        padding:.55rem 1.15rem;border-radius:var(--radius-xs);
        font-family:var(--font);font-size:.8rem;font-weight:600;
        cursor:pointer;transition:all .25s;text-decoration:none;border:none;
    }
    .welcome-btn-white{background:#fff;color:var(--primary);box-shadow:0 4px 12px rgba(0,0,0,.1)}
    .welcome-btn-white:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,0,0,.15)}
    .welcome-btn-outline{background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.3);backdrop-filter:blur(4px)}
    .welcome-btn-outline:hover{background:rgba(255,255,255,.2);border-color:rgba(255,255,255,.5)}

    /* ═══ STATS GRID ══════════════════════════════════════ */
    .stats-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:.85rem;margin-bottom:1.75rem}
    .stat-card{
        background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);
        padding:1.1rem 1.15rem;position:relative;overflow:hidden;
        transition:all .25s;cursor:default;
    }
    .stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--stat-color,var(--primary))}
    .stat-card:hover{transform:translateY(-3px);box-shadow:var(--shadow);border-color:var(--border-h)}
    .stat-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem}
    .stat-icon{
        width:38px;height:38px;border-radius:10px;
        display:flex;align-items:center;justify-content:center;
        font-size:1.1rem;flex-shrink:0;
    }
    .stat-label{font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted)}
    .stat-value{font-size:1.85rem;font-weight:800;color:var(--text);line-height:1}
    @keyframes countUp{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
    .stat-card{animation:countUp .4s ease both}
    .stat-card:nth-child(2){animation-delay:.05s}.stat-card:nth-child(3){animation-delay:.1s}
    .stat-card:nth-child(4){animation-delay:.15s}.stat-card:nth-child(5){animation-delay:.2s}

    /* ═══ SECTION CARD ═══════════════════════════════════ */
    .section-card{
        background:var(--bg-card);border:1px solid var(--border);
        border-radius:var(--radius);margin-bottom:1.5rem;overflow:hidden;box-shadow:var(--shadow);
    }
    .section-header{
        display:flex;align-items:center;justify-content:space-between;
        padding:1rem 1.35rem;border-bottom:1px solid var(--border);
        flex-wrap:wrap;gap:.5rem;
    }
    .section-title{font-size:1rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:.5rem}
    .section-count{font-size:.72rem;color:var(--text-muted);background:var(--surface);padding:2px 8px;border-radius:10px;border:1px solid var(--border)}

    /* ── Filter Tabs ───────────────────────────────────── */
    .filter-bar{display:flex;gap:.35rem;padding:.6rem 1.35rem;border-bottom:1px solid var(--border);overflow-x:auto;flex-wrap:nowrap}
    .filter-tab{
        padding:.35rem .75rem;border-radius:20px;border:1px solid var(--border);
        background:transparent;color:var(--text-muted);font-size:.72rem;font-weight:600;
        cursor:pointer;white-space:nowrap;transition:all .2s;font-family:var(--font);
    }
    .filter-tab:hover{border-color:var(--primary);color:var(--primary)}
    .filter-tab.active{background:var(--primary);color:#fff;border-color:var(--primary)}

    /* ═══ TABLE ═══════════════════════════════════════════ */
    .table-wrap{overflow-x:auto}
    .tb{width:100%;border-collapse:collapse}
    .tb thead{background:var(--surface)}
    .tb th{
        padding:.6rem .85rem;text-align:left;font-size:.62rem;font-weight:700;
        text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);
        border-bottom:1px solid var(--border);white-space:nowrap;
    }
    .tb td{padding:.65rem .85rem;border-bottom:1px solid var(--border);font-size:.82rem;color:var(--text-sec);vertical-align:middle}
    .tb tbody tr{transition:background .15s;cursor:default}
    .tb tbody tr:hover{background:var(--surface)}
    .tb tbody tr:last-child td{border-bottom:none}
    .tb-thumb{width:40px;height:40px;object-fit:cover;border-radius:8px;border:1px solid var(--border)}
    .tb-thumb-ph{width:40px;height:40px;background:var(--surface);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1rem;border:1px solid var(--border)}
    .tb-title{font-weight:600;color:var(--text)}
    .tb-empresa{font-size:.72rem;font-weight:600;color:var(--primary-l)}
    .tb-meta{font-size:.72rem;color:var(--text-muted)}
    .tb-btn{
        padding:.3rem .7rem;border-radius:var(--radius-xs);border:1px solid var(--border);
        background:var(--surface);color:var(--text-sec);font-size:.72rem;font-weight:600;
        cursor:pointer;transition:all .2s;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;
        font-family:var(--font);
    }
    .tb-btn:hover{border-color:var(--primary);color:var(--primary)}

    /* ── Status Badge ──────────────────────────────────── */
    .st-badge{
        display:inline-flex;align-items:center;gap:.35rem;
        padding:.2rem .65rem;border-radius:20px;font-size:.7rem;font-weight:700;white-space:nowrap;
    }
    .st-badge::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor}
    .st-pendente{background:rgba(245,158,11,.1);color:var(--yellow)}
    .st-analise{background:rgba(59,130,246,.1);color:var(--blue)}
    .st-aprovado{background:rgba(16,185,129,.1);color:var(--green)}
    .st-reprovado{background:rgba(239,68,68,.1);color:var(--red)}

    /* ═══ MOBILE CARDS ═══════════════════════════════════ */
    .m-cards{display:none}
    .m-card{
        background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);
        padding:.85rem;margin-bottom:.6rem;transition:all .2s;
    }
    .m-card:hover{border-color:var(--primary)}
    .m-card-head{display:flex;align-items:center;gap:.65rem;margin-bottom:.6rem}
    .m-card-thumb{width:38px;height:38px;object-fit:cover;border-radius:8px;border:1px solid var(--border);flex-shrink:0}
    .m-card-thumb-ph{width:38px;height:38px;background:var(--bg-card);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.9rem;border:1px solid var(--border);flex-shrink:0}
    .m-card-info{flex:1;min-width:0}
    .m-card-title{font-size:.85rem;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .m-card-empresa{font-size:.72rem;font-weight:600;color:var(--primary-l)}
    .m-card-grid{display:grid;grid-template-columns:1fr 1fr;gap:.25rem .6rem;margin-bottom:.6rem}
    .m-card-field{display:flex;flex-direction:column}
    .m-card-lbl{font-size:.55rem;font-weight:700;text-transform:uppercase;letter-spacing:.3px;color:var(--text-muted)}
    .m-card-val{font-size:.75rem;font-weight:600;color:var(--text-sec)}
    .m-card-foot{display:flex;align-items:center;justify-content:space-between;gap:.4rem;flex-wrap:wrap;padding-top:.5rem;border-top:1px solid var(--border)}

    /* ═══ VAGAS GRID ══════════════════════════════════════ */
    .vagas-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:.85rem;padding:1.25rem}
    .vaga-card{
        background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);
        padding:1.15rem;transition:all .3s;display:flex;flex-direction:column;
    }
    .vaga-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg);border-color:var(--primary)}
    .vaga-card h4{font-size:.95rem;font-weight:700;color:var(--text);margin-bottom:.15rem;line-height:1.3}
    .vaga-empresa{font-size:.78rem;font-weight:600;color:var(--primary-l);margin-bottom:.5rem}
    .vaga-meta{display:flex;flex-direction:column;gap:.3rem;margin-bottom:.85rem;font-size:.78rem;color:var(--text-sec);flex:1}

    /* ═══ EMPTY STATE ════════════════════════════════════ */
    .empty{text-align:center;padding:3rem 1.5rem;color:var(--text-muted)}
    .empty-icon{font-size:3rem;margin-bottom:.75rem;opacity:.4}
    .empty h3{font-size:1.1rem;color:var(--text);margin-bottom:.3rem;font-weight:700}
    .empty p{font-size:.85rem;margin-bottom:1rem}

    /* ═══ RESPONSIVE ═════════════════════════════════════ */
    @media(max-width:1100px){.stats-grid{grid-template-columns:repeat(3,1fr)}}
    @media(max-width:900px){
        .sidebar{transform:translateX(-100%);width:260px;box-shadow:4px 0 20px rgba(0,0,0,.4)}
        .sidebar.open{transform:translateX(0)}
        .sidebar.collapsed{width:260px}
        .main{margin-left:0!important}
        .mobile-menu{display:flex}
        .content{padding:1.25rem 1rem}
        .welcome{padding:1.25rem 1.5rem}.welcome h1{font-size:1.3rem}
        .stats-grid{grid-template-columns:repeat(2,1fr);gap:.6rem}
        .table-wrap{display:none}.m-cards{display:block}
        .vagas-grid{grid-template-columns:1fr}
        .sidebar-toggle{display:none}
    }
    @media(max-width:480px){
        .content{padding:1rem .75rem}
        .stats-grid{grid-template-columns:1fr 1fr;gap:.5rem}
        .stat-card{padding:.85rem}
        .stat-value{font-size:1.5rem}
        .stat-icon{width:32px;height:32px;font-size:.95rem}
        .welcome h1{font-size:1.15rem}.welcome p{font-size:.8rem}
        .welcome-actions{flex-direction:column}.welcome-btn{width:100%;justify-content:center}
        .m-card-grid{grid-template-columns:1fr}
    }
    </style>
</head>
<body>
<div class="sidebar-overlay" id="sOverlay" onclick="closeSidebar()"></div>
<div class="layout">

<!-- ═══ SIDEBAR ═══════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="./vaga.php" class="sidebar-brand">
            <div class="sidebar-brand-icon">🚀</div>
            <span class="sidebar-brand-text sidebar-text">TGA Carreiras</span>
        </a>
        <div class="sidebar-user">
            <div class="user-avatar"><?= htmlspecialchars($iniciais) ?></div>
            <div class="sidebar-user-info sidebar-text">
                <h3><?= htmlspecialchars($candidatoNome) ?></h3>
                <p><?= htmlspecialchars($candidatoEmail) ?></p>
            </div>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleCollapse()" title="Recolher">‹</button>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-section-title sidebar-text">Principal</div>
        <a href="dashboard.php" class="nav-link active"><span class="nav-icon">📊</span><span class="sidebar-text">Dashboard</span></a>
        <a href="./vaga.php" class="nav-link"><span class="nav-icon">💼</span><span class="sidebar-text">Buscar Vagas</span></a>
        <a href="#cand" class="nav-link" onclick="closeSidebar();document.getElementById('secCand')?.scrollIntoView({behavior:'smooth'})"><span class="nav-icon">📄</span><span class="sidebar-text">Candidaturas</span><span class="nav-badge"><?= $total ?></span></a>

        <div class="sidebar-section-title sidebar-text" style="margin-top:.5rem">Conta</div>
        <a href="perfil.php" class="nav-link"><span class="nav-icon">👤</span><span class="sidebar-text">Meu Perfil</span></a>
        <a href="#" class="nav-link"><span class="nav-icon">⚙️</span><span class="sidebar-text">Configurações</span></a>
        <a href="logout.php" class="nav-link danger"><span class="nav-icon">🚪</span><span class="sidebar-text">Sair</span></a>
    </nav>

    <div class="sidebar-footer">
        <div style="font-size:.65rem;color:var(--text-muted);font-weight:500" class="sidebar-text">© <?= date('Y') ?> TGA Carreiras</div>
    </div>
</aside>

<!-- ═══ MAIN ═════════════════════════════════════════════ -->
<div class="main" id="mainArea">

    <div class="topbar">
        <div class="topbar-left">
            <button class="mobile-menu" onclick="toggleSidebar()">☰</button>
            <span class="topbar-title">📊 Dashboard</span>
        </div>
        <div class="topbar-right">
            <button class="topbar-btn" onclick="toggleTheme()" title="Tema"><span id="themeIcon">🌙</span></button>
            <a href="./vaga.php" class="btn-cta">🔍 <span>Buscar Vagas</span></a>
        </div>
    </div>

    <div class="content">

        <!-- Welcome Banner -->
        <div class="welcome">
            <div class="welcome-inner">
                <h1>👋 Olá, <?= htmlspecialchars($primeiroNome) ?>!</h1>
                <p>Acompanhe suas candidaturas e encontre novas oportunidades profissionais</p>
                <div class="welcome-actions">
                    <a href="./vaga.php" class="welcome-btn welcome-btn-white">🔍 Explorar Vagas</a>
                    <a href="perfil.php" class="welcome-btn welcome-btn-outline">👤 Meu Perfil</a>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card" style="--stat-color:var(--primary)">
                <div class="stat-head"><div class="stat-icon" style="background:rgba(37,99,235,.1)">📊</div><div class="stat-label">Total</div></div>
                <div class="stat-value"><?= $total ?></div>
            </div>
            <div class="stat-card" style="--stat-color:var(--yellow)">
                <div class="stat-head"><div class="stat-icon" style="background:rgba(245,158,11,.1)">⏳</div><div class="stat-label">Pendentes</div></div>
                <div class="stat-value"><?= $pendentes ?></div>
            </div>
            <div class="stat-card" style="--stat-color:var(--blue)">
                <div class="stat-head"><div class="stat-icon" style="background:rgba(59,130,246,.1)">🔍</div><div class="stat-label">Em Análise</div></div>
                <div class="stat-value"><?= $emAnalise ?></div>
            </div>
            <div class="stat-card" style="--stat-color:var(--green)">
                <div class="stat-head"><div class="stat-icon" style="background:rgba(16,185,129,.1)">✅</div><div class="stat-label">Aprovados</div></div>
                <div class="stat-value"><?= $aprovados ?></div>
            </div>
            <div class="stat-card" style="--stat-color:var(--red)">
                <div class="stat-head"><div class="stat-icon" style="background:rgba(239,68,68,.1)">❌</div><div class="stat-label">Reprovados</div></div>
                <div class="stat-value"><?= $reprovados ?></div>
            </div>
        </div>

        <!-- Candidaturas -->
        <div class="section-card" id="secCand">
            <div class="section-header">
                <h3 class="section-title">📋 Minhas Candidaturas</h3>
                <span class="section-count"><?= $total ?> candidatura(s)</span>
            </div>

            <?php if ($total > 0): ?>
            <div class="filter-bar">
                <button class="filter-tab active" onclick="filtrar('all',this)">Todas (<?= $total ?>)</button>
                <?php if ($pendentes): ?><button class="filter-tab" onclick="filtrar('pendente',this)">⏳ Pendentes (<?= $pendentes ?>)</button><?php endif; ?>
                <?php if ($emAnalise): ?><button class="filter-tab" onclick="filtrar('em_analise',this)">🔍 Análise (<?= $emAnalise ?>)</button><?php endif; ?>
                <?php if ($aprovados): ?><button class="filter-tab" onclick="filtrar('aprovado',this)">✅ Aprovados (<?= $aprovados ?>)</button><?php endif; ?>
                <?php if ($reprovados): ?><button class="filter-tab" onclick="filtrar('reprovado',this)">❌ Reprovados (<?= $reprovados ?>)</button><?php endif; ?>
            </div>

            <!-- Desktop Table -->
            <div class="table-wrap">
                <table class="tb"><thead><tr>
                    <th>Img</th><th>Vaga / Empresa</th><th>Local</th><th>Salário</th>
                    <th>Horário</th><th>Escolaridade</th><th>Status</th><th>Data</th><th>Ação</th>
                </tr></thead><tbody>
                <?php foreach ($candidaturas as $c):
                    $st = $statusMap[$c['status']] ?? $statusMap['pendente'];
                ?>
                <tr data-status="<?= htmlspecialchars($c['status']) ?>">
                    <td><?php if(!empty($c['imagem'])):?><img src="../uploads/vagas/<?=htmlspecialchars($c['imagem'])?>" class="tb-thumb" alt=""><?php else:?><div class="tb-thumb-ph">💼</div><?php endif;?></td>
                    <td><div class="tb-title"><?=htmlspecialchars($c['titulo']??'Vaga removida')?></div><?php if(!empty($c['empresa'])):?><div class="tb-empresa">🏢 <?=htmlspecialchars($c['empresa'])?></div><?php endif;?></td>
                    <td>📍 <?=htmlspecialchars($c['localizacao']??'—')?></td>
                    <td>💰 <?=formatarSalarioDash($c['salario']??'')?></td>
                    <td><?=htmlspecialchars($c['disponibilidade']??'—')?></td>
                    <td><?=htmlspecialchars($c['escolaridade']??'—')?></td>
                    <td><span class="st-badge <?=$st['cls']?>"><?=$st['label']?></span></td>
                    <td><div class="tb-meta"><?=date('d/m/Y',strtotime($c['data_candidatura']))?></div><div class="tb-meta"><?=date('H:i',strtotime($c['data_candidatura']))?></div></td>
                    <td><div style="display:flex;gap:.3rem;flex-wrap:wrap"><?php if(!empty($c['vaga_id'])):?><a href="../public/vaga.php?id=<?=$c['vaga_id']?>" class="tb-btn">Ver →</a><?php endif;?><?php if(!empty($c['curriculo'])):?><a href="../<?=htmlspecialchars($c['curriculo'])?>" target="_blank" class="tb-btn">📄</a><?php endif;?></div></td>
                </tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>

            <!-- Mobile Cards -->
            <div class="m-cards" style="padding:.85rem">
                <?php foreach ($candidaturas as $c):
                    $st = $statusMap[$c['status']] ?? $statusMap['pendente'];
                ?>
                <div class="m-card" data-status="<?=htmlspecialchars($c['status'])?>">
                    <div class="m-card-head">
                        <?php if(!empty($c['imagem'])):?><img src="../uploads/vagas/<?=htmlspecialchars($c['imagem'])?>" class="m-card-thumb" alt=""><?php else:?><div class="m-card-thumb-ph">💼</div><?php endif;?>
                        <div class="m-card-info"><div class="m-card-title"><?=htmlspecialchars($c['titulo']??'Vaga removida')?></div><?php if(!empty($c['empresa'])):?><div class="m-card-empresa">🏢 <?=htmlspecialchars($c['empresa'])?></div><?php endif;?></div>
                    </div>
                    <div class="m-card-grid">
                        <div class="m-card-field"><span class="m-card-lbl">📍 Local</span><span class="m-card-val"><?=htmlspecialchars($c['localizacao']??'—')?></span></div>
                        <div class="m-card-field"><span class="m-card-lbl">💰 Salário</span><span class="m-card-val"><?=formatarSalarioDash($c['salario']??'')?></span></div>
                        <div class="m-card-field"><span class="m-card-lbl">⏰ Horário</span><span class="m-card-val"><?=htmlspecialchars($c['disponibilidade']??'—')?></span></div>
                        <div class="m-card-field"><span class="m-card-lbl">🎓 Escolaridade</span><span class="m-card-val"><?=htmlspecialchars($c['escolaridade']??'—')?></span></div>
                    </div>
                    <div class="m-card-foot">
                        <span class="st-badge <?=$st['cls']?>"><?=$st['label']?></span>
                        <span class="m-card-val">📅 <?=date('d/m/Y H:i',strtotime($c['data_candidatura']))?></span>
                        <div style="display:flex;gap:.3rem"><?php if(!empty($c['vaga_id'])):?><a href="../public/vaga.php?id=<?=$c['vaga_id']?>" class="tb-btn">Ver →</a><?php endif;?><?php if(!empty($c['curriculo'])):?><a href="../<?=htmlspecialchars($c['curriculo'])?>" target="_blank" class="tb-btn">📄</a><?php endif;?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php else: ?>
            <div class="empty">
                <div class="empty-icon">📭</div>
                <h3>Nenhuma candidatura ainda</h3>
                <p>Explore nossas vagas e dê o primeiro passo!</p>
                <a href="./vaga.php" class="btn-cta">🔍 Buscar Vagas</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Vagas Recomendadas -->
        <?php if (count($vagasRecomendadas) > 0): ?>
        <div class="section-card">
            <div class="section-header">
                <h3 class="section-title">✨ Vagas Recomendadas</h3>
                <a href="./vaga.php" class="tb-btn">Ver todas →</a>
            </div>
            <div class="vagas-grid">
                <?php foreach ($vagasRecomendadas as $v): ?>
                <div class="vaga-card">
                    <h4><?=htmlspecialchars($v['titulo'])?></h4>
                    <?php if(!empty($v['empresa'])):?><div class="vaga-empresa">🏢 <?=htmlspecialchars($v['empresa'])?></div><?php endif;?>
                    <div class="vaga-meta">
                        <div>📍 <?=htmlspecialchars($v['localizacao']??'Não informado')?></div>
                        <div>💰 <?=formatarSalarioDash($v['salario']??'')?></div>
                        <div>📅 <?=date('d/m/Y',strtotime($v['data_publicacao']))?></div>
                    </div>
                    <a href="../public/vaga.php?id=<?=$v['id']?>" class="btn-cta" style="align-self:flex-start;font-size:.78rem;padding:.45rem .85rem">Ver Detalhes →</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
</div>

<script>
"use strict";

/* ── Theme ───────────────────────────────────────────── */
function toggleTheme(){
    const h=document.documentElement,n=(h.getAttribute('data-theme')||'dark')==='dark'?'light':'dark';
    h.setAttribute('data-theme',n);
    document.getElementById('themeIcon').textContent=n==='light'?'☀️':'🌙';
    localStorage.setItem('theme',n);
}

/* ── Sidebar Mobile ──────────────────────────────────── */
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sOverlay').classList.toggle('open')}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sOverlay').classList.remove('open')}

/* ── Sidebar Collapse (desktop) ──────────────────────── */
function toggleCollapse(){
    const s=document.getElementById('sidebar'),m=document.getElementById('mainArea');
    s.classList.toggle('collapsed');m.classList.toggle('sidebar-collapsed');
    localStorage.setItem('sidebar_collapsed',s.classList.contains('collapsed')?'1':'0');
}

/* ── Filter Candidaturas ─────────────────────────────── */
function filtrar(status,btn){
    document.querySelectorAll('.filter-tab').forEach(t=>t.classList.remove('active'));btn.classList.add('active');
    document.querySelectorAll('.tb tbody tr[data-status],.m-card[data-status]').forEach(el=>{
        el.style.display=(status==='all'||el.dataset.status===status)?'':'none';
    });
}

/* ── Init ─────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded',()=>{
    const t=localStorage.getItem('theme')||'dark';
    document.documentElement.setAttribute('data-theme',t);
    document.getElementById('themeIcon').textContent=t==='light'?'☀️':'🌙';

    if(localStorage.getItem('sidebar_collapsed')==='1'&&window.innerWidth>900){
        document.getElementById('sidebar').classList.add('collapsed');
        document.getElementById('mainArea').classList.add('sidebar-collapsed');
    }
});

document.addEventListener('keydown',e=>{if(e.key==='Escape')closeSidebar()});
</script>
</body>
</html>