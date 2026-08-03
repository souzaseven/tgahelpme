/* ===================================================
   1. BUSCA / FILTRO
=================================================== */
document.addEventListener('DOMContentLoaded', function () {
    const searchInput  = document.getElementById('searchInput');
    const clearBtn     = document.getElementById('searchClear');
    const resultsBar   = document.getElementById('searchResultsBar');
    const resultsCount = document.getElementById('searchResultsCount');

    if (!searchInput) return;

    let debounceTimer = null;

    function escHtml(str) {
        return str.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function filterContent() {
        const raw  = searchInput.value;
        const term = raw.trim().toLowerCase();

        if (clearBtn) clearBtn.style.display = term ? 'flex' : 'none';

        if (!term) {
            document.querySelectorAll('section').forEach(s => {
                s.style.display = '';
                s.classList.remove('search-filtering');
            });
            document.querySelectorAll('.Dowloads-item').forEach(i => i.style.display = '');
            if (resultsBar) resultsBar.style.display = 'none';
            return;
        }

        let totalItems = 0, totalSections = 0;

        document.querySelectorAll('section').forEach(section => {
            const h2El         = section.querySelector('h2');
            const sectionTitle = h2El ? h2El.innerText.toLowerCase() : '';
            const titleMatch   = sectionTitle.includes(term);
            const items        = section.querySelectorAll('.Dowloads-item');
            let visibleItems   = 0;

            if (items.length > 0) {
                items.forEach(item => {
                    const itemText = (item.innerText || item.textContent || '').toLowerCase();
                    const show = titleMatch || itemText.includes(term);
                    item.style.display = show ? '' : 'none';
                    if (show) visibleItems++;
                });
            } else {
                const sectionText = (section.innerText || section.textContent || '').toLowerCase();
                if (sectionText.includes(term)) visibleItems = 1;
            }

            const sectionShow = titleMatch || visibleItems > 0;
            section.style.display = sectionShow ? '' : 'none';
            if (sectionShow) {
                section.classList.add('search-filtering');
                totalSections++;
                totalItems += (items.length > 0 ? visibleItems : 1);
            } else {
                section.classList.remove('search-filtering');
            }
        });

        if (resultsBar) {
            resultsBar.style.display = '';
            if (totalItems === 0 && totalSections === 0) {
                resultsBar.classList.add('no-results');
                resultsCount.innerHTML = '<i class="fas fa-search"></i> Nenhum resultado para <strong>"' + escHtml(raw) + '"</strong>';
            } else {
                resultsBar.classList.remove('no-results');
                resultsCount.innerHTML =
                    '<span class="count-badge">' + totalItems + ' resultado' + (totalItems !== 1 ? 's' : '') + '</span>' +
                    ' em <strong>' + totalSections + '</strong> seç' + (totalSections !== 1 ? 'ões' : 'ão');
            }
        }
    }

    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(filterContent, 220);
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            searchInput.value = '';
            searchInput.focus();
            filterContent();
        });
    }

    searchInput.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            searchInput.value = '';
            filterContent();
            searchInput.blur();
        }
    });
});


/* ===================================================
   2. MODO ESCURO
=================================================== */
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
}


/* ===================================================
   3. BOTÕES DE ROLAGEM: TOPO E FIM
=================================================== */
document.addEventListener('DOMContentLoaded', function () {
    const topBtn    = document.getElementById('scrollToTopBtn');
    const bottomBtn = document.getElementById('scrollToBottomBtn');

    function updateScrollBtns() {
        const scrollY   = window.scrollY || document.documentElement.scrollTop;
        const maxScroll = document.documentElement.scrollHeight - window.innerHeight;
        if (topBtn)    topBtn.style.display    = scrollY > 100 ? 'flex' : 'none';
        if (bottomBtn) bottomBtn.style.display = (maxScroll > 200 && scrollY < maxScroll - 100) ? 'flex' : 'none';
    }

    window.addEventListener('scroll', updateScrollBtns);
    window.addEventListener('load', updateScrollBtns);
    updateScrollBtns();

    if (topBtn)    topBtn.onclick    = () => window.scrollTo({ top: 0, behavior: 'smooth' });
    if (bottomBtn) bottomBtn.onclick = () => window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' });
});


