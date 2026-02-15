<?php
// Pega os filtros da URL
$filaFiltro = $_GET['fila'] ?? 'todos';
$statusFiltro = $_GET['status'] ?? 'todos';
$limit = $_GET['limit'] ?? 50;
$page = $_GET['page'] ?? 1;

// Busca agentes
$agentes = $api->getAgentes(['limit' => $limit, 'page' => $page]);

// Busca filas para o filtro (com tratamento de erro)
$filas = ['success' => false, 'data' => []];
try {
    $filas = $api->getFilas(['limit' => 100]);
} catch (Exception $e) {
    // Se falhar, continua sem filtro de filas
}

$mapFilas = [];

if ($filas['success'] && !empty($filas['data'])) {
    foreach ($filas['data'] as $fila) {
        $mapFilas[$fila['id']] = $fila['name'];
    }
}

?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">👤 Gerenciamento de Agentes</h2>
        <button class="btn btn-primary" onclick="abrirNovoAgente()">
            ➕ Novo Agente
        </button>
    </div>
    <div class="card-body">
        <!-- Filtros Avançados -->
        <div class="card" style="background: var(--bg-tertiary); padding: 1rem; margin-bottom: 1.5rem;">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">🔍 Buscar por nome/login</label>
                    <input type="text" 
                           class="form-control" 
                           id="searchInput"
                           placeholder="Digite para buscar..." 
                           onkeyup="filterTable('tabelaAgentes', this.value)">
                </div>

                <div class="form-group">
                    <label class="form-label">👥 Filtrar por Fila</label>
                    <select class="form-control" id="filaFiltro" onchange="aplicarFiltroFila()">
                        <option value="todos">📊 Todas as Filas</option>
                        <option value="sem_fila">❌ Sem Fila</option>
                        <?php if ($filas['success'] && !empty($filas['data'])): ?>
                            <?php foreach ($filas['data'] as $fila): ?>
                                <option value="<?= $fila['id'] ?? '' ?>">
                                    Fila <?= $fila['id'] ?? '' ?> - <?= htmlspecialchars($fila['name'] ?? $fila['nome'] ?? 'Sem nome') ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">📊 Filtrar por Status</label>
<select class="form-control" id="statusFiltro" onchange="aplicarFiltroStatus()">
    <option value="todos">🌐 Todos</option>
    <option value="disponivel">🟢 Logados (Disponíveis)</option>
    <option value="pausado">⏸️ Em Pausa</option>
    <option value="offline">⚪ Offline</option>
    <option value="desabilitado">🔴 Arquivados</option>
</select>


                </div>

                <div class="form-group" style="display: flex; align-items: flex-end; gap: 0.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="limparFiltros()">
                        🔄 Limpar
                    </button>
                    <button type="button" class="btn btn-info" onclick="exportarAgentes()">
                        📥 Exportar
                    </button>
                </div>
            </div>
        </div>

        <!-- Estatísticas Rápidas -->
        <div class="dashboard-grid" style="margin-bottom: 1.5rem;">
            <div class="card stats-card" style="padding: 1rem;">
                <div class="stats-label">Total</div>
                <div class="stats-value" style="font-size: 1.5rem;" id="totalAgentes">-</div>
            </div>
            <div class="card stats-card" style="padding: 1rem;">
                <div class="stats-label">Logados</div>
                <div class="stats-value" style="font-size: 1.5rem; color: var(--success);" id="totalLogados">-</div>
            </div>
            <div class="card stats-card" style="padding: 1rem;">
                <div class="stats-label">Em Pausa</div>
                <div class="stats-value" style="font-size: 1.5rem; color: var(--warning);" id="totalPausados">-</div>
            </div>
            <div class="card stats-card" style="padding: 1rem;">
                <div class="stats-label">Ativos</div>
                <div class="stats-value" style="font-size: 1.5rem; color: var(--info);" id="totalAtivos">-</div>
            </div>

<div class="card stats-card" style="padding: 1rem;">
    <div class="stats-label">Offline</div>
    <div class="stats-value" style="font-size: 1.5rem; color: #cbd5e1;" id="totalOffline">-</div>
