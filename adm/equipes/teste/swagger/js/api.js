/**
 * api.js
 * Camada fina sobre fetch(): monta a requisição, mede o tempo e devolve
 * um objeto padronizado { request, response } para a UI exibir.
 *
 * Nenhum header é inventado aqui: o formato de autenticação (header
 * "authorization", contendo o token puro devolvido pelo login) segue
 * exatamente o que a documentação Swagger da TGA descreve para a rota
 * POST /v1/usuarios-api/login.
 */

const TGA_API_BASE = 'https://api.tgasistemas.io/v1';

const TgaApi = {
  /**
   * Executa uma chamada HTTP e retorna um resultado estruturado,
   * nunca lançando exceção para erros HTTP (isso é tratado pela UI).
   *
   * @param {Object} opts
   * @param {string} opts.method
   * @param {string} opts.path        Caminho relativo, ex: "/usuarios-api/login"
   * @param {Object} [opts.body]      Corpo (será serializado em JSON)
   * @param {boolean} [opts.auth]     Se true, envia o header authorization com o token de sessão
   */
  async request({ method, path, body, auth = false }) {
    const url = `${TGA_API_BASE}${path}`;

    const headers = { 'Content-Type': 'application/json' };
    if (auth) {
      const token = TgaStorage.getToken();
      // Confirmado empiricamente contra a API real: o token precisa do
      // prefixo "Bearer " (scheme bearerAuth do spec OpenAPI). Sem o
      // prefixo, o servidor responde como se nenhum token tivesse sido
      // enviado ("chave da API não informada") em vez de "token inválido".
      if (token) headers['authorization'] = `Bearer ${token}`;
    }

    const fetchOptions = { method, headers };
    if (body !== undefined) fetchOptions.body = JSON.stringify(body);

    const requestSnapshot = {
      method,
      url,
      headers: { ...headers },
      body: body !== undefined ? body : null,
    };

    const startedAt = performance.now();
    let response, data, networkError = null;

    try {
      response = await fetch(url, fetchOptions);
    } catch (err) {
      networkError = err;
    }

    const durationMs = Math.round(performance.now() - startedAt);

    if (networkError) {
      return {
        request: requestSnapshot,
        response: null,
        durationMs,
        networkError,
      };
    }

    const contentType = response.headers.get('content-type') || '';
    try {
      data = contentType.includes('application/json')
        ? await response.json()
        : await response.text();
    } catch (err) {
      data = null;
    }

    return {
      request: requestSnapshot,
      response: {
        status: response.status,
        statusText: response.statusText,
        headers: Object.fromEntries(response.headers.entries()),
        body: data,
      },
      durationMs,
      networkError: null,
    };
  },

  /**
   * Testa se a API está acessível, sem exigir autenticação.
   *
   * Usa GET puro (sem headers customizados) contra /status: por não ser um
   * método "unsafe" nem levar headers fora da lista segura do CORS, o
   * navegador não dispara preflight — evita o problema de usar OPTIONS como
   * método da requisição (o servidor não lista OPTIONS em
   * access-control-allow-methods, então um fetch com method:"OPTIONS" falha
   * no próprio preflight, mesmo com a API 100% online). Qualquer resposta
   * HTTP (mesmo 401, já que /status exige token) confirma que a API está no
   * ar; só erro de rede é tratado como offline.
   */
  async testConnectivity() {
    try {
      const startedAt = performance.now();
      const response = await fetch(`${TGA_API_BASE}/status`, {
        method: 'GET',
      });
      const durationMs = Math.round(performance.now() - startedAt);
      return { online: true, status: response.status, durationMs };
    } catch (err) {
      return { online: false, error: err.message };
    }
  },
};
