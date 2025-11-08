const ENDPOINT = "http://10.1.1.240:3000";

// Cache das equipes atualizado em segundo plano
let equipesCache = null;

// Atualiza o cache das equipes a cada 5 segundos
async function atualizarEquipesPeriodicamente() {
    try {
        const res = await fetch(`${ENDPOINT}/equipes`);
        const dados = await res.json();
        equipesCache = dados;
        console.log("🔄 Equipes atualizadas no cache do background");
    } catch (err) {
        console.error("❌ Erro ao buscar equipes no polling:", err);
    }
}

// Inicia o polling
atualizarEquipesPeriodicamente(); // Chamada inicial
setInterval(atualizarEquipesPeriodicamente, 5000); // Depois a cada 5s

// Recebe mensagens do content.js
chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
    if (msg.tipo === "obter_equipes") {
        if (equipesCache) {
            sendResponse({ sucesso: true, equipes: equipesCache });
        } else {
            sendResponse({ sucesso: false, erro: "Nenhum dado em cache ainda." });
        }
        return true;
    }
    if (msg.tipo === "entrar_pausa") {
        fetch(`${ENDPOINT}/entrar_pausa`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ nome: msg.nome, tipo: msg.tipoPausa }) // <-- aqui o nome mudou
        })
            .then(res => res.json())
            .then(dados => {
                console.log(`☕ ${msg.nome} entrou em pausa (${msg.tipoPausa})`);
                atualizarEquipesPeriodicamente();
                sendResponse({ sucesso: true, dados });
            })
            .catch(err => {
                console.error("❌ Erro ao entrar em pausa:", err);
                sendResponse({ sucesso: false });
            });
        return true;
    }

    if (msg.tipo === "sair_pausa") {
        fetch(`${ENDPOINT}/sair_pausa`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ nome: msg.nome })
        })
            .then(res => res.json())
            .then(dados => {
                console.log(`🔁 ${msg.nome} saiu da pausa`);
                atualizarEquipesPeriodicamente(); // Atualiza o cache após ação
                sendResponse({ sucesso: true, dados });
            })
            .catch(err => {
                console.error("❌ Erro ao sair da pausa:", err);
                sendResponse({ sucesso: false });
            });
        return true;
    }
});
