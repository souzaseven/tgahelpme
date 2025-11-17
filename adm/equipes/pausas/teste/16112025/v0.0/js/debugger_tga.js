/* ======================================================================
   TGA DEBUGGER PRO v7.1 — Invisível + Acesso Secreto para Admin
   ====================================================================== */

(function() {

    // =============================================================
    // 0) ATIVAR SOMENTE PARA ADMIN
    // =============================================================
    function isAdmin() {
        const raw = localStorage.getItem("operador_nome") || "";
        const nome = raw.toLowerCase().trim();

        const ADM = [
            "anderson",
            "anderson souza",
            "anderson de souza",
            "admin"
        ];

        return ADM.includes(nome);
    }

    if (!isAdmin()) {
        console.log("%c[Debugger] Oculto para operadores.", "color:#888");
        return; 
    }

    console.log("%c[Debugger] Admin detectado. Modo secreto ativado.", "color:#0f0");

    // =============================================================
    // 🔐 O painel só abre com CTRL+SHIFT+D
    // =============================================================
    let painelCriado = false;

    document.addEventListener("keydown", (e) => {
        if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === "d") {
            if (!painelCriado) {
                criarDebugger();
                painelCriado = true;
            } else {
                const box = document.getElementById("tga-devtools");
                if (box) box.style.display = box.style.display === "none" ? "flex" : "none";
            }
        }
    });

    // =============================================================
    // 1) FUNÇÃO PRINCIPAL DE CRIAÇÃO DO PAINEL
    // =============================================================
    function criarDebugger() {

    const html = `
    <div id="tga-devtools" style="display:none;">
        <div class="tga-header">
            <span>🧩 TGA Debugger PRO</span>
            <div class="tga-actions">
                <button id="dbg-min">─</button>
                <button id="dbg-close">✕</button>
            </div>
        </div>

        <div class="tga-tabs">
            <button class="active" data-tab="console">Console</button>
            <button data-tab="network">Rede</button>
            <button data-tab="state">Estado</button>
            <button data-tab="fila">Fila</button>
            <button data-tab="performance">Performance</button>
            <button data-tab="dom">DOM</button>
            <button data-tab="errors">Erros</button>
            <button data-tab="system">Sistema</button>
        </div>

        <div class="tga-content">
            <div id="tab-console" class="tab active"></div>
            <div id="tab-network" class="tab"></div>
            <div id="tab-state" class="tab"></div>
            <div id="tab-fila" class="tab"></div>
            <div id="tab-performance" class="tab"></div>
            <div id="tab-dom" class="tab"></div>
            <div id="tab-errors" class="tab"></div>
            <div id="tab-system" class="tab"></div>
        </div>

        <div class="tga-resize"></div>
    </div>
    `;

    const wrap = document.createElement("div");
    wrap.innerHTML = html;
    document.body.appendChild(wrap);

    document.getElementById("tga-devtools").style.display = "flex";

    // =============================================================
    // 2) CSS ESTILO CHROME
    // =============================================================
    const css = `
    #tga-devtools {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 650px;
        height: 420px;
        background: #0e141b;
        color: #dce6f3;
        font-family: Consolas, monospace;
        border: 1px solid #2a3c4e;
        border-radius: 8px 8px 0 0;
        z-index: 999999;
        display: flex;
        flex-direction: column;
        box-shadow: 0 0 20px rgba(0,0,0,0.5);
    }
    .tga-header {
        background: #111a24;
        padding: 6px 10px;
        font-weight: bold;
        color: #7bb4ff;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: move;
        user-select: none;
        border-bottom: 1px solid #1f2d40;
    }
    .tga-tabs {
        display: flex;
        background: #121c27;
        border-bottom: 1px solid #223144;
    }
    .tga-tabs button {
        flex: 1;
        background: transparent;
        border: none;
        padding: 6px;
        color: #a9c2dd;
        cursor: pointer;
        font-size: 12px;
        border-right: 1px solid #1f2a38;
    }
    .tga-tabs button.active {
        background: #1b2a3b;
        color: white;
        font-weight: 600;
    }
    .tga-content {
        flex: 1;
        overflow-y: auto;
        padding: 10px;
        font-size: 12px;
    }
    .tab { display:none; }
    .tab.active { display:block; }
    .log-line {
        border-bottom: 1px solid rgba(255,255,255,0.05);
        padding: 3px;
        word-break: break-word;
    }
    .log-info { color: #73c9ff; }
    .log-warn { color: #ffe083; }
    .log-error { color: #ff6b6b; }
    `;
    const style = document.createElement("style");
    style.innerHTML = css;
    document.head.appendChild(style);



    // =============================================================
    // 3) TROCA DE ABAS
    // =============================================================
    document.querySelectorAll(".tga-tabs button").forEach(btn => {
        btn.onclick = () => {
            document.querySelectorAll(".tga-tabs button").forEach(b => b.classList.remove("active"));
            btn.classList.add("active");

            const target = btn.dataset.tab;

            document.querySelectorAll(".tab").forEach(t => t.classList.remove("active"));
            document.getElementById("tab-" + target).classList.add("active");
        };
    });

    // =============================================================
    // 4) INTERCEPTAÇÃO DE LOGS
    // =============================================================
    function addLog(type, text) {
        const area = document.getElementById("tab-console");
        const div = document.createElement("div");
        div.className = `log-line log-${type}`;
        div.textContent = `[${new Date().toLocaleTimeString()}] ${text}`;
        area.appendChild(div);
        area.scrollTop = area.scrollHeight;
    }

    const orig = {
        log: console.log,
        warn: console.warn,
        error: console.error,
        info: console.info
    };

    console.log = (...a) => { addLog("log", a.join(" ")); orig.log(...a); };
    console.warn = (...a) => { addLog("warn", a.join(" ")); orig.warn(...a); };
    console.error = (...a) => { addLog("error", a.join(" ")); orig.error(...a); };
    console.info = (...a) => { addLog("info", a.join(" ")); orig.info(...a); };

    // =============================================================
    // 5) DRAG DO PAINEL
    // =============================================================
    (function enableDrag() {
        const box = document.getElementById("tga-devtools");
        const header = document.querySelector(".tga-header");

        let dragging = false, offsetX = 0, offsetY = 0;

        header.onmousedown = (e) => {
            dragging = true;
            offsetX = e.clientX - box.offsetLeft;
            offsetY = e.clientY - box.offsetTop;
        };
        document.onmousemove = (e) => {
            if (!dragging) return;
            box.style.left = (e.clientX - offsetX) + "px";
            box.style.top = (e.clientY - offsetY) + "px";
        };
        document.onmouseup = () => dragging = false;
    })();

    // =============================================================
    // 6) REDIMENSIONAR
    // =============================================================
    const resizer = document.querySelector(".tga-resize");
    let resizing = false, startY, startH;

    resizer.onmousedown = (e) => {
        resizing = true;
        startY = e.clientY;
        startH = wrap.offsetHeight;
    };
    document.onmousemove = (e) => {
        if (!resizing) return;
        wrap.style.height = (startH - (e.clientY - startY)) + "px";
    };
    document.onmouseup = () => resizing = false;

    // =============================================================
    // 7) FECHAR E MINIMIZAR
    // =============================================================
    document.getElementById("dbg-close").onclick = () => {
        document.getElementById("tga-devtools").style.display = "none";
    };
    document.getElementById("dbg-min").onclick = () => {
        const c = document.querySelector(".tga-content");
        c.style.display = c.style.display === "none" ? "block" : "none";
    };

} // fim criarDebugger()

})(); // fim IIFE
