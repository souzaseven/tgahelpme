// script.js - Sistema completo de controle de pausas (COM NOTIFICAÇÕES E VOZ)

// SISTEMA DE AUTENTICAÇÃO
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
                    console.error('Nome inválido! Verifique a lista de nomes permitidos.');
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

// CONTROLE PRINCIPAL DE PAUSAS
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
                
                // Atualizar contadores com novo estado
                if (typeof contadorEspera !== 'undefined') {
                    console.log('Atualizando contador de espera com novo estado...');
                    contadorEspera.atualizarEstado(this.estado);
                }
                
                if (typeof contadorPausa !== 'undefined') {
                    console.log('Atualizando contador de pausa com novo estado...');
                    contadorPausa.atualizarEstado(this.estado);
                }
                
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

    async tentarEntrarNaPausa(nome) {
        this.atualizarStatusSync('Verificando vaga...', 'loading');
        
        const vagasDisponiveis = 2 - this.estado.filter(p => p.status === 'pausa').length;
        
        if (vagasDisponiveis > 0) {
            const resultado = await this.chamarAPI('entrar_na_pausa_agora', { nome });
            
            if (resultado.success) {
                if (typeof window.notificacoesGlobal !== 'undefined') {
                    await window.notificacoesGlobal.notificarEntradaPausa(nome);
                }
                await this.carregarEstado();
                return true;
            } else {
                console.error(`❌ Erro ao entrar na pausa: ${resultado.message}`);
                return false;
            }
        } else {
            console.warn('⚠️ Não há vagas disponíveis no momento');
            
            const pessoasNaEspera = this.estado.filter(p => p.status === 'espera')
                .sort((a, b) => new Date(a.inicio_espera) - new Date(b.inicio_espera));
            const posicao = pessoasNaEspera.findIndex(p => p.nome === nome) + 1;
            
            if (posicao > 0) {
                console.log(`ℹ️ Você está na posição ${posicao}º da fila. Aguarde uma vaga.`);
            }
            
            return false;
        }
    }

    async ficarComoSegundo(nome) {
        this.atualizarStatusSync('Movendo para segunda posição...', 'loading');
        const resultado = await this.chamarAPI('ficar_segundo', { nome });
        
        if (resultado.success) {
            console.log(`ℹ️ ${nome} agora é o segundo da fila`);
            await this.carregarEstado();
        } else {
            console.error(`❌ Erro ao alterar posição na fila: ${resultado.message}`);
        }
        
        return resultado.success;
    }

    async entrarNaPausaAgora(nome) {
        if (!sistemaAutenticacao.verificarPermissao(nome)) {
            console.error('❌ Acesso negado! Nome não encontrado na lista.');
            return false;
        }

        const pessoasNaEspera = this.estado.filter(p => p.status === 'espera')
            .sort((a, b) => new Date(a.inicio_espera) - new Date(b.inicio_espera));
        
        const ehPrimeiro = pessoasNaEspera.length > 0 && pessoasNaEspera[0].nome === nome;
        
        if (!ehPrimeiro) {
            console.error('❌ Apenas o primeiro da fila pode entrar na pausa');
            return false;
        }

        const vagasOcupadas = this.estado.filter(p => p.status === 'pausa').length;
        if (vagasOcupadas >= 2) {
            console.warn('⚠️ Não há vagas disponíveis no momento');
            return false;
        }

        this.atualizarStatusSync('Entrando na pausa...', 'loading');
        const resultado = await this.chamarAPI('entrar_na_pausa_agora', { nome });
        
        if (resultado.success) {
            if (typeof window.notificacoesGlobal !== 'undefined') {
                await window.notificacoesGlobal.notificarEntradaPausa(nome);
            }
            await this.carregarEstado();
            return true;
        } else {
            console.error(`❌ Erro ao entrar na pausa: ${resultado.message}`);
            return false;
        }
    }
    
    async trocarComPrimeiro(nome) {
        if (!sistemaAutenticacao.verificarPermissao(nome)) {
            console.error('❌ Acesso negado! Nome não encontrado na lista.');
            return false;
        }

        const pessoasNaEspera = this.estado.filter(p => p.status === 'espera')
            .sort((a, b) => new Date(a.inicio_espera) - new Date(b.inicio_espera));
        
        const ehSegundo = pessoasNaEspera.length > 1 && pessoasNaEspera[1].nome === nome;
        
        if (!ehSegundo) {
            console.error('❌ Apenas o segundo da fila pode solicitar troca com o primeiro');
            return false;
        }

        const primeiro = pessoasNaEspera[0];
        
        if (!primeiro) {
            console.error('❌ Não há primeiro na fila');
            return false;
        }

        if (typeof window.notificacoesGlobal !== 'undefined') {
            await window.notificacoesGlobal.notificarSolicitacaoTroca(primeiro.nome, nome);
        } else {
            await this.notificarSolicitacaoTroca(primeiro.nome, nome);
        }
        
        console.log(`ℹ️ Solicitação enviada para ${primeiro.nome}. Ele recebeu uma notificação.`);

        return true;
    }

    async notificarSolicitacaoTroca(primeiroNome, segundoNome) {
        const primeiroNomeCurto = primeiroNome.split(' ')[0];
        const segundoNomeCurto = segundoNome.split(' ')[0];
        
        const mensagemCompleta = `📢 <strong>Atenção!</strong><br>🔄 <strong>${segundoNomeCurto}</strong> quer trocar de lugar com <strong>${primeiroNomeCurto}</strong>`;
        
        console.log('🔄 SOLICITAÇÃO DE TROCA:', mensagemCompleta);
        
        if (typeof notificacoesTempoReal !== 'undefined') {
            await notificacoesTempoReal.enviarNotificacaoGlobal(mensagemCompleta, 'warning', 15000);
        }
    }

    async entrarNaPausa(nome) {
        if (!sistemaAutenticacao.verificarPermissao(nome)) {
            console.error('❌ Acesso negado! Nome não encontrado na lista.');
            return false;
        }

        const pessoa = this.estado.find(p => p.nome === nome);
        if (pessoa && pessoa.status !== 'disponivel') {
            console.error(`❌ ${nome} já está na ${pessoa.status === 'pausa' ? 'pausa' : 'lista de espera'}`);
            return false;
        }

        return await this.entrarNaEspera(nome);
    }

    async entrarNaEspera(nome) {
        this.atualizarStatusSync('Processando...', 'loading');
        const resultado = await this.chamarAPI('entrar_espera', { nome });
        
        if (resultado.success) {
            if (typeof window.notificacoesGlobal !== 'undefined') {
                await window.notificacoesGlobal.notificarEntradaFila(nome);
            }
            
            const pessoasNaEspera = this.estado.filter(p => p.status === 'espera')
                .sort((a, b) => new Date(a.inicio_espera) - new Date(b.inicio_espera));
            const posicao = pessoasNaEspera.findIndex(p => p.nome === nome) + 1;
            
            if (posicao > 0) {
                console.log(`ℹ️ Você entrou na posição ${posicao}º da fila.`);
                
                if (posicao === 1 && typeof window.notificacoesGlobal !== 'undefined') {
                    await window.notificacoesGlobal.notificarPrimeiroFila(nome);
                }
            }
            
            await this.carregarEstado();
            return true;
        } else {
            console.error(`❌ Erro ao entrar na lista de espera: ${resultado.message}`);
            return false;
        }
    }

    async moverParaUltimaPosicao(nome) {
        this.atualizarStatusSync('Processando...', 'loading');
        const resultado = await this.chamarAPI('mover_ultima_posicao', { nome });
        
        if (resultado.success) {
            console.log(`ℹ️ ${nome} foi para o final da fila`);
            await this.carregarEstado();
        } else {
            console.error('❌ Erro ao alterar posição na fila');
        }
        
        return resultado.success;
    }

    async sairDaPausa(nome) {
        if (!sistemaAutenticacao.verificarPermissao(nome)) {
            console.error('❌ Acesso negado! Nome não encontrado na lista.');
            return false;
        }

        this.atualizarStatusSync('Processando...', 'loading');
        const resultado = await this.chamarAPI('sair_pausa', { nome });
        
        if (resultado.success) {
            if (typeof window.notificacoesGlobal !== 'undefined') {
                await window.notificacoesGlobal.notificarSaidaPausa(nome);
            }
            await this.carregarEstado();
        } else {
            console.error('❌ Erro ao sair da pausa');
        }
        
        return resultado.success;
    }

    async sairDaEspera(nome) {
        if (!sistemaAutenticacao.verificarPermissao(nome)) {
            console.error('❌ Acesso negado! Nome não encontrado na lista.');
            return false;
        }

        this.atualizarStatusSync('Processando...', 'loading');
        const resultado = await this.chamarAPI('sair_espera', { nome });
        
        if (resultado.success) {
            if (typeof window.notificacoesGlobal !== 'undefined') {
                await window.notificacoesGlobal.notificarSaidaFila(nome);
            }
            await this.carregarEstado();
        } else {
            console.error('❌ Erro ao sair da espera');
        }
        
        return resultado.success;
    }

    async decidirSobreVaga(nome) {
        if (!sistemaAutenticacao.verificarPermissao(nome)) {
            console.error('❌ Acesso negado! Nome não encontrado na lista.');
            return false;
        }

        const pessoa = this.estado.find(p => p.nome === nome);
        if (!pessoa || pessoa.status !== 'espera') {
            console.error('❌ Você não está na lista de espera');
            return false;
        }

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
            const emAlerta = typeof contadorPausa !== 'undefined' && contadorPausa.estaEmAlerta(pessoa.nome);
            const classeAlerta = emAlerta ? 'piscante-alerta' : '';
            
            return `
                <div class="item ${classeAlerta}">
                    <div class="item-info">
                        <div class="item-nome">${pessoa.nome}</div>
                        <div class="contador-pausa" data-nome="${pessoa.nome}">
                            <div class="contador-tempo-pausa tempo-normal">
                                <i class="fas fa-hourglass-half"></i>
                                <span class="tempo-decorrido">00:00</span>
                            </div>
                        </div>
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

        setTimeout(() => {
            if (typeof contadorPausa !== 'undefined') {
                naPausa.forEach(pessoa => {
                    const tempoAtual = contadorPausa.obterTempo(pessoa.nome);
                    if (tempoAtual > 0) {
                        contadorPausa.atualizarDisplay(pessoa.nome, tempoAtual);
                    }
                });
            }
        }, 50);
    }

    atualizarListaEspera() {
        const container = document.getElementById('lista-espera');
        if (!container) {
            console.error('Container lista-espera não encontrado');
            return;
        }

        const naEspera = this.estado.filter(p => p.status === 'espera');
        
        naEspera.sort((a, b) => new Date(a.inicio_espera) - new Date(b.inicio_espera));
        
        if (naEspera.length === 0) {
            if (!container.querySelector('.lista-vazia')) {
                container.innerHTML = '<div class="lista-vazia"><i class="fas fa-clipboard-list" style="font-size: 2.5rem; margin-bottom: 15px; opacity: 0.5;"></i><div>Nenhuma pessoa na lista de espera</div></div>';
            }
            return;
        }

        const htmlAtual = container.innerHTML;
        const novoHTML = this.gerarHTMLListaEspera(naEspera);
        
        if (htmlAtual !== novoHTML) {
            container.innerHTML = novoHTML;
            
            setTimeout(() => {
                if (typeof contadorEspera !== 'undefined') {
                    naEspera.forEach(pessoa => {
                        const tempoAtual = contadorEspera.obterTempo(pessoa.nome);
                        if (tempoAtual > 0) {
                            contadorEspera.atualizarDisplay(pessoa.nome, tempoAtual);
                        }
                    });
                }
            }, 50);
        }
    }

    gerarHTMLListaEspera(naEspera) {
        let html = '';
        
        for (let i = 0; i < naEspera.length; i++) {
            const pessoa = naEspera[i];
            const posicao = i + 1;
            const ehPrimeiro = posicao === 1;
            const temPermissao = sistemaAutenticacao.verificarPermissao(pessoa.nome);
            
            let botao = '';
            
            if (temPermissao) {
                botao = `<button class="btn btn-decidir" onclick="controle.decidirSobreVaga('${pessoa.nome}')"><i class="fas fa-question-circle"></i> Decidir</button>`;
            } else {
                botao = '<button class="btn btn-bloqueado" disabled><i class="fas fa-lock"></i> Acesso Restrito</button>';
            }

            html += `<div class="item espera" data-nome="${pessoa.nome}">
                        <div class="item-info">
                            <div class="item-nome">${pessoa.nome}</div>
                            <div class="item-status">
                                <span class="badge espera">POSIÇÃO ${posicao}º</span>
                                ${ehPrimeiro ? '<span class="badge primeiro"><i class="fas fa-crown"></i> PRIMEIRO</span>' : ''}
                            </div>
                        </div>
                        <div class="item-botoes">
                            ${botao}
                        </div>
                    </div>`;
        }
        
        return html;
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
                            <i class="fas fa-question-circle"></i> Decidir
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
                    <div class="participante-botoes">
                        ${botoes}
                    </div>
                </div>
            `;
        }).join('');
    }

    async perguntarEntradaPausa(nome) {
        if (!sistemaAutenticacao.verificarPermissao(nome)) {
            console.log('Sem permissão para:', nome);
            this.pessoasComModalAberto.delete(nome);
            return;
        }

        console.log('Mostrando modal de decisão para:', nome);

        const pessoasNaEspera = this.estado.filter(p => p.status === 'espera')
            .sort((a, b) => new Date(a.inicio_espera) - new Date(b.inicio_espera));
        
        const ehPrimeiro = pessoasNaEspera.length > 0 && pessoasNaEspera[0].nome === nome;
        const ehSegundo = pessoasNaEspera.length > 1 && pessoasNaEspera[1].nome === nome;
    
        const temMaisDeUmaPessoa = pessoasNaEspera.length > 1;
        const posicaoAtual = pessoasNaEspera.findIndex(p => p.nome === nome) + 1;
        
        const vagasOcupadas = this.estado.filter(p => p.status === 'pausa').length;
        const haVagas = vagasOcupadas < 2;

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
                        ${ehSegundo ? `<p style="font-size: 0.9rem; color: var(--info); margin-top: 5px;">
                            <i class="fas fa-arrow-up"></i> Você é o <strong>segundo</strong> da fila!
                        </p>` : ''}
                        ${!ehPrimeiro && !ehSegundo ? `<p style="font-size: 0.9rem; color: var(--info); margin-top: 5px;">
                            <i class="fas fa-list-ol"></i> Você está na <strong>posição ${posicaoAtual}º</strong> da fila
                        </p>` : ''}
                        <p style="font-size: 0.9rem; color: var(--gray); margin-top: 10px;">
                            O que você gostaria de fazer?
                        </p>
                    </div>
                    <div class="modal-footer" style="display: flex; flex-direction: column; gap: 10px; justify-content: center;">
                        ${ehPrimeiro && haVagas ? `
                        <button id="btn-entrar-agora" class="btn btn-success">
                            <i class="fas fa-sign-in-alt"></i> Entrar na Pausa
                        </button>
                        ` : ''}
                        
                        ${ehPrimeiro && !haVagas ? `
                        <button id="btn-aguardando" class="btn btn-espera" disabled>
                            <i class="fas fa-clock"></i> Aguardando Vaga (Primeiro da Fila)
                        </button>
                        ` : ''}
                        
                        ${ehSegundo ? `
                        <button id="btn-trocar-primeiro" class="btn btn-warning">
                            <i class="fas fa-exchange-alt"></i> Solicitar Troca com o Primeiro
                        </button>
                        ` : ''}

                        ${!ehPrimeiro && !ehSegundo ? `
                        <button id="btn-posicao" class="btn btn-posicao" disabled>
                            <i class="fas fa-list-ol"></i> Posição ${posicaoAtual}º - Aguarde sua vez
                        </button>
                        ` : ''}

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
                            <i class="fas fa-times"></i> Fechar
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            const btnEntrarAgora = document.getElementById('btn-entrar-agora');
            const btnTrocarPrimeiro = document.getElementById('btn-trocar-primeiro');
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
                        await this.entrarNaPausaAgora(nome);
                        break;
                    case 'trocar_primeiro':
                        console.log(`${nome} escolheu trocar com o primeiro`);
                        await this.trocarComPrimeiro(nome);
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
                        break;
                }
                resolve(opcao);
            };

            if (btnEntrarAgora) {
                btnEntrarAgora.addEventListener('click', () => decidir('entrar_agora'));
            }
            if (btnTrocarPrimeiro) {
                btnTrocarPrimeiro.addEventListener('click', () => decidir('trocar_primeiro'));
            }
            if (btnSegundaPosicao) {
                btnSegundaPosicao.addEventListener('click', () => decidir('segunda_posicao'));
            }
            btnUltimaPosicao.addEventListener('click', () => decidir('ultima_posicao'));
            btnSairFila.addEventListener('click', () => decidir('sair_fila'));
            btnFechar.addEventListener('click', () => decidir('fechar'));
            btnFecharModal.addEventListener('click', () => decidir('fechar'));

            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    decidir('fechar');
                }
            });

            const fecharComESC = (e) => {
                if (e.key === 'Escape') {
                    decidir('fechar');
                    document.removeEventListener('keydown', fecharComESC);
                }
            };
            document.addEventListener('keydown', fecharComESC);
        });
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

