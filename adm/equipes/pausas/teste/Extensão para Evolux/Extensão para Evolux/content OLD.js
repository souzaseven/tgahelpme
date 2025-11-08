// window.equipes = {
//     equipeDaniel: [
//         { nome: "user 2", empausa: false, tipo: null },
//         { nome: "user 3", empausa: false, tipo: null },
//         { nome: "user 1", empausa: false, tipo: null },
//     ],
//     equipeAlex: [
//         { nome: "user 4", empausa: false, tipo: null },
//         { nome: "user 5", empausa: false, tipo: null },
//         { nome: "user 6", empausa: false, tipo: null },
//     ],
//     equipeWillian: [
//         { nome: "Brenno Kayan Ribeiro de Souza", empausa: false, tipo: null },
//         { nome: "user 5", empausa: true, tipo: "lanche" },
//         { nome: "user 6", empausa: true, tipo: "lanche" },
//     ]
// };



async function carregarEquipesDoServidor() {
    try {
        const res = await fetch("http://<SEU_IP_LOCAL>:3000/equipes");
        const dados = await res.json();
        window.equipes = dados;
        console.log("📦 Equipes carregadas do backend");
    } catch (err) {
        console.error("❌ Erro ao carregar equipes do backend:", err);
    }
}
// 1. Capturar nome do usuário logado
function obterNomeUsuario() {
    const span = document.querySelector("span.agent-name");
    if (span) {
        const nome = span.innerText.trim();
        console.log("Nome detectado:", nome);
        return nome;
    } else {
        console.warn("Elemento 'span.agent-name' não encontrado.");
        return null;
    }
}

// 2. Descobrir a equipe a partir do nome
function obterEquipeDoUsuario(nome) {
    for (const [equipe, membros] of Object.entries(window.equipes)) {
        if (membros.find(m => m.nome === nome)) {
            return equipe;
        }
    }
    return null;
}

// 3. Contar quantas pessoas estão em pausa na equipe
function contarPausadosNaEquipe(equipe) {
    const membros = window.equipes[equipe] || [];
    return membros.filter(m => m.empausa).length;
}

// 4. Verificar e bloquear ou liberar o botão de pausa principal
function verificarEquipeAntesDaPausa() {
    const nomeUsuario = obterNomeUsuario();
    if (!nomeUsuario) return;

    const equipe = obterEquipeDoUsuario(nomeUsuario);
    if (!equipe) {
        console.warn("Equipe do usuário não encontrada.");
        return;
    }

    const pausados = contarPausadosNaEquipe(equipe);
    console.log(`Equipe: ${equipe}, Pausados: ${pausados}`);

    const btn = document.querySelector("button.btn-disabled.tour-pause-button.pause-button");
    if (btn) {
        // ❌ NÃO bloquear mais o botão principal, apenas dentro do modal
        btn.removeAttribute("disabled");
        btn.style.opacity = "1";
        btn.style.cursor = "";
        console.log("ℹ️ Botão principal de pausa sempre liberado; bloqueio ocorrerá dentro do modal.");
    }
}

function monitorarBotaoDePausa() {
    const btn = document.querySelector("button.btn-disabled.tour-pause-button.pause-button");

    if (btn && !btn.dataset.listenerAdicionado) {
        console.log("🟢 Botão de pausa detectado. Verificando restrições...");
        verificarEquipeAntesDaPausa();

        btn.addEventListener("click", () => {
            verificarEquipeAntesDaPausa();
            setTimeout(verificarEquipeAntesDaPausa, 200);
        });

        btn.dataset.listenerAdicionado = "true";
    }
}

// 5. Detectar abertura do modal de pausa e aplicar regras nos botões de lanche
const observerModal = new MutationObserver(mutations => {
    for (const mutation of mutations) {
        for (const node of mutation.addedNodes) {
            if (node.nodeType === 1 && node.matches(".modal-dialog")) {
                console.log("🟠 Modal de pausa aberto");
                setTimeout(() => verificarPausasDisponiveis(node), 300);
            }
        }
    }
});

observerModal.observe(document.body, {
    childList: true,
    subtree: true
});

function verificarPausasDisponiveis(modalNode) {
    const nomeUsuario = obterNomeUsuario();
    const equipe = obterEquipeDoUsuario(nomeUsuario);
    if (!equipe) return;

    const membros = window.equipes[equipe];
    const emLanche = membros.filter(m => m.empausa && m.tipo === "lanche").length;

    const botoes = modalNode.querySelectorAll("button.btn-pause-list");

    botoes.forEach(btn => {
        const texto = btn.textContent.trim().toLowerCase();
        const ehLanche = texto.includes("lanche manhã") || texto.includes("lanche tarde");

        if (ehLanche) {
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

            // Evitar adicionar múltiplos listeners
            if (!btn.dataset.listenerAdicionado) {
                btn.addEventListener("click", () => {
                    const membro = membros.find(m => m.nome === nomeUsuario);
                    if (membro) {
                        membro.empausa = true;
                        membro.tipo = "lanche";
                        console.log(`☕ ${nomeUsuario} iniciou pausa: ${texto}`);
                        // Aqui você pode enviar um HTTP request também
                    }
                });
                btn.dataset.listenerAdicionado = "true";
            }
        }
    });
}

function monitorarBotaoDespausar() {
    const botaoDespausar = document.querySelector("button.unpause-button");

    if (botaoDespausar && !botaoDespausar.dataset.listenerAdicionado) {
        botaoDespausar.addEventListener("click", () => {
            const nomeUsuario = obterNomeUsuario();
            const equipe = obterEquipeDoUsuario(nomeUsuario);
            if (!equipe) return;

            const membro = window.equipes[equipe].find(m => m.nome === nomeUsuario);
            if (membro) {
                membro.empausa = false;
                membro.tipo = null;
                console.log(`🔁 ${nomeUsuario} saiu da pausa`);
                // Aqui também poderia enviar um HTTP request, se quiser
            }
        });

        botaoDespausar.dataset.listenerAdicionado = "true";
    }
}


// 6. Observar alterações no DOM para reaplicar lógica do botão principal
const observer = new MutationObserver(() => {
    monitorarBotaoDePausa();
});

observer.observe(document.body, {
    childList: true,
    subtree: true
});

// 🔁 Observar alterações para detectar quando o botão de despausa aparece
const observerDespausar = new MutationObserver(() => {
    monitorarBotaoDespausar();
});

observerDespausar.observe(document.body, {
    childList: true,
    subtree: true
});

monitorarBotaoDePausa();

// Tornar funções visíveis globalmente
window.verificarEquipeAntesDaPausa = verificarEquipeAntesDaPausa;
window.obterNomeUsuario = obterNomeUsuario;
