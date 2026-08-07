// Controle Financeiro — app.js

// ── Notificações Push / Browser ───────────────────────────────
const _NOTIF_KEY = 'financeOS_ultima_notif';

async function solicitarNotificacoes() {
    if (!('Notification' in window)) {
        toast('Seu navegador não suporta notificações.', 'warning'); return;
    }
    const perm = await Notification.requestPermission();
    if (perm === 'granted') {
        toast('Notificações ativadas! Você será avisado sobre vencimentos.', 'success');
        verificarVencimentos(true);
    } else {
        toast('Permissão de notificação negada.', 'error');
    }
}

async function verificarVencimentos(forcar = false) {
    if (Notification.permission !== 'granted') return;

    // Throttle: verifica no máximo 1x por hora
    const ultima = parseInt(localStorage.getItem(_NOTIF_KEY) || '0');
    if (!forcar && Date.now() - ultima < 3_600_000) return;
    localStorage.setItem(_NOTIF_KEY, Date.now());

    try {
        // Busca transações pendentes de hoje e amanhã
        const hoje   = new Date().toISOString().split('T')[0];
        const amanha = new Date(Date.now() + 86_400_000).toISOString().split('T')[0];
        const mes    = parseInt(hoje.split('-')[1]);
        const ano    = parseInt(hoje.split('-')[0]);

        const res  = await fetch(`backend/api/calendario.php?mes=${mes}&ano=${ano}`);
        const json = await res.json();
        if (!json.success) return;

        const pendentes = json.events.filter(e =>
            e.status === 'pendente' && e.tipo !== 'receita' &&
            (e.data.startsWith(hoje) || e.data.startsWith(amanha))
        );
        const atrasadas = json.events.filter(e =>
            e.status === 'pendente' && e.tipo !== 'receita' && e.data < hoje
        );

        if (atrasadas.length > 0) {
            const total = atrasadas.reduce((s, e) => s + parseFloat(e.valor), 0);
            _mostrarNotif(
                '⚠️ Contas em atraso',
                `${atrasadas.length} conta${atrasadas.length > 1 ? 's' : ''} em atraso — total: R$ ${total.toLocaleString('pt-BR', {minimumFractionDigits:2})}`,
                '?p=despesas'
            );
        } else if (pendentes.length > 0) {
            const total = pendentes.reduce((s, e) => s + parseFloat(e.valor), 0);
            _mostrarNotif(
                '📅 Vencimentos próximos',
                `${pendentes.length} conta${pendentes.length > 1 ? 's' : ''} vencendo hoje/amanhã — R$ ${total.toLocaleString('pt-BR', {minimumFractionDigits:2})}`,
                '?p=calendario'
            );
        }
    } catch (_) {}
}

function _mostrarNotif(titulo, corpo, url) {
    try {
        const n = new Notification(titulo, {
            body: corpo,
            icon: 'assets/icons/icon.php?size=192',
            tag:  'financeos-alert',
            requireInteraction: false,
        });
        n.onclick = () => { window.focus(); window.location.href = url; n.close(); };
    } catch (_) {}
}

// Verifica automaticamente ao carregar (se já tem permissão)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => verificarVencimentos());
} else {
    verificarVencimentos();
}

