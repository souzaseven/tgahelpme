/**
 * explorer.js
 * Fase 4 — componente genérico: monta o card de qualquer endpoint listado em
 * TGA_ENDPOINTS (js/endpoints.js) a partir de configuração, sem HTML
 * específico por endpoint. Reaproveita TgaApi/TgaUi/TgaStorage já existentes
 * (nada de duplicar a lógica de request/response/erro criada nas Fases 1–3).
 */

const TgaExplorer = {
  current: null,

  init() {
    this.renderSidebarList();
    this.selectEndpoint(TGA_ENDPOINTS[0].id);
  },

  // ---------------- Sidebar ----------------

  /**
   * Monta um grupo (com título e contador) por categoria encontrada em
   * TGA_ENDPOINTS — hoje "Misc" e "Configurações". Adicionar uma nova
   * categoria é só usar um `category`/`categoryLabel` novo em
   * js/endpoints.js; nenhum HTML precisa mudar aqui.
   */
  renderSidebarList() {
    const container = document.getElementById('endpointGroups');
    const categories = [...new Set(TGA_ENDPOINTS.map(ep => ep.category))];

    container.innerHTML = categories.map(cat => {
      const eps = TGA_ENDPOINTS.filter(ep => ep.category === cat);
      const items = eps.map(ep => `
        <li class="sidebar__item sidebar__item--sub" data-endpoint-id="${ep.id}">
          <span class="method-tag method-tag--${ep.method.toLowerCase()}">${ep.method}</span>
          <span>${ep.menuLabel}</span>
        </li>
      `).join('');

      return `
        <div class="sidebar__group">
          <h2>${eps[0].categoryLabel} <span class="count">${eps.length}</span></h2>
          <ul>${items}</ul>
        </div>
      `;
    }).join('');

    container.querySelectorAll('li[data-endpoint-id]').forEach(li => {
      // scroll: true só no clique do usuário — a seleção inicial (init())
      // não deve fazer a página pular assim que carrega.
      li.addEventListener('click', () => this.selectEndpoint(li.dataset.endpointId, { scroll: true }));
    });
  },

  highlightSidebar(id) {
    document.querySelectorAll('#endpointGroups li').forEach(li => {
      li.classList.toggle('sidebar__item--active', li.dataset.endpointId === id);
    });
  },

  // ---------------- Card ----------------

  selectEndpoint(id, { scroll = false } = {}) {
    const ep = TGA_ENDPOINTS.find(e => e.id === id);
    if (!ep) return;
    this.current = ep;
    this.highlightSidebar(id);
    this.renderCard();

    if (scroll) {
      document.getElementById('endpointCardContainer')
        .scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  },

  renderCard() {
    const ep = this.current;
    const container = document.getElementById('endpointCardContainer');
    const pathParams = ep.pathParams || [];

    const pathParamsHtml = pathParams.length === 0 ? '' : `
      <span class="fieldset-label">Parâmetros da URL</span>
      <div class="form-grid">${pathParams.map(p => this.renderPathField(p)).join('')}</div>
    `;

    const paramsHtml = ep.params.length === 0
      ? ''
      : `${pathParams.length ? '<span class="fieldset-label">Parâmetros de consulta</span>' : ''}
         <div class="form-grid">${ep.params.filter(p => !p.wide).map(p => this.renderField(p)).join('')}</div>
         ${ep.params.filter(p => p.wide).map(p => this.renderField(p)).join('')}`;

    const bodyHtml = ep.body === undefined ? '' : `
      <div class="form__field form__field--wide">
        <label for="epBody">Corpo da requisição (JSON) — edite antes de executar</label>
        <textarea id="epBody" class="body-input" spellcheck="false" rows="10">${this.escapeHtml(JSON.stringify(ep.body, null, 2))}</textarea>
      </div>
    `;

    container.innerHTML = `
      <section class="panel" id="endpointCard">
        <div class="panel__header">
          <div class="endpoint-title">
            <span class="method-badge method-badge--${ep.method.toLowerCase()}">${ep.method}</span>
            <code>/v1${ep.path}</code>
          </div>
          <div class="panel__header-badges">
            ${ep.method !== 'GET' ? '<span class="badge badge--warn">⚠ Grava dados reais</span>' : ''}
            <span class="badge badge--neutral">${ep.requiresAuth ? 'Requer token' : 'Público'}</span>
          </div>
        </div>
        <div class="panel__body">
          <h3>${ep.title}</h3>
          <p class="muted">${ep.description}</p>
          ${ep.method !== 'GET' ? `
            <div class="warning-note">
              Este endpoint <strong>${ep.method}</strong> altera dados reais no TGA ERP ao ser executado
              (cadastra, atualiza ou apaga um registro de verdade) — não é uma simulação. Confira o corpo/parâmetros
              com atenção antes de clicar em Executar.
            </div>
          ` : ''}

          <form id="epForm" autocomplete="off">
            ${pathParamsHtml}
            ${paramsHtml}
            ${bodyHtml}
            <div class="form__actions">
              <button type="submit" id="epExecBtn" class="btn btn--primary">Executar</button>
              <span id="epMeta" class="muted small"></span>
            </div>
          </form>
        </div>
      </section>
    `;

    document.getElementById('epForm').addEventListener('submit', (e) => {
      e.preventDefault();
      this.run();
    });

    this.refreshAuthState();
  },

  renderField(p) {
    const id = `epParam__${p.name}`;
    let control;
    if (p.type === 'select') {
      control = `<select id="${id}">
        ${p.options.map(o => `<option value="${o.value}">${o.label}</option>`).join('')}
      </select>`;
    } else {
      const extra = p.type === 'number' ? `min="${p.min || 1}"` : '';
      control = `<input type="${p.type}" id="${id}" placeholder="${p.placeholder || ''}" ${extra}
        ${p.default !== undefined ? `value="${p.default}"` : ''}>`;
    }
    return `<div class="form__field${p.wide ? ' form__field--wide' : ''}">
      <label for="${id}">${p.label}</label>
      ${control}
    </div>`;
  },

  /** Parâmetros que fazem parte do caminho da URL (ex.: {codcfo}) — sempre obrigatórios. */
  renderPathField(p) {
    const id = `epPath__${p.name}`;
    return `<div class="form__field">
      <label for="${id}">${p.label} <span class="required-mark" title="Obrigatório">*</span></label>
      <input type="text" id="${id}" placeholder="${p.placeholder || ''}" required>
    </div>`;
  },

  escapeHtml(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  },

  // ---------------- Execução ----------------

  /** Aplica/remove o estado visual de "carregando" nos campos do form (não no botão). */
  setFormLoading(loading) {
    const form = document.getElementById('epForm');
    if (!form) return;
    form.classList.toggle('is-loading', loading);
    form.querySelectorAll('input, select, textarea').forEach(el => { el.disabled = loading; });
  },

  showFieldError(message) {
    const meta = document.getElementById('epMeta');
    meta.textContent = message;
    meta.className = 'field-error small';
  },

  /** Substitui os tokens {nome} no path pelos valores preenchidos. Retorna null se algum obrigatório estiver vazio. */
  resolvePath(ep) {
    let path = ep.path;
    for (const p of (ep.pathParams || [])) {
      const el = document.getElementById(`epPath__${p.name}`);
      const value = el.value.trim();
      if (!value) return { error: `Preencha "${p.label}" antes de executar.` };
      path = path.replace(`{${p.name}}`, encodeURIComponent(value));
    }
    return { path };
  },

  async run() {
    const ep = this.current;
    const btn = document.getElementById('epExecBtn');
    const meta = document.getElementById('epMeta');
    meta.className = 'muted small';

    if (ep.requiresAuth && !TgaStorage.hasToken()) return;

    // Parâmetros da URL são obrigatórios — nunca disparar a requisição com
    // um {token} literal na URL por falta de preenchimento.
    const resolved = this.resolvePath(ep);
    if (resolved.error) {
      this.showFieldError(resolved.error);
      return;
    }

    // Corpo (POST/PUT/DELETE com body): precisa ser JSON válido antes de
    // sequer tentar enviar — nunca mandar texto quebrado pra API.
    let body;
    if (ep.body !== undefined) {
      const bodyText = document.getElementById('epBody').value;
      try {
        body = JSON.parse(bodyText);
      } catch (err) {
        this.showFieldError(`JSON inválido no corpo da requisição: ${err.message}`);
        return;
      }
    }

    btn.disabled = true;
    btn.textContent = 'Executando…';
    this.setFormLoading(true);

    const query = new URLSearchParams();
    ep.params.forEach(p => {
      const el = document.getElementById(`epParam__${p.name}`);
      const value = el.value.trim();
      if (value !== '') query.set(p.name, value);
    });

    const qs = query.toString();
    const path = `${resolved.path}${qs ? `?${qs}` : ''}`;
    const time = new Date().toLocaleTimeString('pt-BR');
    const t0 = performance.now();

    const result = await TgaApi.request({
      method: ep.method,
      path,
      body,
      auth: ep.requiresAuth,
    });

    const elapsed = Math.round(performance.now() - t0);

    TgaUi.renderRequest(result.request);
    TgaUi.renderResponse(result);

    TgaUi.addHistoryEntry({
      time,
      method: ep.method,
      path: resolved.path,
      status: result.response ? result.response.status : null,
      durationMs: result.durationMs,
      full: result,
    });

    // Campos voltam ao normal assim que a resposta chega, em qualquer caso.
    this.setFormLoading(false);
    btn.textContent = 'Executar';

    // Sessão expirada/token inválido: nunca insistir silenciosamente.
    if (result.response && result.response.status === 401 && ep.requiresAuth) {
      TgaStorage.clearToken();
      TgaUi.setSessionExpired(); // refreshAuthState() já roda daqui e cuida do botão
      return;
    }

    meta.textContent = `Última execução às ${new Date().toLocaleTimeString('pt-BR')} (${elapsed} ms)`;
    btn.disabled = false;
  },

  // ---------------- Estado de autenticação ----------------

  /** Chamado pela Sessão da API sempre que o estado de login muda. */
  refreshAuthState() {
    const ep = this.current;
    const btn = document.getElementById('epExecBtn');
    const meta = document.getElementById('epMeta');
    if (!ep || !btn) return;

    if (!ep.requiresAuth) {
      btn.disabled = false;
      return;
    }

    const authenticated = TgaStorage.hasToken();
    btn.disabled = !authenticated;

    if (authenticated) {
      meta.textContent = '';
    } else {
      const statusText = document.getElementById('sessionStatus').textContent;
      meta.textContent = statusText === 'Sessão expirada'
        ? 'Sessão expirada — autentique novamente'
        : 'Faça login para habilitar';
    }
  },
};
