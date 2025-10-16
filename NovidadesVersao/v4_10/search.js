// ===== SISTEMA DE BUSCA AVANÇADO =====
class TGASearch {
    constructor() {
        this.searchInput = document.getElementById('searchInput');
        this.resultsCount = document.getElementById('resultsCount');
        this.versionCards = document.querySelectorAll('.version-card');
        this.sections = document.querySelectorAll('.version-section, .module-section');
        this.searchTimeout = null;
        this.lastSearchQuery = '';
        
        this.init();
    }

    init() {
        if (this.searchInput) {
            this.setupEventListeners();
            this.updateResultsCount(this.versionCards.length);
        }
    }

    setupEventListeners() {
        // Busca em tempo real com debounce
        this.searchInput.addEventListener('input', (e) => {
            this.handleSearchInput(e.target.value);
        });

        // Limpar busca com ESC
        this.searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.clearSearch();
            }
        });

        // Foco automático com Ctrl+K
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                this.searchInput.focus();
            }
        });
    }

    handleSearchInput(query) {
        clearTimeout(this.searchTimeout);
        
        this.searchTimeout = setTimeout(() => {
            this.performSearch(query.trim());
        }, 250);
    }

    performSearch(query) {
        this.lastSearchQuery = query;
        
        if (!query) {
            this.clearSearch();
            return;
        }

        let visibleCount = 0;
        let hasResults = false;

        // Processar cada card
        this.versionCards.forEach(card => {
            const matches = this.cardMatchesQuery(card, query);
            
            if (matches) {
                card.style.display = 'block';
                this.highlightMatches(card, query);
                visibleCount++;
                hasResults = true;
            } else {
                card.style.display = 'none';
                this.removeHighlights(card);
            }
        });

        // Ocultar seções vazias e mostrar as que têm resultados
        this.toggleEmptySections();

        // Atualizar contador
        this.updateResultsCount(visibleCount, query);

        // Mostrar mensagem se não houver resultados
        if (!hasResults) {
            this.showNoResultsMessage(query);
        } else {
            this.hideNoResultsMessage();
        }

        // Rolar para o primeiro resultado
        this.scrollToFirstResult();
    }

    cardMatchesQuery(card, query) {
        const searchableText = this.getCardSearchableText(card).toLowerCase();
        const searchTerms = this.parseSearchQuery(query.toLowerCase());
        
        return searchTerms.every(term => searchableText.includes(term));
    }

    getCardSearchableText(card) {
        const title = card.querySelector('.version-title')?.textContent || '';
        const description = card.querySelector('.version-desc')?.textContent || '';
        const badges = Array.from(card.querySelectorAll('.version-badge'))
            .map(badge => badge.textContent)
            .join(' ');
        const buttons = Array.from(card.querySelectorAll('.btn'))
            .map(btn => btn.textContent)
            .join(' ');
        
        return `${title} ${description} ${badges} ${buttons}`;
    }

    parseSearchQuery(query) {
        // Suporte a múltiplos termos e formatos especiais
        const terms = query.split(/\s+/).filter(term => term.length > 0);
        
        return terms.map(term => {
            // Converter "2410" para "24.10"
            if (/^\d{4}$/.test(term)) {
                return term.substring(0, 2) + '.' + term.substring(2);
            }
            // Converter "2509" para "25.09"
            if (/^\d{3}$/.test(term)) {
                return term.substring(0, 2) + '.0' + term.substring(2);
            }
            return term;
        });
    }

    highlightMatches(card, query) {
        this.removeHighlights(card);
        
        const searchTerms = this.parseSearchQuery(query.toLowerCase());
        const elementsToHighlight = [
            card.querySelector('.version-title'),
            card.querySelector('.version-desc')
        ].filter(el => el);

        elementsToHighlight.forEach(element => {
            const originalHTML = element.innerHTML;
            let highlightedHTML = originalHTML;

            searchTerms.forEach(term => {
                const regex = new RegExp(this.escapeRegex(term), 'gi');
                highlightedHTML = highlightedHTML.replace(
                    regex, 
                    match => `<mark class="search-highlight">${match}</mark>`
                );
            });

            element.innerHTML = highlightedHTML;
        });
    }

    removeHighlights(card) {
        const highlights = card.querySelectorAll('.search-highlight');
        highlights.forEach(highlight => {
            const parent = highlight.parentNode;
            parent.replaceChild(document.createTextNode(highlight.textContent), highlight);
            parent.normalize();
        });
    }

    escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    toggleEmptySections() {
        this.sections.forEach(section => {
            const visibleCards = section.querySelectorAll('.version-card[style="display: block"]');
            const sectionHeader = section.querySelector('.section-header');
            
            if (visibleCards.length === 0) {
                section.style.display = 'none';
            } else {
                section.style.display = 'block';
                // Destacar seção com resultados
                if (this.lastSearchQuery && sectionHeader) {
                    sectionHeader.style.backgroundColor = 'var(--bg-tertiary)';
                    sectionHeader.style.borderLeft = '4px solid var(--primary-color)';
                    sectionHeader.style.paddingLeft = 'var(--spacing-sm)';
                }
            }
        });
    }

    updateResultsCount(count, query = '') {
        if (!this.resultsCount) return;

        if (query) {
            this.resultsCount.textContent = `${count} versão${count !== 1 ? 's' : ''} encontrada${count !== 1 ? 's' : ''} para "${query}"`;
            this.resultsCount.style.color = 'var(--primary-color)';
            this.resultsCount.style.fontWeight = '600';
        } else {
            this.resultsCount.textContent = `${count} versão${count !== 1 ? 's' : ''} disponível${count !== 1 ? 's' : ''}`;
            this.resultsCount.style.color = 'var(--text-muted)';
            this.resultsCount.style.fontWeight = 'normal';
        }
    }

    showNoResultsMessage(query) {
        this.hideNoResultsMessage();
        
        const message = document.createElement('div');
        message.className = 'no-results-message';
        message.innerHTML = `
            <div class="no-results-content">
                <i class="fas fa-search"></i>
                <h3>Nenhum resultado encontrado</h3>
                <p>Não encontramos versões correspondentes a "<strong>${query}</strong>"</p>
                <div class="suggestions">
                    <p><strong>Sugestões:</strong></p>
                    <ul>
                        <li>Verifique a ortografia dos termos</li>
                        <li>Tente termos de busca mais genéricos</li>
                        <li>Use números como "2410" para buscar "24.10"</li>
                        <li>Busque por módulos específicos (PDV, Mobile, etc.)</li>
                    </ul>
                </div>
                <button class="btn btn-primary clear-search-btn">
                    <i class="fas fa-times"></i>
                    Limpar busca
                </button>
            </div>
        `;

        document.querySelector('.main-content').appendChild(message);

        // Botão para limpar busca
        message.querySelector('.clear-search-btn').addEventListener('click', () => {
            this.clearSearch();
        });

        // Adicionar estilos se não existirem
        this.ensureNoResultsStyles();
    }

    hideNoResultsMessage() {
        const existingMessage = document.querySelector('.no-results-message');
        if (existingMessage) {
            existingMessage.remove();
        }
    }

    ensureNoResultsStyles() {
        if (!document.querySelector('#no-results-styles')) {
            const styles = document.createElement('style');
            styles.id = 'no-results-styles';
            styles.textContent = `
                .no-results-message {
                    text-align: center;
                    padding: var(--spacing-xl);
                    background: var(--bg-secondary);
                    border-radius: var(--border-radius);
                    margin: var(--spacing-lg) 0;
                    border: 2px dashed var(--border-color);
                }
                .no-results-content i {
                    font-size: 3rem;
                    color: var(--text-muted);
                    margin-bottom: var(--spacing-md);
                }
                .no-results-content h3 {
                    color: var(--text-primary);
                    margin-bottom: var(--spacing-sm);
                }
                .no-results-content p {
                    color: var(--text-secondary);
                    margin-bottom: var(--spacing-md);
                }
                .suggestions {
                    text-align: left;
                    max-width: 400px;
                    margin: 0 auto var(--spacing-lg);
                }
                .suggestions ul {
                    color: var(--text-muted);
                    padding-left: var(--spacing-md);
                }
                .suggestions li {
                    margin-bottom: var(--spacing-xs);
                }
                .search-highlight {
                    background-color: var(--accent-color);
                    color: white;
                    padding: 0.1rem 0.2rem;
                    border-radius: 2px;
                    font-weight: 600;
                }
            `;
            document.head.appendChild(styles);
        }
    }

    clearSearch() {
        this.searchInput.value = '';
        this.lastSearchQuery = '';
        
        // Mostrar todos os cards
        this.versionCards.forEach(card => {
            card.style.display = 'block';
            this.removeHighlights(card);
        });

        // Mostrar todas as seções
        this.sections.forEach(section => {
            section.style.display = 'block';
            const sectionHeader = section.querySelector('.section-header');
            if (sectionHeader) {
                sectionHeader.style.backgroundColor = '';
                sectionHeader.style.borderLeft = '';
                sectionHeader.style.paddingLeft = '';
            }
        });

        // Atualizar contador
        this.updateResultsCount(this.versionCards.length);
        
        // Ocultar mensagem de sem resultados
        this.hideNoResultsMessage();
        
        // Focar no campo de busca
        this.searchInput.focus();
    }

    scrollToFirstResult() {
        const firstVisibleCard = document.querySelector('.version-card[style="display: block"]');
        if (firstVisibleCard) {
            firstVisibleCard.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }
    }

    // Método público para busca programática
    search(query) {
        this.searchInput.value = query;
        this.performSearch(query);
    }

    // Método público para limpar busca
    clear() {
        this.clearSearch();
    }
}

// ===== INICIALIZAÇÃO DO SISTEMA DE BUSCA =====
document.addEventListener('DOMContentLoaded', () => {
    window.tgaSearch = new TGASearch();
    
    // Expor métodos globais para uso externo
    window.searchVersions = (query) => window.tgaSearch.search(query);
    window.clearSearch = () => window.tgaSearch.clear();
});

// ===== INTEGRAÇÃO COM O APP PRINCIPAL =====
if (window.tgaApp) {
    // Sobrescrever o método initSearch do app principal
    const originalInitSearch = window.tgaApp.initSearch;
    window.tgaApp.initSearch = function() {
        // O sistema de busca já é inicializado automaticamente
        console.log('Sistema de busca inicializado');
    };
}