// ============================================================
// controle_pausa.js (v5.1)
// Núcleo de estado, render e eventos do Sistema de Pausas
// - Integra com php/controle_pausa_novo.php (v1.15+)
// - Compatível com interface_botoes.js e acoes_operador.js
// ============================================================

console.log(
  "%c[Controle de Pausa v5.1] núcleo carregado",
  "color:#00ff88;font-weight:bold;"
);

class ControlePausaSistema {
  constructor() {
    // -----------------------------------------
    // CONFIGURAÇÕES BÁSICAS
    // -----------------------------------------
    this.urlPHP = "./php/controle_pausa_novo.php";
    this.intervaloAtualizacao = 2000; // 2s - polling único
    this.intervaloCronometro = 1000;  // 1s - contador visual
    this.maxPausas = 2;

    // -----------------------------------------
    // ESTADO EM MEMÓRIA
    // -----------------------------------------
    this.estado = [];         // lista de operadores vindos do PHP
    this.atualizando = false; // trava de requisição
    this.jaSaudou = false;    // evita múltiplas boas-vindas
    this.modoMinhaEquipe = true; // inicia focado na equipe do operador

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
        aguardando: "🟢 Vaga aberta (aguardando confirmação)",
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

    // Botão de administração (derrubar todos)
    this.injetarToolbarAdmin();

    // Polling de estado
    setInterval(() => this.sincronizarAtualizacoes(), this.intervaloAtualizacao);

    // Atualização visual dos cronômetros
    setInterval(() => this.atualizarCronometros(), this.intervaloCronometro);

    // Permissão de notificação
    try {
      if ("Notification" in window && Notification.permission !== "granted") {
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

      // Evita re-renderização se nada mudou
      if (JSON.stringify(novoEstado) === JSON.stringify(this.estado)) return;

      this.estado = novoEstado;

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

      // Evento global para outros módulos (status_cards, interface_botoes etc.)
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
  // CRONÔMETROS VISUAIS (espera / aguardando / pausa)
  // -----------------------------------------
  atualizarCronometros() {
    const itens = document.querySelectorAll(".op-item .tempo");
    itens.forEach((div) => {
      const inicio = div.dataset.tinicio;
      const status = div.dataset.status;
      if (!inicio) return;

      // Conta tempo para espera, aguardando e pausa
      if (!["espera", "aguardando", "pausa"].includes(status)) return;

      const diff = (Date.now() - new Date(inicio).getTime()) / 1000;
      div.textContent = this.formatarTempo(diff);
    });
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
  }

  criarItemOperador(p) {
    const item = document.createElement("div");
    item.className = `op-item ${p.status || "ativo"}`;

    // Tempo dinâmico: usa tempo_entrada como referência
    const tEntrada = p.tempo_entrada || null;
    const tempoInicial =
      p.tempo_espera_dinamico != null
        ? this.formatarTempo(p.tempo_espera_dinamico)
        : "--:--";

    const labelPosicao =
      p.status === "espera" && p.posicao_fila
        ? ` • #${p.posicao_fila}`
        : p.status === "aguardando"
        ? " • Vaga liberada"
        : "";

    item.innerHTML = `
      <strong>${p.nome}</strong>
      <small>${this.formatarStatus(p.status)}${labelPosicao}</small>
      <div class="tempo" 
           data-tinicio="${tEntrada || ""}" 
           data-status="${p.status || "ativo"}">${tempoInicial}</div>
      <div class="botoes-operador" aria-live="polite"></div>
    `;

    // Aqui interface_botoes.js vai assumir os botões,
    // ouvindo "ui:operadores-renderizados" e "estado:atualizado".

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

        document.dispatchEvent(
          new CustomEvent("status:alterado", {
            detail: { nome: payload.nome, acao }
          })
        );

        this.notificarEquipeStatus(payload.nome, acao);
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
  // NOTIFICAÇÃO PARA EQUIPE (toast + Notification)
  // -----------------------------------------
  notificarEquipeStatus(nome, acao) {
    const operador = this.estado.find(
      (p) => this.normalizar(p.nome) === this.normalizar(nome)
    );
    if (!operador) return;

    const equipe = operador.equipe;
    const mesmaEquipe = this.estado.filter((p) => p.equipe === equipe);

    // Não notifica o próprio operador duas vezes
    if (this.normalizar(nome) === this.normalizar(this.operador)) return;

    let mensagem = "";
    if (acao.includes("forcar_pausa") || acao.includes("entrar_fila")) {
      mensagem = `☕ ${nome} alterou o status de pausa/fila.`;
    } else if (acao.includes("voltar_disponivel")) {
      mensagem = `✅ ${nome} voltou a ficar disponível.`;
    } else if (acao.includes("expirar")) {
      mensagem = `🔴 ${nome} teve a pausa expirada.`;
    } else {
      mensagem = `${nome} alterou o status.`;
    }

    // Apenas exibe toast para quem está na mesma equipe
    if (
      this.operador &&
      mesmaEquipe.some(
        (p) => this.normalizar(p.nome) === this.normalizar(this.operador)
      )
    ) {
      this.toast(mensagem);
    }

    // Notificação nativa do navegador (se permitido)
    try {
      if ("Notification" in window && Notification.permission === "granted") {
        new Notification("Alteração de Status", {
          body: mensagem,
          icon: "https://tgameajuda.com/img/principal/bot-tga.webp"
        });
      }
    } catch (e) {
      // ignora
    }
  }

  // -----------------------------------------
  // ADMIN: DERRUBAR TODOS PARA DISPONÍVEL
  // -----------------------------------------
  async adminForcarTodosDisponivel() {
    const ehAdmin =
      this.normalizar(this.operador) === this.normalizar("Anderson de Souza");
    if (!ehAdmin) {
      this.toast("Apenas o administrador pode executar esta ação.", true);
      return;
    }

    const confirmar = confirm(
      "Você deseja derrubar TODOS das pausas/fila (todas as equipes) para Disponível?"
    );
    if (!confirmar) return;

    // 1) Tenta endpoint em lote
    try {
      const resp = await fetch(`${this.urlPHP}?acao=forcar_todos_disponivel`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ admin: this.operador })
      });
      const ret = await resp.json();
      if (ret && ret.success) {
        this.toast("✅ Todos foram definidos como Disponível.");
        await this.sincronizarAtualizacoes();
        return;
      }
    } catch (e) {
      // se der erro, cai no fallback
    }

    // 2) Fallback: derruba um por um silenciosamente
    const alvos = (this.estado || []).filter((p) =>
      ["pausa", "espera", "aguardando", "expirada"].includes(p.status)
    );
    if (!alvos.length) {
      this.toast("Não há ninguém em pausa/fila para derrubar.");
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
      this.toast(`✅ ${alvos.length} operador(es) voltaram a Disponível.`);
      await this.sincronizarAtualizacoes();
    } catch (e) {
      console.error(e);
      this.toast("Erro ao derrubar todos.", true);
    }
  }

  // -----------------------------------------
  // BOTÃO FLUTUANTE DE ADMIN
  // -----------------------------------------
  injetarToolbarAdmin() {
    const ehAdmin =
      this.normalizar(this.operador) === this.normalizar("Anderson de Souza");
    if (!ehAdmin) return;
    if (document.getElementById("btnAdminDerrubarTodos")) return;

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
    btn.onmouseenter = () => (btn.style.filter = "brightness(1.05)");
    btn.onmouseleave = () => (btn.style.filter = "none");
    btn.onclick = () => this.adminForcarTodosDisponivel();

    document.body.appendChild(btn);
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
  if (!window.controle) {
    window.controle = new ControlePausaSistema();
    window.controle.iniciar();
  }
  // Gatilho inicial para focar na própria equipe, se existir
  setTimeout(() => {
    const btn = document.getElementById("btnFiltroEquipe");
    btn && btn.click();
  }, 500);
});
