function setupSorting() {
    const sortAprovado = document.getElementById("sort-aprovado");

    if (sortAprovado) {
        let aprovadoSortDirection = false;
        sortAprovado.addEventListener("click", function () {
            let rows = Array.from(document.querySelectorAll("table tbody tr"));
            rows.sort((a, b) => {
                const aprovadoA = a.cells[3].textContent.trim();
                const aprovadoB = b.cells[3].textContent.trim();

                const order = { 'Em Análise': 1, 'SIM': 2, 'Não': 3 };
                const orderA = order[aprovadoA] || 4;
                const orderB = order[aprovadoB] || 4;

                return aprovadoSortDirection ? orderA - orderB : orderB - orderA;
            });

            const tbody = document.querySelector("table tbody");
            if (tbody) {
                tbody.innerHTML = "";
                rows.forEach(row => tbody.appendChild(row));
            }

            aprovadoSortDirection = !aprovadoSortDirection;

            atualizarEstatisticas();
        });
    }
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function scrollToBottom() {
    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
}

function setupScrollButtons() {
    const scrollUpBtn = document.getElementById("scroll-up-btn");
    const scrollDownBtn = document.getElementById("scroll-down-btn");

    if (scrollUpBtn) {
        scrollUpBtn.addEventListener('click', scrollToTop);
        scrollUpBtn.style.display = "none";
    }

    if (scrollDownBtn) {
        scrollDownBtn.addEventListener('click', scrollToBottom);
        scrollDownBtn.style.display = "none";
    }

    window.onscroll = function () {
        if (scrollUpBtn) {
            scrollUpBtn.style.display = (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) ? "flex" : "none";
        }

        if (scrollDownBtn) {
            const isAtBottom = (window.innerHeight + window.scrollY) >= document.body.offsetHeight - 100;
            scrollDownBtn.style.display = isAtBottom ? "none" : "flex";
        }
    };
}

function atualizarEstatisticas() {
    const rows = document.querySelectorAll('#suggestions-table tbody tr');
    let total = 0, aprovadas = 0, emAnalise = 0, naoAprovadas = 0;

    rows.forEach(row => {
        if (row.style.display !== 'none') {
            total++;
            const status = row.cells[3]?.textContent.trim();
            if (status === 'SIM') aprovadas++;
            else if (status === 'Em Análise') emAnalise++;
            else if (status === 'Não') naoAprovadas++;
        }
    });

    const totalElement = document.querySelector('.stat-item-compact:nth-child(1) .stat-number-compact');
    const aprovadasElement = document.querySelector('.stat-item-compact:nth-child(2) .stat-number-compact');
    const emAnaliseElement = document.querySelector('.stat-item-compact:nth-child(3) .stat-number-compact');

    if (totalElement) totalElement.textContent = total;
    if (aprovadasElement) aprovadasElement.textContent = aprovadas;
    if (emAnaliseElement) emAnaliseElement.textContent = emAnalise;

    console.log(`Estatísticas: Total=${total}, Aprovadas=${aprovadas}, Em Análise=${emAnalise}, Não Aprovadas=${naoAprovadas}`);
}

function applyFilters() {
    const searchInput = document.getElementById('search-input');
    const filtroStatus = document.getElementById('filtro-aprovado');
    
    if (!searchInput || !filtroStatus) return;

    const searchTerm = searchInput.value.toLowerCase();
    const filtroStatusValue = filtroStatus.value;
    const rows = document.querySelectorAll('#suggestions-table tbody tr');

    rows.forEach(row => {
        const nome = row.cells[1]?.textContent.toLowerCase() || '';
        const sugestao = row.cells[2]?.textContent.toLowerCase() || '';
        const status = row.cells[3]?.textContent.trim() || '';

        let matchesSearch = nome.includes(searchTerm) || sugestao.includes(searchTerm);
        let matchesStatus = true;

        if (filtroStatusValue === 'sim' && status !== 'SIM') matchesStatus = false;
        if (filtroStatusValue === 'nao' && status !== 'Em Análise') matchesStatus = false;
        if (filtroStatusValue === 'em analise' && status !== 'Não') matchesStatus = false;

        row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    });

    atualizarEstatisticas();
}

function setupRealTimeSearch() {
    const searchInput = document.getElementById('search-input');
    const searchIcon = document.querySelector('.search-icon');
    const filtroAprovado = document.getElementById('filtro-aprovado');

    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (searchIcon) searchIcon.addEventListener('click', applyFilters);
    if (filtroAprovado) filtroAprovado.addEventListener('change', applyFilters);
}

function initializePage() {
    console.log('Inicializando página...');
    
    // Verificar se os elementos necessários existem
    const suggestionsTable = document.getElementById('suggestions-table');
    if (!suggestionsTable) {
        console.log('Tabela de sugestões não encontrada, pulando inicialização de recursos relacionados.');
        return;
    }
    
    setupSorting();
    setupScrollButtons();
    setupRealTimeSearch();
    
    // Aguardar um pouco mais para garantir que o DOM esteja completamente carregado
    setTimeout(atualizarEstatisticas, 300);
    console.log('Página inicializada com sucesso');
}

// Inicializar quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(initializePage, 100);
});

// Fallback para garantir que a inicialização ocorra
window.addEventListener('load', function() {
    setTimeout(initializePage, 500);
});

// Recarregar a página periodicamente (opcional)
setInterval(() => {
    const searchInput = document.getElementById('search-input');
    const filtroAprovado = document.getElementById('filtro-aprovado');

    if (searchInput && filtroAprovado) {
        if (searchInput.value === '' && filtroAprovado.value === '') {
            console.log('Recarregando página...');
            location.reload();
        }
    }
}, 60000);