<div class="card">
    <div class="card-header">
        <h2 class="card-title">📊 CDR - Registro Detalhado da Chamada</h2>
    </div>

    <div class="card-body">

        <div class="form-row">

            <div class="form-group">
                <label class="form-label">UUID da Chamada</label>
                <input type="text" id="cdrUuid" class="form-control" placeholder="Ex: 6fda0cde-d22d-11ec-bda6-29a83953d677">
            </div>

            <div class="form-group" style="display:flex; align-items:flex-end;">
                <button class="btn btn-primary" onclick="buscarCDR()">
                    🔍 Buscar CDR
                </button>
            </div>

        </div>

        <div id="resultadoCDR" style="margin-top:1.5rem;"></div>

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

async function buscarCDR() {

    const uuid = document.getElementById('cdrUuid').value;

    if (!uuid) {
        showNotification('Informe o UUID', 'warning');
        return;
    }

    showNotification('Consultando CDR...', 'info');

    const response = await api.post(`api-handler.php?action=buscar_cdr&uuid=${uuid}`);

    if (!response.success) {
        showNotification('Erro: ' + (response.meta?.message || 'Erro'), 'danger');
        return;
    }

    const cdr = response.data;

    const tempoAtendimento = cdr.answer_time
        ? Math.floor((new Date(cdr.end_time) - new Date(cdr.answer_time)) / 1000)
        : 0;

    let html = `
        <div class="card mt-2">
            <div class="card-body">

                <h3>📊 Detalhes do CDR</h3>

                <p><strong>ID:</strong> ${cdr.id}</p>
                <p><strong>Origem:</strong> ${cdr.callerid_number}</p>
                <p><strong>Destino:</strong> ${cdr.destination_number}</p>
                <p><strong>Tipo:</strong> ${cdr.call_type}</p>
                <p><strong>Grupo:</strong> ${cdr.callgroup || '-'}</p>
                <p><strong>Codec:</strong> ${cdr.codec}</p>

                <hr>

                <p><strong>Início:</strong> ${cdr.start_time}</p>
                <p><strong>Atendeu:</strong> ${cdr.answer_time || 'Não atendida'}</p>
                <p><strong>Fim:</strong> ${cdr.end_time}</p>

                <p><strong>Duração Total:</strong> ${formatarTempo(cdr.duration)}</p>
                <p><strong>Tempo de Atendimento:</strong> ${formatarTempo(tempoAtendimento)}</p>

                <hr>

                <p><strong>Status:</strong> ${cdr.status}</p>
                <p><strong>Tronco:</strong> ${cdr.trunk_name || '-'}</p>
                <p><strong>UUID:</strong> ${cdr.uuid}</p>

            </div>
        </div>
    `;

    document.getElementById('resultadoCDR').innerHTML = html;
}

</script>