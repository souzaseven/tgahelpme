/**
 * Sistema de Visualização de Equipes da Semana
 * Versão: 2.0 - Layout igual à imagem
 */

class EquipesSemana {
    constructor() {
        this.equipesFixas = ['Alex Sandro Braulio', 'Daniel Feix', 'Willian Pereira Reis'];
        this.dados = {
            semana: {},
            operadores: []
        };
    }

    /**
     * Inicializa o sistema
     */
    async init() {
        await this.carregarDados();
        this.renderizarSemanaTelefone();
    }

    /**
     * Carrega dados da API
     */
    async carregarDados() {
        try {
            const response = await fetch('operadores.php?_=' + Date.now());
            const data = await response.json();

            if (data.success) {
                this.dados.semana = data.semana || {};
                this.dados.operadores = data.data || [];
            } else {
                throw new Error('Erro ao carregar dados da API');
            }
        } catch (error) {
            console.error('Erro ao carregar equipes:', error);
            this.dados.semana = {
                telefone: 'Erro ao carregar',
                chat: 'Erro ao carregar'
            };
            this.dados.operadores = [];
        }
    }

    /**
     * NOVA FUNÇÃO: Renderiza a visualização da semana de telefone (igual à imagem)
     */
    renderizarSemanaTelefone() {
        const container = document.getElementById('equipesSemanaContainer');
        
        container.innerHTML = `
            <div class="equipes-semana-container">
                <!-- Semana de Telefone - Layout Principal -->
                <div class="semana-telefone-container">
                    <h2>📅 Semana de Telefone</h2>
                    <div class="rotacao-telefone-grid">
                        <div class="rotacao-telefone-item anterior">
                            <span class="rotulo">Anterior</span>
                            <span class="valor">${this.calcularRotacao().anterior}</span>
                        </div>
                        <div class="rotacao-telefone-item atual">
                            <span class="rotulo">Atual</span>
                            <span class="valor">${this.calcularRotacao().atual}</span>
                        </div>
                        <div class="rotacao-telefone-item proxima">
                            <span class="rotulo">Próxima</span>
                            <span class="valor">${this.calcularRotacao().proxima}</span>
                        </div>
                    </div>
                </div>

                <!-- Dashboard Resumido -->
                <div class="equipes-dashboard">
                    <div class="equipe-card telefone">
                        <h3><i class="fas fa-phone"></i> Equipe Telefone da Semana</h3>
                        <span>${this.dados.semana.telefone || 'Não definido'}</span>
                    </div>
                    <div class="equipe-card chat">
                        <h3><i class="fas fa-comments"></i> Equipe Chat da Semana</h3>
                        <span>${this.dados.semana.chat || 'Não definido'}</span>
                    </div>
                </div>

                <!-- Operadores por Equipe -->
                <div class="operadores-lista">
                    <h2><i class="fas fa-users"></i> Operadores por Equipe</h2>
                    ${this.gerarBlocosOperadores()}
                </div>
            </div>
        `;
        
        this.configurarEventos();
    }

    /**
     * Calcula a rotação baseada na equipe atual
     */
    calcularRotacao() {
        const equipeAtual = this.dados.semana.telefone;
        
        if (!equipeAtual || equipeAtual === 'Não definido') {
            return {
                anterior: 'Nenhuma',
                atual: 'Não definida',
                proxima: 'Nenhuma'
            };
        }

        const index = this.equipesFixas.findIndex(equipe => 
            equipe.toLowerCase() === equipeAtual.toLowerCase()
        );

        if (index === -1) {
            return {
                anterior: 'Desconhecida',
                atual: equipeAtual,
                proxima: 'Desconhecida'
            };
        }

        return {
            anterior: this.equipesFixas[(index - 1 + this.equipesFixas.length) % this.equipesFixas.length],
            atual: this.equipesFixas[index],
            proxima: this.equipesFixas[(index + 1) % this.equipesFixas.length]
        };
    }

    /**
     * Gera os blocos de operadores por equipe
     */
    gerarBlocosOperadores() {
        if (!this.dados.operadores || this.dados.operadores.length === 0) {
            return `
                <div class="no-operadores">
                    <i class="fas fa-users-slash"></i>
                    <p>Nenhum operador encontrado</p>
                </div>
            `;
        }

        const operadoresPorLider = this.agruparPorLider();
        let html = '';

        this.equipesFixas.forEach(lider => {
            const operadores = operadoresPorLider[lider] || [];
            if (operadores.length > 0) {
                html += this.gerarBlocoMonitor(lider, operadores);
            }
        });

        return html;
    }

