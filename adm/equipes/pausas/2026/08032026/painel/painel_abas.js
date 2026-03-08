// ============================================================
// painel_abas.js – Abas internas de equipes (FINAL CORRIGIDO)
// ============================================================

const dadosOperador = JSON.parse(localStorage.getItem("tga_operador") || "{}");

// ============================================================
// Exibir botão "Equipes" se permitido
// ============================================================
document.addEventListener("DOMContentLoaded", () => {
    const btnEquipes = document.getElementById("btnEquipesAbas");
    if (btnEquipes && (dadosOperador?.is_admin === 1 || dadosOperador?.logatodasequipes === 1)) {
        btnEquipes.classList.remove("hidden");
        console.log("[PAINEL_ABAS] Botão Equipes habilitado");
    }
});

// ============================================================
// Modo Abas
// ============================================================
function entrarModoAbas() {
    console.log("[PAINEL_ABAS] Entrando no modo abas");

    document.getElementById("painelPrincipal")?.classList.add("hidden");
    document.getElementById("conteudoExtra")?.classList.add("hidden");

    const abas = document.getElementById("abasEquipes");
    const painel = document.getElementById("painelEquipesInternas");

    abas?.classList.remove("hidden");
    painel?.classList.remove("hidden");

    abas.style.display = "flex";
    painel.style.display = "block";
    painel.style.visibility = "visible";
}

function sairModoAbas() {
    console.log("[PAINEL_ABAS] Saindo do modo abas");

    document.getElementById("painelPrincipal")?.classList.remove("hidden");
    document.getElementById("abasEquipes")?.classList.add("hidden");
    document.getElementById("painelEquipesInternas")?.classList.add("hidden");
}

// ============================================================
// Seleção de Equipes
// ============================================================
window.abrirSelecaoEquipes = async function () {
    sairModoAbas();

    const conteudo = document.getElementById("conteudoExtra");
    if (!conteudo) return;

    conteudo.classList.remove("hidden");

    document.querySelector(".painel-topo")?.classList.add("hidden");
    document.querySelector(".painel-dashboard")?.classList.add("hidden");
    document.querySelector(".card-participantes")?.classList.add("hidden");

    conteudo.innerHTML = `
        <div style="text-align:center;padding:20px;">
            <i class="fa-solid fa-spinner fa-spin" style="font-size:26px;color:#38bdf8;"></i>
            <p style="color:#ccc">Carregando equipes disponíveis...</p>
        </div>
    `;

    try {
        const resp = await fetch("../backend/listar_equipes_disponiveis.php");
        const r = await resp.json();

        if (!r.success || !r.equipes?.length) {
            conteudo.innerHTML = `<p style="color:red">Nenhuma equipe disponível.</p>`;
            return;
        }

        conteudo.innerHTML = `
            <h2><i class="fa-solid fa-layer-group"></i> Escolha uma equipe</h2>
            <div class="todas-equipes-grid">
                ${r.equipes.map(eq => `
                    <div class="card-equipe" onclick="abrirGuiaEquipe('${eq}')">
                        <i class="fa-solid fa-users"></i> ${eq}
                    </div>
                `).join("")}
            </div>
        `;
    } catch (e) {
        console.error("[PAINEL_ABAS] Erro ao carregar equipes:", e);
        conteudo.innerHTML = `<p style="color:red">Falha ao comunicar com o servidor.</p>`;
    }
};