</div>

        </div>

        <!-- Tabela de Agentes -->
        <div class="table-responsive">
            <table id="tabelaAgentes">
<thead>
    <tr>
        <th onclick="ordenarTabela(0)">ID ⬍</th>
        <th onclick="ordenarTabela(1)">Nome ⬍</th>
        <th onclick="ordenarTabela(2)">Login ⬍</th>
        <th onclick="ordenarTabela(3)">Ramal ⬍</th>
        <th onclick="ordenarTabela(4)">Filas ⬍</th>
        <th onclick="ordenarTabela(5)">Status ⬍</th>
        <th onclick="ordenarTabela(6)">Pausa ⬍</th>
        <th onclick="ordenarTabela(7)">Último Login ⬍</th>
        <th>Ações</th> 
</thead>


                <tbody>
                  <?php if ($agentes['success'] && !empty($agentes['data'])): ?>
<?php 
$totalAgentes = 0;
$totalLogados = 0;
$totalPausados = 0;
$totalAtivos = 0;
$totalOffline = 0;

foreach ($agentes['data'] as $agente):

    $isLogado = !empty($agente['last_login']) 
        && empty($agente['last_login']['time_logoff']);

    $estaPausado = !empty($agente['current_pause']);
    $ramalNumero = $agente['current_extension']['number'] ?? '-';
    $filasAgente = $agente['queues'] ?? [];

    // 🔥 DEFINE O STATUS PRIMEIRO
    if (!$agente['enable'] || $agente['archived']) {
        $statusRow = 'desabilitado';
    } elseif ($isLogado && $estaPausado) {
        $statusRow = 'pausado';
    } elseif ($isLogado && !$estaPausado) {
        $statusRow = 'disponivel';
    } else {
        $statusRow = 'offline';
    }

    // 🔥 DEPOIS SOMA
    $totalAgentes++;

    if ($statusRow === 'disponivel') {
        $totalLogados++;
        $totalAtivos++;
    }

    if ($statusRow === 'pausado') {
        $totalLogados++;
        $totalPausados++;
    }

    if ($statusRow === 'offline') {
        $totalOffline++;
    }
?>


<tr data-fila="<?= implode(',', $filasAgente) ?>" 
    data-status="<?= $statusRow ?>">

                                <td><strong><?= htmlspecialchars($agente['id']) ?></strong></td>
                                <td><?= htmlspecialchars($agente['name']) ?></td>
                                <td><?= htmlspecialchars($agente['login']) ?></td>
                                <td>
                                    <?php if ($ramalNumero !== '-'): ?>
                                        <span class="badge badge-info"><?= htmlspecialchars($ramalNumero) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">-</span>
                                    <?php endif; ?>
                                </td>
                               <td>
<?php if (!empty($filasAgente)): ?>

    <?php
        $nomesFilas = [];

        foreach ($filasAgente as $filaId) {
            if (isset($mapFilas[$filaId])) {
                $nomesFilas[] = $mapFilas[$filaId];
            }
        }
    ?>

    <?php foreach ($nomesFilas as $nomeFila): ?>
        <span class="badge badge-primary">
            <?= htmlspecialchars($nomeFila) ?>
        </span>
    <?php endforeach; ?>

<?php else: ?>
    <span class="badge badge-secondary">Sem fila</span>
<?php endif; ?>
</td>

                                <td>
                                    <?php if ($agente['enable'] && !$agente['archived']): ?>
                                        <span class="badge badge-<?= $isLogado ? 'success' : 'warning' ?>">
                                            <?= $isLogado ? '✓ Logado' : '○ Disponível' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">
                                            <?= $agente['archived'] ? '📦 Arquivado' : '🔴 Desabilitado' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                      <?php if ($estaPausado): ?>

    <?php 
        $pauseId = $agente['current_pause']['reason']['id'] ?? null;

        $mapPausas = [
            1 => '🍽️ Almoço',
            2 => '☕ Lanche Manhã',
            3 => '🍵 Lanche Tarde'
        ];

        $pausaNome = $mapPausas[$pauseId] ?? '⏸️ Em Pausa';
    ?>

    <span class="badge badge-warning">
        <?= $pausaNome ?>
    </span>

