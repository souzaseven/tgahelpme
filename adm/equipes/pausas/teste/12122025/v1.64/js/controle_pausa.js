// ============================================================
// controle_pausa.js (v5.0 unificado)
// Núcleo de estado, render e eventos do Sistema de Pausas
// ============================================================

console.log("%c[Controle de Pausa v5.0] núcleo carregado", "color:#00ff88;font-weight:bold;");

class ControlePausaSistema {
  constructor() {
    // --- Config ---
    this.urlPHP = "./php/controle_pausa_novo.php";
    this.intervaloAtualizacao = 2000; // 2s, único polling
    this.intervaloCronometro = 1000;  // 1s, só para relógio da fila
    this.maxPausas = 2;

    // --- Estado ---
    this.estado = [];
    this.atualizando = false;
    this.jaSaudou = false;
    this.modoMinhaEquipe = true; // inicia focado na minha equipe
    this.pesoStatus = { ativo: 0, disponivel: 0, espera: 1, pausa: 2, expirada: 3 };

    // --- DOM refs ---
    this.listaParticipantes = document.getElementById("listaParticipantes");
    this.headerUsuario = document.getElementById("usuarioLogado");

    // --- Sessão ---
    this.operador = localStorage.getItem("operador_nome") || "";
  }

  normalizar(s) {
    return (s || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").trim().toLowerCase();
  }

  async iniciar() {
    console.log("🚀 [Controle] iniciando…");
    document.body.classList.add("modo-minha-equipe");

    await this.sincronizarAtualizacoes(); // 1ª carga
    await this.exibirIdentificacao();
    this.inicializarFiltroEquipes();
this.injetarToolbarAdmin();

    const btnEquipe = document.getElementById("btnFiltroEquipe");
    const btnTodas = document.getElementById("btnFiltroTodas");
    if (btnEquipe && btnTodas) { btnEquipe.style.display = "none"; btnTodas.style.display = "inline-block"; }

    setInterval(() => this.sincronizarAtualizacoes(), this.intervaloAtualizacao);
    setInterval(() => this.atualizarCronometros(), this.intervaloCronometro);

    try {
      if ("Notification" in window && Notification.permission !== "granted") {
        Notification.requestPermission().catch(() => {});
      }
    } catch {}
  }

  async sincronizarAtualizacoes() {
    if (this.atualizando) return;
    this.atualizando = true;
    try {
      const resp = await fetch(`${this.urlPHP}?acao=get_estado`, { cache: "no-store" });
      const dados = await resp.json();
      if (!dados.success) return;

      const novoEstado = dados.estado || [];
      if (JSON.stringify(novoEstado) === JSON.stringify(this.estado)) return;

      this.estado = novoEstado;

      if (this.modoMinhaEquipe) {
        const minhaEquipe = this.estado.find(
          p => this.normalizar(p.nome) === this.normalizar(this.operador)
        )?.equipe;
        const filtrada = minhaEquipe ? this.estado.filter(p => p.equipe === minhaEquipe) : [];
        this.renderizarParticipantes(filtrada);
      } else {
        this.renderizarParticipantes(this.estado);
      }

      document.dispatchEvent(new CustomEvent("estado:atualizado", { detail: { estado: this.estado } }));
    } catch (e) {
      console.warn("⚠️ Falha ao sincronizar:", e);
    } finally {
      this.atualizando = false;
    }
  }

  async exibirIdentificacao() {
    const operador = this.operador;
    const admin = this.normalizar(operador) === this.normalizar("Anderson de Souza");
    try {
      const resp = await fetch(`${this.urlPHP}?acao=get_estado`, { cache: "no-store" });
      const dados = await resp.json();
      if (!dados.success) throw new Error("Erro ao buscar equipe");

      const userData = dados.estado.find(p => this.normalizar(p.nome) === this.normalizar(operador));
      const equipe = userData?.equipe || null;

      if (admin) {
        this.headerUsuario && (this.headerUsuario.textContent = `👑 Administrador: ${operador}`);
        if (!this.jaSaudou) { this.toast(`👋 Bem-vindo, ${operador}! Você tem acesso administrativo.`); this.jaSaudou = true; }
        return;
      }

      if (!equipe) {
        this.headerUsuario && (this.headerUsuario.textContent = `${operador} • 🟠 Usuário sem equipe definida`);
        if (!this.jaSaudou) { this.toast(`⚠️ ${operador}, sua equipe não está cadastrada.`, true); this.jaSaudou = true; }
        return;
      }

      this.headerUsuario && (this.headerUsuario.textContent = `👤 Operador: ${operador} • Equipe: ${equipe}`);
      if (!this.jaSaudou) { this.toast(`👋 Bem-vindo, ${operador}! Você pertence à equipe ${equipe}.`); this.jaSaudou = true; }
    } catch {
      this.headerUsuario && (this.headerUsuario.textContent = "Usuário não identificado");
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
    return ({
      pausa: "☕ Em Pausa",
      espera: "🟡 Em Espera",
      disponivel: "✅ Disponível",
      ativo: "🟢 Ativo",
      expirada: "🔴 Expirada"
    }[s] || s);
  }

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

    document.dispatchEvent(new CustomEvent("ui:operadores-renderizados"));
  }

  criarItemOperador(p) {
    const item = document.createElement("div");
    item.className = `op-item ${p.status}`;
    const tempo = this.formatarTempo(p.tempo_espera_dinamico || 0);

    item.innerHTML = `
      <strong>${p.nome}</strong>
      <small>${this.formatarStatus(p.status)} ${p.posicao_fila ? `• #${p.posicao_fila}` : ""}</small>
      <div class="tempo" data-tinicio="${p.tempo_entrada || ""}">${tempo}</div>
      <div class="botoes-operador" aria-live="polite"></div>
    `;
    return item;
  }

async enviarAcao(acao, dados) {
  try {
    // ✅ Garante que sempre vai com nome + equipe
    const payload = { ...dados };
    if (!payload.nome) {
      this.toast("Nome é obrigatório.", true);
      return;
    }
    if (!payload.equipe) {
      payload.equipe = this.buscarEquipePorOperador(payload.nome) || "";
    }
    if (!payload.equipe) {
      this.toast("Equipe não encontrada para o operador.", true);
      return;
    }

    const resp = await fetch(`${this.urlPHP}?acao=${acao}`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });
    const ret = await resp.json();

    if (ret.success) {
      this.toast(`✅ ${ret.msg || "Ação executada"}`);
      await this.sincronizarAtualizacoes();
      document.dispatchEvent(new CustomEvent("status:alterado", { detail: { nome: payload.nome, acao } }));
      this.notificarEquipeStatus(payload.nome, acao);
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
    div.style.borderLeft = `6px solid ${erro ? "#ff4444" : "#00ff88"}`;
    div.style.left = "20px";
    div.style.top = "20px";
    div.style.position = "fixed";
    div.style.transform = "translateX(-160px)";
    div.style.background = "rgba(0,0,0,0.6)";
    div.style.padding = "10px 14px";
    div.style.borderRadius = "10px";
    div.style.backdropFilter = "blur(3px)";
    div.style.color = "#fff";
    div.innerHTML = `<span style="margin-right:6px;">${erro ? "⚠️" : "💬"}</span> ${msg}`;
    document.body.appendChild(div);

    requestAnimationFrame(() => {
      div.style.transition = "transform 0.35s ease, opacity 0.35s ease";
      div.style.transform = "translateX(0)";
      div.style.opacity = "1";
    });

    setTimeout(() => {
      div.style.opacity = "0";
      div.style.transform = "translateX(-160px)";
      setTimeout(() => div.remove(), 380);
    }, 3500);
  }

  notificarEquipeStatus(nome, acao) {
    const operador = this.estado.find(p => this.normalizar(p.nome) === this.normalizar(nome));
    if (!operador) return;

    const equipe = operador.equipe;
    const todos = this.estado.filter(p => p.equipe === equipe);

    if (this.normalizar(nome) === this.normalizar(this.operador)) return;

    let mensagem = "";
    if (acao.includes("pausa")) mensagem = `☕ ${nome} entrou em pausa.`;
    else if (acao.includes("fila")) mensagem = `🕓 ${nome} entrou na fila de espera.`;
    else if (acao.includes("voltar")) mensagem = `✅ ${nome} voltou a ficar disponível.`;
    else if (acao.includes("expirada")) mensagem = `🔴 ${nome} teve a pausa expirada.`;
    else mensagem = `${nome} alterou seu status.`;

    if (this.operador && todos.some(p => this.normalizar(p.nome) === this.normalizar(this.operador))) {
      this.toast(mensagem);
    }

    try {
      if ("Notification" in window && Notification.permission === "granted") {
        new Notification("Alteração de Status", {
          body: mensagem,
          icon: "https://tgameajuda.com/img/principal/bot-tga.webp"
        });
      }
    } catch {}
  }

  buscarEquipePorOperador(nome) {
    const p = this.estado.find(p => this.normalizar(p.nome) === this.normalizar(nome));
    return p ? p.equipe : "";
  }
// ============================================================
// ADMIN: Derrubar todo mundo (pausa/espera/expirada) -> disponível
// Tenta endpoint em lote; se não existir, faz fallback operador a operador
// ============================================================
async adminForcarTodosDisponivel() {
  const ehAdmin = this.normalizar(this.operador) === this.normalizar("Anderson de Souza");
  if (!ehAdmin) { this.toast("Apenas o administrador pode executar esta ação.", true); return; }

  const confirmar = confirm("Você deseja derrubar TODOS das pausas/fila (todas as equipes) para Disponível?");
  if (!confirmar) return;

  // 1) Tenta endpoint em lote (se você implementar no PHP)
  try {
    const resp = await fetch(`${this.urlPHP}?acao=forcar_todos_disponivel`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ admin: this.operador })
    });
    const ret = await resp.json();
    if (ret?.success) {
      this.toast("✅ Todos foram definidos como Disponível.");
      await this.sincronizarAtualizacoes();
      return;
    }
  } catch (e) {
    // segue para fallback
  }

  // 2) Fallback: derruba um por um (silencioso para não spammar)
  const alvos = (this.estado || []).filter(p => ["pausa", "espera", "expirada"].includes(p.status));
  if (!alvos.length) { this.toast("Não há ninguém em pausa/fila para derrubar."); return; }

  try {
    // Faz em pequenos lotes para não sobrecarregar o PHP
    for (const p of alvos) {
      await fetch(`${this.urlPHP}?acao=voltar_disponivel`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ nome: p.nome, equipe: p.equipe })
      }).catch(()=>{});
      // pequeno intervalo para aliviar o servidor
      await new Promise(r => setTimeout(r, 80));
    }
    this.toast(`✅ ${alvos.length} operador(es) voltaram a Disponível.`);
    await this.sincronizarAtualizacoes();
  } catch (e) {
    console.error(e);
    this.toast("Erro ao derrubar todos.", true);
  }
}

// ============================================================
// Insere um botão flutuante de admin na UI
// ============================================================
injetarToolbarAdmin() {
  const ehAdmin = this.normalizar(this.operador) === this.normalizar("Anderson de Souza");
  if (!ehAdmin || document.getElementById("btnAdminDerrubarTodos")) return;

  const btn = document.createElement("button");
  btn.id = "btnAdminDerrubarTodos";
  btn.textContent = "☄ Derrubar Pausas/Fila";
  Object.assign(btn.style, {
    position: "fixed",
    right: "16px",
    bottom: "16px",
    zIndex: 9999,
    padding: "10px 14px",
    background: "#ff3b30",
    color: "#fff",
    border: "none",
    borderRadius: "10px",
    boxShadow: "0 8px 20px rgba(0,0,0,.25)",
    cursor: "pointer",
    fontWeight: "600"
  });
  btn.onmouseenter = () => btn.style.filter = "brightness(1.05)";
  btn.onmouseleave = () => btn.style.filter = "none";
  btn.onclick = () => this.adminForcarTodosDisponivel();

  document.body.appendChild(btn);
}


// ============================================================
// Insere um botão flutuante de admin na UI
// ============================================================
injetarToolbarAdmin() {
  const ehAdmin = this.normalizar(this.operador) === this.normalizar("Anderson de Souza");
  if (!ehAdmin || document.getElementById("btnAdminDerrubarTodos")) return;

  const btn = document.createElement("button");
  btn.id = "btnAdminDerrubarTodos";
  btn.textContent = "☄ Derrubar Pausas/Fila";
  Object.assign(btn.style, {
    position: "fixed",
    right: "16px",
    bottom: "16px",
    zIndex: 9999,
    padding: "10px 14px",
    background: "#ff3b30",
    color: "#fff",
    border: "none",
    borderRadius: "10px",
    boxShadow: "0 8px 20px rgba(0,0,0,.25)",
    cursor: "pointer",
    fontWeight: "600"
  });
  btn.onmouseenter = () => btn.style.filter = "brightness(1.05)";
  btn.onmouseleave = () => btn.style.filter = "none";
  btn.onclick = () => this.adminForcarTodosDisponivel();

  document.body.appendChild(btn);
}

  inicializarFiltroEquipes() {
    const btnEquipe = document.getElementById("btnFiltroEquipe");
    const btnTodas = document.getElementById("btnFiltroTodas");
    const lista = document.getElementById("listaParticipantes");
    if (!btnEquipe || !btnTodas || !lista) return;

    const operador = this.operador;

    btnEquipe.onclick = async () => {
      const minhaEquipe = this.buscarEquipePorOperador(operador) || null;
      if (!minhaEquipe) { this.toast("Usuário sem equipe definida", true); return; }
      const filtrada = this.estado.filter(p => p.equipe === minhaEquipe);
      this.modoMinhaEquipe = true;
      document.body.classList.add("modo-minha-equipe");
      lista.classList.remove("todas-equipes");
      this.renderizarParticipantes(filtrada);
      btnEquipe.style.display = "none";
      btnTodas.style.display = "inline-block";
    };

    btnTodas.onclick = () => {
      this.modoMinhaEquipe = false;
      document.body.classList.remove("modo-minha-equipe");
      lista.classList.add("todas-equipes");
      this.renderizarParticipantes(this.estado);
      btnTodas.style.display = "none";
      btnEquipe.style.display = "inline-block";
    };
  }
}

window.ControlePausaSistema = ControlePausaSistema;

document.addEventListener("DOMContentLoaded", () => {
  if (!window.controle) {
    window.controle = new ControlePausaSistema();
    window.controle.iniciar();
  }
  setTimeout(() => { const btn = document.getElementById("btnFiltroEquipe"); btn && btn.click(); }, 500);
});
