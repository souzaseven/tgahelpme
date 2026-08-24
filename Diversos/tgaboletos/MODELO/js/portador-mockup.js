/**
 * portador-mockup.js
 * ------------------------------------------------------------------
 * Réplica visual (HTML/CSS) da tela real "Portador" do ERP, a partir
 * das imagens de referência enviadas (abas Dados do Cedente,
 * Instruções para o Banco, Impressão, Remessa/Retorno). Os campos
 * relevantes para o banco em questão recebem um contorno na cor de
 * destaque (--accent) daquele banco.
 *
 * É uma maquete SEM backend: os campos são de verdade editáveis
 * (digita, marca checkbox, escolhe opção), mas nada é salvo em
 * lugar nenhum — serve como referência visual e para o suporte
 * simular o preenchimento, não como formulário real. A única exceção
 * é "Seq. Remessa", que fica desabilitado de propósito porque na
 * tela real também é um campo controlado pelo sistema, não digitável.
 *
 * Cada rótulo com sublinhado pontilhado tem uma dica ao passar o
 * mouse (função `dica`, abaixo) explicando pra que o campo serve —
 * o texto vem sempre de portadorCamposBase (js/data.js), a mesma
 * fonte usada em "Ver explicação campo a campo", pra nunca duplicar
 * (e desalinhar) a mesma explicação em dois lugares.
 *
 * As cores do "aplicativo" são fixas (não seguem o tema claro/escuro
 * do painel) — é para parecer com a tela real do ERP, como um print,
 * e não com o resto do painel.
 * ------------------------------------------------------------------
 */
window.CentralBoletos = window.CentralBoletos || {};

