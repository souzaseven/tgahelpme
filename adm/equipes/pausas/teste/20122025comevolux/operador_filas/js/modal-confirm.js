/* ==========================================================
   modal-confirm.js — Confirmação de ações críticas
   - Modal reutilizável
   - Overlay + ESC
   - Prevenção de múltiplas instâncias
========================================================== */

window.confirmarAcao = function (mensagem, onConfirm) {

  // Remove modal existente (evita duplicação)
  const existente = document.querySelector(".modal-confirm");
  if (existente) existente.remove();

  // Cria overlay
  const modal = document.createElement("div");
  modal.className = "modal-confirm";
  modal.tabIndex = -1;

  modal.innerHTML = `
    <div class="modal-overlay"></div>

    <div class="modal-box" role="dialog" aria-modal="true">
      <h3>Confirmação</h3>
      <p>${mensagem}</p>

      <div class="modal-actions">
        <button class="btn btn-secondary btn-cancelar">
          Cancelar
        </button>

        <button class="btn btn-primary btn-confirmar">
          Confirmar
        </button>
      </div>
    </div>
  `;

  document.body.appendChild(modal);

  const btnCancelar  = modal.querySelector(".btn-cancelar");
  const btnConfirmar = modal.querySelector(".btn-confirmar");
  const overlay      = modal.querySelector(".modal-overlay");

  // =========================
  // FECHAR MODAL
  // =========================
  const fechar = () => {
    modal.classList.add("closing");
    setTimeout(() => modal.remove(), 150);
    document.removeEventListener("keydown", escListener);
  };

  // =========================
  // CONFIRMAR
  // =========================
  btnConfirmar.onclick = () => {
    fechar();
    if (typeof onConfirm === "function") {
      onConfirm();
    }
  };

  // =========================
  // CANCELAR
  // =========================
  btnCancelar.onclick = fechar;
  overlay.onclick = fechar;

  // =========================
  // ESC
  // =========================
  const escListener = (e) => {
    if (e.key === "Escape") fechar();
  };

  document.addEventListener("keydown", escListener);

  // =========================
  // FOCO AUTOMÁTICO
  // =========================
  setTimeout(() => btnConfirmar.focus(), 50);
};
