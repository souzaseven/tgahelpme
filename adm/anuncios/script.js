// Google Analytics
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    gtag('js', new Date());
    gtag('config', 'G-S8EC5C2WTG');
    gtag('config', 'G-E7ZNTJSRYR');

    // Sistema de Modo Claro/Escuro
    const themeToggle = document.getElementById('themeToggle');
    const body = document.body;
    
    // Verificar preferência salva ou definir padrão
    const savedTheme = localStorage.getItem('theme') || 'dark';
    body.className = savedTheme + '-mode';
    updateThemeIcon();
    
    themeToggle.addEventListener('click', () => {
        if (body.classList.contains('dark-mode')) {
            body.classList.replace('dark-mode', 'light-mode');
            localStorage.setItem('theme', 'light');
        } else {
            body.classList.replace('light-mode', 'dark-mode');
            localStorage.setItem('theme', 'dark');
        }
        updateThemeIcon();
    });
    
    function updateThemeIcon() {
        const icon = themeToggle.querySelector('i');
        if (body.classList.contains('dark-mode')) {
            icon.className = 'fas fa-sun';
        } else {
            icon.className = 'fas fa-moon';
        }
    }

    // Sistema de Auto-Atualização (1 minuto) - 
    let refreshCount = parseInt(localStorage.getItem('refreshCount')) || 0; 
    let timeRemaining = 120;
    let autoRefreshEnabled = true;
    let refreshInterval;
    let progressInterval;
    
    const refreshCountElement = document.getElementById('refreshCount');
    const timeRemainingElement = document.getElementById('timeRemaining');
    const refreshNowButton = document.getElementById('refreshNow');
    const toggleAutoRefreshButton = document.getElementById('toggleAutoRefresh');
    const progressBar = document.getElementById('progressBar');
    
    // Atualizar o contador na tela ao carregar a página 
    refreshCountElement.textContent = refreshCount;
    
    // Iniciar contador
    startAutoRefresh();
    
    function startAutoRefresh() {
        if (refreshInterval) clearInterval(refreshInterval);
        if (progressInterval) clearInterval(progressInterval);
        
        // Atualizar contador de tempo
        refreshInterval = setInterval(() => {
            if (autoRefreshEnabled) {
                timeRemaining--;
                timeRemainingElement.textContent = timeRemaining;
                
                // Atualizar barra de progresso
                updateProgressBar();
                
                if (timeRemaining <= 0) {
                    refreshPage();
                }
            }
        }, 1000);
        
        // Animação suave da barra de progresso (10x por segundo)
        progressInterval = setInterval(() => {
            if (autoRefreshEnabled) {
                updateProgressBar();
            }
        }, 100);
    }
    
    function updateProgressBar() {
        if (progressBar) {
            const progress = ((60 - timeRemaining) / 60) * 100;
            progressBar.style.width = progress + '%';
        }
    }
    
    function refreshPage() {
        refreshCount++;
        localStorage.setItem('refreshCount', refreshCount); 
        refreshCountElement.textContent = refreshCount;
        timeRemaining = 60;
        timeRemainingElement.textContent = timeRemaining;
        
        // Resetar barra de progresso
        if (progressBar) {
            progressBar.style.width = '0%';
        }
        
        // Recarregar anúncios
        reloadAds();
        
        // Feedback visual
        showRefreshFeedback();
    }
    
    function reloadAds() {
        // Forçar recarregamento dos scripts de anúncios
        if (window.adsbygoogle) {
            // Recarregar todos os anúncios
            try {
                (adsbygoogle = window.adsbygoogle || []).push({});
                console.log('Anúncios recarregados - ' + new Date().toLocaleTimeString());
            } catch (error) {
                console.error('Erro ao recarregar anúncios:', error);
            }
        }
    }
    
    function showRefreshFeedback() {
        // Efeito visual de atualização
        const cards = document.querySelectorAll('.card');
        cards.forEach(card => {
            card.style.transform = 'scale(0.98)';
            setTimeout(() => {
                card.style.transform = 'scale(1)';
            }, 300);
        });
        
        // Feedback no painel de atualização
        const refreshPanel = document.querySelector('.refresh-panel');
        if (refreshPanel) {
            refreshPanel.style.backgroundColor = 'var(--primary-light)';
            setTimeout(() => {
                refreshPanel.style.backgroundColor = '';
            }, 500);
        }
    }
    
    refreshNowButton.addEventListener('click', () => {
        refreshPage();
    });
    
    toggleAutoRefreshButton.addEventListener('click', () => {
        autoRefreshEnabled = !autoRefreshEnabled;
        toggleAutoRefreshButton.textContent = autoRefreshEnabled ? 'Pausar' : 'Retomar';
        toggleAutoRefreshButton.className = autoRefreshEnabled ? 'btn btn-outline' : 'btn btn-primary';
        
        if (autoRefreshEnabled) {
            startAutoRefresh();
        } else {
            clearInterval(refreshInterval);
            clearInterval(progressInterval);
            if (progressBar) {
                progressBar.style.width = '0%';
            }
        }
    });

    // Service Worker e Notificações
    if ('serviceWorker' in navigator && 'Notification' in window) {
        navigator.serviceWorker.register('./notificacao/service-worker.js')
            .then(reg => console.log('SW registrado:', reg))
            .catch(err => console.error('Erro ao registrar SW:', err));

        if (Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                console.log(`Permissão para notificações: ${permission}`);
            });
        }
    } else {
        console.log('Seu navegador não suporta notificações.');
    }

    // Verificação de nova versão do site
    document.addEventListener("DOMContentLoaded", function () {
        const currentVersion = document.querySelector("#page-version")?.textContent.trim();
        const storedVersion = localStorage.getItem("siteVersion");
        if (currentVersion && storedVersion !== currentVersion) {
            console.log(`Nova versão disponível! Versão atual: ${currentVersion}`);
            localStorage.setItem("siteVersion", currentVersion);
        }
    });

    // Firebase Config
    const firebaseConfig = {
        apiKey: "BECXD5Aj5_o9zNq2VNDsCDguVl2hCoS9u0Oty-KG79nKd-3WnGEn4ey8Zm7NCcuVufLuH-hAnAnLEFlGWw7w7EM",
        authDomain: "tgameajuda.firebaseapp.com",
        projectId: "tgameajuda",
        storageBucket: "tgameajuda.appspot.com",
        messagingSenderId: "74941945706",
        appId: "1:74941945706:web:9f0da4e18bb9247a3bb713",
        measurementId: "G-J6NCJKCF63"
    };

    firebase.initializeApp(firebaseConfig);
    const messaging = firebase.messaging();

    function requestPermission() {
        Notification.requestPermission().then(permission => {
            if (permission === "granted") getFCMToken();
        });
    }

    function getFCMToken() {
        messaging.getToken({ vapidKey: firebaseConfig.apiKey })
            .then(token => {
                if (token) console.log('Token FCM:', token);
            })
            .catch(err => console.error('Erro ao obter token:', err));
    }

    document.addEventListener("DOMContentLoaded", requestPermission);

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('./firebase-messaging-sw.js')
            .then(reg => console.log('Service Worker do Firebase registrado:', reg))
            .catch(err => console.error('Erro SW Firebase:', err));
    }

    // Scroll to top functionality
    const scrollToTopBtn = document.getElementById('scrollToTopBtn');
    
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            scrollToTopBtn.style.display = 'flex';
        } else {
            scrollToTopBtn.style.display = 'none';
        }
    });
    
    scrollToTopBtn.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });

    // Inicializar anúncios quando a página carregar
    window.addEventListener('load', function() {
        // Forçar carregamento dos anúncios após um pequeno delay
        setTimeout(() => {
            reloadAds();
        }, 1000);
    });

    // Recarregar anúncios quando a página ganhar foco
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            reloadAds();
        }
    });

    // Recarregar anúncios quando a conexão voltar
    window.addEventListener('online', function() {
        reloadAds();
    });


