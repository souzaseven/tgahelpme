/**
 * ============================================================
 * MÓDULO DE SUPERVISÃO - v2.0
 * ============================================================
 * Gerencia o painel de supervisão com dados de pausas e rankings
 * Implementa: Cache, validação, tratamento de erros, performance
 */

// ============================================================
// CONFIGURAÇÕES E CONSTANTES
// ============================================================
const SUPERVISAO_CONFIG = {
    API_ENDPOINT: "../backend/supervisao_resumo.php",
    CACHE_DURATION: 30000, // 30 segundos
    MAX_ITEMS_RANKING: 10, // Limitar rankings
    LOG_LEVEL: "INFO", // DEBUG, INFO, WARN, ERROR
    TIMEOUT: 8000, // 8 segundos
    RETRY_ATTEMPTS: 3,
    RETRY_DELAY: 1000
};

// ============================================================
// LOGGER ESTRUTURADO
// ============================================================
class Logger {
    constructor(module, level = "INFO") {
        this.module = module;
        this.level = level;
        this.levels = { DEBUG: 0, INFO: 1, WARN: 2, ERROR: 3 };
    }

    log(type, message, data = null) {
        if (this.levels[type] < this.levels[this.level]) return;

        const timestamp = new Date().toLocaleTimeString('pt-BR');
        const styles = {
            DEBUG: "color:#00bcd4;font-weight:bold",
            INFO: "color:#4caf50;font-weight:bold",
            WARN: "color:#ff9800;font-weight:bold",
            ERROR: "color:#f44336;font-weight:bold"
        };

        const prefix = `[${timestamp}] [${this.module}] [${type}]`;
        console.log(`%c${prefix}`, styles[type], message);

        if (data) {
            console.table(data);
        }
    }

    debug(msg, data) { this.log("DEBUG", msg, data); }
    info(msg, data) { this.log("INFO", msg, data); }
    warn(msg, data) { this.log("WARN", msg, data); }
    error(msg, data) { this.log("ERROR", msg, data); }
}

const logger = new Logger("SUPERVISAO", SUPERVISAO_CONFIG.LOG_LEVEL);

// ============================================================
// GERENCIADOR DE CACHE
// ============================================================
class CacheManager {
    constructor(duration = SUPERVISAO_CONFIG.CACHE_DURATION) {
        this.duration = duration;
        this.cache = new Map();
    }

    set(key, value) {
        this.cache.set(key, {
            data: value,
            timestamp: Date.now()
        });
        logger.debug(`Cache atualizado: ${key}`);
    }

    get(key) {
        const item = this.cache.get(key);

        if (!item) return null;

        const isExpired = (Date.now() - item.timestamp) > this.duration;

        if (isExpired) {
            this.cache.delete(key);
            logger.debug(`Cache expirado: ${key}`);
            return null;
        }

        logger.debug(`Cache hit: ${key}`);
        return item.data;
    }

    clear(key = null) {
        if (key) {
            this.cache.delete(key);
        } else {
            this.cache.clear();
        }
    }

    isValid(key) {
        return this.get(key) !== null;
    }
}

const cacheManager = new CacheManager();

// ============================================================
// VALIDADOR DE DADOS
// ============================================================
class DataValidator {
    static validarResposta(dados) {
        if (!dados || typeof dados !== 'object') {
            throw new Error('Resposta inválida: esperado objeto');
        }

        if (dados.success === false) {
            throw new Error(dados.message || 'Erro desconhecido do servidor');
        }

        // Validar estrutura esperada
        const campos = ['pausa', 'expirados', 'ranking_pausa', 'ranking_fila'];
        for (const campo of campos) {
            if (!Array.isArray(dados[campo])) {
                throw new Error(`Campo inválido: ${campo}`);
            }
        }

        return true;
    }

    static sanitizarTexto(texto) {
        if (typeof texto !== 'string') return '';
        
        const element = document.createElement('div');
        element.textContent = texto;
        return element.innerHTML;
    }

    static validarItem(item, campos = ['nome', 'tempo', 'total']) {
        if (typeof item !== 'object') return false;
        
        return campos.every(campo => campo in item);
    }
}

