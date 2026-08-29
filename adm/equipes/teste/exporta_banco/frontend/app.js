// Inspetor de Banco Firebird — front-end (vanilla JS)

const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

const telaConexao = $("#tela-conexao");
const appEl = $("#app");
const painel = $("#painel");
const listaLateral = $("#lista-lateral");
const resultadoBusca = $("#resultado-busca");

let abaAtual = "tabelas";
let cacheTabelas = [];
let cacheProcedures = [];
let cacheTriggers = [];
let cacheGenerators = null;
let cacheColunasPorTabela = {};

// --------------------------------------------------------------------
// Lembrar a última conexão usada (exceto senha, nunca guardada)
// --------------------------------------------------------------------

const CHAVE_ULTIMA_CONEXAO = "inspetor_firebird_ultima_conexao";

function salvarUltimaConexao(dados) {
  try {
    const { password, ...semSenha } = dados;
    localStorage.setItem(CHAVE_ULTIMA_CONEXAO, JSON.stringify(semSenha));
  } catch (_) {
    // localStorage indisponível (aba anônima, etc.) — não é crítico, ignora.
  }
}

function carregarUltimaConexao() {
  try {
    const dados = localStorage.getItem(CHAVE_ULTIMA_CONEXAO);
    return dados ? JSON.parse(dados) : null;
  } catch (_) {
    return null;
  }
}

function preencherFormularioComUltimaConexao() {
  const dados = carregarUltimaConexao();
  if (!dados) return;
  const form = $("#form-conexao");
  if (dados.database) form.database.value = dados.database;
  if (dados.host) form.host.value = dados.host;
  if (dados.port) form.port.value = dados.port;
  if (dados.user) form.user.value = dados.user;
  if (dados.charset) form.charset.value = dados.charset;
  if (dados.role) form.role.value = dados.role;
}

// --------------------------------------------------------------------
// Conexão
// --------------------------------------------------------------------

async function apiFetch(url, opts) {
  const resp = await fetch(url, opts);
  if (!resp.ok) {
    let msg = `Erro ${resp.status}`;
    try {
      const data = await resp.json();
      msg = data.detail || msg;
    } catch (_) {}
    throw new Error(msg);
  }
  return resp.json();
}

$("#form-conexao").addEventListener("submit", async (ev) => {
  ev.preventDefault();
  const form = ev.target;
  const dados = dadosDoFormulario();

  const erroEl = $("#erro-conexao");
  erroEl.hidden = true;
  const botao = form.querySelector("button[type=submit]");
  botao.disabled = true;
  botao.textContent = "Conectando...";

  try {
    const resp = await apiFetch("/api/connect", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(dados),
    });
    salvarUltimaConexao(dados);
    entrarNoApp(resp);
  } catch (err) {
    console.error("Falha ao conectar:", err);
    erroEl.textContent = err.message || "Falha inesperada ao conectar (veja o console do navegador).";
    erroEl.hidden = false;
  } finally {
    botao.disabled = false;
    botao.textContent = "Conectar";
  }
});

// --------------------------------------------------------------------
// Testar conexão (sem sair da tela de login)
// --------------------------------------------------------------------

function dadosDoFormulario() {
  const form = $("#form-conexao");
  return {
    database: form.database.value.trim(),
    host: form.host.value.trim(),
    port: parseInt(form.port.value, 10),
    user: form.user.value.trim(),
    password: form.password.value,
    charset: form.charset.value,
    role: form.role.value.trim() || null,
  };
}

$("#btn-testar").addEventListener("click", async () => {
  const resultadoEl = $("#resultado-teste");
  const botao = $("#btn-testar");
  resultadoEl.hidden = true;
  botao.disabled = true;
  const textoOriginal = botao.textContent;
  botao.textContent = "Testando...";

  try {
    const resp = await apiFetch("/api/test-connection", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(dadosDoFormulario()),
    });
    resultadoEl.className = "alerta-sucesso";
    resultadoEl.textContent = `✅ Conexão bem-sucedida! Firebird versão ${resp.version}.`;
    resultadoEl.hidden = false;
  } catch (err) {
    console.error("Falha ao testar conexão:", err);
    resultadoEl.className = "alerta-erro";
    resultadoEl.textContent = `❌ ${err.message}`;
    resultadoEl.hidden = false;
  } finally {
    botao.disabled = false;
    botao.textContent = textoOriginal;
  }
});

// --------------------------------------------------------------------
// Selecionar arquivo .FDB (explorador de arquivos do servidor)
// --------------------------------------------------------------------

const modalArquivos = $("#modal-arquivos");
const modalCaminhoAtual = $("#modal-caminho-atual");
const modalListaArquivos = $("#modal-lista-arquivos");

$("#btn-abrir-arquivo").addEventListener("click", () => {
  modalArquivos.hidden = false;
  navegarPara(null);
});

$("#btn-fechar-modal").addEventListener("click", () => {
  modalArquivos.hidden = true;
});

modalArquivos.addEventListener("click", (ev) => {
  if (ev.target === modalArquivos) modalArquivos.hidden = true;
});

async function navegarPara(caminho) {
  modalCaminhoAtual.textContent = caminho || "Unidades de disco";
  modalListaArquivos.innerHTML = `<p class="dica" style="padding:10px">Carregando...</p>`;

  try {
    const url = caminho ? `/api/browse?path=${encodeURIComponent(caminho)}` : "/api/browse";
    const res = await apiFetch(url);
    const itens = [];

    if (res.parent !== null && res.parent !== undefined) {
      itens.push(
        `<div class="modal-item" data-tipo="voltar" data-caminho="${escHtml(res.parent)}">
          <span class="icone">⬆️</span><span>.. (voltar)</span>
        </div>`
      );
    } else if (res.current_path) {
      itens.push(
        `<div class="modal-item" data-tipo="voltar" data-caminho="">
          <span class="icone">⬆️</span><span>.. (unidades de disco)</span>
        </div>`
      );
    }

    res.directories.forEach((d) => {
      itens.push(
        `<div class="modal-item" data-tipo="pasta" data-caminho="${escHtml(d.path)}">
          <span class="icone">📁</span><span>${escHtml(d.name)}</span>
        </div>`
      );
    });

    res.files.forEach((f) => {
      itens.push(
        `<div class="modal-item arquivo-banco" data-tipo="arquivo" data-caminho="${escHtml(f.path)}">
          <span class="icone">🗄️</span><span>${escHtml(f.name)}</span>
        </div>`
      );
    });

    modalListaArquivos.innerHTML = itens.length
      ? itens.join("")
      : `<p class="vazio" style="padding:10px">Nenhuma pasta ou arquivo .FDB/.GDB aqui.</p>`;

    $$(".modal-item", modalListaArquivos).forEach((el) => {
      el.addEventListener("click", () => {
        const tipo = el.dataset.tipo;
        const caminho = el.dataset.caminho;
        if (tipo === "arquivo") {
          $("#input-database").value = caminho;
          modalArquivos.hidden = true;
        } else {
          navegarPara(caminho || null);
        }
      });
    });
  } catch (err) {
    modalListaArquivos.innerHTML = `<div class="alerta-erro">${escHtml(err.message)}</div>`;
  }
}

$("#btn-desconectar").addEventListener("click", async () => {
  await apiFetch("/api/disconnect", { method: "POST" });
  location.reload();
});

function entrarNoApp(resp) {
  telaConexao.hidden = true;
  appEl.hidden = false;
  const info = resp.connection;
  $("#status-conexao").textContent =
    `🟢 ${info.database} @ ${info.host}:${info.port} (${info.user}) — Firebird ${resp.version}`;
  selecionarAba("visaogeral");
}

async function verificarStatusInicial() {
  try {
    const status = await apiFetch("/api/status");
    if (status.connected) {
      const overview = await apiFetch("/api/overview");
      telaConexao.hidden = true;
      appEl.hidden = false;
      const info = status.connection;
      $("#status-conexao").textContent =
        `🟢 ${info.database} @ ${info.host}:${info.port} (${info.user}) — Firebird ${overview.version}`;
      selecionarAba("visaogeral");
    }
  } catch (_) {
    // sem conexão ainda — mantém tela de login
  }
}

// --------------------------------------------------------------------
// Overview
// --------------------------------------------------------------------

/** Hora atual formatada (HH:MM:SS) — usada nos indicadores "Atualizado às". */
function horarioAgora() {
  return new Date().toLocaleTimeString("pt-BR");
}

/** Escreve "Atualizado às HH:MM:SS" no elemento `id`, se ele existir na tela atual. */
function marcarAtualizado(id) {
  const el = $(id);
  if (el) el.textContent = `Atualizado às ${horarioAgora()}`;
}

async function carregarOverview() {
  try {
    const overview = await apiFetch("/api/overview");
    renderOverview(overview);
  } catch (err) {
    console.error(err);
  }
  await carregarResumoNegocio();
  marcarAtualizado("#visaogeral-atualizado-em");
}

function renderOverview(overview) {
  const cartoes = $("#cartoes-overview");
  if (!cartoes) return; // usuário já navegou para outra tela antes da resposta chegar
  const c = overview.counts || {};
  cartoes.innerHTML = `
    ${cartaoHtml(c.tabelas, "Tabelas", { aba: "tabelas", cor: "info" })}
    ${cartaoHtml(c.views, "Views", { aba: "tabelas", cor: "roxo" })}
    ${cartaoHtml(c.procedures, "Procedures", { aba: "procedures", cor: "ciano" })}
    ${cartaoHtml(c.triggers, "Triggers", { aba: "triggers", cor: "amarelo" })}
    ${cartaoHtml(c.generators, "Generators", { aba: "generators", cor: "sucesso" })}
  `;
  ativarCliqueCards(cartoes);
}

/**
 * Monta um cartão de resumo. Passe `opts.aba` para levar a uma aba da barra
 * lateral, ou `opts.tabela` (+ opcional `opts.where`) para abrir a tabela já
 * com os dados filtrados carregados (usado nos cards de negócio).
 */
function cartaoHtml(numero, rotulo, opts = {}) {
  const clicavel = opts.aba || opts.tabela;
  const attrs = [];
  if (opts.aba) attrs.push(`data-aba="${escHtml(opts.aba)}"`);
  if (opts.tabela) attrs.push(`data-tabela="${escHtml(opts.tabela)}"`);
  if (opts.where) attrs.push(`data-where="${escHtml(opts.where)}"`);
  if (clicavel) attrs.push(`data-rotulo="${escHtml(rotulo)}"`);
  if (clicavel && opts.cor) attrs.push(`data-cor="${escHtml(opts.cor)}"`);

  const classes = ["cartao"];
  if (clicavel) classes.push("cartao-clicavel");
  if (opts.cor) classes.push(`cartao--${opts.cor}`);

  const podeFavoritar = clicavel && opts.favoritavel !== false;
  const favoritoBtn = podeFavoritar
    ? `<button type="button" class="btn-favorito" title="Favoritar / desfavoritar (fixa no topo da Visão Geral)">☆</button>`
    : "";

  return `<div class="${classes.join(" ")}" ${attrs.join(" ")}>
    ${favoritoBtn}
    <div class="numero">${numero ?? "-"}</div><div class="rotulo">${rotulo}</div>
  </div>`;
}

function ativarCliqueCards(container) {
  $$(".cartao-clicavel, .link-tabela-inline", container).forEach((el) => {
    el.addEventListener("click", () => {
      const { aba, tabela, where, rotulo } = el.dataset;
      if (tabela) {
        irParaTabelaComFiltro(tabela, where || null, rotulo || el.textContent.trim());
      } else if (aba) {
        selecionarAba(aba);
      }
    });
  });
  ativarFavoritos(container);
}

// --------------------------------------------------------------------
// Favoritos — cards fixados manualmente no topo da Visão Geral
// --------------------------------------------------------------------

const CHAVE_FAVORITOS = "inspetor_favoritos";

function chaveCartao(ds) {
  return `${ds.aba || ""}|${ds.tabela || ""}|${ds.where || ""}`;
}

function obterFavoritos() {
  try {
    return JSON.parse(localStorage.getItem(CHAVE_FAVORITOS) || "[]");
  } catch {
    return [];
  }
}

function alternarFavorito(chave, ds) {
  let favoritos = obterFavoritos();
  const idx = favoritos.findIndex((f) => f.chave === chave);
  let agoraAtivo;
  if (idx >= 0) {
    favoritos.splice(idx, 1);
    agoraAtivo = false;
  } else {
    favoritos.push({
      chave,
      aba: ds.aba || "",
      tabela: ds.tabela || "",
      where: ds.where || "",
      rotulo: ds.rotulo || "",
      cor: ds.cor || "",
    });
    if (favoritos.length > 16) favoritos = favoritos.slice(-16);
    agoraAtivo = true;
  }
  try {
    localStorage.setItem(CHAVE_FAVORITOS, JSON.stringify(favoritos));
  } catch {
    /* localStorage indisponível — favorito não persiste, mas não quebra a tela */
  }
  return agoraAtivo;
}

/** Inicializa a estrelinha ☆/★ de cada card clicável dentro de `container`. */
function ativarFavoritos(container) {
  const favoritos = obterFavoritos();
  $$(".cartao-clicavel > .btn-favorito", container).forEach((btn) => {
    const cartao = btn.closest(".cartao-clicavel");
    const chave = chaveCartao(cartao.dataset);
    const ativo = favoritos.some((f) => f.chave === chave);
    btn.textContent = ativo ? "★" : "☆";
    btn.classList.toggle("ativo", ativo);
    btn.addEventListener("click", (e) => {
      e.stopPropagation();
      const agoraAtivo = alternarFavorito(chave, cartao.dataset);
      btn.textContent = agoraAtivo ? "★" : "☆";
      btn.classList.toggle("ativo", agoraAtivo);
      renderizarFavoritos();
    });
  });
}

