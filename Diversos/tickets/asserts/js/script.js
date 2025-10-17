document.addEventListener("DOMContentLoaded", () => {
  const abrirTicketsBtn = document.getElementById("abrirTickets");
  const statusMessage = document.getElementById("statusMessage");
  const progressBar = document.getElementById("progressBar");
  const progressContainer = document.querySelector(".progress-container");
  const loader = document.getElementById("loader");
  const toastContainer = document.getElementById("toastContainer");

  // Dark mode toggle
  document.getElementById("toggleDarkMode").addEventListener("click", () => {
    document.body.classList.toggle("dark");
  });

  // Restaura histórico
  document.getElementById("numeroInicial").value = localStorage.getItem("numeroInicial") || "";
  document.getElementById("numeroFinal").value = localStorage.getItem("numeroFinal") || "";

  // Feedback visual nos inputs
  ["numeroInicial", "numeroFinal"].forEach(id => {
    document.getElementById(id).addEventListener("input", e => {
      e.target.classList.remove("valid", "invalid");
      if (e.target.value !== "") {
        e.target.classList.add(isNaN(e.target.value) ? "invalid" : "valid");
      }
    });
  });

  abrirTicketsBtn.addEventListener("click", () => {
    const numeroInicial = parseInt(document.getElementById("numeroInicial").value);
    const numeroFinal = parseInt(document.getElementById("numeroFinal").value);

    // Salva no histórico
    localStorage.setItem("numeroInicial", document.getElementById("numeroInicial").value);
    localStorage.setItem("numeroFinal", document.getElementById("numeroFinal").value);

    // Reset
    statusMessage.className = "status";
    statusMessage.textContent = "";
    progressBar.style.width = "0%";
    progressContainer.style.display = "none";
    loader.style.display = "none";

    // Validações
    if (isNaN(numeroInicial)) return showMessage("Por favor, informe um número inicial válido.", "error");
    if (isNaN(numeroFinal)) return showMessage("Por favor, informe um número final válido.", "error");
    if (numeroInicial > numeroFinal) return showMessage("O número final deve ser maior ou igual ao inicial.", "error");

    const totalTabs = numeroFinal - numeroInicial + 1;

    if (totalTabs > 5) {
      if (!confirm(`Você está prestes a abrir ${totalTabs} abas. Deseja continuar?`)) {
        return showMessage("Operação cancelada.", "error");
      }
    }

    // Mostra loader e progresso
    loader.style.display = "block";
    progressContainer.style.display = "block";
    showMessage("Abrindo abas...", "success");

    let openedTabs = 0;
    const openTabWithDelay = (i) => {
      if (i > numeroFinal) {
        loader.style.display = "none";
        showMessage(`${openedTabs} abas foram abertas!`, "success");
        showToast("✅ Finalizado com sucesso!");
        return;
      }

      setTimeout(() => {
        const url = `https://atendimento.tgasistemas.com.br/Ticket/Edit/${i}`;
        const newWindow = window.open(url, "_blank");
        if (newWindow) openedTabs++;

        const percent = Math.round(((i - numeroInicial + 1) / totalTabs) * 100);
        progressBar.style.width = percent + "%";

        openTabWithDelay(i + 1);
      }, 200);
    };

    openTabWithDelay(numeroInicial);
  });

  function showMessage(msg, type) {
    statusMessage.textContent = msg;
    statusMessage.className = "status " + type;
  }

  function showToast(msg) {
    const toast = document.createElement("div");
    toast.className = "toast";
    toast.textContent = msg;
    toastContainer.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = "0";
      toast.style.transform = "translateX(100%)";
      setTimeout(() => toast.remove(), 500);
    }, 3000);
  }
});
