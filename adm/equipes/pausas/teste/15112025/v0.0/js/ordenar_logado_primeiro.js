// ============================================================
// ordenar_logado_primeiro.js
// Destaca e move o operador logado para o topo da lista
// ============================================================

console.log("%c[ordenar_logado_primeiro.js] ativo", "color:#00ff88;font-weight:bold;");

// Executa sempre após renderização dos operadores
document.addEventListener("ui:operadores-renderizados", destacarPrimeiro);
document.addEventListener("estado:atualizado", destacarPrimeiro);

function destacarPrimeiro() {
    const ctrl = window.controle;
    if (!ctrl || !ctrl.operador) return;

    const nomeLogado = ctrl.normalizar(ctrl.operador);

    // Procura itens da equipe onde o logado está
    document.querySelectorAll(".equipe-operadores").forEach((lista) => {
        const itens = [...lista.querySelectorAll(".op-item")];

        // Localiza item do próprio operador
        const meuItem = itens.find(
            (el) => ctrl.normalizar(el.querySelector("strong")?.textContent) === nomeLogado
        );

        if (!meuItem) return;

        // Aplica destaque visual
        meuItem.classList.add("operador-logado-destaque");

        // Move para o topo da equipe
        lista.prepend(meuItem);
    });
}
