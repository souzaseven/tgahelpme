// ====================== SISTEMA DE BUSCA GLOBAL ======================
function setupGlobalSearch() {
    const searchInput = document.getElementById('videoSearch');
    
    if (!searchInput) return;
    
    searchInput.addEventListener('input', (e) => {
        const searchTerm = e.target.value.toLowerCase().trim();
        
        // Se estiver vazio, mostra tudo
        if (searchTerm === '') {
            showAllContent();
            return;
        }
        
        // Busca em todo o conteúdo
        searchInContent(searchTerm);
    });
}

function showAllContent() {
    // Mostra todas as seções e cards
    document.querySelectorAll('section, .card, .video-item').forEach(element => {
        element.style.display = '';
        element.style.opacity = '1';
    });
    
    // Remove destaques
    document.querySelectorAll('.search-highlight').forEach(highlight => {
        const parent = highlight.parentNode;
        parent.replaceChild(document.createTextNode(highlight.textContent), highlight);
        parent.normalize();
    });
    
    // Remove feedback de nenhum resultado
    const existingFeedback = document.querySelector('.search-feedback');
    if (existingFeedback) {
        existingFeedback.remove();
    }
}

function searchInContent(searchTerm) {
    let foundResults = false;
    
    // Remove destaques anteriores
    document.querySelectorAll('.search-highlight').forEach(highlight => {
        const parent = highlight.parentNode;
        parent.replaceChild(document.createTextNode(highlight.textContent), highlight);
        parent.normalize();
    });
    
    // Busca em todas as seções
    document.querySelectorAll('section').forEach(section => {
        let sectionHasMatch = false;
        
        // Busca em títulos
        const titles = section.querySelectorAll('h2, h3, .card-title, .video-title');
        titles.forEach(title => {
            if (title.textContent.toLowerCase().includes(searchTerm)) {
                sectionHasMatch = true;
                highlightText(title, searchTerm);
            }
        });
        
        // Busca em conteúdo de cards
        const cardContents = section.querySelectorAll('.card-content, p, li, code');
        cardContents.forEach(content => {
            if (content.textContent.toLowerCase().includes(searchTerm)) {
                sectionHasMatch = true;
                highlightText(content, searchTerm);
            }
        });
        
        // Busca em links
        const links = section.querySelectorAll('.link-url');
        links.forEach(link => {
            if (link.textContent.toLowerCase().includes(searchTerm)) {
                sectionHasMatch = true;
                highlightText(link, searchTerm);
            }
        });
        
        // Mostra/oculta seção baseado nos resultados
        if (sectionHasMatch) {
            section.style.display = '';
            section.style.opacity = '1';
            
            // Dentro da seção, mostra apenas os cards com matches
            section.querySelectorAll('.card, .video-item').forEach(card => {
                const cardText = card.textContent.toLowerCase();
                if (cardText.includes(searchTerm)) {
                    card.style.display = '';
                    card.style.opacity = '1';
                    foundResults = true;
                } else {
                    card.style.display = 'none';
                }
            });
        } else {
            section.style.display = 'none';
        }
    });
    
    // Feedback visual quando não encontra resultados
    showSearchFeedback(foundResults, searchTerm);
}

function highlightText(element, searchTerm) {
    const text = element.innerHTML;
    const regex = new RegExp(`(${escapeRegExp(searchTerm)})`, 'gi');
    const highlighted = text.replace(regex, '<mark class="search-highlight">$1</mark>');
    element.innerHTML = highlighted;
}

function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function showSearchFeedback(foundResults, searchTerm) {
    // Remove feedback anterior se existir
    const existingFeedback = document.querySelector('.search-feedback');
    if (existingFeedback) {
        existingFeedback.remove();
    }
    
    if (!foundResults && searchTerm.length > 0) {
        const feedback = document.createElement('div');
        feedback.className = 'search-feedback';
        feedback.innerHTML = `
            <div class="no-results-card">
                <div class="card-icon">🔍</div>
                <h3>Nenhum resultado encontrado</h3>
                <p>Não encontramos resultados para: "<strong>${searchTerm}</strong>"</p>
                <p class="suggestion">Tente usar termos diferentes ou verifique a ortografia.</p>
            </div>
        `;
        
        document.querySelector('main').appendChild(feedback);
    }
}

// ====================== SISTEMA DE VÍDEOS ======================
function openModal(videoId, videoTitle) {
    const modal = document.getElementById('videoModal');
    const videoContainer = document.getElementById('modalVideoContainer');
    const titleElement = document.getElementById('modalVideoTitle');
    
    if (!modal || !videoContainer || !titleElement) return;
    
    videoContainer.innerHTML = `
        <iframe 
            src="https://www.youtube.com/embed/${videoId}?autoplay=1" 
            title="${videoTitle}"
            frameborder="0" 
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
            allowfullscreen>
        </iframe>
    `;
    
    titleElement.textContent = videoTitle;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const modal = document.getElementById('videoModal');
    if (!modal) return;
    
    modal.classList.remove('active');
    document.body.style.overflow = 'auto';
    
    // Limpar o iframe para parar o vídeo
    const videoContainer = document.getElementById('modalVideoContainer');
    if (videoContainer) {
        videoContainer.innerHTML = '';
    }
}

function setupVideoSystem() {
    // Event listeners para os vídeos
    document.querySelectorAll('.video-item').forEach(video => {
        video.addEventListener('click', () => {
            const videoId = video.getAttribute('data-video-id');
            const videoTitle = video.getAttribute('data-video-title');
            openModal(videoId, videoTitle);
        });
    });
    
    // Event listeners do modal
    const modalClose = document.getElementById('modalClose');
    const videoModal = document.getElementById('videoModal');
    
    if (modalClose) {
        modalClose.addEventListener('click', closeModal);
    }
    
    if (videoModal) {
        videoModal.addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeModal();
        });
    }
    
    // Fechar modal com ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeModal();
    });
}

// ====================== INICIALIZAÇÃO ======================
document.addEventListener('DOMContentLoaded', () => {
    setupGlobalSearch();
    setupVideoSystem();
    
    // Adiciona placeholder dinâmico
    const searchInput = document.getElementById('videoSearch');
    if (searchInput) {
        searchInput.placeholder = "🔍 Buscar em todo o conteúdo...";
    }
});