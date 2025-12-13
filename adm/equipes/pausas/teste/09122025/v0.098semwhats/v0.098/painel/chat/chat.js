/*==========================================================
   chat.js — Chat individual automático (WhatsApp-like)
==========================================================*/

console.log("%c[CHAT] Listener global iniciado", "color:#00eaff;font-weight:bold;");

let ultimoIdMensagem = 0;
let intervaloChat = null;
let chatAberto = false;

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
  ABRIR / FECHAR CHAT (UI)
==========================================================*/
window.abrirChat = function () {
    const overlay = document.getElementById("chatOverlay");
    if (!overlay) return;

    overlay.classList.remove("hidden");
    overlay.classList.remove("chat-piscando");
    chatAberto = true;
};

window.fecharChat = function () {
    const overlay = document.getElementById("chatOverlay");
    if (!overlay) return;

    overlay.classList.add("hidden");
    chatAberto = false;
};

/*==========================================================
  INICIALIZAÇÃO GLOBAL (SEM CLICAR EM CHAT)
==========================================================*/
document.addEventListener("DOMContentLoaded", async () => {

    const op = getOperador();
    if (!op) return;

    await carregarDestinatarios();
    await carregarMensagens(); // preload inicial

    // 🔥 ESCUTA GLOBAL (funciona mesmo com chat fechado)
    intervaloChat = setInterval(carregarMensagens, 2000);

    // Enter para enviar
    const campo = document.getElementById("chatTexto");
    if (campo) {
        campo.addEventListener("keydown", e => {
            if (e.key === "Enter") {
                e.preventDefault();
                enviarChat();
            }
        });
    }

    // Permissão de notificação
    if ("Notification" in window && Notification.permission !== "granted") {
        Notification.requestPermission();
    }
});

/*==========================================================
  LISTAR CONTATOS
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
  CARREGAR MENSAGENS (GLOBAL)
==========================================================*/
async function carregarMensagens() {

    const op = getOperador();
    if (!op) return;

    const resp = await fetch(
        `chat/listar_mensagens.php?equipe=${op.equipe}&t=${Date.now()}`
    );

    const msgs = await resp.json();
    if (!Array.isArray(msgs)) return;

    let novaMensagemRecebida = null;

    msgs.forEach(m => {

        const msgId = Number(m.id);
        if (msgId <= ultimoIdMensagem) return;

        const deId = Number(m.de_id);
        const paraId = Number(m.para_id);

        // 🔥 SOMENTE MENSAGENS RECEBIDAS (não as minhas)
        if (paraId === Number(op.id) && deId !== Number(op.id)) {
            novaMensagemRecebida = m;
        }

        ultimoIdMensagem = Math.max(ultimoIdMensagem, msgId);
    });

    // 🔔 SE CHEGOU NOVA MENSAGEM
    if (novaMensagemRecebida) {
        abrirChatAutomatico(novaMensagemRecebida);
    }

    // Atualiza UI se o chat estiver aberto
    if (chatAberto) {
        renderizarConversaAtual(msgs);
    }
}

/*==========================================================
  ABERTURA AUTOMÁTICA + NOTIFICAÇÃO
==========================================================*/
function abrirChatAutomatico(msg) {

    const select = document.getElementById("chatDestino");
    if (!select) return;

    // Seleciona automaticamente o contato
    select.value = String(msg.de_id);

    abrirChat();
    tocarSomChat();
    notificarDesktop(`Nova mensagem de ${msg.de_nome}`);

    renderizarConversaAtual();
}

/*==========================================================
  RENDERIZAR CONVERSA ATUAL
==========================================================*/
function renderizarConversaAtual(msgs = null) {

    const op = getOperador();
    const contatoId = Number(chatDestino.value);
    const box = document.getElementById("chatMensagens");

    if (!contatoId || !box) return;

    if (!msgs) return;

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
                <div class="hora">
                    ${hora}
                    ${souEu ? `<span class="checks">✔✔</span>` : ""}
                </div>
            </div>
        `;
    });

    box.scrollTop = box.scrollHeight;
}

/*==========================================================
  NOTIFICAÇÃO + SOM
==========================================================*/
function notificarDesktop(msg) {
    if ("Notification" in window && Notification.permission === "granted") {
        new Notification("Chat – Controle de Pausas", {
            body: msg,
            icon: "https://tgameajuda.com/img/principal/bot-tga.webp"
        });
    }
}

function tocarSomChat() {
    let audio = document.getElementById("chatSom");
    if (!audio) {
        audio = document.createElement("audio");
        audio.id = "chatSom";
        audio.src = "chat/notify.mp3";
        audio.preload = "auto";
        document.body.appendChild(audio);
    }
    audio.currentTime = 0;
    audio.play().catch(() => {});
}