// Formata número como BRL (display)
function brl(v) {
    return 'R$ ' + parseFloat(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Converte número para string mascarada (ex: 1234.56 → "1.234,56")
function brlMask(v) {
    const cents = Math.round(parseFloat(v || 0) * 100);
    let d = String(cents);
    while (d.length < 3) d = '0' + d;
    const dec    = d.slice(-2);
    const int    = d.slice(0, -2).replace(/^0+/, '') || '0';
    const intFmt = int.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return intFmt + ',' + dec;
}

// Converte string mascarada de volta para número (ex: "1.234,56" → 1234.56)
function parseCurrency(s) {
    return parseFloat(String(s || '0').replace(/\./g, '').replace(',', '.')) || 0;
}

// Aplica máscara BRL a um input — digita da direita para a esquerda
function maskCurrency(el) {
    let raw = '';

    function render() {
        if (!raw) { el.value = ''; return; }
        let d = raw;
        while (d.length < 3) d = '0' + d;
        const dec = d.slice(-2);
        const int = d.slice(0, -2).replace(/^0+/, '') || '0';
        el.value = int.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + dec;
    }

    // Sincroniza raw sempre que o campo recebe foco —
    // garante que form.reset() ou atribuição externa limpem o raw stale
    el.addEventListener('focus', () => {
        const digits = el.value.replace(/\D/g, '');
        if (raw !== digits) raw = digits;
        el.select();
    });

    el.addEventListener('keydown', e => {
        if (e.key === 'Backspace') {
            e.preventDefault();
            raw = raw.slice(0, -1);
            render();
        } else if (/^\d$/.test(e.key)) {
            e.preventDefault();
            raw += e.key;
            render();
        }
        // Tab, Enter, setas e demais teclas passam normalmente
    });

    el.addEventListener('paste', e => {
        e.preventDefault();
        raw += (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
        render();
    });

    if (el.value) {
        raw = el.value.replace(/\D/g, '');
        render();
    }
}

// Escapa HTML antes de injetar em innerHTML/template literal (evita XSS).
// Antes duplicada em ~11 pages/*.php, uma delas (relatorios.php) sem
// escapar aspas — inconsistência corrigida ao centralizar aqui.
function esc(s) {
    return String(s || '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Formata data ISO (YYYY-MM-DD ou com hora) para dd/mm/aaaa.
// Antes duplicada em 9 pages/*.php.
function fmtData(s) {
    if (!s) return '—';
    const d = s.split('T')[0].split('-');
    return `${d[2]}/${d[1]}/${d[0]}`;
}

// Exibe toast de feedback
function toast(msg, tipo = 'info') {
    const cores = { success: '#10b981', error: '#f43f5e', info: '#6366f1', warning: '#f59e0b' };
    const t = document.createElement('div');
    t.textContent = msg;
    Object.assign(t.style, {
        position: 'fixed', bottom: '1.5rem', right: '1.5rem', zIndex: 9999,
        background: cores[tipo] || cores.info, color: '#fff',
        padding: '.75rem 1.25rem', borderRadius: '.75rem',
        fontSize: '.875rem', fontWeight: '600',
        boxShadow: '0 8px 24px rgba(0,0,0,.4)',
        transition: 'opacity .3s ease', opacity: '0',
    });
    document.body.appendChild(t);
    requestAnimationFrame(() => t.style.opacity = '1');
    setTimeout(() => {
        t.style.opacity = '0';
        setTimeout(() => t.remove(), 300);
    }, 3000);
}

// ── Upload de comprovante (foto de despesa/receita) ────────────
const COMPROVANTE_TAMANHO_MAX = 5 * 1024 * 1024; // 5 MB — mesmo limite do importador de extrato

async function uploadComprovante(file) {
    if (!file.type.startsWith('image/')) {
        toast('Selecione uma imagem (foto do comprovante).', 'warning');
        return null;
    }
    if (file.size > COMPROVANTE_TAMANHO_MAX) {
        toast('Imagem muito grande. Máximo permitido: 5 MB.', 'warning');
        return null;
    }

    const formData = new FormData();
    formData.append('comprovante', file);

    try {
        const res  = await fetch('backend/api/upload_comprovante.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro || 'Erro ao enviar imagem');
        return json.path;
    } catch (err) {
        toast('Erro ao enviar comprovante: ' + err.message, 'error');
        return null;
    }
}

// Monta o preview (thumbnail + botão remover) dentro de um container do modal.
// `path` null/vazio limpa o preview.
function renderComprovantePreview(containerEl, path) {
    if (!containerEl) return;
    if (!path) {
        containerEl.innerHTML = '';
        containerEl.style.display = 'none';
        return;
    }
    containerEl.style.display = 'flex';
    containerEl.innerHTML = `
        <a href="${escHtml(path)}" target="_blank" rel="noopener" style="display:block">
            <img src="${escHtml(path)}" alt="Comprovante" style="width:56px;height:56px;object-fit:cover;border-radius:.5rem;border:1px solid var(--border)">
        </a>
        <button type="button" class="btn-icon" title="Remover comprovante" onclick="removerComprovante()"
                style="width:26px;height:26px;font-size:.75rem;color:var(--rose)">
            <i class="fa-solid fa-xmark"></i>
        </button>`;
}

// Handlers ligados aos inputs de câmera/galeria e ao botão de remover do
// bloco "Comprovante" (mesmos IDs em pages/despesas.php e pages/receitas.php).
async function onSelecionarComprovante(inputEl) {
    const file = inputEl.files[0];
    if (!file) return;
    const path = await uploadComprovante(file);
    inputEl.value = '';
    if (!path) return;
    document.getElementById('fComprovantePath').value = path;
    renderComprovantePreview(document.getElementById('fComprovantePreview'), path);
}

function removerComprovante() {
    document.getElementById('fComprovantePath').value = '';
    renderComprovantePreview(document.getElementById('fComprovantePreview'), null);
}

// Highlight do item ativo no nav (reforço caso PHP não pegue)
document.querySelectorAll('.nav-item').forEach(link => {
    const url = new URL(link.href, location.origin);
    if (url.searchParams.get('p') === new URLSearchParams(location.search).get('p')) {
        link.classList.add('active');
    }
});

// ── Sidebar mobile (gaveta) ───────────────────────────────────
function toggleSidebar() {
    const sidebar  = document.querySelector('.sidebar');
    const overlay  = document.getElementById('sidebarOverlay');
    if (!sidebar) return;
    const aberta = sidebar.classList.toggle('aberta');
    overlay?.classList.toggle('open', aberta);
    document.body.classList.toggle('sidebar-aberta', aberta);
}

// ── Sidebar recolhível (desktop) ──────────────────────────────
function toggleSidebarCollapse() {
    const sidebar  = document.getElementById('mainSidebar');
    const layout   = document.getElementById('mainLayout');
    const btn      = document.getElementById('sidebarToggleBtn');
    if (!sidebar) return;

    const collapsed = sidebar.classList.toggle('collapsed');
    layout?.classList.toggle('sidebar-collapsed', collapsed);
    document.documentElement.removeAttribute('data-sidebar-collapsed');

    localStorage.setItem('financeOS_sidebarCollapsed', collapsed ? '1' : '0');

    if (btn) {
        btn.title = collapsed ? 'Expandir menu' : 'Recolher menu';
    }
}

// Restaura estado ao carregar (remove atributo inline e aplica classes CSS)
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('mainSidebar');
    const layout  = document.getElementById('mainLayout');
    if (!sidebar) return;

    const saved       = localStorage.getItem('financeOS_sidebarCollapsed');
    const autoCollapse = saved === null && window.innerWidth < 1200;
    const shouldCollapse = saved === '1' || autoCollapse;

    if (shouldCollapse) {
        sidebar.classList.add('collapsed');
        layout?.classList.add('sidebar-collapsed');
        const btn = document.getElementById('sidebarToggleBtn');
        if (btn) btn.title = 'Expandir menu';
    }
    // Remove early-load attribute (CSS classes tomam controle)
    document.documentElement.removeAttribute('data-sidebar-collapsed');
});

// Fecha sidebar ao clicar em link de navegação (mobile)
document.querySelectorAll('.nav-item').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            document.querySelector('.sidebar')?.classList.remove('aberta');
            document.getElementById('sidebarOverlay')?.classList.remove('open');
            document.body.classList.remove('sidebar-aberta');
        }
    });
});

