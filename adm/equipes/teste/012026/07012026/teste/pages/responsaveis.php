<?php
// Responsáveis usa backend/api/responsaveis.php
$p = TABLE_PREFIX;
$iconesPadrao = [
    'user','user-tie','user-nurse','user-graduate','person','child',
    'venus','mars','people-group','briefcase','house','laptop',
    'star','heart','crown','shield',
];
?>

<style>
/* Modal base agora vive em assets/css/main.css. */
.icone-grid {
    display:grid; grid-template-columns:repeat(auto-fill,minmax(40px,1fr)); gap:.4rem;
    padding:.25rem;
}
.icone-opt {
    width:40px; height:40px; border-radius:var(--radius-sm); border:2px solid transparent;
    display:flex; align-items:center; justify-content:center; cursor:pointer;
    background:var(--bg-700); color:var(--text-400); font-size:.85rem;
    transition:all var(--ease);
}
.icone-opt:hover { border-color:var(--indigo); color:var(--text-100); }
.icone-opt.ativo { border-color:var(--indigo); background:var(--indigo-soft); color:var(--indigo); }
.resp-avatar {
    width:38px; height:38px; border-radius:50%; display:flex;
    align-items:center; justify-content:center; font-size:.85rem; flex-shrink:0;
}
</style>

<!-- ── Cabeçalho ────────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <div class="page-title">Responsáveis</div>
        <div class="page-sub" id="pageSub">Carregando...</div>
    </div>
    <button class="btn btn-primary btn-sm" onclick="abrirModal()">
        <i class="fa-solid fa-plus"></i> Novo Responsável
    </button>
</div>

<!-- ── KPIs ─────────────────────────────────────────────────── -->
<div class="kpi-grid" style="margin-bottom:1.25rem">
    <div class="kpi-card indigo">
        <div class="kpi-header">
            <div class="kpi-label">Total</div>
            <div class="kpi-icon indigo"><i class="fa-solid fa-people-group"></i></div>
        </div>
        <div class="kpi-value" id="kpiTotal">—</div>
        <div class="kpi-trend neutral"><i class="fa-solid fa-circle-info fa-xs"></i> Todos os responsáveis</div>
    </div>
    <div class="kpi-card emerald">
        <div class="kpi-header">
            <div class="kpi-label">Ativos</div>
            <div class="kpi-icon emerald"><i class="fa-solid fa-circle-check"></i></div>
        </div>
        <div class="kpi-value" id="kpiAtivos">—</div>
        <div class="kpi-trend neutral"><i class="fa-solid fa-circle-info fa-xs"></i> Em uso no sistema</div>
    </div>
    <div class="kpi-card rose">
        <div class="kpi-header">
            <div class="kpi-label">Total Despesas</div>
            <div class="kpi-icon rose"><i class="fa-solid fa-arrow-trend-down"></i></div>
        </div>
        <div class="kpi-value" id="kpiDespesas">—</div>
        <div class="kpi-trend neutral"><i class="fa-solid fa-circle-info fa-xs"></i> Acumulado geral</div>
    </div>
    <div class="kpi-card emerald">
        <div class="kpi-header">
            <div class="kpi-label">Total Receitas</div>
            <div class="kpi-icon emerald"><i class="fa-solid fa-arrow-trend-up"></i></div>
        </div>
        <div class="kpi-value" id="kpiReceitas">—</div>
        <div class="kpi-trend neutral"><i class="fa-solid fa-circle-info fa-xs"></i> Acumulado geral</div>
    </div>
</div>

<!-- ── Tabela ───────────────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">Responsáveis</div>
            <div class="card-subtitle" id="tabelaInfo">—</div>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th class="text-right">Transações</th>
                    <th class="text-right">Despesas</th>
                    <th class="text-right">Receitas</th>
                    <th>Status</th>
                    <th style="width:96px"></th>
                </tr>
            </thead>
            <tbody id="tabelaBody">
                <tr>
                    <td colspan="6" style="text-align:center;padding:2.5rem;color:var(--text-600)">
                        <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Modal ────────────────────────────────────────────────── -->