/* ===================================================
   4. MENSAGEM MOTIVACIONAL
=================================================== */
function showMotivationalMessage() {
    const hours = new Date().getHours();
    let message;

    if (hours < 12) {
        message = "Bom dia! Seja bem-vindo(a).<br>Vamos conquistar o dia com energia e foco!";
    } else if (hours < 18) {
        message = "Boa tarde! Seja bem-vindo(a).<br>Continue firme, você está indo muito bem!";
    } else {
        message = "Boa noite! Seja bem-vindo(a).<br>Um excelente descanso para recarregar as energias!";
    }

    const msgEl = document.getElementById('message');
    if (msgEl) msgEl.innerHTML = message;

    const messageElement = document.getElementById('motivational-message');
    if (messageElement) {
        messageElement.style.display = 'flex';
        setTimeout(() => { messageElement.style.display = 'none'; }, 5000);
    }
}

if (!localStorage.getItem('motivationalMessageShown')) {
    showMotivationalMessage();
    localStorage.setItem('motivationalMessageShown', 'true');
}


/* ===================================================
   5. BOTÕES DO MANUAL PDF
=================================================== */
const pdfUrl = './telefonia-evolux/manuaispdf/Manual do Operador - Versão 6.75.pdf';

function viewPdfInBackground() {
    if (!document.getElementById('pdfIframe')) {
        const iframe = document.createElement('iframe');
        iframe.src = pdfUrl;
        iframe.id  = 'pdfIframe';
        iframe.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100vh;z-index:999;border:none;';
        document.body.appendChild(iframe);

        const closeButton = document.createElement('button');
        closeButton.innerText = 'Fechar';
        closeButton.style.cssText = 'position:absolute;top:20px;right:20px;padding:10px 15px;background:#FF5C5C;color:white;border:none;border-radius:5px;cursor:pointer;z-index:1000;';
        closeButton.addEventListener('click', closePdfInBackground);
        document.body.appendChild(closeButton);
    }
}

function closePdfInBackground() {
    const iframe = document.getElementById('pdfIframe');
    if (iframe) iframe.remove();
    const closeButton = document.querySelector('button[style*="position:absolute"]');
    if (closeButton) closeButton.remove();
}

function downloadPdf() {
    const link = document.createElement('a');
    link.href = pdfUrl;
    link.download = 'Manual do Operador - Versão 6.75.pdf';
    link.click();
}

function openPdfInNewTab() {
    window.open(pdfUrl, '_blank');
}


/* ===================================================
   6. COPIAR LINKS
=================================================== */
function copyLinkgmail() {
    const link = "https://myaccount.google.com/apppasswords?utm_source=google-account&utm_medium=myaccountsecurity&utm_campaign=tsv-settings&rapt=AEjHL4OHLAbIlcVML9-0cdvQJf7CDFww6cn7XzilZV1rwmqZy2tOLi_GQ0-OzicbFJiiEANFQi-QP5Sy8gGHiLQWzy6y96OxaKFgks8KkaFWSjUHnWi2J8I";
    navigator.clipboard.writeText(link)
        .then(() => alert("Link copiado para a área de transferência!"))
        .catch(err => console.error("Erro ao copiar o link: ", err));
}

function copyLinkoutlook() {
    const link = "https://account.live.com/proofs/AppPassword?uaid=0d4fbc709a0b4baa9be4bf7658cea8a4&mpsplit=2&mkt=pt-BR";
    navigator.clipboard.writeText(link)
        .then(() => alert("Link copiado para a área de transferência!"))
        .catch(err => console.error("Erro ao copiar o link: ", err));
}