/*automatico scrool*/
let direction = 'down';
let isAutoScrollActive = true;
let scrollInterval;
let userInteracted = false;

const config = {
    scrollAmount: 50,
    scrollInterval: 100,
    pauseAtEnds: 5000,
    smoothScroll: true
};

function createControls() {
    const controls = document.createElement('div');
    controls.id = 'scroll-controls';
    controls.innerHTML = `
        <div id="scroll-status">Auto Scroll: Ativo ▼</div>
        <button onclick="toggleAutoScroll()" id="toggle-btn">⏸️ Pausar</button>
        <button onclick="changeDirection()" id="direction-btn">🔄 Inverter Direção</button>
        <button onclick="stopAutoScroll()">⏹️ Parar</button>
    `;
    document.body.appendChild(controls);
}

function updateStatus() {
    const status = document.getElementById('scroll-status');
    const toggleBtn = document.getElementById('toggle-btn');
    const directionBtn = document.getElementById('direction-btn');
    
    if (status && toggleBtn && directionBtn) {
        status.textContent = `Auto Scroll: ${isAutoScrollActive ? 'Ativo' : 'Pausado'} ${direction === 'down' ? '▼' : '▲'}`;
        toggleBtn.innerHTML = isAutoScrollActive ? '⏸️ Pausar' : '▶️ Continuar';
        directionBtn.innerHTML = direction === 'down' ? '🔼 Rolagem para Cima' : '🔽 Rolagem para Baixo';
    }
}

