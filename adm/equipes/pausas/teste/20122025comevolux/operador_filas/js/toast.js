/* ==========================================================
   toast.js — Sistema de Notificações (Toast)
   - Suporte a success / error / warning / info
   - Empilhamento automático
   - Clique para fechar
   - UX consistente com dashboard
========================================================== */

(function () {

  /* ======================================================
     CONTAINER ÚNICO
  ====================================================== */
  function getContainer() {
    let container = document.getElementById("toast-container");
    if (!container) {
      container = document.createElement("div");
      container.id = "toast-container";
      document.body.appendChild(container);
    }
    return container;
  }

  /* ======================================================
     ÍCONES POR TIPO
  ====================================================== */
  const icons = {
    success: "fa-circle-check",
    error: "fa-circle-xmark",
    warning: "fa-triangle-exclamation",
    info: "fa-circle-info"
  };

  /* ======================================================
     CRIAR TOAST
  ====================================================== */
  function criarToast(
    mensagem,
    tipo = "success",
    tempo = 3500
  ) {
    const container = getContainer();

    const toast = document.createElement("div");
    toast.className = `toast toast-${tipo}`;
    toast.innerHTML = `
      <i class="fas ${icons[tipo] || icons.success}"></i>
      <span>${mensagem}</span>
    `;

    container.appendChild(toast);

    // anima entrada
    requestAnimationFrame(() => {
      toast.classList.add("show");
    });

    // fechar manual
    toast.onclick = () => removerToast(toast);

    // auto remover
    if (tempo > 0) {
      setTimeout(() => removerToast(toast), tempo);
    }
  }

  function removerToast(toast) {
    if (!toast) return;
    toast.classList.remove("show");
    toast.classList.add("hide");

    setTimeout(() => toast.remove(), 300);
  }

  /* ======================================================
     API GLOBAL (COMPATÍVEL)
  ====================================================== */
  window.toastSucesso = msg => criarToast(msg, "success");
  window.toastErro    = msg => criarToast(msg, "error");
  window.toastAviso   = msg => criarToast(msg, "warning");
  window.toastInfo    = msg => criarToast(msg, "info");

})();
