/* ===== EVENT LISTENER PARA INPUT DA LINHA DIGITÁVEL ===== */
// Monitora qualquer entrada de texto no campo da linha digitável
document.getElementById('linha-digitavel').addEventListener('input', function(event) {
    // Obtém o valor atual do campo de entrada
    const linhaDigitavel = event.target.value;
    
    // Formata a linha digitável e atualiza o campo de visualização
    document.getElementById('linha-digitavel2').value = formatarLinhaDigitavel(linhaDigitavel);
    
    // Se houver conteúdo no campo, esconde o container de código exemplo
    if (linhaDigitavel.length > 0) {
        document.getElementById('codigo-container').classList.add('hidden');
    }
    
    // Atualiza os resultados com base na linha digitável inserida
    atualizarResultados(linhaDigitavel);
});

/* ===== FUNÇÃO DE FORMATAÇÃO DA LINHA DIGITÁVEL ===== */
// Converte a linha digitável para o formato padrão brasileiro
function formatarLinhaDigitavel(linhaDigitavel) {
    // Remove todos os caracteres não numéricos
    const numeros = linhaDigitavel.replace(/\D/g, '');
    
    // Aplica a formatação padrão: XXXXX.XXXXX XXXXX.X XXXXXXXXXXXXXX
    return numeros.replace(/(\d{5})(\d{5})(\d{5})(\d{1})(\d{14})/, '$1.$2 $3.$4 $5');
}

/* ===== FUNÇÃO DE CÁLCULO DE VENCIMENTO ===== */
// Calcula a data de vencimento baseada no fator de vencimento do boleto
function calcularVencimento(fatorVencimento) {
    // Data base estabelecida pelo Banco Central (07/10/1997)
    const baseDate = new Date(1997, 9, 7); // Mês 9 = Outubro (0-indexed)
    
    // Converte o fator para número inteiro
    let daysToAdd = parseInt(fatorVencimento);
    
    // Lógica especial para fatores >= 1000 (ajuste de calendário)
    if (daysToAdd >= 1000) {
        daysToAdd -= 1000; // Subtrai 1000 dias
        baseDate.setFullYear(2025); // Define ano específico
        baseDate.setMonth(1); // Define mês específico (Fevereiro)
        baseDate.setDate(22); // Define dia específico
    }
    
    // Adiciona os dias ao fator de vencimento
    baseDate.setDate(baseDate.getDate() + daysToAdd);
    
    // Retorna a data formatada no padrão brasileiro
    return baseDate.toLocaleDateString('pt-BR');
}

