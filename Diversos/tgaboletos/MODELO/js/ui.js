/**
 * ui.js
 * ------------------------------------------------------------------
 * Funções de renderização reutilizáveis (helpers de UI). Não sabem
 * navegar sozinhas — apenas transformam dados em HTML. Quem decide
 * "o que mostrar agora" é o app.js (router).
 * ------------------------------------------------------------------
 */
window.CentralBoletos = window.CentralBoletos || {};

(function () {
  const STATUS_LABEL = {
    configurado: "Configurado",
    em_documentacao: "Em documentação",
    teste_disponivel: "Teste disponível",
  };
  const STATUS_CLASS = {
    configurado: "badge badge--ok",
    em_documentacao: "badge badge--pending",
    teste_disponivel: "badge badge--info",
  };

  function escapeHtml(str) {
    if (str === undefined || str === null) return "";
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function badgeStatus(status) {
    const label = STATUS_LABEL[status] || status;
    const cls = STATUS_CLASS[status] || "badge";
    return `<span class="${cls}">${escapeHtml(label)}</span>`;
  }

  function cardBanco(banco) {
    return `
      <a class="card-banco" href="#/banco/${banco.id}" style="--accent:${banco.corDestaque}">
        <div class="card-banco__top">
          <span class="card-banco__marca" aria-hidden="true">${escapeHtml(banco.nome.charAt(0))}</span>
          ${badgeStatus(banco.status)}
        </div>
        <h3 class="card-banco__nome">${escapeHtml(banco.nome)}</h3>
        <p class="card-banco__codigo">Código FEBRABAN: ${escapeHtml(banco.codigoBanco || "—")}</p>
        <p class="card-banco__resumo">${escapeHtml((banco.resumo || "").slice(0, 110))}${banco.resumo && banco.resumo.length > 110 ? "…" : ""}</p>
        <span class="card-banco__cta">Abrir configuração →</span>
      </a>`;
  }

  function campoLinha(campo) {
    const obrig = campo.obrigatorio
      ? '<span class="tag tag--req">Obrigatório</span>'
      : '<span class="tag tag--opt">Opcional</span>';
    const critico = campo.critico ? '<span class="tag tag--critico">Campo-chave</span>' : "";
    const inferido = campo.inferido
      ? `<span class="tag tag--inferido" title="${escapeHtml(campo.observacaoInferencia || "Significado inferido a partir do contexto da tela, não confirmado explicitamente.")}">Inferido</span>`
      : "";
    const opcoes = campo.opcoesObservadas
      ? `<div class="campo__opcoes"><strong>Opções observadas na tela:</strong> ${campo.opcoesObservadas.map(escapeHtml).join(", ")}</div>`
      : "";
    return `
      <div class="campo-card">
        <div class="campo-card__head">
          <span class="campo-card__nome">${escapeHtml(campo.nome)}</span>
          <span class="campo-card__tipo">${escapeHtml(campo.tipo || "")}</span>
        </div>
        <div class="campo-card__tags">${obrig} ${critico} ${inferido}</div>
        <p class="campo-card__desc">${escapeHtml(campo.paraQueServe || "")}</p>
        ${opcoes}
      </div>`;
  }

  function campoIntegracao(campo) {
    return `
      <div class="campo-card campo-card--api">
        <div class="campo-card__head">
          <span class="campo-card__nome">${escapeHtml(campo.nome)}</span>
          ${campo.obrigatorio ? '<span class="tag tag--req">Obrigatório</span>' : '<span class="tag tag--opt">Opcional</span>'}
        </div>
        <p class="campo-card__desc"><strong>Para que serve:</strong> ${escapeHtml(campo.paraQueServe)}</p>
        <p class="campo-card__desc"><strong>Onde conseguir:</strong> ${escapeHtml(campo.ondeConseguir)}</p>
        <p class="campo-card__desc"><strong>Ambiente:</strong> ${escapeHtml(campo.ambiente)}</p>
        <p class="campo-card__exemplo"><strong>Exemplo:</strong> <code>${escapeHtml(campo.exemplo)}</code></p>
      </div>`;
  }

  window.CentralBoletos.ui = {
    escapeHtml,
    badgeStatus,
    cardBanco,
    campoLinha,
    campoIntegracao,
    STATUS_LABEL,
  };
})();
