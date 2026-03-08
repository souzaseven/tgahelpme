/* ============================================================
   operador.js — FASE 9 (CORRIGIDO + PROBLEMA 2 ADICIONADO)

   CORREÇÃO 1: Cronômetro agora usa inicio_espera do BANCO DE DADOS
               e não reseta quando alguém troca de posição.

   NOVO (Problema 2): Quando o operador está em 1ª posição e
               há vaga disponível, inicia contagem de 5 minutos.
               Se não decidir (entrar em pausa ou trocar posição),
               é rebaixado automaticamente para o final da fila.
============================================================ */

console.log("%c[OPERADOR.JS] FASE 9 CORRIGIDO carregado.", "color:#38bdf8;font-weight:bold;");

// ============================================================
// ESTADO DO COUNTDOWN (Problema 2)
// ============================================================
let _countdownTimer    = null;   // setInterval do countdown
let _countdownAtivo    = false;  // evita iniciar duplo
let _countdownOperador = null;   // ID do operador monitorado

/* ============================================================
   INSERIR BOTÕES EM CADA CARD
============================================================ */
function inserirBotoesIndividuais(operadoresPainel = []) {

    const dados = JSON.parse(localStorage.getItem("tga_operador"));
    if (!dados) return;

    const isAdmin  = dados.is_admin == 1;
    const isLider  = dados.elider == 1;
    const idLogado = Number(dados.id);

    const cards = document.querySelectorAll(".linha-participante");

    cards.forEach(card => {

        const id = Number(card.dataset.id);
        if (!id || isNaN(id)) return;
        const operador = operadoresPainel.find(o => o.id === id);
        if (!operador) return;

        // Remove botões anteriores
        const antigo = card.querySelector(".op-botoes");
        if (antigo) antigo.remove();

        if (!isAdmin && !isLider && operador.id !== idLogado) {
            return;
        }

        const box = document.createElement("div");
        box.className = "op-botoes";

        // ============================================================
        // OPERADOR EM ESPERA (fila)
        // ============================================================
        if (operador.status === "espera") {

            if (isAdmin || isLider) {
                box.innerHTML = `
                    <button class="op-btn op-decidir"
                            onclick="abrirModalDecidir(${operador.id}, true)">
                        <i class="fas fa-list-check"></i> Decidir (Supervisão)
                    </button>
                `;
            } else if (operador.id === idLogado) {
                box.innerHTML = `
                    <button class="op-btn op-decidir"
                            onclick="abrirModalDecidir(${operador.id}, false)">
                        <i class="fas fa-list-check"></i> Decidir na fila
                    </button>
                `;
            } else {
                box.innerHTML = `
                    <button class="op-btn op-sair-fila"
                            onclick="sairFilaIndividual(${operador.id})">
                        <i class="fas fa-circle-xmark"></i> Sair da fila
                    </button>
                `;
            }
        }

        // ============================================================
        // OPERADOR ATIVO
        // ============================================================
        else if (operador.status === "ativo") {
            box.innerHTML = `
                <button class="op-btn op-espera"
                        onclick="entrarFilaIndividual(${operador.id})">
                    <i class="fas fa-clock"></i> Ir para fila de Espera
                </button>
                <button class="op-btn op-chat"
                        onclick="abrirChat(${operador.id})">
                    <i class="fas fa-comments"></i> Chat
                </button>
            `;
        }

        // ============================================================
        // OPERADOR EM PAUSA
        // ============================================================
        else if (operador.status === "pausa") {
            if (operador.id === idLogado || isAdmin || isLider) {
                box.innerHTML = `
                    <button class="op-btn op-voltar">
                        <i class="fas fa-play"></i> Sair da Pausa
                    </button>
                `;
            }
        }

        card.appendChild(box);
    });
}

/* ============================================================
   AÇÕES INDIVIDUAIS
============================================================ */
window.entrarFilaIndividual = async id => {
    if (_countdownAtivo && _countdownOperador === id) {
        pararCountdown(id);
    }
    await fetch("../backend/entrar_fila.php", {
        method: "POST",
        body: new URLSearchParams({ id })
    });
    setTimeout(() => window.carregarPainel?.(), 250);
};

window.sairFilaIndividual = async id => {
    if (_countdownAtivo && _countdownOperador === id) {
        pararCountdown(id);
    }
    await fetch("../backend/sair_fila.php", {
        method: "POST",
        body: new URLSearchParams({ id })
    });
    setTimeout(() => window.carregarPainel?.(), 250);
};

// ============================================================
// BOTÃO SAIR DA PAUSA
// ============================================================
document.addEventListener("click", async e => {

    const btn = e.target.closest(".op-btn.op-voltar");
    if (!btn) return;

    const card = btn.closest(".linha-participante");
    const id   = Number(card?.dataset.id);
    if (!id) return;

    try {
        const resp = await fetch("../backend/sair_pausa.php", {
            method: "POST",
            body: new URLSearchParams({ id })
        });

        const r = await resp.json();

        console.log("[SAIR_PAUSA] retorno:", r);

        if (!r.success) {
            toastAviso(r.erro || "Falha ao sair da pausa.", "warning");
            return;
        }

        toastAviso("Você saiu da pausa.", "success");
        window.carregarPainel?.();

    } catch (err) {
        console.error("[SAIR_PAUSA] erro:", err);
        toastAviso("Erro ao comunicar com o servidor.", "error");
    }
});

/* ============================================================
   PROBLEMA 1 — CRONÔMETRO CORRETO
   Uso: iniciarCronometroEspera(segundosEsperando)
   com o valor que vem do consultar_posicao_fila.php
============================================================ */

