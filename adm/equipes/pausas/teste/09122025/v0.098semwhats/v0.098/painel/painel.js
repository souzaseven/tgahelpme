// ============================================================
// painel.js - FASE 6 + FASE 7 + FASE 8/9 (Alertas inteligentes)
// ============================================================
// - Status: ativo / espera / pausa
// - Atualização automática do painel
// - Lista de pausa, fila e equipe
// - Cronômetro e posição na fila (tempo vindo do servidor)
// - Cronômetro na pausa (tempo vindo do servidor)
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
let intervaloPausa       = null;
let ultimoStatusOperador = null;
let ultimaPosicaoFila    = null;
let estavaNaFila         = false;
let estavaEmPausa        = false;
let ultimoTotalPausa     = 0;
let ultimosIdsPausa      = [];
// Quantidade de avisos já enviados para pausa acima de 20min (por operador)
let avisosPausaExpirada  = {};

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
       /* boxOperador.innerHTML = `
            <div class="op-logado">
                <i class="fas fa-user"></i> ${nomeOperador}
            </div>
        `;*/
const eLiderLogado = dadosOperador.elider == 1;

boxOperador.innerHTML = `
    <div class="op-logado ${eLiderLogado ? "lider" : ""}">
        ${eLiderLogado ? '<span class="icone-lider">👑</span>' : ''}
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

    // Torna acessível para operador.js e decidir.js
    window.carregarPainel = carregarPainel;

    // ========================================================
    // 3) ALERTAS INTELIGENTES — FASE 8/9
    // ========================================================
    function analisarMudancas(fila, equipeCompleta, pausa) {

        const dadosLS = JSON.parse(localStorage.getItem("tga_operador"));
        if (!dadosLS) return;

        const operador = equipeCompleta.find(o => o.id == dadosLS.id);
        if (!operador) return;

        const statusAtual = operador.status || "ativo";

        // ---------------------------
        // Entrada / saída da FILA
        // ---------------------------
        const estaNaFila = statusAtual === "espera";
        if (estaNaFila && !estavaNaFila) window.dispararAlertas?.("entrou_fila", { alvo: "meu" });
        if (!estaNaFila && estavaNaFila) window.dispararAlertas?.("saiu_fila",   { alvo: "meu" });
        estavaNaFila = estaNaFila;

        // ---------------------------
        // Saída da PAUSA
        // ---------------------------
/*
        const emPausa = statusAtual === "pausa";
        if (!emPausa && estavaEmPausa) window.dispararAlertas?.("saiu_pausa", { alvo: "meu" });
        estavaEmPausa = emPausa;
*/
// Entrada e saída da PAUSA
const emPausa = statusAtual === "pausa";

if (emPausa && !estavaEmPausa) {
    window.dispararAlertas?.("entrou_pausa", {
        nome: operador.nome,
        alvo: operador.id == dadosLS.id ? "meu" : "outro"
    });
}

if (!emPausa && estavaEmPausa) {
    window.dispararAlertas?.("saiu_pausa", {
        nome: operador.nome,
        alvo: operador.id == dadosLS.id ? "meu" : "outro"
    });
}

estavaEmPausa = emPausa;


        // ---------------------------
        // Mudança de posição na FILA
        // (posicao_fila DEVE vir do backend)
        // ---------------------------
        if (estaNaFila) {
            const reg = fila.find(f => f.id == dadosLS.id);
            if (reg) {
                const pos = reg.posicao_fila;

                if (ultimaPosicaoFila !== null && pos !== ultimaPosicaoFila) {
                    if (pos == 1) {
                        window.dispararAlertas?.("virou_primeiro", { alvo: "meu" });
                    } else {
                        window.dispararAlertas?.("posicao_mudou", { posicao: pos, alvo: "meu" });
                    }
                }
                ultimaPosicaoFila = pos;
            }
        } else {
            ultimaPosicaoFila = null;
        }

        // ---------------------------
        // Vaga abriu + alguém entrou em pausa
        // ---------------------------
        const totalPausaAtual = Array.isArray(pausa) ? pausa.length : 0;
        const idsAtuais = (pausa || []).map(p => p.id);

        // Vaga abriu: antes cheio (2) e agora menos que 2
        if (estaNaFila && totalPausaAtual < 2 && ultimoTotalPausa >= 2) {
            window.dispararAlertas?.("vaga_abriu", { alvo: "meu" });
        }

        // Alguém novo em pausa
        const novoPausado = idsAtuais.find(id => !ultimosIdsPausa.includes(id));
        if (novoPausado) {
            const opNovo = (pausa || []).find(p => p.id == novoPausado);
            window.dispararAlertas?.("alguem_entrou_pausa", {
                nome: opNovo?.nome || "um operador",
                alvo: "outro"
            });
        }

  // AVISO DE PAUSA > 20 MIN (NOVO)
        if (Array.isArray(pausa)) {
            pausa.forEach(op => {
                const id    = op.id;
                const tempo = op.tempo_pausa_seg ?? 0;

                if (tempo >= 1200) {
                    const jaAvisou = avisosPausaExpirada[id] || 0;

                    if (jaAvisou < 3) {
                        window.dispararAlertas?.("pausa_expirada", {
                            nome: op.nome,
                            alvo: "todos"
                        });
                        avisosPausaExpirada[id] = jaAvisou + 1;
                    }
                } else {
                    avisosPausaExpirada[id] = 0;
                }
            });
        }

        ultimosIdsPausa      = idsAtuais;
        ultimoTotalPausa     = totalPausaAtual;
        ultimoStatusOperador = statusAtual;
    }

    // ========================================================
    // FUNÇÃO AUXILIAR — FORMATAR SEGUNDOS
    // ========================================================
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


    // ========================================================
    // 4) LISTA DE PAUSA — COM CRONÔMETRO + EXCEDIDO
    //    tempo_pausa_seg vem do backend
    //    Não reseta ao atualizar painel
    // ========================================================
    function iniciarCronometroPausa() {
        if (intervaloPausa) {
            clearInterval(intervaloPausa);
            intervaloPausa = null;
        }

        intervaloPausa = setInterval(() => {
            // Cronômetro principal da pausa
            document.querySelectorAll(".tempo-pausa").forEach(el => {
                let t = parseInt(el.dataset.segundos || "0", 10);
                t++;
                el.dataset.segundos = t;
                el.textContent = formatarSegundos(t);
            });

            // Cronômetro do tempo excedido (acima de 20min)
            document.querySelectorAll(".tempo-excedido").forEach(ex => {
                let te = parseInt(ex.dataset.excedido || "0", 10);
                te++;
                ex.dataset.excedido = te;
                ex.textContent = formatarSegundos(te);
            });
        }, 1000);
    }

    function atualizarListaPausa(lista) {
        const box = document.getElementById("listaPausa");
        if (!box) return;

        if (!lista || lista.length === 0) {
            box.innerHTML = `<p class="lista-vazia">Nenhum operador está em pausa.</p>`;
            if (intervaloPausa) {
                clearInterval(intervaloPausa);
                intervaloPausa = null;
            }
            return;
        }

        box.innerHTML = lista.map(op => {
            const id = op.id;
            const backendSeg = parseInt(op.tempo_pausa_seg ?? 0, 10) || 0;

            // Mantém o maior valor entre o que já estava contando na tela (dataset)
            // e o que veio do backend, evitando reset visual
            const elAntigo = document.querySelector(`.tempo-pausa[data-id="${id}"]`);
            let baseSeg = backendSeg;
            if (elAntigo) {
                const atual = parseInt(elAntigo.dataset.segundos || "0", 10);
                if (atual > baseSeg) baseSeg = atual;
            }

            const expirado = baseSeg >= 1200; // 20min = 1200s
            let excedidoBase = 0;

            if (expirado) {
                const exAntigo = document.querySelector(`.tempo-excedido[data-id="${id}"]`);
                if (exAntigo) {
                    excedidoBase = parseInt(exAntigo.dataset.excedido || "0", 10) || 0;
                } else {
                    excedidoBase = baseSeg - 1200;
                }
            }

            return `
               <!-- <div class="linha-participante ${expirado ? "pausa-expirada" : ""}">-->
