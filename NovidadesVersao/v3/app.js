// ===== APP PRINCIPAL =====
class TGAApp {
    constructor() {
        this.currentTheme = localStorage.getItem('theme') || 'light';
        this.layoutMode = localStorage.getItem('layoutMode') || 'normal';
        this.downloadStats = this.getDownloadStats();
        this.recentVersions = this.getRecentVersions();
        this.init();
    }

    init() {
        this.applyTheme();
        this.applyLayoutMode();
        this.initScrollToTop();
        this.initSmoothScrolling();
        this.initVersionCards();
        this.initNotifications();
        this.initFilters();
        this.initLayoutControls();
        this.initPrintButton();
        this.initKeyboardShortcuts();
        this.initSearch();
        this.markRecentVersions();
    }

    // ===== SISTEMA DE DOWNLOADS =====
    getDownloadStats() {
        return JSON.parse(localStorage.getItem('tga-download-stats') || '{}');
    }

    saveDownloadStats() {
        localStorage.setItem('tga-download-stats', JSON.stringify(this.downloadStats));
    }

    getDownloadCount(versionId) {
        return this.downloadStats[versionId] || 0;
    }

    incrementDownloadCount(versionId) {
        const currentCount = this.getDownloadCount(versionId);
        this.downloadStats[versionId] = currentCount + 1;
        this.saveDownloadStats();
        
        // Atualizar a exibição imediatamente
        this.updateDownloadCounter(versionId, this.downloadStats[versionId]);
        
        return this.downloadStats[versionId];
    }

    updateDownloadCounter(versionId, count) {
        const counter = document.querySelector(`[data-version-id="${versionId}"] .download-count`);
        if (counter) {
            counter.textContent = count;
            counter.parentElement.classList.add('updated');
            setTimeout(() => {
                counter.parentElement.classList.remove('updated');
            }, 600);
        }
    }

    // ===== SISTEMA DE VERSÕES RECENTES =====
    getRecentVersions() {
        // Versões consideradas "recentes" (últimas 2 versões)
        const currentVersion = '25.09';
       // const previousVersion = '25.08';
        
        //return [currentVersion, previousVersion];
return [currentVersion];
    }

    markRecentVersions() {
        const cards = document.querySelectorAll('.version-card');
        
        cards.forEach(card => {
            const version = card.dataset.version;
            
            if (this.recentVersions.includes(version)) {
                // Adicionar badge NOVA se não existir
                if (!card.querySelector('.version-badge.new')) {
                    const badge = document.createElement('span');
                    badge.className = 'version-badge new';
                    badge.textContent = 'Nova';
                    card.querySelector('.card-header').appendChild(badge);
                }
                
                // Adicionar classe especial para versões novas
                card.classList.add('new-version');
            }
        });
    }