// Fecha sidebar com Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelector('.sidebar')?.classList.remove('aberta');
        document.getElementById('sidebarOverlay')?.classList.remove('open');
        document.body.classList.remove('sidebar-aberta');
    }
});

// Inicializa máscaras monetárias em todos os [data-currency]
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-currency]').forEach(maskCurrency);
});

// ── Autocomplete de descrições ────────────────────────────────
// Uso: initAutocomplete('fDesc', 'despesa')
function initAutocomplete(inputId, tipo) {
    const input = document.getElementById(inputId);
    if (!input || input._acInit) return;
    input._acInit = true;

    let _timer   = null;
    let _selIdx  = -1;

    function getDropdown() { return document.getElementById('_ac_drop'); }

    function fechar() {
        getDropdown()?.remove();
        _selIdx = -1;
    }

    function posicionar(el) {
        const r = input.getBoundingClientRect();
        Object.assign(el.style, {
            top:      (r.bottom + window.scrollY + 4) + 'px',
            left:     (r.left   + window.scrollX)     + 'px',
            width:    r.width + 'px',
        });
    }

    function renderizar(sugestoes) {
        fechar();
        if (!sugestoes.length) return;

        const el = document.createElement('div');
        el.id = '_ac_drop';
        Object.assign(el.style, {
            position:   'absolute',
            background: 'var(--bg-700)',
            border:     '1px solid var(--border)',
            borderRadius: 'var(--radius)',
            boxShadow:  'var(--shadow-lg)',
            zIndex:     '9300',
            maxHeight:  '220px',
            overflowY:  'auto',
            fontSize:   '.875rem',
        });
        posicionar(el);

        sugestoes.forEach((s, i) => {
            const item = document.createElement('div');
            item.dataset.val = s;
            item.style.cssText =
                'padding:.55rem .875rem;cursor:pointer;border-bottom:1px solid var(--border);' +
                'display:flex;align-items:center;gap:.5rem;color:var(--text-200)';
            item.innerHTML = `<i class="fa-solid fa-clock-rotate-left fa-xs" style="color:var(--text-600);flex-shrink:0"></i><span>${escHtml(s)}</span>`;

            item.addEventListener('mouseenter', () => {
                el.querySelectorAll('[data-val]').forEach(x => x.style.background = '');
                item.style.background = 'var(--bg-600)';
                _selIdx = i;
            });
            item.addEventListener('mousedown', e => {
                e.preventDefault();
                input.value = s;
                input.dispatchEvent(new Event('focus'));
                fechar();
                input.focus();
            });
            el.appendChild(item);
        });

        document.body.appendChild(el);
    }

    async function buscar() {
        const q = input.value.trim();
        if (q.length < 2) { fechar(); return; }
        try {
            const res  = await fetch(`backend/api/autocomplete.php?q=${encodeURIComponent(q)}&tipo=${tipo}`);
            const json = await res.json();
            if (json.success) renderizar(json.dados);
        } catch (_) { fechar(); }
    }

    input.addEventListener('input', () => {
        clearTimeout(_timer);
        _timer = setTimeout(buscar, 220);
    });

    input.addEventListener('keydown', e => {
        const el    = getDropdown();
        if (!el) return;
        const items = [...el.querySelectorAll('[data-val]')];

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            _selIdx = Math.min(_selIdx + 1, items.length - 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            _selIdx = Math.max(_selIdx - 1, 0);
        } else if (e.key === 'Enter' && _selIdx >= 0) {
            e.preventDefault();
            input.value = items[_selIdx].dataset.val;
            input.dispatchEvent(new Event('focus'));
            fechar();
            return;
        } else if (e.key === 'Escape') {
            fechar(); return;
        }

        items.forEach((x, i) => x.style.background = i === _selIdx ? 'var(--bg-600)' : '');
    });

    input.addEventListener('blur', () => setTimeout(fechar, 180));

    window.addEventListener('scroll', () => {
        const el = getDropdown();
        if (el) posicionar(el);
    }, true);
    window.addEventListener('resize', () => {
        const el = getDropdown();
        if (el) posicionar(el);
    });
}

