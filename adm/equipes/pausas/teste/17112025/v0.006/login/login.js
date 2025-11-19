// login.js - Lógica de login baseada em lider / operadores
// Usa o endpoint: ../backend/listar_equipes_login.php
// Estrutura esperada:
// {
//   success: true,
//   equipes: [
//     { lider: "Daniel Feix", operadores: ["Anderson de Souza", ...] },
//     ...
//   ]
// }

document.addEventListener("DOMContentLoaded", () => {

    const elEquipes       = document.getElementById("listaEquipes");
    const elOperadores    = document.getElementById("listaOperadores");

    const stepEquipes     = document.getElementById("stepEquipes");
    const stepOperadores  = document.getElementById("stepOperadores");

    const btnVoltar       = document.getElementById("btnVoltar");
    const btnConfirmar    = document.getElementById("btnConfirmar");
    const erroOperador    = document.getElementById("erroOperador");
    const tituloEquipe    = document.getElementById("tituloEquipe");

    let equipesData       = [];   // guarda o JSON de equipes vindo do backend
    let indiceEquipeSel   = null; // índice da equipe selecionada em equipesData
    let operadorSel       = null; // nome do operador selecionado

    // ---------------------------------------------------------------------
    // 1) Buscar equipes + operadores no backend
    // ---------------------------------------------------------------------
    fetch("../backend/listar_equipes_login.php")
        .then(r => r.json())
        .then(dados => {
            console.log("[LOGIN] Resposta listar_equipes_login.php:", dados);

            if (!dados || !dados.success || !Array.isArray(dados.equipes)) {
                elEquipes.innerHTML = "<p>Não foi possível carregar as equipes.</p>";
                return;
            }

            equipesData = dados.equipes;
            renderizarEquipes();
        })
        .catch(err => {
            console.error("[LOGIN] Erro ao buscar equipes:", err);
            elEquipes.innerHTML = "<p>Erro ao carregar equipes.</p>";
        });

    // ---------------------------------------------------------------------
    // 2) Renderizar lista de equipes (cada líder é uma equipe)
    // ---------------------------------------------------------------------
    function renderizarEquipes() {
        elEquipes.innerHTML = "";

        if (!equipesData.length) {
            elEquipes.innerHTML = "<p>Nenhuma equipe encontrada.</p>";
            return;
        }

        equipesData.forEach((eq, index) => {
            const div = document.createElement("div");
            div.className = "card-equipe";
            div.textContent = eq.lider;
            div.title = `Equipe de ${eq.lider}`;
            div.onclick = () => selecionarEquipe(index);
            elEquipes.appendChild(div);
        });
    }

    // ---------------------------------------------------------------------
    // 3) Quando clica numa equipe, vai para etapa de operadores
    // ---------------------------------------------------------------------
    function selecionarEquipe(indice) {
        indiceEquipeSel = indice;
        operadorSel = null;
        btnConfirmar.disabled = true;
        erroOperador.classList.add("oculto");

        const equipe = equipesData[indiceEquipeSel];
        tituloEquipe.textContent = `Equipe: ${equipe.lider}`;

        // Mostra step operadores
        stepEquipes.classList.remove("ativo");
        stepOperadores.classList.add("ativo");

        renderizarOperadores(equipe);
    }

    // ---------------------------------------------------------------------
    // 4) Renderizar operadores da equipe selecionada
    // ---------------------------------------------------------------------
    function renderizarOperadores(equipe) {
        elOperadores.innerHTML = "";

        if (!equipe.operadores || !equipe.operadores.length) {
            elOperadores.innerHTML = "<p>Nenhum operador cadastrado para essa equipe.</p>";
            return;
        }

        equipe.operadores.forEach(nomeOp => {
            const card = document.createElement("div");
            card.className = "card-operador";
            card.textContent = nomeOp;
            card.onclick = () => selecionarOperador(nomeOp, card);
            elOperadores.appendChild(card);
        });
    }

    // ---------------------------------------------------------------------
    // 5) Selecionar operador (marca visualmente + habilita confirmar)
    // ---------------------------------------------------------------------
    function selecionarOperador(nome, cardEl) {
        operadorSel = nome;

        // Remove seleção anterior
        document.querySelectorAll(".card-operador").forEach(el => {
            el.classList.remove("selecionado");
        });

        // Marca o atual
        cardEl.classList.add("selecionado");
        btnConfirmar.disabled = false;
        erroOperador.classList.add("oculto");
    }

    // ---------------------------------------------------------------------
    // 6) Botão Voltar → volta para lista de equipes
    // ---------------------------------------------------------------------
    btnVoltar.onclick = () => {
        stepOperadores.classList.remove("ativo");
        stepEquipes.classList.add("ativo");

        operadorSel = null;
        indiceEquipeSel = null;
        btnConfirmar.disabled = true;
        erroOperador.classList.add("oculto");
    };

    // ---------------------------------------------------------------------
    // 7) Confirmar → salvar em localStorage e ir para o painel
    // ---------------------------------------------------------------------
    btnConfirmar.onclick = () => {

        if (indiceEquipeSel === null || !operadorSel) {
            erroOperador.textContent = "Selecione um operador antes de continuar.";
            erroOperador.classList.remove("oculto");
            return;
        }

        const equipe = equipesData[indiceEquipeSel];

        const payload = {
            operador: operadorSel,
            equipe: equipe.lider,
            id: null, // por enquanto não temos ID numérico, só nome
            preferencias: {
                audio: true,
                notificacao: true,
                toast: true
            },
            ultimoAcesso: new Date().toISOString()
        };

        console.log("[LOGIN] Salvando tga_operador no localStorage:", payload);
        localStorage.setItem("tga_operador", JSON.stringify(payload));

        // Redirecionar para o painel
        window.location.href = "../index.php";
    };

});
