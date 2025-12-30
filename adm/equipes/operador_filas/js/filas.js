/* ==========================================================
   filas_helper.js — Utilitários de Fila
   - Compatível com app.js / ui_operadores.js
========================================================== */

/* ==========================================================
   CARREGAR FILAS (OPCIONAL)
   ⚠️ Use apenas se NÃO estiver usando app.js
========================================================== */
async function carregarFilas() {
  const resp = await fetch("../backend/listar_filas.php");
  const json = await resp.json();

  return json.data || json.filas || [];
}

/* ==========================================================
   ALTERAR FILA DO OPERADOR
========================================================== */
async function alterarFila(operadorId, evoluxAgentId) {
  const select = document.getElementById(`fila-select-${operadorId}`);
  if (!select) {
    alert("Fila não encontrada");
    return;
  }

  const queueId   = select.value;
  const queueNome = select.options[select.selectedIndex]?.text;

  if (!queueId) {
    alert("Selecione uma fila");
    return;
  }

  const form = new FormData();
  form.append("id", operadorId);
  form.append("evolux_agent_id", evoluxAgentId);
  form.append("evolux_queue_id", queueId);
  form.append("fila", queueNome);

  try {
    const resp = await fetch("../backend/alterar_fila.php", {
      method: "POST",
      body: form
    });

    const json = await resp.json();

    if (json.success) {
      alert("Fila alterada com sucesso");

      // 🔁 atualiza painel inteiro
      if (typeof window.carregar === "function") {
        window.carregar();
      }

    } else {
      alert(json.erro || "Erro ao alterar fila");
    }

  } catch (e) {
    console.error("Erro alterarFila:", e);
    alert("Erro de comunicação com o servidor");
  }
}
