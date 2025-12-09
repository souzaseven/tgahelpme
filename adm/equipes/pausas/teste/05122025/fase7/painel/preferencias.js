// ============================================================
// preferencias.js — Fase 8 (localStorage + banco, seguro)
// ============================================================

document.addEventListener("DOMContentLoaded", () => {

    // Dados do operador logado (se existir)
    const dadosOperador = JSON.parse(localStorage.getItem("tga_operador") || "null");

    const elSom   = document.getElementById("prefSom");
    const elAviso = document.getElementById("prefAvisoPausa");
    const elTema  = document.getElementById("prefTema");

    // Se a página NÃO tem campos de preferências, apenas sai
    if (!elSom && !elAviso && !elTema) {
        console.warn("[PREFERENCIAS] Nenhum campo de preferências encontrado na página.");
        return;
    }

    // -----------------------------
    // 1) Carregar valores locais
    // -----------------------------
    const prefSomLS   = localStorage.getItem("pref_som");           // "on" / "off"
    const prefAvisoLS = localStorage.getItem("pref_aviso_pausa");   // "on" / "off"
    const prefTemaLS  = localStorage.getItem("pref_tema");          // "dark" / "light" ...

    if (elSom)   elSom.value   = prefSomLS   ?? "on";
    if (elAviso) elAviso.value = prefAvisoLS ?? "on";
    if (elTema)  elTema.value  = prefTemaLS  ?? "dark";

    // Se tiver vindo do banco (via tga_operador.pref_som), sincroniza
    if (dadosOperador && typeof dadosOperador.pref_som !== "undefined" && elSom) {
        elSom.value = dadosOperador.pref_som == 1 ? "on" : "off";
    }

    // -----------------------------
    // 2) Botão SALVAR
    // -----------------------------
    const btnSalvar = document.querySelector(".btn-salvar, .btn-salvar-pref");
    if (btnSalvar) {
        btnSalvar.addEventListener("click", async () => {

            // Valores atuais da tela
            const valorSom     = elSom   ? elSom.value   : "on";
            const valorAviso   = elAviso ? elAviso.value : "on";
            const valorTema    = elTema  ? elTema.value  : "dark";
            const prefSomInt   = valorSom === "on" ? 1 : 0;

            // 2.1) Salvar no localStorage
            localStorage.setItem("pref_som", valorSom);
            localStorage.setItem("pref_aviso_pausa", valorAviso);
            localStorage.setItem("pref_tema", valorTema);

            // 2.2) Se tiver operador logado, salva também no backend
            if (dadosOperador && dadosOperador.id) {
                try {
                    const resp = await fetch("../backend/salvar_preferencias.php", {
                        method: "POST",
                        body: new URLSearchParams({
                            id: dadosOperador.id,
                            pref_som: prefSomInt
                            // se depois quiser mandar pref_audio/pref_notificacao,
                            // é só incluir aqui.
                        })
                    });

                    const r = await resp.json();

                    if (!r.success) {
                        alert("Erro ao salvar no servidor: " + (r.erro || "desconhecido"));
                    } else {
                        // Atualiza o objeto tga_operador
                        dadosOperador.pref_som = prefSomInt;
                        localStorage.setItem("tga_operador", JSON.stringify(dadosOperador));
                        alert("Preferências salvas com sucesso!");
                    }

                } catch (e) {
                    console.error("[PREFERENCIAS] Erro ao salvar no servidor:", e);
                    alert("Erro ao comunicar com o servidor ao salvar preferências.");
                }

            } else {
                // Cenário sem operador logado (ex.: teste isolado)
                alert("Preferências salvas localmente!");
            }
        });
    }

    // -----------------------------
    // 3) Botão TESTAR SOM
    // -----------------------------
    const btnTestar = document.querySelector(".btn-testar, .btn-testar-som, #btnTestarSom");
    if (btnTestar) {
        btnTestar.addEventListener("click", () => {
            const audio = new Audio("../sons/notificacao.mp3");
            audio.play().catch(() => alert("Arquivo de som não encontrado."));
        });
    }
});

// ============================================================
// VOLTAR PARA O PAINEL
// ============================================================
window.voltarPainel = function () {
    window.location.href = "index.php";
};
