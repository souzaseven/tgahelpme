function iniciarPainelEquipe({ equipe, destino }) {
    const container = document.querySelector(destino);
    if (!container) return;

    // Evita múltiplas execuções se já renderizado
    if (container.dataset.loaded === "true") return;
    container.dataset.loaded = "true";

    container.innerHTML = `
        <h2>Painel da Equipe <strong>${equipe}</strong></h2>

        <div class="painel-dashboard">
            <div class="card">
                <h3>Operadores em Pausa</h3>
                <div class="lista-participantes lista-pausa"><p class="lista-vazia">Carregando…</p></div>
            </div>
            <div class="card">
                <h3>Fila de Espera</h3>
                <div class="lista-participantes lista-fila"><p class="lista-vazia">Carregando…</p></div>
            </div>
        </div>

        <div class="card card-participantes">
            <h3>Equipe Completa</h3>
            <div class="lista-participantes lista-equipe"><p class="lista-vazia">Carregando…</p></div>
        </div>
    `;

    const elPausa = container.querySelector(".lista-pausa");
    const elFila = container.querySelector(".lista-fila");
    const elEquipe = container.querySelector(".lista-equipe");

    async function carregar() {
        try {
            const resp = await fetch("../backend/obter_status_equipe.php", {
                method: "POST",
                body: new URLSearchParams({ equipe })
            });

            const dados = await resp.json();
            console.log(`[PAINEL] [${equipe}] Dados atualizados`, dados);

            if (!dados.success) {
                elPausa.innerHTML = `<p class="lista-vazia">Erro ao carregar pausa.</p>`;
                elFila.innerHTML = `<p class="lista-vazia">Erro ao carregar fila.</p>`;
                elEquipe.innerHTML = `<p class="lista-vazia">Erro ao carregar equipe.</p>`;
                return;
            }

            const { pausa, fila, equipe_completa } = dados;

            elPausa.innerHTML = pausa.length === 0
                ? `<p class="lista-vazia">Nenhum operador está em pausa.</p>`
                : pausa.map(op => `
                    <div class="linha-participante ${op.elider == 1 ? "lider" : ""}">
                        <span class="nome">${op.elider == 1 ? "👑 " : ""}${op.nome}</span>
                        <span class="tempo-pausa">${formatarSegundos(op.tempo_pausa_seg)}</span>
                    </div>
                `).join("");

            elFila.innerHTML = fila.length === 0
                ? `<p class="lista-vazia">Nenhum operador na fila.</p>`
                : fila.map(op => `
                    <div class="linha-participante">
                        <span class="posicao-fila">${op.posicao_fila}º</span>
                        <span class="nome">${op.elider == 1 ? "👑 " : ""}${op.nome}</span>
                    </div>
                `).join("");

            elEquipe.innerHTML = equipe_completa.length === 0
                ? `<p class="lista-vazia">Nenhum operador encontrado.</p>`
                : equipe_completa.map(op => `
                    <div class="linha-participante ${op.elider == 1 ? "lider" : ""}">
                        <span class="nome">${op.elider == 1 ? "👑 " : ""}${op.nome}</span>
                        <span class="bolinha-estado ${op.status}"></span>
                    </div>
                `).join("");

        } catch (e) {
            console.error(`[PAINEL] [${equipe}] Erro ao carregar painel:`, e);
            elPausa.innerHTML = `<p class="lista-vazia">Erro ao comunicar com o servidor.</p>`;
            elFila.innerHTML = "";
            elEquipe.innerHTML = "";
        }
    }

    carregar();
    setInterval(carregar, 8000);
}
