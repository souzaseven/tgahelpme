/* =========================================================
   TOKEN EXPIRATION TIMER
========================================================= */

(function () {

  let remaining = Number(window.__TOKEN_REMAINING__ || 0);
  const el = document.getElementById('tokenExpireTimer');

  if (!el || remaining <= 0) return;

  function formatTime(sec) {
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;

    return `${String(h).padStart(2, '0')}:` +
           `${String(m).padStart(2, '0')}:` +
           `${String(s).padStart(2, '0')}`;
  }

  function update() {

    if (remaining <= 0) {
      el.textContent = '🔒 Sessão expirada';
      // Caminho relativo — funciona em qualquer ambiente sem precisar atualizar URL
      window.location.href = 'login.php';
      return;
    }

    el.textContent = `🔐 Expira em ${formatTime(remaining)}`;

    // ⚠️ Aviso visual quando faltar 2 minutos
    if (remaining <= 120) {
      el.style.color      = '#ef4444';
      el.style.fontWeight = '600';
    }

    remaining--;
  }

  update();
  setInterval(update, 1000);

})();