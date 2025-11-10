// ===========================================================
// contador_espera.js (v1.1)
// Sistema de contagem de tempo de espera na fila
// ===========================================================

class ContadorEspera {
  constructor() {
    this.contadores = new Map();
    this.intervalos = new Map();
  }

  inicializar(estado) {
    this.pararTodos();
    const esperas = estado.filter(p => p.status === 'espera');
    esperas.forEach(p => this.iniciarContador(p.nome, p.inicio_espera));
  }

  iniciarContador(nome, inicio) {
    if (!inicio) return;
    if (this.intervalos.has(nome)) this.pararContador(nome);

    const atualizar = () => {
      const tempo = this.calcularSegundos(inicio);
      this.contadores.set(nome, tempo);
      this.atualizarDisplay(nome, tempo);
    };

    atualizar();
    const id = setInterval(atualizar, 1000);
    this.intervalos.set(nome, id);
  }

  calcularSegundos(inicioStr) {
    const inicio = new Date(inicioStr + 'Z');
    const agora = new Date();
    const diff = Math.floor((agora - inicio) / 1000);
    return diff < 0 ? 0 : diff;
  }

  atualizarDisplay(nome, segundos) {
    const formatado = this.formatar(segundos);
    const item = this.encontrarItem(nome);
    if (!item) return;

    let el = item.querySelector('.contador-espera');
    if (!el) {
      el = document.createElement('div');
      el.className = 'contador-espera';
      el.innerHTML = `
        <div class="contador-tempo">
          <i class="fas fa-clock"></i>
          <span class="tempo-decorrido">${formatado}</span>
        </div>`;
      item.querySelector('.item-info')?.appendChild(el);
    } else {
      el.querySelector('.tempo-decorrido').textContent = formatado;
    }
  }

  pararContador(nome) {
    clearInterval(this.intervalos.get(nome));
    this.intervalos.delete(nome);
    this.contadores.delete(nome);
  }

  pararTodos() {
    this.intervalos.forEach(id => clearInterval(id));
    this.intervalos.clear();
    this.contadores.clear();
  }

  encontrarItem(nome) {
    return [...document.querySelectorAll('#lista-espera .item')]
      .find(el => el.querySelector('.item-nome')?.textContent.trim() === nome);
  }

  formatar(seg) {
    const m = Math.floor(seg / 60);
    const s = seg % 60;
    return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
  }

  atualizarEstado(estado) {
    const esperas = estado.filter(p => p.status === 'espera');
    const ativos = new Set(this.contadores.keys());

    esperas.forEach(p => {
      if (!this.contadores.has(p.nome)) this.iniciarContador(p.nome, p.inicio_espera);
    });
    ativos.forEach(nome => {
      if (!esperas.some(p => p.nome === nome)) this.pararContador(nome);
    });
  }
}

// Inicialização global
window.contadorEspera = new ContadorEspera();
