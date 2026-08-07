<?php
$p = TABLE_PREFIX;

$niveisMap = [
    'info'    => ['label' => 'Info',    'cor' => 'cyan',   'icon' => 'circle-info'],
    'aviso'   => ['label' => 'Aviso',   'cor' => 'amber',  'icon' => 'circle-exclamation'],
    'urgente' => ['label' => 'Urgente', 'cor' => 'rose',   'icon' => 'triangle-exclamation'],
];
$tiposMap = [
    'vencimento'    => 'Vencimento',
    'limite_cartao' => 'Limite Cartão',
    'orcamento'     => 'Orçamento',
    'meta'          => 'Meta',
    'emprestimo'    => 'Empréstimo',
    'saldo'         => 'Saldo',
    'custom'        => 'Personalizado',
];
?>

<style>
/* Modal base e barra de filtros agora vivem em assets/css/main.css. */
.alerta-row {
    display: flex; align-items: flex-start; gap: 1rem;
    padding: .85rem 1rem; border-bottom: 1px solid var(--border);
    transition: background var(--ease);
}
.alerta-row:last-child { border-bottom: none; }
.alerta-row.lido { opacity: .45; }
.alerta-row:hover { background: var(--bg-700); }
.alerta-nivel-dot {
    width: 36px; height: 36px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; margin-top: .1rem; font-size: .9rem;
}
.alerta-nivel-dot.urgente { background: var(--rose-soft);  color: var(--rose);  }
.alerta-nivel-dot.aviso   { background: var(--amber-soft); color: var(--amber); }
.alerta-nivel-dot.info    { background: var(--cyan-soft);  color: var(--cyan);  }
.alerta-titulo  { font-size: .875rem; font-weight: 600; margin-bottom: .2rem; }
.alerta-msg     { font-size: .78rem; color: var(--text-600); }
.alerta-meta    { font-size: .72rem; color: var(--text-600); margin-top: .3rem; display: flex; gap: .75rem; flex-wrap: wrap; }
.alerta-actions { margin-left: auto; display: flex; gap: .4rem; flex-shrink: 0; }
</style>

<!-- ── Cabeçalho ────────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <div class="page-title">Alertas</div>
        <div class="page-sub" id="pageSub">Carregando...</div>
    </div>
    <div class="d-flex gap-1">
        <button class="btn btn-ghost btn-sm" onclick="gerarAutos()" title="Os alertas já são gerados automaticamente; use aqui só se quiser forçar uma checagem agora">
            <i class="fa-solid fa-rotate"></i> Verificar Agora
        </button>
        <button class="btn btn-ghost btn-sm" onclick="marcarTodosLidos()">
            <i class="fa-solid fa-check-double"></i> Marcar Todos Lidos
        </button>
        <button class="btn btn-primary btn-sm" onclick="abrirModal()">
            <i class="fa-solid fa-plus"></i> Novo Alerta
        </button>
    </div>
</div>

<!-- ── KPIs ─────────────────────────────────────────────────── -->
<div class="kpi-grid" style="margin-bottom:1.25rem">
    <div class="kpi-card rose">
        <div class="kpi-header">
            <div class="kpi-label">Não Lidos</div>
            <div class="kpi-icon rose"><i class="fa-solid fa-bell"></i></div>
        </div>
        <div class="kpi-value" id="kpiNaoLido">—</div>
        <div class="kpi-trend neutral"><i class="fa-solid fa-circle-info fa-xs"></i> Pendentes de leitura</div>
    </div>
    <div class="kpi-card rose">
        <div class="kpi-header">
            <div class="kpi-label">Urgentes</div>
            <div class="kpi-icon rose"><i class="fa-solid fa-triangle-exclamation"></i></div>
        </div>
        <div class="kpi-value" id="kpiUrgente">—</div>
        <div class="kpi-trend neutral"><i class="fa-solid fa-circle-info fa-xs"></i> Atenção imediata</div>
    </div>
    <div class="kpi-card amber">
        <div class="kpi-header">
            <div class="kpi-label">Avisos</div>
            <div class="kpi-icon amber"><i class="fa-solid fa-circle-exclamation"></i></div>
        </div>
        <div class="kpi-value" id="kpiAviso">—</div>
        <div class="kpi-trend neutral"><i class="fa-solid fa-circle-info fa-xs"></i> Atenção em breve</div>
    </div>
    <div class="kpi-card indigo">
        <div class="kpi-header">
            <div class="kpi-label">Informativos</div>
            <div class="kpi-icon indigo"><i class="fa-solid fa-circle-info"></i></div>
        </div>
        <div class="kpi-value" id="kpiInfo">—</div>
        <div class="kpi-trend neutral"><i class="fa-solid fa-circle-info fa-xs"></i> Só para conhecimento</div>
    </div>
