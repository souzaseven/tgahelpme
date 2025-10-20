let currentTable = '';
let currentPage = 1;
let currentLimit = 50;
let currentTotal = 0;

// Carregar dados iniciais
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById("content").innerHTML = `
        <div class="text-center" style="padding: 4rem 2rem;">
            <h1 style="font-size: 2.5rem; margin-bottom: 1rem;">🚀 Painel Administrativo</h1>
            <p class="text-muted" style="font-size: 1.2rem;">Selecione uma tabela no menu acima para começar</p>
        </div>
    `;
});

// Função auxiliar para mostrar loading em botões
async function withLoading(button, callback) {
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="loading-spinner"></span> Processando...';
    button.disabled = true;
    
    try {
        const result = await callback();
        return result;
    } finally {
        button.innerHTML = originalText;
        button.disabled = false;
    }
}

// Função para tratamento de erros da API
function handleApiError(error, defaultMessage = 'Erro inesperado') {
    console.error('Erro API:', error);
    
    if (error.message.includes('Failed to fetch')) {
        return 'Erro de conexão. Verifique sua internet.';
    }
    if (error.message.includes('500')) {
        return 'Erro interno do servidor. Tente novamente.';
    }
    if (error.message.includes('404')) {
        return 'Recurso não encontrado.';
    }
    if (error.message.includes('403')) {
        return 'Acesso não autorizado.';
    }
    
    return error.message || defaultMessage;
}

// Função para fazer requisições com tratamento de erro
async function apiRequest(url, options = {}) {
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': CSRF_TOKEN
        }
    };
    
    const finalOptions = { ...defaultOptions, ...options };
    
    try {
        const response = await fetch(url, finalOptions);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message);
        }
        
        return data;
    } catch (error) {
        throw new Error(handleApiError(error));
    }
}

// Carregar dados da tabela
async function loadData(table, page = 1, limit = 50) {
    currentTable = table;
    currentPage = page;
    currentLimit = limit;
    const content = document.getElementById("content");
    
    try {
        content.innerHTML = '<div class="loading pulse">⏳ Carregando dados...</div>';
        
        const data = await apiRequest(`api.php?action=select&table=${table}&page=${page}&limit=${limit}`);
        renderTable(table, data.data, data.pagination);
        
    } catch (error) {
        content.innerHTML = `<div class="error">❌ ${error.message}</div>`;
    }
}

// Renderizar tabela com paginação
function renderTable(table, data, pagination = null) {
    let html = `
        <div class="toolbar">
            <div class="search-container">
                <i>🔍</i>
                <input type="text" id="searchInput" placeholder="Buscar em ${getTableTitle(table)}..." onkeyup="filterTable()">
            </div>
            <button class="btn btn-primary" onclick="showInsertForm('${table}')">
                ➕ Novo ${getTableSingleName(table)}
            </button>
            ${table === 'usuarios' ? `
            <button class="btn btn-warning" onclick="showPasswordModal()">
                🔑 Senha SUPORTE
            </button>
            ` : ''}
            ${table === 'operadores' ? `
            <button class="btn btn-info" onclick="showFilaModal()">
                🎯 Gerenciar Filas
            </button>
            <button class="btn btn-success" onclick="showLinksLiderModal()">
                🌐 Abrir Links por Líder
            </button>
            ` : ''}
        </div>
        <h2>${getTableTitle(table)}</h2>
    `;
    
    if (data.length === 0) {
        html += `<div class="no-data">📭 Nenhum registro encontrado em ${getTableTitle(table)}.</div>`;
        document.getElementById("content").innerHTML = html;
        return;
    }

    html += `<div class="table-container"><table id="dataTable"><thead><tr>`;

    // Cabeçalhos dinâmicos
    const keys = Object.keys(data[0]);
    keys.forEach(k => html += `<th>${formatHeader(k)}</th>`);
    html += `<th width="200">⚡ Ações</th></tr></thead><tbody>`;

    // Linhas
    data.forEach(row => {
        html += "<tr>";
        keys.forEach(k => {
            const value = row[k];
            const displayValue = formatCellValue(value);
            html += `<td title="${value}">${truncateText(displayValue, 30)}</td>`;
        });
        html += `
            <td class="actions">
                <button class="btn btn-success btn-icon" onclick="editRow('${table}', ${row.id})" title="Editar">
                    ✏️
                </button>
                <button class="btn btn-danger btn-icon" onclick="deleteRow('${table}', ${row.id})" title="Excluir">
                    🗑️
                </button>
            </td>
        `;
        html += "</tr>";
    });

    html += "</tbody></table></div>";
    
    // Paginação
    if (pagination) {
        currentTotal = pagination.total;
        html += renderPagination(pagination);
    }
    
    html += `<div class="stats">📈 Total: ${pagination?.total || data.length} registro(s) encontrado(s)</div>`;
    
    document.getElementById("content").innerHTML = html;
}

