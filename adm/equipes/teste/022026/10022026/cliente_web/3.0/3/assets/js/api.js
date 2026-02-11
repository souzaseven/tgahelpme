/* =========================================================
   API Helper — Fetch centralizado
   
   Responsabilidades:
    - Injeta o token CSRF (window.__CSRF__) em toda requisição
    - Detecta sessão/CSRF expirado (HTTP 401/403 ou mensagem do backend)
      e redireciona para login.php automaticamente
    - Exibe toast de erro para falhas lógicas da API
    - Evita toasts duplicados quando já está redirecionando
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

  // Pequena pausa para o toast de "Sessão expirada" ser lido antes do redirect.
  // Uso caminho relativo para que funcione em qualquer ambiente (dev, prod, subpasta).
  setTimeout(() => {
    window.location.href = 'login.php';
  }, 300);
}