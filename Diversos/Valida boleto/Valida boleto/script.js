  document.getElementById('linha-digitavel').addEventListener('input', function(event) {
            const linhaDigitavel = event.target.value;
            document.getElementById('linha-digitavel2').value = formatarLinhaDigitavel(linhaDigitavel);
            
            if (linhaDigitavel.length > 0) {
                document.getElementById('codigo-container').classList.add('hidden');
            }
            atualizarResultados(linhaDigitavel);
        });

        function formatarLinhaDigitavel(linhaDigitavel) {
            const numeros = linhaDigitavel.replace(/\D/g, '');
            return numeros.replace(/(\d{5})(\d{5})(\d{5})(\d{1})(\d{14})/, '$1.$2 $3.$4 $5');
        }

        function calcularVencimento(fatorVencimento) {
            const baseDate = new Date(1997, 9, 7);
            baseDate.setDate(baseDate.getDate() + parseInt(fatorVencimento));
            return baseDate.toLocaleDateString('pt-BR');
        }

        function atualizarResultados(linhaDigitavel) {
            linhaDigitavel = cleanLinhaDigitavel(linhaDigitavel);
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = '';

            const parsedBoleto = parseBoleto(linhaDigitavel);

            if (linhaDigitavel.length >= 3) {
                resultDiv.innerHTML += `<div class="field"><span>Código do Banco:</span> ${parsedBoleto.codigoBanco || 'N/A'}</div>`;
            }
            if (linhaDigitavel.length >= 4) {
                resultDiv.innerHTML += `<div class="field"><span>Código da Moeda:</span> ${parsedBoleto.codigoMoeda || 'N/A'}</div>`;
            }
            if (linhaDigitavel.length >= 5) {
                resultDiv.innerHTML += `<div class="field"><span>COM ou SEM Registro:</span> ${parsedBoleto.comOuSemRegistro || 'N/A'}</div>`;
            }
            if (linhaDigitavel.length >= 6) {
                resultDiv.innerHTML += `<div class="field"><span>Tipo de Carteira:</span> ${parsedBoleto.tipoCarteira || 'N/A'}</div>`;
            }
            if (linhaDigitavel.length >= 14) {
                resultDiv.innerHTML += `<div class="field"><span>Nosso Número:</span> ${parsedBoleto.nossoNumero || 'N/A'}</div>`;
            }
            if (linhaDigitavel.length >= 15) {
                resultDiv.innerHTML += `<div class="field"><span>DV - Nosso Número:</span> ${parsedBoleto.dvNossoNumero || 'N/A'}</div>`;
            }
            if (linhaDigitavel.length >= 16) {
                resultDiv.innerHTML += `<div class="field"><span>DV - do 1º Campo:</span> ${parsedBoleto.dvPrimeiroCampo || 'N/A'}</div>`;
            }
            if (linhaDigitavel.length >= 20) {
                resultDiv.innerHTML += `<div class="field"><span>Cooperativa:</span> ${parsedBoleto.cooperativa || 'N/A'}</div>`;
            }
            if (linhaDigitavel.length >= 21) {
                resultDiv.innerHTML += `<div class="field"><span>DV - do 2º Campo:</span> ${parsedBoleto.dvSegundoCampo || 'N/A'}</div>`;
            }
            if (linhaDigitavel.length >= 23) {
                resultDiv.innerHTML += `<div class="field"><span>Posto:</span> ${parsedBoleto.Posto || 'N/A'}</div>`;
            }
            if (linhaDigitavel.length >= 28) {
                resultDiv.innerHTML += `<div class="field"><span>Cedente:</span> ${parsedBoleto.cedente || 'N/A'}</div>`;
            }
            if (linhaDigitavel.length >= 29) {
                resultDiv.innerHTML += `<div class="field"><span>Com ou SEM Valor:</span> ${parsedBoleto.comOuSemValor || 'N/A'}</div>`;
            }
            if (linhaDigitavel.length >= 30) {
                resultDiv.innerHTML += `<div class="field"><span>Campo FIXO:</span> ${parsedBoleto.campoFixo || 'N/A'}</div>`;
            }
            if (linhaDigitavel.length >= 31) {
                resultDiv.innerHTML += `<div class="field"><span>DV - Campo Livre:</span> ${parsedBoleto.dvCampoLivre || 'N/A'}</div>`;
            }
            if (linhaDigitavel.length >= 32) {
                resultDiv.innerHTML += `<div class="field"><span>DV - de 3º Campo:</span> ${parsedBoleto.dvTerceiroCampo || 'N/A'}</div>`;
            }
            if (linhaDigitavel.length >= 33) {
                resultDiv.innerHTML += `<div class="field"><span>DV - Geral:</span> ${parsedBoleto.dvGeral || 'N/A'}</div>`;
            }
            if (linhaDigitavel.length >= 37) {
                resultDiv.innerHTML += `
                    <div class="field-inline">
                        <div class="field"><span>Fator de Vencimento:</span> ${parsedBoleto.fatorVencimento || 'N/A'}</div>
                        <div class="field"><span>Valor do Documento:</span> R$ ${(parseInt(parsedBoleto.valorDocumento) / 100).toFixed(2) || 'N/A'}</div>
                        <div class="field"><span>Data de Vencimento:</span> ${calcularVencimento(parsedBoleto.fatorVencimento) || 'N/A'}</div>
                    </div>`;
            }
        }

        function parseBoleto(linhaDigitavel) {
            const codigoBanco = linhaDigitavel.substring(0, 3) || 'N/A';
            const codigoMoeda = linhaDigitavel.substring(3, 4) || 'N/A';
            const comOuSemRegistro = linhaDigitavel.substring(4, 5) || 'N/A';
            const tipoCarteira = linhaDigitavel.substring(5, 6) || 'N/A';
            const nossoNumero = linhaDigitavel.substring(6, 14) || 'N/A';
            const dvNossoNumero = linhaDigitavel.substring(14, 15) || 'N/A';
            const dvPrimeiroCampo = linhaDigitavel.substring(15, 16) || 'N/A';
            const cooperativa = linhaDigitavel.substring(16, 20) || 'N/A';
            const dvSegundoCampo = linhaDigitavel.substring(20, 21) || 'N/A';
            const Posto = linhaDigitavel.substring(21, 23) || 'N/A';
            const cedente = linhaDigitavel.substring(23, 28) || 'N/A';
            const comOuSemValor = linhaDigitavel.substring(28, 29) || 'N/A';
            const campoFixo = linhaDigitavel.substring(29, 30) || 'N/A';
            const dvCampoLivre = linhaDigitavel.substring(30, 31) || 'N/A';
            const dvTerceiroCampo = linhaDigitavel.substring(31, 32) || 'N/A';
            const dvGeral = linhaDigitavel.substring(32, 33) || 'N/A';
            const fatorVencimento = linhaDigitavel.substring(33, 37) || 'N/A';
            const valorDocumento = linhaDigitavel.substring(37, 47) || 'N/A';

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

        function cleanLinhaDigitavel(linhaDigitavel) {
            return linhaDigitavel.replace(/[^0-9]/g, '');
        }

        document.getElementById('clear-button').addEventListener('click', function() {
            document.getElementById('linha-digitavel').value = '';
            document.getElementById('linha-digitavel2').value = '';
            document.getElementById('result').innerHTML = '';
            document.getElementById('copy-info').textContent = '';
            document.getElementById('codigo-container').classList.remove('hidden');
        });

        document.getElementById('copy-button').addEventListener('click', function() {
            const linhaDigitavel = document.getElementById('linha-digitavel2').value;
            if (linhaDigitavel) {
                navigator.clipboard.writeText(linhaDigitavel).then(() => {
                    document.getElementById('copy-info').textContent = 'Linha digitável copiada com sucesso!';
                }).catch(err => {
                    console.error('Erro ao copiar o texto: ', err);
                });
            } else {
                document.getElementById('copy-info').textContent = 'Não há linha digitável para copiar!';
            }
        });

        document.getElementById('copy-codigo-button').addEventListener('click', function() {
            const codigo = document.getElementById('codigo').textContent;
            navigator.clipboard.writeText(codigo).then(() => {
                alert('Código copiado com sucesso!');
            }).catch(err => {
                console.error('Erro ao copiar o código: ', err);
            });
        });