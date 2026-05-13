/* =========================================================
   API Helper - Fetch centralizado
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
