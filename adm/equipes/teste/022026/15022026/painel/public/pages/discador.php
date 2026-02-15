<div class="card">
    <div class="card-header">
        <h2 class="card-title">📢 Discador - Gerenciamento de Campanhas</h2>
    </div>

    <div class="card-body">

        <!-- ================= BUSCAR ASSINANTE ================= -->
        <div class="card mb-3">
            <div class="card-header">
                🔍 Buscar Assinante
            </div>
            <div class="card-body">

                <div class="form-row">
                    <div class="form-group">
                        <label>Nome</label>
                        <input type="text" id="searchName" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Número</label>
                        <input type="text" id="searchNumber" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Campanha ID</label>
                        <input type="number" id="searchCampaign" class="form-control">
                    </div>

                    <div class="form-group" style="display:flex;align-items:flex-end;">
                        <button class="btn btn-primary" onclick="buscarAssinante()">
                            🔍 Buscar
                        </button>
                    </div>
                </div>

                <div id="resultadoAssinante"></div>

            </div>
        </div>


        <!-- ================= CRIAR CAMPANHA ================= -->
        <div class="card mb-3">
            <div class="card-header">
                📢 Criar Campanha
            </div>
            <div class="card-body">

                <div class="form-row">
                    <div class="form-group">
                        <label>Nome</label>
                        <input type="text" id="campaignName" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Tipo</label>
                        <select id="campaignType" class="form-control">
                            <option value="transfer">Transfer</option>
                            <option value="ivr">IVR</option>
                            <option value="broadcast">Broadcast</option>
                        </select>
                    </div>

                    <div class="form-group" style="display:flex;align-items:flex-end;">
                        <button class="btn btn-success" onclick="criarCampanha()">
                            ➕ Criar
                        </button>
                    </div>
                </div>

            </div>
        </div>


        <!-- ================= CONTROLE CAMPANHA ================= -->
        <div class="card mb-3">
            <div class="card-header">
                ▶️ Controle de Campanha
            </div>
            <div class="card-body">

                <div class="form-row">

                    <div class="form-group">
                        <label>Campaign ID</label>
                        <input type="number" id="controlCampaignId" class="form-control">
                    </div>

                    <div class="form-group" style="display:flex;align-items:flex-end;gap:0.5rem;">
                        <button class="btn btn-primary" onclick="iniciarCampanha()">▶️ Iniciar</button>
                        <button class="btn btn-warning" onclick="pararCampanha()">⏹ Parar</button>
                        <button class="btn btn-danger" onclick="limparCampanha()">🧹 Limpar</button>
                    </div>

                </div>

            </div>
        </div>


        <!-- ================= CADASTRAR ASSINANTE ================= -->
        <div class="card">
            <div class="card-header">
                👤 Cadastrar Assinante
            </div>
            <div class="card-body">

                <div class="form-row">
                    <div class="form-group">
                        <label>Campaign ID</label>
                        <input type="number" id="newCampaignId" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Nome</label>
                        <input type="text" id="newSubscriberName" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Número</label>
                        <input type="text" id="newSubscriberNumber" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>CPF (custom)</label>
                        <input type="text" id="newSubscriberCpf" class="form-control">
                    </div>

                    <div class="form-group" style="display:flex;align-items:flex-end;">
                        <button class="btn btn-info" onclick="cadastrarAssinante()">
                            💾 Cadastrar
                        </button>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<script>

/* ================= BUSCAR ASSINANTE ================= */

async function buscarAssinante() {

    const name = document.getElementById('searchName').value;
    const number = document.getElementById('searchNumber').value;
    const campaign_id = document.getElementById('searchCampaign').value;

    showNotification('Buscando assinante...', 'info');

    const response = await api.post(
        `api-handler.php?action=buscar_assinante&name=${name}&number=${number}&campaign_id=${campaign_id}`
    );

    if (!response.success) {
        showNotification(response.meta?.message || 'Erro', 'danger');
        return;
    }

    const data = response.data;

    let html = `<div class="mt-3">`;

    if (!data.total) {
        html += `<div class="alert alert-warning">Nenhum assinante encontrado</div>`;
    } else {

        html += `<strong>Total:</strong> ${data.total}<br><br>`;

        data.results.forEach(item => {
            html += `
                <div class="card mb-2">
                    <div class="card-body">
                        <strong>${item.name}</strong><br>
                        Status: ${item.status}<br>
                        Número: ${item.numbers?.[0]?.number || '-'}
                    </div>
                </div>
            `;
        });
    }

    html += `</div>`;

    document.getElementById('resultadoAssinante').innerHTML = html;
}


/* ================= CRIAR CAMPANHA ================= */

async function criarCampanha() {

    const data = {
        name: document.getElementById('campaignName').value,
        campaign_type: document.getElementById('campaignType').value,
        script: "Script automático",
        retry_count: 3,
        retry_seconds: 10,
        wrapup_time: 5,
        min_call_ratio: 1,
        max_call_ratio: 2,
        call_ratio_increase_step: 1,
        call_ratio_decrease_step: 1,
        call_ratio_analysis_interval: 30,
        concurrent_calls: 1
    };

    showNotification('Criando campanha...', 'info');

    const response = await api.post('api-handler.php?action=criar_campanha', data);

    if (response.success) {
        showNotification('Campanha criada com sucesso!', 'success');
    } else {
        showNotification(response.meta?.message || 'Erro', 'danger');
    }
}


/* ================= CONTROLE ================= */

async function iniciarCampanha() {
    const id = document.getElementById('controlCampaignId').value;
    await executarAcaoCampanha(id, 'iniciar_campanha', 'Campanha iniciada');
}

async function pararCampanha() {
    const id = document.getElementById('controlCampaignId').value;
    await executarAcaoCampanha(id, 'parar_campanha', 'Campanha parada');
}

async function limparCampanha() {
    const id = document.getElementById('controlCampaignId').value;
    await executarAcaoCampanha(id, 'limpar_campanha', 'Assinantes removidos');
}

async function executarAcaoCampanha(id, action, sucessoMsg) {

    if (!id) {
        showNotification('Informe o Campaign ID', 'warning');
        return;
    }

    showNotification('Executando ação...', 'info');

    const response = await api.post(`api-handler.php?action=${action}&id=${id}`);

    if (response.success) {
        showNotification(sucessoMsg, 'success');
    } else {
        showNotification(response.meta?.message || 'Erro', 'danger');
    }
}


/* ================= CADASTRAR ASSINANTE ================= */

async function cadastrarAssinante() {

    const data = {
        campaign_id: document.getElementById('newCampaignId').value,
        name: document.getElementById('newSubscriberName').value,
        number: document.getElementById('newSubscriberNumber').value,
        custom_cpf: document.getElementById('newSubscriberCpf').value
    };

    showNotification('Cadastrando assinante...', 'info');

    const response = await api.post('api-handler.php?action=cadastrar_assinante', data);

    if (response.success) {
        showNotification('Assinante cadastrado!', 'success');
    } else {
        showNotification(response.meta?.message || 'Erro', 'danger');
    }
}

</script>
