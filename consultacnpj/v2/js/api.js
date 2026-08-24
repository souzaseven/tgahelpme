/**
 * api.js
 * Comunicação com a BrasilAPI (endpoint de CNPJ), através do proxy PHP
 * do nosso próprio servidor (api/cnpj.php) — escolhido por rodar em
 * qualquer hospedagem compartilhada comum (ex.: HostGator), sem precisar
 * de um processo Node próprio. (Existe também um server.js/Express
 * equivalente, para quem tiver hospedagem com suporte a Node.)
 * Documentação da BrasilAPI: https://brasilapi.com.br/docs#tag/CNPJ
 * Endpoint real: GET https://brasilapi.com.br/api/cnpj/v1/{cnpj}
 * Endpoint chamado daqui: GET /api/cnpj.php?cnpj={cnpj} (mesma origem)
 *
 * Por quê passar pelo proxy em vez de chamar a BrasilAPI direto do
 * navegador: quando a BrasilAPI responde 429 (limite de requisições
 * excedido), a resposta não traz o header Access-Control-Allow-Origin —
 * o navegador bloqueia a leitura por CORS e mascara o problema real
 * (rate limit) como se fosse falha de CORS. Chamando nossa própria
 * origem, CORS deixa de ser um problema, e o servidor ainda mantém um
 * cache curto que reduz a chance de atingir esse limite.
 *
 * A BrasilAPI é um projeto público que agrega fontes oficiais e já
 * teve relatos de indisponibilidade/504 no endpoint de CNPJ — por isso
 * toda falha é tratada aqui e classificada em um tipo amigável para a UI.
 */

const Api = (() => {

  // Caminho relativo (sem "/" na frente): o site pode estar publicado numa
  // subpasta (ex.: tgameajuda.com/consultacnpj/), e um caminho absoluto
  // ("/api/cnpj.php") apontaria para a raiz do domínio, não para a subpasta.
  const BASE_URL = "api/cnpj.php";
  const TIMEOUT_MS = 15000;
  const MAX_ATTEMPTS = 2;
  const RETRY_DELAY_MS = 800;

  function sleep(ms) {
    return new Promise((resolve) => window.setTimeout(resolve, ms));
  }

  /**
   * Tipos de erro amigáveis para a UI decidir a mensagem exibida.
   */
  const ErrorType = {
    INVALID_FORMAT: "INVALID_FORMAT",
    NOT_FOUND: "NOT_FOUND",
    TIMEOUT: "TIMEOUT",
    NETWORK: "NETWORK",
    SERVER: "SERVER",
    RATE_LIMIT: "RATE_LIMIT",
    PARSE: "PARSE",
    UNKNOWN: "UNKNOWN",
  };

  class ApiError extends Error {
    constructor(type, message, details) {
      super(message);
      this.type = type;
      this.details = details || {};
    }
  }

  /**
   * Faz uma única tentativa de consulta (sem retry).
   * Retorna { data, meta } em caso de sucesso.
   * Lança ApiError em caso de falha, já classificado por tipo.
   */
  async function tentarUmaVez(normalizedCnpj) {
    const url = `${BASE_URL}?cnpj=${normalizedCnpj}`;
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), TIMEOUT_MS);

    const startedAt = performance.now();
    let response;

    try {
      response = await fetch(url, {
        method: "GET",
        headers: { Accept: "application/json" },
        signal: controller.signal,
      });
    } catch (err) {
      clearTimeout(timeoutId);
      const elapsedMs = Math.round(performance.now() - startedAt);
      if (err.name === "AbortError") {
        throw new ApiError(ErrorType.TIMEOUT, "Tempo de resposta excedido.", {
          url, method: "GET", elapsedMs,
        });
      }
      throw new ApiError(ErrorType.NETWORK, "Falha de rede ao consultar a API.", {
        url, method: "GET", elapsedMs, cause: err.message,
      });
    }

    clearTimeout(timeoutId);
    const elapsedMs = Math.round(performance.now() - startedAt);

    const meta = {
      url,
      method: "GET",
      status: response.status,
      elapsedMs,
      requestedAt: new Date().toISOString(),
    };

    if (response.status === 404) {
      throw new ApiError(ErrorType.NOT_FOUND, "Empresa não encontrada.", meta);
    }

    if (response.status === 400) {
      // A BrasilAPI valida o dígito verificador no servidor: um CNPJ com
      // 14 caracteres mas checksum inválido retorna 400, não 404.
      throw new ApiError(ErrorType.INVALID_FORMAT, "CNPJ inválido.", meta);
    }

    if (response.status === 429) {
      throw new ApiError(
        ErrorType.RATE_LIMIT,
        "Limite de consultas atingido. Tente novamente em instantes.",
        meta
      );
    }

    if (response.status >= 500) {
      throw new ApiError(ErrorType.SERVER, "A BrasilAPI está indisponível no momento.", meta);
    }

    if (!response.ok) {
      throw new ApiError(ErrorType.UNKNOWN, `Erro inesperado (HTTP ${response.status}).`, meta);
    }

    let data;
    try {
      data = await response.json();
    } catch (err) {
      throw new ApiError(ErrorType.PARSE, "A resposta da API veio em um formato inesperado.", meta);
    }

    return { data, meta };
  }

  /**
   * Consulta um CNPJ (já normalizado, sem máscara) na BrasilAPI, com uma
   * nova tentativa automática quando a falha for de indisponibilidade do
   * servidor (5xx) — o endpoint de CNPJ da BrasilAPI já teve relatos
   * conhecidos de instabilidade/504. Não tenta novamente em casos que não
   * mudariam com uma nova tentativa (CNPJ inválido, não encontrado etc.).
   *
   * @param {string} normalizedCnpj
   * @param {{ onRetry?: (tentativa: number) => void }} [options]
   */
  async function consultarCnpj(normalizedCnpj, options = {}) {
    const { onRetry } = options;
    let ultimoErro;

    for (let tentativa = 1; tentativa <= MAX_ATTEMPTS; tentativa++) {
      try {
        return await tentarUmaVez(normalizedCnpj);
      } catch (err) {
        ultimoErro = err;
        const podeTentarNovamente =
          tentativa < MAX_ATTEMPTS && err instanceof ApiError && err.type === ErrorType.SERVER;

        if (!podeTentarNovamente) throw err;

        if (onRetry) onRetry(tentativa);
        await sleep(RETRY_DELAY_MS);
      }
    }

    throw ultimoErro;
  }

  return {
    consultarCnpj,
    ApiError,
    ErrorType,
    BASE_URL,
  };

})();
