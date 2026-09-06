"use strict";

const form = document.getElementById("analyze-form");
const urlInput = document.getElementById("url-input");
const clearBtn = document.getElementById("clear-btn");
const analyzeBtn = document.getElementById("analyze-btn");
const analyzeLabel = analyzeBtn.querySelector(".btn__label");
const formError = document.getElementById("form-error");
const formErrorText = document.getElementById("form-error-text");
const retryBtn = document.getElementById("retry-btn");
const statusBox = document.getElementById("status");
const statusText = document.getElementById("status-text");
const progress = document.getElementById("progress");
const progressBar = document.getElementById("progress-bar");
const result = document.getElementById("result");

const resultThumb = document.getElementById("result-thumb");
const resultDuration = document.getElementById("result-duration");
const resultPlatform = document.getElementById("result-platform");
const resultTitle = document.getElementById("result-title");
const resultMeta = document.getElementById("result-meta");
const qualityRow = document.getElementById("quality-row");
const qualityChips = document.getElementById("quality-chips");
const downloadVideoBtn = document.getElementById("download-video");
const downloadAudioBtn = document.getElementById("download-audio");
const downloadAudioLabel = document.getElementById("download-audio-label");

const SUPPORTED_HOST_SUFFIXES = [
  "instagram.com", "tiktok.com", "facebook.com", "fb.watch",
  "youtube.com", "youtu.be",
];
const TYPE_LABELS = {
  reel: "Reel", video: "Vídeo", post: "Post", photo: "Foto",
  clip: "Clipe", short: "Short",
};
const PLATFORM_LABELS = {
  instagram: "Instagram", tiktok: "TikTok", facebook: "Facebook", youtube: "YouTube",
};

/** Estado do resultado atual. */
let current = null;          // { url, videoFormats, audioFormat }
let selectedQuality = null;

/* -------------------- helpers -------------------- */

function looksLikeSupportedUrl(value) {
  let u;
  try {
    u = new URL(value.trim());
  } catch {
    return false;
  }
  if (u.protocol !== "https:") return false;
  const host = u.hostname.replace(/^www\./, "");
  return SUPPORTED_HOST_SUFFIXES.some((s) => host === s || host.endsWith("." + s));
}

let retryHandler = null;

function showError(message, retry) {
  formErrorText.textContent = message;
  retryHandler = typeof retry === "function" ? retry : null;
  retryBtn.hidden = retryHandler === null;
  formError.hidden = false;
}

function clearError() {
  formError.hidden = true;
  formErrorText.textContent = "";
  retryBtn.hidden = true;
  retryHandler = null;
}

retryBtn.addEventListener("click", () => {
  const fn = retryHandler;
  clearError();
  if (fn) fn();
});

function showStatus(message) {
  statusText.textContent = message;
  statusBox.hidden = false;
}

function hideStatus() {
  statusBox.hidden = true;
  setProgress(undefined);
}

/** pct: número (0–100) mostra a barra; null mostra indeterminada; undefined esconde. */
function setProgress(pct) {
  if (pct === undefined) {
    progress.hidden = true;
    progress.classList.remove("progress--indeterminate");
    progressBar.style.width = "0%";
    return;
  }
  progress.hidden = false;
  if (pct === null) {
    progress.classList.add("progress--indeterminate");
  } else {
    progress.classList.remove("progress--indeterminate");
    progressBar.style.width = `${Math.max(0, Math.min(100, pct))}%`;
  }
}

function setBusy(isBusy, label) {
  analyzeBtn.disabled = isBusy;
  urlInput.disabled = isBusy;
  downloadVideoBtn.disabled = isBusy;
  downloadAudioBtn.disabled = isBusy;
  analyzeLabel.textContent = isBusy ? "Analisando..." : "Analisar";
  if (isBusy) showStatus(label || "Obtendo informações...");
  else hideStatus();
}

function formatDuration(seconds) {
  if (seconds == null) return null;
  const m = Math.floor(seconds / 60);
  const s = Math.floor(seconds % 60);
  return `${String(m).padStart(2, "0")}:${String(s).padStart(2, "0")}`;
}

function formatSize(bytes) {
  if (!bytes) return null;
  const mb = bytes / (1024 * 1024);
  if (mb < 1) return `${Math.round(bytes / 1024)} KB`;
  return `${mb.toFixed(1)} MB`;
}

