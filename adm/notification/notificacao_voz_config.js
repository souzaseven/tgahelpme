// ==========================
// notificacao_voz_config.js
// Escolher voz feminina ou masculina para as notificações
// ==========================

// Elementos da interface (botão e rótulo)
document.addEventListener("DOMContentLoaded", () => {
  const container = document.createElement("div");
  container.style.marginTop = "15px";

  // Botão de alternância de voz
  const btnVoz = document.createElement("button");
  btnVoz.id = "toggleVoz";
  btnVoz.className = "toggle";
  btnVoz.textContent = "👩 Voz: Feminina";
  container.appendChild(btnVoz);

  // Adiciona ao corpo (abaixo dos outros botões)
  document.body.appendChild(container);

  // Estado inicial
  window.vozGenero = "feminina";

  // Alternar voz ao clicar
  btnVoz.addEventListener("click", () => {
    window.vozGenero = window.vozGenero === "feminina" ? "masculina" : "feminina";
    btnVoz.textContent =
      window.vozGenero === "feminina" ? "👩 Voz: Feminina" : "👨 Voz: Masculina";
    btnVoz.classList.toggle("desativado", window.vozGenero === "masculina");
  });
});

/**
 * Função auxiliar: retorna uma voz compatível com o gênero escolhido
 */
function obterVozPorGenero() {
  const voices = speechSynthesis.getVoices();
  if (!voices.length) {
    // Algumas vezes é necessário carregar as vozes
    speechSynthesis.onvoiceschanged = () => obterVozPorGenero();
  }

  // Tenta encontrar voz feminina/masculina em português
  if (window.vozGenero === "feminina") {
    return (
      voices.find(v => v.lang.startsWith("pt") && /female|feminina|brasil/i.test(v.name)) ||
      voices.find(v => v.lang.startsWith("pt"))
    );
  } else {
    return (
      voices.find(v => v.lang.startsWith("pt") && /male|masculino|daniel/i.test(v.name)) ||
      voices.find(v => v.lang.startsWith("pt"))
    );
  }
}

/**
 * Reescreve falarTexto() para usar a voz escolhida
 */
function falarTexto(texto = "Testando envio de notificação") {
  try {
    const msg = new SpeechSynthesisUtterance(texto);
    msg.lang = "pt-BR";
    msg.rate = 1;
    msg.pitch = 1;

    // Obtém voz selecionada
    const voz = obterVozPorGenero();
    if (voz) msg.voice = voz;

    speechSynthesis.cancel();
    speechSynthesis.speak(msg);
  } catch (e) {
    console.error("Erro ao tentar falar texto:", e);
  }
}