// ============================================================
// GERENCIADOR DE REQUISIÇÕES HTTP
// ============================================================
class ApiManager {
    static async fetch(url, options = {}, retryCount = 0) {
        try {
            const controller = new AbortController();
            const timeout = setTimeout(
                () => controller.abort(),
                options.timeout || SUPERVISAO_CONFIG.TIMEOUT
            );

            const response = await fetch(url, {
                ...options,
                signal: controller.signal
            });

            clearTimeout(timeout);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            DataValidator.validarResposta(data);

            return data;

        } catch (error) {
            clearTimeout(timeout);

            if (retryCount < SUPERVISAO_CONFIG.RETRY_ATTEMPTS) {
                logger.warn(`Tentativa ${retryCount + 1} falhou, retentando...`, error.message);
                
                await this.delay(SUPERVISAO_CONFIG.RETRY_DELAY);
                return this.fetch(url, options, retryCount + 1);
            }

            throw error;
        }
    }

    static delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// ============================================================
// GERENCIADOR DE ESTADO DA UI
// ============================================================
class UIStateManager {
    constructor() {
        this.isLoading = false;
        this.currentView = null;
    }

    setLoading(estado) {
        this.isLoading = estado;
    }

    setView(view) {
        this.currentView = view;
    }
}

const uiState = new UIStateManager();

// ============================================================
// RENDERIZADOR DE COMPONENTES
// ============================================================
class ComponentRenderer {
    /**
     * Renderiza lista de operadores que excederam tempo
     */
    static renderExpirados(operadores, titulo = "Operadores que excederam 20 min") {
        if (!operadores || operadores.length === 0) {
            return `
                <div class="sub-card card-vazio">
                    <h3>${titulo}</h3>
                    <p class="mensagem-vazia">
                        <i class="fa-solid fa-check-circle"></i>
                        Nenhum operador excedeu o limite
                    </p>
                </div>
            `;
        }

        const items = operadores
            .slice(0, SUPERVISAO_CONFIG.MAX_ITEMS_RANKING)
            .map(op => {
                const sanitized = DataValidator.sanitizarTexto(op.nome);
                const tempo = DataValidator.sanitizarTexto(op.tempo);
                
                return `
                    <li class="item-expirado">
                        <span class="nome">${sanitized}</span>
                        <span class="tempo">
                            <i class="fa-solid fa-hourglass-end"></i>
                            ${tempo}
                        </span>
                    </li>
                `;
            })
            .join("");

        return `
            <div class="sub-card card-expirados">
                <h3>${titulo}</h3>
                <ul class="lista-expirados">
                    ${items}
                </ul>
            </div>
        `;
    }

    /**
     * Renderiza ranking
     */
    static renderRanking(items, titulo, icone = "fa-trophy") {
        if (!items || items.length === 0) {
            return `
                <div class="sub-card card-vazio">
                    <h3>${titulo}</h3>
                    <p class="mensagem-vazia">
                        <i class="fa-solid fa-inbox"></i>
                        Sem registros disponíveis
                    </p>
                </div>
            `;
        }

        const itemsLimitados = items.slice(0, SUPERVISAO_CONFIG.MAX_ITEMS_RANKING);
        
        const listItems = itemsLimitados
            .map((item, index) => {
                const sanitized = DataValidator.sanitizarTexto(item.nome);
                const total = DataValidator.sanitizarTexto(item.total);
                const posicao = index + 1;
                const medal = this.getMedalIcon(posicao);

                return `
                    <li class="ranking-item rank-${posicao}">
                        <span class="posicao">${medal}</span>
                        <span class="nome">${sanitized}</span>
                        <span class="valor">${total}</span>
                    </li>
                `;
            })
            .join("");

        return `
            <div class="sub-card card-ranking">
                <h3>
                    <i class="fa-solid ${icone}"></i>
                    ${titulo}
                </h3>
                <ol class="lista-ranking">
                    ${listItems}
                </ol>
            </div>
        `;
    }

    /**
     * Retorna ícone da medalha baseado na posição
     */
    static getMedalIcon(posicao) {
        const medals = {
            1: '🥇',
            2: '🥈',
            3: '🥉'
        };
        return medals[posicao] || `#${posicao}`;
    }

    /**
     * Renderiza spinner de carregamento
     */
    static renderLoading() {
        return `
            <div class="supervisao-loading">
                <div class="spinner"></div>
                <p>Carregando dados de supervisão...</p>
            </div>
        `;
    }