// SISTEMA DE NOTIFICAÇÕES ESPECÍFICAS
class NotificacoesEspecificas {
    constructor() {
        this.notificacoesTempoReal = null;
    }

    setNotificacoesTempoReal(sistema) {
        this.notificacoesTempoReal = sistema;
    }

    async notificarEntradaFila(nome) {
        const nomeCurto = nome.split(' ')[0];
        const mensagem = `📋 <strong>${nomeCurto}</strong> entrou na fila de espera`;
        
        if (this.notificacoesTempoReal) {
            await this.notificacoesTempoReal.enviarNotificacaoGlobal(mensagem, 'info', 5000);
        } else {
            console.log('🔔', mensagem);
        }
    }

    async notificarEntradaPausa(nome) {
        const nomeCurto = nome.split(' ')[0];
        const mensagem = `✅ <strong>${nomeCurto}</strong> entrou na pausa`;
        
        if (this.notificacoesTempoReal) {
            await this.notificacoesTempoReal.enviarNotificacaoGlobal(mensagem, 'success', 6000);
        } else {
            console.log('🔔', mensagem);
        }
    }

    async notificarSaidaFila(nome) {
        const nomeCurto = nome.split(' ')[0];
        const mensagem = `🚪 <strong>${nomeCurto}</strong> saiu da fila de espera`;
        
        if (this.notificacoesTempoReal) {
            await this.notificacoesTempoReal.enviarNotificacaoGlobal(mensagem, 'info', 5000);
        } else {
            console.log('🔔', mensagem);
        }
    }

