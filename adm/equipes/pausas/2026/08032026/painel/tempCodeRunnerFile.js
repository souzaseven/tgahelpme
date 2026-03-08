// ============================================================
// decidir.js — Modal "Decidir na fila de espera" (FASE 9 + melhorias)
// ============================================================
// - Compatível com index.php atual
// - Modal arrastável usando o <h3> como topo
// - Fechar com ESC
// ============================================================

console.log("%c[DECIDIR] Fase 9 carregada", "color:#fb923c;font-weight:bold;");

let decidirContexto = null;


// ------------------------------------------------------------
// PEGAR OPERADOR LOGADO
// ------------------------------------------------------------
function decidirGetOperador() {
    try {
        return JSON.parse(localStorage.getItem("tga_operador")) || null;
    } catch (e) {
        return null;
    }
}

// ------------------------------------------------------------
// ABRIR MODAL
// ------------------------------------------------------------
//window.abrirModalDecidir = async function (operadorId) {
/*window.abrirModalDecidir = async function (operadorId, isSupervisor = false) {

    const dados = decidirGetOperador();
    if (!dados) {
        alert("Sessão expirada. Faça login novamente.");
        window.location.href = "../login/login.php";
        return;
    }

    const overlay  = document.getElementById("decidirOverlay");
    const box      = document.querySelector(".decidir-modal");
    const boxOpcoes = document.getElementById("decidirOpcoes");

    if (!overlay || !box || !boxOpcoes) {
        console.error("[DECIDIR] Elementos do modal não encontrados.");
        return;
    }

    overlay.classList.remove("hidden");
    box.classList.remove("hidden");

    // Reset de posição
    box.style.left = "";
    box.style.top  = "";

    boxOpcoes.innerHTML = `
        <p class="decidir-info">Carregando informações da fila...</p>
    `;

    try {
        const resp = await fetch("../backend/consultar_posicao_fila.php", {
            method: "POST",
            body: new URLSearchParams({
                operador_id: operadorId,
                equipe: dados.equipe
            })
        });

        const r = await resp.json();

        if (!r.success) {
            boxOpcoes.innerHTML = `
                <p class="decidir-erro">${r.erro || "Erro ao consultar posição na fila."}</p>
            `;
            return;
        }
/*
        decidirContexto = {
            operadorId,
            equipe: dados.equipe,
            posicao: Number(r.posicao),
            vagasPausa: Number(r.vagas_pausa)
        };*/
/*
decidirContexto = {
    operadorId,
    equipe: dados.equipe,
    posicao: Number(r.posicao),
    vagasPausa: Number(r.vagas_pausa),
    isSupervisor: isSupervisor
};


        let html = "";

        // ==== ENTRAR EM PAUSA (somente primeiro da fila) ====
        if (decidirContexto.posicao === 1) {
            if (decidirContexto.vagasPausa > 0) {
                html += `
                    <button class="decidir-btn principal" onclick="decidirEntrarPausa()">
                        <i class="fa-solid fa-circle-play"></i> Entrar em pausa agora
                    </button>
                `;
            } else {
                html += `
                    <p class="decidir-info">Não há vaga disponível para pausa no momento.</p>
                `;
            }
        } else {
            html += `
                <p class="decidir-info">
                    Apenas o primeiro da fila pode entrar em pausa quando houver vaga.
                </p>
            `;
        }

        // ==== MOVER FILA (somente se for o primeiro) ====
        if (decidirContexto.posicao === 1) {
            html += `
                <button class="decidir-btn" onclick="decidirIrSegundo()">
                    <i class="fa-solid fa-arrow-down-short-wide"></i> Ir para segundo da fila
                </button>

                <button class="decidir-btn" onclick="decidirIrUltimo()">
                    <i class="fa-solid fa-arrow-down-long"></i> Ir para último da fila
                </button>
            `;
        }

        // ==== SAIR DA FILA ====
        html += `
            <button class="decidir-btn sair" onclick="decidirSairEspera()">
                <i class="fa-solid fa-circle-xmark"></i> Sair da espera (ficar ativo)
            </button>
        `;

        boxOpcoes.innerHTML = html;

    } catch (e) {
        console.error("[DECIDIR] Erro ao abrir modal:", e);
        boxOpcoes.innerHTML = `<p class="decidir-erro">Falha ao comunicar com o servidor.</p>`;
    }
};*/

// ------------------------------------------------------------
// ABRIR MODAL (Versão Supervisor Total)
// ------------------------------------------------------------
if ('Notification' in window && Notification.permission === "default") {
    Notification.requestPermission();
}

