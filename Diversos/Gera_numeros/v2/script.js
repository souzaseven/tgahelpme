// Toast compartilhado (usado também pelo converter.js)
let toastTimeoutId = null;
function showToast(message) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    toast.textContent = message;
    toast.hidden = false;
    toast.classList.add('toast-visible');
    clearTimeout(toastTimeoutId);
    toastTimeoutId = setTimeout(() => {
        toast.classList.remove('toast-visible');
        toast.hidden = true;
    }, 2500);
}

function generateImage() {
    const canvas = document.getElementById('canvas');
    const ctx = canvas.getContext('2d');

    const number = document.getElementById('number').value;
    const circleColor = document.getElementById('circleColor').value;
    const numberColor = document.getElementById('numberColor').value;

    // Set canvas size to fit the display area
    canvas.width = 512;
    canvas.height = 512;

    // Clear canvas
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Draw circle
    ctx.beginPath();
    ctx.arc(canvas.width / 2, canvas.height / 2, 200, 0, Math.PI * 2, false);
    ctx.fillStyle = circleColor;
    ctx.fill();

    // Determine font size based on number length
    let fontSize = 200; // Default font size
    let textWidth;
    let textHeight;

    // Reduce font size until the text fits within the circle
    do {
        ctx.font = `bold ${fontSize}px Arial`;
        textWidth = ctx.measureText(number).width;
        textHeight = fontSize; // Rough estimate for text height
        fontSize -= 10; // Reduce font size
    } while ((textWidth > 2 * 200 || textHeight > 2 * 200) && fontSize > 20);

    // Draw number
    ctx.fillStyle = numberColor;
    ctx.font = `bold ${fontSize}px Arial`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(number, canvas.width / 2, canvas.height / 2);
}

// Dispara o download de uma dataURL sem depender de <a> envolvendo <button>
function downloadDataUrl(dataUrl, fileName) {
    const link = document.createElement('a');
    link.href = dataUrl;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    link.remove();
}

// Sincroniza o "pill" visual (bolinha + texto hex) com o valor real do <input type="color">
function updateColorPill(inputId, swatchId, hexId) {
    const input = document.getElementById(inputId);
    const swatch = document.getElementById(swatchId);
    const hex = document.getElementById(hexId);
    const value = input.value.toUpperCase();
    swatch.style.backgroundColor = value;
    hex.textContent = value;
}

function updateColorPills() {
    updateColorPill('circleColor', 'circleColorSwatch', 'circleColorHex');
    updateColorPill('numberColor', 'numberColorSwatch', 'numberColorHex');
}

function updateGenerator() {
    generateImage();
    updateColorPills();
}

// Event listeners for automatic updates
document.getElementById('number').addEventListener('input', updateGenerator);
document.getElementById('circleColor').addEventListener('input', updateGenerator);
document.getElementById('numberColor').addEventListener('input', updateGenerator);

document.getElementById('downloadButton').addEventListener('click', () => {
    const canvas = document.getElementById('canvas');
    downloadDataUrl(canvas.toDataURL('image/png'), 'number.png');
    showToast('Imagem baixada com sucesso!');
});

document.getElementById('iconDownloadButton').addEventListener('click', () => {
    const canvas = document.getElementById('canvas');
    downloadDataUrl(canvas.toDataURL('image/png'), 'number.ico');
    showToast('Ícone baixado com sucesso!');
});

// Initial image generation
generateImage();
updateColorPills();
