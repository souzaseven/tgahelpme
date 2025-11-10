// notificacoes_tempo_real_atualizado.js - Sistema de notificações em tempo real com VOZ

class NotificacoesTempoReal {
    constructor() {
        this.ultimaNotificacao = null;
        this.intervaloNotificacoes = null;
        this.inicializado = false;
        this.contadorFalhas = 0;
        this.maxFalhas = 5;
        this.notificacoesProcessadas = new Set();
        this.apiUrl = 'controle_pausa.php';
    }

    inicializar() {
        if (this.inicializado) {
            console.log('🔔 Sistema de notificações já inicializado');
            return;
        }
        
        console.log('🔄 INICIANDO SISTEMA DE NOTIFICAÇÕES EM TEMPO REAL...');
        
        // Verificar mais frequentemente (2 segundos)
        this.intervaloNotificacoes = setInterval(() => {
            this.verificarNotificacoes();
        }, 2000);
        
        // Verificar imediatamente
        setTimeout(() => this.verificarNotificacoes(), 500);
        
        this.inicializado = true;
        console.log('✅ SISTEMA DE NOTIFICAÇÕES EM TEMPO REAL INICIALIZADO');
    }

    async verificarNotificacoes() {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    acao: 'get_notificacoes',
                    origem: 'sistema_notificacoes',
                    timestamp: Date.now(),
                    ultima_notificacao: this.ultimaNotificacao
                })
            });
            
            if (response.ok) {
                const resultado = await response.json();
                
                if (resultado.success && resultado.notificacoes && resultado.notificacoes.length > 0) {
                    console.log(`🎯 ${resultado.notificacoes.length} NOTIFICAÇÕES NOVAS ENCONTRADAS`);
                    await this.processarNotificacoes(resultado.notificacoes);
                    this.contadorFalhas = 0;
                }
            } else {
                this.contadorFalhas++;
                console.warn('❌ FALHA AO BUSCAR NOTIFICAÇÕES:', response.status);
                
                if (this.contadorFalhas >= this.maxFalhas) {
                    console.error('🚨 MUITAS FALHAS CONSECUTIVAS - REINICIANDO...');
                    this.reinicializar();
                }
            }
        } catch (error) {
            this.contadorFalhas++;
            console.warn('❌ ERRO DE CONEXÃO:', error);
            
            if (this.contadorFalhas >= this.maxFalhas) {
                this.reinicializar();
            }
        }
    }

    async processarNotificacoes(notificacoes) {
        // Ordenar por timestamp (mais recentes primeiro)
        notificacoes.sort((a, b) => new Date(b.criada_em) - new Date(a.criada_em));
        
        for (const notificacao of notificacoes) {
            // Verificar se já processamos esta notificação
            if (this.notificacoesProcessadas.has(notificacao.id)) {
                continue;
            }
            
            console.log('🆕 NOVA NOTIFICAÇÃO:', notificacao);
            
            // Atualizar última notificação processada
            this.ultimaNotificacao = notificacao.id;
            this.notificacoesProcessadas.add(notificacao.id);
            
            // Limitar o tamanho do conjunto para evitar memory leaks
            if (this.notificacoesProcessadas.size > 100) {
                const first = this.notificacoesProcessadas.values().next().value;
                this.notificacoesProcessadas.delete(first);
            }
            
            // Mostrar a notificação
            await this.mostrarNotificacao(notificacao);
            
            // Pequeno delay entre notificações
            await new Promise(resolve => setTimeout(resolve, 100));
        }
    }

    async mostrarNotificacao(notificacao) {
        if (!notificacao.mensagem || notificacao.mensagem.trim() === '') {
            console.error('❌ NOTIFICAÇÃO VAZIA - IGNORANDO');
            return;
        }
        
        console.log('🎯 EXIBINDO NOTIFICAÇÃO:', notificacao.mensagem);
        
        // 1. Usar o sistema global de notificações (se disponível)
        if (typeof notificacoesGlobal !== 'undefined') {
            
            // Mostrar toast
            notificacoesGlobal.mostrarToast(
                notificacao.mensagem, 
                notificacao.tipo || 'info', 
                notificacao.duracao || 5000
            );
            
            // Tocar som apropriado
            this.tocarSomNotificacao(notificacao.tipo);
            
            // Verificar se é uma notificação específica para o usuário logado
            this.verificarNotificacaoEspecifica(notificacao.mensagem);
            
        } else {
            this.mostrarNotificacaoFallback(notificacao);
        }
    }

    tocarSomNotificacao(tipo) {
        if (typeof sonsNotificacoes === 'undefined') {
            return;
        }

        try {
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
        } catch (error) {
            console.error('🔇 Erro ao tocar som:', error);
        }
    }

    verificarNotificacaoEspecifica(mensagem) {
        // Verificar se a notificação é específica para o usuário logado
        if (typeof sistemaAutenticacao !== 'undefined' && sistemaAutenticacao.usuarioAtual) {
            const usuarioLogado = sistemaAutenticacao.usuarioAtual.toLowerCase();
            
            // Verificar se a mensagem contém o nome do usuário logado
            if (mensagem.toLowerCase().includes(usuarioLogado)) {
                console.log('👤 NOTIFICAÇÃO ESPECÍFICA PARA USUÁRIO LOGADO:', usuarioLogado);
                
                // Notificação Windows mais destacada
                if (typeof sistemaVoz !== 'undefined' && sistemaVoz.notificacaoAtiva) {
                    const titulo = '👤 Para Você!';
                    const mensagemLimpa = this.limparHTML(mensagem);
                    sistemaVoz.mostrarNotificacaoWindows(titulo, `Para você: ${mensagemLimpa}`);
                    
                    // Voz personalizada se áudio estiver ativo
                    if (sistemaVoz.audioAtivo && sistemaVoz.vozGenero !== 'mudo') {
                        const primeiroNome = usuarioLogado.split(' ')[0];
                        const mensagemVoz = `Atenção ${primeiroNome}! ${this.limparHTMLParaVoz(mensagem)}`;
                        sistemaVoz.falarNotificacao(mensagemVoz);
                    }
                }
            }
        }
    }

    limparHTML(texto) {
        return texto.replace(/<[^>]*>/g, '');
    }

    limparHTMLParaVoz(texto) {
        return texto.replace(/<[^>]*>/g, '')
                   .replace(/📋|✅|🚪|👋|👑|🔄|⏰|🔔/g, '')
                   .replace(/\s+/g, ' ')
                   .trim();
    }

    mostrarNotificacaoFallback(notificacao) {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #333;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            z-index: 10000;
            max-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            border-left: 4px solid ${this.getCorTipo(notificacao.tipo)};
            animation: slideInRight 0.3s ease-out;
        `;
        
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="${this.getIconeTipo(notificacao.tipo)}" style="font-size: 1.2rem;"></i>
                <div>${notificacao.mensagem}</div>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remover após duração
        const duracao = notificacao.duracao || 5000;
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => notification.remove(), 300);
            }
        }, duracao);
        
        this.adicionarCSSAnimacoes();
    }

    getCorTipo(tipo) {
        const cores = {
            success: '#28a745',
            warning: '#ffc107',
            danger: '#dc3545',
            info: '#17a2b8'
        };
        return cores[tipo] || '#007bff';
    }

    getIconeTipo(tipo) {
        const icones = {
            success: 'fas fa-check-circle',
            warning: 'fas fa-exclamation-triangle',
            danger: 'fas fa-times-circle',
            info: 'fas fa-info-circle'
        };
        return icones[tipo] || 'fas fa-bell';
    }

    adicionarCSSAnimacoes() {
        if (document.getElementById('notificacoes-animacoes-css')) return;
        
        const style = document.createElement('style');
        style.id = 'notificacoes-animacoes-css';
        style.textContent = `
            @keyframes slideInRight {
                from {
                    transform: translateX(400px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(400px);
                    opacity: 0;
                }
            }
        `;
        
        document.head.appendChild(style);
    }

    // ==================== ENVIO DE NOTIFICAÇÕES GLOBAIS ====================
    async enviarNotificacaoGlobal(mensagem, tipo = 'info', duracao = 10000) {
        if (!mensagem || mensagem.trim() === '') {
            console.warn('❌ Tentativa de enviar notificação vazia. Cancelado.');
            return { success: false, error: 'mensagem_vazia' };
        }

        console.log('🌐 ENVIANDO NOTIFICAÇÃO GLOBAL PARA TODAS AS TELAS:', {
            mensagem: mensagem.substring(0, 100) + '...',
            tipo: tipo,
            duracao: duracao
        });

        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    acao: 'enviar_notificacao',
                    mensagem: mensagem,
                    tipo: tipo,
                    duracao: duracao,
                    forcar: true,
                    origem: 'frontend',
                    timestamp: new Date().toISOString()
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const resultado = await response.json();
            
            if (resultado.success) {
                console.log('✅ Notificação global enviada com sucesso:', resultado.id);
                
                // Também mostrar localmente imediatamente
                this.mostrarNotificacao({
                    id: resultado.id,
                    mensagem: mensagem,
                    tipo: tipo,
                    duracao: duracao
                });
                
                return resultado;
            } else {
                console.error('❌ Falha ao enviar notificação global:', resultado.error);
                
                // Fallback: mostrar apenas localmente
                this.mostrarNotificacao({
                    id: 'local-' + Date.now(),
                    mensagem: mensagem,
                    tipo: tipo,
                    duracao: duracao
                });
                
                return { success: false, error: resultado.error };
            }
        } catch (error) {
            console.error('❌ Erro ao enviar notificação global:', error);
            
            // Fallback crítico: mostrar apenas localmente
            this.mostrarNotificacao({
                id: 'local-fallback-' + Date.now(),
                mensagem: mensagem,
                tipo: tipo,
                duracao: duracao
            });
            
            return { success: false, error: error.message };
        }
    }

    // ==================== CONTROLE DE ESTADO ====================
    reinicializar() {
        console.log('🔄 REINICIALIZANDO SISTEMA DE NOTIFICAÇÕES...');
        this.destruir();
        
        setTimeout(() => {
            this.contadorFalhas = 0;
            this.notificacoesProcessadas.clear();
            this.ultimaNotificacao = null;
            this.inicializar();
        }, 5000);
    }

    destruir() {
        if (this.intervaloNotificacoes) {
            clearInterval(this.intervaloNotificacoes);
            this.intervaloNotificacoes = null;
        }
        this.inicializado = false;
    }

    // ==================== STATUS DO SISTEMA ====================
    getStatus() {
        return {
            inicializado: this.inicializado,
            contadorFalhas: this.contadorFalhas,
            ultimaNotificacao: this.ultimaNotificacao,
            notificacoesProcessadas: this.notificacoesProcessadas.size
        };
    }
}

// Inicializar sistema de notificações em tempo real
const notificacoesTempoReal = new NotificacoesTempoReal();

// Adicionar método mostrarToast ao objeto global se não existir
if (typeof notificacoesGlobal !== 'undefined' && !notificacoesGlobal.mostrarToast) {
    notificacoesGlobal.mostrarToast = function(mensagem, tipo = 'info', duracao = 5000) {
        // Implementação simples de toast
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #333;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            z-index: 10000;
            max-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            border-left: 4px solid ${this.getCorTipo(tipo)};
        `;
        toast.innerHTML = mensagem;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, duracao);
    };
    
    notificacoesGlobal.getCorTipo = function(tipo) {
        const cores = {
            success: '#28a745',
            warning: '#ffc107',
            danger: '#dc3545',
            info: '#17a2b8'
        };
        return cores[tipo] || '#007bff';
    };
}