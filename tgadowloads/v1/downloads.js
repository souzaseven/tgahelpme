document.addEventListener('DOMContentLoaded', function () {

    // Função para exibir ou ocultar seções com base na pesquisa
   /* function filterContent() {
    /*   const searchInput = document.getElementById('searchInput');
        const searchTerm = searchInput.value.toLowerCase();
        const sections = document.querySelectorAll('section');

        sections.forEach(function (section) {
            const title = section.querySelector('h2').innerText.toLowerCase();
            const videoItems = section.querySelectorAll('.video-item');
            let sectionVisible = title.includes(searchTerm);  // Verifica se o título da seção contém o termo

            // Filtra vídeos dentro de cada seção
            videoItems.forEach(function (videoItem) {
                const videoTitle = videoItem.querySelector('.video-title').innerText.toLowerCase();
                if (videoTitle.includes(searchTerm)) {
                    videoItem.style.display = 'block'; // Exibe o vídeo se o título contiver o termo
                } else {
                    videoItem.style.display = 'none'; // Oculta o vídeo se não contiver o termo
                }
            });

            // Exibe ou oculta a seção com base no título
            if (sectionVisible || Array.from(videoItems).some(item => item.style.display === 'block')) {
                section.style.display = 'block'; // Exibe a seção se ela corresponder à pesquisa
            } else {
                section.style.display = 'none'; // Oculta a seção se não houver correspondência
            }
        });
    }*/

function filterContent() {
    const searchInput = document.getElementById('searchInput');
    const searchTerm = searchInput.value.toLowerCase();
    const sections = document.querySelectorAll('section');
    let visibleItems = 0; // Conta os itens visíveis

    sections.forEach(function (section) {
        const title = section.querySelector('h2').innerText.toLowerCase();
        const videoItems = section.querySelectorAll('.video-item');
        let sectionVisible = title.includes(searchTerm);

        videoItems.forEach(function (videoItem) {
            const videoTitle = videoItem.querySelector('.video-title').innerText.toLowerCase();
            if (videoTitle.includes(searchTerm)) {
                videoItem.style.display = 'block';
                visibleItems++; // Incrementa os itens visíveis
            } else {
                videoItem.style.display = 'none';
            }
        });

        if (sectionVisible || Array.from(videoItems).some(item => item.style.display === 'block')) {
            section.style.display = 'block';
        } else {
            section.style.display = 'none';
        }
    });

    // Centraliza se apenas um item estiver visível
    if (visibleItems === 1) {
        document.querySelectorAll('.video-item:visible').forEach(item => {
            item.style.margin = '0 auto';
            item.style.textAlign = 'center';
item.style.display = 'flex';
item.style.alignItems = 'center';
item.style.justifyContent = 'center';

        });
    }
}


    // Evento para filtrar conteúdo ao digitar na pesquisa
    const searchInput = document.getElementById('searchInput');
    searchInput.addEventListener('input', filterContent);
})