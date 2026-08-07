<?php
// Categorias usa backend/api/configuracoes.php (acao=categorias / salvar_categoria / toggle_categoria / delete tipo=categoria)
$p = TABLE_PREFIX;
$iconesPadrao = [
    // Geral
    'tag','star','heart','bookmark','question','circle-info','ellipsis',
    // Alimentação & Bebida
    'utensils','pizza-slice','burger','mug-hot','beer-mug-empty','wine-glass',
    'bowl-food','drumstick-bite','cake-candles','fish','carrot','apple-whole',
    'ice-cream','cookie','bread-slice','egg',
    // Compras & Estilo
    'cart-shopping','bag-shopping','shop','shirt','gem','glasses','umbrella',
    'scissors','vest','socks',
    // Transporte
    'car','motorcycle','bus','train','bicycle','plane','ship','truck',
    'gas-pump','helicopter','tractor',
    // Casa & Moradia
    'house','couch','bed','lightbulb','key','lock','wrench','toolbox',
    'paint-roller','hammer','door-open','plug','recycle','broom','toilet','shower',
    // Saúde & Bem-estar
    'stethoscope','prescription-bottle','heart-pulse','syringe','eye','brain',
    'dumbbell','spa','shield-halved','shield-heart','wheelchair','tooth',
    'lungs','bone','bandage',
    // Entretenimento & Lazer
    'tv','gamepad','music','headphones','guitar','ticket','film','radio',
    'masks-theater','dice','microphone','volleyball','chess','billiard-ball',
    'person-swimming','person-skiing','trophy',
    // Educação & Conhecimento
    'book','book-open','graduation-cap','school','pencil','pen','chalkboard',
    'laptop','magnifying-glass','microscope','ruler',
    // Finanças & Economia
    'wallet','coins','money-bill-wave','piggy-bank','chart-line','chart-bar',
    'credit-card','hand-holding-dollar','dollar-sign','sack-dollar','vault',
    'receipt','file-invoice-dollar','hand-holding-heart','percent','landmark',
    'money-check-dollar','scale-balanced','hand-fist',
    // Trabalho & Negócios
    'briefcase','mobile-screen','desktop','printer','clipboard','file',
    'folder','users','user-tie','hard-hat','industry','factory','handshake',
    'chart-pie','building','warehouse',
    // Comunicação & Tech
    'wifi','phone','envelope','message','comments','globe','satellite-dish',
    'paper-plane','tower-broadcast',
    // Pets & Natureza
    'paw','leaf','tree','seedling','sun','cloud','snowflake','mountain',
    'fire','droplet','wind','bolt',
    // Viagem
    'map-location-dot','compass','suitcase','plane','tent','earth-americas',
    // Família & Pessoas
    'person','people-group','baby','child','ring','face-smile','hand-holding-heart',
    // Eventos & Celebrações
    'balloon','champagne-glasses','gift','party-horn','calendar',
    // Segurança & Jurídico
    'shield-halved','gavel','file-shield','user-shield',
    // Outros
    'trash','clock','hourglass','rotate','arrow-trend-up','arrow-trend-down',
];
?>

<style>
/* Modal base e barra de filtros agora vivem em assets/css/main.css. */
.icone-grid {
    display:grid; grid-template-columns:repeat(auto-fill,minmax(40px,1fr)); gap:.4rem;
    max-height:220px; overflow-y:auto; padding:.25rem;
}
.icone-opt[style*="display:none"] { display:none !important; }
.icone-opt {
    width:40px; height:40px; border-radius:var(--radius-sm); border:2px solid transparent;
    display:flex; align-items:center; justify-content:center; cursor:pointer;
    background:var(--bg-700); color:var(--text-400); font-size:.85rem;
    transition:all var(--ease);
}
.icone-opt:hover  { border-color:var(--indigo); color:var(--text-100); }
.icone-opt.ativo  { border-color:var(--indigo); background:var(--indigo-soft); color:var(--indigo); }
tr.sub-row td { background:rgba(99,102,241,.025); }
tr.sub-row:hover td { background:rgba(99,102,241,.055); }
.sub-indent {
    display:flex; align-items:center; gap:.5rem; padding-left:1.75rem; position:relative;
}
.sub-indent::before {
    content:'└'; position:absolute; left:.5rem; top:50%; transform:translateY(-50%);
    font-size:.85rem; color:var(--text-600); line-height:1;
}
</style>

