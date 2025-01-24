document.getElementById("searchButton").addEventListener("click", function() {
    let searchTerm = document.getElementById("searchInput").value;

    // Mostrando animação de "Consultando..."
    let resultsContainer = document.getElementById("resultsContainer");
    resultsContainer.innerHTML = "<p>Consultando...</p>";

    // Simula a busca (substitua com a busca real)
    setTimeout(function() {
        // Aqui você pode realizar a busca real, substitua essa parte com sua lógica de busca
        // Exemplo de resultado simulado que deve ser substituído pela sua consulta real
        fetchResults(searchTerm); // Chama a função de busca com o termo de pesquisa
    }, 1500); // Tempo de espera para simular a busca
});

// Função para buscar os resultados no banco de dados
function fetchResults(query) {
    fetch('./buscar.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'query=' + encodeURIComponent(query)
    })
    .then(response => response.json())
    .then(data => {
        hideLoading(); // Esconde o texto de carregamento
        displayResults(data); // Exibe os resultados reais
    })
    .catch(error => {
        hideLoading(); // Esconde o texto de carregamento
        console.error('Erro ao buscar os dados:', error);
    });
}

// Função para esconder o texto de carregamento
function hideLoading() {
    const loadingText = document.getElementById('loadingText');
    loadingText.style.display = 'none';
}

// Função para exibir os resultados na página
function displayResults(data) {
    const resultsContainer = document.getElementById('resultsContainer');
    resultsContainer.innerHTML = '';

    if (data.length > 0) {
        data.forEach(item => {
            const resultItem = document.createElement('div');
            resultItem.className = 'result-item';
            resultItem.innerHTML = `${item.problema} - ${item.solucao}`;
            resultItem.onclick = function() {
                openModal(item);
            };
            resultsContainer.appendChild(resultItem);
        });
    } else {
        resultsContainer.innerHTML = '<p>Nenhum resultado encontrado.</p>';
    }
}

// Função para abrir o modal com os detalhes
function openModal(item) {
    const modal = document.getElementById('modal');
    const modalContent = document.getElementById('modalContent');
    modalContent.innerHTML = `<p><strong>Problema:</strong> ${item.problema}</p><p><strong>Solução:</strong> ${item.solucao}</p>`;
    modal.style.display = 'flex';
}

// Fechar o modal
document.getElementById('closeButton').addEventListener('click', function() {
    const modal = document.getElementById('modal');
    modal.style.display = 'none';
});

// Fechar o modal quando clicar fora da área do conteúdo
window.onclick = function(event) {
    const modal = document.getElementById('modal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
};


  document.getElementById("searchButton").addEventListener("click", function () {
            const query = document.getElementById("searchInput").value;
            const resultsContainer = document.getElementById("resultsContainer");
            const loadingText = document.getElementById("loadingText");

            // Exibir o texto de carregamento
            loadingText.style.display = "block";

            // Limpar resultados anteriores
            resultsContainer.innerHTML = '';

            // Simulação de consulta
            setTimeout(function () {
                // Aqui você pode adicionar a lógica para buscar os dados no banco
                // Exemplo de resultados
                const results = ["Só mais um pouquinho, estou organizando os resultados"]; // Exemplos

                results.forEach(result => {
                    const li = document.createElement("li");
                    li.classList.add("result-item");
                    li.textContent = result;
                    resultsContainer.appendChild(li);
                });

                // Esconder o texto de carregamento
                loadingText.style.display = "none";
            }, 2000); // Simula 2 segundos de carregamento
        });

// Dentro do setTimeout
if (query.trim() === "") {
    resultsContainer.innerHTML = ''; // Limpa a tela
} else if (results.length === 0) {
    resultsContainer.innerHTML = '<li class="result-item">Nenhum resultado encontrado.</li>'; // Exibe mensagem de nenhum resultado
}


// Obtém o botão
const scrollToTopBtn = document.getElementById("scrollToTopBtn");

// Mostra o botão quando o usuário rola até o final da página
window.onscroll = function() {
    if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
        scrollToTopBtn.style.display = "block"; // Exibe o botão
    } else {
        scrollToTopBtn.style.display = "none"; // Esconde o botão
    }
};

// Função para rolar até o topo da página
scrollToTopBtn.onclick = function() {
    window.scrollTo({
        top: 0,
        behavior: "smooth" // Rolagem suave
    });
};

document.getElementById("searchButton").addEventListener("click", function () {
    const query = document.getElementById("searchInput").value;
    const resultsContainer = document.getElementById("resultsContainer");
    const loadingText = document.getElementById("loadingText");
    const historyList = document.getElementById("historyList");

    // Exibir o texto de carregamento
    loadingText.style.display = "block";

    // Limpar resultados anteriores
    resultsContainer.innerHTML = '';

    // Adicionar consulta ao histórico
    if (query.trim() !== "") {
        // Adiciona o histórico no início da lista
        const liHistory = document.createElement("li");
        liHistory.textContent = query;
        historyList.insertBefore(liHistory, historyList.firstChild); // Adiciona ao topo da lista
    }

    // Simulação de consulta
    setTimeout(function () {
        const results = ["Só mais um pouquinho, estou organizando os resultados"]; // Exemplos

        results.forEach(result => {
            const li = document.createElement("li");
            li.classList.add("result-item");
            li.textContent = result;
            resultsContainer.appendChild(li);
        });

        // Esconder o texto de carregamento
        loadingText.style.display = "none";
    }, 2000);
});
