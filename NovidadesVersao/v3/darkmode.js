// ===== SISTEMA DE MODO ESCURO AVANÇADO =====
class TGADarkMode {
    constructor() {
        this.currentTheme = this.getSavedTheme();
        this.systemPreference = this.getSystemPreference();
        this.autoToggleEnabled = this.getAutoToggleSetting();
        this.transitionDuration = 300;
        
        this.init();
    }

    init() {
        this.applyTheme();
        this.setupEventListeners();
        this.injectTransitionStyles();
        this.createThemeToggleButton();
        this.setupAutoToggle();
    }

    getSavedTheme() {
        const saved = localStorage.getItem('tga-theme');
        return saved || 'auto';
    }

    getSystemPreference() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    getAutoToggleSetting() {
        return localStorage.getItem('tga-auto-theme') !== 'false';
    }

    getEffectiveTheme() {
        if (this.currentTheme === 'auto') {
            return this.systemPreference;
        }
        return this.currentTheme;
    }

    applyTheme() {
        const theme = this.getEffectiveTheme();
        const startTime = performance.now();

        // Adicionar classe de transição
        document.documentElement.classList.add('theme-transitioning');

        // Aplicar tema
        document.documentElement.setAttribute('data-theme', theme);

        // Atualizar meta theme-color
        this.updateMetaThemeColor(theme);

        // Atualizar ícones e elementos de UI
        this.updateUIElements(theme);

        // Remover classe de transição após animação
        setTimeout(() => {
            document.documentElement.classList.remove('theme-transitioning');
        }, this.transitionDuration);

        // Log de performance
        const endTime = performance.now();
        console.log(`Tema aplicado: ${theme} (${(endTime - startTime).toFixed(2)}ms)`);
    }

    updateMetaThemeColor(theme) {
        let metaTheme = document.querySelector('meta[name="theme-color"]');
        const colors = {
            light: '#2563eb',
            dark: '#1e293b'
        };

        if (!metaTheme) {
            metaTheme = document.createElement('meta');
            metaTheme.name = 'theme-color';
            document.head.appendChild(metaTheme);
        }

        metaTheme.content = colors[theme] || colors.light;
    }

    updateUIElements(theme) {
        // Atualizar botão de tema
        const themeButtons = document.querySelectorAll('.dark-mode-btn, .theme-toggle-btn');
        themeButtons.forEach(btn => {
            const icon = btn.querySelector('i');
            if (icon) {
                if (this.currentTheme === 'auto') {
                    icon.className = 'fas fa-adjust';
                    btn.title = `Modo automático (${this.getEffectiveTheme() === 'dark' ? 'escuro' : 'claro'})`;
                } else {
                    icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
                    btn.title = `Alternar para modo ${theme === 'dark' ? 'claro' : 'escuro'}`;
                }
            }
        });

        // Atualizar favicon para tema escuro
        this.updateFavicon(theme);

        // Disparar evento personalizado
        window.dispatchEvent(new CustomEvent('themeChanged', {
            detail: { theme, effectiveTheme: this.getEffectiveTheme() }
        }));
    }

    updateFavicon(theme) {
        // Pode ser implementado para usar favicons diferentes para cada tema
        // Por enquanto, mantém o favicon padrão
        console.log(`Tema: ${theme} - Favicon poderia ser atualizado aqui`);
    }

