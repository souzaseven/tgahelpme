// sistema_notificacoes_global.js - Sistema completo de notificações (CORRIGIDO)

class SistemaNotificacoesGlobal {
    constructor() {
        this.notificacoesAtivas = new Map();
        this.inicializado = false;
        this.inicializar();
    }

    inicializar() {
        if (this.inicializado) return;
        
        this.criarContainerToast();
        this.solicitarPermissaoNotificacao();
        this.inicializarSistemaVoz();
        
        this.inicializado = true;
        console.log('🔔 SISTEMA DE NOTIFICAÇÕES GLOBAL INICIALIZADO');
    }

    // ==================== SISTEMA DE TOAST ====================
    criarContainerToast() {
        if (!document.getElementById('toast-container-global')) {
            const container = document.createElement('div');
            container.id = 'toast-container-global';
            container.className = 'toast-container-global';
            document.body.appendChild(container);
        }
    }

    mostrarToast(mensagem, tipo = 'info', duracao = 5000) {
        const container = document.getElementById('toast-container-global');
        const toastId = 'toast-' + Date.now();
        const toast = document.createElement('div');
        
        toast.id = toastId;
        toast.className = `toast-global toast-${tipo}`;
        toast.innerHTML = `
            <div class="toast-icon">
                <i class="${this.getIcone(tipo)}"></i>
            </div>
            <div class="toast-content">
                <div class="toast-message">${mensagem}</div>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;

        container.appendChild(toast);
        
        // Animação de entrada
        setTimeout(() => toast.classList.add('show'), 10);
        
        // Auto-remover
        if (duracao > 0) {
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 300);
                }
            }, duracao);
        }

        return toastId;
    }

    // ==================== NOTIFICAÇÕES WINDOWS ====================
    solicitarPermissaoNotificacao() {
        if ("Notification" in window && Notification.permission === "default") {
            Notification.requestPermission();
        }
    }

    mostrarNotificacaoWindows(titulo, mensagem, icon = null) {
        if (!("Notification" in window)) {
            console.log("Este navegador não suporta notificações desktop");
            return;
        }

        if (Notification.permission === "granted") {
            const options = {
                body: mensagem,
                icon: icon || '/favicon.ico',
                badge: '/favicon.ico',
                tag: 'controle-pausa',
                requireInteraction: false // Mudado para false para não bloquear
            };

            new Notification(titulo, options);
        } else if (Notification.permission === "default") {
            Notification.requestPermission().then(permission => {
                if (permission === "granted") {
                    this.mostrarNotificacaoWindows(titulo, mensagem, icon);
                }
            });
        }
    }

    // ==================== SISTEMA DE VOZ ====================
    inicializarSistemaVoz() {
        if (!('speechSynthesis' in window)) {
            console.warn('Síntese de voz não suportada');
            return;
        }

        this.vozPortugues = null;
        this.carregarVozes();
    }

    carregarVozes() {
        const vozes = speechSynthesis.getVoices();
        if (vozes.length > 0) {
            this.selecionarVozPortugues(vozes);
        } else {
            speechSynthesis.addEventListener('voiceschanged', () => {
                this.selecionarVozPortugues(speechSynthesis.getVoices());
            });
        }
    }

    selecionarVozPortugues(vozes) {
        const vozesPT = vozes.filter(voz => voz.lang.includes('pt'));
        if (vozesPT.length > 0) {
            // Preferir vozes femininas
            const vozFeminina = vozesPT.find(voz => 
                voz.name.includes('Female') || voz.name.includes('feminina') || voz.name.includes('Portugues')
            );
            this.vozPortugues = vozFeminina || vozesPT[0];
        }
    }

    falarNotificacao(texto) {
        if (!this.vozPortugues || !('speechSynthesis' in window)) return;

        // Parar fala anterior
        speechSynthesis.cancel();

        // CORREÇÃO: Limpar completamente HTML e emojis para voz
        const textoFalavel = this.prepararTextoParaVoz(texto);

        if (!textoFalavel || textoFalavel.trim() === '') return;

        const utterance = new SpeechSynthesisUtterance(textoFalavel);
        utterance.voice = this.vozPortugues;
        utterance.rate = 0.9;
        utterance.pitch = 1;
        utterance.volume = 0.8;
        utterance.lang = 'pt-BR';

        utterance.onerror = (event) => {
            console.warn('Erro na síntese de voz:', event.error);
        };

        speechSynthesis.speak(utterance);
    }

    prepararTextoParaVoz(texto) {
        return texto
            .replace(/<br\s*\/?>/gi, '. ') // Quebras de linha viram pontos
            .replace(/<small[^>]*>.*?<\/small>/gi, '') // Remove textos pequenos
            .replace(/<strong>/gi, '') // Remove strong mas mantém o texto
            .replace(/<\/strong>/gi, '')
            .replace(/<[^>]*>/g, '') // Remove todas as tags HTML
            .replace(/📋/g, '') // Remove emojis
            .replace(/👑/g, '')
            .replace(/☕/g, '')
            .replace(/🚪/g, '')
            .replace(/❌/g, '')
            .replace(/🔄/g, 'Troca: ')
            .replace(/🚨/g, 'Alerta! ')
            .replace(/✅/g, '')
            .replace(/⚠️/g, 'Atenção! ')
            .replace(/ℹ️/g, '')
            .replace(/\s+/g, ' ') // Remove espaços múltiplos
            .replace(/\.\s*\./g, '.') // Remove pontos duplicados
            .trim();
    }

    // ==================== NOTIFICAÇÃO COMPLETA ====================
    async notificarGlobal(mensagem, tipo = 'info', duracao = 5000, forcarGlobal = false) {
        console.log(`🔔 NOTIFICAÇÃO [${tipo}]: ${mensagem}`);

        // 1. Toast local
        this.mostrarToast(mensagem, tipo, duracao);

        // 2. Notificação Windows
        const titulo = this.getTituloNotificacao(tipo);
        this.mostrarNotificacaoWindows(titulo, this.limparHTML(mensagem));

        // 3. Voz (CORRIGIDO: sem emojis/HTML)
        this.falarNotificacao(mensagem);

        // 4. Sons
        this.tocarSomNotificacao(tipo);

        // 5. Enviar para todas as telas (se necessário)
        if (forcarGlobal && typeof notificacoesTempoReal !== 'undefined') {
            await notificacoesTempoReal.enviarNotificacaoGlobal(mensagem, tipo, duracao);
        }
    }

    tocarSomNotificacao(tipo) {
        if (typeof sonsNotificacoes === 'undefined') return;

        switch (tipo) {
            case 'success':
                sonsNotificacoes.tocarSomSucesso();
                break;
            case 'warning':
                sonsNotificacoes.tocarSomAlerta();
                break;
            case 'danger':
                sonsNotificacoes.tocarSomTempoExcedido();
                break;
            case 'info':
            default:
                sonsNotificacoes.tocarSomInfo();
                break;
        }
    }

    // ==================== NOTIFICAÇÕES ESPECÍFICAS ====================
    async notificarEntradaFila(nome) {
        const mensagem = `📋 <strong>${nome}</strong> entrou na fila de espera`;
        await this.notificarGlobal(mensagem, 'info', 4000, true);
        this.tocarSomEspecifico('entradaFila');
    }

    async notificarPrimeiroFila(nome) {
        const mensagem = `👑 <strong>${nome}</strong> agora é o primeiro da fila! Pode entrar na pausa.`;
        await this.notificarGlobal(mensagem, 'success', 6000, true);
        this.tocarSomEspecifico('primeiroFila');
    }

    async notificarEntradaPausa(nome) {
        const mensagem = `☕ <strong>${nome}</strong> entrou na pausa`;
        await this.notificarGlobal(mensagem, 'success', 4000, true);
        this.tocarSomEspecifico('entradaPausa');
    }

    async notificarSaidaPausa(nome) {
        const mensagem = `🚪 <strong>${nome}</strong> saiu da pausa`;
        await this.notificarGlobal(mensagem, 'info', 4000, true);
        this.tocarSomEspecifico('saidaPausa');
    }

    async notificarSaidaFila(nome) {
        const mensagem = `❌ <strong>${nome}</strong> saiu da fila de espera`;
        await this.notificarGlobal(mensagem, 'warning', 4000, true);
        this.tocarSomEspecifico('saidaFila');
    }

    async notificarSolicitacaoTroca(primeiroNome, segundoNome) {
        const primeiroCurto = primeiroNome.split(' ')[0];
        const segundoCurto = segundoNome.split(' ')[0];
        
        const mensagem = `🔄 <strong>${segundoCurto}</strong> quer trocar de lugar com <strong>${primeiroCurto}</strong>`;
        await this.notificarGlobal(mensagem, 'warning', 10000, true);
        this.tocarSomEspecifico('solicitacaoTroca');
    }

    async notificarTempoExcedido(nome) {
        const mensagem = `🚨 <strong>${nome}</strong> excedeu o tempo de pausa de 20 minutos!`;
        await this.notificarGlobal(mensagem, 'danger', 10000, true);
        this.tocarSomEspecifico('tempoExcedido');
    }

    // NOVO: Notificação para pessoa logada quando é a primeira da fila
    async notificarVocePrimeiroFila(nome) {
        const mensagem = `🎉 <strong>${nome}</strong>, você é o primeiro da fila! Clique em "Decidir" para entrar na pausa.`;
        await this.notificarGlobal(mensagem, 'success', 8000, false); // Apenas local
        this.tocarSomEspecifico('primeiroFila');
    }

    tocarSomEspecifico(acao) {
        if (typeof sonsNotificacoes === 'undefined') return;

        switch (acao) {
            case 'entradaFila':
                sonsNotificacoes.tocarSomEntradaFila();
                break;
            case 'primeiroFila':
                sonsNotificacoes.tocarSomPrimeiroFila();
                break;
            case 'entradaPausa':
                sonsNotificacoes.tocarSomEntradaPausa();
                break;
            case 'saidaPausa':
                sonsNotificacoes.tocarSomSaidaPausa();
                break;
            case 'saidaFila':
                sonsNotificacoes.tocarSomSaidaFila();
                break;
            case 'solicitacaoTroca':
                sonsNotificacoes.tocarSomTroca();
                break;
            case 'tempoExcedido':
                sonsNotificacoes.tocarSomTempoExcedido();
                break;
        }
    }

    // ==================== FUNÇÕES AUXILIARES ====================
    getIcone(tipo) {
        const icones = {
            success: 'fas fa-check-circle',
            warning: 'fas fa-exclamation-triangle',
            danger: 'fas fa-times-circle',
            info: 'fas fa-info-circle'
        };
        return icones[tipo] || 'fas fa-bell';
    }

    getTituloNotificacao(tipo) {
        const titulos = {
            success: '✅ Pausa - Sucesso',
            warning: '⚠️ Pausa - Atenção',
            danger: '🚨 Pausa - Alerta',
            info: 'ℹ️ Pausa - Informação'
        };
        return titulos[tipo] || 'Controle de Pausa';
    }

    limparHTML(texto) {
        return texto.replace(/<[^>]*>/g, '');
    }

    // ==================== CONTROLE DE NOTIFICAÇÕES ====================
    limparTodasNotificacoes() {
        const container = document.getElementById('toast-container-global');
        if (container) {
            container.innerHTML = '';
        }
        speechSynthesis.cancel();
    }
}

// Inicializar globalmente
const notificacoesGlobal = new SistemaNotificacoesGlobal();