    async notificarSaidaPausa(nome) {
        const nomeCurto = nome.split(' ')[0];
        const mensagem = `👋 <strong>${nomeCurto}</strong> saiu da pausa`;
        
        if (this.notificacoesTempoReal) {
            await this.notificacoesTempoReal.enviarNotificacaoGlobal(mensagem, 'info', 5000);
        } else {
            console.log('🔔', mensagem);
        }
    }

    async notificarPrimeiroFila(nome) {
        const nomeCurto = nome.split(' ')[0];
        const mensagem = `👑 <strong>${nomeCurto}</strong> é o primeiro da fila! Pode entrar na pausa quando houver vaga.`;
        
        if (this.notificacoesTempoReal) {
            await this.notificacoesTempoReal.enviarNotificacaoGlobal(mensagem, 'warning', 8000);
        } else {
            console.log('🔔', mensagem);
        }
    }

    async notificarSolicitacaoTroca(primeiroNome, segundoNome) {
        const primeiroNomeCurto = primeiroNome.split(' ')[0];
        const segundoNomeCurto = segundoNome.split(' ')[0];
        
        const mensagem = `🔄 <strong>${segundoNomeCurto}</strong> quer trocar de lugar com <strong>${primeiroNomeCurto}</strong>`;
        
        if (this.notificacoesTempoReal) {
            await this.notificacoesTempoReal.enviarNotificacaoGlobal(mensagem, 'warning', 15000);
        } else {
            console.log('🔔', mensagem);
        }
    }