<!-- ── Cabeçalho ────────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <div class="page-title">Categorias</div>
        <div class="page-sub" id="pageSub">Carregando...</div>
    </div>
    <div class="d-flex gap-1">
        <button class="btn btn-ghost btn-sm" onclick="inserirPadrao()">
            <i class="fa-solid fa-wand-magic-sparkles"></i> Inserir Padrão
        </button>
        <button class="btn btn-primary btn-sm" onclick="abrirModal()">
            <i class="fa-solid fa-plus"></i> Nova Categoria
        </button>
    </div>
</div>

<!-- ── KPIs ─────────────────────────────────────────────────── -->
<div class="kpi-grid" style="margin-bottom:1.25rem">
    <div class="kpi-card indigo">
        <div class="kpi-header">
            <div class="kpi-label">Total Ativas</div>
            <div class="kpi-icon indigo"><i class="fa-solid fa-tags"></i></div>
        </div>
        <div class="kpi-value" id="kpiTotal">—</div>
        <div class="kpi-trend neutral"><i class="fa-solid fa-circle-info fa-xs"></i> Todas as categorias</div>
    </div>
    <div class="kpi-card rose">
        <div class="kpi-header">
            <div class="kpi-label">Despesas</div>
            <div class="kpi-icon rose"><i class="fa-solid fa-arrow-trend-down"></i></div>
        </div>
        <div class="kpi-value" id="kpiDespesa">—</div>
        <div class="kpi-trend neutral"><i class="fa-solid fa-circle-info fa-xs"></i> Categorias de despesa</div>
    </div>
    <div class="kpi-card emerald">
        <div class="kpi-header">
            <div class="kpi-label">Receitas</div>
            <div class="kpi-icon emerald"><i class="fa-solid fa-arrow-trend-up"></i></div>
        </div>
        <div class="kpi-value" id="kpiReceita">—</div>
        <div class="kpi-trend neutral"><i class="fa-solid fa-circle-info fa-xs"></i> Categorias de receita</div>
    </div>
    <div class="kpi-card amber">
        <div class="kpi-header">
            <div class="kpi-label">Ambos</div>
            <div class="kpi-icon amber"><i class="fa-solid fa-arrows-left-right"></i></div>
        </div>
        <div class="kpi-value" id="kpiAmbos">—</div>
        <div class="kpi-trend neutral"><i class="fa-solid fa-circle-info fa-xs"></i> Receita e despesa</div>
    </div>
</div>

<!-- ── Filtros ──────────────────────────────────────────────── -->
<div class="filters-bar">
    <div class="filter-group">
        <span class="filter-label">Tipo</span>
        <select id="filTipo" class="form-control" style="min-width:140px" onchange="carregarDados()">
            <option value="">Todos</option>
            <option value="despesa">Despesa</option>
            <option value="receita">Receita</option>
            <option value="ambos">Ambos</option>
        </select>
    </div>
    <div class="filter-group">
        <span class="filter-label">Status</span>
        <select id="filStatus" class="form-control" style="min-width:130px" onchange="carregarDados()">
            <option value="todos">Todos</option>
            <option value="ativo" selected>Ativos</option>
            <option value="inativo">Inativos</option>
        </select>
    </div>
    <div class="filter-group" style="flex:1;min-width:180px">
        <span class="filter-label">Buscar</span>
        <input type="search" id="filBusca" class="form-control"
               placeholder="Nome da categoria..." oninput="debounce()">
    </div>
</div>

<!-- ── Tabela ───────────────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">Categorias</div>
            <div class="card-subtitle" id="tabelaInfo">—</div>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Categoria</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th style="width:90px"></th>
                </tr>
            </thead>
            <tbody id="tabelaBody">
                <tr>
                    <td colspan="4" style="text-align:center;padding:2.5rem;color:var(--text-600)">
                        <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Modal ────────────────────────────────────────────────── -->
