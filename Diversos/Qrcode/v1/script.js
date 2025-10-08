// Atualização da cor dos inputs de cores
document.getElementById('colorqrpicker').addEventListener('input', function() {
    document.getElementById('corqr').value = this.value;
});

document.getElementById('colorbgpicker').addEventListener('input', function() {
    document.getElementById('corbg').value = this.value;
});

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
        alert('Por favor, insira um texto para gerar o QR Code.');
        return;
    }

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
    document.getElementById('download-btn').style.display = 'inline-block';
}

// Função para baixar QR Code
function downloadQRCode() {
    const canvas = document.querySelector('#qrcode canvas');
    const link = document.createElement('a');
    link.download = 'qrcode.png';
    link.href = canvas.toDataURL("image/png").replace("image/png", "image/octet-stream");
    link.click();
}