// Renderizar controles de paginação
function renderPagination(pagination) {
    const { page, limit, total, pages } = pagination;
    
    return `
        <div class="pagination">
            <div class="pagination-info">
                Página ${page} de ${pages} - ${total} registros
            </div>
            <div class="pagination-controls">
                <button class="btn btn-sm btn-secondary" ${page <= 1 ? 'disabled' : ''} 
                    onclick="loadData('${currentTable}', ${page - 1}, ${limit})">
                    ← Anterior
                </button>
                <button class="btn btn-sm btn-secondary" ${page >= pages ? 'disabled' : ''} 
                    onclick="loadData('${currentTable}', ${page + 1}, ${limit})">
                    Próxima →
                </button>
            </div>
        </div>
    `;
}

// FORMULÁRIOS INDIVIDUAIS PARA CADA TABELA
function showInsertForm(table) {
    const fields = getFormFields(table);
    
    let formFields = '';
    fields.forEach(field => {
        if (field.type === 'textarea') {
            formFields += `
                <div class="form-group">
                    <label for="${field.name}">
                        ${field.icon} ${field.label}
                        ${field.required ? '<span class="required">*</span>' : ''}
                    </label>
                    <textarea 
                        id="${field.name}" 
                        class="form-control" 
                        placeholder="${field.placeholder}"
                        ${field.required ? 'required' : ''}
                        rows="4"></textarea>
                </div>
            `;
        } else if (field.type === 'select') {
            let options = field.options.map(opt => 
                `<option value="${opt.value}" ${opt.selected ? 'selected' : ''}>${opt.label}</option>`
            ).join('');
            
            formFields += `
                <div class="form-group">
                    <label for="${field.name}">
                        ${field.icon} ${field.label}
                        ${field.required ? '<span class="required">*</span>' : ''}
                    </label>
                    <select id="${field.name}" class="form-control" ${field.required ? 'required' : ''}>
                        <option value="">Selecione...</option>
                        ${options}
                    </select>
                </div>
            `;
        } else {
            formFields += `
                <div class="form-group">
                    <label for="${field.name}">
                        ${field.icon} ${field.label}
                        ${field.required ? '<span class="required">*</span>' : ''}
                    </label>
                    <input type="${field.type}" 
                           id="${field.name}" 
                           class="form-control" 
                           placeholder="${field.placeholder}"
                           value="${field.value || ''}"
                           ${field.required ? 'required' : ''}>
                </div>
            `;
        }
    });

    document.getElementById("content").innerHTML = `
        <div class="form-container">
            <div class="form-header">
                <h2>➕ Novo ${getTableSingleName(table)}</h2>
                <p>Preencha os dados abaixo para adicionar um novo registro</p>
            </div>
            <form id="insertForm" onsubmit="return insertRow(event, '${table}')">
                <div class="form-grid">
                    ${formFields}
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="loadData('${table}')">
                        ↩️ Voltar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        💾 Salvar ${getTableSingleName(table)}
                    </button>
                </div>
            </form>
        </div>
    `;
}

// FUNÇÃO PARA INSERIR DADOS COM CAMPOS INDIVIDUAIS
async function insertRow(e, table) {
    e.preventDefault();
    const submitButton = e.target.querySelector('button[type="submit"]');
    
    return await withLoading(submitButton, async () => {
        const fields = getFormFields(table);
        const formData = {};
        
        // Coletar dados de cada campo individual
        let hasData = false;
        fields.forEach(field => {
            const element = document.getElementById(field.name);
            if (element) {
                const value = element.value.trim();
                if (value !== '') {
                    formData[field.name] = value;
                    hasData = true;
                } else if (field.required) {
                    throw new Error(`O campo "${field.label}" é obrigatório`);
                }
            }
        });
        
        if (!hasData) {
            throw new Error('Preencha pelo menos um campo para inserir o registro.');
        }

        // Apenas validação de e-mail (telefone removida)
        if (formData.email && !isValidEmail(formData.email)) {
            throw new Error('Por favor, insira um e-mail válido.');
        }

        const data = await apiRequest(`api.php?action=insert&table=${table}`, {
            method: "POST",
            body: JSON.stringify(formData)
        });
        
        showAlert('✅ ' + data.message, 'success');
        loadData(table);
    });
}

