// ============================================================
// controle_pausa.js (v4.6) - Equipes, Identificação e Legenda
// ============================================================

console.log("%c[Controle de Pausa v4.6] Sistema carregado...", "color:#00ff88;font-weight:bold;");

class ControlePausaSistema {
  constructor() {
    this.urlPHP = "./php/controle_pausa_novo.php";
    this.intervaloAtualizacao = 8000;
    this.intervaloCronometro = 1000;
    this.maxPausas = 2;

    this.listaParticipantes = document.getElementById("listaParticipantes");
    this.headerUsuario = document.getElementById("usuarioLogado");
    this.estado = [];
    this.pesoStatus = { ativo: 0, disponivel: 0, espera: 1, pausa: 2, expirada: 3 };

    this.operador = localStorage.getItem("operador_nome") || "";
  }

  async iniciar() {
    console.log("🚀 [Controle] Iniciando...");
    await this.atualizarEstado();
    await this.exibirIdentificacao();


    this.inicializarFiltroEquipes();
    this.adicionarLegendaStatus();
    setInterval(() => this.atualizarEstado(), this.intervaloAtualizacao);
    setInterval(() => this.atualizarCronometros(), this.intervaloCronometro);
  }

  normalizar(s) {
    return (s || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").trim().toLowerCase();
  }

  // =======================================================
  // 👤 Exibir operador e equipe
  // =======================================================
  async exibirIdentificacao() {
    const operador = this.operador;
    const admin = this.normalizar(operador) === this.normalizar("Anderson de Souza");

    try {
      const resp = await fetch(`${this.urlPHP}?acao=get_estado`);
      const dados = await resp.json();
      if (!dados.success) throw new Error("Erro ao buscar equipe");
      const userData = dados.estado.find(p => this.normalizar(p.nome).includes(this.normalizar(operador)));


      let equipe = userData?.equipe || null;

      if (admin) {
        this.headerUsuario.textContent = `👑 Administrador: ${operador}`;
        this.toast(`👋 Bem-vindo, ${operador}! Você tem acesso administrativo.`);
        return;
      }

      if (!equipe) {
        this.headerUsuario.textContent = `${operador} • 🟠 Usuário sem equipe definida`;
        this.toast(`⚠️ ${operador}, sua equipe não está cadastrada.`, true);
        return;
      }

      this.headerUsuario.textContent = `👤 Operador: ${operador} • Equipe: ${equipe}`;
      this.toast(`👋 Bem-vindo, ${operador}! Você pertence à equipe ${equipe}.`);
    } catch {
      this.headerUsuario.textContent = "Usuário não identificado";
    }
  }

  // =======================================================
  // 🔄 Atualiza estado (consulta banco)
  // =======================================================
  async atualizarEstado() {
    try {
      const resp = await fetch(`${this.urlPHP}?acao=get_estado`, { cache: "no-store" });
      const dados = await resp.json();
      if (!dados.success) throw new Error("Erro ao obter estado");

      this.estado = dados.estado || [];
      this.renderizarParticipantes();
      this.verificarPrimeiroDaFila();
    } catch (e) {
      console.error("❌ Falha ao atualizar:", e);
    }
  }

  // =======================================================
  // 🎨 Renderização de participantes
  // =======================================================
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
    const tempo = this.formatarTempo(p.tempo_espera_dinamico || 0);
    item.innerHTML = `
      <strong>${p.nome}</strong>
      <small>${this.formatarStatus(p.status)} ${p.posicao_fila ? `• #${p.posicao_fila}` : ""}</small>
      <div class="tempo" data-tinicio="${p.tempo_entrada || ""}">${tempo}</div>
    `;

    const botoes = document.createElement("div");
    botoes.className = "user-botoes";

    const nomeUser = this.normalizar(this.operador);
    const admin = this.normalizar(this.operador) === this.normalizar("Anderson de Souza");
    const ehUser = this.normalizar(p.nome) === nomeUser;

    if (admin) {
      botoes.innerHTML = `
        <button class="btn-acao" onclick="window.controle.enviarAcao('entrar_fila',${JSON.stringify(p)})">🕓 Fila</button>
        <button class="btn-acao" onclick="window.controle.enviarAcao('forcar_pausa',${JSON.stringify(p)})">☕ Pausa</button>
        <button class="btn-acao" onclick="window.controle.enviarAcao('voltar_disponivel',${JSON.stringify(p)})">✅ Disponível</button>`;
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
        return `<button class="btn-acao" onclick="window.controle.enviarAcao('entrar_fila',{nome:'${nome}',equipe:'${eq}'})">🕓 Entrar na Fila</button>`;
      case "espera":
        return `
          <button class="btn-acao" onclick="window.controle.enviarAcao('solicitar_troca',{equipe:'${eq}'})">🔁 Solicitar Troca</button>
          <button class="btn-acao" onclick="window.controle.enviarAcao('voltar_disponivel',{nome:'${nome}',equipe:'${eq}'})">❌ Sair</button>`;
      case "pausa":
        return `<button class="btn-acao" onclick="window.controle.enviarAcao('voltar_disponivel',{nome:'${nome}',equipe:'${eq}'})">✅ Voltar</button>`;
      case "expirada":
        return `<button class="btn-acao" onclick="window.controle.enviarAcao('voltar_disponivel',{nome:'${nome}',equipe:'${eq}'})">🔄 Reiniciar</button>`;
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
      espera: "🟡 Em Espera",
      disponivel: "✅ Disponível",
      ativo: "🟢 Ativo",
      expirada: "🔴 Expirada"
    }[s] || s;
  }

  async enviarAcao(acao, dados) {
    try {
      const resp = await fetch(`${this.urlPHP}?acao=${acao}`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(dados)
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
  div.innerHTML = `<span style="margin-right:6px;">${erro ? "⚠️" : "💬"}</span> ${msg}`;
  document.body.appendChild(div);

  // Animação de saída suave
  setTimeout(() => {
    div.style.transition = "opacity 0.6s ease, transform 0.6s ease";
    div.style.opacity = "0";
    div.style.transform = "translateX(100px)";
    setTimeout(() => div.remove(), 700);
  }, 4000);
}


  // =======================================================
  // 🧭 Legenda de status (cores)
  // =======================================================
/*
  adicionarLegendaStatus() {
    const participantesHeader = document.querySelector(".participantes-container h2");
    if (!participantesHeader || document.getElementById("legendaStatus")) return;

    const legenda = document.createElement("div");
    legenda.id = "legendaStatus";
    legenda.style.fontSize = "0.8rem";
    legenda.style.color = "#ccc";
    legenda.style.display = "flex";
    legenda.style.gap = "12px";
    legenda.style.flexWrap = "wrap";
    legenda.style.marginTop = "6px";

    legenda.innerHTML = `
      🟢 Ativo • 🟡 Espera • ☕ Pausa • 🔴 Expirada • ✅ Disponível
    `;
    participantesHeader.parentElement.appendChild(legenda);
  }

  verificarPrimeiroDaFila() {
    const user = (this.operador || "").toLowerCase();
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
      </div>`;
    document.body.appendChild(modal);

    modal.querySelector("#btnSeg").onclick = async () => {
      await this.enviarAcao("decidir_troca", {
        equipe,
        decisor: this.operador,
        decisao: "segundo"
      });
      modal.remove();
    };
    modal.querySelector("#btnFim").onclick = async () => {
      await this.enviarAcao("decidir_troca", {
        equipe,
        decisor: this.operador,
        decisao: "fim"
      });
      modal.remove();
    };
    modal.querySelector("#btnFechar").onclick = () => modal.remove();
  }
*/

  // =======================================================
  // 🔍 Filtro: Mostrar somente minha equipe
  // =======================================================
  inicializarFiltroEquipes() {
    const operador = this.operador;
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

    btnEquipe.onclick = async () => {
      const minhaEquipe = await this.buscarMinhaEquipe(operador);
      if (!minhaEquipe) {
        this.toast("Usuário sem equipe definida", true);
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

  async buscarMinhaEquipe(nome) {
    try {
      const resp = await fetch(`${this.urlPHP}?acao=get_estado`);
      const dados = await resp.json();
      if (!dados.success) return null;
     const registro = dados.estado.find(p => this.normalizar(p.nome).includes(this.normalizar(nome)));

      return registro?.equipe || null;
    } catch {
      return null;
    }
  }
}

window.ControlePausaSistema = ControlePausaSistema;
document.addEventListener("DOMContentLoaded", () => {
  if (!window.controle) {
    window.controle = new ControlePausaSistema();
    window.controle.iniciar();
  }
});