</div>

<!-- ── Filtros ──────────────────────────────────────────────── -->
<div class="filters-bar">
    <div class="filter-group">
        <span class="filter-label">Nível</span>
        <select id="filNivel" class="form-control" style="min-width:130px" onchange="carregarDados()">
            <option value="">Todos</option>
            <option value="urgente">Urgente</option>
            <option value="aviso">Aviso</option>
            <option value="info">Info</option>
        </select>
    </div>
    <div class="filter-group">
        <span class="filter-label">Tipo</span>
        <select id="filTipo" class="form-control" style="min-width:160px" onchange="carregarDados()">
            <option value="">Todos</option>
            <?php foreach ($tiposMap as $k => $v): ?>
            <option value="<?= $k ?>"><?= $v ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="filter-group">
        <span class="filter-label">Status</span>
        <select id="filLido" class="form-control" style="min-width:130px" onchange="carregarDados()">
            <option value="">Todos</option>
            <option value="0">Não lidos</option>
            <option value="1">Lidos</option>
        </select>
    </div>
</div>

<!-- ── Lista ────────────────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">Alertas</div>
            <div class="card-subtitle" id="listaInfo">—</div>
        </div>
    </div>
    <div id="listaAlerta">
        <div style="text-align:center;padding:2.5rem;color:var(--text-600)">
            <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
        </div>
    </div>
</div>

