// ============================================================
// painel.js — FASE 6 + FASE 7 + FASE 8/9 (VERSÃO FINAL LIMPA)
// ============================================================

let intervaloFila = null;
let intervaloPausa = null;

let ultimoStatusOperador = null;
let ultimaPosicaoFila = null;
let estavaNaFila = false;
let estavaEmPausa = false;

let ultimoTotalPausa = 0;
let ultimosIdsPausa = [];

let avisosPausaExpirada = {};
let avisoAutoDisparado = {};

let evoluxProgressInterval = null;

// ============================================================
// DOM READY
// ============================================================
document.addEventListener("DOMContentLoaded", () => {

    console.log("[PAINEL] painel.js carregado");

    const dadosOperador = JSON.parse(localStorage.getItem("tga_operador"));
    if (!dadosOperador) { window.location.href = "../login/login.php"; return; }

    const isAdmin = dadosOperador.is_admin == 1;
    const nomeOperador = dadosOperador.operador || dadosOperador.nome || dadosOperador.usuario || "Operador";

    const boxOperador = document.getElementById("boxOperadorLogado");
    if (boxOperador) {
        const eLiderLogado = dadosOperador.elider == 1;
        boxOperador.innerHTML = `
            <div class="op-logado ${eLiderLogado ? "lider" : ""}">
                ${eLiderLogado ? '<span class="icone-lider">👑</span>' : ""}
                <i class="fas fa-user"></i> ${nomeOperador}
            </div>`;
    }

    const subTitulo    = document.getElementById("subtituloEquipe");
    const tituloEquipe = document.getElementById("nomeEquipeTitulo");
    if (subTitulo)    subTitulo.textContent    = dadosOperador.equipe;
    if (tituloEquipe) tituloEquipe.textContent = dadosOperador.equipe;

    // ========================================================
    // ORDENAÇÃO DA EQUIPE COMPLETA
    // ========================================================
    let ordemAtual = localStorage.getItem("tga_ordem_equipe") || "cadastro";

    function injetarBotoesOrdem() {
        if (document.getElementById("btn-ordem-equipe")) return;
        const box = document.getElementById("listaEquipeCompleta");
        if (!box) return;
        const container = box.closest(".bloco-lista") || box.parentElement;
        if (!container) return;

        const wrap = document.createElement("div");
        wrap.id = "btn-ordem-equipe";
        wrap.innerHTML = `
            <span style="font-size:12px;color:#94a3b8;margin-right:6px;">Ordenar:</span>
            <button data-ordem="cadastro"   class="btn-ordem">📋 Cadastro</button>
            <button data-ordem="alfabetica" class="btn-ordem">🔤 A–Z</button>
            <button data-ordem="pausa"      class="btn-ordem">☕ Pausas por último</button>
        `;
        wrap.style.cssText = "display:flex;align-items:center;gap:6px;padding:8px 0 4px;flex-wrap:wrap;";
        container.insertBefore(wrap, box);

        wrap.querySelectorAll(".btn-ordem").forEach(btn => btn.classList.toggle("ativo", btn.dataset.ordem === ordemAtual));
        wrap.querySelectorAll(".btn-ordem").forEach(btn => {
            btn.addEventListener("click", () => {
                ordemAtual = btn.dataset.ordem;
                localStorage.setItem("tga_ordem_equipe", ordemAtual);
                wrap.querySelectorAll(".btn-ordem").forEach(b => b.classList.remove("ativo"));
                btn.classList.add("ativo");
                carregarPainel();
            });
        });
    }

    function ordenarEquipe(lista) {
        const copia = [...lista];
        if (ordemAtual === "alfabetica") {
            return copia.sort((a, b) => (a.nome || "").localeCompare(b.nome || "", "pt-BR"));
        }
        if (ordemAtual === "pausa") {
            // Ordena: 0 pausas → 1 pausa → 2 pausas (quem tirou as duas fica por último)
            return copia.sort((a, b) => {
                const pa = ((a.pausas_hoje?.manha ? 1 : 0) + (a.pausas_hoje?.tarde ? 1 : 0));
                const pb = ((b.pausas_hoje?.manha ? 1 : 0) + (b.pausas_hoje?.tarde ? 1 : 0));
                if (pa !== pb) return pa - pb;
                return (a.nome || "").localeCompare(b.nome || "", "pt-BR");
            });
        }
        // cadastro = ordem por ID
        return copia.sort((a, b) => a.id - b.id);
    }

    // ========================================================
    // CARREGAR PAINEL
    // ========================================================
    async function carregarPainel() {
        try {
            const resp = await fetch("../backend/obter_status_equipe.php", {
                method: "POST",
                body: new URLSearchParams({ equipe: dadosOperador.equipe })
            });
            if (!resp.ok) throw new Error("Erro HTTP " + resp.status);
            const dados = await resp.json();
            if (!dados.success) { console.warn("[PAINEL] Erro backend:", dados.erro); return; }

            const { pausa, fila, equipe_completa } = dados;

            atualizarListaPausa(pausa);
            atualizarFila(fila);
            atualizarEquipeCompleta(equipe_completa);
            analisarMudancas(fila, equipe_completa, pausa);

        } catch (e) {
            console.error("[PAINEL] Erro carregarPainel:", e);
        }
    }

    window.carregarPainel = carregarPainel;

    // ========================================================
    // ALERTAS INTELIGENTES
    // ========================================================
    function analisarMudancas(fila, equipeCompleta, pausa) {
        const nomeLogado = (dadosOperador.nome || nomeOperador || "").trim().toLowerCase();
        const operador = equipeCompleta.find(o =>
            (isAdmin && o.id == dadosOperador.id) ||
            (!isAdmin && (o.nome || "").trim().toLowerCase() === nomeLogado)
        );
        if (!operador) return;

        const statusAtual = operador.status || "ativo";

        const estaNaFila = statusAtual === "espera";
        if (estaNaFila && !estavaNaFila) dispararAlertas?.("entrou_fila", { alvo: "meu" });
        if (!estaNaFila && estavaNaFila) dispararAlertas?.("saiu_fila",   { alvo: "meu" });
        estavaNaFila = estaNaFila;

        const emPausa = statusAtual === "pausa";
        if (emPausa && !estavaEmPausa) dispararAlertas?.("entrou_pausa", { nome: operador.nome, alvo: "meu" });
        if (!emPausa && estavaEmPausa) {
            dispararAlertas?.("saiu_pausa", { nome: operador.nome, alvo: "meu" });
            if (isAdmin && operador.id) avisoAutoDisparado[operador.id] = false;
        }
        estavaEmPausa = emPausa;

        if (estaNaFila) {
            const reg = fila.find(f => f.id == dadosOperador.id);
            if (reg) {
                const pos = reg.posicao_fila;
                if (ultimaPosicaoFila !== null && pos !== ultimaPosicaoFila) {
                    dispararAlertas?.(pos == 1 ? "virou_primeiro" : "posicao_mudou", { posicao: pos, alvo: "meu" });
                }
                ultimaPosicaoFila = pos;
                const vagasDisponiveis = Math.max(0, 2 - pausa.length);
                window.verificarCountdownVaga?.(Number(dadosOperador.id), pos, vagasDisponiveis, dadosOperador.equipe);
            }
        } else {
            ultimaPosicaoFila = null;
            window.verificarCountdownVaga?.(Number(dadosOperador.id), 999, 0, dadosOperador.equipe);
        }

        const totalPausaAtual = pausa.length;
        if (estaNaFila && totalPausaAtual < 2 && ultimoTotalPausa >= 2) dispararAlertas?.("vaga_abriu", { alvo: "meu" });
        ultimoTotalPausa = totalPausaAtual;
    }

    // ========================================================
    // UTIL
    // ========================================================
    function formatarSegundos(seg) {
        const s = Math.max(0, parseInt(seg, 10) || 0);
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        const r = s % 60;
        return `${h.toString().padStart(2,"0")}:${m.toString().padStart(2,"0")}:${r.toString().padStart(2,"0")}`;
    }

    function formatarMin(seg) {
        if (!seg || seg < 60) return seg + "s";
        const m = Math.floor(seg / 60);
        const s = seg % 60;
        return s > 0 ? m + "min " + s + "s" : m + "min";
    }

    // ========================================================
    // BADGES PAUSAS
    // ========================================================
    function badgesPausasHoje(pausasHoje, operadorId, nomeOp) {
        if (!pausasHoje) return "";
        const badges = [];
        if (pausasHoje.manha) {
            const hora = pausasHoje.manha_hora ? ` · ${pausasHoje.manha_hora}` : "";
            badges.push(`<span class="badge-pausa-hoje badge-manha" onclick="abrirHistoricoPausa(${operadorId}, '${nomeOp}', ${JSON.stringify(pausasHoje)})">☕ Lanche da manhã${hora}</span>`);
        }
        if (pausasHoje.tarde) {
            const hora = pausasHoje.tarde_hora ? ` · ${pausasHoje.tarde_hora}` : "";
            badges.push(`<span class="badge-pausa-hoje badge-tarde" onclick="abrirHistoricoPausa(${operadorId}, '${nomeOp}', ${JSON.stringify(pausasHoje)})">🥤 Lanche da tarde${hora}</span>`);
        }
        if (pausasHoje.total_seg > 0) {
            badges.push(`<span class="badge-pausa-hoje badge-total">⏱ ${formatarMin(pausasHoje.total_seg)} em pausa</span>`);
        }
        return badges.length ? `<span class="badges-pausas-hoje">${badges.join("")}</span>` : "";
    }

    window.abrirHistoricoPausa = function(id, nome, pausas) {
        const linhas = [];
        if (pausas.manha) {
            const dur = pausas.manha_dur ? ` — ${formatarMin(pausas.manha_dur)}` : "";
            linhas.push(`<div class="hist-linha"><span class="hist-icon badge-manha">☀️ Lanche manhã</span><span class="hist-detalhe">${pausas.manha_hora || "--:--"}${dur}</span></div>`);
        }
        if (pausas.tarde) {
            const dur = pausas.tarde_dur ? ` — ${formatarMin(pausas.tarde_dur)}` : "";
            linhas.push(`<div class="hist-linha"><span class="hist-icon badge-tarde">🌙 Lanche tarde</span><span class="hist-detalhe">${pausas.tarde_hora || "--:--"}${dur}</span></div>`);
        }
        if (pausas.total_seg > 0) linhas.push(`<div class="hist-total">Total: ${formatarMin(pausas.total_seg)} em pausa hoje</div>`);

        let modal = document.getElementById("modal-historico-pausa");
        if (!modal) {
            modal = document.createElement("div");
            modal.id = "modal-historico-pausa";
            modal.onclick = e => { if (e.target === modal) modal.remove(); };
            document.body.appendChild(modal);
        }
        modal.innerHTML = `
            <div class="modal-hist-box">
                <div class="modal-hist-header">
                    <span>📋 Pausas de hoje — ${nome}</span>
                    <button onclick="document.getElementById('modal-historico-pausa').remove()">✕</button>
                </div>
                <div class="modal-hist-corpo">${linhas.join("") || "<p>Nenhuma pausa registrada hoje.</p>"}</div>
            </div>`;
        modal.style.display = "flex";
    };

    // ========================================================
    // PAUSA
    // ========================================================
    function iniciarCronometroPausa() {
        clearInterval(intervaloPausa);
        intervaloPausa = setInterval(() => {
            document.querySelectorAll(".tempo-pausa").forEach(el => {
                el.dataset.segundos++;
                el.textContent = formatarSegundos(el.dataset.segundos);
            });
        }, 1000);
    }

    function atualizarListaPausa(lista) {
        const box = document.getElementById("listaPausa");
        if (!box) return;

        if (!lista.length) {
            box.innerHTML = `<p class="lista-vazia">Nenhum operador está em pausa.</p>`;
            clearInterval(intervaloPausa);
            return;
        }

        box.innerHTML = lista.map(op => {
            const expirado = op.status === "expirado";
            const forcada  = op.forcada && op.status === "pausa";
            const alertar  = expirado || forcada;
            const styleAlerta = alertar
                ? 'style="border:2px solid #ef4444!important;background:rgba(239,68,68,0.15)!important;box-shadow:0 0 16px rgba(239,68,68,0.4)!important;"'
                : "";
            return `
            <div class="linha-participante status-${op.status}${op.forcada ? ' forcada' : ''}" data-id="${op.id}" ${styleAlerta}>
                <span class="nome">
                    ${op.elider ? "👑 " : ""}${op.nome}
                    ${isAdmin ? `<small class="op-id">[#${op.id}]</small>` : ""}
                </span>
                ${badgesPausasHoje(op.pausas_hoje, op.id, op.nome)}
                ${isAdmin ? `
                    <button class="${op.forcada ? 'btn-forcar-saida' : 'btn-forcar-pausa'}"
                        onclick="${op.forcada ? `forcarSaidaPausa(${op.id}, '${op.nome}')` : `forcarPausa(${op.id}, '${op.nome}')`}">
                        ${op.forcada ? '▶' : '⏸'}
                    </button>
                ` : ""}
                <span class="tempo-pausa" data-segundos="${op.tempo_pausa_seg}">
                    ${formatarSegundos(op.tempo_pausa_seg)}
                </span>
            </div>`;
        }).join("");

        iniciarCronometroPausa();
        iniciarPiscaAlerta();
        requestAnimationFrame(() => { inserirBotoesIndividuais(lista); });
    }

    // Pisca permanente para alertas
    let _piscaTimer = null;
    let _piscaEstado = false;

    function iniciarPiscaAlerta() {
        if (_piscaTimer) return;
        _piscaTimer = setInterval(() => {
            const alertas = document.querySelectorAll(".linha-participante.status-expirado, .linha-participante.status-pausa.forcada");
            if (!alertas.length) return;
            _piscaEstado = !_piscaEstado;
            alertas.forEach(el => {
                el.style.background = _piscaEstado
                    ? "rgba(239,68,68,0.28)"
                    : "rgba(239,68,68,0.08)";
                el.style.boxShadow = _piscaEstado
                    ? "0 0 20px rgba(239,68,68,0.6)"
                    : "0 0 8px rgba(239,68,68,0.2)";
                el.style.border = "2px solid #ef4444";
            });
        }, 700);
    }

    // ========================================================
    // FILA
    // ========================================================
    function iniciarCronometroFila() {
        clearInterval(intervaloFila);
        intervaloFila = setInterval(() => {
            document.querySelectorAll(".tempo-fila").forEach(el => {
                el.dataset.segundos++;
                el.textContent = formatarSegundos(el.dataset.segundos);
            });
            document.querySelectorAll(".countdown-card-tempo").forEach(el => {
                const seg = Math.max(0, (parseInt(el.dataset.segundos) || 0) - 1);
                el.dataset.segundos = seg;
                el.textContent = `muda de posição em: ${formatarSegundos(seg)}`;
                if (seg <= 0) el.style.display = "none";
            });
        }, 1000);
    }

    function atualizarFila(lista) {
        const box = document.getElementById("listaFila");
        if (!box) return;

        if (!lista.length) {
            box.innerHTML = `<p class="lista-vazia">Nenhum operador na fila.</p>`;
            clearInterval(intervaloFila);
            return;
        }

        box.innerHTML = lista.map(op => `
            <div class="linha-participante status-${op.status}" data-id="${op.id}">
                <span class="posicao-fila">${op.posicao_fila}º</span>
                <span class="nome">
                    ${op.elider ? "👑 " : ""}${op.nome}
                    ${isAdmin ? `<small class="op-id">[#${op.id}]</small>` : ""}
                </span>
                ${badgesPausasHoje(op.pausas_hoje, op.id, op.nome)}
                ${isAdmin ? `
                    <button class="btn-forcar-pausa"
                        onclick="forcarPausa(${op.id}, '${op.nome}')">
                        ⏸
                    </button>
                ` : ""}
                <span class="tempo-fila" data-segundos="${op.tempo_espera_seg}">
                    ${formatarSegundos(op.tempo_espera_seg)}
                </span>
                ${op.countdown_seg != null ? `
                    <span class="countdown-card-tempo"
                          data-segundos="${op.countdown_seg}"
                          style="font-size:12px;color:#f59e0b;font-weight:600;margin-left:6px;white-space:nowrap;">
                        muda de posição em: ${formatarSegundos(op.countdown_seg)}
                    </span>
                ` : ""}
            </div>
        `).join("");

        iniciarCronometroFila();
        requestAnimationFrame(() => { inserirBotoesIndividuais(lista); });
    }

    // ========================================================
    // EQUIPE COMPLETA
    // ========================================================
    function atualizarEquipeCompleta(lista) {
        const box = document.getElementById("listaEquipeCompleta");
        if (!box) return;

        injetarBotoesOrdem();

        const listaOrdenada = ordenarEquipe(lista);

        box.innerHTML = listaOrdenada.map(op => `
            <div class="linha-participante status-${op.status}" data-id="${op.id}">
                <span class="nome">
                    ${op.elider ? "👑 " : ""}${op.nome}
                    ${isAdmin ? `<small class="op-id">[#${op.id}]</small>` : ""}
                </span>
                ${badgesPausasHoje(op.pausas_hoje, op.id, op.nome)}
                ${isAdmin ? `
                    <button class="btn-forcar-pausa"
                        onclick="forcarPausa(${op.id}, '${op.nome}')">
                        ⏸
                    </button>
                ` : ""}
                <span class="bolinha-estado ${op.status}"></span>
            </div>
        `).join("");

        requestAnimationFrame(() => { inserirBotoesIndividuais(lista); });
    }

    // ========================================================
    // EVOLUX LOADER
    // ========================================================
    window.mostrarEvoluxLoader = texto => {
        document.getElementById("evolux-loader")?.classList.remove("hidden");
        document.getElementById("evolux-loader-text").textContent = texto;
    };
    window.ocultarEvoluxLoader = () => {
        document.getElementById("evolux-loader")?.classList.add("hidden");
    };

    window.forcarSaidaPausa = async function (operadorId, nome) {
        if (!isAdmin) return;
        if (!confirm(`Forçar saída da pausa para ${nome}?`)) return;
        try {
            mostrarEvoluxLoader(`Forçando saída da pausa para ${nome}...`);
            const resp = await fetch("../backend/forcar_saida_pausa.php", {
                method: "POST",
                body: new URLSearchParams({ id: operadorId })
            });
            const r = await resp.json();
            ocultarEvoluxLoader();
            if (!r.success) { alert(r.erro || "Erro ao forçar saída da pausa."); return; }
            alert(`✅ ${nome} saiu da pausa.`);
            carregarPainel();
        } catch (e) {
            ocultarEvoluxLoader();
            alert("Erro ao comunicar com servidor.");
            console.error(e);
        }
    };

    window.forcarPausa = async function (operadorId, nome) {
        if (!isAdmin) return;
        if (!confirm(`Forçar pausa para ${nome}?`)) return;
        try {
            mostrarEvoluxLoader(`Forçando pausa para ${nome}...`);
            const resp = await fetch("../backend/forcar_pausa_banco.php", {
                method: "POST",
                body: new URLSearchParams({ id: operadorId })
            });
            const r = await resp.json();
            ocultarEvoluxLoader();
            if (!r.success) { alert(r.erro || "Erro ao forçar pausa."); return; }
            if (r.ja_estava_em_pausa) {
                alert(`⚠ ${nome} já estava em pausa no Evolux.\nControle sincronizado.`);
            } else {
                alert(`✅ Pausa forçada para ${nome}.`);
            }
            carregarPainel();
        } catch (e) {
            ocultarEvoluxLoader();
            alert("Erro ao comunicar com servidor.");
            console.error(e);
        }
    };

    // ========================================================
    // INIT
    // ========================================================
    carregarPainel();
    setInterval(carregarPainel, 8000);
});

/* ============================================================
   CSS DINÂMICO
   ============================================================ */
(function injetarCSS() {
    if (document.getElementById("css-badges-pausas")) return;
    const s = document.createElement("style");
    s.id = "css-badges-pausas";
    s.textContent = `
        /* ── BADGES PAUSAS ── */
        .badges-pausas-hoje {
            display: inline-flex;
            gap: 4px;
            flex-wrap: wrap;
            margin-left: 6px;
            align-items: center;
        }
        .badge-pausa-hoje {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 20px;
            white-space: nowrap;
            cursor: pointer;
            transition: opacity 0.15s;
        }
        .badge-pausa-hoje:hover { opacity: 0.75; }
        .badge-manha {
            background: rgba(251,191,36,0.15);
            color: #fbbf24;
            border: 1px solid rgba(251,191,36,0.4);
        }
        .badge-tarde {
            background: rgba(139,92,246,0.15);
            color: #a78bfa;
            border: 1px solid rgba(139,92,246,0.4);
        }
        .badge-total {
            background: rgba(99,102,241,0.1);
            color: #818cf8;
            border: 1px solid rgba(99,102,241,0.3);
            cursor: default;
        }

        /* ── BORDAS STATUS ── */
        .linha-participante.status-ativo    { border-left: 3px solid #22c55e; }
        .linha-participante.status-espera   { border-left: 3px solid #f59e0b; }
        .linha-participante.status-pausa    { border-left: 3px solid #f97316; }
        .linha-participante.status-expirado .nome,
        .linha-participante.status-expirado .tempo-pausa {
            color: #fca5a5 !important;
            font-weight: 700;
        }

        /* ── BOTÕES DE ORDENAÇÃO ── */
        .btn-ordem {
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.05);
            color: #94a3b8;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-ordem:hover { background: rgba(255,255,255,0.1); color: #e2e8f0; }
        .btn-ordem.ativo {
            background: rgba(99,102,241,0.2);
            border-color: rgba(99,102,241,0.5);
            color: #a5b4fc;
        }

        /* ── MODAL HISTÓRICO ── */
        #modal-historico-pausa {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.55);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .modal-hist-box {
            background: #1e2535;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            width: 360px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        .modal-hist-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 18px;
            background: rgba(255,255,255,0.05);
            border-bottom: 1px solid rgba(255,255,255,0.08);
            font-weight: 600;
            font-size: 14px;
            color: #e2e8f0;
        }
        .modal-hist-header button {
            background: none; border: none; color: #94a3b8;
            cursor: pointer; font-size: 16px; padding: 0 4px;
        }
        .modal-hist-header button:hover { color: #ef4444; }
        .modal-hist-corpo {
            padding: 18px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .hist-linha {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            background: rgba(255,255,255,0.04);
            border-radius: 8px;
        }
        .hist-icon {
            font-size: 12px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
        }
        .hist-detalhe { font-size: 13px; color: #94a3b8; }
        .hist-total {
            text-align: center;
            font-size: 13px;
            font-weight: 600;
            color: #818cf8;
            padding-top: 6px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
    `;
    document.head.appendChild(s);
})();