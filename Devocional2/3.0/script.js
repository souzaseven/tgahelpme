/**
 * PAINEL DE DEVOCIONAIS - SISTEMA COMPLETO
 * Arquivo: script.js
 * Descrição: Gerencia o painel de devocionais com CRUD completo, modais e temas
 * Autor: Souza System
 * Versão: 2.0
 */

// ============ CONSTANTES E VARIÁVEIS GLOBAIS ============
const elementos = {
    // Botões principais
    newDevocionalBtn: document.getElementById('newDevocionalBtn'),
    toggleDarkMode: document.getElementById('toggleDarkMode'),
    cancelarBtn: document.getElementById('cancelarBtn'),
    salvarBtn: document.getElementById('salvarBtn'),
    confirmarLogin: document.getElementById('confirmarLogin'),
    
    // Inputs do formulário
    inputData: document.getElementById('inputData'),
    inputTema: document.getElementById('inputTema'),
    inputTexto: document.getElementById('inputTexto'),
    inputMinistradoPor: document.getElementById('inputMinistradoPor'),
    
    // Elementos de exibição
    infoDevocional: document.getElementById('infoDevocional'),
    searchInput: document.querySelector('#searchInput:not(#usuarioLogin)'),
    historicoContainer: document.querySelector('.historico-container'),
    
    // Modais
    modal: document.getElementById('modal'),
    modalTexto: document.getElementById('modalTexto'),
    loginModal: document.getElementById('loginModal'),
    confirmarExclusaoModal: document.getElementById('confirmarExclusaoModal'),
    
    // Elementos do painel
    devocionalAnterior: document.getElementById('devocionalAnterior'),
    devocionalAtual: document.getElementById('devocionalAtual'),
    devocionalProximo: document.getElementById('devocionalProximo')
};

// Estado da aplicação
const estado = {
    historicoDevocionais: [],
    devocionalEditando: null,
    devocionalParaExcluir: null,
    ultimaBusca: '',
    darkMode: true
};

// ============ FUNÇÕES UTILITÁRIAS ============

/**
 * Alterna entre tema claro e escuro
 */
function alternarTema() {
    document.body.classList.toggle('dark');
    estado.darkMode = document.body.classList.contains('dark');
    localStorage.setItem('darkMode', estado.darkMode ? 'enabled' : 'disabled');
}

/**
 * Mostra uma notificação na tela
 * @param {string} mensagem - Mensagem a ser exibida
 * @param {string} tipo - Tipo da notificação (success, error, warning, info)
 */
function mostrarNotificacao(mensagem, tipo = 'info') {
    // Criar estilo se não existir
    if (!document.querySelector('#estilo-notificacoes')) {
        const estilo = document.createElement('style');
        estilo.id = 'estilo-notificacoes';
        estilo.textContent = `
            .notificacao {
                position: fixed;
                bottom: 20px;
                right: 20px;
                padding: 15px 25px;
                border-radius: 8px;
                color: white;
                font-weight: 500;
                transform: translateY(100px);
                opacity: 0;
                transition: all 0.3s ease;
                z-index: 10000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                max-width: 400px;
                word-wrap: break-word;
            }
            .notificacao.show { 
                transform: translateY(0); 
                opacity: 1; 
            }
            .notificacao-success { background: var(--success-color); }
            .notificacao-error { background: var(--danger-color); }
            .notificacao-warning { background: var(--accent-color); color: #333; }
            .notificacao-info { background: var(--primary-color); }
            .mensagem-vazia {
                text-align: center;
                padding: 40px 20px;
                color: #666;
                font-size: 16px;
                grid-column: 1 / -1;
            }
            body.dark .mensagem-vazia { color: #aaa; }
        `;
        document.head.appendChild(estilo);
    }

    const notificacao = document.createElement('div');
    notificacao.className = `notificacao notificacao-${tipo}`;
    notificacao.textContent = mensagem;
    notificacao.setAttribute('role', 'alert');
    notificacao.setAttribute('aria-live', 'polite');
    
    document.body.appendChild(notificacao);
    
    // Animação de entrada
    setTimeout(() => notificacao.classList.add('show'), 10);
    
    // Remover após 4 segundos
    setTimeout(() => {
        notificacao.classList.remove('show');
        setTimeout(() => {
            if (document.body.contains(notificacao)) {
                document.body.removeChild(notificacao);
            }
        }, 300);
    }, 4000);
}

/**
 * Escapa caracteres HTML para prevenir XSS
 * @param {string} str - String a ser escapada
 * @returns {string} String segura
 */
function escapeHTML(str) {
    if (!str) return '';
    return str.replace(/[&<>'"]/g, 
        tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag]));
}

/**
 * Formata uma data para exibição
 * @param {string} dataStr - String da data
 * @param {boolean} short - Formato curto (DD/MM/AAAA)
 * @returns {string} Data formatada
 */
