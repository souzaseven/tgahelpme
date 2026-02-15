// Utilitários
const $ = (selector) => document.querySelector(selector);
const $$ = (selector) => document.querySelectorAll(selector);

// ========== SISTEMA DE AUTO-REFRESH INTELIGENTE ==========
class AutoRefresh {
    constructor() {
        this.intervals = {
            dashboard: 30000,
            realtime: 5000,
            callcenter: 20000,
            agentes: 15000,
            chamadas: 10000,
            filas: 15000,
            cdr: 60000,
            default: 60000
        };
        
        this.currentPage = this.getCurrentPage();
        this.timer = null;
        this.countdown = 0;
        this.countdownInterval = null;
        this.init();
    }

    getCurrentPage() {
        const params = new URLSearchParams(window.location.search);
        return params.get('page') || 'dashboard';
    }

    init() {
        this.createRefreshIndicator();
        this.startAutoRefresh();
    }

    createRefreshIndicator() {
        const headerActions = $('.header-actions');
        if (!headerActions) return;

        const indicator = document.createElement('div');
        indicator.className = 'refresh-indicator';
        indicator.innerHTML = `
            <span class="refresh-dot"></span>
            <span id="refresh-countdown">
                <span id="countdown-time">--</span>s
            </span>
        `;
        
        const updateBtn = headerActions.querySelector('.btn');
        if (updateBtn) {
            headerActions.insertBefore(indicator, updateBtn);
        } else {
            headerActions.appendChild(indicator);
        }
    }

    getRefreshInterval() {
        return this.intervals[this.currentPage] || this.intervals.default;
    }

    startAutoRefresh() {
        const interval = this.getRefreshInterval();
        this.countdown = Math.floor(interval / 1000);
        
        this.countdownInterval = setInterval(() => {
            this.countdown--;
            const countdownEl = $('#countdown-time');
            if (countdownEl) {
                countdownEl.textContent = this.countdown;
                
                if (this.countdown <= 5) {
                    countdownEl.style.color = 'var(--warning)';
                } else {
                    countdownEl.style.color = 'var(--text-secondary)';
                }
            }
            
            if (this.countdown <= 0) {
                clearInterval(this.countdownInterval);
            }
        }, 1000);

        this.timer = setTimeout(() => {
            console.log(`🔄 Auto-refresh: Atualizando página ${this.currentPage}`);
            location.reload();
        }, interval);
    }

    stop() {
        if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
        }
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
            this.countdownInterval = null;
        }
    }
}

let autoRefresh;

// ========== GERENCIAMENTO DE NOTIFICAÇÕES ==========
const showNotification = (message, type = 'info') => {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;
    alert.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 300px; animation: slideIn 0.3s ease-out;';
    
    document.body.appendChild(alert);
    
    setTimeout(() => {
        alert.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => alert.remove(), 300);
    }, 5000);
};

// ========== GERENCIAMENTO DE MODAIS ==========
const openModal = (modalId) => {
    const modal = $(`#${modalId}`);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
};

const closeModal = (modalId) => {
    const modal = $(`#${modalId}`);
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
};

document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('show');
        document.body.style.overflow = '';
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const openModals = $$('.modal.show');
        openModals.forEach(modal => {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        });
    }
});

