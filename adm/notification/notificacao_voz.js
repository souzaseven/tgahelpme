// ==========================
// notificacao_voz.js
// Fala audível ao enviar notificação + controle de áudio e notificação
// ==========================

/**
 * Função para falar o texto em voz alta.
 * Usa a API SpeechSynthesis (funciona no Chrome, Edge e Firefox)
 */
function falarTexto(texto = "Testando envio de notificação") {
  try {
    const msg = new SpeechSynthesisUtterance(texto);
    msg.lang = "pt-BR"; // idioma da fala
    msg.rate = 1;       // velocidade normal
    msg.pitch = 1;      // tom padrão
    speechSynthesis.cancel(); // interrompe falas anteriores
    speechSynthesis.speak(msg);
  } catch (e) {
    console.error("Erro ao tentar falar texto:", e);
  }
}

// ==========================
// Espera o DOM carregar antes de acessar os botões
// ==========================
document.addEventListener("DOMContentLoaded", () => {
  const toggleNotificacao = document.getElementById("toggleNotificacao");
  const toggleAudio = document.getElementById("toggleAudio");

  // Estados padrão
  window.notificacaoAtiva = true;
  window.audioAtivo = true;

  // ======== Alternar notificação Windows ========
  toggleNotificacao.addEventListener("click", () => {
    window.notificacaoAtiva = !window.notificacaoAtiva;
    toggleNotificacao.textContent = window.notificacaoAtiva
      ? "🔔 Notificação: Ativada"
      : "🔕 Notificação: Desativada";
    toggleNotificacao.classList.toggle("desativado", !window.notificacaoAtiva);
  });

  // ======== Alternar áudio ========
  toggleAudio.addEventListener("click", () => {
    window.audioAtivo = !window.audioAtivo;
    toggleAudio.textContent = window.audioAtivo
      ? "🔊 Áudio: Ativado"
      : "🔇 Áudio: Desativado";
    toggleAudio.classList.toggle("desativado", !window.audioAtivo);
  });
});
