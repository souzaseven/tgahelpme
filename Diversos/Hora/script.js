 function updateTime() {
        const now = new Date();
        const hours = now.getHours().toString().padStart(2, '0');
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const seconds = now.getSeconds().toString().padStart(2, '0');
        const dayNames = ["Domingo", "Segunda", "Terça", "Quarta", "Quinta", "Sexta", "Sábado"];
        const monthNames = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];
        
        const dayName = dayNames[now.getDay()];
        const date = now.getDate();
        const monthName = monthNames[now.getMonth()];
        const year = now.getFullYear();
        
        const timeString = `${hours}:${minutes}:${seconds}`;
        const dateString = `${dayName}, ${date} de ${monthName} de ${year}`;
        
        document.getElementById('current-time').textContent = timeString;
        document.getElementById('current-date').textContent = dateString;
    }

    function getLocationAndTime() {
        // Substitua YOUR_API_KEY por sua chave de API da ipgeolocation.io
        fetch('https://api.ipgeolocation.io/ipgeo?apiKey=YOUR_API_KEY')
            .then(response => response.json())
            .then(data => {
                if (data.city && data.country_name) {
                    const locationDiv = document.getElementById('location');
                    locationDiv.textContent = `Localização: ${data.city}, ${data.country_name}`;
                    locationDiv.style.display = 'block';
                }
                updateTime();
                setInterval(updateTime, 1000); // Atualiza a hora a cada segundo
            })
            .catch(error => {
                console.error('Erro ao obter localização:', error);
                updateTime();
                setInterval(updateTime, 1000); // Continua atualizando a hora mesmo sem localização
            });
    }

    document.addEventListener('DOMContentLoaded', getLocationAndTime);