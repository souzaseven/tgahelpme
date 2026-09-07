/*
 * theme.js — descobre e acompanha o tema (claro/escuro).
 * ------------------------------------------------------------------
 * Quando o usuario escolhe "auto", tentamos seguir o WhatsApp.
 * O WhatsApp historicamente marca o tema com a classe "dark" no
 * <html> ou <body>, ou com [data-theme]. Como fallback, usamos a
 * preferencia do sistema.
 */

/** @returns {"light"|"dark"} */
export function resolveTheme(mode) {
  if (mode === "light" || mode === "dark") return mode;

  const de = document.documentElement;
  const body = document.body;

  if (de.classList.contains("dark") || (body && body.classList.contains("dark"))) return "dark";

  const attr =
    de.getAttribute("data-theme") || (body && body.getAttribute("data-theme")) || "";
  if (attr.toLowerCase() === "dark") return "dark";
  if (attr.toLowerCase() === "light") return "light";

  try {
    if (window.matchMedia("(prefers-color-scheme: dark)").matches) return "dark";
  } catch {
    /* ignora */
  }
  return "light";
}

/**
 * Chama `cb` sempre que o tema do ambiente puder ter mudado.
 * Observa apenas atributos do <html> (barato). Retorna funcao de limpeza.
 */
export function watchEnvTheme(cb) {
  let mq = null;
  const obs = new MutationObserver(() => cb());
  try {
    obs.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ["class", "data-theme"],
    });
  } catch {
    /* ignora */
  }
  try {
    mq = window.matchMedia("(prefers-color-scheme: dark)");
    mq.addEventListener("change", cb);
  } catch {
    /* ignora */
  }
  return () => {
    try { obs.disconnect(); } catch {}
    try { if (mq) mq.removeEventListener("change", cb); } catch {}
  };
}
