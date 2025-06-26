/*document.addEventListener('DOMContentLoaded', function () {

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
                visibleItems++;
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
*/
function filterContent() {
    const searchInput = document.getElementById('searchInput');
    const searchTerm = searchInput.value.toLowerCase();
    const videoItems = document.querySelectorAll('.video-item');
    let visibleItems = 0;

    videoItems.forEach(videoItem => {
        const titleElement = videoItem.querySelector('.video-title');
        if (!titleElement) {
            videoItem.style.display = 'none';
            return;
        }

        const videoTitle = titleElement.innerText.toLowerCase();
        const match = videoTitle.includes(searchTerm);

        videoItem.style.display = match ? 'block' : 'none';
        if (match) visibleItems++;
    });

    // Centraliza visual se só um item estiver visível
    if (visibleItems === 1) {
        const visibleItem = [...document.querySelectorAll('.video-item')].find(
            el => el.style.display !== 'none'
        );
        if (visibleItem) {
            Object.assign(visibleItem.style, {
                margin: '0 auto',
                textAlign: 'center',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center'
            });
        }
    }
}

// Ativa o filtro ao digitar
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', filterContent);
    }
});

