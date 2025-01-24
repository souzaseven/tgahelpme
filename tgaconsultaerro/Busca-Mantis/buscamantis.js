const generateLinkButton = document.getElementById('generateLink');
        const mantisNumberInput = document.getElementById('mantisNumber');
        const linkContainer = document.getElementById('linkContainer');
        const historyList = document.getElementById('historyList');
        const clearHistoryButton = document.getElementById('clearHistory');

        // Carregar histórico do localStorage
        const savedHistory = JSON.parse(localStorage.getItem('mantisHistory')) || [];

        // Função para atualizar o histórico no DOM
        function updateHistory() {
            historyList.innerHTML = ''; // Limpa o histórico exibido
            savedHistory.slice().reverse().forEach(number => {
                const historyItem = document.createElement('div');
                historyItem.classList.add('history-item');
                historyItem.textContent = number;

                // Evento para abrir o link ao clicar no número
                historyItem.addEventListener('click', () => {
                    const link = `https://mantis.tgasistemas.com.br/view.php?id=${number}`;
                    window.open(link, '_blank');
                });

                historyList.appendChild(historyItem);
            });
        }

        // Função para adicionar um número ao histórico
        function addToHistory(number) {
            savedHistory.push(number); // Adiciona o número ao array
            localStorage.setItem('mantisHistory', JSON.stringify(savedHistory)); // Salva no localStorage
            updateHistory(); // Atualiza o histórico na tela
        }

        // Limpar histórico
        clearHistoryButton.addEventListener('click', () => {
            localStorage.removeItem('mantisHistory'); // Remove do localStorage
            savedHistory.length = 0; // Limpa o array
            updateHistory(); // Atualiza o histórico na tela
        });

        // Evento de clique para gerar link e salvar no histórico
        generateLinkButton.addEventListener('click', function () {
            const mantisNumber = mantisNumberInput.value;

            if (mantisNumber) {
                const link = `https://mantis.tgasistemas.com.br/view.php?id=${mantisNumber}`;
                // Exibe o link na página
                linkContainer.innerHTML = `<a href="${link}" target="_blank">Acessar: ${link}</a>`;
                // Adiciona ao histórico
                addToHistory(mantisNumber);
                // Abre o link automaticamente em uma nova aba
                window.open(link, '_blank');
            } else {
                linkContainer.innerHTML = `<p style="color: red;">Por favor, insira um número válido!</p>`;
            }
        });

        // Atualiza o histórico na tela ao carregar a página
        updateHistory();