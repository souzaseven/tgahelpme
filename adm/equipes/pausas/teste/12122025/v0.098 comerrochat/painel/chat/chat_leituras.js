/*==========================================================
  chat_leituras.js — Controle de leitura (multi navegador)
==========================================================*/

console.log(
    "%c[CHAT LEITURAS] Módulo carregado",
    "color:#22c55e;font-weight:bold;"
);

window.ChatLeituras = {

    // { contato_id: ultimo_id_lido }
    leituras: {},

    /*======================================================
      CARREGAR LEITURAS DO BANCO (por operador)
    ======================================================*/
    async carregar() {
        const op = getOperador();
        if (!op) return;

        try {
            const resp = await fetch(
                `chat/listar_leituras.php?operador_id=${op.id}&t=${Date.now()}`
            );
            const r = await resp.json();

            if (r.success) {
                // 🔹 Converte todos os valores para números
                this.leituras = {};
                for (const [cid, lid] of Object.entries(r.leituras || {})) {
                    this.leituras[cid] = Number(lid);
                }

                console.log("[CHAT LEITURAS] Leituras carregadas:", this.leituras);
            } else {
                console.warn("[CHAT LEITURAS] Backend retornou erro:", r.erro);
            }

        } catch (e) {
            console.warn("[CHAT LEITURAS] Falha ao carregar leituras", e);
        }
    },

    /*======================================================
      ÚLTIMO ID LIDO POR CONTATO
    ======================================================*/
    getUltimoLido(contatoId) {
        return Number(this.leituras[contatoId] || 0);
    },

    /*======================================================
      MARCAR COMO LIDO (LOCAL + BANCO)
    ======================================================*/
    async marcarComoLido(contatoId, ultimoId) {
        const op = getOperador();
        if (!op || !Number.isInteger(contatoId) || !Number.isInteger(ultimoId) || ultimoId <= 0) return;

        // 🔹 Atualiza local imediatamente (UX)
        this.leituras[contatoId] = ultimoId;

        try {
            await fetch("chat/marcar_lido.php", {
                method: "POST",
                body: new URLSearchParams({
                    operador_id: op.id,
                    contato_id: contatoId,
                    ultimo_id: ultimoId
                })
            });

            console.log(
                `[CHAT LEITURAS] Marcado como lido → operador=${op.id}, contato=${contatoId}, ultimo_id=${ultimoId}`
            );

        } catch (e) {
            console.warn("[CHAT LEITURAS] Falha ao salvar leitura no banco", e);
        }
    },

    /*======================================================
      CALCULAR NÃO LIDAS POR CONTATO
      Retorno: { contato_id: quantidade }
    ======================================================*/
    calcularNaoLidas(msgs) {
        const op = getOperador();
        if (!op || !Array.isArray(msgs)) return {};

        const contagem = {};

        msgs.forEach(m => {
            if (Number(m.para_id) !== Number(op.id)) return;

            const contatoId = Number(m.de_id);
            const ultimoLido = this.getUltimoLido(contatoId);
            if (!contatoId || !Number.isInteger(ultimoLido)) return;

            if (Number(m.id) > ultimoLido) {
                contagem[contatoId] = (contagem[contatoId] || 0) + 1;
            }
        });

        return contagem;
    },

    /*======================================================
      TOTAL GERAL DE NÃO LIDAS (badge topo)
    ======================================================*/
    getTotalNaoLidas(msgs) {
        const porContato = this.calcularNaoLidas(msgs);
        return Object.values(porContato).reduce((a, b) => a + b, 0);
    }
};
