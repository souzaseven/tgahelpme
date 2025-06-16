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



/*secao do banner -  adquirentes homologadas*/
// Obtenha a imagem e o modal
const img = document.getElementById("image");
const modal = document.getElementById("modal");
const modalImg = document.getElementById("expandedImage");
const closeBtn = document.getElementById("close");

// Ao clicar na imagem, exibe o modal e a imagem expandida
img.onclick = function() {
    modal.style.display = "block";
    modalImg.src = img.src;
};

// Ao clicar no botão de fechar, oculta o modal
closeBtn.onclick = function() {
    modal.style.display = "none";
};



/*enviar arquivos */
function abrirModalUpload(tipo) {
    document.getElementById('modalUpload').style.display = 'block';
    document.getElementById('tipoInput').value = tipo;
}

document.getElementById('formUpload').addEventListener('submit', async function (e) {
    e.preventDefault();

    const formData = new FormData(this);
    const response = await fetch('upload_wallpaper.php', {
        method: 'POST',
        body: formData
    });

    const result = await response.json();
    alert(result.message);
    if (result.success) location.reload();
});



/* excluir arquivos*/
async function excluirArquivo(id) {
    if (!confirm('Deseja excluir este arquivo?')) return;

    const response = await fetch('excluir_wallpaper.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ id })
    });

    const result = await response.json();
    alert(result.message);
    if (result.success) location.reload();
}



/*carrega lista dos wallpapers */
async function carregarWallpapers(tipo) {
    const response = await fetch(`listar_wallpapers.php?tipo=${tipo}`);
    const dados = await response.json();

    if (dados.success) {
        const container = document.getElementById(`wallpapers-${tipo}`);
        container.innerHTML = '';

        dados.data.forEach(item => {
            const div = document.createElement('div');
            div.className = 'wallpaper-item';
            div.innerHTML = `
                <img src="${item.caminho}" class="wallpaper" onclick="openModal(this)">
                <a href="${item.caminho}" download class="download-btn">Baixar</a>
                ${usuarioAtual === 'Anderson' ? `<button onclick="excluirArquivo(${item.id})">Excluir</button>` : ''}
            `;
            container.appendChild(div);
        });
    } else {
        alert('Erro ao carregar wallpapers');
    }
}


/*carrega automaticamente */
async function carregarWallpapers(tipo) {
    const container = document.getElementById(`wallpapers-${tipo}`);
    container.innerHTML = '<p>Carregando...</p>';

    try {
        const response = await fetch(`listar_wallpapers.php?tipo=${tipo}`);
        const dados = await response.json();

        if (dados.success) {
            container.innerHTML = '';

            dados.data.forEach(item => {
                const div = document.createElement('div');
                div.className = 'wallpaper-item';

                const img = document.createElement('img');
                img.src = item.caminho;
                img.alt = item.nome_arquivo;
                img.className = 'wallpaper';
                img.loading = 'lazy'; // carregamento leve
                img.onclick = () => openModal(img);

                const link = document.createElement('a');
                link.href = item.caminho;
                link.download = '';
                link.className = 'download-btn';
                link.textContent = 'Baixar';

                div.appendChild(img);
                div.appendChild(link);

                if (typeof usuarioAtual !== 'undefined' && usuarioAtual === 'Anderson') {
                    const btnExcluir = document.createElement('button');
                    btnExcluir.textContent = 'Excluir';
                    btnExcluir.onclick = () => excluirArquivo(item.id);
                    btnExcluir.style.margin = '5px';
                    div.appendChild(btnExcluir);
                }

                container.appendChild(div);
            });
        } else {
            container.innerHTML = '<p>Erro ao carregar imagens.</p>';
        }
    } catch (e) {
        console.error(e);
        container.innerHTML = '<p>Erro na requisição.</p>';
    }
}

// Carregamento automático após o DOM estar pronto
document.addEventListener('DOMContentLoaded', function () {
    carregarWallpapers('tga');
    carregarWallpapers('outros');
});
