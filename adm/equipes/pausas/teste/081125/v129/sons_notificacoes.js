// sons_notificacoes.js - Sistema de sons para notificações

class SonsNotificacoes {
    constructor() {
        this.sonsCarregados = false;
        this.audioContext = null;
        this.sonsPredefinidos = new Map();
        this.carregarSons();
    }

    carregarSons() {
        try {
            // Criar contexto de áudio
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            this.criarSonsPredefinidos();
            this.sonsCarregados = true;
            console.log('🔊 Sistema de sons inicializado');
        } catch (error) {
            console.warn('🔇 Áudio não suportado:', error);
            this.sonsCarregados = false;
        }
    }

    criarSonsPredefinidos() {
        // Som de notificação normal (entrada/saída)
        this.sonsPredefinidos.set('notificacao', {
            frequencias: [800, 600],
            duracao: 0.3,
            volume: 0.3
        });

        // Som de alerta (troca/tempo excedido)
        this.sonsPredefinidos.set('alerta', {
            frequencias: [1000, 1200, 1000],
            duracao: 0.4,
            volume: 0.4
        });

        // Som de sucesso (entrada na pausa)
        this.sonsPredefinidos.set('sucesso', {
            frequencias: [600, 800, 1000],
            duracao: 0.5,
            volume: 0.3
        });

        // Som de informação (status geral)
        this.sonsPredefinidos.set('info', {
            frequencias: [700, 700],
            duracao: 0.2,
            volume: 0.2
        });

        // Som urgente (tempo excedido crítico)
        this.sonsPredefinidos.set('urgente', {
            frequencias: [1200, 800, 1200, 800],
            duracao: 0.6,
            volume: 0.5
        });
    }

    tocarSom(tipo = 'notificacao') {
        if (!this.sonsCarregados || !this.audioContext) {
            console.log('🔇 Sons não disponíveis');
            return;
        }

        const somConfig = this.sonsPredefinidos.get(tipo) || this.sonsPredefinidos.get('notificacao');

        try {
            const oscillator = this.audioContext.createOscillator();
            const gainNode = this.audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(this.audioContext.destination);

            // Configurar tipo de onda (mais agradável)
            oscillator.type = 'sine';

            // Aplicar frequências sequenciais
            const now = this.audioContext.currentTime;
            somConfig.frequencias.forEach((freq, index) => {
                oscillator.frequency.setValueAtTime(freq, now + (index * 0.1));
            });

            // Configurar volume com fade
            gainNode.gain.setValueAtTime(0, now);
            gainNode.gain.linearRampToValueAtTime(somConfig.volume, now + 0.05);
            gainNode.gain.exponentialRampToValueAtTime(0.01, now + somConfig.duracao);

            oscillator.start(now);
            oscillator.stop(now + somConfig.duracao);

        } catch (error) {
            console.log('🔇 Erro ao tocar som:', error);
        }
    }

    // ==================== SONS ESPECÍFICOS ====================

    tocarSomTroca() {
        this.tocarSom('alerta');
    }

    tocarSomAlerta() {
        this.tocarSom('urgente');
    }

    tocarSomSucesso() {
        this.tocarSom('sucesso');
    }

    tocarSomInfo() {
        this.tocarSom('info');
    }

    tocarSomEntradaPausa() {
        this.tocarSom('sucesso');
    }

    tocarSomSaidaPausa() {
        this.tocarSom('info');
    }

    tocarSomEntradaFila() {
        this.tocarSom('notificacao');
    }

    tocarSomSaidaFila() {
        this.tocarSom('info');
    }

    tocarSomPrimeiroFila() {
        this.tocarSom('sucesso');
    }

    tocarSomTempoExcedido() {
        this.tocarSom('urgente');
    }

    // ==================== CONTROLE DE SONS ====================

    silenciar() {
        if (this.audioContext) {
            this.audioContext.suspend();
        }
    }

    ativar() {
        if (this.audioContext) {
            this.audioContext.resume();
        }
    }

    // ==================== VERIFICAÇÃO DE SUPORTE ====================

    suportaAudio() {
        return this.sonsCarregados && this.audioContext;
    }

    // ==================== SONS PERSONALIZADOS ====================

    tocarSomPersonalizado(frequencias = [440], duracao = 0.5, volume = 0.3) {
        if (!this.sonsCarregados || !this.audioContext) return;

        try {
            const oscillator = this.audioContext.createOscillator();
            const gainNode = this.audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(this.audioContext.destination);

            oscillator.type = 'sine';
            
            const now = this.audioContext.currentTime;
            frequencias.forEach((freq, index) => {
                oscillator.frequency.setValueAtTime(freq, now + (index * 0.1));
            });

            gainNode.gain.setValueAtTime(0, now);
            gainNode.gain.linearRampToValueAtTime(volume, now + 0.05);
            gainNode.gain.exponentialRampToValueAtTime(0.01, now + duracao);

            oscillator.start(now);
            oscillator.stop(now + duracao);

        } catch (error) {
            console.log('🔇 Erro ao tocar som personalizado:', error);
        }
    }
}

// Inicializar sistema de sons globalmente
const sonsNotificacoes = new SonsNotificacoes();