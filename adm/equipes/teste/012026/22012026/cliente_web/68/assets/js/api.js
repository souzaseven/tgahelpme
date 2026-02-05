/* =========================================================
   API Helper - Fetch centralizado
========================================================= */
/*
async function apiFetch(url, options = {}) {
  const config = {
    method: options.method || 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.__CSRF__ || ''
    },
    body: options.body ? JSON.stringify(options.body) : null
  };

  try {
    const res = await fetch(url, config);
    const data = await res.json();

    if (!res.ok || data.error) {
      throw new Error(data.message || 'Erro na requisição');
    }

    return data;
  } catch (err) {
    showToast(err.message, 'danger');
    throw err;
  }
}
*/
/* =========================================================
   API Helper - Fetch centralizado (com redirect automático)
========================================================= */

async function apiFetch(url, options = {}) {
  const config = {
    method: options.method || 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.__CSRF__ || ''
    },
    body: options.body ? JSON.stringify(options.body) : null
  };

  try {
    const res = await fetch(url, config);

    /* 🚨 Sessão ou CSRF expirado (HTTP) */
    if (res.status === 401 || res.status === 403) {
      redirectToLogin();
      throw new Error('Sessão expirada');
    }

    let data;
    try {
      data = await res.json();
    } catch (e) {
      redirectToLogin();
      throw new Error('Resposta inválida do servidor');
    }

    /* 🚨 CSRF inválido retornado pelo backend */
    if (
      data?.message &&
      typeof data.message === 'string' &&
      data.message.toLowerCase().includes('csrf')
    ) {
      redirectToLogin();
      throw new Error('Sessão expirada');
    }

    /* 🚨 Erro lógico da API */
    if (!res.ok || data.error || data.success === false) {
      throw new Error(data.message || 'Erro na requisição');
    }

    return data;

  } catch (err) {
    // evita toast repetido quando já está redirecionando
    if (!window.__REDIRECTING__) {
      showToast(err.message, 'danger');
    }
    throw err;
  }
}

/* =========================================================
   Redirect centralizado para login
========================================================= */
function redirectToLogin() {
  if (window.__REDIRECTING__) return;
  window.__REDIRECTING__ = true;

  // pequena pausa para UX
  setTimeout(() => {
    window.location.href =
      'https://tgameajuda.com/adm/cliente_web/login.html#google_vignette';
  }, 300);
}
