/**
 * ===================================================
 * PAINEL — DESPAUSAR AGENTES (EVOLUX)
 * ===================================================
 * - Remove pausa do agente
 * - Usa endpoint oficial Evolux
 * - Mantém padrão do painel de pausas
 * ===================================================
 */

const BASE_BACKEND_DESPAUSA = "backend";

/* ===============================
   DESPAUSAR AGENTE
=============================== */
async function despausarAgente(agentId) {
  if (!agentId) return;

  try {
    const res = await fetch(`${BASE_BACKEND_DESPAUSA}/despausar_agente.php`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        agent_id: agentId
      })
    });

    const json = await res.json();

    if (json.success) {
      toast("Agente despausado com sucesso", "success");
    } else {
      toast(json.error || "Falha ao despausar agente", "warning");
    }

  } catch (e) {
    console.error("[DESPAUSAR] Erro:", e);
    toast("Erro de comunicação ao despausar", "warning");
  }
}
