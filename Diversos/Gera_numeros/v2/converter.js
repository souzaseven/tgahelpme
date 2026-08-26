// Conversor de Imagens: upload, redimensionamento e conversão de formato
(function () {
    const imageInput = document.getElementById('imageInput');
    const useGeneratedBtn = document.getElementById('useGeneratedBtn');
    const imagePreview = document.getElementById('imagePreview');
    const fileNameDisplay = document.getElementById('fileNameDisplay');
    const fileSuccessIcon = document.getElementById('fileSuccessIcon');
    const infoPanel = document.getElementById('infoPanel');
    const infoFileName = document.getElementById('infoFileName');
    const infoDimensions = document.getElementById('infoDimensions');
    const infoType = document.getElementById('infoType');
    const infoSize = document.getElementById('infoSize');
    const widthInput = document.getElementById('widthInput');
    const heightInput = document.getElementById('heightInput');
    const keepRatio = document.getElementById('keepRatio');
    const resetDimensionsBtn = document.getElementById('resetDimensionsBtn');
    const outputFormat = document.getElementById('outputFormat');
    const convertBtn = document.getElementById('convertBtn');
    const convertStatus = document.getElementById('convertStatus');
    const downloadResults = document.getElementById('downloadResults');

    const DOWNLOAD_ICON_SVG = '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M5 21h14"/></svg>';

    const EXTENSIONS = {
        'image/webp': 'webp',
        'image/png': 'png',
        'image/jpeg': 'jpg'
    };

    let selectedFiles = [];
    let originalWidth = 0;
    let originalHeight = 0;
    let aspectRatio = 1;

    function clearPreview(container, placeholderText) {
        container.innerHTML = '';
        const placeholder = document.createElement('span');
        placeholder.className = 'preview-placeholder';
        placeholder.textContent = placeholderText;
        container.appendChild(placeholder);
    }

    function setPreviewImage(container, src) {
        container.innerHTML = '';
        const img = document.createElement('img');
        img.src = src;
        img.alt = '';
        container.appendChild(img);
    }

    function baseName(fileName) {
        const dotIndex = fileName.lastIndexOf('.');
        return dotIndex > 0 ? fileName.slice(0, dotIndex) : fileName;
    }

    function fileExtensionLabel(file) {
        const dotIndex = file.name.lastIndexOf('.');
        const ext = dotIndex > 0 ? file.name.slice(dotIndex + 1) : '';
        return (ext || file.type.split('/')[1] || '?').toUpperCase();
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) return `${bytes} B`;
        const kb = bytes / 1024;
        if (kb < 1024) return `${kb.toFixed(1)} KB`;
        return `${(kb / 1024).toFixed(1)} MB`;
    }

    function updateFileIndicator() {
        if (selectedFiles.length === 0) {
            fileNameDisplay.textContent = 'Nenhum arquivo escolhido';
            fileSuccessIcon.hidden = true;
            return;
        }

        const [first, ...rest] = selectedFiles;
        fileNameDisplay.textContent = rest.length > 0
            ? `${first.name} +${rest.length}`
            : first.name;
        fileSuccessIcon.hidden = false;
    }

    function hideStatus() {
        convertStatus.hidden = true;
        convertStatus.classList.remove('status-success', 'status-error');
    }

    // Carrega uma lista de File (vinda do <input type="file"> ou do botão
    // "Usar imagem gerada acima") e atualiza o preview/dimensões.
    function loadFilesIntoConverter(files) {
        selectedFiles = Array.from(files || []);
        downloadResults.innerHTML = '';
        hideStatus();
        updateFileIndicator();

        if (selectedFiles.length === 0) {
            clearPreview(imagePreview, 'Nenhuma imagem');
            infoPanel.hidden = true;
            return;
        }

        const firstFile = selectedFiles[0];
        const reader = new FileReader();
        reader.onload = (event) => {
            const img = new Image();
            img.onload = () => {
                originalWidth = img.naturalWidth;
                originalHeight = img.naturalHeight;
                aspectRatio = originalWidth / originalHeight;

                widthInput.value = originalWidth;
                heightInput.value = originalHeight;

                infoPanel.hidden = false;
                infoFileName.textContent = firstFile.name;
                infoDimensions.textContent = `${originalWidth} × ${originalHeight} px`;
                infoType.textContent = fileExtensionLabel(firstFile);
                infoSize.textContent = formatFileSize(firstFile.size);

                setPreviewImage(imagePreview, event.target.result);
            };
            img.src = event.target.result;
        };
        reader.readAsDataURL(firstFile);
    }

    imageInput.addEventListener('change', () => {
        loadFilesIntoConverter(imageInput.files);
    });

    // Reaproveita a imagem gerada no Gerador de Números como entrada do conversor
    useGeneratedBtn.addEventListener('click', () => {
        const sourceCanvas = document.getElementById('canvas');
        if (!sourceCanvas) return;

        sourceCanvas.toBlob((blob) => {
            if (!blob) {
                showToast('Não foi possível capturar a imagem gerada.');
                return;
            }

            const file = new File([blob], 'numero-gerado.png', { type: 'image/png' });

            // Reflete o arquivo no próprio <input type="file"> quando o
            // navegador suportar DataTransfer (mantém a UI consistente);
            // caso contrário, o estado interno já é suficiente.
            try {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                imageInput.files = dataTransfer.files;
            } catch (error) {
                // Navegador sem suporte a DataTransfer.items.add — segue só com o estado interno.
            }

            loadFilesIntoConverter([file]);
            showToast('Imagem gerada carregada no conversor!');
        }, 'image/png');
    });

    widthInput.addEventListener('input', () => {
        if (!keepRatio.checked || !aspectRatio) return;
        const newWidth = parseInt(widthInput.value, 10);
        if (newWidth > 0) {
            heightInput.value = Math.round(newWidth / aspectRatio);
        }
    });

    heightInput.addEventListener('input', () => {
        if (!keepRatio.checked || !aspectRatio) return;
        const newHeight = parseInt(heightInput.value, 10);
        if (newHeight > 0) {
            widthInput.value = Math.round(newHeight * aspectRatio);
        }
    });

    resetDimensionsBtn.addEventListener('click', () => {
        if (!originalWidth || !originalHeight) return;
        widthInput.value = originalWidth;
        heightInput.value = originalHeight;
    });

    function loadImage(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (event) => {
                const img = new Image();
                img.onload = () => resolve(img);
                img.onerror = reject;
                img.src = event.target.result;
            };
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }

    function convertImage(img, width, height, mimeType) {
        return new Promise((resolve, reject) => {
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');

            if (mimeType === 'image/jpeg') {
                // JPEG não suporta transparência: preenche com fundo branco
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, width, height);
            }

            ctx.drawImage(img, 0, 0, width, height);
            canvas.toBlob((blob) => {
                if (blob) {
                    resolve(blob);
                } else {
                    reject(new Error('Falha ao converter imagem'));
                }
            }, mimeType, 0.92);
        });
    }

    convertBtn.addEventListener('click', async () => {
        if (selectedFiles.length === 0) return;

        const width = parseInt(widthInput.value, 10);
        const height = parseInt(heightInput.value, 10);
        if (!width || !height || width <= 0 || height <= 0) return;

        const mimeType = outputFormat.value;
        const extension = EXTENSIONS[mimeType] || 'png';

        downloadResults.innerHTML = '';
        hideStatus();
        convertBtn.disabled = true;
        convertBtn.textContent = 'Convertendo...';

        let convertedCount = 0;

        try {
            for (let i = 0; i < selectedFiles.length; i++) {
                const file = selectedFiles[i];
                const img = await loadImage(file);
                const blob = await convertImage(img, width, height, mimeType);
                const url = URL.createObjectURL(blob);
                const fileName = `${baseName(file.name)}.${extension}`;

                if (i === 0) {
                    setPreviewImage(imagePreview, url);
                }

                const item = document.createElement('li');
                const link = document.createElement('a');
                link.href = url;
                link.download = fileName;
                link.innerHTML = `${DOWNLOAD_ICON_SVG} ${fileName}`;
                item.appendChild(link);
                downloadResults.appendChild(item);
                convertedCount++;
            }

            convertStatus.hidden = false;
            convertStatus.classList.add('status-success');
            convertStatus.textContent = convertedCount === 1
                ? 'Imagem convertida com sucesso!'
                : `${convertedCount} imagens convertidas com sucesso!`;
            showToast('Conversão concluída!');
        } catch (error) {
            convertStatus.hidden = false;
            convertStatus.classList.add('status-error');
            convertStatus.textContent = 'Ocorreu um erro ao converter uma ou mais imagens.';
        } finally {
            convertBtn.disabled = false;
            convertBtn.innerHTML = '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 7h11l-3-3"/><path d="M17 17H6l3 3"/></svg> Converter imagens';
        }
    });
})();
