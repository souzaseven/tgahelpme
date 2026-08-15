// ---------- Tema (claro/escuro) ----------
const THEME_STORAGE_KEY = 'baileys-theme';
const themeToggleBtn = document.getElementById('theme-toggle');

function applyTheme(theme) {
  if (theme === 'dark' || theme === 'light') {
    document.documentElement.setAttribute('data-theme', theme);
  } else {
    document.documentElement.removeAttribute('data-theme');
  }
  themeToggleBtn.textContent = currentIsDark() ? '☀️' : '🌙';
}

function currentIsDark() {
  const explicit = document.documentElement.getAttribute('data-theme');
  if (explicit === 'dark') return true;
  if (explicit === 'light') return false;
  return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

applyTheme(localStorage.getItem(THEME_STORAGE_KEY));

themeToggleBtn.addEventListener('click', () => {
  const nextTheme = currentIsDark() ? 'light' : 'dark';
  localStorage.setItem(THEME_STORAGE_KEY, nextTheme);
  applyTheme(nextTheme);
});

// ---------- Painel do dispositivo (QR code / status / reiniciar / desconectar) ----------
const statusDot = document.getElementById('status-dot');
const statusBadge = document.getElementById('status-badge');
const qrWrapper = document.getElementById('qrcode-wrapper');
const qrImg = document.getElementById('qrcode-img');
const qrCountdown = document.getElementById('qr-countdown');
const deviceResultWrapper = document.getElementById('device-result-wrapper');
const deviceResult = document.getElementById('device-result');
const successBanner = document.getElementById('success-banner');
const copyDeviceResultBtn = document.getElementById('copy-device-result');

const btnConnect = document.getElementById('btn-connect');
const btnStatus = document.getElementById('btn-status');
const btnRestart = document.getElementById('btn-restart');
const btnLogout = document.getElementById('btn-logout');

// Ajuste esta lista caso a APIBrasil use outro nome de estado para "conectado".
const CONNECTED_STATES = ['open', 'connected'];
const WARNING_STATES = ['connecting', 'aguardando qr', 'processando...'];
const DANGER_STATES = ['close', 'closed', 'erro', 'erro de rede', 'unauthenticated'];

const POLL_INTERVAL_MS = 4000;
const POLL_TIMEOUT_MS = 3 * 60 * 1000; // QR code do WhatsApp expira em poucos minutos

let pollIntervalId = null;
let pollTimeoutId = null;
let countdownIntervalId = null;

function setStatus(text) {
  statusBadge.textContent = text;

  const normalized = text.toLowerCase();
  statusDot.className = 'status-dot';

  if (CONNECTED_STATES.includes(normalized)) {
    statusDot.classList.add('success');
  } else if (DANGER_STATES.includes(normalized)) {
    statusDot.classList.add('danger');
  } else if (WARNING_STATES.includes(normalized) || normalized.includes('process')) {
    statusDot.classList.add('warning', 'pulse');
  }
}

function showDeviceResult(text) {
  deviceResultWrapper.style.display = 'block';
  deviceResult.textContent = text;
}

function setButtonLoading(button, isLoading, idleLabel) {
  button.disabled = isLoading;
  button.innerHTML = isLoading
    ? '<span class="spinner"></span> Aguarde...'
    : idleLabel;
}

function stopPolling() {
  if (pollIntervalId) {
    clearInterval(pollIntervalId);
    pollIntervalId = null;
  }
  if (pollTimeoutId) {
    clearTimeout(pollTimeoutId);
    pollTimeoutId = null;
  }
  if (countdownIntervalId) {
    clearInterval(countdownIntervalId);
    countdownIntervalId = null;
  }
  qrCountdown.textContent = '';
}

function showConnectedSuccess(data) {
  stopPolling();
  qrWrapper.style.display = 'none';
  successBanner.style.display = 'flex';
  setStatus(data.instance?.status ?? 'conectado');
  showDeviceResult(JSON.stringify(data, null, 2));
}

/**
 * Consulta o status da conexão em segundo plano, sem os estados visuais de
 * "carregando" dos botões, e passa para o banner de sucesso assim que
 * detectar que o WhatsApp foi pareado (QR lido).
 */
async function pollConnectionState() {
  try {
    const response = await fetch('instance-action.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'connectionState' }),
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
      return; // ignora falhas pontuais durante o polling e tenta de novo no próximo tick
    }

    const status = data.instance?.status ?? '';
    if (status) {
      setStatus(status);
    }

    if (CONNECTED_STATES.includes(status.toLowerCase())) {
      showConnectedSuccess(data);
    }
  } catch {
    // Ignora erro de rede pontual — o próximo tick tenta de novo.
  }
}

function startPolling() {
  stopPolling();
  successBanner.style.display = 'none';

  const deadline = Date.now() + POLL_TIMEOUT_MS;

  countdownIntervalId = setInterval(() => {
    const secondsLeft = Math.max(0, Math.round((deadline - Date.now()) / 1000));
    qrCountdown.textContent = `Expira em ${secondsLeft}s`;
  }, 1000);

  pollIntervalId = setInterval(pollConnectionState, POLL_INTERVAL_MS);
  pollTimeoutId = setTimeout(() => {
    stopPolling();
    qrWrapper.style.display = 'none';
    setStatus('QR expirado — gere um novo');
  }, POLL_TIMEOUT_MS);
}

async function callInstanceAction(action, button, idleLabel) {
  setButtonLoading(button, true, idleLabel);
  setStatus('processando...');

  try {
    const response = await fetch('instance-action.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action }),
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
      setStatus('erro');
      showDeviceResult(`Erro: ${data.message ?? 'Falha desconhecida.'}`);
      return data;
    }

    const status = data.instance?.status ?? (data.qrcode ? 'aguardando QR' : 'ok');
    setStatus(status);

    if (data.qrcode?.base64) {
      qrImg.src = data.qrcode.base64;
      qrWrapper.style.display = 'block';
    } else {
      qrWrapper.style.display = 'none';
    }

    showDeviceResult(JSON.stringify(data, null, 2));
    return data;
  } catch (error) {
    setStatus('erro de rede');
    showDeviceResult(`Erro de rede: ${error.message}`);
    return null;
  } finally {
    setButtonLoading(button, false, idleLabel);
  }
}

btnConnect.addEventListener('click', async () => {
  successBanner.style.display = 'none';
  const data = await callInstanceAction('connect', btnConnect, '<span>📷</span> Gerar QR Code');

  // Só começa a checar o status sozinho se realmente veio um QR novo para ler.
  if (data?.success && data.qrcode?.base64) {
    startPolling();
  }
});

btnStatus.addEventListener('click', () => callInstanceAction('connectionState', btnStatus, '<span>🔄</span> Verificar status'));

btnRestart.addEventListener('click', () => {
  stopPolling();
  successBanner.style.display = 'none';
  callInstanceAction('restart', btnRestart, '<span>♻️</span> Reiniciar');
});

btnLogout.addEventListener('click', () => {
  stopPolling();
  qrWrapper.style.display = 'none';
  successBanner.style.display = 'none';
  callInstanceAction('logout', btnLogout, '<span>⏻</span> Desconectar');
});

copyDeviceResultBtn.addEventListener('click', async () => {
  try {
    await navigator.clipboard.writeText(deviceResult.textContent);
    const original = copyDeviceResultBtn.textContent;
    copyDeviceResultBtn.textContent = 'Copiado!';
    setTimeout(() => { copyDeviceResultBtn.textContent = original; }, 1500);
  } catch {
    // Clipboard indisponível (ex.: contexto não-HTTPS) — ignora silenciosamente.
  }
});

// ---------- Envio de mensagem ----------
const sendResultWrapper = document.getElementById('send-result-wrapper');
const sendResult = document.getElementById('send-result');
const btnSend = document.getElementById('btn-send');

document.getElementById('send-form').addEventListener('submit', async (event) => {
  event.preventDefault();

  const number = document.getElementById('number').value.trim();
  const text = document.getElementById('text').value.trim();

  setButtonLoading(btnSend, true, '<span>📨</span> Enviar');
  sendResultWrapper.style.display = 'block';
  sendResult.textContent = 'Enviando...';

  try {
    const response = await fetch('send-message.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ number, text }),
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
      sendResult.textContent = `Erro: ${data.message ?? 'Falha desconhecida.'}`;
      return;
    }

    sendResult.textContent = `Mensagem enviada com sucesso! ID: ${data.messageId ?? 'n/d'}`;
  } catch (error) {
    sendResult.textContent = `Erro de rede: ${error.message}`;
  } finally {
    setButtonLoading(btnSend, false, '<span>📨</span> Enviar');
  }
});
