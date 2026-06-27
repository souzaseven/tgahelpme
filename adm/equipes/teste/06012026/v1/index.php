<?php
require_once __DIR__ . '/backend/banco/conexao.php';

$pagina = $_GET['p'] ?? 'dashboard';
$paginas_validas = ['dashboard','contas','receitas','despesas','cartoes','recorrencias','emprestimos','investimentos','metas','relatorios','alertas','calendario','analise','categorias','responsaveis','terceiros','configuracoes','importar'];
if (!in_array($pagina, $paginas_validas)) $pagina = 'dashboard';

$titulos = [
    'dashboard'    => 'Dashboard',
    'contas'       => 'Contas Bancárias',
    'receitas'     => 'Receitas',
    'despesas'     => 'Despesas',
    'cartoes'      => 'Cartões de Crédito',
    'recorrencias' => 'Contas Fixas',
    'emprestimos'  => 'Empréstimos',
    'investimentos'=> 'Investimentos',
    'metas'        => 'Metas Financeiras',
    'relatorios'   => 'Relatórios & BI',
    'alertas'      => 'Alertas',
    'calendario'   => 'Calendário',
    'analise'      => 'Análise de Gastos',
    'categorias'   => 'Categorias',
    'responsaveis' => 'Responsáveis',
    'terceiros'    => 'Controle de Terceiros',
    'configuracoes'=> 'Configurações',
    'importar'     => 'Importar Extrato',
];

$nav = [
    ['sep' => 'Visão Geral'],
    ['p' => 'dashboard',    'label' => 'Dashboard',        'icon' => 'fa-chart-pie'],
    ['p' => 'contas',       'label' => 'Contas',           'icon' => 'fa-university'],
    ['sep' => 'Movimentações'],
    ['p' => 'receitas',     'label' => 'Receitas',         'icon' => 'fa-arrow-trend-up'],
    ['p' => 'despesas',     'label' => 'Despesas',         'icon' => 'fa-arrow-trend-down'],
    ['p' => 'importar',    'label' => 'Importar Extrato', 'icon' => 'fa-file-import'],
    ['p' => 'cartoes',      'label' => 'Cartões',          'icon' => 'fa-credit-card'],
    ['p' => 'recorrencias', 'label' => 'Contas Fixas',     'icon' => 'fa-rotate'],
    ['sep' => 'Planejamento'],
    ['p' => 'emprestimos',  'label' => 'Empréstimos',      'icon' => 'fa-landmark'],
    ['p' => 'investimentos','label' => 'Investimentos',    'icon' => 'fa-chart-line'],
    ['p' => 'metas',        'label' => 'Metas',            'icon' => 'fa-bullseye'],
    ['sep' => 'Análise'],
    ['p' => 'relatorios',   'label' => 'Relatórios & BI',  'icon' => 'fa-chart-bar'],
    ['p' => 'calendario',   'label' => 'Calendário',       'icon' => 'fa-calendar-days'],
    ['p' => 'analise',      'label' => 'Análise de Gastos','icon' => 'fa-brain'],
    ['p' => 'alertas',      'label' => 'Alertas',          'icon' => 'fa-bell'],
    ['sep' => 'Sistema'],
    ['p' => 'terceiros',    'label' => 'Terceiros',        'icon' => 'fa-people-arrows'],
    ['p' => 'categorias',   'label' => 'Categorias',       'icon' => 'fa-tags'],
    ['p' => 'responsaveis', 'label' => 'Responsáveis',     'icon' => 'fa-people-group'],
    ['p' => 'configuracoes','label' => 'Configurações',    'icon' => 'fa-gear'],
];