<!-- ── Modal ────────────────────────────────────────────────── -->
<div id="modalOverlay" class="modal-overlay" onclick="if(event.target===this)fecharModal()">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title" id="modalTitulo">Novo Alerta</div>
            <button type="button" class="btn-icon" onclick="fecharModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="formAlerta" onsubmit="salvarAlerta(event)">
            <div class="modal-body">
                <input type="hidden" id="editId" value="">

                <div class="form-group" style="margin-bottom:1rem">
                    <label class="form-label">Título <span style="color:var(--rose)">*</span></label>
                    <input type="text" id="fTitulo" class="form-control"
                           placeholder="Ex: Fatura do cartão vence em 3 dias" required maxlength="150">
                </div>

                <div class="form-grid form-grid-3" style="margin-bottom:1rem">
                    <div class="form-group">
                        <label class="form-label">Tipo</label>
                        <select id="fTipo" class="form-control">
                            <?php foreach ($tiposMap as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nível</label>
                        <select id="fNivel" class="form-control">
                            <option value="info">Info</option>
                            <option value="aviso" selected>Aviso</option>
                            <option value="urgente">Urgente</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data do Alerta</label>
                        <input type="date" id="fData" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Mensagem</label>
                    <textarea id="fMensagem" class="form-control" rows="3"
                              placeholder="Detalhes adicionais (opcional)"></textarea>
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
let _alertas = [];

const nivelMeta = {
    urgente: { icon: 'fa-triangle-exclamation', cls: 'urgente', label: 'Urgente' },
    aviso:   { icon: 'fa-circle-exclamation',   cls: 'aviso',   label: 'Aviso'   },
    info:    { icon: 'fa-circle-info',           cls: 'info',    label: 'Info'    },
};

const tiposLabel = <?= json_encode($tiposMap) ?>;

// ── Carregar ──────────────────────────────────────────────────
async function carregarDados() {
    const nivel = document.getElementById('filNivel').value;
    const tipo  = document.getElementById('filTipo').value;
    const lido  = document.getElementById('filLido').value;

    const params = new URLSearchParams({ acao: 'listar' });
    if (nivel) params.set('nivel', nivel);
    if (tipo)  params.set('tipo', tipo);
    if (lido !== '') params.set('lido', lido);

    document.getElementById('listaAlerta').innerHTML =
        `<div style="text-align:center;padding:2.5rem;color:var(--text-600)">
            <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
        </div>`;

    try {
        const res  = await fetch('backend/api/alertas.php?' + params);
        const json = await res.json();
        if (!json.success) throw new Error(json.erro || 'Erro ao buscar alertas');
        _alertas = json.dados;
        atualizarKPIs(json.kpi);
        renderLista(json.dados);
    } catch (err) {
        document.getElementById('listaAlerta').innerHTML =
            `<div style="text-align:center;padding:2rem;color:var(--rose)">
                <i class="fa-solid fa-triangle-exclamation"></i> ${esc(err.message)}
            </div>`;
    }
}

// ── KPIs ──────────────────────────────────────────────────────
function atualizarKPIs(kpi) {
    document.getElementById('kpiNaoLido').textContent = kpi.nao_lido ?? 0;
    document.getElementById('kpiUrgente').textContent = kpi.urgente  ?? 0;
    document.getElementById('kpiAviso').textContent   = kpi.aviso    ?? 0;
    document.getElementById('kpiInfo').textContent    = kpi.info     ?? 0;
    document.getElementById('pageSub').textContent    = `${kpi.nao_lido ?? 0} não lidos · ${kpi.total ?? 0} total`;
}

// ── Render ────────────────────────────────────────────────────
function renderLista(dados) {
    const wrap = document.getElementById('listaAlerta');
    document.getElementById('listaInfo').textContent =
        `${dados.length} alerta${dados.length !== 1 ? 's' : ''} exibido${dados.length !== 1 ? 's' : ''}`;

    if (!dados.length) {
        wrap.innerHTML = `
            <div style="text-align:center;padding:3rem;color:var(--text-600)">
                <i class="fa-solid fa-bell-slash fa-2x" style="margin-bottom:.75rem;display:block"></i>
                Nenhum alerta encontrado.
            </div>`;
        return;
    }

    wrap.innerHTML = dados.map(al => {
        const meta  = nivelMeta[al.nivel] || nivelMeta.info;
        const data  = al.data_alerta ? fmtData(al.data_alerta) : '';
        const criado = fmtDataHora(al.criado_em);
        const tipoLabel = tiposLabel[al.tipo] || al.tipo;

        return `<div class="alerta-row ${al.lido == 1 ? 'lido' : ''}">
            <div class="alerta-nivel-dot ${meta.cls}">
                <i class="fa-solid ${meta.icon}"></i>
            </div>
            <div style="flex:1;min-width:0">
                <div class="alerta-titulo">${esc(al.titulo)}</div>
                ${al.mensagem ? `<div class="alerta-msg">${esc(al.mensagem)}</div>` : ''}
                <div class="alerta-meta">
                    <span><i class="fa-solid fa-tag fa-xs"></i> ${esc(tipoLabel)}</span>
                    <span><span class="badge ${meta.cls}" style="font-size:.68rem;padding:.1rem .45rem">${meta.label}</span></span>
                    ${data ? `<span><i class="fa-solid fa-calendar-day fa-xs"></i> ${data}</span>` : ''}
                    <span style="color:var(--text-600)">${criado}</span>
                    ${al.lido == 1 ? '<span style="color:var(--emerald)"><i class="fa-solid fa-check fa-xs"></i> Lido</span>' : ''}
                </div>
            </div>
            <div class="alerta-actions">
                ${al.lido != 1 ? `
                <button class="btn-icon" onclick="marcarLido(${+al.id})" title="Marcar como lido"
                        style="width:28px;height:28px;font-size:.75rem;color:var(--emerald)">
                    <i class="fa-solid fa-check"></i>
                </button>` : ''}
                <button class="btn-icon" onclick="abrirModal(${+al.id})" title="Editar"
                        style="width:28px;height:28px;font-size:.75rem">
                    <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button class="btn-icon" onclick="excluir(${+al.id})" title="Excluir"
                        style="width:28px;height:28px;font-size:.75rem;color:var(--rose)">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>`;
    }).join('');
}

// ── Modal ─────────────────────────────────────────────────────
function abrirModal(id = null) {
    document.getElementById('formAlerta').reset();
    document.getElementById('editId').value = id || '';
    if (id) {
        document.getElementById('modalTitulo').textContent = 'Editar Alerta';
        const al = _alertas.find(a => +a.id === +id);
        if (al) {
            document.getElementById('fTitulo').value   = al.titulo   || '';
            document.getElementById('fTipo').value     = al.tipo     || 'custom';
            document.getElementById('fNivel').value    = al.nivel    || 'aviso';
            document.getElementById('fMensagem').value = al.mensagem || '';
            document.getElementById('fData').value     = (al.data_alerta || '').split('T')[0];
        }
    } else {
        document.getElementById('modalTitulo').textContent = 'Novo Alerta';
    }
    document.getElementById('modalOverlay').classList.add('open');
    setTimeout(() => document.getElementById('fTitulo').focus(), 80);
}

function fecharModal() {
    document.getElementById('modalOverlay').classList.remove('open');
}

// ── Salvar ────────────────────────────────────────────────────
async function salvarAlerta(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSalvar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Salvando...';

    const id = document.getElementById('editId').value;
    const payload = {
        acao:       'salvar',
        titulo:     document.getElementById('fTitulo').value.trim(),
        tipo:       document.getElementById('fTipo').value,
        nivel:      document.getElementById('fNivel').value,
        mensagem:   document.getElementById('fMensagem').value.trim(),
        data_alerta: document.getElementById('fData').value || null,
    };
    if (id) payload.id = parseInt(id);

    try {
        const res  = await fetch('backend/api/alertas.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro || 'Erro ao salvar');
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

// ── Marcar lido ───────────────────────────────────────────────
async function marcarLido(id) {
    try {
        const res  = await fetch('backend/api/alertas.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'marcar_lido', id }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        carregarDados();
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

function marcarTodosLidos() {
    confirmar('Marcar todos como lidos', 'Todos os alertas ativos serão marcados como lidos.', async () => {
        try {
            const res  = await fetch('backend/api/alertas.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ acao: 'marcar_lido', id: 0 }),
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.erro);
            toast(json.msg, 'success');
            carregarDados();
        } catch (err) { toast('Erro: ' + err.message, 'error'); }
    }, { btnLabel: 'Confirmar', icone: 'check-double', btnCor: 'var(--emerald)' });
}

// ── Gerar automáticos ─────────────────────────────────────────
async function gerarAutos() {
    const btn = event.currentTarget;
    btn.disabled = true;
    try {
        const res  = await fetch('backend/api/alertas.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'gerar' }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, json.gerados > 0 ? 'success' : 'info');
        carregarDados();
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
    finally { btn.disabled = false; }
}

// ── Excluir ───────────────────────────────────────────────────
function excluir(id) {
    const al   = _alertas.find(a => +a.id === +id);
    const nome = al ? `"${al.titulo}"` : 'este alerta';
    confirmar('Excluir alerta', `Excluir ${nome}? Esta ação não pode ser desfeita.`, async () => {
        try {
            const res  = await fetch(`backend/api/alertas.php?id=${id}`, { method: 'DELETE' });
            const json = await res.json();
            if (!json.success) throw new Error(json.erro);
            toast(json.msg || 'Removido.', 'success');
            carregarDados();
        } catch (err) { toast('Erro: ' + err.message, 'error'); }
    });
}

// ── Helpers ───────────────────────────────────────────────────
// esc()/fmtData() vêm de assets/js/app.js (carregado globalmente por index.php)
function fmtDataHora(s) {
    if (!s) return '';
    const dt = new Date(s);
    return dt.toLocaleString('pt-BR', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' });
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharModal(); });
document.addEventListener('DOMContentLoaded', carregarDados);
</script>
