// Função de busca automática
function setupSearch() {
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#suggestions-body tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
}

// Ordenar a tabela por data
function setupDateSorting() {
    const sortDate = document.getElementById("sort-date");
    if (sortDate) {
        let sortDirection = false;
        sortDate.addEventListener("click", function() {
            let rows = Array.from(document.querySelectorAll("table tbody tr"));
            rows.sort((a, b) => {
                const dateA = new Date(a.cells[5].textContent);
                const dateB = new Date(b.cells[5].textContent);
                return sortDirection ? dateA - dateB : dateB - dateA;
            });

            const tbody = document.querySelector("table tbody");
            if (tbody) {
                tbody.innerHTML = "";
                rows.forEach(row => tbody.appendChild(row));
            }

            sortDirection = !sortDirection;
            const icon = document.querySelector("#sort-date i");
            icon.className = sortDirection ? "fas fa-sort-up" : "fas fa-sort-down";
        });
    }
}

// Ordenar a tabela por "Aprovado"
function setupAprovadoSorting() {
    const sortAprovado = document.getElementById("sort-aprovado");
    if (sortAprovado) {
        let aprovadoSortDirection = false;
        sortAprovado.addEventListener("click", function() {
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
        });
    }
}

// Funções de scroll
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

    window.onscroll = function() {
        if (scrollUpBtn) {
            scrollUpBtn.style.display = (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) ? "block" : "none";
        }

        if (scrollDownBtn) {
            const isAtBottom = (window.innerHeight + window.scrollY) >= document.body.offsetHeight - 100;
            scrollDownBtn.style.display = isAtBottom ? "none" : "block";
        }
    };
}

// Inicializar página
function initializePage() {
    setupDateSorting();
    setupAprovadoSorting();
    setupScrollButtons();
    setupSearch();
}

// Inicializar quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    initializePage();
});

// Recarregar a página periodicamente
setInterval(function() {
    location.reload();
}, 60000);