/** Seção "⭐ Favoritos" no topo da Visão Geral — só aparece se houver algum. */
function renderizarFavoritos() {
  const el = $("#secao-favoritos");
  if (!el) return;
  const favoritos = obterFavoritos();
  if (!favoritos.length) {
    el.innerHTML = "";
    el.hidden = true;
    return;
  }
  el.hidden = false;
  el.innerHTML = `
    <div class="titulo-secao-cards">⭐ Favoritos</div>
    <div class="cartoes-overview cartoes-overview--compacto">
      ${favoritos
        .map(
          (f) => `<div class="cartao cartao-clicavel cartao-favorito${f.cor ? ` cartao--${f.cor}` : ""}"
             data-aba="${escHtml(f.aba)}" data-tabela="${escHtml(f.tabela)}" data-where="${escHtml(f.where)}"
             data-rotulo="${escHtml(f.rotulo)}" data-cor="${escHtml(f.cor)}">
          <button type="button" class="btn-favorito ativo" title="Remover dos favoritos">★</button>
          <div class="rotulo">${escHtml(f.rotulo)}</div>
        </div>`
        )
        .join("")}
    </div>`;
  ativarCliqueCards(el);
}

// --------------------------------------------------------------------
// Exportar CSV (rankings/tabelas)
// --------------------------------------------------------------------