    // ===== GERENCIAMENTO DE TEMA =====
    applyTheme() {
        document.documentElement.setAttribute('data-theme', this.currentTheme);
        
        const themeBtn = document.querySelector('.dark-mode-btn i');
        if (themeBtn) {
            themeBtn.className = this.currentTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
    }

    toggleTheme() {
        this.currentTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        localStorage.setItem('theme', this.currentTheme);
        this.applyTheme();
        
        this.showNotification(
            `Modo ${this.currentTheme === 'dark' ? 'escuro' : 'claro'} ativado`,
            'success'
        );
    }

    // ===== CONTROLES DE LAYOUT =====
    applyLayoutMode() {
        document.body.classList.remove('dense-layout', 'super-compact');
        
        if (this.layoutMode === 'dense') {
            document.body.classList.add('dense-layout');
        } else if (this.layoutMode === 'super-compact') {
            document.body.classList.add('super-compact');
        }
    }

    setLayoutMode(mode) {
        this.layoutMode = mode;
        localStorage.setItem('layoutMode', mode);
        this.applyLayoutMode();
        
        const modeNames = {
            'normal': 'Normal',
            'dense': 'Compacto',
            'super-compact': 'Super Compacto'
        };
        
        this.showNotification(
            `Layout ${modeNames[mode]} ativado`,
            'info'
        );
    }

    initLayoutControls() {
        const floatingActions = document.querySelector('.floating-actions');
        if (floatingActions) {
            const layoutBtn = document.createElement('button');
            layoutBtn.className = 'action-btn layout-btn';
            layoutBtn.innerHTML = '<i class="fas fa-compress-alt"></i>';
            layoutBtn.title = 'Alternar layout (Ctrl+L)';
            layoutBtn.addEventListener('click', () => this.toggleLayoutMode());
            floatingActions.appendChild(layoutBtn);
        }
    }

    toggleLayoutMode() {
        const modes = ['normal', 'dense', 'super-compact'];
        const currentIndex = modes.indexOf(this.layoutMode);
        const nextIndex = (currentIndex + 1) % modes.length;
        this.setLayoutMode(modes[nextIndex]);
    }

    // ===== SCROLL PARA O TOPO =====
    initScrollToTop() {
        const scrollBtn = document.getElementById('scrollToTopBtn');
        if (!scrollBtn) return;
        
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                scrollBtn.style.display = 'flex';
            } else {
                scrollBtn.style.display = 'none';
            }
        });

        scrollBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    // ===== ROLAGEM SUAVE =====
    initSmoothScrolling() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    // ===== CARDS DE VERSÃO =====
    initVersionCards() {
        const cards = document.querySelectorAll('.version-card');
        
        cards.forEach(card => {
            // Adicionar contador de downloads
            this.addDownloadCounter(card);
            
            // Configurar tracking de downloads
            this.setupDownloadTracking(card);
            
            // Efeito de hover
            card.addEventListener('mouseenter', () => {
                if (this.layoutMode === 'normal') {
                    card.style.transform = 'translateY(-4px)';
                } else {
                    card.style.transform = 'translateY(-2px)';
                }
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)';
            });

            // Clique para expandir detalhes
            card.addEventListener('click', (e) => {
                if (!e.target.closest('a')) {
                    this.toggleCardDetails(card);
                }
            });
        });
    }

    addDownloadCounter(card) {
        const version = card.dataset.version;
        const versionId = this.getVersionId(card);
        const downloadCount = this.getDownloadCount(versionId);
        
        const downloadCounter = document.createElement('div');
        downloadCounter.className = 'download-counter';
        downloadCounter.innerHTML = `
            <i class="fas fa-download"></i>
            <span class="download-count">${downloadCount}</span>
            <span>  </span>
        `;
        
        const cardBody = card.querySelector('.card-body');
        if (cardBody) {
            cardBody.appendChild(downloadCounter);
        }
    }

    getVersionId(card) {
        // Criar ID único baseado na versão e tipo
        const version = card.dataset.version;
        const title = card.querySelector('.version-title').textContent;
        return `${version}-${title.replace(/\s+/g, '-').toLowerCase()}`;
    }

    setupDownloadTracking(card) {
        const downloadButtons = card.querySelectorAll('a[href*=".exe"], a[href*=".apk"], a[download]');
        const versionId = this.getVersionId(card);
        
        downloadButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                // Incrementar contador
                const newCount = this.incrementDownloadCount(versionId);
                
                // Mostrar confirmação
                this.showDownloadNotification(versionId, newCount);
                
                // Log para analytics
                console.log(`Download iniciado: ${versionId}, Total: ${newCount}`);
            });
        });
    }

    showDownloadNotification(versionId, count) {
        const versionName = versionId.split('-')[0];
        this.showNotification(
            `Download da versão ${versionName} iniciado! (${count} downloads)`,
            'success',
            2000
        );
    }

    toggleCardDetails(card) {
        const details = card.querySelector('.card-details');
        if (!details) {
            this.createCardDetails(card);
        } else {
            details.remove();
        }
    }

    createCardDetails(card) {
        const version = card.dataset.version;
        const versionId = this.getVersionId(card);
        const downloadCount = this.getDownloadCount(versionId);
        
        const details = document.createElement('div');
        details.className = 'card-details';
        details.innerHTML = `
            <div class="details-content">
         
            </div>
        `;
        
        card.appendChild(details);
        
        setTimeout(() => {
            details.style.opacity = '1';
            details.style.transform = 'translateY(0)';
        }, 10);
    }

    getReleaseDate(version) {
        const dates = {
            '25.09': '25/09/2024',
            '25.08': '25/08/2024',
            '25.07': '25/07/2024',
            '25.06': '25/06/2024',
            '25.05': '25/05/2024',
            '25.04': '25/04/2024',
            '25.03': '25/03/2024',
            '25.02': '25/02/2024',
            '25.01': '25/01/2024'
        };
        return dates[version] || 'Data não disponível';
    }

    getFileSize(version) {
        const sizes = {
            '25.09': '145 MB',
            '25.08': '142 MB',
            '25.07': '140 MB',
            '25.06': '138 MB',
            '25.05': '135 MB',
            '25.04': '132 MB',
            '25.03': '130 MB',
            '25.02': '128 MB',
            '25.01': '125 MB'
        };
        return sizes[version] || 'Tamanho não disponível';
    }

    // ===== SISTEMA DE NOTIFICAÇÕES =====
    initNotifications() {
        this.notificationContainer = document.createElement('div');
        this.notificationContainer.className = 'toast-container';
        document.body.appendChild(this.notificationContainer);
    }

    showNotification(message, type = 'info', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-circle',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };

        toast.innerHTML = `
            <i class="toast-icon ${icons[type]}"></i>
            <div class="toast-content">
                <div class="toast-title">${this.capitalize(type)}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close">
                <i class="fas fa-times"></i>
            </button>
        `;

        this.notificationContainer.appendChild(toast);

        setTimeout(() => toast.classList.add('show'), 100);

        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => this.hideNotification(toast));

        if (duration > 0) {
            setTimeout(() => this.hideNotification(toast), duration);
        }

        return toast;
    }

    hideNotification(toast) {
        toast.classList.remove('show');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }

    // ===== FILTROS =====
    initFilters() {
        this.createFilterButtons();
    }

    createFilterButtons() {
        const filterContainer = document.createElement('div');
        filterContainer.className = 'filter-group';
        
        const counts = this.calculateFilterCounts();
        
        filterContainer.innerHTML = `
            <button class="filter-btn active" data-filter="all">
                Todas
                <span class="count-badge">${counts.all}</span>
            </button>
            <button class="filter-btn" data-filter="new">
                Novas
                <span class="count-badge">${counts.new}</span>
            </button>
            <button class="filter-btn" data-filter="mobile">
                <i class="fas fa-mobile-alt"></i>
                Mobile
                <span class="count-badge">${counts.mobile}</span>
            </button>
            <button class="filter-btn" data-filter="pdv">
                <i class="fas fa-cash-register"></i>
                PDV
                <span class="count-badge">${counts.pdv}</span>
            </button>
            <button class="filter-btn" data-filter="whatsapp">
                <i class="fab fa-whatsapp"></i>
                WhatsApp
                <span class="count-badge">${counts.whatsapp}</span>
            </button>

<button class="filter-btn" data-filter="atualizador">
            <i class="fas fa-sync-alt"></i>
            Atualizador
            <span class="count-badge">${counts.atualizador}</span>
        </button>
        <button class="filter-btn" data-filter="migrador">
            <i class="fas fa-database"></i>
            Migrador
            <span class="count-badge">${counts.migrador}</span>
        </button>
        `;

        const searchSection = document.querySelector('.search-section .container');
        if (searchSection) {
            const filterSection = document.createElement('div');
            filterSection.className = 'filter-section';
            filterSection.innerHTML = '<div class="filter-section-title">Filtrar por:</div>';
            filterSection.appendChild(filterContainer);
            searchSection.appendChild(filterSection);
        }

        filterContainer.addEventListener('click', (e) => {
            if (e.target.closest('.filter-btn')) {
                const button = e.target.closest('.filter-btn');
                this.handleFilterClick(button);
            }
        });
    }

    calculateFilterCounts() {
        const cards = document.querySelectorAll('.version-card');
        const counts = {
            all: cards.length,
            new: 0,
            mobile: 0,
            pdv: 0,
            whatsapp: 0,
atualizador: 0,
migrador: 0 
        };

           cards.forEach(card => {
        if (card.querySelector('.version-badge.new')) {
            counts.new++;
        }

        const cardText = card.textContent.toLowerCase();
        const parentSection = card.closest('section');
        
        if (parentSection && parentSection.id) {
            if (parentSection.id.includes('mobile') || parentSection.id.includes('forca')) {
                counts.mobile++;
            } else if (parentSection.id.includes('pdv')) {
                counts.pdv++;
            } else if (parentSection.id.includes('whatsapp')) {
                counts.whatsapp++;
            }
        } else if (cardText.includes('mobile') || cardText.includes('força') || cardText.includes('fv')) {
            counts.mobile++;
        } else if (cardText.includes('pdv')) {
            counts.pdv++;
        } else if (cardText.includes('whatsapp') || cardText.includes('wa')) {
            counts.whatsapp++;
        }
        
        // NOVAS CONDIÇÕES PARA ATUALIZADOR E MIGRADOR
        if (cardText.includes('atualizador')) {
            counts.atualizador++;
        }
        if (cardText.includes('migrador')) {
            counts.migrador++;
        }
    });

    return counts;
}

    handleFilterClick(button) {
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        button.classList.add('active');
        
        const filter = button.dataset.filter;
        this.filterVersions(filter);
        
        this.animateFilterButton(button);
    }

    animateFilterButton(button) {
        button.style.transform = 'scale(0.95)';
        setTimeout(() => {
            button.style.transform = 'scale(1)';
        }, 150);
    }

    filterVersions(filter) {
        const cards = document.querySelectorAll('.version-card');
        let visibleCount = 0;

        cards.forEach(card => {
            const matches = this.cardMatchesFilter(card, filter);
            
            if (matches) {
                card.style.display = 'block';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
                visibleCount++;
            } else {
                card.style.opacity = '0';
                card.style.transform = 'translateY(10px)';
                setTimeout(() => {
                    card.style.display = 'none';
                }, 200);
            }
        });

        this.updateFilterResultsCount(visibleCount, filter);
    }

    cardMatchesFilter(card, filter) {
        if (filter === 'all') return true;
        
        const cardText = card.textContent.toLowerCase();
        const parentSection = card.closest('section');
        
        switch (filter) {
            case 'new':
                return card.querySelector('.version-badge.new') !== null;
                
            case 'mobile':
                return (parentSection && (
                    parentSection.id.includes('mobile') || 
                    parentSection.id.includes('forca-vendas')
                )) || cardText.includes('mobile') || 
                       cardText.includes('força') || 
                       cardText.includes('fv');
                
            case 'pdv':
                return (parentSection && parentSection.id.includes('pdv')) || 
                       cardText.includes('pdv');
                
            case 'whatsapp':
                return (parentSection && parentSection.id.includes('whatsapp')) || 
                       cardText.includes('whatsapp') || 
                       cardText.includes('wa');
                 case 'atualizador':
            return cardText.includes('atualizador');
            
        case 'migrador':
            return cardText.includes('migrador');
            default:
                return true;
        }
    }

    updateFilterResultsCount(count, filter) {
        const resultsCount = document.querySelector('.results-count');
        if (!resultsCount) return;

        const filterNames = {
            all: 'todas as',
            new: 'novas',
            mobile: 'mobile',
            pdv: 'PDV',
            whatsapp: 'WhatsApp'
        };

        resultsCount.textContent = `${count} versão${count !== 1 ? 's' : ''} ${filterNames[filter]} encontrada${count !== 1 ? 's' : ''}`;
        resultsCount.style.color = 'var(--primary-color)';
        resultsCount.style.fontWeight = '600';
    }

    // ===== BOTÃO DE IMPRESSÃO =====
    initPrintButton() {
        const printBtn = document.getElementById('printBtn');
        if (printBtn) {
            printBtn.addEventListener('click', () => {
                window.print();
            });
        }
    }

    // ===== ATALHOS DE TECLADO =====
    initKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.querySelector('.search-input');
                if (searchInput) {
                    searchInput.focus();
                }
            }

            if (e.key === 'Escape') {
                const searchInput = document.querySelector('.search-input');
                if (searchInput && searchInput.value) {
                    searchInput.value = '';
                    searchInput.focus();
                    this.performSearch('');
                }
            }

            if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
                e.preventDefault();
                this.toggleTheme();
            }

            if ((e.ctrlKey || e.metaKey) && e.key === 'l') {
                e.preventDefault();
                this.toggleLayoutMode();
            }
        });
    }

    // ===== BUSCA EM TEMPO REAL =====
    initSearch() {
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            let searchTimeout;
            
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.performSearch(e.target.value);
                }, 300);
            });

            this.performSearch('');
        }
    }

    performSearch(query) {
        const cards = document.querySelectorAll('.version-card');
        const resultsCount = document.querySelector('.results-count');
        let visibleCount = 0;

        const searchQuery = query.toLowerCase().trim();

        cards.forEach(card => {
            const title = card.querySelector('.version-title').textContent.toLowerCase();
            const description = card.querySelector('.version-desc').textContent.toLowerCase();
            const versionWithoutDot = title.replace('.', '');
            
            if (!searchQuery || 
                title.includes(searchQuery) || 
                description.includes(searchQuery) ||
                versionWithoutDot.includes(searchQuery)) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (resultsCount) {
            if (searchQuery) {
                resultsCount.textContent = `${visibleCount} versão${visibleCount !== 1 ? 's' : ''} encontrada${visibleCount !== 1 ? 's' : ''} para "${query}"`;
                resultsCount.style.color = 'var(--primary-color)';
                resultsCount.style.fontWeight = '600';
            } else {
                resultsCount.textContent = `${visibleCount} versão${visibleCount !== 1 ? 's' : ''} disponível${visibleCount !== 1 ? 's' : ''}`;
                resultsCount.style.color = 'var(--text-muted)';
                resultsCount.style.fontWeight = 'normal';
            }
        }
    }

    // ===== UTILITÁRIOS =====
    capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
}

// ===== INICIALIZAÇÃO DA APLICAÇÃO =====
document.addEventListener('DOMContentLoaded', () => {
    window.tgaApp = new TGAApp();
});

// ===== FUNÇÕES GLOBAIS =====
window.toggleDarkMode = () => {
    if (window.tgaApp) {
        window.tgaApp.toggleTheme();
    }
};

window.searchVersions = (query) => {
    if (window.tgaApp) {
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.value = query;
            window.tgaApp.performSearch(query);
        }
    }
};