/* ===== FUNÇÃO DE ATUALIZAÇÃO DOS RESULTADOS ===== */
// Processa a linha digitável e exibe todas as informações do boleto
function atualizarResultados(linhaDigitavel) {
    // Limpa caracteres não numéricos
    linhaDigitavel = cleanLinhaDigitavel(linhaDigitavel);
    
    // Obtém referência ao container de resultados
    const resultDiv = document.getElementById('result');
    
    // Limpa resultados anteriores
    resultDiv.innerHTML = '';

    // Parseia a linha digitável para extrair informações
    const parsedBoleto = parseBoleto(linhaDigitavel);
    
    // Variável para acumular o HTML dos resultados
    let resultHTML = '';

    // ===== BLOCO DE VALIDAÇÃO PROGRESSIVA =====
    // Cada campo é exibido conforme o usuário digita mais caracteres
    
    // Exibe código do banco a partir do 3º caractere
    if (linhaDigitavel.length >= 3) {
        resultHTML += `<div class="result-item"><div class="result-label">Código do Banco</div><div class="result-value">${parsedBoleto.codigoBanco || 'N/A'}</div></div>`;
    }
    
    // Exibe código da moeda a partir do 4º caractere
    if (linhaDigitavel.length >= 4) {
        resultHTML += `<div class="result-item"><div class="result-label">Código da Moeda</div><div class="result-value">${parsedBoleto.codigoMoeda || 'N/A'}</div></div>`;
    }
    
    // Exibe tipo de registro a partir do 5º caractere
    if (linhaDigitavel.length >= 5) {
        resultHTML += `<div class="result-item"><div class="result-label">COM ou SEM Registro</div><div class="result-value">${parsedBoleto.comOuSemRegistro || 'N/A'}</div></div>`;
    }
    
    // Exibe tipo de carteira a partir do 6º caractere
    if (linhaDigitavel.length >= 6) {
        resultHTML += `<div class="result-item"><div class="result-label">Tipo de Carteira</div><div class="result-value">${parsedBoleto.tipoCarteira || 'N/A'}</div></div>`;
    }
    
    // Exibe nosso número a partir do 14º caractere
    if (linhaDigitavel.length >= 14) {
        resultHTML += `<div class="result-item"><div class="result-label">Nosso Número</div><div class="result-value">${parsedBoleto.nossoNumero || 'N/A'}</div></div>`;
    }
    
    // Exibe DV do nosso número a partir do 15º caractere
    if (linhaDigitavel.length >= 15) {
        resultHTML += `<div class="result-item"><div class="result-label">DV - Nosso Número</div><div class="result-value">${parsedBoleto.dvNossoNumero || 'N/A'}</div></div>`;
    }
    
    // Exibe DV do 1º campo a partir do 16º caractere
    if (linhaDigitavel.length >= 16) {
        resultHTML += `<div class="result-item"><div class="result-label">DV - do 1º Campo</div><div class="result-value">${parsedBoleto.dvPrimeiroCampo || 'N/A'}</div></div>`;
    }
    
    // Exibe cooperativa a partir do 20º caractere
    if (linhaDigitavel.length >= 20) {
        resultHTML += `<div class="result-item"><div class="result-label">Cooperativa</div><div class="result-value">${parsedBoleto.cooperativa || 'N/A'}</div></div>`;
    }
    
    // Exibe DV do 2º campo a partir do 21º caractere
    if (linhaDigitavel.length >= 21) {
        resultHTML += `<div class="result-item"><div class="result-label">DV - do 2º Campo</div><div class="result-value">${parsedBoleto.dvSegundoCampo || 'N/A'}</div></div>`;
    }
    
    // Exibe posto a partir do 23º caractere
    if (linhaDigitavel.length >= 23) {
        resultHTML += `<div class="result-item"><div class="result-label">Posto</div><div class="result-value">${parsedBoleto.Posto || 'N/A'}</div></div>`;
    }
    
    // Exibe cedente a partir do 28º caractere
    if (linhaDigitavel.length >= 28) {
        resultHTML += `<div class="result-item"><div class="result-label">Cedente</div><div class="result-value">${parsedBoleto.cedente || 'N/A'}</div></div>`;
    }
    
    // Exibe tipo de valor a partir do 29º caractere
    if (linhaDigitavel.length >= 29) {
        resultHTML += `<div class="result-item"><div class="result-label">Com ou SEM Valor</div><div class="result-value">${parsedBoleto.comOuSemValor || 'N/A'}</div></div>`;
    }
    
    // Exibe campo fixo a partir do 30º caractere
    if (linhaDigitavel.length >= 30) {
        resultHTML += `<div class="result-item"><div class="result-label">Campo FIXO</div><div class="result-value">${parsedBoleto.campoFixo || 'N/A'}</div></div>`;
    }
    
    // Exibe DV do campo livre a partir do 31º caractere
    if (linhaDigitavel.length >= 31) {
        resultHTML += `<div class="result-item"><div class="result-label">DV - Campo Livre</div><div class="result-value">${parsedBoleto.dvCampoLivre || 'N/A'}</div></div>`;
    }
    
    // Exibe DV do 3º campo a partir do 32º caractere
    if (linhaDigitavel.length >= 32) {
        resultHTML += `<div class="result-item"><div class="result-label">DV - de 3º Campo</div><div class="result-value">${parsedBoleto.dvTerceiroCampo || 'N/A'}</div></div>`;
    }
    
    // Exibe DV geral a partir do 33º caractere
    if (linhaDigitavel.length >= 33) {
        resultHTML += `<div class="result-item"><div class="result-label">DV - Geral</div><div class="result-value">${parsedBoleto.dvGeral || 'N/A'}</div></div>`;
    }
    
    // ===== BLOCO DE INFORMAÇÕES FINANCEIRAS =====
    // Exibe informações de vencimento e valor a partir do 37º caractere
    if (linhaDigitavel.length >= 37) {
        resultHTML += `
            <div class="result-item"><div class="result-label">Fator de Vencimento</div><div class="result-value">${parsedBoleto.fatorVencimento || 'N/A'}</div></div>
            <div class="result-item"><div class="result-label">Valor do Documento</div><div class="result-value">R$ ${(parseInt(parsedBoleto.valorDocumento) / 100).toFixed(2) || 'N/A'}</div></div>
            <div class="result-item"><div class="result-label">Data de Vencimento</div><div class="result-value">${calcularVencimento(parsedBoleto.fatorVencimento) || 'N/A'}</div></div>`;
    }
    
    // Insere todo o HTML gerado no container de resultados
    resultDiv.innerHTML = `<div class="result-grid">${resultHTML}</div>`;
}

