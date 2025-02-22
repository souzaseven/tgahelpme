 document.addEventListener('DOMContentLoaded', () => {
        const today = new Date().toISOString().substr(0, 10);
        document.getElementById('data').value = today;

        document.getElementById('getFile').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.getElementById('logoPreview');
                    img.src = e.target.result;
                    img.style.display = 'block';
                    document.querySelector('.logomarca').style.display = 'none';
                    document.querySelector('.resize-container').style.display = 'inline-block';
                };
                reader.readAsDataURL(file);
            }
        });

        // Resize functionality
        const resizeHandle = document.querySelector('.resize-handle');
        const resizeContainer = document.querySelector('.resize-container');
        let isResizing = false;

        resizeHandle.addEventListener('mousedown', function(e) {
            isResizing = true;
            document.addEventListener('mousemove', resize);
            document.addEventListener('mouseup', stopResize);
        });

        function resize(e) {
            if (isResizing) {
                const width = e.clientX - resizeContainer.getBoundingClientRect().left;
                const height = e.clientY - resizeContainer.getBoundingClientRect().top;
                resizeContainer.style.width = `${width}px`;
                resizeContainer.style.height = `${height}px`;
            }
        }

        function stopResize() {
            isResizing = false;
            document.removeEventListener('mousemove', resize);
            document.removeEventListener('mouseup', stopResize);
        }
    });

    document.getElementById('imprimir').addEventListener('click', function() {
        const vias = document.getElementById('vias').value;
        for (let i = 0; i < vias; i++) {
            window.print(); // Print the document. Each print will create a new copy.
        }
    });

    document.getElementById('limpar').addEventListener('click', function() {
        // Clear all input fields
        document.querySelectorAll('.recibo input').forEach(input => input.value = '');
        document.querySelector('.logomarca').style.display = 'flex'; // Show the logomarca label again
        document.querySelector('.resize-container').style.display = 'none'; // Hide the logo preview
        document.getElementById('logoPreview').src = ''; // Clear the logo src
        document.dispatchEvent(new Event('DOMContentLoaded')); // Reset date inputs
    });