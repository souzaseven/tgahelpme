// =============================================================
// registrar_online.js
// Marca operador como ONLINE após login (uso independente)
// -------------------------------------------------------------
// • Não interfere em outros arquivos
// • Pode ser incluído em qualquer página
// • Executa apenas quando há operador logado
// • Envia nome + equipe para registrar_online.php
// =============================================================

console.log("%c[registrar_online.js] carregado", "color:#4caf50;font-weight:bold;");

document.addEventListener("DOMContentLoaded", () => {

    // Obtém dados do operador via localStorage (salvos pelo login.js)
    const nome   = localStorage.getItem("operador_nome");
    const equipe = localStorage.getItem("operador_equipe");

    // Se não houver login, não faz nada
    if (!nome || !equipe) {
        console.warn("[registrar_online] Nenhum operador logado.");
        return;
    }

    console.log(`[registrar_online] Registrando ONLINE: ${nome} / ${equipe}`);

    // Envia presença ao backend
    fetch("php/registrar_online.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: 
            "nome="   + encodeURIComponent(nome) +
            "&equipe=" + encodeURIComponent(equipe)
    })
    .then(resp => resp.json())
    .then(data => {
        if (data.success) {
            console.log("[registrar_online] Operador marcado como ONLINE.");
        } else {
            console.error("[registrar_online] Erro:", data.error);
        }
    })
    .catch(err => {
        console.error("[registrar_online] Falha ao registrar presença:", err);
    });
});
