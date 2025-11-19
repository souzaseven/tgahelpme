// bootstrap_login.js
(function () {

    const dados = localStorage.getItem("tga_operador");

    // Se já está logado → ir para o painel
    if (dados) {
        window.location.href = "../index.php";
        return;
    }

    // Senão, permanece na tela de login
})();
