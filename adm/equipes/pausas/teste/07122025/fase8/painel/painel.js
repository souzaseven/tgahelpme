// ============================================================
// painel.js - FASE 6 + FASE 7 + FASE 8/9 (Alertas inteligentes)
// ============================================================
// - Status: ativo / espera / pausa
// - Atualização automática do painel
// - Lista de pausa, fila e equipe
// - Cronômetro e posição na fila
// - Integra operador.js
// - FASE 8/9: ALERTAS automáticos com base nas preferências
//   Eventos previstos para dispararAlertas(evento, payload?):
//     • entrou_fila
//     • saiu_fila
//     • saiu_pausa
//     • virou_primeiro
//     • posicao_mudou   { posicao }
//     • vaga_abriu
//     • alguem_entrou_pausa { nome, alvo: "outro" }
// ============================================================

let intervaloFila        = null;
let ultimoStatusOperador = null;
let ultimaPosicaoFila    = null;
let estavaNaFila         = false;
let estavaEmPausa        = false;
let ultimoTotalPausa     = 0;
let ultimosIdsPausa      = [];

// ============================================================
// DOM READY
// ============================================================
document.addEventListener("DOMContentLoaded", () => {

    // ========================================================
    // 1) CARREGAR OPERADOR LOGADO
    // ========================================================
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

    const boxOperador = document.getElementById("boxOperadorLogado");
    if (boxOperador) {
        boxOperador.innerHTML = `
            <div class="op-logado">
                <i class="fas fa-user"></i> ${nomeOperador}
            </div>
        `;
    }

    const subTitulo    = document.getElementById("subtituloEquipe");
    const tituloEquipe = document.getElementById("nomeEquipeTitulo");

    if (subTitulo)    subTitulo.textContent    = dadosOperador.equipe;
    if (tituloEquipe) tituloEquipe.textContent = dadosOperador.equipe;

    // ========================================================
    // 2) FUNÇÃO PRINCIPAL — CARREGAR PAINEL
    // ========================================================
    async function carregarPainel() {
        try {
            const resp = await fetch("../backend/obter_status_equipe.php", {
                method: "POST",
                body: new URLSearchParams({ equipe: dadosOperador.equipe })
            });

            const dados = await resp.json();
            if (!dados.success) return;

            const { pausa, fila, equipe_completa } = dados;

            atualizarListaPausa(pausa);
            atualizarFila(fila);
            atualizarEquipeCompleta(equipe_completa);

            analisarMudancas(fila, equipe_completa, pausa);

        } catch (e) {
            console.error("[ERRO carregarPainel]", e);
        }
    }

    // Torna acessível para operador.js
    window.carregarPainel = carregarPainel;

    // ========================================================
    // 3) ALERTAS INTELIGENTES — FASE 8/9
    // ========================================================
    function analisarMudancas(fila, equipeCompleta, pausa) {

        // Recarrega operador do localStorage (caso algo mude)
        const dadosLS = JSON.parse(localStorage.getItem("tga_operador"));
        if (!dadosLS) return;

        const operador = equipeCompleta.find(o => o.id == dadosLS.id);
        if (!operador) return;

        const statusAtual = operador.status || "ativo";

        // ---------------------------
        // 3.1 — Entrada / saída da FILA
        // ---------------------------
        const estaNaFila = statusAtual === "espera";

        if (estaNaFila && !estavaNaFila) {
            if (window.dispararAlertas)
                window.dispararAlertas("entrou_fila", { alvo: "meu" });
        }

        if (!estaNaFila && estavaNaFila) {
            if (window.dispararAlertas)
                window.dispararAlertas("saiu_fila", { alvo: "meu" });
        }

        estavaNaFila = estaNaFila;

        // ---------------------------
        // 3.2 — Saída da PAUSA
        // ---------------------------
        const emPausa = statusAtual === "pausa";

        if (!emPausa && estavaEmPausa) {
            if (window.dispararAlertas)
                window.dispararAlertas("saiu_pausa", { alvo: "meu" });
        }

        estavaEmPausa = emPausa;

        // ---------------------------
        // 3.3 — Mudança de posição na FILA
        // ---------------------------
        if (estaNaFila) {
            const registroFila = fila.find(f => f.id == dadosLS.id);
            if (registroFila) {
                const posicaoAtual = registroFila.posicao_fila;

                if (ultimaPosicaoFila !== null && posicaoAtual !== ultimaPosicaoFila) {
                    if (posicaoAtual == 1) {
                        // virou primeiro da fila
                        if (window.dispararAlertas)
                            window.dispararAlertas("virou_primeiro", { alvo: "meu" });
                    } else {
                        // mudança de posição normal
                        if (window.dispararAlertas)
                            window.dispararAlertas("posicao_mudou", {
                                posicao: posicaoAtual,
                                alvo: "meu"
                            });
                    }
                }

                ultimaPosicaoFila = posicaoAtual;
            }
        } else {
            ultimaPosicaoFila = null;
        }

        // ---------------------------
        // 3.4 — VAGA ABRIU E ALGUÉM ENTROU EM PAUSA
        // ---------------------------
        const totalPausaAtual = Array.isArray(pausa) ? pausa.length : 0;
        const idsAtuais = (pausa || []).map(p => p.id);

        // Vaga abriu (antes estava cheio, agora tem < 2)
        if (estaNaFila && totalPausaAtual < 2 && ultimoTotalPausa >= 2) {
            if (window.dispararAlertas)
                window.dispararAlertas("vaga_abriu", { alvo: "meu" });
        }

        // Detectar novo operador em pausa (sempre é "outro")
        const novoPausadoId = idsAtuais.find(id => !ultimosIdsPausa.includes(id));
        if (novoPausadoId) {
            const opNovo = (pausa || []).find(p => p.id == novoPausadoId);
            if (window.dispararAlertas) {
                window.dispararAlertas("alguem_entrou_pausa", {
                    nome: opNovo?.nome || "um operador",
                    alvo: "outro"
                });
            }
        }

        ultimosIdsPausa  = idsAtuais;
        ultimoTotalPausa = totalPausaAtual;

        ultimoStatusOperador = statusAtual;
    }

    // ========================================================
    // 4) LISTA DE PAUSA
    // ========================================================
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

    // ========================================================
    // 5) LISTA DA FILA — COM CRONÔMETRO
    // ========================================================
    function formatarSegundos(seg) {
        const s = Math.max(0, parseInt(seg, 10) || 0);
        const m = Math.floor(s / 60);
        const r = s % 60;
        return `${m.toString().padStart(2, "0")}:${r.toString().padStart(2, "0")}`;
    }

    function iniciarCronometroFila() {
        if (intervaloFila) {
            clearInterval(intervaloFila);
            intervaloFila = null;
        }

        intervaloFila = setInterval(() => {
            document.querySelectorAll(".tempo-fila").forEach(el => {
                let t = parseInt(el.dataset.tempo || "0", 10);
                el.dataset.tempo = ++t;
                el.textContent = formatarSegundos(t);
            });
        }, 1000);
    }

    function atualizarFila(lista) {
        const box = document.getElementById("listaFila");
        if (!box) return;

        if (!lista || lista.length === 0) {
            box.innerHTML = `<p class="lista-vazia">Nenhum operador na fila.</p>`;
            if (intervaloFila) {
                clearInterval(intervaloFila);
                intervaloFila = null;
            }
            return;
        }

        box.innerHTML = lista.map(op => `
            <div class="linha-participante">
                <div class="linha-fila-topo">
                    <span class="posicao-fila">${op.posicao_fila}º</span>
                    <span class="nome">${op.nome}</span>
                </div>
                <div class="linha-fila-tempo">
                    <i class="fa-regular fa-clock"></i>
                    <span class="tempo-fila"
                          data-id="${op.id}"
                          data-tempo="${op.tempo_espera_seg}">
                        ${formatarSegundos(op.tempo_espera_seg)}
                    </span>
                </div>
            </div>
        `).join("");

        iniciarCronometroFila();
    }

    // ========================================================
    // 6) LISTA DA EQUIPE COMPLETA
    // ========================================================
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

        // Função do operador.js (Fase 6)
        inserirBotoesIndividuais(lista);
    }

    // ========================================================
    // 7) BOTÕES GLOBAIS (Fase 6)
    // ========================================================
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

    // ========================================================
    // 8) ATUALIZAÇÃO AUTOMÁTICA
    // ========================================================
    carregarPainel();
    setInterval(carregarPainel, 8000);
});