    setupEventListeners() {
        // Botões de toggle de tema
        document.addEventListener('click', (e) => {
            if (e.target.closest('.dark-mode-btn') || e.target.closest('.theme-toggle-btn')) {
                this.toggleTheme();
            }
        });

        // Observar mudanças no sistema
        this.setupSystemPreferenceListener();

        // Tecla de atalho (Ctrl+Shift+D)
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'D') {
                e.preventDefault();
                this.toggleTheme();
            }
        });
    }

    setupSystemPreferenceListener() {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        
        const handleSystemChange = (e) => {
            this.systemPreference = e.matches ? 'dark' : 'light';
            
            if (this.currentTheme === 'auto' && this.autoToggleEnabled) {
                this.applyTheme();
                
                // Mostrar notificação suave
                this.showSystemThemeNotification();
            }
        };

        // Suporte para navegadores modernos e antigos
        if (mediaQuery.addEventListener) {
            mediaQuery.addEventListener('change', handleSystemChange);
        } else {
            mediaQuery.addListener(handleSystemChange);
        }
    }

    showSystemThemeNotification() {
        if (window.tgaApp && window.tgaApp.showNotification) {
            window.tgaApp.showNotification(
                `Modo do sistema alterado para ${this.systemPreference === 'dark' ? 'escuro' : 'claro'}`,
                'info',
                2000
            );
        }
    }

    createThemeToggleButton() {
        // Verificar se já existe um botão de tema
        let themeBtn = document.querySelector('.dark-mode-btn');
        
        if (!themeBtn) {
            themeBtn = document.createElement('button');
            themeBtn.className = 'action-btn dark-mode-btn';
            themeBtn.innerHTML = '<i class="fas fa-moon"></i>';
            themeBtn.title = 'Alternar tema (Ctrl+Shift+D)';
            
            // Adicionar às ações flutuantes
            const floatingActions = document.querySelector('.floating-actions');
            if (floatingActions) {
                floatingActions.appendChild(themeBtn);
            }
        }

        // Atualizar o botão existente
        this.updateUIElements(this.getEffectiveTheme());
    }

    toggleTheme() {
        const themes = ['light', 'dark', 'auto'];
        const currentIndex = themes.indexOf(this.currentTheme);
        const nextIndex = (currentIndex + 1) % themes.length;
        
        this.setTheme(themes[nextIndex]);
    }

    setTheme(theme) {
        if (!['light', 'dark', 'auto'].includes(theme)) {
            console.warn('Tema inválido:', theme);
            return;
        }

        this.currentTheme = theme;
        localStorage.setItem('tga-theme', theme);

        this.applyTheme();
        this.showThemeNotification(theme);
    }

    showThemeNotification(theme) {
        if (window.tgaApp && window.tgaApp.showNotification) {
            const messages = {
                light: 'Modo claro ativado',
                dark: 'Modo escuro ativado',
                auto: 'Modo automático ativado'
            };
            
            window.tgaApp.showNotification(messages[theme], 'success', 2000);
        }
    }

    setupAutoToggle() {
        // Pode ser expandido para toggle automático baseado em horário
        console.log('Auto-toggle configurado:', this.autoToggleEnabled);
    }

    injectTransitionStyles() {
        if (!document.querySelector('#theme-transition-styles')) {
            const styles = document.createElement('style');
            styles.id = 'theme-transition-styles';
            styles.textContent = `
                .theme-transitioning * {
                    transition-duration: ${this.transitionDuration}ms !important;
                    transition-property: background-color, border-color, color, fill, stroke !important;
                    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1) !important;
                }
                
                /* Melhorias visuais para o modo escuro */
                [data-theme="dark"] {
                    color-scheme: dark;
                }
                
                [data-theme="dark"] img {
                    filter: brightness(.8) contrast(1.2);
                }
                
                [data-theme="dark"] .version-card {
                    border-color: var(--border-color);
                }
                
                [data-theme="dark"] .search-highlight {
                    background-color: var(--accent-color);
                    color: var(--bg-primary);
                }
                
                /* Animações suaves para elementos específicos */
                .version-card,
                .btn,
                .action-btn,
                .search-input {
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                }
            `;
            document.head.appendChild(styles);
        }
    }

    // Métodos públicos para controle externo
    enableDarkMode() {
        this.setTheme('dark');
    }

    enableLightMode() {
        this.setTheme('light');
    }

    enableAutoMode() {
        this.setTheme('auto');
    }

    isDarkMode() {
        return this.getEffectiveTheme() === 'dark';
    }

    isLightMode() {
        return this.getEffectiveTheme() === 'light';
    }

    isAutoMode() {
        return this.currentTheme === 'auto';
    }

    // Estatísticas de uso (opcional)
    getThemeStatistics() {
        const stats = JSON.parse(localStorage.getItem('tga-theme-stats') || '{}');
        return {
            current: this.currentTheme,
            effective: this.getEffectiveTheme(),
            system: this.systemPreference,
            autoToggle: this.autoToggleEnabled,
            usage: stats
        };
    }
}

// ===== INICIALIZAÇÃO DO MODO ESCURO =====
document.addEventListener('DOMContentLoaded', () => {
    window.tgaDarkMode = new TGADarkMode();
    
    // Expor métodos globais para uso externo
    window.enableDarkMode = () => window.tgaDarkMode.enableDarkMode();
    window.enableLightMode = () => window.tgaDarkMode.enableLightMode();
    window.enableAutoMode = () => window.tgaDarkMode.enableAutoMode();
    window.toggleDarkMode = () => window.tgaDarkMode.toggleTheme();
});

// ===== INTEGRAÇÃO COM O APP PRINCIPAL =====
if (window.tgaApp) {
    // Substituir a função global toggleDarkMode
    window.toggleDarkMode = () => window.tgaDarkMode.toggleTheme();
    
    // Integrar com o sistema de notificações do app
    window.tgaDarkMode.showThemeNotification = function(theme) {
        if (window.tgaApp && window.tgaApp.showNotification) {
            const messages = {
                light: 'Modo claro ativado ☀️',
                dark: 'Modo escuro ativado 🌙', 
                auto: 'Modo automático ativado ⚡'
            };
            
            window.tgaApp.showNotification(messages[theme], 'success', 2000);
        }
    };
}

// ===== DETECÇÃO DE RECURSOS =====
if (!window.matchMedia) {
    console.warn('matchMedia não suportado - modo automático desabilitado');
    // Fallback: sempre usar modo claro
    document.documentElement.setAttribute('data-theme', 'light');
}