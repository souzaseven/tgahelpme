// bootstrap.js - Controle global da raiz

console.log("%c[GLOBAL] bootstrap.js carregado", "color:#0ff;font-weight:bold;");

// Verificar se já existe operador logado
const sessao = localStorage.getItem("tga_operador");

// Se está no painel mas não tem login, volta para o login
if (!window.location.pathname.includes("/login/")) {
    if (!sessao) {
        console.warn("[GLOBAL] Sessão não encontrada → mandando pro login");
        window.location.href = "./login/login.php";
    }
}
