// ============================================================
// equipes_todas.js - Tela "Todas as Equipes"
// ============================================================

console.log("%c[PAINEL] Módulo equipes_todas.js carregado", "color:#7dd3fc");

function abrirTelaTodasEquipes() {

    const app = document.getElementById("app");
    if (!app) return;

    // Tela completa, ocupando toda largura do painel
    app.innerHTML = `
        <div class="painel-container-todas-equipes">

            <h1 style="color:#38bdf8;margin-bottom:16px;">
                <i class="fas fa-users"></i> Todas as Equipes
            </h1>

            <div id="resumoTotais" style="
                font-size:15px;
                color:#94a3b8;
                margin-bottom:18px;
                padding:10px 14px;
                border:1px solid #1e293b;
                border-radius:8px;
                background:#0b1120;
                display:inline-block;
            ">
                Carregando totais...
            </div>

            <div id="listaTodasEquipes" class="lista-equipes-grid">
                <div class="lista-vazia">
                    Carregando equipes...
                </div>
            </div>
        </div>
    `;

    const baseBackend = window.SISTEMA_CONFIG?.caminhos?.backend || "./backend/";
    const url = baseBackend + "listar_equipes_login.php";

    fetch(url)
        .then(r => r.json())
        .then(resp => {
            console.log("[TODAS EQUIPES] Dados recebidos:", resp);

            const container = document.getElementById("listaTodasEquipes");
            const resumoTotais = document.getElementById("resumoTotais");

            if (!resp || !resp.success || !Array.isArray(resp.equipes)) {
                container.innerHTML = `<div class="lista-vazia">Erro ao carregar equipes.</div>`;
                resumoTotais.innerHTML = "Não foi possível carregar totais.";
                return;
            }

            container.innerHTML = "";

            // ===========================
            // CALCULAR QUANTIDADES
            // ===========================
            let totalOperadoresGeral = 0;

            resp.equipes.forEach(eq => {
                totalOperadoresGeral += eq.operadores.length;
            });

            resumoTotais.innerHTML = `
                <strong>Total geral de operadores:</strong> ${totalOperadoresGeral}
            `;

            // ===========================
            // LISTAR EQUIPES
            // ===========================
            resp.equipes.forEach(eq => {
                const quantidade = eq.operadores.length;

                const card = document.createElement("div");
                card.className = "card card-equipe-listagem";

                card.innerHTML = `
                    <h3 class="titulo-equipe">
                        <i class="fas fa-user-tie"></i> ${eq.lider}
                    </h3>

                    <p style="margin:4px 0 10px; color:#94a3b8; font-size:14px;">
                        <strong>${quantidade}</strong> operador(es)
                    </p>

                    <div class="lista-participantes">
                        ${
                            eq.operadores.map(op => `
                                <div class="linha-participante">
                                    <span class="bolinha-estado"></span>
                                    <span class="nome-op">${op.nome}</span>
                                </div>
                            `).join("")
                        }
                    </div>
                `;

                container.appendChild(card);
            });

        })
        .catch(err => {
            console.error("[TODAS EQUIPES] Erro:", err);
            document.getElementById("listaTodasEquipes").innerHTML = `
                <div class="lista-vazia">Erro ao carregar equipes.</div>`;
        });

}