let _timerInterval = null;
let _segundosAtual = 0;

window.iniciarCronometroEspera = function(segundosBanco, elementoId = "cronometro-espera") {
    const diff = Math.abs(segundosBanco - _segundosAtual);

    if (!_timerInterval || diff > 10) {
        _segundosAtual = segundosBanco;
    }
    if (_timerInterval) return;

    _timerInterval = setInterval(() => {
        _segundosAtual++;
        const el = document.getElementById(elementoId);
        if (el) {
            el.textContent = formatarTempo(_segundosAtual);
        }
    }, 1000);
};

window.pararCronometroEspera = function() {
    clearInterval(_timerInterval);
    _timerInterval = null;
    _segundosAtual = 0;
};

function formatarTempo(segundos) {
    const h = Math.floor(segundos / 3600);
    const m = Math.floor((segundos % 3600) / 60);
    const s = segundos % 60;
    if (h > 0) return `${pad(h)}:${pad(m)}:${pad(s)}`;
    return `${pad(m)}:${pad(s)}`;
}

function pad(n) {
    return String(n).padStart(2, "0");
}

/* ============================================================
   PROBLEMA 2 — COUNTDOWN DE 5 MINUTOS
   Chamado pelo painel quando:
     - operador logado está em 1ª posição
     - há vaga disponível (vagas_pausa > 0)
============================================================ */

const COUNTDOWN_SEGUNDOS = 5 * 60; // 5 minutos

window.verificarCountdownVaga = function(operadorId, posicao, vagasPausa, equipe) {
    const deveIniciar = posicao === 1 && vagasPausa > 0;

    if (!deveIniciar) {
        if (_countdownAtivo && _countdownOperador === operadorId) {
            pararCountdown();
            removerBannerCountdown();
        }
        return;
    }

    // Já está rodando para este operador? Não duplica
    if (_countdownAtivo && _countdownOperador === operadorId) {
        return;
    }

    iniciarCountdown(operadorId, equipe);
};

let _countdownRestante = 0; // expõe o restante atual para refreshes externos

function iniciarCountdown(operadorId, equipe) {
    console.log("[COUNTDOWN] Iniciando para operador:", operadorId);

    _countdownAtivo    = true;
    _countdownOperador = operadorId;
    let restante       = COUNTDOWN_SEGUNDOS;
    _countdownRestante = restante;

    // Grava no banco para todos verem
    fetch("../backend/salvar_countdown.php", {
        method: "POST",
        body: new URLSearchParams({ operador_id: operadorId, acao: "iniciar" })
    }).catch(() => {});

    exibirBannerCountdown(restante);

    _countdownTimer = setInterval(async () => {
        restante--;
        _countdownRestante = restante;
        atualizarBannerCountdown(restante);

        if (restante <= 0) {
            pararCountdown();
            removerBannerCountdown();
            await rebaixarPosicao(operadorId, equipe);
        }
    }, 1000);
}

function pararCountdown(operadorId = null) {
    clearInterval(_countdownTimer);
    _countdownTimer    = null;
    _countdownAtivo    = false;
    _countdownOperador = null;
    _countdownRestante = 0;

    // Limpa no banco
    const id = operadorId || (JSON.parse(localStorage.getItem("tga_operador"))?.id);
    if (id) {
        fetch("../backend/salvar_countdown.php", {
            method: "POST",
            body: new URLSearchParams({ operador_id: id, acao: "parar" })
        }).catch(() => {});
    }

    console.log("[COUNTDOWN] Parado.");
}

async function rebaixarPosicao(operadorId, equipe) {
    console.log("[COUNTDOWN] Tempo esgotado. Rebaixando operador:", operadorId);

    try {
        const resp = await fetch("../backend/rebaixar_posicao.php", {
            method: "POST",
            body: new URLSearchParams({ operador_id: operadorId, equipe })
        });
        const r = await resp.json();

        if (r.success && r.rebaixou) {
            toastAviso("⏰ Tempo esgotado! Sua posição foi alterada na fila.", "warning");
        }

        window.carregarPainel?.();

    } catch (err) {
        console.error("[COUNTDOWN] Erro ao rebaixar:", err);
    }
}

// ============================================================
// BANNER DO COUNTDOWN
// ============================================================
function exibirBannerCountdown(restante) {
    removerBannerCountdown();

    const banner = document.createElement("div");
    banner.id = "banner-countdown-vaga";
    banner.style.cssText = `
        position: fixed;
        top: 0; left: 0; right: 0;
        z-index: 9999;
        background: linear-gradient(135deg, #f59e0b, #ef4444);
        color: #fff;
        text-align: center;
        padding: 12px 20px;
        font-size: 15px;
        font-weight: bold;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    `;
    banner.innerHTML = `
        <i class="fas fa-triangle-exclamation"></i>
        <span>
            Há uma vaga disponível! Você é o 1º da fila.
            Decida em <span id="countdown-tempo">${formatarTempo(restante)}</span>
            ou será alterada a sua posição.
        </span>
        <i class="fas fa-triangle-exclamation"></i>
    `;

    document.body.prepend(banner);
}

function atualizarBannerCountdown(restante) {
    const el = document.getElementById("countdown-tempo");
    if (el) el.textContent = formatarTempo(restante);

    // Pisca nos últimos 60 segundos
    const banner = document.getElementById("banner-countdown-vaga");
    if (banner && restante <= 60) {
        banner.style.opacity = restante % 2 === 0 ? "0.7" : "1";
    }
}

function removerBannerCountdown() {
    const el = document.getElementById("banner-countdown-vaga");
    if (el) el.remove();
}