<!--<div class="linha-participante ${expirado ? "pausa-expirada" : ""} ${op.elider == 1 ? "lider" : ""}">-->
<div class="linha-participante ${expirado ? "pausa-expirada" : ""} ${op.elider == 1 ? "lider" : ""}"
     data-nome="${op.nome}">


                    <div class="linha-fila-topo">
                        <!-- <span class="nome">${op.nome}</span>-->
<span class="nome">
    ${op.elider == 1 ? '<span class="icone-lider">👑</span>' : ''}
    ${op.nome}
</span>

                    </div>

                    <div class="linha-fila-tempo">
                        <i class="fa-regular fa-clock"></i>
                        <span class="tempo-pausa"
                              data-id="${id}"
                              data-segundos="${baseSeg}">
                            ${formatarSegundos(baseSeg)}
                        </span>
                    </div>

                    ${
                        expirado
                        ? `
                            <div class="linha-fila-tempo excedido">
                                <i class="fa-solid fa-hourglass-end"></i>
                                <span class="tempo-excedido"
                                      data-id="${id}"
                                      data-excedido="${excedidoBase}">
                                    ${formatarSegundos(excedidoBase)}
                                </span>
                            </div>
                        `
                        : ""
                    }
                </div>
            `;
        }).join("");

        iniciarCronometroPausa();
inserirBotoesIndividuais(lista);
    }

    // ========================================================
    // 5) LISTA DA FILA — COM CRONÔMETRO
    //    tempo_espera_seg DEVE vir do backend
    //    posicao_fila DEVE vir do backend (para refletir mover 2º/último)
    //    Não reseta ao mover segundo/último nem ao atualizar painel
    // ========================================================
    function iniciarCronometroFila() {
        if (intervaloFila) {
            clearInterval(intervaloFila);
            intervaloFila = null;
        }

        intervaloFila = setInterval(() => {
            document.querySelectorAll(".tempo-fila").forEach(el => {
                let t = parseInt(el.dataset.segundos || "0", 10);
                t++;
                el.dataset.segundos = t;
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

        // Monta HTML, preservando o maior tempo entre backend e dataset
        box.innerHTML = lista.map(op => {
            const id = op.id;
            const backendSeg = parseInt(op.tempo_espera_seg ?? 0, 10) || 0;
            const posicao    = op.posicao_fila ?? "";

            const elAntigo = document.querySelector(`.tempo-fila[data-id="${id}"]`);
            let baseSeg = backendSeg;
            if (elAntigo) {
                const atual = parseInt(elAntigo.dataset.segundos || "0", 10);
                if (atual > baseSeg) baseSeg = atual;
            }

            return `
              <!--  <div class="linha-participante">-->