/* ===== FUNÇÃO DE PARSE DA LINHA DIGITÁVEL ===== */
// Extrai todas as informações específicas da linha digitável do boleto
function parseBoleto(linhaDigitavel) {
    // Extrai cada campo específico usando substring
    const codigoBanco = linhaDigitavel.substring(0, 3) || 'N/A';           // Posições 1-3
    const codigoMoeda = linhaDigitavel.substring(3, 4) || 'N/A';           // Posição 4
    const comOuSemRegistro = linhaDigitavel.substring(4, 5) || 'N/A';      // Posição 5
    const tipoCarteira = linhaDigitavel.substring(5, 6) || 'N/A';          // Posição 6
    const nossoNumero = linhaDigitavel.substring(6, 14) || 'N/A';          // Posições 7-14
    const dvNossoNumero = linhaDigitavel.substring(14, 15) || 'N/A';       // Posição 15
    const dvPrimeiroCampo = linhaDigitavel.substring(15, 16) || 'N/A';     // Posição 16
    const cooperativa = linhaDigitavel.substring(16, 20) || 'N/A';         // Posições 17-20
    const dvSegundoCampo = linhaDigitavel.substring(20, 21) || 'N/A';      // Posição 21
    const Posto = linhaDigitavel.substring(21, 23) || 'N/A';               // Posições 22-23
    const cedente = linhaDigitavel.substring(23, 28) || 'N/A';             // Posições 24-28
    const comOuSemValor = linhaDigitavel.substring(28, 29) || 'N/A';       // Posição 29
    const campoFixo = linhaDigitavel.substring(29, 30) || 'N/A';           // Posição 30
    const dvCampoLivre = linhaDigitavel.substring(30, 31) || 'N/A';        // Posição 31
    const dvTerceiroCampo = linhaDigitavel.substring(31, 32) || 'N/A';     // Posição 32
    const dvGeral = linhaDigitavel.substring(32, 33) || 'N/A';             // Posição 33
    const fatorVencimento = linhaDigitavel.substring(33, 37) || 'N/A';     // Posições 34-37
    const valorDocumento = linhaDigitavel.substring(37, 47) || 'N/A';      // Posições 38-47

    // Retorna objeto com todas as informações extraídas
    return {
        codigoBanco,
        codigoMoeda,
        comOuSemRegistro,
        tipoCarteira,
        nossoNumero,
        dvNossoNumero,
        dvPrimeiroCampo,
        cooperativa,
        dvSegundoCampo,
        Posto,
        cedente,
        comOuSemValor,
        campoFixo,
        dvCampoLivre,
        dvTerceiroCampo,
        dvGeral,
        fatorVencimento,
        valorDocumento
    };
}

/* ===== FUNÇÃO DE LIMPEZA DA LINHA DIGITÁVEL ===== */
// Remove todos os caracteres não numéricos
function cleanLinhaDigitavel(linhaDigitavel) {
    return linhaDigitavel.replace(/[^0-9]/g, '');
}

/* ===== EVENT LISTENER PARA BOTÃO LIMPAR ===== */
// Reseta todos os campos e estados da aplicação
document.getElementById('clear-button').addEventListener('click', function() {
    // Limpa campos de entrada
    document.getElementById('linha-digitavel').value = '';
    document.getElementById('linha-digitavel2').value = '';
    
    // Limpa resultados exibidos
    document.getElementById('result').innerHTML = '';
    
    // Limpa e esconde mensagem de cópia
    document.getElementById('copy-info').textContent = '';
    document.getElementById('copy-info').classList.remove('show');
    
    // Mostra novamente o container de código exemplo
    document.getElementById('codigo-container').classList.remove('hidden');
});

/* ===== EVENT LISTENER PARA BOTÃO COPIAR LINHA DIGITÁVEL ===== */
// Copia a linha digitável formatada para a área de transferência
document.getElementById('copy-button').addEventListener('click', function() {
    // Obtém a linha digitável formatada
    const linhaDigitavel = document.getElementById('linha-digitavel2').value;
    const copyInfo = document.getElementById('copy-info');
    
    // Verifica se há conteúdo para copiar
    if (linhaDigitavel) {
        // Usa API Clipboard para copiar o texto
        navigator.clipboard.writeText(linhaDigitavel).then(() => {
            // Feedback de sucesso
            copyInfo.textContent = 'Linha digitável copiada com sucesso!';
            copyInfo.classList.add('show');
            
            // Esconde a mensagem após 3 segundos
            setTimeout(() => {
                copyInfo.classList.remove('show');
            }, 3000);
        }).catch(err => {
            // Feedback de erro
            copyInfo.textContent = 'Erro ao copiar a linha digitável!';
            copyInfo.classList.add('show');
            console.error('Erro ao copiar o texto: ', err);
        });
    } else {
        // Feedback se não houver conteúdo
        copyInfo.textContent = 'Não há linha digitável para copiar!';
        copyInfo.classList.add('show');
    }
});

/* ===== EVENT LISTENER PARA BOTÃO COPIAR CÓDIGO EXEMPLO ===== */
// Copia o código de exemplo para a área de transferência
document.getElementById('copy-codigo-button').addEventListener('click', function() {
    // Obtém o código exemplo
    const codigo = document.getElementById('codigo').textContent;
    
    // Usa API Clipboard para copiar o texto
    navigator.clipboard.writeText(codigo).then(() => {
        // Alerta de confirmação
        alert('Código copiado com sucesso!');
    }).catch(err => {
        // Log de erro no console
        console.error('Erro ao copiar o código: ', err);
    });
});