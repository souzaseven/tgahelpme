// js/ads-optimized.js
// Arquivo otimizado para máximo rendimento com AdSense
// Estratégia: Lateral fixa, rodapé sticky, anúncios contextuais

// ======================
// CONFIGURAÇÃO GLOBAL
// ======================
var adClient = "ca-pub-8542251167876044";
var isMobile = window.innerWidth <= 768;
var adsInitialized = false;

// ======================
// 1. ANÚNCIO LATERAL FIXO (DESKTOP)
// Ideal para espaços laterais não utilizados
// ======================
function createSidebarAds() {
    if (isMobile) return; // Apenas desktop
    
    // Lado esquerdo
    var leftAd = document.createElement('div');
    leftAd.id = 'adsense-sidebar-left';
    leftAd.style.cssText = `
        position: fixed;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        width: 160px;
        z-index: 9998;
        background: transparent;
    `;
    leftAd.innerHTML = `
        <ins class="adsbygoogle"
             style="display:block"
             data-ad-client="${adClient}"
             data-ad-slot="8872320133"
             data-ad-format="vertical"
             data-full-width-responsive="false"></ins>
        <script>(adsbygoogle = window.adsbygoogle || []).push({});<\/script>
    `;
    document.body.appendChild(leftAd);
    
    // Lado direito
    var rightAd = document.createElement('div');
    rightAd.id = 'adsense-sidebar-right';
    rightAd.style.cssText = `
        position: fixed;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        width: 160px;
        z-index: 9998;
        background: transparent;
    `;
    rightAd.innerHTML = `
        <ins class="adsbygoogle"
             style="display:block"
             data-ad-client="${adClient}"
             data-ad-slot="6489699370"
             data-ad-format="vertical"
             data-full-width-responsive="false"></ins>
        <script>(adsbygoogle = window.adsbygoogle || []).push({});<\/script>
    `;
    document.body.appendChild(rightAd);
}

// ======================
// 2. RODAPÉ STICKY (MOBILE & DESKTOP)
// Alto CTR por estar sempre visível
// ======================
function createStickyFooter() {
    var footerAd = document.createElement('div');
    footerAd.id = 'adsense-sticky-footer';
    footerAd.style.cssText = `
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        background: #fff;
        z-index: 9999;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        padding: 8px;
        display: none;
    `;
    footerAd.innerHTML = `
        <div style="max-width: 728px; margin: 0 auto;">
            <ins class="adsbygoogle"
                 style="display:block"
                 data-ad-client="${adClient}"
                 data-ad-slot="${isMobile ? '8209975152' : '7559238469'}"
                 data-ad-format="${isMobile ? 'horizontal' : 'auto'}"
                 data-full-width-responsive="true"></ins>
            <script>(adsbygoogle = window.adsbygoogle || []).push({});<\/script>
        </div>
        <button id="close-sticky-ad" style="position:absolute; top:5px; right:5px; background:none; border:none; font-size:16px; cursor:pointer; color:#666;">×</button>
    `;
    document.body.appendChild(footerAd);
    
    // Mostrar após 3 segundos
    setTimeout(function() {
        footerAd.style.display = 'block';
    }, 3000);
    
    // Botão fechar
    document.getElementById('close-sticky-ad').addEventListener('click', function() {
        footerAd.style.display = 'none';
    });
}

// ======================
// 3. ANÚNCIOS NO CONTEÚDO
// Posicionados estrategicamente no conteúdo
// ======================
function injectContentAds() {
    // Anúncio após primeiro parágrafo
    var firstParagraph = document.querySelector('article p, .post-content p, main p');
    if (firstParagraph) {
        var adContainer = document.createElement('div');
        adContainer.className = 'content-ad';
        adContainer.style.cssText = 'margin: 25px 0; text-align: center;';
        adContainer.innerHTML = `
            <ins class="adsbygoogle"
                 style="display:block; text-align:center;"
                 data-ad-layout="in-article"
                 data-ad-format="fluid"
                 data-ad-client="${adClient}"
                 data-ad-slot="6077693880"></ins>
            <script>(adsbygoogle = window.adsbygoogle || []).push({});<\/script>
        `;
        firstParagraph.parentNode.insertBefore(adContainer, firstParagraph.nextSibling);
    }
    
    // Anúncio no meio do conteúdo
    var allParagraphs = document.querySelectorAll('article p, .post-content p, main p');
    if (allParagraphs.length > 5) {
        var middleIndex = Math.floor(allParagraphs.length / 2);
        var middleAd = document.createElement('div');
        middleAd.className = 'content-ad-middle';
        middleAd.style.cssText = 'margin: 30px 0; text-align: center; clear: both;';
        middleAd.innerHTML = `
            <ins class="adsbygoogle"
                 style="display:block"
                 data-ad-client="${adClient}"
                 data-ad-slot="5176617700"
                 data-ad-format="auto"
                 data-full-width-responsive="true"></ins>
            <script>(adsbygoogle = window.adsbygoogle || []).push({});<\/script>
        `;
        allParagraphs[middleIndex].parentNode.insertBefore(middleAd, allParagraphs[middleIndex].nextSibling);
    }
    
    // Anúncio antes dos comentários/fim do artigo
    var endSelectors = ['#comments', '.comments', '.article-end', 'footer', '.post-end'];
    var endElement = null;
    for (var selector of endSelectors) {
        endElement = document.querySelector(selector);
        if (endElement) break;
    }
    if (endElement) {
        var endAd = document.createElement('div');
        endAd.className = 'content-ad-end';
        endAd.style.cssText = 'margin: 25px 0; text-align: center;';
        endAd.innerHTML = `
            <ins class="adsbygoogle"
                 style="display:block"
                 data-ad-client="${adClient}"
                 data-ad-slot="6246156790"
                 data-ad-format="auto"
                 data-full-width-responsive="true"></ins>
            <script>(adsbygoogle = window.adsbygoogle || []).push({});<\/script>
        `;
        endElement.parentNode.insertBefore(endAd, endElement);
    }
}