function formatarData(dataStr, short = false) {
    if (!dataStr) return '';
    
    let data;
    if (dataStr.includes('-')) {
        const partes = dataStr.split('-');
        data = new Date(partes[0], partes[1] - 1, partes[2]);
    } else {
        data = new Date(dataStr);
    }
    
    if (isNaN(data.getTime())) return dataStr;
    
    const options = short 
        ? { day: '2-digit', month: '2-digit', year: 'numeric' }
        : { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    
    return data.toLocaleDateString('pt-BR', options);
}

/**
 * Parse de string para Date
 * @param {string} dataStr - String da data
 * @returns {Date} Objeto Date
 */
function parseData(dataStr) {
    if (!dataStr) return null;
    if (dataStr.includes('-')) {
        const [y,m,d] = dataStr.split('-');
        return new Date(y, m-1, d);
    }
    if (dataStr.includes('/')) {
        const [d,m,y] = dataStr.split('/');
        return new Date(y, m-1, d);
    }
    return new Date(dataStr);
}

/**
 * Calcula o intervalo da semana (segunda a domingo)
 * @param {Date} date - Data de referência
 * @returns {Object} Objeto com inicioSemana e fimSemana
 */
function getSemanaIntervalo(date = new Date()) {
    const d = new Date(date);
    const diaSemana = d.getDay(); // 0 = domingo ... 6 = sábado

    // começo da semana: segunda (dia 1)
    const diffInicio = d.getDate() - diaSemana + (diaSemana === 0 ? -6 : 1);
    const inicioSemana = new Date(d.setDate(diffInicio));
    inicioSemana.setHours(0,0,0,0);

    // fim da semana: domingo
    const fimSemana = new Date(inicioSemana);
    fimSemana.setDate(inicioSemana.getDate() + 6);
    fimSemana.setHours(23,59,59,999);

    return { inicioSemana, fimSemana };
}

/**
 * Agrupa devocionais por mês/ano
 * @param {Array} devocionais - Lista de devocionais
 * @param {string} filtro - Texto para filtrar
 * @returns {Array} Devocionais agrupados
 */
function agruparPorMes(devocionais, filtro = '') {
    const meses = {};
    
    const devocionaisFiltrados = filtro 
        ? devocionais.filter(devocional => {
            const tema = devocional.tema.toLowerCase();
            const texto = devocional.texto.toLowerCase();
            const dataFormatada = formatarData(devocional.data, true).toLowerCase();
            return tema.includes(filtro) || texto.includes(filtro) || dataFormatada.includes(filtro);
        })
        : [...devocionais];
    
    devocionaisFiltrados.forEach(devocional => {
        const data = new Date(devocional.data);
        const mesAno = new Intl.DateTimeFormat('pt-BR', { 
            month: 'long', 
            year: 'numeric', 
            timeZone: 'UTC' 
        }).format(data);

        const mesAnoFormatado = mesAno.charAt(0).toUpperCase() + mesAno.slice(1);
        
        if (!meses[mesAnoFormatado]) {
            meses[mesAnoFormatado] = [];
        }
        meses[mesAnoFormatado].push(devocional);
    });
    
    return Object.entries(meses)
        .map(([mesAno, devocionais]) => ({
            mesAno,
            devocionais: devocionais.sort((a, b) => new Date(b.data) - new Date(a.data))
        }))
        .sort((a, b) => new Date(b.devocionais[0].data) - new Date(a.devocionais[0].data));
}

/**
 * Mostra uma mensagem quando não há devocionais
 * @param {string} mensagem - Mensagem a ser exibida
 */
function mostrarMensagemVazia(mensagem) {
    const emptyMessage = document.createElement('p');
    emptyMessage.textContent = mensagem;
    emptyMessage.className = 'mensagem-vazia';
    elementos.historicoContainer.appendChild(emptyMessage);
}

// ============ INICIALIZAÇÃO DA APLICAÇÃO ============

document.addEventListener('DOMContentLoaded', () => {
    inicializarAplicacao();
});

function inicializarAplicacao() {
    try {
        document.body.classList.add('dark');
        localStorage.setItem('darkMode', 'enabled');
        estado.darkMode = true;
        
        configurarEventListeners();
        carregarDevocionais();
        configurarRadioButtons();
        
        console.log('✅ Aplicação inicializada com sucesso');
    } catch (error) {
        console.error('❌ Erro na inicialização:', error);
        mostrarNotificacao('Erro ao inicializar aplicação', 'error');
    }
}

// ============ CONFIGURAÇÃO DE EVENT LISTENERS ============

function configurarEventListeners() {
    // Botões principais
    elementos.newDevocionalBtn.addEventListener('click', handleNovoDevocional);
    elementos.toggleDarkMode.addEventListener('click', alternarTema);
    elementos.cancelarBtn.addEventListener('click', handleCancelar);
    elementos.salvarBtn.addEventListener('click', handleSalvarDevocional);
    elementos.confirmarLogin.addEventListener('click', handleConfirmarLogin);
    
    // Busca
    elementos.searchInput.addEventListener('input', handleBusca);
    
    // Foco na data
    elementos.inputData.addEventListener('focus', () => {
        if (!elementos.inputData.value) {
            const hoje = new Date();
            elementos.inputData.value = hoje.toISOString().split('T')[0];
        }
    });
    
    // Botões dos outros modais
    configurarBotoesModais();
    configurarRadioButtons();
}

function configurarBotoesModais() {
    // Modal de texto completo
    const fecharTextoBtn = document.getElementById('fecharTextoBtn');
    if (fecharTextoBtn) {
        fecharTextoBtn.addEventListener('click', fecharModalTexto);
    }
    
    // Modal de login
    const cancelarLoginBtn = document.getElementById('cancelarLoginBtn');
    if (cancelarLoginBtn) {
        cancelarLoginBtn.addEventListener('click', fecharLoginModal);
    }
    
    // Modal de confirmação de exclusão
    const cancelarExclusaoBtn = document.getElementById('cancelarExclusaoBtn');
    const confirmarExclusaoBtn = document.getElementById('confirmarExclusaoBtn');
    
    if (cancelarExclusaoBtn) {
        cancelarExclusaoBtn.addEventListener('click', fecharConfirmarExclusaoModal);
    }
    if (confirmarExclusaoBtn) {
        confirmarExclusaoBtn.addEventListener('click', handleConfirmarExclusao);
    }
}

function configurarRadioButtons() {
    document.querySelectorAll('input[name="ministradoPor"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const outroSelecionado = document.getElementById('radioOutro').checked;
            const vazioSelecionado = document.getElementById('radioVazio').checked;
            
            elementos.inputMinistradoPor.disabled = !outroSelecionado;
            
            if (outroSelecionado) {
                elementos.inputMinistradoPor.value = '';
                setTimeout(() => elementos.inputMinistradoPor.focus(), 100);
            } else if (vazioSelecionado) {
                elementos.inputMinistradoPor.value = '';
            } else {
                elementos.inputMinistradoPor.value = radio.value;
            }
        });
    });
}

