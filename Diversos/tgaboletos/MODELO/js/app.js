/**
 * app.js
 * ------------------------------------------------------------------
 * Ponto de entrada + roteador simples baseado em hash (#/...).
 * Não usa framework: cada rota é uma função que gera HTML e injeta
 * em #app-root. Isso mantém a Fase 1 (base) fiel ao stack pedido
 * (HTML + CSS + JS puro).
 * ------------------------------------------------------------------
 */
(function () {
  const root = document.getElementById("app-root");
  const menuLateral = document.getElementById("menu-bancos");

  function montarMenuLateral() {
    const { bancos } = window.CentralBoletos;
    menuLateral.innerHTML = bancos
      .map((b) => `<li><a href="#/banco/${b.id}">${b.nome}</a></li>`)
      .join("");
  }

  // ---------------- DASHBOARD ----------------
  function renderDashboard() {
    const { bancos } = window.CentralBoletos;
    const { ui } = window.CentralBoletos;
    const totalErros = bancos.reduce((acc, b) => acc + (b.erros ? b.erros.length : 0), 0);
    // "Documentação iniciada" mede conteúdo real (dados do cliente já
    // mapeados), não o rótulo de status — evita um card "0" enganoso
    // quando um banco (ex.: BB) já tem bastante conteúdo mas segue
    // como "em_documentacao" por ainda não ter teste de API real.
    const documentados = bancos.filter((b) => (b.dadosCliente || []).length > 0).length;
    const ultimaAtualizacaoIso = bancos.reduce((max, b) => (b.ultimaAtualizacao > max ? b.ultimaAtualizacao : max), "0000-00-00");
    const [ay, am, ad] = ultimaAtualizacaoIso.split("-");
    const ultimaAtualizacaoBr = `${ad}/${am}/${ay}`;

    root.innerHTML = `
      <div class="dashboard">
        <header class="dashboard__header">
          <div>
            <h1>Bancos com Boleto via API</h1>
            <p class="subtitulo">Central de Configuração de Boletos via API — base de apoio para o suporte configurar portadores corretamente.</p>
          </div>
        </header>

        <div class="busca-global">
          <input type="search" id="input-busca" placeholder="Pesquisar banco, campo, erro ou configuração (ex.: Client ID, 401, convênio, Sicredi)…" autocomplete="off">
          <div id="resultado-busca" class="resultado-busca"></div>
        </div>

        <section class="mini-dashboard">
          <div class="mini-card">
            <span class="mini-card__numero">${bancos.length}</span>
            <span class="mini-card__label">Bancos mapeados</span>
          </div>
          <div class="mini-card">
            <span class="mini-card__numero">${documentados}</span>
            <span class="mini-card__label">Com documentação iniciada</span>
          </div>
          <div class="mini-card">
            <span class="mini-card__numero">${totalErros}</span>
            <span class="mini-card__label">Erros catalogados</span>
          </div>
          <div class="mini-card">
            <span class="mini-card__numero">${ultimaAtualizacaoBr}</span>
            <span class="mini-card__label">Última atualização</span>
          </div>
        </section>

        <section>
          <h2 class="secao-titulo">Bancos</h2>
          <div class="grid-cards">
            ${bancos.map(ui.cardBanco).join("")}
          </div>
        </section>
      </div>`;

    const input = document.getElementById("input-busca");
    const resultadoBox = document.getElementById("resultado-busca");
    input.addEventListener("input", () => {
      const termo = input.value;
      if (!termo.trim()) {
        resultadoBox.classList.remove("resultado-busca--aberto");
        resultadoBox.innerHTML = "";
        return;
      }
      const resultados = window.CentralBoletos.busca.buscar(termo).slice(0, 12);
      resultadoBox.classList.add("resultado-busca--aberto");
      resultadoBox.innerHTML = resultados.length
        ? resultados
            .map(
              (r) => `<a class="resultado-item" href="${r.href}">
                <span class="resultado-item__tipo">${r.tipo}</span>
                <span class="resultado-item__titulo">${r.titulo}</span>
                <span class="resultado-item__contexto">${(r.contexto || "").slice(0, 90)}</span>
              </a>`
            )
            .join("")
        : `<p class="resultado-vazio">Nenhum resultado para "${window.CentralBoletos.ui.escapeHtml(termo)}".</p>`;
    });
  }

  // ---------------- PÁGINA DE BANCO ----------------
  function renderBanco(id) {
    const banco = window.CentralBoletos.bancos.find((b) => b.id === id);
    if (!banco) {
      root.innerHTML = `<div class="painel-secao"><h2>Banco não encontrado</h2><a href="#/">← Voltar</a></div>`;
      return;
    }
    root.innerHTML = window.CentralBoletos.bancosPage.renderPaginaBanco(banco);
    window.CentralBoletos.portadorMockup.wireTabs(root);
  }

  // ---------------- PÁGINA "MODELO DO PORTADOR" ----------------
  function renderPortadorGeral() {
    const base = window.CentralBoletos.portadorCamposBase;
    const { ui } = window.CentralBoletos;
    const abas = [base.abaDadosCedente, base.abaInstrucoesBanco, base.abaImpressao, base.abaRemessaRetorno, base.abaOutrosDados];
    // Pseudo-banco neutro só para reaproveitar o mesmo mockup visual
    // aqui, sem destacar nenhum campo específico de um banco.
    const bancoNeutro = { id: "modelo", camposPortadorRelevantes: [] };
    root.innerHTML = `
      <div class="pagina-banco">
        <div class="pagina-banco__header">
          <a href="#/" class="voltar">← Bancos</a>
          <div class="pagina-banco__titulo"><h1>Modelo do Portador</h1></div>
        </div>
        <p class="painel-texto">
          Cadastro único do ERP, comum a todos os bancos. A escolha do banco/API acontece no campo
          <strong>Tipo Cobrança API</strong> (aba Remessa/Retorno). Extraído diretamente das telas de referência.
        </p>
        ${window.CentralBoletos.portadorMockup.render(bancoNeutro)}
        <h2 class="secao-titulo" style="margin-top:26px;">Explicação campo a campo</h2>
        ${abas
          .map(
            (aba) => `
          <section class="painel-secao">
            <h2>${aba.titulo}</h2>
            ${aba.observacao ? `<p class="aviso-inline">${aba.observacao}</p>` : ""}
            <div class="campos-grid">${(aba.campos || []).map(ui.campoLinha).join("") || '<p class="aviso-inline">Sem campos documentados ainda.</p>'}</div>
          </section>`
          )
          .join("")}
      </div>`;
    window.CentralBoletos.portadorMockup.wireTabs(root);
  }

  // ---------------- PÁGINA "BASE DE CONHECIMENTO DE ERROS" ----------------
  function renderErrosGerais() {
    const lista = window.CentralBoletos.baseConhecimentoGeral;
    root.innerHTML = `
      <div class="pagina-banco">
        <div class="pagina-banco__header">
          <a href="#/" class="voltar">← Bancos</a>
          <div class="pagina-banco__titulo"><h1>Base de Conhecimento — Erros</h1></div>
        </div>
        <section class="painel-secao">
          ${lista
            .map(
              (item) => `
            <div class="erro-card">
              <h4>${item.erro}</h4>
              <p><strong>Causa:</strong> ${item.causa}</p>
              <p><strong>Solução:</strong> ${item.solucao}</p>
              <p class="aviso-inline">${item.observacao}</p>
            </div>`
            )
            .join("")}
        </section>
      </div>`;
  }

  // ---------------- ROTEADOR ----------------
  function rotear() {
    const hash = (location.hash || "#/").split("#").filter(Boolean); // suporta "#/banco/bb#erros"
    const rota = "#" + (hash[0] || "/").replace(/^#/, "");
    const partes = rota.replace(/^#/, "").split("/").filter(Boolean);

    document.querySelectorAll(".menu-lateral a").forEach((a) => {
      const href = a.getAttribute("href");
      a.classList.toggle("ativo", href === rota || (href === "#/" && rota === "#/"));
    });

    if (partes[0] === "banco" && partes[1]) {
      renderBanco(partes[1]);
    } else if (partes[0] === "portador") {
      renderPortadorGeral();
    } else if (partes[0] === "erros") {
      renderErrosGerais();
    } else {
      renderDashboard();
    }
    window.scrollTo(0, 0);
  }

  window.addEventListener("hashchange", rotear);
  window.addEventListener("DOMContentLoaded", () => {
    montarMenuLateral();
    rotear();
  });
})();
