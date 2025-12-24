/* ==========================================================
   app.js — Gerenciamento de Filas (Estado Global)
   RESPONSÁVEL POR:
   - carregar dados do backend
   - manter estado global (operadores / filas)
   - orquestrar renderização da UI
   - integrar ações em massa (bulk)
========================================================== */

console.log(
  "%c[APP] app.js carregado",
  "color:#38bdf8;font-weight:bold;"
);

/* ==========================================================
   IMPORTS (UI)
========================================================== */
import {
  renderizarOperadores,
  atualizarResumo
} from "./ui_operadores.js";

// ações em massa (bulk)
import "./bulk-actions.js";

/* ==========================================================
   ESTADO GLOBAL (FONTE ÚNICA DE VERDADE)
========================================================== */
export let operadores = [];
export let filas = [];

// cache global compartilhado com UI
window.__operadoresCache = [];

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
      fetch("backend/listar_operadores.php"),
      fetch("backend/listar_filas.php")
    ]);

    if (!opsResp.ok || !filasResp.ok) {
      throw new Error("Falha ao consultar backend");
    }

    const opsJson   = await opsResp.json();
    const filasJson = await filasResp.json();

    operadores = Array.isArray(opsJson?.operadores)
      ? opsJson.operadores
      : [];

    filas = Array.isArray(filasJson?.data)
      ? filasJson.data
      : Array.isArray(filasJson?.filas)
        ? filasJson.filas
        : [];

    // cache global (UI + bulk)
    window.__operadoresCache = operadores;

    // renderizações
    renderizarOperadores(operadores, filas);
    atualizarResumo(operadores, filas);

    // bulk
    if (typeof window.popularFilaBulk === "function") {
      window.popularFilaBulk(filas);
    }

    console.log("✅ Dados carregados:", {
      operadores: operadores.length,
      filas: filas.length
    });

  } catch (error) {
    console.error("[APP] Erro ao carregar dados:", error);

    if (typeof window.toastErro === "function") {
      toastErro("Erro ao carregar operadores ou filas");
    } else {
      alert("Erro ao carregar operadores ou filas");
    }
  }
}

/* ==========================================================
   EXPOR FUNÇÕES ESSENCIAIS PARA A UI
========================================================== */
window.carregar = carregar;
