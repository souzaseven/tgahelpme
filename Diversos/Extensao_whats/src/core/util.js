/*
 * util.js — utilitarios pequenos, sem dependencias.
 */

/** Atrasa a execucao de fn ate `ms` sem novas chamadas. */
export function debounce(fn, ms) {
  let t = 0;
  const wrapped = (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn(...args), ms);
  };
  wrapped.cancel = () => clearTimeout(t);
  return wrapped;
}

/** Limita fn a no maximo 1 execucao a cada `ms`. */
export function throttle(fn, ms) {
  let last = 0;
  let pending = null;
  return (...args) => {
    const now = Date.now();
    const remaining = ms - (now - last);
    if (remaining <= 0) {
      last = now;
      fn(...args);
    } else if (!pending) {
      pending = setTimeout(() => {
        last = Date.now();
        pending = null;
        fn(...args);
      }, remaining);
    }
  };
}

/** Id curto e suficientemente unico para uso local. */
export function uid() {
  return "r-" + Date.now().toString(36) + "-" + Math.random().toString(36).slice(2, 8);
}

/**
 * Aguarda `testFn()` retornar algo "truthy". Resolve com esse valor.
 * Rejeita apos `timeout` ms. Usa polling leve (nao observa o DOM inteiro).
 */
export function waitFor(testFn, { timeout = 20000, interval = 300 } = {}) {
  return new Promise((resolve, reject) => {
    const start = Date.now();
    const tick = () => {
      let result = null;
      try {
        result = testFn();
      } catch {
        result = null;
      }
      if (result) return resolve(result);
      if (Date.now() - start > timeout) return reject(new Error("waitFor: timeout"));
      setTimeout(tick, interval);
    };
    tick();
  });
}

/** Clamps n ao intervalo [min, max]. */
export function clamp(n, min, max) {
  return Math.min(max, Math.max(min, n));
}
