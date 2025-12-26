// ===================================================
// loader.js — Carregador dinâmico de módulos HTML
// ---------------------------------------------------
// RESPONSABILIDADE:
// - Carregar arquivos HTML dentro do painel principal
// - Executar scripts internos do HTML carregado
// - Evitar recarregamento da página
// ---------------------------------------------------
// DEPENDE DE:
// - <div id="conteudo-modulo"></div> no HTML
// ===================================================

(function () {

  const container = document.getElementById("conteudo-modulo");

  if (!container) {
    console.warn("[LOADER] Container #conteudo-modulo não encontrado.");
    return;
  }

  /**
   * Abre um módulo HTML dentro do painel
   * @param {string} arquivo Caminho do HTML (ex: 'criar.html')
   */
  window.abrirModulo = async function (arquivo) {

    if (!arquivo) {
      console.warn("[LOADER] Nenhum arquivo informado.");
      return;
    }

    try {
      // Feedback visual
      container.innerHTML = `
        <div style="padding:20px; color:#94a3b8">
          Carregando módulo...
        </div>
      `;

      const resp = await fetch(arquivo, {
        cache: "no-store",
        credentials: "same-origin"
      });

      if (!resp.ok) {
        throw new Error(`HTTP ${resp.status} ao carregar ${arquivo}`);
      }

      const html = await resp.text();

      // Limpa conteúdo anterior
      container.innerHTML = "";

      // Cria um wrapper para o HTML carregado
      const wrapper = document.createElement("div");
      wrapper.innerHTML = html;

      // Executa scripts do módulo
      const scripts = wrapper.querySelectorAll("script");

      scripts.forEach(script => {
        const novoScript = document.createElement("script");

        // Copia atributos
        if (script.src) {
          novoScript.src = script.src;
          novoScript.defer = true;
        } else {
          novoScript.textContent = script.textContent;
        }

        document.body.appendChild(novoScript);
        script.remove();
      });

      // Injeta o HTML sem scripts
      container.appendChild(wrapper);

      console.log(`[LOADER] Módulo carregado: ${arquivo}`);

    } catch (e) {
      console.error("[LOADER] Erro:", e);

      container.innerHTML = `
        <div style="padding:20px; color:#ef4444">
          Erro ao carregar o módulo.<br>
          <small>${e.message}</small>
        </div>
      `;
    }
  };

})();
