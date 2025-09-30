document.addEventListener('DOMContentLoaded', function () {

    // Função para exibir ou ocultar seções com base na pesquisa
    function filterContent() {
        const searchInput = document.getElementById('searchInput');
        let searchTerm = searchInput.value.toLowerCase();

        // Adiciona um ponto se a pesquisa for uma sequência numérica sem ponto (ex: 2410 → 24.10)
        if (/^\d{4}$/.test(searchTerm)) {
            searchTerm = searchTerm.substring(0, 2) + '.' + searchTerm.substring(2);
        }

        const sections = document.querySelectorAll('section');
        
        // Caso a pesquisa seja um número, busca pelo id correspondente
        const specialDiv = document.getElementById(searchTerm); // Busca a div com o id correspondente ao número
        const versionTitle = document.querySelector('h2.section-title'); // Busca por título da seção

        if (/^\d+$/.test(searchTerm)) {  // Verifica se a pesquisa é um número
            sections.forEach(function (section) {
                const title = section.querySelector('h2') ? section.querySelector('h2').innerText.toLowerCase() : ''; // Título da seção
                const versionItems = section.querySelectorAll('.version-item'); // Itens de versão
                let sectionVisible = title.includes(searchTerm);  // Verifica se o título da seção contém o termo

                // Verifica se a seção tem o id igual ao valor digitado
                if (section.id === searchTerm) {
                    section.style.display = 'block'; // Exibe a seção com o id correspondente
                } else {
                    section.style.display = 'none'; // Oculta as seções que não correspondem ao id
                }

                // Filtra as versões dentro de cada seção com base na pesquisa
                versionItems.forEach(function (versionItem) {
                    const versionTitle = versionItem.querySelector('.version-title') ? versionItem.querySelector('.version-title').innerText.toLowerCase() : ''; // Título da versão
                    if (versionTitle.includes(searchTerm)) {
                        versionItem.style.display = 'block'; // Exibe a versão se o título contiver o termo
                    } else {
                        versionItem.style.display = 'none'; // Oculta a versão se não contiver o termo
                    }
                });
            });
        } else {
            sections.forEach(function (section) {
                const title = section.querySelector('h2') ? section.querySelector('h2').innerText.toLowerCase() : ''; // Título da seção
                const versionItems = section.querySelectorAll('.version-item'); // Itens de versão
                let sectionVisible = title.includes(searchTerm);  // Verifica se o título da seção contém o termo

                // Se o termo for "versões 2024", mostra todos os itens dentro dessa seção
                if (searchTerm.includes('versões 2024')) {
                    section.style.display = 'block'; // Exibe a seção
                    versionItems.forEach(function (versionItem) {
                        versionItem.style.display = 'block'; // Exibe todos os itens de versão
                    });
                } else {
                    // Filtra as versões dentro de cada seção
                    versionItems.forEach(function (versionItem) {
                        const versionTitle = versionItem.querySelector('.version-title') ? versionItem.querySelector('.version-title').innerText.toLowerCase() : ''; // Título da versão
                        if (versionTitle.includes(searchTerm)) {
                            versionItem.style.display = 'block'; // Exibe a versão se o título contiver o termo
                        } else {
                            versionItem.style.display = 'none'; // Oculta a versão se não contiver o termo
                        }
                    });

                    // Exibe ou oculta a seção com base no título ou nas versões visíveis
                    if (sectionVisible || Array.from(versionItems).some(item => item.style.display === 'block')) {
                        section.style.display = 'block'; // Exibe a seção se ela corresponder à pesquisa
                    } else {
                        section.style.display = 'none'; // Oculta a seção se não houver correspondência
                    }
                }
            });
        }
    }

    // Evento para filtrar conteúdo ao digitar na pesquisa
    const searchInput = document.getElementById('searchInput');
    searchInput.addEventListener('input', filterContent);
});



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

// Exemplo de alternância de classe de modo escuro
const body = document.body;
const versionItems = document.querySelectorAll('.version-item');

function toggleDarkMode() {
    body.classList.toggle('dark-mode');
    versionItems.forEach(item => item.classList.toggle('dark-mode'));
}