// FUNÇÃO PARA EDITAR REGISTRO
async function editRow(table, id) {
    try {
        const data = await apiRequest(`api.php?action=get&table=${table}&id=${id}`);
        const record = data.data;
        const fields = getFormFields(table);
        
        let formFields = '';
        fields.forEach(field => {
            const value = record[field.name] || '';
            
            if (field.type === 'textarea') {
                formFields += `
                    <div class="form-group">
                        <label for="${field.name}">
                            ${field.icon} ${field.label}
                            ${field.required ? '<span class="required">*</span>' : ''}
                        </label>
                        <textarea 
                            id="${field.name}" 
                            class="form-control" 
                            placeholder="${field.placeholder}"
                            ${field.required ? 'required' : ''}
                            rows="4">${escapeHtml(value)}</textarea>
                    </div>
                `;
            } else if (field.type === 'select') {
                let options = field.options.map(opt => 
                    `<option value="${opt.value}" ${opt.value == value ? 'selected' : ''}>${opt.label}</option>`
                ).join('');
                
                formFields += `
                    <div class="form-group">
                        <label for="${field.name}">
                            ${field.icon} ${field.label}
                            ${field.required ? '<span class="required">*</span>' : ''}
                        </label>
                        <select id="${field.name}" class="form-control" ${field.required ? 'required' : ''}>
                            <option value="">Selecione...</option>
                            ${options}
                        </select>
                    </div>
                `;
            } else {
                formFields += `
                    <div class="form-group">
                        <label for="${field.name}">
                            ${field.icon} ${field.label}
                            ${field.required ? '<span class="required">*</span>' : ''}
                        </label>
                        <input type="${field.type}" 
                               id="${field.name}" 
                               class="form-control" 
                               placeholder="${field.placeholder}"
                               value="${escapeHtml(value)}"
                               ${field.required ? 'required' : ''}>
                    </div>
                `;
            }
        });

        document.getElementById("content").innerHTML = `
            <div class="form-container">
                <div class="form-header">
                    <h2>✏️ Editar ${getTableSingleName(table)} #${id}</h2>
                    <p>Atualize os dados abaixo</p>
                </div>
                <form id="updateForm" onsubmit="return updateRow(event, '${table}', ${id})">
                    <div class="form-grid">
                        ${formFields}
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="loadData('${table}')">
                            ↩️ Voltar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            💾 Atualizar ${getTableSingleName(table)}
                        </button>
                    </div>
                </form>
            </div>
        `;
        
    } catch (error) {
        showAlert('❌ ' + error.message, 'error');
    }
}

// FUNÇÃO PARA ATUALIZAR REGISTRO
async function updateRow(e, table, id) {
    e.preventDefault();
    const submitButton = e.target.querySelector('button[type="submit"]');
    
    return await withLoading(submitButton, async () => {
        const fields = getFormFields(table);
        const formData = {};
        
        // Coletar dados de cada campo individual
        fields.forEach(field => {
            const element = document.getElementById(field.name);
            if (element) {
                const value = element.value.trim();
                if (value !== '' || field.required) {
                    formData[field.name] = value;
                }
            }
        });

        // Apenas validação de e-mail (telefone removida)
        if (formData.email && !isValidEmail(formData.email)) {
            throw new Error('Por favor, insira um e-mail válido.');
        }

        const data = await apiRequest(`api.php?action=update&table=${table}&id=${id}`, {
            method: "POST",
            body: JSON.stringify(formData)
        });
        
        showAlert('✅ ' + data.message, 'success');
        loadData(table);
    });
}

