<div class="card">
    <div class="card-header">
        <h2 class="card-title">📋 Feature Plans - Planos de Discagem</h2>
        <button class="btn btn-primary" onclick="carregarFeaturePlans()">
            🔄 Atualizar
        </button>
    </div>

    <div class="card-body">

        <div id="featurePlanResultado">
            <div class="alert alert-info">
                Clique em "Atualizar" para carregar os planos.
            </div>
        </div>

    </div>
</div>

<script>

async function carregarFeaturePlans() {

    showNotification('Buscando planos...', 'info');

    const response = await api.get('api-handler.php?action=listar_feature_plans');


    if (!response.success) {
        showNotification(response.meta?.message || 'Erro ao buscar planos', 'danger');
        return;
    }

    const plans = response.data || [];

    let html = '';

    if (!plans.length) {
        html = `<div class="alert alert-warning">Nenhum plano encontrado.</div>`;
    } else {

        html += `
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        plans.forEach(plan => {
            html += `
                <tr>
                    <td><strong>${plan.id}</strong></td>
                    <td>${plan.name}</td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
        `;
    }

    document.getElementById('featurePlanResultado').innerHTML = html;

    showNotification('Planos carregados com sucesso!', 'success');
}

document.addEventListener('DOMContentLoaded', carregarFeaturePlans);

</script>
