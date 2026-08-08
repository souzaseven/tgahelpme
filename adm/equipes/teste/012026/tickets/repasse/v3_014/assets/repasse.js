'use strict';

// ─── Estado global ───────────────────────────────────────────────────────────
let semanaAtual = '';
let todosOps    = {};
let repasseData = {};
let feriasAtivas = {};
let resumoMesOperadores = {};
let filtroAtivo  = 'todos'; // 'todos' | 'tef' | 'troca_regime' | 'ferias' | 'nao_repassar'
let filtroEquipe = '';     // '' = todas, ou nome do lider
let ordemTabela  = 'ordem_original';

const STORAGE_FILTRO_ATIVO  = 'repasse:filtro-ativo';
const STORAGE_FILTRO_EQUIPE = 'repasse:filtro-equipe';
const STORAGE_ORDEM_TABELA  = 'repasse:ordem-tabela';

// SVG maquininha POS
const ICON_POS = `<svg class="icon-pos" viewBox="0 0 20 26" width="13" height="17"
    fill="none" stroke="currentColor" stroke-width="1.8"
    stroke-linecap="round" stroke-linejoin="round" title="TEF">
  <rect x="2" y="1" width="16" height="24" rx="2.5"/>
  <rect x="5" y="4" width="10" height="6" rx="1"/>
  <circle cx="7"  cy="15" r="1.1" fill="currentColor" stroke="none"/>
  <circle cx="10" cy="15" r="1.1" fill="currentColor" stroke="none"/>
  <circle cx="13" cy="15" r="1.1" fill="currentColor" stroke="none"/>
  <circle cx="7"  cy="19.5" r="1.1" fill="currentColor" stroke="none"/>
  <circle cx="10" cy="19.5" r="1.1" fill="currentColor" stroke="none"/>
  <circle cx="13" cy="19.5" r="1.1" fill="currentColor" stroke="none"/>
</svg>`;

// ─── Init ────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    restaurarPreferenciasFiltros();

    const semanaUrl = obterSemanaUrl();
    semanaAtual = semanaUrl || obterSegundaAtual();
    semanaAtual = normalizarParaSegunda(semanaAtual);

    if (semanaUrl && semanaUrl !== semanaAtual) {
        history.replaceState(null, '', `?semana=${semanaAtual}`);
    }

    document.getElementById('btn-semana-anterior').addEventListener('click', () => navSemana(-1));
    document.getElementById('btn-proxima-semana').addEventListener('click',  () => navSemana(+1));
    document.getElementById('btn-salvar').addEventListener('click', salvarSemana);
    document.getElementById('ordem-tabela').addEventListener('change', (event) => {
        ordemTabela = event.target.value || 'ordem_original';
        localStorage.setItem(STORAGE_ORDEM_TABELA, ordemTabela);
        aplicarOrdenacaoTabelas();
    });

    // Filtros linha (TEF / férias)
    document.querySelectorAll('.filter-btn[data-filter]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.filter-btn[data-filter]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            filtroAtivo = btn.dataset.filter;
            salvarPreferenciasFiltros();
            aplicarFiltro();
        });
    });

    sincronizarFiltroLinha();
    sincronizarOrdenacaoTabela();

    // Notificação do sino (apenas admin) - com polling e badge
    const btnNotificacao = document.getElementById('btn-notificacao');
    let notificacoesNovas = 0;
    let notificacoesCache = [];
    if (btnNotificacao) {
        // Badge
        let badge = document.createElement('span');
        badge.id = 'notificacao-badge';
        badge.className = 'notificacao-badge';
        btnNotificacao.style.position = 'relative';
        btnNotificacao.appendChild(badge);

        async function buscarNotificacoes(atualizarBadge = true) {
            try {
                const res = await fetch('api/notificacoes.php');
                if (res.status === 403) {
                    badge.style.display = 'none';
                    return;
                }
                const json = await res.json();
                notificacoesNovas = (json.novos && json.novos.length) ? json.novos.length : 0;
                notificacoesCache = json.novos || [];
                if (atualizarBadge) {
                    badge.textContent = notificacoesNovas;
                    badge.style.display = notificacoesNovas > 0 ? 'inline-block' : 'none';
                }
            } catch (e) {
                badge.style.display = 'none';
            }
        }

        // Polling automático a cada 30s
        setInterval(() => buscarNotificacoes(true), 30000);
        // Busca inicial
        buscarNotificacoes(true);

        btnNotificacao.addEventListener('click', async () => {
            await buscarNotificacoes(false); // Atualiza lista ao abrir
            if (notificacoesNovas > 0) {
                let html = `<div class="notificacao-header">
                    <span>🔔 Novos tickets recebidos</span>
                    <button class="fechar" id="fechar-painel-notificacao">&times;</button>
                </div>`;
                html += '<ul class="notificacao-lista">';
                for (const t of notificacoesCache) {
                    html += `<li title="Semana: ${t.semana}"><span class="avatar">👤</span><div><b>${t.nome_operador}</b> <span class="lider" title="Líder">(${t.lider})</span><div class="hora">${t.alterado_em.split(' ')[1]}</div></div></li>`;
                }
                html += '</ul>';
                html += '<button id="limpar-notificacoes" class="btn-limpar">Marcar como lido</button>';
                mostrarPainelNotificacao(html);
                badge.style.display = 'none';
            } else {
                let html = `<div class="notificacao-header">
                    <span>🔔 Novos tickets recebidos</span>
                    <button class="fechar" id="fechar-painel-notificacao">&times;</button>
                </div><div class="notificacao-vazio">Nenhum ticket novo nas últimas 10 minutos.</div>`;
                mostrarPainelNotificacao(html);
            }
        });
    }

    // Função para mostrar painel de notificações
    function mostrarPainelNotificacao(html) {
        let painel = document.getElementById('painel-notificacao');
        if (!painel) {
            painel = document.createElement('div');
            painel.id = 'painel-notificacao';
            document.body.appendChild(painel);
        }
        painel.innerHTML = html;
        // Fechar painel
        document.getElementById('fechar-painel-notificacao').onclick = () => painel.remove();
        // Marcar como lido
        const btnLimpar = document.getElementById('limpar-notificacoes');
        if (btnLimpar) btnLimpar.onclick = () => painel.remove();
    }

    carregarDados();

    // Mantém a sessão PHP viva enquanto a aba estiver aberta
    setInterval(async () => {
        try {
            const res = await fetch('api/keepalive.php');
            if (res.status === 401) {
                mostrarToast('Sessão expirada. Redirecionando para o login...', 'error');
                setTimeout(() => { window.location.href = 'login.php'; }, 2000);
            }
        } catch (_) { /* ignora falhas de rede */ }
    }, 10 * 60 * 1000); // a cada 10 minutos
});

