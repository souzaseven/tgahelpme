document.addEventListener('DOMContentLoaded', () => {
    const faqItems = Array.from(document.querySelectorAll('.faq-item'));
    const searchInput = document.getElementById('searchInput');
    const scrollUpBtn = document.getElementById('scroll-up-btn');
    const scrollDownBtn = document.getElementById('scroll-down-btn');
    const themeToggle = document.getElementById('theme-toggle');

    const modal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    const closeModal = document.getElementById('closeModal');
    const loadingIndicator = document.getElementById('loadingIndicator');

    const itemsPerLoad = 12;
    let visibleCount = itemsPerLoad;
    let filteredItems = [...faqItems];

    function normalizeText(text) {
        return (text || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function closeAnswer(question, answer) {
        if (!question || !answer) return;
        answer.style.display = 'none';
        question.setAttribute('aria-expanded', 'false');
        const icon = question.querySelector('i');
        if (icon) {
            icon.classList.remove('fa-chevron-up');
            icon.classList.add('fa-chevron-down');
        }
    }

    function openAnswer(question, answer) {
        if (!question || !answer) return;
        answer.style.display = 'block';
        question.setAttribute('aria-expanded', 'true');
        const icon = question.querySelector('i');
        if (icon) {
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-up');
        }

        answer.querySelectorAll('img[data-src]').forEach((img) => {
            if (!img.getAttribute('src')) {
                img.src = img.getAttribute('data-src');
            }
            img.removeAttribute('data-src');
        });
    }

    function closeAllAnswersExcept(currentAnswer) {
        faqItems.forEach((item) => {
            const question = item.querySelector('.faq-question');
            const answer = item.querySelector('.faq-answer');
            if (answer !== currentAnswer) {
                closeAnswer(question, answer);
            }
        });
    }

    function setupFaqAccessibility() {
        faqItems.forEach((item, index) => {
            const question = item.querySelector('.faq-question');
            const answer = item.querySelector('.faq-answer');
            if (!question || !answer) return;

            const answerId = answer.id || `faq-answer-${index + 1}`;
            answer.id = answerId;

            question.setAttribute('role', 'button');
            question.setAttribute('tabindex', '0');
            question.setAttribute('aria-controls', answerId);
            question.setAttribute('aria-expanded', 'false');

            closeAnswer(question, answer);

            const toggleCurrent = () => {
                const isOpen = answer.style.display === 'block';
                if (isOpen) {
                    closeAnswer(question, answer);
                } else {
                    closeAllAnswersExcept(answer);
                    openAnswer(question, answer);
                }
            };

            question.addEventListener('click', toggleCurrent);
            question.addEventListener('keydown', (event) => {
                const isActionKey = event.key === 'Enter' || event.key === ' ';
                if (!isActionKey) return;
                event.preventDefault();
                toggleCurrent();
            });
        });
    }

    function updateFilteredItems() {
        const query = normalizeText(searchInput ? searchInput.value : '');

        filteredItems = faqItems.filter((item) => {
            if (!query) return true;
            const fullContent = normalizeText(item.textContent);
            return fullContent.includes(query);
        });
    }

    function renderItems() {
        faqItems.forEach((item) => {
            item.style.display = 'none';
            const question = item.querySelector('.faq-question');
            const answer = item.querySelector('.faq-answer');
            closeAnswer(question, answer);
        });

        filteredItems.slice(0, visibleCount).forEach((item) => {
            item.style.display = 'block';
        });

        updateScrollButtons();
    }

    function loadMoreIfNeeded() {
        if (visibleCount >= filteredItems.length) return;
        const nearBottom = window.innerHeight + window.scrollY >= document.body.offsetHeight - 120;
        if (!nearBottom) return;

        visibleCount += itemsPerLoad;
        renderItems();
    }

    function updateScrollButtons() {
        if (!scrollUpBtn || !scrollDownBtn) return;
        scrollUpBtn.style.display = window.scrollY > 120 ? 'block' : 'none';

        const isAtBottom = window.innerHeight + window.scrollY >= document.body.offsetHeight - 8;
        scrollDownBtn.style.display = isAtBottom ? 'none' : 'block';
    }

    function scrollToTop() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function scrollToBottom() {
        window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    }

    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('faq-theme', theme);

        if (!themeToggle) return;
        const icon = themeToggle.querySelector('.theme-toggle__icon');
        const text = themeToggle.querySelector('.theme-toggle__text');
        const isDark = theme === 'dark';

        themeToggle.setAttribute('aria-pressed', String(isDark));
        if (icon) icon.textContent = isDark ? '☀️' : '🌙';
        if (text) text.textContent = isDark ? 'Modo claro' : 'Modo escuro';
    }

    function setupTheme() {
        const savedTheme = localStorage.getItem('faq-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const initialTheme = savedTheme || (prefersDark ? 'dark' : 'light');

        setTheme(initialTheme);

        if (!themeToggle) return;
        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            setTheme(currentTheme === 'dark' ? 'light' : 'dark');
        });
    }

    function setupImages() {
        document.querySelectorAll('.faq-answer img').forEach((img) => {
            if (!img.getAttribute('loading')) img.setAttribute('loading', 'lazy');
            if (!img.getAttribute('decoding')) img.setAttribute('decoding', 'async');
        });
    }

    function openModalWithImage(source) {
        if (!modal || !modalImage || !loadingIndicator) return;
        loadingIndicator.textContent = 'Carregando...';
        loadingIndicator.style.display = 'block';
        modalImage.src = '';
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');

        modalImage.onload = () => {
            loadingIndicator.style.display = 'none';
        };

        modalImage.onerror = () => {
            loadingIndicator.textContent = 'Erro ao carregar a imagem';
        };

        modalImage.src = source;
    }

    function closeImageModal() {
        if (!modal || !modalImage || !loadingIndicator) return;
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        modalImage.src = '';
        loadingIndicator.style.display = 'none';
        loadingIndicator.textContent = 'Carregando...';
        document.body.classList.remove('modal-open');
    }

    function setupModal() {
        if (!modal || !modalImage || !closeModal || !loadingIndicator) return;

        document.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLImageElement)) return;
            if (!target.closest('.faq-answer')) return;
            openModalWithImage(target.currentSrc || target.src);
        });

        closeModal.addEventListener('click', closeImageModal);
        closeModal.addEventListener('keydown', (event) => {
            const isActionKey = event.key === 'Enter' || event.key === ' ';
            if (!isActionKey) return;
            event.preventDefault();
            closeImageModal();
        });

        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeImageModal();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.style.display === 'flex') {
                closeImageModal();
            }
        });
    }

    function secureExternalLinks() {
        document.querySelectorAll('a[target="_blank"]').forEach((link) => {
            const currentRel = (link.getAttribute('rel') || '').toLowerCase();
            const relValues = new Set(currentRel.split(' ').filter(Boolean));
            relValues.add('noopener');
            relValues.add('noreferrer');
            link.setAttribute('rel', Array.from(relValues).join(' '));
        });
    }

    setupTheme();
    setupImages();
    setupFaqAccessibility();
    setupModal();
    secureExternalLinks();

    updateFilteredItems();
    renderItems();

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            visibleCount = itemsPerLoad;
            updateFilteredItems();
            renderItems();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    if (scrollUpBtn) {
        scrollUpBtn.addEventListener('click', scrollToTop);
    }

    if (scrollDownBtn) {
        scrollDownBtn.addEventListener('click', scrollToBottom);
    }

    window.addEventListener('scroll', () => {
        updateScrollButtons();
        loadMoreIfNeeded();
    }, { passive: true });
});
