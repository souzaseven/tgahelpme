// login.js - Login baseado em líderes + operadores (com ID)
// Usa: ../backend/listar_equipes_login.php

document.addEventListener("DOMContentLoaded", () => {

    const elEquipes       = document.getElementById("listaEquipes");
    const elOperadores    = document.getElementById("listaOperadores");

    const stepEquipes     = document.getElementById("stepEquipes");
    const stepOperadores  = document.getElementById("stepOperadores");

    const btnVoltar       = document.getElementById("btnVoltar");
    const btnConfirmar    = document.getElementById("btnConfirmar");
    const erroOperador    = document.getElementById("erroOperador");
    const tituloEquipe    = document.getElementById("tituloEquipe");

    let equipesData       = [];
    let indiceEquipeSel   = null;
    let operadorSel       = null;

    // ===============================================================
    // 1) Buscar equipes no backend
    // ===============================================================
    fetch("../backend/listar_equipes_login.php")
        .then(r => r.json())
        .then(dados => {
            console.log("[LOGIN] Resposta:", dados);

            if (!dados || !dados.success || !Array.isArray(dados.equipes)) {
                elEquipes.innerHTML = "<p>Não foi possível carregar as equipes.</p>";
                return;
            }

            equipesData = dados.equipes;
            renderizarEquipes();
        })
        .catch(err => {
            console.error("[LOGIN] Erro:", err);
            elEquipes.innerHTML = "<p>Erro ao carregar equipes.</p>";
        });

    // ===============================================================
    // 2) Exibir equipes (cada líder é uma equipe)
    // ===============================================================
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

    // ===============================================================
    // 3) Selecionar equipe
    // ===============================================================
    function selecionarEquipe(indice) {
        indiceEquipeSel = indice;
        operadorSel = null;
        btnConfirmar.disabled = true;
        erroOperador.classList.add("oculto");

        const equipe = equipesData[indiceEquipeSel];
        tituloEquipe.textContent = `Equipe: ${equipe.lider}`;

        stepEquipes.classList.remove("ativo");
        stepOperadores.classList.add("ativo");

        renderizarOperadores(equipe);
    }

    // ===============================================================
    // 4) Exibir operadores da equipe
    // ===============================================================
    function renderizarOperadores(equipe) {
        elOperadores.innerHTML = "";

        if (!equipe.operadores || !equipe.operadores.length) {
            elOperadores.innerHTML = "<p>Nenhum operador cadastrado para essa equipe.</p>";
            return;
        }

        equipe.operadores.forEach(op => {
            const card = document.createElement("div");
            card.className = "card-operador";
            card.textContent = op.nome;
            card.onclick = () => selecionarOperador(op, card);
            elOperadores.appendChild(card);
        });
    }

    // ===============================================================
    // 5) Selecionar operador
    // ===============================================================
    function selecionarOperador(op, cardEl) {
        operadorSel = op; // agora é {id, nome}

        document.querySelectorAll(".card-operador")
            .forEach(el => el.classList.remove("selecionado"));

        cardEl.classList.add("selecionado");
        btnConfirmar.disabled = false;
        erroOperador.classList.add("oculto");
    }

    // ===============================================================
    // 6) Voltar
    // ===============================================================
    btnVoltar.onclick = () => {
        stepOperadores.classList.remove("ativo");
        stepEquipes.classList.add("ativo");

        operadorSel = null;
        indiceEquipeSel = null;
        btnConfirmar.disabled = true;
        erroOperador.classList.add("oculto");
    };

    // ===============================================================
    // 7) Confirmar login
    // ===============================================================
    btnConfirmar.onclick = () => {

        if (indiceEquipeSel === null || !operadorSel) {
            erroOperador.textContent = "Selecione um operador antes de continuar.";
            erroOperador.classList.remove("oculto");
            return;
        }

        const equipe = equipesData[indiceEquipeSel];

        const payload = {
            id: operadorSel.id,
            operador: operadorSel.nome,
            equipe: equipe.lider,
            ultimoAcesso: new Date().toISOString()
        };

        console.log("[LOGIN] Salvando tga_operador:", payload);

        localStorage.setItem("tga_operador", JSON.stringify(payload));

        window.location.href = "../index.php";
    };

});
