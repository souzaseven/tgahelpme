function detectarEquipe(nome, equipes) {
  for (const [equipe, membros] of Object.entries(equipes)) {
    if (membros.some(m => m.nome === nome)) {
      return equipe;
    }
  }
  return null;
}

chrome.tabs.query({ active: true, currentWindow: true }, tabs => {
  const tabId = tabs[0].id;

  // 1. Pega o nome do usuário logado
  chrome.scripting.executeScript({
    target: { tabId },
    func: () => {
      const el = document.querySelector("span.agent-name");
      return el ? el.innerText.trim() : null;
    }
  }, (results) => {
    const nomeUsuario = results[0].result;
    if (!nomeUsuario) return;

    // 2. Pega o objeto de equipes do content.js
    chrome.scripting.executeScript({
      target: { tabId },
      func: () => window.equipes
    }, (res) => {
      const equipes = res[0].result;
      const equipeNome = detectarEquipe(nomeUsuario, equipes);
      if (!equipeNome) return;

      const membros = equipes[equipeNome];

      // 3. Exibe o nome do usuário e equipe
      document.getElementById("nome").textContent = nomeUsuario;
      document.getElementById("equipe").textContent = equipeNome;

      // 4. Renderiza os checkboxes e selects
      membros.forEach(membro => {
        const div = document.createElement("div");
        div.style.marginTop = "5px";

        const label = document.createElement("label");
        label.textContent = membro.nome + ": ";

        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";

        const select = document.createElement("select");
        ["lanche", "almoco", "outro", "null"].forEach(tipo => {
          const opt = document.createElement("option");
          opt.value = tipo;
          opt.text = tipo;
          select.appendChild(opt);
        });

        // Obter status atual
        chrome.scripting.executeScript({
          target: { tabId },
          args: [membro.nome],
          func: (nome) => {
            const equipe = Object.values(window.equipes).find(eq => eq.find(m => m.nome === nome));
            const membro = equipe?.find(m => m.nome === nome);
            return {
              empausa: membro?.empausa || false,
              tipo: membro?.tipo || "null"
            };
          }
        }, (resCheck) => {
          const data = resCheck[0].result;
          checkbox.checked = data.empausa;
          select.value = data.tipo || "null";
        });

        // Atualizar content.js quando houver mudança
        const atualizarEstado = () => {
          const novoStatus = checkbox.checked;
          const novoTipo = select.value === "null" ? null : select.value;

          chrome.scripting.executeScript({
            target: { tabId },
            args: [membro.nome, novoStatus, novoTipo],
            func: (nome, novoStatus, tipo) => {
              for (const membros of Object.values(window.equipes)) {
                const membro = membros.find(m => m.nome === nome);
                if (membro) {
                  membro.empausa = novoStatus;
                  membro.tipo = tipo;
                  console.log(`🔁 ${nome} agora está ${novoStatus ? `em pausa (${tipo})` : "ativo"}`);
                }
              }

              if (typeof window.verificarEquipeAntesDaPausa === "function") {
                window.verificarEquipeAntesDaPausa();
              }
            }
          });
        };

        checkbox.addEventListener("change", atualizarEstado);
        select.addEventListener("change", atualizarEstado);

        label.appendChild(checkbox);
        div.appendChild(label);
        div.appendChild(select);
        document.body.appendChild(div);
      });
    });
  });
});