window.clearSearch = () => {
    if (window.tgaApp) {
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.value = '';
            window.tgaApp.performSearch('');
        }
    }
};

// Função para resetar estatísticas (útil para desenvolvimento)
window.resetDownloadStats = () => {
    if (window.tgaApp && confirm('Tem certeza que deseja resetar todas as estatísticas de download?')) {
        localStorage.removeItem('tga-download-stats');
        window.location.reload();
    }
};

// Atualiza a página a cada 1 min
setInterval(function() {
    window.location.reload();
}, 60000); // 60000ms = 1 min
// Verificar e aplicar preferências ao carregar a página
function initializeProfileVisibility() {
    const profile = document.querySelector('.floating-profile');
    const profileInfo = document.querySelector('.floating-profile-info');
    const toggleBtn = document.querySelector('.floating-profile-toggle');
    const restoreBtn = document.querySelector('.restore-profile-btn');
    
    if (!profile || !profileInfo) return;
    
    // Verificar se é mobile
    const isMobile = window.innerWidth <= 768;
    
    // Verificar preferências salvas
    const isProfileHidden = localStorage.getItem('profileHidden') === 'true';
    const isProfileVisible = localStorage.getItem('profileVisible') === 'true';
    
    console.log('Mobile:', isMobile, 'Hidden:', isProfileHidden, 'Visible:', isProfileVisible);
    
    // Lógica corrigida:
    // 1. Primeiro verifica se há preferência salva
    // 2. Se não houver preferência, decide baseado no dispositivo
    if (isProfileHidden) {
        // Usuário escolheu ocultar explicitamente
        hideProfile();
        console.log('Perfil oculto por preferência salva');
    } else if (isProfileVisible) {
        // Usuário escolheu mostrar explicitamente
        showProfile();
        console.log('Perfil visível por preferência salva');
    } else {
        // Sem preferência salva - comportamento padrão
        if (isMobile) {
            // No mobile: ocultar por padrão
            hideProfile();
            if (restoreBtn) restoreBtn.style.display = 'flex';
            console.log('Perfil oculto (padrão mobile)');
        } else {
            // No desktop: mostrar por padrão
            showProfile();
            console.log('Perfil visível (padrão desktop)');
        }
    }
    
    // Configurar botão toggle inicial
    if (toggleBtn) {
        const isVisible = !profile.classList.contains('hidden');
        toggleBtn.innerHTML = isVisible ? '<i class="fas fa-times"></i>' : '<i class="fas fa-eye"></i>';
        toggleBtn.title = isVisible ? 'Ocultar perfil' : 'Mostrar perfil';
        
        // No mobile, manter o botão sempre visível
        if (isMobile) {
            toggleBtn.style.opacity = '1';
        }
    }
}

