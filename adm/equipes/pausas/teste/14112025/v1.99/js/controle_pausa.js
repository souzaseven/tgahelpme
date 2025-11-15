// ============================================================
// controle_pausa.js (v5.3)
// Núcleo de estado, render e eventos do Sistema de Pausas
// - Integra com php/controle_pausa_novo.php (v1.17+)
// - Compatível com interface_botoes.js (v3.0) e acoes_operador.js
// - Notificações por equipe via diff de estado (sem socket)
// - Cronômetro contínuo (sem piscar)
// - Destaque visual do operador logado
// - Botões de admin: Derrubar Pausados / Derrubar Fila (por equipe)
// ============================================================

console.log(
  "%c[Controle de Pausa v5.3] núcleo carregado",
  "color:#00ff88;font-weight:bold;"
);

class ControlePausaSistema {
  constructor() {
    // -----------------------------------------
    // CONFIGURAÇÕES BÁSICAS
    // -----------------------------------------
    this.urlPHP = "./php/controle_pausa_novo.php";
    this.intervaloAtualizacao = 2000; // 2s - polling único
   
    this.maxPausas = 2;

    // -----------------------------------------
    // ESTADO EM MEMÓRIA
    // -----------------------------------------
    this.estado = [];               // lista de operadores vindos do PHP
    this.atualizando = false;       // trava de requisição
    this.jaSaudou = false;          // evita múltiplas boas-vindas
    this.modoMinhaEquipe = true;    // inicia focado na equipe do operador

    // Mapa para diff de notificações
    this.mapaEstadoAnterior = new Map(); // chave: nome|equipe  valor: status
    this.primeiraCargaNotificacao = true;

    // Ordem de prioridade para renderização
    this.pesoStatus = {
      ativo: 0,
      disponivel: 0,
      espera: 1,
      aguardando: 2,
      pausa: 3,
      expirada: 4
    };

    // -----------------------------------------
    // REFERÊNCIAS DO DOM
    // -----------------------------------------
    this.listaParticipantes = document.getElementById("listaParticipantes");
    this.headerUsuario = document.getElementById("usuarioLogado");
    this.hudOperador = document.getElementById("hud-operador");

    // -----------------------------------------
    // SESSÃO / OPERADOR LOGADO
    // -----------------------------------------
    this.operador = localStorage.getItem("operador_nome") || "";
  }

