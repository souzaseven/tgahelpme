/* ==========================================================
   app.js — Gerenciamento de Filas (Estado Global)
========================================================== */

console.log(
  "%c[APP] app.js carregado",
  "color:#38bdf8;font-weight:bold;"
);

/* =========================
   IMPORTS
========================= */
import {
  renderizarOperadores,
  atualizarResumo
} from "./ui_operadores.js";
import "./bulk-actions.js";

/* =========================
   ESTADO GLOBAL
========================= */
export let operadores = [];
export let filas = [];

window.__operadoresCache = [];

/* =========================
   INIT
========================= */
document.addEventListener("DOMContentLoaded", () => {
  carregar();
});

/* =========================
   CARREGAR DADOS
========================= */
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

    operadores = Array.isArray(opsJson?.operadores) ? opsJson.operadores : [];
    filas = Array.isArray(filasJson?.data)
      ? filasJson.data
      : Array.isArray(filasJson?.filas)
        ? filasJson.filas
        : [];

    window.__operadoresCache = operadores;

    /* =========================
       DEBUG — FILAS
    ========================= */
    console.log("🔍 Filas carregadas:");
    filas.forEach(fila => {
      console.log(`ID: ${fila.id} | Nome: ${fila.name || fila.nome}`);
    });

    /* =========================
       RENDERIZA UI
    ========================= */
    renderizarOperadores(operadores, filas);
    atualizarResumo(operadores, filas);

    /* =========================
       CONTADORES (POR NOME DA FILA)
    ========================= */
    let totalTelefone = 0;
    let totalChat = 0;

    operadores.forEach(op => {
      const nomeFila = (op.fila || "").toLowerCase();

      // Debug individual
      console.log(`[OPERADOR] ${op.nome} → Fila: ${op.fila}`);

      if (nomeFila === "suporte matriz") {
        totalTelefone++;
      }

      if (nomeFila === "fila matriz chat/whats") {
        totalChat++;
      }
    });

    /* =========================
       ATUALIZA CARDS
    ========================= */
    const elTelefone = document.getElementById("suporteTelefone");
    const elChat = document.getElementById("suporteChat");

    if (elTelefone) elTelefone.textContent = totalTelefone;
    if (elChat) elChat.textContent = totalChat;

    /* =========================
       BULK
    ========================= */
    if (typeof window.popularFilaBulk === "function") {
      window.popularFilaBulk(filas);
    }

    console.log("✅ Dados carregados:", {
      operadores: operadores.length,
      filas: filas.length,
      suporteTelefone: totalTelefone,
      suporteChat: totalChat
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

/* =========================
   EXPOR PARA A UI
========================= */
window.carregar = carregar;
