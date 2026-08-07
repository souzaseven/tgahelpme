<?php
// ── Dados iniciais ────────────────────────────────────────────
$p = TABLE_PREFIX;

$configs = $pdo->query("SELECT chave, valor FROM `{$p}configuracoes`")->fetchAll();
$cfg = [];
foreach ($configs as $c) $cfg[$c['chave']] = $c['valor'];

// Remove duplicatas silenciosamente (mantém menor id de cada nome+tipo)
try {
    $pdo->exec(
        "DELETE c FROM `{$p}categorias` c
         INNER JOIN (
             SELECT MIN(id) min_id, nome, tipo FROM `{$p}categorias` GROUP BY nome, tipo HAVING COUNT(*) > 1
         ) d ON c.nome = d.nome AND c.tipo = d.tipo AND c.id <> d.min_id"
    );
} catch (Throwable $e) { /* ignora se não houver duplicatas */ }

$categorias = $pdo->query(
    "SELECT c.*, cp.nome pai_nome, cp.cor pai_cor, cp.icone pai_icone
     FROM `{$p}categorias` c
     LEFT JOIN `{$p}categorias` cp ON cp.id = c.categoria_pai
     ORDER BY c.tipo,
              COALESCE(cp.nome, c.nome),
              c.categoria_pai IS NOT NULL,
              c.nome"
)->fetchAll();

// Monta árvore: pais com filhos agrupados
$_catPais = array_filter($categorias, fn($c) => !$c['categoria_pai']);
$_catSubs = [];
foreach ($categorias as $c) {
    if ($c['categoria_pai']) $_catSubs[$c['categoria_pai']][] = $c;
}

try {
    $responsaveis = $pdo->query(
        "SELECT r.*, COUNT(t.id) total
         FROM `{$p}responsaveis` r
         LEFT JOIN `{$p}transacoes` t ON t.responsavel_id = r.id
         GROUP BY r.id ORDER BY r.ativo DESC, r.nome"
    )->fetchAll();
} catch (PDOException $e) { $responsaveis = []; }

// ── Dados de apoio para a aba "Atalhos de preenchimento" ────────────────────
$_atalhoContas  = $pdo->query("SELECT id, nome FROM `{$p}contas`  WHERE ativo=1 ORDER BY nome")->fetchAll();
$_atalhoCartoes = $pdo->query("SELECT id, nome FROM `{$p}cartoes` WHERE ativo=1 ORDER BY nome")->fetchAll();
try {
    $_atalhoTerceiros = $pdo->query("SELECT id, nome FROM `{$p}terceiros` WHERE ativo=1 ORDER BY nome")->fetchAll();
} catch (PDOException $e) { $_atalhoTerceiros = []; }

$anoAtual = (int) date('Y');
$mesAtual = (int) date('m');
$nomesMeses = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho',
               'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
?>

<style>
/* ── Tabs ────────────────────────────────────────────────── */
.cfg-tabs { display:flex; gap:0; border-bottom:2px solid var(--border); margin-bottom:1.5rem; overflow-x:auto; scrollbar-width:none; }
.cfg-tab  { padding:.6rem 1.1rem; font-size:.85rem; font-weight:500; color:var(--text-400); background:none; border:none; border-bottom:2px solid transparent; margin-bottom:-2px; cursor:pointer; white-space:nowrap; transition:color .15s,border-color .15s; }
.cfg-tab:hover { color:var(--text-200); }
.cfg-tab.active { color:var(--indigo); border-bottom-color:var(--indigo); }
.cfg-pane { display:none; }
.cfg-pane.active { display:block; animation:cfgIn .18s ease; }
@keyframes cfgIn { from{opacity:0;transform:translateY(4px)} to{opacity:1;transform:none} }
/* ── Section card ────────────────────────────────────────── */
.cfg-section {
    background: var(--bg-800);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    margin-bottom: 1.25rem;
    overflow: hidden;
}
.cfg-section-title {
    padding: .875rem 1.25rem;
    font-size: .8rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .07em;
    color: var(--text-400);
    border-bottom: 1px solid var(--border);
    background: var(--bg-700);
    display: flex; align-items: center; justify-content: space-between;
}
.cfg-field {
    display: flex; align-items: center; justify-content: space-between;
    padding: .875rem 1.25rem;
    border-bottom: 1px solid var(--border);
    gap: 1rem;
    flex-wrap: wrap;
}
.cfg-field:last-child { border-bottom: none; }
.cfg-field-label { font-size: .875rem; font-weight: 500; flex: 1; min-width: 160px; }
.cfg-field-desc  { font-size: .75rem; color: var(--text-400); margin-top: .1rem; }
.cfg-field-input { flex: 0 0 auto; min-width: 220px; }
/* ── Linha de categoria ──────────────────────────────────── */
.cat-row {
    display: flex; align-items: center; gap: .875rem;
    padding: .75rem 1.25rem;
    border-bottom: 1px solid var(--border);
    transition: var(--ease);
}
.cat-row:last-child { border-bottom: none; }
.cat-row:hover { background: var(--bg-700); }
.cat-row.inativa { opacity: .45; }
.cat-icon-preview {
    width: 32px; height: 32px; border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: .875rem; flex-shrink: 0;
}
/* ── Modal ───────────────────────────────────────────────── */
/* Modal base agora vive em assets/css/main.css. */
/* ── Export card ─────────────────────────────────────────── */
.export-card {
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    background: var(--bg-700);
    transition: var(--ease);
}
.export-card:hover { border-color: var(--indigo); }
/* ── Stats grid ──────────────────────────────────────────── */
.stats-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:.875rem; }
.stat-chip {
    background: var(--bg-700);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: .875rem 1rem;
    text-align: center;
}
.stat-chip-val  { font-size: 1.35rem; font-weight: 700; color: var(--indigo); }
.stat-chip-lbl  { font-size: .72rem; color: var(--text-400); margin-top: .2rem; text-transform: uppercase; letter-spacing: .05em; }
/* ── Preview de ícone ────────────────────────────────────── */
#iconePreview { font-size: 1.2rem; width:36px; height:36px; display:flex; align-items:center; justify-content:center; border-radius:var(--radius-sm); flex-shrink:0; }
</style>

