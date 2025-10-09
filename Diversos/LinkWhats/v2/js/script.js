     // Elementos DOM
        const numeroInput = document.getElementById('numero');
        const mensagemTextarea = document.getElementById('mensagem');
        const gerarBtn = document.getElementById('gerar');
        const iniciarBtn = document.getElementById('iniciar');
        const gerarQrBtn = document.getElementById('gerar-qrcode');
        const copiarLinkBtn = document.getElementById('copiar-link');
        const abrirWhatsappBtn = document.getElementById('abrir-whatsapp');
        const copiarQrBtn = document.getElementById('copiar-qr');
        const erroDiv = document.getElementById('erro');
        const linkContainer = document.getElementById('link-container');
        const linkDisplayContainer = document.getElementById('link-display-container');
        const linkDisplay = document.getElementById('link-display');
        const whatsappLink = document.getElementById('whatsapp-link');
        const whatsappWebLink = document.getElementById('whatsapp-web-link');
        const qrcodeContainer = document.getElementById('qrcode-container');
        const qrcodeDiv = document.getElementById('qrcode');
        const feedbackDiv = document.getElementById('feedback');

        // Variável para armazenar o link gerado
        let linkGerado = '';

        // Validação do número de telefone
        function validarNumero(numero) {
            // Remove caracteres não numéricos
            const numeroLimpo = numero.replace(/\D/g, '');
            // Verifica se tem entre 10 e 15 dígitos (incluindo código do país)
            return numeroLimpo.length >= 10 && numeroLimpo.length <= 15;
        }

        // Atualizar estado dos botões
        function atualizarBotoes() {
            const numeroValido = validarNumero(numeroInput.value);
            
            gerarBtn.disabled = !numeroValido;
            iniciarBtn.disabled = !numeroValido;
            gerarQrBtn.disabled = !numeroValido;
            
            // Esconder erro se o número for válido
            if (numeroValido) {
                erroDiv.style.display = 'none';
            }
        }

        // Função para fazer scroll suave para o elemento
        function scrollParaElemento(elemento) {
            elemento.scrollIntoView({ 
                behavior: 'smooth',
                block: 'center'
            });
        }

        // Gerar link do WhatsApp
        function gerarLinkWhatsApp() {
            const numero = numeroInput.value.replace(/\D/g, '');
            const mensagem = encodeURIComponent(mensagemTextarea.value);
            
            // Link para WhatsApp mobile
            const linkMobile = `https://wa.me/${numero}?text=${mensagem}`;
            
            // Link para WhatsApp Web
            const linkWeb = `https://web.whatsapp.com/send?phone=${numero}&text=${mensagem}`;
            
            // Atualizar links
            whatsappLink.href = linkMobile;
            whatsappWebLink.href = linkWeb;
            linkGerado = linkMobile;
            
            // Mostrar links
            whatsappLink.style.display = 'inline-block';
            whatsappWebLink.style.display = 'inline-block';
            
            // Mostrar link gerado em texto
            linkDisplay.textContent = linkGerado;
            linkDisplayContainer.style.display = 'flex';
            
            // Adicionar animação
            linkContainer.classList.add('fade-in');
            linkDisplayContainer.classList.add('fade-in');
            
            // Fazer scroll suave para o link gerado
            setTimeout(() => {
                scrollParaElemento(linkDisplayContainer);
            }, 300);
            
            // Mostrar feedback
            mostrarFeedback('Links gerados com sucesso!');
        }

        // Iniciar conversa no WhatsApp
        function iniciarConversa() {
            const numero = numeroInput.value.replace(/\D/g, '');
            const mensagem = encodeURIComponent(mensagemTextarea.value);
            
            // Abrir WhatsApp mobile
            window.open(`https://wa.me/${numero}?text=${mensagem}`, '_blank');
            
            // Mostrar feedback
            mostrarFeedback('Conversa iniciada no WhatsApp!');
        }

        // Copiar link para a área de transferência
        function copiarLink() {
            if (!linkGerado) {
                mostrarFeedback('Nenhum link gerado para copiar', true);
                return;
            }
            
            navigator.clipboard.writeText(linkGerado)
                .then(() => {
                    // Feedback visual
                    copiarLinkBtn.innerHTML = '<i class="fas fa-check"></i> Copiado!';
                    copiarLinkBtn.classList.add('pulse');
                    
                    setTimeout(() => {
                        copiarLinkBtn.innerHTML = '<i class="fa fa-copy"></i> Copiar Link';
                        copiarLinkBtn.classList.remove('pulse');
                    }, 2000);
                    
                    mostrarFeedback('Link copiado para a área de transferência!');
                })
                .catch(err => {
                    console.error('Erro ao copiar: ', err);
                    mostrarFeedback('Erro ao copiar o link', true);
                });
        }

        // Abrir WhatsApp com o link gerado
        function abrirWhatsApp() {
            if (!linkGerado) {
                mostrarFeedback('Nenhum link gerado para abrir', true);
                return;
            }
            
            window.open(linkGerado, '_blank');
            mostrarFeedback('WhatsApp aberto com sucesso!');
        }

        // Gerar QR Code
        function gerarQRCode() {
            const numero = numeroInput.value.replace(/\D/g, '');
            const mensagem = encodeURIComponent(mensagemTextarea.value);
            const link = `https://wa.me/${numero}?text=${mensagem}`;
            
            // Limpar QR Code anterior
            qrcodeDiv.innerHTML = '';
            
            // Gerar novo QR Code
            new QRCode(qrcodeDiv, {
                text: link,
                width: 256,
                height: 256,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
            
            // Mostrar QR Code e botão de copiar
            qrcodeContainer.style.display = 'flex';
            copiarQrBtn.style.display = 'flex';
            
            // Fazer scroll suave para o QR Code
            setTimeout(() => {
                scrollParaElemento(qrcodeContainer);
            }, 300);
            
            // Mostrar feedback
            mostrarFeedback('QR Code gerado com sucesso!');
        }

        // Copiar QR Code como imagem
        function copiarQRCode() {
            const canvas = qrcodeDiv.querySelector('canvas');
            if (!canvas) {
                mostrarFeedback('QR Code não encontrado', true);
                return;
            }
            
            canvas.toBlob(function(blob) {
                const item = new ClipboardItem({ "image/png": blob });
                navigator.clipboard.write([item])
                    .then(() => {
                        // Feedback visual
                        copiarQrBtn.innerHTML = '<i class="fas fa-check"></i> QR Code Copiado!';
                        copiarQrBtn.classList.add('pulse');
                        
                        setTimeout(() => {
                            copiarQrBtn.innerHTML = '<i class="fas fa-download"></i> Copiar QR Code';
                            copiarQrBtn.classList.remove('pulse');
                        }, 2000);
                        
                        mostrarFeedback('QR Code copiado para a área de transferência!');
                    })
                    .catch(err => {
                        console.error('Erro ao copiar QR Code: ', err);
                        // Fallback: download da imagem
                        const link = document.createElement('a');
                        link.download = 'whatsapp-qrcode.png';
                        link.href = canvas.toDataURL();
                        link.click();
                        mostrarFeedback('QR Code baixado como imagem!');
                    });
            });
        }

        // Mostrar feedback
        function mostrarFeedback(mensagem, isError = false) {
            feedbackDiv.innerHTML = `<i class="fas fa-${isError ? 'exclamation-triangle' : 'check-circle'}"></i> ${mensagem}`;
            
            if (isError) {
                feedbackDiv.style.background = 'linear-gradient(145deg, var(--danger), #e55a5a)';
            } else {
                feedbackDiv.style.background = 'linear-gradient(145deg, var(--success), #00b874)';
            }
            
            feedbackDiv.style.display = 'block';
            feedbackDiv.classList.add('fade-in');
            
            setTimeout(() => {
                feedbackDiv.style.display = 'none';
            }, 3000);
        }

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            // Event listeners
            numeroInput.addEventListener('input', atualizarBotoes);
            mensagemTextarea.addEventListener('input', atualizarBotoes);
            gerarBtn.addEventListener('click', gerarLinkWhatsApp);
            iniciarBtn.addEventListener('click', iniciarConversa);
            copiarLinkBtn.addEventListener('click', copiarLink);
            abrirWhatsappBtn.addEventListener('click', abrirWhatsApp);
            gerarQrBtn.addEventListener('click', gerarQRCode);
            copiarQrBtn.addEventListener('click', copiarQRCode);
            
            // Inicializar estado dos botões
            atualizarBotoes();
        });

        // Função para alerta do WhatsApp Web (mantida para compatibilidade)
        function alertaWhatsAppWeb() {
            mostrarFeedback('Abrindo WhatsApp Web...');
        }