/* ===================================================
   7. MENU
=================================================== */
function toggleMenu() {
    const menu = document.querySelector('.nav');
    if (!menu) return;
    menu.style.display = menu.style.display === 'flex' ? 'none' : 'flex';
}

// Fecha ao clicar fora
document.addEventListener('click', function (event) {
    const menu          = document.querySelector('.nav');
    const menuContainer = document.querySelector('.menu-container');
    if (!menu || !menuContainer) return;
    if (!menu.contains(event.target) && !menuContainer.contains(event.target)) {
        menu.style.display = 'none';
    }
});

// Rolagem suave para âncoras
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const targetEl = document.querySelector(this.getAttribute('href'));
        if (targetEl) targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});

// Hover: abre ao passar o mouse sobre o botão, fecha ao sair do menu
const _navMenu = document.querySelector('.nav');
const _menuBtn = document.querySelector('.menu-container');
if (_navMenu && _menuBtn) {
    _menuBtn.addEventListener('mouseover', () => { _navMenu.style.display = 'flex'; });
    _navMenu.addEventListener('mouseleave', () => { _navMenu.style.display = 'none'; });
}


/* ===================================================
   8. BOTÃO MOSTRAR MAIS
=================================================== */
const showMoreBtn = document.getElementById('showMoreBtn');
if (showMoreBtn) {
    showMoreBtn.addEventListener('click', function () {
        const moreItems = document.getElementById('moreItems1');
        if (!moreItems) return;
        const expanded = moreItems.style.display === 'block';
        moreItems.style.display = expanded ? 'none' : 'block';
        this.textContent = expanded ? 'Mostrar mais' : 'Ocultar';
    });
}


