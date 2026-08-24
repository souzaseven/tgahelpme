/**
 * bancos.js
 * ------------------------------------------------------------------
 * Renderiza a página de detalhe de UM banco:
 * Visão Geral → Portador → Dados do Cliente → Checklist → Passo a Passo
 * → Teste → Erros Comuns.
 * ------------------------------------------------------------------
 */
window.CentralBoletos = window.CentralBoletos || {};

(function () {
  function get() {
    return {
      ui: window.CentralBoletos.ui,
      portadorCamposBase: window.CentralBoletos.portadorCamposBase,
    };
  }

  function renderVisaoGeral(banco) {
    return `
      <section class="painel-secao" id="visao-geral">
        <h2>1. Visão Geral</h2>
        <div class="grid-info">
          <div><span class="grid-info__label">Banco</span><span>${banco.nome}</span></div>
          <div><span class="grid-info__label">Código FEBRABAN</span><span>${banco.codigoBanco || "—"}</span></div>
          <div><span class="grid-info__label">Tipo de integração</span><span>${(banco.tipoIntegracao || []).join(", ") || "A confirmar"}</span></div>
          <div><span class="grid-info__label">Autenticação</span><span>${banco.autenticacao || "A confirmar"}</span></div>
        </div>
        ${precisaRevisao(banco.ultimaAtualizacao) ? '<p class="alerta alerta--warn">⚠ Esta configuração precisa ser revisada.</p>' : ""}
      </section>`;
  }

  function precisaRevisao(iso) {
    if (!iso) return true;
    const data = new Date(iso);
    const seisMeses = 1000 * 60 * 60 * 24 * 180;
    return Date.now() - data.getTime() > seisMeses;
  }

  function renderPortador(banco) {
    const { ui, portadorCamposBase } = get();
    const relevantes = new Set(banco.camposPortadorRelevantes || []);
    const abas = [
      portadorCamposBase.abaDadosCedente,
      portadorCamposBase.abaInstrucoesBanco,
      portadorCamposBase.abaImpressao,
      portadorCamposBase.abaRemessaRetorno,
    ];

    const blocosAbas = abas
      .map((aba) => {
        const campos = (aba.campos || [])
          .map((c) => {
            const destaque = relevantes.has(c.nome) ? " campo-card--destaque" : "";
            return ui.campoLinha(c).replace('class="campo-card"', `class="campo-card${destaque}"`);
          })
          .join("");
        const variaveis = aba.variaveisDisponiveis
          ? `<div class="variaveis-box"><strong>Variáveis disponíveis:</strong>
              <div class="variaveis-grid">
                ${aba.variaveisDisponiveis.map((v) => `<span class="variavel-tag"><code>${v.tag}</code> ${v.significado}</span>`).join("")}
              </div></div>`
          : "";
        return `
          <div class="aba-portador">
            <h4>Aba: ${aba.titulo}</h4>
            ${aba.observacao ? `<p class="aviso-inline">${aba.observacao}</p>` : ""}
            <div class="campos-grid">${campos || '<p class="aviso-inline">Sem campos documentados nesta aba ainda.</p>'}</div>
            ${variaveis}
          </div>`;
      })
      .join("");

    return `
      <section class="painel-secao" id="portador">
        <h2>2. Portador — tela real do ERP</h2>
        <p class="painel-texto">
          Este banco é configurado no mesmo cadastro de <strong>Portador</strong> usado por todos os bancos.
          Os campos com contorno na cor do banco são os que mais importam especificamente para
          <strong>${banco.nome}</strong>; os demais fazem parte do modelo padrão do Portador. Clique nas abas
          para navegar e preencha os campos como referência — é uma réplica da tela real; nada aqui é salvo.
        </p>
        ${window.CentralBoletos.portadorMockup.render(banco)}
        <details class="detalhe-campos">
          <summary>Ver explicação campo a campo</summary>
          ${blocosAbas}
        </details>
      </section>`;
  }

  function renderIntegracaoApi(banco) {
    const { ui } = get();
    if (!banco.integracaoApi || !banco.integracaoApi.campos || !banco.integracaoApi.campos.length) {
      return `
        <section class="painel-secao" id="integracao-api">
          <h2>3. Parâmetros de Integração via API</h2>
          <p class="aviso-inline">Ainda não documentados para este banco. Envie imagens/documentação específica para completar esta seção.</p>
        </section>`;
    }
    return `
      <section class="painel-secao" id="integracao-api">
        <h2>3. Parâmetros de Integração via API</h2>
        <p class="aviso-inline aviso-inline--origem">${banco.integracaoApi.observacao}</p>
        <div class="campos-grid">
          ${banco.integracaoApi.campos.map(ui.campoIntegracao).join("")}
        </div>
      </section>`;
  }

  function renderDadosCliente(banco) {
    if (!banco.dadosCliente || !banco.dadosCliente.length) {
      return `<section class="painel-secao" id="dados-cliente"><h2>4. Dados que devemos solicitar ao cliente</h2><p class="aviso-inline">Pendente de documentação.</p></section>`;
    }
    return `
      <section class="painel-secao" id="dados-cliente">
        <h2>4. Dados que devemos solicitar ao cliente</h2>
        <ul class="lista-check">${banco.dadosCliente.map((d) => `<li>${d}</li>`).join("")}</ul>
      </section>`;
  }

  function renderChecklist(banco) {
    if (!banco.checklist || !banco.checklist.length) {
      return `<section class="painel-secao" id="checklist"><h2>5. Checklist antes de configurar</h2><p class="aviso-inline">Pendente de documentação.</p></section>`;
    }
    return `
      <section class="painel-secao" id="checklist">
        <h2>5. Checklist antes de configurar</h2>
        <ul class="lista-checklist">
          ${banco.checklist.map((item) => `<li><label><input type="checkbox"> ${item}</label></li>`).join("")}
        </ul>
      </section>`;
  }

  function renderPassoAPasso(banco) {
    if (!banco.passoAPasso || !banco.passoAPasso.length) {
      return `<section class="painel-secao" id="passo-a-passo"><h2>6. Como configurar</h2><p class="aviso-inline">Pendente de documentação.</p></section>`;
    }
    return `
      <section class="painel-secao" id="passo-a-passo">
        <h2>6. Como configurar</h2>
        <ol class="lista-passos">
          ${banco.passoAPasso.map((p) => `<li><strong>Passo ${p.passo} — ${p.titulo}.</strong> ${p.texto}</li>`).join("")}
        </ol>
      </section>`;
  }

  function renderTestes(banco) {
    const itens = (banco.testes && banco.testes.itens) || [];
    const linhas = itens.length
      ? itens.map((i) => `<li>${i.nome}: <span class="status-teste status-teste--${i.status}">${i.status === "nao_testado" ? "⚠ Não testada" : i.status}</span></li>`).join("")
      : "";
    return `
      <section class="painel-secao" id="testes">
        <h2>7. Testar Configuração</h2>
        <p class="alerta alerta--info">Teste ainda não integrado. Esta área será ativada na Fase 11 (Testes de API) do projeto.</p>
        ${linhas ? `<ul class="lista-check">${linhas}</ul>` : ""}
      </section>`;
  }

  function renderErros(banco) {
    if (!banco.erros || !banco.erros.length) {
      return `<section class="painel-secao" id="erros"><h2>8. Erros Comuns</h2><p class="aviso-inline">Nenhum erro catalogado ainda para este banco.</p></section>`;
    }
    return `
      <section class="painel-secao" id="erros">
        <h2>8. Erros Comuns</h2>
        ${banco.erros
          .map(
            (e) => `
          <div class="erro-card">
            <h4>${e.codigo} — ${e.titulo}</h4>
            <p><strong>Possíveis causas:</strong></p>
            <ul>${e.causas.map((c) => `<li>${c}</li>`).join("")}</ul>
          </div>`
          )
          .join("")}
      </section>`;
  }

  function renderPaginaBanco(banco) {
    const { ui } = get();
    return `
      <div class="pagina-banco" style="--accent:${banco.corDestaque}">
        <div class="pagina-banco__header">
          <a href="#/" class="voltar">← Bancos</a>
          <div class="pagina-banco__titulo">
            <h1>${banco.nome}</h1>
            ${ui.badgeStatus(banco.status)}
          </div>
        </div>
        ${renderVisaoGeral(banco)}
        ${renderPortador(banco)}
        ${renderIntegracaoApi(banco)}
        ${renderDadosCliente(banco)}
        ${renderChecklist(banco)}
        ${renderPassoAPasso(banco)}
        ${renderTestes(banco)}
        ${renderErros(banco)}
      </div>`;
  }

  window.CentralBoletos.bancosPage = { renderPaginaBanco };
})();
