  /* =========================================================
    PDV OFF - Módulo
    Painel Unificado Clientes Web (v3 FINAL)
    Autor: Anderson de Souza
  ========================================================= */

  /* ==========================
    STATE
  ========================== */
  let pdvOffState = {
    page: 1,
    limit: 10,
    q: ''
  };

  /* ==========================
    LOAD
  ========================== */
  async function loadPdvOff() {
    try {
      const view = document.getElementById('view-pdvoff');
      if (!view) return;

      if (!document.getElementById('tblPdvOff')) {
        view.innerHTML = `
          <div class="card">

            <div class="toolbar">
              <div class="search">
                <input id="pdvOffSearch"
                      placeholder="Buscar por código, cliente, servidor">
              </div>

              <div class="filters">
                <button class="btn primary" id="btnNovoPdvOff">
                  ➕ Novo PDV OFF
                </button>
              </div>
            </div>

            <div class="table-wrap">
              <table class="table" id="tblPdvOff">
                <thead>
                  <tr>
                    <th>Código</th>
                    <th>Cliente</th>
                    <th>Servidor</th>
                    <th>Caixas</th>
                    <th>Observação</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>

            <div class="pager">
              <span id="pdvOffInfo" class="muted"></span>
              <div>
                <button class="btn ghost" id="pdvOffPrev">←</button>
                <span class="badge" id="pdvOffPage">1</span>
                <button class="btn ghost" id="pdvOffNext">→</button>
              </div>
            </div>

          </div>
        `;

        bindPdvOffEvents();
      }

      const res = await apiFetch('backend/api_pdvoff.php', {
        method: 'POST',
        body: {
          action: 'list',
          page: pdvOffState.page,
          limit: pdvOffState.limit,
          q: pdvOffState.q
        }
      });

      if (!res.success) {
        throw new Error(res.message || 'Erro ao carregar PDV OFF');
      }

      renderPdvOff(res.rows || []);
      updatePdvOffPager(res);

    } catch (e) {
      console.error(e);
      showToast('Erro ao carregar PDV OFF', 'danger');
    }
  }

  /* ==========================
    EVENTS
  ========================== */
  function bindPdvOffEvents() {
    document.getElementById('btnNovoPdvOff').onclick = novoPdvOff;

    document.getElementById('pdvOffSearch').oninput = e => {
      pdvOffState.q = e.target.value;
      pdvOffState.page = 1;
      loadPdvOff();
    };

    document.getElementById('pdvOffPrev').onclick = () => {
      if (pdvOffState.page > 1) {
        pdvOffState.page--;
        loadPdvOff();
      }
    };

    document.getElementById('pdvOffNext').onclick = () => {
      pdvOffState.page++;
      loadPdvOff();
    };
  }

  /* ==========================
    RENDER
  ========================== */
  function renderPdvOff(rows) {
    const tbody = document.querySelector('#tblPdvOff tbody');
    tbody.innerHTML = '';

    if (!rows.length) {
      tbody.innerHTML =
        `<tr><td colspan="6">Nenhum registro encontrado</td></tr>`;
      return;
    }

    rows.forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${r.cod_cliente}</td>
        <td>${r.cliente}</td>
        <td>${r.acesso_server || '-'}</td>
        <td>${r.caixas}</td>
        <td>${r.observacao || '-'}</td>
        <td>
          <button class="btn ghost" onclick="editarPdvOff(${r.id})">✏️</button>
          <button class="btn ghost" onclick="excluirPdvOff(${r.id})">🗑</button>
        </td>
      `;
      tbody.appendChild(tr);
    });
  }

  /* ==========================
    PAGINAÇÃO
  ========================== */
  function updatePdvOffPager(res) {
    document.getElementById('pdvOffPage').textContent = res.page;
    document.getElementById('pdvOffInfo').textContent =
      `Exibindo ${res.count_page} de ${res.total}`;
  }

  /* ==========================
    NOVO
  ========================== */
  function novoPdvOff() {
    openModal({
      title: 'Novo PDV OFF',
      entity: 'pdvoff',
      fields: [
        { label: 'Código do Cliente', name: 'cod_cliente', required: true },
        { label: 'Cliente', name: 'cliente', required: true },
       { 
  label: 'Servidor', 
  name: 'acesso_server', 
  value: 'AD' 
},

        { label: 'Quantidade de Caixas', name: 'caixas', type: 'number' },
        { label: 'Observação', name: 'observacao', full: true }
      ]
    });

    bindPdvOffSubmit();
  }

  /* ==========================
    EDITAR
  ========================== */
  async function editarPdvOff(id) {
    const res = await apiFetch('backend/api_pdvoff.php', {
      method: 'POST',
      body: { action: 'get', id }
    });

    if (!res.success) {
      showToast('Erro ao abrir registro', 'danger');
      return;
    }

    const r = res.row;

    openModal({
      title: 'Editar PDV OFF',
      entity: 'pdvoff',
      id,
      fields: [
        { label: 'Código do Cliente', name: 'cod_cliente', value: r.cod_cliente, required: true },
        { label: 'Cliente', name: 'cliente', value: r.cliente, required: true },
        { label: 'Servidor', name: 'acesso_server', value: r.acesso_server },
        { label: 'Quantidade de Caixas', name: 'caixas', type: 'number', value: r.caixas },
        { label: 'Observação', name: 'observacao', value: r.observacao, full: true }
      ]
    });

    bindPdvOffSubmit(id);
  }

  /* ==========================
    SUBMIT (CREATE / UPDATE)
  ========================== */
  function bindPdvOffSubmit(id = '') {
    modalForm.onsubmit = null;

    modalForm.onsubmit = async e => {
      e.preventDefault();

      const data = {};
      modalForm.querySelectorAll('input[name]').forEach(i => {
        data[i.name] = i.value;
      });

      try {
        const res = await apiFetch('backend/api_pdvoff.php', {
          method: 'POST',
          body: {
            action: id ? 'update' : 'create',
            id,
            data
          }
        });

        if (!res.success) {
          throw new Error(res.message || 'Erro ao salvar');
        }

        showToast('Registro PDV OFF salvo com sucesso', 'success');
        closeModal();
        loadPdvOff();

      } catch (err) {
        console.error(err);
        showToast(err.message || 'Erro ao salvar', 'danger');
      }
    };
  }

  /* ==========================
    EXCLUIR
  ========================== */
  async function excluirPdvOff(id) {
    if (!confirm('Excluir este registro PDV OFF?')) return;

    await apiFetch('backend/api_pdvoff.php', {
      method: 'POST',
      body: { action: 'delete', id }
    });

    showToast('Registro PDV OFF removido', 'success');
    loadPdvOff();
  }
