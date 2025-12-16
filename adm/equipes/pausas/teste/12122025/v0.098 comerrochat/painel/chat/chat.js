/*==========================================================
   chat.js — Chat individual (UX PROFISSIONAL)
==========================================================*/

console.log("%c[CHAT] Listener global iniciado", "color:#00eaff;font-weight:bold;");

let intervaloChat = null;
let chatAberto = false;
let mensagensCache = [];
let ultimoIdNotificado = 0;
let naoLidasPorContato = {}; // { contatoId: quantidade }


/*==========================================================
  OPERADOR LOGADO
==========================================================*/
function getOperador() {
    try {
        return JSON.parse(localStorage.getItem("tga_operador"));
    } catch {
        return null;
    }
}

/*==========================================================
  CONTROLE DE LEITURA (LOCAL)
==========================================================*/
function getLastReadChatId(operadorId) {
    return Number(localStorage.getItem(`chat_last_read_${operadorId}`) || 0);
}

function setLastReadChatId(operadorId, id) {
    localStorage.setItem(`chat_last_read_${operadorId}`, id);
}
/*==========================================================
  ABRIR / FECHAR CHAT
==========================================================*/
window.abrirChat = function () {
    const overlay = document.getElementById("chatOverlay");
    if (!overlay) return;

    overlay.classList.remove("hidden");
    chatAberto = true;

    // Marcar como lido ao abrir o chat, se houver contato selecionado
    const contatoId = Number(document.getElementById("chatDestino")?.value);
    if (contatoId) {
        marcarComoLidoContato(contatoId);
    }

    esconderPreview();

    // ✅ Adiciona o listener de Enter no campo (evita múltiplos)
    const campo = document.getElementById("chatTexto");
    if (campo && !campo.dataset.listenerAdded) {
        campo.addEventListener("keydown", e => {
            if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                enviarChat();
            }
        });
        campo.dataset.listenerAdded = "true"; // marca para não duplicar listener
    }
};

/*==========================================================
  MARCAR COMO LIDO CONTATO (LOCAL + BACKEND)
==========================================================*/
async function marcarComoLidoContato(contatoId) {
    const op = getOperador();
    if (!op || !mensagensCache.length) return;

    // Pega o último ID da conversa com esse contato
    const ultimas = mensagensCache.filter(m =>
        Number(m.de_id) === contatoId &&
        Number(m.para_id) === Number(op.id)
    );

    if (!ultimas.length) return;

    const ultimoId = Math.max(...ultimas.map(m => Number(m.id)));

    // 1️⃣ Atualiza LOCAL
    naoLidasPorContato[contatoId] = 0;
    setLastReadChatId(op.id, ultimoId);
    carregarDestinatarios();
    atualizarBadgeChat(0);

    // 2️⃣ Atualiza BACKEND (sem quebrar o chat se falhar)
    try {
        await fetch("chat/marcar_lido.php", {
            method: "POST",
            body: new URLSearchParams({
                operador_id: op.id,
                contato_id: contatoId,
                ultimo_id: ultimoId
            })
        });
    } catch (e) {
        console.warn("[CHAT] Falha ao registrar leitura no backend:", e);
    }
}

/*==========================================================
  INIT
==========================================================*/
document.addEventListener("DOMContentLoaded", async () => {
    const op = getOperador();
    if (!op) return;

    await carregarMensagens();
    await carregarDestinatarios();

    intervaloChat = setInterval(carregarMensagens, 2000);

    // Não registra aqui — registra quando o chat abre

    // Listener para troca de contato
    const select = document.getElementById("chatDestino");
    if (select) {
        select.addEventListener("change", () => {
            const contatoId = Number(select.value);
            if (!contatoId) return;

            renderizarConversaAtual(mensagensCache);
            marcarComoLidoContato(contatoId);
        });
    }

    if ("Notification" in window && Notification.permission !== "granted") {
        Notification.requestPermission();
    }
});



/*==========================================================
  DESTINATÁRIOS
==========================================================*/
async function carregarDestinatarios() {
    const op = getOperador();
    const select = document.getElementById("chatDestino");
    if (!op || !select) return;

    const resp = await fetch("chat/listar_operadores.php", {
        method: "POST",
        body: new URLSearchParams({ equipe: op.equipe })
    });

    const dados = await resp.json();
    select.innerHTML = `<option value="">Selecione um contato...</option>`;

    if (!dados.success) return;

    dados.operadores.forEach(o => {
        if (Number(o.id) !== Number(op.id)) {
            const qtd = naoLidasPorContato[o.id] || 0;
            const label = qtd > 0 ? `${o.nome} (${qtd})` : o.nome;
            const estilo = qtd > 0 ? 'style="color:gold;"' : "";
            select.innerHTML += `<option value="${o.id}" ${estilo}>${label}</option>`;
        }
    });
}