<?php elseif ($isLogado): ?>
    <span class="badge badge-success">🟢 Disponível</span>
<?php else: ?>
    <span class="badge badge-secondary">Offline</span>
<?php endif; ?>

                                </td>
                                <td>
                                    <?php if (!empty($agente['last_login'])): ?>
                                        <small><?= date('d/m/Y H:i', strtotime($agente['last_login']['time_login'])) ?></small>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">Nunca</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <?php if ($isLogado && $estaPausado): ?>
                                            <button class="btn btn-sm btn-success" 
                                                    onclick="despausarAgente(<?= $agente['id'] ?>)"
                                                    title="Despausar">▶️</button>
                                        <?php elseif ($isLogado && !$estaPausado): ?>
                                            <button class="btn btn-sm btn-warning" 
                                                    onclick="abrirModalPausa(<?= $agente['id'] ?>)"
                                                    title="Pausar">⏸️</button>
                                        <?php endif; ?>
                                        
                                        <?php if ($isLogado): ?>
                                            <button class="btn btn-sm btn-danger" 
                                                    onclick="deslogarAgente(<?= $agente['id'] ?>)"
                                                    title="Deslogar">🚪</button>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-sm btn-secondary" 
                                                onclick='editarAgente(<?= json_encode($agente) ?>)'
                                                title="Editar">✏️</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <script>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('totalAgentes').textContent = <?= $totalAgentes ?>;
    document.getElementById('totalLogados').textContent = <?= $totalLogados ?>;
    document.getElementById('totalPausados').textContent = <?= $totalPausados ?>;
    document.getElementById('totalAtivos').textContent = <?= $totalAtivos ?>;
    document.getElementById('totalOffline').textContent = <?= $totalOffline ?>;
});
</script>

                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center">
                                <?= $agentes['success'] ? 'Nenhum agente cadastrado' : 'Erro: ' . ($agentes['error'] ?? 'Desconhecido') ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
<?php if (!empty($agentes['pagination'])): 

    $total  = $agentes['pagination']['total'] ?? 0;
    $limit  = (int)$limit;
    $offset = $agentes['pagination']['offset'] ?? 0;

    $totalPages  = $limit > 0 ? ceil($total / $limit) : 1;
    $currentPage = $limit > 0 ? floor($offset / $limit) + 1 : 1;
?>

<div style="margin-top: 1.5rem; text-align: center;">

    <div style="margin-bottom: 0.5rem; color: var(--text-secondary);">
        Página <?= $currentPage ?> de <?= $totalPages ?> 
        | Total: <?= $total ?> agentes
    </div>

    <div style="display:flex; justify-content:center; gap:0.5rem; flex-wrap:wrap;">

        <?php if ($currentPage > 1): ?>
            <a class="btn btn-secondary btn-sm"
               href="?page=agentes&limit=<?= $limit ?>&page=<?= $currentPage - 1 ?>">
               ⬅ Anterior
            </a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a class="btn btn-sm <?= $i == $currentPage ? 'btn-primary' : 'btn-light' ?>"
               href="?page=agentes&limit=<?= $limit ?>&page=<?= $i ?>">
               <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($currentPage < $totalPages): ?>
            <a class="btn btn-secondary btn-sm"
               href="?page=agentes&limit=<?= $limit ?>&page=<?= $currentPage + 1 ?>">
               Próxima ➡
            </a>
        <?php endif; ?>

    </div>
</div>

<?php endif; ?>

    </div>
</div>

<!-- Modal Novo/Editar Agente -->
<div id="modalAgente" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="modalAgenteTitle">Novo Agente</h3>
        </div>
        <form id="formAgente" onsubmit="salvarAgente(event)">
            <input type="hidden" id="agenteId" name="id">
            
            <div class="form-group">
                <label class="form-label">Nome *</label>
                <input type="text" class="form-control" id="agenteNome" name="name" required>
            </div>

            <div class="form-group">
                <label class="form-label">Login *</label>
                <input type="text" class="form-control" id="agenteLogin" name="login" required>
                <small style="color: var(--text-muted);">Exemplo: anderson.souza</small>
            </div>

            <div class="form-group">
                <label class="form-label">Senha *</label>
                <input type="password" class="form-control" id="agenteSenha" name="password" required>
                <small style="color: var(--text-muted);">Mínimo 8 caracteres</small>
            </div>

            <div class="form-group">
                <label class="form-label">Filas (IDs separados por vírgula)</label>
                <input type="text" class="form-control" id="agenteFilas" name="queues" 
                       placeholder="3,7">
                <small style="color: var(--text-muted);">Exemplo: 3,7</small>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalAgente')">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    💾 Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Pausar Agente -->
