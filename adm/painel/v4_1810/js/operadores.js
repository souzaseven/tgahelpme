const operadores = {
    // Renderizar tabela de operadores
    renderTable(data, pagination = null) {
        let html = `
            <div class="toolbar">
                <div class="search-container">
                    <i>🔍</i>
                    <input type="text" id="searchInput" placeholder="Buscar em operadores..." onkeyup="filterTable()">
                </div>
                <button class="btn btn-primary" onclick="operadores.showInsertForm()">
                    ➕ Novo Operador
                </button>
                <button class="btn btn-info" onclick="showFilaModal()">
                    🎯 Gerenciar Filas
                </button>
                <button class="btn btn-success" onclick="showLinksLiderModal()">
                    🌐 Abrir Links por Líder
                </button>
  <button class="btn btn-warning" onclick="showPainelOperadorModal()">
                📞 Painel do Operador
            </button>

            </div>
           
        `;

 html += `
    <div class="semana-telefone-resumo">
        <h2>📅 Semana de Telefone</h2>
        <div class="rotacao-telefone-grid-resumo">
            <div class="rotacao-telefone-item-resumo anterior">
                <span class="rotulo">Anterior</span>
                <span id="telefoneAnteriorResumo">Carregando...</span>
            </div>
            <div class="rotacao-telefone-item-resumo atual">
                <span class="rotulo">Atual</span>
                <span id="telefoneAtualResumo">Carregando...</span>
            </div>
            <div class="rotacao-telefone-item-resumo proxima">
                <span class="rotulo">Próxima</span>
                <span id="telefoneProximaResumo">Carregando...</span>
            </div>
        </div>
    </div>
    
    <h2 class="operadores-titulo">⚡ Operadores de Suporte</h2>
`;
        
        if (data.length === 0) {
            html += `<div class="no-data">📭 Nenhum operador encontrado.</div>`;
            document.getElementById("content").innerHTML = html;
            return;
        }

        html += ` 
<div class="table-container"><table id="dataTable"><thead><tr>
            <th>🆔 ID</th>
            <th>👤 Nome</th>
            <th>🔗 Link</th>
            <th>👑 Líder</th>
            <th>🎯 Fila</th>
            <th width="200">⚡ Ações</th>
        </tr></thead><tbody>`;

        // Linhas
        data.forEach(row => {
            html += `
                <tr>
                    <td>${row.id}</td>
                    <td title="${row.nome}">${truncateText(row.nome, 30)}</td>
                    <td title="${row.link}">${truncateText(row.link, 25)}</td>
                    <td>${row.lider}</td>
                    <td>${row.fila}</td>
                    <td class="actions">
                        <button class="btn btn-success btn-icon" onclick="operadores.editRow(${row.id})" title="Editar">
                            ✏️
                        </button>
                        <button class="btn btn-danger btn-icon" onclick="deleteRow('operadores', ${row.id})" title="Excluir">
                            🗑️
                        </button>
                    </td>
                </tr>
            `;
        });

        html += "</tbody></table></div>";
        
        // Paginação
        if (pagination) {
            currentTotal = pagination.total;
            html += renderPagination(pagination);
        }
        
        html += `<div class="stats">📈 Total: ${pagination?.total || data.length} operador(es) encontrado(s)</div>`;
        
        document.getElementById("content").innerHTML = html;
 this.carregarEquipesResumo();
    },

async carregarEquipesResumo() {
    try {
        const response = await fetch('operadores.php?_=' + Date.now());
        const data = await response.json();
        
        if (data.success && data.semana) {
            const equipeAtual = data.semana.telefone;
            const equipesFixas = ['Alex Sandro Braulio', 'Daniel Feix', 'Willian Pereira Reis'];
            
            const index = equipesFixas.findIndex(equipe => 
                equipe.toLowerCase() === equipeAtual.toLowerCase()
            );

            if (index !== -1) {
                document.getElementById('telefoneAnteriorResumo').textContent = 
                    equipesFixas[(index - 1 + equipesFixas.length) % equipesFixas.length];
                document.getElementById('telefoneAtualResumo').textContent = equipesFixas[index];
                document.getElementById('telefoneProximaResumo').textContent = 
                    equipesFixas[(index + 1) % equipesFixas.length];
            }
        }
    } catch (error) {
        console.error('Erro ao carregar equipes:', error);
        document.getElementById('telefoneAnteriorResumo').textContent = 'Erro';
        document.getElementById('telefoneAtualResumo').textContent = 'Erro';
        document.getElementById('telefoneProximaResumo').textContent = 'Erro';
    }
},

    // Mostrar formulário de inserção
    showInsertForm() {
        const formFields = `
            <div class="form-group">
                <label for="nome">
                    👤 Nome do Operador
                    <span class="required">*</span>
                </label>
                <input type="text" id="nome" class="form-control" 
                       placeholder="Digite o nome completo" required>
            </div>
            <div class="form-group">
                <label for="link">
                    🔗 Link
                    <span class="required">*</span>
                </label>
                <input type="text" id="link" class="form-control" 
                       placeholder="https://exemplo.com ou exemplo.com" required>
            </div>
            <div class="form-group">
                <label for="lider">
                    👑 Líder Responsável
                    <span class="required">*</span>
                </label>
                <select id="lider" class="form-control" required>
                    <option value="">Selecione...</option>
                    <option value="Alex Sandro Braulio">Alex Sandro Braulio</option>
                    <option value="Daniel Feix">Daniel Feix</option>
                    <option value="Willian Pereira Reis">Willian Pereira Reis</option>
                </select>
            </div>
            <div class="form-group">
                <label for="fila">
                    🎯 Fila de Atendimento
                    <span class="required">*</span>
                </label>
                <select id="fila" class="form-control" required>
                    <option value="">Selecione...</option>
                    <option value="Suporte Matriz">Suporte Matriz</option>
                    <option value="Fila Matriz Chat/Whats">Fila Matriz Chat/Whats</option>
                </select>
            </div>
        `;

        document.getElementById("content").innerHTML = `
            <div class="form-container">
                <div class="form-header">
                    <h2>➕ Novo Operador</h2>
                    <p>Preencha os dados abaixo para adicionar um novo operador</p>
                </div>
                <form id="insertForm" onsubmit="return operadores.insertRow(event)">
                    <div class="form-grid">
                        ${formFields}
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="loadData('operadores')">
                            ↩️ Voltar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            💾 Salvar Operador
                        </button>
                    </div>
                </form>
            </div>
        `;
    },

    // Inserir operador
    async insertRow(e) {
        e.preventDefault();
        const submitButton = e.target.querySelector('button[type="submit"]');
        
        return await withLoading(submitButton, async () => {
            const formData = {
                nome: document.getElementById('nome').value.trim(),
                link: document.getElementById('link').value.trim(),
                lider: document.getElementById('lider').value,
                fila: document.getElementById('fila').value
            };
            
            // Validações
            if (!formData.nome || !formData.link || !formData.lider || !formData.fila) {
                throw new Error('Todos os campos obrigatórios devem ser preenchidos');
            }

            const data = await apiRequest('api.php?action=insert&table=operadores', {
                method: "POST",
                body: JSON.stringify(formData)
            });
            
            showAlert('✅ ' + data.message, 'success');
            loadData('operadores');
        });
    },

    // Editar operador
    async editRow(id) {
        try {
            const data = await apiRequest(`api.php?action=get&table=operadores&id=${id}`);
            const record = data.data;
            
            const formFields = `
                <div class="form-group">
                    <label for="nome">
                        👤 Nome do Operador
                        <span class="required">*</span>
                    </label>
                    <input type="text" id="nome" class="form-control" 
                           placeholder="Digite o nome completo" 
                           value="${escapeHtml(record.nome || '')}" required>
                </div>
                <div class="form-group">
                    <label for="link">
                        🔗 Link
                        <span class="required">*</span>
                    </label>
                    <input type="text" id="link" class="form-control" 
                           placeholder="https://exemplo.com ou exemplo.com"
                           value="${escapeHtml(record.link || '')}" required>
                </div>
                <div class="form-group">
                    <label for="lider">
                        👑 Líder Responsável
                        <span class="required">*</span>
                    </label>
                    <select id="lider" class="form-control" required>
                        <option value="">Selecione...</option>
                        <option value="Alex Sandro Braulio" ${record.lider === 'Alex Sandro Braulio' ? 'selected' : ''}>Alex Sandro Braulio</option>
                        <option value="Daniel Feix" ${record.lider === 'Daniel Feix' ? 'selected' : ''}>Daniel Feix</option>
                        <option value="Willian Pereira Reis" ${record.lider === 'Willian Pereira Reis' ? 'selected' : ''}>Willian Pereira Reis</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="fila">
                        🎯 Fila de Atendimento
                        <span class="required">*</span>
                    </label>
                    <select id="fila" class="form-control" required>
                        <option value="">Selecione...</option>
                        <option value="Suporte Matriz" ${record.fila === 'Suporte Matriz' ? 'selected' : ''}>Suporte Matriz</option>
                        <option value="Fila Matriz Chat/Whats" ${record.fila === 'Fila Matriz Chat/Whats' ? 'selected' : ''}>Fila Matriz Chat/Whats</option>
                    </select>
                </div>
            `;

            document.getElementById("content").innerHTML = `
                <div class="form-container">
                    <div class="form-header">
                        <h2>✏️ Editar Operador #${id}</h2>
                        <p>Atualize os dados abaixo</p>
                    </div>
                    <form id="updateForm" onsubmit="return operadores.updateRow(event, ${id})">
                        <div class="form-grid">
                            ${formFields}
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="loadData('operadores')">
                                ↩️ Voltar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                💾 Atualizar Operador
                            </button>
                        </div>
                    </form>
                </div>
            `;
            
        } catch (error) {
            showAlert('❌ ' + error.message, 'error');
        }
    },

    // Atualizar operador
    async updateRow(e, id) {
        e.preventDefault();
        const submitButton = e.target.querySelector('button[type="submit"]');
        
        return await withLoading(submitButton, async () => {
            const formData = {
                nome: document.getElementById('nome').value.trim(),
                link: document.getElementById('link').value.trim(),
                lider: document.getElementById('lider').value,
                fila: document.getElementById('fila').value
            };
            
            // Validações
            if (!formData.nome || !formData.link || !formData.lider || !formData.fila) {
                throw new Error('Todos os campos obrigatórios devem ser preenchidos');
            }

            const data = await apiRequest(`api.php?action=update&table=operadores&id=${id}`, {
                method: "POST",
                body: JSON.stringify(formData)
            });
            
            showAlert('✅ ' + data.message, 'success');
            loadData('operadores');
        });
    }
};

/**
 * FUNÇÃO PARA CARREGAR EQUIPES DA SEMANA
 * Adicione esta função FORA do objeto 'operadores'
 */
function carregarEquipesSemana() {
    document.getElementById("content").innerHTML = `
        <div class="toolbar">
            <button class="btn btn-secondary" onclick="loadData('operadores')">
                ↩️ Voltar para Lista
            </button>
            <button class="btn btn-primary" onclick="carregarEquipesSemana()">
                🔄 Atualizar
            </button>
        </div>
        
        <div id="equipesSemanaContainer">
            <div class="loading-equipes">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Carregando equipes da semana...</p>
            </div>
        </div>
    `;
    
    // Chama a função que você já tem implementada
    if (typeof equipesSemana !== 'undefined' && typeof equipesSemana.init === 'function') {
        equipesSemana.init();
    } else {
        // Fallback caso a função principal não esteja disponível
        setTimeout(() => {
            document.getElementById('equipesSemanaContainer').innerHTML = `
                <div class="error-equipes">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Sistema de equipes não disponível</p>
                    <p>Verifique se o arquivo equipes-semana.js está carregado</p>
                </div>
            `;
        }, 1000);
    }
}


