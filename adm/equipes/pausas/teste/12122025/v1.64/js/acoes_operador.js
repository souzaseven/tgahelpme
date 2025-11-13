// ============================================================
// acoes_operador.js (v2.0) — shim de compatibilidade
// Mantido apenas para chamadas legadas a aplicarBotoesOperador()
// ============================================================

console.log("%c[acoes_operador.js] shim ativo (delegando para interface_botoes)", "color:#00ff88;");

function aplicarBotoesOperador() {
  if (typeof window.aplicarBotoesOperador === "function") {
    try { window.aplicarBotoesOperador(); } catch {}
  }
}