/* -------------------- render -------------------- */

function renderQualityChips(videoFormats, maxBytes) {
  qualityChips.innerHTML = "";
  if (!videoFormats.length) {
    selectedQuality = null;
    return;
  }

  const fits = (f) => !maxBytes || !f.filesize || f.filesize <= maxBytes * 1.05;
  // Pré-seleciona a melhor qualidade que cabe no limite (senão, a primeira).
  const defaultIndex = Math.max(0, videoFormats.findIndex(fits));

  videoFormats.forEach((fmt, i) => {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "segmented__item";
    btn.textContent = fmt.label;
    btn.setAttribute("aria-pressed", String(i === defaultIndex));
    if (!fits(fmt)) {
      btn.classList.add("segmented__item--over");
      const size = formatSize(fmt.filesize);
      btn.title = size ? `~${size} — acima do limite` : "acima do limite";
    }
    btn.addEventListener("click", () => {
      selectedQuality = fmt.quality;
      qualityChips.querySelectorAll(".segmented__item").forEach((el) => {
        el.setAttribute("aria-pressed", String(el === btn));
      });
    });
    qualityChips.appendChild(btn);
  });
  selectedQuality = videoFormats[defaultIndex].quality;
}

function renderResult(data) {
  const formats = data.formats || [];
  const videoFormats = formats.filter((f) => f.kind === "video");
  const audioFormat = formats.find((f) => f.kind === "audio") || null;

  current = { url: urlInput.value.trim(), videoFormats, audioFormat };

  // Plataforma detectada
  const platformLabel = PLATFORM_LABELS[data.platform] || data.platform || "";
  resultPlatform.textContent = platformLabel;

  // Título + metadados
  resultTitle.textContent = data.title || `Conteúdo do ${platformLabel || "vídeo"}`;

  const meta = [];
  const typeLabel = TYPE_LABELS[data.type] || data.type;
  if (typeLabel) meta.push(typeLabel);
  const dur = formatDuration(data.duration);
  if (dur) meta.push(dur);
  const topSize = formatSize(videoFormats[0]?.filesize);
  if (topSize) meta.push(`~${topSize}`);
  resultMeta.innerHTML = "";
  meta.forEach((text) => {
    const span = document.createElement("span");
    span.textContent = text;
    resultMeta.appendChild(span);
  });

  // Thumbnail
  if (data.thumbnail) {
    resultThumb.src = data.thumbnail;
    resultThumb.hidden = false;
  } else {
    resultThumb.removeAttribute("src");
    resultThumb.hidden = true;
  }
  if (dur) {
    resultDuration.textContent = dur;
    resultDuration.hidden = false;
  } else {
    resultDuration.hidden = true;
  }

  // Qualidades e ações
  const maxBytes = (data.max_size_mb || 0) * 1024 * 1024;
  renderQualityChips(videoFormats, maxBytes);
  // Só mostra o seletor quando há mais de uma opção real de resolução.
  qualityRow.hidden = videoFormats.length <= 1;
  downloadVideoBtn.hidden = videoFormats.length === 0;

  if (audioFormat) {
    downloadAudioBtn.hidden = false;
    downloadAudioLabel.textContent = `Baixar áudio (${audioFormat.label})`;
  } else {
    downloadAudioBtn.hidden = true;
  }

  result.hidden = false;
}

/* -------------------- ações -------------------- */

function filenameFromDisposition(header, fallback) {
  if (!header) return fallback;
  const utf8 = /filename\*=UTF-8''([^;]+)/i.exec(header);
  if (utf8) {
    try { return decodeURIComponent(utf8[1]); } catch { /* ignore */ }
  }
  const plain = /filename="?([^";]+)"?/i.exec(header);
  return plain ? plain[1] : fallback;
}

function triggerBlobDownload(blob, filename) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 10_000);
}