function restaurarPreferenciasFiltros() {
    const filtroSalvo = localStorage.getItem(STORAGE_FILTRO_ATIVO);
    const equipeSalva = localStorage.getItem(STORAGE_FILTRO_EQUIPE);
    const ordemSalva  = localStorage.getItem(STORAGE_ORDEM_TABELA);

    if (['todos', 'tef', 'troca_regime', 'ferias', 'nao_repassar'].includes(filtroSalvo)) {
        filtroAtivo = filtroSalvo;
    }

    filtroEquipe = equipeSalva || '';
    ordemTabela = ordemSalva || 'ordem_original';
}

function salvarPreferenciasFiltros() {
    localStorage.setItem(STORAGE_FILTRO_ATIVO, filtroAtivo);
    localStorage.setItem(STORAGE_FILTRO_EQUIPE, filtroEquipe);
}

function sincronizarFiltroLinha() {
    document.querySelectorAll('.filter-btn[data-filter]').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.filter === filtroAtivo);
    });
}

function sincronizarOrdenacaoTabela() {
    const select = document.getElementById('ordem-tabela');
    if (!select) return;
    select.value = ordemTabela || 'ordem_original';
}

// ─── Filtros ──────────────────────────────────────────────────────────────────
function aplicarFiltro() {
    // Filtro de linha (TEF / Troca de Regime / férias)
    document.querySelectorAll('.repasse-table tbody tr').forEach(tr => {
        let visivel = true;
        if (filtroAtivo === 'tef')    visivel = tr.dataset.tef === '1';
        if (filtroAtivo === 'troca_regime') visivel = tr.dataset.trocaRegime === '1';
        if (filtroAtivo === 'ferias') visivel = tr.classList.contains('ferias-row') || tr.classList.contains('ferias-parcial');
        if (filtroAtivo === 'nao_repassar') visivel = tr.classList.contains('nao-repassar-row');
        tr.style.display = visivel ? '' : 'none';
    });

    // Filtro de equipe (mostra/oculta cards inteiros)
    document.querySelectorAll('.team-card').forEach(card => {
        const visivel = filtroEquipe === '' || card.dataset.lider === filtroEquipe;
        card.style.display = visivel ? '' : 'none';
    });
}

function construirFiltrosEquipe() {
    const bar = document.getElementById('filter-equipes');
    if (!bar) return;
    bar.innerHTML = '';

    const lideres = Object.keys(todosOps);
    if (filtroEquipe && !lideres.includes(filtroEquipe)) {
        filtroEquipe = '';
        salvarPreferenciasFiltros();
    }

    // Botão "Todas"
    const btnTodas = document.createElement('button');
    btnTodas.className   = 'filter-btn filter-equipe';
    btnTodas.dataset.lider = '';
    btnTodas.textContent = 'Todas as equipes';
    btnTodas.addEventListener('click', () => selecionarEquipe('', btnTodas));
    bar.appendChild(btnTodas);

    for (const lider in todosOps) {
        const btn = document.createElement('button');
        btn.className     = 'filter-btn filter-equipe';
        btn.dataset.lider = lider;
        btn.title         = lider;

        const nomeSpan = document.createElement('span');
        nomeSpan.textContent = lider.split(' ')[0];

        const badge = document.createElement('span');
        badge.className = 'equipe-btn-badge';
        badge.dataset.liderBadge = lider;
        badge.textContent = '0';

        btn.appendChild(nomeSpan);
        btn.appendChild(badge);
        btn.addEventListener('click', () => selecionarEquipe(lider, btn));
        bar.appendChild(btn);
    }

    sincronizarFiltroEquipe();
}

function selecionarEquipe(lider, btnClicado) {
    document.querySelectorAll('.filter-equipe').forEach(b => b.classList.remove('active'));
    btnClicado.classList.add('active');
    filtroEquipe = lider;
    salvarPreferenciasFiltros();
    aplicarFiltro();
}

function sincronizarFiltroEquipe() {
    const botoes = document.querySelectorAll('.filter-equipe');
    let encontrou = false;

    botoes.forEach(btn => {
        const ativo = btn.dataset.lider === filtroEquipe;
        btn.classList.toggle('active', ativo);
        encontrou = encontrou || ativo;
    });

    if (!encontrou) {
        filtroEquipe = '';
        botoes.forEach(btn => btn.classList.toggle('active', btn.dataset.lider === ''));
        salvarPreferenciasFiltros();
    }
}

