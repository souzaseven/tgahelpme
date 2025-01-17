// Função para copiar a variável para a área de transferência
function copyVariable(variable) {
    const tempInput = document.createElement('input');
    document.body.appendChild(tempInput);
    tempInput.value = variable;
    tempInput.select();
    document.execCommand('copy');
    document.body.removeChild(tempInput);
    alert(`Variável "${variable}" copiada!`);
}

// Função para adicionar um novo texto no container2
function addNewText() {
    const newText = document.getElementById('newTextInput').value.trim();

    if (newText === '') {
        alert('Por favor, insira um texto.');
        return;
    }

    // Cria o novo bloco de mensagem
    const newBlock = document.createElement('div');
    newBlock.classList.add('variable-block');

    const newTitle = document.createElement('div');
    newTitle.classList.add('block-title');
    newTitle.textContent = 'Nova Mensagem';

    const newMessage = document.createElement('p');
    newMessage.textContent = newText;

    newBlock.appendChild(newTitle);
    newBlock.appendChild(newMessage);

    // Adiciona o novo bloco ao container2
    const container2 = document.querySelector('.container2');
    container2.appendChild(newBlock);

    // Limpa o campo de entrada
    document.getElementById('newTextInput').value = '';
}

 function copyText() {
        // Seleciona o botão clicado
        const button = event.target;

        // Encontra o parágrafo anterior ao botão
        const messageElement = button.previousElementSibling;

        // Verifica se existe o parágrafo com o texto a copiar
        if (messageElement && messageElement.classList.contains('text-to-copy')) {
            // Obtém o texto do parágrafo
            const textToCopy = messageElement.textContent;

            // Copia o texto para a área de transferência
            navigator.clipboard.writeText(textToCopy)
                .then(() => {
                    alert('Texto copiado com sucesso!');
                })
                .catch(err => {
                    alert('Erro ao copiar o texto: ' + err);
                });
        } else {
            alert('Não foi possível encontrar o texto para copiar.');
        }
    }