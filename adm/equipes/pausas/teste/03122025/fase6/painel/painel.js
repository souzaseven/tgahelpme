// ============================================================
// painel.js - FASE 6 FINAL do Controle de Pausas
// ============================================================
// - Status: ativo / espera / pausa
// - Atualização automática do painel
// - Lista de pausa, fila e equipe
// - Integra operador.js
// ============================================================

document.addEventListener("DOMContentLoaded", () => {

    // ============================================================
    // 1) Carregar operador logado
    // ============================================================
    const dadosOperador = JSON.parse(localStorage.getItem("tga_operador"));
    if (!dadosOperador) {
        window.location.href = "../login/login.php";
        return;
    }

    // 🔥 CORREÇÃO DO ERRO “undefined”
    const nomeOperador = dadosOperador.operador ?? dadosOperador.nome ?? "Operador";

    // Mostrar nome no topo
    const boxOperador = document.getElementById("boxOperadorLogado");
    boxOperador.innerHTML = `
        <div class="op-logado">
            <i class="fas fa-user"></i> ${nomeOperador}
        </div>
    `;

    // ============================================================
    // 2) FUNÇÃO PRINCIPAL — CARREGAR PAINEL
    // ============================================================
    async function carregarPainel() {

        try {
            const resp = await fetch("../backend/obter_status_equipe.php", {
                method: "POST",
                body: new URLSearchParams({ equipe: dadosOperador.equipe })
            });

            const dados = await resp.json();

            if (!dados.success) {
                console.warn("[PAINEL] Resposta sem sucesso:", dados);
                return;
            }

            const { pausa, fila, equipe_completa } = dados;

            atualizarListaPausa(pausa);
            atualizarFila(fila);
            atualizarEquipeCompleta(equipe_completa);

        } catch (e) {
            console.error("[ERRO carregarPainel]", e);
        }
    }

    // ============================================================
    // 3) LISTA DE OPERADORES EM PAUSA
    // ============================================================
    function atualizarListaPausa(lista) {
        const box = document.getElementById("listaPausa");

        if (!lista || lista.length === 0) {
            box.innerHTML = `<p class="lista-vazia">Nenhum operador está em pausa.</p>`;
            return;
        }

        box.innerHTML = lista.map(op => `
            <div class="linha-participante" data-nome="${op.nome}">
                <span class="nome">${op.nome}</span>
                <span class="bolinha-estado pausa"></span>
            </div>
        `).join("");
    }

    // ============================================================
    // 4) LISTA DA FILA DE ESPERA
    // ============================================================
    function atualizarFila(lista) {
        const box = document.getElementById("listaFila");

        if (!lista || lista.length === 0) {
            box.innerHTML = `<p class="lista-vazia">Nenhum operador na fila.</p>`;
            return;
        }

        box.innerHTML = lista.map(op => `
            <div class="linha-participante" data-nome="${op.nome}">
                <span class="nome">${op.nome}</span>
                <span class="bolinha-estado espera"></span>
            </div>
        `).join("");
    }

    // ============================================================
    // 5) LISTA DA EQUIPE COMPLETA
    // ============================================================
    function atualizarEquipeCompleta(lista) {
        const box = document.getElementById("listaEquipeCompleta");

        if (!lista || lista.length === 0) {
            box.innerHTML = `<p class="lista-vazia">Nenhum operador encontrado.</p>`;
            return;
        }

        box.innerHTML = lista.map(op => `
            <div class="linha-participante" data-nome="${op.nome}">
                <span class="nome">${op.nome}</span>
                <span class="bolinha-estado ${op.status}"></span>
            </div>
        `).join("");

        // botões individuais (Fase 6)
        inserirBotoesIndividuais(lista);
    }

    // ============================================================
    // 6) AÇÕES GLOBAIS (Fase 6)
    // ============================================================
    window.iniciarPausa = async id => {
        await fetch("../backend/iniciar_pausa.php", {
            method: "POST",
            body: new URLSearchParams({ id })
        });
        setTimeout(carregarPainel, 200);
    };

    window.sairPausa = async id => {
        await fetch("../backend/sair_pausa.php", {
            method: "POST",
            body: new URLSearchParams({ id })
        });
        setTimeout(carregarPainel, 200);
    };

    // ============================================================
    // 7) Atualização automática (8s)
    // ============================================================
    carregarPainel();
    setInterval(carregarPainel, 8000);

});