// ─── Helpers de data ─────────────────────────────────────────────────────────
function obterSegundaAtual() {
    const hoje = new Date();
    const dia  = hoje.getDay();
    const diff = dia === 0 ? -6 : 1 - dia;
    const seg  = new Date(hoje);
    seg.setDate(hoje.getDate() + diff);
    return formatarData(seg);
}

function formatarData(d) {
    const y  = d.getFullYear();
    const m  = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${dd}`;
}

function obterSemanaUrl() {
    return new URLSearchParams(window.location.search).get('semana') || '';
}

function navSemana(offset) {
    const d = new Date(semanaAtual + 'T12:00:00');
    d.setDate(d.getDate() + offset * 7);
    semanaAtual = normalizarParaSegunda(formatarData(d));
    history.pushState(null, '', `?semana=${semanaAtual}`);
    carregarDados();
}

function normalizarParaSegunda(dataStr) {
    const d   = new Date(dataStr + 'T12:00:00');
    const dia = d.getDay(); // 0=Dom, 1=Seg, ..., 6=Sab
    const diff = dia === 0 ? -6 : 1 - dia;
    d.setDate(d.getDate() + diff);
    return formatarData(d);
}

function formatarDataExibicao(dataStr) {
    const d = new Date(dataStr + 'T12:00:00');
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function obterSexta(segunda) {
    const d = new Date(segunda + 'T12:00:00');
    d.setDate(d.getDate() + 5);
    return formatarData(d);
}

// Retorna 'seg'|'ter'|'qua'|'qui'|'sex'|'sab'|null para hoje dentro da semana exibida
function diaColunaHoje() {
    const hoje   = new Date();
    const hojeStr = formatarData(hoje);
    const diasSemana = ['seg','ter','qua','qui','sex','sab'];
    for (let i = 0; i < 6; i++) {
        const d = new Date(semanaAtual + 'T12:00:00');
        d.setDate(d.getDate() + i);
        if (formatarData(d) === hojeStr) return diasSemana[i];
    }
    return null;
}

// ─── Carregar dados ───────────────────────────────────────────────────────────
async function carregarDados() {
    mostrarLoading(true);

    try {
        const [resOps, resRep] = await Promise.all([
            fetch('api/operadores.php'),
            fetch(`api/buscar_repasse.php?semana=${semanaAtual}`),
        ]);

        if (resOps.status === 401 || resRep.status === 401) {
            window.location.href = 'login.php';
            return;
        }

        todosOps    = await resOps.json();
        const dados = await resRep.json();
        repasseData = dados.registros || {};
        feriasAtivas = dados.ferias_ativas || {};
        resumoMesOperadores = dados.resumo_mes_operadores || {};

        renderizarTudo();
        construirFiltrosEquipe();
        atualizarTituloSemana();
        aplicarFiltro();
        atualizarTodosBadgesEquipe();
    } catch (err) {
        console.error(err);
        mostrarToast('Erro ao carregar dados.', 'error');
    } finally {
        mostrarLoading(false);
    }
}

// ─── Renderização ─────────────────────────────────────────────────────────────
function renderizarTudo() {
    const main = document.getElementById('conteudo');
    main.innerHTML = '';

    for (const lider in todosOps) {
        main.appendChild(criarCardEquipe(lider, todosOps[lider]));
        renderizarLinhas(lider, todosOps[lider]);
    }

    destacarColunaDiaAtual();
}

function criarCardEquipe(lider, operadores) {
    const card = document.createElement('div');
    card.className = 'team-card';
    card.dataset.lider = lider;

    const fila = operadores.length ? operadores[0].fila : '';
    const slug = slugify(lider);

    card.innerHTML = `
        <div class="team-header">
            <div class="team-header-info">
                <div class="team-title-row">
                    <h2>${htmlEsc(lider)}</h2>
                    <span class="team-total-badge" data-stat="total-equipe" title="Total de tickets na semana">0</span>
                </div>
                <p class="team-fila">${htmlEsc(fila)}</p>
            </div>
            <div class="team-stats" id="stats-${slug}">
                <span class="stat-item" title="Operadores"><span class="stat-icon">👥</span><span class="stat-val" data-stat="total">0</span></span>
                <span class="stat-sep">·</span>
                <span class="stat-item" title="Em Férias"><span class="stat-icon">🏖</span><span class="stat-val" data-stat="ferias">0</span></span>
                <span class="stat-sep">·</span>
                <span class="stat-item" title="Tickets hoje"><span class="stat-icon">🎫</span><span class="stat-val" data-stat="hoje">0</span> hoje</span>
                <span class="stat-sep">·</span>
                <span class="stat-item" title="Tickets na semana"><span class="stat-icon">📊</span><span class="stat-val" data-stat="semana">0</span> semana</span>
                <span class="stat-sep">·</span>
                <span class="stat-item" title="Tickets no mês"><span class="stat-icon">🗓️</span><span class="stat-val" data-stat="mes">0</span> mês</span>
            </div>
        </div>
        <div class="table-wrap">
            <table class="repasse-table">
                <thead>
                    <tr>
                        <th class="th-suporte">Suporte</th>
                        <th class="th-nao-repassar">Não Repassar</th>
                        <th class="th-ferias">Férias</th>
                        <th data-dia="seg">Seg</th><th data-dia="ter">Ter</th><th data-dia="qua">Qua</th><th data-dia="qui">Qui</th><th data-dia="sex">Sex</th><th data-dia="sab">Sáb</th>
                        <th>Total</th>
                        <th class="th-del"></th>
                    </tr>
                </thead>
                <tbody id="tbody-${slug}"></tbody>
                <tfoot id="tfoot-${slug}"></tfoot>
            </table>
        </div>
        <div class="table-footer-bar">
            <div class="total-geral-bar" id="tgeral-${slug}">
                Total da equipe: <strong><span>0</span></strong>
            </div>
        </div>
    `;

    return card;
}

function renderizarLinhas(lider, operadores) {
    const slug  = slugify(lider);
    const tbody = document.getElementById(`tbody-${slug}`);
    if (!tbody) return;

    tbody.innerHTML = '';

    const normais = [];
    const ferias  = [];

    operadores.forEach((op, idx) => {
        const feriasPeriodo = feriasAtivas[op.id] || null;
        const dados = repasseData[op.id] || { seg:0, ter:0, qua:0, qui:0, sex:0, sab:0, em_ferias:false, nao_repassar:false };
        // Só trata como férias completas se a semana inteira estiver coberta
        if (feriasPeriodo?.semana_toda) dados.em_ferias = true;
        const tr = criarLinha(lider, op, dados, idx, feriasPeriodo);
        if (dados.em_ferias) ferias.push(tr);
        else normais.push(tr);
    });

    // Normais primeiro, férias no final
    [...normais, ...ferias].forEach(tr => tbody.appendChild(tr));

    aplicarOrdenacaoTabelas(lider);

    atualizarRodape(lider);
    atualizarHeaderStats(lider);
}

function criarLinha(lider, op, dados, ordem, feriasPeriodo = null) {
    const tr = document.createElement('tr');
    tr.dataset.opId   = op.id;
    tr.dataset.opNome = op.nome;
    tr.dataset.opNomeLc = String(op.nome || '').toLocaleLowerCase('pt-BR');
    tr.dataset.lider  = lider;
    tr.dataset.ordem  = ordem ?? 9999;
    tr.dataset.tef    = op.tef ? '1' : '0';
    tr.dataset.trocaRegime = op.troca_regime ? '1' : '0';
    tr.dataset.tempoMeses = op.tempo_empresa_meses ?? -1;
    tr.dataset.tempoTexto = op.tempo_empresa_texto || 'Sem data';

    const emFerias    = !!dados.em_ferias;
    const naoRepassar = !!dados.nao_repassar;
    if (emFerias) {
        tr.classList.add('ferias-row', 'ferias-bottom');
    } else if (feriasPeriodo) {
        // Férias parciais: marcador sem fundo global no TR
        tr.classList.add('ferias-parcial');
    }
    if (naoRepassar) {
        tr.classList.add('nao-repassar-row');
    }

    const posIcon = op.tef
        ? `<span class="icon-pos-wrap" title="TEF">${ICON_POS}</span>`
        : '';

    const dias = ['seg', 'ter', 'qua', 'qui', 'sex', 'sab'];
    const total = dias.reduce((s, d) => s + (parseInt(dados[d]) || 0), 0);

    const nomeHtml = emFerias
        ? `<span class="op-nome-texto ferias-nome">🏖 ${htmlEsc(op.nome)}</span>${posIcon}`
        : `<span class="op-nome-texto">${htmlEsc(op.nome)}</span>${posIcon}`;

    const metaHtml = `<div class="op-meta">Tempo empresa: ${htmlEsc(op.tempo_empresa_texto || 'Sem data')}</div>`;

    const diasParciais = (!emFerias && feriasPeriodo?.dias_ferias) ? feriasPeriodo.dias_ferias : [];
    const celsDias = dias.map(d => {
        const val         = parseInt(dados[d]) || 0;
        const diaEmFerias = emFerias || diasParciais.includes(d);
        const diaBloqueado = diaEmFerias || naoRepassar;
        // Célula de férias parcial ou de "não repassar" recebe classe para highlight individual
        const classeExtra = (!emFerias && diasParciais.includes(d))
            ? ' dia-ferias'
            : (naoRepassar ? ' dia-bloqueada' : '');
        return diaBloqueado
            ? `<td class="col-dia${classeExtra}" data-dia="${d}"><input type="number" class="dia-input" data-dia="${d}" min="0" value="" placeholder="—" disabled></td>`
            : `<td class="col-dia" data-dia="${d}"><input type="number" class="dia-input" data-dia="${d}" min="0" value="${val}"></td>`;
    }).join('');

    tr.innerHTML = `
        <td>
            <div class="op-nome">${nomeHtml}</div>
            ${metaHtml}
        </td>
        <td class="nao-repassar-cell">
            <label class="nao-repassar-label">
                <input type="checkbox" class="nao-repassar-check" ${naoRepassar ? 'checked' : ''}>
                <span class="nao-repassar-label-text">Não Repassar</span>
            </label>
        </td>
        <td class="ferias-cell">
            ${feriasPeriodo
                ? `<div class="ferias-ativa">
                       <span class="ferias-periodo-texto">🏖 ${formatarDataBr(feriasPeriodo.data_inicio)}–${formatarDataBr(feriasPeriodo.data_fim)}</span>
                       <button class="btn-cancelar-ferias" title="Cancelar férias">✕</button>
                   </div>`
                : `<label class="ferias-label">
                       <input type="checkbox" class="ferias-check">
                       <span class="ferias-label-text">Em Férias</span>
                   </label>`
            }
        </td>
        ${celsDias}
        <td class="total-cell">${(emFerias || naoRepassar) ? '—' : total}</td>
        <td>
            <button class="btn-excluir-linha" title="Remover desta semana">×</button>
        </td>
    `;

    if (feriasPeriodo) {
        tr.querySelector('.btn-cancelar-ferias').addEventListener('click', () => {
            confirmarCancelarFerias(tr, feriasPeriodo, op, lider);
        });
    } else {
        tr.querySelector('.ferias-check').addEventListener('click', function (e) {
            e.preventDefault();
            abrirModalFerias(op, lider, tr);
        });
    }

    tr.querySelector('.nao-repassar-check').addEventListener('change', function () {
        toggleNaoRepassar(tr, this.checked, lider);
    });

    tr.querySelectorAll('.dia-input').forEach(inp => {
        inp.addEventListener('input', () => {
            recalcularLinha(tr);
            atualizarRodape(lider);
            atualizarHeaderStats(lider);
        });
    });

    tr.querySelector('.btn-excluir-linha').addEventListener('click', () => removerLinha(tr, lider));

    return tr;
}

function destacarColunaDiaAtual() {
    const diaAtual = diaColunaHoje();

    document.querySelectorAll('.repasse-table th, .repasse-table td').forEach(el => {
        el.classList.remove('dia-atual-col');
    });

    if (!diaAtual) {
        return;
    }

    document.querySelectorAll(`.repasse-table th[data-dia="${diaAtual}"]`).forEach(th => {
        th.classList.add('dia-atual-col');
    });

    document.querySelectorAll(`.repasse-table td[data-dia="${diaAtual}"]`).forEach(td => {
        td.classList.add('dia-atual-col');
    });
}

function toggleFerias(tr, ativo, lider) {
    const inputs  = tr.querySelectorAll('.dia-input');
    const total   = tr.querySelector('.total-cell');
    const nomeDiv = tr.querySelector('.op-nome');
    const tbody   = tr.closest('tbody');

    if (ativo) {
        tr.classList.add('ferias-row', 'ferias-bottom');
        inputs.forEach(i => { i.disabled = true; i.value = ''; i.placeholder = '—'; });
        if (total) total.textContent = '—';

        // Atualiza nome com emoji
        const nomeSpan = tr.querySelector('.op-nome-texto');
        if (nomeSpan && !nomeSpan.textContent.startsWith('🏖')) {
            nomeSpan.classList.add('ferias-nome');
            nomeSpan.textContent = '🏖 ' + tr.dataset.opNome;
        }

        // Move para o final do tbody
        tbody.appendChild(tr);
    } else {
        tr.classList.remove('ferias-row', 'ferias-bottom');
        if (!tr.classList.contains('nao-repassar-row')) {
            inputs.forEach(i => { i.disabled = false; i.value = 0; i.placeholder = ''; });
            recalcularLinha(tr);
        } else if (total) {
            total.textContent = '—';
        }

        // Restaura nome sem emoji
        const nomeSpan = tr.querySelector('.op-nome-texto');
        if (nomeSpan) {
            nomeSpan.classList.remove('ferias-nome');
            nomeSpan.textContent = tr.dataset.opNome;
        }

        // Reposiciona na ordem original (antes do primeiro férias ou antes de quem tem ordem maior)
        const ordemAtual = parseInt(tr.dataset.ordem);
        let inseridoAntes = false;
        tbody.querySelectorAll('tr').forEach(outra => {
            if (inseridoAntes) return;
            const ordemOutra = parseInt(outra.dataset.ordem);
            if (outra !== tr && ordemOutra > ordemAtual) {
                tbody.insertBefore(tr, outra);
                inseridoAntes = true;
            }
        });
        if (!inseridoAntes) {
            // Coloca antes das linhas de férias completas
            const primeiraFerias = tbody.querySelector('tr.ferias-bottom');
            if (primeiraFerias) tbody.insertBefore(tr, primeiraFerias);
            else tbody.appendChild(tr);
        }
    }

    atualizarRodape(lider);
    atualizarHeaderStats(lider);
    aplicarOrdenacaoTabelas(lider);
    aplicarFiltro();
}

// ─── Não repassar: bloqueio manual da semana, sem período determinado ─────────
function toggleNaoRepassar(tr, ativo, lider) {
    tr.classList.toggle('nao-repassar-row', ativo);

    const inputs = tr.querySelectorAll('.dia-input');
    const total  = tr.querySelector('.total-cell');
    const emFerias = tr.classList.contains('ferias-row') || tr.classList.contains('ferias-parcial');

    if (ativo) {
        inputs.forEach(inp => {
            inp.disabled = true;
            inp.value = '';
            inp.placeholder = '—';
            inp.closest('td')?.classList.add('dia-bloqueada');
        });
        if (total) total.textContent = '—';
    } else if (!emFerias) {
        inputs.forEach(inp => {
            inp.disabled = false;
            inp.value = 0;
            inp.placeholder = '';
            inp.closest('td')?.classList.remove('dia-bloqueada');
        });
        recalcularLinha(tr);
    }

    atualizarRodape(lider);
    atualizarHeaderStats(lider);
    aplicarFiltro();
}

// ─── Férias: helpers e funções de período ─────────────────────────────────────

function formatarDataBr(dateStr) {
    if (!dateStr) return '';
    const [, mes, dia] = dateStr.split('-');
    return `${dia}/${mes}`;
}

function computarDiasFerias(dataInicio, dataFim, semana) {
    const diasNomes = ['seg', 'ter', 'qua', 'qui', 'sex', 'sab'];
    const vacStart  = new Date(dataInicio + 'T12:00:00');
    const vacEnd    = new Date(dataFim    + 'T12:00:00');
    const semanaBase = new Date(semana    + 'T12:00:00');
    const result = [];
    for (let i = 0; i < 6; i++) {
        const d = new Date(semanaBase);
        d.setDate(d.getDate() + i);
        if (d >= vacStart && d <= vacEnd) result.push(diasNomes[i]);
    }
    return result;
}

function garantirModalFerias() {
    if (document.getElementById('modal-ferias')) return;
    const modal = document.createElement('div');
    modal.id = 'modal-ferias';
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-box">
            <h3 class="modal-titulo">🏖 Período de Férias</h3>
            <p class="modal-op-nome"></p>
            <div class="modal-campos">
                <label class="modal-label">Início
                    <input type="date" id="ferias-data-inicio" class="modal-input">
                </label>
                <label class="modal-label">Fim
                    <input type="date" id="ferias-data-fim" class="modal-input">
                </label>
            </div>
            <div class="modal-acoes">
                <button id="modal-ferias-cancelar" class="btn btn-outline-dark">Cancelar</button>
                <button id="modal-ferias-confirmar" class="btn btn-primary">Salvar Férias</button>
            </div>
        </div>`;
    document.body.appendChild(modal);

    modal.addEventListener('click', e => { if (e.target === modal) fecharModalFerias(); });
    document.getElementById('modal-ferias-cancelar').addEventListener('click', fecharModalFerias);
}