// ============ GERENCIAMENTO DE MODAIS ============

function abrirModal(modalElement) {
    if (!modalElement) return;
    
    modalElement.classList.remove('hidden');
    modalElement.setAttribute('aria-hidden', 'false');
    
    const focusableElement = modalElement.querySelector('button, input, textarea, select');
    if (focusableElement) focusableElement.focus();
    
    const escHandler = (e) => {
        if (e.key === 'Escape') fecharModal(modalElement);
    };
    
    modalElement._escHandler = escHandler;
    document.addEventListener('keydown', escHandler);
}

function fecharModal(modalElement) {
    if (!modalElement) return;
    
    modalElement.classList.add('hidden');
    modalElement.setAttribute('aria-hidden', 'true');
    
    if (modalElement._escHandler) {
        document.removeEventListener('keydown', modalElement._escHandler);
        delete modalElement._escHandler;
    }
}

function abrirModalPrincipal() {
    elementos.modal.querySelector('#modal-titulo').textContent = 
        estado.devocionalEditando ? 'Editar Devocional' : 'Novo Devocional';
    abrirModal(elementos.modal);
    
    setTimeout(() => {
        if (estado.devocionalEditando) {
            elementos.inputTema.focus();
        } else {
            elementos.inputData.focus();
        }
    }, 100);
}

function fecharModalPrincipal() {
    fecharModal(elementos.modal);
    limparInputs();
}

function abrirModalTexto(conteudo) {
    elementos.modalTexto.querySelector('#modalTextoConteudo').textContent = 
        conteudo?.trim() || 'Nenhum texto disponível.';
    abrirModal(elementos.modalTexto);
}

function fecharModalTexto() {
    fecharModal(elementos.modalTexto);
}

function abrirLoginModal() {
    abrirModal(elementos.loginModal);
}

function fecharLoginModal() {
    fecharModal(elementos.loginModal);
    elementos.loginModal.querySelector('#usuarioLogin').value = '';
    elementos.loginModal.querySelector('#senhaLogin').value = '';
}

function abrirConfirmarExclusaoModal() {
    abrirModal(elementos.confirmarExclusaoModal);
}

function fecharConfirmarExclusaoModal() {
    fecharModal(elementos.confirmarExclusaoModal);
    estado.devocionalParaExcluir = null;
}

// ============ HANDLERS DE EVENTOS ============

function handleNovoDevocional() {
    estado.devocionalEditando = null;
    abrirModalPrincipal();
}

function handleCancelar() {
    fecharModalPrincipal();
}

function handleBusca(e) {
    const texto = e.target.value.trim().toLowerCase();
    if (texto !== estado.ultimaBusca) {
        estado.ultimaBusca = texto;
        atualizarHistorico();
    }
}

// ============ GERENCIAMENTO DE DEVOCIONAIS ============

async function carregarDevocionais() {
    try {
        mostrarNotificacao('Carregando devocionais...', 'info');
        
        const response = await fetch('listar_devocionais.php');
        if (!response.ok) throw new Error(`Erro HTTP: ${response.status}`);
        
        const result = await response.json();

        if (result.success) {
            estado.historicoDevocionais = Array.isArray(result.data) ? result.data : [];
            atualizarUltimoDevocional();
            atualizarHistorico();
            atualizarPainelDevocional();
            mostrarNotificacao('Devocionais carregados com sucesso', 'success');
        } else {
            throw new Error(result.message || 'Erro ao carregar devocionais');
        }
    } catch (error) {
        console.error('Erro ao carregar devocionais:', error);
        mostrarNotificacao('Erro ao carregar devocionais: ' + error.message, 'error');
    }
}

// ============ VALIDAÇÃO DO FORMULÁRIO ATUALIZADA ============

async function validarFormulario() {
    const data = elementos.inputData.value;
    const tema = elementos.inputTema.value.trim();
    const texto = elementos.inputTexto.value.trim();
    
    const ministradoPor = (() => {
        const selectedRadio = document.querySelector('input[name="ministradoPor"]:checked');
        if (selectedRadio) {
            if (selectedRadio.value === 'outro') {
                return elementos.inputMinistradoPor.value.trim();
            }
            return selectedRadio.value;
        }
        return '';
    })();

    // Validações básicas
    if (!data) {
        mostrarNotificacao('Selecione uma data', 'warning');
        elementos.inputData.focus();
        return null;
    }
    
    if (!tema) {
        mostrarNotificacao('Digite o tema do devocional', 'warning');
        elementos.inputTema.focus();
        return null;
    }



    // Verificar se ministrante está vazio e pedir confirmação
    if (!ministradoPor) {
        const confirmarVazio = await mostrarConfirmacao(
            'Ministrante não especificado',
            'Você está salvando sem especificar quem ministrou. Deseja continuar?',
            'info'
        );
        
        if (!confirmarVazio) {
            document.querySelector('input[name="ministradoPor"]').focus();
            return null;
        }
    }

    return { data, tema, texto, ministradoPor };
}

