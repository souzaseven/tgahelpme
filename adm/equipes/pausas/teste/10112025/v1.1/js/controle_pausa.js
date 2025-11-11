// ============================================================
// controle_pausa.js (v4.2) - Controle de Pausa com Filtro de Equipes
// ============================================================

console.log("%c[Controle de Pausa v4.2] Sistema carregado...", "color:#00ff88;font-weight:bold;");

class ControlePausaSistema {
  constructor() {
    this.urlPHP = "./php/controle_pausa_novo.php";
    this.urlOperadores = "./php/listar_operadores.php";
    this.intervaloAtualizacao = 8000;
    this.intervaloCronometro = 1000;
    this.maxPausas = 2;

    this.listaParticipantes = document.getElementById("listaParticipantes");
    this.contPausa = document.getElementById("contador-pausa");
    this.contEspera = document.getElementById("contador-espera");

    this.estado = [];
    this.todosOperadores = [];
    this.pesoStatus = { ativo: 0, disponivel: 0, espera: 1, pausa: 2, expirada: 3 };
  }

  async iniciar() {
    console.log("🚀 [Controle] Iniciando...");
    await this.carregarParticipantesFixos();
    await this.atualizarEstado();
    this.inicializarFiltroEquipes(); // ✅ Novo
    setInterval(() => this.atualizarEstado(), this.intervaloAtualizacao);
    setInterval(() => this.atualizarCronometros(), this.intervaloCronometro);
  }

  async carregarParticipantesFixos() {
    const resp = await fetch(this.urlOperadores);
    const dados = await resp.json();
    if (dados.success) {
      this.todosOperadores = dados.equipes.flatMap(eq => {
        const lider = eq.lider || eq.nome;
        return (eq.operadores || []).map(o => ({
          nome: o.nome,
          equipe: eq.nome,
          lider,
          status: o.status || "disponivel",
        }));
      });
    }
  }

  async atualizarEstado() {
    try {
      const resp = await fetch(`${this.urlPHP}?acao=get_estado`);
      const dados = await resp.json();
      if (!dados.success) throw new Error("Erro de resposta");

      this.estado = dados.estado || [];
      this.renderizarParticipantes();
      this.verificarPrimeiroDaFila();
    } catch (e) {
      console.error("❌ Falha ao atualizar:", e);
    }
  }

  renderizarParticipantes(lista = this.estado) {
    const container = this.listaParticipantes;
    if (!container) return;

    container.innerHTML = "";
    const grupos = {};

    lista.forEach(p => (grupos[p.equipe] ||= []).push(p));

    Object.keys(grupos).forEach(equipe => {
      const box = document.createElement("div");
      box.className = "equipe-bloco";
      box.innerHTML = `<h3>${equipe}</h3><div class="equipe-operadores"></div>`;
      const inner = box.querySelector(".equipe-operadores");

      grupos[equipe]
        .sort((a, b) => (this.pesoStatus[a.status] ?? 9) - (this.pesoStatus[b.status] ?? 9))
        .forEach(p => inner.appendChild(this.criarItemOperador(p)));

      container.appendChild(box);
    });
  }

