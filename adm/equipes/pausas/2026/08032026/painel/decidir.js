// ============================================================
// decidir.js — Modal "Decidir na fila de espera" (FASE 9 FINAL)
// ============================================================
// - Compatível com index.php atual
// - Modal arrastável usando o <h3>
// - Fechar com ESC
// - Loader Evolux com cancelamento real
// ============================================================

console.log("%c[DECIDIR] Fase 9 FINAL carregada", "color:#22c55e;font-weight:bold;");

// FALLBACK GLOBAL PARA TOAST (ANTI-CRASH)
/*
if (typeof window.toastAviso !== "function") {
    window.toastAviso = function (msg, tipo = "info") {
        console.log(`[TOAST:${tipo}]`, msg);
        // fallback seguro: NÃO chamar alert aqui
    };
}
*/


// ============================================================
// CONTEXTO GLOBAL
// ============================================================
let decidirContexto = null;
let evoluxAbortController = null;
let evoluxEmProcesso = false;

// ============================================================
// INTERCEPTAR ALERT → TOAST
// ============================================================
/*
window.alert = function (msg) {
    if (typeof toastAviso === "function") {
        toastAviso(msg, "warning");
    } else {
        console.warn("⚠️ ALERT:", msg);
    }
};
*/

// ============================================================
// UTIL — OPERADOR LOGADO
// ============================================================
function decidirGetOperador() {
    try {
        return JSON.parse(localStorage.getItem("tga_operador")) || null;
    } catch {
        return null;
    }
}

// ============================================================
// MONITORAR RETORNO DO EVOLUX (AUTO-DETECÇÃO)
// ============================================================
function monitorarRetornoEvolux(agentId) {
    let tentativas = 0;

    const intervalo = setInterval(async () => {
        tentativas++;

        try {
            const resp = await fetch(
                `../backend/verificar_evolux_online.php?agent_id=${agentId}`
            );
            const r = await resp.json();

            if (r.online) {
                clearInterval(intervalo);

                toastAviso(
                    "Conexão com o Evolux restabelecida.\n" +
                    "Você já pode tentar entrar em pausa novamente.",
                    "success"
                );
            }

            if (tentativas >= 6) { // ~30s
                clearInterval(intervalo);
            }

        } catch (e) {
            clearInterval(intervalo);
        }

    }, 5000);
}

// ============================================================
// NOTIFICAR SUPERVISOR — FALHA EVOLUX
// ============================================================
function notificarSupervisorEvolux({ operadorId, equipe, tipo, mensagem }) {
    fetch("../backend/notificar_supervisor.php", {
        method: "POST",
        body: new URLSearchParams({
            operador_id: operadorId,
            equipe,
            tipo,
            mensagem
        })
    }).catch(() => {
        console.warn("[EVOLUX] Falha ao notificar supervisor.");
    });
}

// ============================================================
// FECHAR MODAL
// ============================================================
window.fecharModalDecidir = function () {
    document.getElementById("decidirOverlay")?.classList.add("hidden");
    document.querySelector(".decidir-modal")?.classList.add("hidden");
    decidirContexto = null;
};

// ESC
document.addEventListener("keydown", e => {
    if (e.key === "Escape") fecharModalDecidir();
});

// ============================================================
// CANCELAR EVOLUX
// ============================================================
window.cancelarEvolux = function () {
    if (evoluxAbortController) {
        evoluxAbortController.abort();
        evoluxAbortController = null;
    }

    evoluxEmProcesso = false;
    ocultarEvoluxLoader();
    toastAviso("Operação cancelada pelo usuário.", "info");
};