// ── UI do alternador "Mês/Ano vs Período" ─────────────────────
// Alterna a exibição dos dois blocos de filtro de data e o destaque
// visual dos botões. Antes essa mesma lógica de DOM estava copiada
// dentro de setModoFiltro() em receitas.php, despesas.php, analise.php
// e terceiros.php, e duas vezes dentro de relatorios.php (abas Resumo/
// Categorias, por isso o parâmetro sufixo). Cada página continua com
// sua própria função setModoFiltro(...) — só a parte de DOM foi
// centralizada aqui; decidir SE/COMO recarregar os dados depois de
// trocar o modo continua responsabilidade de cada página.
function aplicarModoFiltroUI(modo, sufixo = '') {
    const elMes = document.getElementById('filtroMes' + sufixo);
    const elPer = document.getElementById('filtroPeriodo' + sufixo);
    if (elMes) elMes.style.display = modo === 'mes'     ? 'flex' : 'none';
    if (elPer) elPer.style.display = modo === 'periodo' ? 'flex' : 'none';
    const bm = document.getElementById('fBtnMes' + sufixo);
    const bp = document.getElementById('fBtnPer' + sufixo);
    if (bm) {
        bm.style.background = modo === 'mes' ? 'var(--indigo)' : 'transparent';
        bm.style.color      = modo === 'mes' ? '#fff'          : 'var(--text-500)';
    }
    if (bp) {
        bp.style.background = modo === 'periodo' ? 'var(--indigo)' : 'transparent';
        bp.style.color      = modo === 'periodo' ? '#fff'          : 'var(--text-500)';
    }
}

