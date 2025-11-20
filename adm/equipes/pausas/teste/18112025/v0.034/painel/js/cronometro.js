/* cronometros.js */
console.log("%c[CRONOMETROS] módulo carregado", "color:#facc15;font-weight:bold;");

/*
  Estrutura:
  Cronometros.fila[idOperador] = timestamp;
  Cronometros.pausa[idOperador] = timestamp;
*/

window.Cronometros = {
    fila: {},
    pausa: {},

    formatar(ms) {
        let total = Math.floor(ms / 1000);
        let h = Math.floor(total / 3600);
        let m = Math.floor((total % 3600) / 60);
        let s = total % 60;

        if (h > 0) {
            return `${String(h).padStart(2,"0")}:${String(m).padStart(2,"0")}:${String(s).padStart(2,"0")}`;
        }
        return `${String(m).padStart(2,"0")}:${String(s).padStart(2,"0")}`;
    }
};

/* 
   Animação suave: roda 10x por segundo (100ms) 
   sem travar a interface e sem "piscadas".
*/
setInterval(() => {
    const agora = Date.now();

    // Atualiza tempos da FILA
    document.querySelectorAll(".tempo-fila").forEach(el => {
        const id = el.dataset.id;
        const inicio = Cronometros.fila[id];
        if (!inicio) return;

        const diff = agora - inicio;
        el.textContent = Cronometros.formatar(diff);
    });

    // Atualiza tempos da PAUSA
    document.querySelectorAll(".tempo-pausa").forEach(el => {
        const id = el.dataset.id;
        const inicio = Cronometros.pausa[id];
        if (!inicio) return;

        const diff = agora - inicio;
        el.textContent = Cronometros.formatar(diff);
    });

}, 150); // cronômetro suave
