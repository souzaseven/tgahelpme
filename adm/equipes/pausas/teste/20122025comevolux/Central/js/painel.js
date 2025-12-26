// ===================================================
// CONTROLE DE VERSÃO DO ESTADO
// Sempre incremente ao alterar módulos
// ===================================================
const VERSAO_ESTADO = 3;
const STORAGE_KEY = "modulos_evolux";

// ===================================================
// ESTADO PADRÃO DO PAINEL (FONTE DA VERDADE)
// - ativo: true  => módulo implementado
// - ativo: false => módulo futuro / planejado
// ===================================================
const estadoPadrao = [

  {
    id: "chamadas",
    nome: "Chamadas",
    descricao: "Controle e acompanhamento das chamadas",
    objetivo: "Garantir rastreabilidade das interações telefônicas.",
    ativo: false,
    link: "/evolux/chamadas",
    doc: "/docs/chamadas.html"
  },

  {
    id: "central",
    nome: "Central de Atendimento",
    descricao: "Visão geral do contact center",
    objetivo: "Oferecer um panorama rápido da operação.",
    ativo: false,
    link: "/evolux/central",
    doc: "/docs/central.html"
  },

  {
    id: "cdr",
    nome: "CDR",
    descricao: "Registros detalhados de chamadas",
    objetivo: "Base histórica para auditoria e relatórios.",
    ativo: false,
    link: "/evolux/cdr",
    doc: "/docs/cdr.html"
  },

  {
    id: "discador",
    nome: "Discador",
    descricao: "Discagem automática e campanhas",
    objetivo: "Aumentar produtividade em contatos ativos.",
    ativo: false,
    link: "/evolux/discador",
    doc: "/docs/discador.html"
  },

  {
    id: "plano-recursos",
    nome: "Plano de Recursos",
    descricao: "Configuração de recursos do sistema",
    objetivo: "Equilibrar uso da infraestrutura disponível.",
    ativo: false,
    link: "/evolux/recursos",
    doc: "/docs/recursos.html"
  },

  {
    id: "filas",
    nome: "Filas",
    descricao: "Gerenciamento de filas de atendimento",
    objetivo: "Organizar fluxo e reduzir tempo de espera.",
    ativo: true,
    link: "https://tgameajuda.com/telefonia-evolux/filas_agentes/login.php",
    doc: "https://documenter.getpostman.com/view/13244735/Uyr4Kzh5#6816f8f7-873e-4e1b-86d0-e7402bafd192"
  },

  {
    id: "pbx",
    nome: "PBX",
    descricao: "Configurações da central telefônica",
    objetivo: "Manter a base técnica da telefonia estável.",
    ativo: false,
    link: "/evolux/pbx",
    doc: "/docs/pbx.html"
  },

  {
    id: "tempo-real",
    nome: "Tempo Real",
    descricao: "Monitoramento em tempo real",
    objetivo: "Permitir reação imediata a eventos críticos.",
    ativo: false,
    link: "/evolux/tempo-real",
    doc: "/docs/tempo-real.html"
  },

  {
    id: "relatorios",
    nome: "Relatórios",
    descricao: "Relatórios operacionais e gerenciais",
    objetivo: "Apoiar decisões estratégicas com dados.",
    ativo: true,
    link: "../relatorios/index.html",
    doc: "https://documenter.getpostman.com/view/13244735/Uyr4Kzh5#efd49c88-419d-4e6e-9a9d-bd38ecf4920b"
  },

  {
    id: "tarefas",
    nome: "Tarefas",
    descricao: "Gestão de tarefas internas",
    objetivo: "Organizar atividades paralelas à operação.",
    ativo: false,
    link: "/evolux/tarefas",
    doc: "/docs/tarefas.html"
  },

  {
    id: "inquilino",
    nome: "Inquilino",
    descricao: "Gestão de tenants / empresas",
    objetivo: "Permitir operação multiempresa segura.",
    ativo: false,
    link: "/evolux/inquilino",
    doc: "/docs/inquilino.html"
  },

  {
    id: "usuarios",
    nome: "Usuários",
    descricao: "Gerenciamento de usuários do sistema",
    objetivo: "Controlar acessos e permissões.",
    ativo: false,
    link: "/evolux/usuarios",
    doc: "/docs/usuarios.html"
  },

  {
    id: "conversa",
    nome: "Conversa",
    descricao: "Histórico e gestão de conversas",
    objetivo: "Centralizar interações entre canais.",
    ativo: false,
    link: "/evolux/conversa",
    doc: "/docs/conversa.html"
  },

  {
    id: "pausa",
    nome: "Controle de Pausa",
    descricao: "Gerenciamento de usuários em lanche",
    objetivo: "Controlar pausas e lanches.",
    ativo: true,
    link: "https://tgameajuda.com/adm/equipes/pausas/painel/index.php",
    doc: "https://documenter.getpostman.com/view/13244735/Uyr4Kzh5#584fd576-52a6-40fd-a6da-57c55b5e3c2f"
  },

  {
    id: "agente",
    nome: "Agente",
    descricao: "Gestão e status dos agentes",
    objetivo: "Centralizar o controle operacional dos agentes.",
    ativo: true,
    link: "../agente/index.html",
    doc: "https://documenter.getpostman.com/view/13244735/Uyr4Kzh5#6816f8f7-873e-4e1b-86d0-e7402bafd192"
  }
];

// ===================================================
// CARREGAMENTO FORÇADO DO ESTADO (VERSÃO)
// ===================================================
function carregarEstadoAtualizado() {
  const salvo = JSON.parse(localStorage.getItem(STORAGE_KEY));

  if (!salvo || salvo.versao !== VERSAO_ESTADO) {
    localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({ versao: VERSAO_ESTADO, modulos: estadoPadrao })
    );
    return estadoPadrao;
  }

  return salvo.modulos;
}

// ===================================================
// ESTADO EM USO
// ===================================================
let modulos = carregarEstadoAtualizado();

// ===================================================
// RENDERIZAÇÃO (IMPLEMENTADOS x FUTUROS)
// ===================================================
function renderizar() {
  const ativosEl = document.getElementById("lista-ativos");
  const futurosEl = document.getElementById("lista-futuros");

  ativosEl.innerHTML = "";
  futurosEl.innerHTML = "";

  modulos.forEach(mod => {
    const card = document.createElement("div");
    card.className = `card ${mod.ativo ? "ativo" : "inativo"}`;

    card.innerHTML = `
      <h3>${mod.nome}</h3>
      <div class="descricao">${mod.descricao}</div>
      <div class="objetivo"><strong>Objetivo:</strong> ${mod.objetivo}</div>

      <div class="acoes">
        <button onclick="toggleModulo('${mod.id}')">
          ${mod.ativo ? "Desativar" : "Ativar"}
        </button>
        <a href="${mod.link}" target="_blank" class="btn-sec">Acessar</a>
        <a href="${mod.doc}" target="_blank" class="btn-doc">Documentação</a>
      </div>
    `;

    mod.ativo ? ativosEl.appendChild(card) : futurosEl.appendChild(card);
  });

  localStorage.setItem(
    STORAGE_KEY,
    JSON.stringify({ versao: VERSAO_ESTADO, modulos })
  );
}

// ===================================================
// AÇÕES
// ===================================================
function toggleModulo(id) {
  modulos = modulos.map(m =>
    m.id === id ? { ...m, ativo: !m.ativo } : m
  );
  renderizar();
}

// ===================================================
// INIT
// ===================================================
renderizar();