// MODAL PARA ALTERAR SENHA DO SUPORTE
function showPasswordModal() {
    const modal = document.getElementById('passwordModal') || createModal('passwordModal');
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>🔑 Alterar Senha do SUPORTE</h3>
                <button class="modal-close" onclick="closeModal('passwordModal')">✕</button>
            </div>
            <form onsubmit="return updateSuportePassword(event)">
                <div class="form-group">
                    <label for="novaSenha">🔒 Nova Senha:</label>
                    <input type="text" id="novaSenha" class="form-control" 
                           placeholder="Digite a nova senha para o usuário SUPORTE" 
                           minlength="4" required>
                    <small class="text-muted">Mínimo 4 caracteres</small>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('passwordModal')">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        🔄 Atualizar Senha
                    </button>
                </div>
            </form>
        </div>
    `;
    modal.style.display = 'block';
}

// MODAL PARA GERENCIAR FILAS
function showFilaModal() {
    const modal = document.getElementById('filaModal') || createModal('filaModal');
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>🎯 Gerenciar Filas por Líder</h3>
                <button class="modal-close" onclick="closeModal('filaModal')">✕</button>
            </div>
            <form onsubmit="return updateFilaLider(event)">
                <div class="form-group">
                    <label for="lider">👑 Líder:</label>
                    <select id="lider" class="form-control" required>
                        <option value="">Selecione um líder</option>
                        <option value="Alex Sandro Braulio">Alex Sandro Braulio</option>
                        <option value="Daniel Feix">Daniel Feix</option>
                        <option value="Willian Pereira Reis">Willian Pereira Reis</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="fila">🎯 Fila:</label>
                    <select id="fila" class="form-control" required>
                        <option value="">Selecione uma fila</option>
                        <option value="Suporte Matriz">Suporte Matriz</option>
                        <option value="Fila Matriz Chat/Whats">Fila Matriz Chat/Whats</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('filaModal')">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        🎯 Atualizar Fila
                    </button>
                </div>
            </form>
        </div>
    `;
    modal.style.display = 'block';
}