/* ===================================================
   9. ARRASTAR MENU (IIFE)
=================================================== */
(function () {
    const STORAGE_KEY = 'nav-order-v3';
    const nav  = document.querySelector('.nav');
    const list = document.querySelector('.nav ul');

    if (!nav || !list) return;

    // Força estado oculto inicial (sobrepõe CSS display:flex que conflita com .hidden)
    nav.style.display = 'none';

    [...list.children].forEach((li, idx) => {
        if (!li.dataset.key) {
            const href = li.querySelector('a')?.getAttribute('href');
            li.dataset.key = href || `item-${idx}`;
        }
        li.setAttribute('draggable', 'true');
    });

    const defaultOrder = [...list.children].map(li => li.dataset.key);

    function applyOrder(keys) {
        const map = new Map([...list.children].map(li => [li.dataset.key, li]));
        keys.forEach(k => { const li = map.get(k); if (li) list.appendChild(li); });
    }
    function saveOrder() {
        const keys = [...list.children].map(li => li.dataset.key);
        localStorage.setItem(STORAGE_KEY, JSON.stringify(keys));
    }

    (function loadSaved() {
        try {
            const saved = JSON.parse(localStorage.getItem(STORAGE_KEY));
            if (Array.isArray(saved)) applyOrder(saved);
        } catch (_) {}
    })();

    let draggingEl = null;

    list.addEventListener('dragstart', (e) => {
        const li = e.target.closest('li[draggable="true"]');
        if (!li) return;
        draggingEl = li;
        li.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', li.dataset.key);
    });

    list.addEventListener('dragover', (e) => {
        e.preventDefault();
        const after = getDragAfterElement(list, e.clientY);
        if (after == null) {
            list.appendChild(draggingEl);
        } else {
            list.insertBefore(draggingEl, after);
        }
    });

    list.addEventListener('dragend', () => {
        if (!draggingEl) return;
        draggingEl.classList.remove('dragging');
        draggingEl = null;
        saveOrder();
    });

    function getDragAfterElement(container, y) {
        const els = [...container.querySelectorAll('li[draggable="true"]:not(.dragging)')];
        return els.reduce((closest, child) => {
            const box    = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    list.addEventListener('click', saveOrder);

    window.toggleMenu = function () {
        nav.style.display = nav.style.display === 'flex' ? 'none' : 'flex';
    };

    window.sortAZ = function () {
        const items = [...list.children];
        items.sort((a, b) => {
            const ta = (a.querySelector('a')?.textContent || '').trim();
            const tb = (b.querySelector('a')?.textContent || '').trim();
            return ta.localeCompare(tb, 'pt-BR', { sensitivity: 'base' });
        });
        items.forEach(li => list.appendChild(li));
        saveOrder();
    };

    window.resetOrder = function () {
        localStorage.removeItem(STORAGE_KEY);
        applyOrder(defaultOrder);
    };
})();


/* ===================================================
   10. TOOLTIP BOTÃO FAQ
=================================================== */
document.addEventListener('DOMContentLoaded', function () {
    const tooltip = document.getElementById('floating-tooltip');
    if (tooltip) {
        tooltip.style.visibility = 'visible';
        tooltip.style.opacity    = '1';
        setTimeout(hideTooltip, 3000);
    }
});

function showTooltip() {
    const tooltip = document.getElementById('floating-tooltip');
    if (!tooltip) return;
    tooltip.style.visibility = 'visible';
    tooltip.style.opacity    = '1';
}

function hideTooltip() {
    const tooltip = document.getElementById('floating-tooltip');
    if (!tooltip) return;
    tooltip.style.opacity = '0';
    setTimeout(() => { tooltip.style.visibility = 'hidden'; }, 500);
}

function redirectToFAQ() {
    window.location.href = 'https://tgameajuda.com/tgamanuais/perguntas-frequentes.html';
}


/* ===================================================
   11. SEMANA DE TELEFONE
=================================================== */
async function carregarSemanaTelefone() {
    const painelAuto   = document.getElementById('semana-telefone-automatico');
    const painelManual = document.getElementById('semana-telefone-manual');

    function txt(id, valor) {
        const el = document.getElementById(id);
        if (el) el.textContent = valor || '—';
    }

    function formatarLista(valor) {
        if (Array.isArray(valor)) return valor.map(i => String(i).trim()).filter(Boolean).join('\n');
        if (typeof valor === 'string') return valor.trim();
        return '';
    }

    try {
        const res  = await fetch('https://tgameajuda.com/telefone/status_telefone.php?v=' + Date.now());
        const data = await res.json();

        if ((data?.modo || '').toLowerCase() === 'manual') {
            const m = data.manual || {};
            txt('manualTelefone',    formatarLista(m.telefone));
            txt('manualChat',        formatarLista(m.chat));
            txt('manualFolga',       formatarLista(m.folga));
            txt('manualCompensacao', formatarLista(m.compensacao));

            const subtituloEl = document.getElementById('manualSubtitulo');
            const avisoEl     = document.getElementById('manualAviso');
            const subtitulo   = (m.subtitulo    || '').trim();
            const aviso       = (m.aviso_resumo || '').trim();

            if (subtituloEl) { subtituloEl.textContent = subtitulo; subtituloEl.style.display = subtitulo ? '' : 'none'; }
            if (avisoEl)     { avisoEl.textContent     = aviso;     avisoEl.style.display     = aviso     ? '' : 'none'; }

            if (painelAuto)   painelAuto.style.display  = 'none';
            if (painelManual) painelManual.style.display = '';
            return;
        }

        if (painelManual) painelManual.style.display = 'none';
        if (painelAuto)   painelAuto.style.display   = '';
        txt('telefoneAnteriorResumo', data.anterior);
        txt('telefoneAtualResumo',    data.atual);
        txt('telefoneProximaResumo',  data.proxima);

    } catch (e) {
        console.error('Erro ao carregar semana telefone', e);
    }
}

carregarSemanaTelefone();
setInterval(carregarSemanaTelefone, 60000);