<div id="modalOverlay" class="modal-overlay" onclick="if(event.target===this)fecharModal()">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title" id="modalTitulo">Nova Categoria</div>
            <button type="button" class="btn-icon" onclick="fecharModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="formCat" onsubmit="salvar(event)">
            <div class="modal-body">
                <input type="hidden" id="editId" value="">

                <div class="form-group" style="margin-bottom:1rem">
                    <label class="form-label">Nome <span style="color:var(--rose)">*</span></label>
                    <input type="text" id="fNome" class="form-control"
                           placeholder="Ex: Supermercado, Salário..." required maxlength="100">
                </div>

                <div class="form-group" id="catPaiGroup" style="margin-bottom:1rem">
                    <label class="form-label">
                        <i class="fa-solid fa-sitemap fa-xs" style="color:var(--indigo);margin-right:.2rem"></i>
                        Categoria Pai
                        <span class="text-xs text-muted" style="font-weight:400;margin-left:.25rem">(opcional)</span>
                    </label>
                    <select id="fCatPai" class="form-control">
                        <option value="">— Categoria raiz (sem pai) —</option>
                    </select>
                    <div id="catPaiNote" class="text-xs text-muted" style="margin-top:.3rem;display:none">
                        <i class="fa-solid fa-circle-info fa-xs"></i>
                        Esta categoria já possui subcategorias e não pode ser vinculada a outra.
                    </div>
                </div>

                <div class="form-grid form-grid-2" style="margin-bottom:1rem">
                    <div class="form-group">
                        <label class="form-label">Tipo</label>
                        <select id="fTipoF" class="form-control">
                            <option value="despesa">Despesa</option>
                            <option value="receita">Receita</option>
                            <option value="ambos">Ambos</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cor</label>
                        <div class="d-flex align-center gap-1">
                            <input type="color" id="fCor" value="#6366f1"
                                   style="width:44px;height:38px;border-radius:var(--radius-sm);border:1px solid var(--border);padding:2px;background:var(--bg-700);cursor:pointer">
                            <span id="fCorText" class="text-sm text-muted">#6366f1</span>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:1rem">
                    <label class="form-label">
                        <i class="fa-solid fa-file-invoice-dollar fa-xs" style="color:var(--amber)"></i>
                        Classificação Imposto de Renda
                    </label>
                    <select id="fTipoIR" class="form-control">
                        <option value="">— Não classificar —</option>
                        <optgroup label="Rendimentos">
                            <option value="tributavel">Rendimento Tributável (salário, aluguel, PJ...)</option>
                            <option value="isento">Rendimento Isento (dividendos, FGTS, bolsas...)</option>
                        </optgroup>
                        <optgroup label="Deduções">
                            <option value="deducao_saude">Dedução — Saúde (sem limite legal)</option>
                            <option value="deducao_educacao">Dedução — Educação (limite ~R$ 3.561/ano)</option>
                            <option value="deducao_previd">Dedução — Previdência (INSS, PGBL)</option>
                            <option value="deducao_outro">Dedução — Outras</option>
                        </optgroup>
                    </select>
                    <div class="text-xs text-muted" style="margin-top:.3rem">
                        <i class="fa-solid fa-circle-info fa-xs"></i>
                        Define como esta categoria aparece no Relatório de IR em Relatórios.
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Ícone</label>
                    <input type="hidden" id="fIcone" value="tag">
                    <input type="search" id="iconeSearch" class="form-control"
                           placeholder="Buscar ícone... (ex: casa, carro, saúde)"
                           oninput="filtrarIcones(this.value)"
                           style="margin-bottom:.4rem;font-size:.8rem">
                    <div class="icone-grid" id="iconeGrid">
                        <?php foreach ($iconesPadrao as $ic): ?>
                        <div class="icone-opt <?= $ic === 'tag' ? 'ativo' : '' ?>"
                             data-ic="<?= $ic ?>" onclick="selecionarIcone(this)"
                             title="<?= $ic ?>">
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
let _cats   = [];
let _timer  = null;

function debounce() { clearTimeout(_timer); _timer = setTimeout(filtrarLocal, 280); }

// ── Carregar ──────────────────────────────────────────────────
async function carregarDados() {
    const status = document.getElementById('filStatus').value;
    const params = new URLSearchParams({ acao: 'categorias', status });

    document.getElementById('tabelaBody').innerHTML =
        `<tr><td colspan="4" style="text-align:center;padding:2rem;color:var(--text-600)">
            <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
        </td></tr>`;

    try {
        const res  = await fetch('backend/api/configuracoes.php?' + params);
        const json = await res.json();
        if (!json.success) throw new Error(json.erro || 'Erro');
        _cats = json.categorias;
        filtrarLocal();
    } catch (err) {
        document.getElementById('tabelaBody').innerHTML =
            `<tr><td colspan="4" style="text-align:center;padding:2rem;color:var(--rose)">
                <i class="fa-solid fa-triangle-exclamation"></i> ${esc(err.message)}
            </td></tr>`;
    }
}

