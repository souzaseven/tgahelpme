/**
 * PAINEL DE CONTROLE DE OPERADORES - SCRIPT PRINCIPAL
 * Versão: 4.6
 * 
 * Melhorias desta versão:
 * - Correção definitiva no tratamento das filas dos operadores
 * - Melhor tratamento de dados da API
 * - Exibição correta de todas as filas por operador
 * - Logs de depuração aprimorados
 */

// Variáveis globais
let expandedMonitors = JSON.parse(localStorage.getItem('expandedMonitors')) || [];
let selectedAgents = JSON.parse(localStorage.getItem('selectedAgents')) || [];
let allOperators = [];
let filteredOperators = [];

/**
 * Alterna entre os temas claro e escuro
 */
function toggleTheme() {
    const body = document.body;
    const currentTheme = body.getAttribute('data-theme');
    const themeToggle = document.querySelector('.theme-toggle');
    
    if (currentTheme === 'dark') {
        body.setAttribute('data-theme', 'light');
        themeToggle.innerHTML = '<i class="fas fa-moon" aria-hidden="true"></i> Modo Escuro';
        localStorage.setItem('theme', 'light');
    } else {
        body.setAttribute('data-theme', 'dark');
        themeToggle.innerHTML = '<i class="fas fa-sun" aria-hidden="true"></i> Modo Claro';
        localStorage.setItem('theme', 'dark');
    }
}

/**
 * Carrega o tema salvo no localStorage ao iniciar
 */
function loadTheme() {
    const savedTheme = localStorage.getItem('theme') || 'dark';
    document.body.setAttribute('data-theme', savedTheme);
    
    const themeToggle = document.querySelector('.theme-toggle');
    if (savedTheme === 'dark') {
        themeToggle.innerHTML = '<i class="fas fa-sun" aria-hidden="true"></i> Modo Claro';
    } else {
        themeToggle.innerHTML = '<i class="fas fa-moon" aria-hidden="true"></i> Modo Escuro';
    }
}

/**
 * Atualiza o botão flutuante de "Abrir Selecionados"
 */
function updateOpenSelectedButton() {
    const selectedCount = selectedAgents.length;
    const openSelectedBtn = document.querySelector('.open-selected-container');
    
    if (selectedCount > 0) {
        openSelectedBtn.style.display = 'block';
        openSelectedBtn.innerHTML = `
            <button class="open-selected-btn" onclick="openSelectedAgents()" tabindex="0" 
                    aria-label="Abrir ${selectedCount} agentes selecionados">
                <i class="fas fa-external-link-alt" aria-hidden="true"></i> 
                Abrir Selecionados (${selectedCount})
            </button>
        `;
    } else {
        openSelectedBtn.style.display = 'none';
    }
}

/**
 * Mostra mensagem quando nenhum filtro está selecionado
 */
function showNoFiltersMessage() {
    const container = document.getElementById('operatorsContainer');
    container.innerHTML = `
        <div class="no-results">
            <i class="fas fa-info-circle" aria-hidden="true"></i>
            <p>Por favor, selecione pelo menos uma equipe ou grupo de fila para exibir os operadores.</p>
        </div>
    `;
    
    // Zerar as estatísticas
    document.getElementById('totalOperators').textContent = '0';
    document.getElementById('team1Count').textContent = '0';
    document.getElementById('team2Count').textContent = '0';
    document.getElementById('team3Count').textContent = '0';
}

/**
 * Mostra mensagem quando uma fila específica está vazia
 */
function showEmptyQueueMessage(queueName) {
    const container = document.getElementById('operatorsContainer');
    container.innerHTML = `
        <div class="no-results">
            <i class="fas fa-users-slash" aria-hidden="true"></i>
            <p>Nenhum operador encontrado na fila <strong>${queueName}</strong>.</p>
        </div>
    `;
    
    // Zerar as estatísticas
    document.getElementById('totalOperators').textContent = '0';
    document.getElementById('team1Count').textContent = '0';
    document.getElementById('team2Count').textContent = '0';
    document.getElementById('team3Count').textContent = '0';
}

