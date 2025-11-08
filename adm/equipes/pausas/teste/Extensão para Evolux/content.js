async function carregarEquipesDoServidor() {
    return new Promise((resolve) => {
        chrome.runtime.sendMessage({ tipo: "obter_equipes" }, (resposta) => {
            if (resposta?.sucesso) {
                window.equipes = resposta.equipes;
                console.log("📦 Equipes carregadas do background");
            } else {
                console.error("❌ Falha ao carregar equipes do background");
            }
            resolve();
        });
    });
}
function monitorarBotaoDePausa() {
    const btn = document.querySelector("button.btn-disabled.tour-pause-button.pause-button");

    if (btn && !btn.dataset.listenerAdicionado) {
        console.log("🟢 Botão de pausa detectado.");
        verificarEquipeAntesDaPausa();

        btn.addEventListener("click", () => {
            verificarEquipeAntesDaPausa();
            setTimeout(verificarEquipeAntesDaPausa, 200);
        });

        btn.dataset.listenerAdicionado = "true";
    }
}
function obterNomeUsuario() {
    const span = document.querySelector("span.agent-name");
    if (span) {
        const nome = span.innerText.trim();
        console.log("👤 Nome detectado:", nome);
        return nome;
    }
    console.warn("⚠️ Elemento 'span.agent-name' não encontrado.");
    return null;
}

function obterEquipeDoUsuario(nome) {
    for (const [equipe, membros] of Object.entries(window.equipes || {})) {
        if (membros.find(m => m.nome === nome)) {
            return equipe;
        }
    }
    return null;
}

function contarPausadosNaEquipe(equipe) {
    const membros = window.equipes[equipe] || [];
    return membros.filter(m => m.empausa).length;
}

// function verificarEquipeAntesDaPausa() {
//     const nomeUsuario = obterNomeUsuario();
//     if (!nomeUsuario) return;

//     const equipe = obterEquipeDoUsuario(nomeUsuario);
//     if (!equipe) {
//         console.warn("⚠️ Equipe do usuário não encontrada.");
//         return;
//     }

//     const pausados = contarPausadosNaEquipe(equipe);
//     console.log(`🛑 Equipe: ${equipe}, Pausados: ${pausados}`);

//     const btn = document.querySelector("button.btn-disabled.tour-pause-button.pause-button");
//     if (btn) {
//         btn.removeAttribute("disabled");
//         btn.style.opacity = "1";
//         btn.style.cursor = "";
//         console.log("ℹ️ Botão principal de pausa sempre liberado (validação no modal).");
//     }
// }

async function verificarEquipeAntesDaPausa() {
    await carregarEquipesDoServidor(); // ⬅️ Adicione isto

    const nomeUsuario = obterNomeUsuario();
    if (!nomeUsuario) return;

    const equipe = obterEquipeDoUsuario(nomeUsuario);
    if (!equipe) {
        console.warn("⚠️ Equipe do usuário não encontrada.");
        return;
    }

    const pausados = contarPausadosNaEquipe(equipe);
    console.log(`🛑 Equipe: ${equipe}, Pausados: ${pausados}`);

    const btn = document.querySelector("button.btn-disabled.tour-pause-button.pause-button");
    if (btn) {
        btn.removeAttribute("disabled");
        btn.style.opacity = "1";
        btn.style.cursor = "";
        console.log("ℹ️ Botão principal de pausa sempre liberado (validação no modal).");
    }
}





function monitorarBotaoConfirmarPausa(modalNode) {
    const btnConfirmar = modalNode.querySelector("button.confirm-pause-button");

    if (btnConfirmar && !btnConfirmar.dataset.listenerAdicionado) {
        btnConfirmar.addEventListener("click", async () => {
            const nomeUsuario = obterNomeUsuario();
            if (!nomeUsuario) return;

            chrome.runtime.sendMessage({
                tipo: "entrar_pausa",
                nome: nomeUsuario,
                tipoPausa: "lanche" // ou outro tipo, se quiser inferir com base na seleção do modal
            }, async (res) => {
                if (res?.sucesso) {
                    console.log(`☕ ${nomeUsuario} iniciou pausa`);
                    await carregarEquipesDoServidor();
                } else {
                    console.error("❌ Erro ao entrar em pausa");
                }
            });
        });

        btnConfirmar.dataset.listenerAdicionado = "true";
        console.log("✅ Listener adicionado ao botão de confirmar pausa");
    }
}