function filtrarLocal() {
    const tipo  = document.getElementById('filTipo').value;
    const busca = document.getElementById('filBusca').value.toLowerCase().trim();

    // Conjunto inicial de IDs que passam no filtro direto
    const matchIds = new Set(_cats.filter(c => {
        if (tipo  && c.tipo !== tipo) return false;
        if (busca && !c.nome.toLowerCase().includes(busca)) return false;
        return true;
    }).map(c => +c.id));

    // Expande contexto: pai de sub que deu match + subs de pai que deu match
    const ids = new Set(matchIds);
    _cats.forEach(c => {
        if (c.categoria_pai && matchIds.has(+c.id))
            ids.add(+c.categoria_pai);              // inclui pai da sub
        if (!c.categoria_pai && matchIds.has(+c.id))
            _cats.filter(s => +s.categoria_pai === +c.id).forEach(s => ids.add(+s.id)); // inclui subs do pai
    });

    const dados = _cats.filter(c => ids.has(+c.id));

    const d = _cats.filter(c => c.ativo == 1);
    const nPais = d.filter(c => !c.categoria_pai).length;
    const nSubs = d.filter(c =>  c.categoria_pai).length;
    document.getElementById('kpiTotal').textContent   = nPais;
    document.getElementById('kpiDespesa').textContent = d.filter(c => c.tipo === 'despesa' && !c.categoria_pai).length;
    document.getElementById('kpiReceita').textContent = d.filter(c => c.tipo === 'receita' && !c.categoria_pai).length;
    document.getElementById('kpiAmbos').textContent   = d.filter(c => c.tipo === 'ambos'   && !c.categoria_pai).length;
    document.getElementById('pageSub').textContent    =
        `${nPais} categori${nPais !== 1 ? 'as' : 'a'} ativa${nPais !== 1 ? 's' : ''}` +
        (nSubs ? ` · ${nSubs} subcategori${nSubs !== 1 ? 'as' : 'a'}` : '');

    renderTabela(dados);
}

// ── Render ────────────────────────────────────────────────────
const _tipoMeta = {
    despesa: { label:'Despesa', cor:'rose'   },
    receita: { label:'Receita', cor:'emerald'},
    ambos:   { label:'Ambos',   cor:'amber'  },
};
const _irMeta = {
    tributavel:       { label:'IR: Tributável',  cor:'var(--amber)'  },
    isento:           { label:'IR: Isento',      cor:'var(--emerald)'},
    deducao_saude:    { label:'IR: Saúde',       cor:'var(--rose)'   },
    deducao_educacao: { label:'IR: Educação',    cor:'var(--indigo)' },
    deducao_previd:   { label:'IR: Previdência', cor:'var(--violet)' },
    deducao_outro:    { label:'IR: Outra Ded.',  cor:'var(--cyan)'   },
};