    /**
     * Agrupa operadores por líder
     */
    agruparPorLider() {
        return this.dados.operadores.reduce((acc, operador) => {
            const lider = operador.lider || 'Sem equipe';
            if (!acc[lider]) {
                acc[lider] = [];
            }
            acc[lider].push(operador);
            return acc;
        }, {});
    }

    /**
     * Gera bloco para cada monitor/líder
     */
    gerarBlocoMonitor(lider, operadores) {
        const operadoresHTML = operadores.map(op => {
            // Processar filas
            let filas = [];
            try {
                filas = typeof op.fila === 'string' ? JSON.parse(op.fila) : op.fila;
                filas = Array.isArray(filas) ? filas : [filas];
            } catch {
                filas = [op.fila || 'Sem fila definida'];
            }

            return `
                <div class="agent-item">
                    <div class="agent-info">
                        <div class="agent-name">${op.nome || 'Sem nome'}</div>
                        <div class="agent-queue">${filas.join(', ')}</div>
                    </div>
                    <div class="agent-actions">
                        <a href="${op.link || '#'}" target="_blank">
                            <i class="fas fa-external-link-alt"></i>
                            Acessar
                        </a>
                    </div>
                </div>
            `;
        }).join('');

        return `
            <div class="monitor-block">
                <div class="monitor-header">
                    <h3><i class="fas fa-user-tie"></i> ${lider}</h3>
                    <span>${operadores.length} operador(es)</span>
                </div>
                <div class="agents-list">
                    ${operadoresHTML}
                </div>
            </div>
        `;
    }

    /**
     * Configura eventos
     */
    configurarEventos() {
        // Eventos de clique nos headers dos monitores
        setTimeout(() => {
            document.querySelectorAll('.monitor-header').forEach(header => {
                header.addEventListener('click', function() {
                    const agentsList = this.nextElementSibling;
                    const isVisible = agentsList.style.display !== 'none';
                    agentsList.style.display = isVisible ? 'none' : 'block';
                    
                    // Alternar ícone
                    const icon = this.querySelector('i.fa-chevron-down, i.fa-chevron-up');
                    if (icon) {
                        icon.className = isVisible ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
                    }
                });
            });
        }, 100);
    }

    /**
     * Atualiza os dados
     */
    async atualizarDados() {
        const btn = document.querySelector('.btn-equipes.atualizar');
        if (btn) {
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Atualizando...';
            btn.disabled = true;

            try {
                await this.carregarDados();
                this.renderizarSemanaTelefone();
                this.mostrarMensagem('✅ Dados atualizados com sucesso!', 'success');
            } catch (error) {
                this.mostrarMensagem('❌ Erro ao atualizar dados', 'error');
            } finally {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }
        } else {
            // Fallback para quando não há botão específico
            await this.carregarDados();
            this.renderizarSemanaTelefone();
        }
    }

    /**
     * Mostra mensagem temporária
     */
    mostrarMensagem(mensagem, tipo) {
        const div = document.createElement('div');
        div.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background: ${tipo === 'success' ? '#27ae60' : '#e74c3c'};
            color: white;
            border-radius: 5px;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        `;
        div.textContent = mensagem;
        document.body.appendChild(div);

        setTimeout(() => {
            if (div.parentNode) {
                document.body.removeChild(div);
            }
        }, 3000);
    }
}

// Instância global
const equipesSemana = new EquipesSemana();

/**
 * Função para carregar a visualização de equipes
 */
function carregarEquipesSemana() {
    document.getElementById("content").innerHTML = `
        <div class="toolbar">
            <button class="btn btn-secondary" onclick="loadData('operadores')">
                ↩️ Voltar para Lista
            </button>
            <button class="btn btn-primary" onclick="equipesSemana.atualizarDados()">
                🔄 Atualizar
            </button>
        </div>
        
        <div id="equipesSemanaContainer">
            <div class="loading-equipes">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Carregando equipes da semana...</p>
            </div>
        </div>
    `;
    
    // Inicializa o sistema
    setTimeout(() => {
        equipesSemana.init();
    }, 100);
}