<div id="modalPausarAgente" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Pausar Agente</h3>
        </div>

        <input type="hidden" id="pausarAgenteId">

        <div class="form-group">
            <label class="form-label">Selecione o motivo da pausa *</label>

            <div style="display:flex; gap:1rem; flex-wrap:wrap;">

                <button type="button" class="btn btn-warning"
                    onclick="pausarDireto(1)">
                    🍽️ Almoço
                </button>

                <button type="button" class="btn btn-info"
                    onclick="pausarDireto(2)">
                    ☕ Lanche Manhã
                </button>

                <button type="button" class="btn btn-primary"
                    onclick="pausarDireto(3)">
                    🍵 Lanche Tarde
                </button>

            </div>
        </div>

        <div class="alert alert-info" style="margin-top:1.5rem;">
            <strong>ℹ️ Informativo:</strong><br>
            1 - Almoço<br>
            2 - Lanche da Manhã<br>
            3 - Lanche da Tarde<br><br>
            
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary"
                onclick="closeModal('modalPausarAgente')">
                Cancelar
            </button>
        </div>

    </div>
</div>



<script>
// Filtros
function aplicarFiltroFila() {
    const fila = document.getElementById('filaFiltro').value;
    const rows = document.querySelectorAll('#tabelaAgentes tbody tr');
    
    rows.forEach(row => {
        if (fila === 'todos') {
            row.style.display = '';
        } else if (fila === 'sem_fila') {
            const filasRow = row.getAttribute('data-fila');
            row.style.display = !filasRow || filasRow === '' ? '' : 'none';
        } else {
            const filasRow = row.getAttribute('data-fila').split(',');
            row.style.display = filasRow.includes(fila) ? '' : 'none';
        }
    });
    
    atualizarEstatisticas();
}

function aplicarFiltroStatus() {
    const status = document.getElementById('statusFiltro').value;
    const rows = document.querySelectorAll('#tabelaAgentes tbody tr');
    
    rows.forEach(row => {
        const statusRow = row.getAttribute('data-status');

        if (status === 'todos') {
            row.style.display = '';
        } else {
            row.style.display = statusRow === status ? '' : 'none';
        }
    });

    atualizarEstatisticas();
}


function atualizarEstatisticas() {
    const rows = document.querySelectorAll('#tabelaAgentes tbody tr');
    let total = 0, logados = 0, pausados = 0, disponiveis = 0, offline = 0;

    rows.forEach(row => {
        if (row.style.display !== 'none') {
            total++;
            const status = row.getAttribute('data-status');

            if (status === 'disponivel' || status === 'pausado') logados++;
            if (status === 'pausado') pausados++;
            if (status === 'disponivel') disponiveis++;
            if (status === 'offline') offline++;
        }
    });

    document.getElementById('totalAgentes').textContent = total;
    document.getElementById('totalLogados').textContent = logados;
    document.getElementById('totalPausados').textContent = pausados;
    document.getElementById('totalAtivos').textContent = disponiveis;
    document.getElementById('totalOffline').textContent = offline;
}




function limparFiltros() {
    document.getElementById('filaFiltro').value = 'todos';
    document.getElementById('statusFiltro').value = 'todos';
    document.getElementById('searchInput').value = '';
    aplicarFiltroFila();
    aplicarFiltroStatus();
}

