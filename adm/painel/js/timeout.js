// Timeout System - Auto Logout e Bloqueio de Tela
class TimeoutManager {
    constructor() {
/*
        // ⏰ TEMPOS ATUAIS CONFIGURADOS:
        this.timeoutDuration = 30 * 60 * 1000;    // 30 minutos = Tempo total de inatividade
        this.warningDuration = 2 * 60 * 1000;     // 2 minutos = Aviso antes do bloqueio  
        this.logoutDuration = 1 * 60 * 1000;      // 1 minuto = Tempo após bloqueio para logout automático
        */
// ⏰ TEMPOS ATUAIS CONFIGURADOS:
this.timeoutDuration = 10 * 60 * 1000;     // 1 minuto = Tempo total de inatividade
this.warningDuration = 5 * 60 * 1000;         // 30 segundos = Aviso antes do bloqueio  
this.logoutDuration = 6 * 60 * 1000;          // 30 segundos = Tempo após bloqueio para logout automático
        this.timer = null;
        this.warningTimer = null;
        this.logoutTimer = null;
        this.isWarningShown = false;
        this.isLocked = false;
        
        this.init();
    }

    init() {
        this.createTimeoutOverlay();
        this.setupEventListeners();
        this.resetTimer();
        
        console.log('🕒 Sistema de timeout inicializado');
        this.showCurrentSettings();
    }

    // ✅ MÉTODO PARA MOSTRAR CONFIGURAÇÕES ATUAIS
    showCurrentSettings() {
        console.log('⏰ Configurações atuais:');
        console.log(`   - Inatividade: ${this.timeoutDuration / 60000} minutos`);
        console.log(`   - Aviso prévio: ${this.warningDuration / 60000} minutos antes`);
        console.log(`   - Logout automático: ${this.logoutDuration / 60000} minutos após bloqueio`);
        
        // Também mostrar em alert para facilitar
        setTimeout(() => {
            alert(`⏰ Configurações atuais do timeout:\n\n` +
                  `Tempo total de inatividade: ${this.timeoutDuration / 60000} minutos\n` +
                  `Aviso prévio: ${this.warningDuration / 60000} minutos antes\n` +
                  `Logout automático: ${this.logoutDuration / 60000} minutos após bloqueio`);
        }, 1000);
    }

    // ✅ MÉTODO PARA TESTE RÁPIDO
    quickTest() {
        const oldTimeout = this.timeoutDuration;
        const oldWarning = this.warningDuration;
        const oldLogout = this.logoutDuration;
        
        this.timeoutDuration = 60 * 1000;      // 1 minuto
        this.warningDuration = 30 * 1000;      // 30 segundos
        this.logoutDuration = 20 * 1000;       // 20 segundos
        
        this.resetTimer();
        
        alert('🧪 Modo teste ativado:\n\n' +
              'Timeout: 1 minuto\n' +
              'Aviso: 30 segundos\n' +
              'Logout: 20 segundos após bloqueio\n\n' +
              '⚠️ Para voltar às configurações normais, recarregue a página.');
              
        console.log('🧪 Modo teste ativado - Tempos reduzidos para teste rápido');
        
        // Opcional: restaurar após 5 minutos
        setTimeout(() => {
            this.timeoutDuration = oldTimeout;
            this.warningDuration = oldWarning;
            this.logoutDuration = oldLogout;
            this.resetTimer();
            console.log('🔄 Configurações restauradas para valores normais');
        }, 5 * 60 * 1000);
    }