// Alertas ativos (badge no sino)
$totalAlertas = (int) $pdo->query(
    "SELECT COUNT(*) FROM `controle_planilhas_alertas` WHERE lido = 0"
)->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulos[$pagina]) ?> — Controle Financeiro</title>
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <meta name="theme-color" content="#6366f1">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Finanças">
    <link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/512/639/639365.png">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="assets/icons/icon.php?size=192">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <!-- Restaura estado da sidebar e tema antes do render para evitar flash -->
    <script>
    // Injeta token CSRF em todos os fetch() não-GET da mesma origem
    (function(){
        var _token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        var _orig  = window.fetch;
        window.fetch = function(url, opts) {
            opts = opts || {};
            var method = (opts.method || 'GET').toUpperCase();
            if (_token && method !== 'GET' && method !== 'HEAD' && !/^https?:\/\//i.test(url)) {
                opts.headers = Object.assign({}, opts.headers || {}, { 'X-CSRF-Token': _token });
            }
            return _orig.call(this, url, opts);
        };
    })();
    (function(){
        // Restaura tema antes do render
        var savedTheme = localStorage.getItem('financeOS_tema') || 'dark';
        document.documentElement.dataset.theme = savedTheme;

        var saved = localStorage.getItem('financeOS_sidebarCollapsed');
        var autoCollapse = saved === null && window.screen.width < 1200;
        if (saved === '1' || autoCollapse) {
            document.documentElement.dataset.sidebarCollapsed = '1';
        }
    })();
    </script>
    <style>
        html[data-sidebar-collapsed="1"] #mainLayout  { grid-template-columns: 62px 1fr; }
        html[data-sidebar-collapsed="1"] #mainSidebar { width: 62px; }

        /* ── Skeleton loading ─────────────────────────────────── */
        @keyframes sk-shimmer { to { background-position: -200% center; } }
        .skel {
            border-radius: 5px;
            background: linear-gradient(90deg,
                var(--bg-700, #1e293b) 25%,
                var(--bg-600, #334155) 50%,
                var(--bg-700, #1e293b) 75%
            );
            background-size: 200% auto;
            animation: sk-shimmer 1.5s linear infinite;
        }
        .skel-text  { display: block; height: .82em; }
        .skel-sm    { display: block; height: .58em; }
        .skel-badge { display: inline-block; height: 1.45rem; width: 64px; border-radius: 100px; }
        .skel-kpi   { height: 1.6rem; width: 72%; border-radius: 6px; margin: 4px auto 0; }
    </style>
</head>
<body>

<div class="layout" id="mainLayout">

    <!-- ── Sidebar ── -->
    <aside class="sidebar" id="mainSidebar">
        <div class="sidebar-logo">
            <div class="logo-icon"><i class="fa-solid fa-chart-pie"></i></div>
            <div class="sidebar-logo-info">
                <div class="logo-text">Controle Financeiro</div>
                <div class="logo-ver">v1.0</div>
            </div>
            <button class="sidebar-toggle" id="sidebarToggleBtn"
                    onclick="toggleSidebarCollapse()" title="Recolher menu">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
        </div>

        <nav style="flex:1; padding: .5rem 0; overflow: visible;">
            <?php foreach ($nav as $item): ?>
                <?php if (isset($item['sep'])): ?>
                    <div class="sidebar-section"><?= $item['sep'] ?></div>
                <?php else: ?>
                    <a href="?p=<?= $item['p'] ?>"
                       class="nav-item <?= $pagina === $item['p'] ? 'active' : '' ?>"
                       data-label="<?= htmlspecialchars($item['label']) ?>">
                        <i class="fa-solid <?= $item['icon'] ?>"></i>
                        <span class="nav-label"><?= $item['label'] ?></span>
                        <?php if ($item['p'] === 'alertas' && $totalAlertas > 0): ?>
                            <span class="nav-badge"><?= $totalAlertas ?></span>
                        <?php endif ?>
                    </a>
                <?php endif ?>
            <?php endforeach ?>
        </nav>

        <div class="sidebar-footer">
            Controle Financeiro &copy; <?= date('Y') ?>
        </div>
    </aside>

    <!-- ── Main ── -->
    <div class="main-wrapper">

        <!-- Header -->
        <header class="header">
            <button class="btn-hamburguer" onclick="toggleSidebar()" aria-label="Menu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div style="flex:1">
                <div class="header-title"><?= htmlspecialchars($titulos[$pagina]) ?></div>
                <div class="header-breadcrumb">
                    <span>Controle Financeiro</span> / <?= htmlspecialchars($titulos[$pagina]) ?>
                </div>
            </div>
            <div class="header-actions">
                <span class="header-date" style="font-size:.8rem; color:var(--text-600)">
                    <?= date('d/m/Y') ?>
                </span>
                <button onclick="abrirBuscaGlobal()" title="Busca global (Ctrl+K)"
                        style="display:flex;align-items:center;gap:.5rem;background:var(--bg-700);border:1px solid var(--border);
                               border-radius:var(--radius);padding:.4rem .85rem;color:var(--text-400);font-size:.78rem;
                               cursor:pointer;transition:var(--ease)"
                        onmouseover="this.style.borderColor='var(--indigo)'"
                        onmouseout="this.style.borderColor='var(--border)'">
                    <i class="fa-solid fa-magnifying-glass fa-xs"></i>
                    <span>Buscar</span>
                    <kbd style="background:var(--bg-600);border:1px solid var(--border);border-radius:3px;
                                padding:.05rem .3rem;font-size:.65rem;margin-left:.2rem">Ctrl K</kbd>
                </button>
                <button id="temaToggle" onclick="alternarTema()" title="Alternar tema claro/escuro"
                        style="width:36px;height:36px;border-radius:var(--radius);background:var(--bg-700);
                               border:1px solid var(--border);cursor:pointer;display:flex;align-items:center;
                               justify-content:center;color:var(--text-400);transition:var(--ease)"
                        onmouseover="this.style.borderColor='var(--indigo)'"
                        onmouseout="this.style.borderColor='var(--border)'">
                    <i id="temaIcone" class="fa-solid fa-moon fa-sm"></i>
                </button>
                <a href="?p=relatorios" class="btn-icon" title="Relatórios">
                    <i class="fa-solid fa-chart-bar"></i>
                </a>
                <a href="?p=alertas" class="btn-icon" title="Alertas (<?= $totalAlertas ?> não lidos)" style="position:relative">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($totalAlertas > 0): ?>
                        <span class="dot"></span>
                    <?php endif ?>
                </a>
                <div class="avatar" title="Usuário">U</div>
            </div>
        </header>

        <!-- Conteúdo da página -->
        <main class="content">
            <?php
            $arquivo = __DIR__ . "/pages/{$pagina}.php";
            if (file_exists($arquivo)) {
                include $arquivo;
            } else {
                include __DIR__ . '/pages/em_breve.php';
            }
            ?>
        </main>

    </div><!-- /main-wrapper -->
</div><!-- /layout -->

<!-- Overlay escuro para fechar sidebar no mobile -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('service-worker.js')
        .catch(() => {}); // silencioso se falhar (ex: HTTP sem HTTPS)
}
</script>

<!-- ── Busca Global (Ctrl+K) ───────────────────────────────────────── -->
<div id="buscaOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9000;align-items:flex-start;justify-content:center;padding-top:15vh">
    <div style="background:var(--bg-800);border:1px solid var(--border);border-radius:var(--radius-lg);width:100%;max-width:560px;box-shadow:var(--shadow-lg);overflow:hidden">
        <div style="display:flex;align-items:center;gap:.75rem;padding:.875rem 1.125rem;border-bottom:1px solid var(--border)">
            <i class="fa-solid fa-magnifying-glass" style="color:var(--text-500);font-size:.95rem"></i>
            <input type="text" id="buscaInput" placeholder="Buscar transações, contas, cartões, metas..."
                   style="flex:1;background:none;border:none;outline:none;color:var(--text-100);font-size:.95rem;font-family:inherit"
                   autocomplete="off" spellcheck="false">
            <kbd style="background:var(--bg-700);border:1px solid var(--border);border-radius:4px;padding:.15rem .4rem;font-size:.68rem;color:var(--text-500)">Esc</kbd>
        </div>
        <div id="buscaResultados" style="max-height:380px;overflow-y:auto;padding:.5rem 0">
            <div style="padding:2rem;text-align:center;color:var(--text-600);font-size:.85rem">
                <i class="fa-solid fa-magnifying-glass fa-lg" style="display:block;margin-bottom:.5rem;opacity:.3"></i>
                Digite para buscar em todo o sistema
            </div>
        </div>
        <div style="padding:.6rem 1.125rem;border-top:1px solid var(--border);display:flex;gap:1.25rem;font-size:.68rem;color:var(--text-600)">
            <span><kbd style="background:var(--bg-700);border:1px solid var(--border);border-radius:3px;padding:.1rem .3rem">↑↓</kbd> Navegar</span>
            <span><kbd style="background:var(--bg-700);border:1px solid var(--border);border-radius:3px;padding:.1rem .3rem">Enter</kbd> Ir para</span>
            <span><kbd style="background:var(--bg-700);border:1px solid var(--border);border-radius:3px;padding:.1rem .3rem">Esc</kbd> Fechar</span>
        </div>
    </div>
</div>

<script>
(function () {
    const overlay  = document.getElementById('buscaOverlay');
    const input    = document.getElementById('buscaInput');
    const resulDiv = document.getElementById('buscaResultados');
    let _timer = null, _idx = -1, _items = [];

    function abrir() {
        overlay.style.display = 'flex';
        input.value = '';
        resulDiv.innerHTML = `<div style="padding:2rem;text-align:center;color:var(--text-600);font-size:.85rem">
            <i class="fa-solid fa-magnifying-glass fa-lg" style="display:block;margin-bottom:.5rem;opacity:.3"></i>
            Digite para buscar em todo o sistema</div>`;
        _idx = -1; _items = [];
        setTimeout(() => input.focus(), 50);
    }

    function fechar() {
        overlay.style.display = 'none';
        input.blur();
    }

    function esc(s) {
        return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function renderResultados(lista) {
        _items = lista;
        _idx = -1;
        if (!lista.length) {
            resulDiv.innerHTML = `<div style="padding:2rem;text-align:center;color:var(--text-600);font-size:.85rem">
                Nenhum resultado encontrado.</div>`;
            return;
        }
        resulDiv.innerHTML = lista.map((r, i) => `
            <a href="${esc(r.link)}" class="busca-item" data-idx="${i}"
               style="display:flex;align-items:center;gap:.875rem;padding:.7rem 1.125rem;text-decoration:none;
                      color:var(--text-100);transition:background .1s;cursor:pointer"
               onmouseenter="this.parentElement.querySelectorAll('.busca-item').forEach((el,j)=>{el.style.background=j===${i}?'var(--bg-700)':''})"
               onclick="fecharBuscaGlobal()">
                <div style="width:32px;height:32px;border-radius:8px;background:${esc(r.cor)}18;
                            display:flex;align-items:center;justify-content:center;flex-shrink:0;color:${esc(r.cor)}">
                    <i class="fa-solid ${esc(r.icone)} fa-sm"></i>
                </div>
                <div style="flex:1;min-width:0">
                    <div style="font-size:.875rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(r.titulo)}</div>
                    <div style="font-size:.72rem;color:var(--text-500);margin-top:.1rem">${esc(r.subtitulo)}</div>
                </div>
                <i class="fa-solid fa-arrow-right fa-xs" style="color:var(--text-600);opacity:.5"></i>
            </a>`).join('');
    }

    async function buscar(q) {
        if (q.length < 2) {
            renderResultados([]);
            resulDiv.innerHTML = `<div style="padding:2rem;text-align:center;color:var(--text-600);font-size:.85rem">
                <i class="fa-solid fa-magnifying-glass fa-lg" style="display:block;margin-bottom:.5rem;opacity:.3"></i>
                Digite ao menos 2 caracteres</div>`;
            return;
        }
        resulDiv.innerHTML = `<div style="padding:2rem;text-align:center;color:var(--text-600);font-size:.85rem">
            <i class="fa-solid fa-spinner fa-spin"></i> Buscando...</div>`;
        try {
            const res  = await fetch('backend/api/busca.php?q=' + encodeURIComponent(q));
            const json = await res.json();
            renderResultados(json.resultados || []);
        } catch { renderResultados([]); }
    }

    input.addEventListener('input', () => {
        clearTimeout(_timer);
        _timer = setTimeout(() => buscar(input.value.trim()), 280);
    });

    input.addEventListener('keydown', e => {
        const items = resulDiv.querySelectorAll('.busca-item');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            _idx = Math.min(_idx + 1, items.length - 1);
            items.forEach((el, i) => el.style.background = i === _idx ? 'var(--bg-700)' : '');
            items[_idx]?.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            _idx = Math.max(_idx - 1, 0);
            items.forEach((el, i) => el.style.background = i === _idx ? 'var(--bg-700)' : '');
            items[_idx]?.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter' && _idx >= 0) {
            items[_idx]?.click();
        } else if (e.key === 'Escape') {
            fechar();
        }
    });

    overlay.addEventListener('click', e => { if (e.target === overlay) fechar(); });

    document.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            overlay.style.display === 'none' ? abrir() : fechar();
        }
        if (e.key === 'Escape' && overlay.style.display !== 'none') fechar();
    });

    // Expõe para uso externo
    window.abrirBuscaGlobal  = abrir;
    window.fecharBuscaGlobal = fechar;
})();
</script>