window.abrirModalDecidir = async function (operadorId, isSupervisor = false) {

    const dados = decidirGetOperador();
    if (!dados) {
        alert("Sessão expirada. Faça login novamente.");
        window.location.href = "../login/login.php";
        return;
    }

    const overlay   = document.getElementById("decidirOverlay");
    const box       = document.querySelector(".decidir-modal");
    const boxOpcoes = document.getElementById("decidirOpcoes");

    if (!overlay || !box || !boxOpcoes) {
        console.error("[DECIDIR] Elementos do modal não encontrados.");
        return;
    }

    overlay.classList.remove("hidden");
    box.classList.remove("hidden");

    box.style.left = "";
    box.style.top  = "";

    boxOpcoes.innerHTML = `
        <p class="decidir-info">Carregando informações da fila...</p>
    `;

    try {
        const resp = await fetch("../backend/consultar_posicao_fila.php", {
            method: "POST",
            body: new URLSearchParams({
                operador_id: operadorId,
                equipe: dados.equipe
            })
        });

        const r = await resp.json();



        if (!r.success) {
            boxOpcoes.innerHTML = `
                <p class="decidir-erro">${r.erro || "Erro ao consultar posição na fila."}</p>
            `;
            return;
        }

        decidirContexto = {
            operadorId,
            equipe: dados.equipe,
            posicao: Number(r.posicao),
            vagasPausa: Number(r.vagas_pausa),
totalFila: Number(r.total_fila),
            isSupervisor,
isAdmin: dados.is_admin === 1
        };

        let html = "";
decidirContexto.totalFila = Number(r.total_fila);

        // ============================================================
        // MODO SUPERVISOR — ACESSO TOTAL A TODAS AS AÇÕES
        // ============================================================
       /* if (isSupervisor) {

            html += `
                <button class="decidir-btn principal" onclick="decidirEntrarPausa()">
                    <i class="fa-solid fa-circle-play"></i> Forçar entrada em pausa
                </button>

                <button class="decidir-btn" onclick="decidirIrSegundo()">
                    <i class="fa-solid fa-arrow-down-short-wide"></i> Enviar para 2º da fila
                </button>

                <button class="decidir-btn" onclick="decidirIrUltimo()">
                    <i class="fa-solid fa-arrow-down-long"></i> Enviar para último da fila
                </button>

                <button class="decidir-btn sair" onclick="decidirSairEspera()">
                    <i class="fa-solid fa-circle-xmark"></i> Tirar da fila (ficar ativo)
                </button>
            `;

            boxOpcoes.innerHTML = html;
            return; // <<< IMPORTANTE
        }*/
// ============================================================
// MODO SUPERVISOR/ADMIN — AÇÕES COMPLETAS
// ============================================================
if (isSupervisor || decidirContexto.isAdmin) {

    html += `
        <button class="decidir-btn principal" onclick="decidirEntrarPausa()">
            <i class="fa-solid fa-circle-play"></i> Forçar pausa
        </button>

        <button class="decidir-btn" onclick="decidirIrSegundo()">
            <i class="fa-solid fa-arrow-down-short-wide"></i> Enviar para 2º
        </button>

        <button class="decidir-btn" onclick="decidirIrUltimo()">
            <i class="fa-solid fa-arrow-down-long"></i> Enviar para último
        </button>

        <div class="decidir-custom-pos">
            <input type="number" min="1" id="novaPosicao" placeholder="Nova posição" />
            <button class="decidir-btn" onclick="decidirIrParaPosicao()">
                <i class="fa-solid fa-arrow-down-wide-short"></i> Alterar posição
            </button>
        </div>

        <button class="decidir-btn aviso" onclick="decidirEnviarAviso()">
            <i class="fa-solid fa-bell"></i> Enviar aviso
        </button>

        <button class="decidir-btn fechar" onclick="fecharModalDecidir()">
            <i class="fa-solid fa-xmark"></i> Fechar
        </button>
    `;

    boxOpcoes.innerHTML = html;
    return;
}

// ============================================================
// USUÁRIO COMUM — AÇÕES
// ============================================================
html += `
    <button class="decidir-btn principal" onclick="decidirEntrarPausa()">
        <i class="fa-solid fa-circle-play"></i> Entrar em pausa
    </button>
`;

// Mostrar movimentos básicos (se for 1)
if (decidirContexto.posicao === 1) {
    html += `
        <button class="decidir-btn" onclick="decidirIrSegundo()">
            <i class="fa-solid fa-arrow-down-short-wide"></i> Ir para segundo
        </button>

        <button class="decidir-btn" onclick="decidirIrUltimo()">
            <i class="fa-solid fa-arrow-down-long"></i> Ir para último
        </button>
    `;
}

// Campo de posição personalizada (apenas para mover para baixo)
html += `
    <div class="decidir-custom-pos">
        <label for="novaPosicao">Mudar para posição (>= ${decidirContexto.posicao + 1}):</label>
        <input
            type="number"
            min="${decidirContexto.posicao + 1}"
 max="${decidirContexto.totalFila}"
            id="novaPosicao"
            placeholder="Digite nova posição"
        />
        <button class="decidir-btn" onclick="decidirIrParaPosicao()">
            <i class="fa-solid fa-arrow-down-wide-short"></i> Mudar posição
        </button>
    </div>
`;

html += `
    <button class="decidir-btn sair" onclick="decidirSairEspera()">
        <i class="fa-solid fa-circle-xmark"></i> Sair da espera
    </button>
`;

boxOpcoes.innerHTML = html;




        // ============================================================
        // REGRA NORMAL (usuário comum)
        // ============================================================
/*
        if (decidirContexto.posicao === 1 && decidirContexto.vagasPausa > 0) {

            html += `
                <button class="decidir-btn principal" onclick="decidirEntrarPausa()">
                    <i class="fa-solid fa-circle-play"></i> Entrar em pausa agora
                </button>
            `;

        } else {

            html += `
                <p class="decidir-info">Apenas o primeiro da fila pode entrar em pausa.</p>
            `;
        }

        if (decidirContexto.posicao === 1) {
            html += `
                <button class="decidir-btn" onclick="decidirIrSegundo()">
                    <i class="fa-solid fa-arrow-down-short-wide"></i> Ir para segundo da fila
                </button>

                <button class="decidir-btn" onclick="decidirIrUltimo()">
                    <i class="fa-solid fa-arrow-down-long"></i> Ir para último da fila
                </button>
            `;
        }

        html += `
            <button class="decidir-btn sair" onclick="decidirSairEspera()">
                <i class="fa-solid fa-circle-xmark"></i> Sair da espera (ficar ativo)
            </button>
        `;

        boxOpcoes.innerHTML = html;*/
        // ============================================================
        // REGRA NORMAL ou ADMIN
        // ============================================================
/*
        if (
            (decidirContexto.posicao === 1 && decidirContexto.vagasPausa > 0) ||
            decidirContexto.isAdmin
        ) {
            html += `
                <button class="decidir-btn principal" onclick="decidirEntrarPausa()">
                    <i class="fa-solid fa-circle-play"></i> Entrar em pausa agora
                </button>
            `;

            // Notificação para operadores normais
            if (decidirContexto.vagasPausa > 0 && !decidirContexto.isAdmin && 'Notification' in window) {
                Notification.requestPermission().then(permission => {
                    if (permission === "granted") {
                        new Notification("Vaga de Pausa Disponível", {
                            body: "Uma nova vaga de pausa foi aberta. Você pode entrar agora!",
                            icon: "../img/icon-pausa.png"
                        });
                    }
                });
            }
        } else {
            html += `
                <p class="decidir-info">Apenas o primeiro da fila pode entrar em pausa quando houver vaga.</p>
            `;
        }

        // Opções de movimentação
        if (decidirContexto.posicao === 1 || decidirContexto.isAdmin) {
            html += `
                <button class="decidir-btn" onclick="decidirIrSegundo()">
                    <i class="fa-solid fa-arrow-down-short-wide"></i> Ir para segundo da fila
                </button>

                <button class="decidir-btn" onclick="decidirIrUltimo()">
                    <i class="fa-solid fa-arrow-down-long"></i> Ir para último da fila
                </button>
            `;
        }

        // Opção especial para admin: mudar para posição específica
        if (decidirContexto.isAdmin) {
            html += `
                <input type="number" min="1" id="novaPosicao" placeholder="Nova posição na fila" style="margin-top:10px;" />
                <button class="decidir-btn" onclick="decidirIrParaPosicao()">
                    <i class="fa-solid fa-arrow-down-wide-short"></i> Alterar para posição personalizada
                </button>
            `;
        }

        html += `
            <button class="decidir-btn sair" onclick="decidirSairEspera()">
                <i class="fa-solid fa-circle-xmark"></i> Sair da espera (ficar ativo)
            </button>
        `;

        boxOpcoes.innerHTML = html;


    } catch (e) {
        console.error("[DECIDIR] Erro ao abrir modal:", e);
        boxOpcoes.innerHTML = `<p class="decidir-erro">Falha ao comunicar com o servidor.</p>`;
    }
};

*/
// ------------------------------------------------------------
// FECHAR MODAL
// ------------------------------------------------------------
window.fecharModalDecidir = function () {
    document.getElementById("decidirOverlay")?.classList.add("hidden");
    document.querySelector(".decidir-modal")?.classList.add("hidden");
    decidirContexto = null;
};

// Fechar com ESC
document.addEventListener("keydown", e => {
    if (e.key === "Escape") fecharModalDecidir();
});

// ------------------------------------------------------------
// AÇÕES
// ------------------------------------------------------------
window.decidirEntrarPausa = async function () {
    if (!decidirContexto) return;

    try {
        const resp = await fetch("../backend/iniciar_pausa.php", {
            method: "POST",
            body: new URLSearchParams({
                id: decidirContexto.operadorId,
                equipe: decidirContexto.equipe
            })
        });

        const r = await resp.json().catch(() => null);
        if (!r || !r.success) alert(r?.erro || "Não foi possível iniciar a pausa.");

    } catch {
        alert("Falha ao tentar iniciar a pausa.");
    }

    fecharModalDecidir();
    setTimeout(() => window.carregarPainel?.(), 300);
};

async function decidirMoverFila(acao) {
    if (!decidirContexto) return;

    try {
        const resp = await fetch("../backend/decidir_mover_fila.php", {
            method: "POST",
            body: new URLSearchParams({
                operador_id: decidirContexto.operadorId,
                equipe: decidirContexto.equipe,
                acao
            })
        });

        const r = await resp.json().catch(() => null);
        if (!r || !r.success) alert(r?.erro || "Não foi possível mover na fila.");

    } catch {
        alert("Falha ao tentar mover na fila.");
    }

    fecharModalDecidir();
    setTimeout(() => window.carregarPainel?.(), 300);
}

window.decidirIrSegundo = () => decidirMoverFila("segundo");
window.decidirIrUltimo  = () => decidirMoverFila("ultimo");

window.decidirSairEspera = async function () {
    if (!decidirContexto) return;

    try {
        await fetch("../backend/sair_fila.php", {
            method: "POST",
            body: new URLSearchParams({ id: decidirContexto.operadorId })
        });
    } catch (e) {
        console.error("[DECIDIR] Erro ao sair da fila:", e);
    }

    fecharModalDecidir();
    setTimeout(() => window.carregarPainel?.(), 300);
};

// ------------------------------------------------------------
// MODAL ARRASTÁVEL — usa o <h3> como topo
// ------------------------------------------------------------
(function enableDrag() {
    const box = document.querySelector(".decidir-modal");
    if (!box) return;

    const header = box.querySelector("h3");
    if (!header) return;

    let dragging = false;
    let offsetX = 0, offsetY = 0;

    header.style.cursor = "move";

    header.addEventListener("mousedown", e => {
        dragging = true;
        const rect = box.getBoundingClientRect();
        offsetX = e.clientX - rect.left;
        offsetY = e.clientY - rect.top;

        box.style.position = "fixed";
    });

    document.addEventListener("mousemove", e => {
        if (!dragging) return;
        box.style.left = (e.clientX - offsetX) + "px";
        box.style.top  = (e.clientY - offsetY) + "px";
    });

    document.addEventListener("mouseup", () => dragging = false);
})();


window.decidirIrParaPosicao = async function () {
    const novaPos = document.getElementById("novaPosicao")?.value;
    if (!novaPos || isNaN(novaPos) || novaPos < 1) {
        alert("Digite posição válida.");
        return;
    }

    try {
        const resp = await fetch("../backend/decidir_mover_fila.php", {
            method: "POST",
            body: new URLSearchParams({
                operador_id: decidirContexto.operadorId,
                equipe: decidirContexto.equipe,
                acao: "custom",
                nova_posicao: novaPos,
                is_admin: decidirContexto.isAdmin ? 1 : 0
            })
        });

        const r = await resp.json().catch(() => null);
        if (!r || !r.success) {
            alert(r?.erro || "Não foi possível alterar posição.");
        }

    } catch {
        alert("Erro ao mover operador.");
    }

    fecharModalDecidir();
    setTimeout(() => window.carregarPainel?.(), 300);
};



/*=====================================
ENVIAR AVISO PARA O OPERADOR
=======================================*/
window.decidirEnviarAviso = function () {
    const mensagem = prompt("Digite a mensagem para enviar aos usuários:");
    if (!mensagem) return;

    fetch("../backend/enviar_aviso.php", {
        method: "POST",
        body: new URLSearchParams({
            equipe: decidirContexto.equipe,
            mensagem
        })
    })
    .then(resp => resp.json())
    .then(r => {
        if (!r.success) alert(r.erro || "Falha ao enviar aviso.");
        else {
            alert("Aviso enviado com sucesso!");

            // 🚀 Aqui exibimos a notificação Windows
            if ('Notification' in window && Notification.permission === "granted") {
                new Notification("Aviso enviado", {
                    body: mensagem,
                    icon: "../img/icon-bell.png"
                });
            }
        }
    })
    .catch(() => alert("Erro ao tentar enviar aviso."));
};
