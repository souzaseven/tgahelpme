(() => {
  "use strict";

  const API = {
    start: "api/start.php",
    send: "api/send.php",
    state: "api/state.php",
  };

  const STATUS_LABELS = {
    idle: "Desconectado",
    connecting: "Conectando...",
    qrcode: "Aguardando leitura do QR Code",
    connected: "Conectado",
    error: "Erro",
  };

  const el = {
    statusDot: document.getElementById("status-dot"),
    statusLabel: document.getElementById("status-label"),
    formConnect: document.getElementById("form-connect"),
    btnConnect: document.getElementById("btn-connect"),
    connectFeedback: document.getElementById("connect-feedback"),
    qrcodeArea: document.getElementById("qrcode-area"),
    qrcodeImage: document.getElementById("qrcode-image"),
    qrcodeText: document.getElementById("qrcode-text"),
    formSend: document.getElementById("form-send"),
    btnSend: document.getElementById("btn-send"),
    sendFeedback: document.getElementById("send-feedback"),
    messageLog: document.getElementById("message-log"),
  };

  let pollTimer = null;

  async function apiPost(url, body) {
    const response = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    });

    const payload = await response.json().catch(() => ({
      error: true,
      message: "Resposta inválida do servidor.",
    }));

    if (!response.ok || payload.error) {
      throw new Error(payload.message || "Erro desconhecido.");
    }

    return payload.data;
  }

  async function apiGet(url) {
    const response = await fetch(url);
    const payload = await response.json();
    return payload.data;
  }

  function setFeedback(node, message, type) {
    node.textContent = message;
    node.className = "feedback" + (type ? ` feedback--${type}` : "");
  }

  function setStatus(status) {
    const known = STATUS_LABELS[status] ? status : "idle";
    el.statusDot.className = `status-dot status-dot--${known}`;
    el.statusLabel.textContent = STATUS_LABELS[known];
  }

  /**
   * Tenta localizar o QR Code dentro de um payload de resposta cujo shape
   * exato ainda não foi confirmado pela documentação da API Brasil. Procura
   * por chaves comuns em serviços desse tipo (qrcode, qr_code, base64...).
   * Ajuste esta função assim que soubermos o campo real.
   */
  function extractQrCode(payload) {
    if (!payload || typeof payload !== "object") {
      return null;
    }

    const candidates = ["qrcode", "qr_code", "qrCode", "base64", "image", "qr"];

    for (const key of candidates) {
      const value = payload[key];
      if (typeof value === "string" && value.length > 20) {
        return value;
      }
    }

    // Procura um nível a mais dentro de campos comuns tipo "data" / "response".
    for (const wrapper of ["data", "response", "result"]) {
      if (payload[wrapper]) {
        const nested = extractQrCode(payload[wrapper]);
        if (nested) {
          return nested;
        }
      }
    }

    return null;
  }

  function renderQrCode(rawValue) {
    if (!rawValue) {
      el.qrcodeArea.hidden = true;
      el.qrcodeImage.removeAttribute("src");
      return;
    }

    el.qrcodeArea.hidden = false;

    const looksLikeUrl = /^https?:\/\//i.test(rawValue);
    const looksLikeDataUri = rawValue.startsWith("data:image");

    if (looksLikeUrl || looksLikeDataUri) {
      el.qrcodeImage.src = rawValue;
      el.qrcodeText.textContent = "";
    } else if (/^[A-Za-z0-9+/=]+$/.test(rawValue)) {
      // Parece base64 puro, sem o prefixo data:image/...;base64,
      el.qrcodeImage.src = `data:image/png;base64,${rawValue}`;
      el.qrcodeText.textContent = "";
    } else {
      // Não parece imagem — trata como código de pareamento em texto.
      el.qrcodeImage.removeAttribute("src");
      el.qrcodeText.textContent = rawValue;
    }
  }

  function renderMessages(messages) {
    if (!messages || messages.length === 0) {
      el.messageLog.innerHTML = '<li class="message-log__empty">Nenhum evento ainda.</li>';
      return;
    }

    el.messageLog.innerHTML = messages
      .map((entry) => {
        const when = entry.sent_at || entry.received_at || "";
        const direction = entry.direction === "in" ? "Recebida" : "Enviada";
        const detail = entry.message || JSON.stringify(entry.payload || {});
        return `<li><time>${when} · ${direction}</time>${escapeHtml(detail)}</li>`;
      })
      .join("");
  }

  function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  function applyState(state) {
    if (!state) return;

    setStatus(state.status || "idle");

    const qr =
      extractQrCode(state.qrcode_payload) ||
      extractQrCode(state.start_response);

    renderQrCode(qr);
    renderMessages(state.messages);

    if (state.status === "connected") {
      stopPolling();
      setFeedback(el.connectFeedback, "WhatsApp conectado com sucesso!", "ok");
    }

    if (state.status === "error" && state.last_error) {
      setFeedback(el.connectFeedback, state.last_error, "error");
    }
  }

  function startPolling() {
    stopPolling();
    pollTimer = setInterval(async () => {
      try {
        const state = await apiGet(API.state);
        applyState(state);
      } catch (err) {
        // Falha silenciosa no poll não deve travar a UI.
        console.error("Falha ao consultar estado:", err);
      }
    }, 3000);
  }

  function stopPolling() {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  el.formConnect.addEventListener("submit", async (event) => {
    event.preventDefault();

    const session = document.getElementById("session").value.trim();
    el.btnConnect.disabled = true;
    setFeedback(el.connectFeedback, "Iniciando sessão...", null);
    setStatus("connecting");

    try {
      const data = await apiPost(API.start, { session });

      const qr = extractQrCode(data);
      if (qr) {
        setStatus("qrcode");
        renderQrCode(qr);
        setFeedback(el.connectFeedback, "Escaneie o QR Code no seu WhatsApp.", "ok");
      } else {
        setFeedback(
          el.connectFeedback,
          "Sessão iniciada. Aguardando QR Code / confirmação de conexão...",
          null
        );
      }

      startPolling();
    } catch (err) {
      setStatus("error");
      setFeedback(el.connectFeedback, err.message, "error");
    } finally {
      el.btnConnect.disabled = false;
    }
  });

  el.formSend.addEventListener("submit", async (event) => {
    event.preventDefault();

    const number = document.getElementById("number").value.trim();
    const message = document.getElementById("message").value.trim();
    const session = document.getElementById("session").value.trim();

    el.btnSend.disabled = true;
    setFeedback(el.sendFeedback, "Enviando...", null);

    try {
      await apiPost(API.send, { session, number, message });
      setFeedback(el.sendFeedback, "Mensagem enviada!", "ok");
      document.getElementById("message").value = "";

      const state = await apiGet(API.state);
      applyState(state);
    } catch (err) {
      setFeedback(el.sendFeedback, err.message, "error");
    } finally {
      el.btnSend.disabled = false;
    }
  });

  // Carrega o estado atual assim que a página abre.
  (async () => {
    try {
      const state = await apiGet(API.state);
      applyState(state);
      if (state && (state.status === "connecting" || state.status === "qrcode")) {
        startPolling();
      }
    } catch (err) {
      console.error("Falha ao carregar estado inicial:", err);
    }
  })();
})();