  // -----------------------------------------
  // UTILIDADES
  // -----------------------------------------
  normalizar(s) {
    return (s || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .trim()
      .toLowerCase();
  }

  formatarTempo(segundos) {
    const s = Math.max(0, Math.floor(segundos));
    const m = Math.floor(s / 60)
      .toString()
      .padStart(2, "0");
    const r = (s % 60).toString().padStart(2, "0");
    return `${m}:${r}`;
  }

  formatarStatus(s) {
    return (
      {
        pausa: "☕ Em Pausa",
        espera: "🟡 Em Espera",
        aguardando: "🟢⚡ Vaga aberta (aguardando confirmação)",
        disponivel: "✅ Disponível",
        ativo: "🟢 Ativo",
        expirada: "🔴 Expirada"
      }[s] || s
    );
  }

  buscarEquipePorOperador(nome) {
    const p = this.estado.find(
      (x) => this.normalizar(x.nome) === this.normalizar(nome)
    );
    return p ? p.equipe : "";
  }
  // -----------------------------------------
  // INICIALIZAÇÃO GERAL
  // -----------------------------------------
  async iniciar() {
    console.log("🚀 [Controle] iniciando…");

    document.body.classList.add("modo-minha-equipe");

    // 1ª carga de estado
    await this.sincronizarAtualizacoes();

    // Exibe identificação do usuário / equipe
    await this.exibirIdentificacao();

    // Filtro "Minha equipe" / "Todas equipes"
    this.inicializarFiltroEquipes();

    // 🔔 Preferências de notificação (som / desktop)
    this.inicializarPreferenciasNotificacao();

    // Botões de administração (derrubar pausados / fila)
    this.injetarToolbarAdmin();

    // Polling de estado
    setInterval(() => this.sincronizarAtualizacoes(), this.intervaloAtualizacao);

    // Permissão de notificação (apenas se usuário quiser desktop)
    try {
      const permitirDesktop = localStorage.getItem("pref_desktop") === "1";
      if (
        permitirDesktop &&
        "Notification" in window &&
        Notification.permission !== "granted"
      ) {
        Notification.requestPermission().catch(() => {});
      }
    } catch (e) {
      // ignora
    }
  }

// -----------------------------------------
// SINCRONIZAÇÃO COM O BACKEND
// -----------------------------------------
async sincronizarAtualizacoes() {
  if (this.atualizando) return;
  this.atualizando = true;

  try {
    const resp = await fetch(`${this.urlPHP}?acao=get_estado`, {
      cache: "no-store"
    });
    const dados = await resp.json();
    if (!dados.success) {
      console.warn("⚠️ get_estado retornou erro:", dados);
      return;
    }

    const novoEstado = dados.estado || [];

    // Evita re-renderização e diff se nada mudou
    if (JSON.stringify(novoEstado) === JSON.stringify(this.estado)) return;

    const estadoAnterior = this.estado;
    this.estado = novoEstado;

    // 🔔 Notificações baseadas em diff
    this.processarDiffNotificacoes(estadoAnterior, novoEstado);

    // 🔗 Integração com módulo Cronometro
    if (window.Cronometro) {
      this.estado.forEach(p => {
        if (["espera", "pausa", "aguardando"].includes(p.status)) {
          Cronometro.iniciar(p.nome, p.status, p.tempo_entrada);
        } else {
          Cronometro.parar(p.nome, false); // false = não salvar no backend
        }
      });
    }

    // Modo "Minha equipe" ou "Todas"
    if (this.modoMinhaEquipe) {
      const minhaEquipe = this.buscarEquipePorOperador(this.operador);
      const filtrada = minhaEquipe
        ? this.estado.filter((p) => p.equipe === minhaEquipe)
        : [];
      this.renderizarParticipantes(filtrada);
    } else {
      this.renderizarParticipantes(this.estado);
    }

    // Dispara evento global
    document.dispatchEvent(
      new CustomEvent("estado:atualizado", { detail: { estado: this.estado } })
    );

  } catch (e) {
    console.warn("⚠️ Falha ao sincronizar:", e);
  } finally {
    this.atualizando = false;
  }
}

  // -----------------------------------------
  // PREFERÊNCIAS DE NOTIFICAÇÃO (Som / Desktop)
  // -----------------------------------------
  inicializarPreferenciasNotificacao() {
    const btnPref = document.getElementById("btnPreferenciasNotificacao");
    if (!btnPref) return;

    const atualizarLabel = () => {
      const permitirSom = localStorage.getItem("pref_som") === "1";
      const permitirDesktop = localStorage.getItem("pref_desktop") === "1";

      let status = [];
      status.push(permitirSom ? "🔊 Som ON" : "🔇 Som OFF");
      status.push(permitirDesktop ? "🖥️ Desktop ON" : "🖥️ Desktop OFF");

      btnPref.textContent = `🔔 Preferências (${status.join(" • ")})`;
    };

    // Define padrão se ainda não existir
    if (localStorage.getItem("pref_som") === null) {
      localStorage.setItem("pref_som", "1"); // som ligado por padrão
    }
    if (localStorage.getItem("pref_desktop") === null) {
      localStorage.setItem("pref_desktop", "1"); // desktop ligado por padrão
    }

    atualizarLabel();

    btnPref.addEventListener("click", async () => {
      // Alterna som
      const somAtual = localStorage.getItem("pref_som") === "1";
      const novoSom = !somAtual;
      localStorage.setItem("pref_som", novoSom ? "1" : "0");

      // Alterna desktop
      const desktopAtual = localStorage.getItem("pref_desktop") === "1";
      const novoDesktop = !desktopAtual;
      localStorage.setItem("pref_desktop", novoDesktop ? "1" : "0");

      // Se ativou desktop e ainda não tem permissão, pede
      if (
        novoDesktop &&
        "Notification" in window &&
        Notification.permission !== "granted"
      ) {
        try {
          await Notification.requestPermission();
        } catch (e) {}
      }

      atualizarLabel();

      this.toast(
        `Preferências atualizadas: Som ${novoSom ? "ativado" : "desativado"} • Notificação desktop ${novoDesktop ? "ativada" : "desativada"}`
      );
    });
  }

  // -----------------------------------------
  // DIFF PARA NOTIFICAÇÕES POR EQUIPE
  // -----------------------------------------
  processarDiffNotificacoes(estadoAntigo, estadoNovo) {
    // Monta mapas: chave = nome|equipe
    const mapNovo = new Map();
    estadoNovo.forEach((p) => {
      const chave = `${this.normalizar(p.nome)}|${this.normalizar(p.equipe)}`;
      mapNovo.set(chave, p.status);
    });

    // Primeira carga: só armazena, não notifica
    if (this.primeiraCargaNotificacao) {
      this.mapaEstadoAnterior = mapNovo;
      this.primeiraCargaNotificacao = false;
      return;
    }

    const mapAntigo = this.mapaEstadoAnterior;

    mapNovo.forEach((statusAtual, chave) => {
      const statusAnterior = mapAntigo.get(chave);
      if (!statusAnterior) {
        // novo usuário → notificação opcional (por enquanto não)
        return;
      }
      if (statusAtual === statusAnterior) return;

      const [nomeN, equipeN] = chave.split("|");
      this.notificarEquipeStatus({
        nome: nomeN,
        equipe: equipeN,
        statusAnterior,
        statusAtual
      });
    });

    this.mapaEstadoAnterior = mapNovo;
  }

  // -----------------------------------------
  // IDENTIFICAÇÃO DO OPERADOR / EQUIPE
  // -----------------------------------------
  async exibirIdentificacao() {
    const operador = this.operador;
    if (!operador) {
      if (this.headerUsuario) {
        this.headerUsuario.textContent = "Usuário não identificado";
      }
      return;
    }

    const ehAdmin =
      this.normalizar(operador) === this.normalizar("Anderson de Souza");

    try {
      const resp = await fetch(`${this.urlPHP}?acao=get_estado`, {
        cache: "no-store"
      });
      const dados = await resp.json();
      if (!dados.success) throw new Error("Erro ao buscar equipe");

      const userData = (dados.estado || []).find(
        (p) => this.normalizar(p.nome) === this.normalizar(operador)
      );
      const equipe = userData?.equipe || null;

      if (ehAdmin) {
        if (this.headerUsuario) {
          this.headerUsuario.textContent = `👑 Administrador: ${operador}`;
        }
        if (!this.jaSaudou) {
          this.toast(
            `👋 Bem-vindo, ${operador}! Você tem acesso administrativo.`
          );
          this.jaSaudou = true;
        }
        return;
      }

      if (!equipe) {
        if (this.headerUsuario) {
          this.headerUsuario.textContent = `${operador} • 🟠 Usuário sem equipe definida`;
        }
        if (!this.jaSaudou) {
          this.toast(
            `⚠️ ${operador}, sua equipe não está cadastrada no painel.`,
            true
          );
          this.jaSaudou = true;
        }
        return;
      }

      if (this.headerUsuario) {
        this.headerUsuario.textContent = `👤 Operador: ${operador} • Equipe: ${equipe}`;
      }
      if (!this.jaSaudou) {
        this.toast(
          `👋 Bem-vindo, ${operador}! Você pertence à equipe ${equipe}.`
        );
        this.jaSaudou = true;
      }
    } catch (e) {
      console.warn("⚠️ Erro ao exibir identificação:", e);
      if (this.headerUsuario) {
        this.headerUsuario.textContent = "Usuário não identificado";
      }
    }
  }

  // -----------------------------------------
  // RENDERIZAÇÃO PRINCIPAL
  // -----------------------------------------
  renderizarParticipantes(lista = this.estado) {
    const container = this.listaParticipantes;
    if (!container) return;

    container.innerHTML = "";

    // Agrupa por equipe
    const grupos = {};
    (lista || []).forEach((p) => {
      if (!grupos[p.equipe]) grupos[p.equipe] = [];
      grupos[p.equipe].push(p);
    });

    // Monta bloco por equipe
    Object.keys(grupos).forEach((equipe) => {
      const participantes = grupos[equipe] || [];
      const qtd = participantes.length;

      const ativos = participantes.filter((p) =>
        ["ativo", "disponivel"].includes(p.status)
      ).length;
      const pausas = participantes.filter((p) => p.status === "pausa").length;
      const espera = participantes.filter((p) => p.status === "espera").length;
      const aguardando = participantes.filter(
        (p) => p.status === "aguardando"
      ).length;

      const box = document.createElement("div");
      box.className = "equipe-bloco";
      box.innerHTML = `
        <h3>
          ${equipe}
          <span class="contador-equipe">
            <i class="fas fa-users"></i> ${qtd} operador${
        qtd > 1 ? "es" : ""
      }
            <span class="detalhes-status">
              <span class="ativo">🟢 ${ativos}</span>
              <span class="espera">⏳ ${espera}</span>
              <span class="aguardando">🟢⚡ ${aguardando}</span>
              <span class="pausa">☕ ${pausas}</span>
            </span>
          </span>
        </h3>
        <div class="equipe-operadores"></div>
      `;

      const inner = box.querySelector(".equipe-operadores");

      // Ordena pela prioridade do status
      participantes
        .slice()
        .sort(
          (a, b) =>
            (this.pesoStatus[a.status] ?? 9) -
            (this.pesoStatus[b.status] ?? 9)
        )
        .forEach((p) => {
          inner.appendChild(this.criarItemOperador(p));
        });

      container.appendChild(box);
    });

    // HUD superior com resumo
    const totalOperadores = lista.length;
    const totalEquipes = Object.keys(grupos).length;
    if (this.hudOperador) {
      if (this.modoMinhaEquipe) {
        const minhaEquipe = Object.keys(grupos)[0] || "";
        this.hudOperador.textContent = `👥 ${minhaEquipe} — ${totalOperadores} operador${
          totalOperadores > 1 ? "es" : ""
        }`;
      } else {
        this.hudOperador.textContent = `🌎 ${totalEquipes} equipe${
          totalEquipes > 1 ? "s" : ""
        } • ${totalOperadores} operador${
          totalOperadores > 1 ? "es" : ""
        }`;
      }
    }

    // Evento para outros scripts ajustarem botões/estilos
    document.dispatchEvent(new CustomEvent("ui:operadores-renderizados"));
setTimeout(() => {
  if (window.Cronometro) {
    document.querySelectorAll(".cronometro").forEach(div => {
      const nome = div.dataset.nome;
      const status = div.dataset.status;
      const inicio = div.dataset.tinicio;

      if (["espera", "pausa", "aguardando"].includes(status)) {
        Cronometro.iniciar(nome, status, inicio);
      } else {
        Cronometro.parar(nome, false);
      }
    });
  }
}, 50);

  }

 criarItemOperador(p) {
    const item = document.createElement("div");
    item.className = `op-item ${p.status || "ativo"}`;

    // Destaque visual para o operador logado
    if (this.normalizar(p.nome) === this.normalizar(this.operador)) {
        item.classList.add("operador-logado");
    }

    const tEntrada = p.tempo_entrada || "";
    let tempoInicial = "--:--";

    if (tEntrada && ["espera","aguardando","pausa"].includes(p.status)) {
        const diff = (Date.now() - new Date(tEntrada).getTime()) / 1000;
        tempoInicial = this.formatarTempo(diff);
    }

    const labelPosicao =
        p.status === "espera" && p.posicao_fila
        ? ` • #${p.posicao_fila}`
        : p.status === "aguardando"
        ? " • Vaga liberada"
        : "";

    item.innerHTML = `
        <strong>${p.nome}</strong>
        <small>${this.formatarStatus(p.status)}${labelPosicao}</small>

        <div class="cronometro"
             data-nome="${p.nome}"
             data-status="${p.status}"
             data-tinicio="${tEntrada}">
             ${tempoInicial}
        </div>

        <div class="botoes-operador" aria-live="polite"></div>
    `;

    return item;
}


  // -----------------------------------------
  // AÇÕES GENÉRICAS (POST para o PHP)
  // -----------------------------------------
  async enviarAcao(acao, dados) {
    try {
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
        this.toast(`✅ ${ret.msg || "Ação executada."}`);
        await this.sincronizarAtualizacoes();

        // 🔗 Integração com módulo Cronometro (frontend)
        if (window.Cronometro) {
          if (acao === "entrar_fila") {
            Cronometro.iniciar(payload.nome, "espera");
          }
          if (acao === "forcar_pausa") {
            Cronometro.iniciar(payload.nome, "pausa");
          }
          if (acao === "voltar_disponivel") {
            Cronometro.parar(payload.nome);
          }
        }

        document.dispatchEvent(
          new CustomEvent("status:alterado", {
            detail: { nome: payload.nome, acao }
          })
        );
      } else {
        this.toast(`⚠️ ${ret.error || "Ação não permitida."}`, true);
      }
    } catch (e) {
      console.error("❌ Erro na ação:", e);
      this.toast("Erro de comunicação com o servidor.", true);
    }
  }