function _rowCat(c, isSub) {
    const tm = _tipoMeta[c.tipo] || { label: c.tipo, cor:'indigo' };
    const irBadge = c.tipo_ir && _irMeta[c.tipo_ir]
        ? `<span style="font-size:.68rem;font-weight:600;padding:.15rem .45rem;border-radius:999px;
                        background:${_irMeta[c.tipo_ir].cor}22;color:${_irMeta[c.tipo_ir].cor}">
               <i class="fa-solid fa-file-invoice-dollar fa-xs"></i> ${_irMeta[c.tipo_ir].label}
           </span>` : '';

    const iconeBox = `<div style="width:28px;height:28px;border-radius:var(--radius-sm);
                                  background:${esc(c.cor)}22;color:${esc(c.cor)};flex-shrink:0;
                                  display:flex;align-items:center;justify-content:center;font-size:.78rem">
                          <i class="fa-solid fa-${esc(c.icone||'tag')}"></i>
                      </div>`;

    const nameCell = isSub
        ? `<div class="sub-indent">
               ${iconeBox}
               <div>
                   <div class="fw-600 text-sm">${esc(c.nome)}</div>
                   ${irBadge ? `<div style="margin-top:.15rem">${irBadge}</div>` : ''}
               </div>
           </div>`
        : `<div class="d-flex align-center gap-1">
               <div style="width:32px;height:32px;border-radius:var(--radius-sm);
                           background:${esc(c.cor)}22;color:${esc(c.cor)};flex-shrink:0;
                           display:flex;align-items:center;justify-content:center;font-size:.8rem">
                   <i class="fa-solid fa-${esc(c.icone||'tag')}"></i>
               </div>
               <div>
                   <div class="fw-600 text-sm">${esc(c.nome)}</div>
                   ${irBadge ? `<div style="margin-top:.2rem">${irBadge}</div>` : ''}
               </div>
           </div>`;

    const novaSubBtn = !isSub
        ? `<button class="btn-icon" onclick="abrirModalSub(${+c.id})" title="Nova subcategoria"
                   style="width:28px;height:28px;font-size:.75rem;color:var(--indigo)">
               <i class="fa-solid fa-diagram-successor"></i>
           </button>` : '';

    return `<tr class="${isSub ? 'sub-row' : ''}">
        <td>${nameCell}</td>
        <td><span class="badge ${tm.cor}">${tm.label}</span></td>
        <td><span class="badge ${c.ativo == 1 ? 'pago' : 'cancelado'}">${c.ativo == 1 ? 'Ativo' : 'Inativo'}</span></td>
        <td>
            <div class="d-flex gap-1" style="justify-content:flex-end">
                ${novaSubBtn}
                <button class="btn-icon" onclick="abrirModal(${+c.id})" title="Editar"
                        style="width:28px;height:28px;font-size:.75rem">
                    <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button class="btn-icon" onclick="toggle(${+c.id})"
                        title="${c.ativo == 1 ? 'Desativar' : 'Ativar'}"
                        style="width:28px;height:28px;font-size:.75rem;color:${c.ativo == 1 ? 'var(--amber)' : 'var(--emerald)'}">
                    <i class="fa-solid fa-${c.ativo == 1 ? 'toggle-on' : 'toggle-off'}"></i>
                </button>
                <button class="btn-icon" onclick="excluir(${+c.id})" title="Excluir"
                        style="width:28px;height:28px;font-size:.75rem;color:var(--rose)">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </td>
    </tr>`;
}

function renderTabela(dados) {
    document.getElementById('tabelaInfo').textContent =
        `${dados.length} resultado${dados.length !== 1 ? 's' : ''}`;

    if (!dados.length) {
        document.getElementById('tabelaBody').innerHTML =
            `<tr><td colspan="4" style="text-align:center;padding:2.5rem;color:var(--text-600)">
                <i class="fa-solid fa-tags fa-2x" style="margin-bottom:.75rem;display:block"></i>
                Nenhuma categoria encontrada.
            </td></tr>`;
        return;
    }

    // Monta mapa de subcategorias
    const subsMap = {};
    dados.filter(c => c.categoria_pai).forEach(c => {
        const pai = +c.categoria_pai;
        if (!subsMap[pai]) subsMap[pai] = [];
        subsMap[pai].push(c);
    });

    // Pais presentes no conjunto filtrado
    const pais = dados.filter(c => !c.categoria_pai);
    // Subcategorias cujo pai não está no conjunto filtrado (órfãs — busca isolou a sub)
    const orfas = dados.filter(c => c.categoria_pai && !dados.find(p => +p.id === +c.categoria_pai));

    let html = '';
    pais.forEach(c => {
        html += _rowCat(c, false);
        (subsMap[+c.id] || []).forEach(s => { html += _rowCat(s, true); });
    });
    // Órfãs aparecem no fim com indicação do pai
    orfas.forEach(c => {
        const paiNome = (_cats.find(p => +p.id === +c.categoria_pai) || {}).nome || '';
        html += `<tr class="sub-row"><td>
            <div class="sub-indent">
                <div style="width:28px;height:28px;border-radius:var(--radius-sm);
                            background:${esc(c.cor)}22;color:${esc(c.cor)};flex-shrink:0;
                            display:flex;align-items:center;justify-content:center;font-size:.78rem">
                    <i class="fa-solid fa-${esc(c.icone||'tag')}"></i>
                </div>
                <div>
                    <div class="fw-600 text-sm">${esc(c.nome)}</div>
                    ${paiNome ? `<div class="text-xs text-muted">em ${esc(paiNome)}</div>` : ''}
                </div>
            </div>
        </td>
        <td><span class="badge ${(_tipoMeta[c.tipo]||{cor:'indigo'}).cor}">${(_tipoMeta[c.tipo]||{label:c.tipo}).label}</span></td>
        <td><span class="badge ${c.ativo == 1 ? 'pago' : 'cancelado'}">${c.ativo == 1 ? 'Ativo' : 'Inativo'}</span></td>
        <td>
            <div class="d-flex gap-1" style="justify-content:flex-end">
                <button class="btn-icon" onclick="abrirModal(${+c.id})" title="Editar"
                        style="width:28px;height:28px;font-size:.75rem">
                    <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button class="btn-icon" onclick="toggle(${+c.id})"
                        title="${c.ativo == 1 ? 'Desativar' : 'Ativar'}"
                        style="width:28px;height:28px;font-size:.75rem;color:${c.ativo == 1 ? 'var(--amber)' : 'var(--emerald)'}">
                    <i class="fa-solid fa-${c.ativo == 1 ? 'toggle-on' : 'toggle-off'}"></i>
                </button>
                <button class="btn-icon" onclick="excluir(${+c.id})" title="Excluir"
                        style="width:28px;height:28px;font-size:.75rem;color:var(--rose)">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </td></tr>`;
    });

    document.getElementById('tabelaBody').innerHTML = html;
}

