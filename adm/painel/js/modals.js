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

// MODAL PARA ABRIR LINKS POR LÍDER
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

// FUNÇÕES DE MODAL CORRIGIDAS
function createModal(id) {
    let modal = document.getElementById(id);
    if (!modal) {
        modal = document.createElement('div');
        modal.id = id;
        modal.className = 'modal';
        document.body.appendChild(modal);
        
        // Adicionar evento para fechar com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display === 'block') {
                closeModal(id);
            }
        });
    }
    return modal;
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        // Adicionar animação de saída
        modal.style.opacity = '0';
        modal.style.transform = 'translateY(-20px)';
        
        setTimeout(() => {
            modal.style.display = 'none';
            // Resetar estilos para próxima abertura
            modal.style.opacity = '';
            modal.style.transform = '';
            // Limpar conteúdo para evitar duplicação
            modal.innerHTML = '';
        }, 300);
    }
}

// Fechar modal ao clicar fora - CORRIGIDO
window.onclick = function(event) {
    const modals = document.getElementsByClassName('modal');
    for (let modal of modals) {
        if (event.target === modal) {
            closeModal(modal.id);
        }
    }
}

// MODAL PARA PAINEL DO OPERADOR
function showPainelOperadorModal() {
    const modal = document.getElementById('painelOperadorModal') || createModal('painelOperadorModal');
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3>📞 Painel do Operador - Evolux</h3>
                <button class="modal-close" onclick="closeModal('painelOperadorModal')">✕</button>
            </div>
            <div class="modal-body">
                <div class="painel-links-grid">
                    <button class="painel-link-btn primary" onclick="window.open('https://tgameajuda.com/telefonia-evolux/painel-operador/index.php', '_blank')">
                        <span class="btn-icon">🏠</span>
                        Painel do Operador
                    </button>
                    <button class="painel-link-btn info" onclick="window.open('https://tgasistemas.evolux.io/panel/queue?id=9&slug=suporte_matriz&type=queue#details', '_blank')">
                        <span class="btn-icon">📊</span>
                        Painel de Ligação
                    </button>
                    <button class="painel-link-btn success" onclick="window.open('https://tgasistemas.evolux.io/callcenter/agent', '_blank')">
                        <span class="btn-icon">👥</span>
                        Painel de Operadores
                    </button>
                    <button class="painel-link-btn warning" onclick="window.open('https://tgasistemas.evolux.io/callcenter/supervisor/agents_monitor', '_blank')">
                        <span class="btn-icon">👁️</span>
                        Monitor de Operadores
                    </button>
                    <button class="painel-link-btn secondary" onclick="window.open('https://tgasistemas.evolux.io/callcenter/agent_group?page=1&query=', '_blank')">
                        <span class="btn-icon">👨‍👩‍👧‍👦</span>
                        Grupo de Operadores
                    </button>
                    <button class="painel-link-btn danger" onclick="window.open('https://tgasistemas.evolux.io/callcenter/report/listen_calls', '_blank')">
                        <span class="btn-icon">📞</span>
                        Histórico de Chamadas
                    </button>
                    <button class="painel-link-btn dark" onclick="window.open('https://tgasistemas.evolux.io/callcenter/report/logon', '_blank')">
                        <span class="btn-icon">🔐</span>
                        Histórico de Login
                    </button>
                    <button class="painel-link-btn purple" onclick="window.open('https://tgasistemas.evolux.io/callcenter/report/pause', '_blank')">
                        <span class="btn-icon">⏸️</span>
                        Histórico de Pausas
                    </button>
                    <button class="painel-link-btn teal" onclick="window.open('https://tgasistemas.evolux.io/callcenter/report/agents_performance', '_blank')">
                        <span class="btn-icon">📈</span>
                        Produtividade de Operadores
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('painelOperadorModal')">
                    Fechar
                </button>
            </div>
        </div>
    `;
    modal.style.display = 'block';
}

// PREVENIR PROPAGAÇÃO DE CLIQUE DENTRO DO MODAL
document.addEventListener('click', function(e) {
    if (e.target.closest('.modal-content')) {
        e.stopPropagation();
    }
});