/**
 * Realça o termo buscado no texto
 */
function highlightTerm(text, term) {
    if (!term) return text;
    const regex = new RegExp(`(${term})`, 'gi');
    return text.replace(regex, '<mark>$1</mark>');
}

/**
 * Filtra os operadores com base nos critérios selecionados
 */
function filterOperators() {
    console.log('Iniciando filtro...');
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const activeQueues = getActiveCheckboxes('queue');
    const activeTeams = getActiveCheckboxes('team');
    
    console.log('Filtros ativos - Filas:', activeQueues, 'Equipes:', activeTeams);
    console.log('Total de operadores:', allOperators.length);
    
    // Verificar se nenhum filtro está selecionado
    if (activeQueues.length === 0 && activeTeams.length === 0) {
        console.log('Nenhum filtro selecionado');
        showNoFiltersMessage();
        return;
    }
    
    // Filtrar operadores
    filteredOperators = allOperators.filter(operator => {
        // Verificar se o operador tem filas válidas
        if (!operator.queues || operator.queues.length === 0) {
            console.warn('Operador sem filas válidas:', operator);
            return false;
        }

        const matchesSearch = searchTerm === '' || 
                            operator.name.toLowerCase().includes(searchTerm) || 
                            operator.monitor.toLowerCase().includes(searchTerm);

        // Filtro por fila - mostra os que estão em pelo menos uma das filas selecionadas
        const matchesQueue = activeQueues.length === 0 || 
                           operator.queues.some(queue => activeQueues.includes(queue));
        
        // Filtro por equipe - se nenhuma equipe marcada, mostra todas das filas selecionadas
        const matchesTeam = activeTeams.length === 0 || 
                          activeTeams.includes(operator.monitor);
        
        return matchesSearch && matchesQueue && matchesTeam;
    });
    
    console.log('Operadores filtrados:', filteredOperators.length);
    
    // Verificar se há apenas uma fila selecionada e nenhum operador nela
    if (activeQueues.length === 1 && activeTeams.length === 0 && filteredOperators.length === 0) {
        console.log('Fila vazia:', activeQueues[0]);
        showEmptyQueueMessage(activeQueues[0]);
        return;
    }
    
    updateStatistics();
    displayOperators();
    updateOpenSelectedButton();
}

/**
 * Obtém os valores dos checkboxes marcados
 */
function getActiveCheckboxes(name) {
    const checkboxes = document.querySelectorAll(`input[name="${name}"]:checked`);
    return Array.from(checkboxes).map(cb => cb.value.trim());
}

/**
 * Atualiza as estatísticas no dashboard
 */
function updateStatistics() {
    const total = filteredOperators.length;
    const team1Count = filteredOperators.filter(op => op.monitor === 'Alex Sandro Braulio').length;
    const team2Count = filteredOperators.filter(op => op.monitor === 'Daniel Feix').length;
    const team3Count = filteredOperators.filter(op => op.monitor === 'Willian Pereira Reis').length;
    
    document.getElementById('totalOperators').textContent = total;
    document.getElementById('team1Count').textContent = team1Count;
    document.getElementById('team2Count').textContent = team2Count;
    document.getElementById('team3Count').textContent = team3Count;
}

/**
 * Alterna a seleção de um agente
 */
function toggleAgentSelection(link, isSelected) {
    if (isSelected) {
        if (!selectedAgents.includes(link)) {
            selectedAgents.push(link);
        }
    } else {
        selectedAgents = selectedAgents.filter(item => item !== link);
    }
    
    // Atualizar visualmente o item
    document.querySelectorAll(`.agent-item[data-link="${link}"]`).forEach(item => {
        item.classList.toggle('selected', isSelected);
    });
    
    // Salvar no localStorage
    localStorage.setItem('selectedAgents', JSON.stringify(selectedAgents));
    updateOpenSelectedButton();
}

/**
 * Abre os agentes selecionados
 */
