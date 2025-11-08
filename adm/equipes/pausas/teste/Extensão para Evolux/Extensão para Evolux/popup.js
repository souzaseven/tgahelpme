function detectarEquipe(nome, equipes) {
  for (const [equipe, membros] of Object.entries(equipes || {})) {
    if (membros.some(m => m.nome === nome)) {
      return equipe;
    }
  }
  return null;
}

function atualizarPopup(nomeUsuario, equipes) {
  const equipeNome = detectarEquipe(nomeUsuario, equipes);
  const membros = equipeNome ? equipes[equipeNome] : [];

  // Atualiza a UI
  document.getElementById("nome").textContent = nomeUsuario || "Desconhecido";
  document.getElementById("equipe").textContent = equipeNome || "Não encontrada";

  const listaDiv = document.createElement("div");
  listaDiv.style.marginTop = "10px";
  listaDiv.innerHTML = "<div class='titulo'>Em pausa:</div>";

  membros.forEach(membro => {
    if (membro.empausa) {
      const item = document.createElement("div");
      item.textContent = `${membro.nome} (${membro.tipo || "sem tipo"})`;
      listaDiv.appendChild(item);
    }
  });

  document.body.appendChild(listaDiv);
}

chrome.tabs.query({ active: true, currentWindow: true }, tabs => {
  const tabId = tabs[0].id;

  // 1. Obter o nome do usuário da página
  chrome.scripting.executeScript({
    target: { tabId },
    func: () => {
      const el = document.querySelector("span.agent-name");
      return el ? el.innerText.trim() : null;
    }
  }, ([resultNome]) => {
    const nomeUsuario = resultNome?.result;

    if (!nomeUsuario) {
      document.getElementById("nome").textContent = "Não encontrado";
      document.getElementById("equipe").textContent = "N/A";
      return;
    }

    // 2. Obter o estado das equipes do content.js
    chrome.scripting.executeScript({
      target: { tabId },
      func: () => window.equipes
    }, ([resultEquipes]) => {
      const equipes = resultEquipes?.result;

      if (!equipes || Object.keys(equipes).length === 0) {
        document.getElementById("equipe").textContent = "Equipe não carregada";
        return;
      }

      atualizarPopup(nomeUsuario, equipes);
    });
  });
});