function abrirModalFerias(op, lider, tr) {
    garantirModalFerias();
    const modal = document.getElementById('modal-ferias');
    modal.querySelector('.modal-op-nome').textContent = op.nome;

    const hoje = new Date().toISOString().slice(0, 10);
    document.getElementById('ferias-data-inicio').value = hoje;
    document.getElementById('ferias-data-fim').value    = '';

    modal.style.display = 'flex';

    // Troca o botão para limpar listeners anteriores
    const btnAntigo = document.getElementById('modal-ferias-confirmar');
    const btnNovo   = btnAntigo.cloneNode(true);
    btnAntigo.parentNode.replaceChild(btnNovo, btnAntigo);

    btnNovo.addEventListener('click', async () => {
        const inicio = document.getElementById('ferias-data-inicio').value;
        const fim    = document.getElementById('ferias-data-fim').value;

        if (!inicio || !fim) { mostrarToast('Informe as datas de início e fim.', 'error'); return; }
        if (fim < inicio)    { mostrarToast('A data de fim deve ser posterior ao início.', 'error'); return; }

        btnNovo.disabled    = true;
        btnNovo.textContent = '⏳ Salvando...';

        try {
            const res  = await fetch('api/ferias.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ action: 'criar', operador_id: op.id, nome_operador: op.nome, data_inicio: inicio, data_fim: fim }),
            });
            const json = await res.json();

            if (json.success) {
                fecharModalFerias();
                const diasFerias = computarDiasFerias(inicio, fim, semanaAtual);
                atualizarCelulaFerias(tr, {
                    id: json.id, data_inicio: inicio, data_fim: fim,
                    dias_ferias: diasFerias, semana_toda: diasFerias.length === 6,
                }, lider);
                mostrarToast(`Férias salvas: ${formatarDataBr(inicio)} – ${formatarDataBr(fim)}`, 'success');
            } else {
                mostrarToast(json.erro || 'Erro ao salvar férias.', 'error');
            }
        } catch (err) {
            mostrarToast('Falha na comunicação com o servidor.', 'error');
        } finally {
            btnNovo.disabled    = false;
            btnNovo.textContent = 'Salvar Férias';
        }
    });
}

