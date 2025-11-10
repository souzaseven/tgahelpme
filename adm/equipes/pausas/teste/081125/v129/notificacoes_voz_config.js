// notificacoes_voz_config.js - Sistema completo de voz e controles para notificações

class SistemaVozNotificacoes {
    constructor() {
        this.vozGenero = 'feminina'; // feminina, masculina, mudo
        this.notificacaoAtiva = true;
        this.audioAtivo = true;
        this.vozesCarregadas = false;
        this.vozesDisponiveis = [];
        
        this.inicializar();
    }

    inicializar() {
        console.log('🔊 INICIANDO SISTEMA DE VOZ E CONTROLES...');
        
        // Carregar vozes disponíveis
        this.carregarVozes();
        
        // Carregar configurações salvas
        this.carregarConfiguracoes();
        
        // Criar interface de controles
        this.criarInterfaceControles();
        
        console.log('✅ SISTEMA DE VOZ CONFIGURADO');
    }

    carregarVozes() {
        // Tentar carregar vozes imediatamente
        this.vozesDisponiveis = speechSynthesis.getVoices();
        
        if (this.vozesDisponiveis.length > 0) {
            this.vozesCarregadas = true;
            console.log(`🔊 ${this.vozesDisponiveis.length} vozes carregadas`);
        } else {
            // Esperar pelo evento de carregamento
            speechSynthesis.onvoiceschanged = () => {
                this.vozesDisponiveis = speechSynthesis.getVoices();
                this.vozesCarregadas = true;
                console.log(`🔊 ${this.vozesDisponiveis.length} vozes carregadas`);
            };
        }
    }

    carregarConfiguracoes() {
        // Carregar do localStorage se existir
        const vozSalva = localStorage.getItem('vozGenero');
        const notificacaoSalva = localStorage.getItem('notificacaoAtiva');
        const audioSalvo = localStorage.getItem('audioAtivo');
        
        if (vozSalva) this.vozGenero = vozSalva;
        if (notificacaoSalva !== null) this.notificacaoAtiva = notificacaoSalva === 'true';
        if (audioSalvo !== null) this.audioAtivo = audioSalvo === 'true';
        
        console.log('⚙️ Configurações carregadas:', {
            voz: this.vozGenero,
            notificacao: this.notificacaoAtiva,
            audio: this.audioAtivo
        });
    }

    salvarConfiguracoes() {
        localStorage.setItem('vozGenero', this.vozGenero);
        localStorage.setItem('notificacaoAtiva', this.notificacaoAtiva);
        localStorage.setItem('audioAtivo', this.audioAtivo);
    }

    criarInterfaceControles() {
        // Criar container para controles
        const container = document.createElement('div');
        container.id = 'controles-voz';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 15px;
            border-radius: 10px;
            z-index: 9999;
            font-family: Arial, sans-serif;
            font-size: 14px;
            min-width: 200px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
            border: 2px solid #333;
        `;

        container.innerHTML = `
            <div style="margin-bottom: 15px; font-weight: bold; border-bottom: 1px solid #444; padding-bottom: 8px;">
                <i class="fas fa-cog"></i> Configurações de Notificação
            </div>
            
            <div style="margin-bottom: 10px;">
                <button id="toggleNotificacao" class="btn-controle ${!this.notificacaoAtiva ? 'desativado' : ''}">
                    ${this.notificacaoAtiva ? '🔔 Notificação: Ativada' : '🔕 Notificação: Desativada'}
                </button>
            </div>
            
            <div style="margin-bottom: 10px;">
                <button id="toggleAudio" class="btn-controle ${!this.audioAtivo ? 'desativado' : ''}">
                    ${this.audioAtivo ? '🔊 Áudio: Ativado' : '🔇 Áudio: Desativado'}
                </button>
            </div>
            
            <div style="margin-bottom: 5px;">
                <button id="toggleVoz" class="btn-controle ${this.vozGenero === 'mudo' ? 'desativado' : ''}">
                    ${this.getTextoBotaoVoz()}
                </button>
            </div>
            
            <div style="font-size: 12px; color: #888; margin-top: 10px; border-top: 1px solid #444; padding-top: 8px;">
                <i class="fas fa-info-circle"></i> Clique para alternar
            </div>
        `;

        document.body.appendChild(container);

        // Adicionar estilos CSS
        this.adicionarEstilosCSS();

        // Configurar eventos dos botões
        this.configurarEventos();
        
        // Tornar arrastável
        this.tornarArrastavel(container);
    }

    adicionarEstilosCSS() {
        const style = document.createElement('style');
        style.textContent = `
            .btn-controle {
                background: #007bff;
                color: white;
                border: none;
                padding: 8px 12px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 12px;
                width: 100%;
                transition: all 0.3s ease;
                text-align: left;
            }
            
            .btn-controle:hover {
                background: #0056b3;
                transform: translateY(-1px);
            }
            
            .btn-controle.desativado {
                background: #6c757d;
            }
            
            .btn-controle.desativado:hover {
                background: #545b62;
            }
            
            #controles-voz {
                user-select: none;
            }
            