(function () {
  // Ícones em SVG inline — evita depender de fonte de emoji do sistema.
  const ICONE_LUPA =
    '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><circle cx="10.5" cy="10.5" r="6.5"></circle><path d="M20 20l-4.5-4.5"></path></svg>';
  const ICONE_PASTA =
    '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M3 5.5A1.5 1.5 0 0 1 4.5 4h4.7l1.8 2H19.5A1.5 1.5 0 0 1 21 7.5v11A1.5 1.5 0 0 1 19.5 20h-15A1.5 1.5 0 0 1 3 18.5v-13z"></path></svg>';
  const ICONE_IMPRESSORA =
    '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="9" width="12" height="7" rx="1"></rect><path d="M6 9V4h12v5M8 16v4h8v-4"></path></svg>';
  const ICONE_FILIAL =
    '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M4 21V4h9v6h7v11h-6v-4h-4v4H4z"></path></svg>';
  const ICONE_ALERTA =
    '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.5 21.5 20h-19L12 3.5z"></path><path d="M12 10v4M12 17h.01"></path></svg>';
  const ICONE_CERTIFICADO =
    '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11A8 8 0 1 0 18 16"></path><path d="M20 5v6h-6"></path></svg>';
  const ICONE_ENGRENAGEM =
    '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="3.2"></circle><path d="M12 3v3M12 18v3M4.8 4.8l2.1 2.1M17.1 17.1l2.1 2.1M3 12h3M18 12h3M4.8 19.2l2.1-2.1M17.1 6.9l2.1-2.1"></path></svg>';
  const ICONE_MOEDA =
    '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h13l-3.5-3.5M20 16H7l3.5 3.5"></path></svg>';

  function destaque(nome, relevantes) {
    return relevantes.has(nome) ? " erp-mock__destaque" : "";
  }

  // Monta { "nome do campo": "texto de para que serve" } a partir da
  // mesma base de dados usada no resto do painel — uma fonte só de
  // verdade para o texto da dica.
  function todasAbasCampos() {
    const base = window.CentralBoletos.portadorCamposBase;
    return [base.abaDadosCedente, base.abaInstrucoesBanco, base.abaImpressao, base.abaRemessaRetorno, base.abaOutrosDados];
  }

  function getMapaDicas() {
    const mapa = {};
    todasAbasCampos().forEach((aba) => {
      (aba.campos || []).forEach((c) => {
        mapa[c.nome] = c.paraQueServe;
      });
    });
    return mapa;
  }

  // Devolve o objeto completo do campo (não só o texto de "para que
  // serve") — usado quando é preciso ler outras propriedades, como
  // opcoesObservadas/opcoesExplicadas/somenteBanco.
  function getCampoData(chave) {
    for (const aba of todasAbasCampos()) {
      const achado = (aba.campos || []).find((c) => c.nome === chave);
      if (achado) return achado;
    }
    return null;
  }

  // Envolve o texto visível de um rótulo num <span> com tooltip, desde
  // que exista explicação cadastrada para `chave` em portadorCamposBase.
  // Sem explicação cadastrada, devolve o texto puro (sem sublinhado).
  function dica(chave, textoVisivel) {
    const texto = getMapaDicas()[chave];
    const visivel = textoVisivel !== undefined ? textoVisivel : chave;
    if (!texto) return visivel;
    return `<span class="erp-mock__dica" data-tip="${window.CentralBoletos.ui.escapeHtml(texto)}">${visivel}</span>`;
  }

  // Checkbox de verdade (alterna ao clicar), mas com a mesma aparência
  // da tela real (quadrado azul com check quando marcado). `label` já
  // vem pronto (pode incluir o <span> da dica); `extra` permite embutir
  // outro campo dentro do mesmo rótulo (caso do "Protestar [dias]
  // dias corridos.").
  function checkbox(label, marcadoPorPadrao, extra) {
    return `<label class="erp-mock__chk">
      <input type="checkbox" class="erp-mock__chk-input"${marcadoPorPadrao ? " checked" : ""}>
      <span class="erp-mock__chk-icone" aria-hidden="true"></span>${label}${extra || ""}
    </label>`;
  }

  // ---------------- ABA: DADOS DO CEDENTE ----------------
  function paneDadosCedente(relevantes, nomeGrupoRadio, nomeBanco, ehBB) {
    const esc = window.CentralBoletos.ui.escapeHtml;

    // Modalidade Cobrança (BB*) — opções e dica de cada uma vêm de
    // data.js (opcoesObservadas/opcoesExplicadas), pra não duplicar
    // a lista aqui e desalinhar se um dia mudar lá.
    const campoModalidade = getCampoData("Modalidade Cobrança (BB*)") || {};
    const opcoesModalidadeHtml = (campoModalidade.opcoesObservadas || [])
      .map((op) => {
        const explicacao = (campoModalidade.opcoesExplicadas || {})[op];
        const tituloAttr = explicacao ? ` title="${esc(explicacao)}"` : "";
        return `<option${tituloAttr}>${op}</option>`;
      })
      .join("");

    return `
      <div class="erp-mock__grid">
        <div class="erp-mock__linha">
          <div class="erp-mock__campo${destaque("Conta/Caixa Vinculada", relevantes)}" style="flex:2; min-width:280px;">
            <label>${dica("Conta/Caixa Vinculada", "Conta/Caixa Vinculada:")}</label>
            <div class="erp-mock__busca-inline">
              <input type="text" style="width:80px;" value="1">
              <span class="erp-mock__busca-btn">${ICONE_LUPA}</span>
              <input type="text" style="flex:1;" value="${esc(nomeBanco)}">
            </div>
          </div>
          <div class="erp-mock__grupo" style="min-width:230px;">
            ${checkbox(dica("Imprimir Boleto?", "Imprimir Boleto ?"), true)}
            ${checkbox(dica("Cobrança Registrada", "Cobrança Registrada"), true)}
            ${checkbox(dica("Protestar (dias corridos)", "Protestar"), false, ' <input type="text" style="width:34px; margin:0 4px;"> dias corridos.')}
            ${checkbox(dica("Pré Impresso", "Pré Impresso"), false)}
          </div>
        </div>

        <div class="erp-mock__campo" style="max-width:560px;">
          <label>${dica("Nome do Cedente", "Nome do Cedente:")}</label>
          <input type="text" value="Razão Social">
        </div>

        <div class="erp-mock__linha">
          <div class="erp-mock__campo${destaque("Código do Cedente", relevantes)}"><label>${dica("Código do Cedente", "Código do Cedente")}</label><input type="text" style="width:110px;"></div>
          <div class="erp-mock__campo${destaque("DV", relevantes)}"><label>${dica("DV", "DV")}</label><input type="text" style="width:42px;"></div>
          <div class="erp-mock__campo${destaque("Carteira", relevantes)}"><label>${dica("Carteira", "Carteira")}</label><input type="text" style="width:56px;"></div>
          <div class="erp-mock__campo${destaque("Var *", relevantes)}"><label>${dica("Var *", "Var *")}</label><input type="text" style="width:42px;"></div>
          <div class="erp-mock__grupo">
            <div class="erp-mock__grupo-titulo">${dica("Tipo Inscrição", "Tipo Inscrição")}</div>
            <div class="erp-mock__radios">
              <label><input type="radio" name="${nomeGrupoRadio}" checked> CNPJ</label>
              <label><input type="radio" name="${nomeGrupoRadio}"> CPF</label>
              <label><input type="radio" name="${nomeGrupoRadio}"> Outros</label>
            </div>
          </div>
          <div class="erp-mock__campo" style="flex:1; min-width:170px;"><label>${dica("Nº Inscrição", "Nº Inscrição (somente números):")}</label><input type="text" inputmode="numeric"></div>
        </div>

        <div class="erp-mock__linha">
          <div class="erp-mock__campo${destaque("Nosso Número (Próx)", relevantes)}"><label>${dica("Nosso Número (Próx)", "Nosso Número (Próx):")}</label><input type="text" style="width:130px;"></div>
          <div class="erp-mock__campo${destaque("Tam", relevantes)}"><label>${dica("Tam", "Tam:")}</label><input type="text" style="width:42px;"></div>
          <div class="erp-mock__campo${destaque("Nº Convênio", relevantes)}"><label>${dica("Nº Convênio", "Nº Convênio")}</label><input type="text" style="width:100px;"></div>
          <div class="erp-mock__campo"><label>${dica("Aceite", "Aceite:")}</label><select style="width:78px;"><option value=""></option><option>Não</option><option>Sim</option></select></div>
          <div class="erp-mock__campo">
            <label>${dica("Filial Defalt", "Filial Defalt:")}</label>
            <div class="erp-mock__busca-inline"><input type="text" style="width:100px;"><span class="erp-mock__busca-btn">${ICONE_FILIAL}</span></div>
          </div>
        </div>

        <div class="erp-mock__linha">
          <div class="erp-mock__grupo${destaque("Modalidade Cobrança (BB*)", relevantes)}" data-erp-campo="modalidade-cobranca" style="min-width:300px;">
            <div class="erp-mock__grupo-titulo">${dica("Modalidade Cobrança (BB*)", "Modalidade Cobrança (BB*)")}</div>
            <select style="width:100%;">
              <option value=""></option>
              ${opcoesModalidadeHtml}
            </select>
            <p class="erp-mock__aviso-campo" data-erp-aviso-modalidade hidden>⚠ Campo específico do Banco do Brasil — não deveria ser preenchido neste portador.</p>
          </div>
          <div class="erp-mock__campo${destaque("Nº Operação", relevantes)}" style="flex:1; min-width:180px;"><label>${dica("Nº Operação", "Nº Operação")}</label><input type="text"></div>
        </div>

        <p class="erp-mock__nota">* exigido para alguns bancos</p>
      </div>`;
  }

  // ---------------- ABA: INSTRUÇÕES PARA O BANCO ----------------
  function paneInstrucoes(variaveis) {
    return `
      <div class="erp-mock__duas-colunas">
        <div class="erp-mock__campo" style="flex:2;">
          <label>${dica("Instruções para Boleto", "Instruções para Boleto:")}</label>
          <div class="erp-mock__textarea-linhas">
            ${Array(7).fill('<input type="text">').join("")}
          </div>
        </div>
        <div class="erp-mock__grupo" style="flex:1; min-width:220px;">
          <div class="erp-mock__grupo-titulo">Variáveis:</div>
          <div class="erp-mock__lista-variaveis">
            ${variaveis.map((v) => `<div><code>${v.tag}</code> - ${v.significado}</div>`).join("")}
          </div>
        </div>
      </div>`;
  }

  // ---------------- ABA: IMPRESSÃO ----------------
  function paneImpressao() {
    return `
      <div class="erp-mock__grid">
        <div class="erp-mock__campo" style="max-width:520px;">
          <label>${dica("Impressora Padrão", "Impressora Padrão:")}</label>
          <div class="erp-mock__busca-inline"><input type="text" style="flex:1;"><span class="erp-mock__busca-btn">${ICONE_IMPRESSORA}</span></div>
        </div>
        <div class="erp-mock__linha">
          <div class="erp-mock__campo"><label>${dica("Destino da Impressão", "Destino da Impressão:")}</label><select style="width:150px;">
            <option value=""></option>
            <option>Vídeo</option>
            <option>Impressora</option>
          </select></div>
          <div class="erp-mock__campo"><label>${dica("Formato de Impressão", "Formato de Impressão:")}</label><select style="width:150px;">
            <option value=""></option>
            <option>Folha Inteira</option>
            <option>Carnê (3 por folha)</option>
            <option>Detalhado</option>
            <option>Meia Folha</option>
            <option>Carta</option>
          </select></div>
        </div>
        <div class="erp-mock__linha">
          <div class="erp-mock__campo"><label>${dica("Espécie de documento", "Espécie de documento:")}</label><select style="width:150px;">
            <option value=""></option>
            <option>Duplicatas Mercantil</option>
            <option>Duplicatas de Serviço</option>
            <option>Outros</option>
          </select></div>
          <div class="erp-mock__campo"><label>${dica('Tipo Cooperativa "CrediSIS"', 'Tipo Cooperativa "CrediSIS":')}</label><select style="width:150px;">
            <option value=""></option>
            <option>JiCred</option>
            <option>Belém</option>
            <option>Sudoeste</option>
            <option>Cooperufpa</option>
            <option>CrediAri</option>
            <option>CrediBem</option>
            <option>Eucred</option>
          </select></div>
          <div class="erp-mock__campo"><label>${dica("Endereço Impresso", "Endereço Impresso:")}</label><select style="width:130px;">
            <option>Principal</option>
            <option selected>Cobrança</option>
            <option>Propriedade</option>
          </select></div>
        </div>
        <div class="erp-mock__campo" style="max-width:640px;">
          <label>${dica("Local de Pagamento", "Local de Pagamento:")}</label>
          <input type="text">
        </div>
        <div class="erp-mock__campo">
          <label>${dica("Mensagem Envio whatsapp", "Mensagem Envio whatsapp:")}</label>
          <div class="erp-mock__busca-inline">
            <input type="text" style="width:70px;">
            <span class="erp-mock__busca-btn erp-mock__busca-btn--alerta">${ICONE_ALERTA}</span>
            <input type="text" style="flex:1; min-width:260px;">
          </div>
        </div>
      </div>`;
  }

  // Painel "Caminho Arquivo" — usado quando Tipo Cobrança API = "Nenhum"
  // (ou qualquer valor sem entrada em chaveApiPorTipo).
  function painelCaminhoArquivo(relevantes) {
    return `
      <div class="erp-mock__subtab-header"><span class="erp-mock__subtab">Caminho Arquivo</span></div>
      <div class="erp-mock__subtab-body">
        <div class="erp-mock__campo${destaque("Pasta do Arquivo de Remessa", relevantes)}" style="max-width:640px;">
          <label>${dica("Pasta do Arquivo de Remessa", "Pasta do Arquivo de Remessa:")} <span class="erp-mock__tag-inferido" title="Rótulo inferido por simetria com o campo abaixo — não estava legível na imagem.">inferido</span></label>
          <div class="erp-mock__busca-inline"><input type="text" style="flex:1;"><span class="erp-mock__busca-btn">${ICONE_PASTA}</span></div>
        </div>
        <div class="erp-mock__campo" style="max-width:640px;">
          <label>${dica("Pasta do Arquivo de Retorno", "Pasta do Arquivo de Retorno:")}</label>
          <div class="erp-mock__busca-inline"><input type="text" style="flex:1;"><span class="erp-mock__busca-btn">${ICONE_PASTA}</span></div>
        </div>
      </div>`;
  }

  // Painel "Chave API" — usado quando Tipo Cobrança API é um banco com
  // integração via API. `extras` vem de chaveApiPorTipo (js/data.js) e
  // decide quais campos a mais aparecem, além dos comuns a todos.
  function painelChaveApi(extras) {
    const semSecret = !!extras.semClientSecret;
    const campoLateral = extras.workspaceId
      ? `<div class="erp-mock__campo" style="min-width:210px;"><label>${dica("workspace_id / DeveloperKey", "workspace_id / DeveloperKey:")}</label><div class="erp-mock__busca-inline"><input type="text" style="flex:1;"><span class="erp-mock__busca-btn">${ICONE_ENGRENAGEM}</span></div></div>`
      : extras.nDiasAgenda
      ? `<div class="erp-mock__campo" style="min-width:130px;"><label>${dica("Nº Dias Agenda", "Nº Dias Agenda:")}</label><input type="text" style="width:90px;"></div>`
      : "";

    return `
      <div class="erp-mock__subtab-header"><span class="erp-mock__subtab">Chave API</span></div>
      <div class="erp-mock__subtab-body">
        <div class="erp-mock__linha">
          <div class="erp-mock__campo"><label>${dica("Ambiente", "Ambiente:")}</label><select style="width:140px;">
            <option value=""></option>
            <option>Homologação</option>
            <option>Produção</option>
          </select></div>
          ${checkbox(dica("Usa Pix", "Usa Pix"), false)}
          ${checkbox(dica("Imprimir Sem Registrar", "Imprimir Sem Registrar"), false)}
          ${campoLateral}
        </div>
        ${extras.chavePix ? `<div class="erp-mock__campo" style="max-width:420px;"><label>${dica("Chave Pix", "Chave Pix:")}</label><input type="text"></div>` : ""}
        <div class="erp-mock__campo"><label>${dica("client_id", "client_id / UserName:")}</label><input type="text"></div>
        ${semSecret ? "" : `<div class="erp-mock__campo"><label>${dica("client_secret", "client_secret / Password:")}</label><input type="password"></div>`}
        ${
          extras.certificado
            ? `<div class="erp-mock__linha">
                <button type="button" class="erp-mock__cert-btn">${ICONE_CERTIFICADO} Carregar Certificado .KEY</button>
                <button type="button" class="erp-mock__cert-btn">${ICONE_CERTIFICADO} Carregar Certificado .CRT / .PEM</button>
              </div>`
            : ""
        }
        ${
          extras.versaoApi
            ? `<div class="erp-mock__campo" style="max-width:140px;"><label>${dica("Versão API", "Versão API:")}</label><select style="width:100%;"><option value=""></option><option>V2</option><option>V3</option></select></div>`
            : ""
        }
      </div>`;
  }

  // Decide qual dos dois painéis acima mostrar, a partir do valor
  // atual do Tipo Cobrança API. Chamada tanto na primeira renderização
  // quanto de novo (via wireTabs) sempre que o select muda de valor.
  function painelCaminhoOuChave(tipoSelecionado, relevantes) {
    const config = window.CentralBoletos.portadorCamposBase.abaRemessaRetorno.chaveApiPorTipo || {};
    const extras = config[tipoSelecionado];
    return extras ? painelChaveApi(extras) : painelCaminhoArquivo(relevantes);
  }

  // ---------------- ABA: REMESSA/RETORNO ----------------
  function paneRemessaRetorno(relevantes, opcoesTipoCobranca, tipoIntegracaoBanco) {
    const selecionado = (tipoIntegracaoBanco || []).find((t) => opcoesTipoCobranca.includes(t));
    return `
      <div class="erp-mock__grid">
        <div class="erp-mock__linha">
          <div class="erp-mock__campo"><label>${dica("Layout CNAB", "Layout CNAB:")}</label><select style="width:110px;">
            <option value=""></option>
            <option>400</option>
            <option>240</option>
            <option>240C</option>
          </select></div>
          <div class="erp-mock__campo"><label>${dica("Másc. Arq.Ret", "Másc. Arq.Ret")}</label><input type="text" style="width:100px;"></div>
          <div class="erp-mock__campo"><label>${dica("Tipo de Emissão", "Tipo de Emissão")}</label><select style="width:120px;">
            <option value=""></option>
            <option>Cliente Emite</option>
            <option>Banco Emite</option>
          </select></div>
          <div class="erp-mock__campo"><label>${dica("Seq. Remessa", "Seq. Remessa")}</label><input type="text" style="width:70px;"></div>
          <div class="erp-mock__campo"><label>${dica("Gerar Extrato na Baixa", "Gerar Extrato na Baixa:")}</label><select style="width:120px;">
            <option value=""></option>
            <option>A = Agrupado</option>
            <option>D = Detalhado</option>
          </select></div>
          ${checkbox(dica("Marcar Protesto", "Marcar Protesto"), false)}
        </div>

        <div class="erp-mock__linha">
          <div class="erp-mock__campo${destaque("Tipo Cobrança API", relevantes)} erp-mock__campo--chave" style="flex:1; min-width:260px;">
            <label>${dica("Tipo Cobrança API", "Tipo Cobrança API:")} <span class="erp-mock__tag-chave">campo-chave</span></label>
            <select style="width:100%;">
              ${opcoesTipoCobranca
                .map((o) => `<option${o === selecionado || (o === "Nenhum" && !selecionado) ? " selected" : ""}>${o}</option>`)
                .join("")}
            </select>
          </div>
          <div class="erp-mock__campo"><label>${dica("Código negativação", "Código negativação:")}</label><select style="width:130px;">
            <option value=""></option>
            <option>2 = Negativar Dias Úteis</option>
            <option>3 = Não Negativar</option>
          </select></div>
          <div class="erp-mock__campo"><label>${dica("Dias Negativação", "Dias Negativação:")}</label><input type="text" style="width:80px;"></div>
        </div>

        <div data-erp-caminho-chave>${painelCaminhoOuChave(selecionado || "Nenhum", relevantes)}</div>
      </div>`;
  }

  // ---------------- ABA: OUTROS DADOS ----------------
  // A maioria dos campos aqui é específica de UM banco só (CEF,
  // Sicredi ou Sicoob) — por isso ficam sinalizados como grupos.
  function paneOutrosDados(relevantes) {
    return `
      <div class="erp-mock__grid">
        <div class="erp-mock__linha" style="align-items:flex-start;">
          <div style="display:flex; flex-direction:column; gap:10px;">
            <div class="erp-mock__campo"><label>${dica("Acrescer % Multa", "Acrescer % Multa")}</label><input type="text" style="width:100px;"></div>
            <div class="erp-mock__campo"><label>${dica("R$ Taxa Boleto", "R$ Taxa Boleto")}</label><input type="text" style="width:100px;"></div>
            <div class="erp-mock__campo"><label>${dica("Vl. Mínimo Boleto", "Vl. Mínimo Boleto")}</label><input type="text" style="width:100px;"></div>
            <div class="erp-mock__campo"><label>${dica("Campo Extra", "Campo Extra")}</label><input type="text" style="width:100px;"></div>
          </div>

          <div class="erp-mock__grupo">
            <div class="erp-mock__grupo-titulo">Somente para Banco CEF</div>
            <div class="erp-mock__linha">
              <div class="erp-mock__campo${destaque("Layout (CEF)", relevantes)}"><label>${dica("Layout (CEF)", "Layout :")}</label><select style="width:110px;">
                <option value=""></option>
                <option>1 - SICOOB</option>
                <option>2 - SIGCB</option>
              </select></div>
              <div class="erp-mock__campo${destaque("Dia(s) p/ Dev. (CEF)", relevantes)}"><label>${dica("Dia(s) p/ Dev. (CEF)", "Dia(s) p/ Dev.:")}</label><input type="text" style="width:70px;"></div>
            </div>
          </div>

          <div class="erp-mock__grupo">
            <div class="erp-mock__grupo-titulo">Somente para Banco Sicredi</div>
            <div class="erp-mock__linha">
              <div class="erp-mock__campo${destaque("Posto (Sicredi)", relevantes)}"><label>${dica("Posto (Sicredi)", "Posto:")}</label><input type="text" style="width:50px;"></div>
              <div class="erp-mock__campo${destaque("Ano(AA) (Sicredi)", relevantes)}"><label>${dica("Ano(AA) (Sicredi)", "Ano(AA):")}</label><input type="text" style="width:50px;"></div>
              <div class="erp-mock__campo${destaque("Byte (Sicredi)", relevantes)}"><label>${dica("Byte (Sicredi)", "Byte:")}</label><input type="text" style="width:44px;"></div>
            </div>
            ${checkbox(dica("Nosso Número Composto", "Nosso Número Composto"), false)}
          </div>
        </div>

        <div class="erp-mock__linha" style="align-items:flex-start;">
          <div class="erp-mock__campo"><label>${dica("Ultimo Boleto", "Ultimo Boleto")}</label><input type="text" style="width:100px;"></div>

          <div style="display:flex; flex-direction:column; gap:10px; flex:1; min-width:280px;">
            <div class="erp-mock__linha">
              <div class="erp-mock__campo" style="flex:1; min-width:160px;"><label>${dica("Razão Social", "Razão Social")}</label><input type="text"></div>
              <div class="erp-mock__campo" style="flex:1; min-width:140px;"><label>${dica("CNPJ", "CNPJ")}</label><input type="text"></div>
            </div>
            <div class="erp-mock__campo"><label>${dica("Endereço", "Endereço:")}</label><input type="text"></div>
          </div>

          <div class="erp-mock__grupo${destaque("Nº Layout Lote", relevantes)}${relevantes.has("Desconsidera Data Juros e Multa") ? " erp-mock__destaque" : ""}">
            <div class="erp-mock__grupo-titulo">Banco Sicoob</div>
            <div class="erp-mock__campo"><label>${dica("Nº Layout Lote", "Nº Layout Lote:")}</label><select style="width:100%;">
              <option value=""></option>
              <option>040</option>
              <option>045</option>
            </select></div>
            ${checkbox(dica("Desconsidera Data Juros e Multa", "Desconsidera Data Juros e Multa"), false)}
          </div>
        </div>

        <div class="erp-mock__linha">
          <div class="erp-mock__campo"><label>${dica("Fone Beneficiário", "Fone Beneficiário:")}</label><input type="text" style="width:140px;"></div>
          <div class="erp-mock__campo"><label>${dica("Cod. Avalista", "Cod. Avalista")}</label>
            <div class="erp-mock__busca-inline"><input type="text" style="width:100px;"><span class="erp-mock__busca-btn">${ICONE_FILIAL}</span></div>
          </div>
          <div class="erp-mock__campo"><label>${dica("C.Custo Taxa Adm", "C.Custo Taxa Adm:")}</label>
            <div class="erp-mock__busca-inline"><input type="text" style="width:100px;"><span class="erp-mock__busca-btn">${ICONE_MOEDA}</span></div>
          </div>
        </div>

        <div class="erp-mock__linha" style="align-items:flex-start;">
          <div class="erp-mock__grupo" style="min-width:230px;">
            <div class="erp-mock__grupo-titulo">SPED Fiscal - Registro 1601</div>
            <div class="erp-mock__campo"><label>${dica("Cod. Participante", "Cod. Participante")}</label>
              <div class="erp-mock__busca-inline"><input type="text" style="flex:1;"><span class="erp-mock__busca-btn">${ICONE_FILIAL}</span></div>
            </div>
            <div class="erp-mock__campo" style="margin-top:8px;"><label>${dica("Cod. Intermediário", "Cod. Intermediário")}</label>
              <div class="erp-mock__busca-inline"><input type="text" style="flex:1;"><span class="erp-mock__busca-btn">${ICONE_FILIAL}</span></div>
            </div>
          </div>

          <div class="erp-mock__campo" style="min-width:150px;"><label>${dica("Dias Min. Ven. Boleto", "Dias Min. Ven. Boleto")}</label><input type="text"></div>

          <div style="display:flex; flex-direction:column; gap:4px;">
            ${checkbox(dica("Usa Unicred", "Usa Unicred"), false)}
            ${checkbox(dica("Barra Desconto Duplicata", "Barra Desconto Duplicata"), false)}
            ${checkbox(dica("Usa Banco Fator FIDC", "Usa Banco Fator FIDC"), false)}
            ${checkbox(dica("Usa Banco IB Sigma", "Usa Banco IB Sigma"), false)}
            ${checkbox(dica("Usa Cobrança Escritural", "Usa Cobrança Escritural"), false)}
            ${checkbox(dica("Usa Financeira NASA", "Usa Financeira NASA"), false)}
            ${checkbox(dica("Usar Endereço Portador", "Usar Endereço Portador"), false)}
          </div>
        </div>
      </div>`;
  }

  const ABA_DEFS = [
    { chave: "dados-cedente", titulo: "Dados do Cedente" },
    { chave: "instrucoes", titulo: "Instruções para o Banco" },
    { chave: "impressao", titulo: "Impressão" },
    { chave: "remessa-retorno", titulo: "Remessa/Retorno" },
    { chave: "outros-dados", titulo: "Outros Dados" },
  ];

  function render(banco) {
    const base = window.CentralBoletos.portadorCamposBase;
    const relevantes = new Set(banco.camposPortadorRelevantes || []);
    const opcoesTipoCobranca = base.abaRemessaRetorno.campos.find((c) => c.nome === "Tipo Cobrança API").opcoesObservadas || [];
    const uid = "erp-mock-" + banco.id;
    const nomeBanco = banco.nome || "Nome do banco";
    const esc = window.CentralBoletos.ui.escapeHtml;

    const paneHtml = {
      "dados-cedente": paneDadosCedente(relevantes, uid + "-tipo-insc", nomeBanco, banco.id === "bb"),
      instrucoes: paneInstrucoes(base.abaInstrucoesBanco.variaveisDisponiveis || []),
      impressao: paneImpressao(),
      "remessa-retorno": paneRemessaRetorno(relevantes, opcoesTipoCobranca, banco.tipoIntegracao),
      "outros-dados": paneOutrosDados(relevantes),
    };

    return `
      <div class="erp-mock" id="${uid}" data-relevantes="${esc(JSON.stringify(banco.camposPortadorRelevantes || []))}" data-eh-bb="${banco.id === "bb"}">
        <div class="erp-mock__titlebar"><span class="erp-mock__titlebar-icone">$</span> Portador</div>
        <div class="erp-mock__toolbar">
          <button class="erp-mock__tbtn" disabled title="Maquete de referência — sem ação real">Novo</button>
          <button class="erp-mock__tbtn erp-mock__tbtn--ativo" disabled title="Maquete de referência — sem ação real">Salvar</button>
          <button class="erp-mock__tbtn" disabled title="Maquete de referência — sem ação real">Cancelar</button>
          <button class="erp-mock__tbtn" disabled title="Maquete de referência — sem ação real">Excluir</button>
          <button class="erp-mock__tbtn" disabled title="Maquete de referência — sem ação real">Buscar</button>
          <button class="erp-mock__tbtn" disabled title="Maquete de referência — sem ação real">Imprimir</button>
          <button class="erp-mock__tbtn" disabled title="Maquete de referência — sem ação real">Fechar</button>
        </div>
        <div class="erp-mock__body">
          <div class="erp-mock__ident">Identificação</div>
          <div class="erp-mock__linha-topo">
            <div class="erp-mock__campo"><label>${dica("Código", "Código")}</label><input type="text" style="width:110px;" value="1"></div>
            <div class="erp-mock__campo" style="flex:1; min-width:220px;"><label>${dica("Descrição", "Descrição")}</label><input type="text" value="API BOLETO ${esc(nomeBanco)}"></div>
            ${checkbox(dica("Inativo", "Inativo"), false)}
          </div>
          <div class="erp-mock__tabs" role="tablist">
            ${ABA_DEFS.map(
              (a, i) => `<span class="erp-mock__tab${i === 0 ? " erp-mock__tab--ativa" : ""}" data-erp-tab="${a.chave}" role="tab">${a.titulo}</span>`
            ).join("")}
          </div>
          ${ABA_DEFS.map(
            (a, i) => `<div class="erp-mock__pane" data-erp-pane="${a.chave}" ${i === 0 ? "" : "hidden"}>${paneHtml[a.chave]}</div>`
          ).join("")}
        </div>
      </div>`;
  }

  // Liga a troca de abas por clique e a troca automática de
  // Caminho Arquivo ↔ Chave API. Chamar depois de inserir o HTML no
  // DOM (o mockup não sabe se navegar sozinho — quem chama decide
  // quando o container já existe).
  function wireTabs(container) {
    (container || document).querySelectorAll(".erp-mock").forEach((mock) => {
      mock.querySelectorAll(".erp-mock__tab").forEach((tab) => {
        tab.addEventListener("click", () => {
          const alvo = tab.getAttribute("data-erp-tab");
          mock.querySelectorAll(".erp-mock__tab").forEach((t) => t.classList.toggle("erp-mock__tab--ativa", t === tab));
          mock.querySelectorAll(".erp-mock__pane").forEach((p) => {
            p.hidden = p.getAttribute("data-erp-pane") !== alvo;
          });
        });
      });

      const selectTipoCobranca = mock.querySelector(".erp-mock__campo--chave select");
      const alvoCaminhoChave = mock.querySelector("[data-erp-caminho-chave]");
      if (selectTipoCobranca && alvoCaminhoChave) {
        selectTipoCobranca.addEventListener("change", () => {
          let relevantes;
          try {
            relevantes = new Set(JSON.parse(mock.getAttribute("data-relevantes") || "[]"));
          } catch (e) {
            relevantes = new Set();
          }
          alvoCaminhoChave.innerHTML = painelCaminhoOuChave(selectTipoCobranca.value, relevantes);
        });
      }

      // "Modalidade Cobrança (BB*)" é exclusiva do Banco do Brasil —
      // se for preenchida em qualquer outro portador, mostra aviso.
      const selectModalidade = mock.querySelector('[data-erp-campo="modalidade-cobranca"] select');
      const avisoModalidade = mock.querySelector("[data-erp-aviso-modalidade]");
      if (selectModalidade && avisoModalidade) {
        const ehBB = mock.getAttribute("data-eh-bb") === "true";
        const atualizarAvisoModalidade = () => {
          avisoModalidade.hidden = ehBB || !selectModalidade.value;
        };
        selectModalidade.addEventListener("change", atualizarAvisoModalidade);
      }
    });
  }

  window.CentralBoletos.portadorMockup = { render, wireTabs };
})();