async function handleSalvarDevocional() {
    const dados = await validarFormulario();
    if (!dados) return;

    try {
        const formData = new FormData();
        formData.append('data', dados.data);
        formData.append('tema', dados.tema);
        formData.append('texto', dados.texto);
        formData.append('ministrado_por', dados.ministradoPor);

        if (estado.devocionalEditando) {
            formData.append('id', estado.devocionalEditando.id);
        }

        const response = await fetch('salvar_devocional.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            mostrarNotificacao('Devocional salvo com sucesso!', 'success');
            fecharModalPrincipal();
            await carregarDevocionais();
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        console.error('Erro ao salvar devocional:', error);
        mostrarNotificacao('Erro ao salvar devocional: ' + error.message, 'error');
    }
}

// ============ FUNÇÃO DE CONFIRMAÇÃO ============

function mostrarConfirmacao(titulo, mensagem, tipo = 'warning') {
    return new Promise((resolve) => {
        let confirmModal = document.getElementById('confirmModal');
        
        if (!confirmModal) {
            confirmModal = document.createElement('div');
            confirmModal.id = 'confirmModal';
            confirmModal.className = 'modal hidden';
            confirmModal.innerHTML = `
                <div class="modal-content">
                    <h2 id="confirmModal-titulo">${titulo}</h2>
                    <p id="confirmModal-mensagem">${mensagem}</p>
                    <div class="modal-actions">
                        <button id="confirmModal-cancelar" class="btn btn-secondary">Cancelar</button>
                        <button id="confirmModal-confirmar" class="btn btn-primary">Confirmar</button>
                    </div>
                </div>
            `;
            document.body.appendChild(confirmModal);
        }

        confirmModal.querySelector('#confirmModal-titulo').textContent = titulo;
        confirmModal.querySelector('#confirmModal-mensagem').textContent = mensagem;

        confirmModal.classList.remove('hidden');
        confirmModal.setAttribute('aria-hidden', 'false');

        const cancelarBtn = confirmModal.querySelector('#confirmModal-cancelar');
        const confirmarBtn = confirmModal.querySelector('#confirmModal-confirmar');

        const cleanup = () => {
            confirmModal.classList.add('hidden');
            confirmModal.setAttribute('aria-hidden', 'true');
            cancelarBtn.onclick = null;
            confirmarBtn.onclick = null;
        };

        cancelarBtn.onclick = () => {
            cleanup();
            resolve(false);
        };

        confirmarBtn.onclick = () => {
            cleanup();
            resolve(true);
        };

        const escHandler = (e) => {
            if (e.key === 'Escape') {
                cleanup();
                resolve(false);
            }
        };
        
        document.addEventListener('keydown', escHandler);
        confirmModal._escHandler = escHandler;
    });
}

// ============ FUNÇÕES AUXILIARES ============

/**
 * Limpa os inputs do formulário
 */
function limparInputs() {
    if (!estado.devocionalEditando) {
        elementos.inputData.value = '';
        document.querySelectorAll('input[name="ministradoPor"]').forEach(radio => {
            radio.checked = false;
        });
        elementos.inputMinistradoPor.value = '';
        elementos.inputMinistradoPor.disabled = true;
    }
    elementos.inputTema.value = '';
    elementos.inputTexto.value = '';
}

/**
 * Preenche o formulário com dados do devocional para edição
 * @param {Object} devocional - Dados do devocional
 */
function preencherFormularioEdicao(devocional) {
    elementos.inputData.value = devocional.data;
    elementos.inputTema.value = devocional.tema;
    elementos.inputTexto.value = devocional.texto;
    
    const ministrantes = ['Maicon', 'Daniel Feix', 'Antônio'];
    
    if (ministrantes.includes(devocional.ministrado_por)) {
        document.querySelector(`input[name="ministradoPor"][value="${devocional.ministrado_por}"]`).checked = true;
        elementos.inputMinistradoPor.value = devocional.ministrado_por;
        elementos.inputMinistradoPor.disabled = true;
    } else if (devocional.ministrado_por && devocional.ministrado_por.trim() !== '') {
        document.getElementById('radioOutro').checked = true;
        elementos.inputMinistradoPor.value = devocional.ministrado_por;
        elementos.inputMinistradoPor.disabled = false;
    } else {
        document.getElementById('radioVazio').checked = true;
        elementos.inputMinistradoPor.value = '';
        elementos.inputMinistradoPor.disabled = true;
    }
}

// ============ ATUALIZAÇÃO DE INTERFACE ============

/**
 * Atualiza o devocional da semana atual
 */
function atualizarUltimoDevocional() {
    if (!elementos.infoDevocional) return;

    if (estado.historicoDevocionais.length > 0) {
        const { inicioSemana, fimSemana } = getSemanaIntervalo(new Date());
        const daSemana = estado.historicoDevocionais.find(d => {
            const dataDev = parseData(d.data);
            return dataDev >= inicioSemana && dataDev <= fimSemana;
        });

        if (daSemana) {
            elementos.infoDevocional.innerHTML = `
                <h3>${escapeHTML(daSemana.tema)}</h3>
                <p><strong>${formatarData(daSemana.data)}</strong></p>
                <p><em>Ministrado por: ${escapeHTML(daSemana.ministrado_por || '')}</em></p>
            `;
        } else {
            elementos.infoDevocional.textContent = 'Nenhum devocional desta semana cadastrado ainda.';
        }
    } else {
        elementos.infoDevocional.textContent = 'Nenhum devocional cadastrado ainda.';
    }
}

/**
 * Atualiza o histórico de devocionais na interface
 */
function atualizarHistorico() {
    if (!elementos.historicoContainer) return;
    
    const filtro = elementos.searchInput ? elementos.searchInput.value.toLowerCase() : '';
    elementos.historicoContainer.innerHTML = '';

    if (!estado.historicoDevocionais || estado.historicoDevocionais.length === 0) {
        mostrarMensagemVazia('Nenhum devocional encontrado.');
        return;
    }

    const devocionaisAgrupados = agruparPorMes(estado.historicoDevocionais, filtro);

    if (devocionaisAgrupados.length === 0) {
        mostrarMensagemVazia('Nenhum devocional encontrado para esta busca.');
        return;
    }

    devocionaisAgrupados.forEach(criarSecaoMes);
    configurarBotoesAcao();
}

/**
 * Cria a seção de um mês no histórico
 * @param {Object} grupo - Grupo de devocionais do mês
 */
function criarSecaoMes(grupo) {
    const monthSection = document.createElement('div');
    monthSection.className = 'month-section';

    monthSection.innerHTML = `
        <div class="month-header">
            <span>${escapeHTML(grupo.mesAno)}</span>
            <span>${grupo.devocionais.length} devocional${grupo.devocionais.length !== 1 ? 's' : ''}</span>
        </div>
        <div class="month-content">
            <ul class="month-list">
                ${grupo.devocionais.map(criarItemDevocional).join('')}
            </ul>
        </div>
    `;

    elementos.historicoContainer.appendChild(monthSection);
}

/**
 * Cria um item de devocional para a lista
 * @param {Object} devocional - Dados do devocional
 * @returns {string} HTML do item
 */
function criarItemDevocional(devocional) {
    return `
        <li>
            <div class="devocional-info">
                <div class="devocional-date">${formatarData(devocional.data, true)}</div>
                <div class="devocional-tema">${escapeHTML(devocional.tema)}</div>
               <div class="ministrado-por">${devocional.ministrado_por ? escapeHTML(devocional.ministrado_por) : ''}</div>
                <div class="botoes-acao">
                    <button class="ver-texto-btn" data-texto="${escapeHTML(devocional.texto || '')}">
                        Ver texto
                    </button>
                    <button class="edit-btn" data-id="${devocional.id}">
                        <span class="material-icons">edit</span> Editar
                    </button>
                    <button class="delete-btn" data-id="${devocional.id}">
                        <span class="material-icons">delete</span> Excluir
                    </button>
                </div>
            </div>
        </li>
    `;
}

/**
 * Atualiza o painel de devocionais (anterior, atual, próximo)
 */
function atualizarPainelDevocional() {
    if (!estado.historicoDevocionais || estado.historicoDevocionais.length === 0) return;

    const hoje = new Date();
    const { inicioSemana, fimSemana } = getSemanaIntervalo(hoje);
    const ordenados = [...estado.historicoDevocionais].sort((a, b) => parseData(a.data) - parseData(b.data));

    const atual = ordenados.find(d => {
        const dataDev = parseData(d.data);
        return dataDev >= inicioSemana && dataDev <= fimSemana;
    });

    let anterior = null, proximo = null;
    if (atual) {
        anterior = ordenados.findLast(d => parseData(d.data) < inicioSemana);
        proximo = ordenados.find(d => parseData(d.data) > fimSemana);
    }

    // Atualizar elementos do painel
    if (elementos.devocionalAnterior) {
        elementos.devocionalAnterior.innerHTML = anterior 
            ? `<strong>${escapeHTML(anterior.tema)}</strong><br>${formatarData(anterior.data)}<br><em>${escapeHTML(anterior.ministrado_por || '')}</em>`
            : "Nenhum devocional anterior";
    }

    if (elementos.devocionalAtual) {
        elementos.devocionalAtual.innerHTML = atual 
            ? `<strong>${escapeHTML(atual.tema)}</strong><br>${formatarData(atual.data)}<br><em>${escapeHTML(atual.ministrado_por || '')}</em>`
            : "Nenhum devocional atual";
    }

    if (elementos.devocionalProximo) {
        elementos.devocionalProximo.innerHTML = proximo 
            ? `<strong>${escapeHTML(proximo.tema)}</strong><br>${formatarData(proximo.data)}<br><em>${escapeHTML(proximo.ministrado_por || '')}</em>`
            : "Nenhum devocional futuro";
    }
}

/**
 * Configura os botões de ação nos itens do histórico
 */
function configurarBotoesAcao() {
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.onclick = (e) => {
            const id = e.currentTarget.getAttribute('data-id');
            editarDevocional(id);
        };
    });

    document.querySelectorAll('.ver-texto-btn').forEach(btn => {
        btn.onclick = () => {
            const texto = btn.getAttribute('data-texto');
            abrirModalTexto(texto);
        };
    });

    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.onclick = (e) => {
            const id = e.currentTarget.getAttribute('data-id');
            confirmarExclusao(id);
        };
    });
}