function fecharModalFerias() {
    const modal = document.getElementById('modal-ferias');
    if (modal) modal.style.display = 'none';
}

async function confirmarCancelarFerias(tr, feriasPeriodo, op, lider) {
    const ini = formatarDataBr(feriasPeriodo.data_inicio);
    const fim = formatarDataBr(feriasPeriodo.data_fim);
    if (!confirm(`Cancelar férias de ${op.nome}?\nPeríodo: ${ini} – ${fim}`)) return;

    try {
        const res  = await fetch('api/ferias.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action: 'cancelar', id: feriasPeriodo.id }),
        });
        const json = await res.json();

        if (json.success) {
            atualizarCelulaFerias(tr, null, lider);
            mostrarToast('Férias canceladas.', 'success');
        } else {
            mostrarToast(json.erro || 'Erro ao cancelar férias.', 'error');
        }
    } catch (err) {
        mostrarToast('Falha na comunicação com o servidor.', 'error');
    }
}

function atualizarCelulaFerias(tr, feriasPeriodo, lider) {
    const opId   = parseInt(tr.dataset.opId);
    const opNome = tr.dataset.opNome;

    if (feriasPeriodo) {
        feriasAtivas[opId] = feriasPeriodo;
    } else {
        delete feriasAtivas[opId];
    }

    const celula = tr.querySelector('.ferias-cell');
    if (feriasPeriodo) {
        celula.innerHTML = `
            <div class="ferias-ativa">
                <span class="ferias-periodo-texto">🏖 ${formatarDataBr(feriasPeriodo.data_inicio)}–${formatarDataBr(feriasPeriodo.data_fim)}</span>
                <button class="btn-cancelar-ferias" title="Cancelar férias">✕</button>
            </div>`;
        celula.querySelector('.btn-cancelar-ferias').addEventListener('click', () => {
            confirmarCancelarFerias(tr, feriasPeriodo, { id: opId, nome: opNome }, lider);
        });

        if (feriasPeriodo.semana_toda) {
            toggleFerias(tr, true, lider);
        } else {
            // Semana parcial: marca o TR e destaca só as células de férias
            tr.classList.add('ferias-parcial');
            const diasFerias = feriasPeriodo.dias_ferias || [];
            tr.querySelectorAll('.dia-input').forEach(inp => {
                const td = inp.closest('td');
                if (diasFerias.includes(inp.dataset.dia)) {
                    inp.disabled = true; inp.value = ''; inp.placeholder = '—';
                    if (td) td.classList.add('dia-ferias');
                } else {
                    inp.disabled = false;
                    if (td) td.classList.remove('dia-ferias');
                }
            });
            recalcularLinha(tr);
            atualizarRodape(lider);
            atualizarHeaderStats(lider);
            aplicarFiltro();
        }
    } else {
        celula.innerHTML = `
            <label class="ferias-label">
                <input type="checkbox" class="ferias-check">
                <span class="ferias-label-text">Em Férias</span>
            </label>`;
        celula.querySelector('.ferias-check').addEventListener('click', function (e) {
            e.preventDefault();
            abrirModalFerias({ id: opId, nome: opNome }, lider, tr);
        });
        // Limpa marcadores de férias parciais
        tr.classList.remove('ferias-parcial');
        tr.querySelectorAll('td.dia-ferias').forEach(td => td.classList.remove('dia-ferias'));
        toggleFerias(tr, false, lider);
    }
}

