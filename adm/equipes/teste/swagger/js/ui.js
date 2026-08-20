/**
 * ui.js
 * Funções de renderização: request viewer, response viewer, badges de
 * status, máscara de token/senha e histórico de requisições em memória.
 */

const TgaUi = {
  // Histórico fica só na memória da aba (nunca em storage persistente).
  history: [],

  // Expiração do token atual (Date, ou null se não for um JWT reconhecível).
  tokenExpiryDate: null,

  /** Mascara valores sensíveis (senha) antes de exibir no Request Viewer. */
  maskSensitiveBody(body) {
    if (!body || typeof body !== 'object') return body;
    const clone = { ...body };
    if ('password' in clone) clone.password = '•'.repeat(String(clone.password).length || 8);
    return clone;
  },

  /** Mascara o token para exibição parcial (primeiros/últimos caracteres). */
  maskToken(token) {
    if (!token) return '';
    if (token.length <= 12) return '•'.repeat(token.length);
    return `${token.slice(0, 6)}${'•'.repeat(18)}${token.slice(-4)}`;
  },

  renderRequest({ method, url, headers, body }) {
    document.getElementById('reqMethod').textContent = method;
    document.getElementById('reqUrl').textContent = url;

    const displayHeaders = { ...headers };
    // O header authorization nunca é exibido por completo no viewer.
    // Mantém o prefixo "Bearer " visível (é o que explica o formato exigido
    // pela API) e mascara só o token em si.
    if (displayHeaders.authorization) {
      const match = displayHeaders.authorization.match(/^Bearer (.+)$/);
      displayHeaders.authorization = match
        ? `Bearer ${this.maskToken(match[1])}`
        : this.maskToken(displayHeaders.authorization);
    }
    document.getElementById('reqHeaders').textContent = JSON.stringify(displayHeaders, null, 2);

    const safeBody = this.maskSensitiveBody(body);
    document.getElementById('reqBody').textContent = safeBody
      ? JSON.stringify(safeBody, null, 2)
      : '(sem corpo)';

    // Guarda a requisição "crua" (não mascarada) só para o gerador de
    // código, que decide por si só o que mascarar (senha sempre, token
    // conforme o checkbox "Incluir token real").
    this.lastRequest = { method, url, headers, body };
    this.renderCodegen();
  },

  /** Gera o exemplo de código na aba/linguagem atualmente selecionada. */
  renderCodegen() {
    const codeEl = document.getElementById('codegenBody');
    const copyBtn = document.getElementById('btnCopyCodegen');
    if (!this.lastRequest) return;

    const activeTab = document.querySelector('#codegenTabs .view-tab--active');
    const lang = activeTab ? activeTab.dataset.lang : 'javascript';
    const includeToken = document.getElementById('codegenIncludeToken').checked;

    const code = TgaCodegen.generate(this.lastRequest, lang, includeToken);
    codeEl.textContent = code;
    copyBtn.disabled = false;
    copyBtn.dataset.payload = code;
  },

  statusBadgeClass(status) {
    if (status >= 200 && status < 300) return 'badge--ok';
    if (status >= 400 && status < 500) return 'badge--danger';
    if (status >= 500) return 'badge--danger';
    return 'badge--neutral';
  },

  statusText(status, statusText) {
    return `${status} ${statusText || ''}`.trim();
  },

  renderResponse({ response, durationMs, networkError }) {
    const badge = document.getElementById('resStatus');
    const time = document.getElementById('resTime');
    const copyBtn = document.getElementById('btnCopyResponse');
    const exportBtn = document.getElementById('btnExportCsv');
    const searchInput = document.getElementById('responseSearch');
    searchInput.value = '';

    if (networkError) {
      badge.textContent = 'Falha de rede';
      badge.className = 'badge badge--danger';
      time.textContent = `${durationMs} ms`;
      this.lastResponseBody = null;
      this.lastJsonText = String(networkError.message || networkError);
      this.lastRawText = this.lastJsonText;
      copyBtn.disabled = true;
      exportBtn.disabled = true;
      this.setResponseView('json');
      this.renderErrorInterpretation(null, networkError);
      return;
    }

    badge.textContent = this.statusText(response.status, response.statusText);
    badge.className = `badge ${this.statusBadgeClass(response.status)}`;
    time.textContent = `${durationMs} ms`;

    this.lastResponseBody = response.body;
    this.lastJsonText = typeof response.body === 'string'
      ? response.body
      : JSON.stringify(response.body, null, 2);
    this.lastRawText = typeof response.body === 'string'
      ? response.body
      : JSON.stringify(response.body);

    copyBtn.disabled = false;
    copyBtn.dataset.payload = this.lastJsonText;

    // Exportar CSV só faz sentido quando a resposta tem uma lista de
    // registros (mesma detecção usada na aba Tabela) — independe de qual
    // aba está ativa no momento.
    const rows = this.extractTableRows(this.lastResponseBody);
    exportBtn.disabled = !(rows && rows.length > 0 && typeof rows[0] === 'object' && rows[0] !== null);

    this.setResponseView('json');
    this.renderErrorInterpretation(response, null);
  },

  /** Alterna entre as abas JSON / Tabela / Ficha / Raw do Response Viewer. */
  setResponseView(view) {
    this.currentView = view;
    // Escopado a #responseViewTabs: a classe "view-tab" também é usada nas
    // abas do Gerador de Código (JS/PHP/cURL) — sem esse escopo, trocar de
    // aba aqui desmarcava por engano a aba de linguagem ativa lá embaixo.
    document.querySelectorAll('#responseViewTabs .view-tab').forEach(tab => {
      tab.classList.toggle('view-tab--active', tab.dataset.view === view);
    });

    const resBody = document.getElementById('resBody');
    const resTable = document.getElementById('resTable');
    const searchInput = document.getElementById('responseSearch');

    if (view === 'table' || view === 'ficha') {
      const rows = this.extractTableRows(this.lastResponseBody);
      resTable.innerHTML = view === 'table'
        ? this.buildTableHtml(rows)
        : this.buildFichaHtml(this.lastResponseBody);
      resBody.classList.add('hidden');
      resTable.classList.remove('hidden');
      searchInput.classList.toggle('hidden', !rows || rows.length === 0);
      this.filterStructuredView(searchInput.value);
      this.updateScrollHint(view);
    } else {
      resBody.textContent = view === 'raw' ? this.lastRawText : this.lastJsonText;
      resBody.classList.remove('hidden');
      resTable.classList.add('hidden');
      searchInput.classList.add('hidden');
      document.getElementById('tableScrollHint').classList.add('hidden');
    }
  },

  /**
   * Mostra o aviso "role para o lado" só quando a tabela realmente não cabe
   * na largura disponível (detectado de verdade via scrollWidth/clientWidth,
   * não um palpite). Só faz sentido na aba Tabela — a Ficha nunca corta
   * horizontalmente, os cartões quebram linha sozinhos.
   */
  updateScrollHint(view) {
    const hint = document.getElementById('tableScrollHint');
    if (view !== 'table') {
      hint.classList.add('hidden');
      return;
    }
    // Espera o navegador desenhar a tabela antes de medir (senão scrollWidth vem zerado).
    requestAnimationFrame(() => {
      const resTable = document.getElementById('resTable');
      const overflowing = resTable.scrollWidth > resTable.clientWidth + 1;
      hint.classList.toggle('hidden', !overflowing);
    });
  },

  /** Busca dentro da tabela OU da ficha, conforme a aba ativa no momento. */
  filterStructuredView(term) {
    if (this.currentView === 'ficha') this.filterCards(term);
    else this.filterTable(term);
  },

  /**
   * Encontra o array de registros dentro da resposta para montar a tabela.
   * Endpoints Misc devolvem `{ok, message, data: [...]}` — mas também aceita
   * a resposta já ser um array puro, ou outras chaves comuns de paginação.
   */
  extractTableRows(body) {
    if (Array.isArray(body)) return body;
    if (body && typeof body === 'object') {
      for (const key of ['data', 'result', 'results', 'items']) {
        if (Array.isArray(body[key])) return body[key];
      }
    }
    return null;
  },

  buildTableHtml(rows) {
    if (!rows || rows.length === 0) {
      return '<p class="muted small">Não há uma lista de registros nesta resposta para exibir em tabela.</p>';
    }
    if (typeof rows[0] !== 'object' || rows[0] === null) {
      return '<p class="muted small">Os itens desta lista não são objetos — use a aba JSON.</p>';
    }

    // Escapa tudo: os valores vêm da API externa, nunca confiar ao injetar em innerHTML.
    const esc = (v) => String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const cell = (v) => {
      if (v === undefined || v === null) return '';
      return esc(typeof v === 'object' ? JSON.stringify(v) : v);
    };

    const columns = [...new Set(rows.flatMap(r => (r && typeof r === 'object') ? Object.keys(r) : []))];
    const thead = columns.map(c => `<th>${esc(c)}</th>`).join('');
    const tbody = rows.map(r => `<tr>${columns.map(c => `<td>${cell(r[c])}</td>`).join('')}</tr>`).join('');

    return `<div class="table-scroll"><table class="response-table"><thead><tr>${thead}</tr></thead><tbody>${tbody}</tbody></table></div>`;
  },

  /** Filtra as linhas visíveis da tabela pelo termo pesquisado (case-insensitive). */
  filterTable(term) {
    const t = term.trim().toLowerCase();
    document.querySelectorAll('#resTable .response-table tbody tr').forEach(tr => {
      tr.classList.toggle('hidden', !(!t || tr.textContent.toLowerCase().includes(t)));
    });
  },

  // ==================== Aba "Ficha" — tela estruturada ====================
  // Em vez de JSON cru ou grade de planilha, mostra os dados como cartões de
  // registro: título + selo de código em destaque, campos com rótulo
  // legível e valor formatado (moeda/data/percentual) — como uma tela de
  // sistema de verdade.

  /** Dicionário dos campos mais comuns nos endpoints da TGA → rótulo legível. */
  FIELD_LABELS: {
    NOME: 'Nome', NOMEFANTASIA: 'Nome Fantasia', NOMECONSUMIDOR: 'Nome do Consumidor',
    CODCFO: 'Código do Cliente', CGCCFO: 'CPF/CNPJ', PESSOAFISJUR: 'Pessoa Física/Jurídica',
    TELEFONE: 'Telefone', TELEFONE1: 'Telefone', TELEFONE2: 'Telefone 2', FAX: 'Fax',
    EMAIL: 'E-mail', CONTATO: 'Contato',
    RUA: 'Endereço', NUMERO: 'Número', BAIRRO: 'Bairro', CIDADE: 'Cidade', CEP: 'CEP',
    COMPLEMENTO: 'Complemento', CODETD: 'UF', CI_NUMERO: 'RG', CI_ORGAO: 'Órgão Emissor',
    CI_UF: 'UF do RG', DTAEMISAORG: 'Data de Emissão do RG',
    INDICADORIE: 'Indicador IE', INSCRESTADUAL: 'Inscrição Estadual',
    CODVEN: 'Vendedor', CODRPR: 'Representante', CARGO: 'Cargo',
    COMISSAO1: 'Comissão 1', COMISSAO2: 'Comissão 2',
    CODEMPRESA: 'Empresa', CODFILIAL: 'Filial',
    CODPRD: 'Código do Produto', MODELO: 'Modelo', ANO: 'Ano', COR: 'Cor',
    NUMEROSERIE: 'Número de Série', CODAUXILIAR: 'Código Auxiliar', PESO: 'Peso',
    CODTMV: 'Tipo de Movimento', CODCPG: 'Condição de Pagamento', CODCONDPGTO: 'Condição de Pagamento',
    CODPORTADOR: 'Portador', CODTABPRECO: 'Tabela de Preço',
    IDMOV: 'ID do Movimento', IDMOVMOBILE: 'ID Mobile', ID_PROP: 'Propriedade',
    DATAEMISSAO: 'Data de Emissão', DATACRIACAO: 'Data de Criação', DATA: 'Data',
    OBSERVACAO: 'Observação', OBS: 'Observação', STATUS: 'Status', DESCRICAO: 'Descrição',
    COSTATUS: 'Código do Status', STATUSOS: 'Status',
    QUANTIDADE: 'Quantidade', PRECOUNITARIO: 'Preço Unitário', PRECOTABELA: 'Preço de Tabela',
    TOTALITEM: 'Total do Item', CODUND: 'Unidade',
    VALORBRUTO: 'Valor Bruto', VALORLIQUIDO: 'Valor Líquido', VALORDESC: 'Valor de Desconto',
    VALORFRETE: 'Valor do Frete', VALORDESP: 'Valor da Despesa', VALORSEGURO: 'Valor do Seguro',
    VALORSEMPROMOCAO: 'Valor sem Promoção', PERCENTUALDESC: 'Percentual de Desconto',
    PERCENTUALFRETE: 'Percentual de Frete', PERCENTUALDESP: 'Percentual de Despesa',
    PERCENTUALSEGURO: 'Percentual de Seguro', DESCONTOMAXIMO: 'Desconto Máximo',
    AVISTA: 'À Vista', PRAZO1: 'Prazo', QUANTASVEZES1: 'Quantidade de Parcelas',
    PERIODOEMDIAS1: 'Período (dias)', CONTAGEMDIAS1: 'Contagem de Dias', ACRESC_DESC: 'Acréscimo/Desconto',
    LATITUDE: 'Latitude', LONGITUDE: 'Longitude',
    // Envelope padrão de sucesso/erro da API usa chaves minúsculas — convenção
    // diferente dos campos do ERP (sempre maiúsculos), por isso ficam à parte aqui.
    ok: 'OK', error: 'Erro', error_code: 'Código do Erro', reason: 'Motivo',
    status: 'Status', message: 'Mensagem', code: 'Código',
  },

  /** Converte NOMEDOCAMPO em algo legível — usa o dicionário acima, com um fallback genérico. */
  humanizeLabel(key) {
    if (this.FIELD_LABELS[key]) return this.FIELD_LABELS[key];
    const spaced = key.replace(/_/g, ' ').replace(/([A-Za-z]+?)(\d+)$/, '$1 $2');
    return spaced.charAt(0).toUpperCase() + spaced.slice(1).toLowerCase();
  },

  /** Formata o valor conforme uma heurística pelo nome do campo (moeda/percentual/data/número). Retorna null pra valores vazios (não exibidos). */
  formatFieldValue(key, value) {
    if (value === null || value === undefined || value === '') return null;
    if (typeof value === 'object') return JSON.stringify(value);
    if (typeof value === 'boolean') return value ? 'Sim' : 'Não';
    if (typeof value === 'number') {
      if (/^VALOR/.test(key)) return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
      if (/^PERCENTUAL/.test(key)) return `${value}%`;
      return value.toLocaleString('pt-BR');
    }
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value)) {
      const d = new Date(value);
      if (!isNaN(d.getTime())) return d.toLocaleDateString('pt-BR');
    }
    return String(value);
  },

  /** Decide qual campo vira o título do cartão (o mais "identificador" do registro). */
  pickTitleKey(keys, record) {
    const priority = ['NOME', 'NOMEFANTASIA', 'NOMECONSUMIDOR', 'DESCRICAO', 'STATUS', 'STATUSOS'];
    for (const k of priority) if (keys.includes(k) && record[k]) return k;
    return keys.find(k => typeof record[k] === 'string' && record[k].trim() !== '') || null;
  },

  /** Decide qual campo vira o selo (badge) ao lado do título — geralmente um código. */
  pickBadgeKey(keys, record, titleKey) {
    const priority = ['CODCFO', 'CGCCFO', 'CODVEN', 'CODPRD', 'CODCONDPGTO', 'CODTMV', 'IDMOV', 'CODEMPRESA', 'COSTATUS', 'code'];
    for (const k of priority) {
      if (k !== titleKey && keys.includes(k) && record[k] !== undefined && record[k] !== null && record[k] !== '') return k;
    }
    return null;
  },

  /** Descobre a "coisa certa" a mostrar como ficha: uma lista de registros, ou um único objeto. */
  extractRecordData(body) {
    const rows = this.extractTableRows(body);
    if (rows) return { type: 'list', rows };
    if (body && typeof body === 'object') {
      if (body.data && typeof body.data === 'object' && !Array.isArray(body.data)) {
        return { type: 'single', record: body.data };
      }
      return { type: 'single', record: body };
    }
    return null;
  },

  buildRecordCard(record, opts = {}) {
    if (!record || typeof record !== 'object') return '';
    const esc = (v) => String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const keys = Object.keys(record);
    const titleKey = this.pickTitleKey(keys, record);
    const badgeKey = this.pickBadgeKey(keys, record, titleKey);

    const title = titleKey ? esc(record[titleKey]) : 'Registro';
    const badge = badgeKey ? esc(record[badgeKey]) : '';

    const fieldsHtml = keys
      .filter(k => k !== titleKey && k !== badgeKey)
      .map(k => {
        const formatted = this.formatFieldValue(k, record[k]);
        if (formatted === null) return '';
        return `<div class="record-field"><span class="record-field__label">${esc(this.humanizeLabel(k))}</span><span class="record-field__value">${esc(formatted)}</span></div>`;
      })
      .filter(Boolean)
      .join('');

    return `
      <div class="record-card${opts.large ? ' record-card--large' : ''}">
        <div class="record-card__header">
          <span class="record-card__title">${title || '—'}</span>
          ${badge ? `<span class="record-card__badge">${badge}</span>` : ''}
        </div>
        <div class="record-card__fields">${fieldsHtml || '<p class="muted small">Sem outros campos.</p>'}</div>
      </div>
    `;
  },

  buildFichaHtml(body) {
    const data = this.extractRecordData(body);
    if (!data) return '<p class="muted small">Não foi possível montar uma tela estruturada para esta resposta — use a aba JSON.</p>';

    if (data.type === 'list') {
      if (data.rows.length === 0) return '<p class="muted small">Nenhum registro para exibir.</p>';
      if (typeof data.rows[0] !== 'object' || data.rows[0] === null) {
        return '<p class="muted small">Os itens desta lista não são objetos — use a aba JSON.</p>';
      }
      return `<div class="record-grid">${data.rows.map(r => this.buildRecordCard(r)).join('')}</div>`;
    }

    if (typeof data.record !== 'object' || data.record === null || Object.keys(data.record).length === 0) {
      return '<p class="muted small">Não foi possível montar uma tela estruturada para esta resposta — use a aba JSON.</p>';
    }
    return `<div class="record-grid record-grid--single">${this.buildRecordCard(data.record, { large: true })}</div>`;
  },

  /** Filtra os cartões visíveis da Ficha pelo termo pesquisado (case-insensitive). */
  filterCards(term) {
    const t = term.trim().toLowerCase();
    document.querySelectorAll('#resTable .record-card').forEach(card => {
      card.classList.toggle('hidden', !(!t || card.textContent.toLowerCase().includes(t)));
    });
  },

  /** Monta um CSV (separado por vírgula, RFC 4180) a partir das mesmas linhas usadas na aba Tabela. */
  buildCsv(rows) {
    const columns = [...new Set(rows.flatMap(r => (r && typeof r === 'object') ? Object.keys(r) : []))];

    const escapeCsv = (v) => {
      if (v === undefined || v === null) return '';
      const s = typeof v === 'object' ? JSON.stringify(v) : String(v);
      return /[",\r\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
    };

    const headerLine = columns.map(escapeCsv).join(',');
    const dataLines = rows.map(r => columns.map(c => escapeCsv(r[c])).join(','));
    return [headerLine, ...dataLines].join('\r\n');
  },

  /** Dispara o download do CSV pelo navegador (Blob + link temporário). */
  downloadCsv(filename, csvContent) {
    // BOM no início ajuda o Excel a detectar UTF-8 e mostrar acentos certos.
    const blob = new Blob(['﻿' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  },

  /** Deriva um nome de arquivo a partir do path da última requisição (ex.: "regioes"). */
  currentRequestSegment() {
    if (!this.lastRequest || !this.lastRequest.url) return 'resposta';
    try {
      const path = new URL(this.lastRequest.url).pathname;
      return path.split('/').filter(Boolean).pop() || 'resposta';
    } catch (e) {
      return 'resposta';
    }
  },

  exportTableCsv() {
    const rows = this.extractTableRows(this.lastResponseBody);
    if (!rows || rows.length === 0) return;
    const csv = this.buildCsv(rows);
    const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
    this.downloadCsv(`tga-${this.currentRequestSegment()}-${stamp}.csv`, csv);
  },

  /** Traduz erros comuns em explicações compreensíveis, com sugestão de ação. */
  renderErrorInterpretation(response, networkError) {
    const box = document.getElementById('errorInterpretation');

    if (networkError) {
      box.className = 'error-box';
      box.innerHTML = `
        <h4>Falha de comunicação</h4>
        <p>Não foi possível alcançar a API. Possíveis causas:</p>
        <ul>
          <li>Sem conexão com a internet;</li>
          <li>A API está indisponível no momento;</li>
          <li>Bloqueio de CORS ou firewall/proxy local.</li>
        </ul>
        <strong>Sugestão:</strong> verifique sua conexão e tente novamente.
      `;
      return;
    }

    if (!response || response.status < 400) {
      box.classList.add('hidden');
      box.innerHTML = '';
      return;
    }

    const status = response.status;
    const errorCode = response.body && response.body.error_code;
    const message = (response.body && response.body.message) || '';

    let title = `${status} — Erro`;
    let reasons = [];
    let suggestion = 'Revise a requisição e tente novamente.';

    if (status === 401) {
      title = '401 — Não autorizado';
      reasons = ['token ausente', 'token expirado', 'token inválido', 'usuário e/ou senha incorretos (na rota de login)'];
      suggestion = 'Faça uma nova autenticação.';
    } else if (status === 403) {
      title = '403 — Acesso negado';
      reasons = ['o usuário autenticado não tem permissão para este recurso'];
      suggestion = 'Verifique as permissões do usuário da API.';
    } else if (status === 404) {
      title = '404 — Não encontrado';
      reasons = ['endpoint ou registro inexistente', 'ID informado não existe'];
      suggestion = 'Confira o caminho da URL e os parâmetros informados.';
    } else if (status === 429) {
      title = '429 — Limite de requisições excedido';
      reasons = ['muitas requisições em um curto intervalo (rate limit)'];
      suggestion = 'Aguarde alguns instantes antes de tentar novamente.';
    } else if (status >= 500) {
      title = `${status} — Erro do servidor`;
      reasons = ['instabilidade ou indisponibilidade temporária da API TGA'];
      suggestion = 'Tente novamente em alguns minutos. Se persistir, contate o suporte TGA.';
    } else if (status >= 400) {
      title = `${status} — Erro do cliente`;
      reasons = ['dados enviados em formato inválido', 'parâmetro obrigatório ausente'];
      suggestion = 'Revise os dados enviados no corpo ou nos parâmetros da requisição.';
    }

    box.className = 'error-box';
    box.innerHTML = `
      <h4>${title}</h4>
      <p>Possíveis motivos:</p>
      <ul>${reasons.map(r => `<li>${r}</li>`).join('')}</ul>
      ${message ? `<p><em>Mensagem da API${errorCode ? ` (${errorCode})` : ''}: "${message}"</em></p>` : ''}
      <strong>Sugestão:</strong> ${suggestion}
    `;
  },

  addHistoryEntry({ time, method, path, status, durationMs, full }) {
    this.history.unshift({ time, method, path, status, durationMs, full });
    this.renderHistory();
  },

  renderHistory() {
    const tbody = document.getElementById('historyBody');
    if (this.history.length === 0) {
      tbody.innerHTML = '<tr class="history-empty"><td colspan="5">Nenhuma requisição realizada ainda.</td></tr>';
      return;
    }

    tbody.innerHTML = this.history.map((entry, idx) => `
      <tr data-idx="${idx}">
        <td>${entry.time}</td>
        <td>${entry.method}</td>
        <td><code>${entry.path}</code></td>
        <td><span class="badge ${entry.status ? this.statusBadgeClass(entry.status) : 'badge--danger'}">${entry.status || 'erro'}</span></td>
        <td>${entry.durationMs} ms</td>
      </tr>
    `).join('');

    tbody.querySelectorAll('tr[data-idx]').forEach(row => {
      row.addEventListener('click', () => {
        const entry = this.history[Number(row.dataset.idx)];
        if (entry && entry.full) {
          this.renderRequest(entry.full.request);
          this.renderResponse(entry.full);
        }
      });
    });
  },

  setSessionAuthenticated(token) {
    document.getElementById('sessionStatus').textContent = 'Autenticado';
    document.getElementById('sessionStatus').className = 'badge badge--ok';
    document.getElementById('tokenDisplay').textContent = this.maskToken(token);
    document.getElementById('tokenDisplay').dataset.full = token;
    document.getElementById('tokenDisplay').dataset.revealed = 'false';
    ['btnShowToken', 'btnCopyToken', 'btnClearSession'].forEach(id => {
      document.getElementById(id).disabled = false;
    });
    document.getElementById('btnReauth').classList.add('hidden');

    // JWT é um detalhe de implementação da API — se não for reconhecível,
    // getExpiration() devolve null e a linha de expiração fica escondida.
    this.tokenExpiryDate = TgaJwt.getExpiration(token);
    this.renderTokenExpiry();

    if (window.TgaExplorer) TgaExplorer.refreshAuthState();
  },

  setSessionExpired() {
    document.getElementById('sessionStatus').textContent = 'Sessão expirada';
    document.getElementById('sessionStatus').className = 'badge badge--danger';
    document.getElementById('tokenDisplay').textContent = '— token expirado —';
    delete document.getElementById('tokenDisplay').dataset.full;
    ['btnShowToken', 'btnCopyToken'].forEach(id => {
      document.getElementById(id).disabled = true;
    });
    document.getElementById('btnReauth').classList.remove('hidden');

    this.tokenExpiryDate = null;
    document.getElementById('tokenExpiry').classList.add('hidden');

    if (window.TgaExplorer) TgaExplorer.refreshAuthState();
  },

  setSessionCleared() {
    document.getElementById('sessionStatus').textContent = 'Não autenticado';
    document.getElementById('sessionStatus').className = 'badge badge--warn';
    document.getElementById('tokenDisplay').textContent = '— nenhum token —';
    delete document.getElementById('tokenDisplay').dataset.full;
    ['btnShowToken', 'btnCopyToken', 'btnClearSession'].forEach(id => {
      document.getElementById(id).disabled = true;
    });
    document.getElementById('btnReauth').classList.add('hidden');

    this.tokenExpiryDate = null;
    document.getElementById('tokenExpiry').classList.add('hidden');

    if (window.TgaExplorer) TgaExplorer.refreshAuthState();
  },

  /** Atualiza o texto "Expira em..."; chamado no login e periodicamente pelo app.js. */
  renderTokenExpiry() {
    const el = document.getElementById('tokenExpiry');
    if (!this.tokenExpiryDate) {
      el.classList.add('hidden');
      return;
    }

    const diffMs = this.tokenExpiryDate.getTime() - Date.now();
    el.classList.remove('hidden');

    if (diffMs <= 0) {
      el.textContent = 'Token expirado.';
      return;
    }

    el.textContent = `Expira em ${this.formatDuration(diffMs)} (${this.tokenExpiryDate.toLocaleString('pt-BR')})`;
  },

  /** Verificação proativa: se o token já passou do `exp`, marca a sessão como expirada sem esperar um 401. */
  checkTokenExpiry() {
    if (this.tokenExpiryDate && Date.now() >= this.tokenExpiryDate.getTime()) {
      TgaStorage.clearToken();
      this.setSessionExpired();
      return;
    }
    this.renderTokenExpiry();
  },

  formatDuration(ms) {
    const totalMinutes = Math.round(ms / 60000);
    if (totalMinutes < 1) return 'menos de 1 minuto';
    if (totalMinutes < 60) return `${totalMinutes} min`;

    const totalHours = Math.floor(totalMinutes / 60);
    const remMinutes = totalMinutes % 60;
    if (totalHours < 24) return `${totalHours}h${remMinutes ? ` ${remMinutes}min` : ''}`;

    const days = Math.floor(totalHours / 24);
    const remHours = totalHours % 24;
    return `${days} dia${days > 1 ? 's' : ''}${remHours ? ` e ${remHours}h` : ''}`;
  },

  showLoginFeedback(message, type) {
    const el = document.getElementById('loginFeedback');
    el.textContent = message;
    el.className = `feedback feedback--${type}`;
  },

  setConnStatus(online) {
    const el = document.getElementById('connStatus');
    if (online) {
      el.textContent = 'API Online';
      el.className = 'badge badge--ok';
    } else {
      el.textContent = 'Falha de comunicação';
      el.className = 'badge badge--danger';
    }
  },
};