/*==========================================================
  ENVIAR MENSAGEM
==========================================================*/
window.enviarChat = async function () {
    const op = getOperador();
    const campoTexto = document.getElementById("chatTexto");
    const selectDestino = document.getElementById("chatDestino");

    if (!op || !campoTexto || !selectDestino) return;

    const texto = campoTexto.value.trim();
    const para = selectDestino.value;

    if (!texto || !para) return;

    await fetch("chat/enviar_mensagem.php", {
        method: "POST",
        body: new URLSearchParams({
            de_id: op.id,
            de_nome: op.operador,
            para,
            equipe: op.equipe,
            mensagem: texto
        })
    });

    campoTexto.value = "";
};
/*==========================================================
  CARREGAR MENSAGENS (CORE)
==========================================================*/
async function carregarMensagens() {
    const op = getOperador();
    if (!op) return;

    try {
        const resp = await fetch(
            `chat/listar_mensagens.php?equipe=${op.equipe}&meu_id=${op.id}&t=${Date.now()}`
        );

        const data = await resp.json();
        if (!data || !Array.isArray(data.mensagens)) return;

        mensagensCache = data.mensagens;
        naoLidasPorContato = data.naoLidasPorContato || {};

        const ultimoLido = getLastReadChatId(op.id);

        const naoLidas = mensagensCache.filter(m =>
            Number(m.id) > ultimoLido &&
            Number(m.para_id) === Number(op.id)
        );

        // 🔔 Badge de novas mensagens
        atualizarBadgeChat(naoLidas.length);

        // 🔔 Notificação se nova mensagem recebida e chat fechado
        if (naoLidas.length > 0 && !chatAberto) {
            const ultima = naoLidas[naoLidas.length - 1];

            if (Number(ultima.id) > ultimoIdNotificado) {
                ultimoIdNotificado = Number(ultima.id);
                notificarWindows(
                    "💬 Nova mensagem no Chat",
                    `${ultima.de_nome}: ${ultima.mensagem.substring(0, 80)}`
                );
                atualizarPreviewMensagem(ultima);
            }
        } else {
            esconderPreview();
        }

        // 💬 Atualiza conversa se o chat estiver aberto
        if (chatAberto) {
            renderizarConversaAtual(mensagensCache);
        }

    } catch (e) {
        console.error("[CHAT] Falha ao carregar mensagens:", e);
    }
}
/*==========================================================
  BADGE + ANIMAÇÃO
==========================================================*/
function atualizarBadgeChat(qtd) {
    const badge = document.getElementById("chatBadge");
    const icone = document.getElementById("chatIcone");

    if (!badge || !icone) return;

    const temMensagens = qtd > 0;

    badge.textContent = qtd;
    badge.classList.toggle("hidden", !temMensagens);
    icone.classList.toggle("piscando", temMensagens);
}
/*==========================================================
  PREVIEW
==========================================================*/
function atualizarPreviewMensagem(msg) {
    const preview = document.getElementById("chatPreview");
    if (!preview || !msg) return;

    const texto = msg.mensagem.length > 60
        ? `${msg.mensagem.substring(0, 60)}...`
        : msg.mensagem;

    preview.textContent = `${msg.de_nome}: ${texto}`;
    preview.classList.remove("hidden");
}

function esconderPreview() {
    const preview = document.getElementById("chatPreview");
    if (!preview) return;
    preview.classList.add("hidden");
}
/*==========================================================
  MARCAR COMO LIDAS (LOCAL)
==========================================================*/
function marcarMensagensComoLidasContato(contatoId) {
    const op = getOperador();
    if (!op || !mensagensCache.length || !contatoId) return;

    // Filtra as mensagens recebidas do contato atual
    const ultimas = mensagensCache.filter(m =>
        Number(m.de_id) === contatoId &&
        Number(m.para_id) === Number(op.id)
    );

    if (!ultimas.length) return;

    const ultimoId = Math.max(...ultimas.map(m => Number(m.id)));

    // Marca como lido localmente
    setLastReadChatId(op.id, ultimoId);
    naoLidasPorContato[contatoId] = 0;
    carregarDestinatarios();
    atualizarBadgeChat(0);

    // Atualiza no backend
    fetch("chat/marcar_lido.php", {
        method: "POST",
        body: new URLSearchParams({
            operador_id: op.id,
            contato_id: contatoId,
            ultimo_id: ultimoId
        })
    }).catch(e => console.warn("[CHAT] Erro ao marcar como lido:", e));
}

/*==========================================================
  RENDERIZAÇÃO
==========================================================*/
function renderizarConversaAtual(msgs) {
    const op = getOperador();
    const contatoId = Number(chatDestino.value);
    const box = document.getElementById("chatMensagens");

    if (!contatoId || !box) return;

    box.innerHTML = "";

    msgs.forEach(m => {
        const deId = Number(m.de_id);
        const paraId = Number(m.para_id);
        const meuId = Number(op.id);

        const valido =
            (deId === contatoId && paraId === meuId) ||
            (deId === meuId && paraId === contatoId);

        if (!valido) return;

        const hora = new Date(m.data_envio).toLocaleTimeString("pt-BR", {
            hour: "2-digit",
            minute: "2-digit"
        });

        const souEu = deId === meuId;

        box.innerHTML += `
            <div class="msg ${souEu ? "msg-enviada" : "msg-recebida"}">
                <div class="autor">${m.de_nome}</div>
                <div class="texto">${m.mensagem}</div>
                <div class="hora">${hora}</div>
            </div>
        `;
    });

    box.scrollTop = box.scrollHeight;

    if (chatAberto && contatoId) {
        marcarMensagensComoLidasContato(contatoId);
    }
}


/*==========================================================
  NOTIFICAÇÃO WINDOWS
==========================================================*/
function notificarWindows(titulo, mensagem) {
    if (!("Notification" in window)) return;

    if (Notification.permission === "granted") {
        new Notification(titulo, {
            body: mensagem,
            icon: "https://tgameajuda.com/img/principal/bot-tga.webp"
        });
    } else if (Notification.permission !== "denied") {
        Notification.requestPermission().then(permission => {
            if (permission === "granted") {
                new Notification(titulo, {
                    body: mensagem,
                    icon: "https://tgameajuda.com/img/principal/bot-tga.webp"
                });
            }
        });
    }
}