<div id="modalOverlay" class="modal-overlay" onclick="if(event.target===this)fecharModal()">
    <div class="modal-box" style="max-width:500px">
        <div class="modal-header">
            <div class="modal-title" id="modalTitulo">Novo Responsável</div>
            <button type="button" class="btn-icon" onclick="fecharModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="formResp" onsubmit="salvar(event)">
            <div class="modal-body">
                <input type="hidden" id="editId" value="">

                <div class="form-group" style="margin-bottom:1rem">
                    <label class="form-label">Nome <span style="color:var(--rose)">*</span></label>
                    <input type="text" id="fNome" class="form-control"
                           placeholder="Ex: João, Maria, Empresa..." required maxlength="100">
                </div>

                <div class="form-group" style="margin-bottom:1rem">
                    <label class="form-label">Cor</label>
                    <div class="d-flex align-center gap-1">
                        <input type="color" id="fCor" value="#6366f1"
                               style="width:44px;height:38px;border-radius:var(--radius-sm);border:1px solid var(--border);padding:2px;background:var(--bg-700);cursor:pointer">
                        <span id="fCorText" class="text-sm text-muted">#6366f1</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Ícone</label>
                    <input type="hidden" id="fIcone" value="user">
                    <div class="icone-grid">
                        <?php foreach ($iconesPadrao as $ic): ?>
                        <div class="icone-opt <?= $ic === 'user' ? 'ativo' : '' ?>"
                             data-ic="<?= $ic ?>" onclick="selecionarIcone(this)" title="<?= $ic ?>">
                            <i class="fa-solid fa-<?= $ic ?>"></i>
                        </div>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="fecharModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnSalvar">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let _resps = [];

// ── Carregar ──────────────────────────────────────────────────
async function carregarDados() {
    try {
        const res  = await fetch('backend/api/responsaveis.php');
        const json = await res.json();
        if (!json.success) throw new Error(json.erro || 'Erro');
        _resps = json.dados;
        atualizarKPIs();
        renderTabela();
    } catch (err) {
        document.getElementById('tabelaBody').innerHTML =
            `<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--rose)">
                <i class="fa-solid fa-triangle-exclamation"></i> ${esc(err.message)}
            </td></tr>`;
    }
}

function atualizarKPIs() {
    const ativos    = _resps.filter(r => r.ativo == 1);
    const totDes    = _resps.reduce((s, r) => s + parseFloat(r.total_despesas || 0), 0);
    const totRec    = _resps.reduce((s, r) => s + parseFloat(r.total_receitas || 0), 0);
    document.getElementById('kpiTotal').textContent   = _resps.length;
    document.getElementById('kpiAtivos').textContent  = ativos.length;
    document.getElementById('kpiDespesas').textContent = brl(totDes);
    document.getElementById('kpiReceitas').textContent = brl(totRec);
    document.getElementById('pageSub').textContent = `${_resps.length} responsável${_resps.length !== 1 ? 'is' : ''} cadastrado${_resps.length !== 1 ? 's' : ''}`;
}

