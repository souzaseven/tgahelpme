/* ===== BIBLIOTECA DE VALIDAÇÃO/DECODIFICAÇÃO DE BOLETOS (FEBRABAN) ===== */
/*                                                                          */
/* Todas as funções abaixo são PURAS: recebem valores, retornam valores,   */
/* nunca tocam no DOM (document, window.alert, etc). Isso permite testar   */
/* cada uma isoladamente no console do navegador, ex:                     */
/*   BoletoLib.calcularModulo10('123456789')                              */
/*                                                                          */
/* Suporta dois formatos:                                                  */
/*  - Boleto de cobrança bancária (linha digitável 47 / código de barras 44)*/
/*  - Documento de arrecadação (linha digitável 48, código de barras 44)   */
/*                                                                          */
/* IMPORTANTE: esta ferramenta valida ESTRUTURA e DÍGITOS VERIFICADORES.   */
/* Um código matematicamente válido não garante que a cobrança seja        */
/* legítima. Sempre oriente o usuário a confirmar o beneficiário no banco. */

(function (global) {
    'use strict';

    /* ===== NORMALIZAÇÃO ===== */
    // Remove tudo que não é dígito (espaços, pontos, hífens, letras, quebras de linha)
    function normalizarCodigo(valorBruto) {
        return String(valorBruto || '').replace(/\D/g, '');
    }

    /* ===== DETECÇÃO DE FORMATO ===== */
    // Decide o que a string normalizada (só dígitos) representa, sem validar DVs ainda.
    function detectarTipoCodigo(normalizado) {
        const len = normalizado.length;

        if (len === 0) {
            return { tipo: 'vazio' };
        }
        if (len === 47) {
            return { tipo: 'linha_cobranca' };
        }
        if (len === 44) {
            // 44 dígitos é sempre tratado como código de barras de cobrança:
            // é o comprimento exato desse formato, então tem prioridade sobre
            // a hipótese de ser um código de arrecadação incompleto (48).
            return { tipo: 'codigo_barras_cobranca' };
        }
        if (len === 48) {
            if (normalizado[0] === '8') {
                return { tipo: 'linha_arrecadacao' };
            }
            return { tipo: 'nao_reconhecido', motivo: 'Possui 48 dígitos, mas não começa com "8" (identificador de arrecadação).' };
        }
        if (len > 48) {
            return { tipo: 'nao_reconhecido', motivo: 'Quantidade de dígitos maior do que qualquer formato suportado.' };
        }

        // Ainda incompleto: o alvo depende do que já foi digitado até agora.
        const alvo = normalizado[0] === '8' ? 48 : 47;
        return { tipo: 'incompleto', atual: len, alvo };
    }

    /* ===== MÓDULO 10 (usado nos 3 DVs de campo da cobrança e, conforme o ===== */
    /* ===== indicador de valor, também na arrecadação)                    ===== */
    function calcularModulo10(numero) {
        const digitos = String(numero).split('').map(Number);
        let peso = 2;
        let soma = 0;

        for (let i = digitos.length - 1; i >= 0; i--) {
            let produto = digitos[i] * peso;
            if (produto >= 10) {
                // soma os dois algarismos do produto (ex.: 16 -> 1+6=7)
                produto = Math.floor(produto / 10) + (produto % 10);
            }
            soma += produto;
            peso = peso === 2 ? 1 : 2;
        }

        const resto = soma % 10;
        return resto === 0 ? 0 : 10 - resto;
    }

    /* ===== MÓDULO 11 ===== */
    // ATENÇÃO: existem DUAS regras de arredondamento diferentes, por isso o
    // parâmetro `contexto` é obrigatório — usar a regra errada faz o DV
    // geral de um dos dois formatos sair sistematicamente errado.
    //
    // contexto 'cobranca'   (DV geral do código de barras de boleto bancário):
    //   se resto ∈ {0, 1, 10} -> DV = 1 ; senão DV = 11 - resto
    //   (regra publicada nos manuais de emissão de boletos de Santander/Caixa)
    //
    // contexto 'arrecadacao' (DV geral e DVs de bloco de documento de arrecadação
    //   quando o indicador de valor é 7 ou 9):
    //   se resto ∈ {0, 1} -> DV = 0 ; se resto = 10 -> DV = 1 ; senão DV = 11 - resto
    //   (layout de arrecadação FEBRABAN — regra distinta da cobrança no caso
    //   dos restos 0 e 1)
    function calcularModulo11(numero, contexto) {
        if (contexto !== 'cobranca' && contexto !== 'arrecadacao') {
            throw new Error('calcularModulo11: contexto deve ser "cobranca" ou "arrecadacao"');
        }

        const digitos = String(numero).split('').map(Number);
        let peso = 2;
        let soma = 0;

        for (let i = digitos.length - 1; i >= 0; i--) {
            soma += digitos[i] * peso;
            peso = peso === 9 ? 2 : peso + 1;
        }

        const resto = soma % 11;

        if (contexto === 'cobranca') {
            if (resto === 0 || resto === 1 || resto === 10) return 1;
            return 11 - resto;
        }

        // arrecadacao
        if (resto === 0 || resto === 1) return 0;
        if (resto === 10) return 1;
        return 11 - resto;
    }

    /* ===== CONVERSÃO LINHA DIGITÁVEL <-> CÓDIGO DE BARRAS (COBRANÇA) ===== */
    //
    // Estrutura da linha digitável de cobrança (47 posições, 0-based):
    //   Campo 1 = [0:10]  -> dado=[0:9]  (banco[0:3]+moeda[3:4]+campoLivre[0:5]), DV1=[9]
    //   Campo 2 = [10:21] -> dado=[10:20] (campoLivre[5:15]), DV2=[20]
    //   Campo 3 = [21:32] -> dado=[21:31] (campoLivre[15:25]), DV3=[31]
    //   DV geral do código de barras = [32]
    //   Campo 5 = [33:47] -> fator de vencimento [33:37] + valor [37:47]
    //
    // Estrutura do código de barras (44 posições, 0-based):
    //   banco[0:3] + moeda[3:4] + DVgeral[4:5] + fator[5:9] + valor[9:19] + campoLivre[19:44]

    function linhaDigitavelParaCodigoBarras(linha47) {
        const campo1Dado = linha47.substring(0, 9);
        const campo2Dado = linha47.substring(10, 20);
        const campo3Dado = linha47.substring(21, 31);
        const dvGeral = linha47.substring(32, 33);
        const fatorValor = linha47.substring(33, 47);

        const banco = campo1Dado.substring(0, 3);
        const moeda = campo1Dado.substring(3, 4);
        const campoLivre = campo1Dado.substring(4, 9) + campo2Dado + campo3Dado; // 5+10+10=25

        return banco + moeda + dvGeral + fatorValor + campoLivre; // 3+1+1+14+25=44
    }

    function codigoBarrasParaLinhaDigitavel(barcode44) {
        const banco = barcode44.substring(0, 3);
        const moeda = barcode44.substring(3, 4);
        const dvGeral = barcode44.substring(4, 5);
        const fatorValor = barcode44.substring(5, 19);
        const campoLivre = barcode44.substring(19, 44);

        const campo1Dado = banco + moeda + campoLivre.substring(0, 5);
        const campo2Dado = campoLivre.substring(5, 15);
        const campo3Dado = campoLivre.substring(15, 25);

        const dv1 = calcularModulo10(campo1Dado);
        const dv2 = calcularModulo10(campo2Dado);
        const dv3 = calcularModulo10(campo3Dado);

        return (
            campo1Dado + dv1 +
            campo2Dado + dv2 +
            campo3Dado + dv3 +
            dvGeral +
            fatorValor
        ); // 10+11+11+1+14=47
    }

    /* ===== FORMATAÇÃO DE VALOR ===== */
    // Recebe os dígitos de valor (em centavos) e devolve "R$ 1.234,56".
    // Nunca lança erro: entrada inválida ou zerada vira R$ 0,00.
    function formatarValor(digitosValor) {
        const centavos = parseInt(digitosValor, 10) || 0;
        const reais = centavos / 100;
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(reais);
    }

    /* ===== FATOR DE VENCIMENTO -> DATA ===== */
    // Regra em vigor (comunicado FEBRABAN de 2025): a contagem de dias a partir
    // de 07/10/1997 chegaria ao limite de 4 dígitos (9999) em 21/02/2025. A partir
    // de 22/02/2025 o fator foi reiniciado em 1000 (não em 0000), usando essa nova
    // data como base.
    //
    // Limitação conhecida (documentada para o usuário, não escondida): um fator
    // >= 1000 já existia normalmente no ciclo antigo (a partir de meados de 2000).
    // Não é possível, só com a linha digitável, distinguir com 100% de certeza um
    // boleto antigo (emitido antes de 22/02/2025, ciclo antigo) de um boleto novo
    // com o mesmo fator no ciclo reiniciado. Esta função assume o ciclo NOVO
    // sempre que o fator for >= 1000, por ser o cenário mais provável para
    // boletos emitidos a partir de 2025, e retorna um aviso explicando a limitação.
    function calcularDataVencimento(fatorStr) {
        if (!/^\d{4}$/.test(String(fatorStr || ''))) {
            return { data: null, texto: null, mensagem: 'Fator de vencimento inválido.', aviso: null };
        }

        const fator = parseInt(fatorStr, 10);

        if (fator === 0) {
            return { data: null, texto: null, mensagem: 'Sem vencimento definido (fator 0000).', aviso: null };
        }

        let base;
        let aviso = null;

        if (fator < 1000) {
            base = new Date(1997, 9, 7); // 07/10/1997 — mês 9 = outubro (0-indexed)
            base.setDate(base.getDate() + fator);
        } else {
            base = new Date(2025, 1, 22); // 22/02/2025 — nova data-base do ciclo reiniciado
            base.setDate(base.getDate() + (fator - 1000));
            aviso = 'Data calculada considerando o ciclo de vencimento vigente após 22/02/2025. ' +
                'Boletos emitidos antes dessa data com fator igual ou maior que 1000 podem exibir uma data incorreta.';
        }

        return { data: base, texto: base.toLocaleDateString('pt-BR'), mensagem: null, aviso };
    }

    /* ===== BANCO / MOEDA ===== */
    function buscarBanco(codigo3) {
        const nome = global.BANCOS && global.BANCOS[codigo3];
        return {
            codigo: codigo3,
            nome: nome || `Código ${codigo3} (banco não identificado no mapa local)`
        };
    }

    function buscarMoeda(codigo1) {
        const nome = global.MOEDAS && global.MOEDAS[codigo1];
        return {
            codigo: codigo1,
            nome: nome || `Código ${codigo1} (moeda não identificada)`
        };
    }

    /* ===== VALIDAÇÃO — BOLETO DE COBRANÇA ===== */
    function validarLinhaBancaria(linha47) {
        if (!/^\d{47}$/.test(linha47)) {
            return {
                tipo: 'cobranca',
                valido: false,
                erroEstrutural: 'A linha digitável de cobrança deve ter exatamente 47 dígitos numéricos.'
            };
        }

        const campo1Dado = linha47.substring(0, 9);
        const dv1Informado = linha47.substring(9, 10);
        const campo2Dado = linha47.substring(10, 20);
        const dv2Informado = linha47.substring(20, 21);
        const campo3Dado = linha47.substring(21, 31);
        const dv3Informado = linha47.substring(31, 32);
        const dvGeralInformado = linha47.substring(32, 33);

        const dv1Calc = calcularModulo10(campo1Dado);
        const dv2Calc = calcularModulo10(campo2Dado);
        const dv3Calc = calcularModulo10(campo3Dado);

        const diagnostico = [
            { campo: 'DV Campo 1', informado: dv1Informado, calculado: String(dv1Calc), ok: dv1Informado === String(dv1Calc) },
            { campo: 'DV Campo 2', informado: dv2Informado, calculado: String(dv2Calc), ok: dv2Informado === String(dv2Calc) },
            { campo: 'DV Campo 3', informado: dv3Informado, calculado: String(dv3Calc), ok: dv3Informado === String(dv3Calc) }
        ];

        const codigoBarras = linhaDigitavelParaCodigoBarras(linha47);
        // remove a posição do DV geral (índice 4) do código de barras para recalculá-lo
        const codigoParaDVGeral = codigoBarras.substring(0, 4) + codigoBarras.substring(5);
        const dvGeralCalc = calcularModulo11(codigoParaDVGeral, 'cobranca');

        diagnostico.push({
            campo: 'DV Geral (código de barras)',
            informado: dvGeralInformado,
            calculado: String(dvGeralCalc),
            ok: dvGeralInformado === String(dvGeralCalc)
        });

        const banco = buscarBanco(codigoBarras.substring(0, 3));
        const moeda = buscarMoeda(codigoBarras.substring(3, 4));
        const fator = codigoBarras.substring(5, 9);
        const valorDigitos = codigoBarras.substring(9, 19);
        const campoLivre = codigoBarras.substring(19, 44);
        const vencimento = calcularDataVencimento(fator);
        const valido = diagnostico.every(function (d) { return d.ok; });

        return {
            tipo: 'cobranca',
            valido: valido,
            linhaDigitavel: linha47,
            linhaDigitavelFormatada: formatarLinhaCobranca(linha47),
            codigoBarras: codigoBarras,
            banco: banco,
            moeda: moeda,
            fatorVencimento: fator,
            vencimento: vencimento,
            valorFormatado: formatarValor(valorDigitos),
            campoLivre: campoLivre,
            diagnostico: diagnostico
        };
    }

    // Formatação visual da linha digitável de cobrança: XXXXX.XXXXX XXXXX.XXXXXX XXXXX.XXXXXX X XXXXXXXXXXXXXX
    function formatarLinhaCobranca(linha47) {
        if (!/^\d{47}$/.test(linha47)) return linha47;
        return (
            linha47.substring(0, 5) + '.' + linha47.substring(5, 10) + ' ' +
            linha47.substring(10, 15) + '.' + linha47.substring(15, 21) + ' ' +
            linha47.substring(21, 26) + '.' + linha47.substring(26, 32) + ' ' +
            linha47.substring(32, 33) + ' ' +
            linha47.substring(33, 47)
        );
    }

    /* ===== VALIDAÇÃO — DOCUMENTO DE ARRECADAÇÃO ===== */
    //
    // Estrutura da linha digitável de arrecadação (48 posições): 4 blocos de
    // 12 caracteres (11 dígitos de dado + 1 DV).
    // O 3º dígito da linha (índice 2) indica o módulo usado para todos os DVs:
    //   6 ou 8 -> módulo 10
    //   7 ou 9 -> módulo 11
    //
    // Código de barras (44 posições), remontado a partir dos 4 blocos sem os DVs:
    //   produto[0:1] + segmento[1:2] + indicador[2:3] + DVgeral[3:4] +
    //   valor[4:15] (11 dígitos) + campo específico do órgão[15:44]
    function validarArrecadacao(linha48) {
        if (!/^\d{48}$/.test(linha48) || linha48[0] !== '8') {
            return {
                tipo: 'arrecadacao',
                valido: false,
                erroEstrutural: 'A linha de arrecadação deve ter exatamente 48 dígitos numéricos e começar com "8".'
            };
        }

        const indicadorValor = linha48[2];
        let contexto;
        let avisoIndicador = null;

        if (indicadorValor === '6' || indicadorValor === '8') {
            contexto = 'modulo10';
        } else if (indicadorValor === '7' || indicadorValor === '9') {
            contexto = 'modulo11';
        } else {
            // indicador fora do esperado: não trava a ferramenta, mas avisa
            // claramente que a suposição (módulo 10) pode estar errada.
            contexto = 'modulo10';
            avisoIndicador = `Indicador de valor "${indicadorValor}" não é um dos valores documentados (6, 7, 8 ou 9). ` +
                'Calculando com módulo 10 como suposição — confira o resultado com cautela.';
        }

        const calcularDV = contexto === 'modulo10'
            ? calcularModulo10
            : function (n) { return calcularModulo11(n, 'arrecadacao'); };

        const blocos = [
            linha48.substring(0, 12),
            linha48.substring(12, 24),
            linha48.substring(24, 36),
            linha48.substring(36, 48)
        ];

        const diagnostico = [];
        let codigoBarras = '';

        blocos.forEach(function (bloco, indice) {
            const dado = bloco.substring(0, 11);
            const dvInformado = bloco.substring(11, 12);
            const dvCalc = calcularDV(dado);
            diagnostico.push({
                campo: 'DV Bloco ' + (indice + 1),
                informado: dvInformado,
                calculado: String(dvCalc),
                ok: dvInformado === String(dvCalc)
            });
            codigoBarras += dado;
        });
        // codigoBarras agora tem 44 dígitos (4 blocos x 11)

        const segmento = codigoBarras[1];
        const dvGeralInformado = codigoBarras[3];
        const codigoParaDVGeral = codigoBarras.substring(0, 3) + codigoBarras.substring(4);
        const dvGeralCalc = calcularDV(codigoParaDVGeral);

        diagnostico.push({
            campo: 'DV Geral (código de barras)',
            informado: dvGeralInformado,
            calculado: String(dvGeralCalc),
            ok: dvGeralInformado === String(dvGeralCalc)
        });

        const valorDigitos = codigoBarras.substring(4, 15); // 11 dígitos
        const idEmpresaOrgao = codigoBarras.substring(15, 19);
        const campoLivre = codigoBarras.substring(19, 44);

        let valorFormatado = formatarValor(valorDigitos);
        let notaValor = null;
        if (indicadorValor === '8' || indicadorValor === '9') {
            notaValor = 'Indicador de valor de referência — o valor efetivamente cobrado pode ser diferente do exibido aqui; confirme com o emissor.';
        }

        const valido = diagnostico.every(function (d) { return d.ok; });

        return {
            tipo: 'arrecadacao',
            valido: valido,
            linhaDigitavel: linha48,
            codigoBarras: codigoBarras,
            segmento: segmento,
            indicadorValor: indicadorValor,
            valorFormatado: valorFormatado,
            notaValor: notaValor,
            idEmpresaOrgao: idEmpresaOrgao,
            campoLivre: campoLivre,
            diagnostico: diagnostico,
            avisoIndicador: avisoIndicador
        };
    }

    /* ===== LEITURA POSICIONAL "CLÁSSICA" (compatibilidade com a versão anterior) ===== */
    // A versão anterior da ferramenta sempre mostrava esses campos, com base em
    // um layout de campo livre comum (não é universal — cada banco pode usar
    // essas posições de forma diferente, por isso a tela exibe um aviso). Esta
    // decomposição é mantida e calculada de forma progressiva — cada campo só
    // aparece quando já há dígitos suficientes — mesmo quando o código não bate
    // exatamente com os 47 dígitos do padrão FEBRABAN, para nunca deixar a tela
    // vazia enquanto houver algo útil para conferir visualmente.
    function decodificarClassico(normalizado) {
        const l = normalizado;
        const campos = {};

        if (l.length >= 3) campos.codigoBanco = buscarBanco(l.substring(0, 3));
        if (l.length >= 4) campos.codigoMoeda = buscarMoeda(l.substring(3, 4));
        if (l.length >= 5) campos.comOuSemRegistro = l.substring(4, 5);
        if (l.length >= 6) campos.tipoCarteira = l.substring(5, 6);
        if (l.length >= 14) campos.nossoNumero = l.substring(6, 14);
        if (l.length >= 15) campos.dvNossoNumero = l.substring(14, 15);
        if (l.length >= 16) campos.dvPrimeiroCampo = l.substring(15, 16);
        if (l.length >= 20) campos.cooperativa = l.substring(16, 20);
        if (l.length >= 21) campos.dvSegundoCampo = l.substring(20, 21);
        if (l.length >= 23) campos.posto = l.substring(21, 23);
        if (l.length >= 28) campos.cedente = l.substring(23, 28);
        if (l.length >= 29) campos.comOuSemValor = l.substring(28, 29);
        if (l.length >= 30) campos.campoFixo = l.substring(29, 30);
        if (l.length >= 31) campos.dvCampoLivre = l.substring(30, 31);
        if (l.length >= 32) campos.dvTerceiroCampo = l.substring(31, 32);
        if (l.length >= 33) campos.dvGeral = l.substring(32, 33);
        if (l.length >= 37) {
            campos.fatorVencimento = l.substring(33, 37);
            campos.vencimento = calcularDataVencimento(campos.fatorVencimento);
        }
        if (l.length >= 47) {
            campos.valorDocumento = l.substring(37, 47);
            campos.valorFormatado = formatarValor(campos.valorDocumento);
        }

        return campos;
    }

    /* ===== ORQUESTRADOR ÚNICO ===== */
    // Ponto de entrada usado pelo script.js: recebe o valor já normalizado
    // (só dígitos) e devolve um objeto pronto para renderização.
    //
    // A leitura "clássica" (decodificarClassico) é calculada sempre que o
    // código não começa com "8" (formato de arrecadação, que tem layout
    // totalmente diferente) e tem entre 3 e 60 dígitos — mesmo quando o
    // comprimento final não bate com nenhum formato oficial — para que a tela
    // sempre mostre algo em vez de simplesmente recusar a entrada. A validação
    // rigorosa de dígitos verificadores (diagnóstico OK/ERRO) só é exibida
    // quando o comprimento bate exatamente com um formato documentado (47, 44
    // ou 48 dígitos iniciando em "8").
    function decodificarBoleto(normalizado) {
        const info = detectarTipoCodigo(normalizado);
        const pareceCobranca = normalizado.length >= 3 && normalizado.length <= 60 && normalizado[0] !== '8';
        const classico = pareceCobranca ? decodificarClassico(normalizado) : null;

        switch (info.tipo) {
            case 'vazio':
                return { status: 'vazio' };

            case 'incompleto':
                return { status: 'incompleto', atual: info.atual, alvo: info.alvo, classico: classico };

            case 'nao_reconhecido':
                // Comprimento fora do padrão (ex.: 1 dígito a mais ou a menos por
                // erro de digitação/colagem). Ainda assim mostramos a leitura
                // clássica quando fizer sentido, deixando claro que os dígitos
                // verificadores não foram conferidos com segurança nesse caso.
                return { status: 'aproximado', motivo: info.motivo, classico: classico };

            case 'linha_cobranca': {
                const resultado = validarLinhaBancaria(normalizado);
                return Object.assign({ status: resultado.valido ? 'valido' : 'invalido', classico: classico }, resultado);
            }

            case 'codigo_barras_cobranca': {
                const linha = codigoBarrasParaLinhaDigitavel(normalizado);
                const resultado = validarLinhaBancaria(linha);
                return Object.assign({ status: resultado.valido ? 'valido' : 'invalido', origemCodigoBarras: true, classico: decodificarClassico(linha) }, resultado);
            }

            case 'linha_arrecadacao': {
                const resultado = validarArrecadacao(normalizado);
                return Object.assign({ status: resultado.valido ? 'valido' : 'invalido' }, resultado);
            }

            default:
                return { status: 'aproximado', motivo: 'Formato não reconhecido.', classico: classico };
        }
    }

    /* ===== EXPORTAÇÃO (namespace global, sem bundler) ===== */
    global.BoletoLib = {
        normalizarCodigo: normalizarCodigo,
        detectarTipoCodigo: detectarTipoCodigo,
        calcularModulo10: calcularModulo10,
        calcularModulo11: calcularModulo11,
        linhaDigitavelParaCodigoBarras: linhaDigitavelParaCodigoBarras,
        codigoBarrasParaLinhaDigitavel: codigoBarrasParaLinhaDigitavel,
        formatarValor: formatarValor,
        calcularDataVencimento: calcularDataVencimento,
        buscarBanco: buscarBanco,
        buscarMoeda: buscarMoeda,
        validarLinhaBancaria: validarLinhaBancaria,
        validarArrecadacao: validarArrecadacao,
        formatarLinhaCobranca: formatarLinhaCobranca,
        decodificarClassico: decodificarClassico,
        decodificarBoleto: decodificarBoleto
    };
})(window);