function openSelectedAgents() {
    if (selectedAgents.length === 0) {
        alert('Nenhum agente selecionado!');
        return;
    }
    
    // Abrir cada link em uma nova aba (com pequeno delay para evitar bloqueio do navegador)
    selectedAgents.forEach((link, index) => {
        setTimeout(() => {
            window.open(link, '_blank');
        }, index * 100);
    });
}

/**
 * Cria o HTML para um item de agente
 */
function createAgentItem(agent, searchTerm) {
    const highlightedName = highlightTerm(agent.name, searchTerm);
    // Mostra todas as filas separadas por vírgula
    const highlightedQueues = highlightTerm(agent.queues.join(', '), searchTerm);
    const isSelected = selectedAgents.includes(agent.link);
    
    return `
        <div class="agent-item ${isSelected ? 'selected' : ''}" data-link="${agent.link}">
            <div class="agent-select">
                <input type="checkbox" ${isSelected ? 'checked' : ''} 
                       onchange="toggleAgentSelection('${agent.link}', this.checked)"
                       aria-label="Selecionar ${agent.name}">
            </div>
            <div class="agent-info">
                <div class="agent-name">${highlightedName}</div>
                <div class="agent-queue">${highlightedQueues}</div>
            </div>
            <div class="agent-actions">
                <a href="${agent.link}" target="_blank" class="agent-link" aria-label="Acessar ${agent.name}">
                    <i class="fas fa-external-link-alt" aria-hidden="true"></i> Acessar
                </a>
            </div>
        </div>
    `;
}

/**
 * Exibe os operadores agrupados por monitor
 */
function displayOperators() {
    const container = document.getElementById('operatorsContainer');
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    
    container.innerHTML = '';
    
    // Se nenhum resultado
    if (filteredOperators.length === 0) {
        container.innerHTML = '<div class="no-results">Nenhum operador encontrado com os filtros atuais.</div>';
        return;
    }
    
    // Agrupar operadores por monitor e ordenar alfabeticamente
    const groupedByMonitor = filteredOperators.reduce((acc, operator) => {
        if (!acc[operator.monitor]) {
            acc[operator.monitor] = [];
        }
        acc[operator.monitor].push(operator);
        return acc;
    }, {});
    
    // Ordenar os monitores alfabeticamente
    const sortedMonitors = Object.keys(groupedByMonitor).sort((a, b) => a.localeCompare(b));
    
    // Criar blocos para cada monitor
    for (const monitor of sortedMonitors) {
        const agents = groupedByMonitor[monitor];
        
        // Ordenar agentes alfabeticamente
        agents.sort((a, b) => a.name.localeCompare(b.name));
        
        const monitorBlock = document.createElement('div');
        monitorBlock.className = 'monitor-block';
        monitorBlock.dataset.monitor = monitor;
        
        // Cabeçalho do monitor
        const monitorHeader = document.createElement('div');
        monitorHeader.className = 'monitor-header';
        monitorHeader.innerHTML = `
            <h2>${highlightTerm(monitor, searchTerm)}</h2>
            <div class="monitor-actions">
                <button class="monitor-btn open-all-btn" data-monitor="${monitor}" aria-label="Abrir todos os agentes de ${monitor}">
                    <i class="fas fa-external-link-alt" aria-hidden="true"></i> Abrir Todos
                </button>
                <button class="monitor-btn toggle-agents-btn" aria-label="Mostrar/ocultar agentes">
                    <i class="fas fa-chevron-${expandedMonitors.includes(monitor) ? 'up' : 'down'}" aria-hidden="true"></i>
                </button>
            </div>
        `;
        
        // Lista de agentes
        const agentsList = document.createElement('div');
        agentsList.className = 'agents-list';
        
        // Adicionar cada agente
        agents.forEach(agent => {
            agentsList.innerHTML += createAgentItem(agent, searchTerm);
        });
        
        // Restaurar estado expandido se estava expandido antes
        if (expandedMonitors.includes(monitor)) {
            agentsList.style.display = 'block';
        }
        
        monitorBlock.appendChild(monitorHeader);
        monitorBlock.appendChild(agentsList);
        container.appendChild(monitorBlock);
    }
    
    // Adicionar eventos aos botões
    addMonitorEvents();
}