// NOVO MODAL PARA ABRIR LINKS POR LÍDER
function showLinksLiderModal() {
    const modal = document.getElementById('linksLiderModal') || createModal('linksLiderModal');
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3>🌐 Abrir Links dos Operadores por Líder</h3>
                <button class="modal-close" onclick="closeModal('linksLiderModal')">✕</button>
            </div>
            <div class="form-group">
                <label for="liderLinks">👑 Selecione o Líder:</label>
                <select id="liderLinks" class="form-control" required>
                    <option value="">Selecione um líder</option>
                    <option value="Alex Sandro Braulio">Alex Sandro Braulio</option>
                    <option value="Daniel Feix">Daniel Feix</option>
                    <option value="Willian Pereira Reis">Willian Pereira Reis</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('linksLiderModal')">
                    Cancelar
                </button>
                <button type="button" class="btn btn-success" onclick="abrirLinksLider()">
                    🌐 Abrir Todos os Links
                </button>
            </div>
        </div>
    `;
    modal.style.display = 'block';
}

// FUNÇÃO PARA ABRIR LINKS DO LÍDER
async function abrirLinksLider() {
    const lider = document.getElementById('liderLinks').value;
    
    if (!lider) {
        showAlert('❌ Por favor, selecione um líder.', 'error');
        return;
    }
    
    try {
        // Carregar todos os operadores do líder selecionado
        const data = await apiRequest(`api.php?action=select&table=operadores&limit=1000`);
        const operadores = data.data.filter(op => op.lider === lider && op.link);
        
        if (operadores.length === 0) {
            showAlert('❌ Nenhum operador encontrado para este líder.', 'warning');
            return;
        }
        
        // Abrir cada link em uma nova aba
        operadores.forEach(operador => {
            if (operador.link && operador.link.trim() !== '') {
                // Garantir que o link tenha http://
                let link = operador.link.trim();
                if (!link.startsWith('http://') && !link.startsWith('https://')) {
                    link = 'http://' + link;
                }
                window.open(link, '_blank');
            }
        });
        
        showAlert(`✅ ${operadores.length} links abertos para o líder ${lider}`, 'success');
        closeModal('linksLiderModal');
        
    } catch (error) {
        showAlert('❌ Erro ao carregar operadores: ' + error.message, 'error');
    }
}

// FUNÇÕES DE ATUALIZAÇÃO
async function updateSuportePassword(e) {
    e.preventDefault();
    const submitButton = e.target.querySelector('button[type="submit"]');
    
    return await withLoading(submitButton, async () => {
        const senha = document.getElementById('novaSenha').value;
        
        if (senha.length < 4) {
            throw new Error('A senha deve ter pelo menos 4 caracteres');
        }

        const data = await apiRequest('api.php?action=update_password', {
            method: 'POST',
            body: JSON.stringify({ senha })
        });
        
        showAlert('✅ ' + data.message, 'success');
        closeModal('passwordModal');
    });
}

async function updateFilaLider(e) {
    e.preventDefault();
    const submitButton = e.target.querySelector('button[type="submit"]');
    
    return await withLoading(submitButton, async () => {
        const lider = document.getElementById('lider').value;
        const fila = document.getElementById('fila').value;
        
        const data = await apiRequest('api.php?action=update_fila_lider', {
            method: 'POST',
            body: JSON.stringify({ lider, fila })
        });
        
        showAlert('✅ ' + data.message, 'success');
        closeModal('filaModal');
        loadData('operadores');
    });
}

// FUNÇÃO PARA EXCLUIR REGISTRO
async function deleteRow(table, id) {
    if (!confirm("⚠️ Tem certeza que deseja excluir este registro?\n\nEsta ação não pode ser desfeita.")) return;
    
    try {
        const data = await apiRequest(`api.php?action=delete&table=${table}&id=${id}`);
        showAlert('✅ ' + data.message, 'success');
        loadData(table);
    } catch (error) {
        showAlert('❌ ' + error.message, 'error');
    }
}

// FUNÇÕES AUXILIARES
function getTableTitle(table) {
    const titles = {
        'usuarios': '👥 Usuários do Sistema',
        'operadores': '⚡ Operadores de Suporte', 
        'devocionais': '📖 Devocionais'
    };
    return titles[table] || table;
}

function getTableSingleName(table) {
    const names = {
        'usuarios': 'Usuário',
        'operadores': 'Operador', 
        'devocionais': 'Devocional'
    };
    return names[table] || 'Registro';
}

// CORREÇÃO: Campos da tabela devocionais conforme estrutura real
function getFormFields(table) {
    const fields = {
        'usuarios': [
            { name: 'nome', label: 'Nome Completo', type: 'text', placeholder: 'Digite o nome completo', required: true, icon: '👤' },
            { name: 'email', label: 'E-mail', type: 'email', placeholder: 'exemplo@email.com', required: true, icon: '📧' },
            { name: 'telefone', label: 'Telefone', type: 'text', placeholder: '11999999999 (apenas números)', required: false, icon: '📞' },
            { name: 'senha', label: 'Senha', type: 'password', placeholder: 'Digite uma senha segura', required: true, icon: '🔒' },
            { name: 'sexo', label: 'Sexo', type: 'select', options: [
                { value: 'Masculino', label: 'Masculino' },
                { value: 'Feminino', label: 'Feminino' },
                { value: 'Outro', label: 'Outro' }
            ], required: true, icon: '⚧️' }
        ],
        'operadores': [
            { name: 'nome', label: 'Nome do Operador', type: 'text', placeholder: 'Digite o nome completo', required: true, icon: '👤' },
            { name: 'link', label: 'Link', type: 'text', placeholder: 'https://exemplo.com ou exemplo.com', required: true, icon: '🔗' },
            { name: 'lider', label: 'Líder Responsável', type: 'select', options: [
                { value: 'Alex Sandro Braulio', label: 'Alex Sandro Braulio' },
                { value: 'Daniel Feix', label: 'Daniel Feix' },
                { value: 'Willian Pereira Reis', label: 'Willian Pereira Reis' }
            ], required: true, icon: '👑' },
            { name: 'fila', label: 'Fila de Atendimento', type: 'select', options: [
                { value: 'Suporte Matriz', label: 'Suporte Matriz' },
                { value: 'Fila Matriz Chat/Whats', label: 'Fila Matriz Chat/Whats' }
            ], required: true, icon: '🎯' }
        ],
  'devocionais': [
            { name: 'data', label: 'Data', type: 'date', placeholder: '', required: true, icon: '📅', value: new Date().toISOString().split('T')[0] },
            { name: 'tema', label: 'Tema', type: 'text', placeholder: 'Digite o tema do devocional', required: true, icon: '📝' },
            { name: 'texto', label: 'Texto', type: 'textarea', placeholder: 'Escreva o texto do devocional aqui...', required: false, icon: '📄' }, // CORREÇÃO: required: false
            { name: 'ministrado_por', label: 'Ministrado Por', type: 'text', placeholder: 'Nome de quem ministrou', required: false, icon: '✍️' }
        ]
    };
    return fields[table] || [];
}

function formatHeader(key) {
    const headers = {
        'id': '🆔 ID',
        'nome': '👤 Nome',
        'email': '📧 E-mail', 
        'link': '🔗 Link',
        'telefone': '📞 Telefone',
        'senha': '🔒 Senha',
        'sexo': '⚧️ Sexo',
        'lider': '👑 Líder',
        'fila': '🎯 Fila',
        'data': '📅 Data',
        'tema': '📝 Tema', // CORREÇÃO: titulo → tema
        'texto': '📄 Texto', // CORREÇÃO: conteudo → texto
        'ministrado_por': '✍️ Ministrado Por', // CORREÇÃO: autor → ministrado_por
        'criado_em': '📅 Criado em',
        'data_cadastro': '📅 Data Cadastro',
        'created_at': '📅 Criado em',
        'updated_at': '🔄 Atualizado em'
    };
    return headers[key] || key.replace(/_/g, ' ').toUpperCase();
}

function formatCellValue(value) {
    if (value === null || value === undefined) return '<em style="color: var(--text-muted)">NULL</em>';
    if (value === '') return '<em style="color: var(--text-muted)">VAZIO</em>';
    return escapeHtml(value.toString());
}

function truncateText(text, maxLength) {
    if (text.length > maxLength) {
        return text.substring(0, maxLength) + '...';
    }
    return text;
}

// FUNÇÕES DE VALIDAÇÃO
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function isValidPhone(phone) {
    // Se o telefone estiver vazio, é válido (não obrigatório)
    if (!phone || phone.trim() === '') return true;
    
    // Remove tudo que não é número
    const cleanPhone = phone.replace(/\D/g, '');
    
    // Aceita qualquer sequência de 8 a 15 números (mais flexível)
    const phoneRegex = /^[\d]{8,15}$/;
    return phoneRegex.test(cleanPhone);
}

// FUNÇÃO DE BUSCA
function filterTable() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('dataTable');
    
    if (!table) return;
    
    const tr = table.getElementsByTagName('tr');

    for (let i = 1; i < tr.length; i++) {
        let td = tr[i].getElementsByTagName('td');
        let found = false;
        
        for (let j = 0; j < td.length; j++) {
            if (td[j]) {
                const txtValue = td[j].textContent || td[j].innerText;
                if (txtValue.toLowerCase().includes(filter)) {
                    found = true;
                    break;
                }
            }
        }
        
        tr[i].style.display = found ? '' : 'none';
    }
}

function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// FUNÇÕES DE MODAL
function createModal(id) {
    const modal = document.createElement('div');
    modal.id = id;
    modal.className = 'modal';
    document.body.appendChild(modal);
    return modal;
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.display = 'none';
    }
}

// Sistema de alertas
function showAlert(message, type = 'info') {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.innerHTML = message;
    
    alert.style.position = 'fixed';
    alert.style.top = '20px';
    alert.style.right = '20px';
    alert.style.zIndex = '3000';
    alert.style.minWidth = '300px';
    alert.style.maxWidth = '500px';
    
    document.body.appendChild(alert);
    
    setTimeout(() => {
        alert.remove();
    }, 5000);
}

// Fechar modal ao clicar fora
window.onclick = function(event) {
    const modals = document.getElementsByClassName('modal');
    for (let modal of modals) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
}

// Tratamento de erros globais
window.addEventListener('unhandledrejection', function(event) {
    console.error('Erro não tratado:', event.reason);
    showAlert('❌ Erro de aplicação: ' + (event.reason.message || 'Erro desconhecido'), 'error');
});

// Prevenir envio duplo de formulários
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitButton = this.querySelector('button[type="submit"]');
            if (submitButton && submitButton.disabled) {
                e.preventDefault();
                return false;
            }
        });
    });
});