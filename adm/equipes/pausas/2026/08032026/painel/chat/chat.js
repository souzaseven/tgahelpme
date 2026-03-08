/*==========================================================
   chat.js — Chat individual (UX PROFISSIONAL)
==========================================================*/

console.log("%c[CHAT] Listener global iniciado", "color:#00eaff;font-weight:bold;");

let intervaloChat = null;
let chatAberto = false;
let mensagensCache = [];
let ultimoIdNotificado = 0;

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
  CORREÇÃO: aceita contatoId para pré-selecionar o destinatário
==========================================================*/
window.abrirChat = async function (contatoId = null) {
    const overlay = document.getElementById("chatOverlay");
    if (!overlay) return;

    overlay.classList.remove("hidden");
    chatAberto = true;

    // Se veio um ID (clicou no botão do card do operador),
    // garante que os destinatários estão carregados e seleciona
    if (contatoId) {
        await carregarDestinatarios();
        const select = document.getElementById("chatDestino");
        if (select) select.value = String(contatoId);
        await carregarMensagens();
    }

    esconderPreview();
    marcarMensagensComoLidas();
};

window.fecharChat = function () {
    const overlay = document.getElementById("chatOverlay");
    if (!overlay) return;

    overlay.classList.add("hidden");
    chatAberto = false;
};

window.toggleChat = function () {
    chatAberto ? fecharChat() : abrirChat();
};

/*==========================================================
  INIT
==========================================================*/
document.addEventListener("DOMContentLoaded", async () => {

    const op = getOperador();
    if (!op) return;

    await carregarDestinatarios();
    await carregarMensagens();

    intervaloChat = setInterval(carregarMensagens, 2000);

    const campo = document.getElementById("chatTexto");
    if (campo) {
        campo.addEventListener("keydown", e => {
            if (e.key === "Enter") {
                e.preventDefault();
                enviarChat();
            }
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

    // Não recarrega se já está populado (preserva seleção atual)
    if (select.options.length > 1) return;

    const resp = await fetch("chat/listar_operadores.php", {
        method: "POST",
        body: new URLSearchParams({ equipe: op.equipe })
    });

    const dados = await resp.json();
    select.innerHTML = `<option value="">Selecione um contato...</option>`;

    if (!dados.success) return;

    dados.operadores.forEach(o => {
        if (Number(o.id) !== Number(op.id)) {
            select.innerHTML += `<option value="${o.id}">${o.nome}</option>`;
        }
    });
}

/*==========================================================
  ENVIAR MENSAGEM
==========================================================*/
window.enviarChat = async function () {
    const op = getOperador();
    const texto = chatTexto.value.trim();
    const para = chatDestino.value;

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

    chatTexto.value = "";
};

/*==========================================================
  CARREGAR MENSAGENS (CORE)
==========================================================*/
async function carregarMensagens() {

    const op = getOperador();
    if (!op) return;

    const resp = await fetch(
        `chat/listar_mensagens.php?equipe=${op.equipe}&t=${Date.now()}`
    );

    const msgs = await resp.json();
    if (!Array.isArray(msgs)) return;

    mensagensCache = msgs;

    const ultimoLido = getLastReadChatId(op.id);

    const naoLidas = msgs.filter(m =>
        Number(m.id) > ultimoLido &&
        Number(m.para_id) === Number(op.id)
    );

    // --------------------------
    // BADGE
    // --------------------------
    atualizarBadgeChat(naoLidas.length);

    // --------------------------
    // NOTIFICAÇÃO + PREVIEW
    // --------------------------
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

    // --------------------------
    // CHAT ABERTO
    // --------------------------
    if (chatAberto) {
        renderizarConversaAtual(msgs);
    }
}

/*==========================================================
  BADGE + ANIMAÇÃO
==========================================================*/
function atualizarBadgeChat(qtd) {
    const badge = document.getElementById("chatBadge");
    const icone = document.getElementById("chatIcone");

    if (!badge || !icone) return;

    if (qtd > 0) {
        badge.textContent = qtd;
        badge.classList.remove("hidden");
        icone.classList.add("piscando");
    } else {
        badge.classList.add("hidden");
        icone.classList.remove("piscando");
    }
}

/*==========================================================
  PREVIEW
==========================================================*/
function atualizarPreviewMensagem(msg) {
    const preview = document.getElementById("chatPreview");
    if (!preview || !msg) return;

    preview.textContent =
        `${msg.de_nome}: ${msg.mensagem.substring(0, 60)}${msg.mensagem.length > 60 ? "..." : ""}`;

    preview.classList.remove("hidden");
}

function esconderPreview() {
    const preview = document.getElementById("chatPreview");
    if (preview) preview.classList.add("hidden");
}

/*==========================================================
  MARCAR COMO LIDAS (LOCAL)
==========================================================*/
function marcarMensagensComoLidas() {
    const op = getOperador();
    if (!op || !mensagensCache.length) return;

    const ultimoId = Math.max(...mensagensCache.map(m => Number(m.id)));

    setLastReadChatId(op.id, ultimoId);
    atualizarBadgeChat(0);
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

        const valido =
            (deId === contatoId && paraId === Number(op.id)) ||
            (deId === Number(op.id) && paraId === contatoId);

        if (!valido) return;

        const hora = new Date(m.data_envio).toLocaleTimeString("pt-BR", {
            hour: "2-digit",
            minute: "2-digit"
        });

        const souEu = deId === Number(op.id);

        box.innerHTML += `
            <div class="msg ${souEu ? "msg-enviada" : "msg-recebida"}">
                <div class="autor">${m.de_nome}</div>
                <div class="texto">${m.mensagem}</div>
                <div class="hora">${hora}</div>
            </div>
        `;
    });

    box.scrollTop = box.scrollHeight;
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
    }
}