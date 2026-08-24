/**
 * app.js
 * Orquestração: liga formulário, API, UI e Storage.
 */

(() => {

  // ---------- REDE DE SEGURANÇA CONTRA ERROS INESPERADOS ----------
  // Fica registrada antes de qualquer outra coisa, para pegar até erros
  // que aconteçam durante a própria inicialização. Não impede o erro,
  // só garante que o usuário veja um aviso em vez de a tela travar
  // silenciosamente sem feedback nenhum.
  let lastGlobalErrorToastAt = 0;
  function avisarErroInesperado(origem, detalhe) {
    console.error(`[erro inesperado] ${origem}:`, detalhe);
    const agora = Date.now();
    if (agora - lastGlobalErrorToastAt < 5000) return; // evita enxurrada de toasts
    lastGlobalErrorToastAt = agora;
    if (typeof Ui !== "undefined" && Ui.toast) {
      Ui.toast("Ocorreu um erro inesperado. Se algo parar de responder, recarregue a página.", "error");
    }
  }
  window.addEventListener("error", (evt) => {
    avisarErroInesperado("script", evt.error || evt.message);
  });
  window.addEventListener("unhandledrejection", (evt) => {
    avisarErroInesperado("promise", evt.reason);
  });

  const form = document.getElementById("searchForm");
  const input = document.getElementById("cnpjInput");
  const searchBtn = document.getElementById("searchBtn");
  const searchBtnLabel = document.getElementById("searchBtnLabel");
  const cnpjError = document.getElementById("cnpjError");

  const retryBtn = document.getElementById("retryBtn");
  const clearErrorBtn = document.getElementById("clearErrorBtn");
  const tryAgainBtn = document.getElementById("tryAgainBtn");
  const clearNotFoundBtn = document.getElementById("clearNotFoundBtn");

  const favoriteBtn = document.getElementById("favoriteBtn");
  const generatePdfBtn = document.getElementById("generatePdfBtn");
  const quickActionsBtn = document.getElementById("quickActionsBtn");
  const quickActionsMenu = document.getElementById("quickActionsMenu");
  const copyAddressBtn = document.getElementById("copyAddressBtn");
  const copyJsonBtn = document.getElementById("copyJsonBtn");
  const clearHistoryBtn = document.getElementById("clearHistoryBtn");

  const themeToggle = document.getElementById("themeToggle");
  const themeIcon = document.getElementById("themeIcon");
  const themeLabel = document.getElementById("themeLabel");

  const tabBtnCards = document.getElementById("tabBtnCards");
  const tabBtnJson = document.getElementById("tabBtnJson");
  const tabBtnTech = document.getElementById("tabBtnTech");

  // Estado da última consulta bem-sucedida (para ações rápidas / favoritar / imprimir)
  let currentResult = null; // { data, meta, normalizedCnpj }
  let isRequesting = false;
  let lastAttemptedRawValue = "";

  // ---------- MÁSCARA NO CAMPO ----------

  input.addEventListener("input", () => {
    const cursorWasAtEnd = input.selectionStart === input.value.length;
    input.value = Utils.maskCnpj(input.value);
    if (cursorWasAtEnd) {
      input.setSelectionRange(input.value.length, input.value.length);
    }
    Ui.setFieldInvalid(input, cnpjError, null);
  });

  input.addEventListener("paste", (evt) => {
    // Se o que foi colado for um texto maior (ex.: um trecho de e-mail
    // com "CNPJ: 12.345.678/0001-90 - Empresa X"), extrai só o CNPJ em
    // vez de tentar usar o texto inteiro como número.
    const pastedText = (evt.clipboardData || window.clipboardData)?.getData("text") || "";
    const extraido = Utils.extractCnpjFromText(pastedText);

    if (extraido && Utils.normalizeCnpj(pastedText) !== extraido) {
      evt.preventDefault();
      input.value = Utils.maskCnpj(extraido);
      Ui.setFieldInvalid(input, cnpjError, null);
      return;
    }

    // Colagem "limpa" (só o CNPJ): deixa o listener de "input" cuidar da máscara
    window.setTimeout(() => {
      input.value = Utils.maskCnpj(input.value);
    }, 0);
  });

  // ---------- SUBMISSÃO ----------

  form.addEventListener("submit", (evt) => {
    evt.preventDefault();
    handleSearch(input.value);
  });

  async function handleSearch(rawValue) {
    if (isRequesting) return; // evita requisições duplicadas

    const trimmed = (rawValue || "").trim();
    if (!trimmed) {
      Ui.setFieldInvalid(input, cnpjError, "Digite um CNPJ para consultar.");
      input.focus();
      return;
    }

    let normalized = Utils.normalizeCnpj(trimmed);

    if (!Utils.isPlausibleCnpj(normalized)) {
      // Segunda chance: talvez o campo tenha texto extra ao redor do CNPJ
      // (ex.: colado de um documento sem passar pelo evento "paste", ou
      // arrastado para o campo). Tenta extrair o número de dentro do texto.
      const extraido = Utils.extractCnpjFromText(trimmed);
      if (extraido) {
        normalized = extraido;
        input.value = Utils.maskCnpj(normalized);
      } else {
        Ui.setFieldInvalid(input, cnpjError, "CNPJ inválido. Verifique se digitou os 14 caracteres corretamente.");
        input.focus();
        return;
      }
    }

    if (!Utils.isValidCnpjChecksum(normalized)) {
      Ui.setFieldInvalid(input, cnpjError, "CNPJ inválido. Verifique o número digitado.");
      input.focus();
      return;
    }

    Ui.setFieldInvalid(input, cnpjError, null);
    lastAttemptedRawValue = normalized;

    // Cache local de curta duração: evita nova ida à rede para um CNPJ
    // consultado há pouco tempo (ver Storage.CACHE_TTL_MS).
    const cached = Storage.getCached(normalized);
    if (cached) {
      applyResult(cached.data, {
        url: `${Api.BASE_URL}?cnpj=${normalized}`,
        method: "GET",
        status: 200,
        elapsedMs: 0,
        requestedAt: new Date(cached.cachedAt).toISOString(),
        fromCache: true,
      }, normalized);
      Ui.toast("Exibindo dados em cache (consulta recente).");
      return;
    }

    isRequesting = true;
    searchBtn.disabled = true;
    searchBtnLabel.textContent = "Consultando...";
    Ui.setLoading(true);

    // Se a consulta demorar, avisa que a BrasilAPI pode estar lenta
    // (o endpoint de CNPJ já teve relatos de indisponibilidade/504).
    const slowTimer = window.setTimeout(() => {
      Ui.setLoadingMessage("A BrasilAPI pode estar lenta neste momento. Aguarde mais um instante...");
    }, 6000);

    try {
      const { data, meta } = await Api.consultarCnpj(normalized, {
        onRetry: () => Ui.setLoadingMessage("Tivemos uma falha temporária. Tentando novamente..."),
      });
      Storage.setCached(normalized, data);
      applyResult(data, meta, normalized);
    } catch (err) {
      currentResult = null;
      handleApiError(err);
    } finally {
      window.clearTimeout(slowTimer);
      isRequesting = false;
      searchBtn.disabled = false;
      searchBtnLabel.textContent = "Consultar";
    }
  }

  /** Aplica um resultado (vindo da rede ou do cache) na UI e no histórico. */
  function applyResult(data, meta, normalized) {
    currentResult = { data, meta, normalizedCnpj: normalized };

    Ui.renderResult(data, meta, normalized);
    Ui.switchTab("tabCards");

    Storage.addRecent({
      cnpj: normalized,
      razaoSocial: data.razao_social || "",
      nomeFantasia: data.nome_fantasia || "",
      consultedAt: new Date().toISOString(),
    });
    refreshRecents();

    Ui.setFavoriteButtonState(Storage.isFavorite(normalized));
  }

  function handleApiError(err) {
    if (!(err instanceof Api.ApiError)) {
      Ui.showError(
        "Não foi possível consultar o CNPJ neste momento.",
        "Tente novamente em alguns instantes."
      );
      return;
    }

    switch (err.type) {
      case Api.ErrorType.NOT_FOUND:
        Ui.showNotFound();
        break;
      case Api.ErrorType.INVALID_FORMAT:
        Ui.showError(
          "O CNPJ informado não é válido.",
          "Confira se todos os números foram digitados corretamente e tente novamente."
        );
        break;
      case Api.ErrorType.TIMEOUT:
        Ui.showError(
          "A consulta demorou mais do que o esperado.",
          "A BrasilAPI pode estar lenta neste momento. Tente novamente em instantes."
        );
        break;
      case Api.ErrorType.NETWORK:
        Ui.showError(
          "Não foi possível conectar ao serviço de consulta.",
          "Verifique sua conexão com a internet e tente novamente."
        );
        break;
      case Api.ErrorType.SERVER:
        Ui.showError(
          "Não foi possível consultar o CNPJ neste momento.",
          "A BrasilAPI está indisponível no momento. Tente novamente em alguns instantes."
        );
        break;
      case Api.ErrorType.RATE_LIMIT:
        Ui.showError(
          "Limite de consultas atingido.",
          "Muitas consultas em pouco tempo. Aguarde um instante e tente novamente."
        );
        break;
      case Api.ErrorType.PARSE:
        Ui.showError(
          "Recebemos uma resposta inesperada do serviço de consulta.",
          "Tente novamente em alguns instantes."
        );
        break;
      default:
        Ui.showError(
          "Não foi possível consultar o CNPJ neste momento.",
          "Tente novamente em alguns instantes."
        );
    }
  }

  // ---------- ESTADOS: RETRY / LIMPAR ----------

  retryBtn.addEventListener("click", () => {
    if (lastAttemptedRawValue) handleSearch(lastAttemptedRawValue);
  });

  tryAgainBtn.addEventListener("click", () => {
    input.focus();
    input.select();
  });

  clearErrorBtn.addEventListener("click", resetToEmpty);
  clearNotFoundBtn.addEventListener("click", resetToEmpty);

  function resetToEmpty() {
    input.value = "";
    currentResult = null;
    Ui.setFieldInvalid(input, cnpjError, null);
    Ui.showEmpty();
    input.focus();
  }

  // ---------- SELEÇÃO A PARTIR DE RECENTES/FAVORITOS ----------

  function selectFromSide(cnpj) {
    input.value = Utils.maskCnpj(cnpj);
    handleSearch(cnpj);
  }

  /** Recarrega a lista de recentes a partir do Storage (com ícone de favorito). */
  function refreshRecents() {
    Ui.renderRecents(Storage.getRecents(), {
      onSelect: selectFromSide,
      onRemove: removeRecent,
      onToggleFavorite: toggleFavorite,
      isFavorite: Storage.isFavorite,
    });
  }

  /** Recarrega a lista de favoritos a partir do Storage. */
  function refreshFavorites() {
    Ui.renderFavorites(Storage.getFavorites(), { onSelect: selectFromSide, onRemove: removeFavorite });
  }

  function removeRecent(cnpj) {
    Storage.removeRecent(cnpj);
    refreshRecents();
    Ui.toast("Item removido do histórico.");
  }

  function removeFavorite(cnpj) {
    Storage.removeFavorite(cnpj);
    refreshFavorites();
    refreshRecents(); // a estrela do item nos recentes também precisa refletir a remoção
    if (currentResult && currentResult.normalizedCnpj === cnpj) {
      Ui.setFavoriteButtonState(false);
    }
  }

  clearHistoryBtn.addEventListener("click", () => {
    Storage.clearRecents();
    refreshRecents();
    Ui.toast("Histórico de consultas limpo.");
  });

  // ---------- FAVORITAR ----------

  /**
   * Alterna favorito para uma empresa (usado tanto pelo botão de
   * favoritar do resultado quanto pela estrela na lista de recentes).
   * Sempre grava só os 3 campos que um favorito precisa — se vier de
   * "Recentes", o item também carrega "consultedAt", que não deve ir
   * parar no registro de favorito.
   */
  function toggleFavorite(entry) {
    const cleanEntry = {
      cnpj: entry.cnpj,
      razaoSocial: entry.razaoSocial || "",
      nomeFantasia: entry.nomeFantasia || "",
    };
    const result = Storage.toggleFavorite(cleanEntry);
    refreshFavorites();
    refreshRecents();
    if (currentResult && currentResult.normalizedCnpj === entry.cnpj) {
      Ui.setFavoriteButtonState(result.isFavorite);
    }
    Ui.toast(result.isFavorite ? "Empresa adicionada aos favoritos." : "Empresa removida dos favoritos.");
    return result;
  }

  favoriteBtn.addEventListener("click", () => {
    if (!currentResult) return;
    const { data, normalizedCnpj } = currentResult;
    toggleFavorite({
      cnpj: normalizedCnpj,
      razaoSocial: data.razao_social || "",
      nomeFantasia: data.nome_fantasia || "",
    });
  });

  // ---------- ABAS ----------

  tabBtnCards.addEventListener("click", () => Ui.switchTab("tabCards"));
  tabBtnJson.addEventListener("click", () => Ui.switchTab("tabJson"));
  tabBtnTech.addEventListener("click", () => Ui.switchTab("tabTech"));

  // ---------- MENU DE AÇÕES RÁPIDAS ----------

  quickActionsBtn.addEventListener("click", (evt) => {
    evt.stopPropagation();
    const isOpen = !quickActionsMenu.hidden;
    quickActionsMenu.hidden = isOpen;
    quickActionsBtn.setAttribute("aria-expanded", String(!isOpen));
  });

  document.addEventListener("click", (evt) => {
    if (!quickActionsMenu.hidden && !quickActionsMenu.contains(evt.target) && evt.target !== quickActionsBtn) {
      quickActionsMenu.hidden = true;
      quickActionsBtn.setAttribute("aria-expanded", "false");
    }
  });

  document.addEventListener("keydown", (evt) => {
    if (evt.key === "Escape" && !quickActionsMenu.hidden) {
      quickActionsMenu.hidden = true;
      quickActionsBtn.setAttribute("aria-expanded", "false");
      quickActionsBtn.focus();
    }
  });

  quickActionsMenu.addEventListener("click", (evt) => {
    const btn = evt.target.closest("[data-action]");
    if (!btn || !currentResult) return;
    quickActionsMenu.hidden = true;
    quickActionsBtn.setAttribute("aria-expanded", "false");
    runQuickAction(btn.dataset.action);
  });

  function runQuickAction(action) {
    const { data, meta, normalizedCnpj } = currentResult;
    switch (action) {
      case "copy-cnpj":
        copyToClipboard(Utils.formatCnpjDisplay(normalizedCnpj), "CNPJ copiado com sucesso.");
        break;
      case "copy-razao":
        copyToClipboard(data.razao_social || "", "Razão social copiada com sucesso.");
        break;
      case "copy-fantasia":
        copyToClipboard(data.nome_fantasia || "Não informado", "Nome fantasia copiado com sucesso.");
        break;
      case "copy-endereco":
        copyToClipboard(Ui.montarEnderecoTexto(data), "Endereço copiado com sucesso.");
        break;
      case "copy-completo":
        copyToClipboard(montarResumoCompleto(data, normalizedCnpj), "Dados completos copiados com sucesso.");
        break;
      case "copy-json":
        copyToClipboard(JSON.stringify(data, null, 2), "JSON copiado com sucesso.");
        break;
    }
  }

  // ---------- GERAR PDF ----------
  // Usa a impressão nativa do navegador (Ctrl+P → "Salvar como PDF"), sem
  // depender de nenhuma biblioteca externa. A ficha impressa é sempre a
  // própria (identidade da ferramenta, com aviso de que não é um
  // documento oficial) — nunca reproduz certidões/comprovantes de órgãos
  // públicos.

  generatePdfBtn.addEventListener("click", () => {
    if (!currentResult) return;

    // Garante que a aba "Resumo" esteja visível: imprimir com a aba JSON
    // ou Detalhes técnicos aberta deixaria a ficha em branco (essas abas
    // ficam ocultas na impressão).
    Ui.switchTab("tabCards");

    const printGeneratedAt = document.getElementById("printGeneratedAt");
    if (printGeneratedAt) {
      printGeneratedAt.textContent = ` · Gerado em ${new Date().toLocaleString("pt-BR")}`;
    }

    Ui.toast("Na janela que abrir, escolha \"Salvar como PDF\" como destino.");
    window.setTimeout(() => window.print(), 300);
  });

  copyAddressBtn.addEventListener("click", () => {
    if (!currentResult) return;
    copyToClipboard(Ui.montarEnderecoTexto(currentResult.data), "Endereço copiado com sucesso.", copyAddressBtn);
  });

  copyJsonBtn.addEventListener("click", () => {
    if (!currentResult) return;
    copyToClipboard(JSON.stringify(currentResult.data, null, 2), "JSON copiado com sucesso.", copyJsonBtn);
  });

  function montarResumoCompleto(data, normalizedCnpj) {
    const cnaePrincipal = Ui.resolverCnaePrincipal(data);
    const linhas = [
      `Razão social: ${Utils.orNotInformed(data.razao_social)}`,
      `Nome fantasia: ${Utils.orNotInformed(data.nome_fantasia)}`,
      `CNPJ: ${Utils.formatCnpjDisplay(normalizedCnpj)}`,
      `Situação cadastral: ${Ui.resolverSituacaoCadastral(data)}`,
      `Natureza jurídica: ${Utils.orNotInformed(data.natureza_juridica)}`,
      `Porte: ${Utils.orNotInformed(data.porte)}`,
      `Capital social: ${Utils.formatCurrencyBRL(data.capital_social) || "Não informado"}`,
      `Data de abertura: ${Utils.formatDateBR(data.data_inicio_atividade) || "Não informada"}`,
      `Endereço: ${Ui.montarEnderecoTexto(data) || "Não informado"}`,
      `Telefone: ${Utils.formatPhone(null, data.ddd_telefone_1) || "Não informado"}`,
      `E-mail: ${Utils.orNotInformed(data.email)}`,
      `Atividade principal: ${cnaePrincipal ? (cnaePrincipal.descricao || cnaePrincipal.codigo) : "Não informado"}`,
    ];
    return linhas.join("\n");
  }

  function copyToClipboard(text, successMessage, buttonEl) {
    if (!text) {
      Ui.toast("Nada para copiar.", "error");
      return;
    }
    const done = () => {
      Ui.toast(successMessage);
      if (buttonEl) Ui.flashButton(buttonEl, "Copiado ✓");
    };
    const fail = () => Ui.toast("Não foi possível copiar. Copie manualmente.", "error");

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(fail);
    } else {
      try {
        const textarea = document.createElement("textarea");
        textarea.value = text;
        textarea.style.position = "fixed";
        textarea.style.opacity = "0";
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand("copy");
        document.body.removeChild(textarea);
        done();
      } catch (err) {
        fail();
      }
    }
  }

  // ---------- TEMA ----------

  const THEME_CYCLE = ["auto", "light", "dark"];
  const THEME_META = {
    auto: { icon: "🌓", label: "Automático" },
    light: { icon: "☀️", label: "Claro" },
    dark: { icon: "🌙", label: "Escuro" },
  };

  function applyTheme(theme) {
    if (theme === "auto") {
      document.documentElement.removeAttribute("data-theme");
    } else {
      document.documentElement.setAttribute("data-theme", theme);
    }
    themeIcon.textContent = THEME_META[theme].icon;
    themeLabel.textContent = THEME_META[theme].label;
    themeToggle.setAttribute("aria-label", `Tema atual: ${THEME_META[theme].label}. Clique para alternar.`);
  }

  themeToggle.addEventListener("click", () => {
    const current = Storage.getTheme();
    const nextIdx = (THEME_CYCLE.indexOf(current) + 1) % THEME_CYCLE.length;
    const next = THEME_CYCLE[nextIdx];
    Storage.setTheme(next);
    applyTheme(next);
  });

  // ---------- SERVICE WORKER (shell instalável, sem afetar as consultas) ----------

  function registerServiceWorker() {
    if (!("serviceWorker" in navigator)) return;
    // Evita erro em ambientes sem suporte (ex.: aberto via file://)
    if (location.protocol === "file:") return;
    window.addEventListener("load", () => {
      navigator.serviceWorker.register("sw.js").catch(() => {
        // Falha silenciosa: o app funciona normalmente sem o service worker.
      });
    });
  }

  // ---------- CONTADOR DE VISITAS (serviço de terceiros — hits.sh) ----------
  // Se o serviço externo cair ou bloquear a requisição, some com o badge
  // em vez de deixar o ícone de imagem quebrada no rodapé.

  function setupVisitCounterFallback() {
    const counterImg = document.querySelector(".visit-counter img");
    if (!counterImg) return;
    counterImg.addEventListener("error", () => {
      const wrapper = counterImg.closest(".visit-counter");
      if (wrapper) wrapper.hidden = true;
    }, { once: true });
  }

  // ---------- INICIALIZAÇÃO ----------

  function init() {
    applyTheme(Storage.getTheme());

    refreshRecents();
    refreshFavorites();

    Ui.showEmpty();
    input.focus();
    registerServiceWorker();
    setupVisitCounterFallback();
  }

  init();

})();