function valorCampoOrdenacao(tr, campo) {
    if (campo === 'nome') return tr.dataset.opNomeLc || '';
    if (campo === 'tempo') return parseInt(tr.dataset.tempoMeses, 10);
    if (campo === 'total') return parseInt(tr.querySelector('.total-cell')?.textContent, 10);

    const dias = ['seg', 'ter', 'qua', 'qui', 'sex', 'sab'];
    if (dias.includes(campo)) {
        const input = tr.querySelector(`.dia-input[data-dia="${campo}"]`);
        return input ? parseInt(input.value, 10) : 0;
    }

    return null;
}

function aplicarOrdenacaoTabelas(liderAlvo = null) {
    const tbodyList = liderAlvo
        ? [document.getElementById(`tbody-${slugify(liderAlvo)}`)].filter(Boolean)
        : Array.from(document.querySelectorAll('.repasse-table tbody'));

    tbodyList.forEach(tbody => {
        const rows = Array.from(tbody.querySelectorAll('tr'));
        if (!rows.length) return;

        if (ordemTabela === 'ordem_original') {
            rows.sort((a, b) => {
                const feriasA = a.classList.contains('ferias-bottom') ? 1 : 0;
                const feriasB = b.classList.contains('ferias-bottom') ? 1 : 0;
                if (feriasA !== feriasB) return feriasA - feriasB;
                return (parseInt(a.dataset.ordem, 10) || 9999) - (parseInt(b.dataset.ordem, 10) || 9999);
            });
        } else {
            const [campo, dir] = String(ordemTabela).split('_');
            const multiplicador = dir === 'asc' ? 1 : -1;

            rows.sort((a, b) => {
                const valorA = valorCampoOrdenacao(a, campo);
                const valorB = valorCampoOrdenacao(b, campo);

                if (typeof valorA === 'string' || typeof valorB === 'string') {
                    const cmp = String(valorA || '').localeCompare(String(valorB || ''), 'pt-BR');
                    return cmp * multiplicador;
                }

                const aNum = Number.isFinite(valorA) ? valorA : -1;
                const bNum = Number.isFinite(valorB) ? valorB : -1;
                if (aNum === bNum) {
                    return String(a.dataset.opNomeLc || '').localeCompare(String(b.dataset.opNomeLc || ''), 'pt-BR');
                }
                return (aNum - bNum) * multiplicador;
            });
        }

        rows.forEach(tr => tbody.appendChild(tr));
    });
}