// ============ FUNÇÕES DE EDIÇÃO E EXCLUSÃO ============

/**
 * Prepara a edição de um devocional
 * @param {string} id - ID do devocional a ser editado
 */
function editarDevocional(id) {
    sessionStorage.setItem('devocionalParaEditar', id);
    abrirLoginModal();
    elementos.loginModal.querySelector('#usuarioLogin').focus();
}

/**
 * Handle de confirmação de login para edição
 */
async function handleConfirmarLogin() {
    const usuario = elementos.loginModal.querySelector('#usuarioLogin').value.trim();
    const senha = elementos.loginModal.querySelector('#senhaLogin').value.trim();

    if (!usuario || !senha) {
        mostrarNotificacao('Informe usuário e senha', 'warning');
        return;
    }

    try {
        const resposta = await fetch('verificar_usuario.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `nome=${encodeURIComponent(usuario)}&senha=${encodeURIComponent(senha)}`
        });

        const resultado = await resposta.json();

        if (!resultado.success && usuario !== "admdevocional") {
            mostrarNotificacao('Acesso negado!', 'error');
            window.location.href = 'inicio.html';
            return;
        }

        const id = sessionStorage.getItem('devocionalParaEditar');
        const devocional = estado.historicoDevocionais.find(d => d.id == id);
        
        if (devocional) {
            estado.devocionalEditando = devocional;
            preencherFormularioEdicao(devocional);
            fecharLoginModal();
            abrirModalPrincipal();
        } else {
            throw new Error('Devocional não encontrado');
        }
    } catch (error) {
        console.error('Erro ao verificar usuário:', error);
        mostrarNotificacao('Erro ao verificar usuário', 'error');
    }
}

