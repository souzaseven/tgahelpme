class CookieManager {
    constructor() {
        this.cookieRefreshInterval = 30 * 60 * 1000; // 30 minutos
        this.dataRefreshInterval = 30 * 1000; // 30 segundos
        this.isRefreshing = false;
    }
    
    async refreshCookies() {
        if (this.isRefreshing) return false;
        
        this.isRefreshing = true;
        try {
            const response = await fetch('refresh_cookies.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                console.log('Cookies atualizados:', result.timestamp);
                this.updateStatus('Cookies atualizados', 'success');
                return true;
            } else {
                console.error('Erro ao atualizar cookies:', result.error);
                this.updateStatus('Erro ao atualizar cookies', 'error');
                return false;
            }
        } catch (error) {
            console.error('Erro na requisição:', error);
            this.updateStatus('Erro de conexão', 'error');
            return false;
        } finally {
            this.isRefreshing = false;
        }
    }
    
    startAutoRefresh() {
        // Atualizar cookies a cada 30 minutos
        setInterval(() => {
            this.refreshCookies();
        }, this.cookieRefreshInterval);
        
        // Atualizar dados a cada 30 segundos
        setInterval(() => {
            this.loadPausedAgents();
        }, this.dataRefreshInterval);
        
        // Iniciar agora
        this.loadPausedAgents();
        this.updateStatus('Sistema iniciado', 'success');
    }
    
    async loadPausedAgents() {
    const loading = document.getElementById('loading');
    const error = document.getElementById('error');
    const container = document.getElementById('agents-container');
    
    if (loading) loading.style.display = 'block';
    if (error) error.style.display = 'none';
    if (container) container.style.display = 'none';
    
    try {
        // USAR O NOVO ARQUIVO COM COOKIES ARMAZENADOS
        const response = await fetch('scraping_with_cookies.php');
        const data = await response.json();
        
        if (data.error) {
            if (data.error.includes('cookie') || data.error.includes('expirado') || data.error.includes('login')) {
                this.showError('Sessão expirada. <a href="update_cookies.html" style="color: white; text-decoration: underline;">Clique aqui para atualizar os cookies</a>');
            } else {
                throw new Error(data.error);
            }
        } else {
            this.displayAgents(data);
            this.updateStatus('Dados atualizados', 'success');
        }
        
    } catch (err) {
        console.error('Erro ao carregar agentes:', err);
        this.showError('Erro: ' + err.message);
        this.updateStatus('Erro ao carregar', 'error');
    } finally {
        if (loading) loading.style.display = 'none';
    }
}
    
    displayAgents(data) {
        const totalCount = document.getElementById('total-count');
        const agentsList = document.getElementById('agents-list');
        const container = document.getElementById('agents-container');
        const lastUpdate = document.getElementById('last-update');
        
        if (totalCount) totalCount.textContent = data.total_paused || 0;
        if (lastUpdate) lastUpdate.textContent = new Date().toLocaleString('pt-BR');
        
        if (agentsList) {
            agentsList.innerHTML = '';
            
            if (!data.agents || data.agents.length === 0) {
                agentsList.innerHTML = '<tr><td colspan="3" style="text-align: center;">Nenhum operador pausado</td></tr>';
            } else {
                data.agents.forEach(agent => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${this.escapeHtml(agent.name)}</td>
                        <td>${this.escapeHtml(agent.reason)}</td>
                        <td>${this.escapeHtml(agent.duration)}</td>
                    `;
                    agentsList.appendChild(row);
                });
            }
        }
        
        if (container) container.style.display = 'block';
    }
    
    showError(message) {
        const error = document.getElementById('error');
        if (error) {
            error.textContent = message;
            error.style.display = 'block';
        }
    }
    
    updateStatus(message, type = 'info') {
        const status = document.getElementById('status');
        if (status) {
            status.textContent = message;
            status.style.color = type === 'error' ? '#dc3545' : 
                               type === 'success' ? '#28a745' : 
                               type === 'warning' ? '#ffc107' : '#6c757d';
        }
        console.log(`Status: ${message}`);
    }
    
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Não inicializar aqui - será inicializado no HTML