function recalcularLinha(tr) {
    const inputs = tr.querySelectorAll('.dia-input');
    let total = 0;
    inputs.forEach(i => { total += Math.max(0, parseInt(i.value) || 0); });
    const cell = tr.querySelector('.total-cell');
    if (cell) cell.textContent = total;

    const lider = tr.dataset.lider;
    if (lider) {
        aplicarOrdenacaoTabelas(lider);
    }
}

function atualizarRodape(lider) {
    const slug  = slugify(lider);
    const tbody = document.getElementById(`tbody-${slug}`);
    const tfoot = document.getElementById(`tfoot-${slug}`);
    if (!tbody || !tfoot) return;

    const dias    = ['seg', 'ter', 'qua', 'qui', 'sex', 'sab'];
    const totDias = Object.fromEntries(dias.map(d => [d, 0]));
    let totGeral  = 0;

    tbody.querySelectorAll('tr').forEach(tr => {
        dias.forEach(d => {
            const inp = tr.querySelector(`.dia-input[data-dia="${d}"]`);
            totDias[d] += inp && !inp.disabled ? (Math.max(0, parseInt(inp.value) || 0)) : 0;
        });
        const tc = tr.querySelector('.total-cell');
        const v  = tc ? parseInt(tc.textContent) : 0;
        totGeral += isNaN(v) ? 0 : v;
    });

    const celsDias = dias.map(d => `<td>${totDias[d]}</td>`).join('');
    tfoot.innerHTML = `
        <tr class="tfoot-totals">
            <td>Total por dia</td><td></td>
            ${celsDias}
            <td>${totGeral}</td><td></td>
        </tr>
    `;

    const bar = document.getElementById(`tgeral-${slug}`);
    if (bar) bar.querySelector('span').textContent = totGeral;

    // Atualiza badge no botão de filtro desta equipe
    const badge = document.querySelector(`[data-lider-badge="${CSS.escape(lider)}"]`);
    if (badge) badge.textContent = totGeral;

    atualizarTotalGeralSemana();
}

function atualizarTotalGeralSemana() {
    let total = 0;

    document.querySelectorAll('.repasse-table tbody tr').forEach(tr => {
        if (tr.classList.contains('ferias-bottom')) return;
        const valor = parseInt(tr.querySelector('.total-cell')?.textContent, 10);
        total += Number.isFinite(valor) ? valor : 0;
    });

    const el = document.getElementById('total-semana-geral');
    if (el) {
        el.textContent = `Total geral: ${total.toLocaleString('pt-BR')}`;
    }
}