/**
 * Confirma a exclusão de um devocional
 * @param {string} id - ID do devocional a ser excluído
 */
function confirmarExclusao(id) {
    estado.devocionalParaExcluir = id;
    abrirConfirmarExclusaoModal();
}

/**
 * Handle de confirmação de exclusão
 */
async function handleConfirmarExclusao() {
    if (!estado.devocionalParaExcluir) return;
    
    const usuario = prompt("Digite seu nome de usuário:");
    const senha = prompt("Digite sua senha:");
    
    if (!usuario || !senha) {
        mostrarNotificacao('Usuário e senha são obrigatórios', 'error');
        return;
    }

    try {
        const response = await fetch('verificar_exclusao.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${estado.devocionalParaExcluir}&usuario=${encodeURIComponent(usuario)}&senha=${encodeURIComponent(senha)}`
        });

        const result = await response.json();

        if (result.success) {
            mostrarNotificacao('Devocional excluído com sucesso!', 'success');
            await carregarDevocionais();
        } else {
            throw new Error(result.message || 'Não tem permissão para excluir');
        }
    } catch (error) {
        console.error('Erro ao excluir devocional:', error);
        mostrarNotificacao(error.message, 'error');
    } finally {
        fecharConfirmarExclusaoModal();
    }
}

// ============ EXPORTAÇÃO PARA USO GLOBAL ============
// Torna as funções disponíveis globalmente para os event handlers do HTML
window.fecharModalTexto = fecharModalTexto;
window.fecharLoginModal = fecharLoginModal;

console.log('🚀 Script de devocionais carregado e pronto!');

// ============ SISTEMA DE FILTROS E PAGINAÇÃO DO HISTÓRICO ============

// Estado da paginação e filtros
const estadoFiltros = {
    paginaAtual: 1,
    itensPorPagina: 20,
    dataInicio: '',
    dataFim: '',
    buscaTexto: '',
    ministranteFiltro: '',
    devocionalsFiltrados: []
};

/**
 * Inicializa o sistema de filtros e paginação
 */
function inicializarFiltros() {
    // Definir intervalo do mês atual como padrão
    const hoje = new Date();
    const primeiroDiaMes = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    const ultimoDiaMes = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0);
    
    const dataInicioFormatada = primeiroDiaMes.toISOString().split('T')[0];
    const dataFimFormatada = ultimoDiaMes.toISOString().split('T')[0];
    
    document.getElementById('filtroDataInicio').value = dataInicioFormatada;
    document.getElementById('filtroDataFim').value = dataFimFormatada;
    estadoFiltros.dataInicio = dataInicioFormatada;
    estadoFiltros.dataFim = dataFimFormatada;
    
    // Event listeners dos filtros - APLICAÇÃO AUTOMÁTICA
    document.getElementById('filtroDataInicio').addEventListener('change', () => {
        estadoFiltros.dataInicio = document.getElementById('filtroDataInicio').value;
        estadoFiltros.paginaAtual = 1;
        aplicarFiltros();
    });
    
    document.getElementById('filtroDataFim').addEventListener('change', () => {
        estadoFiltros.dataFim = document.getElementById('filtroDataFim').value;
        estadoFiltros.paginaAtual = 1;
        aplicarFiltros();
    });
    
    document.getElementById('filtroBuscaTexto').addEventListener('input', 
        debounce(() => {
            estadoFiltros.buscaTexto = document.getElementById('filtroBuscaTexto').value.toLowerCase();
            estadoFiltros.paginaAtual = 1;
            aplicarFiltros();
        }, 500)
    );
    
    document.getElementById('filtroMinistrante').addEventListener('change', () => {
        estadoFiltros.ministranteFiltro = document.getElementById('filtroMinistrante').value;
        estadoFiltros.paginaAtual = 1;
        aplicarFiltros();
    });
    
    // Botão limpar
    document.getElementById('btnLimparFiltros').addEventListener('click', limparFiltros);
    
    // Event listeners da paginação
    document.getElementById('btnPaginaAnterior').addEventListener('click', () => {
        if (estadoFiltros.paginaAtual > 1) {
            estadoFiltros.paginaAtual--;
            renderizarHistoricoComPaginacao();
        }
    });
    
    document.getElementById('btnPaginaProxima').addEventListener('click', () => {
        const totalPaginas = Math.ceil(estadoFiltros.devocionalsFiltrados.length / estadoFiltros.itensPorPagina);
        if (estadoFiltros.paginaAtual < totalPaginas) {
            estadoFiltros.paginaAtual++;
            renderizarHistoricoComPaginacao();
        }
    });
    
    document.getElementById('itensPorPagina').addEventListener('change', (e) => {
        estadoFiltros.itensPorPagina = parseInt(e.target.value);
        estadoFiltros.paginaAtual = 1;
        renderizarHistoricoComPaginacao();
    });
}

