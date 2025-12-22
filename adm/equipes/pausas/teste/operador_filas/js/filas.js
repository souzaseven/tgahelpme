async function carregarFilas() {
    const resp = await fetch('../backend/listar_filas.php');
    const json = await resp.json();
    return json.filas || [];
}

async function alterarFila(operadorId, evoluxAgentId) {
    const select = document.querySelector(`#fila_${operadorId}`);
    const queueId = select.value;
    const queueNome = select.options[select.selectedIndex].text;

    const form = new FormData();
    form.append('operador_id', operadorId);
    form.append('evolux_agent_id', evoluxAgentId);
    form.append('queue_id', queueId);
    form.append('queue_nome', queueNome);

    const resp = await fetch('../backend/alterar_fila_agente.php', {
        method: 'POST',
        body: form
    });

    const json = await resp.json();

    if (json.success) {
        alert('Fila alterada com sucesso');
    } else {
        alert(json.erro || 'Erro ao alterar fila');
    }
}
