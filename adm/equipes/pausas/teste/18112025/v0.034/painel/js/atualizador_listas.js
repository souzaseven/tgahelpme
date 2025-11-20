/*atualizador_listas.js*/ 

console.log("%c[ATUALIZADOR] módulo carregado", "color:#4ade80;font-weight:bold;");

// ============================
// Atualização das listas
// ============================
function atualizarListasPainel() {

    // Se o painel ainda não existe, não faz nada
    const listaPausas = document.getElementById("listaPausas");
    const listaFila   = document.getElementById("listaFila");

    if (!listaPausas || !listaFila) {
        // painel ainda não carregou
        return;
    }

    // ============================
    // LISTAR PAUSAS
    // ============================
    fetch("backend/fila/listar_pausas.php")
        .then(r => r.json())
        .then(resp => {

            if (!resp.success || !resp.pausas) {
                listaPausas.innerHTML = `<div class="lista-vazia">Nenhuma pausa encontrada.</div>`;
                return;
            }

            listaPausas.innerHTML = "";

            resp.pausas.forEach(p => {

                // Registrar cronômetro suave
                if (window.Cronometros) {
                    Cronometros.pausa[p.id] = new Date(p.inicio).getTime();
                }

                const linha = document.createElement("div");
                linha.className = "linha-participante";
                linha.innerHTML = `
                    <span class="bolinha-estado" style="background:#38bdf8"></span>
                    <span>${p.nome}</span>
                    <span class="tempo-pausa" data-id="${p.id}" style="font-size:12px;color:#94a3b8;">
                        --
                    </span>
                `;

                listaPausas.appendChild(linha);
            });
        })
        .catch(() => {
            listaPausas.innerHTML = `<div class="lista-vazia">Erro ao carregar pausas.</div>`;
        });


    // ============================
    // LISTAR FILA
    // ============================

    if (!window.EQUIPE) {
        console.warn("[ATUALIZADOR] EQUIPE não definida ainda.");
        return;
    }

    fetch(`backend/fila/listar_fila.php?equipe=${encodeURIComponent(EQUIPE)}`)
        .then(r => r.json())
        .then(resp => {

            if (!resp.success || !resp.fila) {
                listaFila.innerHTML = `<div class="lista-vazia">Ninguém na fila.</div>`;
                return;
            }

            listaFila.innerHTML = "";

            resp.fila.forEach(f => {

                // Registrar cronômetro suave
                if (window.Cronometros) {
                    Cronometros.fila[f.id] = new Date(f.inicio).getTime();
                }

                const linha = document.createElement("div");
                linha.className = "linha-participante";
                linha.innerHTML = `
                    <span class="bolinha-estado" style="background:#eab308"></span>
                    <span>#${f.posicao}</span>
                    <span>${f.nome}</span>
                    <span class="tempo-fila" data-id="${f.id}" style="font-size:12px;color:#94a3b8;">
                        --
                    </span>
                `;
                listaFila.appendChild(linha);
            });
        })
        .catch(() => {
            listaFila.innerHTML = `<div class="lista-vazia">Erro ao carregar fila.</div>`;
        });
}


// ============================
// Atualização automática (2s)
// ============================
setInterval(atualizarListasPainel, 2000);
