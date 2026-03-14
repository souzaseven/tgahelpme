/* =========================================================
CENTRAL DE NOTIFICAÇÕES — SININHO
notifications.js
========================================================= */

const Notificacoes = (() => {

    const MAX_NOTIFICACOES = 50;
    let lista = JSON.parse(localStorage.getItem("notif_historico") || "[]");
    let painelAberto = false;

    /* ── Inicializar HTML ─────────────────────────────── */
    function init() {
        /* Sininho no DOM */
        const wrapper = document.createElement("div");
        wrapper.id = "notif-bell-wrap";
        wrapper.innerHTML = `
            <button id="notif-bell-btn" title="Notificações" aria-label="Notificações">
                <svg id="notif-bell-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                <span id="notif-badge" class="notif-badge hidden">0</span>
            </button>
            <div id="notif-painel" class="notif-painel" style="display:none">
                <div class="notif-header">
                    <span class="notif-titulo">Notificações</span>
                    <button class="notif-limpar" id="notif-limpar-btn" title="Limpar tudo">Limpar</button>
                </div>
                <div id="notif-lista" class="notif-lista">
                    <div class="notif-vazia">Nenhuma notificação</div>
                </div>
            </div>
        `;
        document.body.appendChild(wrapper);

        /* Eventos */
        document.getElementById("notif-bell-btn").addEventListener("click", (e) => {
            e.stopPropagation();
            togglePainel();
        });

        document.getElementById("notif-limpar-btn").addEventListener("click", (e) => {
            e.stopPropagation();
            limpar();
        });

        document.addEventListener("click", (e) => {
            if (!e.target.closest("#notif-bell-wrap")) fecharPainel();
        });

        injetarCSS();
        renderLista();
        atualizarBadge();
    }

    /* ── Adicionar notificação ────────────────────────── */
    function adicionar(nome, tipo) {
        const agora = Date.now();
        const item = {
            id:    agora + Math.random(),
            nome,
            tipo,
            ts:    agora,
            lida:  false
        };
        lista.unshift(item);
        if (lista.length > MAX_NOTIFICACOES) lista = lista.slice(0, MAX_NOTIFICACOES);
        salvar();
        renderLista();
        atualizarBadge();
        animarSininho();
    }

    /* ── Toggle painel ────────────────────────────────── */
    function togglePainel() {
        painelAberto ? fecharPainel() : abrirPainel();
    }

    function abrirPainel() {
        painelAberto = true;
        const painel = document.getElementById("notif-painel");
        if (painel) {
            painel.style.display = "flex";
            requestAnimationFrame(() => painel.classList.add("open"));
        }
        marcarTodasLidas();
    }

    function fecharPainel() {
        painelAberto = false;
        const painel = document.getElementById("notif-painel");
        if (painel) {
            painel.classList.remove("open");
            setTimeout(() => { if (!painelAberto) painel.style.display = "none"; }, 220);
        }
    }

    /* ── Marcar todas como lidas ──────────────────────── */
    function marcarTodasLidas() {
        lista.forEach(n => n.lida = true);
        salvar();
        atualizarBadge();
    }

    /* ── Limpar tudo ──────────────────────────────────── */
    function limpar() {
        lista = [];
        salvar();
        renderLista();
        atualizarBadge();
    }

    /* ── Renderizar lista ─────────────────────────────── */
    function renderLista() {
        const el = document.getElementById("notif-lista");
        if (!el) return;

        if (lista.length === 0) {
            el.innerHTML = `<div class="notif-vazia">Nenhuma notificação</div>`;
            return;
        }

        el.innerHTML = lista.map(n => {
            const icon  = n.tipo === "online" ? "🟢" : n.tipo === "offline" ? "🔴" : "🟡";
            const label = n.tipo === "online"  ? "entrou online"
                        : n.tipo === "offline" ? "ficou offline"
                        : "entrou em pausa";
            const hora  = new Date(n.ts).toLocaleTimeString("pt-BR", {
                hour: "2-digit", minute: "2-digit", second: "2-digit",
                timeZone: typeof TIMEZONE !== "undefined" ? TIMEZONE : "America/Cuiaba"
            });
            return `
                <div class="notif-item ${n.lida ? "" : "notif-nao-lida"} notif-tipo-${n.tipo}">
                    <span class="notif-item-icon">${icon}</span>
                    <div class="notif-item-body">
                        <span class="notif-item-nome">${n.nome}</span>
                        <span class="notif-item-label">${label}</span>
                    </div>
                    <span class="notif-item-hora">${hora}</span>
                </div>`;
        }).join("");
    }

    /* ── Badge de não lidas ───────────────────────────── */
    function atualizarBadge() {
        const badge    = document.getElementById("notif-badge");
        const naoLidas = lista.filter(n => !n.lida).length;
        if (!badge) return;
        if (naoLidas > 0) {
            badge.textContent = naoLidas > 99 ? "99+" : naoLidas;
            badge.classList.remove("hidden");
        } else {
            badge.classList.add("hidden");
        }
    }

    /* ── Animação do sininho ──────────────────────────── */
    function animarSininho() {
        const btn = document.getElementById("notif-bell-btn");
        if (!btn) return;
        btn.classList.remove("notif-ring");
        void btn.offsetWidth; // reflow
        btn.classList.add("notif-ring");
        setTimeout(() => btn.classList.remove("notif-ring"), 700);
    }

    /* ── Persistência ─────────────────────────────────── */
    function salvar() {
        try { localStorage.setItem("notif_historico", JSON.stringify(lista)); } catch(e) {}
    }

    /* ── CSS injetado ─────────────────────────────────── */
    function injetarCSS() {
        const style = document.createElement("style");
        style.textContent = `
        #notif-bell-wrap {
            position: fixed;
            top: 18px;
            right: 24px;
            z-index: 9999;
            font-family: inherit;
        }

        #notif-bell-btn {
            position: relative;
            background: #1a2332;
            border: 1px solid #2a3a50;
            border-radius: 10px;
            color: #8ab4d4;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s, color 0.15s, border-color 0.15s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }

        #notif-bell-btn:hover {
            background: #223044;
            color: #5ab4ff;
            border-color: #3a5a80;
        }

        #notif-bell-icon {
            width: 18px;
            height: 18px;
        }

        .notif-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: #e84040;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            border: 2px solid #0d1520;
            transition: transform 0.15s;
        }

        .notif-badge.hidden { display: none; }

        @keyframes notifRing {
            0%   { transform: rotate(0deg); }
            15%  { transform: rotate(18deg); }
            30%  { transform: rotate(-14deg); }
            45%  { transform: rotate(10deg); }
            60%  { transform: rotate(-6deg); }
            75%  { transform: rotate(3deg); }
            100% { transform: rotate(0deg); }
        }

        #notif-bell-btn.notif-ring #notif-bell-icon {
            animation: notifRing 0.7s ease;
            transform-origin: center top;
        }

        /* ── Painel ── */
        .notif-painel {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 320px;
            background: #111c2a;
            border: 1px solid #2a3a50;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.5);
            flex-direction: column;
            overflow: hidden;
            opacity: 0;
            transform: translateY(-8px) scale(0.97);
            transition: opacity 0.2s ease, transform 0.2s ease;
        }

        .notif-painel.open {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        .notif-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid #1e2e42;
            background: #0d1828;
        }

        .notif-titulo {
            font-size: 13px;
            font-weight: 600;
            color: #c8ddf0;
            letter-spacing: 0.03em;
        }

        .notif-limpar {
            background: none;
            border: 1px solid #2a3a50;
            border-radius: 6px;
            color: #6a8fb0;
            font-size: 11px;
            padding: 3px 8px;
            cursor: pointer;
            transition: color 0.15s, border-color 0.15s;
        }

        .notif-limpar:hover {
            color: #e84040;
            border-color: #e84040;
        }

        /* ── Lista ── */
        .notif-lista {
            max-height: 380px;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: #2a3a50 transparent;
        }

        .notif-lista::-webkit-scrollbar { width: 4px; }
        .notif-lista::-webkit-scrollbar-track { background: transparent; }
        .notif-lista::-webkit-scrollbar-thumb { background: #2a3a50; border-radius: 2px; }

        .notif-vazia {
            padding: 28px 16px;
            text-align: center;
            color: #3a5a7a;
            font-size: 13px;
        }

        /* ── Item ── */
        .notif-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-bottom: 1px solid #162030;
            transition: background 0.12s;
        }

        .notif-item:last-child { border-bottom: none; }
        .notif-item:hover { background: #162233; }

        .notif-nao-lida {
            background: #0f1e30;
        }

        .notif-nao-lida::before {
            content: "";
            position: absolute;
            left: 0;
            width: 3px;
            height: 100%;
            background: #5ab4ff;
        }

        .notif-item { position: relative; }

        .notif-tipo-online.notif-nao-lida::before   { background: #22c55e; }
        .notif-tipo-offline.notif-nao-lida::before  { background: #e84040; }
        .notif-tipo-pausado.notif-nao-lida::before  { background: #f59e0b; }

        .notif-item-icon {
            font-size: 15px;
            flex-shrink: 0;
        }

        .notif-item-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 1px;
            min-width: 0;
        }

        .notif-item-nome {
            font-size: 12px;
            font-weight: 600;
            color: #c8ddf0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .notif-item-label {
            font-size: 11px;
            color: #5a8ab0;
        }

        .notif-item-hora {
            font-size: 10px;
            color: #3a5a7a;
            flex-shrink: 0;
            font-variant-numeric: tabular-nums;
        }
        `;
        document.head.appendChild(style);
    }

    /* ── API pública ──────────────────────────────────── */
    return { init, adicionar };

})();

/* Inicializar quando o DOM estiver pronto */
document.addEventListener("DOMContentLoaded", () => Notificacoes.init());