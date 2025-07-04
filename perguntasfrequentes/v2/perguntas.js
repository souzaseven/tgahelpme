// ========== [COLAPSAR/EXPANDIR FAQ + Lazy Load] ==========
document.querySelectorAll('.faq-question').forEach(question => {
    question.addEventListener('click', () => {
        const answer = question.nextElementSibling;
        const icon = question.querySelector('i');

        // Fecha outras respostas
        document.querySelectorAll('.faq-answer').forEach(a => {
            if (a !== answer) a.style.display = 'none';
        });

        // Reseta ícones
        document.querySelectorAll('.faq-question i').forEach(i => {
            if (i !== icon) i.classList.replace('fa-chevron-up', 'fa-chevron-down');
        });

        const isOpen = answer.style.display === 'block';

        // Fecha se já estiver aberta
        if (isOpen) {
            answer.style.display = 'none';
            icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
        } else {
            answer.style.display = 'block';
            icon.classList.replace('fa-chevron-down', 'fa-chevron-up');

            // === [Carregar imagens lazy ao expandir] ===
            answer.querySelectorAll('img[data-src]').forEach(img => {
                img.src = img.getAttribute('data-src');
                img.removeAttribute('data-src');
            });
        }
    });
});


// ========== [SCROLL PARA TOPO E FUNDO] ==========
function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function scrollToBottom() {
    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
}

window.onscroll = function () {
    const scrollUpBtn = document.getElementById("scroll-up-btn");
    const scrollDownBtn = document.getElementById("scroll-down-btn");

    scrollUpBtn.style.display = window.scrollY > 100 ? "block" : "none";
    scrollDownBtn.style.display = (window.innerHeight + window.scrollY) >= document.body.offsetHeight
        ? "none" : "block";
};


// ========== [FILTRO DE PESQUISA COM TEXTO COMPLETO] ==========
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchInput');
    const faqItems = Array.from(document.querySelectorAll('.faq-item'));

    // ========== [DESATIVADO - Preenchimento automático do campo de busca ao carregar a página] ==========
    // if (searchInput.value.trim() === '') {
    //     searchInput.value = 'contatos da TGA';
    // }

    applyFilter(searchInput.value);

    searchInput.addEventListener('input', function () {
        applyFilter(this.value);
    });

    function applyFilter(query) {
        const lowerCaseQuery = query.toLowerCase().trim();
        faqItems.forEach(item => {
            const fullContent = item.textContent.toLowerCase();
            item.style.display = fullContent.includes(lowerCaseQuery) ? 'block' : 'none';
        });
    }
});


// ========== [CARREGAMENTO PROGRESSIVO - 10 POR VEZ] ==========
document.addEventListener("DOMContentLoaded", function () {
    const faqItems = Array.from(document.querySelectorAll(".faq-item"));
    const itemsPerLoad = 10;
    let currentIndex = 0;

    function showNextItems() {
        const nextItems = faqItems.slice(currentIndex, currentIndex + itemsPerLoad);
        nextItems.forEach(item => item.style.display = "block");
        currentIndex += itemsPerLoad;
    }

    faqItems.forEach(item => item.style.display = "none");
    showNextItems();

    window.addEventListener("scroll", function () {
        if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 100) {
            showNextItems();
        }
    });
});


// ========== [MODAL DE IMAGEM AMPLIADA COM X PARA FECHAR] ==========
document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    const closeModal = document.getElementById('closeModal');
    const loadingIndicator = document.getElementById('loadingIndicator');

    document.querySelectorAll('.faq-answer').forEach(answer => {
        answer.addEventListener('click', e => {
            if (e.target.tagName === 'IMG') {
                loadingIndicator.style.display = 'block';
                modalImage.src = '';
                modal.style.display = 'flex';

                modalImage.onload = () => {
                    loadingIndicator.style.display = 'none';
                };

                modalImage.onerror = () => {
                    loadingIndicator.innerText = 'Erro ao carregar a imagem';
                };

                modalImage.src = e.target.src + '?cb=' + new Date().getTime();
            }
        });
    });

    closeModal.addEventListener('click', () => {
        modal.style.display = 'none';
        modalImage.src = '';
        loadingIndicator.style.display = 'none';
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
            modalImage.src = '';
            loadingIndicator.style.display = 'none';
        }
    });
});
