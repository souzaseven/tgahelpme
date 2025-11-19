/*atualizador_listas.js*/ 

console.log("%c[ATUALIZADOR] módulo carregado", "color:#4ade80;font-weight:bold;");

function atualizarListasPainel() {
    // Atualiza pausas
    fetch("backend/fila/listar_pausas.php")
        .then(r => r.json())
        .then(resp => {
            if (resp.success && resp.pausas) {
                const lista = document.getElementById("listaPausas");
                lista.innerHTML = "";

resp.pausas.forEach(p => {
    // Registrar cronômetro suave
    Cronometros.pausa[p.id] = new Date(p.inicio).getTime();

    const linha = document.createElement("div");
    linha.className = "linha-participante";
    linha.innerHTML = `
        <span class="bolinha-estado" style="background:#38bdf8"></span>
        <span>${p.nome}</span>
        <span class="tempo-pausa" data-id="${p.id}" style="font-size:12px;color:#94a3b8;">
            --
        </span>
    `;
    lista.appendChild(linha);
});

            }
        });

    // Atualiza fila
    fetch(`backend/fila/listar_fila.php?equipe=${encodeURIComponent(EQUIPE)}`)
        .then(r => r.json())
        .then(resp => {
            if (resp.success) {
                const lista = document.getElementById("listaFila");
                lista.innerHTML = "";

resp.fila.forEach(f => {
    // Registrar cronômetro suave
    Cronometros.fila[f.id] = new Date(f.inicio).getTime();

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
    lista.appendChild(linha);
});

            }
        });
}

// Atualização automática a cada 2s
setInterval(atualizarListasPainel, 2000);
