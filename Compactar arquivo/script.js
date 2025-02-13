document.addEventListener('DOMContentLoaded', () => {
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-upload');
    const downloadLink = document.getElementById('download-link');
    const status = document.getElementById('status');
    const progressBar = document.getElementById('progress-bar');
    const fileProgress = document.getElementById('file-progress');
    const fileSizeDisplay = document.getElementById('file-size');
    const selectedEntries = [];
    let totalFileSize = 0; // Tamanho total dos arquivos em bytes

    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('hover');
    });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('hover'));
    dropZone.addEventListener('drop', async (e) => {
        e.preventDefault();
        dropZone.classList.remove('hover');
        await handleEntries(e.dataTransfer.items);
    });

    fileInput.addEventListener('change', async () => {
        await handleEntries(fileInput.files);
    });

    async function handleEntries(items) {
        selectedEntries.length = 0;
        totalFileSize = 0;
        const promises = [];

        for (const item of items) {
            const entry = item.webkitGetAsEntry ? item.webkitGetAsEntry() : item;
            if (entry) {
                promises.push(traverseEntry(entry));
            }
        }

        await Promise.all(promises);

        if (selectedEntries.length > 0) {
            dropZone.innerHTML = `${selectedEntries.length} arquivo(s) ou pasta(s) selecionado(s)`;
            fileSizeDisplay.textContent = `Tamanho Total: ${formatFileSize(totalFileSize)}`;
            fileProgress.textContent = `Carregado: 0 de ${formatFileSize(totalFileSize)}`;
            await processFiles(selectedEntries);
        } else {
            dropZone.innerHTML = 'Nenhum arquivo ou pasta válido foi selecionado.';
            fileSizeDisplay.textContent = '';
            fileProgress.textContent = '';
        }
    }

    async function traverseEntry(entry, path = '') {
        if (entry.isFile) {
            await new Promise((resolve) =>
                entry.file((file) => {
                    totalFileSize += file.size; // Adiciona o tamanho do arquivo ao total
                    selectedEntries.push({ file, path });
                    resolve();
                })
            );
        } else if (entry.isDirectory) {
            const reader = entry.createReader();
            let entries = [];

            await new Promise((resolve) => {
                const readEntries = () => {
                    reader.readEntries((result) => {
                        if (result.length === 0) {
                            resolve();
                        } else {
                            entries = entries.concat(result);
                            readEntries();
                        }
                    });
                };
                readEntries();
            });

            const promises = entries.map((subEntry) => traverseEntry(subEntry, `${path}${entry.name}/`));
            await Promise.all(promises);
        }
    }

    async function processFiles(entries) {
        const zip = new JSZip();
        progressBar.style.display = 'block';
        status.textContent = 'Iniciando compactação...';
        let loadedSize = 0; // Tamanho carregado durante a compactação

        entries.forEach(({ file, path }) => {
            zip.file(`${path}${file.name}`, file);
        });

        try {
            const zipBlob = await zip.generateAsync(
                { type: 'blob' },
                (metadata) => {
                    const progress = Math.floor(metadata.percent);
                    loadedSize = (metadata.percent / 100) * totalFileSize; // Calcula o tamanho carregado com base na % de compactação
                    progressBar.value = progress;
                    fileProgress.textContent = `Carregado: ${formatFileSize(loadedSize)} de ${formatFileSize(totalFileSize)}`;
                    status.textContent = `Compactando: ${progress}% concluído...`;
                }
            );

            const url = URL.createObjectURL(zipBlob);
            downloadLink.href = url;
            downloadLink.download = 'arquivos_compactados.zip';
            downloadLink.style.display = 'block';
            progressBar.style.display = 'none';
            progressBar.value = 0;
            status.textContent = 'Compactação concluída!';
        } catch (error) {
            status.textContent = 'Erro ao compactar os arquivos.';
            console.error('Erro durante a compactação:', error);
            progressBar.style.display = 'none';
        }
    }

    function formatFileSize(bytes) {
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        if (bytes === 0) return '0 Bytes';
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return `${(bytes / Math.pow(1024, i)).toFixed(2)} ${sizes[i]}`;
    }
});
