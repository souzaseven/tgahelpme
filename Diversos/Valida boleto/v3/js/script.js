/* ===== ORQUESTRAÇÃO / DOM DO VALIDADOR DE BOLETO ===== */
/* Este arquivo só cuida de eventos e manipulação de tela. Toda a lógica  */
/* de normalização, detecção de formato e validação vive em boleto.js    */
/* (window.BoletoLib), que não conhece o DOM.                            */

(function () {
    'use strict';

    /* ===== REFERÊNCIAS DE DOM ===== */
    const inputEl = document.getElementById('codigo-input');
    const contadorEl = document.getElementById('contador');
    const btnColar = document.getElementById('btn-colar');
    const btnLimpar = document.getElementById('btn-limpar');
    const btnExemplo = document.getElementById('btn-exemplo');
    const statusBanner = document.getElementById('status-banner');
    const resumoGrid = document.getElementById('resumo-grid');
    const btnDetalhes = document.getElementById('btn-detalhes');
    const detalhesPanel = document.getElementById('detalhes-panel');
    const campoBlocksEl = document.getElementById('campo-blocks');
    const diagnosticTableEl = document.getElementById('diagnostic-table');
    const campoLivreEl = document.getElementById('campo-livre-info');
    const codigoBarrasRowEl = document.getElementById('codigo-barras-row');
    const toastEl = document.getElementById('toast');

    // Exemplo válido usado pelo botão "Usar exemplo" — gerado e conferido
    // com o próprio BoletoLib (ver TESTES.md, Caso 1).
    const LINHA_EXEMPLO = '34191.23454 67890.123457 67890.123457 8 07000000123456';

    /* ===== HIGHLIGHT CRUZADO (Resultado <-> Estrutura) ===== */
    // Mesmas posições usadas em BoletoLib.decodificarClassico (linha de
    // cobrança de 47 dígitos), repetidas aqui só para montar a visualização —
    // cada posição pertence a exatamente um campo, sem sobreposição.
    const CAMPO_RANGES_COBRANCA = [
        ['codigoBanco', 0, 3], ['codigoMoeda', 3, 4], ['comOuSemRegistro', 4, 5], ['tipoCarteira', 5, 6],
        ['nossoNumero', 6, 14], ['dvNossoNumero', 14, 15], ['dvPrimeiroCampo', 15, 16],
        ['cooperativa', 16, 20], ['dvSegundoCampo', 20, 21], ['posto', 21, 23], ['cedente', 23, 28],
        ['comOuSemValor', 28, 29], ['campoFixo', 29, 30], ['dvCampoLivre', 30, 31], ['dvTerceiroCampo', 31, 32],
        ['dvGeral', 32, 33], ['fatorVencimento', 33, 37], ['valorDocumento', 37, 47]
    ];
    const CAMPO_POR_INDICE = new Array(47);
    CAMPO_RANGES_COBRANCA.forEach(function (faixa) {
        for (let i = faixa[1]; i < faixa[2]; i++) CAMPO_POR_INDICE[i] = faixa[0];
    });

    // Liga/desliga o destaque em todo elemento (card do Resultado ou dígito
    // da Estrutura) que tenha o mesmo data-campo, nos dois sentidos.
    function destacarCampo(campo, ligado) {
        if (!campo) return;
        document.querySelectorAll('[data-campo="' + campo + '"]').forEach(function (el) {
            el.classList.toggle('hl', ligado);
        });
    }

    function ligarHighlightCruzado(container) {
        container.addEventListener('mouseover', function (e) {
            const el = e.target.closest('[data-campo]');
            if (el) destacarCampo(el.getAttribute('data-campo'), true);
        });
        container.addEventListener('mouseout', function (e) {
            const el = e.target.closest('[data-campo]');
            if (el) destacarCampo(el.getAttribute('data-campo'), false);
        });
    }

    /* ===== CONTADOR DE DÍGITOS ===== */
    function atualizarContador(normalizado, info) {
        const len = normalizado.length;
        if (len === 0) {
            contadorEl.textContent = '0 dígitos';
            return;
        }
        if (info && info.status === 'incompleto') {
            contadorEl.textContent = info.atual + ' / ' + info.alvo + ' dígitos';
            return;
        }
        contadorEl.textContent = len + ' dígitos';
    }

    /* ===== BANNER DE STATUS ===== */
    function renderizarStatusBanner(resultado) {
        statusBanner.hidden = false;
        statusBanner.className = 'status-banner';
        switch (resultado.status) {
            case 'vazio':
                statusBanner.classList.add('status-vazio');
                statusBanner.textContent = 'Cole a linha digitável ou o código de barras do boleto acima.';
                break;
            case 'incompleto':
                statusBanner.classList.add('status-incompleto');
                statusBanner.textContent = 'Código ainda incompleto. Continue digitando ou colando.';
                break;
            case 'nao_reconhecido':
                statusBanner.classList.add('status-nao-reconhecido');
                statusBanner.textContent = 'O formato informado não foi reconhecido. ' + (resultado.motivo || '');
                break;
            case 'aproximado':
                // Sem faixa de aviso aqui de propósito (a pedido do usuário):
                // os campos identificados, renderizados logo abaixo, já bastam.
                statusBanner.hidden = true;
                statusBanner.textContent = '';
                break;
            case 'valido':
                statusBanner.classList.add('status-valido');
                statusBanner.textContent = '✓ Estrutura e dígitos verificadores válidos';
                break;
            case 'invalido':
                statusBanner.classList.add('status-invalido');
                statusBanner.textContent = '✗ Linha digitável inválida — confira o diagnóstico em "Ver detalhes técnicos"';
                break;
            default:
                statusBanner.classList.add('status-vazio');
                statusBanner.textContent = '';
        }

        if (resultado.erroEstrutural) {
            statusBanner.textContent = '✗ ' + resultado.erroEstrutural;
        }
    }

    /* ===== RESUMO PRINCIPAL ===== */
    // "hint" vira o atributo title do card inteiro — tooltip nativo do
    // navegador ao passar o mouse, explicando o que aquele campo significa.
    // "campo" (opcional) liga esse card ao highlight cruzado com a
    // visualização de estrutura — ver ligarHighlightCruzado/destacarCampo.
    function itemResumo(label, valor, hint, campo) {
        const tituloAttr = hint ? ' title="' + escapeAtributo(hint) + '"' : '';
        const campoAttr = campo ? ' data-campo="' + escapeAtributo(campo) + '"' : '';
        return '<div class="result-item"' + tituloAttr + campoAttr + '><div class="result-label">' + label + '</div>' +
            '<div class="result-value">' + valor + '</div></div>';
    }

    // Explicação de cada campo da leitura clássica, exibida como tooltip.
    const HINTS_CLASSICO = {
        codigoBanco: 'Código de 3 dígitos que identifica o banco ou instituição financeira que emitiu o boleto (código de compensação do Banco Central).',
        codigoMoeda: 'Código da moeda do boleto. O valor "9" representa o Real (BRL), o único em uso desde o Plano Real.',
        comOuSemRegistro: 'Indica se o boleto é registrado (dados enviados à central do banco) ou não. O significado exato varia conforme o layout de cada banco.',
        tipoCarteira: 'Identifica o tipo de carteira de cobrança usada pelo banco (ex.: com registro, sem registro, garantida). Varia conforme a instituição emissora.',
        nossoNumero: 'Número de controle interno que o banco/beneficiário usa para identificar esse boleto especificamente.',
        dvNossoNumero: 'Dígito que confere se o "Nosso Número" foi digitado ou lido corretamente.',
        dvPrimeiroCampo: 'Dígito que confere se o 1º campo da linha digitável (dígitos 1 a 9) foi digitado corretamente.',
        cooperativa: 'Código da cooperativa de crédito responsável pelo boleto (quando emitido por instituições como Sicredi ou Sicoob).',
        dvSegundoCampo: 'Dígito que confere se o 2º campo da linha digitável (dígitos 11 a 20) foi digitado corretamente.',
        posto: 'Código do posto de atendimento (agência/posto) do banco emissor responsável pelo boleto.',
        cedente: 'Identificação do cedente (quem está cobrando) dentro do campo livre — pode representar código de cliente, conta ou agência, dependendo do banco.',
        comOuSemValor: 'Indica se o valor do boleto é fixo ou pode ser alterado pelo pagador (ex.: em doações com valor livre).',
        campoFixo: 'Dígito de uso reservado pela FEBRABAN dentro do layout do campo livre.',
        dvCampoLivre: 'Dígito que confere se essa parte do campo livre foi digitada corretamente.',
        dvTerceiroCampo: 'Dígito que confere se o 3º campo da linha digitável (dígitos 22 a 31) foi digitado corretamente.',
        dvGeral: 'Dígito que confere a integridade de todo o código de barras.',
        fatorVencimento: 'Número de dias contados a partir de uma data-base (07/10/1997, ou 22/02/2025 no ciclo mais recente) que define o vencimento do boleto.',
        valorDocumento: 'Valor do boleto em reais, extraído dos 10 dígitos finais da linha digitável (os 2 últimos são os centavos).',
        vencimento: 'Data de vencimento do boleto, calculada a partir do fator de vencimento.'
    };

    // Rótulo curto de cada campo, usado como title="" nos dígitos da
    // Estrutura (o hint completo já mora em HINTS_CLASSICO, nos cards).
    const LABEL_CAMPO = {
        codigoBanco: 'Código do Banco', codigoMoeda: 'Código da Moeda', comOuSemRegistro: 'COM ou SEM Registro',
        tipoCarteira: 'Tipo de Carteira', nossoNumero: 'Nosso Número', dvNossoNumero: 'DV - Nosso Número',
        dvPrimeiroCampo: 'DV - do 1º Campo', cooperativa: 'Cooperativa', dvSegundoCampo: 'DV - do 2º Campo',
        posto: 'Posto', cedente: 'Cedente', comOuSemValor: 'Com ou SEM Valor', campoFixo: 'Campo FIXO',
        dvCampoLivre: 'DV - Campo Livre', dvTerceiroCampo: 'DV - de 3º Campo', dvGeral: 'DV - Geral',
        fatorVencimento: 'Fator de Vencimento', valorDocumento: 'Valor do Documento'
    };

    // Leitura posicional "clássica": mostra todos os campos identificados,
    // igual à versão anterior da ferramenta, com revelação progressiva
    // conforme a quantidade de dígitos já digitados/colados. Funciona mesmo
    // quando o comprimento total não bate com o padrão de 47 dígitos.
    function renderizarClassico(classico) {
        let html = '';

        if (classico.codigoBanco) html += itemResumo('Código do Banco', escapeHTML(classico.codigoBanco.codigo + ' — ' + classico.codigoBanco.nome), HINTS_CLASSICO.codigoBanco, 'codigoBanco');
        if (classico.codigoMoeda) html += itemResumo('Código da Moeda', escapeHTML(classico.codigoMoeda.codigo + ' — ' + classico.codigoMoeda.nome), HINTS_CLASSICO.codigoMoeda, 'codigoMoeda');
        if (classico.comOuSemRegistro) html += itemResumo('COM ou SEM Registro', escapeHTML(classico.comOuSemRegistro), HINTS_CLASSICO.comOuSemRegistro, 'comOuSemRegistro');
        if (classico.tipoCarteira) html += itemResumo('Tipo de Carteira', escapeHTML(classico.tipoCarteira), HINTS_CLASSICO.tipoCarteira, 'tipoCarteira');
        if (classico.nossoNumero) html += itemResumo('Nosso Número', escapeHTML(classico.nossoNumero), HINTS_CLASSICO.nossoNumero, 'nossoNumero');
        if (classico.dvNossoNumero) html += itemResumo('DV - Nosso Número', escapeHTML(classico.dvNossoNumero), HINTS_CLASSICO.dvNossoNumero, 'dvNossoNumero');
        if (classico.dvPrimeiroCampo) html += itemResumo('DV - do 1º Campo', escapeHTML(classico.dvPrimeiroCampo), HINTS_CLASSICO.dvPrimeiroCampo, 'dvPrimeiroCampo');
        if (classico.cooperativa) html += itemResumo('Cooperativa', escapeHTML(classico.cooperativa), HINTS_CLASSICO.cooperativa, 'cooperativa');
        if (classico.dvSegundoCampo) html += itemResumo('DV - do 2º Campo', escapeHTML(classico.dvSegundoCampo), HINTS_CLASSICO.dvSegundoCampo, 'dvSegundoCampo');
        if (classico.posto) html += itemResumo('Posto', escapeHTML(classico.posto), HINTS_CLASSICO.posto, 'posto');
        if (classico.cedente) html += itemResumo('Cedente', escapeHTML(classico.cedente), HINTS_CLASSICO.cedente, 'cedente');
        if (classico.comOuSemValor) html += itemResumo('Com ou SEM Valor', escapeHTML(classico.comOuSemValor), HINTS_CLASSICO.comOuSemValor, 'comOuSemValor');
        if (classico.campoFixo) html += itemResumo('Campo FIXO', escapeHTML(classico.campoFixo), HINTS_CLASSICO.campoFixo, 'campoFixo');
        if (classico.dvCampoLivre) html += itemResumo('DV - Campo Livre', escapeHTML(classico.dvCampoLivre), HINTS_CLASSICO.dvCampoLivre, 'dvCampoLivre');
        if (classico.dvTerceiroCampo) html += itemResumo('DV - de 3º Campo', escapeHTML(classico.dvTerceiroCampo), HINTS_CLASSICO.dvTerceiroCampo, 'dvTerceiroCampo');
        if (classico.dvGeral) html += itemResumo('DV - Geral', escapeHTML(classico.dvGeral), HINTS_CLASSICO.dvGeral, 'dvGeral');
        if (classico.fatorVencimento) html += itemResumo('Fator de Vencimento', escapeHTML(classico.fatorVencimento), HINTS_CLASSICO.fatorVencimento, 'fatorVencimento');
        if (classico.valorFormatado) html += itemResumo('Valor do Documento', classico.valorFormatado, HINTS_CLASSICO.valorDocumento, 'valorDocumento');
        if (classico.vencimento) {
            html += itemResumo('Data de Vencimento', classico.vencimento.texto
                ? escapeHTML(classico.vencimento.texto) + (classico.vencimento.aviso ? ' ⚠️' : '')
                : escapeHTML(classico.vencimento.mensagem || 'Não foi possível determinar com segurança'), HINTS_CLASSICO.vencimento, 'fatorVencimento');
        }

        resumoGrid.innerHTML = html;
    }

    // Resumo específico do documento de arrecadação (layout totalmente
    // diferente do de cobrança, por isso não usa a leitura clássica acima).
    function renderizarResumoArrecadacao(resultado) {
        let html = '';
        html += itemResumo('Tipo', 'Documento de arrecadação',
            'Indica que o código lido segue o layout de documento de arrecadação (tributos, contas, convênios) e não o de boleto de cobrança bancária.');
        html += itemResumo('Segmento', resultado.segmento,
            'Identifica o segmento do documento (ex.: prefeituras, saneamento, energia elétrica, telecomunicações, órgãos governamentais, carnês), conforme tabela da FEBRABAN.');
        html += itemResumo('Valor', resultado.valorFormatado + (resultado.notaValor ? ' ⚠️' : ''),
            'Valor do documento de arrecadação, em reais.' + (resultado.notaValor ? ' ' + resultado.notaValor : ''));
        html += itemResumo('Indicador de valor', resultado.indicadorValor,
            'Dígito que informa se o valor é efetivo (fixo) ou de referência (pode ser diferente do valor realmente cobrado).');
        resumoGrid.innerHTML = html;
    }

    /* ===== ESTRUTURA VISUAL (blocos coloridos) ===== */
    function blocoHTML(rotulo, valor, classe) {
        return '<div class="campo-block ' + classe + '" title="' + escapeAtributo(rotulo) + '">' +
            '<div class="campo-block-label">' + escapeHTML(rotulo) + '</div>' +
            '<div class="campo-block-valor">' + escapeHTML(valor) + '</div></div>';
    }

    // Igual a blocoHTML, mas recebe o valor já pronto em HTML (dígitos
    // individuais envolvidos em spans com data-campo) em vez de texto puro —
    // usado quando queremos highlight cruzado dígito a dígito.
    function blocoHTMLComSpans(rotulo, htmlValor, classe) {
        return '<div class="campo-block ' + classe + '" title="' + escapeAtributo(rotulo) + '">' +
            '<div class="campo-block-label">' + escapeHTML(rotulo) + '</div>' +
            '<div class="campo-block-valor">' + htmlValor + '</div></div>';
    }

    // Envolve cada caractere de "texto" num span com data-campo, usando
    // CAMPO_POR_INDICE para saber a qual campo aquela posição pertence.
    // "offsetInicial" é a posição de texto[0] dentro da linha completa.
    function valorComHighlight(texto, offsetInicial) {
        let html = '';
        for (let i = 0; i < texto.length; i++) {
            const campo = CAMPO_POR_INDICE[offsetInicial + i];
            const atributos = campo
                ? ' data-campo="' + campo + '" title="' + escapeAtributo(LABEL_CAMPO[campo] || campo) + '"'
                : '';
            html += '<span class="lv-char"' + atributos + '>' + escapeHTML(texto[i]) + '</span>';
        }
        return html;
    }

    function renderizarBlocos(resultado) {
        let html = '';

        if (resultado.tipo === 'cobranca') {
            const l = resultado.linhaDigitavel;
            html += blocoHTMLComSpans('Campo 1 (banco + parte do campo livre)', valorComHighlight(l.substring(0, 10), 0), 'campo-1');
            html += blocoHTMLComSpans('Campo 2 (campo livre)', valorComHighlight(l.substring(10, 21), 10), 'campo-2');
            html += blocoHTMLComSpans('Campo 3 (campo livre)', valorComHighlight(l.substring(21, 32), 21), 'campo-3');
            html += blocoHTMLComSpans('DV Geral', valorComHighlight(l.substring(32, 33), 32), 'campo-dv');
            html += blocoHTMLComSpans('Campo 5 (fator + valor)', valorComHighlight(l.substring(33, 47), 33), 'campo-5');
        } else if (resultado.tipo === 'arrecadacao') {
            // Layout totalmente diferente do de cobrança — sem highlight
            // cruzado por enquanto, só a divisão em blocos.
            const l = resultado.linhaDigitavel;
            html += blocoHTML('Bloco 1', l.substring(0, 12), 'campo-1');
            html += blocoHTML('Bloco 2', l.substring(12, 24), 'campo-2');
            html += blocoHTML('Bloco 3', l.substring(24, 36), 'campo-3');
            html += blocoHTML('Bloco 4', l.substring(36, 48), 'campo-5');
        }

        campoBlocksEl.innerHTML = html;
    }

    /* ===== TABELA DE DIAGNÓSTICO ===== */
    function renderizarDiagnostico(resultado) {
        if (!resultado.diagnostico || resultado.diagnostico.length === 0) {
            diagnosticTableEl.innerHTML = '';
            return;
        }

        let html = '<thead><tr><th>Campo</th><th>Informado</th><th>Calculado</th><th>Status</th></tr></thead><tbody>';
        resultado.diagnostico.forEach(function (item) {
            html += '<tr class="' + (item.ok ? 'diag-ok' : 'diag-erro') + '">' +
                '<td>' + escapeHTML(item.campo) + '</td>' +
                '<td>' + escapeHTML(item.informado) + '</td>' +
                '<td>' + escapeHTML(item.calculado) + '</td>' +
                '<td>' + (item.ok ? 'OK' : 'ERRO') + '</td></tr>';
        });
        html += '</tbody>';
        diagnosticTableEl.innerHTML = html;

        if (resultado.avisoIndicador) {
            diagnosticTableEl.innerHTML += '';
        }
    }

    /* ===== CAMPO LIVRE ===== */
    function renderizarCampoLivre(resultado) {
        if (!resultado.campoLivre) {
            campoLivreEl.innerHTML = '';
            return;
        }
        let html = '<div class="result-label">Campo livre</div>' +
            '<div class="code-display">' + escapeHTML(resultado.campoLivre) + '</div>' +
            '<p class="campo-livre-nota">A leitura de carteira, nosso número, cooperativa, posto e cedente exibida ' +
            'acima em "Resultado" segue a disposição mais comum usada pelos bancos — mas cada instituição pode ' +
            'posicionar esses dados de forma diferente, então confira sempre no site do banco emissor em caso de dúvida.</p>';

        if (resultado.tipo === 'arrecadacao' && resultado.idEmpresaOrgao) {
            html += '<div class="result-label">Identificação da empresa/órgão</div>' +
                '<div class="code-display">' + escapeHTML(resultado.idEmpresaOrgao) + '</div>';
        }
        if (resultado.notaValor) {
            html += '<p class="campo-livre-nota">⚠️ ' + escapeHTML(resultado.notaValor) + '</p>';
        }
        if (resultado.avisoIndicador) {
            html += '<p class="campo-livre-nota">⚠️ ' + escapeHTML(resultado.avisoIndicador) + '</p>';
        }
        if (resultado.vencimento && resultado.vencimento.aviso) {
            html += '<p class="campo-livre-nota">⚠️ ' + escapeHTML(resultado.vencimento.aviso) + '</p>';
        }

        campoLivreEl.innerHTML = html;
    }

    /* ===== CÓDIGO DE BARRAS ===== */
    function renderizarCodigoBarras(resultado) {
        if (!resultado.codigoBarras) {
            codigoBarrasRowEl.innerHTML = '';
            return;
        }
        codigoBarrasRowEl.innerHTML =
            '<div class="result-label">Código de barras correspondente</div>' +
            '<div class="code-display" id="codigo-barras-texto">' + escapeHTML(resultado.codigoBarras) + '</div>' +
            '<button type="button" class="btn-small" id="btn-copiar-barras">' +
            '<i class="fas fa-copy"></i> Copiar código de barras</button>' +
            '<button type="button" class="btn-small" id="btn-copiar-diagnostico">' +
            '<i class="fas fa-copy"></i> Copiar diagnóstico</button>';

        const btnCopiarBarras = document.getElementById('btn-copiar-barras');
        if (btnCopiarBarras) {
            btnCopiarBarras.addEventListener('click', function () {
                copiarTexto(resultado.codigoBarras, 'Código de barras copiado com sucesso.');
            });
        }

        const btnCopiarDiagnostico = document.getElementById('btn-copiar-diagnostico');
        if (btnCopiarDiagnostico) {
            btnCopiarDiagnostico.addEventListener('click', function () {
                const texto = (resultado.diagnostico || [])
                    .map(function (d) { return d.campo + ': ' + (d.ok ? 'OK' : 'ERRO (informado ' + d.informado + ' / calculado ' + d.calculado + ')'); })
                    .join('\n');
                copiarTexto(texto, 'Diagnóstico copiado com sucesso.');
            });
        }
    }

    /* ===== RENDERIZAÇÃO PRINCIPAL ===== */
    function renderizarResultado(resultado) {
        renderizarStatusBanner(resultado);

        // Leitura de campos: sempre que houver "classico" (código com jeito de
        // cobrança, mesmo que incompleto ou de comprimento incomum) ou for um
        // documento de arrecadação, mostramos os campos identificados. Isso
        // preserva o comportamento anterior de sempre exibir algo enquanto o
        // usuário digita/cola, mesmo antes (ou apesar) de qualquer validação.
        if (resultado.classico) {
            renderizarClassico(resultado.classico);
        } else if (resultado.tipo === 'arrecadacao') {
            renderizarResumoArrecadacao(resultado);
        } else {
            resumoGrid.innerHTML = '';
        }

        // A seção técnica (estrutura em blocos, tabela de diagnóstico, campo
        // livre e código de barras) só existe quando a validação rigorosa
        // rodou de fato — ou seja, quando o comprimento bateu exatamente com
        // um formato documentado (47, 44 ou 48 dígitos iniciando em "8").
        const temDiagnostico = Array.isArray(resultado.diagnostico) && resultado.diagnostico.length > 0;

        if (!temDiagnostico) {
            campoBlocksEl.innerHTML = '';
            diagnosticTableEl.innerHTML = '';
            campoLivreEl.innerHTML = '';
            codigoBarrasRowEl.innerHTML = '';
            btnDetalhes.hidden = true;
            detalhesPanel.hidden = true;
            btnDetalhes.setAttribute('aria-expanded', 'false');
            btnDetalhes.textContent = 'Ver detalhes técnicos';
            return;
        }

        btnDetalhes.hidden = false;
        renderizarBlocos(resultado);
        renderizarDiagnostico(resultado);
        renderizarCampoLivre(resultado);
        renderizarCodigoBarras(resultado);
    }

    /* ===== UTILITÁRIO DE ESCAPE (evita HTML injection ao renderizar dados do usuário) ===== */
    function escapeHTML(valor) {
        return String(valor == null ? '' : valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    // Mesma ideia de escapeHTML, mas também escapa aspas — necessário para
    // texto inserido dentro de um atributo (ex.: title="...") em vez de
    // dentro do conteúdo de uma tag.
    function escapeAtributo(valor) {
        return escapeHTML(valor).replace(/"/g, '&quot;');
    }

    /* ===== TOAST ===== */
    let toastTimeout = null;
    function mostrarToast(mensagem) {
        toastEl.textContent = mensagem;
        toastEl.classList.add('show');
        if (toastTimeout) clearTimeout(toastTimeout);
        toastTimeout = setTimeout(function () {
            toastEl.classList.remove('show');
        }, 3000);
    }

    /* ===== COPIAR (com fallback para navegadores/contextos sem Clipboard API) ===== */
    function copiarTexto(texto, mensagemSucesso) {
        if (!texto) {
            mostrarToast('Não há conteúdo para copiar.');
            return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texto).then(function () {
                mostrarToast(mensagemSucesso || 'Copiado com sucesso.');
            }).catch(function () {
                copiarTextoFallback(texto, mensagemSucesso);
            });
        } else {
            copiarTextoFallback(texto, mensagemSucesso);
        }
    }

    function copiarTextoFallback(texto, mensagemSucesso) {
        const temp = document.createElement('textarea');
        temp.value = texto;
        temp.style.position = 'fixed';
        temp.style.opacity = '0';
        document.body.appendChild(temp);
        temp.focus();
        temp.select();
        try {
            document.execCommand('copy');
            mostrarToast(mensagemSucesso || 'Copiado com sucesso.');
        } catch (err) {
            mostrarToast('Não foi possível copiar automaticamente.');
        }
        document.body.removeChild(temp);
    }

    /* ===== FLUXO PRINCIPAL: INPUT -> NORMALIZAR -> DECODIFICAR -> RENDERIZAR ===== */
    function handleInput() {
        const bruto = inputEl.value;
        const normalizado = BoletoLib.normalizarCodigo(bruto);
        const resultado = BoletoLib.decodificarBoleto(normalizado);
        atualizarContador(normalizado, resultado);
        renderizarResultado(resultado);
    }

    /* ===== AÇÕES ===== */
    function handleColar() {
        if (navigator.clipboard && navigator.clipboard.readText) {
            navigator.clipboard.readText().then(function (texto) {
                inputEl.value = texto;
                inputEl.focus();
                handleInput();
            }).catch(function () {
                mostrarToast('Não foi possível colar automaticamente. Use Ctrl+V no campo.');
                inputEl.focus();
            });
        } else {
            mostrarToast('Cole manualmente com Ctrl+V no campo.');
            inputEl.focus();
        }
    }

    function handleLimpar() {
        inputEl.value = '';
        inputEl.focus();
        handleInput();
    }

    function handleExemplo() {
        inputEl.value = LINHA_EXEMPLO;
        handleInput();
    }

    function toggleDetalhes() {
        const expandido = detalhesPanel.hidden === false;
        detalhesPanel.hidden = expandido;
        btnDetalhes.setAttribute('aria-expanded', String(!expandido));
        btnDetalhes.textContent = expandido ? 'Ver detalhes técnicos' : 'Ocultar detalhes técnicos';
    }

    /* ===== EVENTOS ===== */
    inputEl.addEventListener('input', handleInput);
    btnColar.addEventListener('click', handleColar);
    btnLimpar.addEventListener('click', handleLimpar);
    btnDetalhes.addEventListener('click', toggleDetalhes);
    if (btnExemplo) btnExemplo.addEventListener('click', handleExemplo);
    ligarHighlightCruzado(resumoGrid);
    ligarHighlightCruzado(campoBlocksEl);

    /* ===== ESTADO INICIAL ===== */
    renderizarResultado({ status: 'vazio' });
    atualizarContador('', { status: 'vazio' });
})();
