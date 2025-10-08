// Atualização da cor dos inputs de cores
document.getElementById('colorqrpicker').addEventListener('input', function() {
    document.getElementById('corqr').value = this.value;
});

document.getElementById('colorbgpicker').addEventListener('input', function() {
    document.getElementById('corbg').value = this.value;
});

// Função para mostrar/ocultar mensagem de erro
function showError(message) {
    const errorElement = document.getElementById('error-message');
    errorElement.textContent = message;
    errorElement.style.display = 'block';
    
    // Adicionar animação
    errorElement.classList.remove('fade-in');
    void errorElement.offsetWidth; // Trigger reflow
    errorElement.classList.add('fade-in');
    
    setTimeout(() => {
        errorElement.style.display = 'none';
    }, 5000);
}

// Função para mostrar sucesso
function showSuccess(message) {
    const errorElement = document.getElementById('error-message');
    errorElement.textContent = message;
    errorElement.style.display = 'block';
    errorElement.style.color = '#4ade80';
    errorElement.style.backgroundColor = 'rgba(74, 222, 128, 0.1)';
    errorElement.style.border = '1px solid rgba(74, 222, 128, 0.3)';
    
    // Adicionar animação
    errorElement.classList.remove('fade-in');
    void errorElement.offsetWidth; // Trigger reflow
    errorElement.classList.add('fade-in');
    
    setTimeout(() => {
        errorElement.style.display = 'none';
        // Reset para estilo de erro
        errorElement.style.color = '#ff6b6b';
        errorElement.style.backgroundColor = 'rgba(255, 107, 107, 0.1)';
        errorElement.style.border = '1px solid rgba(255, 107, 107, 0.3)';
    }, 3000);
}

// Função para gerar QR Code
function generateQRCode() {
    const qrCodeContainer = document.getElementById('qrcode');
    qrCodeContainer.innerHTML = '';
    const text = document.getElementById('qrcodetxt').value;
    const size = parseInt(document.getElementById('tamanho').value) * 50;
    const errorLevel = document.getElementById('errorlevel').value;
    const colorDark = document.getElementById('corqr').value || "#000000";
    const colorLight = document.getElementById('corbg').value || "#ffffff";

    if (text === '') {
        showError('Por favor, insira um texto ou URL para gerar o QR Code.');
        return;
    }

    try {
        const qr = qrcode(0, errorLevel);
        qr.addData(text);
        qr.make();

        const canvas = document.createElement("canvas");
        canvas.width = size;
        canvas.height = size;
        const ctx = canvas.getContext("2d");

        const tileW = canvas.width  / qr.getModuleCount();
        const tileH = canvas.height / qr.getModuleCount();

        ctx.fillStyle = colorLight;
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        for (let row = 0; row < qr.getModuleCount(); row++) {
            for (let col = 0; col < qr.getModuleCount(); col++) {
                ctx.fillStyle = qr.isDark(row, col) ? colorDark : colorLight;
                ctx.fillRect(col * tileW, row * tileH, tileW, tileH);
            }
        }

        qrCodeContainer.appendChild(canvas);
        
        // Adicionar título do QR Code
        const qrTitle = document.createElement('div');
        qrTitle.className = 'qr-title fade-in';
        qrTitle.textContent = 'Seu QR Code foi gerado com sucesso!';
        qrCodeContainer.appendChild(qrTitle);
        
        document.getElementById('download-btn').style.display = 'flex';
        
        // Adicionar animação
        qrCodeContainer.classList.add('fade-in');
        
        showSuccess('QR Code gerado com sucesso!');
        
    } catch (error) {
        showError('Ocorreu um erro ao gerar o QR Code. Tente novamente.');
        console.error('Erro ao gerar QR Code:', error);
    }
}

// Função para baixar QR Code
function downloadQRCode() {
    const canvas = document.querySelector('#qrcode canvas');
    if (!canvas) {
        showError('Nenhum QR Code foi gerado ainda.');
        return;
    }
    
    const link = document.createElement('a');
    link.download = 'qrcode.png';
    link.href = canvas.toDataURL("image/png").replace("image/png", "image/octet-stream");
    link.click();
    
    showSuccess('QR Code baixado com sucesso!');
}

// Adicionar funcionalidade de Enter para gerar QR Code
document.getElementById('qrcodetxt').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        generateQRCode();
    }
});

// Inicialização da página
document.addEventListener('DOMContentLoaded', function() {
    // Adicionar placeholder no container do QR Code
    const qrCodeContainer = document.getElementById('qrcode');
    const placeholder = document.createElement('div');
    placeholder.className = 'qr-placeholder';
    placeholder.innerHTML = '<i class="fas fa-qrcode" style="font-size: 3rem; margin-bottom: 10px; color: #007ced;"></i><p>Seu QR Code aparecerá aqui</p>';
    qrCodeContainer.appendChild(placeholder);
    
    // Adicionar elemento de mensagem de erro
    const errorElement = document.createElement('div');
    errorElement.id = 'error-message';
    errorElement.className = 'error-message';
    document.querySelector('.container').insertBefore(errorElement, document.querySelector('.form-group'));
    
    // Adicionar efeito de brilho ao carregar a página
    document.querySelector('.container').classList.add('fade-in');
});