    async notificarTempoExcedido(nome) {
        const nomeCurto = nome.split(' ')[0];
        const mensagem = `⏰ <strong>${nomeCurto}</strong> excedeu o tempo de pausa!`;
        
        if (this.notificacoesTempoReal) {
            await this.notificacoesTempoReal.enviarNotificacaoGlobal(mensagem, 'danger', 10000);
        } else {
            console.log('🔔', mensagem);
        }
    }

    // Método para mostrar toast (se necessário)
    mostrarToast(mensagem, tipo = 'info', duracao = 5000) {
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
}

// INICIALIZAÇÃO DO SISTEMA
const sistemaAutenticacao = new SistemaAutenticacao();
const controle = new ControlePausa();

// DECLARAÇÃO ÚNICA DA VARIÁVEL GLOBAL
if (typeof window.notificacoesGlobal === 'undefined') {
    window.notificacoesGlobal = new NotificacoesEspecificas();
}

// CONFIGURAR EVENT LISTENERS
document.addEventListener('DOMContentLoaded', async function() {
    console.log('🚀 INICIANDO SISTEMA DE CONTROLE DE PAUSAS...');
    
    console.log('🔍 VERIFICANDO SISTEMA:');
    console.log('🔍 notificacoesTempoReal:', typeof notificacoesTempoReal);
    console.log('🔍 notificacoesGlobal:', typeof window.notificacoesGlobal);
    console.log('🔍 sistemaVoz:', typeof sistemaVoz);
    
    await sistemaAutenticacao.iniciarAutenticacao();
    
    // CONFIGURAR SISTEMA DE NOTIFICAÇÕES
    if (typeof notificacoesTempoReal !== 'undefined') {
        window.notificacoesGlobal.setNotificacoesTempoReal(notificacoesTempoReal);
        notificacoesTempoReal.inicializar();
        console.log('🔔 SISTEMA DE NOTIFICAÇÕES CONFIGURADO');
    } else {
        console.error('❌ SISTEMA DE NOTIFICAÇÕES NÃO ENCONTRADO');
    }
    
    // INICIALIZAR SISTEMA DE VOZ
    if (typeof sistemaVoz !== 'undefined') {
        sistemaVoz.inicializar();
        console.log('🔊 SISTEMA DE VOZ INICIALIZADO');
    } else {
        console.error('❌ SISTEMA DE VOZ NÃO ENCONTRADO');
    }
    
    controle.iniciarMonitoramento();
});


// CÓDIGO DE DIAGNÓSTICO - Adicione no final do script.js
function testarSistemaNotificacoes() {
    console.log('🔍 === DIAGNÓSTICO DO SISTEMA ===');
    
    // Verificar sistemas carregados
    console.log('📋 Sistemas carregados:');
    console.log('- sistemaAutenticacao:', typeof sistemaAutenticacao !== 'undefined' ? '✅' : '❌');
    console.log('- controle:', typeof controle !== 'undefined' ? '✅' : '❌');
    console.log('- notificacoesGlobal:', typeof notificacoesGlobal !== 'undefined' ? '✅' : '❌');
    console.log('- notificacoesTempoReal:', typeof notificacoesTempoReal !== 'undefined' ? '✅' : '❌');
    console.log('- sistemaVoz:', typeof sistemaVoz !== 'undefined' ? '✅' : '❌');
    console.log('- sonsNotificacoes:', typeof sonsNotificacoes !== 'undefined' ? '✅' : '❌');
    
    // Verificar status do sistema de voz
    if (typeof sistemaVoz !== 'undefined') {
        console.log('🔊 Status do sistema de voz:', sistemaVoz.getStatus());
    } else {
        console.log('❌ Sistema de voz não carregado');
    }
    
    // Testar notificação manual
    console.log('🎯 Testando notificação manual...');
    
    // Testar sistema de voz diretamente
    if (typeof sistemaVoz !== 'undefined') {
        console.log('🗣️ Testando voz...');
        sistemaVoz.falarNotificacao('Teste de voz do sistema');
    }
    
    // Testar notificação global
    if (typeof notificacoesTempoReal !== 'undefined') {
        console.log('🌐 Testando notificação global...');
        notificacoesTempoReal.enviarNotificacaoGlobal(
            '🔔 TESTE: Notificação de diagnóstico do sistema', 
            'info', 
            5000
        );
    }
    
    console.log('🔍 === FIM DO DIAGNÓSTICO ===');
}

// Executar diagnóstico após 3 segundos
setTimeout(() => {
    testarSistemaNotificacoes();
}, 3000);

// Também adicione um botão de teste na interface
document.addEventListener('DOMContentLoaded', function() {
    // Adicionar botão de teste
    const testButton = document.createElement('button');
    testButton.textContent = '🧪 Testar Sistema';
    testButton.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #ff6b00;
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 5px;
        cursor: pointer;
        z-index: 10000;
        font-size: 14px;
    `;
    testButton.onclick = testarSistemaNotificacoes;
    document.body.appendChild(testButton);
});