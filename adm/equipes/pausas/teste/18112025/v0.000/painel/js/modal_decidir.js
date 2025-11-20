/*==============================
  modal_decidir.js
================================ */

console.log("%c[MODAL DECIDIR] carregado", "color:#7dd3fc");

function abrirModalDecidir() {
    const modal = document.getElementById("modalDecidir");
    if (!modal) {
        console.warn("[MODAL DECIDIR] #modalDecidir não encontrado.");
        return;
    }

    // Garante que filaCache e OPERADOR_ID existam
    if (!Array.isArray(filaCache)) {
        console.warn("[MODAL DECIDIR] filaCache não está disponível.");
        return;
    }

    const souPrimeiro = filaCache.length > 0 && String(filaCache[0].operador_id) === String(OPERADOR_ID);

    const opcoesPrimeiro = document.getElementById("opcoesPrimeiro");
    const opcoesOutros   = document.getElementById("opcoesOutros");

    if (!opcoesPrimeiro || !opcoesOutros) {
        console.warn("[MODAL DECIDIR] Elementos de opções não encontrados.");
        return;
    }

    // Reset de visibilidade
    opcoesPrimeiro.classList.add("hidden");
    opcoesOutros.classList.add("hidden");

    if (souPrimeiro) {
        opcoesPrimeiro.classList.remove("hidden");
    } else {
        opcoesOutros.classList.remove("hidden");
    }

    modal.classList.remove("hidden");
}

function fecharModalDecidir() {
    const modal = document.getElementById("modalDecidir");
    if (!modal) return;
    modal.classList.add("hidden");
}

// Clique no botão "Decidir"
document.addEventListener("click", (e) => {
    if (e.target.closest("#btnDecidir")) {
        abrirModalDecidir();
    }
});

// Fechar clicando fora do conteúdo do modal
document.addEventListener("click", (e) => {
    const modal = document.getElementById("modalDecidir");
    if (!modal || modal.classList.contains("hidden")) return;

    if (e.target === modal) {
        fecharModalDecidir();
    }
});

// Fechar com ESC
document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
        fecharModalDecidir();
    }
});

/* ==========================================
   AÇÕES DO MODAL "DECIDIR"
   (stubs prontos para ligar no backend)
========================================== */

function decidirIrParaSegundo() {
    console.log("[DECIDIR] Ir para segundo da fila (TODO: implementar backend).");

    // Exemplo de chamada futura:
    // enviarAcaoDecisaoFila("ir_segundo");

    alert("Ação: Ir para o segundo da fila.\n(Backend ainda não implementado.)");
    fecharModalDecidir();
}

function decidirIrParaFinal() {
    console.log("[DECIDIR] Ir para final da fila (TODO: implementar backend).");

    // Exemplo de chamada futura:
    // enviarAcaoDecisaoFila("ir_final");

    alert("Ação: Ir para o final da fila.\n(Backend ainda não implementado.)");
    fecharModalDecidir();
}

function decidirTrocarPosicao() {
    console.log("[DECIDIR] Trocar posição com outro operador (TODO: implementar UI de escolha).");

    // Aqui no futuro você abre outro modal para escolher com quem trocar

    alert("Ação: Trocar posição com outro operador.\n(Tela de escolha ainda não implementada.)");
    fecharModalDecidir();
}
