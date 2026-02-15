<div class="card">
    <div class="card-header">
        <h2 class="card-title">📞 Buscar Chamada por Call ID</h2>
    </div>

    <div class="card-body">

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Call ID</label>
                <input type="number" id="callIdInput" class="form-control" placeholder="Ex: 27848">
            </div>

            <div class="form-group" style="display:flex; align-items:flex-end;">
                <button class="btn btn-primary" onclick="buscarCall()">
                    🔍 Buscar
                </button>
            </div>
        </div>

        <div id="resultadoCall" style="margin-top:1.5rem;"></div>

    </div>
</div>

<script>

function formatarTempo(segundos) {

    if (!segundos || isNaN(segundos)) return '00:00:00';

    const h = Math.floor(segundos / 3600);
    const m = Math.floor((segundos % 3600) / 60);
    const s = segundos % 60;

    const pad = (n) => String(n).padStart(2, '0');

    return `${pad(h)}:${pad(m)}:${pad(s)}`;
}

async function buscarCall() {

    const callId = document.getElementById('callIdInput').value;

    if (!callId) {
        showNotification('Informe o Call ID', 'warning');
        return;
    }

    showNotification('Buscando chamada...', 'info');

    try {

        const response = await api.post(`api-handler.php?action=buscar_call&id=${callId}`);

        if (!response.success) {
            showNotification('Erro: ' + (response.meta?.message || 'Erro'), 'danger');
            return;
        }

        const call = response.data;

        let html = `
            <div class="card mt-2">
                <div class="card-body">

                    <h3>📞 Detalhes da Chamada</h3>

                    <p><strong>Agente:</strong> ${call.agent_name || '-'}</p>
                    <p><strong>Fila:</strong> ${call.queue_name || '-'}</p>
                    <p><strong>Tipo:</strong> ${call.call_type_description || '-'}</p>
                    <p><strong>Número:</strong> ${call.receiver_number || '-'}</p>
                    <p><strong>Tempo de Espera:</strong> ${formatarTempo(call.wait_duration)}</p>
                    <p><strong>Duração:</strong> ${formatarTempo(call.call_duration)}</p>
                    <p><strong>Finalização:</strong> ${call.end_by_description || '-'}</p>

               ${call.download_audio ? `
    <button class="btn btn-info mt-2"
        onclick="window.open('${call.download_audio}', '_blank')">
        🎧 Ouvir / Baixar Gravação
    </button>
` : ''}


                </div>
            </div>
        `;

        document.getElementById('resultadoCall').innerHTML = html;

    } catch (error) {
        showNotification('Erro inesperado: ' + error.message, 'danger');
    }
}

function baixarGravacao(callId) {

    showNotification('Abrindo gravação...', 'info');

  
    const url = `api-handler.php?action=baixar_gravacao&id=${callId}`;

    window.open(url, '_blank');
}

</script>
