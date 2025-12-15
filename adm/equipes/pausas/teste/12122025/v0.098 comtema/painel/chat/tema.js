document.addEventListener("DOMContentLoaded", () => {
    const op = getOperador();
    if (!op) return;

    const temaModal = document.getElementById("chatTemaModal");
    const salvarBtn = document.getElementById("salvarTemaBtn");
    const radios = document.querySelectorAll('input[name="tema"]');

    document.getElementById("chat-config-btn")?.addEventListener("click", () => {
        temaModal.classList.remove("hidden");
    });

    temaModal?.addEventListener("click", (e) => {
        if (e.target === temaModal) {
            temaModal.classList.add("hidden");
        }
    });

    salvarBtn?.addEventListener("click", async () => {
        const temaSelecionado = [...radios].find(r => r.checked)?.value;
        if (!temaSelecionado) return;

        await fetch("chat/set_tema.php", {
            method: "POST",
            body: new URLSearchParams({
                operador_id: op.id,
                tema: temaSelecionado
            })
        });

        aplicarTema(temaSelecionado);
        temaModal.classList.add("hidden");
    });

    carregarTemaAtual(op.id);
});

function aplicarTema(tema) {
    document.body.classList.remove("tema-claro", "tema-escuro");
    document.body.classList.add(`tema-${tema}`);
}

async function carregarTemaAtual(operador_id) {
    const resp = await fetch("chat/get_tema.php?operador_id=" + operador_id);
    const data = await resp.json();
    const tema = data.tema || "claro";

    aplicarTema(tema);
    const radio = document.querySelector(`input[name="tema"][value="${tema}"]`);
    if (radio) radio.checked = true;
}
