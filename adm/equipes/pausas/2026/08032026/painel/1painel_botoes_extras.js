document.addEventListener("DOMContentLoaded", () => {
    const dadosOperador = JSON.parse(localStorage.getItem("tga_operador"));

    // 🔒 VERIFICAÇÃO ADM / LÍDER
    const eAdm = dadosOperador?.elider == 1 || dadosOperador?.is_admin == 1;
    if (!eAdm) {
        console.log("[EXTRAS] Usuário não é ADM, recursos desativados.");
        return;
    }

    function formatarIDENome(op) {
        return `[${op.id}] ${op.elider ? "👑 " : ""}${op.nome}`;
    }

    function inserirExtras(lista) {
        lista.forEach(op => {
            const linha = document.querySelector(`.linha-participante[data-id="${op.id}"]`);
            if (!linha) return;

            const nomeEl = linha.querySelector(".nome");
            if (nomeEl && !nomeEl.textContent.startsWith(`[${op.id}]`)) {
                nomeEl.textContent = formatarIDENome(op);
            }

            if (op.status !== "pausa" && !linha.querySelector(".botao-forcar-pausa")) {
                const botao = document.createElement("button");
                botao.className = "botao-forcar-pausa";
                botao.textContent = "⏸️ Forçar Pausa";

                botao.addEventListener("click", () => {
                    if (confirm(`Deseja FORÇAR a pausa de ${op.nome}?`)) {
                        forcarPausaLocal(op.id);
                    }
                });

                linha.appendChild(botao);
            }
        });
    }

    async function forcarPausaLocal(id) {
        try {
            mostrarEvoluxLoader?.("Atualizando pausa no banco...");

            const resp = await fetch("../backend/forcar_pausa_local.php", {
                method: "POST",
                body: new URLSearchParams({ id })
            });

            let json = null;
            try {
                json = await resp.json();
            } catch {
                const texto = await resp.text();
                console.error("[EXTRAS] Resposta inválida da API:", texto);
                alert("Erro inesperado ao forçar pausa local (resposta inválida).");
                return;
            }

            ocultarEvoluxLoader?.();

            if (json.success) {
                alert("Pausa forçada localmente com sucesso.");
                window.carregarPainel?.();
            } else {
                alert("Erro ao pausar localmente: " + json.erro);
            }

        } catch (e) {
            ocultarEvoluxLoader?.();
            console.error("[EXTRAS] Erro ao pausar local:", e);
            alert("Erro inesperado ao tentar pausar localmente.");
        }
    }

    setInterval(() => {
        if (window.__painel_ultimaEquipeCompleta) {
            inserirExtras(window.__painel_ultimaEquipeCompleta);
        }
    }, 3000);
});
