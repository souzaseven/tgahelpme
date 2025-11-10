document.addEventListener("DOMContentLoaded", () => {
  const modal = document.getElementById("modalOperador");
  const nomeInput = document.getElementById("nomeOperador");
  const senhaWrap = document.getElementById("senhaContainer");
  const senhaInput = document.getElementById("senhaOperador");
  const btnLogin = document.getElementById("btnLogin");
  const btnLogout = document.getElementById("btnLogout");

  // Mostrar/ocultar campo de senha (apenas se "anderson" minúsculo)
  nomeInput.addEventListener("input", () => {
    senhaWrap.style.display = (nomeInput.value.trim().toLowerCase() === "anderson") ? "block" : "none";
  });

  // Se não há operador salvo, abre modal
  const operadorSalvo = localStorage.getItem("operador_nome");
  if (!operadorSalvo) {
    modal.classList.add("ativo");
  } else {
    // mostra botão sair
    if (btnLogout) btnLogout.style.display = "flex";
  }

  // Login
  btnLogin.addEventListener("click", async () => {
    const nome = nomeInput.value.trim();
    const senha = senhaInput.value.trim();

    if (!nome) {
      alert("Digite seu nome para continuar.");
      return;
    }

    try {
      // faz login no backend
      const resp = await fetch("php/login_operador.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({ nome, senha })
      });
      const data = await resp.json();

      if (!data.success) {
        alert(data.error || "Erro ao autenticar.");
        return;
      }

      // Persistência local
      localStorage.setItem("operador_nome", data.admin ? "Anderson" : nome);
      if (data.admin) localStorage.setItem("modo_admin", "true");
      else localStorage.removeItem("modo_admin");

      modal.classList.remove("ativo");
      if (btnLogout) btnLogout.style.display = "flex";
      location.reload();
    } catch (e) {
      console.error(e);
      alert("Falha na comunicação com o servidor.");
    }
  });

  // Logout (derruba sessão + storage)
  if (btnLogout) {
    btnLogout.addEventListener("click", async () => {
      if (!confirm("Deseja desconectar e trocar de usuário?")) return;

      try {
        await fetch("php/logout.php", { cache: "no-store" });
      } catch { /* ignora erro de rede */ }

      localStorage.removeItem("operador_nome");
      localStorage.removeItem("modo_admin");
      // força redigitar no modal
      modal.classList.add("ativo");
      btnLogout.style.display = "none";
      // recarrega pra resetar telas/contadores
      location.reload();
    });
  }
});
