/* ============================================================
   cronometro.js (v1.1)
   Módulo independente de contagem de tempo para Pausa e Fila
   ============================================================ */

console.log("%c[cronometro.js] carregado", "color:#00ff88;font-weight:bold;");

const Cronometro = {
    ativos: {},     // lista de cronômetros ativos: { nome: {inicio, tipo, timer}}
    limitePausa: 20 * 60, // 20 min em segundos

    // ------------------------------------------------------------
    // Inicia o cronômetro (espera ou pausa)
    // ------------------------------------------------------------
    iniciar(nome, tipo, inicioTimestamp = null) {
        if (!nome || !tipo) return;

        // já existe → não recria
        if (this.ativos[nome]) return;

        const inicio = inicioTimestamp
            ? new Date(inicioTimestamp).getTime()
            : Date.now();

        this.ativos[nome] = {
            inicio,
            tipo,
            timer: setInterval(() => this.atualizar(nome), 1000)
        };

        // salva no localStorage
        localStorage.setItem(`cron_${nome}`, JSON.stringify({
            inicio,
            tipo
        }));

        this.atualizar(nome);
    },

    // ------------------------------------------------------------
    // Atualiza contador do operador
    // ------------------------------------------------------------
    atualizar(nome) {
        const data = this.ativos[nome];
        if (!data) return;

        const diff = Math.floor((Date.now() - data.inicio) / 1000);
        const tempoFmt = this.formatar(diff);

        this.exibir(nome, tempoFmt, data.tipo, diff);
    },

    // ------------------------------------------------------------
    // Para o cronômetro e envia ao backend
    // ------------------------------------------------------------
    parar(nome, enviarParaBanco = true) {
        const data = this.ativos[nome];
        if (!data) return;

        clearInterval(data.timer);

        const fim = Date.now();
        const duracao = Math.floor((fim - data.inicio) / 1000);

        if (enviarParaBanco) {
            fetch("./php/cronometro.php", {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify({
                    nome,
                    tipo: data.tipo,
                    inicio: data.inicio,
                    fim,
                    duracao
                })
            }).catch(()=>{});
        }

        delete this.ativos[nome];
        localStorage.removeItem(`cron_${nome}`);

        // ❗ NÃO mostrar 00:00 para não piscar
        this.exibir(nome, "", "reset", 0);
    },

    // ------------------------------------------------------------
    // Exibe o cronômetro visualmente
    // ------------------------------------------------------------
    exibir(nome, tempo, tipo, diff) {

        // Encontrar operador de forma compatível
        const todos = document.querySelectorAll(".op-item");
        let item = null;

        todos.forEach(el => {
            const strong = el.querySelector("strong");
            if (!strong) return;

            const texto = strong.textContent.trim().toLowerCase();
            if (texto === nome.trim().toLowerCase()) {
                item = el;
            }
        });

        if (!item) return;

        let cronDiv = item.querySelector(".cronometro");

        // cria caso não exista
        if (!cronDiv) {
            cronDiv = document.createElement("div");
            cronDiv.className = "cronometro";
            item.appendChild(cronDiv);
        }

        // evita mostrar 00:00 no reset
        if (tipo !== "reset") {
            cronDiv.textContent = tempo;
        }

        cronDiv.className = "cronometro";

        // estilos
        if (tipo === "espera") cronDiv.classList.add("cron-espera");

        if (tipo === "pausa") {
            cronDiv.classList.add("cron-pausa");

            if (diff > this.limitePausa) {
                cronDiv.classList.remove("cron-pausa");
                cronDiv.classList.add("cron-excedido");

                item.classList.add("expirado-limite");
            }
        }
    },

    // Formata em mm:ss
    formatar(seg) {
        const m = String(Math.floor(seg / 60)).padStart(2, "0");
        const s = String(seg % 60).padStart(2, "0");
        return `${m}:${s}`;
    },

    // ------------------------------------------------------------
    // Retoma contadores após recarregar a página
    // ------------------------------------------------------------
    retomar() {
        Object.keys(localStorage).forEach(key => {
            if (!key.startsWith("cron_")) return;
            try {
                const data = JSON.parse(localStorage.getItem(key));
                if (data.inicio && data.tipo) {
                    const nome = key.replace("cron_", "");
                    this.iniciar(nome, data.tipo, data.inicio);
                }
            } catch {}
        });
    }
};

// Bootstrap
document.addEventListener("DOMContentLoaded", () => {
    Cronometro.retomar();
});