  // -----------------------------------------
  // TOAST SIMPLES
  // -----------------------------------------
  toast(msg, erro = false) {
    const div = document.createElement("div");
    div.className = "toast-global";
    Object.assign(div.style, {
      position: "fixed",
      left: "20px",
      top: "20px",
      transform: "translateX(-160px)",
      background: "rgba(0,0,0,0.70)",
      padding: "10px 14px",
      borderRadius: "10px",
      backdropFilter: "blur(4px)",
      color: "#fff",
      zIndex: 99999,
      borderLeft: `6px solid ${erro ? "#ff4444" : "#00ff88"}`,
      opacity: 0
    });

    div.innerHTML = `<span style="margin-right:6px;">${
      erro ? "⚠️" : "💬"
    }</span> ${msg}`;
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
  // -----------------------------------------
// NOTIFICAÇÃO CORRETA — Respeita Preferências do Operador
// -----------------------------------------
notificarEquipeStatus({ nome, equipe, statusAnterior, statusAtual }) {
    const prefs = this.carregarPreferencias();

    const operadorAtual = this.estado.find(
        (p) => this.normalizar(p.nome) === this.normalizar(this.operador)
    );

    const mesmaEquipe =
        operadorAtual &&
        this.normalizar(operadorAtual.equipe) === this.normalizar(equipe);

    const ehAdmin =
        this.normalizar(this.operador) === this.normalizar("Anderson de Souza");

    if (!mesmaEquipe && !ehAdmin) return;

    let mensagem = "";

    if (statusAtual === "pausa") mensagem = `☕ ${nome} entrou em pausa.`;
    else if (statusAnterior === "pausa" && statusAtual === "ativo") mensagem = `✅ ${nome} voltou da pausa.`;
    else if (statusAtual === "espera") mensagem = `🕓 ${nome} entrou na fila.`;
    else if (statusAtual === "aguardando") mensagem = `⚡ ${nome} está com vaga liberada.`;
    else if (statusAtual === "expirada") mensagem = `🔴 ${nome} teve a pausa expirada.`;
    else mensagem = `${nome} mudou o status.`;

    // **Toast SEMPRE aparece (é visual interno)**
    this.toast(mensagem);

    // 🔊 SOM — somente se ativado
    if (prefs.som && window.somPausa?.aviso) {
        window.somPausa.aviso(mensagem);
    }

    // 🖥️ Notificação Desktop (Windows) — apenas se ativada
    if (
        prefs.notificacao &&
        "Notification" in window &&
        Notification.permission === "granted"
    ) {
        new Notification("Alteração de Status", {
            body: mensagem,
            icon: "https://tgameajuda.com/img/principal/bot-tga.webp"
        });
    }
}


// -----------------------------------------
// PREFERÊNCIAS DO OPERADOR (Som / Notificação / Layout)
// -----------------------------------------
carregarPreferencias() {
    return {
        som: localStorage.getItem("pref_som") === "1",
        notificacao: localStorage.getItem("pref_desktop") === "1",
        layout: localStorage.getItem("pref_layout") || "default"
    };
}

salvarPreferencias(prefs) {
    localStorage.setItem("pref_som", prefs.som ? "1" : "0");
    localStorage.setItem("pref_desktop", prefs.notificacao ? "1" : "0");
    localStorage.setItem("pref_layout", prefs.layout);
}



  // -----------------------------------------
  // ADMIN: DERRUBAR PAUSADOS / FILA (por EQUIPE)
  // -----------------------------------------
  async adminDerrubarPausadosEquipe() {
    const ehAdmin =
      this.normalizar(this.operador) === this.normalizar("Anderson de Souza");
    if (!ehAdmin) {
      this.toast("Apenas o administrador pode executar esta ação.", true);
      return;
    }

    const minhaEquipe = this.buscarEquipePorOperador(this.operador);
    if (!minhaEquipe) {
      this.toast("Não foi possível identificar sua equipe para derrubar pausados.", true);
      return;
    }

    const confirmar = confirm(
      `Você deseja derrubar TODOS os operadores EM PAUSA da equipe "${minhaEquipe}" para Disponível?`
    );
    if (!confirmar) return;

    const alvos = (this.estado || []).filter(
      (p) =>
        this.normalizar(p.equipe) === this.normalizar(minhaEquipe) &&
        p.status === "pausa"
    );

    if (!alvos.length) {
      this.toast("Nenhum operador em pausa na sua equipe.", false);
      return;
    }

    try {
      for (const p of alvos) {
        await fetch(`${this.urlPHP}?acao=voltar_disponivel`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ nome: p.nome, equipe: p.equipe })
        }).catch(() => {});
        await new Promise((r) => setTimeout(r, 80));
      }
      this.toast(`🔴 ${alvos.length} operador(es) em pausa voltaram a Disponível (equipe ${minhaEquipe}).`);
      await this.sincronizarAtualizacoes();
    } catch (e) {
      console.error(e);
      this.toast("Erro ao derrubar pausados da equipe.", true);
    }
  }

  async adminDerrubarFilaEquipe() {
    const ehAdmin =
      this.normalizar(this.operador) === this.normalizar("Anderson de Souza");
    if (!ehAdmin) {
      this.toast("Apenas o administrador pode executar esta ação.", true);
      return;
    }

    const minhaEquipe = this.buscarEquipePorOperador(this.operador);
    if (!minhaEquipe) {
      this.toast("Não foi possível identificar sua equipe para derrubar fila.", true);
      return;
    }

    const confirmar = confirm(
      `Você deseja derrubar TODOS os operadores da FILA (espera/aguardando) da equipe "${minhaEquipe}" para Disponível?`
    );
    if (!confirmar) return;

    const alvos = (this.estado || []).filter(
      (p) =>
        this.normalizar(p.equipe) === this.normalizar(minhaEquipe) &&
        ["espera", "aguardando"].includes(p.status)
    );

    if (!alvos.length) {
      this.toast("Nenhum operador na fila de espera da sua equipe.", false);
      return;
    }

    try {
      for (const p of alvos) {
        await fetch(`${this.urlPHP}?acao=voltar_disponivel`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ nome: p.nome, equipe: p.equipe })
        }).catch(() => {});
        await new Promise((r) => setTimeout(r, 80));
      }
      this.toast(`🟡 ${alvos.length} operador(es) na fila foram definidos como Disponível (equipe ${minhaEquipe}).`);
      await this.sincronizarAtualizacoes();
    } catch (e) {
      console.error(e);
      this.toast("Erro ao derrubar fila da equipe.", true);
    }
  }

  // -----------------------------------------
  // BOTÕES FLUTUANTES DE ADMIN
  // -----------------------------------------
  injetarToolbarAdmin() {
    const ehAdmin =
      this.normalizar(this.operador) === this.normalizar("Anderson de Souza");
    if (!ehAdmin) return;
    if (document.getElementById("toolbarAdminPausaFila")) return;

    const wrap = document.createElement("div");
    wrap.id = "toolbarAdminPausaFila";
    Object.assign(wrap.style, {
      position: "fixed",
      right: "16px",
      bottom: "16px",
      zIndex: 9999,
      display: "flex",
      flexDirection: "column",
      gap: "8px"
    });

    const btnPausa = document.createElement("button");
    btnPausa.textContent = "🔴 Derrubar Pausados";
    Object.assign(btnPausa.style, {
      padding: "8px 12px",
      background: "#ff3b30",
      color: "#fff",
      border: "none",
      borderRadius: "10px",
      boxShadow: "0 8px 20px rgba(0,0,0,.25)",
      cursor: "pointer",
      fontWeight: "600"
    });
    btnPausa.onmouseenter = () => (btnPausa.style.filter = "brightness(1.05)");
    btnPausa.onmouseleave = () => (btnPausa.style.filter = "none");
    btnPausa.onclick = () => this.adminDerrubarPausadosEquipe();

    const btnFila = document.createElement("button");
    btnFila.textContent = "🟡 Derrubar Fila";
    Object.assign(btnFila.style, {
      padding: "8px 12px",
      background: "#ffcc00",
      color: "#000",
      border: "none",
      borderRadius: "10px",
      boxShadow: "0 8px 20px rgba(0,0,0,.25)",
      cursor: "pointer",
      fontWeight: "600"
    });
    btnFila.onmouseenter = () => (btnFila.style.filter = "brightness(1.05)");
    btnFila.onmouseleave = () => (btnFila.style.filter = "none");
    btnFila.onclick = () => this.adminDerrubarFilaEquipe();

    wrap.appendChild(btnPausa);
    wrap.appendChild(btnFila);
    document.body.appendChild(wrap);
  }

  // -----------------------------------------
  // FILTRO: MINHA EQUIPE / TODAS EQUIPES
  // -----------------------------------------
  inicializarFiltroEquipes() {
    const btnEquipe = document.getElementById("btnFiltroEquipe");
    const btnTodas = document.getElementById("btnFiltroTodas");
    const lista = document.getElementById("listaParticipantes");

    if (!btnEquipe || !btnTodas || !lista) return;

    const operador = this.operador;

    // Mostrar apenas minha equipe
    btnEquipe.onclick = () => {
      const minhaEquipe = this.buscarEquipePorOperador(operador) || null;
      if (!minhaEquipe) {
        this.toast("Usuário sem equipe definida.", true);
        return;
      }
      const filtrada = this.estado.filter((p) => p.equipe === minhaEquipe);
      this.modoMinhaEquipe = true;
      document.body.classList.add("modo-minha-equipe");
      lista.classList.remove("todas-equipes");
      this.renderizarParticipantes(filtrada);
      btnEquipe.style.display = "none";
      btnTodas.style.display = "inline-block";
    };

    // Mostrar todas as equipes
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

// Exposição global
window.ControlePausaSistema = ControlePausaSistema;

// Bootstrap ao carregar a página
document.addEventListener("DOMContentLoaded", () => {

  // Inicializa o núcleo principal somente uma vez
  if (!window.controle) {
    window.controle = new ControlePausaSistema();
    window.controle.iniciar();
  }

  // 🔄 Retomar cronômetros já ativos (fila/pausa) usando tempo_entrada
  if (window.Cronometro && typeof Cronometro.retomar === "function") {
    Cronometro.retomar();
  }

  // Focar automaticamente na própria equipe (se existir o botão)
  setTimeout(() => {
    const btn = document.getElementById("btnFiltroEquipe");
    if (btn) btn.click();
  }, 500);
});

// ========================================
// MODAL DE PREFERÊNCIAS
// ========================================
document.addEventListener("DOMContentLoaded", () => {
    const modal = document.getElementById("modalPreferencias");
    const btn = document.getElementById("btnPreferencias");
    const btnSalvar = document.getElementById("btnSalvarPreferencias");

    const cbSom = document.getElementById("prefSom");
    const cbDesk = document.getElementById("prefDesktop");

    btn.onclick = () => {
        const prefs = window.controle.carregarPreferencias(); /// <-- CORRETO
        cbSom.checked = prefs.som;
        cbDesk.checked = prefs.notificacao;
        modal.classList.remove("hidden");
    };

    btnSalvar.onclick = () => {
        window.controle.salvarPreferencias({   /// <-- CORRETO
            som: cbSom.checked,
            notificacao: cbDesk.checked,
            layout: "default"
        });

        modal.classList.add("hidden");
        window.controle.toast("Preferências atualizadas com sucesso!");   /// <-- CORRETO
    };

    modal.onclick = (e) => {
        if (e.target === modal) modal.classList.add("hidden");
    }
});