/**
 * Debounce para otimizar busca
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Aplica os filtros selecionados
 */
function aplicarFiltros() {
    estadoFiltros.dataInicio = document.getElementById('filtroDataInicio').value;
    estadoFiltros.dataFim = document.getElementById('filtroDataFim').value;
    estadoFiltros.buscaTexto = document.getElementById('filtroBuscaTexto').value.toLowerCase();
    estadoFiltros.ministranteFiltro = document.getElementById('filtroMinistrante').value;
    
    filtrarDevocionais();
    renderizarHistoricoComPaginacao();
}

/**
 * Limpa todos os filtros
 */
function limparFiltros() {
    const hoje = new Date();
    const primeiroDiaMes = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    const ultimoDiaMes = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0);
    
    const dataInicioFormatada = primeiroDiaMes.toISOString().split('T')[0];
    const dataFimFormatada = ultimoDiaMes.toISOString().split('T')[0];
    
    document.getElementById('filtroDataInicio').value = dataInicioFormatada;
    document.getElementById('filtroDataFim').value = dataFimFormatada;
    document.getElementById('filtroBuscaTexto').value = '';
    document.getElementById('filtroMinistrante').value = '';
    
    estadoFiltros.dataInicio = dataInicioFormatada;
    estadoFiltros.dataFim = dataFimFormatada;
    estadoFiltros.buscaTexto = '';
    estadoFiltros.ministranteFiltro = '';
    estadoFiltros.paginaAtual = 1;
    
    aplicarFiltros();
}

/**
 * Filtra os devocionais com base nos critérios
 */
function filtrarDevocionais() {
    let resultado = [...estado.historicoDevocionais];
    
    // Filtro por intervalo de datas
    if (estadoFiltros.dataInicio && estadoFiltros.dataFim) {
        const dataInicioObj = new Date(estadoFiltros.dataInicio);
        const dataFimObj = new Date(estadoFiltros.dataFim);
        
        resultado = resultado.filter(d => {
            const dataDevocional = new Date(d.data);
            return dataDevocional >= dataInicioObj && dataDevocional <= dataFimObj;
        });
    } else if (estadoFiltros.dataInicio) {
        const dataInicioObj = new Date(estadoFiltros.dataInicio);
        resultado = resultado.filter(d => new Date(d.data) >= dataInicioObj);
    } else if (estadoFiltros.dataFim) {
        const dataFimObj = new Date(estadoFiltros.dataFim);
        resultado = resultado.filter(d => new Date(d.data) <= dataFimObj);
    }
    
    // Filtro por texto
    if (estadoFiltros.buscaTexto) {
        resultado = resultado.filter(d => 
            d.tema.toLowerCase().includes(estadoFiltros.buscaTexto) ||
            (d.texto && d.texto.toLowerCase().includes(estadoFiltros.buscaTexto))
        );
    }
    
    // Filtro por ministrante
    if (estadoFiltros.ministranteFiltro) {
        if (estadoFiltros.ministranteFiltro === 'outros') {
            const conhecidos = ['Maicon', 'Daniel Feix', 'Antônio'];
            resultado = resultado.filter(d => 
                d.ministrado_por && 
                d.ministrado_por.trim() !== '' && 
                !conhecidos.includes(d.ministrado_por)
            );
        } else {
            resultado = resultado.filter(d => 
                d.ministrado_por === estadoFiltros.ministranteFiltro
            );
        }
    }
    
    // Ordenar por data (mais recente primeiro)
    resultado.sort((a, b) => new Date(b.data) - new Date(a.data));
    
    estadoFiltros.devocionalsFiltrados = resultado;
    
    // Atualizar texto informativo
    let periodoTexto = '';
    if (estadoFiltros.dataInicio && estadoFiltros.dataFim) {
        const dataInicioFormatada = formatarData(estadoFiltros.dataInicio, true);
        const dataFimFormatada = formatarData(estadoFiltros.dataFim, true);
        periodoTexto = `de ${dataInicioFormatada} até ${dataFimFormatada}`;
    } else if (estadoFiltros.dataInicio) {
        periodoTexto = `a partir de ${formatarData(estadoFiltros.dataInicio, true)}`;
    } else if (estadoFiltros.dataFim) {
        periodoTexto = `até ${formatarData(estadoFiltros.dataFim, true)}`;
    } else {
        periodoTexto = 'de todos os períodos';
    }
    
    document.getElementById('resultadosFiltro').textContent = 
        `${resultado.length} devocional${resultado.length !== 1 ? 'is' : ''} encontrado${resultado.length !== 1 ? 's' : ''} ${periodoTexto}`;
}

/**
 * Renderiza o histórico com paginação em formato tabela Excel
 */