    createTimeoutOverlay() {
        // Overlay principal de bloqueio
        const overlay = document.createElement('div');
        overlay.className = 'timeout-overlay';
        overlay.id = 'timeoutOverlay';
        overlay.innerHTML = `
            <div class="timeout-container">
                <div class="timeout-icon">⏰</div>
                <h1 class="timeout-title">Sessão Expirada</h1>
                <p class="timeout-message">
                    Sua sessão foi bloqueada por inatividade. 
                    Para continuar usando o sistema, clique em "Continuar". 
                    Caso contrário, você será desconectado automaticamente.
                </p>
                <div class="timeout-timer" id="logoutTimer">
                    Logout automático em: <span id="countdown">60</span> segundos
                </div>
                <div class="timeout-buttons">
                    <button class="timeout-btn timeout-btn-continue" onclick="timeoutManager.continueSession()">
                        <span>🔓</span> Continuar Sessão
                    </button>
                    <button class="timeout-btn timeout-btn-logout" onclick="timeoutManager.logout()">
                        <span>🚪</span> Sair do Sistema
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);

        // Aviso de timeout próximo (AGORA NO CANTO DIREITO)
        const warning = document.createElement('div');
        warning.className = 'timeout-warning';
        warning.id = 'timeoutWarning';
        warning.innerHTML = `
            <div class="timeout-warning-content">
                <div class="timeout-warning-icon">⚠️</div>
                <div class="timeout-warning-text">
                    <div class="timeout-warning-title">Sessão Prestes a Expirar</div>
                    <div class="timeout-warning-message">
                        Sua sessão expirará em breve por inatividade.
                    </div>
                    <div class="timeout-warning-timer">
                        <span id="warningCountdown">120</span> segundos
                    </div>
                </div>
                <button class="timeout-warning-close" onclick="timeoutManager.hideWarning()">×</button>
            </div>
        `;
        document.body.appendChild(warning);
    }

    setupEventListeners() {
        // Eventos que resetam o timer
        const events = [
            'mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click',
            'input', 'focus', 'blur'
        ];

        events.forEach(event => {
            document.addEventListener(event, () => {
                if (!this.isLocked) {
                    this.resetTimer();
                }
            });
        });

        // Prevenir ações quando bloqueado
        document.addEventListener('keydown', (e) => {
            if (this.isLocked) {
                e.preventDefault();
                e.stopPropagation();
            }
        });

        document.addEventListener('click', (e) => {
            if (this.isLocked) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    }

    resetTimer() {
        // Limpar timers existentes
        clearTimeout(this.timer);
        clearTimeout(this.warningTimer);
        clearTimeout(this.logoutTimer);

        // Esconder avisos se estiverem visíveis
        this.hideWarning();

        // Se estava bloqueado, desbloquear
        if (this.isLocked) {
            this.unlockScreen();
        }

        // Configurar novo timer
        this.timer = setTimeout(() => {
            this.showWarning();
        }, this.timeoutDuration - this.warningDuration);

        console.log('🔄 Timer resetado - Próximo aviso em ' + ((this.timeoutDuration - this.warningDuration) / 60000) + ' minutos');
    }

    showWarning() {
        if (this.isWarningShown || this.isLocked) return;

        this.isWarningShown = true;
        const warning = document.getElementById('timeoutWarning');
        const countdownElement = document.getElementById('warningCountdown');
        
        let countdown = this.warningDuration / 1000;
        
        // Atualizar contagem regressiva
        const countdownInterval = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(countdownInterval);
                this.lockScreen();
            }
        }, 1000);

        // Mostrar aviso
        warning.style.display = 'block';

        // Configurar timer para bloqueio automático
        this.warningTimer = setTimeout(() => {
            this.lockScreen();
        }, this.warningDuration);

        console.log('⚠️ Aviso de timeout mostrado - Bloqueio em ' + (this.warningDuration / 60000) + ' minutos');
    }

    hideWarning() {
        if (!this.isWarningShown) return;

        this.isWarningShown = false;
        const warning = document.getElementById('timeoutWarning');
        warning.style.display = 'none';

        clearTimeout(this.warningTimer);
        console.log('❌ Aviso de timeout escondido');
    }

    lockScreen() {
        this.isLocked = true;
        this.isWarningShown = false;

        const overlay = document.getElementById('timeoutOverlay');
        const warning = document.getElementById('timeoutWarning');
        const countdownElement = document.getElementById('countdown');
        
        // Esconder aviso se estiver visível
        warning.style.display = 'none';

        // Mostrar overlay de bloqueio
        overlay.style.display = 'block';

        let countdown = this.logoutDuration / 1000;
        
        // Configurar contagem regressiva para logout
        const countdownInterval = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(countdownInterval);
                this.logout();
            }
        }, 1000);

        // Configurar timer para logout automático
        this.logoutTimer = setTimeout(() => {
            this.logout();
        }, this.logoutDuration);

        console.log('🔒 Tela bloqueada - Logout em ' + (this.logoutDuration / 60000) + ' minutos');
    }

    unlockScreen() {
        this.isLocked = false;
        this.isWarningShown = false;

        const overlay = document.getElementById('timeoutOverlay');
        overlay.style.display = 'none';

        clearTimeout(this.logoutTimer);
        console.log('🔓 Tela desbloqueada');
    }

    continueSession() {
        this.unlockScreen();
        this.resetTimer();
        
        // Mostrar mensagem de sucesso
        if (typeof showAlert !== 'undefined') {
            showAlert('✅ Sessão renovada com sucesso!', 'success');
        } else {
            alert('✅ Sessão renovada com sucesso!');
        }
        
        console.log('🔄 Sessão continuada');
    }

    logout() {
        // Limpar todos os timers
        clearTimeout(this.timer);
        clearTimeout(this.warningTimer);
        clearTimeout(this.logoutTimer);

        // Redirecionar para logout
        if (confirm('Sua sessão expirou por inatividade. Deseja fazer login novamente?')) {
            window.location.href = 'logout.php';
        } else {
            this.continueSession();
        }
    }

    // Métodos públicos para configuração
    setTimeout(minutes) {
        this.timeoutDuration = minutes * 60 * 1000;
        this.resetTimer();
        console.log(`⏰ Timeout configurado para ${minutes} minutos`);
        this.showCurrentSettings();
    }

    setWarning(minutes) {
        this.warningDuration = minutes * 60 * 1000;
        console.log(`⚠️ Aviso configurado para ${minutes} minutos antes`);
        this.showCurrentSettings();
    }

    setLogout(minutes) {
        this.logoutDuration = minutes * 60 * 1000;
        console.log(`🚪 Logout automático configurado para ${minutes} minutos após bloqueio`);
        this.showCurrentSettings();
    }

    // Forçar logout manual
    forceLogout() {
        this.logout();
    }

    // Forçar bloqueio manual (para testes)
    forceLock() {
        this.lockScreen();
    }
}

// Inicializar o gerenciador de timeout
const timeoutManager = new TimeoutManager();

// Adicionar botão manual de logout no header
function addManualLogoutButton() {
    const header = document.querySelector('.header-content');
    if (header) {
        const logoutBtn = document.createElement('button');
        logoutBtn.className = 'btn btn-danger btn-sm';
        logoutBtn.innerHTML = '🚪 Sair';
        logoutBtn.title = 'Sair do sistema';
        logoutBtn.onclick = () => timeoutManager.forceLogout();
        
        // Adicionar ao final do header
        header.appendChild(logoutBtn);
    }
}

// Adicionar botão quando o DOM carregar
document.addEventListener('DOMContentLoaded', function() {
    addManualLogoutButton();
});

// Exportar para uso global
window.timeoutManager = timeoutManager;

// Comandos úteis para teste no console:
console.log(`
🎮 Comandos disponíveis para teste:

// Ver configurações atuais
timeoutManager.showCurrentSettings()

// Teste rápido (reduz tempos)
timeoutManager.quickTest()

// Forçar bloqueio
timeoutManager.forceLock()

// Configurar tempos personalizados (em minutos)
timeoutManager.setTimeout(5)      // 5 minutos de inatividade
timeoutManager.setWarning(1)      // 1 minuto de aviso prévio
timeoutManager.setLogout(1)       // 1 minuto para logout automático
`);