function exportarCsv(nomeArquivo, colunas, linhas) {
  const escCsv = (v) => {
    const s = String(v ?? "");
    return /[",;\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
  };
  const conteudo = "﻿" + [colunas, ...linhas].map((l) => l.map(escCsv).join(";")).join("\r\n");
  const blob = new Blob([conteudo], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = nomeArquivo;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

// --------------------------------------------------------------------
// Modo "somente números" — esconde tabelas/rankings, mantém só os cards
// --------------------------------------------------------------------

const CHAVE_MODO_SOMENTE_NUMEROS = "inspetor_modo_somente_numeros";

function aplicarModoSomenteNumeros(ativo) {
  document.body.classList.toggle("modo-somente-numeros", ativo);
  const btn = $("#btn-somente-numeros");
  if (btn) btn.classList.toggle("ativo", ativo);
}

function inicializarModoSomenteNumeros() {
  const ativo = localStorage.getItem(CHAVE_MODO_SOMENTE_NUMEROS) === "1";
  aplicarModoSomenteNumeros(ativo);
  $("#btn-somente-numeros")?.addEventListener("click", () => {
    const novo = !document.body.classList.contains("modo-somente-numeros");
    try {
      localStorage.setItem(CHAVE_MODO_SOMENTE_NUMEROS, novo ? "1" : "0");
    } catch {
      /* ignora — só afeta persistência entre sessões */
    }
    aplicarModoSomenteNumeros(novo);
  });
}

async function carregarResumoNegocio() {
  const el = $("#secao-negocio");
  if (!el) return;
  try {
    const resumo = await apiFetch("/api/business-summary");
    el.innerHTML = renderCardsNegocio(resumo);
    ativarCliqueCards(el);
    montarAtalhosVisaoGeral(resumo);
    if (resumo.financeiro) inicializarFiltroPeriodoFinanceiro();
    $("#csv-movimentos-tipo")?.addEventListener("click", () =>
      exportarCsv(
        "movimentos_por_tipo.csv",
        ["Tipo de Documento", "Código", "Quantidade"],
        resumo.movimentos_por_tipo.map((tp) => [tp.nome, tp.codigo, tp.qtd])
      )
    );
  } catch (err) {
    el.innerHTML = "";
    montarAtalhosVisaoGeral(null);
  }
}

/** Barra de atalhos no topo da Visão Geral — rola até cada seção existente. */
function montarAtalhosVisaoGeral(resumo) {
  const barra = $("#atalhos-visao-geral");
  if (!barra) return;

  const atalhos = [{ id: "secao-estrutura", rotulo: "📋 Estrutura" }];
  if (resumo?.produtos) atalhos.push({ id: "secao-produtos-servicos", rotulo: "📦 Produtos & Serviços" });
  if (resumo?.parceiros) atalhos.push({ id: "secao-clientes-fornecedores", rotulo: "🤝 Clientes & Fornecedores" });
  if (resumo?.movimentos || resumo?.ordens_servico) atalhos.push({ id: "secao-sistema-estoque", rotulo: "🏭 Sistema Estoque" });
  if (resumo?.financeiro) atalhos.push({ id: "secao-sistema-financeiro", rotulo: "💰 Sistema Financeiro" });

  if (atalhos.length <= 1) {
    barra.hidden = true;
    return;
  }

  barra.hidden = false;
  barra.innerHTML = atalhos
    .map((a) => `<button type="button" class="atalho-secao" data-alvo="${a.id}">${a.rotulo}</button>`)
    .join("");

  $$(".atalho-secao", barra).forEach((btn) => {
    btn.addEventListener("click", () => {
      document.getElementById(btn.dataset.alvo)?.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  });
}

// --------------------------------------------------------------------
// Financeiro: valores a pagar/a receber e movimentado num período
// --------------------------------------------------------------------

function formatarMoeda(valor) {
  return (valor ?? 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
}

/** Calcula [inicio, fim] (ISO yyyy-mm-dd) para um atalho de período. */
function calcularPresetPeriodo(preset) {
  const hoje = new Date();
  const fmt = (d) => d.toISOString().slice(0, 10);
  switch (preset) {
    case "hoje":
      return [fmt(hoje), fmt(hoje)];
    case "ontem": {
      const o = new Date(hoje);
      o.setDate(o.getDate() - 1);
      return [fmt(o), fmt(o)];
    }
    case "7dias": {
      const i = new Date(hoje);
      i.setDate(i.getDate() - 6);
      return [fmt(i), fmt(hoje)];
    }
    case "mes":
      return [fmt(new Date(hoje.getFullYear(), hoje.getMonth(), 1)), fmt(hoje)];
    case "ano":
      return [fmt(new Date(hoje.getFullYear(), 0, 1)), fmt(hoje)];
    default:
      return [fmt(hoje), fmt(hoje)];
  }
}

/** Liga os botões `.botao-periodo` dentro de `container` a `aoEscolher(inicio, fim)`. */
function ativarBotoesPeriodo(container, aoEscolher) {
  if (!container) return;
  $$(".botao-periodo", container).forEach((btn) => {
    btn.addEventListener("click", () => aoEscolher(...calcularPresetPeriodo(btn.dataset.preset)));
  });
}

function inicializarFiltroPeriodoFinanceiro() {
  const inicioEl = $("#fin-periodo-inicio");
  const fimEl = $("#fin-periodo-fim");
  const btn = $("#btn-atualizar-periodo");
  if (!inicioEl || !fimEl || !btn) return;

  const hoje = new Date();
  const primeiroDia = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
  inicioEl.value = primeiroDia.toISOString().slice(0, 10);
  fimEl.value = hoje.toISOString().slice(0, 10);

  const aplicarPeriodo = (inicio, fim) => {
    inicioEl.value = inicio;
    fimEl.value = fim;
    carregarFinanceiroValores(inicio, fim);
  };

  btn.addEventListener("click", () => carregarFinanceiroValores(inicioEl.value, fimEl.value));
  ativarBotoesPeriodo($("#secao-financeiro-valores"), aplicarPeriodo);

  const periodoGlobal = $("#periodo-global");
  if (periodoGlobal) {
    periodoGlobal.hidden = false;
    ativarBotoesPeriodo(periodoGlobal, aplicarPeriodo);
  }

  carregarFinanceiroValores(inicioEl.value, fimEl.value);
}

async function carregarFinanceiroValores(inicio, fim) {
  const el = $("#cards-financeiro-valores");
  if (!el) return;
  el.innerHTML = `<p class="dica">Carregando...</p>`;

  try {
    const params = new URLSearchParams({ inicio, fim });
    const d = await apiFetch(`/api/financeiro-valores?${params.toString()}`);
    if (!d || !("a_pagar" in d)) {
      el.innerHTML = `<p class="vazio">Sem dados financeiros disponíveis.</p>`;
      return;
    }
    const t = "FLAN";
    const periodo = `DATABAIXA BETWEEN '${d.inicio}' AND '${d.fim}'`;
    el.innerHTML = `
      ${cartaoHtml(formatarMoeda(d.a_pagar), "A Pagar (em aberto)", { tabela: t, where: "PAGREC = 'P' AND STATUSLAN = 'A'", cor: "amarelo" })}
      ${cartaoHtml(formatarMoeda(d.a_receber), "A Receber (em aberto)", { tabela: t, where: "PAGREC = 'R' AND STATUSLAN = 'A'", cor: "info" })}
      ${cartaoHtml(formatarMoeda(d.recebido_periodo), "Recebido no período", { tabela: t, where: `PAGREC = 'R' AND STATUSLAN = 'B' AND ${periodo}`, cor: "sucesso" })}
      ${cartaoHtml(formatarMoeda(d.pago_periodo), "Pago no período", { tabela: t, where: `PAGREC = 'P' AND STATUSLAN = 'B' AND ${periodo}`, cor: "ciano" })}
    `;
    ativarCliqueCards(el);
  } catch (err) {
    el.innerHTML = `<div class="alerta-erro">${escHtml(err.message)}</div>`;
  }
}

function renderCardsNegocio(resumo) {
  if (!resumo) return "";
  const temAlgumaChave = Object.keys(resumo).length > 0;
  if (!temAlgumaChave) return "";
  let html = "";

  if (resumo.produtos) {
    const t = "TPRODUTO";
    const lado = (dados, rotulo, whereTipo) => `
      <div class="subgrupo-cards">
        <div class="subtitulo-cards">${rotulo}</div>
        <div class="cartoes-overview cartoes-overview--compacto">
          ${cartaoHtml(dados.total, "Total", { tabela: t, where: whereTipo })}
          ${cartaoHtml(dados.ativos, "Ativos", { tabela: t, where: `${whereTipo} AND INATIVO = 'F'`, cor: "sucesso" })}
          ${cartaoHtml(dados.inativos, "Inativos", { tabela: t, where: `${whereTipo} AND INATIVO = 'T'`, cor: "negativo" })}
          ${cartaoHtml(dados.com_saldo, "Com saldo", { tabela: t, where: `${whereTipo} AND COALESCE(SALDOGERALFISICO, 0) <> 0`, cor: "sucesso" })}
          ${cartaoHtml(dados.sem_saldo, "Sem saldo", { tabela: t, where: `${whereTipo} AND COALESCE(SALDOGERALFISICO, 0) = 0`, cor: "negativo" })}
        </div>
      </div>`;

    html += `
      <div class="grupo-cards" id="secao-produtos-servicos">
        <div class="titulo-secao-cards">Produtos &amp; Serviços</div>
        <div class="cards-duas-colunas">
          ${lado(resumo.produtos.produtos, "Produtos", "TIPO = 'P'")}
          ${lado(resumo.produtos.servicos, "Serviços", "TIPO = 'S'")}
        </div>
      </div>`;
  }

  if (resumo.parceiros) {
    const t = "FCFO";
    const ladoParceiro = (dados, rotulo, whereTipo) => `
      <div class="subgrupo-cards">
        <div class="subtitulo-cards">${rotulo}</div>
        <div class="cartoes-overview cartoes-overview--compacto">
          ${cartaoHtml(dados.total, "Total", { tabela: t, where: whereTipo })}
          ${cartaoHtml(dados.ativos, "Ativos", { tabela: t, where: `${whereTipo} AND ATIVO = 'T'`, cor: "sucesso" })}
          ${cartaoHtml(dados.inativos, "Inativos", { tabela: t, where: `${whereTipo} AND ATIVO = 'F'`, cor: "negativo" })}
        </div>
      </div>`;

    html += `
      <div class="grupo-cards" id="secao-clientes-fornecedores">
        <div class="titulo-secao-cards">Clientes &amp; Fornecedores (${resumo.parceiros.total_geral} no total)</div>
        <div class="cards-tres-colunas">
          ${ladoParceiro(resumo.parceiros.clientes, "Clientes", "TIPO = 'C'")}
          ${ladoParceiro(resumo.parceiros.fornecedores, "Fornecedores", "TIPO = 'F'")}
          ${ladoParceiro(resumo.parceiros.ambos, "Ambos", "TIPO = 'A'")}
        </div>
      </div>`;

    if (resumo.clientes_situacao) {
      const cs = resumo.clientes_situacao;
      // Mesmas subconsultas usadas no backend para calcular os totais —
      // repetidas aqui pra que cada card leve exatamente aos clientes que
      // ele representa. Um cliente cai em uma única categoria (a mais
      // urgente primeiro); "Inativos" só pega quem tem cadastro inativo.
      const temVencido = `EXISTS (SELECT 1 FROM FLAN L WHERE L.CODCFO = FCFO.CODCFO AND L.PAGREC = 'R' AND L.STATUSLAN = 'A' AND L.DATAVENCIMENTO < CURRENT_DATE)`;
      const temHoje = `EXISTS (SELECT 1 FROM FLAN L WHERE L.CODCFO = FCFO.CODCFO AND L.PAGREC = 'R' AND L.STATUSLAN = 'A' AND L.DATAVENCIMENTO = CURRENT_DATE)`;
      const temAberto = `EXISTS (SELECT 1 FROM FLAN L WHERE L.CODCFO = FCFO.CODCFO AND L.PAGREC = 'R' AND L.STATUSLAN = 'A')`;
      const temAVencer = `EXISTS (SELECT 1 FROM FLAN L WHERE L.CODCFO = FCFO.CODCFO AND L.PAGREC = 'R' AND L.STATUSLAN = 'A' AND L.DATAVENCIMENTO > CURRENT_DATE)`;

      html += `
        <div class="grupo-cards">
          <div class="titulo-secao-cards">Clientes por Situação Financeira (${cs.total} clientes)</div>
          <div class="cartoes-overview cartoes-overview--compacto">
            ${cartaoHtml(cs.vencidos, "Com Lançamentos Vencidos", { tabela: t, where: `TIPO = 'C' AND ATIVO = 'T' AND ${temVencido}`, cor: "negativo" })}
            ${cartaoHtml(cs.vencidos_no_dia, "Com Lançamentos Vencendo Hoje", { tabela: t, where: `TIPO = 'C' AND ATIVO = 'T' AND NOT ${temVencido} AND ${temHoje}`, cor: "info" })}
            ${cartaoHtml(cs.a_vencer, "Com Lançamentos a Vencer", { tabela: t, where: `TIPO = 'C' AND ATIVO = 'T' AND NOT ${temVencido} AND NOT ${temHoje} AND ${temAVencer}`, cor: "sucesso" })}
            ${cartaoHtml(cs.sem_aberto, "Sem Lançamentos em Aberto", { tabela: t, where: `TIPO = 'C' AND ATIVO = 'T' AND NOT ${temAberto}`, cor: "grafite" })}
            ${cartaoHtml(cs.inativos, "Clientes Inativos", { tabela: t, where: "TIPO = 'C' AND ATIVO = 'F'", cor: "grafite" })}
          </div>
          <p class="dica" style="margin-top:10px">Só considera lançamentos a receber (contas de cliente); cada cliente aparece em uma única categoria, da mais urgente pra menos.</p>
        </div>`;
    }
  }

  // ---- Sistema Estoque: Movimentos, Ordens de Serviço e Documentos Fiscais ----
  if (resumo.movimentos || resumo.ordens_servico || resumo.documentos_fiscais) {
    html += `<div class="titulo-sistema" id="secao-sistema-estoque">📦 Sistema Estoque</div>`;
  }

  if (resumo.movimentos) {
    const m = resumo.movimentos;
    const t = "TMOV";
    const porTipoHtml = resumo.movimentos_por_tipo?.length
      ? `<div class="grupo-cards--detalhe" style="margin-top:14px">
          <div class="titulo-secao-cards titulo-com-acao">Por Tipo de Documento (Orçamento, Pedido, PDV, Nota, Compra...)
            <button type="button" class="botao-csv" id="csv-movimentos-tipo">⬇️ CSV</button>
          </div>
          <div class="wrap-tabela"><table class="tabela-dados">
            <thead><tr><th>Tipo de Documento</th><th>Código</th><th>Quantidade</th><th>% do Total</th></tr></thead>
            <tbody>${resumo.movimentos_por_tipo
              .map(
                (tp) => `<tr>
                  <td><span class="link-tabela-inline" data-tabela="${t}" data-where="CODTMV = '${escHtml(tp.codigo)}'">${escHtml(tp.nome)}</span></td>
                  <td>${escHtml(tp.codigo)}</td>
                  <td>${formatarNumero(tp.qtd)}</td>
                  <td>${((tp.qtd / m.total) * 100).toFixed(1)}%</td>
                </tr>`
              )
              .join("")}</tbody>
          </table></div>
        </div>`
      : "";

    html += `
      <div class="grupo-cards">
        <div class="titulo-secao-cards">Movimentos (${m.total} no total)</div>
        <div class="cartoes-overview">
          ${cartaoHtml(m.normal, "Normal", { tabela: t, where: "STATUS = 'N'", cor: "ciano" })}
          ${cartaoHtml(m.faturado, "Faturado", { tabela: t, where: "STATUS = 'F'", cor: "info" })}
          ${cartaoHtml(m.a_faturar, "A Faturar", { tabela: t, where: "STATUS = 'A'", cor: "roxo" })}
          ${cartaoHtml(m.parc_quitado, "Parc. Quitado", { tabela: t, where: "STATUS = 'P'", cor: "amarelo" })}
          ${cartaoHtml(m.quitado, "Quitado", { tabela: t, where: "STATUS = 'Q'", cor: "sucesso" })}
          ${cartaoHtml(m.cancelado, "Cancelado", { tabela: t, where: "STATUS = 'C'", cor: "negativo" })}
        </div>
        ${porTipoHtml}
      </div>`;
  }

  if (resumo.ordens_servico) {
    const os = resumo.ordens_servico;
    const t = "TMOV";
    html += `
      <div class="grupo-cards">
        <div class="titulo-secao-cards">Ordens de Serviço (${os.total} no total)</div>
        <div class="cartoes-overview">
          ${cartaoHtml(os.em_aberto, "Em Aberto", { tabela: t, where: "STATUS2 = 'A'", cor: "amarelo" })}
          ${cartaoHtml(os.em_servico, "Em Serviço", { tabela: t, where: "STATUS2 = 'S'", cor: "ciano" })}
          ${cartaoHtml(os.encerrado, "Encerrado", { tabela: t, where: "STATUS2 = 'E'", cor: "sucesso" })}
        </div>
      </div>`;
  }

  if (resumo.documentos_fiscais) {
    const df = resumo.documentos_fiscais;

    // NF-e/NFC-e: status vem de TNFE, mas o modelo (55/65) só existe em
    // TMOV. Não dá pra fazer um JOIN de verdade no nosso filtro de card,
    // mas uma SUBQUERY no WHERE resolve igual, sem precisar mudar o FROM.
    const ladoNfe = (dados, rotulo, modelo) => {
      const t = "TNFE";
      const doModelo = `IDMOV IN (SELECT IDMOV FROM TMOV WHERE MODELODOCUMENTO = '${modelo}')`;
      return `
        <div class="subgrupo-cards">
          <div class="subtitulo-cards">
            <span class="link-tabela-inline" data-tabela="${t}" data-where="${escHtml(doModelo)}">${rotulo} (${dados.total})</span>
          </div>
          <div class="cartoes-overview cartoes-overview--compacto">
            ${cartaoHtml(dados.digitada, "Em Digitação", { tabela: t, where: `${doModelo} AND STATUSNFE = 0`, cor: "info" })}
            ${cartaoHtml(dados.autorizada, "Autorizada", { tabela: t, where: `${doModelo} AND STATUSNFE = 1`, cor: "sucesso" })}
            ${cartaoHtml(dados.processamento, "Em Processamento", { tabela: t, where: `${doModelo} AND STATUSNFE = 4`, cor: "amarelo" })}
            ${cartaoHtml(dados.rejeitada, "Rejeitada", { tabela: t, where: `${doModelo} AND STATUSNFE = 5`, cor: "negativo" })}
            ${cartaoHtml(dados.cancelada, "Cancelada", { tabela: t, where: `${doModelo} AND STATUSNFE = 2`, cor: "grafite" })}
            ${cartaoHtml(dados.denegada, "Denegada", { tabela: t, where: `${doModelo} AND STATUSNFE = 3`, cor: "laranja" })}
            ${cartaoHtml(dados.inutilizada, "Inutilizada", { tabela: t, where: `${doModelo} AND STATUSNFE = 6`, cor: "grafite" })}
          </div>
        </div>`;
    };

    html += `
      <div class="grupo-cards">
        <div class="titulo-secao-cards">
          Documentos Fiscais Eletrônicos
          ${df.nfe_indefinido?.total ? `<span class="detalhe-meta" style="font-weight:400; text-transform:none; letter-spacing:normal;">
            &nbsp;— + <span class="link-tabela-inline" data-tabela="TNFE" data-where="IDMOV NOT IN (SELECT IDMOV FROM TMOV WHERE MODELODOCUMENTO IN ('55','65'))">${df.nfe_indefinido.total} NF-e/NFC-e sem modelo identificado</span> em versões antigas do cadastro
          </span>` : ""}
        </div>
        <div class="cards-duas-colunas">
          ${ladoNfe(df.nfe, "NF-e", "55")}
          ${ladoNfe(df.nfce, "NFC-e", "65")}
        </div>
      </div>

      <div class="grupo-cards">
        <div class="cards-tres-colunas">
          <div class="subgrupo-cards">
            <div class="subtitulo-cards">NFS-e (${df.nfse.total})</div>
            <div class="cartoes-overview cartoes-overview--compacto">
              ${cartaoHtml(df.nfse.digitacao, "Em Digitação", { tabela: "TNFEMUNICIPAL", where: "STATUS = 'D'", cor: "info" })}
              ${cartaoHtml(df.nfse.processamento, "Em Processamento", { tabela: "TNFEMUNICIPAL", where: "STATUS = 'P'", cor: "amarelo" })}
              ${cartaoHtml(df.nfse.autorizada, "Autorizada", { tabela: "TNFEMUNICIPAL", where: "STATUS = 'E'", cor: "sucesso" })}
              ${cartaoHtml(df.nfse.rejeitada, "Rejeitada", { tabela: "TNFEMUNICIPAL", where: "STATUS = 'R'", cor: "negativo" })}
              ${cartaoHtml(df.nfse.cancelada, "Cancelada", { tabela: "TNFEMUNICIPAL", where: "STATUS = 'C'", cor: "grafite" })}
            </div>
          </div>
          <div class="subgrupo-cards">
            <div class="subtitulo-cards">CT-e (${df.cte.total})</div>
            <div class="cartoes-overview cartoes-overview--compacto">
              ${cartaoHtml(df.cte.digitacao, "Em Digitação", { tabela: "TCTE", where: "STATUS = 'D'", cor: "info" })}
              ${cartaoHtml(df.cte.processamento, "Em Processamento", { tabela: "TCTE", where: "STATUS = 'P'", cor: "amarelo" })}
              ${cartaoHtml(df.cte.autorizado, "Autorizado", { tabela: "TCTE", where: "STATUS = 'A'", cor: "sucesso" })}
              ${cartaoHtml(df.cte.rejeitado, "Rejeitado", { tabela: "TCTE", where: "STATUS = 'R'", cor: "negativo" })}
              ${cartaoHtml(df.cte.cancelado, "Cancelado", { tabela: "TCTE", where: "STATUS = 'C'", cor: "grafite" })}
            </div>
          </div>
          <div class="subgrupo-cards">
            <div class="subtitulo-cards">MDF-e (${df.mdfe.total})</div>
            <div class="cartoes-overview cartoes-overview--compacto">
              ${cartaoHtml(df.mdfe.digitacao, "Em Digitação", { tabela: "TMDFE", where: "STATUS = 0", cor: "info" })}
              ${cartaoHtml(df.mdfe.processamento, "Em Processamento", { tabela: "TMDFE", where: "STATUS = 1", cor: "amarelo" })}
              ${cartaoHtml(df.mdfe.autorizado, "Autorizado", { tabela: "TMDFE", where: "STATUS = 2", cor: "sucesso" })}
              ${cartaoHtml(df.mdfe.encerrado, "Encerrado", { tabela: "TMDFE", where: "STATUS = 3", cor: "sucesso" })}
              ${cartaoHtml(df.mdfe.rejeitado, "Rejeitado", { tabela: "TMDFE", where: "STATUS = 4", cor: "negativo" })}
              ${cartaoHtml(df.mdfe.cancelado, "Cancelado", { tabela: "TMDFE", where: "STATUS = 5", cor: "grafite" })}
            </div>
          </div>
        </div>
      </div>

      ${df.mde ? `<div class="grupo-cards">
        <div class="titulo-secao-cards">MD-e / DF-e recebidos de terceiros — Manifestação do Destinatário (${df.mde.total})</div>
        <div class="cartoes-overview cartoes-overview--compacto">
          ${cartaoHtml(df.mde.nao_manifestada, "Não Manifestadas", { tabela: "TDFE", where: "DFE = '0'", cor: "info" })}
          ${cartaoHtml(df.mde.confirmacao_operacao, "Confirmação da Operação", { tabela: "TDFE", where: "DFE = '1'", cor: "sucesso" })}
          ${cartaoHtml(df.mde.ciencia_emissao, "Ciência da Emissão", { tabela: "TDFE", where: "DFE = '2'", cor: "amarelo" })}
          ${cartaoHtml(df.mde.operacao_desconhecida, "Operação Desconhecida", { tabela: "TDFE", where: "DFE = '3'", cor: "grafite" })}
          ${cartaoHtml(df.mde.operacao_nao_realizada, "Operação Não Realizada", { tabela: "TDFE", where: "DFE = '4'", cor: "negativo" })}
        </div>
        <div class="titulo-secao-cards" style="margin-top:16px">Download do XML</div>
        <div class="cartoes-overview cartoes-overview--compacto">
          ${cartaoHtml(df.mde.download_realizado, "Download Realizado", { tabela: "TDFE", where: "NFE = 'T'", cor: "sucesso" })}
          ${cartaoHtml(df.mde.download_pendente, "Download Pendente", { tabela: "TDFE", where: "NFE = 'F'", cor: "amarelo" })}
        </div>
        <p class="dica" style="margin-top:10px">Não encontrei no banco um campo confiável para "Já Importada" (documento convertido em movimento/entrada) — prefiro deixar de fora a mostrar um número adivinhado.</p>
      </div>` : ""}`;
  }

  // ---- Sistema Financeiro: Lançamentos e Valores (FLAN) ----
  if (resumo.financeiro) {
    const f = resumo.financeiro;
    const t = "FLAN";
    html += `
      <div class="titulo-sistema" id="secao-sistema-financeiro">💰 Sistema Financeiro</div>
      <div class="grupo-cards" id="secao-financeiro-valores">
        <div class="titulo-secao-cards">Valores (a pagar/a receber e movimentado no período)</div>
        <div class="botoes-periodo">
          <button type="button" class="botao-periodo" data-preset="hoje">Hoje</button>
          <button type="button" class="botao-periodo" data-preset="ontem">Ontem</button>
          <button type="button" class="botao-periodo" data-preset="7dias">7 dias</button>
          <button type="button" class="botao-periodo" data-preset="mes">Este mês</button>
          <button type="button" class="botao-periodo" data-preset="ano">Este ano</button>
        </div>
        <div class="filtro-periodo">
          <label>De <input type="date" id="fin-periodo-inicio"></label>
          <label>Até <input type="date" id="fin-periodo-fim"></label>
          <button type="button" id="btn-atualizar-periodo" class="botao-secundario">🔄 Atualizar</button>
        </div>
        <div id="cards-financeiro-valores" class="cartoes-overview">
          <p class="dica">Carregando...</p>
        </div>
      </div>
      <div class="grupo-cards">
        <div class="titulo-secao-cards">Lançamentos (${f.total} no total)</div>
        <div class="cartoes-overview">
          ${cartaoHtml(f.aberto, "Aberto", { tabela: t, where: "STATUSLAN = 'A'", cor: "amarelo" })}
          ${cartaoHtml(f.faturado, "Faturado", { tabela: t, where: "STATUSLAN = 'F'", cor: "info" })}
          ${cartaoHtml(f.baixado, "Baixado", { tabela: t, where: "STATUSLAN = 'B'", cor: "sucesso" })}
          ${cartaoHtml(f.cancelado, "Cancelado", { tabela: t, where: "STATUSLAN = 'C'", cor: "negativo" })}
        </div>
      </div>`;
  }

  return html;
}

// --------------------------------------------------------------------
// Navegar até uma tabela já com um filtro de dados aplicado (cards)
// --------------------------------------------------------------------

let filtroPendente = null; // { tabela, where, rotulo }

function irParaTabelaComFiltro(tabela, where, rotulo) {
  filtroPendente = { tabela, where, rotulo };
  selecionarAba("tabelas").then(() => abrirTabela(tabela));
}

function mostrarBoasVindas() {
  painel.innerHTML = `
    <div class="painel-boas-vindas">
      <div class="detalhe-cabecalho">
        <h2>Visão geral do banco</h2>
        <div class="cabecalho-acoes">
          <span id="visaogeral-atualizado-em" class="detalhe-meta-atualizado"></span>
          <button type="button" id="btn-atualizar-visaogeral" class="botao-secundario">🔄 Atualizar</button>
        </div>
      </div>
      <div id="atalhos-visao-geral" class="atalhos-secoes" hidden></div>
      <div id="periodo-global" class="grupo-cards" hidden>
        <div class="titulo-secao-cards">📅 Período (afeta os cards financeiros com data)</div>
        <div class="botoes-periodo">
          <button type="button" class="botao-periodo" data-preset="hoje">Hoje</button>
          <button type="button" class="botao-periodo" data-preset="ontem">Ontem</button>
          <button type="button" class="botao-periodo" data-preset="7dias">7 dias</button>
          <button type="button" class="botao-periodo" data-preset="mes">Este mês</button>
          <button type="button" class="botao-periodo" data-preset="ano">Este ano</button>
        </div>
      </div>
      <div id="secao-favoritos" class="grupo-cards" hidden></div>
      <div class="grupo-cards" id="secao-estrutura">
        <div id="cartoes-overview" class="cartoes-overview"></div>
      </div>
      <div id="secao-negocio"></div>
      <p class="dica">Selecione um item na barra lateral para ver os detalhes, ou use a busca acima.</p>
    </div>`;
  $("#btn-atualizar-visaogeral").addEventListener("click", carregarOverview);
  renderizarFavoritos();
  carregarOverview();
}

// --------------------------------------------------------------------
// Abas laterais
// --------------------------------------------------------------------

$$(".aba-lateral").forEach((btn) => {
  btn.addEventListener("click", () => selecionarAba(btn.dataset.aba));
});

async function selecionarAba(aba) {
  abaAtual = aba;
  $$(".aba-lateral").forEach((b) => b.classList.toggle("ativa", b.dataset.aba === aba));
  resultadoBusca.hidden = true;

  if (aba === "visaogeral") {
    listaLateral.innerHTML = `<p class="vazio" style="padding:8px">Resumo geral do banco — veja mais detalhes nas outras abas.</p>`;
    mostrarBoasVindas();
  } else if (aba === "tabelas") {
    if (!cacheTabelas.length) cacheTabelas = await apiFetch("/api/tables");
    renderListaTabelas(cacheTabelas);
    painel.innerHTML = `
      <div class="painel-boas-vindas">
        <h2>Tabelas &amp; Views</h2>
        <p class="dica">Selecione uma tabela na lista ao lado (clique na seta para uma prévia rápida dos campos), ou use a busca no topo.</p>
      </div>`;
  } else if (aba === "procedures") {
    if (!cacheProcedures.length) cacheProcedures = await apiFetch("/api/procedures");
    renderListaLateral(
      cacheProcedures.map((p) => ({ id: p.name, rotulo: p.name, tag: null })),
      (id) => abrirProcedure(id)
    );
    mostrarBoasVindas();
  } else if (aba === "triggers") {
    if (!cacheTriggers.length) cacheTriggers = await apiFetch("/api/triggers");
    renderListaLateral(
      cacheTriggers.map((t) => ({ id: t.name, rotulo: t.name, tag: t.table_name || "DB" })),
      (id) => abrirTrigger(id)
    );
    mostrarBoasVindas();
  } else if (aba === "generators") {
    listaLateral.innerHTML = `<p class="vazio" style="padding:8px">Veja a lista completa no painel.</p>`;
    await abrirGenerators();
  } else if (aba === "analise") {
    listaLateral.innerHTML = `<p class="vazio" style="padding:8px">Painel financeiro consolidado (saldo de caixas, hoje/atraso, aging list).</p>`;
    await abrirAnaliseFinanceira();
  } else if (aba === "analise-estoque") {
    listaLateral.innerHTML = `<p class="vazio" style="padding:8px">Saldo, mínimo/máximo, custo e valor do estoque.</p>`;
    await abrirAnaliseEstoque();
  } else if (aba === "mudancas") {
    listaLateral.innerHTML = `<p class="vazio" style="padding:8px">Compare a estrutura atual com uma referência salva anteriormente.</p>`;
    await abrirMudancasSchema();
  } else if (aba === "sql") {
    listaLateral.innerHTML = `<p class="vazio" style="padding:8px">Digite uma consulta SELECT no painel ao lado.</p>`;
    abrirConsoleSql();
  }
}

function renderListaLateral(itens, aoClicar) {
  if (!itens.length) {
    listaLateral.innerHTML = `<p class="vazio" style="padding:8px">Nenhum item encontrado.</p>`;
    return;
  }
  listaLateral.innerHTML = itens
    .map(
      (it) => `<div class="item-lista" data-id="${escHtml(it.id)}">
        <span>${escHtml(it.rotulo)}</span>
        ${it.tag ? `<span class="tag-view">${it.tag}</span>` : ""}
      </div>`
    )
    .join("");
  $$(".item-lista", listaLateral).forEach((el) => {
    el.addEventListener("click", () => {
      $$(".item-lista", listaLateral).forEach((e) => e.classList.remove("selecionado"));
      el.classList.add("selecionado");
      aoClicar(el.dataset.id);
    });
  });
}

// --------------------------------------------------------------------
// Árvore expansível de tabelas (barra lateral)
// --------------------------------------------------------------------

function renderListaTabelas(tabelas) {
  if (!tabelas.length) {
    listaLateral.innerHTML = `<p class="vazio" style="padding:8px">Nenhuma tabela encontrada.</p>`;
    return;
  }

  listaLateral.innerHTML = tabelas
    .map(
      (t) => `<div class="item-tabela">
        <div class="item-tabela-cabecalho" data-nome="${escHtml(t.name)}">
          <span class="seta-expandir" data-nome="${escHtml(t.name)}">▸</span>
          <span class="nome-tabela">${escHtml(t.name)}</span>
          ${t.is_view ? '<span class="tag-view">VIEW</span>' : ""}
        </div>
        <div class="item-tabela-colunas" data-nome="${escHtml(t.name)}" hidden></div>
      </div>`
    )
    .join("");

  $$(".seta-expandir", listaLateral).forEach((el) => {
    el.addEventListener("click", (ev) => {
      ev.stopPropagation();
      alternarExpansaoTabela(el.dataset.nome);
    });
  });

  $$(".item-tabela-cabecalho", listaLateral).forEach((el) => {
    el.addEventListener("click", () => {
      $$(".item-tabela-cabecalho", listaLateral).forEach((e) => e.classList.remove("selecionado"));
      el.classList.add("selecionado");
      abrirTabela(el.dataset.nome);
    });
  });
}

async function alternarExpansaoTabela(nome) {
  const seta = listaLateral.querySelector(`.seta-expandir[data-nome="${CSS.escape(nome)}"]`);
  const colunasEl = listaLateral.querySelector(`.item-tabela-colunas[data-nome="${CSS.escape(nome)}"]`);
  if (!colunasEl) return;

  const jaAberto = !colunasEl.hidden;
  if (jaAberto) {
    colunasEl.hidden = true;
    seta.textContent = "▸";
    return;
  }

  seta.textContent = "▾";
  colunasEl.hidden = false;

  if (cacheColunasPorTabela[nome]) {
    colunasEl.innerHTML = cacheColunasPorTabela[nome];
    return;
  }

  colunasEl.innerHTML = `<div class="coluna-mini"><span class="dica">Carregando...</span></div>`;
  try {
    const colunas = await apiFetch(`/api/tables/${encodeURIComponent(nome)}/columns`);
    const html = colunas.length
      ? colunas
          .map(
            (c) => `<div class="coluna-mini">
              <span>${escHtml(c.name)}${c.nullable ? "" : " *"}</span>
              <span class="coluna-tipo">${escHtml(c.type_desc)}</span>
            </div>`
          )
          .join("")
      : `<div class="coluna-mini"><span class="dica">Sem campos.</span></div>`;
    cacheColunasPorTabela[nome] = html;
    colunasEl.innerHTML = html;
  } catch (err) {
    colunasEl.innerHTML = `<div class="coluna-mini" style="color:var(--erro)">${escHtml(err.message)}</div>`;
  }
}

// --------------------------------------------------------------------
// Busca
// --------------------------------------------------------------------

// Atalho de teclado: "/" (fora de um campo de texto) ou Ctrl+K (em qualquer
// lugar) leva o foco direto pra caixa de busca — como em vários sites/apps.
document.addEventListener("keydown", (ev) => {
  const alvo = ev.target;
  const emCampoDeTexto = alvo && (alvo.tagName === "INPUT" || alvo.tagName === "TEXTAREA" || alvo.isContentEditable);
  const ehCtrlK = (ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === "k";
  const ehBarra = ev.key === "/" && !emCampoDeTexto;
  if (ehCtrlK || ehBarra) {
    ev.preventDefault();
    $("#busca").focus();
    $("#busca").select();
  }
});

let timeoutBusca = null;
$("#busca").addEventListener("input", (ev) => {
  clearTimeout(timeoutBusca);
  const termo = ev.target.value.trim();
  if (termo.length < 2) {
    resultadoBusca.hidden = true;
    return;
  }
  timeoutBusca = setTimeout(() => executarBusca(termo), 300);
});

async function executarBusca(termo) {
  try {
    const res = await apiFetch(`/api/search?q=${encodeURIComponent(termo)}`);
    resultadoBusca.hidden = false;
    const blocos = [];

    if (res.tables.length) {
      blocos.push(
        `<h4>Tabelas/Views</h4>` +
          res.tables
            .map(
              (t) =>
                `<div class="item-lista" data-tipo="tabela" data-id="${escHtml(t.name)}">
                  <span>${escHtml(t.name)}</span>${t.is_view ? '<span class="tag-view">VIEW</span>' : ""}
                </div>`
            )
            .join("")
      );
    }
    if (res.columns.length) {
      blocos.push(
        `<h4>Campos</h4>` +
          res.columns
            .slice(0, 50)
            .map(
              (c) =>
                `<div class="item-lista" data-tipo="tabela" data-id="${escHtml(c.table_name)}">
                  <span>${escHtml(c.table_name)}.${escHtml(c.field_name)}</span>
                </div>`
            )
            .join("")
      );
    }
    if (res.procedures.length) {
      blocos.push(
        `<h4>Procedures</h4>` +
          res.procedures
            .map(
              (p) =>
                `<div class="item-lista" data-tipo="procedure" data-id="${escHtml(p.name)}">
                  <span>${escHtml(p.name)}</span>
                </div>`
            )
            .join("")
      );
    }
    if (res.triggers.length) {
      blocos.push(
        `<h4>Triggers</h4>` +
          res.triggers
            .map(
              (t) =>
                `<div class="item-lista" data-tipo="trigger" data-id="${escHtml(t.name)}">
                  <span>${escHtml(t.name)} <small style="opacity:.6">(${escHtml(t.table_name || "DB")})</small></span>
                </div>`
            )
            .join("")
      );
    }
    if (res.generators.length) {
      blocos.push(
        `<h4>Generators</h4>` +
          res.generators.map((g) => `<div class="item-lista"><span>${escHtml(g.name)}</span></div>`).join("")
      );
    }

    resultadoBusca.innerHTML = blocos.length
      ? blocos.join("")
      : `<p class="vazio" style="padding:8px">Nada encontrado para "${escHtml(termo)}".</p>`;

    $$(".item-lista[data-tipo]", resultadoBusca).forEach((el) => {
      el.addEventListener("click", () => {
        const tipo = el.dataset.tipo;
        const id = el.dataset.id;
        if (!id) return;
        if (tipo === "tabela") {
          selecionarAba("tabelas").then(() => abrirTabela(id));
        } else if (tipo === "procedure") {
          selecionarAba("procedures").then(() => abrirProcedure(id));
        } else if (tipo === "trigger") {
          selecionarAba("triggers").then(() => abrirTrigger(id));
        }
      });
    });
  } catch (err) {
    console.error(err);
  }
}

// --------------------------------------------------------------------
// Detalhe de tabela/view
// --------------------------------------------------------------------

async function abrirTabela(nome) {
  painel.innerHTML = `<p class="dica">Carregando ${escHtml(nome)}...</p>`;
  try {
    const t = await apiFetch(`/api/tables/${encodeURIComponent(nome)}`);
    renderTabela(t);

    // Se viemos de um clique num card de resumo, já carrega os dados filtrados
    if (filtroPendente && filtroPendente.tabela.toUpperCase() === nome.toUpperCase()) {
      const { where, rotulo } = filtroPendente;
      filtroPendente = null;
      carregarDadosTabela(t.name, where, rotulo);
      $("#secao-dados")?.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  } catch (err) {
    painel.innerHTML = `<div class="alerta-erro">${escHtml(err.message)}</div>`;
  }
}

function renderTabela(t) {
  const pk = t.primary_key ? t.primary_key.fields.join(", ") : null;

  const colunasHtml = t.columns
    .map((c) => {
      const ehPk = t.primary_key && t.primary_key.fields.includes(c.name);
      return `<tr>
        <td>${ehPk ? "🔑 " : ""}${escHtml(c.name)}</td>
        <td>${escHtml(c.type_desc)}</td>
        <td>${c.nullable ? '<span class="chip-nao">permite NULL</span>' : '<span class="chip-sim">NOT NULL</span>'}</td>
        <td>${c.default_source ? escHtml(c.default_source) : ""}</td>
        <td>${c.domain_name ? escHtml(c.domain_name) : ""}</td>
      </tr>`;
    })
    .join("");

  const fkHtml = t.foreign_keys.length
    ? t.foreign_keys
        .map(
          (fk) => `<tr>
        <td>${escHtml(fk.fk_name)}</td>
        <td>${escHtml(fk.fields.join(", "))}</td>
        <td>&rarr; <span class="link-tabela" data-tabela="${escHtml(fk.ref_table)}">${escHtml(fk.ref_table)}</span></td>
        <td>${escHtml(fk.ref_fields.join(", "))}</td>
        <td>${escHtml(fk.update_rule || "")}</td>
        <td>${escHtml(fk.delete_rule || "")}</td>
      </tr>`
        )
        .join("")
    : "";

  const refHtml = t.referenced_by.length
    ? t.referenced_by
        .map(
          (r) => `<tr>
        <td><span class="link-tabela" data-tabela="${escHtml(r.from_table)}">${escHtml(r.from_table)}</span></td>
        <td>${escHtml(r.fields.join(", "))}</td>
        <td>${escHtml(r.ref_fields.join(", "))}</td>
      </tr>`
        )
        .join("")
    : "";

  const idxHtml = t.indexes.length
    ? t.indexes
        .map(
          (i) => `<tr>
        <td>${escHtml(i.index_name)}</td>
        <td>${escHtml(i.fields.join(", "))}</td>
        <td>${i.unique ? '<span class="chip-sim">SIM</span>' : '<span class="chip-nao">não</span>'}</td>
        <td>${escHtml(i.order)}</td>
        <td>${escHtml(i.constraint_type || "")}</td>
        <td>${i.inactive ? '<span class="chip-nao">inativo</span>' : '<span class="chip-sim">ativo</span>'}</td>
      </tr>`
        )
        .join("")
    : "";

  const trigHtml = t.triggers.length
    ? t.triggers
        .map(
          (tg) => `<tr>
        <td>${escHtml(tg.name)}</td>
        <td>${escHtml(tg.type_desc)}</td>
        <td>${tg.sequence}</td>
        <td>${tg.inactive ? '<span class="chip-nao">inativo</span>' : '<span class="chip-sim">ativo</span>'}</td>
      </tr>`
        )
        .join("")
    : "";

  painel.innerHTML = `
    <div class="detalhe-cabecalho">
      <h2>${escHtml(t.name)}</h2>
      <span class="badge">${t.is_view ? "VIEW" : "TABELA"}</span>
    </div>
    <div class="detalhe-meta">
      ${t.is_view ? "" : `Registros: <strong>${t.row_count ?? "?"}</strong> &nbsp;|&nbsp; `}
      Chave primária: <strong>${pk ? escHtml(pk) : "nenhuma"}</strong>
    </div>

    ${
      t.is_view
        ? `<div class="secao"><h3>Definição da View (SQL)</h3><pre class="bloco-codigo">${escHtml(t.view_source || "(sem fonte disponível)")}</pre></div>`
        : ""
    }

    <div class="secao">
      <h3>Campos (${t.columns.length})</h3>
      <div class="wrap-tabela">
        <table class="tabela-dados">
          <thead><tr><th>Nome</th><th>Tipo</th><th>Nulo</th><th>Default</th><th>Domínio</th></tr></thead>
          <tbody>${colunasHtml}</tbody>
        </table>
      </div>
    </div>

    <div class="secao" id="secao-dados">
      <div class="secao-dados-cabecalho">
        <h3>Dados</h3>
        <button type="button" id="btn-preview-dados" class="botao-secundario">
          👁️ Ver dados (SELECT * FROM ${escHtml(t.name)})
        </button>
      </div>
      <div id="filtro-info" class="filtro-info" hidden></div>
      <div id="editor-dados-area" class="editor-dados" hidden>
        <textarea id="sql-dados-input" class="sql-dados-textarea" spellcheck="false" rows="3"></textarea>
        <div class="sql-dados-acoes">
          <button type="button" id="btn-executar-dados" class="botao-primario">▶ Executar</button>
          <span id="sql-dados-status"></span>
        </div>
      </div>
      <div id="preview-dados-area"></div>
    </div>

    <div class="secao">
      <h3>Chaves estrangeiras (${t.foreign_keys.length})</h3>
      ${
        fkHtml
          ? `<div class="wrap-tabela"><table class="tabela-dados">
              <thead><tr><th>Constraint</th><th>Campo(s)</th><th>Referencia</th><th>Campo ref.</th><th>On Update</th><th>On Delete</th></tr></thead>
              <tbody>${fkHtml}</tbody></table></div>`
          : `<p class="vazio">Esta tabela não possui chaves estrangeiras.</p>`
      }
    </div>

    <div class="secao">
      <h3>Referenciada por (${t.referenced_by.length})</h3>
      ${
        refHtml
          ? `<div class="wrap-tabela"><table class="tabela-dados">
              <thead><tr><th>Tabela</th><th>Campo(s)</th><th>Aponta para</th></tr></thead>
              <tbody>${refHtml}</tbody></table></div>`
          : `<p class="vazio">Nenhuma outra tabela referencia esta.</p>`
      }
    </div>

    <div class="secao">
      <h3>Índices (${t.indexes.length})</h3>
      ${
        idxHtml
          ? `<div class="wrap-tabela"><table class="tabela-dados">
              <thead><tr><th>Nome</th><th>Campo(s)</th><th>Único</th><th>Ordem</th><th>Constraint</th><th>Status</th></tr></thead>
              <tbody>${idxHtml}</tbody></table></div>`
          : `<p class="vazio">Nenhum índice encontrado.</p>`
      }
    </div>

    <div class="secao">
      <h3>Triggers (${t.triggers.length})</h3>
      ${
        trigHtml
          ? `<div class="wrap-tabela"><table class="tabela-dados">
              <thead><tr><th>Nome</th><th>Evento</th><th>Ordem</th><th>Status</th></tr></thead>
              <tbody>${trigHtml}</tbody></table></div>`
          : `<p class="vazio">Nenhuma trigger nesta tabela.</p>`
      }
    </div>
  `;

  $$(".link-tabela", painel).forEach((el) => {
    el.addEventListener("click", () => abrirTabela(el.dataset.tabela));
  });

  const btnPreview = $("#btn-preview-dados", painel);
  if (btnPreview) {
    btnPreview.addEventListener("click", () => carregarDadosTabela(t.name, null, null));
  }

  const btnExecutar = $("#btn-executar-dados", painel);
  if (btnExecutar) {
    btnExecutar.addEventListener("click", () => executarSqlDados());
  }
  const sqlInput = $("#sql-dados-input", painel);
  if (sqlInput) {
    sqlInput.addEventListener("keydown", (ev) => {
      if (ev.ctrlKey && ev.key === "Enter") executarSqlDados();
    });
  }
}

/**
 * Carrega dados de uma tabela na seção "Dados" do detalhe — sem filtro,
 * equivale a "SELECT * FROM tabela"; com `where`, mostra só o subconjunto
 * (usado pelos cards clicáveis de resumo). Preenche o editor de SQL com a
 * consulta usada, para que possa ser editada e reexecutada livremente.
 */
function carregarDadosTabela(nomeTabela, where, rotuloFiltro) {
  const editorArea = $("#editor-dados-area");
  const input = $("#sql-dados-input");
  const infoEl = $("#filtro-info");
  if (!editorArea || !input || !infoEl) return; // usuário já navegou para outra tela

  const limite = where ? 200 : 100;
  const sql = where
    ? `SELECT FIRST ${limite} * FROM ${nomeTabela} WHERE ${where}`
    : `SELECT FIRST ${limite} * FROM ${nomeTabela}`;

  editorArea.hidden = false;
  input.value = sql;
  input.dataset.sqlOriginal = sql;

  if (where) {
    infoEl.hidden = false;
    infoEl.innerHTML = `
      Mostrando: <strong>${escHtml(rotuloFiltro || "")}</strong>
      <code>WHERE ${escHtml(where)}</code>
      <button type="button" id="btn-limpar-filtro" class="botao-secundario">✕ Limpar filtro</button>`;
    $("#btn-limpar-filtro").addEventListener("click", () => carregarDadosTabela(nomeTabela, null, null));
  } else {
    infoEl.hidden = true;
    infoEl.innerHTML = "";
  }

  executarSqlDados();
}

/**
 * Executa o SQL que está no editor da seção "Dados" (somente leitura, passa
 * pela mesma validação do console SQL) e mostra o resultado logo abaixo.
 * Pode ser chamada tanto pelo fluxo automático (cards/"Ver dados") quanto
 * manualmente pelo usuário depois de editar a consulta.
 */
async function executarSqlDados() {
  const input = $("#sql-dados-input");
  const area = $("#preview-dados-area");
  const infoEl = $("#filtro-info");
  const status = $("#sql-dados-status");
  const botao = $("#btn-executar-dados");
  if (!input || !area) return;

  const sql = input.value.trim();
  if (!sql) return;

  // Se o usuário alterou a consulta original (ex: veio de um card com filtro
  // e mudou o WHERE), a etiqueta de filtro fica desatualizada — esconde.
  if (infoEl && sql !== input.dataset.sqlOriginal) {
    infoEl.hidden = true;
    infoEl.innerHTML = "";
  }

  if (botao) botao.disabled = true;
  status.innerHTML = `<span class="spinner"></span> Executando...`;
  area.innerHTML = "";

  try {
    const inicio = performance.now();
    const res = await apiFetch("/api/query", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ sql, limite: 500 }),
    });
    const tempo = ((performance.now() - inicio) / 1000).toFixed(2);
    status.textContent = `${res.linhas.length} linha(s) em ${tempo}s${res.truncado ? " (resultado truncado)" : ""}`;

    renderizarGradeComFiltro(area, res.colunas, res.linhas, sql);
  } catch (err) {
    status.textContent = "";
    area.innerHTML = `<div class="alerta-erro">${escHtml(err.message)}</div>`;
  } finally {
    if (botao) botao.disabled = false;
  }
}

// --------------------------------------------------------------------
// Detalhe de procedure
// --------------------------------------------------------------------

async function abrirProcedure(nome) {
  painel.innerHTML = `<p class="dica">Carregando ${escHtml(nome)}...</p>`;
  try {
    const p = await apiFetch(`/api/procedures/${encodeURIComponent(nome)}`);
    renderProcedure(p);
  } catch (err) {
    painel.innerHTML = `<div class="alerta-erro">${escHtml(err.message)}</div>`;
  }
}

function paramsHtml(params) {
  if (!params.length) return `<p class="vazio">Nenhum.</p>`;
  return `<div class="wrap-tabela"><table class="tabela-dados">
    <thead><tr><th>#</th><th>Nome</th><th>Tipo</th></tr></thead>
    <tbody>${params
      .map((p) => `<tr><td>${p.field_position + 1}</td><td>${escHtml(p.name)}</td><td>${escHtml(p.type_desc)}</td></tr>`)
      .join("")}</tbody>
  </table></div>`;
}

function renderProcedure(p) {
  painel.innerHTML = `
    <div class="detalhe-cabecalho">
      <h2>${escHtml(p.name)}</h2>
      <span class="badge">PROCEDURE</span>
    </div>

    <div class="secao">
      <h3>Parâmetros de entrada (${p.input_params.length})</h3>
      ${paramsHtml(p.input_params)}
    </div>

    <div class="secao">
      <h3>Parâmetros de saída (${p.output_params.length})</h3>
      ${paramsHtml(p.output_params)}
    </div>

    <div class="secao">
      <h3>Código fonte</h3>
      <pre class="bloco-codigo">${escHtml(p.source || "(sem fonte disponível)")}</pre>
    </div>
  `;
}

// --------------------------------------------------------------------
// Detalhe de trigger
// --------------------------------------------------------------------

async function abrirTrigger(nome) {
  painel.innerHTML = `<p class="dica">Carregando ${escHtml(nome)}...</p>`;
  try {
    const t = await apiFetch(`/api/triggers/${encodeURIComponent(nome)}`);
    renderTrigger(t);
  } catch (err) {
    painel.innerHTML = `<div class="alerta-erro">${escHtml(err.message)}</div>`;
  }
}

function renderTrigger(t) {
  const tabelaHtml = t.table_name
    ? `<span class="link-tabela" data-tabela="${escHtml(t.table_name)}">${escHtml(t.table_name)}</span>`
    : "(trigger de banco de dados)";

  painel.innerHTML = `
    <div class="detalhe-cabecalho">
      <h2>${escHtml(t.name)}</h2>
      <span class="badge">TRIGGER</span>
    </div>
    <div class="detalhe-meta">
      Tabela: <strong>${tabelaHtml}</strong>
      &nbsp;|&nbsp; Evento: <strong>${escHtml(t.type_desc)}</strong>
      &nbsp;|&nbsp; Ordem: <strong>${t.sequence}</strong>
      &nbsp;|&nbsp; Status: ${t.inactive ? '<span class="chip-nao">inativa</span>' : '<span class="chip-sim">ativa</span>'}
    </div>

    <div class="secao">
      <h3>Código fonte</h3>
      <pre class="bloco-codigo">${escHtml(t.source || "(sem fonte disponível)")}</pre>
    </div>
  `;

  $$(".link-tabela", painel).forEach((el) => {
    el.addEventListener("click", () => selecionarAba("tabelas").then(() => abrirTabela(el.dataset.tabela)));
  });
}

// --------------------------------------------------------------------
// Generators
// --------------------------------------------------------------------

async function abrirGenerators() {
  painel.innerHTML = `<p class="dica">Carregando generators...</p>`;
  try {
    const gens = await apiFetch("/api/generators");
    const linhas = gens
      .map((g) => `<tr><td>${escHtml(g.name)}</td><td>${g.current_value ?? "?"}</td></tr>`)
      .join("");
    painel.innerHTML = `
      <div class="detalhe-cabecalho"><h2>Generators / Sequences</h2></div>
      <div class="wrap-tabela"><table class="tabela-dados">
        <thead><tr><th>Nome</th><th>Valor atual</th></tr></thead>
        <tbody>${linhas || '<tr><td colspan="2">Nenhum generator encontrado.</td></tr>'}</tbody>
      </table></div>
    `;
  } catch (err) {
    painel.innerHTML = `<div class="alerta-erro">${escHtml(err.message)}</div>`;
  }
}

// --------------------------------------------------------------------
// Mudanças na estrutura (comparação com referência salva)
// --------------------------------------------------------------------

// --------------------------------------------------------------------
// Análise Financeira (saldo de caixas, hoje/atraso, aging list)
// --------------------------------------------------------------------

const ROTULOS_FAIXA_AGING = {
  hoje: "Hoje",
  d01_07: "01 a 07 Dias",
  d08_15: "08 a 15 Dias",
  d16_30: "16 a 30 Dias",
  d31_60: "31 a 60 Dias",
  d61_90: "61 a 90 Dias",
  mais_90: "Mais de 90 Dias",
};

async function abrirAnaliseFinanceira(dataRef) {
  painel.innerHTML = `<p class="dica">Carregando análise financeira...</p>`;
  try {
    const url = dataRef ? `/api/analise-financeira?data=${encodeURIComponent(dataRef)}` : "/api/analise-financeira";
    const d = await apiFetch(url);
    renderAnaliseFinanceira(d, dataRef);
  } catch (err) {
    painel.innerHTML = `<div class="alerta-erro">${escHtml(err.message)}</div>`;
  }
}

function renderAnaliseFinanceira(d, dataRefAtual) {
  if (!d || !("hoje" in d)) {
    painel.innerHTML = `
      <div class="detalhe-cabecalho"><h2>Análise Financeira</h2></div>
      <p class="vazio">Este banco não tem a tabela FLAN — a análise financeira não está disponível.</p>
    `;
    return;
  }

  const t = "FLAN";
  const inicioMes = d.primeiro_dia_mes_iso;
  const hojeIso = d.hoje_iso;

  const caixasHtml = d.caixas
    ? `
      <div class="grupo-cards grupo-cards--detalhe">
        <div class="titulo-secao-cards titulo-com-acao">Saldo por Caixa/Conta (Total: ${formatarMoeda(d.saldo_total)})
          <button type="button" class="botao-csv" id="csv-caixas">⬇️ CSV</button>
        </div>
        <div class="wrap-tabela"><table class="tabela-dados">
          <thead><tr><th>Código</th><th>Caixa</th><th>Saldo Inicial</th><th>Saldo Atual</th><th>Status</th></tr></thead>
          <tbody>${d.caixas
            .map(
              (c) => `<tr>
                <td>${escHtml(c.codigo)}</td>
                <td>${escHtml(c.descricao)}</td>
                <td>${formatarMoeda(c.saldo_inicial)}</td>
                <td>${formatarMoeda(c.saldo_atual)}</td>
                <td>${c.inativo ? '<span class="chip-nao">inativo</span>' : '<span class="chip-sim">ativo</span>'}</td>
              </tr>`
            )
            .join("")}</tbody>
        </table></div>
      </div>`
    : "";

  const linhasAging = Object.keys(ROTULOS_FAIXA_AGING)
    .map((chave) => {
      const f = d.aging[chave];
      return `<tr>
        <td>${ROTULOS_FAIXA_AGING[chave]}</td>
        <td>${formatarMoeda(f.receber_vencidos)}</td>
        <td>${formatarMoeda(f.pagar_vencidos)}</td>
        <td>${formatarMoeda(f.receber_vencer)}</td>
        <td>${formatarMoeda(f.pagar_vencer)}</td>
      </tr>`;
    })
    .join("");

  const rankingAtrasoTabelaHtml = (lista, titulo, idCsv, rotuloEntidade) =>
    lista?.length
      ? `<div class="grupo-cards grupo-cards--detalhe">
          <div class="titulo-secao-cards titulo-com-acao">${titulo}
            <button type="button" class="botao-csv" id="${idCsv}">⬇️ CSV</button>
          </div>
          <div class="wrap-tabela"><table class="tabela-dados">
            <thead><tr><th>#</th><th>${rotuloEntidade}</th><th>Valor em Atraso</th><th>Lançamentos</th><th>Vencido há mais tempo</th></tr></thead>
            <tbody>${lista
              .map(
                (c, i) => `<tr>
                  <td>${i + 1}</td>
                  <td><span class="link-tabela-inline" data-tabela="FCFO" data-where="CODCFO = '${escHtml(c.codigo)}'">${escHtml(c.nome)}</span></td>
                  <td>${formatarMoeda(c.valor_atraso)}</td>
                  <td>${c.qtd_lancamentos}</td>
                  <td>${c.dias_atraso_mais_antigo != null ? `${c.dias_atraso_mais_antigo} dias` : "-"}</td>
                </tr>`
              )
              .join("")}</tbody>
          </table></div>
        </div>`
      : "";

  const rankingAtrasoHtml = rankingAtrasoTabelaHtml(d.ranking_clientes_atraso, "Top 10 Clientes com Maior Valor em Atraso", "csv-ranking-atraso", "Cliente");
  const rankingAtrasoFornecedoresHtml = rankingAtrasoTabelaHtml(d.ranking_fornecedores_atraso, "Top 10 Fornecedores com Maior Valor em Atraso", "csv-ranking-atraso-fornecedores", "Fornecedor");

  painel.innerHTML = `
    <div class="detalhe-cabecalho"><h2>Análise Financeira</h2></div>
    <div class="grupo-cards">
      <div class="titulo-secao-cards">📅 Posição em</div>
      <div class="botoes-periodo">
        <button type="button" class="botao-periodo" data-preset-data="hoje">Hoje</button>
        <button type="button" class="botao-periodo" data-preset-data="ontem">Ontem</button>
      </div>
      <div class="filtro-periodo">
        <label>Data <input type="date" id="analise-data-ref" value="${dataRefAtual || hojeIso}"></label>
        <button type="button" id="btn-atualizar-analise" class="botao-secundario">🔄 Atualizar</button>
        <span id="analise-atualizado-em" class="detalhe-meta-atualizado"></span>
        ${dataRefAtual ? `<span class="detalhe-meta">Mostrando a posição em <strong>${escHtml(dataRefAtual)}</strong>.</span>` : ""}
      </div>
    </div>

    <div class="grupo-cards">
      <div class="titulo-secao-cards">Hoje &amp; Em Atraso no Mês</div>
      <div class="cartoes-overview cartoes-overview--valores">
        ${cartaoHtml(formatarMoeda(d.hoje.receber), "A Receber Hoje", { tabela: t, where: `PAGREC = 'R' AND STATUSLAN = 'A' AND DATAVENCIMENTO = '${hojeIso}'`, cor: "sucesso" })}
        ${cartaoHtml(formatarMoeda(d.hoje.pagar), "A Pagar Hoje", { tabela: t, where: `PAGREC = 'P' AND STATUSLAN = 'A' AND DATAVENCIMENTO = '${hojeIso}'`, cor: "negativo" })}
        ${cartaoHtml(formatarMoeda(d.atraso_mes.receber), "Receber em Atraso (mês)", { tabela: t, where: `PAGREC = 'R' AND STATUSLAN = 'A' AND DATAVENCIMENTO < '${hojeIso}' AND DATAVENCIMENTO >= '${inicioMes}'`, cor: "amarelo" })}
        ${cartaoHtml(formatarMoeda(d.atraso_mes.pagar), "Pagar em Atraso (mês)", { tabela: t, where: `PAGREC = 'P' AND STATUSLAN = 'A' AND DATAVENCIMENTO < '${hojeIso}' AND DATAVENCIMENTO >= '${inicioMes}'`, cor: "ciano" })}
      </div>
    </div>

    ${caixasHtml}

    <div class="grupo-cards grupo-cards--detalhe">
      <div class="titulo-secao-cards titulo-com-acao">Posição dos Lançamentos ("Aging List")
        <button type="button" class="botao-csv" id="csv-aging">⬇️ CSV</button>
      </div>
      <div class="wrap-tabela"><table class="tabela-dados">
        <thead><tr><th>Período</th><th>Receber Vencidos</th><th>Pagar Vencidos</th><th>Receber A Vencer</th><th>Pagar A Vencer</th></tr></thead>
        <tbody>${linhasAging}</tbody>
      </table></div>
    </div>

    ${rankingAtrasoHtml}
    ${rankingAtrasoFornecedoresHtml}
  `;
  ativarCliqueCards(painel);

  $("#btn-atualizar-analise").addEventListener("click", () => {
    abrirAnaliseFinanceira($("#analise-data-ref").value);
  });
  $$(".botao-periodo[data-preset-data]", painel).forEach((btn) => {
    btn.addEventListener("click", () => {
      const [dataEscolhida] = btn.dataset.presetData === "ontem" ? calcularPresetPeriodo("ontem") : calcularPresetPeriodo("hoje");
      abrirAnaliseFinanceira(dataEscolhida);
    });
  });
  $("#csv-caixas")?.addEventListener("click", () =>
    exportarCsv(
      "saldo_por_caixa.csv",
      ["Código", "Caixa", "Saldo Inicial", "Saldo Atual", "Status"],
      d.caixas.map((c) => [c.codigo, c.descricao, c.saldo_inicial, c.saldo_atual, c.inativo ? "inativo" : "ativo"])
    )
  );
  $("#csv-aging")?.addEventListener("click", () =>
    exportarCsv(
      "aging_list.csv",
      ["Período", "Receber Vencidos", "Pagar Vencidos", "Receber A Vencer", "Pagar A Vencer"],
      Object.keys(ROTULOS_FAIXA_AGING).map((chave) => {
        const f = d.aging[chave];
        return [ROTULOS_FAIXA_AGING[chave], f.receber_vencidos, f.pagar_vencidos, f.receber_vencer, f.pagar_vencer];
      })
    )
  );
  $("#csv-ranking-atraso")?.addEventListener("click", () =>
    exportarCsv(
      "ranking_clientes_atraso.csv",
      ["Cliente", "Valor em Atraso", "Lançamentos", "Vencido há mais tempo (dias)"],
      d.ranking_clientes_atraso.map((c) => [c.nome, c.valor_atraso, c.qtd_lancamentos, c.dias_atraso_mais_antigo ?? ""])
    )
  );
  $("#csv-ranking-atraso-fornecedores")?.addEventListener("click", () =>
    exportarCsv(
      "ranking_fornecedores_atraso.csv",
      ["Fornecedor", "Valor em Atraso", "Lançamentos", "Vencido há mais tempo (dias)"],
      d.ranking_fornecedores_atraso.map((c) => [c.nome, c.valor_atraso, c.qtd_lancamentos, c.dias_atraso_mais_antigo ?? ""])
    )
  );

  marcarAtualizado("#analise-atualizado-em");
}

// --------------------------------------------------------------------
// Análise de Estoque (saldo, mínimo/máximo, custo/valor, rankings)
// --------------------------------------------------------------------

function formatarNumero(valor) {
  return (valor ?? 0).toLocaleString("pt-BR", { maximumFractionDigits: 2 });
}

function barraRankingHtml(rotulo, valor, valorMax) {
  const pct = valorMax > 0 ? Math.max(2, (valor / valorMax) * 100) : 0;
  return `
    <div class="linha-ranking">
      <span class="linha-ranking-rotulo" title="${escHtml(rotulo)}">${escHtml(rotulo)}</span>
      <div class="linha-ranking-barra-fundo"><div class="linha-ranking-barra" style="width:${pct}%"></div></div>
      <span class="linha-ranking-valor">${formatarMoeda(valor)}</span>
    </div>`;
}

let estoqueLimiteAtual = 10;
let estoqueDiasParadoAtual = 90;

async function abrirAnaliseEstoque(limite, diasParado) {
  if (limite) estoqueLimiteAtual = limite;
  if (diasParado) estoqueDiasParadoAtual = diasParado;
  painel.innerHTML = `<p class="dica">Carregando análise de estoque...</p>`;
  try {
    const url = `/api/analise-estoque?limite=${estoqueLimiteAtual}&dias_parado=${estoqueDiasParadoAtual}`;
    const d = await apiFetch(url);
    renderAnaliseEstoque(d);
  } catch (err) {
    painel.innerHTML = `<div class="alerta-erro">${escHtml(err.message)}</div>`;
  }
}

function renderAnaliseEstoque(d) {
  if (!d || !d.resumo) {
    painel.innerHTML = `
      <div class="detalhe-cabecalho"><h2>Análise de Estoque</h2></div>
      <p class="vazio">Este banco não tem a tabela TPRODUTO — a análise de estoque não está disponível.</p>
    `;
    return;
  }

  const r = d.resumo;
  const t = "TPRODUTO";
  const limite = d.limite_atual || estoqueLimiteAtual;

  const maxGrupo = d.por_grupo?.length ? Math.max(...d.por_grupo.map((i) => i.custo)) : 0;
  const maxFab = d.por_fabricante?.length ? Math.max(...d.por_fabricante.map((i) => i.custo)) : 0;

  const grupoHtml = d.por_grupo?.length
    ? `<div class="grupo-cards grupo-cards--detalhe">
        <div class="titulo-secao-cards titulo-com-acao">Custo do Estoque por Grupo (${limite} principais)
          <button type="button" class="botao-csv" id="csv-por-grupo">⬇️ CSV</button>
        </div>
        ${d.por_grupo.map((i) => barraRankingHtml(i.nome, i.custo, maxGrupo)).join("")}
      </div>`
    : "";

  const fabricanteHtml = d.por_fabricante?.length
    ? `<div class="grupo-cards grupo-cards--detalhe">
        <div class="titulo-secao-cards titulo-com-acao">Custo do Estoque por Fabricante (${limite} principais)
          <button type="button" class="botao-csv" id="csv-por-fabricante">⬇️ CSV</button>
        </div>
        ${d.por_fabricante.map((i) => barraRankingHtml(i.nome, i.custo, maxFab)).join("")}
      </div>`
    : "";

  const s = d.saldo_status;
  const saldoStatusHtml = s
    ? `<div class="grupo-cards">
        <div class="titulo-secao-cards">Saldo Físico dos Produtos</div>
        <div class="cartoes-overview">
          ${cartaoHtml(s.negativo, "Saldo Negativo", { tabela: t, where: "TIPO = 'P' AND SALDOGERALFISICO < 0", cor: "negativo" })}
          ${cartaoHtml(s.zero, "Saldo Zerado", { tabela: t, where: "TIPO = 'P' AND SALDOGERALFISICO = 0", cor: "amarelo" })}
          ${cartaoHtml(s.positivo, "Saldo Positivo", { tabela: t, where: "TIPO = 'P' AND SALDOGERALFISICO > 0", cor: "sucesso" })}
        </div>
        ${s.negativo > 0 ? `<p class="dica" style="margin-top:10px">⚠️ Saldo negativo geralmente indica venda registrada sem entrada de estoque correspondente — vale conferir.</p>` : ""}
      </div>`
    : "";

  const topProdutosHtml = d.top_produtos?.length
    ? `<div class="grupo-cards grupo-cards--detalhe">
        <div class="titulo-secao-cards titulo-com-acao">Top ${limite} Produtos por Valor em Estoque (custo)
          <button type="button" class="botao-csv" id="csv-top-produtos">⬇️ CSV</button>
        </div>
        <div class="wrap-tabela"><table class="tabela-dados">
          <thead><tr><th>Código</th><th>Produto</th><th>Saldo</th><th>Custo Unit.</th><th>Custo Total</th></tr></thead>
          <tbody>${d.top_produtos
            .map(
              (p) => `<tr>
                <td>${escHtml(p.codigo)}</td>
                <td><span class="link-tabela-inline" data-tabela="${t}" data-where="TIPO = 'P' AND CODPRD = '${escHtml(p.codigo)}'">${escHtml(p.nome)}</span></td>
                <td>${formatarNumero(p.saldo)}</td>
                <td>${formatarMoeda(p.custo_unitario)}</td>
                <td>${formatarMoeda(p.custo_total)}</td>
              </tr>`
            )
            .join("")}</tbody>
        </table></div>
      </div>`
    : "";

  const pp = d.produtos_parados;
  const paradosHtml = pp
    ? `<div class="grupo-cards">
        <div class="titulo-secao-cards">Produtos Parados (sem giro)</div>
        <div class="cartoes-overview cartoes-overview--compacto">
          ${cartaoHtml(pp.qtd_sem_giro, `Sem giro há +${pp.dias_limite} dias`, { tabela: t, where: `TIPO = 'P' AND SALDOGERALFISICO > 0 AND NOT EXISTS (SELECT 1 FROM TMOVITENS M WHERE M.CODPRD = TPRODUTO.CODPRD AND M.DATAEMISSAO >= '${dataMenosdias(pp.dias_limite)}')`, cor: "negativo", favoritavel: false })}
        </div>
        ${
          pp.lista?.length
            ? `<div class="grupo-cards--detalhe" style="margin-top:14px">
                <div class="titulo-secao-cards titulo-com-acao">Os ${limite} produtos com saldo há mais tempo sem movimentação
                  <button type="button" class="botao-csv" id="csv-produtos-parados">⬇️ CSV</button>
                </div>
                <div class="wrap-tabela"><table class="tabela-dados">
                  <thead><tr><th>Código</th><th>Produto</th><th>Saldo</th><th>Custo Unit.</th><th>Última Movimentação</th><th>Dias Parado</th></tr></thead>
                  <tbody>${pp.lista
                    .map(
                      (p) => `<tr>
                        <td>${escHtml(p.codigo)}</td>
                        <td><span class="link-tabela-inline" data-tabela="${t}" data-where="TIPO = 'P' AND CODPRD = '${escHtml(p.codigo)}'">${escHtml(p.nome)}</span></td>
                        <td>${formatarNumero(p.saldo)}</td>
                        <td>${formatarMoeda(p.custo_unitario)}</td>
                        <td>${p.ultima_movimentacao ? escHtml(p.ultima_movimentacao.split("T")[0]) : "<em>nunca</em>"}</td>
                        <td>${p.dias_parado ?? "-"}</td>
                      </tr>`
                    )
                    .join("")}</tbody>
                </table></div>
              </div>`
            : ""
        }
      </div>`
    : "";

  const margemPotencial = r.valor_estoque - r.custo_estoque;

  painel.innerHTML = `
    <div class="detalhe-cabecalho">
      <h2>Análise de Estoque</h2>
      <div class="cabecalho-acoes">
        <span id="estoque-atualizado-em" class="detalhe-meta-atualizado"></span>
        <button type="button" id="btn-atualizar-estoque" class="botao-secundario">🔄 Atualizar</button>
      </div>
    </div>
    <div class="grupo-cards">
      <div class="titulo-secao-cards">📊 Mostrar</div>
      <div class="filtro-periodo">
        <label>Top <select id="estoque-limite">
          ${[10, 20, 50, 100].map((n) => `<option value="${n}" ${n === estoqueLimiteAtual ? "selected" : ""}>${n}</option>`).join("")}
        </select></label>
        <label>Dias sem giro <select id="estoque-dias-parado">
          ${[30, 60, 90, 180].map((n) => `<option value="${n}" ${n === estoqueDiasParadoAtual ? "selected" : ""}>${n}</option>`).join("")}
        </select></label>
      </div>
    </div>

    <div class="grupo-cards">
      <div class="titulo-secao-cards">Resumo</div>
      <div class="cartoes-overview cartoes-overview--valores">
        ${cartaoHtml(formatarNumero(r.saldo_total), "Saldo no Estoque", { cor: "info", favoritavel: false })}
        ${cartaoHtml(r.abaixo_minimo, "Abaixo do Mínimo", { tabela: t, where: "TIPO = 'P' AND SALDOGERALFISICO < ESTOQUEMINIMO", cor: "negativo" })}
        ${cartaoHtml(r.acima_maximo, "Acima do Máximo", { tabela: t, where: "TIPO = 'P' AND ESTOQUEMAXIMO > 0 AND SALDOGERALFISICO > ESTOQUEMAXIMO", cor: "amarelo" })}
        ${cartaoHtml(formatarMoeda(r.custo_estoque), "Custo do Estoque", { cor: "roxo", favoritavel: false })}
        ${cartaoHtml(formatarMoeda(r.valor_estoque), "Valor do Estoque", { cor: "sucesso", favoritavel: false })}
        ${cartaoHtml(formatarMoeda(margemPotencial), "Margem Potencial", { cor: "ciano", favoritavel: false })}
      </div>
    </div>

    ${saldoStatusHtml}
    ${paradosHtml}
    ${topProdutosHtml}
    ${grupoHtml}
    ${fabricanteHtml}

    <p class="dica">
      "Compras x Vendas", "Ranking de Fornecedores" e "Pedidos de Compra pendentes" ficaram de fora:
      não encontrei no banco uma tabela que classifique os movimentos como compra/venda com segurança,
      nem uma tabela de pedidos de compra — prefiro não mostrar números adivinhados.
    </p>
  `;
  ativarCliqueCards(painel);

  $("#btn-atualizar-estoque").addEventListener("click", () => abrirAnaliseEstoque());
  $("#estoque-limite").addEventListener("change", (e) => abrirAnaliseEstoque(Number(e.target.value)));
  $("#estoque-dias-parado").addEventListener("change", (e) => abrirAnaliseEstoque(null, Number(e.target.value)));

  $("#csv-top-produtos")?.addEventListener("click", () =>
    exportarCsv(
      "top_produtos_estoque.csv",
      ["Código", "Produto", "Saldo", "Custo Unitário", "Custo Total"],
      d.top_produtos.map((p) => [p.codigo, p.nome, p.saldo, p.custo_unitario, p.custo_total])
    )
  );
  $("#csv-por-grupo")?.addEventListener("click", () =>
    exportarCsv("custo_estoque_por_grupo.csv", ["Grupo", "Custo"], d.por_grupo.map((i) => [i.nome, i.custo]))
  );
  $("#csv-por-fabricante")?.addEventListener("click", () =>
    exportarCsv("custo_estoque_por_fabricante.csv", ["Fabricante", "Custo"], d.por_fabricante.map((i) => [i.nome, i.custo]))
  );
  $("#csv-produtos-parados")?.addEventListener("click", () =>
    exportarCsv(
      "produtos_parados.csv",
      ["Código", "Produto", "Saldo", "Custo Unitário", "Última Movimentação", "Dias Parado"],
      pp.lista.map((p) => [p.codigo, p.nome, p.saldo, p.custo_unitario, p.ultima_movimentacao || "nunca", p.dias_parado ?? ""])
    )
  );

  marcarAtualizado("#estoque-atualizado-em");
}

/** Data ISO (YYYY-MM-DD) de N dias atrás — usada no filtro do card "sem giro". */
function dataMenosdias(dias) {
  const d = new Date();
  d.setDate(d.getDate() - dias);
  return d.toISOString().split("T")[0];
}

async function abrirMudancasSchema() {
  painel.innerHTML = `<p class="dica">Verificando mudanças na estrutura...</p>`;
  try {
    const res = await apiFetch("/api/schema/diff");
    renderMudancasSchema(res);
  } catch (err) {
    painel.innerHTML = `<div class="alerta-erro">${escHtml(err.message)}</div>`;
  }
}

function renderMudancasSchema(res) {
  if (!res.tem_referencia) {
    painel.innerHTML = `
      <div class="detalhe-cabecalho"><h2>Mudanças na Estrutura</h2></div>
      <p class="dica">
        Ainda não existe uma referência salva para este banco. Clique no botão
        abaixo para salvar a estrutura atual — da próxima vez que o sistema
        for atualizado (novas tabelas, campos, etc.), volte aqui para ver
        exatamente o que mudou.
      </p>
      <button type="button" id="btn-salvar-referencia" class="botao-primario" style="width:auto; padding:9px 20px;">
        💾 Salvar situação atual como referência
      </button>
    `;
    $("#btn-salvar-referencia").addEventListener("click", salvarReferenciaSchema);
    return;
  }

  const d = res.diff;
  const dataRef = new Date(res.capturado_em).toLocaleString("pt-BR");
  let corpo = "";

  if (!res.tem_mudancas) {
    corpo = `<p class="alerta-sucesso">✅ Nenhuma mudança na estrutura desde a última referência.</p>`;
  } else {
    if (d.tabelas_adicionadas.length) corpo += secaoListaSimples("🆕 Tabelas novas", d.tabelas_adicionadas, true);
    if (d.tabelas_removidas.length) corpo += secaoListaSimples("🗑️ Tabelas removidas", d.tabelas_removidas, false);

    corpo += secaoCamposPorTabela("🆕 Campos novos", d.campos_adicionados, true);
    corpo += secaoCamposPorTabela("🗑️ Campos removidos", d.campos_removidos, false);

    const tabelasAlteradas = Object.keys(d.campos_alterados);
    if (tabelasAlteradas.length) {
      corpo += `<div class="secao"><h3>✏️ Campos alterados</h3>${tabelasAlteradas
        .map(
          (t) => `
        <p class="detalhe-meta" style="margin-top:12px"><strong>${escHtml(t)}</strong></p>
        <div class="wrap-tabela"><table class="tabela-dados">
          <thead><tr><th>Campo</th><th>Antes</th><th>Depois</th></tr></thead>
          <tbody>${d.campos_alterados[t]
            .map(
              (c) => `<tr>
                <td>${escHtml(c.campo)}</td>
                <td>${escHtml(c.de.tipo)}${c.de.nullable ? "" : " NOT NULL"}</td>
                <td>${escHtml(c.para.tipo)}${c.para.nullable ? "" : " NOT NULL"}</td>
              </tr>`
            )
            .join("")}</tbody>
        </table></div>`
        )
        .join("")}</div>`;
    }

    const rotulos = { procedures: "Procedures", triggers: "Triggers", generators: "Generators" };
    for (const chave of ["procedures", "triggers", "generators"]) {
      if (d[chave].adicionados.length) corpo += secaoListaSimples(`🆕 ${rotulos[chave]} novas`, d[chave].adicionados, true);
      if (d[chave].removidos.length) corpo += secaoListaSimples(`🗑️ ${rotulos[chave]} removidas`, d[chave].removidos, false);
    }
  }

  painel.innerHTML = `
    <div class="detalhe-cabecalho"><h2>Mudanças na Estrutura</h2></div>
    <p class="detalhe-meta">Referência salva em: <strong>${escHtml(dataRef)}</strong></p>
    <div class="linha-botoes" style="max-width: 420px; margin-bottom: 6px;">
      <button type="button" id="btn-verificar-agora" class="botao-secundario">🔄 Verificar agora</button>
      <button type="button" id="btn-salvar-referencia" class="botao-primario">💾 Atualizar referência</button>
    </div>
    <p class="detalhe-meta"><span id="mudancas-atualizado-em" class="detalhe-meta-atualizado"></span></p>
    ${corpo}
  `;
  $("#btn-verificar-agora").addEventListener("click", abrirMudancasSchema);
  $("#btn-salvar-referencia").addEventListener("click", salvarReferenciaSchema);
  $$(".link-tabela", painel).forEach((el) => {
    el.addEventListener("click", () => selecionarAba("tabelas").then(() => abrirTabela(el.dataset.tabela)));
  });
  marcarAtualizado("#mudancas-atualizado-em");
}

function secaoListaSimples(titulo, itens, positivo) {
  const classe = positivo ? "chip-sim" : "chip-nao";
  return `<div class="secao"><h3>${titulo} (${itens.length})</h3>
    <p class="detalhe-meta">${itens.map((i) => `<span class="${classe}">${escHtml(i)}</span>`).join(" &nbsp;•&nbsp; ")}</p>
  </div>`;
}

function secaoCamposPorTabela(titulo, porTabela, positivo) {
  const tabelas = Object.keys(porTabela);
  if (!tabelas.length) return "";
  const classe = positivo ? "chip-sim" : "chip-nao";
  return `<div class="secao"><h3>${titulo}</h3>${tabelas
    .map(
      (t) => `<p class="detalhe-meta">
        <span class="link-tabela" data-tabela="${escHtml(t)}">${escHtml(t)}</span>:
        ${porTabela[t].map((c) => `<span class="${classe}">${escHtml(c)}</span>`).join(" &nbsp;•&nbsp; ")}
      </p>`
    )
    .join("")}</div>`;
}

async function salvarReferenciaSchema() {
  const btn = $("#btn-salvar-referencia");
  if (btn) {
    btn.disabled = true;
    btn.textContent = "Salvando...";
  }
  try {
    await apiFetch("/api/schema/snapshot", { method: "POST" });
    await abrirMudancasSchema();
  } catch (err) {
    if (btn) btn.disabled = false;
    painel.insertAdjacentHTML("afterbegin", `<div class="alerta-erro">${escHtml(err.message)}</div>`);
  }
}

// --------------------------------------------------------------------
// Console SQL (somente leitura)
// --------------------------------------------------------------------

function abrirConsoleSql() {
  painel.innerHTML = `
    <div class="detalhe-cabecalho"><h2>Console de consultas (somente leitura)</h2></div>
    <p class="dica">Apenas comandos <strong>SELECT</strong> (ou <strong>WITH ... SELECT</strong>) são permitidos.</p>
    <div class="console-sql">
      <textarea id="sql-input" placeholder="SELECT FIRST 100 * FROM SUA_TABELA"></textarea>
      <div class="console-sql-acoes">
        <button id="btn-executar-sql" class="botao-primario">Executar</button>
        <button id="btn-salvar-sql" type="button" class="botao-secundario">💾 Salvar consulta</button>
        <span id="sql-status"></span>
      </div>
      <div id="sql-salvas"></div>
      <div id="sql-resultado"></div>
    </div>
  `;

  $("#btn-executar-sql").addEventListener("click", executarSql);
  $("#sql-input").addEventListener("keydown", (ev) => {
    if (ev.ctrlKey && ev.key === "Enter") executarSql();
  });
  $("#btn-salvar-sql").addEventListener("click", salvarConsultaAtual);
  renderizarConsultasSalvas();
}

// --------------------------------------------------------------------
// Consultas salvas do Console SQL (localStorage, só neste navegador)
// --------------------------------------------------------------------

const CHAVE_SQL_SALVAS = "inspetor_sql_salvas";

function obterConsultasSalvas() {
  try {
    return JSON.parse(localStorage.getItem(CHAVE_SQL_SALVAS) || "[]");
  } catch {
    return [];
  }
}

function gravarConsultasSalvas(lista) {
  try {
    localStorage.setItem(CHAVE_SQL_SALVAS, JSON.stringify(lista));
  } catch {
    /* localStorage indisponível — só não persiste entre sessões */
  }
}

function salvarConsultaAtual() {
  const input = $("#sql-input");
  const sql = input.value.trim();
  if (!sql) return;
  const sugestao = sql.replace(/\s+/g, " ").slice(0, 50);
  const nome = prompt("Nome para esta consulta:", sugestao);
  if (!nome || !nome.trim()) return;
  const lista = obterConsultasSalvas();
  lista.push({ nome: nome.trim(), sql });
  gravarConsultasSalvas(lista.slice(-30)); // mantém só as 30 mais recentes
  renderizarConsultasSalvas();
}

function renderizarConsultasSalvas() {
  const el = $("#sql-salvas");
  if (!el) return;
  const lista = obterConsultasSalvas();
  if (!lista.length) {
    el.innerHTML = "";
    return;
  }
  el.innerHTML = `
    <div class="sql-salvas-titulo">💾 Consultas salvas</div>
    <div class="sql-salvas-lista">
      ${lista
        .map(
          (q, i) => `<div class="sql-salva-chip" data-i="${i}" title="${escHtml(q.sql)}">
            <span class="sql-salva-nome">${escHtml(q.nome)}</span>
            <button type="button" class="sql-salva-remover" data-i="${i}" title="Remover esta consulta salva">×</button>
          </div>`
        )
        .join("")}
    </div>`;

  $$(".sql-salva-chip", el).forEach((chip) => {
    chip.addEventListener("click", () => {
      const i = Number(chip.dataset.i);
      $("#sql-input").value = obterConsultasSalvas()[i]?.sql || "";
    });
  });
  $$(".sql-salva-remover", el).forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.stopPropagation();
      const i = Number(btn.dataset.i);
      const lista = obterConsultasSalvas();
      lista.splice(i, 1);
      gravarConsultasSalvas(lista);
      renderizarConsultasSalvas();
    });
  });
}

async function executarSql() {
  const sql = $("#sql-input").value.trim();
  const statusEl = $("#sql-status");
  const resultadoEl = $("#sql-resultado");
  if (!sql) return;

  statusEl.innerHTML = `<span class="spinner"></span> Executando...`;
  resultadoEl.innerHTML = "";

  try {
    const inicio = performance.now();
    const res = await apiFetch("/api/query", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ sql, limite: 500 }),
    });
    const tempo = ((performance.now() - inicio) / 1000).toFixed(2);
    statusEl.textContent = `${res.linhas.length} linha(s) em ${tempo}s${res.truncado ? " (resultado truncado em 500 linhas)" : ""}`;

    renderizarGradeComFiltro(resultadoEl, res.colunas, res.linhas, sql);
  } catch (err) {
    statusEl.textContent = "";
    resultadoEl.innerHTML = `<div class="alerta-erro">${escHtml(err.message)}</div>`;
  }
}