// ── Utilitário de escape HTML ─────────────────────────────────
function escHtml(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ── Modal de confirmação customizado ─────────────────────────
// Uso simples:   confirmar('Excluir "Supermercado"?', () => deletar(id))
// Com título:    confirmar('Excluir despesa', 'Excluir "Supermercado"?', () => deletar(id))
// Com opções:    confirmar('Título', 'Msg', cb, { btnLabel:'Sim', icone:'check', btnCor:'var(--emerald)' })
function confirmar(tituloOuMsg, msgOuCb, callbackOuOpts, opts = {}) {
    // Suporta assinatura com 2 args: confirmar(msg, callback)
    let titulo, msg, callback;
    if (typeof msgOuCb === 'function') {
        titulo   = 'Confirmar ação';
        msg      = tituloOuMsg;
        callback = msgOuCb;
        opts     = callbackOuOpts || {};
    } else {
        titulo   = tituloOuMsg;
        msg      = msgOuCb;
        callback = callbackOuOpts;
    }

    document.getElementById('_cfModal')?.remove();

    const btnLabel = opts.btnLabel || 'Excluir';
    const icone    = opts.icone    || 'trash';
    const btnCor   = opts.btnCor   || 'var(--rose)';

    const el = document.createElement('div');
    el.id    = '_cfModal';
    el.style.cssText =
        'display:flex;position:fixed;inset:0;background:rgba(0,0,0,.65);' +
        'z-index:9100;align-items:center;justify-content:center;padding:1rem';

    el.innerHTML = `
        <div style="background:var(--bg-800);border:1px solid var(--border);
                    border-radius:var(--radius-lg);width:100%;max-width:420px;
                    box-shadow:var(--shadow-lg);animation:modalIn .18s ease">
            <div style="padding:1.1rem 1.5rem;border-bottom:1px solid var(--border);
                        display:flex;align-items:center;gap:.75rem">
                <div style="width:34px;height:34px;border-radius:50%;flex-shrink:0;
                            background:rgba(244,63,94,.15);color:var(--rose);
                            display:flex;align-items:center;justify-content:center">
                    <i class="fa-solid fa-triangle-exclamation fa-sm"></i>
                </div>
                <div style="font-size:.95rem;font-weight:700">${escHtml(titulo)}</div>
            </div>
            <div style="padding:1.1rem 1.5rem">
                <p style="font-size:.875rem;color:var(--text-200);line-height:1.55" id="_cfMsg"></p>
            </div>
            <div style="padding:.875rem 1.5rem;border-top:1px solid var(--border);
                        display:flex;justify-content:flex-end;gap:.6rem">
                <button class="btn btn-ghost" id="_cfCancel">Cancelar</button>
                <button class="btn" id="_cfOk"
                        style="background:${btnCor};color:#fff;border-color:${btnCor};gap:.45rem">
                    <i class="fa-solid fa-${escHtml(icone)} fa-sm"></i> ${escHtml(btnLabel)}
                </button>
            </div>
        </div>`;

    document.body.appendChild(el);
    const _cfMsgEl = document.getElementById('_cfMsg');
    _cfMsgEl.textContent = msg;

    const fechar = () => el.remove();
    el.addEventListener('click', e => { if (e.target === el) fechar(); });
    document.getElementById('_cfCancel').addEventListener('click', fechar);
    document.getElementById('_cfOk').addEventListener('click', () => { fechar(); callback(); });
    document.getElementById('_cfOk').focus();

    const onKey = e => { if (e.key === 'Escape') { fechar(); document.removeEventListener('keydown', onKey); } };
    document.addEventListener('keydown', onKey);
}

// ── Paginação client-side ─────────────────────────────────────
// Uso: criar um objeto Paginacao e chamar .render(dados, callback)
function criarPaginacao(idWrap, idInfo, idBtns, idPorPag) {
    let _pag = 1;

    function getPorPag() {
        return parseInt(document.getElementById(idPorPag)?.value || '25');
    }

    function paginar(dados) {
        const pp  = getPorPag();
        const tot = dados.length;
        const tPags = Math.max(1, Math.ceil(tot / pp));
        if (_pag > tPags) _pag = tPags;
        const ini  = (_pag - 1) * pp;
        return { slice: dados.slice(ini, ini + pp), pagina: _pag, totalPags: tPags, total: tot, ini };
    }

    function renderControles(total, totalPags, onMudar) {
        const pp  = getPorPag();
        const ini = (_pag - 1) * pp + 1;
        const fim = Math.min(_pag * pp, total);

        const infoEl = document.getElementById(idInfo);
        if (infoEl) infoEl.textContent = total > 0
            ? `Exibindo ${ini}–${fim} de ${total}`
            : 'Nenhum registro';

        const btnsEl = document.getElementById(idBtns);
        if (!btnsEl) return;

        if (totalPags <= 1) { btnsEl.innerHTML = ''; return; }

        const ir = p => { _pag = p; onMudar(); };
        let html = `
            <button class="btn btn-ghost btn-sm" onclick="(${ir})(1)" ${_pag===1?'disabled':''}>«</button>
            <button class="btn btn-ghost btn-sm" onclick="(${ir})(${_pag-1})" ${_pag===1?'disabled':''}>‹</button>`;

        const inicio = Math.max(1, _pag - 2);
        const fim2   = Math.min(totalPags, _pag + 2);
        if (inicio > 1) html += `<span class="text-muted text-sm" style="padding:0 .25rem">…</span>`;
        for (let p = inicio; p <= fim2; p++) {
            html += `<button class="btn btn-sm ${p===_pag?'btn-primary':'btn-ghost'}" onclick="(${ir})(${p})">${p}</button>`;
        }
        if (fim2 < totalPags) html += `<span class="text-muted text-sm" style="padding:0 .25rem">…</span>`;

        html += `
            <button class="btn btn-ghost btn-sm" onclick="(${ir})(${_pag+1})" ${_pag===totalPags?'disabled':''}>›</button>
            <button class="btn btn-ghost btn-sm" onclick="(${ir})(${totalPags})" ${_pag===totalPags?'disabled':''}>»</button>`;

        btnsEl.innerHTML = html;
    }

    return {
        reset() { _pag = 1; },
        paginar,
        renderControles,
    };
}