async function startDownload(kind) {
  if (!current) return;
  const retry = () => startDownload(kind);
  clearError();
  setBusy(true, kind === "audio" ? "Preparando áudio..." : "Preparando...");
  setProgress(null);

  let jobId;
  try {
    const res = await fetch("/api/download", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        url: current.url,
        format: kind,
        quality: kind === "video" ? selectedQuality : null,
      }),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.job_id) {
      showError(data.error || "Não foi possível processar o link.", retry);
      setBusy(false);
      return;
    }
    jobId = data.job_id;
  } catch {
    showError("Falha de conexão. Tente novamente.", retry);
    setBusy(false);
    return;
  }

  let settled = false;
  let errorCount = 0;
  const events = new EventSource(`/api/download/${jobId}/events`);

  events.onmessage = (ev) => {
    let snap;
    try {
      snap = JSON.parse(ev.data);
    } catch {
      return;
    }
    errorCount = 0;

    if (snap.state === "error") {
      settled = true;
      events.close();
      showError(snap.error || "Não foi possível processar o link.", retry);
      setBusy(false);
      return;
    }

    if (snap.state === "done") {
      settled = true;
      events.close();
      fetchDownloadedFile(jobId, kind, retry).finally(() => setBusy(false));
      return;
    }

    const pct = typeof snap.percent === "number" ? ` ${Math.round(snap.percent)}%` : "";
    showStatus((snap.message || "Baixando...") + pct);
    setProgress(typeof snap.percent === "number" ? snap.percent : null);
  };

  // O EventSource reconecta sozinho; só desistimos após várias quedas seguidas.
  events.onerror = () => {
    if (settled) return;
    errorCount += 1;
    if (errorCount > 5) {
      settled = true;
      events.close();
      finishFromServer(jobId, kind, retry);
    }
  };
}

/** Última tentativa: o job pode ter terminado enquanto o SSE oscilava. */
async function finishFromServer(jobId, kind, retry) {
  try {
    const res = await fetch(`/api/download/${jobId}/file`);
    if (res.ok) {
      const blob = await res.blob();
      triggerBlobDownload(
        blob,
        filenameFromDisposition(
          res.headers.get("content-disposition"),
          kind === "audio" ? "audio.mp3" : "video.mp4",
        ),
      );
    } else if (res.status === 409) {
      showError("O download está demorando mais que o esperado. Tente novamente.", retry);
    } else {
      const data = await res.json().catch(() => ({}));
      showError(data.error || "Não foi possível concluir o download.", retry);
    }
  } catch {
    showError("Falha de conexão. Tente novamente.", retry);
  } finally {
    setBusy(false);
  }
}

async function fetchDownloadedFile(jobId, kind, retry) {
  showStatus("Finalizando...");
  setProgress(100);
  try {
    const res = await fetch(`/api/download/${jobId}/file`);
    if (!res.ok) {
      const data = await res.json().catch(() => ({}));
      showError(data.error || "Não foi possível baixar o arquivo.", retry);
      return;
    }
    const blob = await res.blob();
    const filename = filenameFromDisposition(
      res.headers.get("content-disposition"),
      kind === "audio" ? "audio.mp3" : "video.mp4",
    );
    triggerBlobDownload(blob, filename);
  } catch {
    showError("Falha de conexão ao baixar o arquivo.", retry);
  }
}

async function runAnalyze(value) {
  clearError();
  result.hidden = true;
  setBusy(true, "Obtendo informações...");
  try {
    const res = await fetch("/api/analyze", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ url: value }),
    });
    const data = await res.json().catch(() => ({}));

    if (!res.ok || !data.success) {
      showError(
        data.error || "Não foi possível acessar esse conteúdo.",
        () => runAnalyze(value),
      );
      return;
    }
    renderResult(data);
  } catch {
    showError("Falha de conexão. Tente novamente.", () => runAnalyze(value));
  } finally {
    setBusy(false);
  }
}

/* -------------------- eventos -------------------- */

urlInput.addEventListener("input", () => {
  clearBtn.hidden = urlInput.value.length === 0;
});

clearBtn.addEventListener("click", () => {
  urlInput.value = "";
  clearBtn.hidden = true;
  urlInput.focus();
});

downloadVideoBtn.addEventListener("click", () => startDownload("video"));
downloadAudioBtn.addEventListener("click", () => startDownload("audio"));

form.addEventListener("submit", (event) => {
  event.preventDefault();
  clearError();
  result.hidden = true;

  const value = urlInput.value.trim();
  if (!value) {
    showError("Cole um link para continuar.");
    return;
  }
  if (!looksLikeSupportedUrl(value)) {
    showError("Link inválido. Cole a URL de um vídeo do Instagram, TikTok, Facebook ou YouTube.");
    return;
  }
  runAnalyze(value);
});
