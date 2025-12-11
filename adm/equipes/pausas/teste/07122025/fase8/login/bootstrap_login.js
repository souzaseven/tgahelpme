// bootstrap_login.js

(function () {

    const dados = localStorage.getItem("tga_operador");

    // Se já existe operador logado → vai direto para o painel
    if (dados) {
        window.location.href = "../painel/index.php";
        return;
    }

    // Caso contrário, carrega tela de login normalmente
})();
