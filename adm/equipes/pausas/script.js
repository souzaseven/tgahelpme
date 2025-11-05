// script.js

// PRIMEIRO: Definir o ToastManager ANTES de tudo
class ToastManager {
    constructor() {
        this.container = null;
        this.toasts = new Set();
        this.initializeContainer();
    }

    initializeContainer() {
        this.container = document.getElementById('toast-container');
        
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'toast-container';
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
    }

    show(message, type = 'info', duration = 4000) {
        if (!this.container) {
            this.initializeContainer();
        }

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        const icons = {
            success: 'fas fa-check-circle',
            warning: 'fas fa-exclamation-triangle',
            danger: 'fas fa-times-circle',
            info: 'fas fa-info-circle'
        };

        toast.innerHTML = `
            <div class="toast-icon">
                <i class="${icons[type]}"></i>
            </div>
            <div class="toast-content">
                <div class="toast-title">${this.getTitle(type)}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" onclick="toastManager.hide(this.parentElement)">
                <i class="fas fa-times"></i>
            </button>
        `;

        this.container.appendChild(toast);
        
        setTimeout(() => toast.classList.add('show'), 10);
        
        if (duration > 0) {
            setTimeout(() => {
                this.hide(toast);
            }, duration);
        }

        this.toasts.add(toast);
        return toast;
    }

    hide(toast) {
        if (!toast || !toast.parentElement) return;
        
        toast.classList.remove('show');
        toast.classList.add('hide');
        
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
            this.toasts.delete(toast);
        }, 300);
    }

    getTitle(type) {
        const titles = {
            success: 'Sucesso!',
            warning: 'Atenção!',
            danger: 'Erro!',
            info: 'Informação'
        };
        return titles[type];
    }

    clearAll() {
        this.toasts.forEach(toast => this.hide(toast));
    }
}

// SEGUNDO: Inicializar o gerenciador de toasts
const toastManager = new ToastManager();

// TERCEIRO: Funções auxiliares para notificações específicas
function notificarEntradaPausa(nome) {
    toastManager.show(`${nome} entrou na pausa`, 'success', 5000);
}

function notificarSaidaPausa(nome) {
    toastManager.show(`${nome} saiu da pausa`, 'info', 5000);
}

function notificarEntradaEspera(nome) {
    toastManager.show(`${nome} entrou na lista de espera`, 'warning', 5000);
}

function notificarSaidaEspera(nome) {
    toastManager.show(`${nome} saiu da lista de espera`, 'info', 5000);
}

function notificarTempoExpirado(nome) {
    toastManager.show(`${nome} excedeu o tempo de pausa!`, 'danger', 8000);
}

function notificarErro(mensagem) {
    toastManager.show(mensagem, 'danger', 6000);
}

function notificarAcessoNegado() {
    toastManager.show('Acesso negado! Nome não encontrado na lista.', 'danger', 6000);
}

function notificarSemVagas() {
    toastManager.show('Não há vagas disponíveis no momento', 'warning', 5000);
}

function notificarVagaDisponivel(nome) {
    toastManager.show(`${nome}, há vaga disponível! Clique em "Decidir" para entrar.`, 'info', 5000);
}

// QUARTO: Sistema de Autenticação
class SistemaAutenticacao {
    constructor() {
        this.usuarioAtual = null;
        this.usuarioADM = 'adm';
        this.primeirosNomes = [
            "Anderson", "Antônio", "Carlos", "Daniel", "Heitor", 
            "Igor", "Jesse", "Jessica", "Lucas", "Moisés", 
            "Pablo", "Suzana", "Uanderson"
        ];
    }

    salvarUsuario(nome) {
        localStorage.setItem('usuarioPausa', nome);
        this.usuarioAtual = nome;
    }

    carregarUsuario() {
        const usuario = localStorage.getItem('usuarioPausa');
        if (usuario) {
            this.usuarioAtual = usuario;
        }
        return usuario;
    }

    limparUsuario() {
        localStorage.removeItem('usuarioPausa');
        this.usuarioAtual = null;
    }

    verificarPermissao(nome) {
        if (this.usuarioAtual === this.usuarioADM) {
            return true;
        }

        const primeiroNomeUsuario = this.usuarioAtual?.split(' ')[0]?.toLowerCase();
        const primeiroNomePessoa = nome.split(' ')[0]?.toLowerCase();
        
        return primeiroNomeUsuario === primeiroNomePessoa;
    }

