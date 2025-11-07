// ==========================
// notificacao.js
// Notificação Web + Toast integrado + Controle de Áudio
// ==========================

// Seletores principais
const statusEl = document.getElementById('status');
const btn = document.getElementById('btnNotificar');
const toastContainer = document.getElementById('toastContainer');

// ==========================
// Função: Criar e exibir toast
// ==========================
function mostrarToast(
  mensagem,
  icone = "https://raw.githubusercontent.com/souzaseven/tgahelpme/Desafios/icon%20bot%20tga.ico"
) {
  const toast = document.createElement("div");
  toast.className = "toast";
  toast.innerHTML = `<img src="${icone}" alt="icone"><span>${mensagem}</span>`;
  toastContainer.appendChild(toast);

  // Remove automaticamente após 4 segundos
  setTimeout(() => toast.remove(), 4000);
}

// ==========================
// Verifica se o navegador suporta notificações
// ==========================
if (!("Notification" in window)) {
  statusEl.textContent = "❌ Este navegador não suporta notificações desktop.";
  btn.disabled = true;
} else {
  atualizarStatusPermissao();
}

// ==========================
// Atualiza status de permissão no texto da página
// ==========================
function atualizarStatusPermissao() {
  switch (Notification.permission) {
    case "granted":
      statusEl.textContent =
        "✅ Permissão concedida. Clique no botão para enviar uma notificação.";
      break;
    case "denied":
      statusEl.textContent =
        "🚫 Permissão negada. Ative nas configurações do navegador.";
      break;
    default:
      statusEl.textContent =
        "⚠️ Permissão ainda não concedida. Clique no botão para solicitar.";
  }
}

// ==========================
// Função principal: Enviar notificação
// ==========================
async function enviarNotificacao() {
  try {
    let perm = Notification.permission;

    // Se ainda não foi concedida, pede permissão
    if (perm !== "granted") {
      perm = await Notification.requestPermission();
      atualizarStatusPermissao();
    }

    // Mostra sempre o toast
    mostrarToast("🚀 Nova atualização detectada no sistema Smart POS!");

    // === ÁUDIO: fala o texto se estiver ativado ===
    if (window.audioAtivo && typeof falarTexto === "function") {
      falarTexto("Testando envio de notificação");
    }

    // === NOTIFICAÇÃO DO WINDOWS: só envia se estiver ativada ===
    if (!window.notificacaoAtiva) {
      console.log("🔕 Notificação do Windows desativada pelo usuário.");
      return;
    }

    // Se a permissão for concedida, dispara a notificação desktop
    if (perm === "granted") {
      const opcoes = {
        body: "🚀 Nova atualização detectada no sistema Smart POS!",
        icon: "https://raw.githubusercontent.com/souzaseven/tgahelpme/Desafios/icon%20bot%20tga.ico",
        badge: "https://tgameajuda.com/favicon.ico",
        requireInteraction: true, // mantém visível até o usuário fechar
      };

      const noti = new Notification("📢 TGA - Alerta de Sistema", opcoes);

      // Ação quando o usuário clicar na notificação
      noti.onclick = () => {
        window.focus();
        window.open("https://tgameajuda.com", "_blank");
        noti.close();
      };

      console.log("✅ Notificação enviada com sucesso!");
    } else if (perm === "denied") {
      alert("🚫 Você bloqueou as notificações no navegador.");
    }
  } catch (erro) {
    console.error("Erro ao enviar notificação:", erro);
    mostrarToast("⚠️ Erro ao tentar enviar notificação.");
  }
}

// ==========================
// Evento: Clique no botão principal
// ==========================
btn.addEventListener("click", enviarNotificacao);
