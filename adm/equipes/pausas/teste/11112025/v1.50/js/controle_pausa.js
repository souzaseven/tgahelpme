// ============================================================
// controle_pausa.js (v4.8) - Equipes, Identificação e Contadores
// ============================================================

console.log("%c[Controle de Pausa v4.8] Sistema carregado...", "color:#00ff88;font-weight:bold;");

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
    this.jaSaudou = false;
    this.modoMinhaEquipe = true; // ✅ Inicia diretamente no modo "minha equipe"
  }

  async iniciar() {
    console.log("🚀 [Controle] Iniciando...");

    // força visual inicial do modo equipe
    document.body.classList.add("modo-minha-equipe");

    await this.atualizarEstado();
    await this.exibirIdentificacao();
    this.inicializarFiltroEquipes();

    // ajusta visibilidade dos botões
    const btnEquipe = document.getElementById("btnFiltroEquipe");
    const btnTodas = document.getElementById("btnFiltroTodas");
    if (btnEquipe && btnTodas) {
      btnEquipe.style.display = "none";
      btnTodas.style.display = "inline-block";
    }

    // Atualização periódica (mantém o modo atual)
    setInterval(async () => {
      if (this.modoMinhaEquipe) {
        try {
          const resp = await fetch(`${this.urlPHP}?acao=get_estado`, { cache: "no-store" });
          const dados = await resp.json();
          if (dados.success) {
            this.estado = dados.estado || [];
            const minhaEquipe = this.estado.find(
              p => this.normalizar(p.nome) === this.normalizar(this.operador)
            )?.equipe;
            if (minhaEquipe) {
              const filtrada = this.estado.filter(p => p.equipe === minhaEquipe);
              this.renderizarParticipantes(filtrada);
            }
          }
        } catch (e) {
          console.warn("⚠️ Atualização parcial falhou:", e);
        }
      } else {
        this.atualizarEstado();
      }
    }, this.intervaloAtualizacao);

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
      const equipe = userData?.equipe || null;

      if (admin) {
        this.headerUsuario.textContent = `👑 Administrador: ${operador}`;
        if (!this.jaSaudou) {
          this.toast(`👋 Bem-vindo, ${operador}! Você tem acesso administrativo.`);
          this.jaSaudou = true;
        }
        return;
      }

      if (!equipe) {
        this.headerUsuario.textContent = `${operador} • 🟠 Usuário sem equipe definida`;
        if (!this.jaSaudou) {
          this.toast(`⚠️ ${operador}, sua equipe não está cadastrada.`, true);
          this.jaSaudou = true;
        }
        return;
      }

      this.headerUsuario.textContent = `👤 Operador: ${operador} • Equipe: ${equipe}`;
      if (!this.jaSaudou) {
        this.toast(`👋 Bem-vindo, ${operador}! Você pertence à equipe ${equipe}.`);
        this.jaSaudou = true;
      }
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

      if (this.modoMinhaEquipe) {
        const minhaEquipe = this.estado.find(
          p => this.normalizar(p.nome) === this.normalizar(this.operador)
        )?.equipe;
        const filtrada = minhaEquipe ? this.estado.filter(p => p.equipe === minhaEquipe) : [];
        this.renderizarParticipantes(filtrada);
      } else {
        this.renderizarParticipantes();
      }
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
      const participantes = grupos[equipe];
      const qtd = participantes.length;

      const ativos = participantes.filter(p => ["ativo", "disponivel"].includes(p.status)).length;
      const pausas = participantes.filter(p => p.status === "pausa").length;
      const espera = participantes.filter(p => p.status === "espera").length;

      const box = document.createElement("div");
      box.className = "equipe-bloco";
      box.innerHTML = `
        <h3>
          ${equipe}
          <span class="contador-equipe">
            <i class="fas fa-users"></i> ${qtd} operador${qtd > 1 ? "es" : ""}
            <span class="detalhes-status">
              <span class="ativo">🟢 ${ativos}</span>
              <span class="espera">⏳ ${espera}</span>
              <span class="pausa">☕ ${pausas}</span>
            </span>
          </span>
        </h3>
        <div class="equipe-operadores"></div>
      `;

      const inner = box.querySelector(".equipe-operadores");
      participantes
        .sort((a, b) => (this.pesoStatus[a.status] ?? 9) - (this.pesoStatus[b.status] ?? 9))
        .forEach(p => inner.appendChild(this.criarItemOperador(p)));

      container.appendChild(box);
    });

    // HUD
    const totalOperadores = lista.length;
    const totalEquipes = Object.keys(grupos).length;
    const hud = document.getElementById("hud-operador");

    if (hud) {
      if (this.modoMinhaEquipe) {
        const minhaEquipe = Object.keys(grupos)[0] || "";
        hud.textContent = `👥 ${minhaEquipe} — ${totalOperadores} operador${totalOperadores > 1 ? "es" : ""}`;
      } else {
        hud.textContent = `🌎 ${totalEquipes} equipe${totalEquipes > 1 ? "s" : ""} • ${totalOperadores} operador${totalOperadores > 1 ? "es" : ""}`;
      }
    }
// 🔁 Reaplicar botões do operador logado (sem observer)
if (window.aplicarBotoesOperador) {
  // pequeno delay para garantir que o DOM já terminou de renderizar
  setTimeout(() => aplicarBotoesOperador(this.operador.toLowerCase()), 100);
}


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
    div.style.left = "20px";
    div.style.right = "auto";
    div.style.top = "20px";
    div.style.position = "fixed";
    div.style.transform = "translateX(-150px)";
    div.innerHTML = `<span style="margin-right:6px;">${erro ? "⚠️" : "💬"}</span> ${msg}`;
    document.body.appendChild(div);

    void div.offsetWidth;
    div.style.transition = "transform 0.4s ease, opacity 0.4s ease";
    div.style.transform = "translateX(0)";
    div.style.opacity = "1";

    setTimeout(() => {
      div.style.opacity = "0";
      div.style.transform = "translateX(-150px)";
      setTimeout(() => div.remove(), 500);
    }, 4000);
  }

  // =======================================================
  // 🔍 Filtro de Equipes
  // =======================================================
  inicializarFiltroEquipes() {
  const btnEquipe = document.getElementById("btnFiltroEquipe");
  const btnTodas = document.getElementById("btnFiltroTodas");
  const lista = document.getElementById("listaParticipantes");

  if (!btnEquipe || !btnTodas || !lista) return;

  const operador = this.operador;

  btnEquipe.onclick = async () => {
    const minhaEquipe = await this.buscarMinhaEquipe(operador);
    if (!minhaEquipe) {
      this.toast("Usuário sem equipe definida", true);
      return;
    }
    const filtrada = this.estado.filter(p => p.equipe === minhaEquipe);
    this.modoMinhaEquipe = true;
    document.body.classList.add("modo-minha-equipe");
    lista.classList.remove("todas-equipes"); // Remove grid
    this.renderizarParticipantes(filtrada);
    btnEquipe.style.display = "none";
    btnTodas.style.display = "inline-block";
  };

  btnTodas.onclick = () => {
    this.modoMinhaEquipe = false;
    document.body.classList.remove("modo-minha-equipe");
    lista.classList.add("todas-equipes"); // Ativa grid
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


window.addEventListener('load', function () {
  setTimeout(function () {
    const botaoFiltro = document.getElementById('btnFiltroEquipe');
    if (botaoFiltro) {
      botaoFiltro.click(); // Simula o clique
    }
  }, 500); // Pequeno delay para garantir que os scripts carregaram
});