<script>
function alternarTema() {
    const html    = document.documentElement;
    const atual   = html.dataset.theme || 'dark';
    const novo    = atual === 'dark' ? 'light' : 'dark';
    html.dataset.theme = novo;
    localStorage.setItem('financeOS_tema', novo);
    const icone = document.getElementById('temaIcone');
    if (icone) icone.className = novo === 'dark' ? 'fa-solid fa-moon fa-sm' : 'fa-solid fa-sun fa-sm';
}

// Aplica ícone correto ao carregar
(function () {
    const tema   = localStorage.getItem('financeOS_tema') || 'dark';
    const icone  = document.getElementById('temaIcone');
    if (icone) icone.className = tema === 'dark' ? 'fa-solid fa-moon fa-sm' : 'fa-solid fa-sun fa-sm';
})();
</script>


<!-- ── Notificações proativas ───────────────────────────────── -->
<div id="notifPanel" style="position:fixed;bottom:1.25rem;right:1.25rem;z-index:8000;
     display:flex;flex-direction:column;gap:.6rem;max-width:340px;pointer-events:none"></div>

<script>
(function () {
    let _notifQueue = [];
    let _notifIdx   = 0;

    function mostrarNotif(alerta) {
        const el = document.createElement('div');
        el.style.cssText = `
            background: var(--bg-800); border: 1px solid ${alerta.urgente ? alerta.cor : 'var(--border)'};
            border-left: 3px solid ${alerta.cor}; border-radius: var(--radius-lg);
            padding: .875rem 1rem; box-shadow: var(--shadow-lg);
            display: flex; gap: .75rem; align-items: flex-start;
            pointer-events: all; cursor: pointer;
            animation: notifIn .3s ease; max-width: 340px;
            transition: opacity .3s, transform .3s;
        `;
        el.innerHTML = `
            <div style="width:32px;height:32px;border-radius:8px;background:${alerta.cor}18;
                        display:flex;align-items:center;justify-content:center;flex-shrink:0;color:${alerta.cor}">
                <i class="fa-solid ${alerta.icone} fa-sm"></i>
            </div>
            <div style="flex:1;min-width:0">
                <div style="font-size:.8rem;font-weight:700;color:var(--text-100)">${escHtml(alerta.titulo)}</div>
                <div style="font-size:.72rem;color:var(--text-500);margin-top:.1rem;
                            white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escHtml(alerta.corpo)}</div>
            </div>
            <button onclick="event.stopPropagation();this.closest('[style]').remove()"
                    style="background:none;border:none;color:var(--text-600);cursor:pointer;
                           font-size:.75rem;padding:.1rem;flex-shrink:0;line-height:1">×</button>`;
        el.onclick = () => { window.location.href = alerta.link; };

        document.getElementById('notifPanel').appendChild(el);

        // Auto-remove após 8s
        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transform = 'translateX(100%)';
            setTimeout(() => el.remove(), 300);
        }, alerta.urgente ? 12000 : 8000);
    }

    function exibirProxima() {
        if (_notifIdx < _notifQueue.length) {
            mostrarNotif(_notifQueue[_notifIdx++]);
            if (_notifIdx < _notifQueue.length) setTimeout(exibirProxima, 1200);
        }
    }

    async function verificarNotificacoes() {
        try {
            const res  = await fetch('backend/api/notificacoes.php');
            const json = await res.json();
            if (!json.success || !json.alertas.length) return;

            _notifQueue = json.alertas;
            setTimeout(exibirProxima, 2000); // espera 2s após carregar página

            // Solicita permissão para notificações nativas
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission().then(p => {
                    if (p === 'granted') {
                        const urgentes = json.alertas.filter(a => a.urgente);
                        urgentes.forEach(a => new Notification(a.titulo, {
                            body: a.corpo,
                            icon: 'assets/icons/icon.php?size=192',
                        }));
                    }
                });
            } else if (Notification.permission === 'granted') {
                const urgentes = json.alertas.filter(a => a.urgente);
                urgentes.forEach(a => new Notification(a.titulo, {
                    body: a.corpo,
                    icon: 'assets/icons/icon.php?size=192',
                }));
            }
        } catch {}
    }

    document.addEventListener('DOMContentLoaded', verificarNotificacoes);

    // CSS da animação
    const s = document.createElement('style');
    s.textContent = '@keyframes notifIn { from { opacity:0; transform:translateX(30px) } to { opacity:1; transform:none } }';
    document.head.appendChild(s);
})();
</script>

</body>
</html>
