// ===================================================================
// autenticacao.js (v1.1)
// Sistema de autenticação de usuário com suporte a versão automática
// ===================================================================

class SistemaAutenticacao {
  constructor() {
    const cfg = window.SISTEMA_CONFIG || {};
    this.usuarioAtual = null;
    this.usuarioADM = "adm";
    this.primeirosNomes = cfg.nomesPermitidos || ["Anderson", "Carlos", "Maria"];
    this.usuarioId = cfg.usuarioId || 0;
    this.sessaoToken = cfg.sessaoToken || "";

    console.debug(`[Auth] Inicializado | Versão: ${cfg.versao || "indefinida"}`);
  }

  atualizarConfiguracao(novaConfig) {
    const cfg = novaConfig || window.SISTEMA_CONFIG || {};
    this.primeirosNomes = cfg.nomesPermitidos || this.primeirosNomes;
    this.usuarioId = cfg.usuarioId || this.usuarioId;
    this.sessaoToken = cfg.sessaoToken || this.sessaoToken;
  }

  salvarUsuario(nome) {
    try {
      localStorage.setItem("usuarioPausa", nome);
      this.usuarioAtual = nome;
      this.mostrarUsuarioAtual();
    } catch (_) {}
  }

  carregarUsuario() {
    try {
      const nome = localStorage.getItem("usuarioPausa");
      if (nome) this.usuarioAtual = nome;
      return nome;
    } catch (_) {
      return null;
    }
  }

  limparUsuario() {
    try { localStorage.removeItem("usuarioPausa"); } catch (_) {}
    this.usuarioAtual = null;
  }

  verificarNomeValido(nome) {
    if (!nome) return false;
    const primeiro = nome.split(" ")[0]?.toLowerCase();
    const nomesLower = this.primeirosNomes.map(n => n.toLowerCase());
    return nomesLower.includes(primeiro) || nome.toLowerCase() === this.usuarioADM;
  }

  verificarPermissao(nome) {
    if (!this.usuarioAtual) return false;
    if (this.usuarioAtual === this.usuarioADM) return true;
    return (
      this.usuarioAtual.split(" ")[0]?.toLowerCase() ===
      nome.split(" ")[0]?.toLowerCase()
    );
  }

  async mostrarModalLogin() {
    return new Promise(resolve => {
      if (!this.primeirosNomes?.length) {
        console.warn("[Auth] Nenhum nome permitido configurado");
        resolve(false);
        return;
      }

      const overlay = document.createElement("div");
      overlay.className = "modal-overlay";
      overlay.innerHTML = `
        <div class="modal-login">
          <div class="modal-header"><h3>Identificação</h3></div>
          <div class="modal-body">
            <p>Digite seu primeiro nome:</p>
            <input type="text" id="nome-usuario" class="input-login" placeholder="Ex: Anderson" autocomplete="off">
            <div class="modal-nomes">Permitidos: ${this.primeirosNomes.join(", ")}</div>
          </div>
          <div class="modal-footer">
            <button id="btn-entrar" class="btn btn-primary">Entrar</button>
          </div>
        </div>`;
      document.body.appendChild(overlay);

      const input = overlay.querySelector("#nome-usuario");
      const btn = overlay.querySelector("#btn-entrar");

      const entrar = () => {
        const nome = input.value.trim();
        if (!this.verificarNomeValido(nome)) {
          input.style.border = "2px solid red";
          input.focus();
          return;
        }
        this.salvarUsuario(nome);
        overlay.remove();
        resolve(true);
      };

      btn.onclick = entrar;
      input.addEventListener("keypress", e => e.key === "Enter" && entrar());
      document.addEventListener("keydown", e => e.key === "Escape" && overlay.remove());
      input.focus();
    });
  }

  mostrarUsuarioAtual() {
    if (!this.usuarioAtual) return;
    const header = document.querySelector("header");
    if (!header) return;

    const info = document.createElement("div");
    info.className = "user-info";
    info.innerHTML = `
      <i class="fas fa-user"></i> ${this.usuarioAtual}
      <button class="btn-trocar" title="Trocar usuário"
        onclick="sistemaAutenticacao.limparUsuario(); location.reload();">
        <i class="fas fa-sync-alt"></i> Trocar
      </button>`;
    header.querySelector(".user-info")?.remove();
    header.appendChild(info);
  }

  async iniciarAutenticacao() {
    const usuario = this.carregarUsuario();
    if (!usuario) {
      const sucesso = await this.mostrarModalLogin();
      if (!sucesso) return null;
    }
    this.mostrarUsuarioAtual();
    return this.usuarioAtual;
  }
}

// Inicialização segura global
document.addEventListener("DOMContentLoaded", () => {
  setTimeout(() => {
    if (!window.sistemaAutenticacao) {
      window.sistemaAutenticacao = new SistemaAutenticacao();
      window.sistemaAutenticacao.iniciarAutenticacao().then(u =>
        console.debug(`[Auth] Usuário autenticado: ${u}`)
      );
    }
  }, 300);
});