// ── Busca de ícone ────────────────────────────────────────────
// Mapeamento de termos em pt-BR para partes do nome FA
const _iconeBusca = {
    'casa':'house','comida':'utensils bowl burger pizza drumstick fish carrot apple','bebida':'wine beer mug coffee','mercado':'cart bag shop','roupa':'shirt vest socks gem glasses umbrella scissors','carro':'car motorcycle bus train bicycle ship truck plane gas helicopter tractor','saude':'stethoscope prescription heart syringe eye brain dumbbell spa shield wheelchair tooth lungs bone bandage','educacao':'book graduation school pencil pen chalkboard laptop magnifying microscope','dinheiro':'wallet coins money piggy chart credit dollar sack vault receipt file percent landmark','trabalho':'briefcase desktop printer clipboard file folder users hard industry factory handshake building warehouse','entretenimento':'tv gamepad music headphones guitar ticket film radio masks dice microphone volleyball chess','viagem':'map compass suitcase tent earth','familia':'person people baby child ring face','eventos':'balloon champagne gift calendar party','natureza':'leaf tree seedling sun cloud snowflake mountain fire droplet wind bolt paw','internet':'wifi phone envelope message comments globe satellite paper tower',
};

function filtrarIcones(busca) {
    const b = busca.toLowerCase().trim();
    // Expande termos pt-BR → partes do nome FA
    let termos = b;
    Object.entries(_iconeBusca).forEach(([ptbr, fa]) => {
        if (ptbr.includes(b) || b.includes(ptbr)) termos += ' ' + fa;
    });
    const partes = termos.split(/\s+/).filter(Boolean);

    let visiveis = 0;
    document.querySelectorAll('.icone-opt').forEach(el => {
        const nome = el.dataset.ic;
        const visivel = !b || partes.some(p => nome.includes(p));
        el.style.display = visivel ? '' : 'none';
        if (visivel) visiveis++;
    });
}

// ── Modal ─────────────────────────────────────────────────────
function selecionarIcone(el) {
    document.querySelectorAll('.icone-opt').forEach(o => o.classList.remove('ativo'));
    el.classList.add('ativo');
    document.getElementById('fIcone').value = el.dataset.ic;
}

function _popularCatPai(excluirId, preselect) {
    const sel = document.getElementById('fCatPai');
    sel.innerHTML = '<option value="">— Categoria raiz (sem pai) —</option>';
    _cats
        .filter(c => !c.categoria_pai && c.ativo == 1 && +c.id !== +excluirId)
        .forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.nome;
            if (+c.id === +preselect) opt.selected = true;
            sel.appendChild(opt);
        });
}

