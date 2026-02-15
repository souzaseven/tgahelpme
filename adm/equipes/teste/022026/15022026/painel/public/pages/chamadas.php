<div class="card">
    <div class="card-header">
        <h2 class="card-title">📞 Controle de Chamadas</h2>
    </div>

    <div class="card-body">

        <div class="form-row">

            <div class="form-group">
                <label>UUID da Chamada</label>
                <input type="text" id="uuidCall" class="form-control" placeholder="UUID da chamada ativa">
            </div>

            <div class="form-group">
                <label>Número destino</label>
                <input type="text" id="numeroDestino" class="form-control" placeholder="DDD + Número">
            </div>

            <div class="form-group" style="display:flex; gap:10px; align-items:flex-end;">
                <button class="btn btn-warning" onclick="transferirChamada()">🔁 Transferir</button>
                <button class="btn btn-danger" onclick="desligarChamada()">📴 Desligar</button>
            </div>

        </div>

        <hr>

        <div class="form-row">

            <div class="form-group">
                <label>Login Agente</label>
                <input type="text" id="fromLogin" class="form-control">
            </div>

            <div class="form-group">
                <label>Destino</label>
                <input type="text" id="toNumber" class="form-control">
            </div>

            <div class="form-group" style="display:flex; align-items:flex-end;">
                <button class="btn btn-success" onclick="originarChamadaAgente()">📲 Ligar</button>
            </div>

        </div>

    </div>
</div>

<script>
async function transferirChamada() {

    const uuid = document.getElementById('uuidCall').value;
    const destino = document.getElementById('numeroDestino').value;

    if (!uuid || !destino) {
        showNotification('Informe UUID e destino', 'warning');
        return;
    }

    const response = await api.post(
        'api-handler.php?action=transferir_chamada',
        {
            uuid,
            destination_number: destino,
            leg: 'bleg'
        }
    );

    if (response.success) {
        showNotification('Chamada transferida com sucesso!', 'success');
    } else {
        showNotification('Erro ao transferir', 'danger');
    }
}

async function desligarChamada() {

    const uuid = document.getElementById('uuidCall').value;

    if (!uuid) {
        showNotification('Informe UUID', 'warning');
        return;
    }

    const response = await api.post(
        'api-handler.php?action=desligar_chamada',
        { uuid }
    );

    if (response.success) {
        showNotification('Chamada encerrada!', 'success');
    } else {
        showNotification('Erro ao desligar', 'danger');
    }
}

async function originarChamadaAgente() {

    const from = document.getElementById('fromLogin').value;
    const to = document.getElementById('toNumber').value;

    if (!from || !to) {
        showNotification('Informe login e destino', 'warning');
        return;
    }

    const response = await api.post(
        'api-handler.php?action=originar_chamada_agente',
        {
            from,
            to,
            call_info: [
                { label: "Origem Painel", type: "text", value: "TGA Admin" }
            ]
        }
    );

    if (response.success) {
        showNotification('Chamada iniciada!', 'success');
    } else {
        showNotification('Erro ao originar', 'danger');
    }
}

</script>