/**
 * Adiciona eventos aos botões dos blocos de monitor
 */
function addMonitorEvents() {
    // Botão para abrir/fechar lista de agentes
    document.querySelectorAll('.toggle-agents-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const monitorBlock = this.closest('.monitor-block');
            const monitor = monitorBlock.dataset.monitor;
            const agentsList = this.closest('.monitor-header').nextElementSibling;
            const icon = this.querySelector('i');
            
            if (agentsList.style.display === 'block') {
                agentsList.style.display = 'none';
                icon.className = 'fas fa-chevron-down';
                expandedMonitors = expandedMonitors.filter(m => m !== monitor);
            } else {
                agentsList.style.display = 'block';
                icon.className = 'fas fa-chevron-up';
                if (!expandedMonitors.includes(monitor)) {
                    expandedMonitors.push(monitor);
                }
            }
            
            localStorage.setItem('expandedMonitors', JSON.stringify(expandedMonitors));
        });
    });
    
    // Botão para abrir todos os links de um monitor
    document.querySelectorAll('.open-all-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const monitor = this.getAttribute('data-monitor');
            const agents = filteredOperators.filter(op => op.monitor === monitor);
            
            // Abrir com pequeno delay para evitar bloqueio do navegador
            agents.forEach((agent, index) => {
                setTimeout(() => {
                    window.open(agent.link, '_blank');
                }, index * 100);
            });
        });
    });
    
    // Clicar no cabeçalho do monitor alterna a visibilidade dos agentes
    document.querySelectorAll('.monitor-header').forEach(header => {
        header.addEventListener('click', function(e) {
            if (!e.target.closest('.monitor-btn')) {
                const toggleBtn = this.querySelector('.toggle-agents-btn');
                toggleBtn.click();
            }
        });
    });
}

/**
 * Carrega os operadores da API
 */
async function loadOperators() {
    try {
        console.log('Carregando operadores...');
        const response = await fetch('operadores.php');
        
        if (!response.ok) {
            throw new Error(`Erro HTTP: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Dados recebidos da API:', data);
        
        if (data.success && data.data) {
            allOperators = data.data.map(op => {
                // Tratamento robusto das filas
                let queues = [];
                
                // Verifica se existe a propriedade filas
               // if (op.fila !== undefined && op.fila !== null) {
                    // Se for string, tenta parsear como JSON ou usa como valor único
if (op.fila !== undefined && op.fila !== null) {
    if (typeof op.fila === 'string') {
        try {
            const parsed = JSON.parse(op.fila);
            queues = Array.isArray(parsed) ? parsed : [parsed];
        } catch (e) {
            queues = [op.fila];
        }
    } else if (Array.isArray(op.fila)) {
        queues = op.fila;
    } else {
        queues = [String(op.fila)];
    }
}

                
                // Remove valores vazios, nulos ou undefined
                queues = queues.filter(q => q !== null && q !== undefined && q.toString().trim() !== '');
                
                return {
                    name: op.nome || 'Sem nome',
                    monitor: op.lider || 'Sem monitor',
                    queues: queues.length > 0 ? queues : ['Sem fila definida'],
                    link: op.link || '#'
                };
            });
            
            console.log('Operadores mapeados:', allOperators);
            filterOperators();
        } else {
            console.error('Erro na resposta da API:', data.message);
            alert('Erro ao carregar operadores. Verifique o console para mais detalhes.');
        }
    } catch (error) {
        console.error('Falha na requisição:', error);
        alert('Falha ao carregar os operadores. Verifique o console para mais detalhes.');
    }
}

/**
 * Inicializa o painel
 */
function init() {
    console.log('Inicializando painel...');
    loadTheme();
    loadOperators();
    
    // Adicionar eventos
    document.querySelector('.theme-toggle').addEventListener('click', toggleTheme);
    document.getElementById('searchInput').addEventListener('input', filterOperators);
    
    document.querySelectorAll('input[name="queue"], input[name="team"]').forEach(input => {
        input.addEventListener('change', filterOperators);
    });
    
    updateOpenSelectedButton();
}

// Inicializar quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', init);