function abrirModal(id = null) {
    document.getElementById('formCat').reset();
    document.getElementById('editId').value = id || '';
    document.getElementById('fCorText').textContent = '#6366f1';
    document.querySelectorAll('.icone-opt').forEach(o => { o.classList.remove('ativo'); o.style.display = ''; });
    document.querySelector('.icone-opt[data-ic="tag"]')?.classList.add('ativo');
    document.getElementById('fIcone').value      = 'tag';
    document.getElementById('fCor').value        = '#6366f1';
    document.getElementById('fTipoIR').value     = '';
    document.getElementById('iconeSearch').value = '';
    document.getElementById('catPaiNote').style.display = 'none';

    if (id) {
        document.getElementById('modalTitulo').textContent = 'Editar Categoria';
        const c = _cats.find(x => +x.id === +id);
        if (c) {
            document.getElementById('fNome').value   = c.nome    || '';
            document.getElementById('fTipoF').value  = c.tipo    || 'despesa';
            document.getElementById('fCor').value    = c.cor     || '#6366f1';
            document.getElementById('fCorText').textContent = c.cor || '#6366f1';
            document.getElementById('fIcone').value  = c.icone   || 'tag';
            document.getElementById('fTipoIR').value = c.tipo_ir || '';
            document.querySelectorAll('.icone-opt').forEach(o => o.classList.remove('ativo'));
            document.querySelector(`.icone-opt[data-ic="${c.icone||'tag'}"]`)?.classList.add('ativo');

            const temFilhos = _cats.some(x => +x.categoria_pai === +id);
            _popularCatPai(id, c.categoria_pai);
            document.getElementById('fCatPai').disabled = temFilhos;
            document.getElementById('catPaiNote').style.display = temFilhos ? '' : 'none';
        }
    } else {
        document.getElementById('modalTitulo').textContent = 'Nova Categoria';
        _popularCatPai(null, null);
        document.getElementById('fCatPai').disabled = false;
    }

    document.getElementById('modalOverlay').classList.add('open');
    setTimeout(() => document.getElementById('fNome').focus(), 80);
}

function abrirModalSub(paiId) {
    abrirModal(null);
    document.getElementById('modalTitulo').textContent = 'Nova Subcategoria';
    _popularCatPai(null, paiId);
    const pai = _cats.find(c => +c.id === +paiId);
    if (pai) {
        document.getElementById('fTipoF').value = pai.tipo;
        document.getElementById('fCor').value   = pai.cor || '#6366f1';
        document.getElementById('fCorText').textContent = pai.cor || '#6366f1';
    }
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
        acao:         'salvar_categoria',
        nome:         document.getElementById('fNome').value.trim(),
        tipo:         document.getElementById('fTipoF').value,
        cor:          document.getElementById('fCor').value,
        icone:        document.getElementById('fIcone').value,
        tipo_ir:      document.getElementById('fTipoIR').value || null,
        categoria_pai: parseInt(document.getElementById('fCatPai').value) || null,
    };
    if (id) payload.id = parseInt(id);

    try {
        const res  = await fetch('backend/api/configuracoes.php', {
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
        const res  = await fetch('backend/api/configuracoes.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'toggle_categoria', id }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        toast(json.msg, 'success');
        carregarDados();
    } catch (err) { toast('Erro: ' + err.message, 'error'); }
}

// ── Excluir ───────────────────────────────────────────────────
function excluir(id) {
    const c = _cats.find(x => +x.id === +id);
    confirmar('Excluir categoria',
        `Excluir "${c ? c.nome : 'esta categoria'}"? Se houver transações vinculadas, será desativada.`,
        async () => {
            try {
                const res  = await fetch(`backend/api/configuracoes.php?tipo=categoria&id=${id}`, { method: 'DELETE' });
                const json = await res.json();
                if (!json.success) throw new Error(json.erro);
                toast(json.msg, 'success');
                carregarDados();
            } catch (err) { toast('Erro: ' + err.message, 'error'); }
        });
}

// ── Inserir padrão ────────────────────────────────────────────
function inserirPadrao() {
    confirmar('Inserir categorias padrão', 'Adicionar categorias sugeridas? As já existentes serão ignoradas.',
        async () => {
            try {
                const res  = await fetch('backend/api/configuracoes.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ acao: 'inserir_padrao' }),
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.erro);
                toast(json.msg, 'success');
                carregarDados();
            } catch (err) { toast('Erro: ' + err.message, 'error'); }
        }, { btnLabel: 'Inserir', icone: 'wand-magic-sparkles', btnCor: 'var(--indigo)' });
}

// esc() vem de assets/js/app.js (carregado globalmente por index.php)

document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharModal(); });
document.addEventListener('DOMContentLoaded', carregarDados);
</script>
