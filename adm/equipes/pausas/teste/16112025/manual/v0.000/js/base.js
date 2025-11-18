// ======================================================================
// BASE.JS — Versão otimizada e reestruturada
// Controle de sidebar, botões, telas e carregamento de equipes
// ======================================================================

// Aguarda carregamento do DOM
document.addEventListener("DOMContentLoaded", () => {

    // ==================================================================
    // BOTÃO DE ÁUDIO
    // ==================================================================
    const audioBtn = document.getElementById("audio-btn");
    audioBtn?.addEventListener("click", function () {
        this.classList.toggle("active");
        const icon = this.querySelector("i");

        icon.className = this.classList.contains("active")
            ? "fas fa-volume-mute"
            : "fas fa-volume-up";
    });

    // ==================================================================
    // BOTÃO DE NOTIFICAÇÕES
    // ==================================================================
    const notificationBtn = document.getElementById("notification-btn");
    notificationBtn?.addEventListener("click", function () {
        this.classList.toggle("active");
        const badge = this.querySelector(".notification-badge");

        if (this.classList.contains("active")) {
            badge.textContent = "0";
            badge.style.display = "none";
        } else {
            badge.textContent = "3";
            badge.style.display = "flex";
        }
    });

    // ==================================================================
    // TOGGLE DA SIDEBAR
    // ==================================================================
    const toggleSidebar = document.getElementById("toggle-sidebar");
    const mainContainer = document.getElementById("main-container");

    toggleSidebar?.addEventListener("click", function () {
        mainContainer.classList.toggle("sidebar-collapsed");

        const icon = this.querySelector("i");
        icon.className = mainContainer.classList.contains("sidebar-collapsed")
            ? "fas fa-chevron-right"
            : "fas fa-chevron-left";
    });

    // ==================================================================
    // CONTADOR DE TEMPO EM TEMPO REAL
    // ==================================================================
    setInterval(() => {
        document.querySelectorAll(".item-status").forEach(el => {
            if (
                el.classList.contains("status-paused") ||
                el.classList.contains("status-waiting")
            ) {
                const currentTime = parseInt(el.textContent);
                el.textContent = (currentTime + 1) + " min";
            }
        });
    }, 60000);
});

// ======================================================================
// SISTEMA DE TELAS — Alternância entre Painel e Equipes
// ======================================================================

function abrirTela(id) {
    // esconde TELAS específicas
    document.querySelectorAll(".tela").forEach(t => t.classList.remove("active"));

    // destaca menu ativo
    document.querySelectorAll(".nav-item").forEach(i => i.classList.remove("active"));

    // quando abrir tela-equipe → mostrar somente ela
    if (id === "tela-equipe") {
        document.getElementById("tela-equipe").classList.add("active");
        document.querySelector(".main-content").style.display = "none";
        document.querySelector(".nav-item-equipe")?.classList.add("active");
    }
    // painel principal
    else {
        document.querySelector(".main-content").style.display = "block";
        document.getElementById("tela-equipe").classList.remove("active");
        document.querySelector(".nav-item-home")?.classList.add("active");
    }
}

// ======================================================================
// EVENTOS DO MENU
// ======================================================================

// Abrir tela EQUIPES
document.querySelector(".nav-item-equipe")?.addEventListener("click", () => {
    abrirTela("tela-equipe");
    carregarEquipes();
});

// Abrir PAINEL PRINCIPAL
document.querySelector(".nav-item-home")?.addEventListener("click", () => {
    abrirTela(null);
});

// ======================================================================
// CARREGAR EQUIPES (tela-equipe)
// ======================================================================

async function carregarEquipes() {
    const container = document.getElementById("lista-equipes");
    container.innerHTML = "<p>Carregando...</p>";

    try {
        const response = await fetch("php/listar_operadores.php");
        const dados = await response.json();

        if (!dados.success) {
            container.innerHTML = "<p>Erro ao carregar equipes.</p>";
            return;
        }

        container.innerHTML = "";

        dados.equipes.forEach(eq => {
            const bloco = document.createElement("div");
            bloco.className = "bloco-lider";

            bloco.innerHTML = `
                <h3>${eq.lider} — <small>${eq.fila}</small></h3>
                ${eq.operadores
                    .map(
                        op => `
                    <div class="operador-item">
                        <strong>${op.nome}</strong> —
                        <span class="status-${op.status}">${op.status}</span>
                    </div>`
                    )
                    .join("")}
            `;

            container.appendChild(bloco);
        });

    } catch (e) {
        container.innerHTML = "<p>Erro de conexão.</p>";
    }
}
