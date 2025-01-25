document.addEventListener('DOMContentLoaded', function () {

    // Função para exibir ou ocultar seções com base na pesquisa
    function filterContent() {
        const searchInput = document.getElementById('searchInput');
        const searchTerm = searchInput.value.toLowerCase();
        const sections = document.querySelectorAll('section');

        sections.forEach(function (section) {
            const title = section.querySelector('h2') ? section.querySelector('h2').innerText.toLowerCase() : '';
            const videoItems = section.querySelectorAll('.Dowloads-item');
            let sectionVisible = title.includes(searchTerm); // Verifica se o título da seção contém o termo

            // Filtra vídeos dentro de cada seção
            videoItems.forEach(function (videoItem) {
                const videoTitle = videoItem.querySelector('.video-title').innerText.toLowerCase();
                const words = videoTitle.split(/\s+/); // Divide o título em palavras
                const matchFound = words.some(word => word.includes(searchTerm)); // Verifica se alguma palavra contém o termo
                if (matchFound) {
                    videoItem.style.display = 'block'; // Exibe o vídeo se alguma palavra contiver o termo
                } else {
                    videoItem.style.display = 'none'; // Oculta o vídeo se nenhuma palavra contiver o termo
                }
            });

            // Exibe ou oculta a seção com base no título ou vídeos visíveis
            if (sectionVisible || Array.from(videoItems).some(item => item.style.display === 'block')) {
                section.style.display = 'block'; // Exibe a seção se ela corresponder à pesquisa
            } else {
                section.style.display = 'none'; // Oculta a seção se não houver correspondência
            }
        });
    }

    // Evento para filtrar conteúdo ao digitar na pesquisa
    const searchInput = document.getElementById('searchInput');
    searchInput.addEventListener('input', filterContent);
});

const scrollToTopBtn = document.createElement('button');
scrollToTopBtn.textContent = '↑';
scrollToTopBtn.className = 'scroll-to-top';
scrollToTopBtn.onclick = () => window.scrollTo({ top: 0, behavior: 'smooth' });
document.body.appendChild(scrollToTopBtn);

window.addEventListener('scroll', () => {
    scrollToTopBtn.style.display = window.scrollY > 300 ? 'block' : 'none';
});

document.getElementById('searchInput').addEventListener('input', function () {
    const query = this.value.toLowerCase();
    const items = document.querySelectorAll('.Dowloads-item');
    items.forEach(item => {
        const title = item.querySelector('.video-title').textContent.toLowerCase();
        const words = title.split(/\s+/); // Divide o título em palavras
        const matchFound = words.some(word => word.includes(query)); // Verifica se alguma palavra contém o termo
        item.style.display = matchFound ? 'block' : 'none'; // Exibe/oculta conforme a correspondência
    });
});
