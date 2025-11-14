/* ============================================================
   cronometro.js (v1.0)
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

        // Se já existe, não recria
        if (this.ativos[nome]) return;

        const inicio = inicioTimestamp
            ? new Date(inicioTimestamp).getTime()
            : Date.now();

        this.ativos[nome] = {
            inicio,
            tipo,
            timer: setInterval(() => this.atualizar(nome), 1000)
        };

        // salva localmente para retomada em recarregamento
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
    // Para o cronômetro, grava no banco e remove do localStorage
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

        this.exibir(nome, "00:00", "reset", 0);
    },

    // ------------------------------------------------------------
    // Exibe o cronômetro visualmente no item do operador
    // ------------------------------------------------------------
    exibir(nome, tempo, tipo, diff) {
        const item = document.querySelector(
            `.op-item strong:nth-child(1):contains("${nome}")`
        )
        ?.parentElement;

        if (!item) return;

        const cronDiv = item.querySelector(".cronometro");
        if (!cronDiv) return;

        cronDiv.textContent = tempo;

        cronDiv.className = "cronometro";

        if (tipo === "espera") cronDiv.classList.add("cron-espera");
        if (tipo === "pausa") {
            cronDiv.classList.add("cron-pausa");

            if (diff > this.limitePausa) {
                cronDiv.classList.remove("cron-pausa");
                cronDiv.classList.add("cron-excedido");
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
