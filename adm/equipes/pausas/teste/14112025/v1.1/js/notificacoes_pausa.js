// ============================================================
// notificacoes_pausa.js - Sistema de Alertas Sonoros e Desktop
// v1.14 (Compatível com controle_pausa.js v4.1)
// ============================================================
//
// 🔔 Funções:
// - Toast visual → som leve “ping”
// - Toast erro → som grave “erro”
// - Notificação Windows → título + mensagem
// - Evita sons repetidos em menos de 2s
//
// ============================================================

console.log("%c[Notificações v1.14] Sistema de alertas carregado...", "color:#00c6ff;font-weight:bold;");

class SistemaNotificacoes {
  constructor() {
this.ultimoSom = 0;
this.sons = {
  sucesso: 880,
  erro: 200,
  aviso: 440
};

    this.inicializar();
  }

// ============================================================
// ⚙️ Preferências locais (som / desktop)
// ============================================================
carregarPreferencias() {
  return {
    som: localStorage.getItem("pref_som") !== "0",          // default = ligado
    notificacao: localStorage.getItem("pref_desktop") !== "0" // default = ligado
  };
}


  // ============================================================
  // 🔊 Inicializa permissões e contexto de áudio
  // ============================================================
  inicializar() {
    try {
      if (!("Notification" in window)) {
        console.warn("🚫 Notificações do sistema não suportadas.");
      } else if (Notification.permission === "default") {
        Notification.requestPermission();
      }

      // Inicializa contexto de áudio
      this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    } catch (e) {
      console.warn("⚠️ Falha ao inicializar áudio:", e);
    }
  }

  // ============================================================
  // 🔔 Emite som curto
  // ============================================================
  tocarSom(tipo = "sucesso") {
  const prefs = this.carregarPreferencias();
  if (!prefs.som) return; // 🔇 som desativado

  const agora = Date.now();
  if (agora - this.ultimoSom < 1500) return;
  this.ultimoSom = agora;

  try {
    if (!this.audioCtx) return;
    const freq = this.sons[tipo] || 440;
    const osc = this.audioCtx.createOscillator();
    const gain = this.audioCtx.createGain();

    osc.type = tipo === "erro" ? "square" : "sine";
    osc.frequency.setValueAtTime(freq, this.audioCtx.currentTime);
    gain.gain.setValueAtTime(0.05, this.audioCtx.currentTime);

    osc.connect(gain);
    gain.connect(this.audioCtx.destination);
    osc.start();
    osc.stop(this.audioCtx.currentTime + 0.25);
  } catch (e) {
    console.warn("⚠️ Erro ao tocar som:", e);
  }
}


  // ============================================================
  // 🖥️ Exibe notificação do Windows
  // ============================================================
notificarDesktop(titulo, mensagem) {
  const prefs = this.carregarPreferencias();
  if (!prefs.notificacao) return; // 🔕 notificação desativada

  if (!("Notification" in window)) return;
  if (Notification.permission !== "granted") return;

  new Notification(titulo, {
    body: mensagem,
    icon: "https://tgameajuda.com/img/principal/bot-tga.webp"
  });
}


  // ============================================================
  // 🎵 Wrapper: sucesso / erro / aviso
  // ============================================================
  sucesso(msg) {
    this.tocarSom("sucesso");
    this.notificarDesktop("✅ Ação concluída", msg);
  }

  erro(msg) {
    this.tocarSom("erro");
    this.notificarDesktop("❌ Erro", msg);
  }

  aviso(msg) {
    this.tocarSom("aviso");
    this.notificarDesktop("⚠️ Alerta", msg);
  }
}
// ============================================================
// 🔗 Integração global com ControlePausaSistema
// ============================================================
window.somPausa = new SistemaNotificacoes();

// Integração automática com toasts do controle
(function integrarComControle() {
  const oldToast = window.controle?.toast;
  if (!oldToast) {
    setTimeout(integrarComControle, 1000);
    return;
  }

  // Sobrescreve o método toast para tocar som também (respeitando preferências)
  window.controle.toast = function (msg, erro = false) {
    const div = document.createElement("div");
    div.className = "toast-global show";
    div.style.borderLeftColor = erro ? "#ff4444" : "#00ff88";
    div.textContent = msg;
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 4000);

    // 🔊 Verifica preferências ANTES de tocar som
    const prefs = window.somPausa.carregarPreferencias();

    if (prefs.som) {
      if (erro) window.somPausa.erro(msg);
      else window.somPausa.sucesso(msg);
    }
  };
})();
