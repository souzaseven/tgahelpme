/* ==========================================================
   app.js — Gerenciamento de Filas (Bootstrap + Estado)
   RESPONSÁVEL POR:
   - carregar dados
   - manter estado global
   - chamar renderização
========================================================== */

import {
  renderizarOperadores,
  atualizarResumo
} from "./ui_operadores.js";

export let operadores = [];
export let filas = [];

/* ==========================================================
   INIT
========================================================== */
document.addEventListener("DOMContentLoaded", () => {
  carregar();
});

/* ==========================================================
   CARREGAR DADOS (OPERADORES + FILAS)
========================================================== */
export async function carregar() {
  try {
    const [opsResp, filasResp] = await Promise.all([
      fetch("backend/listar_operadores.php").then(r => r.json()),
      fetch("backend/listar_filas.php").then(r => r.json())
    ]);

    operadores = opsResp.operadores || [];
    filas = filasResp.data || filasResp.filas || [];

    renderizarOperadores(operadores, filas);
    atualizarResumo(operadores, filas);

  } catch (e) {
    console.error("[ERRO] carregar()", e);
    alert("Erro ao carregar operadores ou filas");
  }
}