// ======================
// 4. ANÚNCIOS RESPONSIVOS ADICIONAIS
// Preenchem espaços vazios
// ======================
function createResponsiveAds() {
    // Header
    var header = document.querySelector('header, .site-header, .main-header');
    if (header) {
        var headerAd = document.createElement('div');
        headerAd.style.cssText = 'text-align: center; margin: 10px 0;';
        headerAd.innerHTML = `
            <ins class="adsbygoogle"
                 style="display:block"
                 data-ad-client="${adClient}"
                 data-ad-slot="4573982472"
                 data-ad-format="auto"
                 data-full-width-responsive="true"></ins>
            <script>(adsbygoogle = window.adsbygoogle || []).push({});<\/script>
        `;
        header.appendChild(headerAd);
    }
    
    // Sidebar (se existir)
    var sidebar = document.querySelector('.sidebar, aside, #sidebar');
    if (sidebar && !isMobile) {
        var sidebarAd = document.createElement('div');
        sidebarAd.style.cssText = 'margin-bottom: 20px;';
        sidebarAd.innerHTML = `
            <ins class="adsbygoogle"
                 style="display:block"
                 data-ad-client="${adClient}"
                 data-ad-slot="2700845219"
                 data-ad-format="autorelaxed"></ins>
            <script>(adsbygoogle = window.adsbygoogle || []).push({});<\/script>
        `;
        sidebar.insertBefore(sidebarAd, sidebar.firstChild);
    }
}

// ======================
// 5. LAZY LOAD PARA ANÚNCIOS
// Carrega quando visíveis na tela
// ======================
function lazyLoadAds() {
    var ads = document.querySelectorAll('.adsbygoogle');
    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                var ad = entry.target;
                if (!ad.hasAttribute('data-loaded')) {
                    (adsbygoogle = window.adsbygoogle || []).push({});
                    ad.setAttribute('data-loaded', 'true');
                }
            }
        });
    }, { threshold: 0.1 });
    
    ads.forEach(function(ad) {
        observer.observe(ad);
    });
}

// ======================
// 6. ADAPTAR AO TAMANHO DA TELA
// ======================
function handleResize() {
    var wasMobile = isMobile;
    isMobile = window.innerWidth <= 768;
    
    // Remover sidebars em mobile
    if (isMobile) {
        var leftAd = document.getElementById('adsense-sidebar-left');
        var rightAd = document.getElementById('adsense-sidebar-right');
        if (leftAd) leftAd.style.display = 'none';
        if (rightAd) rightAd.style.display = 'none';
    } else if (wasMobile) {
        // Restaurar sidebars em desktop
        var leftAd = document.getElementById('adsense-sidebar-left');
        var rightAd = document.getElementById('adsense-sidebar-right');
        if (leftAd) leftAd.style.display = 'block';
        if (rightAd) rightAd.style.display = 'block';
    }
}

// ======================
// INICIALIZAÇÃO
// ======================
function initAds() {
    if (adsInitialized) return;
    
    // Aguardar DOM carregado
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAds);
        return;
    }
    
    // Criar anúncios
    createSidebarAds();
    createStickyFooter();
    injectContentAds();
    createResponsiveAds();
    
    // Lazy load após um breve delay
    setTimeout(lazyLoadAds, 1000);
    
    // Event listeners
    window.addEventListener('resize', handleResize);
    
    adsInitialized = true;
    
    console.log('Ads otimizados carregados!');
}

// Iniciar
initAds();