function exportarAgentes() {
    const table = document.getElementById('tabelaAgentes');
    const data = [];
    
    table.querySelectorAll('tbody tr').forEach(row => {
        if (row.style.display !== 'none') {
            const cells = row.querySelectorAll('td');
            if (cells.length > 1) {
                data.push({
                    ID: cells[0].textContent.trim(),
                    Nome: cells[1].textContent.trim(),
                    Login: cells[2].textContent.trim(),
                    Ramal: cells[3].textContent.trim(),
                    Status: cells[5].textContent.trim(),
                    Pausa: cells[6].textContent.trim()
                });
            }
        }
    });
    
    exportToCSV(data, 'agentes_' + new Date().toISOString().split('T')[0] + '.csv');
}

function abrirNovoAgente() {
    document.getElementById('modalAgenteTitle').textContent = 'Novo Agente';
    clearForm('formAgente');
    document.getElementById('agenteSenha').required = true;
    document.getElementById('agenteSenha').placeholder = '';
    openModal('modalAgente');
}

function editarAgente(agente) {
    document.getElementById('modalAgenteTitle').textContent = 'Editar Agente';
    document.getElementById('agenteId').value = agente.id || '';
    document.getElementById('agenteNome').value = agente.name || '';
    document.getElementById('agenteLogin').value = agente.login || '';
    document.getElementById('agenteFilas').value = (agente.queues || []).join(',');
    document.getElementById('agenteSenha').required = false;
    document.getElementById('agenteSenha').placeholder = 'Deixe em branco para manter a senha atual';
    openModal('modalAgente');
}

async function salvarAgente(event) {
    event.preventDefault();
    if (!validateForm('formAgente')) return;
    
    const formData = new FormData(event.target);
    const data = {
        name: formData.get('name'),
        login: formData.get('login'),
        password: formData.get('password'),
        queues: formData.get('queues') ? formData.get('queues').split(',').map(q => parseInt(q.trim())) : []
    };
    
    if (!data.password) delete data.password;
    const agenteId = formData.get('id');
    
    try {
        showNotification('Salvando agente...', 'info');
        const response = await api.post('api-handler.php?action=' + (agenteId ? 'editar' : 'salvar') + '_agente', {
            id: agenteId,
            ...data
        });
        
        if (response.success) {
            showNotification('Agente salvo com sucesso!', 'success');
            closeModal('modalAgente');
            clearForm('formAgente');
            setTimeout(() => atualizarAgentesSilencioso(), 800);
        } else {
            showNotification('Erro: ' + (response.meta?.message || 'Erro desconhecido'), 'danger');
        }
    } catch (error) {
        showNotification('Erro ao salvar agente: ' + error.message, 'danger');
    }
}

function abrirModalPausa(id) {
    document.getElementById('pausarAgenteId').value = id;
    openModal('modalPausarAgente');
}
function selecionarPausa(id) {
    document.getElementById('pauseId').value = id;
}

async function pausarDireto(pause_id) {

    const id = document.getElementById('pausarAgenteId').value;

    if (!pause_id) {
        showNotification('Motivo inválido', 'warning');
        return;
    }

    try {

        showNotification('Pausando agente...', 'info');

        const response = await api.post(
            `api-handler.php?action=pausar_agente&id=${id}`,
            { pause_id }
        );

        if (response.success) {

            showNotification('Agente pausado com sucesso!', 'success');

            closeModal('modalPausarAgente');

          setTimeout(() => atualizarAgentesSilencioso(), 800);


        } else {

            showNotification(
                'Erro: ' + (response.meta?.message || 'Erro ao pausar'),
                'danger'
            );
        }

    } catch (error) {

        showNotification(
            'Erro ao pausar agente: ' + error.message,
            'danger'
        );
    }
}



async function despausarAgente(id) {
    try {
        showNotification('Despausando agente...', 'info');
        const response = await api.post(`api-handler.php?action=despausar_agente&id=${id}`);
        
        if (response.success) {
            showNotification('Agente despausado com sucesso!', 'success');
            setTimeout(() => atualizarAgentesSilencioso(), 800);
        } else {
            showNotification('Erro: ' + (response.meta?.message || 'Erro ao despausar'), 'danger');
        }
    } catch (error) {
        showNotification('Erro ao despausar agente: ' + error.message, 'danger');
    }
}