<!-- ── Cabeçalho ──────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <div class="page-title">Configurações</div>
        <div class="page-sub">Preferências, categorias e ferramentas do sistema</div>
    </div>
</div>

<!-- ── Tabs ───────────────────────────────────────────────── -->
<div class="cfg-tabs">
    <button class="cfg-tab active" onclick="cfgTab('geral')">
        <i class="fa-solid fa-sliders fa-xs"></i> Geral
    </button>
    <button class="cfg-tab" onclick="cfgTab('categorias')">
        <i class="fa-solid fa-tags fa-xs"></i> Categorias
    </button>
    <button class="cfg-tab" onclick="cfgTab('responsaveis')">
        <i class="fa-solid fa-users fa-xs"></i> Responsáveis
    </button>
    <button class="cfg-tab" onclick="cfgTab('atalhos')">
        <i class="fa-solid fa-bolt fa-xs"></i> Atalhos
    </button>
    <button class="cfg-tab" onclick="cfgTab('exportar')">
        <i class="fa-solid fa-download fa-xs"></i> Exportar
    </button>
    <button class="cfg-tab" onclick="cfgTab('sistema')">
        <i class="fa-solid fa-circle-info fa-xs"></i> Sistema
    </button>
    <button class="cfg-tab" onclick="cfgTab('notificacoes')">
        <i class="fa-solid fa-bell fa-xs"></i> Notificações
    </button>
    <button class="cfg-tab" onclick="cfgTab('dados')">
        <i class="fa-solid fa-trash-can fa-xs"></i> Dados
    </button>
</div>

<!-- ═══════════════════════════════════════════════════════════
     ABA 1 — GERAL
═══════════════════════════════════════════════════════════ -->
<?php include __DIR__ . '/configuracoes/_tab_geral.php'; ?>

<!-- ═══════════════════════════════════════════════════════════
     ABA 2 — CATEGORIAS
═══════════════════════════════════════════════════════════ -->
<?php include __DIR__ . '/configuracoes/_tab_categorias.php'; ?>

<!-- ═══════════════════════════════════════════════════════════
     ABA 3 — RESPONSÁVEIS
═══════════════════════════════════════════════════════════ -->
<?php include __DIR__ . '/configuracoes/_tab_responsaveis.php'; ?>

<!-- ═══════════════════════════════════════════════════════════
     ABA — ATALHOS DE PREENCHIMENTO
═══════════════════════════════════════════════════════════ -->
<?php include __DIR__ . '/configuracoes/_tab_atalhos.php'; ?>

<!-- ═══════════════════════════════════════════════════════════
     ABA 4 — EXPORTAR
═══════════════════════════════════════════════════════════ -->
<?php include __DIR__ . '/configuracoes/_tab_exportar.php'; ?>

<!-- ═══════════════════════════════════════════════════════════
     ABA 4 — SISTEMA
═══════════════════════════════════════════════════════════ -->
<?php include __DIR__ . '/configuracoes/_tab_sistema.php'; ?>

<!-- ═══════════════════════════════════════════════════════════
     ABA — NOTIFICAÇÕES
═══════════════════════════════════════════════════════════ -->
<?php include __DIR__ . '/configuracoes/_tab_notificacoes.php'; ?>

<!-- ═══════════════════════════════════════════════════════════
     ABA — DADOS