// ============================================================
// MOVER FILA
// ============================================================
async function decidirMoverFila(acao) {
    if (!decidirContexto) return;

    try {
        const resp = await fetch("../backend/decidir_mover_fila.php", {
            method: "POST",
            body: new URLSearchParams({
                operador_id: decidirContexto.operadorId,
                equipe: decidirContexto.equipe,
                acao,
                is_admin: decidirContexto.isAdmin ? 1 : 0
            })
        });

        const r = await resp.json().catch(() => null);
        if (!r || !r.success) {
            toastAviso(r?.erro || "Não foi possível mover na fila.", "warning");
            return;
        }

        fecharModalDecidir();
        setTimeout(() => window.carregarPainel?.(), 300);

    } catch (e) {
        console.error("[DECIDIR] mover fila:", e);
        toastAviso("Erro ao comunicar com o servidor.", "warning");
    }
}
// ============================================================
// ABRIR MODAL
// ============================================================
window.abrirModalDecidir = async function (operadorId, isSupervisor = false) {

    const dados = decidirGetOperador();
    if (!dados) {
        alert("Sessão expirada. Faça login novamente.");
        location.href = "../login/login.php";
        return;
    }

    const overlay   = document.getElementById("decidirOverlay");
    const box       = document.querySelector(".decidir-modal");
    const boxOpcoes = document.getElementById("decidirOpcoes");

    overlay.classList.remove("hidden");
    box.classList.remove("hidden");
    boxOpcoes.innerHTML = `<p class="decidir-info">Carregando informações da fila...</p>`;

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
            boxOpcoes.innerHTML = `<p class="decidir-erro">${r.erro}</p>`;
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

        const podeEntrar =
            (decidirContexto.vagasPausa >= 1 && decidirContexto.posicao === 1) ||
            (decidirContexto.vagasPausa >= 2 && decidirContexto.posicao <= 2);

        if (podeEntrar || decidirContexto.isAdmin || isSupervisor) {
            html += `
                <button class="decidir-btn principal" onclick="decidirEntrarPausa()">
                    <i class="fa-solid fa-circle-play"></i> Entrar em pausa
                </button>
            `;
        }

        // Ir para último (todos podem)
        html += `
            <button class="decidir-btn" onclick="decidirMoverFila('ultimo')">
                <i class="fa-solid fa-arrow-down-long"></i> Ir para último
            </button>
        `;

        // ================= ADMIN / SUPERVISOR =================
        if (decidirContexto.isAdmin || isSupervisor) {
            html += `
                <div class="decidir-custom-pos">
                    <label for="novaPosicaoAdmin">Mudar para posição (admin):</label>
                    <input
                        type="number"
                        min="1"
                        max="${decidirContexto.totalFila}"
                        id="novaPosicaoAdmin"
                        placeholder="N° nova posição"
                    />
                    <button class="decidir-btn" onclick="decidirIrParaPosicao()">
                        <i class="fa-solid fa-arrow-down-wide-short"></i> Alterar posição
                    </button>
                </div>
            `;
        }

        // ================= OPERADOR COMUM =================
        if (!decidirContexto.isAdmin && !isSupervisor) {
            html += `
                <div class="decidir-custom-pos">
                    <label for="novaPosicaoOperador">
                        Ir para posição abaixo da sua (atual: ${decidirContexto.posicao})
                    </label>
                    <input
                        type="number"
                        min="${decidirContexto.posicao + 1}"
                        max="${decidirContexto.totalFila}"
                        id="novaPosicaoOperador"
                        placeholder="N° nova posição"
                    />
                    <button class="decidir-btn" onclick="decidirIrParaPosicao()">
                        <i class="fa-solid fa-arrow-down-wide-short"></i> Confirmar mudança
                    </button>
                </div>
            `;
        }

        html += `
            <button class="decidir-btn sair" onclick="decidirSairEspera()">
                <i class="fa-solid fa-circle-xmark"></i> Sair da espera
            </button>

            <button class="decidir-btn fechar" onclick="fecharModalDecidir()">
                <i class="fa-solid fa-xmark"></i> Fechar
            </button>
        `;

        boxOpcoes.innerHTML = html;

    } catch (e) {
        console.error("[DECIDIR] abrir modal:", e);
        boxOpcoes.innerHTML = `<p class="decidir-erro">Falha ao comunicar com o servidor.</p>`;
    }
};


// ============================================================
// MOVER PARA POSIÇÃO PERSONALIZADA
// ============================================================
window.decidirIrParaPosicao = async function () {
    if (!decidirContexto) return;

    const input =
        document.getElementById("novaPosicaoAdmin") ||
        document.getElementById("novaPosicaoOperador");

    const novaPosicao = parseInt(input?.value || "0", 10);

    if (!novaPosicao || novaPosicao <= 0) {
        toastAviso("Informe uma posição válida.", "warning");
        return;
    }

    // 🔒 Regra extra no FRONT (segurança visual)
    if (
        !decidirContexto.isAdmin &&
        !decidirContexto.isSupervisor &&
        novaPosicao <= decidirContexto.posicao
    ) {
        toastAviso(
            "Você só pode mover para posições abaixo da sua atual.",
            "warning"
        );
        return;
    }

    try {
        const resp = await fetch("../backend/decidir_mover_fila.php", {
            method: "POST",
            body: new URLSearchParams({
                operador_id: decidirContexto.operadorId,
                equipe: decidirContexto.equipe,
                acao: "custom",
                nova_posicao: novaPosicao,
                is_admin: decidirContexto.isAdmin ? 1 : 0
            })
        });

        const r = await resp.json();
        if (!r.success) {
            toastAviso(r.erro || "Erro ao alterar posição.", "warning");
            return;
        }

        fecharModalDecidir();
        setTimeout(() => window.carregarPainel?.(), 300);

    } catch (e) {
        console.error("[DECIDIR] mover posição personalizada:", e);
        toastAviso("Erro ao comunicar com o servidor.", "warning");
    }
};



