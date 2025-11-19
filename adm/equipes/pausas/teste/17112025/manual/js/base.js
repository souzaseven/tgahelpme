// Simulação de funcionalidades interativas
document.addEventListener('DOMContentLoaded', function() {
    // Alternar estado do botão de áudio
    const audioBtn = document.getElementById('audio-btn');
    audioBtn.addEventListener('click', function() {
        this.classList.toggle('active');
        const icon = this.querySelector('i');
        if (this.classList.contains('active')) {
            icon.className = 'fas fa-volume-mute';
        } else {
            icon.className = 'fas fa-volume-up';
        }
    });

    // Alternar estado do botão de notificações
    const notificationBtn = document.getElementById('notification-btn');
    notificationBtn.addEventListener('click', function() {
        this.classList.toggle('active');
        const badge = this.querySelector('.notification-badge');
        if (this.classList.contains('active')) {
            badge.textContent = '0';
            badge.style.display = 'none';
        } else {
            badge.textContent = '3';
            badge.style.display = 'flex';
        }
    });

    // Alternar sidebar
    const toggleSidebar = document.getElementById('toggle-sidebar');
    const mainContainer = document.getElementById('main-container');
    
    toggleSidebar.addEventListener('click', function() {
        mainContainer.classList.toggle('sidebar-collapsed');
        const icon = this.querySelector('i');
        if (mainContainer.classList.contains('sidebar-collapsed')) {
            icon.className = 'fas fa-chevron-right';
        } else {
            icon.className = 'fas fa-chevron-left';
        }
    });

    // Simular atualização de status em tempo real
    setInterval(() => {
        const statusElements = document.querySelectorAll('.item-status');
        statusElements.forEach(el => {
            if (el.classList.contains('status-paused') || el.classList.contains('status-waiting')) {
                const currentTime = parseInt(el.textContent);
                el.textContent = (currentTime + 1) + ' min';
            }
        });
    }, 60000); // Atualiza a cada minuto
});