    /**
     * Renderiza mensagem de erro
     */
    static renderError(mensagem, tipo = "error") {
        return `
            <div class="supervisao-alert alert-${tipo}">
                <i class="fa-solid fa-exclamation-circle"></i>
                <p>${DataValidator.sanitizarTexto(mensagem)}</p>
            </div>
        `;
    }
}

// ============================================================
// PROCESSADOR DE DADOS
// ============================================================
class DataProcessor {
    /**
     * Filtra dados pela equipe logada
     */
    static filtrarPorEquipe(dados, equipeLogada) {
        if (!equipeLogada) {
            logger.warn('Equipe logada não encontrada');
            return null;
        }

        return {
            pausa: this.filtrar(dados.pausa, equipeLogada),
            expirados: this.filtrar(dados.expirados, equipeLogada),
            ranking_pausa: this.filtrar(dados.ranking_pausa, equipeLogada),
            ranking_fila: this.filtrar(dados.ranking_fila, equipeLogada)
        };
    }

    /**
     * Helper para filtrar array por equipe
     */
    static filtrar(array, equipe) {
        return Array.isArray(array) 
            ? array.filter(item => item.equipe === equipe)
            : [];
    }

    /**
     * Ordena ranking por valor descrescente
     */
    static ordenarRanking(items) {
        if (!Array.isArray(items)) return [];
        
        return [...items].sort((a, b) => {
            const valorA = this.extrairNumero(a.total || a.tempo);
            const valorB = this.extrairNumero(b.total || b.tempo);
            return valorB - valorA;
        });
    }

    /**
     * Extrai número de string (ex: "1h 30m" -> 5400)
     */
    static extrairNumero(valor) {
        if (typeof valor === 'number') return valor;
        if (typeof valor !== 'string') return 0;
        
        const match = valor.match(/\d+/);
        return match ? parseInt(match[0]) : 0;
    }
}

// ============================================================
// GERENCIADOR PRINCIPAL DE SUPERVISÃO
// ============================================================
class SupervisaoManager {
    constructor() {
        this.dados = null;
        this.equipeLogada = null;
        this.container = null;
    }

    /**
     * Inicializa o painel de supervisão
     */
    async abrir() {
        try {
            logger.info('Abrindo painel de supervisão');
            uiState.setLoading(true);

            // Preparar UI
            this.preparaUI();

            // Obter dados
            const dados = await this.carregarDados();

            // Obter equipe logada
            this.equipeLogada = this.getEquipeLogada();

            // Renderizar
            this.renderizar(dados);

            logger.info('Painel de supervisão aberto com sucesso');

        } catch (erro) {
            this.tratarErro(erro);
        } finally {
            uiState.setLoading(false);
        }
    }

    /**
     * Carrega dados do servidor (com cache)
     */
    async carregarDados() {
        const cacheKey = 'supervisao_dados';

        // Verificar cache primeiro
        if (cacheManager.isValid(cacheKey)) {
            logger.info('Usando dados em cache');
            return cacheManager.get(cacheKey);
        }

        // Fazer requisição
        logger.info('Requisitando dados do servidor');
        this.container.innerHTML = ComponentRenderer.renderLoading();

        try {
            const dados = await ApiManager.fetch(SUPERVISAO_CONFIG.API_ENDPOINT);
            
            // Armazenar em cache
            cacheManager.set(cacheKey, dados);

            return dados;

        } catch (erro) {
            logger.error('Erro ao carregar dados', erro.message);
            throw new Error(`Falha ao carregar dados: ${erro.message}`);
        }
    }

    /**
     * Obtém equipe logada do localStorage
     */
    getEquipeLogada() {
        try {
            const op = JSON.parse(localStorage.getItem("tga_operador")) || {};
            const equipe = op.equipe || "";

            if (!equipe) {
                logger.warn('Nenhuma equipe encontrada no localStorage');
            }

            return equipe;

        } catch (erro) {
            logger.error('Erro ao obter equipe logada', erro.message);
            return "";
        }
    }

    /**
     * Prepara a interface visual
     */
    preparaUI() {
        const boxExtra = document.getElementById("conteudoExtra");
        const boxSuper = document.getElementById("conteudoSupervisao");

        if (!boxSuper) {
            throw new Error('Elemento #conteudoSupervisao não encontrado');
        }

        // Ocultar painéis
        document.querySelector(".painel-topo")?.style.setProperty('display', 'none');
        document.querySelector(".painel-dashboard")?.style.setProperty('display', 'none');
        document.querySelector(".card-participantes")?.style.setProperty('display', 'none');

        // Mostrar supervisão
        boxExtra?.classList.add("hidden");
        boxSuper.classList.remove("hidden");

        this.container = boxSuper;
    }