// ============================================================
// Abrir Aba da Equipe
// ============================================================
window.abrirGuiaEquipe = async function (equipe) {
    console.log("[PAINEL_ABAS] Abrindo equipe:", equipe);
    entrarModoAbas();

    const abas = document.getElementById("abasEquipes");
    const painel = document.getElementById("painelEquipesInternas");

    const idAba = `aba-${equipe.replace(/\s+/g, "-").toLowerCase()}`;
    const idPainel = `${idAba}-conteudo`;

    // Se já existe, apenas ativa
    if (document.getElementById(idAba)) {
        ativarAba(idAba);
        return;
    }

    // Botão da aba
    const botaoAba = document.createElement("button");
    botaoAba.id = idAba;
    botaoAba.className = "aba-equipe";
    botaoAba.innerHTML = `${equipe} <span class="fechar">×</span>`;

    botaoAba.onclick = () => ativarAba(idAba);
    botaoAba.querySelector(".fechar").onclick = ev => {
        ev.stopPropagation();
        fecharAba(idAba);
    };

    abas.appendChild(botaoAba);

    // Painel da aba
    const divPainel = document.createElement("div");
    divPainel.id = idPainel;
    divPainel.className = "painel-equipe";
    divPainel.style.display = "none";

    divPainel.innerHTML = `
        <div class="carregando">
            <i class="fa-solid fa-spinner fa-spin"></i>
            <p>Carregando ${equipe}…</p>
        </div>
    `;

    painel.appendChild(divPainel);

    abas.classList.remove("hidden");
    painel.classList.remove("hidden");

    abas.style.display = "flex";
    painel.style.display = "block";

    try {
        const resp = await fetch("../backend/obter_status_equipe.php", {
            method: "POST",
            body: new URLSearchParams({ equipe })
        });

        const dados = await resp.json();
        console.log("[DEBUG] Dados carregados:", equipe, dados);

        if (!dados.success) {
            divPainel.innerHTML = `<p class="erro">Erro ao carregar equipe</p>`;
            return;
        }

        if (typeof iniciarPainelEquipe === "function") {
            iniciarPainelEquipe({
                equipe,
                destino: `#${idPainel}`
            });

            // 🔥 FORÇA VISUAL APÓS RENDER
            setTimeout(() => {
                divPainel.classList.remove("hidden");
                divPainel.style.display = "block";
                divPainel.style.visibility = "visible";

                console.log(
                    "[PAINEL_ABAS] HTML final renderizado:",
                    divPainel.innerHTML.length
                );
            }, 50);
        } else {
            divPainel.innerHTML = `<p class="erro">Função iniciarPainelEquipe não encontrada.</p>`;
        }

        ativarAba(idAba);

    } catch (e) {
        console.error("[PAINEL_ABAS] Erro:", e);
        divPainel.innerHTML = `<p class="erro">Falha ao comunicar com o servidor.</p>`;
    }
};

// ============================================================
// Ativar Aba (CORRIGIDO)
// ============================================================
function ativarAba(idAba) {
    console.log("[PAINEL_ABAS] Ativando aba:", idAba);

    document.querySelectorAll(".aba-equipe").forEach(btn => {
        btn.classList.toggle("active", btn.id === idAba);
    });

    document.querySelectorAll(".painel-equipe").forEach(div => {
        const ativo = div.id === `${idAba}-conteudo`;

        div.classList.toggle("hidden", !ativo);
        div.style.display = ativo ? "block" : "none";
        div.style.visibility = ativo ? "visible" : "hidden";
    });
}

// ============================================================
// Fechar Aba
// ============================================================
function fecharAba(idAba) {
    console.log("[PAINEL_ABAS] Fechando aba:", idAba);

    document.getElementById(idAba)?.remove();
    document.getElementById(`${idAba}-conteudo`)?.remove();

    const restantes = document.querySelectorAll(".aba-equipe");
    if (!restantes.length) {
        sairModoAbas();
    } else {
        ativarAba(restantes[0].id);
    }
}

// ============================================================
// Utilitário
// ============================================================
function formatarSegundos(seg) {
    const s = Math.max(0, parseInt(seg, 10) || 0);
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const r = s % 60;
    return (
        h.toString().padStart(2, "0") + ":" +
        m.toString().padStart(2, "0") + ":" +
        r.toString().padStart(2, "0")
    );
}
