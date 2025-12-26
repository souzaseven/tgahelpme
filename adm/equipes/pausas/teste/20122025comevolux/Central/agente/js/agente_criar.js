// ===================================================
// agente_criar.js — Criar Agente (Versão Premium)
// Versão: 2.1.0 | Data: 2025.12.22
// Descrição: Criação de agentes + carregamento dinâmico de filas
// ===================================================


// ===================================================
// 1.0 CONSTANTES E CONFIGURAÇÕES
// ===================================================
const API_ENDPOINT   = 'backend/criar_agente.php';
const FILAS_ENDPOINT = 'backend/listar_filas.php';

const VALIDATE_LOGIN_REGEX = /^[a-zA-Z0-9._-]+$/;
const VALIDATE_RAMAL_REGEX = /^\d*$/;


// ===================================================
// 2.0 FUNÇÃO PRINCIPAL - CRIAR AGENTE
// ===================================================
async function criarAgente(e, dadosCustom = null) {
    try {
        if (e && e.preventDefault) e.preventDefault();

        const dados = dadosCustom || coletarDadosFormulario();

        const validacao = validarDadosAgente(dados);
        if (!validacao.valido) {
            throw new Error(validacao.mensagem);
        }

        iniciarProcessamento();

        const resposta = await enviarParaBackend(dados);

        processarResposta(resposta, e?.target);

        return resposta;

    } catch (erro) {
        lidarComErro(erro);
        throw erro;
    }
}


// ===================================================
// 3.0 COLETA E VALIDAÇÃO DE DADOS
// ===================================================

/**
 * Coleta dados do formulário
 */
function coletarDadosFormulario() {
    return {
        nome:  document.getElementById('nome')?.value.trim()  || '',
        login: document.getElementById('login')?.value.trim() || '',
        ramal: document.getElementById('ramal')?.value.trim() || '',
        fila:  document.getElementById('fila')?.value        || ''
    };
}

/**
 * Validação dos dados do agente
 */
function validarDadosAgente(dados) {
    if (!dados.nome || dados.nome.length < 3) {
        return { valido: false, mensagem: 'O nome deve ter pelo menos 3 caracteres' };
    }

    if (!dados.login) {
        return { valido: false, mensagem: 'O login é obrigatório' };
    }

    if (!VALIDATE_LOGIN_REGEX.test(dados.login)) {
        return { valido: false, mensagem: 'Login inválido. Use letras, números, . _ -' };
    }

    if (dados.ramal && !VALIDATE_RAMAL_REGEX.test(dados.ramal)) {
        return { valido: false, mensagem: 'O ramal deve conter apenas números' };
    }

    if (!dados.fila) {
        return { valido: false, mensagem: 'Selecione uma fila de atendimento' };
    }

    return { valido: true };
}


// ===================================================
// 4.0 FILAS — CARREGAMENTO DINÂMICO
// ===================================================

/**
 * Carrega filas do backend e popula o select
 */
async function carregarFilas() {
    const selectFila = document.getElementById('fila');
    if (!selectFila) return;

    selectFila.innerHTML = '<option value="">Carregando filas...</option>';
    selectFila.disabled = true;

    try {
        const resp = await fetch(FILAS_ENDPOINT, { cache: 'no-store' });
        const json = await resp.json();

        if (!resp.ok || !json.success || !Array.isArray(json.filas)) {
            throw new Error(json.erro || 'Erro ao carregar filas');
        }

        selectFila.innerHTML = '<option value="">Selecione uma fila</option>';

        json.filas.forEach(fila => {
            const opt = document.createElement('option');
            opt.value = fila.id;       // ID real da Evolux
            opt.textContent = fila.name;
            selectFila.appendChild(opt);
        });

        selectFila.disabled = false;

    } catch (erro) {
        console.error('[Filas] Erro:', erro);
        selectFila.innerHTML = '<option value="">Erro ao carregar filas</option>';
        selectFila.disabled = true;
    }
}


// ===================================================
// 5.0 BACKEND / COMUNICAÇÃO
// ===================================================

async function enviarParaBackend(dados) {
    const resposta = await fetch(API_ENDPOINT, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(dados)
    });

    const texto = await resposta.text();
    let json;

    try {
        json = JSON.parse(texto);
    } catch {
        throw new Error('Resposta do servidor não é JSON');
    }

    if (!resposta.ok || !json.success) {
        throw new Error(json.erro || `Erro HTTP ${resposta.status}`);
    }

    return json;
}


// ===================================================
// 6.0 FEEDBACK VISUAL
// ===================================================

function iniciarProcessamento() {
    const r = document.getElementById('resultado-criacao');
    if (!r) return;

    r.className = 'result-message result-loading';
    r.innerHTML = `<span class="spinner"></span><span>Processando...</span>`;
    r.style.display = 'flex';
}

function processarResposta(resposta, formulario = null) {
    const r = document.getElementById('resultado-criacao');
    if (!r) return;

    r.className = 'result-message result-success';
    r.innerHTML = `✅ ${resposta.mensagem || 'Agente criado com sucesso!'}`;

    if (formulario) {
        setTimeout(() => formulario.reset(), 100);
    }

    window.dispatchEvent(new CustomEvent('agente-criado', { detail: resposta }));
}

function lidarComErro(erro) {
    const r = document.getElementById('resultado-criacao');
    if (!r) return;

    r.className = 'result-message result-error';
    r.innerHTML = `❌ ${erro.message || 'Erro ao criar agente'}`;
    r.style.display = 'flex';

    console.error('[Criar Agente]', erro);
}


// ===================================================
// 7.0 VALIDAÇÃO EM TEMPO REAL
// ===================================================

function configurarValidacaoTempoReal() {
    const campos = document.querySelectorAll(
        '#form-criar-agente input, #form-criar-agente select'
    );

    campos.forEach(campo => {
        campo.addEventListener('blur', () => validarCampoTempoReal(campo.id));
        campo.addEventListener('input', () => {
            campo.classList.remove('input-error');
            const erro = document.getElementById(`${campo.id}-error`);
            if (erro) erro.style.display = 'none';
        });
    });
}

function validarCampoTempoReal(id) {
    const campo = document.getElementById(id);
    const erro  = document.getElementById(`${id}-error`);
    if (!campo || !erro) return true;

    let msg = '';

    if (campo.hasAttribute('required') && !campo.value.trim()) {
        msg = 'Campo obrigatório';
    }

    if (id === 'login' && campo.value && !VALIDATE_LOGIN_REGEX.test(campo.value)) {
        msg = 'Login inválido';
    }

    if (id === 'ramal' && campo.value && !VALIDATE_RAMAL_REGEX.test(campo.value)) {
        msg = 'Apenas números';
    }

    if (msg) {
        campo.classList.add('input-error');
        erro.textContent = msg;
        erro.style.display = 'block';
        return false;
    }

    return true;
}


// ===================================================
// 8.0 INICIALIZAÇÃO
// ===================================================

function inicializarFormularioCriacao() {
    const form = document.getElementById('form-criar-agente');
    if (!form) return;

    carregarFilas();
    configurarValidacaoTempoReal();
    form.addEventListener('submit', criarAgente);

    console.log('[Criar Agente] Formulário inicializado');
}

document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', inicializarFormularioCriacao)
    : inicializarFormularioCriacao();


// ===================================================
// 9.0 EXPORTS
// ===================================================
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        criarAgente,
        carregarFilas
    };
}
