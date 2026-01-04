/**
 * ===================================================
 * APP FRONTEND — PAINEL DE AGENTES EVOLUX
 * ===================================================
 * Integração:
 * - GET  backend/listar_agentes.php
 * - POST backend/deslogar_agente.php (FormData: agent_id)
 * Recursos:
 * - Busca por nome/login
 * - Filtro Online/Offline
 * - Cards por fila (clique filtra)
 * - Agrupar por fila (checkbox)
 * - Paginação client-side
 * ===================================================
 */

document.addEventListener("DOMContentLoaded", () => {

  // ===============================
  // ESTADO
  // ===============================
  let agentes = [];            // lista completa vinda do backend
  let agentesFiltrados = [];   // lista após filtros
  let page = 1;
  const perPage = 20;

  let filtroFilaAtivo = "all"; // quando clicar em card, vira o nome da fila

  // ===============================
  // ELEMENTOS
  // ===============================
  const lista        = document.getElementById("listaAgentes");
  const paginaEl     = document.getElementById("pagina");

  const btnPrev      = document.getElementById("prev");
  const btnNext      = document.getElementById("next");

  const filtroStatus = document.getElementById("filtroStatus");
  const buscaNome    = document.getElementById("buscaNome");
  const agruparFila  = document.getElementById("agruparFila");

  const cardsFilas   = document.getElementById("cardsFilas");

  // ===============================
  // HELPERS
  // ===============================
  function escapeHtml(str) {
    return String(str ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function getFilaNome(agent) {
    return agent?.current_outbound_queue?.name || "Sem fila";
  }

  function isOnline(agent) {
    // pela doc: last_login.time_logoff null = ainda logado
    return !!(agent?.last_login && agent.last_login.time_logoff === null);
  }

  function statusLabel(agent) {
    return isOnline(agent) ? "Online" : "Offline";
  }

  function statusClass(agent) {
    return isOnline(agent) ? "status online" : "status offline";
  }

  function enableLabel(agent) {
    // enable true/false é atributo do agente (habilitado no sistema)
    const enabled = agent?.enable === true;
    const archived = agent?.archived === true;
    if (archived) return `<span class="tag tag-archived">Arquivado</span>`;
    return enabled
      ? `<span class="tag tag-enabled">Habilitado</span>`
      : `<span class="tag tag-disabled">Desabilitado</span>`;
  }

  function setLoading(on) {
    if (on) lista.classList.add("loading");
    else lista.classList.remove("loading");
  }

  // ===============================
  // BACKEND CALLS
  // ===============================
  async function fetchAgentes() {
    setLoading(true);
    lista.innerHTML = `<div class="loading-box">Carregando agentes...</div>`;

    try {
      const res = await fetch("backend/listar_agentes.php", { cache: "no-store" });
      const json = await res.json();

      if (!json || !Array.isArray(json.data)) {
        console.error("Resposta inesperada:", json);
        lista.innerHTML = `<div class="erro">Erro ao carregar agentes.</div>`;
        setLoading(false);
        return;
      }

      agentes = json.data;
      page = 1;

      renderCardsFilas();
      aplicarFiltrosERender();

    } catch (e) {
      console.error(e);
      lista.innerHTML = `<div class="erro">Erro inesperado ao carregar agentes.</div>`;
    } finally {
      setLoading(false);
    }
  }

  async function deslogarAgente(agentId) {
    const ok = confirm(`Deseja realmente deslogar o agente #${agentId}?`);
    if (!ok) return;

    try {
      const fd = new FormData();
      fd.append("agent_id", agentId);

      const res = await fetch("backend/deslogar_agente.php", {
        method: "POST",
        body: fd
      });

      const json = await res.json();

      if (json && json.success) {
        alert("Agente deslogado com sucesso!");
        await fetchAgentes();
      } else {
        console.error("Falha no deslogar:", json);
        alert("Falha ao deslogar agente. Veja o console.");
      }

    } catch (e) {
      console.error(e);
      alert("Erro inesperado ao deslogar. Veja o console.");
    }
  }

  // expõe para uso no onclick do HTML renderizado
  window.deslogarAgente = deslogarAgente;

  // ===============================
  // FILTROS
  // ===============================
  function aplicarFiltros() {
    const st = filtroStatus.value; // all | active | inactive
    const q = (buscaNome.value || "").trim().toLowerCase();

    agentesFiltrados = agentes.filter(a => {
      // filtro por fila clicada (cards)
      if (filtroFilaAtivo !== "all") {
        if (getFilaNome(a) !== filtroFilaAtivo) return false;
      }

      // filtro online/offline
      if (st === "active" && !isOnline(a)) return false;
      if (st === "inactive" && isOnline(a)) return false;

      // busca
      if (q) {
        const name = (a?.name || "").toLowerCase();
        const login = (a?.login || "").toLowerCase();
        if (!name.includes(q) && !login.includes(q)) return false;
      }

      return true;
    });
  }

  function aplicarFiltrosERender() {
    aplicarFiltros();
    renderLista();
    renderPaginacao();
  }

  // ===============================
  // CARDS DE FILA
  // ===============================
  function renderCardsFilas() {
    // conta por fila
    const map = new Map();
    for (const a of agentes) {
      const fila = getFilaNome(a);
      const online = isOnline(a);
      if (!map.has(fila)) map.set(fila, { total: 0, online: 0, offline: 0 });
      const item = map.get(fila);
      item.total++;
      if (online) item.online++;
      else item.offline++;
    }

    // transforma em array e ordena por total desc
    const filas = Array.from(map.entries())
      .map(([nome, info]) => ({ nome, ...info }))
      .sort((a, b) => b.total - a.total);

    // adiciona card "Todas"
    const totalAll = agentes.length;
    const onAll = agentes.filter(isOnline).length;
    const offAll = totalAll - onAll;

    const cardAll = `
      <div class="card-fila ${filtroFilaAtivo === "all" ? "active" : ""}" data-fila="all">
        <div class="fila-titulo">Todas as filas</div>
        <div class="fila-numeros">
          <span class="n-total">${totalAll} total</span>
          <span class="n-online">${onAll} online</span>
          <span class="n-offline">${offAll} offline</span>
        </div>
      </div>
    `;

    const cards = filas.map(f => `
      <div class="card-fila ${filtroFilaAtivo === f.nome ? "active" : ""}" data-fila="${escapeHtml(f.nome)}">
        <div class="fila-titulo">${escapeHtml(f.nome)}</div>
        <div class="fila-numeros">
          <span class="n-total">${f.total} total</span>
          <span class="n-online">${f.online} online</span>
          <span class="n-offline">${f.offline} offline</span>
        </div>
      </div>
    `).join("");

    cardsFilas.innerHTML = cardAll + cards;

    // clique filtra
    cardsFilas.querySelectorAll(".card-fila").forEach(el => {
      el.addEventListener("click", () => {
        filtroFilaAtivo = el.dataset.fila === "all" ? "all" : el.dataset.fila;
        page = 1;
        renderCardsFilas();
        aplicarFiltrosERender();
      });
    });
  }

  // ===============================
  // LISTAGEM
  // ===============================
  function renderLista() {
    if (!agentesFiltrados.length) {
      lista.innerHTML = `<div class="vazio">Nenhum agente encontrado.</div>`;
      return;
    }

    // slice pagina
    const start = (page - 1) * perPage;
    const end = start + perPage;
    const pageItems = agentesFiltrados.slice(start, end);

    // modo agrupado por fila
    if (agruparFila.checked) {
      const grupos = new Map();
      for (const a of pageItems) {
        const fila = getFilaNome(a);
        if (!grupos.has(fila)) grupos.set(fila, []);
        grupos.get(fila).push(a);
      }

      // ordena filas por nome
      const filasOrdenadas = Array.from(grupos.keys()).sort((a, b) => a.localeCompare(b));

      lista.innerHTML = filasOrdenadas.map(fila => {
        const itens = grupos.get(fila);

        // dentro do grupo, ordena online primeiro e depois nome
        itens.sort((a, b) => {
          const ao = isOnline(a) ? 0 : 1;
          const bo = isOnline(b) ? 0 : 1;
          if (ao !== bo) return ao - bo;
          return (a.name || "").localeCompare(b.name || "");
        });

        return `
          <div class="grupo">
            <div class="grupo-header">
              <div class="grupo-titulo">${escapeHtml(fila)}</div>
              <div class="grupo-sub">
                <span>${itens.length} agentes</span>
                <span class="sep">•</span>
                <span class="ok">${itens.filter(isOnline).length} online</span>
                <span class="sep">•</span>
                <span class="bad">${itens.filter(a => !isOnline(a)).length} offline</span>
              </div>
            </div>

            <div class="grid">
              ${itens.map(renderCardAgente).join("")}
            </div>
          </div>
        `;
      }).join("");

      return;
    }

    // modo normal (sem agrupar)
    lista.innerHTML = `
      <div class="grid">
        ${pageItems.map(renderCardAgente).join("")}
      </div>
    `;
  }

  function renderCardAgente(agent) {
    const fila = getFilaNome(agent);
    const canLogoff = isOnline(agent);

    const ext = agent?.current_extension?.endpoint || agent?.current_extension?.number || "-";
    const exten = agent?.last_login?.exten || "-";

    return `
      <div class="card-agente">
        <div class="topo">
          <div class="nome">
            <span class="id">#${agent.id}</span>
            <span class="txt">${escapeHtml(agent.name)}</span>
          </div>
          <div class="${statusClass(agent)}">${statusLabel(agent)}</div>
        </div>

        <div class="linha">
          <span class="label">Login:</span>
          <span class="valor">${escapeHtml(agent.login)}</span>
        </div>

        <div class="linha">
          <span class="label">Fila:</span>
          <span class="valor">${escapeHtml(fila)}</span>
        </div>

        <div class="linha">
          <span class="label">Ramal/Endpoint:</span>
          <span class="valor">${escapeHtml(ext)} <span class="muted">(último: ${escapeHtml(exten)})</span></span>
        </div>

        <div class="linha tags">
          ${enableLabel(agent)}
          ${agent?.mfa_enabled ? `<span class="tag tag-mfa">MFA</span>` : ``}
        </div>

        <div class="acoes">
          ${
            canLogoff
              ? `<button class="btn danger" onclick="deslogarAgente(${agent.id})">Deslogar</button>`
              : `<button class="btn ghost" disabled>Offline</button>`
          }
        </div>
      </div>
    `;
  }

  // ===============================
  // PAGINAÇÃO
  // ===============================
  function renderPaginacao() {
    const total = agentesFiltrados.length;
    const totalPages = Math.max(1, Math.ceil(total / perPage));

    // trava bounds
    if (page > totalPages) page = totalPages;
    if (page < 1) page = 1;

    paginaEl.textContent = `${page} / ${totalPages} — ${total} itens`;

    btnPrev.disabled = page <= 1;
    btnNext.disabled = page >= totalPages;
  }

  btnPrev.addEventListener("click", () => {
    page--;
    renderLista();
    renderPaginacao();
  });

  btnNext.addEventListener("click", () => {
    page++;
    renderLista();
    renderPaginacao();
  });

  filtroStatus.addEventListener("change", () => {
    page = 1;
    aplicarFiltrosERender();
  });

  buscaNome.addEventListener("input", () => {
    page = 1;
    aplicarFiltrosERender();
  });

  agruparFila.addEventListener("change", () => {
    page = 1;
    renderLista();
    renderPaginacao();
  });

  // ===============================
  // INIT
  // ===============================
  fetchAgentes();

});
