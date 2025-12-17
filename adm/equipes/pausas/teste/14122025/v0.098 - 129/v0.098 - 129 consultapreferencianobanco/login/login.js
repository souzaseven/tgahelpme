/* ============================================================
   login.js — FASE 6 (VERSÃO OFICIAL)
   Login por equipe → escolha operador → definir_online → painel
   ------------------------------------------------------------
   - Não há Fase 7
   - Não há troca, fila, solicitações ou modal extra
   - Apenas fluxo simples de autenticação local
============================================================ */

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

    /* ============================================================
       1) BUSCAR EQUIPES
    ============================================================ */
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

    /* ============================================================
       2) RENDERIZAR EQUIPES
    ============================================================ */
    function renderizarEquipes() {

        elEquipes.innerHTML = "";

        equipesData.forEach((eq, index) => {

            const nomeEquipe = eq.nome_equipe || eq.equipe || `Equipe: ${eq.lider}`;
            const lider      = eq.lider || "Líder";

            const div = document.createElement("div");
            div.className = "card-equipe";
            div.innerHTML = `
                <strong>${nomeEquipe}</strong><br>
                <small><i class="fas fa-user-tie"></i> ${lider}</small>
            `;

            div.onclick = () => selecionarEquipe(index);

            elEquipes.appendChild(div);
        });
    }

    /* ============================================================
       3) SELECIONAR EQUIPE
    ============================================================ */
    function selecionarEquipe(indice) {
        indiceEquipeSel = indice;

        const equipe = equipesData[indice];
        const nomeEquipe = equipe.nome_equipe || equipe.equipe || equipe.lider;

        tituloEquipe.textContent = "Equipe: " + nomeEquipe;

        stepEquipes.classList.remove("ativo");
        stepOperadores.classList.add("ativo");

        renderizarOperadores(equipe);
    }

    /* ============================================================
       4) LISTAR OPERADORES
    ============================================================ */
    function renderizarOperadores(eq) {

        elOperadores.innerHTML = "";

        if (!eq.operadores || eq.operadores.length === 0) {
            elOperadores.innerHTML = "<p>Nenhum operador cadastrado.</p>";
            return;
        }

        eq.operadores.forEach(op => {
 const eLider = op.elider == 1;

            const card = document.createElement("div");
 card.className = "card-operador" + (eLider ? " lider" : "");

           // card.className = "card-operador";
           // card.textContent = op.nome;
   card.innerHTML = `
            ${eLider ? "👑 " : ""}${op.nome}
        `;

            card.onclick = () => selecionarOperador(op, card);

            elOperadores.appendChild(card);
        });
    }

    /* ============================================================
       5) SELECIONAR OPERADOR
    ============================================================ */
    function selecionarOperador(op, card) {

        operadorSel = op;

        document.querySelectorAll(".card-operador")
            .forEach(el => el.classList.remove("selecionado"));

        card.classList.add("selecionado");

        btnConfirmar.disabled = false;
        erroOperador.classList.add("oculto");
    }

    /* ============================================================
       6) BOTÃO VOLTAR PARA LISTA DE EQUIPES
    ============================================================ */
    btnVoltar.onclick = () => {

        stepOperadores.classList.remove("ativo");
        stepEquipes.classList.add("ativo");

        operadorSel = null;
        indiceEquipeSel = null;

        btnConfirmar.disabled = true;
    };

    /* ============================================================
       7) CONFIRMAR LOGIN
    ============================================================ */
    btnConfirmar.onclick = async () => {

    if (!operadorSel) {
        erroOperador.classList.remove("oculto");
        return;
    }

    const equipe = equipesData[indiceEquipeSel];
    const nomeEquipe = equipe.nome_equipe || equipe.equipe || equipe.lider;

    // ==================== NOVO BLOCO: Solicitar senha se for o primeiro acesso ====================
    if (!operadorSel.senha || operadorSel.senha.trim() === "") {
        let novaSenha = "";

        while (!novaSenha || novaSenha.trim().length < 4) {
            novaSenha = prompt("Bem-vindo! Defina uma senha (mínimo 4 caracteres):");

            if (novaSenha === null) {
                alert("Cadastro de senha obrigatório para continuar.");
                return; // Cancela o login se usuário desistir
            }

            if (novaSenha.trim().length < 4) {
                alert("A senha deve ter no mínimo 4 caracteres.");
            }
        }

        try {
            const resp = await fetch("../backend/salvar_senha.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    id: operadorSel.id,
                    senha: novaSenha.trim()
                })
            });

            const data = await resp.json();
            if (!data.success) {
                alert("Erro ao salvar a senha. Tente novamente.");
                return;
            }

            console.log("[LOGIN] Senha cadastrada com sucesso.");
        } catch (e) {
            alert("Erro na comunicação ao salvar a senha.");
            return;
        }
    }
    // ===============================================================================================

    const payload = {
        id: operadorSel.id,
        operador: operadorSel.nome,
        equipe: nomeEquipe,
        lider: equipe.lider || null,
        is_admin: operadorSel.is_admin ?? 0,
        elider: operadorSel.elider ?? 0,
        ultimoAcesso: new Date().toISOString()
    };

    localStorage.setItem("tga_operador", JSON.stringify(payload));

    console.log("[LOGIN] Enviando para definir_online.php:", {
        id: operadorSel.id
    });

    try {
        const resp = await fetch("../backend/definir_online.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({ id: operadorSel.id })
        });

        const json = await resp.json();
        console.log("[LOGIN] Resposta definir_online.php:", json);

    } catch (e) {
        console.error("[LOGIN] ERRO definir_online.php:", e);
    }

    window.location.href = "../painel/index.php";
};

});
