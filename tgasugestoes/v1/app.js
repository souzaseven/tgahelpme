// Função para alternar entre modo claro e escuro
const themeToggle = document.getElementById("theme-toggle");
themeToggle.addEventListener("click", () => {
    document.body.classList.toggle("dark-mode");
    themeToggle.textContent = document.body.classList.contains("dark-mode") ? "🌞" : "🌙";
});

// Função para mostrar o Toast de sucesso
function showToast() {
    const toast = document.getElementById('toast');
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

// Exemplo de chamada para mostrar o toast de sucesso
// Isso seria chamado após uma ação, como uma edição bem-sucedida
showToast();

// Carregar sugestões usando AJAX (Exemplo)
document.addEventListener('DOMContentLoaded', function () {
    fetch('consultasugestao.php')
        .then(response => response.text())
        .then(data => {
            document.getElementById('suggestions-table').innerHTML = data;
        });
});
