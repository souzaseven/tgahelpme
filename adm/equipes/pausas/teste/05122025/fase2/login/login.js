// login.js - Sistema de Login por Equipe e Operador

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

    elEquipes.innerHTML = "<p>Carregando equipes...</p>";

    // =========================================================
    // 1) BUSCAR EQUIPES
    // =========================================================
    fetch("../backend/listar_equipes_login.php")
        .then(r => r.json())
        .then(d => {
            if (!d.success || !Array.isArray(d.equipes)) {
                elEquipes.innerHTML = "<p>Falha ao carregar equipes.</p>";
                return;
            }
            equipesData = d.equipes;
            renderizarEquipes();
        })
        .catch(() => {
            elEquipes.innerHTML = "<p>Erro ao carregar equipes.</p>";
        });

    // =========================================================
    // 2) RENDERIZAR EQUIPES
    // =========================================================
    function renderizarEquipes() {
        elEquipes.innerHTML = "";

        equipesData.forEach((eq, index) => {

            const nomeEquipe = eq.nome_equipe || eq.equipe || eq.lider;
            const lider      = eq.lider || nomeEquipe;

            const card = document.createElement("div");
            card.className = "card-equipe";

            // Evitar nome duplicado
            let html = `<strong>${nomeEquipe}</strong>`;
            if (nomeEquipe !== lider) {
                html += `<br><small><i class="fas fa-user-tie"></i> ${lider}</small>`;
            }

            card.innerHTML = html;
            card.onclick = () => selecionarEquipe(index);

            elEquipes.appendChild(card);
        });
    }

    // =========================================================
    // 3) SELECIONAR EQUIPE
    // =========================================================
    function selecionarEquipe(indice) {
        indiceEquipeSel = indice;

        const equipe = equipesData[indice];
        const nomeEquipe = equipe.nome_equipe || equipe.equipe || equipe.lider;

        tituloEquipe.textContent = "Equipe: " + nomeEquipe;

        stepEquipes.classList.remove("ativo");
        stepOperadores.classList.add("ativo");

        renderizarOperadores(equipe);
    }

    // =========================================================
    // 4) RENDERIZAR OPERADORES
    // =========================================================
    function renderizarOperadores(eq) {
        elOperadores.innerHTML = "";

        if (!eq.operadores || !eq.operadores.length) {
            elOperadores.innerHTML = "<p>Nenhum operador cadastrado.</p>";
            return;
        }

        eq.operadores.forEach(op => {
            const card = document.createElement("div");
            card.className = "card-operador";
            card.textContent = op.nome;
            card.onclick = () => selecionarOperador(op, card);
            elOperadores.appendChild(card);
        });
    }

    // =========================================================
    // 5) SELECIONAR OPERADOR
    // =========================================================
    function selecionarOperador(op, card) {
        operadorSel = op;

        document.querySelectorAll(".card-operador")
            .forEach(el => el.classList.remove("selecionado"));

        card.classList.add("selecionado");

        btnConfirmar.disabled = false;
        erroOperador.classList.add("oculto");
    }

    // =========================================================
    // 6) BOTÃO VOLTAR
    // =========================================================
    btnVoltar.onclick = () => {
        stepOperadores.classList.remove("ativo");
        stepEquipes.classList.add("ativo");

        operadorSel = null;
        indiceEquipeSel = null;
        btnConfirmar.disabled = true;
    };

    // =========================================================
    // 7) CONFIRMAR LOGIN + DEFINIR STATUS ONLINE
    // =========================================================
    btnConfirmar.onclick = () => {

        if (!operadorSel) {
            erroOperador.classList.remove("oculto");
            return;
        }

        const equipe = equipesData[indiceEquipeSel];
        const nomeEquipe = equipe.nome_equipe || equipe.equipe || equipe.lider;

        const payload = {
            id: operadorSel.id,
            operador: operadorSel.nome,
            equipe: nomeEquipe,
            lider: equipe.lider || null,
            ultimoAcesso: new Date().toISOString()
        };

        localStorage.setItem("tga_operador", JSON.stringify(payload));

        // === DEFINIR STATUS ONLINE AO LOGAR ===
        fetch("../backend/definir_online.php", {
            method: "POST",
            body: new URLSearchParams({
                nome: operadorSel.nome,
                equipe: nomeEquipe
            })
        });

        window.location.href = "../painel/index.php";
    };
});