// Função principal para alternar visibilidade do perfil
function toggleProfile() {
    const profile = document.querySelector('.floating-profile');
    const profileInfo = document.querySelector('.floating-profile-info');
    const toggleBtn = document.querySelector('.floating-profile-toggle');
    const restoreBtn = document.querySelector('.restore-profile-btn');
    
    const isCurrentlyHidden = profile.classList.contains('hidden');
    
    if (isCurrentlyHidden) {
        // Mostrar perfil
        profile.classList.remove('hidden');
        profileInfo.classList.remove('hidden');
        if (restoreBtn) restoreBtn.style.display = 'none';
        
        // Atualizar ícone para X
        if (toggleBtn) {
            toggleBtn.innerHTML = '<i class="fas fa-times"></i>';
            toggleBtn.title = 'Ocultar perfil';
        }
        
        // Salvar preferência
        localStorage.setItem('profileVisible', 'true');
        localStorage.removeItem('profileHidden');
        
        if (window.tgaApp) {
            window.tgaApp.showNotification('Perfil visível', 'success', 1500);
        }
    } else {
        // Ocultar perfil
        profile.classList.add('hidden');
        profileInfo.classList.add('hidden');
        if (restoreBtn) restoreBtn.style.display = 'flex';
        
        // Atualizar ícone para olho
        if (toggleBtn) {
            toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
            toggleBtn.title = 'Mostrar perfil';
        }
        
        // Salvar preferência
        localStorage.setItem('profileHidden', 'true');
        localStorage.removeItem('profileVisible');
        
        if (window.tgaApp) {
            window.tgaApp.showNotification('Perfil ocultado', 'info', 1500);
        }
    }
    
    console.log('Perfil alternado. Agora está:', isCurrentlyHidden ? 'visível' : 'oculto');
}