function atualizarTodosBadgesEquipe() {
    for (const lider in todosOps) {
        const slug  = slugify(lider);
        const tbody = document.getElementById(`tbody-${slug}`);
        if (!tbody) continue;

        let totGeral = 0;
        tbody.querySelectorAll('tr').forEach(tr => {
            const tc = tr.querySelector('.total-cell');
            const v  = tc ? parseInt(tc.textContent) : 0;
            totGeral += isNaN(v) ? 0 : v;
        });

        const badge = document.querySelector(`[data-lider-badge="${CSS.escape(lider)}"]`);
        if (badge) badge.textContent = totGeral;

        // Atualiza também o badge do card no topo
        const cardBadge = document.querySelector(`#stats-${slug}`)?.closest('.team-header')?.querySelector('.team-total-badge');
        if (cardBadge) cardBadge.textContent = totGeral;
    }
}

function atualizarHeaderStats(lider) {
    const slug  = slugify(lider);
    const tbody = document.getElementById(`tbody-${slug}`);
    const stats = document.getElementById(`stats-${slug}`);
    if (!tbody || !stats) return;

    const rows    = tbody.querySelectorAll('tr');
    const total   = rows.length;
    let feriasCt  = 0, hojeTotal = 0, semanaTotal = 0, mesTotal = 0;
    const diaHoje = diaColunaHoje();
    const dias    = ['seg', 'ter', 'qua', 'qui', 'sex', 'sab'];

    rows.forEach(tr => {
        const opId = parseInt(tr.dataset.opId, 10);
        mesTotal += Math.max(0, parseInt(resumoMesOperadores[opId]) || 0);

        if (tr.classList.contains('ferias-row') || tr.classList.contains('ferias-parcial')) feriasCt++;
        if (tr.classList.contains('ferias-bottom')) return; // férias completas: não conta tickets
        dias.forEach(d => {
            const inp = tr.querySelector(`.dia-input[data-dia="${d}"]`);
            const v   = Math.max(0, parseInt(inp?.value) || 0);
            semanaTotal += v;
            if (d === diaHoje) hojeTotal += v;
        });
    });

    const set = (attr, val) => {
        const el = stats.querySelector(`[data-stat="${attr}"]`);
        if (el) el.textContent = val;
    };

    // Atualiza badge no topo do card
    const badge = document.querySelector(`#stats-${slug}`)?.closest('.team-header')?.querySelector('.team-total-badge');
    if (badge) badge.textContent = semanaTotal;

    set('total',  total);
    set('ferias', feriasCt);
    set('hoje',   diaHoje ? hojeTotal : '—');
    set('semana', semanaTotal);
    set('mes',    mesTotal);
}

// ─── Remover linha ────────────────────────────────────────────────────────────
function removerLinha(tr, lider) {
    const nome = tr.dataset.opNome || 'este operador';
    if (!confirm(`Remover ${nome} da semana atual?\nOs dados desta semana para este operador serão apagados ao clicar em "Salvar Semana".`)) return;

    tr.remove();
    atualizarRodape(lider);
    atualizarHeaderStats(lider);
    atualizarTotalGeralSemana();
}

// ─── Salvar semana ────────────────────────────────────────────────────────────
async function salvarSemana() {
    const btn = document.getElementById('btn-salvar');
    btn.disabled    = true;
    btn.textContent = '⏳ Salvando...';

    const payload = { semana: semanaAtual, operadores: [] };

    document.querySelectorAll('.repasse-table tbody tr').forEach(tr => {
        const id   = parseInt(tr.dataset.opId);
        const nome = tr.dataset.opNome || '';
        const dias = ['seg', 'ter', 'qua', 'qui', 'sex', 'sab'];
        const vals = {};
        dias.forEach(d => {
            const inp = tr.querySelector(`.dia-input[data-dia="${d}"]`);
            vals[d]   = Math.max(0, parseInt(inp?.value) || 0);
        });
        const naoRepassar = tr.querySelector('.nao-repassar-check')?.checked ? 1 : 0;
        payload.operadores.push({ operador_id: id, nome, nao_repassar: naoRepassar, ...vals });
    });

    try {
        const res  = await fetch('api/salvar_repasse.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        });
        const json = await res.json();

        if (res.status === 401) {
            mostrarToast('Sessão expirada. Redirecionando para o login...', 'error');
            setTimeout(() => { window.location.href = 'login.php'; }, 2000);
            return;
        }

        if (json.success) {
            await carregarDados();
            mostrarToast(json.mensagem, 'success');
        } else {
            mostrarToast(json.erro || 'Erro ao salvar.', 'error');
        }
    } catch (err) {
        console.error(err);
        mostrarToast('Falha na comunicação com o servidor.', 'error');
    } finally {
        btn.disabled    = false;
        btn.textContent = '💾 Salvar Semana';
    }
}

// ─── Título da semana ─────────────────────────────────────────────────────────
function atualizarTituloSemana() {
    const inicio = formatarDataExibicao(semanaAtual);
    const fim    = formatarDataExibicao(obterSexta(semanaAtual));
    document.getElementById('semana-titulo').textContent = `Semana de ${inicio} a ${fim}`;
    document.title = `Repasse — ${inicio}`;
}

// ─── Toast ────────────────────────────────────────────────────────────────────
// ─── Loading ──────────────────────────────────────────────────────────────────
function mostrarLoading(visivel) {
    const el = document.getElementById('loading');
    if (el) el.style.display = visivel ? 'flex' : 'none';
}

// ─── Utilitários ──────────────────────────────────────────────────────────────
function htmlEsc(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function slugify(str) {
    return str.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
}