async function verificarPausasDisponiveis(modalNode) {
    await carregarEquipesDoServidor(); // ⬅️ Adicione isto

    const nomeUsuario = obterNomeUsuario();
    const equipe = obterEquipeDoUsuario(nomeUsuario);
    if (!equipe) return;

    const membros = window.equipes[equipe];
    const emLanche = membros.filter(m => m.empausa && m.tipo === "lanche").length;
// function verificarPausasDisponiveis(modalNode) {
//     const nomeUsuario = obterNomeUsuario();
//     const equipe = obterEquipeDoUsuario(nomeUsuario);
//     if (!equipe) return;

//     const membros = window.equipes[equipe];
//     const emLanche = membros.filter(m => m.empausa && m.tipo === "lanche").length;

    const botoes = modalNode.querySelectorAll("button.btn-pause-list");

    botoes.forEach(btn => {
        const texto = btn.textContent.trim().toLowerCase();
        const ehLanche = texto.includes("lanche manhã") || texto.includes("lanche tarde");

        if (!ehLanche) return;

        if (emLanche >= 2) {
            btn.setAttribute("disabled", "disabled");
            btn.style.opacity = "0.5";
            btn.style.cursor = "not-allowed";
            console.log(`🚫 Botão "${texto}" desabilitado`);
        } else {
            btn.removeAttribute("disabled");
            btn.style.opacity = "1";
            btn.style.cursor = "";
            console.log(`✅ Botão "${texto}" liberado`);
        }

        if (!btn.dataset.listenerAdicionado) {
            btn.addEventListener("click", () => {
                chrome.runtime.sendMessage({
                    tipo: "entrar_pausa",
                    nome: nomeUsuario,
                    tipoPausa: "lanche"
                }, async (res) => {
                    if (res?.sucesso) {
                        console.log(`☕ ${nomeUsuario} iniciou pausa`);
                        await carregarEquipesDoServidor();
                    } else {
                        console.error("❌ Erro ao entrar em pausa");
                    }
                });
            });
            btn.dataset.listenerAdicionado = "true";
        }
    });
}

function monitorarBotaoDespausar() {
    const botaoDespausar = document.querySelector("button.unpause-button");

    if (botaoDespausar && !botaoDespausar.dataset.listenerAdicionado) {
        botaoDespausar.addEventListener("click", () => {
            const nomeUsuario = obterNomeUsuario();
            if (!nomeUsuario) return;

            chrome.runtime.sendMessage({
                tipo: "sair_pausa",
                nome: nomeUsuario
            }, async (res) => {
                if (res?.sucesso) {
                    console.log(`🔁 ${nomeUsuario} saiu da pausa`);
                    await carregarEquipesDoServidor();
                } else {
                    console.error("❌ Erro ao sair da pausa");
                }
            });
        });

        botaoDespausar.dataset.listenerAdicionado = "true";
    }
}

const observerModal = new MutationObserver(mutations => {
    for (const mutation of mutations) {
        for (const node of mutation.addedNodes) {
            if (node.nodeType === 1 && node.matches(".modal-dialog")) {
                console.log("🟠 Modal de pausa aberto");
                setTimeout(() => verificarPausasDisponiveis(node), 300);
                setTimeout(() => monitorarBotaoConfirmarPausa(node), 400); // ✅ novo aqui
            }
        }
    }
});


observerModal.observe(document.body, { childList: true, subtree: true });

const observer = new MutationObserver(() => {
    monitorarBotaoDePausa();
});
observer.observe(document.body, { childList: true, subtree: true });

const observerDespausar = new MutationObserver(() => {
    monitorarBotaoDespausar();
});
observerDespausar.observe(document.body, { childList: true, subtree: true });

// Início da execução
carregarEquipesDoServidor().then(() => {
    monitorarBotaoDePausa();
});

// Para debugging
window.verificarEquipeAntesDaPausa = verificarEquipeAntesDaPausa;
window.obterNomeUsuario = obterNomeUsuario;