// Função específica para mostrar o perfil (usada pelo botão de restaurar)
function showProfile() {
    const profile = document.querySelector('.floating-profile');
    const profileInfo = document.querySelector('.floating-profile-info');
    
    if (profile && profileInfo) {
        profile.classList.remove('hidden');
        profileInfo.classList.remove('hidden');
        
        // Atualizar botão toggle se existir
        const toggleBtn = document.querySelector('.floating-profile-toggle');
        if (toggleBtn) {
            toggleBtn.innerHTML = '<i class="fas fa-times"></i>';
            toggleBtn.title = 'Ocultar perfil';
        }
        
        // Ocultar botão de restaurar
        const restoreBtn = document.querySelector('.restore-profile-btn');
        if (restoreBtn) restoreBtn.style.display = 'none';
        
        // Salvar preferência
        localStorage.setItem('profileVisible', 'true');
        localStorage.removeItem('profileHidden');
        
        console.log('Perfil mostrado manualmente');
    }
}

// Função específica para ocultar o perfil
function hideProfile() {
    const profile = document.querySelector('.floating-profile');
    const profileInfo = document.querySelector('.floating-profile-info');
    
    if (profile && profileInfo) {
        profile.classList.add('hidden');
        profileInfo.classList.add('hidden');
        
        // Atualizar botão toggle se existir
        const toggleBtn = document.querySelector('.floating-profile-toggle');
        if (toggleBtn) {
            toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
            toggleBtn.title = 'Mostrar perfil';
        }
        
        // Mostrar botão de restaurar
        const restoreBtn = document.querySelector('.restore-profile-btn');
        if (restoreBtn) restoreBtn.style.display = 'flex';
        
        // Salvar preferência
        localStorage.setItem('profileHidden', 'true');
        localStorage.removeItem('profileVisible');
        
        console.log('Perfil ocultado manualmente');
    }
}