// --------------------------------------------------------------------
// Grade de resultados reutilizável: campo de busca (filtra as linhas já
// carregadas, sem nova consulta) + botão para expandir a tabela numa
// visão maior. Usada tanto na seção "Dados" da tabela quanto no Console SQL.
// --------------------------------------------------------------------

function montarTabelaHtml(colunas, linhas) {
  const thead = colunas.map((c) => `<th>${escHtml(c)}</th>`).join("");
  const tbody = linhas
    .map(
      (linha) =>
        `<tr>${linha.map((v) => `<td>${v === null ? "<em>NULL</em>" : escHtml(String(v))}</td>`).join("")}</tr>`
    )
    .join("");
  return `<table class="tabela-dados"><thead><tr>${thead}</tr></thead><tbody>${tbody}</tbody></table>`;
}

/** Renderiza colunas/linhas dentro de `containerEl` com busca e botão de expandir. */
function renderizarGradeComFiltro(containerEl, colunas, linhas, tituloExpandido) {
  if (!containerEl) return;
  if (!colunas.length) {
    containerEl.innerHTML = `<p class="vazio">Nenhuma coluna retornada.</p>`;
    return;
  }

  containerEl.innerHTML = `
    <div class="grade-dados-barra">
      <input type="search" class="grade-dados-busca" placeholder="🔍 Filtrar nos resultados...">
      <span class="grade-dados-contagem">${linhas.length} linha(s)</span>
      <button type="button" class="grade-dados-expandir botao-secundario" title="Expandir para uma visão maior">⛶ Expandir</button>
    </div>
    <div class="wrap-tabela grade-dados-wrap">${montarTabelaHtml(colunas, linhas)}</div>
  `;

  const inputBusca = $(".grade-dados-busca", containerEl);
  const contagemEl = $(".grade-dados-contagem", containerEl);
  const btnExpandir = $(".grade-dados-expandir", containerEl);
  const tabela = $("table.tabela-dados", containerEl);

  inputBusca.addEventListener("input", () => filtrarLinhasTabela(tabela, inputBusca.value, contagemEl, linhas.length));
  btnExpandir.addEventListener("click", () => abrirModalExpandido(tituloExpandido, colunas, linhas, inputBusca.value));
}

