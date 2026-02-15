<div class="card">
    <div class="card-header">
        <h2 class="card-title">📞 Gerenciamento de Filas</h2>
    </div>

    <div class="card-body">

        <!-- ======================== CRIAR FILA ======================== -->

        <div class="card mb-3">
            <div class="card-body">
                <h3>➕ Criar Nova Fila</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label>Nome</label>
                        <input type="text" id="fila_nome" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Número ID</label>
                        <input type="text" id="fila_number" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Slug</label>
                        <input type="text" id="fila_slug" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Número Público</label>
                        <input type="text" id="fila_public_number" class="form-control">
                    </div>

                    <div class="form-group" style="display:flex; align-items:flex-end;">
                        <button class="btn btn-primary" onclick="criarFila()">
                            💾 Criar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================== LISTA ======================== -->

        <div class="card">
            <div class="card-body">
                <h3>📋 Filas Cadastradas</h3>

                <div id="listaFilas"></div>
            </div>
        </div>

    </div>
</div>

<script>

document.addEventListener('DOMContentLoaded', () => {
    carregarFilas();
});

/* ============================================================
   LISTAR FILAS
============================================================ */

async function carregarFilas() {

    const response = await api.post('api-handler.php?action=listar_filas&include_archived=true');

    if (!response.success) {
        showNotification('Erro ao listar filas', 'danger');
        return;
    }

    const filas = response.data || [];

    let html = `
        <table class="table table-dark table-hover mt-3">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Número</th>
                    <th>Público</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
    `;

    filas.forEach(fila => {

        html += `
            <tr>
                <td>${fila.id}</td>
                <td>${fila.name}</td>
                <td>${fila.number}</td>
                <td>${fila.public_number || '-'}</td>
                <td>
                    <button class="btn btn-info btn-sm" onclick="verFila(${fila.id})">
                        👁 Ver
                    </button>
                    <button class="btn btn-warning btn-sm" onclick="arquivarFila(${fila.id})">
                        🗑 Arquivar
                    </button>
                </td>
            </tr>
        `;
    });

    html += '</tbody></table>';

    document.getElementById('listaFilas').innerHTML = html;
}


/* ============================================================
   CRIAR FILA
============================================================ */

async function criarFila() {

    const data = {
        name: document.getElementById('fila_nome').value,
        number: document.getElementById('fila_number').value,
        slug: document.getElementById('fila_slug').value,
        public_number: document.getElementById('fila_public_number').value
    };

    const response = await api.post('api-handler.php?action=criar_fila', data);

    if (!response.success) {
        showNotification('Erro ao criar fila', 'danger');
        return;
    }

    showNotification('Fila criada com sucesso', 'success');

    carregarFilas();
}


/* ============================================================
   CONSULTAR FILA
============================================================ */

async function verFila(id) {

    const response = await api.post(`api-handler.php?action=consultar_fila&id=${id}`);

    if (!response.success) {
        showNotification('Erro ao consultar fila', 'danger');
        return;
    }

    const fila = response.data;

    showNotification(`Fila: ${fila.name}`, 'info');

    listarPausasFila(id);
}


/* ============================================================
   ARQUIVAR FILA
============================================================ */

async function arquivarFila(id) {

    if (!confirm('Deseja arquivar esta fila?')) return;

    const response = await api.post(`api-handler.php?action=arquivar_fila&id=${id}`);

    if (!response.success) {
        showNotification(response.meta?.message || 'Erro ao arquivar', 'warning');
        return;
    }

    showNotification('Fila arquivada', 'success');

    carregarFilas();
}


/* ============================================================
   LISTAR PAUSAS
============================================================ */

async function listarPausasFila(queueId) {

    const response = await api.post(`api-handler.php?action=listar_pausas_fila&id=${queueId}`);

    if (!response.success) {
        showNotification('Erro ao listar pausas', 'danger');
        return;
    }

    console.log('Pausas:', response.data);

    showNotification('Pausas carregadas (ver console)', 'info');
}

</script>