// Adicionar botão de restaurar no floating-actions
function addRestoreProfileButton() {
    const floatingActions = document.querySelector('.floating-actions');
    
    if (floatingActions && !document.querySelector('.restore-profile-btn')) {
        const restoreBtn = document.createElement('button');
        restoreBtn.className = 'action-btn restore-profile-btn';
        restoreBtn.innerHTML = '<i class="fas fa-user"></i>';
        restoreBtn.title = 'Mostrar perfil';
        restoreBtn.style.display = 'none';
        restoreBtn.addEventListener('click', showProfile);
        
        floatingActions.appendChild(restoreBtn);
        console.log('Botão de restaurar adicionado');
    }
}

// Atualizar visibilidade quando a janela for redimensionada
function handleResize() {
    const isMobile = window.innerWidth <= 768;
    const restoreBtn = document.querySelector('.restore-profile-btn');
    const hasPreference = localStorage.getItem('profileHidden') === 'true' || localStorage.getItem('profileVisible') === 'true';
    
    console.log('Redimensionado para:', isMobile ? 'mobile' : 'desktop', 'Preferência:', hasPreference);
    
    if (isMobile && !hasPreference) {
        // Se mudou para mobile e não há preferência salva, ocultar
        hideProfile();
        if (restoreBtn) restoreBtn.style.display = 'flex';
    }
}

// Inicializar quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    console.log('Inicializando visibilidade do perfil...');
    addRestoreProfileButton();
    initializeProfileVisibility();
    
    // Observar redimensionamento da tela
    window.addEventListener('resize', handleResize);
});

// Adicionar ao objeto global para acesso externo
window.toggleProfile = toggleProfile;
window.showProfile = showProfile;
window.hideProfile = hideProfile;
window.initializeProfileVisibility = initializeProfileVisibility;