/** Esconde as linhas de `tabela` que não contêm `termo` em nenhuma coluna. */
function filtrarLinhasTabela(tabela, termo, contagemEl, totalOriginal) {
  const termoNorm = termo.trim().toLowerCase();
  let visiveis = 0;
  $$("tbody tr", tabela).forEach((tr) => {
    const mostra = !termoNorm || tr.textContent.toLowerCase().includes(termoNorm);
    tr.style.display = mostra ? "" : "none";
    if (mostra) visiveis++;
  });
  if (contagemEl) {
    contagemEl.textContent = termoNorm ? `${visiveis} de ${totalOriginal} linha(s)` : `${totalOriginal} linha(s)`;
  }
}

// --------------------------------------------------------------------
// Modal "Expandir" — mesma grade de resultados numa visão bem maior
// --------------------------------------------------------------------

const modalExpandir = $("#modal-expandir");

function abrirModalExpandido(titulo, colunas, linhas, termoInicial) {
  if (!modalExpandir) return;
  $("#modal-expandir-titulo").textContent = titulo || "Dados";
  $("#modal-expandir-tabela-wrap").innerHTML = montarTabelaHtml(colunas, linhas);

  const inputBusca = $("#modal-expandir-busca");
  const contagemEl = $("#modal-expandir-contagem");
  const tabela = $("table.tabela-dados", $("#modal-expandir-tabela-wrap"));

  inputBusca.value = termoInicial || "";
  filtrarLinhasTabela(tabela, inputBusca.value, contagemEl, linhas.length);
  inputBusca.oninput = () => filtrarLinhasTabela(tabela, inputBusca.value, contagemEl, linhas.length);

  modalExpandir.hidden = false;
  inputBusca.focus();
}

if (modalExpandir) {
  $("#btn-fechar-modal-expandir").addEventListener("click", () => { modalExpandir.hidden = true; });
  modalExpandir.addEventListener("click", (ev) => {
    if (ev.target === modalExpandir) modalExpandir.hidden = true;
  });
  document.addEventListener("keydown", (ev) => {
    if (ev.key === "Escape" && !modalExpandir.hidden) modalExpandir.hidden = true;
  });
}

// --------------------------------------------------------------------
// Utilidades
// --------------------------------------------------------------------

function escHtml(str) {
  return String(str).replace(/[&<>"']/g, (c) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
  }[c]));
}

preencherFormularioComUltimaConexao();
verificarStatusInicial();
inicializarModoSomenteNumeros();