    verificarNomeValido(nome) {
        const primeiroNome = nome.split(' ')[0]?.toLowerCase();
        return this.primeirosNomes.map(n => n.toLowerCase()).includes(primeiroNome) || 
               nome.toLowerCase() === this.usuarioADM;
    }

    mostrarModalLogin() {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'modal-overlay';
            modal.innerHTML = `
                <div class="modal-login">
                    <div class="modal-header">
                        <h3><i class="fas fa-user"></i> Identificação</h3>
                    </div>
                    <div class="modal-body">
                        <p>Digite seu primeiro nome para acessar o sistema:</p>
                        <input type="text" id="nome-usuario" placeholder="Ex: Anderson, ou anderson..." class="input-login">
                        <div class="modal-nomes">
                            <strong>Nomes permitidos:</strong>
                            <div class="lista-nomes">${this.primeirosNomes.join(', ')}</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button id="btn-entrar" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Entrar
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            const input = document.getElementById('nome-usuario');
            const btnEntrar = document.getElementById('btn-entrar');

            const entrar = () => {
                const nome = input.value.trim();
                if (nome && this.verificarNomeValido(nome)) {
                    this.salvarUsuario(nome);
                    document.body.removeChild(modal);
                    this.mostrarUsuarioAtual();
                    resolve(true);
                } else {
                    toastManager.show('Nome inválido! Verifique a lista de nomes permitidos.', 'danger', 5000);
                    input.focus();
                }
            };

            btnEntrar.addEventListener('click', entrar);
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') entrar();
            });

            input.focus();
        });
    }

    mostrarUsuarioAtual() {
        const header = document.querySelector('header');
        const userInfo = document.createElement('div');
        userInfo.className = 'user-info';
        userInfo.innerHTML = `
            <div class="user-status">
                <i class="fas fa-user"></i>
                <span>${this.usuarioAtual} ${this.usuarioAtual === this.usuarioADM ? '(ADMIN)' : ''}</span>
                <button onclick="sistemaAutenticacao.trocarUsuario()" class="btn-trocar-usuario">
                    <i class="fas fa-sync-alt"></i> Trocar
                </button>
            </div>
        `;
        
        const existingUserInfo = header.querySelector('.user-info');
        if (existingUserInfo) {
            header.removeChild(existingUserInfo);
        }
        header.appendChild(userInfo);
    }

    trocarUsuario() {
        this.limparUsuario();
        this.iniciarAutenticacao();
    }

    async iniciarAutenticacao() {
        const usuarioSalvo = this.carregarUsuario();
        if (!usuarioSalvo) {
            await this.mostrarModalLogin();
        } else {
            this.mostrarUsuarioAtual();
        }
        return this.usuarioAtual;
    }
}

// QUINTO: Inicializar sistema de autenticação
const sistemaAutenticacao = new SistemaAutenticacao();

// SEXTO: Classe ControlePausa
class ControlePausa {
    constructor() {
        this.apiUrl = 'controle_pausa.php';
        this.estado = [];
        this.expirados = [];
        this.intervalo = null;
        this.ultimaAtualizacao = null;
        this.pessoasComModalAberto = new Set();
        this.carregandoEstado = false;
    }

    async chamarAPI(acao, dados = {}) {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ acao, ...dados })
            });
            
            if (!response.ok) {
                console.error('Erro HTTP:', response.status);
                throw new Error(`Erro na requisição: ${response.status}`);
            }
            
            const resultado = await response.json();
            console.log('Resposta API:', acao, resultado);
            return resultado;
            
        } catch (error) {
            console.error('Erro na chamada API:', error);
            this.atualizarStatusSync('Erro de conexão', 'error');
            return { success: false, error: error.message };
        }
    }

    async carregarEstado() {
        if (this.carregandoEstado) {
            console.log('Já está carregando estado...');
            return;
        }

        this.carregandoEstado = true;
        
        try {
            console.log('Iniciando carregamento do estado...');
            const resultado = await this.chamarAPI('get_estado');
            
            if (resultado.success !== false) {
                this.estado = resultado.estado || [];
                this.expirados = resultado.expirados || [];
                this.ultimaAtualizacao = new Date();
                this.atualizarStatusSync('Sincronizado', 'success');
                
                console.log('Estado carregado:', this.estado.length, 'participantes');
                
                this.atualizarInterface();
            } else {
                console.error('Erro no carregamento:', resultado.error);
                this.atualizarStatusSync('Erro ao carregar', 'error');
            }
        } catch (error) {
            console.error('Erro no carregarEstado:', error);
            this.atualizarStatusSync('Erro de sincronização', 'error');
        } finally {
            this.carregandoEstado = false;
        }
    }

    async perguntarEntradaPausa(nome) {
        if (!sistemaAutenticacao.verificarPermissao(nome)) {
            console.log('Sem permissão para:', nome);
            this.pessoasComModalAberto.delete(nome);
            return;
        }

        console.log('Mostrando modal de decisão para:', nome);

        // Verificar se é o primeiro da fila para mostrar opção "Ficar como Segundo"
        const pessoasNaEspera = this.estado.filter(p => p.status === 'espera')
            .sort((a, b) => new Date(a.inicio_espera) - new Date(b.inicio_espera));
        
        const ehPrimeiro = pessoasNaEspera.length > 0 && pessoasNaEspera[0].nome === nome;
        const temMaisDeUmaPessoa = pessoasNaEspera.length > 1;

        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'modal-overlay';
            modal.innerHTML = `
                <div class="modal-login">
                    <div class="modal-header">
                        <h3><i class="fas fa-question-circle"></i> Opções da Fila</h3>
                        <button class="btn-fechar-modal" id="btn-fechar-modal">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p><strong>${nome}</strong>, você está na fila de espera.</p>
                        ${ehPrimeiro ? `<p style="font-size: 0.9rem; color: var(--success); margin-top: 5px;">
                            <i class="fas fa-crown"></i> Você é o <strong>primeiro</strong> da fila!
                        </p>` : ''}
                        <p style="font-size: 0.9rem; color: var(--gray); margin-top: 10px;">
                            O que você gostaria de fazer?
                        </p>
                    </div>
                    <div class="modal-footer" style="display: flex; flex-direction: column; gap: 10px; justify-content: center;">
                        <button id="btn-entrar-agora" class="btn btn-success">
                            <i class="fas fa-sign-in-alt"></i> Entrar na Pausa (se houver vaga)
                        </button>
                        ${ehPrimeiro && temMaisDeUmaPessoa ? `
                        <button id="btn-segunda-posicao" class="btn btn-info">
                            <i class="fas fa-arrow-down"></i> Ficar como Segundo
                        </button>
                        ` : ''}
                        <button id="btn-ultima-posicao" class="btn btn-secondary">
                            <i class="fas fa-arrow-to-bottom"></i> Ir para o Final da Fila
                        </button>
                        <button id="btn-sair-fila" class="btn btn-danger">
                            <i class="fas fa-times"></i> Sair da Fila de Espera
                        </button>
                        <button id="btn-fechar" class="btn btn-outline">
                            <i class="fas fa-times"></i> Fechar (Não fazer nada)
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            const btnEntrarAgora = document.getElementById('btn-entrar-agora');
            const btnSegundaPosicao = document.getElementById('btn-segunda-posicao');
            const btnUltimaPosicao = document.getElementById('btn-ultima-posicao');
            const btnSairFila = document.getElementById('btn-sair-fila');
            const btnFechar = document.getElementById('btn-fechar');
            const btnFecharModal = document.getElementById('btn-fechar-modal');

            const cleanup = () => {
                if (modal.parentElement) {
                    document.body.removeChild(modal);
                }
                this.pessoasComModalAberto.delete(nome);
            };

            const decidir = async (opcao) => {
                cleanup();
                
                switch (opcao) {
                    case 'entrar_agora':
                        console.log(`${nome} escolheu tentar entrar agora`);
                        await this.tentarEntrarNaPausa(nome);
                        break;
                    case 'segunda_posicao':
                        console.log(`${nome} escolheu ficar como segundo`);
                        await this.ficarComoSegundo(nome);
                        break;
                    case 'ultima_posicao':
                        console.log(`${nome} escolheu última posição`);
                        await this.moverParaUltimaPosicao(nome);
                        break;
                    case 'sair_fila':
                        console.log(`${nome} escolheu sair da fila`);
                        await this.sairDaEspera(nome);
                        break;
                    case 'fechar':
                        console.log(`${nome} fechou o modal sem fazer nada`);
                        toastManager.show('Modal fechado. Você continua na mesma posição.', 'info', 3000);
                        break;
                }
                resolve(opcao);
            };

            btnEntrarAgora.addEventListener('click', () => decidir('entrar_agora'));
            if (btnSegundaPosicao) {
                btnSegundaPosicao.addEventListener('click', () => decidir('segunda_posicao'));
            }
            btnUltimaPosicao.addEventListener('click', () => decidir('ultima_posicao'));
            btnSairFila.addEventListener('click', () => decidir('sair_fila'));
            btnFechar.addEventListener('click', () => decidir('fechar'));
            btnFecharModal.addEventListener('click', () => decidir('fechar'));

            // Fechar modal ao clicar fora
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    decidir('fechar');
                }
            });

            // Fechar com ESC
            const fecharComESC = (e) => {
                if (e.key === 'Escape') {
                    decidir('fechar');
                    document.removeEventListener('keydown', fecharComESC);
                }
            };
            document.addEventListener('keydown', fecharComESC);
        });
    }

    async tentarEntrarNaPausa(nome) {
        this.atualizarStatusSync('Verificando vaga...', 'loading');
        
        // Verificar se há vaga disponível
        const vagasDisponiveis = 2 - this.estado.filter(p => p.status === 'pausa').length;
        
        if (vagasDisponiveis > 0) {
            // Tem vaga, tentar entrar
            const resultado = await this.chamarAPI('entrar_na_pausa_agora', { nome });
            
            if (resultado.success) {
                notificarEntradaPausa(nome);
                await this.carregarEstado();
                return true;
            } else {
                notificarErro(resultado.message || 'Erro ao entrar na pausa');
                return false;
            }
        } else {
            // Não tem vaga
            notificarSemVagas();
            
            // Mostrar posição atual na fila
            const pessoasNaEspera = this.estado.filter(p => p.status === 'espera')
                .sort((a, b) => new Date(a.inicio_espera) - new Date(b.inicio_espera));
            const posicao = pessoasNaEspera.findIndex(p => p.nome === nome) + 1;
            
            if (posicao > 0) {
                toastManager.show(`Você está na posição ${posicao}º da fila. Aguarde uma vaga.`, 'info', 4000);
            }
            
            return false;
        }
    }

    async ficarComoSegundo(nome) {
        this.atualizarStatusSync('Movendo para segunda posição...', 'loading');
        const resultado = await this.chamarAPI('ficar_segundo', { nome });
        
        if (resultado.success) {
            toastManager.show(`${nome} agora é o segundo da fila`, 'info', 3000);
            await this.carregarEstado();
        } else {
            notificarErro(resultado.message || 'Erro ao alterar posição na fila');
        }
        
        return resultado.success;
    }

    async entrarNaPausa(nome) {
        if (!sistemaAutenticacao.verificarPermissao(nome)) {
            notificarAcessoNegado();
            return false;
        }

        // Verificar se já está na fila
        const pessoa = this.estado.find(p => p.nome === nome);
        if (pessoa && pessoa.status !== 'disponivel') {
            // Se já está na espera, mostra modal de decisão
            if (pessoa.status === 'espera') {
                this.perguntarEntradaPausa(nome);
                return true;
            } else {
                notificarErro(`${nome} já está na ${pessoa.status === 'pausa' ? 'pausa' : 'lista de espera'}`);
                return false;
            }
        }

        // Se está disponível, vai para a espera
        return await this.entrarNaEspera(nome);
    }

    async entrarNaEspera(nome) {
        this.atualizarStatusSync('Processando...', 'loading');
        const resultado = await this.chamarAPI('entrar_espera', { nome });
        
        if (resultado.success) {
            notificarEntradaEspera(nome);
            
            // Mostrar posição na fila
            const pessoasNaEspera = this.estado.filter(p => p.status === 'espera')
                .sort((a, b) => new Date(a.inicio_espera) - new Date(b.inicio_espera));
            const posicao = pessoasNaEspera.findIndex(p => p.nome === nome) + 1;
            
            if (posicao > 0) {
                toastManager.show(`Você entrou na posição ${posicao}º da fila. Clique em "Decidir" para ver opções.`, 'info', 5000);
            }
            
            await this.carregarEstado();
            return true;
        } else {
            notificarErro(resultado.message || 'Erro ao entrar na lista de espera');
            return false;
        }
    }

    async moverParaUltimaPosicao(nome) {
        this.atualizarStatusSync('Processando...', 'loading');
        const resultado = await this.chamarAPI('mover_ultima_posicao', { nome });
        
        if (resultado.success) {
            toastManager.show(`${nome} foi para o final da fila`, 'info', 3000);
            await this.carregarEstado();
        } else {
            notificarErro('Erro ao alterar posição na fila');
        }
        
        return resultado.success;
    }

    async sairDaPausa(nome) {
        if (!sistemaAutenticacao.verificarPermissao(nome)) {
            notificarAcessoNegado();
            return false;
        }

        this.atualizarStatusSync('Processando...', 'loading');
        const resultado = await this.chamarAPI('sair_pausa', { nome });
        
        if (resultado.success) {
            notificarSaidaPausa(nome);
            await this.carregarEstado();
        } else {
            notificarErro('Erro ao sair da pausa');
        }
        
        return resultado.success;
    }

    async sairDaEspera(nome) {
        if (!sistemaAutenticacao.verificarPermissao(nome)) {
            notificarAcessoNegado();
            return false;
        }

        this.atualizarStatusSync('Processando...', 'loading');
        const resultado = await this.chamarAPI('sair_espera', { nome });
        
        if (resultado.success) {
            notificarSaidaEspera(nome);
            await this.carregarEstado();
        } else {
            notificarErro('Erro ao sair da espera');
        }
        
        return resultado.success;
    }

    async decidirSobreVaga(nome) {
        if (!sistemaAutenticacao.verificarPermissao(nome)) {
            notificarAcessoNegado();
            return false;
        }

        // Verificar se a pessoa está na espera
        const pessoa = this.estado.find(p => p.nome === nome);
        if (!pessoa || pessoa.status !== 'espera') {
            notificarErro('Você não está na lista de espera');
            return false;
        }

        // Mostrar modal de decisão
        this.perguntarEntradaPausa(nome);
        return true;
    }

    atualizarStatusSync(mensagem, tipo = 'info') {
        const elemento = document.getElementById('sync-status');
        if (elemento) {
            elemento.textContent = mensagem;
            elemento.className = '';
            if (tipo === 'success') elemento.style.color = '#00ff88';
            else if (tipo === 'error') elemento.style.color = '#ff4444';
            else if (tipo === 'loading') {
                elemento.innerHTML = `<span class="loading"></span> ${mensagem}`;
            } else {
                elemento.style.color = '#8b949e';
            }
        }
    }

    atualizarInterface() {
        try {
            console.log('Atualizando interface...');
            this.atualizarListaPausa();
            this.atualizarListaEspera();
            this.atualizarParticipantes();
            this.atualizarContadores();
            console.log('Interface atualizada');
        } catch (error) {
            console.error('Erro ao atualizar interface:', error);
        }
    }

    atualizarListaPausa() {
        const container = document.getElementById('pausa-lista');
        if (!container) {
            console.error('Container pausa-lista não encontrado');
            return;
        }

        const naPausa = this.estado.filter(p => p.status === 'pausa');
        
        if (naPausa.length === 0) {
            container.innerHTML = `
                <div class="lista-vazia">
                    <i class="fas fa-mug-hot" style="font-size: 2.5rem; margin-bottom: 15px; opacity: 0.5;"></i>
                    <div>Nenhuma pessoa na pausa no momento</div>
                </div>
            `;
            return;
        }

        container.innerHTML = naPausa.map(pessoa => {
            return `
                <div class="item">
                    <div class="item-info">
                        <div class="item-nome">${pessoa.nome}</div>
                        <div class="item-status">
                            <span class="badge ativo">EM PAUSA</span>
                        </div>
                    </div>
                    ${sistemaAutenticacao.verificarPermissao(pessoa.nome) ? `
                        <button class="btn btn-sair" onclick="controle.sairDaPausa('${pessoa.nome}')">
                            <i class="fas fa-sign-out-alt"></i> Finalizar Pausa
                        </button>
                    ` : ''}
                </div>
            `;
        }).join('');
    }

    atualizarListaEspera() {
        const container = document.getElementById('lista-espera');
        if (!container) {
            console.error('Container lista-espera não encontrado');
            return;
        }

        const naEspera = this.estado.filter(p => p.status === 'espera')
            .sort((a, b) => new Date(a.inicio_espera) - new Date(b.inicio_espera));
        
        if (naEspera.length === 0) {
            container.innerHTML = `
                <div class="lista-vazia">
                    <i class="fas fa-clipboard-list" style="font-size: 2.5rem; margin-bottom: 15px; opacity: 0.5;"></i>
                    <div>Nenhuma pessoa na lista de espera</div>
                </div>
            `;
            return;
        }

        container.innerHTML = naEspera.map((pessoa, index) => {
            const posicao = index + 1;
            return `
                <div class="item espera">
                    <div class="item-info">
                        <div class="item-nome">${pessoa.nome}</div>
                        <div class="item-status">
                            <span class="badge espera">POSIÇÃO ${posicao}º</span>
                        </div>
                    </div>
                    ${sistemaAutenticacao.verificarPermissao(pessoa.nome) ? `
                        <button class="btn btn-decidir" onclick="controle.decidirSobreVaga('${pessoa.nome}')">
                            <i class="fas fa-question-circle"></i> Decidir
                        </button>
                    ` : ''}
                </div>
            `;
        }).join('');
    }

    atualizarParticipantes() {
        const container = document.getElementById('participantes-lista');
        if (!container) {
            console.error('Container participantes-lista não encontrado');
            return;
        }
        
        container.innerHTML = this.estado.map(pessoa => {
            const classeStatus = `status-${pessoa.status}`;
            const textoStatus = {
                'disponivel': 'Disponível',
                'pausa': 'Em Pausa',
                'espera': 'Na Espera'
            }[pessoa.status];
            
            const temPermissao = sistemaAutenticacao.verificarPermissao(pessoa.nome);
            
            let botoes = '';
            if (temPermissao) {
                if (pessoa.status === 'disponivel') {
                    botoes = `
                        <button class="btn btn-entrar" onclick="controle.entrarNaPausa('${pessoa.nome}')">
                            <i class="fas fa-sign-in-alt"></i> Entrar na Fila de Espera
                        </button>
                    `;
                } else if (pessoa.status === 'pausa') {
                    botoes = `
                        <button class="btn btn-sair" onclick="controle.sairDaPausa('${pessoa.nome}')">
                            <i class="fas fa-sign-out-alt"></i> Finalizar Pausa
                        </button>
                    `;
                } else if (pessoa.status === 'espera') {
                    botoes = `
                        <button class="btn btn-decidir" onclick="controle.decidirSobreVaga('${pessoa.nome}')">
                            <i class="fas fa-question-circle"></i> Decidir sobre Vaga
                        </button>
                    `;
                }
            } else {
                botoes = `
                    <button class="btn btn-bloqueado" disabled>
                        <i class="fas fa-lock"></i> Acesso Restrito
                    </button>
                `;
            }

            return `
                <div class="participante ${pessoa.status}">
                    <div class="participante-header">
                        <div class="participante-nome">${pessoa.nome}</div>
                        <div class="participante-status ${classeStatus}">${textoStatus}</div>
                    </div>
                    ${botoes}
                </div>
            `;
        }).join('');
    }

    atualizarContadores() {
        const naPausa = this.estado.filter(p => p.status === 'pausa').length;
        const naEspera = this.estado.filter(p => p.status === 'espera').length;
        
        const contadorPausa = document.getElementById('contador-pausa');
        const contadorEspera = document.getElementById('contador-espera');
        
        if (contadorPausa) contadorPausa.textContent = naPausa;
        if (contadorEspera) contadorEspera.textContent = naEspera;
    }

    iniciarMonitoramento() {
        this.carregarEstado();
        
        this.intervalo = setInterval(() => {
            this.carregarEstado();
        }, 3000);
    }

    destruir() {
        if (this.intervalo) {
            clearInterval(this.intervalo);
        }
    }
}

// SÉTIMO: Inicializar o sistema
const controle = new ControlePausa();

// OITAVO: Configurar event listeners
document.addEventListener('DOMContentLoaded', async () => {
    console.log('DOM carregado, iniciando autenticação...');
    await sistemaAutenticacao.iniciarAutenticacao();
    controle.iniciarMonitoramento();
});

window.addEventListener('beforeunload', () => {
    controle.destruir();
});