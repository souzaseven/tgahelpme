/* =============================
   atualizador_listas.js (v2)
============================= */

console.log("%c[ATUALIZADOR] módulo carregado", "color:#4ade80;font-weight:bold;");

/* =============================
   Atualização das listas
============================= */
function atualizarListasPainel() {

    const listaPausas = document.getElementById("listaPausas");
    const listaFila   = document.getElementById("listaFila");

    if (!listaPausas || !listaFila) return;

    /* =============================
       LISTAR PAUSAS
    ============================== */
    fetch("backend/fila/listar_pausas.php")
        .then(r => r.json())
        .then(resp => {

            const contadorPausa = document.getElementById("contador-pausa");

            if (!resp.success || !resp.pausas || resp.pausas.length === 0) {
                listaPausas.innerHTML = `<div class="lista-vazia">Nenhum operador em pausa.</div>`;
                if (contadorPausa) contadorPausa.textContent = 0;
                return;
            }

            listaPausas.innerHTML = "";
            if (contadorPausa) contadorPausa.textContent = resp.pausas.length;

            resp.pausas.forEach(p => {

                /* ⏱ Ajuste do cronômetro */
                if (window.Cronometros) {
                    let inicioMs = Date.parse(p.inicio);
                    if (isNaN(inicioMs)) inicioMs = Date.now();
                    Cronometros.pausa[p.id] = inicioMs;
                }

                const linha = document.createElement("div");
                linha.className = "linha-participante";

                linha.innerHTML = `
                    <span class="bolinha-estado" style="background:#38bdf8"></span>
                    <span class="nome-op">${p.nome}</span>
                    <span class="tempo-pausa" data-id="${p.id}">--</span>
                `;

                listaPausas.appendChild(linha);
            });
        })
        .catch(() => {
            listaPausas.innerHTML = `<div class="lista-vazia">Erro ao carregar pausas.</div>`;
        });


    /* =============================
       LISTAR FILA
    ============================== */

    if (!window.EQUIPE) {
        console.warn("[ATUALIZADOR] EQUIPE não definida.");
        return;
    }

    fetch(`backend/fila/listar_fila.php?equipe=${encodeURIComponent(EQUIPE)}`)
        .then(r => r.json())
        .then(resp => {

            const contadorFila = document.getElementById("contador-fila");

            if (!resp.success || !resp.fila || resp.fila.length === 0) {
                listaFila.innerHTML = `<div class="lista-vazia">Ninguém na fila.</div>`;
                if (contadorFila) contadorFila.textContent = 0;
                return;
            }

            listaFila.innerHTML = "";
            if (contadorFila) contadorFila.textContent = resp.fila.length;

            resp.fila.forEach(f => {

                /* ⏱ Ajuste do cronômetro */
                if (window.Cronometros) {
                    let inicioMs = Date.parse(f.inicio);
                    if (isNaN(inicioMs)) inicioMs = Date.now();
                    Cronometros.fila[f.id] = inicioMs;
                }

                const linha = document.createElement("div");
                linha.className = "linha-participante";

                linha.innerHTML = `
                    <span class="bolinha-estado" style="background:#eab308"></span>
                    <span class="posicao-fila">#${f.posicao}</span>
                    <span class="nome-op">${f.nome}</span>
                    <span class="tempo-fila" data-id="${f.id}">--</span>
                `;

                listaFila.appendChild(linha);
            });
        })
        .catch(() => {
            listaFila.innerHTML = `<div class="lista-vazia">Erro ao carregar fila.</div>`;
        });
}


/* =============================
   Atualização automática (2s)
============================= */
setInterval(atualizarListasPainel, 2000);