<div class="linha-participante" data-nome="${op.nome}">

                    <div class="linha-fila-topo">
                        <span class="posicao-fila">${posicao}º</span>
                      <!--  <span class="nome">${op.nome}</span>-->
<span class="nome">
    ${op.elider == 1 ? '<span class="icone-lider">👑</span>' : ''}
    ${op.nome}
</span>

                    </div>
                    <div class="linha-fila-tempo">
                        <i class="fa-regular fa-clock"></i>
                        <span class="tempo-fila"
                              data-id="${id}"
                              data-segundos="${baseSeg}">
                            ${formatarSegundos(baseSeg)}
                        </span>
                    </div>
                </div>
            `;
        }).join("");

        iniciarCronometroFila();
inserirBotoesIndividuais(lista);
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
/*
        box.innerHTML = lista.map(op => `
            <div class="linha-participante" data-nome="${op.nome}">
                <span class="nome">${op.nome}</span>
                <span class="bolinha-estado ${op.status}"></span>
            </div>
        `).join("");*/
box.innerHTML = lista.map(op => `
    <div class="linha-participante ${op.elider == 1 ? "lider" : ""}" data-nome="${op.nome}">
        <span class="nome">
            ${op.elider == 1 ? '<span class="icone-lider">👑</span>' : ''}
            ${op.nome}
        </span>
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