═══════════════════════════════════════════════════════════ -->
<?php include __DIR__ . '/configuracoes/_tab_dados.php'; ?>

<script>
const _CATS  = <?= json_encode(array_values($categorias),   JSON_UNESCAPED_UNICODE) ?>;
const _RESPS = <?= json_encode(array_values($responsaveis), JSON_UNESCAPED_UNICODE) ?>;

// ── Tabs ──────────────────────────────────────────────────
const _cfgTabLoaded = {};
function cfgTab(id) {
    const tabIds = ['geral','categorias','responsaveis','atalhos','exportar','sistema','notificacoes','dados'];
    document.querySelectorAll('.cfg-tab').forEach((b, i) =>
        b.classList.toggle('active', tabIds[i] === id));
    document.querySelectorAll('.cfg-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('cfg-' + id).classList.add('active');
    if (id === 'sistema'       && !_cfgTabLoaded.sistema)      { _cfgTabLoaded.sistema      = true; carregarStats(); }
    if (id === 'dados'         && !_cfgTabLoaded.dados)        { _cfgTabLoaded.dados        = true; carregarContsDados(); }
    if (id === 'notificacoes'  && !_cfgTabLoaded.notificacoes) { _cfgTabLoaded.notificacoes = true; atualizarStatusNotif(); }
    if (id === 'atalhos'       && !_cfgTabLoaded.atalhos)      { _cfgTabLoaded.atalhos      = true; carregarAtalhos(); }
}

function atualizarStatusNotif() {
    const statusEl = document.getElementById('notifStatus');
    const badgeEl  = document.getElementById('notifStatusBadge');
    const btnEl    = document.getElementById('btnAtivarNotif');
    if (!('Notification' in window)) {
        if (statusEl) statusEl.textContent = 'Seu navegador não suporta notificações.';
        if (badgeEl)  { badgeEl.textContent = 'Indisponível'; badgeEl.className = 'badge'; }
        if (btnEl)    btnEl.disabled = true;
        return;
    }
    const perm = Notification.permission;
    const map  = {
        granted: ['Ativadas — você receberá alertas automaticamente', 'Ativado', 'emerald'],
        denied:  ['Bloqueadas — habilite nas configurações do navegador', 'Bloqueado', 'rose'],
        default: ['Não solicitado ainda — clique em "Ativar notificações"', 'Não ativado', 'amber'],
    };
    const [txt, badge, cor] = map[perm] || map.default;
    if (statusEl) statusEl.textContent = txt;
    if (badgeEl)  { badgeEl.textContent = badge; badgeEl.className = `badge ${cor}`; }
    if (btnEl)    btnEl.style.display = perm === 'granted' ? 'none' : '';
}

// ═══════════════════════════════════════════════════════════
// ABA — DADOS
// ═══════════════════════════════════════════════════════════
const _TIPOS_DADOS = [
    { tipo: 'despesas',      label: 'Despesas',      icone: 'arrow-down',      cor: 'var(--rose)'    },
    { tipo: 'receitas',      label: 'Receitas',      icone: 'arrow-up',        cor: 'var(--emerald)' },
    { tipo: 'emprestimos',   label: 'Empréstimos',   icone: 'landmark',        cor: 'var(--amber)'   },
    { tipo: 'investimentos', label: 'Investimentos', icone: 'chart-line',      cor: 'var(--indigo)'  },
    { tipo: 'metas',         label: 'Metas',         icone: 'bullseye',        cor: 'var(--cyan)'    },
];

async function carregarContsDados() {
    try {
        const res  = await fetch('backend/api/dados.php');
        const json = await res.json();
        if (!json.success) return;

        const lista = document.getElementById('dadosLista');
        lista.innerHTML = _TIPOS_DADOS.map(t => {
            const qty = json.counts[t.tipo] ?? 0;
            return `
            <div style="display:flex;justify-content:space-between;align-items:center;
                        padding:.75rem 1rem;background:var(--bg-700);border-radius:var(--radius)">
                <div style="display:flex;align-items:center;gap:.75rem">
                    <div style="width:32px;height:32px;border-radius:50%;background:${t.cor}22;
                                color:${t.cor};display:flex;align-items:center;justify-content:center">
                        <i class="fa-solid fa-${t.icone} fa-sm"></i>
                    </div>
                    <div>
                        <div class="fw-600 text-sm">${t.label}</div>
                        <div class="text-xs text-muted" id="cnt-${t.tipo}">${qty} registro${qty !== 1 ? 's' : ''}</div>
                    </div>
                </div>
                <button class="btn btn-sm" style="color:var(--rose);border:1px solid rgba(244,63,94,.4)"
                        onclick="excluirTipo('${t.tipo}','${t.label}')" ${qty === 0 ? 'disabled' : ''}>
                    <i class="fa-solid fa-trash fa-xs"></i> Excluir tudo
                </button>
            </div>`;
        }).join('');
    } catch (e) {
        document.getElementById('dadosLista').innerHTML =
            `<div class="text-muted text-sm">Erro ao carregar contagens.</div>`;
    }
}

function excluirTipo(tipo, label) {
    confirmar(
        `Excluir todas as ${label}`,
        `Todos os registros de "${label}" serão excluídos permanentemente. Esta ação não pode ser desfeita.`,
        async () => {
            try {
                const res  = await fetch(`backend/api/dados.php?tipo=${tipo}`, { method: 'DELETE' });
                const json = await res.json();
                if (!json.success) throw new Error(json.erro);
                toast(json.msg, 'success');
                _cfgTabLoaded.dados = false;
                carregarContsDados();
                _cfgTabLoaded.dados = true;
            } catch (err) {
                toast('Erro: ' + err.message, 'error');
            }
        },
        { btnLabel: 'Sim, excluir tudo', btnCor: 'var(--rose)' }
    );
}

function limparTudo() {
    confirmar(
        '⚠️ Excluir TODOS os dados',
        'Isso apagará permanentemente todas as despesas, receitas, empréstimos, investimentos, metas, orçamentos e alertas. Os saldos das contas voltarão ao valor inicial. ESTA AÇÃO NÃO PODE SER DESFEITA.',
        async () => {
            try {
                const res  = await fetch('backend/api/dados.php?tipo=tudo', { method: 'DELETE' });
                const json = await res.json();
                if (!json.success) throw new Error(json.erro);
                toast(json.msg, 'success');
                _cfgTabLoaded.dados = false;
                carregarContsDados();
                _cfgTabLoaded.dados = true;
            } catch (err) {
                toast('Erro: ' + err.message, 'error');
            }
        },
        { btnLabel: 'Sim, excluir TUDO', btnCor: 'var(--rose)', icone: 'bomb' }
    );
}

// ═══════════════════════════════════════════════════════════
// ABA 1 — GERAL
// ═══════════════════════════════════════════════════════════
async function salvarConfigs() {
    const configs = {
        usuario_nome:            document.getElementById('cfgNome').value.trim(),
        usuario_email:           document.getElementById('cfgEmail').value.trim(),
        moeda:                   document.getElementById('cfgMoeda').value,
        dia_fechamento_padrao:   document.getElementById('cfgDiaFech').value,
        meta_poupanca:           document.getElementById('cfgMetaPoupanca').value,
        alerta_cartao_pct:       document.getElementById('cfgAlertaCartao').value,
        alerta_dias_antecedencia:document.getElementById('cfgAlertaDias').value,
    };
    try {
        const res  = await fetch('backend/api/configuracoes.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ acao: 'salvar_configs', configs })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

// ═══════════════════════════════════════════════════════════
// ABA 2 — CATEGORIAS
// ═══════════════════════════════════════════════════════════
function filtrarCats(filtro) {
    ['todos','ativo','inativo'].forEach(f => {
        document.getElementById('filtCat-' + f)?.classList.toggle('btn-primary', f === filtro);
        document.getElementById('filtCat-' + f)?.classList.toggle('btn-ghost',   f !== filtro);
    });
    document.querySelectorAll('.cat-item').forEach(row => {
        const ativo = row.dataset.ativo === '1';
        row.style.display =
            filtro === 'todos'   ? '' :
            filtro === 'ativo'   ? (ativo  ? '' : 'none') :
                                   (!ativo ? '' : 'none');
    });
}

function abrirModalCat(id = null, catPaiId = null) {
    document.getElementById('formCat').reset();
    document.getElementById('catId').value    = id || '';
    document.getElementById('catCor').value   = '#6366f1';
    document.getElementById('catIcone').value = 'tag';
    document.getElementById('catPai').value   = catPaiId || '';
    document.getElementById('catModalTitulo').textContent = catPaiId ? 'Nova Subcategoria' : 'Nova Categoria';
    setTipoCat('despesa');
    onChangeCatPai();
    atualizarPreview();
    document.getElementById('modalCat').classList.add('open');
    setTimeout(() => document.getElementById('catNome').focus(), 80);
}

function abrirEditarCat(id) {
    const c = _CATS.find(x => +x.id === +id);
    if (!c) return;
    document.getElementById('catId').value    = c.id;
    document.getElementById('catNome').value  = c.nome          || '';
    document.getElementById('catCor').value   = c.cor           || '#6366f1';
    document.getElementById('catIcone').value = c.icone         || 'tag';
    document.getElementById('catPai').value   = c.categoria_pai || '';
    document.getElementById('catModalTitulo').textContent = c.categoria_pai ? 'Editar Subcategoria' : 'Editar Categoria';
    setTipoCat(c.tipo || 'despesa');
    onChangeCatPai();
    atualizarPreview();
    document.getElementById('modalCat').classList.add('open');
}

function onChangeCatPai() {
    const sel  = document.getElementById('catPai');
    const opt  = sel.options[sel.selectedIndex];
    const wrap = document.getElementById('catTipoWrap');
    if (sel.value) {
        // Subcategoria herda o tipo do pai automaticamente
        const tipo = opt.dataset.tipo || 'despesa';
        setTipoCat(tipo);
        if (wrap) wrap.style.display = 'none';
        // Sugere a cor do pai
        const cor = opt.dataset.cor;
        if (cor && !document.getElementById('catId').value) {
            document.getElementById('catCor').value = cor;
            atualizarPreview();
        }
    } else {
        if (wrap) wrap.style.display = '';
    }
}

function setTipoCat(v) {
    document.getElementById('catTipo').value = v;
    ['despesa','receita','ambos'].forEach(t => {
        const btn = document.getElementById('tipoCatBtn-' + t);
        if (!btn) return;
        btn.className = 'btn btn-sm ' + (t === v ? 'btn-primary' : 'btn-ghost');
    });
}

function atualizarPreview() {
    const cor   = document.getElementById('catCor').value;
    const icone = document.getElementById('catIcone').value.trim() || 'tag';
    const prev  = document.getElementById('iconePreview');
    prev.style.background = cor + '22';
    prev.style.color = cor;
    prev.innerHTML = `<i class="fa-solid fa-${esc(icone)}"></i>`;
}

async function salvarCat(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSalvarCat');
    btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    const id = document.getElementById('catId').value;
    const payload = {
        acao:          'salvar_categoria',
        id:            id ? parseInt(id) : 0,
        nome:          document.getElementById('catNome').value.trim(),
        tipo:          document.getElementById('catTipo').value,
        icone:         document.getElementById('catIcone').value.trim() || 'tag',
        cor:           document.getElementById('catCor').value,
        categoria_pai: parseInt(document.getElementById('catPai').value) || 0,
    };

    try {
        const res  = await fetch('backend/api/configuracoes.php', {
            method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        fecharModal();
        location.reload();
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
    finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Salvar';
    }
}

async function toggleCat(id) {
    try {
        const res  = await fetch('backend/api/configuracoes.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ acao:'toggle_categoria', id })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        location.reload();
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

function inserirCatsPadrao() {
    confirmar('Inserir categorias padrão', 'Adicionar categorias sugeridas? As que já existem serão ignoradas.', async () => {
        try {
            const res  = await fetch('backend/api/configuracoes.php', {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ acao: 'inserir_padrao' })
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.erro);
            toast(json.msg, json.inseridas > 0 ? 'success' : 'info');
            if (json.inseridas > 0) location.reload();
        } catch (err) { toast('Erro: ' + err.message, 'error'); }
    }, { btnLabel: 'Inserir', icone: 'wand-magic-sparkles', btnCor: 'var(--indigo)' });
}

function excluirCat(id, nome) {
    confirmar('Remover categoria', `Remover a categoria "${nome}"? Se estiver em uso, será apenas desativada.`, async () => {
        try {
            const res  = await fetch(`backend/api/configuracoes.php?tipo=categoria&id=${id}`, {method:'DELETE'});
            const json = await res.json();
            if (!json.success) throw new Error(json.erro);
            toast(json.msg, 'success');
            location.reload();
        } catch (err) { toast('Erro: ' + err.message, 'error'); }
    });
}

// ═══════════════════════════════════════════════════════════
// ABA 3 — EXPORTAR
// ═══════════════════════════════════════════════════════════
function jsonToCSV(dados, campos) {
    const header = campos.join(';');
    const linhas = dados.map(row =>
        campos.map(f => {
            const v = row[f] ?? '';
            return typeof v === 'string' && v.includes(';') ? `"${v}"` : v;
        }).join(';')
    );
    return [header, ...linhas].join('\n');
}

function download(conteudo, nomeArquivo, tipo) {
    const blob = new Blob(['﻿' + conteudo], { type: tipo });
    const a    = document.createElement('a');
    a.href     = URL.createObjectURL(blob);
    a.download = nomeArquivo;
    a.click();
    URL.revokeObjectURL(a.href);
}

async function exportarCSV() {
    const deMes  = document.getElementById('exDeMes').value;
    const deAno  = document.getElementById('exDeAno').value;
    const ateMes = document.getElementById('exAteMes').value;
    const ateAno = document.getElementById('exAteAno').value;
    const txTipo = document.getElementById('exTxTipo').value;

    try {
        const url = `backend/api/configuracoes.php?acao=exportar&tipo=transacoes&de_mes=${deMes}&de_ano=${deAno}&ate_mes=${ateMes}&ate_ano=${ateAno}&tx_tipo=${txTipo}`;
        const res  = await fetch(url);
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);

        const campos = ['id','tipo','descricao','valor','data','status','parcela_atual','parcela_total','categoria','conta','cartao','observacao'];
        const csv    = jsonToCSV(json.dados, campos);
        download(csv, `financeos-transacoes-${deAno}${deMes.padStart(2,'0')}-${ateAno}${ateMes.padStart(2,'0')}.csv`, 'text/csv;charset=utf-8');

        mostrarExportStatus(`${json.total} transações exportadas com sucesso.`);
    } catch (err) { toast('Erro na exportação: ' + err.message, 'error'); }
}

async function exportarCategorias() {
    try {
        const res  = await fetch('backend/api/configuracoes.php?acao=exportar&tipo=categorias');
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);

        const campos = ['id','nome','tipo','icone','cor','ativo'];
        const csv    = jsonToCSV(json.dados, campos);
        download(csv, 'financeos-categorias.csv', 'text/csv;charset=utf-8');
        mostrarExportStatus(`${json.dados.length} categorias exportadas.`);
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

async function exportarJSON() {
    const deMes  = document.getElementById('exDeMes').value;
    const deAno  = document.getElementById('exDeAno').value;
    const ateMes = document.getElementById('exAteMes').value;
    const ateAno = document.getElementById('exAteAno').value;
    const txTipo = document.getElementById('exTxTipo').value;

    try {
        const url = `backend/api/configuracoes.php?acao=exportar&tipo=transacoes&de_mes=${deMes}&de_ano=${deAno}&ate_mes=${ateMes}&ate_ano=${ateAno}&tx_tipo=${txTipo}`;
        const res  = await fetch(url);
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);

        const conteudo = JSON.stringify({ exportado_em: new Date().toISOString(), ...json }, null, 2);
        download(conteudo, `financeos-backup-${new Date().toISOString().split('T')[0]}.json`, 'application/json');
        mostrarExportStatus(`${json.total} registros exportados como JSON.`);
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

function mostrarExportStatus(msg) {
    const el = document.getElementById('exportStatus');
    document.getElementById('exportMsg').textContent = msg;
    el.style.display = '';
    setTimeout(() => el.style.display = 'none', 5000);
}

// ═══════════════════════════════════════════════════════════
// ABA 4 — SISTEMA
// ═══════════════════════════════════════════════════════════
async function carregarStats() {
    try {
        const res  = await fetch('backend/api/configuracoes.php?acao=stats');
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);

        const labels = {
            transacoes:'Transações', categorias:'Categorias', contas:'Contas',
            cartoes:'Cartões', emprestimos:'Empréstimos', investimentos:'Investimentos',
            metas:'Metas', recorrencias:'Contas Fixas', alertas:'Alertas',
        };

        document.getElementById('statsGrid').innerHTML =
            Object.entries(json.counts).map(([k, v]) =>
                `<div class="stat-chip">
                    <div class="stat-chip-val">${v.toLocaleString('pt-BR')}</div>
                    <div class="stat-chip-lbl">${labels[k] || k}</div>
                </div>`
            ).join('') +
            `<div class="stat-chip" style="border-color:var(--emerald)">
                <div class="stat-chip-val" style="color:var(--emerald)">${brl(json.total_moviment)}</div>
                <div class="stat-chip-lbl">Total Movimentado</div>
            </div>`;

        if (json.desde) {
            const d = json.desde.split('-');
            document.getElementById('statsDesde').textContent = `${d[2]}/${d[1]}/${d[0]}`;
        }
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

// ═══════════════════════════════════════════════════════════
// ABA 3 — RESPONSÁVEIS
// ═══════════════════════════════════════════════════════════
function abrirModalResp() {
    document.getElementById('formResp').reset();
    document.getElementById('respId').value    = '';
    document.getElementById('respCor').value   = '#6366f1';
    document.getElementById('respIcone').value = 'user';
    document.getElementById('respModalTitulo').textContent = 'Novo Responsável';
    atualizarPreviewResp();
    document.getElementById('modalResp').classList.add('open');
    setTimeout(() => document.getElementById('respNome').focus(), 80);
}

function abrirEditarResp(id) {
    const r = _RESPS.find(x => +x.id === +id);
    if (!r) return;
    document.getElementById('respId').value    = r.id;
    document.getElementById('respNome').value  = r.nome  || '';
    document.getElementById('respCor').value   = r.cor   || '#6366f1';
    document.getElementById('respIcone').value = r.icone || 'user';
    document.getElementById('respModalTitulo').textContent = 'Editar Responsável';
    atualizarPreviewResp();
    document.getElementById('modalResp').classList.add('open');
}

function atualizarPreviewResp() {
    const cor   = document.getElementById('respCor').value;
    const icone = document.getElementById('respIcone').value.trim() || 'user';
    const prev  = document.getElementById('respIconePreview');
    prev.style.background = cor + '22';
    prev.style.color = cor;
    prev.innerHTML = `<i class="fa-solid fa-${esc(icone)}"></i>`;
}

async function salvarResp(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSalvarResp');
    btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    const id = document.getElementById('respId').value;
    const payload = {
        acao:  'salvar',
        id:    id ? parseInt(id) : 0,
        nome:  document.getElementById('respNome').value.trim(),
        icone: document.getElementById('respIcone').value.trim() || 'user',
        cor:   document.getElementById('respCor').value,
    };
    try {
        const res  = await fetch('backend/api/responsaveis.php', {
            method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        fecharModalResp();
        location.reload();
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
    finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Salvar';
    }
}

async function toggleResp(id) {
    try {
        const res  = await fetch('backend/api/responsaveis.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ acao:'toggle', id })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success'); location.reload();
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

function excluirResp(id, nome) {
    confirmar('Remover responsável', `Remover "${nome}"? As transações vinculadas perderão o responsável.`, async () => {
        try {
            const res  = await fetch(`backend/api/responsaveis.php?id=${id}`, {method:'DELETE'});
            const json = await res.json();
            if (!json.success) throw new Error(json.erro);
            toast(json.msg, 'success'); location.reload();
        } catch (err) { toast('Erro: ' + err.message, 'error'); }
    });
}

function fecharModalResp() { document.getElementById('modalResp').classList.remove('open'); }

// ═══════════════════════════════════════════════════════════
// ABA — ATALHOS DE PREENCHIMENTO
// ═══════════════════════════════════════════════════════════
let _atalhos = [];

async function carregarAtalhos() {
    try {
        const res  = await fetch('backend/api/atalhos.php');
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        _atalhos = json.dados;
        renderAtalhos(_atalhos);
    } catch (err) {
        document.getElementById('atalhosLista').innerHTML =
            `<div class="text-muted text-sm" style="padding:1rem">Erro ao carregar atalhos.</div>`;
    }
}

function renderAtalhos(lista) {
    const wrap = document.getElementById('atalhosLista');
    if (!lista.length) {
        wrap.innerHTML = `
        <div class="cfg-section">
            <div class="card-body empty-state lg">
                <i class="fa-solid fa-bolt fa-3x" style="margin-bottom:1rem;color:var(--indigo)"></i>
                <div class="fw-700" style="margin-bottom:.5rem">Nenhum atalho cadastrado</div>
                <div class="text-sm">Cadastre uma descrição comum pra preencher os outros campos sozinha.</div>
            </div>
        </div>`;
        return;
    }
    wrap.innerHTML = `<div class="cfg-section">` + lista.map(a => {
        const partes = [];
        if (a.valor !== null)  partes.push(brl(parseFloat(a.valor)));
        if (a.categoria_nome)  partes.push(a.categoria_nome);
        if (a.conta_nome)      partes.push(a.conta_nome);
        if (a.cartao_nome)     partes.push(a.cartao_nome);
        if (a.responsavel_nome || a.terceiro_nome) partes.push(a.responsavel_nome || a.terceiro_nome);
        return `
        <div class="cat-row ${!+a.ativo ? 'inativa' : ''}">
            <div class="cat-icon-preview" style="background:var(--indigo)22;color:var(--indigo)">
                <i class="fa-solid ${a.tipo === 'receita' ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'}"></i>
            </div>
            <div style="flex:1;min-width:0">
                <div class="fw-600 text-sm">${esc(a.descricao_chave)}</div>
                <div class="text-xs text-muted">${esc(partes.join(' · ') || 'Sem detalhes')}</div>
            </div>
            <span class="badge ${a.tipo === 'receita' ? 'emerald' : 'rose'}">${a.tipo === 'receita' ? 'Receita' : 'Despesa'}</span>
            <div class="d-flex gap-1">
                <button class="btn-icon" onclick="abrirEditarAtalho(${+a.id})" title="Editar"
                        style="width:28px;height:28px;font-size:.72rem">
                    <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button class="btn-icon" onclick="toggleAtalho(${+a.id})"
                        title="${+a.ativo ? 'Desativar' : 'Ativar'}"
                        style="width:28px;height:28px;font-size:.72rem;color:${+a.ativo ? 'var(--amber)' : 'var(--emerald)'}">
                    <i class="fa-solid fa-${+a.ativo ? 'eye-slash' : 'eye'}"></i>
                </button>
                <button class="btn-icon" onclick="excluirAtalho(${+a.id}, '${esc(a.descricao_chave).replace(/'/g, "\\'")}')"
                        title="Excluir" style="width:28px;height:28px;font-size:.72rem;color:var(--rose)">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>`;
    }).join('') + `</div>`;
}

function setTipoAtalho(v) {
    document.getElementById('atalhoTipo').value = v;
    ['despesa','receita'].forEach(t => {
        const btn = document.getElementById('tipoAtalhoBtn-' + t);
        if (!btn) return;
        btn.className = 'btn btn-sm ' + (t === v ? 'btn-primary' : 'btn-ghost');
    });
    popularCategoriasAtalho(v);
}

function popularCategoriasAtalho(tipo, selecionadoId = '') {
    const sel  = document.getElementById('atalhoCategoria');
    const cats = _CATS.filter(c => c.tipo === tipo || c.tipo === 'ambos');
    sel.innerHTML = '<option value="">— Nenhuma —</option>' +
        cats.map(c => `<option value="${c.id}" ${String(c.id) === String(selecionadoId) ? 'selected' : ''}>${c.categoria_pai ? '↳ ' : ''}${esc(c.nome)}</option>`).join('');
}

function abrirModalAtalho() {
    document.getElementById('formAtalho').reset();
    document.getElementById('atalhoId').value = '';
    document.getElementById('atalhoModalTitulo').textContent = 'Novo Atalho';
    setTipoAtalho('despesa');
    document.getElementById('modalAtalho').classList.add('open');
    setTimeout(() => document.getElementById('atalhoDescricao').focus(), 80);
}

function abrirEditarAtalho(id) {
    const a = _atalhos.find(x => +x.id === +id);
    if (!a) return;
    document.getElementById('atalhoId').value         = a.id;
    document.getElementById('atalhoDescricao').value   = a.descricao_chave || '';
    document.getElementById('atalhoValor').value       = a.valor !== null ? brlMask(a.valor) : '';
    document.getElementById('atalhoConta').value       = a.conta_id  || '';
    document.getElementById('atalhoCartao').value      = a.cartao_id || '';
    document.getElementById('atalhoResponsavel').value = a.terceiro_id ? ('tcr_' + a.terceiro_id) : (a.responsavel_id || '');
    document.getElementById('atalhoObservacao').value  = a.observacao || '';
    document.getElementById('atalhoModalTitulo').textContent = 'Editar Atalho';
    setTipoAtalho(a.tipo || 'despesa');
    popularCategoriasAtalho(a.tipo || 'despesa', a.categoria_id || '');
    document.getElementById('modalAtalho').classList.add('open');
}

function fecharModalAtalho() { document.getElementById('modalAtalho').classList.remove('open'); }

async function salvarAtalho(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSalvarAtalho');
    btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    const id        = document.getElementById('atalhoId').value;
    const _respVal  = document.getElementById('atalhoResponsavel').value;
    const _isTcr    = _respVal.startsWith('tcr_');
    const payload = {
        acao:            'salvar',
        id:              id ? parseInt(id) : 0,
        tipo:            document.getElementById('atalhoTipo').value,
        descricao_chave: document.getElementById('atalhoDescricao').value.trim(),
        valor:           parseCurrency(document.getElementById('atalhoValor').value) || null,
        categoria_id:    document.getElementById('atalhoCategoria').value || null,
        conta_id:        document.getElementById('atalhoConta').value    || null,
        cartao_id:       document.getElementById('atalhoCartao').value   || null,
        responsavel_id:  !_isTcr && _respVal ? parseInt(_respVal) : null,
        terceiro_id:     _isTcr ? parseInt(_respVal.replace('tcr_', '')) : null,
        observacao:      document.getElementById('atalhoObservacao').value.trim(),
    };

    try {
        const res  = await fetch('backend/api/atalhos.php', {
            method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        fecharModalAtalho();
        carregarAtalhos();
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
    finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Salvar';
    }
}

async function toggleAtalho(id) {
    try {
        const res  = await fetch('backend/api/atalhos.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ acao: 'toggle', id })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        carregarAtalhos();
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

function excluirAtalho(id, chave) {
    confirmar('Remover atalho', `Remover o atalho "${chave}"?`, async () => {
        try {
            const res  = await fetch(`backend/api/atalhos.php?id=${id}`, {method:'DELETE'});
            const json = await res.json();
            if (!json.success) throw new Error(json.erro);
            toast(json.msg, 'success');
            carregarAtalhos();
        } catch (err) { toast('Erro: ' + err.message, 'error'); }
    });
}

// ── Utilitários ───────────────────────────────────────────
function fecharModal() { document.getElementById('modalCat').classList.remove('open'); }
// esc() vem de assets/js/app.js (carregado globalmente por index.php)
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { fecharModal(); fecharModalResp(); fecharModalAtalho(); }
});
</script>