// ── Render ────────────────────────────────────────────────────
function renderTabela() {
    document.getElementById('tabelaInfo').textContent =
        `${_resps.length} responsável${_resps.length !== 1 ? 'is' : ''}`;

    if (!_resps.length) {
        document.getElementById('tabelaBody').innerHTML =
            `<tr><td colspan="6" style="text-align:center;padding:2.5rem;color:var(--text-600)">
                <i class="fa-solid fa-people-group fa-2x" style="margin-bottom:.75rem;display:block"></i>
                Nenhum responsável cadastrado.
            </td></tr>`;
        return;
    }

    document.getElementById('tabelaBody').innerHTML = _resps.map(r => {
        const saldo = parseFloat(r.total_receitas||0) - parseFloat(r.total_despesas||0);
        return `<tr>
            <td>
                <div class="d-flex align-center gap-1">
                    <div class="resp-avatar"
                         style="background:${esc(r.cor||'#6366f1')}22;color:${esc(r.cor||'#6366f1')}">
                        <i class="fa-solid fa-${esc(r.icone||'user')}"></i>
                    </div>
                    <div>
                        <div class="fw-600 text-sm">${esc(r.nome)}</div>
                        <div class="text-xs" style="color:${esc(r.cor||'#6366f1')}">
                            saldo: <span class="${saldo >= 0 ? 'text-emerald' : 'text-rose'} fw-600">${saldo >= 0 ? '+' : ''}${brl(saldo)}</span>
                        </div>
                    </div>
                </div>
            </td>
            <td class="text-right fw-600">${r.total_transacoes || 0}</td>
            <td class="text-right text-rose fw-600">${brl(parseFloat(r.total_despesas||0))}</td>
            <td class="text-right text-emerald fw-600">${brl(parseFloat(r.total_receitas||0))}</td>
            <td>
                <span class="badge ${r.ativo == 1 ? 'pago' : 'cancelado'}">
                    ${r.ativo == 1 ? 'Ativo' : 'Inativo'}
                </span>
            </td>
            <td>
                <div class="d-flex gap-1" style="justify-content:flex-end">
                    <button class="btn-icon" onclick="abrirModal(${+r.id})" title="Editar"
                            style="width:28px;height:28px;font-size:.75rem">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button class="btn-icon" onclick="toggle(${+r.id})"
                            title="${r.ativo == 1 ? 'Desativar' : 'Ativar'}"
                            style="width:28px;height:28px;font-size:.75rem;color:${r.ativo == 1 ? 'var(--amber)' : 'var(--emerald)'}">
                        <i class="fa-solid fa-${r.ativo == 1 ? 'toggle-on' : 'toggle-off'}"></i>
                    </button>
                    <button class="btn-icon" onclick="excluir(${+r.id})" title="Excluir"
                            style="width:28px;height:28px;font-size:.75rem;color:var(--rose)">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ── Modal ─────────────────────────────────────────────────────
function selecionarIcone(el) {
    document.querySelectorAll('.icone-opt').forEach(o => o.classList.remove('ativo'));
    el.classList.add('ativo');
    document.getElementById('fIcone').value = el.dataset.ic;
}

function abrirModal(id = null) {
    document.getElementById('formResp').reset();
    document.getElementById('editId').value = id || '';
    document.getElementById('fCor').value   = '#6366f1';
    document.getElementById('fCorText').textContent = '#6366f1';
    document.querySelectorAll('.icone-opt').forEach(o => o.classList.remove('ativo'));
    document.querySelector('.icone-opt[data-ic="user"]')?.classList.add('ativo');
    document.getElementById('fIcone').value = 'user';

    if (id) {
        document.getElementById('modalTitulo').textContent = 'Editar Responsável';
        const r = _resps.find(x => +x.id === +id);
        if (r) {
            document.getElementById('fNome').value  = r.nome  || '';
            document.getElementById('fCor').value   = r.cor   || '#6366f1';
            document.getElementById('fCorText').textContent = r.cor || '#6366f1';
            document.getElementById('fIcone').value = r.icone || 'user';
            document.querySelectorAll('.icone-opt').forEach(o => o.classList.remove('ativo'));
            document.querySelector(`.icone-opt[data-ic="${r.icone||'user'}"]`)?.classList.add('ativo');
        }
    } else {
        document.getElementById('modalTitulo').textContent = 'Novo Responsável';
    }

    document.getElementById('modalOverlay').classList.add('open');
    setTimeout(() => document.getElementById('fNome').focus(), 80);
}

function fecharModal() { document.getElementById('modalOverlay').classList.remove('open'); }

document.getElementById('fCor').addEventListener('input', e => {
    document.getElementById('fCorText').textContent = e.target.value;
});

// ── Salvar ────────────────────────────────────────────────────
async function salvar(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSalvar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Salvando...';

    const id = document.getElementById('editId').value;
    const payload = {
        acao:  'salvar',
        nome:  document.getElementById('fNome').value.trim(),
        cor:   document.getElementById('fCor').value,
        icone: document.getElementById('fIcone').value,
    };
    if (id) payload.id = parseInt(id);

    try {
        const res  = await fetch('backend/api/responsaveis.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro || 'Erro');
        toast(json.msg || 'Salvo!', 'success');
        fecharModal();
        carregarDados();
    } catch (err) {
        toast('Erro: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Salvar';
    }
}

// ── Toggle ────────────────────────────────────────────────────
async function toggle(id) {
    try {
        const res  = await fetch('backend/api/responsaveis.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'toggle', id }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        carregarDados();
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

// ── Excluir ───────────────────────────────────────────────────
async function excluir(id) {
    const r = _resps.find(x => +x.id === +id);
    confirmar('Excluir responsável',
        `Excluir "${r ? r.nome : 'este responsável'}"? As transações vinculadas perderão o responsável.`,
        async () => {
            try {
                const res  = await fetch(`backend/api/responsaveis.php?id=${id}`, { method: 'DELETE' });
                const json = await res.json();
                if (!json.success) throw new Error(json.erro);
                toast(json.msg || 'Removido.', 'success');
                carregarDados();
            } catch (err) { toast('Erro: ' + err.message, 'error'); }
        });
}

// esc() vem de assets/js/app.js (carregado globalmente por index.php)

// brl() já vem de assets/js/app.js (carregado globalmente por index.php).

document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharModal(); });
document.addEventListener('DOMContentLoaded', carregarDados);
</script>