// ========== REQUISIÇÕES AJAX (MELHORADO) ==========
const api = {
    async request(url, options = {}) {
        try {
            console.log('📡 API Request:', url, options.method || 'GET');
            
            const response = await fetch(url, {
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                },
                ...options
            });
            
            // Tenta fazer parse do JSON
            let data;
            const contentType = response.headers.get('content-type');
            
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                const text = await response.text();
                console.error('❌ Resposta não é JSON:', text.substring(0, 200));
                throw new Error('Resposta inválida da API (não é JSON)');
            }
            
            console.log('📥 API Response:', data);
            
            // Se a resposta tem success false, lança erro com a mensagem
            if (data.success === false) {
                const errorMsg = data.meta?.message || data.error || 'Erro desconhecido';
                console.error('❌ API Error:', errorMsg);
                throw new Error(errorMsg);
            }
            
            return data;
            
        } catch (error) {
            console.error('❌ Request Error:', error);
            
            // Não mostra notificação duplicada, deixa a função chamadora fazer isso
            throw error;
        }
    },
    
    get(url) {
        return this.request(url, { method: 'GET' });
    },
    
    post(url, data) {
        return this.request(url, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },
    
    put(url, data) {
        return this.request(url, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },
    
    delete(url) {
        return this.request(url, {
            method: 'DELETE'
        });
    }
};

// ========== UTILITÁRIOS ==========

const confirmDelete = (message, callback) => {
    if (confirm(message || 'Tem certeza que deseja excluir?')) {
        callback();
    }
};

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const formatDuration = (seconds) => {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    
    if (hours > 0) {
        return `${hours}h ${minutes}m ${secs}s`;
    } else if (minutes > 0) {
        return `${minutes}m ${secs}s`;
    }
    return `${secs}s`;
};

const validateForm = (formId) => {
    const form = $(`#${formId}`);
    if (!form) return false;
    
    const inputs = form.querySelectorAll('[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.style.borderColor = 'var(--danger)';
            isValid = false;
        } else {
            input.style.borderColor = 'var(--border)';
        }
    });
    
    if (!isValid) {
        showNotification('Preencha todos os campos obrigatórios', 'warning');
    }
    
    return isValid;
};

const clearForm = (formId) => {
    const form = $(`#${formId}`);
    if (form) {
        form.reset();
        form.querySelectorAll('input, select, textarea').forEach(el => {
            el.style.borderColor = 'var(--border)';
        });
    }
};

const exportToCSV = (data, filename) => {
    if (!data || !data.length) {
        showNotification('Nenhum dado para exportar', 'warning');
        return;
    }
    
    const headers = Object.keys(data[0]);
    const csv = [
        headers.join(','),
        ...data.map(row => headers.map(header => {
            const value = row[header] || '';
            return `"${String(value).replace(/"/g, '""')}"`;
        }).join(','))
    ].join('\n');
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename || `export_${Date.now()}.csv`;
    link.click();
    
    showNotification('Arquivo CSV exportado com sucesso!', 'success');
};

const filterTable = (tableId, searchValue) => {
    const table = $(`#${tableId}`);
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    const search = searchValue.toLowerCase();
    let visibleCount = 0;
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const isVisible = text.includes(search);
        row.style.display = isVisible ? '' : 'none';
        if (isVisible) visibleCount++;
    });
    
    const tbody = table.querySelector('tbody');
    let noResultsRow = tbody.querySelector('.no-results');
    
    if (visibleCount === 0 && search) {
        if (!noResultsRow) {
            noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-results';
            noResultsRow.innerHTML = `<td colspan="100" class="text-center" style="padding: 2rem; color: var(--text-muted);">Nenhum resultado encontrado para "${search}"</td>`;
            tbody.appendChild(noResultsRow);
        }
    } else if (noResultsRow) {
        noResultsRow.remove();
    }
};

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('Copiado para a área de transferência!', 'success');
    }).catch(() => {
        showNotification('Erro ao copiar', 'danger');
    });
};

// ========== INICIALIZAÇÃO ==========
document.addEventListener('DOMContentLoaded', () => {
    console.log('🌙 Painel Evolux Admin carregado - Modo Escuro v2.0');
    
    autoRefresh = new AutoRefresh();
    
    const currentPage = autoRefresh.getCurrentPage();
    const interval = autoRefresh.getRefreshInterval();
    console.log(`📄 Página atual: ${currentPage}`);
    console.log(`⏱️ Intervalo de refresh: ${interval/1000}s`);
    
    $$('.nav-link').forEach(link => {
        link.addEventListener('click', (e) => {
            if (autoRefresh) {
                autoRefresh.stop();
                console.log('🛑 Auto-refresh pausado para navegação');
            }
            
            $$('.nav-link').forEach(l => l.classList.remove('active'));
            e.target.closest('.nav-link').classList.add('active');
        });
    });
    
    if (!$('#custom-animations')) {
        const style = document.createElement('style');
        style.id = 'custom-animations';
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    console.log('✅ Todos os módulos carregados com sucesso!');
});

// ========== HELPERS GLOBAIS ==========

const debounce = (func, wait) => {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
};

const formatNumber = (value) => {
    return new Intl.NumberFormat('pt-BR').format(value);
};

console.log('🚀 App.js carregado com sucesso!');