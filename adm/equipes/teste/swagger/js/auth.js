/**
 * auth.js
 * Fluxo de login: POST /v1/usuarios-api/login
 *
 * Resposta de sucesso (200): { ok: true, error: false, token: "..." }
 * Resposta de erro (401):    { ok: false, status: 401, error: true,
 *                              error_code: "E_UNAUTHORIZED", message: "..." }
 */

const TgaAuth = {
  async login(username, password) {
    const startedTime = new Date().toLocaleTimeString('pt-BR');

    const result = await TgaApi.request({
      method: 'POST',
      path: '/usuarios-api/login',
      body: { username, password },
      auth: false,
    });

    // Atualiza os viewers imediatamente (senha é mascarada dentro do UI).
    TgaUi.renderRequest(result.request);
    TgaUi.renderResponse(result);

    TgaUi.addHistoryEntry({
      time: startedTime,
      method: 'POST',
      path: '/usuarios-api/login',
      status: result.response ? result.response.status : null,
      durationMs: result.durationMs,
      full: result,
    });

    if (result.networkError) {
      TgaUi.showLoginFeedback('Não foi possível conectar à API. Verifique sua conexão.', 'error');
      return { success: false };
    }

    const { response } = result;
    const ok = response.status === 200 && response.body && response.body.ok === true && response.body.token;

    if (ok) {
      TgaStorage.saveToken(response.body.token);
      TgaUi.setSessionAuthenticated(response.body.token);
      TgaUi.showLoginFeedback('Autenticação realizada com sucesso.', 'ok');
      return { success: true, token: response.body.token };
    }

    // Falha de autenticação: garante que nenhum token velho fique salvo.
    TgaStorage.clearToken();
    TgaUi.setSessionCleared();

    const message = (response.body && response.body.message) || 'Usuário e/ou senha incorreto(s).';
    TgaUi.showLoginFeedback(message, 'error');
    return { success: false };
  },
};
