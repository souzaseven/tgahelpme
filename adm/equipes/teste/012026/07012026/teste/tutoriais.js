/* ===================================================
   0. MONITORAMENTO DE VERSÃO
   Duas fontes de verdade convivem aqui: o arquivo estático ./versao/*.txt
   e o endpoint ./version-monitor.php. Antes eram 2 sistemas totalmente
   separados (cada um com seu próprio polling de 30s e seu próprio jeito
   de montar o toast) — unificados num só polling/toast pra não notificar
   a mesma troca de versão duas vezes nem duplicar o fetch a cada 30s.
=================================================== */
document.addEventListener("DOMContentLoaded", function () {
    const FILE_VERSION_URL = './versao/v26.03.01.txt';
    const PHP_ENDPOINT = './version-monitor.php';
    const FILE_NOTIFIED_KEY = 'siteVersionNotified';
    const versionSpan = document.getElementById('page-version');
    const VERSION_LINKS = `🔗 <strong>Ver novidades:</strong><br>• <a href='https://atendimento.tgasistemas.com.br/kb/pt-br/article/549816/novidades' target='_blank'>Movidesk</a><br>• <a href='https://tgameajuda.com/NovidadesVersao/novidadeversao.html' target='_blank'>TGA Ajuda</a>`;

    if (!document.getElementById('toastContainer')) {
        const container = document.createElement('div');
        container.id = 'toastContainer';
        document.body.appendChild(container);
    }

    function showVersionToast(message) {
        const title = '📦 Nova Versão Disponível!';
        const toast = document.createElement('div');
        toast.className = 'toast info';
        toast.innerHTML = `<div class="toast-icon">ℹ</div><div class="toast-content"><div class="toast-title">${title}</div><div class="toast-message">${message}</div><div class="toast-progress"></div></div><button class="toast-close">×</button>`;
        document.getElementById('toastContainer').appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 50);
        setTimeout(() => { toast.classList.remove('show'); toast.classList.add('hide'); setTimeout(() => toast.remove(), 300); }, 5000);
        toast.querySelector('.toast-close').addEventListener('click', () => toast.remove());

        if ('Notification' in window && Notification.permission === 'granted') {
            const n = new Notification(title, { body: message.replace(/<[^>]+>/g, ' '), icon: './img/principal/bot-tga.webp' });
            n.onclick = () => window.open('https://tgameajuda.com/NovidadesVersao/novidadeversao.html', '_blank');
        } else if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }

    async function checkFileVersion() {
        try {
            const resp = await fetch(FILE_VERSION_URL + '?t=' + Date.now());
            if (!resp.ok) return;
            const serverVersion = (await resp.text()).trim();
            if (!serverVersion) return;
            if (localStorage.getItem(FILE_NOTIFIED_KEY) !== serverVersion) {
                showVersionToast(`Versão nova: ${serverVersion}<br><br>${VERSION_LINKS}`);
                localStorage.setItem(FILE_NOTIFIED_KEY, serverVersion);
                if (versionSpan) versionSpan.textContent = serverVersion;
            }
        } catch (e) { /* offline ou arquivo indisponível: ignora silenciosamente */ }
    }

    // Se o endpoint PHP não existir neste ambiente (404/erro), não insiste a cada 30s pra sempre.
    let phpEndpointUnavailable = false;
    async function checkPHPVersion() {
        if (phpEndpointUnavailable) return;
        try {
            const resp = await fetch(`${PHP_ENDPOINT}?action=check&t=${Date.now()}`);
            if (!resp.ok) { phpEndpointUnavailable = true; return; }
            const data = await resp.json();
            if (data.success && data.data?.changes) {
                Object.values(data.data.changes).forEach(change => {
                    showVersionToast(`[PHP: ${change.file}] ${change.old} → ${change.new}<br><br>${VERSION_LINKS}`);
                });
            }
        } catch (e) {
            phpEndpointUnavailable = true;
        }
    }

    checkFileVersion();
    checkPHPVersion();
    // Pausa o polling com a aba em segundo plano (evita fetch/trabalho desnecessário).
    setInterval(() => {
        if (document.hidden) return;
        checkFileVersion();
        checkPHPVersion();
    }, 30000);
});


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

    const ESC_MAP = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
    function escHtml(str) {
        return str.replace(/[&<>"']/g, c => ESC_MAP[c]);
    }

    // Índice montado uma única vez (não a cada tecla digitada): evita repercorrer
    // ~36 seções / ~240 itens e evita `innerText`, que força reflow síncrono do
    // navegador a cada leitura — `textContent` lê a árvore do DOM sem isso.
    const sectionIndex = Array.from(document.querySelectorAll('section')).map(section => {
        const h2 = section.querySelector('h2');
        const items = Array.from(section.querySelectorAll('.Dowloads-item')).map(item => ({
            el: item,
            text: (item.textContent || '').toLowerCase()
        }));
        return {
            el: section,
            title: (h2 ? h2.textContent : '').toLowerCase(),
            text: items.length === 0 ? (section.textContent || '').toLowerCase() : '',
            items
        };
    });

    function showAll() {
        sectionIndex.forEach(({ el, items }) => {
            el.style.display = '';
            el.classList.remove('search-filtering');
            items.forEach(({ el: itemEl }) => { itemEl.style.display = ''; });
        });
        if (resultsBar) resultsBar.style.display = 'none';
    }

    function filterContent() {
        const raw  = searchInput.value;
        const term = raw.trim().toLowerCase();

        if (clearBtn) clearBtn.style.display = term ? 'flex' : 'none';

        if (!term) {
            showAll();
            return;
        }

        let totalItems = 0, totalSections = 0;

        sectionIndex.forEach(({ el, title, text, items }) => {
            const titleMatch = title.includes(term);
            let visibleItems = 0;

            if (items.length > 0) {
                items.forEach(({ el: itemEl, text: itemText }) => {
                    const show = titleMatch || itemText.includes(term);
                    itemEl.style.display = show ? '' : 'none';
                    if (show) visibleItems++;
                });
            } else if (text.includes(term)) {
                visibleItems = 1;
            }

            const sectionShow = titleMatch || visibleItems > 0;
            el.style.display = sectionShow ? '' : 'none';
            if (sectionShow) {
                el.classList.add('search-filtering');
                totalSections++;
                totalItems += (items.length > 0 ? visibleItems : 1);
            } else {
                el.classList.remove('search-filtering');
            }
        });

        if (resultsBar) {
            resultsBar.style.display = 'flex';
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
    const isDark = document.documentElement.classList.toggle('dark-mode');
    localStorage.setItem('tga-theme', isDark ? 'dark' : 'light');
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

    // Scroll dispara muitas vezes por segundo; agrupa em 1 leitura/escrita por frame
    // via requestAnimationFrame em vez de rodar a lógica a cada evento.
    let scrollTicking = false;
    function onScroll() {
        if (scrollTicking) return;
        scrollTicking = true;
        requestAnimationFrame(() => { updateScrollBtns(); scrollTicking = false; });
    }

    window.addEventListener('scroll', onScroll, { passive: true });
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
setInterval(() => { if (!document.hidden) carregarSemanaTelefone(); }, 60000);


/* ===================================================
   12. PREVISÃO DAS PRÓXIMAS SEMANAS (telefone)
=================================================== */
function montarPrevisaoSemanas(atualLider) {
    const ordem = ['Alex Sandro Braulio', 'Daniel Feix', 'Willian Pereira Reis'];

    function inicioSemana(dateStr) {
        const d = new Date(dateStr + 'T12:00:00');
        d.setDate(d.getDate() - d.getDay()); // recua até domingo
        return d;
    }

    function fimSemana(d) {
        const f = new Date(d);
        f.setDate(f.getDate() + 6);
        return f;
    }

    function fmtDia(d) {
        return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0');
    }

    const hoje = new Date();
    const hojeStr = hoje.toISOString().slice(0, 10);
    const semanas = [];

    // última semana do mês anterior
    const ultimoDiaMesAnterior = new Date(hoje.getFullYear(), hoje.getMonth(), 0);
    const inicioUlt = inicioSemana(ultimoDiaMesAnterior.toISOString().slice(0, 10));
    semanas.push({ inicio: new Date(inicioUlt), fim: fimSemana(inicioUlt) });

    // semana atual + 4 seguintes (5 no total)
    const inicioAtual = inicioSemana(hojeStr);
    for (let i = 0; i < 5; i++) {
        const ini = new Date(inicioAtual);
        ini.setDate(ini.getDate() + i * 7);
        semanas.push({ inicio: ini, fim: fimSemana(ini) });
    }

    // descobre índice da semana atual
    let semanaAtualIdx = 0;
    semanas.forEach((s, k) => {
        const ini = s.inicio.toISOString().slice(0, 10);
        const fim = s.fim.toISOString().slice(0, 10);
        if (hojeStr >= ini && hojeStr <= fim) semanaAtualIdx = k;
    });

    // índice do líder atual na ordem de rotação
    const idxAtual = ordem.indexOf(atualLider) !== -1 ? ordem.indexOf(atualLider) : 0;

    const tbody = document.getElementById('tbody-previsao-semanas');
    if (!tbody) return;
    tbody.innerHTML = '';

    semanas.forEach((s, i) => {
        const delta   = i - semanaAtualIdx;
        const idxTel  = ((idxAtual + delta) % 3 + 3) % 3;
        const idxComp = (idxTel + 1) % 3;
        const idxChat = (idxTel + 2) % 3;
        const isAtual = i === semanaAtualIdx;

        const tr = document.createElement('tr');
        if (isAtual) tr.classList.add('semana-atual');

        tr.innerHTML = `
            <td class="col-semana">
                ${fmtDia(s.inicio)}–${fmtDia(s.fim)}
                ${isAtual ? '<span class="previsao-atual-tag"> (atual)</span>' : ''}
            </td>
            <td><span class="previsao-badge badge-telefone">${ordem[idxTel]}</span></td>
            <td><span class="previsao-badge badge-chat">${ordem[idxChat]}</span></td>
            <td><span class="previsao-badge badge-compensacao">${ordem[idxComp]}</span></td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('resumo-semanas-mini').classList.add('is-visible');
}

// Aguarda o span ser preenchido por carregarSemanaTelefone() e então monta a tabela
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('telefoneAtualResumo');
    if (!el) return;
    const obsPrevisao = new MutationObserver(function (mutations, obs) {
        if (el.textContent.trim() && el.textContent.trim() !== '—') {
            montarPrevisaoSemanas(el.textContent.trim());
            obs.disconnect();
        }
    });
    obsPrevisao.observe(el, { childList: true, subtree: true, characterData: true });
});


/* ===================================================
   13. SAIR / OCULTAR SESSÃO NO SCROLL
=================================================== */
function sair() {
    localStorage.removeItem("acesso");
}

(function () {
    const sessao = document.getElementById("tempo-sessao");
    if (!sessao) return;
    let ticking = false;
    document.addEventListener("scroll", () => {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(() => {
            sessao.classList.toggle("oculto", window.scrollY > 50);
            ticking = false;
        });
    }, { passive: true });
})();


/* ===================================================
   14. ANÚNCIOS: push consolidado + ocultar unidades soltas
   Só unidades dentro de .ad-placement são exibidas/solicitadas;
   qualquer <ins class="adsbygoogle"> fora disso fica oculto
   (substitui os ~31 blocos <script>...push({})</script> que
   ficavam soltos após cada <ins>, um por seção do site).
=================================================== */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.ad-placement .adsbygoogle').forEach(function () {
        try {
            (window.adsbygoogle = window.adsbygoogle || []).push({});
        } catch (_) { }
    });

    document.querySelectorAll('ins.adsbygoogle').forEach(function (el) {
        if (!el.closest('.ad-placement')) {
            el.style.display = 'none';
            el.setAttribute('aria-hidden', 'true');
        }
    });
});


/* ===================================================
   15. LAZY LOADING GENÉRICO + SEÇÕES NA VIEWPORT + CARD "VERSÃO ATUAL"
=================================================== */
document.addEventListener('DOMContentLoaded', function () {
    // Aplica lazy loading para qualquer mídia que ainda não esteja com essa flag.
    document.querySelectorAll('img:not([loading])').forEach(function (img) {
        img.loading = 'lazy';
        img.decoding = 'async';
        if (!img.getAttribute('fetchpriority')) {
            img.setAttribute('fetchpriority', 'low');
        }
    });

    document.querySelectorAll('iframe:not([loading])').forEach(function (iframe) {
        iframe.loading = 'lazy';
    });

    // Comportamento de seção progressiva: marca seções quando entram na viewport.
    const sections = document.querySelectorAll('section');
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver(function (entries, observer) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('section-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            root: null,
            rootMargin: '300px 0px',
            threshold: 0.01
        });

        sections.forEach(function (section) {
            io.observe(section);
        });
    }

    // Atualiza automaticamente o card "Versão Atual" com a última imagem e último instalador do ano.
    const versaoImg = document.getElementById('versaoAtualImagem');
    const versaoLink = document.getElementById('versaoAtualDownloadLink');
    if (!versaoImg || !versaoLink) {
        return;
    }

    const year = new Date().getFullYear();
    const versoesBase = 'https://tgameajuda.com/img/vers%C3%B5es';
    const imagensDir = `${versoesBase}/${year}/`;
    const instaladoresDir = `https://tgameajuda.com/NovidadesVersao/Instaladores%20${year}/`;

    function extrairLinks(html, extensoes) {
        const hrefRegex = /href\s*=\s*["']([^"'#?]+)["']/gi;
        const links = [];
        let match;

        while ((match = hrefRegex.exec(html)) !== null) {
            const href = match[1].trim();
            if (!href || href === '../') continue;

            const lower = href.toLowerCase();
            if (extensoes.some(function (ext) { return lower.endsWith(ext); })) {
                links.push(href);
            }
        }

        return links;
    }

    function ordenarPorNomeRecente(a, b) {
        return a.localeCompare(b, 'pt-BR', { numeric: true, sensitivity: 'base' });
    }

    async function listarArquivos(url, extensoes) {
        const resp = await fetch(url, { cache: 'no-store' });
        if (!resp.ok) {
            throw new Error(`Falha ao listar ${url}: ${resp.status}`);
        }

        const html = await resp.text();
        const arquivos = extrairLinks(html, extensoes)
            .map(function (href) {
                if (/^https?:\/\//i.test(href)) {
                    return href;
                }
                return new URL(href, url).toString();
            })
            .sort(ordenarPorNomeRecente);

        return arquivos;
    }

    (async function atualizarCardVersaoAtual() {
        try {
            const [imagens, instaladores] = await Promise.all([
                listarArquivos(imagensDir, ['.webp', '.png', '.jpg', '.jpeg']),
                listarArquivos(instaladoresDir, ['.exe'])
            ]);

            if (imagens.length > 0) {
                versaoImg.src = imagens[imagens.length - 1];
            }

            if (instaladores.length > 0) {
                versaoLink.href = instaladores[instaladores.length - 1];
            }
        } catch (err) {
            console.warn('Não foi possível atualizar automaticamente a seção de versão atual.', err);
        }
    })();
});


/* ===================================================
   16. ANÚNCIOS LATERAIS (telas grandes) + ANÚNCIO ENTRE SEÇÕES
=================================================== */
document.addEventListener('DOMContentLoaded', function () {
    // LATERAIS: só exibe em telas grandes. Só registra o listener de resize se
    // os rails existirem no DOM — hoje não existem, então isso evitava rodar
    // 2 querySelectorAll + trabalho à toa a cada resize da janela, para sempre.
    if (document.querySelector('.ad-rail-left') || document.querySelector('.ad-rail-right')) {
        let resizeTimer = null;
        const showLateralAds = () => {
            const left = document.querySelector('.ad-rail-left');
            const right = document.querySelector('.ad-rail-right');
            if (window.innerWidth >= 1200) {
                if (left) left.style.display = 'block';
                if (right) right.style.display = 'block';
                if (window.adsbygoogle) {
                    if (left && left.offsetWidth > 0) window.adsbygoogle.push({});
                    if (right && right.offsetWidth > 0) window.adsbygoogle.push({});
                }
            } else {
                if (left) left.style.display = 'none';
                if (right) right.style.display = 'none';
            }
        };
        showLateralAds();
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(showLateralAds, 150);
        });
    }

    // ENTRE SECTIONS: insere anúncio a cada 2 sections
    const adSlots = [
        { slot: '7622049777', format: 'auto' },
        { slot: '1743677769', format: 'fluid', layoutKey: '-fb+5w+4e-db+86' },
        { slot: '3993704063', format: 'fluid', layout: 'in-article' },
        { slot: '9430596092', format: 'autorelaxed' }
    ];
    let adIndex = 0;
    const sections = Array.from(document.querySelectorAll('section'));
    for (let i = 2; i < sections.length; i += 2) {
        const prevSection = sections[i - 1];
        const nextSection = sections[i];
        if (!prevSection || !prevSection.parentNode) continue;

        const adPlaceholder = document.createElement('div');
        adPlaceholder.className = 'ad-placeholder';

        const slot = adSlots[adIndex % adSlots.length];
        adIndex++;
        const ad = document.createElement('ins');
        ad.className = 'adsbygoogle';
        ad.setAttribute('data-ad-client', 'ca-pub-8542251167876044');
        ad.setAttribute('data-ad-slot', slot.slot);
        ad.setAttribute('data-ad-format', slot.format);
        if (slot.layoutKey) ad.setAttribute('data-ad-layout-key', slot.layoutKey);
        if (slot.layout) ad.setAttribute('data-ad-layout', slot.layout);
        ad.setAttribute('data-full-width-responsive', 'true');
        adPlaceholder.appendChild(ad);

        try {
            if (nextSection && prevSection.parentNode.contains(nextSection)) {
                prevSection.parentNode.insertBefore(adPlaceholder, nextSection);
            } else {
                prevSection.parentNode.appendChild(adPlaceholder);
            }
        } catch (err) {
            console.warn('Não foi possível inserir anúncio entre seções.', err);
            continue;
        }

        if (window.adsbygoogle && ad.offsetWidth > 0) window.adsbygoogle.push({});
    }

    // Carrega o script do AdSense se ainda não estiver presente
    if (!document.querySelector('script[src*="adsbygoogle.js"]')) {
        const script = document.createElement('script');
        script.async = true;
        script.src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js';
        script.setAttribute('crossorigin', 'anonymous');
        document.head.appendChild(script);
    }
});
