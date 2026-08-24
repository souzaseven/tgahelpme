/**
 * ui.js
 * Renderização: estados da página, cards de resultado, abas,
 * toasts e listas laterais (recentes/favoritos).
 * Não faz chamadas de rede nem acessa localStorage diretamente.
 */

const Ui = (() => {

  const el = {
    liveRegion: document.getElementById("liveRegion"),

    emptyState: document.getElementById("emptyState"),
    loadingState: document.getElementById("loadingState"),
    errorState: document.getElementById("errorState"),
    notFoundState: document.getElementById("notFoundState"),
    resultState: document.getElementById("resultState"),

    errorTitle: document.getElementById("errorTitle"),
    errorText: document.getElementById("errorText"),
    loadingText: document.getElementById("loadingText"),

    statusBadge: document.getElementById("statusBadge"),
    resultCompanyName: document.getElementById("resultCompanyName"),
    resultFantasyName: document.getElementById("resultFantasyName"),
    resultCnpjLine: document.getElementById("resultCnpjLine"),

    dlEmpresa: document.getElementById("dlEmpresa"),
    dlSituacao: document.getElementById("dlSituacao"),
    dlEndereco: document.getElementById("dlEndereco"),
    dlContato: document.getElementById("dlContato"),
    dlTech: document.getElementById("dlTech"),

    mapLink: document.getElementById("mapLink"),

    cnaePrincipal: document.getElementById("cnaePrincipal"),
    cnaeSecundariosWrap: document.getElementById("cnaeSecundariosWrap"),
    cnaeSecundariosCount: document.getElementById("cnaeSecundariosCount"),
    cnaeSecundariosList: document.getElementById("cnaeSecundariosList"),

    sociosTableBody: document.getElementById("sociosTableBody"),
    sociosEmpty: document.getElementById("sociosEmpty"),

    jsonOutput: document.getElementById("jsonOutput"),

    favoriteBtn: document.getElementById("favoriteBtn"),

    recentList: document.getElementById("recentList"),
    recentEmpty: document.getElementById("recentEmpty"),
    favoritesList: document.getElementById("favoritesList"),
    favoritesEmpty: document.getElementById("favoritesEmpty"),

    toastContainer: document.getElementById("toastContainer"),
  };

  const STATES = ["emptyState", "loadingState", "errorState", "notFoundState", "resultState"];

  function showState(name) {
    STATES.forEach((key) => {
      el[key].hidden = key !== name;
    });
  }

  function announce(message) {
    el.liveRegion.textContent = "";
    // pequeno delay para garantir que leitores de tela percebam a mudança
    window.setTimeout(() => { el.liveRegion.textContent = message; }, 30);
  }

  const DEFAULT_LOADING_MESSAGE = "Consultando empresa...";

  function setLoading(isLoading) {
    if (isLoading) {
      el.loadingText.textContent = DEFAULT_LOADING_MESSAGE;
      showState("loadingState");
      announce(DEFAULT_LOADING_MESSAGE);
    }
  }

  /** Atualiza a mensagem exibida durante o carregamento (ex.: aviso de lentidão ou nova tentativa). */
  function setLoadingMessage(text) {
    el.loadingText.textContent = text;
    announce(text);
  }

  function showError(title, text) {
    el.errorTitle.textContent = title;
    el.errorText.textContent = text;
    showState("errorState");
    announce(`${title} ${text}`);
  }

  function showNotFound() {
    showState("notFoundState");
    announce("Nenhuma empresa encontrada para este CNPJ.");
  }

  function showEmpty() {
    showState("emptyState");
  }

  // ---------- HELPERS DE RENDERIZAÇÃO ----------

  function ddTerm(label, value, { mono = false } = {}) {
    const dt = document.createElement("dt");
    dt.textContent = label;
    const dd = document.createElement("dd");
    if (Utils.isEmptyValue(value)) {
      dd.textContent = "Não informado";
      dd.classList.add("is-empty");
    } else {
      dd.textContent = value;
    }
    if (mono) dd.style.fontFamily = "var(--font-mono)";
    return [dt, dd];
  }

  function fillDefList(container, pairs) {
    container.innerHTML = "";
    pairs.forEach(([label, value, opts]) => {
      const [dt, dd] = ddTerm(label, value, opts);
      container.appendChild(dt);
      container.appendChild(dd);
    });
  }

  function statusBadgeClass(descricaoSituacao) {
    const s = (descricaoSituacao || "").toUpperCase();
    if (s === "ATIVA") return "status-badge--success";
    if (["BAIXADA", "INAPTA", "NULA"].includes(s)) return "status-badge--danger";
    if (s === "SUSPENSA") return "status-badge--warning";
    return "";
  }

  // ---------- RESULTADO PRINCIPAL ----------

  /**
   * A BrasilAPI agrega mais de uma fonte de dados e, dependendo do fallback
   * utilizado internamente, alguns campos podem vir combinados em vez de
   * separados (ex.: "situacao_cadastral" já como texto, sem
   * "descricao_situacao_cadastral"; "cnae_fiscal" como "codigo - descrição").
   * As funções abaixo normalizam essas variações para a interface não quebrar.
   */
  function resolverSituacaoCadastral(data) {
    if (data.descricao_situacao_cadastral) return data.descricao_situacao_cadastral;
    if (typeof data.situacao_cadastral === "string") return data.situacao_cadastral;
    return "Situação desconhecida";
  }

  function resolverCnaePrincipal(data) {
    if (data.cnae_fiscal_descricao) {
      return { codigo: data.cnae_fiscal || "", descricao: data.cnae_fiscal_descricao };
    }
    if (typeof data.cnae_fiscal === "string" && data.cnae_fiscal.includes(" - ")) {
      const [codigo, ...resto] = data.cnae_fiscal.split(" - ");
      return { codigo: codigo.trim(), descricao: resto.join(" - ").trim() };
    }
    if (data.cnae_fiscal) {
      return { codigo: String(data.cnae_fiscal), descricao: "" };
    }
    return null;
  }

  /** Prefere a descrição textual do porte (ex.: "DEMAIS", "MICRO EMPRESA") quando disponível. */
  function resolverPorte(data) {
    if (data.descricao_porte && data.descricao_porte.trim()) return data.descricao_porte;
    return data.porte;
  }

  function renderResult(data, meta, normalizedCnpj) {
    const razaoSocial = data.razao_social || "Razão social não informada";
    const nomeFantasia = data.nome_fantasia && data.nome_fantasia.trim() ? data.nome_fantasia : "";
    const situacao = resolverSituacaoCadastral(data);

    // Cabeçalho
    el.statusBadge.textContent = situacao;
    el.statusBadge.className = "status-badge " + statusBadgeClass(situacao);
    el.resultCompanyName.textContent = razaoSocial;
    el.resultFantasyName.textContent = nomeFantasia ? `Nome fantasia: ${nomeFantasia}` : "";
    el.resultFantasyName.hidden = !nomeFantasia;
    el.resultCnpjLine.textContent = `CNPJ ${Utils.formatCnpjDisplay(normalizedCnpj)}`;

    // Card: Empresa
    fillDefList(el.dlEmpresa, [
      ["Razão social", data.razao_social],
      ["Nome fantasia", nomeFantasia || null],
      ["CNPJ", Utils.formatCnpjDisplay(normalizedCnpj), { mono: true }],
      ["Natureza jurídica", data.natureza_juridica],
      ["Porte", resolverPorte(data)],
      ["Capital social", Utils.formatCurrencyBRL(data.capital_social)],
      ["Data de abertura", Utils.formatDateBR(data.data_inicio_atividade)],
      ["Matriz/Filial", data.descricao_identificador_matriz_filial],
    ]);

    // Card: Situação cadastral
    fillDefList(el.dlSituacao, [
      ["Situação cadastral", situacao],
      ["Data da situação", Utils.formatDateBR(data.data_situacao_cadastral)],
      ["Motivo", data.descricao_motivo_situacao_cadastral],
      ["Situação especial", data.situacao_especial],
      ["Data da situação especial", Utils.formatDateBR(data.data_situacao_especial)],
      ["Optante pelo Simples", data.opcao_pelo_simples === true ? "Sim" : (data.opcao_pelo_simples === false ? "Não" : null)],
      ["Optante pelo MEI", data.opcao_pelo_mei === true ? "Sim" : (data.opcao_pelo_mei === false ? "Não" : null)],
    ]);

    // Card: Endereço
    const enderecoLinha = montarEnderecoTexto(data);
    fillDefList(el.dlEndereco, [
      ["Logradouro", [data.descricao_tipo_de_logradouro, data.logradouro].filter(Boolean).join(" ") || data.logradouro],
      ["Número", data.numero],
      ["Complemento", data.complemento],
      ["Bairro", data.bairro],
      ["Município", data.municipio],
      ["UF", data.uf],
      ["CEP", Utils.formatCep(data.cep)],
    ]);
    if (enderecoLinha) {
      el.mapLink.href = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(enderecoLinha)}`;
      el.mapLink.hidden = false;
    } else {
      el.mapLink.hidden = true;
    }

    // Card: Contato
    fillDefList(el.dlContato, [
      ["Telefone", Utils.formatPhone(null, data.ddd_telefone_1) || Utils.formatPhone(null, data.ddd_telefone_2)],
      ["Telefone secundário", data.ddd_telefone_1 && data.ddd_telefone_2 ? Utils.formatPhone(null, data.ddd_telefone_2) : null],
      ["E-mail", data.email],
    ]);

    // Atividades
    const cnaePrincipal = resolverCnaePrincipal(data);
    if (cnaePrincipal) {
      el.cnaePrincipal.innerHTML = "";
      const codeSpan = document.createElement("span");
      codeSpan.className = "activity-code";
      codeSpan.textContent = cnaePrincipal.codigo;
      el.cnaePrincipal.appendChild(codeSpan);
      el.cnaePrincipal.appendChild(document.createTextNode(cnaePrincipal.descricao || "Descrição não informada"));
    } else {
      el.cnaePrincipal.textContent = "Não informado";
    }

    const secundarios = Array.isArray(data.cnaes_secundarios) ? data.cnaes_secundarios : [];
    el.cnaeSecundariosCount.textContent = secundarios.length;
    el.cnaeSecundariosList.innerHTML = "";
    if (secundarios.length === 0) {
      el.cnaeSecundariosWrap.hidden = true;
    } else {
      el.cnaeSecundariosWrap.hidden = false;
      secundarios.forEach((cnae) => {
        const li = document.createElement("li");
        const codeSpan = document.createElement("span");
        codeSpan.className = "activity-code";
        codeSpan.textContent = cnae.codigo ? String(cnae.codigo) : "";
        li.appendChild(codeSpan);
        li.appendChild(document.createTextNode(cnae.descricao || "Descrição não informada"));
        el.cnaeSecundariosList.appendChild(li);
      });
    }

    // Sócios
    const qsa = Array.isArray(data.qsa) ? data.qsa : [];
    el.sociosTableBody.innerHTML = "";
    if (qsa.length === 0) {
      el.sociosEmpty.hidden = false;
      document.getElementById("sociosWrap").hidden = true;
    } else {
      el.sociosEmpty.hidden = true;
      document.getElementById("sociosWrap").hidden = false;
      qsa.forEach((socio) => {
        const tr = document.createElement("tr");
        [
          socio.nome_socio || "Não informado",
          socio.qualificacao_socio || "Não informada",
          socio.faixa_etaria || "Não informada",
          Utils.formatDateBR(socio.data_entrada_sociedade) || "Não informada",
        ].forEach((text) => {
          const td = document.createElement("td");
          td.textContent = text;
          tr.appendChild(td);
        });
        el.sociosTableBody.appendChild(tr);
      });
    }

    // JSON
    el.jsonOutput.textContent = JSON.stringify(data, null, 2);

    // Técnico
    fillDefList(el.dlTech, [
      ["Endpoint", meta.url, { mono: true }],
      ["Método", meta.method],
      ["Status HTTP", String(meta.status)],
      ["Fonte", meta.fromCache ? "Cache local (consulta recente)" : "BrasilAPI (rede)"],
      ["Tempo de resposta", meta.fromCache ? "Instantâneo (cache local)" : `${meta.elapsedMs} ms`],
      ["Consultado em", new Date(meta.requestedAt).toLocaleString("pt-BR")],
    ]);

    showState("resultState");
    announce(`Empresa encontrada: ${razaoSocial}. Situação: ${situacao}.`);
  }

  function montarEnderecoTexto(data) {
    const partes = [
      [data.descricao_tipo_de_logradouro, data.logradouro].filter(Boolean).join(" "),
      data.numero,
      data.bairro,
      data.municipio,
      data.uf,
      Utils.formatCep(data.cep),
    ].filter((p) => p && String(p).trim());
    return partes.join(", ");
  }

  // ---------- ABAS ----------

  function switchTab(tabId) {
    const tabs = [
      { btn: "tabBtnCards", panel: "tabCards" },
      { btn: "tabBtnJson", panel: "tabJson" },
      { btn: "tabBtnTech", panel: "tabTech" },
    ];
    tabs.forEach(({ btn, panel }) => {
      const isActive = panel === tabId;
      document.getElementById(btn).classList.toggle("is-active", isActive);
      document.getElementById(btn).setAttribute("aria-selected", String(isActive));
      document.getElementById(panel).hidden = !isActive;
    });
  }

  // ---------- FAVORITOS ----------

  function setFavoriteButtonState(isFavorite) {
    el.favoriteBtn.setAttribute("aria-pressed", String(isFavorite));
    el.favoriteBtn.innerHTML = `<span aria-hidden="true">${isFavorite ? "★" : "☆"}</span>`;
    const label = isFavorite ? "Remover dos favoritos" : "Favoritar";
    el.favoriteBtn.title = label;
    // O conteúdo visível é um ícone com aria-hidden, então o nome acessível
    // precisa vir explicitamente do aria-label — depender só do "title"
    // como fallback é inconsistente entre leitores de tela.
    el.favoriteBtn.setAttribute("aria-label", label);
  }

  function renderSideList(listEl, emptyEl, items, { onSelect, onRemove, onToggleFavorite, isFavorite }) {
    listEl.innerHTML = "";
    emptyEl.hidden = items.length > 0;
    items.forEach((item) => {
      const li = document.createElement("li");

      const textWrap = document.createElement("div");
      textWrap.className = "side-item-text";
      const nameEl = document.createElement("span");
      nameEl.className = "side-item-name";
      nameEl.textContent = item.razaoSocial || item.nomeFantasia || "Empresa";
      const cnpjEl = document.createElement("span");
      cnpjEl.className = "side-item-cnpj";
      cnpjEl.textContent = Utils.formatCnpjDisplay(item.cnpj);
      textWrap.appendChild(nameEl);
      textWrap.appendChild(cnpjEl);

      li.appendChild(textWrap);

      // Estrela de favorito (usada na lista de recentes, para favoritar sem reabrir o resultado)
      if (onToggleFavorite) {
        const isFav = isFavorite ? isFavorite(item.cnpj) : false;
        const favBtn = document.createElement("button");
        favBtn.type = "button";
        favBtn.className = "side-item-fav";
        favBtn.innerHTML = `<span aria-hidden="true">${isFav ? "★" : "☆"}</span>`;
        favBtn.setAttribute("aria-label", isFav ? `Remover ${nameEl.textContent} dos favoritos` : `Adicionar ${nameEl.textContent} aos favoritos`);
        favBtn.setAttribute("aria-pressed", String(isFav));
        favBtn.addEventListener("click", (evt) => {
          evt.stopPropagation();
          onToggleFavorite(item);
        });
        li.appendChild(favBtn);
      }

      const removeBtn = document.createElement("button");
      removeBtn.type = "button";
      removeBtn.className = "side-item-remove";
      removeBtn.setAttribute("aria-label", `Remover ${nameEl.textContent}`);
      removeBtn.innerHTML = "&times;";
      removeBtn.addEventListener("click", (evt) => {
        evt.stopPropagation();
        onRemove(item.cnpj);
      });

      li.appendChild(removeBtn);
      li.addEventListener("click", () => onSelect(item.cnpj));
      listEl.appendChild(li);
    });
  }

  function renderRecents(items, handlers) {
    renderSideList(el.recentList, el.recentEmpty, items, handlers);
  }

  function renderFavorites(items, handlers) {
    renderSideList(el.favoritesList, el.favoritesEmpty, items, handlers);
  }

  // ---------- TOASTS ----------

  function toast(message, type = "info") {
    const div = document.createElement("div");
    div.className = "toast" + (type === "error" ? " toast--error" : "");
    div.textContent = message;
    el.toastContainer.appendChild(div);
    window.setTimeout(() => {
      div.remove();
    }, 2600);
  }

  // ---------- FEEDBACK EM BOTÕES ----------

  /** Troca temporariamente o texto de um botão (ex.: "Copiado ✓") e depois restaura. */
  function flashButton(buttonEl, flashText, duration = 1400) {
    const original = buttonEl.textContent;
    buttonEl.textContent = flashText;
    buttonEl.disabled = true;
    window.setTimeout(() => {
      buttonEl.textContent = original;
      buttonEl.disabled = false;
    }, duration);
  }

  // ---------- VALIDAÇÃO DO CAMPO ----------

  function setFieldInvalid(inputEl, errorEl, message) {
    if (message) {
      inputEl.classList.add("is-invalid");
      inputEl.setAttribute("aria-invalid", "true");
      errorEl.textContent = message;
      errorEl.hidden = false;
    } else {
      inputEl.classList.remove("is-invalid");
      inputEl.removeAttribute("aria-invalid");
      errorEl.textContent = "";
      errorEl.hidden = true;
    }
  }

  return {
    el,
    showState,
    showEmpty,
    setLoading,
    setLoadingMessage,
    showError,
    showNotFound,
    renderResult,
    montarEnderecoTexto,
    resolverSituacaoCadastral,
    resolverCnaePrincipal,
    switchTab,
    setFavoriteButtonState,
    renderRecents,
    renderFavorites,
    toast,
    flashButton,
    setFieldInvalid,
    announce,
  };

})();