function renderizarHistoricoComPaginacao() {
    if (!elementos.historicoContainer) return;
    
    elementos.historicoContainer.innerHTML = '';
    
    if (estadoFiltros.devocionalsFiltrados.length === 0) {
        mostrarMensagemVazia('Nenhum devocional encontrado com os filtros aplicados.');
        atualizarControlesPaginacao(0, 0);
        return;
    }
    
    // Calcular paginação
    const inicio = (estadoFiltros.paginaAtual - 1) * estadoFiltros.itensPorPagina;
    const fim = inicio + estadoFiltros.itensPorPagina;
    const devocionalsPagina = estadoFiltros.devocionalsFiltrados.slice(inicio, fim);
    
    // Criar tabela estilo Excel
    const tabelaContainer = document.createElement('div');
    tabelaContainer.className = 'tabela-excel-container';
    
    let tabelaHTML = `
        <table class="tabela-excel">
            <thead>
                <tr>
                    <th style="width: 120px;">Data</th>
                    <th style="width: 35%;">Tema</th>
                    <th style="width: 25%;">Ministrado Por</th>
                    <th style="width: 280px;">Ações</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    devocionalsPagina.forEach(devocional => {
        tabelaHTML += `
            <tr>
                <td class="tabela-excel-data">${formatarData(devocional.data, true)}</td>
                <td class="tabela-excel-tema">${escapeHTML(devocional.tema)}</td>
                <td class="tabela-excel-ministrado">${devocional.ministrado_por ? escapeHTML(devocional.ministrado_por) : '-'}</td>
                <td>
                    <div class="tabela-excel-acoes">
                        <button class="tabela-excel-btn tabela-excel-btn-ver ver-texto-btn" 
                                data-texto="${escapeHTML(devocional.texto || '')}"
                                title="Ver texto completo">
                            <span class="material-icons">visibility</span>
                            Ver
                        </button>
                        <button class="tabela-excel-btn tabela-excel-btn-editar edit-btn" 
                                data-id="${devocional.id}"
                                title="Editar devocional">
                            <span class="material-icons">edit</span>
                            Editar
                        </button>
                        <button class="tabela-excel-btn tabela-excel-btn-excluir delete-btn" 
                                data-id="${devocional.id}"
                                title="Excluir devocional">
                            <span class="material-icons">delete</span>
                            Excluir
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    tabelaHTML += `
            </tbody>
        </table>
    `;
    
    tabelaContainer.innerHTML = tabelaHTML;
    elementos.historicoContainer.appendChild(tabelaContainer);
    
    configurarBotoesAcao();
    
    atualizarControlesPaginacao(
        estadoFiltros.devocionalsFiltrados.length,
        Math.ceil(estadoFiltros.devocionalsFiltrados.length / estadoFiltros.itensPorPagina)
    );
}

/**
 * Atualiza os controles de paginação
 */
function atualizarControlesPaginacao(totalItens, totalPaginas) {
    // Atualizar texto informativo
    const inicio = (estadoFiltros.paginaAtual - 1) * estadoFiltros.itensPorPagina + 1;
    const fim = Math.min(estadoFiltros.paginaAtual * estadoFiltros.itensPorPagina, totalItens);
    
    document.getElementById('paginacaoTexto').textContent = 
        totalItens > 0 
            ? `Página ${estadoFiltros.paginaAtual} de ${totalPaginas} (${inicio}-${fim} de ${totalItens})`
            : 'Nenhum registro';
    
    // Atualizar botões
    document.getElementById('btnPaginaAnterior').disabled = estadoFiltros.paginaAtual === 1;
    document.getElementById('btnPaginaProxima').disabled = estadoFiltros.paginaAtual === totalPaginas || totalPaginas === 0;
    
    // Renderizar números de página
    renderizarNumerosPagina(totalPaginas);
}

/**
 * Renderiza os números de página
 */
function renderizarNumerosPagina(totalPaginas) {
    const container = document.getElementById('numeroPaginas');
    container.innerHTML = '';
    
    if (totalPaginas <= 1) return;
    
    const maxBotoes = 5;
    let inicio = Math.max(1, estadoFiltros.paginaAtual - Math.floor(maxBotoes / 2));
    let fim = Math.min(totalPaginas, inicio + maxBotoes - 1);
    
    if (fim - inicio < maxBotoes - 1) {
        inicio = Math.max(1, fim - maxBotoes + 1);
    }
    
    for (let i = inicio; i <= fim; i++) {
        const btn = document.createElement('button');
        btn.className = `pagina-numero ${i === estadoFiltros.paginaAtual ? 'ativa' : ''}`;
        btn.textContent = i;
        btn.addEventListener('click', () => {
            estadoFiltros.paginaAtual = i;
            renderizarHistoricoComPaginacao();
        });
        container.appendChild(btn);
    }
}

/**
 * Substitui a função atualizarHistorico original
 */
const atualizarHistoricoOriginal = atualizarHistorico;
atualizarHistorico = function() {
    if (document.getElementById('filtroDataInicio')) {
        // Usar novo sistema com filtros e paginação
        filtrarDevocionais();
        renderizarHistoricoComPaginacao();
    } else {
        // Fallback para o sistema original se os elementos não existirem
        atualizarHistoricoOriginal();
    }
};

// ============ INICIALIZAÇÃO DOS FILTROS ============
const inicializarAplicacaoOriginal = inicializarAplicacao;
inicializarAplicacao = function() {
    inicializarAplicacaoOriginal();
    
    if (document.getElementById('filtroDataInicio')) {
        inicializarFiltros();
        aplicarFiltros();
    }
};

console.log('✅ Sistema de filtros e paginação carregado!');