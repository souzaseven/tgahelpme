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

    // Set up download links
    const downloadLink = document.getElementById('downloadLink');
    downloadLink.href = canvas.toDataURL('image/png');

    const iconDownloadLink = document.getElementById('iconDownloadLink');
    iconDownloadLink.href = canvas.toDataURL('image/x-icon');
}

// Event listeners for automatic updates
document.getElementById('number').addEventListener('input', generateImage);
document.getElementById('circleColor').addEventListener('input', generateImage);
document.getElementById('numberColor').addEventListener('input', generateImage);

// Initial image generation
generateImage();
