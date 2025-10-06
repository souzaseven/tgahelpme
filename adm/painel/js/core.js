// Variáveis globais
let currentTable = '';
let currentPage = 1;
let currentLimit = 50;
let currentTotal = 0;

// ❌ REMOVER COMPLETAMENTE A VERIFICAÇÃO DO CSRF_TOKEN
// O token será passado via PHP quando necessário, não via JavaScript
// Função auxiliar para mostrar loading em botões
async function withLoading(button, callback) {
    const originalText = button.innerHTML;
    const originalDisabled = button.disabled;
    
    // Aplica estado de loading
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Carregando...';
    button.disabled = true;
    
    try {
        // Executa a função callback
        const result = callback();
        
        // Se for uma Promise, trata adequadamente
        if (result instanceof Promise) {
            return await result
                .then(data => {
                    resetButton(button, originalText, originalDisabled);
                    return data;
                })
                .catch(error => {
                    resetButton(button, originalText, originalDisabled);
                    throw error;
                });
        } else {
            resetButton(button, originalText, originalDisabled);
            return result;
        }
    } catch (error) {
        resetButton(button, originalText, originalDisabled);
        throw error;
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
// Função auxiliar para resetar o botão
function resetButton(button, originalText, originalDisabled) {
    button.innerHTML = originalText;
    button.disabled = originalDisabled;
}

// Função para fazer requisições com tratamento de erro// Função para fazer requisições com tratamento de erro
async function apiRequest(url, options = {}) {
    // ✅ VERIFICAR E OBTER O CSRF TOKEN
    let csrfToken = '';
    
    // Tentar obter o token de várias formas
    if (typeof CSRF_TOKEN !== 'undefined' && CSRF_TOKEN) {
        csrfToken = CSRF_TOKEN;
    } else if (window.CSRF_TOKEN) {
        csrfToken = window.CSRF_TOKEN;
    } else {
        console.error('❌ CSRF Token não encontrado!');
    }
    
    console.log('🔐 Enviando CSRF Token:', csrfToken ? 'Token presente' : 'Token ausente');
    
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
            // ✅ ENVIAR CSRF TOKEN NO HEADER
            'X-CSRF-Token': csrfToken
        }
    };
    
    const finalOptions = { ...defaultOptions, ...options };
    
    try {
        const response = await fetch(url, finalOptions);
        
        // Verificar se a resposta é JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Resposta do servidor não é JSON');
        }
        
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
        
        // Chamar a função específica de cada tabela
        switch(table) {
            case 'usuarios':
                usuarios.renderTable(data.data, data.pagination);
                break;
            case 'operadores':
                operadores.renderTable(data.data, data.pagination);
                break;
            case 'devocionais':
                devocionais.renderTable(data.data, data.pagination);
                break;

            default:
                renderGenericTable(table, data.data, data.pagination);

        }
        
    } catch (error) {
        content.innerHTML = `<div class="error">❌ ${error.message}</div>`;
    }
}

// Renderizar tabela genérica (fallback)
function renderGenericTable(table, data, pagination = null) {
    let html = `
        <div class="toolbar">
            <div class="search-container">
                <i>🔍</i>
                <input type="text" id="searchInput" placeholder="Buscar em ${getTableTitle(table)}..." onkeyup="filterTable()">
            </div>
            <button class="btn btn-primary" onclick="showInsertForm('${table}')">
                ➕ Novo ${getTableSingleName(table)}
            </button>
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
        'tema': '📝 Tema',
        'texto': '📄 Texto',
        'ministrado_por': '✍️ Ministrado Por',
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

// Carregar dados iniciais
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById("content").innerHTML = `
        <div class="text-center" style="padding: 4rem 2rem;">
            <h1 style="font-size: 2.5rem; margin-bottom: 1rem;">🚀 Painel Administrativo</h1>
            <p class="text-muted" style="font-size: 1.2rem;">Selecione uma tabela no menu acima para começar</p>
        </div>
    `;
});

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