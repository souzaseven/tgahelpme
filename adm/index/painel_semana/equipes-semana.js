/**
 * Sistema de Visualização de Equipes da Semana - Versão Corrigida
 * Versão: 4.1 - Caminhos totalmente corrigidos
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
     * Inicializa o sistema completo
     */
    async init() {
        await this.carregarDados();
        this.renderizarSemanaTelefone();
    }

    /**
     * ✅ CORREÇÃO: Caminho ABSOLUTO para evitar problemas
     */
    async carregarDados() {
        try {
            
            // ✅ CORREÇÃO: Caminho absoluto desde a raiz
            const response = await fetch('/painel_semana/operadores.php?_=' + Date.now());
            
            
            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }
            
            const text = await response.text();
            
            const data = JSON.parse(text);

            if (data.success) {
                this.dados.semana = data.semana || {};
                this.dados.operadores = data.data || [];
                
                
                this.atualizarResumoTelefone();
            } else {
                console.error('❌ API retornou success: false');
                throw new Error('API retornou success: false');
            }
        } catch (error) {
            console.error('💥 Erro ao carregar equipes:', error);
            this.dados.semana = {
                telefone: 'Erro ao carregar',
                chat: 'Erro ao carregar'
            };
            this.dados.operadores = [];
            
            this.atualizarResumoTelefone();
        }
    }

    /**
     * ✅ FUNÇÃO NOVA: Atualiza APENAS a seção resumo
     */
    atualizarResumoTelefone() {
        const elementoAnterior = document.getElementById('telefoneAnteriorResumo');
        const elementoAtual = document.getElementById('telefoneAtualResumo');
        const elementoProxima = document.getElementById('telefoneProximaResumo');
        
        if (!elementoAnterior || !elementoAtual || !elementoProxima) {
            return;
        }

        const rotacao = this.calcularRotacao();
        
        
        elementoAnterior.textContent = rotacao.anterior;
        elementoAtual.textContent = rotacao.atual;
        elementoProxima.textContent = rotacao.proxima;
        
    }

    /**
     * ✅ FUNÇÃO NOVA: Inicializa apenas o resumo
     */
    async initResumo() {
        await this.carregarDados();
    }

    /**
     * ✅ FUNÇÃO CORRIGIDA: Calcula rotação considerando múltiplas equipes
     */
    calcularRotacao() {
        const equipeAtual = this.dados.semana.telefone;
        

        // Casos de erro ou dados não carregados
        if (!equipeAtual || 
            equipeAtual === 'Nenhuma equipe definida' || 
            equipeAtual === 'Erro ao carregar' ||
            equipeAtual === 'Carregando...') {
            return {
                anterior: 'Nenhuma',
                atual: 'Carregando...',
                proxima: 'Nenhuma'
            };
        }

        // ✅ SE HOUVER MÚLTIPLAS EQUIPES, PEGA A PRIMEIRA PARA CALCULAR ROTAÇÃO
        let equipePrincipal = equipeAtual;
        if (equipeAtual.includes(',')) {
            const equipes = equipeAtual.split(',').map(e => e.trim());
            equipePrincipal = equipes[0];
        }

        const index = this.equipesFixas.findIndex(equipe => 
            equipe.toLowerCase() === equipePrincipal.toLowerCase()
        );


        if (index === -1) {
            console.warn('⚠️ Equipe não encontrada na lista fixa');
            return {
                anterior: 'Desconhecida',
                atual: equipeAtual,
                proxima: 'Desconhecida'
            };
        }

        const anteriorIndex = (index - 1 + this.equipesFixas.length) % this.equipesFixas.length;
        const proximaIndex = (index + 1) % this.equipesFixas.length;
        
        const resultado = {
            anterior: this.equipesFixas[anteriorIndex],
            atual: equipeAtual, // ✅ MOSTRA TODAS AS EQUIPES DO TELEFONE
            proxima: this.equipesFixas[proximaIndex]
        };
        
        return resultado;
    }

    /**
     * Renderiza a visualização completa da semana de telefone
     */
    renderizarSemanaTelefone() {
        const container = document.getElementById('equipesSemanaContainer');
        
        if (!container) {
            return;
        }

        const rotacao = this.calcularRotacao();
        
        container.innerHTML = `
            <div class="equipes-semana-container">
                <!-- Semana de Telefone - Layout Principal -->
                <div class="semana-telefone-container">
                    <h2>📅 Semana de Telefone</h2>
                    <div class="rotacao-telefone-grid">
                        <div class="rotacao-telefone-item anterior">
                            <span class="rotulo">Anterior</span>
                            <span class="valor">${this.escapeHTML(rotacao.anterior)}</span>
                        </div>
                        <div class="rotacao-telefone-item atual">
                            <span class="rotulo">Atual</span>
                            <span class="valor">${this.escapeHTML(rotacao.atual)}</span>
                        </div>
                        <div class="rotacao-telefone-item proxima">
                            <span class="rotulo">Próxima</span>
                            <span class="valor">${this.escapeHTML(rotacao.proxima)}</span>
                        </div>
                    </div>
                </div>

                <!-- Dashboard Resumido -->
                <div class="equipes-dashboard">
                    <div class="equipe-card telefone">
                        <h3><i class="fas fa-phone"></i> Equipe Telefone da Semana</h3>
                        <span>${this.escapeHTML(this.dados.semana.telefone || 'Não definido')}</span>
                    </div>
                    <div class="equipe-card chat">
                        <h3><i class="fas fa-comments"></i> Equipe Chat da Semana</h3>
                        <span>${this.escapeHTML(this.dados.semana.chat || 'Não definido')}</span>
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

        // Adiciona operadores sem equipe definida
        const semEquipe = operadoresPorLider['Sem equipe'] || [];
        if (semEquipe.length > 0) {
            html += this.gerarBlocoMonitor('Sem equipe definida', semEquipe);
        }

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
            let filas = [];
            try {
                filas = typeof op.fila === 'string' ? JSON.parse(op.fila) : op.fila;
                filas = Array.isArray(filas) ? filas : [filas];
            } catch {
                filas = [op.fila || 'Sem fila definida'];
            }

            const nome = op.nome || 'Sem nome';
            const link = op.link || '#';

            return `
                <div class="agent-item">
                    <div class="agent-info">
                        <div class="agent-name">${this.escapeHTML(nome)}</div>
                        <div class="agent-queue">${this.escapeHTML(filas.join(', '))}</div>
                    </div>
                    <div class="agent-actions">
                        <a href="${this.escapeHTML(link)}" target="_blank" rel="noopener">
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
                    <h3><i class="fas fa-user-tie"></i> ${this.escapeHTML(lider)}</h3>
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
        setTimeout(() => {
            document.querySelectorAll('.monitor-header').forEach(header => {
                header.addEventListener('click', function() {
                    const agentsList = this.nextElementSibling;
                    const isVisible = agentsList.style.display !== 'none';
                    agentsList.style.display = isVisible ? 'none' : 'block';
                    
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
        await this.carregarDados();
        this.renderizarSemanaTelefone();
        this.mostrarMensagem('✅ Dados atualizados com sucesso!', 'success');
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
            font-family: Arial, sans-serif;
        `;
        div.textContent = mensagem;
        document.body.appendChild(div);

        setTimeout(() => {
            if (div.parentNode) {
                document.body.removeChild(div);
            }
        }, 3000);
    }

    /**
     * ✅ FUNÇÃO NOVA: Prevenção contra XSS
     */
    escapeHTML(text) {
        if (typeof text !== 'string') return text;
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Instância global
const equipesSemana = new EquipesSemana();

/**
 * Função para carregar a visualização completa de equipes
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
    
    setTimeout(() => {
        equipesSemana.init();
    }, 100);
}

/**
 * ✅ FUNÇÃO NOVA: Inicialização automática do resumo
 */
function inicializarResumoTelefone() {
    const existeResumo = document.getElementById('telefoneAtualResumo');
    
    if (existeResumo) {
        equipesSemana.initResumo();
        
        // Atualização automática a cada 5 minutos
        setInterval(() => {
            equipesSemana.carregarDados();
        }, 300000);
    } else {
    }
}

// ✅ INICIALIZAÇÃO AUTOMÁTICA
document.addEventListener('DOMContentLoaded', function() {
    inicializarResumoTelefone();
});

// ✅ FUNÇÃO PARA TESTE MANUAL - Adicione isso no console do navegador
window.testarSistemaEquipes = function() {
    console.log('🧪 Teste manual do sistema iniciado...');
    equipesSemana.carregarDados().then(() => {
        console.log('✅ Teste concluído');
    });
};

// ✅ FUNÇÃO PARA VERIFICAR CONEXÃO
window.verificarConexao = function() {
    console.log('🔍 Verificando conexão com operadores.php...');
    fetch('/painel_semana/operadores.php')
        .then(response => {
            console.log('📡 Status:', response.status);
            return response.text();
        })
        .then(text => {
            console.log('📄 Resposta:', text.substring(0, 200) + '...');
        })
        .catch(error => {
            console.error('❌ Erro:', error);
        });
};