// ============================================================
// ENTRAR EM PAUSA — EVOLUX (VERSÃO FINAL CONTROLADA)
// ============================================================
window.decidirEntrarPausa = async function () {
    if (!decidirContexto || evoluxEmProcesso) return;

    evoluxEmProcesso = true;
    evoluxAbortController = new AbortController();

    // -----------------------------
    // TIMELINE VISUAL DO PROCESSO
    // -----------------------------
    mostrarEvoluxLoader("Consultando status no Evolux…");

    const t1 = setTimeout(() =>
        mostrarEvoluxLoader("Confirmando usuário no Evolux…"), 2000);

    const t2 = setTimeout(() =>
        mostrarEvoluxLoader("Confirmando ramal no Evolux…"), 4000);

    const t3 = setTimeout(() =>
        mostrarEvoluxLoader("Confirmando posição na fila…"), 5000);

    const t4 = setTimeout(() =>
        mostrarEvoluxLoader("Mudando status para pausa…"), 6000);

    // -----------------------------
    // ⏱️ 10s — Aviso de lentidão
    // -----------------------------
    const aviso10s = setTimeout(() => {
        toastAviso(
            "O Evolux está demorando mais que o normal.\n\n" +
            "Verifique se a aba do Evolux está aberta e ativa.\n" +
            "Se estiver ativa, tente entrar novamente.",
            "warning"
        );
    }, 10000);

    // -----------------------------
    // ⏱️ 15s — Orientação clara
    // -----------------------------
const aviso15s = setTimeout(() => {
    toastAviso(
        "Ainda não houve resposta do Evolux.\n\n" +
        "• Verifique se você está logado no Evolux\n" +
        "• Reabra a aba do sistema\n\n" +
        "Se continuar, comunique o Anderson.",
        "warning"
    );
notificarSupervisorEvolux({
    operadorId: decidirContexto.operadorId,
    equipe: decidirContexto.equipe,
    tipo: "timeout_15s",
    mensagem: "Evolux demorando mais de 15s para iniciar pausa."
});

    fetch("../backend/log_evolux.php", {
        method: "POST",
        body: new URLSearchParams({
            operador_id: decidirContexto.operadorId,
            equipe: decidirContexto.equipe,
            tipo: "timeout_15s",
            mensagem: "Evolux demorando para responder ao iniciar pausa."
        })
    });

}, 15000);


    // -----------------------------
    // ⏱️ 18s — ERRO FINAL
    // -----------------------------
const erro18s = setTimeout(() => {
    if (evoluxAbortController) {
        evoluxAbortController.abort();
        ocultarEvoluxLoader();

        toastAviso(
            "Erro ao comunicar com o operador no Evolux.\n\n" +
            "• Verifique se a aba do Evolux está aberta\n" +
            "• Confirme se você está logado\n\n" +
            "Se não resolver, avise o Anderson.",
            "error"
        );

        // 🧠 MONITORAMENTO AUTOMÁTICO
        monitorarRetornoEvolux(decidirContexto.operadorId);


        // 📊 LOG BACKEND
        fetch("../backend/log_evolux.php", {
            method: "POST",
            body: new URLSearchParams({
                operador_id: decidirContexto.operadorId,
                equipe: decidirContexto.equipe,
                tipo: "erro_18s",
                mensagem: "Timeout ao tentar iniciar pausa no Evolux."
            })
        });

        evoluxEmProcesso = false;
    }
}, 18000);


    try {
        const resp = await fetch("../backend/iniciar_pausa.php", {
            method: "POST",
            signal: evoluxAbortController.signal,
            body: new URLSearchParams({
                id: decidirContexto.operadorId,
                equipe: decidirContexto.equipe
            })
        });
/*
const r = await resp.json().catch(() => null);

if (!r || !r.success) {
    toastAviso(r?.erro || "Falha ao iniciar pausa.", "warning");
    return;
}*/
const raw = await resp.text();

console.group("%c[INICIAR_PAUSA]", "color:#f97316;font-weight:bold;");
console.log("HTTP:", resp.status);
console.log("RAW:", raw);

let r = null;
try {
    r = JSON.parse(raw);
} catch (e) {
    console.error("JSON inválido:", e);
}

if (!r || !r.success) {
    console.warn("Resposta falhou:", r);

    toastAviso(
        r?.erro || r?.mensagem || "Falha ao iniciar pausa no Evolux.",
        "error"
    );

    console.groupEnd();
    return;
}

console.log("Sucesso:", r);
console.groupEnd();


// ===============================
// ⚠️ PAUSA MANUAL (SEM EVOLUX)
// ===============================
if (r.modo === "manual" && r.aviso) {
    toastAviso(r.aviso, "warning");
}

// fluxo normal continua
fecharModalDecidir();
setTimeout(() => window.carregarPainel?.(), 300);




    } catch (e) {
        if (e.name !== "AbortError") {
            console.error("[EVOLUX]", e);
            toastAviso("Erro inesperado ao comunicar com o Evolux.", "error");
        }
    } finally {
        // Limpeza total
        clearTimeout(t1);
        clearTimeout(t2);
        clearTimeout(t3);
        clearTimeout(t4);
        clearTimeout(aviso10s);
        clearTimeout(aviso15s);
        clearTimeout(erro18s);

        evoluxAbortController = null;
        evoluxEmProcesso = false;
        ocultarEvoluxLoader();
    }
};


// ============================================================
// SAIR DA FILA
// ============================================================
window.decidirSairEspera = async function () {
    if (!decidirContexto) return;

    await fetch("../backend/sair_fila.php", {
        method: "POST",
        body: new URLSearchParams({ id: decidirContexto.operadorId })
    });

    fecharModalDecidir();
    setTimeout(() => window.carregarPainel?.(), 300);
};