    /**
     * Renderiza o painel completo
     */
    renderizar(dados) {
        const equipeLogada = this.equipeLogada;
        const dadosEquipe = DataProcessor.filtrarPorEquipe(dados, equipeLogada);

        let html = `
            <div class="supervisao-header">
                <h2 class="titulo-supervisao">
                    <i class="fa-solid fa-user-shield"></i> 
                    Painel de Supervisão
                </h2>
                <div class="supervisao-info">
                    <span class="timestamp">
                        <i class="fa-solid fa-clock"></i>
                        Atualizado às ${new Date().toLocaleTimeString('pt-BR')}
                    </span>
                    <button class="btn-refresh" id="btnRefreshSupervisao" title="Atualizar dados">
                        <i class="fa-solid fa-rotate-right"></i>
                    </button>
                </div>
            </div>

            <div class="supervisao-grid">
                ${this.renderBlocoEquipe(dadosEquipe, equipeLogada)}
                ${this.renderBlocoGeral(dados)}
            </div>
        `;

        this.container.innerHTML = html;

        // Anexar eventos
        this.anexarEventos();
    }

    /**
     * Renderiza bloco de dados da equipe logada
     */
    renderBlocoEquipe(dados, nomeEquipe) {
        if (!nomeEquipe) {
            return `
                <div class="card-super bloco-equipe">
                    <h2 class="titulo-bloco">Minha Equipe</h2>
                    ${ComponentRenderer.renderError('Equipe não identificada', 'warning')}
                </div>
            `;
        }

        return `
            <div class="card-super bloco-equipe">
                <h2 class="titulo-bloco">
                    <i class="fa-solid fa-people-group"></i>
                    Minha Equipe — ${DataValidator.sanitizarTexto(nomeEquipe)}
                </h2>

                ${ComponentRenderer.renderExpirados(
                    dados.expirados,
                    'Que excederam 20 min hoje'
                )}

                ${ComponentRenderer.renderRanking(
                    DataProcessor.ordenarRanking(dados.ranking_pausa),
                    'Ranking de Pausas — Hoje',
                    'fa-hourglass'
                )}

                ${ComponentRenderer.renderRanking(
                    DataProcessor.ordenarRanking(dados.ranking_fila),
                    'Ranking Tempo em Fila — Hoje',
                    'fa-hourglass-start'
                )}
            </div>
        `;
    }

    /**
     * Renderiza bloco de dados gerais
     */
    renderBlocoGeral(dados) {
        return `
            <div class="card-super bloco-geral">
                <h2 class="titulo-bloco">
                    <i class="fa-solid fa-globe"></i>
                    Visão Geral
                </h2>

                ${ComponentRenderer.renderExpirados(
                    dados.expirados,
                    'Operadores com excesso (todas as equipes)'
                )}

                ${ComponentRenderer.renderRanking(
                    DataProcessor.ordenarRanking(dados.ranking_pausa),
                    'Top Pausas — Hoje (Geral)',
                    'fa-star'
                )}

                ${ComponentRenderer.renderRanking(
                    DataProcessor.ordenarRanking(dados.ranking_fila),
                    'Top Fila — Hoje (Geral)',
                    'fa-chart-line'
                )}
            </div>
        `;
    }

    /**
     * Trata erros
     */
    tratarErro(erro) {
        logger.error('Erro ao carregar supervisão', erro.message);

        const mensagem = erro.message || 'Erro desconhecido ao carregar supervisão';
        this.container.innerHTML = ComponentRenderer.renderError(mensagem);
    }

    /**
     * Anexa eventos aos elementos
     */
    anexarEventos() {
        const btnRefresh = document.getElementById('btnRefreshSupervisao');
        
        if (btnRefresh) {
            btnRefresh.addEventListener('click', () => {
                logger.info('Atualizando dados manualmente');
                cacheManager.clear('supervisao_dados');
                this.abrir();
            });
        }
    }
}

// ============================================================
// INICIALIZAÇÃO
// ============================================================
console.log("%c[SUPERVISAO] Módulo v2.0 carregado com sucesso", "color:#4caf50;font-weight:bold");

// Instância global
const supervisaoManager = new SupervisaoManager();

// Função global para manter compatibilidade
window.abrirSupervisao = function() {
    return supervisaoManager.abrir();
};

// Evento de limpeza ao descarregar
window.addEventListener('beforeunload', () => {
    cacheManager.clear();
    logger.info('Limpeza de recursos realizada');
});