async function deslogarAgente(id) {
    if (!confirm('Tem certeza que deseja deslogar este agente?')) return;
    
    try {
        showNotification('Deslogando agente...', 'info');
        const response = await api.post(`api-handler.php?action=deslogar_agente&id=${id}`);
        
        if (response.success) {
            showNotification('Agente deslogado com sucesso!', 'success');
            setTimeout(() => atualizarAgentesSilencioso(), 800);
        } else {
            showNotification('Erro: ' + (response.meta?.message || 'Erro ao deslogar'), 'danger');
        }
    } catch (error) {
        showNotification('Erro ao deslogar agente: ' + error.message, 'danger');
    }
}

async function atualizarAgentesSilencioso() {

    try {

        const response = await api.post('api-handler.php?action=listar_agentes');

        if (!response.success) {
            console.error('Erro ao atualizar agentes');
            return;
        }

        const agentes = response.data || [];

        const tbody = document.querySelector('#tabelaAgentes tbody');
        tbody.innerHTML = '';

        let total = 0;
        let logados = 0;
        let pausados = 0;
        let disponiveis = 0;
        let offline = 0;

        agentes.forEach(agente => {

            const isLogado = agente.last_login && !agente.last_login.time_logoff;
            const estaPausado = !!agente.current_pause;

            let status = 'offline';

            if (!agente.enable || agente.archived) {
                status = 'desabilitado';
            } else if (isLogado && estaPausado) {
                status = 'pausado';
            } else if (isLogado) {
                status = 'disponivel';
            }

            total++;

            if (status === 'disponivel' || status === 'pausado') logados++;
            if (status === 'pausado') pausados++;
            if (status === 'disponivel') disponiveis++;
            if (status === 'offline') offline++;

            const row = `
                <tr data-status="${status}">
                    <td><strong>${agente.id}</strong></td>
                    <td>${agente.name}</td>
                    <td>${agente.login}</td>
                    <td>${agente.current_extension?.number || '-'}</td>
                    <td>${agente.queues?.length || 0} fila(s)</td>
                    <td>${status}</td>
                    <td>${estaPausado ? '⏸️' : '-'}</td>
                    <td>${agente.last_login ? agente.last_login.time_login : 'Nunca'}</td>
                    <td>
                        <!-- mantém seus botões aqui -->
                    </td>
                </tr>
            `;

            tbody.insertAdjacentHTML('beforeend', row);

        });

        document.getElementById('totalAgentes').textContent = total;
        document.getElementById('totalLogados').textContent = logados;
        document.getElementById('totalPausados').textContent = pausados;
        document.getElementById('totalAtivos').textContent = disponiveis;
        document.getElementById('totalOffline').textContent = offline;

    } catch (error) {
        console.error('Erro atualização silenciosa:', error);
    }
}

let ordemAtual = {};
 
function ordenarTabela(colIndex) {

    const table = document.getElementById("tabelaAgentes");
    const tbody = table.querySelector("tbody");
    const rows = Array.from(tbody.querySelectorAll("tr"));

    const asc = ordemAtual[colIndex] !== true;
    ordemAtual[colIndex] = asc;

    rows.sort((a, b) => {

        let aText = a.children[colIndex].innerText.trim();
        let bText = b.children[colIndex].innerText.trim();

        // Remove emojis e símbolos
        aText = aText.replace(/[^\w\s:/-]/g, '');
        bText = bText.replace(/[^\w\s:/-]/g, '');

        // Detecta número
        if (!isNaN(aText) && !isNaN(bText)) {
            return asc 
                ? Number(aText) - Number(bText)
                : Number(bText) - Number(aText);
        }

        // Detecta data
        const dataRegex = /^\d{2}\/\d{2}\/\d{4}/;
        if (dataRegex.test(aText) && dataRegex.test(bText)) {

            const parseDate = (str) => {
                const [d,m,y] = str.split(' ')[0].split('/');
                return new Date(`${y}-${m}-${d}`);
            };

            return asc
                ? parseDate(aText) - parseDate(bText)
                : parseDate(bText) - parseDate(aText);
        }

        // Texto normal
        return asc
            ? aText.localeCompare(bText)
            : bText.localeCompare(aText);
    });

    rows.forEach(row => tbody.appendChild(row));
}

</script>
