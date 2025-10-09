document.addEventListener('DOMContentLoaded', function() {
    const gerarButton = document.getElementById('gerar');
    const iniciarButton = document.getElementById('iniciar');
    const copiarButton = document.getElementById('copiar');
    const qrCodeButton = document.getElementById('gerar-qrcode');
    const numeroInput = document.getElementById('numero');
    const mensagemTextarea = document.getElementById('mensagem');
    const whatsappLink = document.getElementById('whatsapp-link');
    const whatsappWebLink = document.getElementById('whatsapp-web-link');
    const erroDiv = document.getElementById('erro');
    const qrcodeDiv = document.getElementById('qrcode');
    const feedbackDiv = document.getElementById('feedback');
    const themeSelector = document.getElementById('theme-selector');

    const codigoPais = '+55';

    // Função para criar o link do WhatsApp
    function criarLink(numero, mensagem) {
        const mensagemFormatada = encodeURIComponent(mensagem);
        const numeroFormatado = numero.replace(/\D/g, ''); // Remove caracteres não numéricos
        return `https://wa.me/${codigoPais}${numeroFormatado}?text=${mensagemFormatada}`;
    }

    // Função para criar o link do WhatsApp Web
    function criarLinkWeb(numero, mensagem) {
        const mensagemFormatada = encodeURIComponent(mensagem);
        const numeroFormatado = numero.replace(/\D/g, ''); // Remove caracteres não numéricos
        return `https://web.whatsapp.com/send?phone=${codigoPais}${numeroFormatado}&text=${mensagemFormatada}`;
    }

    // Função para gerar o QR Code
    function gerarQRCode(link) {
        qrcodeDiv.innerHTML = '';
        new QRCode(qrcodeDiv, link);
    }

    // Função para validar se o número foi preenchido
    function validarNumero() {
        const numero = numeroInput.value.trim();
        if (numero === '') {
            erroDiv.style.display = 'block';
            return false;
        } else {
            erroDiv.style.display = 'none';
            return true;
        }
    }

    // Função para mostrar feedback de sucesso
    function mostrarFeedback(mensagem) {
        feedbackDiv.innerText = mensagem;
        feedbackDiv.style.display = 'block';
        setTimeout(() => {
            feedbackDiv.style.display = 'none';
        }, 3000); // Esconde após 3 segundos
    }

    // Evento para o botão "Gerar"
    gerarButton.addEventListener('click', function() {
        if (!validarNumero()) return;

        const numero = numeroInput.value;
        const mensagem = mensagemTextarea.value;
        const link = criarLink(numero, mensagem);
        const linkWeb = criarLinkWeb(numero, mensagem);

        whatsappLink.href = link;
        whatsappWebLink.href = linkWeb;

        whatsappLink.style.display = 'inline';
        whatsappLink.textContent = 'Clique aqui para conversar no WhatsApp';

        whatsappWebLink.style.display = 'inline';
        whatsappWebLink.textContent = 'Clique aqui para conversar no WhatsApp Web';

        copiarButton.style.display = 'inline';

        mostrarFeedback('Link gerado com sucesso!');
    });

    // Evento para o botão "Iniciar"
    iniciarButton.addEventListener('click', function() {
        if (!validarNumero()) return;

        const numero = numeroInput.value;
        const mensagem = mensagemTextarea.value;
        const link = criarLink(numero, mensagem);

        window.open(link, '_blank');

        mostrarFeedback('Conversa iniciada com sucesso!');
    });

    // Evento para o botão "Copiar"
    copiarButton.addEventListener('click', function() {
        if (!validarNumero()) return;

        const numero = numeroInput.value;
        const mensagem = mensagemTextarea.value;
        const link = criarLink(numero, mensagem);

        navigator.clipboard.writeText(link).then(() => {
            mostrarFeedback('Link copiado para a área de transferência!');
        });
    });

    // Evento para o botão "Gerar QR Code"
    qrCodeButton.addEventListener('click', function() {
        if (!validarNumero()) return;

        const numero = numeroInput.value;
        const mensagem = mensagemTextarea.value;
        const link = criarLink(numero, mensagem);

        gerarQRCode(link);

        mostrarFeedback('QR Code gerado com sucesso!');
    });

    // Função para aplicar tema
    themeSelector.addEventListener('change', function() {
        const selectedTheme = themeSelector.value;
        document.body.className = selectedTheme;
    });

    // Verificação para habilitar/desabilitar botões
    numeroInput.addEventListener('input', function () {
        const isValid = validarNumero();
        gerarButton.disabled = !isValid;
        iniciarButton.disabled = !isValid;
        qrCodeButton.disabled = !isValid;
    });
});

// Função de alerta para o WhatsApp Web
function alertaWhatsAppWeb() {
    alert('Se preferir, abra este link em um navegador onde o WhatsApp Web já está conectado.');
}
