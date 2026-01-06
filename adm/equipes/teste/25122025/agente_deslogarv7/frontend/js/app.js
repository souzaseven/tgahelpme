/**
 * ===================================================
 * APP FRONTEND — PAINEL DE AGENTES EVOLUX (OFICIAL)
 * ===================================================
 * Integração:
 * - GET  backend/listar_agentes.php
 * - POST backend/deslogar_agente.php (FormData: agent_id)
 * ===================================================
 */

document.addEventListener("DOMContentLoaded", () => {

  /* ===============================
     CONSTANTES
  =============================== */
  const STORAGE_KEY = "painel_agentes_estado";
  const AUTO_REFRESH_MS = 10000; // 10s

  /* ===============================
     ESTADO
  =============================== */
  let agentes = [];
  let agentesFiltrados = [];
  let snapshotStatus = new Map(); // id -> online/offline
  let page = 1;
  const perPage = 20;
  let filtroFilaAtivo = "all";

  /* ===============================
     ELEMENTOS
  =============================== */
  const lista        = document.getElementById("listaAgentes");
  const paginaEl     = document.getElementById("pagina");
  const btnPrev      = document.getElementById("prev");
  const btnNext      = document.getElementById("next");

  const filtroStatus = document.getElementById("filtroStatus");
  const buscaNome    = document.getElementById("buscaNome");
  const agruparFila  = document.getElementById("agruparFila");
  const mostrarArquivados = document.getElementById("mostrarArquivados");

  const cardsFilas   = document.getElementById("cardsFilas");

  // padrões
  agruparFila.checked = true;
  mostrarArquivados.checked = false;

  /* ===============================
     HELPERS
  =============================== */
  const escapeHtml = s =>
    String(s ?? "")
      .replaceAll("&","&amp;")
      .replaceAll("<","&lt;")
      .replaceAll(">","&gt;")
      .replaceAll('"',"&quot;")
      .replaceAll("'","&#039;");

  const getFilaNome = a => a?.current_outbound_queue?.name || "Sem fila";
  const isOnline = a => !!(a?.last_login && a.last_login.time_logoff === null);

  const statusClass = a => isOnline(a) ? "status online" : "status offline";
  const statusLabel = a => isOnline(a) ? "Online" : "Offline";

  function enableLabel(a) {
    if (a?.archived) return `<span class="tag tag-archived">Arquivado</span>`;
    return a?.enable
      ? `<span class="tag tag-enabled">Habilitado</span>`
      : `<span class="tag tag-disabled">Desabilitado</span>`;
  }

  function criarSnapshotStatus(lista) {
    const map = new Map();
    lista.forEach(a => map.set(a.id, isOnline(a)));
    return map;
  }

  /* ===============================
     LOCAL STORAGE
  =============================== */
  function salvarEstado() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
      filtroStatus: filtroStatus.value,
      buscaNome: buscaNome.value,
      agruparFila: agruparFila.checked,
      mostrarArquivados: mostrarArquivados.checked,
      filtroFilaAtivo
    }));
  }

  function restaurarEstado() {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return;
    try {
      const e = JSON.parse(raw);
      if (e.filtroStatus) filtroStatus.value = e.filtroStatus;
      if (e.buscaNome !== undefined) buscaNome.value = e.buscaNome;
      if (e.agruparFila !== undefined) agruparFila.checked = e.agruparFila;
      if (e.mostrarArquivados !== undefined) mostrarArquivados.checked = e.mostrarArquivados;
      if (e.filtroFilaAtivo) filtroFilaAtivo = e.filtroFilaAtivo;
    } catch {}
  }

  /* ===============================
     BACKEND
  =============================== */
  async function fetchAgentes() {
    lista.innerHTML = `<div class="loading">Carregando agentes...</div>`;
    try {
      const res = await fetch("backend/listar_agentes.php", { cache: "no-store" });
      const json = await res.json();
      agentes = Array.isArray(json.data) ? json.data : [];
      snapshotStatus = criarSnapshotStatus(agentes);
      page = 1;
      renderCardsFilas();
      aplicarFiltrosERender();
    } catch (e) {
      console.error(e);
      lista.innerHTML = `<div class="erro">Erro ao carregar agentes</div>`;
    }
  }

  async function atualizarAgentesSilencioso() {
    try {
      const res = await fetch("backend/listar_agentes.php", { cache: "no-store" });
      const json = await res.json();
      if (!Array.isArray(json.data)) return;

      const novos = json.data;
      const novosStatus = criarSnapshotStatus(novos);
      const mudaram = [];

      novosStatus.forEach((online, id) => {
        if (snapshotStatus.has(id) && snapshotStatus.get(id) !== online) {
          mudaram.push(id);
        }
      });

      if (mudaram.length) {
        agentes = novos;
        snapshotStatus = novosStatus;
        renderCardsFilas();
        aplicarFiltrosERender();
        destacarMudancas(mudaram);
        console.log("🔔 Mudança de status:", mudaram);
      }

    } catch (e) {
      console.warn("Auto-refresh falhou", e);
    }
  }

  window.deslogarAgente = async id => {
    if (!confirm(`Deseja realmente deslogar o agente #${id}?`)) return;
    const fd = new FormData();
    fd.append("agent_id", id);
    const res = await fetch("backend/deslogar_agente.php", { method: "POST", body: fd });
    const json = await res.json();
    if (json?.success) fetchAgentes();
    else alert("Erro ao deslogar agente");
  };

  /* ===============================
     FILTROS
  =============================== */
  function aplicarFiltros() {
    const st = filtroStatus.value;
    const q = buscaNome.value.toLowerCase();

    agentesFiltrados = agentes.filter(a => {
      if (!mostrarArquivados.checked && a.archived) return false;
      if (filtroFilaAtivo !== "all" && getFilaNome(a) !== filtroFilaAtivo) return false;
      if (st === "active" && !isOnline(a)) return false;
      if (st === "inactive" && isOnline(a)) return false;
      if (q) {
        const n = (a.name || "").toLowerCase();
        const l = (a.login || "").toLowerCase();
        if (!n.includes(q) && !l.includes(q)) return false;
      }
      return true;
    });
  }

  function aplicarFiltrosERender() {
    aplicarFiltros();
    renderLista();
    renderPaginacao();
    salvarEstado();
  }

  /* ===============================
     CARDS DE FILA
  =============================== */
  function renderCardsFilas() {
    const map = new Map();

    agentes.forEach(a => {
      const fila = getFilaNome(a);
      if (!map.has(fila)) map.set(fila, { total:0, online:0, offline:0, archived:0 });
      const f = map.get(fila);
      f.total++;
      if (a.archived) f.archived++;
      else if (isOnline(a)) f.online++;
      else f.offline++;
    });

    let html = `
      <div class="card-fila ${filtroFilaAtivo==="all"?"active":""}" data-fila="all">
        <div class="fila-titulo">Todas as filas</div>
        <div class="fila-numeros">
          <span>${agentes.length} total</span>
        </div>
      </div>`;

    map.forEach((f,nome) => {
      html += `
        <div class="card-fila ${filtroFilaAtivo===nome?"active":""}" data-fila="${escapeHtml(nome)}">
          <div class="fila-titulo">${escapeHtml(nome)}</div>
          <div class="fila-numeros">
            <span>${f.total} total</span>
            <span class="n-online">${f.online} online</span>
            <span class="n-offline">${f.offline} offline</span>
            <span class="n-archived">${f.archived} arquivados</span>
          </div>
        </div>`;
    });

    cardsFilas.innerHTML = html;

    cardsFilas.querySelectorAll(".card-fila").forEach(c => {
      c.onclick = () => {
        filtroFilaAtivo = c.dataset.fila;
        page = 1;
        renderCardsFilas();
        aplicarFiltrosERender();
      };
    });
  }

  /* ===============================
     LISTAGEM
  =============================== */
  function renderLista() {
    if (!agentesFiltrados.length) {
      lista.innerHTML = `<div class="vazio">Nenhum agente encontrado</div>`;
      return;
    }

    const start = (page - 1) * perPage;
    const itens = agentesFiltrados.slice(start, start + perPage);

    lista.innerHTML = `
      <div class="grid">
        ${itens.map(a => `
          <div class="card-agente">
            <div class="topo">
              <div class="nome"><span class="id">#${a.id}</span> ${escapeHtml(a.name)}</div>
              <div class="${statusClass(a)}">${statusLabel(a)}</div>
            </div>
            <div class="linha"><b>Login:</b> ${escapeHtml(a.login)}</div>
            <div class="linha"><b>Fila:</b> ${escapeHtml(getFilaNome(a))}</div>
            <div class="linha tags">
              ${enableLabel(a)}
              ${a.mfa_enabled ? `<span class="tag tag-mfa">MFA</span>` : ``}
            </div>
            <div class="acoes">
              ${isOnline(a)
                ? `<button class="btn danger" onclick="deslogarAgente(${a.id})">Deslogar</button>`
                : `<button class="btn ghost" disabled>Offline</button>`}
            </div>
          </div>`).join("")}
      </div>`;
  }

  function destacarMudancas(ids) {
    ids.forEach(id => {
      const el = document.querySelector(`.card-agente .id:nth-child(1)`);
      if (!el) return;
      const card = el.closest(".card-agente");
      if (!card) return;
      card.classList.add("highlight-status");
      setTimeout(() => card.classList.remove("highlight-status"), 3000);
    });
  }

  /* ===============================
     PAGINAÇÃO
  =============================== */
  function renderPaginacao() {
    const totalPages = Math.max(1, Math.ceil(agentesFiltrados.length / perPage));
    paginaEl.textContent = `${page} / ${totalPages}`;
    btnPrev.disabled = page <= 1;
    btnNext.disabled = page >= totalPages;
  }

  btnPrev.onclick = () => { page--; renderLista(); renderPaginacao(); };
  btnNext.onclick = () => { page++; renderLista(); renderPaginacao(); };
/* FILTRO MANUAL
  [filtroStatus, buscaNome, agruparFila, mostrarArquivados].forEach(el =>
    el.addEventListener("change", () => {
      page = 1;
      aplicarFiltrosERender();
    })
  );*/
// filtros que mudam por seleção
[filtroStatus, agruparFila, mostrarArquivados].forEach(el =>
  el.addEventListener("change", () => {
    page = 1;
    aplicarFiltrosERender();
  })
);

// busca em tempo real (digitando)
buscaNome.addEventListener("input", () => {
  page = 1;
  aplicarFiltrosERender();
});


  /* ===============================
     INIT
  =============================== */
  restaurarEstado();
  fetchAgentes();
  setInterval(atualizarAgentesSilencioso, AUTO_REFRESH_MS);

});