            #controles-voz.dragging {
                opacity: 0.8;
            }
        `;
        document.head.appendChild(style);
    }

    configurarEventos() {
        const toggleNotificacao = document.getElementById('toggleNotificacao');
        const toggleAudio = document.getElementById('toggleAudio');
        const toggleVoz = document.getElementById('toggleVoz');

        // Alternar notificação Windows
        toggleNotificacao.addEventListener('click', () => {
            this.notificacaoAtiva = !this.notificacaoAtiva;
            toggleNotificacao.textContent = this.notificacaoAtiva 
                ? '🔔 Notificação: Ativada' 
                : '🔕 Notificação: Desativada';
            toggleNotificacao.classList.toggle('desativado', !this.notificacaoAtiva);
            this.salvarConfiguracoes();
            console.log(`🔔 Notificação ${this.notificacaoAtiva ? 'ativada' : 'desativada'}`);
        });

        // Alternar áudio
        toggleAudio.addEventListener('click', () => {
            this.audioAtivo = !this.audioAtivo;
            toggleAudio.textContent = this.audioAtivo 
                ? '🔊 Áudio: Ativado' 
                : '🔇 Áudio: Desativado';
            toggleAudio.classList.toggle('desativado', !this.audioAtivo);
            this.salvarConfiguracoes();
            console.log(`🔊 Áudio ${this.audioAtivo ? 'ativado' : 'desativado'}`);
        });

        // Alternar voz
        toggleVoz.addEventListener('click', () => {
            this.alternarVoz();
            toggleVoz.textContent = this.getTextoBotaoVoz();
            toggleVoz.classList.toggle('desativado', this.vozGenero === 'mudo');
            this.salvarConfiguracoes();
            console.log(`🎙️ Voz alterada para: ${this.vozGenero}`);
        });
    }

    alternarVoz() {
        const opcoes = ['feminina', 'masculina', 'mudo'];
        const indexAtual = opcoes.indexOf(this.vozGenero);
        this.vozGenero = opcoes[(indexAtual + 1) % opcoes.length];
    }

    getTextoBotaoVoz() {
        switch (this.vozGenero) {
            case 'feminina':
                return '👩 Voz: Feminina';
            case 'masculina':
                return '👨 Voz: Masculina';
            case 'mudo':
                return '🔇 Voz: Mudo';
            default:
                return '👩 Voz: Feminina';
        }
    }

    tornarArrastavel(elemento) {
        let pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
        
        elemento.onmousedown = arrastarMouseDown;

        function arrastarMouseDown(e) {
            e = e || window.event;
            e.preventDefault();
            pos3 = e.clientX;
            pos4 = e.clientY;
            document.onmouseup = fecharArrastarElemento;
            document.onmousemove = elementoArrastado;
            elemento.classList.add('dragging');
        }

        function elementoArrastado(e) {
            e = e || window.event;
            e.preventDefault();
            pos1 = pos3 - e.clientX;
            pos2 = pos4 - e.clientY;
            pos3 = e.clientX;
            pos4 = e.clientY;
            elemento.style.top = (elemento.offsetTop - pos2) + "px";
            elemento.style.left = (elemento.offsetLeft - pos1) + "px";
        }

        function fecharArrastarElemento() {
            document.onmouseup = null;
            document.onmousemove = null;
            elemento.classList.remove('dragging');
        }
    }

    // ==================== SISTEMA DE VOZ ====================

    obterVozPorGenero() {
        if (!this.vozesCarregadas || this.vozesDisponiveis.length === 0) {
            console.warn('⚠️ Nenhuma voz disponível');
            return null;
        }

        if (this.vozGenero === 'mudo') {
            return null;
        }

        // Tentar encontrar voz específica por gênero em português
        if (this.vozGenero === 'feminina') {
            return (
                this.vozesDisponiveis.find(v => 
                    v.lang.startsWith("pt") && 
                    (/female|feminina|brasil|portuguesa|victoria|luciana/i.test(v.name))
                ) ||
                this.vozesDisponiveis.find(v => v.lang.startsWith("pt"))
            );
        } else { // masculina
            return (
                this.vozesDisponiveis.find(v => 
                    v.lang.startsWith("pt") && 
                    (/male|masculino|brasil|português|daniel|joão/i.test(v.name))
                ) ||
                this.vozesDisponiveis.find(v => v.lang.startsWith("pt"))
            );
        }
    }

    falarNotificacao(texto = "Notificação do sistema") {
        // Verificar se áudio está ativo e não está mudo
        if (!this.audioAtivo || this.vozGenero === 'mudo') {
            return;
        }

        try {
            const mensagem = new SpeechSynthesisUtterance(texto);
            mensagem.lang = "pt-BR";
            mensagem.rate = 1.0;
            mensagem.pitch = 1.0;
            mensagem.volume = 0.8;

            // Obter voz selecionada
            const voz = this.obterVozPorGenero();
            if (voz) {
                mensagem.voice = voz;
            }

            // Parar falas anteriores
            speechSynthesis.cancel();
            
            // Falar a mensagem
            speechSynthesis.speak(mensagem);
            
            console.log('🔊 Falando notificação:', texto);

        } catch (error) {
            console.error('❌ Erro ao falar notificação:', error);
        }
    }

    // ==================== NOTIFICAÇÕES WINDOWS ====================

    mostrarNotificacaoWindows(titulo, mensagem) {
        if (!this.notificacaoAtiva) {
            return;
        }

        if ("Notification" in window) {
            if (Notification.permission === "granted") {
                new Notification(titulo, {
                    body: mensagem,
                    icon: '/favicon.ico',
                    badge: '/favicon.ico',
                    requireInteraction: true,
                });
            } else if (Notification.permission !== "denied") {
                Notification.requestPermission().then(permission => {
                    if (permission === "granted") {
                        new Notification(titulo, {
                            body: mensagem,
                            icon: '/favicon.ico'
                        });
                    }
                });
            }
        }
    }

    // ==================== UTILITÁRIOS ====================

    testarVoz() {
        const textoTeste = "Teste de voz do sistema de notificações";
        this.falarNotificacao(textoTeste);
    }

    // ==================== STATUS DO SISTEMA ====================

    getStatus() {
        return {
            vozGenero: this.vozGenero,
            notificacaoAtiva: this.notificacaoAtiva,
            audioAtivo: this.audioAtivo,
            vozesCarregadas: this.vozesCarregadas,
            totalVozes: this.vozesDisponiveis.length
        };
    }
}

// Inicializar sistema de voz global
const sistemaVoz = new SistemaVozNotificacoes();