  criarItemOperador(p) {
    const item = document.createElement("div");
    item.className = `op-item ${p.status}`;
    item.dataset.nome = p.nome;
    item.dataset.equipe = p.equipe;

    const tempo = this.formatarTempo(p.tempo_espera_dinamico || 0);
    item.innerHTML = `
      <strong>${p.nome}</strong>
      <small>${this.formatarStatus(p.status)} ${p.posicao_fila ? `• #${p.posicao_fila}` : ""}</small>
      <div class="tempo" data-tinicio="${p.tempo_entrada || ""}">${tempo}</div>
    `;

    const botoes = document.createElement("div");
    botoes.className = "user-botoes";
    const nomeUser = (localStorage.getItem("operador_nome") || "").toLowerCase();
    const admin = localStorage.getItem("modo_admin") === "true";
    const ehUser = p.nome.toLowerCase() === nomeUser;

    if (admin) {
      botoes.innerHTML = `
        <button class="btn-acao entrar-fila">🕓 Fila</button>
        <button class="btn-acao entrar-pausa">☕ Pausa</button>
        <button class="btn-acao disponivel">✅ Disponível</button>`;
      botoes.querySelector(".entrar-fila").onclick = () => this.enviarAcao("entrar_fila", p);
      botoes.querySelector(".entrar-pausa").onclick = () => this.enviarAcao("forcar_pausa", p);
      botoes.querySelector(".disponivel").onclick = () => this.enviarAcao("voltar_disponivel", p);
    } else if (ehUser) {
      botoes.innerHTML = this.botoesPorStatus(p);
    }

    item.appendChild(botoes);
    return item;
  }

  botoesPorStatus(p) {
    const nome = p.nome;
    const eq = p.equipe;
    switch (p.status) {
      case "ativo":
      case "disponivel":
        return `<button class="btn-acao entrar-fila" onclick="window.controle.enviarAcao('entrar_fila',{nome:'${nome}',equipe:'${eq}'})">🕓 Entrar na Fila</button>`;
      case "espera":
        return `
          <button class="btn-acao solicitar-troca" onclick="window.controle.enviarAcao('solicitar_troca',{equipe:'${eq}'})">🔁 Solicitar Troca</button>
          <button class="btn-acao cancelar" onclick="window.controle.enviarAcao('voltar_disponivel',{nome:'${nome}',equipe:'${eq}'})">❌ Sair</button>`;
      case "pausa":
        return `<button class="btn-acao sair" onclick="window.controle.enviarAcao('voltar_disponivel',{nome:'${nome}',equipe:'${eq}'})">✅ Voltar</button>`;
      case "expirada":
        return `<button class="btn-acao sair" onclick="window.controle.enviarAcao('voltar_disponivel',{nome:'${nome}',equipe:'${eq}'})">🔄 Reiniciar</button>`;
      default:
        return "";
    }
  }

  atualizarCronometros() {
    document.querySelectorAll(".op-item.espera .tempo").forEach(div => {
      const t0 = div.dataset.tinicio;
      if (!t0) return;
      const diff = (Date.now() - new Date(t0).getTime()) / 1000;
      div.textContent = this.formatarTempo(diff);
    });
  }

  formatarTempo(seg) {
    const m = Math.floor(seg / 60).toString().padStart(2, "0");
    const s = Math.floor(seg % 60).toString().padStart(2, "0");
    return `${m}:${s}`;
  }

  formatarStatus(s) {
    return {
      pausa: "☕ Em Pausa",
      espera: "⏳ Em Espera",
      disponivel: "✅ Disponível",
      ativo: "🟢 Ativo",
      expirada: "🔴 Expirada"
    }[s] || s;
  }

  async enviarAcao(acao, dados) {
    try {
      const payload = { ...dados, solicitante: localStorage.getItem("operador_nome") || "" };
      const resp = await fetch(`${this.urlPHP}?acao=${acao}`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      });
      const ret = await resp.json();

      if (ret.success) {
        this.toast(`✅ ${ret.msg || "Ação executada"}`);
        await this.atualizarEstado();
      } else {
        this.toast(`⚠️ ${ret.error || "Ação não permitida"}`, true);
      }
    } catch (e) {
      console.error("❌ Erro na ação:", e);
      this.toast("Erro de comunicação com o servidor", true);
    }
  }

  toast(msg, erro = false) {
    const div = document.createElement("div");
    div.className = "toast-global show";
    div.style.borderLeftColor = erro ? "#ff4444" : "#00ff88";
    div.textContent = msg;
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 4000);
  }

  verificarPrimeiroDaFila() {
    const user = (localStorage.getItem("operador_nome") || "").toLowerCase();
    const meuRegistro = this.estado.find(
      p => p.status === "espera" && p.nome.toLowerCase() === user && p.posicao_fila === 1
    );
    if (meuRegistro) this.mostrarModalTroca(meuRegistro.equipe);
  }

  mostrarModalTroca(equipe) {
    if (document.getElementById("modalTroca")) return;

    const modal = document.createElement("div");
    modal.id = "modalTroca";
    modal.className = "modal-overlay";
    modal.innerHTML = `
      <div class="modal-login" style="max-width:420px;">
        <div class="modal-header"><h3>🔁 Solicitação de Troca</h3></div>
        <div class="modal-body">
          <p>O segundo da fila solicitou trocar de posição com você.</p>
          <p>Escolha uma opção abaixo:</p>
        </div>
        <div class="modal-footer">
          <button id="btnSeg" class="btn-acao" style="background:#007ced;">Ficar como segundo</button>
          <button id="btnFim" class="btn-acao" style="background:#ffaa00;">Ir para o fim</button>
          <button id="btnFechar" class="btn-acao" style="background:#444;">Cancelar</button>
        </div>
      </div>
    `;
    document.body.appendChild(modal);

    modal.querySelector("#btnSeg").onclick = async () => {
      await this.enviarAcao("decidir_troca", {
        equipe,
        decisor: localStorage.getItem("operador_nome"),
        decisao: "segundo"
      });
      modal.remove();
    };
    modal.querySelector("#btnFim").onclick = async () => {
      await this.enviarAcao("decidir_troca", {
        equipe,
        decisor: localStorage.getItem("operador_nome"),
        decisao: "fim"
      });
      modal.remove();
    };
    modal.querySelector("#btnFechar").onclick = () => modal.remove();
  }

  // ======================================
  // 🔍 Filtro: Mostrar somente minha equipe
  // ======================================
  inicializarFiltroEquipes() {
    const operador = localStorage.getItem("operador_nome") || "";
    const modoAdmin = localStorage.getItem("modo_admin") === "true";
    const topo = document.querySelector(".topo");
    if (!topo || !operador) return;
    if (document.getElementById("btnFiltroEquipe")) return;

    const box = document.createElement("div");
    box.className = "filtro-equipe-box";
    const btnEquipe = document.createElement("button");
    btnEquipe.id = "btnFiltroEquipe";
    btnEquipe.className = "btn-filtro";
    btnEquipe.textContent = "👥 Mostrar somente minha equipe";
    const btnTodas = document.createElement("button");
    btnTodas.id = "btnFiltroTodas";
    btnTodas.className = "btn-filtro";
    btnTodas.textContent = "🌎 Mostrar todas as equipes";
    btnTodas.style.display = "none";

    box.append(btnEquipe, btnTodas);
    topo.appendChild(box);

    btnEquipe.onclick = () => {
  const minhaEquipe = this.estado.find(p => p.nome.toLowerCase() === operador.toLowerCase())?.equipe;
  
  if (modoAdmin) {
    this.toast("Você é administrador — já visualiza todas as equipes.", false);
    return;
  }

  if (!minhaEquipe) {
    this.toast("Equipe não identificada", true);
    return;
  }

  const filtrada = this.estado.filter(p => p.equipe === minhaEquipe);
  document.body.classList.add("modo-minha-equipe");
  this.renderizarParticipantes(filtrada);
  btnEquipe.style.display = "none";
  btnTodas.style.display = "inline-block";
};


    btnTodas.onclick = () => {
      document.body.classList.remove("modo-minha-equipe");
      this.renderizarParticipantes(this.estado);
      btnTodas.style.display = "none";
      btnEquipe.style.display = "inline-block";
    };
  }
}

// =========================
// 🧩 Inicialização Global
// =========================
window.ControlePausaSistema = ControlePausaSistema;
document.addEventListener("DOMContentLoaded", () => {
  if (!window.controle) {
    window.controle = new ControlePausaSistema();
    window.controle.iniciar();
  }
});