function autoScroll() {
    if (!isAutoScrollActive) return;

    const scrollHeight = document.documentElement.scrollHeight;
    const currentScroll = window.scrollY + window.innerHeight;
    const scrollThreshold = 10;

    if (direction === 'down') {
        if (currentScroll < scrollHeight - scrollThreshold) {
            window.scrollBy({
                top: config.scrollAmount,
                behavior: config.smoothScroll ? 'smooth' : 'auto'
            });
        } else {
            direction = 'up';
            updateStatus();
            setTimeout(autoScroll, config.pauseAtEnds);
            return;
        }
    } else {
        if (window.scrollY > scrollThreshold) {
            window.scrollBy({
                top: -config.scrollAmount,
                behavior: config.smoothScroll ? 'smooth' : 'auto'
            });
        } else {
            direction = 'down';
            updateStatus();
            setTimeout(autoScroll, config.pauseAtEnds);
            return;
        }
    }

    scrollInterval = setTimeout(autoScroll, config.scrollInterval);
}

function toggleAutoScroll() {
    isAutoScrollActive = !isAutoScrollActive;
    if (isAutoScrollActive) {
        autoScroll();
    } else {
        clearTimeout(scrollInterval);
    }
    updateStatus();
}

function stopAutoScroll() {
    isAutoScrollActive = false;
    clearTimeout(scrollInterval);
    updateStatus();
}

function changeDirection() {
    direction = direction === 'down' ? 'up' : 'down';
    updateStatus();
}

function handleUserInteraction() {
    if (!userInteracted) {
        userInteracted = true;
        // Para o auto-scroll na primeira interação do usuário
        stopAutoScroll();
        
        // Remove os event listeners após a primeira interação
        ['mousedown', 'mousemove', 'keydown', 'touchstart', 'wheel'].forEach(event => {
            document.removeEventListener(event, handleUserInteraction);
        });
    }
}

// Inicialização
window.onload = () => {
    createControls();
    
    // Adiciona event listeners para detectar interação do usuário
    ['mousedown', 'mousemove', 'keydown', 'touchstart', 'wheel'].forEach(event => {
        document.addEventListener(event, handleUserInteraction, { passive: true });
    });

    // Inicia o auto-scroll após um delay
    setTimeout(() => {
        if (document.documentElement.scrollHeight > window.innerHeight * 1.2) {
            setTimeout(autoScroll, 2000);
        }
    }, 1000);
};

// Limpeza quando a página for fechada
window.addEventListener('beforeunload', () => {
    clearTimeout(scrollInterval);
});