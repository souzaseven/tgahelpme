// ============================================================
// painel.js - FASE 7 (base Fase 6) do Controle de Pausas
// ============================================================
// - Status: ativo / espera / pausa
// - Atualização automática do painel
// - Lista de pausa, fila e equipe
// - Integra operador.js
// - Fila com posição + cronômetro de espera
// ============================================================

let timerFila = null; // controle do cronômetro da fila

document.addEventListener("DOMContentLoaded", () => {

    // ============================================================
    // 1) Carregar operador logado
    // ============================================================
    const dadosOperador = JSON.parse(localStorage.getItem("tga_operador"));
    if (!dadosOperador) {
        window.location.href = "../login/login.php";
        return;
    }

    const nomeOperador =
          dadosOperador.operador
       || dadosOperador.nome
       || dadosOperador.usuario
       || "Operador";

    // Mostrar nome no topo
    const boxOperador = document.getElementById("boxOperadorLogado");
    if (boxOperador) {
        boxOperador.innerHTML = `
            <div class="op-logado">
                <i class="fas fa-user"></i> ${nomeOperador}
            </div>
        `;
    }

    // Exibir nome da equipe nos 2 lugares
    const subTitulo    = document.getElementById("subtituloEquipe");
    const tituloEquipe = document.getElementById("nomeEquipeTitulo");

    if (subTitulo)    subTitulo.textContent    = dadosOperador.equipe;
    if (tituloEquipe) tituloEquipe.textContent = dadosOperador.equipe;

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

            // Espera-se que o backend retorne:
            // pausa: [], fila: [], equipe_completa: []
            const { pausa, fila, equipe_completa } = dados;

            atualizarListaPausa(pausa);
            atualizarFila(fila);
            atualizarEquipeCompleta(equipe_completa);

        } catch (e) {
            console.error("[ERRO carregarPainel]", e);
        }
    }

    // Deixa disponível globalmente para operador.js
    window.carregarPainel = carregarPainel;

    // ============================================================
    // 3) LISTA DE OPERADORES EM PAUSA
    // ============================================================
    function atualizarListaPausa(lista) {
        const box = document.getElementById("listaPausa");
        if (!box) return;

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
    // 4) LISTA DA FILA DE ESPERA (com posição + cronômetro)
    // ============================================================
    function atualizarFila(lista) {
        const box = document.getElementById("listaFila");
        if (!box) return;

        if (!lista || lista.length === 0) {
            box.innerHTML = `<p class="lista-vazia">Nenhum operador na fila.</p>`;
            // Se não tem ninguém, para o timer
            if (timerFila) {
                clearInterval(timerFila);
                timerFila = null;
            }
            return;
        }

        box.innerHTML = lista.map(op => {
            const pos  = op.posicao_fila ?? "";
            const segs = op.tempo_espera_seg ?? 0;

            return `
                <div class="linha-participante" data-nome="${op.nome}">
                    <div class="linha-fila-topo">
                        <span class="posicao-fila">${pos}º</span>
                        <span class="nome">${op.nome}</span>
                    </div>
                    <div class="linha-fila-tempo">
                        <span class="bolinha-estado espera"></span>
                        <span class="tempo-fila"
                              data-id="${op.id}"
                              data-seg="${segs}">
                            ${formatarTempoFila(segs)}
                        </span>
                    </div>
                </div>
            `;
        }).join("");

        iniciarCronometroFila();
    }

    // ============================================================
    // 5) LISTA DA EQUIPE COMPLETA
    // ============================================================
    function atualizarEquipeCompleta(lista) {
        const box = document.getElementById("listaEquipeCompleta");
        if (!box) return;

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

        // Inserir botões (FASE 6)
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

// ============================================================
// 8) FUNÇÕES AUXILIARES — FILA (cronômetro)
// ============================================================
function formatarTempoFila(segundos) {
    const m = Math.floor(segundos / 60);
    const s = segundos % 60;
    return `${m.toString().padStart(2, "0")}:${s.toString().padStart(2, "0")}`;
}

function iniciarCronometroFila() {
    if (timerFila) {
        clearInterval(timerFila);
    }

    timerFila = setInterval(() => {
        document.querySelectorAll(".tempo-fila").forEach(span => {
            let atual = parseInt(span.dataset.seg || "0", 10);
            atual++;
            span.dataset.seg = String(atual);
            span.textContent = formatarTempoFila(atual);
        });
    }, 1000);
}
