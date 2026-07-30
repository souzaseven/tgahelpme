'use strict';

/*
 * Versão do build — mantenha igual ao "?v=" usado em index.html para script.js e style.css.
 * Incremente este número (e o dos dois arquivos em index.html) a cada nova publicação,
 * assim o navegador do usuário não fica preso a uma cópia antiga em cache.
 */
const APP_VERSION = '1.1.0';
console.log('TGA ERP — Portal de Conhecimento — versão', APP_VERSION);

/* ==========================================================================
   1. BASE DE CONHECIMENTO (DADOS)
   ========================================================================== */
const KNOWLEDGE_BASE = [
  {
    id: 'introducao',
    name: 'Introdução ao Sistema',
    icon: '🖥️',
    desc: 'Conceitos de ERP, instalação, banco de dados, segurança e estrutura do TGA.',
    topics: [
      {
        title: 'O que é um sistema ERP',
        desc: 'ERP (Enterprise Resource Planning) é um sistema de gestão integrada que centraliza processos de estoque, vendas, financeiro e fiscal em uma única base de dados, evitando retrabalho e divergência de informações entre setores.',
        steps: [
          'Identifique os módulos usados pela empresa (Estoque, Financeiro, Fiscal, etc.).',
          'Entenda que uma informação cadastrada em um módulo reflete automaticamente nos demais.',
          'Compreenda o fluxo básico: cadastro → movimentação → relatório.'
        ],
        tips: ['Sempre pense no ERP como uma "fonte única da verdade": o que está errado no cadastro afeta todos os módulos.'],
        alert: 'Nunca cadastre a mesma informação (cliente, produto) duas vezes só porque não achou o registro — use a pesquisa antes de criar um novo.'
      },
      {
        title: 'Instalação do TGA ERP',
        desc: 'A instalação do TGA é feita através do instalador oficial, que configura o executável, os componentes de comunicação e o acesso ao banco de dados Firebird.',
        steps: [
          'Execute o instalador como administrador na estação ou servidor.',
          'Informe o caminho de instalação e o local onde o banco de dados ficará hospedado.',
          'Configure o IP/nome do servidor de banco nas estações clientes.',
          'Teste a conexão antes de finalizar o assistente.'
        ],
        tips: ['Mantenha sempre a mesma versão do sistema em todas as estações para evitar erros de compatibilidade.'],
        alert: 'Verifique se o antivírus não está bloqueando a pasta de instalação antes de reportar erro de conexão.'
      },
      {
        title: 'Configuração de Base de Dados Firebird — Instalação',
        desc: 'O TGA ERP utiliza o Firebird como SGBD. Ele deve ser instalado no servidor (ou máquina que fará esse papel) antes da configuração do sistema.',
        steps: [
          'Baixe a versão do Firebird homologada pelo TGA (32 ou 64 bits conforme o servidor).',
          'Instale como serviço do Windows (Server Component), não como aplicação.',
          'Defina a senha do usuário SYSDBA durante a instalação.',
          'Restaure ou aponte o arquivo .FDB da empresa dentro da pasta de dados.'
        ],
        tips: ['Sempre anote a senha do SYSDBA em local seguro — ela é exigida em restaurações e manutenções.'],
        alert: 'Instalar o Firebird como "Aplicação" em vez de "Serviço" faz o banco cair sempre que o usuário fizer logoff do Windows.'
      },
      {
        title: 'Firebird — Liberação das Portas',
        desc: 'Para que as estações se conectem ao servidor de banco de dados, a porta padrão do Firebird (3050) precisa estar liberada no firewall do servidor e, se houver, do roteador.',
        steps: [
          'Acesse o Firewall do Windows Defender (ou firewall corporativo) no servidor.',
          'Crie uma regra de entrada liberando a porta TCP 3050.',
          'Se o acesso for externo/remoto, libere também o encaminhamento de porta no roteador.',
          'Teste a conexão de uma estação remota usando o IP do servidor.'
        ],
        tips: ['Use o comando "telnet IP 3050" (ou um testador de porta) para confirmar rapidamente se a porta está acessível.'],
        alert: 'Nunca exponha a porta 3050 diretamente na internet sem VPN — isso é um risco grave de segurança para o banco de dados.'
      },
      {
        title: 'Cadastro de Empresa/Filiais',
        desc: 'Toda operação no TGA parte do cadastro correto da empresa matriz e de suas filiais, incluindo dados fiscais, regime tributário e certificado digital.',
        steps: [
          'Acesse Cadastros > Empresa e preencha razão social, CNPJ, IE e regime tributário.',
          'Configure o certificado digital A1 utilizado para emissão de documentos fiscais.',
          'Cadastre cada filial vinculando-a à matriz e definindo série de numeração própria.',
          'Confirme o endereço fiscal, pois ele é usado no DANFE e demais documentos.'
        ],
        tips: ['Revise o regime tributário (Simples, Lucro Presumido/Real) sempre que houver mudança, pois ele impacta diretamente o cálculo de impostos.'],
        alert: 'Certificado digital vencido impede a emissão de NF-e/NFC-e — monitore a validade com antecedência.'
      },
      {
        title: 'Contrassenha',
        desc: 'A contrassenha do TGA é a validação da situação financeira da empresa junto à TGA (se há débitos em aberto ou não) e/ou se a empresa está ativa na base de dados da TGA para utilização do sistema — não deve ser confundida com uma senha interna de aprovação de operações.',
        steps: [
          'Em caso de bloqueio do sistema, verifique junto ao financeiro se há pendências junto à TGA.',
          'Solicite a contrassenha ao suporte/financeiro da TGA após a regularização da situação.',
          'Informe a contrassenha na tela solicitada pelo sistema para liberar novamente o uso.'
        ],
        tips: ['Mantenha os pagamentos referentes ao sistema em dia para evitar bloqueios inesperados por contrassenha durante o expediente.'],
        alert: 'Sem a regularização da situação financeira/cadastral junto à TGA, uma nova contrassenha não é liberada.'
      },
      {
        title: 'LGPD',
        desc: 'A Lei Geral de Proteção de Dados exige que o tratamento de dados pessoais de clientes e funcionários no sistema siga princípios de finalidade, necessidade e segurança.',
        steps: [
          'Cadastre apenas os dados pessoais realmente necessários para a operação comercial.',
          'Restrinja o acesso a telas com dados sensíveis via perfil de usuário.',
          'Tenha um processo definido para atender solicitações de exclusão/correção de dados do titular.'
        ],
        tips: ['Utilize os perfis de acesso do TGA para limitar quem visualiza dados completos de clientes (CPF, endereço, telefone).'],
        alert: 'Extrair relatórios com dados pessoais para planilhas soltas fora do sistema é um risco de vazamento — evite ao máximo.'
      },
      {
        title: 'Backup',
        desc: 'O backup do banco Firebird garante que a empresa não perca informações em caso de falha de hardware, exclusão indevida ou corrupção de dados.',
        steps: [
          'Configure a rotina automática de backup (diária, preferencialmente fora do horário comercial).',
          'Armazene as cópias em local diferente do servidor (nuvem, HD externo, outro servidor).',
          'Teste periodicamente a restauração do backup para garantir que o arquivo é válido.',
          'Mantenha um histórico de pelo menos 7 a 30 dias de backups.'
        ],
        tips: ['A regra 3-2-1 é uma boa prática: 3 cópias, em 2 mídias diferentes, sendo 1 fora do local físico da empresa.'],
        alert: 'Um backup que nunca foi testado não é um backup confiável — sempre valide a restauração.'
      },
      {
        title: 'Estrutura dos Menus e Atalhos',
        desc: 'O TGA organiza suas funções em menus por módulo (Estoque, Financeiro, Fiscal, Configurador) com atalhos de teclado para agilizar a navegação diária.',
        steps: [
          'Explore a barra de menu superior para localizar os módulos disponíveis.',
          'Use a busca rápida de telas (geralmente por atalho) para abrir uma rotina pelo nome.',
          'Memorize os atalhos mais usados no seu setor (venda, consulta de estoque, lançamento financeiro).'
        ],
        tips: ['Fixe as rotinas mais usadas nos favoritos do sistema, se essa opção estiver disponível.'],
        alert: 'Evite decorar apenas o caminho do mouse — em caso de lentidão de rede, os atalhos de teclado agilizam muito o atendimento.'
      },
      {
        title: 'Acessos — Usuários e Perfis de Acesso',
        desc: 'Cada colaborador deve ter um usuário individual vinculado a um perfil de acesso que define quais telas e ações ele pode executar.',
        steps: [
          'Cadastre o usuário com login individual (nunca usuários genéricos compartilhados).',
          'Vincule o usuário a um perfil de acesso já existente ou crie um novo perfil.',
          'Defina permissões de leitura, inclusão, alteração e exclusão por tela/rotina.',
          'Revise os perfis periodicamente, principalmente após mudanças de função.'
        ],
        tips: ['Aplique o princípio do menor privilégio: o usuário deve ter acesso apenas ao que precisa para sua função.'],
        alert: 'Usuários genéricos (ex.: "caixa1" usado por todos) impedem a rastreabilidade de quem realizou cada operação.'
      },
      {
        title: 'Menu Utilitários',
        desc: 'O menu Utilitários reúne ferramentas de manutenção do sistema, como reindexação de banco, importação/exportação de dados e logs de auditoria.',
        steps: [
          'Utilize as rotinas de manutenção de banco em horários de baixo movimento.',
          'Consulte o log de auditoria para verificar alterações feitas por usuários.',
          'Utilize as ferramentas de importação apenas com arquivo no leiaute esperado pelo sistema.'
        ],
        tips: ['Antes de rodar qualquer rotina de manutenção pesada, garanta que existe um backup recente.'],
        alert: 'Rotinas de reindexação/recompactação do banco não devem ser executadas com usuários conectados ao sistema.'
      }
    ]
  },
  {
    id: 'cadastros',
    name: 'Cadastros Gerais',
    icon: '🗂️',
    desc: 'Funcionários, campos complementares, pesquisas, plano de contas e departamentos.',
    topics: [
      {
        title: 'Funcionários',
        desc: 'O cadastro de funcionários armazena dados pessoais, cargo, departamento e vínculo com o usuário do sistema, sendo base para comissionamento e ordens de serviço.',
        steps: [
          'Acesse Cadastros > Funcionários e informe dados pessoais e admissionais.',
          'Vincule o funcionário a um departamento e, se aplicável, a uma meta de comissão.',
          'Associe o funcionário ao usuário de login correspondente.'
        ],
        tips: ['Mantenha a situação do funcionário (ativo/inativo) sempre atualizada para não distorcer relatórios de comissão.'],
        alert: 'Funcionário desligado com usuário ainda ativo é uma falha de segurança — desative o acesso no mesmo dia do desligamento.'
      },
      {
        title: 'Campos Complementares',
        desc: 'Campos complementares permitem adicionar informações extras (personalizadas) a cadastros como cliente, produto ou fornecedor, sem alterar a estrutura padrão do sistema.',
        steps: [
          'Acesse a configuração de campos complementares do cadastro desejado.',
          'Defina o nome do campo, tipo (texto, número, data, lista) e se é obrigatório.',
          'Publique o campo para que ele passe a aparecer na tela de cadastro correspondente.'
        ],
        tips: ['Use campos complementares para informações específicas do segmento da empresa que não existem por padrão no sistema.'],
        alert: 'Excesso de campos obrigatórios deixa o cadastro mais lento — crie apenas os realmente necessários.'
      },
      {
        title: 'Pesquisas',
        desc: 'As telas de pesquisa do TGA permitem localizar registros por múltiplos filtros (código, nome, documento, período), evitando duplicidade de cadastros.',
        steps: [
          'Abra a rotina de pesquisa correspondente (cliente, produto, documento etc.).',
          'Utilize filtros combinados (ex.: nome parcial + cidade) para refinar o resultado.',
          'Ordene as colunas clicando no cabeçalho para localizar mais rápido.'
        ],
        tips: ['Pesquisar por parte do nome com "%" ou similar (conforme a tela) amplia os resultados quando você não lembra o nome completo.'],
        alert: 'Sempre pesquise antes de cadastrar um novo cliente/produto para não gerar duplicidade na base.'
      },
      {
        title: 'Plano de Contas e Centro de Custos',
        desc: 'O plano de contas classifica as movimentações financeiras (receitas e despesas) e o centro de custo indica em qual área/departamento o gasto ou receita ocorreu.',
        steps: [
          'Estruture o plano de contas em níveis (sintéticas e analíticas).',
          'Cadastre os centros de custo representando setores ou filiais.',
          'Vincule cada lançamento financeiro à conta e ao centro de custo corretos.'
        ],
        tips: ['Um plano de contas bem estruturado é a base para relatórios gerenciais confiáveis (DRE por centro de custo, por exemplo).'],
        alert: 'Lançar tudo em "Diversos" ou "Outros" inviabiliza a análise gerencial futura — evite contas genéricas demais.'
      },
      {
        title: 'Departamentos',
        desc: 'Departamentos organizam a empresa por área (Vendas, Financeiro, Estoque, Oficina) e são usados para segmentar relatórios e permissões.',
        steps: [
          'Cadastre os departamentos existentes na empresa.',
          'Vincule funcionários e, se necessário, produtos/serviços aos departamentos.',
          'Utilize o departamento como filtro em relatórios gerenciais.'
        ],
        tips: ['Alinhe os departamentos do sistema com o organograma real da empresa para facilitar a leitura dos relatórios.'],
        alert: '—'
      },
      {
        title: 'Tipos de Documento',
        desc: 'Os tipos de documento identificam a natureza do arquivo/registro (NF-e, boleto, contrato, recibo) usado em processos internos e de anexação.',
        steps: [
          'Cadastre os tipos de documento utilizados pela empresa.',
          'Associe o tipo de documento à rotina correspondente (financeiro, fiscal, RH).',
          'Utilize os tipos para organizar anexos digitais dentro do sistema.'
        ],
        tips: ['Padronize os nomes dos tipos de documento entre filiais para manter relatórios consolidados coerentes.'],
        alert: '—'
      }
    ]
  },
  {
    id: 'estoque-entrada',
    name: 'Estoque — Movimentação de Entrada',
    icon: '📥',
    desc: 'Cadastro de produtos, fornecedores, pedidos de compra e devoluções.',
    topics: [
      {
        title: 'Cadastro de Produtos (Grupos, Tipos, Fabricantes, Grupos de Tributação)',
        desc: 'O cadastro de produto é o núcleo do módulo de estoque. Antes de cadastrar o produto, é necessário configurar suas classificações auxiliares: grupo, tipo, fabricante e grupo de tributação.',
        steps: [
          'Cadastre primeiro os Grupos (ex.: Peças, Acessórios), Tipos de Produto e Fabricantes.',
          'Cadastre o Grupo de Tributação, definindo os impostos padrão daquele conjunto de produtos.',
          'Cadastre o produto vinculando-o ao grupo, tipo, fabricante e grupo de tributação criados.',
          'Preencha unidade de medida, NCM, CEST e código de barras do produto.'
        ],
        tips: ['Configurar corretamente o Grupo de Tributação evita ter que corrigir impostos produto a produto depois.'],
        alert: 'Produto sem NCM ou com NCM incorreto pode gerar rejeição na emissão da nota fiscal.'
      },
      {
        title: 'Cadastro de Cliente/Fornecedor',
        desc: 'Cadastro único de pessoa física ou jurídica que pode atuar como cliente, fornecedor, ou ambos, contendo dados fiscais, endereço e condições comerciais.',
        steps: [
          'Pesquise o CPF/CNPJ antes de cadastrar para evitar duplicidade.',
          'Preencha dados cadastrais, endereço e informações fiscais (IE, regime tributário).',
          'Marque as flags "Cliente" e/ou "Fornecedor" conforme o relacionamento comercial.',
          'Configure limite de crédito e condição de pagamento padrão, se aplicável.'
        ],
        tips: ['Utilize a busca automática de CNPJ (quando disponível) para reduzir erros de digitação de endereço e razão social.'],
        alert: 'CPF/CNPJ inválido ou divergente do informado na nota fiscal do fornecedor causa rejeição na entrada da NF-e.'
      },
      {
        title: 'Condição de Pagamento',
        desc: 'Define o parcelamento e prazo padrão usado nas negociações de compra e venda (à vista, 30/60/90 dias, entrada + parcelas).',
        steps: [
          'Cadastre a condição informando quantidade de parcelas e intervalo de dias entre elas.',
          'Associe a condição de pagamento ao cliente/fornecedor, se for um acordo fixo.',
          'Selecione a condição correta no momento do pedido de compra/venda.'
        ],
        tips: ['Crie condições padrão (à vista, 30 dias, 30/60/90) para agilizar a digitação de pedidos.'],
        alert: '—'
      },
      {
        title: 'Pedido de Compras',
        desc: 'Registra a intenção de compra de mercadorias junto a um fornecedor antes da chegada física da mercadoria e da nota fiscal.',
        steps: [
          'Abra um novo pedido de compra e selecione o fornecedor.',
          'Inclua os produtos, quantidades e valores negociados.',
          'Defina a condição de pagamento e prazo de entrega.',
          'Confirme o pedido para que fique disponível para o recebimento físico posterior.'
        ],
        tips: ['Use o pedido de compra como controle de previsão de estoque, mesmo antes da nota chegar.'],
        alert: 'Pedidos não confirmados ou vencidos podem distorcer a previsão de reposição de estoque — mantenha-os atualizados.'
      },
      {
        title: 'Manifesto do Destinatário e Importação do XML',
        desc: 'O Manifesto do Destinatário é a confirmação eletrônica perante a SEFAZ de que a empresa tem ciência de uma NF-e emitida contra seu CNPJ, permitindo dar ciência, confirmar, ignorar ou desconhecer a operação.',
        steps: [
          'Acesse a rotina de Manifesto do Destinatário e consulte as notas pendentes.',
          'Confira se a mercadoria/nota realmente pertence à empresa.',
          'Registre a manifestação adequada (Ciência da Operação, Confirmação, Desconhecimento ou Não Realização).',
          'Importe o XML da nota manifestada para gerar a entrada de estoque automaticamente.'
        ],
        tips: ['Fazer a manifestação em dia evita bloqueios futuros de aproveitamento de crédito fiscal.'],
        alert: 'Não manifestar notas dentro do prazo pode gerar complicações fiscais e problemas para o fornecedor emissor.'
      },
      {
        title: 'Emissão de Etiqueta de Produtos',
        desc: 'Geração de etiquetas com código de barras e preço para identificação física dos produtos em loja/estoque.',
        steps: [
          'Selecione o(s) produto(s) ou a nota de entrada que originará as etiquetas.',
          'Escolha o modelo/layout de etiqueta compatível com a impressora utilizada.',
          'Defina a quantidade de etiquetas por produto e envie para impressão.'
        ],
        tips: ['Confira o modelo da impressora (térmica x jato de tinta) antes de configurar o layout, para não distorcer o código de barras.'],
        alert: '—'
      },
      {
        title: 'Devolução de Compras',
        desc: 'Processo de estorno de uma entrada de mercadoria, seja por avaria, erro no pedido ou não conformidade, retornando o produto ao fornecedor.',
        steps: [
          'Localize a nota de entrada original a ser devolvida.',
          'Gere o documento de devolução vinculado à nota original.',
          'Confirme a saída do estoque e, se aplicável, emita a nota fiscal de devolução.'
        ],
        tips: ['Sempre vincule a devolução à nota de origem — isso mantém a rastreabilidade fiscal e de estoque.'],
        alert: 'Devolução sem nota fiscal correspondente pode gerar inconsistência no fechamento fiscal do período.'
      },
      {
        title: 'Ajuste de Saldos',
        desc: 'Rotina usada para corrigir divergências entre o saldo físico (contagem/inventário) e o saldo do sistema, para mais ou para menos.',
        steps: [
          'Realize a contagem física do estoque (inventário).',
          'Compare o saldo físico com o saldo do sistema.',
          'Registre o ajuste (entrada ou saída) informando o motivo da divergência.'
        ],
        tips: ['Documente sempre o motivo do ajuste — isso ajuda a identificar causas recorrentes de perda (furto, avaria, erro de lançamento).'],
        alert: 'Ajustes de saldo devem ser restritos a usuários autorizados, pois alteram o estoque sem movimento fiscal.'
      },
      {
        title: 'Relatório de Compras',
        desc: 'Relatório gerencial que consolida as compras realizadas por período, fornecedor, produto ou categoria.',
        steps: [
          'Acesse o relatório de compras e defina o período de análise.',
          'Aplique filtros por fornecedor, grupo de produto ou centro de custo.',
          'Exporte ou imprima o relatório para análise gerencial.'
        ],
        tips: ['Compare o relatório de compras com o de vendas do mesmo período para avaliar giro de estoque.'],
        alert: '—'
      }
    ]
  },
  {
    id: 'estoque-saida',
    name: 'Estoque — Movimentação de Saída',
    icon: '📤',
    desc: 'Orçamentos, ordens de serviço, PDV, faturamento e relatórios de vendas.',
    topics: [
      {
        title: 'Orçamento/Condicional',
        desc: 'Documento sem efeito fiscal usado para apresentar preços ao cliente (orçamento) ou para envio de mercadoria sujeita a devolução (condicional), sem baixar definitivamente o estoque em alguns fluxos.',
        steps: [
          'Cadastre o orçamento/condicional informando cliente, produtos e valores.',
          'Defina o prazo de validade da proposta ou de retorno do condicional.',
          'Converta o orçamento em pedido de venda quando o cliente aprovar.'
        ],
        tips: ['Use o condicional para clientes que "levam para experimentar" — isso mantém o controle do que está fora da loja.'],
        alert: 'Condicionais vencidos e não regularizados distorcem o saldo real disponível para venda.'
      },
      {
        title: 'Ordem de Serviço (cadastro de Objetos)',
        desc: 'Controla serviços prestados sobre um bem do cliente (equipamento, veículo), exigindo o cadastro do "objeto" atendido para rastreabilidade de histórico.',
        steps: [
          'Cadastre o objeto (equipamento/veículo) vinculado ao cliente.',
          'Abra a Ordem de Serviço selecionando o objeto e descrevendo o problema relatado.',
          'Registre peças e serviços utilizados durante a execução.',
          'Finalize a OS e gere o faturamento correspondente.'
        ],
        tips: ['Manter o histórico do objeto atualizado ajuda a identificar problemas recorrentes no mesmo equipamento.'],
        alert: 'OS finalizada sem faturamento gera divergência entre o setor técnico e o financeiro.'
      },
      {
        title: 'Pedido de Venda / PDV',
        desc: 'Registro da venda de produtos/serviços ao cliente, podendo ser feito via pedido tradicional (balcão/representante) ou via PDV (frente de caixa).',
        steps: [
          'Selecione o cliente (ou consumidor final, no PDV).',
          'Inclua os itens vendidos, aplicando desconto conforme alçada permitida.',
          'Escolha a forma de pagamento e finalize a venda.',
          'Emita o documento fiscal correspondente (NF-e/NFC-e/Cupom).'
        ],
        tips: ['No PDV, use a leitura de código de barras sempre que possível para reduzir erros de digitação de item.'],
        alert: 'Descontos acima da alçada do operador devem exigir autorização/senha de supervisor.'
      },
      {
        title: 'PDV Offline (PDV OFF) — Visão Geral',
        desc: 'O PDV OFF permite que a frente de caixa continue vendendo mesmo com a conexão ao banco de dados principal instável ou indisponível, sincronizando as vendas assim que a conexão for restabelecida.',
        steps: [
          'Configure o PDV OFF na estação de caixa seguindo o material de apoio oficial.',
          'Utilize o modo offline apenas quando a conexão com o servidor cair durante o atendimento.',
          'Assim que a conexão voltar, verifique a sincronização das vendas realizadas no período offline.'
        ],
        tips: ['Treine a equipe de caixa para reconhecer quando o PDV entrou em modo offline, evitando pânico durante quedas de conexão.'],
        alert: 'Vendas feitas em modo offline só se tornam definitivas após a sincronização — não finalize o caixa sem confirmar que tudo foi sincronizado.',
        link: { label: 'Tutorial completo de configuração do PDV OFF (PDF)', url: 'https://tgameajuda.com/pdvoff/Configurando%20PDV%20OFF%20pdf.pdf' }
      },
      {
        title: 'PDV OFF — Instalação de Terminal e Servidor',
        desc: 'A conclusão da instalação do PDV OFF envolve dois serviços distintos: o Serviço de Terminais (estação/ponto de venda) e o Serviço de Servidor.',
        steps: [
          'Conclua a instalação do Serviço de Terminais (Ponto de Venda) em cada estação de caixa.',
          'Conclua a instalação do Serviço de Servidor no servidor da empresa.',
          'Durante a instalação do servidor, o serviço de integração "TGA – Server Integrador PDV" é instalado automaticamente.',
          'Após a conclusão, acesse a área de Serviços do Windows e confirme se o "TGA – Server Integrador PDV" está em execução.'
        ],
        tips: ['Sempre confirme o status do serviço de integração no Windows antes de liberar o caixa para uso, evitando dor de cabeça no início do expediente.'],
        alert: '—'
      },
      {
        title: 'PDV OFF — Cadastro do Ponto de Venda (Caixa)',
        desc: 'Cada caixa físico deve ter um Ponto de Venda cadastrado no sistema, com os principais parâmetros que definem como ele opera.',
        steps: [
          '1 — Código Ponto de Venda: informe um código para o PDV.',
          '2 — Descrição: relacionado ao caixa.',
          '3 — Preço Padrão: selecione o preço a ser considerado no momento da venda.',
          '4 — Filial: indique para qual filial serão realizadas as movimentações dos pedidos.',
          '5 — Local de Estoque: associe a localização física onde os produtos se encontram, conforme a filial selecionada.',
          '6 — Vendedor: associe o funcionário responsável pelo controle do caixa (campo opcional).'
        ],
        tips: [],
        alert: 'O campo Vendedor é opcional — se deixado em branco, outro funcionário pode usar o caixa, e as vendas passam a ser vinculadas com base no usuário logado, e não no funcionário vinculado ao ponto de venda.'
      },
      {
        title: 'PDV OFF — Conta Caixa e Série Fiscal do Ponto de Venda',
        desc: 'Cada Ponto de Venda também precisa de uma Conta Caixa e de uma Série Fiscal próprias.',
        steps: [
          '7 — Conta Caixa: cada Ponto de Venda deve ser associado a uma Conta Caixa, previamente cadastrada no retaguarda em Financeiro.',
          '8 — Série Fiscal: cada ponto de venda exige a criação de uma série distinta, cadastrada no Configurador.'
        ],
        tips: ['Caso a série nunca tenha sido utilizada, informe 0 (zero) em sua numeração no Configurador.'],
        alert: 'A utilização do TEF no PDV OFF passa a utilizar as configurações da Conta Caixa, e não mais as do movimento.'
      },
      {
        title: 'PDV OFF — Tipo de Movimento Fiscal e Série Pré-Venda',
        desc: 'Além da série fiscal, cada Ponto de Venda precisa de um Tipo de Movimento Fiscal próprio (para a NFC-e) e de uma Série de Pré-Venda.',
        steps: [
          '9 — Tipo de Movimento Fiscal: selecione o movimento configurado para NFC-e. É obrigatório criar um movimento específico para cada Ponto de Venda.',
          '10 — Série Pré-Venda: seguindo a mesma regra da Série Fiscal, cadastre e relacione a série destinada a esse movimento.'
        ],
        tips: [],
        alert: 'Não criar um movimento fiscal e uma série de pré-venda exclusivos para cada Ponto de Venda pode gerar erros de duplicidade ao salvar o movimento no banco de dados.'
      },
      {
        title: 'PDV OFF — Usuários e Funcionário (Perfil de Caixa)',
        desc: 'O Ponto de Venda também depende da parametrização correta dos cadastros de Usuários e de Funcionário, tanto para o operador de caixa quanto para o supervisor.',
        steps: [
          'Usuários: preencha os campos que determinam a Empresa e a Conta/Caixa relacionadas ao Ponto de Venda. Caminho: Estoque > Cadastros > Segurança > Usuários.',
          'Essa parametrização vale tanto para o Operador do Caixa quanto para o Supervisor.',
          'Funcionário: o colaborador responsável pelo caixa deve ser cadastrado como Funcionário, associando o usuário e definindo o Perfil de Caixa na aba "Frente de Caixa". Caminho: Estoque > Cadastros > Funcionário.',
          'Para o funcionário com função de supervisor, marque a flag de acordo com a função vigente da pessoa.'
        ],
        tips: ['O TGA-PDV valida apenas a senha definida no cadastro de Usuários.'],
        alert: 'Se o cliente/supervisor ainda tiver o PDV ON habilitado no ERP, é recomendado desativá-lo no Configurador, para que, ao logar, ele não entre em movimentações do PDV ON.'
      },
      {
        title: 'PDV OFF — Formas de Pagamento TEF',
        desc: 'A partir do PDV OFF, o TEF passa a utilizar as configurações da Conta Caixa (não mais do movimento). É necessário configurar corretamente cada Forma de Pagamento do tipo cartão.',
        steps: [
          'Tipo Primitivo: selecione "3 - Cartão".',
          'Gerenciador Padrão TEF: selecione a opção correspondente (ex.: Redecard, Cielo, Amex).',
          'Tipo de Transação: selecione o tipo correspondente — 1: Crédito (à vista); 2: Débito; 3: Crédito Parcelado (quando for parcelar no cartão).',
          'Bandeira: selecione a Bandeira correspondente; caso não haja lista, informe a Bandeira Default logo abaixo.'
        ],
        tips: [],
        alert: 'É obrigatório informar o Cód. Cli/Forn do participante do pagamento eletrônico (geralmente a Adquirente ou Subadquirente — ex.: Rede, Cielo, Stone, GetNet, SafraPay, PagSeguro) na aba "Outros dados" da Forma de Pagamento. Essa informação é usada na tag CNPJ do XML da nota, referente ao CNPJ da instituição adquirente/subadquirente.'
      },
      {
        title: 'PDV OFF — Configuração do PIX',
        desc: 'O PIX é configurado como uma Forma de Pagamento, com integração direta à API do banco escolhido.',
        steps: [
          'Acesse Cadastro > Forma de Pagamento.',
          'Marque a opção "Pagamento Instantâneo (PIX)".',
          'Marque também a opção "Usa Integração PIX".',
          'Na aba "Configuração Pix", selecione o banco desejado no campo "API de Integração" e escolha "Produção" no campo "Ambiente".'
        ],
        tips: ['Com as duas opções marcadas, o sistema exibe apenas os campos necessários para a adesão à API PIX escolhida, simplificando a configuração.'],
        alert: '—'
      },
      {
        title: 'PDV OFF — Grupo de Tributação e Produtos',
        desc: 'O TGA-PDV é compatível apenas com a emissão de NFC-e. Caso a empresa utilize o módulo de Escrituração Contábil Fiscal (ECF), ela precisa estar credenciada à NFC-e.',
        steps: [
          'Cadastre o Grupo de Tributação em Estoque > Cadastros > Fiscais > Grupo Tributação, na aba ECF.',
          'No cadastro do produto, vincule o Grupo de Tributação na aba "Tributação", de acordo com a tributação do item.',
          'Caso vá destacar PIS e COFINS, informe também o CST e os percentuais de alíquota correspondentes.'
        ],
        tips: [],
        alert: '—'
      },
      {
        title: 'PDV OFF — Parametrizando o Ponto de Venda no Concentrador',
        desc: 'Após os cadastros no retaguarda, é preciso parametrizar a estação de caixa através do aplicativo TGAPDV – Concentrador.',
        steps: [
          'Na área de trabalho da estação, localize o atalho "TGAPDV – Concentrador" e abra com duplo clique.',
          'Clique no primeiro ícone para abrir as Configurações do Ponto de Venda e informe a senha do mês (fornecida pelo Suporte Técnico).',
          '1) IP do servidor: informe o IP do servidor (ex.: 10.1.1.212).',
          '2) Caminho do Servidor (padrão): C:\\TGA\\Dados\\tga.fdb.',
          '3) Teste de comunicação: clique no ícone para testar a comunicação entre a Estação e o Servidor — se os dados estiverem corretos, o ícone do banco de dados confirma a conexão; caso contrário, exibe erro.',
          '4) Importar Ponto de Venda: antes de pesquisar o Ponto de Venda, importe para exibir os cadastros disponíveis.',
          '5) Ponto de Venda: informe o código referente ao cadastro do Ponto de Venda.',
          '6) Impressora p/ Abertura de Gaveta: informe o IP/nome da impressora utilizada no caixa (ex.: localhost\\MP-4200 TH).',
          '7) Balança Check Out: configure a comunicação para integração com o TGA-PDV, se houver balança.',
          '8) Salvar: após todos os procedimentos, salve para concluir a configuração.'
        ],
        tips: ['Em caso de erro de conexão, verifique se todos os procedimentos foram seguidos: liberação das portas, IP e caminho do servidor.'],
        alert: '—'
      },
      {
        title: 'PDV OFF — Renumeração Manual (Utilizar Último Número Informado)',
        desc: 'O Concentrador também permite ajustar manualmente os sequenciais de NFC-e e Pré-Venda, para casos extremos como duplicidade de numeração.',
        steps: [
          '9) Utilizar último número informado: ao habilitar essa flag, os dois campos abaixo dela são liberados para alteração dos sequenciais de NFC-e e Pré-Venda.',
          '10) Último número para emissão de NFC-e: altera o sequencial de NFC-e.',
          '11) Último número para emissão de Pré-Venda: altera o sequencial de Pré-Venda.'
        ],
        tips: [],
        alert: 'Use essa função apenas em extrema necessidade. Ao marcar a flag e alterar a numeração, salve a configuração — mas NÃO importe o Ponto de Venda novamente, apenas salve.'
      },
      {
        title: 'PDV OFF — Carga Completa',
        desc: 'Depois de concluir toda a parametrização do Ponto de Venda no Concentrador, é necessário realizar a Carga Completa para integrar todos os dados entre o servidor e a estação.',
        steps: [
          'Após parametrizar o Ponto de Venda, execute a Carga Completa na estação.',
          'Aguarde a integração de todos os dados (produtos, clientes, tabelas de preço, etc.) antes de liberar o caixa para uso.'
        ],
        tips: ['Consulte a tabela oficial "TABELAS PDV OFF" para confirmar quais informações são sincronizadas na Carga Completa.'],
        alert: 'Sem a Carga Completa, o Ponto de Venda pode operar com dados desatualizados ou incompletos.'
      },
      {
        title: 'Perfis de Acesso por Atraso, Conceito e Limite de Crédito',
        desc: 'Regras que bloqueiam ou alertam a venda para clientes com títulos em atraso, conceito de crédito ruim ou limite de crédito excedido.',
        steps: [
          'Configure os parâmetros de bloqueio (dias de atraso tolerados, conceito mínimo, limite de crédito).',
          'Associe o perfil de restrição ao cadastro do cliente ou grupo de clientes.',
          'No momento da venda, avalie o alerta/bloqueio apresentado antes de prosseguir.'
        ],
        tips: ['Revise periodicamente o conceito de crédito dos clientes com base no histórico de pagamento.'],
        alert: 'Liberar vendas para clientes bloqueados sem aprovação gerencial aumenta o risco de inadimplência.'
      },
      {
        title: 'Faturamento',
        desc: 'Etapa de conversão do pedido de venda/OS em documento fiscal definitivo (NF-e, NFC-e ou NFS-e), gerando a baixa efetiva do estoque e o lançamento financeiro a receber.',
        steps: [
          'Selecione o(s) pedido(s)/OS a serem faturados.',
          'Confirme itens, valores e tributação antes da emissão.',
          'Emita o documento fiscal e transmita para a SEFAZ/Prefeitura.',
          'Verifique a geração do título financeiro a receber.'
        ],
        tips: ['Confira o status de autorização da nota (autorizada, rejeitada, denegada) logo após a emissão.'],
        alert: 'Nota rejeitada pela SEFAZ não gera efeito fiscal — o problema deve ser corrigido e a nota reemitida.'
      },
      {
        title: 'Devolução de Vendas',
        desc: 'Processo de retorno de mercadoria vendida ao estoque, com emissão de nota fiscal de devolução e, se necessário, estorno do título financeiro.',
        steps: [
          'Localize a nota fiscal de venda original.',
          'Gere a devolução vinculada a essa nota, informando os itens devolvidos.',
          'Emita a nota fiscal de devolução e trate o estorno financeiro (crédito/reembolso).'
        ],
        tips: ['Sempre confira o motivo da devolução — isso ajuda a identificar problemas de qualidade recorrentes.'],
        alert: '—'
      },
      {
        title: 'Relatórios de Vendas / Faturamento',
        desc: 'Relatórios que consolidam o desempenho de vendas por período, vendedor, produto, cliente ou forma de pagamento.',
        steps: [
          'Acesse o relatório de vendas/faturamento e defina o período.',
          'Aplique filtros (vendedor, cliente, grupo de produto).',
          'Analise indicadores como ticket médio e total faturado.'
        ],
        tips: ['Compare períodos equivalentes (mês a mês, ano a ano) para identificar sazonalidade.'],
        alert: '—'
      },
      {
        title: 'Relatório de Estoque',
        desc: 'Mostra a posição atual (ou histórica) de saldo de produtos, permitindo identificar itens em falta, excesso ou parados.',
        steps: [
          'Acesse o relatório de posição de estoque.',
          'Filtre por grupo, fabricante ou faixa de saldo.',
          'Identifique itens com giro baixo ou risco de ruptura.'
        ],
        tips: ['Use o relatório de curva ABC (quando disponível) para priorizar os itens de maior impacto financeiro.'],
        alert: '—'
      },
      {
        title: 'Relatório de Comissão de Vendedores/Técnicos',
        desc: 'Consolida os valores de comissão a pagar a vendedores e técnicos com base nas vendas ou serviços realizados no período.',
        steps: [
          'Configure a regra de comissão (percentual por produto, categoria ou meta atingida).',
          'Acesse o relatório de comissão e defina o período de apuração.',
          'Valide os valores antes de enviar ao financeiro para pagamento.'
        ],
        tips: ['Feche a apuração de comissão sempre após o fechamento das devoluções do período, para não pagar sobre venda estornada.'],
        alert: 'Comissão calculada antes do fechamento de devoluções do mês pode gerar pagamento indevido.'
      }
    ]
  },
  {
    id: 'fiscal',
    name: 'Módulo Fiscal',
    icon: '🧾',
    desc: 'Tributos, CFOP, CST/CSOSN, NCM, e documentos fiscais eletrônicos.',
    topics: [
      {
        title: 'Tributos',
        desc: 'Conjunto de impostos aplicados às operações (ICMS, IPI, PIS, COFINS, ISS), cuja configuração correta no sistema garante o cálculo e destaque correto nos documentos fiscais.',
        steps: [
          'Identifique o regime tributário da empresa (Simples Nacional, Lucro Presumido/Real).',
          'Configure as alíquotas de cada tributo por grupo de produto/serviço.',
          'Associe a regra tributária à Natureza de Operação utilizada.'
        ],
        tips: ['Mantenha a tabela de tributos revisada a cada mudança de legislação estadual/federal.'],
        alert: 'Configuração tributária incorreta pode gerar recolhimento a menor (multa) ou a maior (prejuízo) de impostos.'
      },
      {
        title: 'Natureza de Operação / CFOP',
        desc: 'A Natureza de Operação define a finalidade da movimentação (venda, compra, transferência, devolução) e está diretamente ligada ao CFOP (Código Fiscal de Operações e Prestações) usado na nota.',
        steps: [
          'Cadastre a Natureza de Operação de acordo com o tipo de movimento.',
          'Vincule o CFOP correto conforme a operação seja interna (dentro do estado) ou interestadual.',
          'Associe a natureza de operação ao tipo de movimento no Configurador.'
        ],
        tips: ['CFOPs que começam com 5 são operações internas (dentro do estado) e os que começam com 6 são interestaduais.'],
        alert: 'Usar o CFOP errado pode gerar recolhimento indevido de ICMS ou problemas na escrituração fiscal (SPED).'
      },
      {
        title: 'Situações Tributárias CST / CSOSN',
        desc: 'O CST (Código de Situação Tributária) é usado por empresas do Lucro Presumido/Real, enquanto o CSOSN (Código de Situação da Operação no Simples Nacional) é usado por empresas do Simples Nacional.',
        steps: [
          'Identifique o regime tributário da empresa para escolher entre CST ou CSOSN.',
          'Associe o código correto ao grupo de tributação do produto.',
          'Confira se o código reflete corretamente a tributação (com ou sem substituição, isento, etc.).'
        ],
        tips: ['Nunca copie a tributação de outra empresa sem revisar — o regime tributário pode ser diferente.'],
        alert: 'CST/CSOSN incompatível com o regime tributário da empresa é motivo comum de rejeição pela SEFAZ.'
      },
      {
        title: 'NCM / CEST (MVA)',
        desc: 'O NCM (Nomenclatura Comum do Mercosul) classifica o produto para fins tributários e o CEST identifica mercadorias sujeitas à Substituição Tributária, com sua respectiva MVA (Margem de Valor Agregado).',
        steps: [
          'Consulte o NCM correto do produto na tabela oficial.',
          'Verifique se o produto está sujeito à Substituição Tributária e qual o CEST correspondente.',
          'Configure a MVA aplicável para o cálculo do ICMS-ST.'
        ],
        tips: ['Um NCM incorreto pode mudar completamente a carga tributária do produto — em caso de dúvida, consulte o contador.'],
        alert: 'NCM divergente do praticado pelo mercado pode gerar autuação fiscal por classificação incorreta.'
      },
      {
        title: 'Lista de Serviços LC 116/03',
        desc: 'Tabela nacional que classifica os serviços tributados pelo ISS, usada para emissão correta da NFS-e.',
        steps: [
          'Identifique o item da lista de serviço (LC 116/03) correspondente à atividade prestada.',
          'Associe o item ao cadastro do serviço no sistema.',
          'Confirme a alíquota de ISS praticada pelo município.'
        ],
        tips: ['A alíquota de ISS varia por município — sempre confirme com a legislação local antes de configurar.'],
        alert: '—'
      },
      {
        title: 'NF-e / NFC-e — Diferença entre XML e Documento Auxiliar',
        desc: 'O XML é o arquivo digital que efetivamente tem validade fiscal perante a SEFAZ; o DANFE/DANFCE é apenas a representação impressa (auxiliar) para acompanhar a mercadoria ou entregar ao consumidor.',
        steps: [
          'Sempre guarde o arquivo XML — ele é o documento fiscal válido, não a impressão.',
          'Utilize o DANFE/DANFCE apenas como representação visual/impressa da operação.',
          'Em caso de divergência, o XML autorizado pela SEFAZ prevalece sobre o papel impresso.'
        ],
        tips: ['Explique ao cliente que o "cupom" ou "danfe" impresso é só um resumo — o que vale legalmente é o XML.'],
        alert: 'Perder o arquivo XML (guardando só o papel impresso) pode gerar problemas em auditorias fiscais futuras.'
      },
      {
        title: 'NFS-e — Nota Fiscal de Serviço Eletrônica',
        desc: 'Documento fiscal eletrônico usado para tributar a prestação de serviços, emitido conforme layout definido pela prefeitura do município.',
        steps: [
          'Configure o cadastro municipal e as credenciais de emissão junto à prefeitura.',
          'Associe o serviço prestado ao item correto da lista LC 116/03.',
          'Emita a NFS-e após a conclusão/faturamento do serviço.'
        ],
        tips: ['Cada prefeitura tem seu próprio layout e regras — atualize o sistema sempre que o município alterar o leiaute.'],
        alert: '—'
      },
      {
        title: 'CT-e — Conhecimento de Transporte Eletrônico',
        desc: 'Documento fiscal eletrônico que acoberta a prestação de serviço de transporte de cargas entre localidades.',
        steps: [
          'Cadastre o tomador do serviço e as informações da carga transportada.',
          'Emita o CT-e vinculando a(s) NF-e(s) relacionadas ao transporte.',
          'Transmita o CT-e à SEFAZ antes do início do transporte.'
        ],
        tips: ['Guarde o DACTE junto ao motorista durante o transporte, mesmo sabendo que o XML é o documento oficial.'],
        alert: '—'
      },
      {
        title: 'MDF-e — Manifesto Eletrônico de Documentos Fiscais',
        desc: 'Documento que agrupa e vincula diversas NF-e/CT-e transportadas em um mesmo veículo, exigido em operações interestaduais e outras hipóteses previstas em lei.',
        steps: [
          'Inclua no MDF-e as notas/conhecimentos que serão transportados no veículo.',
          'Informe dados do veículo, motorista e percurso.',
          'Encerre o MDF-e ao final do transporte, na chegada ao destino.'
        ],
        tips: ['Sempre encerre o MDF-e assim que a viagem terminar — um MDF-e pendente pode gerar inconsistências fiscais.'],
        alert: '—'
      },
      {
        title: 'MD-e — Manifesto do Destinatário Eletrônico',
        desc: 'Refere-se ao processo de manifestação eletrônica do destinatário perante a SEFAZ sobre NF-es emitidas contra seu CNPJ (ciência, confirmação, desconhecimento ou não realização da operação).',
        steps: [
          'Consulte periodicamente as notas pendentes de manifestação.',
          'Avalie cada nota e registre a manifestação correspondente.',
          'Integre a manifestação positiva com a entrada de estoque quando aplicável.'
        ],
        tips: ['Automatize a consulta periódica (quando o sistema permitir) para não deixar notas antigas sem manifestação.'],
        alert: 'Ignorar reiteradamente a manifestação de notas pode gerar bloqueios de créditos fiscais.'
      }
    ]
  },
  {
    id: 'configurador',
    name: 'Módulo Configurador',
    icon: '⚙️',
    desc: 'Parâmetros gerais, séries de numeração, impressões e tipos de movimento.',
    topics: [
      {
        title: 'Parâmetros',
        desc: 'Os parâmetros gerais controlam o comportamento global do sistema (arredondamentos, obrigatoriedade de campos, regras de desconto, integrações), definidos uma única vez e aplicados a todas as rotinas.',
        steps: [
          'Acesse o Configurador > Parâmetros Gerais.',
          'Revise as regras de cada módulo (Estoque, Financeiro, Fiscal).',
          'Ajuste com cautela, pois a mudança afeta todos os usuários do sistema.'
        ],
        tips: ['Documente qualquer alteração de parâmetro importante — facilita entender o motivo de um comportamento no futuro.'],
        alert: 'Alterar parâmetros em horário de movimento pode gerar comportamento inesperado para quem já está com a tela aberta.'
      },
      {
        title: 'Séries e Numerações',
        desc: 'Controlam a sequência numérica dos documentos fiscais e internos (NF-e, NFC-e, orçamento, OS), sendo essenciais para evitar duplicidade ou "pulo" de numeração.',
        steps: [
          'Cadastre a série de numeração para cada tipo de documento e filial.',
          'Defina o número inicial de acordo com o último documento emitido.',
          'Nunca reutilize uma numeração já emitida, mesmo que cancelada.'
        ],
        tips: ['Ao abrir uma filial nova, confirme a série exigida pela SEFAZ antes da primeira emissão.'],
        alert: 'Divergência entre a numeração do sistema e a autorizada na SEFAZ trava a emissão de novos documentos.'
      },
      {
        title: 'Ações de Impressão',
        desc: 'Define quais documentos são impressos automaticamente (ou disponibilizados) após uma rotina, como orçamento, cupom, DANFE ou etiqueta.',
        steps: [
          'Configure a ação de impressão vinculada ao tipo de movimento (venda, OS, compra).',
          'Escolha o modelo de impressão (layout) adequado à impressora do setor.',
          'Teste a impressão em ambiente de homologação antes de liberar em produção.'
        ],
        tips: ['Padronize os modelos de impressão entre filiais para manter a identidade visual dos documentos.'],
        alert: '—'
      },
      {
        title: 'Tipos de Movimento (Entradas, Vendas, Outros)',
        desc: 'Os tipos de movimento definem o comportamento de cada operação no estoque e no financeiro: se soma ou subtrai saldo, se gera título financeiro, e qual natureza de operação/CFOP utiliza.',
        steps: [
          'Configure os tipos de movimento de Entrada (compra, devolução de venda, ajuste positivo).',
          'Configure os tipos de movimento de Venda/Saída (venda, devolução de compra, ajuste negativo).',
          'Configure os "Outros Movimentos" (transferência entre filiais, remessa para conserto, bonificação).',
          'Associe cada tipo de movimento à natureza de operação/CFOP correto.'
        ],
        tips: ['Antes de criar um novo tipo de movimento, verifique se um já existente não atende à necessidade, para não fragmentar relatórios.'],
        alert: 'Configurar um tipo de movimento com o sinal de estoque invertido (entrada como saída, por exemplo) distorce todo o saldo do produto.'
      }
    ]
  },
  {
    id: 'financeiro',
    name: 'Módulo Financeiro',
    icon: '💰',
    desc: 'Contas, boletos, lançamentos, caixa, conciliação bancária e relatórios.',
    topics: [
      {
        title: 'Cadastro de Contas/Caixas',
        desc: 'Representa as contas bancárias e caixas físicos da empresa, sendo a base para todo lançamento de pagar/receber e controle de saldo.',
        steps: [
          'Cadastre cada conta bancária com banco, agência e número da conta.',
          'Cadastre os caixas físicos (loja, filial) usados para recebimento em espécie.',
          'Defina o saldo inicial de cada conta/caixa no momento da implantação.'
        ],
        tips: ['Mantenha um caixa/conta separado por finalidade (ex.: caixa loja x conta banco) para facilitar a conciliação.'],
        alert: '—'
      },
      {
        title: 'Cadastro de Portadores (Boletos)',
        desc: 'O portador representa o banco/carteira usado para emissão e cobrança de boletos, contendo as informações de convênio exigidas pelo banco.',
        steps: [
          'Cadastre o portador com os dados de convênio, carteira e código do cliente junto ao banco.',
          'Configure o layout de remessa/retorno compatível com o banco (CNAB 240/400).',
          'Associe o portador à conta bancária correspondente.'
        ],
        tips: ['Solicite ao banco a homologação do arquivo de remessa antes de usar em produção, evitando boletos com dados incorretos.'],
        alert: 'Dados de convênio errados no portador geram boletos que o banco não reconhece ou não processa.'
      },
      {
        title: 'Cadastro de Forma de Pagamento',
        desc: 'Define os meios de recebimento/pagamento aceitos (dinheiro, cartão, boleto, PIX, cheque) e seu comportamento no fluxo de caixa.',
        steps: [
          'Cadastre cada forma de pagamento utilizada pela empresa.',
          'Defina se a forma gera título financeiro (a prazo) ou baixa imediata (à vista).',
          'Associe taxas de cartão/antecipação, quando aplicável.'
        ],
        tips: ['Separe "PIX" e "Dinheiro" como formas distintas mesmo sendo ambos "à vista", para facilitar a conciliação de caixa.'],
        alert: '—'
      },
      {
        title: 'Manutenção de Lançamentos',
        desc: 'Permite incluir, alterar ou consultar títulos a pagar e a receber que não são originados automaticamente por venda/compra (despesas administrativas, por exemplo).',
        steps: [
          'Acesse a manutenção de lançamentos a pagar ou a receber.',
          'Inclua o lançamento informando conta, centro de custo, vencimento e valor.',
          'Revise lançamentos gerados automaticamente por vendas/compras antes de confirmar alterações manuais.'
        ],
        tips: ['Use a classificação por plano de contas em todo lançamento manual, mantendo os relatórios gerenciais consistentes.'],
        alert: 'Alterar valor ou vencimento de um título já vinculado a uma nota fiscal pode gerar divergência entre financeiro e fiscal.'
      },
      {
        title: 'Baixa/Estorno de Lançamentos',
        desc: 'A baixa registra o pagamento/recebimento efetivo de um título; o estorno desfaz essa baixa em caso de erro ou cancelamento.',
        steps: [
          'Selecione o(s) título(s) a baixar e informe a conta/caixa de recebimento.',
          'Confirme eventuais descontos, juros ou multas aplicados na baixa.',
          'Em caso de erro, utilize o estorno de baixa para reabrir o título.'
        ],
        tips: ['Confira sempre a data efetiva de pagamento — baixas com data retroativa incorreta distorcem o fluxo de caixa histórico.'],
        alert: 'Estornar uma baixa já conciliada no extrato bancário pode gerar divergência na conciliação — trate com cuidado.'
      },
      {
        title: 'Manutenção de Extratos',
        desc: 'Permite visualizar e ajustar manualmente os lançamentos do extrato bancário importado ou lançado manualmente no sistema.',
        steps: [
          'Importe o extrato bancário (arquivo OFX ou similar) ou lance manualmente.',
          'Revise os lançamentos duplicados ou não identificados.',
          'Corrija classificações antes de seguir para a conciliação.'
        ],
        tips: ['Importe o extrato com frequência (diária/semanal) para não acumular volume grande de conciliação pendente.'],
        alert: '—'
      },
      {
        title: 'Compensação de Extratos',
        desc: 'Processo de vincular (conciliar) cada lançamento do extrato bancário real a um lançamento/baixa já registrado no sistema, garantindo que o saldo contábil bata com o saldo do banco.',
        steps: [
          'Compare os lançamentos do extrato importado com as baixas já registradas no sistema.',
          'Vincule (compense) cada item correspondente.',
          'Investigue e trate as divergências não compensadas automaticamente.'
        ],
        tips: ['Faça a compensação regularmente — quanto mais tempo passa, mais difícil fica identificar a origem da divergência.'],
        alert: 'Saldo do sistema divergente do extrato bancário real pode indicar lançamento duplicado, esquecido ou tarifa bancária não registrada.'
      },
      {
        title: 'Registradora',
        desc: 'Configuração da integração entre o sistema e o equipamento de frente de caixa (impressora fiscal/SAT/ECF), usada na emissão de cupons no PDV.',
        steps: [
          'Instale e configure o driver do equipamento de acordo com o fabricante.',
          'Configure a porta/comunicação da registradora no sistema.',
          'Realize uma venda de teste para validar a emissão do cupom.'
        ],
        tips: ['Mantenha o driver da registradora sempre atualizado conforme a homologação do fabricante.'],
        alert: '—'
      },
      {
        title: 'Sessão de Caixa (Abertura/Fechamento)',
        desc: 'Controla o período de operação de um caixa, desde a abertura (com fundo de troco) até o fechamento (com a conferência dos valores recebidos por forma de pagamento).',
        steps: [
          'Abra a sessão de caixa informando o valor de fundo de troco.',
          'Realize as vendas normalmente durante o expediente.',
          'Feche a sessão conferindo o valor físico em caixa com o valor apurado pelo sistema.',
          'Registre eventuais diferenças (sobra/falta) de caixa.'
        ],
        tips: ['Nunca inicie um novo turno sem fechar a sessão anterior — isso mistura valores de operadores diferentes.'],
        alert: 'Deixar sessões de caixa abertas por vários dias dificulta a identificação de divergências de fechamento.'
      },
      {
        title: 'Integração Bancária',
        desc: 'Comunicação automatizada entre o sistema e o banco para troca de arquivos de cobrança (remessa/retorno) e, em alguns casos, extrato automático.',
        steps: [
          'Configure as credenciais/convênio da integração bancária escolhida.',
          'Gere e envie o arquivo de remessa conforme a rotina de cobrança.',
          'Importe o arquivo de retorno para atualizar automaticamente os títulos baixados.'
        ],
        tips: ['Valide o layout do banco (CNAB 240/400) periodicamente, pois os bancos atualizam suas especificações.'],
        alert: '—'
      },
      {
        title: 'Remessa/Retorno de Boletos',
        desc: 'Arquivo de Remessa envia ao banco as informações dos boletos a serem registrados/cobrados; o arquivo de Retorno traz do banco a confirmação de pagamentos, baixas e ocorrências.',
        steps: [
          'Gere o arquivo de remessa com os boletos a registrar no banco.',
          'Envie o arquivo pelo internet banking ou integração automática.',
          'Processe o arquivo de retorno recebido do banco para baixar os títulos pagos.'
        ],
        tips: ['Guarde o histórico de arquivos de remessa/retorno processados — eles ajudam a investigar divergências futuras.'],
        alert: 'Processar o mesmo arquivo de retorno duas vezes pode gerar baixa duplicada — confirme o controle de arquivos já processados.'
      },
      {
        title: 'Relatórios Pagar / Receber',
        desc: 'Consolidam a posição de títulos a pagar e a receber, por vencimento, fornecedor/cliente, status (aberto, pago, vencido).',
        steps: [
          'Acesse o relatório de contas a pagar/receber.',
          'Filtre por período de vencimento e status do título.',
          'Utilize o relatório para planejar o fluxo de caixa futuro.'
        ],
        tips: ['Revise diariamente os títulos vencidos para agilizar a cobrança e evitar inadimplência maior.'],
        alert: '—'
      },
      {
        title: 'Extrato de Conta/Caixa',
        desc: 'Mostra todas as movimentações (entradas e saídas) de uma conta ou caixa específico em um período, com saldo inicial e final.',
        steps: [
          'Acesse o extrato da conta/caixa desejada.',
          'Defina o período de consulta.',
          'Confira o saldo final apresentado com o saldo real esperado.'
        ],
        tips: ['Use o extrato do sistema como base de comparação antes de partir para a compensação bancária.'],
        alert: '—'
      },
      {
        title: 'Relatórios de Caixa',
        desc: 'Consolidam o movimento de caixa por sessão/operador, detalhando valores recebidos por forma de pagamento e eventuais diferenças de fechamento.',
        steps: [
          'Acesse o relatório de fechamento de caixa por período ou operador.',
          'Analise o total recebido por forma de pagamento.',
          'Verifique sobras/faltas registradas nos fechamentos.'
        ],
        tips: ['Acompanhe a recorrência de sobras/faltas por operador — pode indicar necessidade de treinamento ou atenção adicional.'],
        alert: '—'
      }
    ]
  },
  {
    id: 'faq',
    name: 'Atendimento ao Cliente & FAQ',
    icon: '❓',
    desc: 'Boas práticas de atendimento, abertura de chamados e dúvidas frequentes.',
    topics: [
      {
        title: 'Postura no Atendimento ao Cliente',
        desc: 'O atendimento a clientes do TGA ERP deve ser cordial, objetivo e orientado à resolução, sempre confirmando o entendimento do problema antes de propor uma solução.',
        steps: [
          'Ouça/leia o relato completo do cliente antes de sugerir qualquer solução.',
          'Confirme dados básicos: versão do sistema, módulo afetado e mensagem de erro exata.',
          'Reproduza o problema no ambiente de teste, se possível, antes de orientar uma correção.',
          'Registre a solução aplicada para consulta em atendimentos futuros semelhantes.'
        ],
        tips: ['Peça sempre um print ou vídeo curto do erro — isso reduz o tempo de diagnóstico consideravelmente.'],
        alert: 'Nunca peça a senha do usuário do cliente para "testar" — utilize acesso remoto autorizado ou ambiente de homologação.'
      },
      {
        title: 'Informações Obrigatórias na Abertura de Chamado',
        desc: 'Para agilizar o atendimento, todo chamado deve conter informações mínimas que permitam reproduzir e diagnosticar o problema relatado.',
        steps: [
          'Registre a empresa/filial e o usuário que identificou o problema.',
          'Descreva o passo a passo exato realizado até o erro aparecer.',
          'Anexe prints, XML ou mensagens de erro completas.',
          'Classifique a urgência do chamado (baixa, média, alta, crítica).'
        ],
        tips: ['Chamados classificados corretamente por urgência ajudam a priorizar problemas que impedem a operação da empresa (ex.: caixa parado).'],
        alert: '—'
      },
      {
        title: 'Escalonamento de Chamados',
        desc: 'Quando o atendimento de primeiro nível não consegue resolver o chamado, ele deve ser escalonado ao nível técnico responsável, mantendo o histórico completo.',
        steps: [
          'Verifique se todas as informações mínimas foram coletadas antes de escalonar.',
          'Descreva as tentativas de solução já realizadas.',
          'Escalone para o time responsável (banco de dados, fiscal, desenvolvimento) conforme a natureza do problema.'
        ],
        tips: ['Escalonar sem contexto suficiente apenas transfere o retrabalho para o próximo nível — capriche na descrição.'],
        alert: '—'
      },
      {
        title: 'FAQ — Perguntas Frequentes',
        desc: 'Respostas rápidas para as dúvidas mais comuns reportadas pelos clientes durante o uso do TGA ERP.',
        steps: [
          '"A nota foi rejeitada, o que fazer?" — Consulte o motivo de rejeição retornado pela SEFAZ, corrija o dado indicado (CST, NCM, destinatário) e reemita.',
          '"O sistema não conecta ao banco de dados" — Verifique se o serviço do Firebird está ativo no servidor e se a porta 3050 está liberada.',
          '"Como recuperar um backup?" — Utilize a rotina de restauração apontando para o arquivo de backup mais recente válido, sempre em ambiente de teste primeiro.',
          '"Por que o estoque não bate com a contagem física?" — Verifique ajustes de saldo não documentados, devoluções não lançadas ou vendas lançadas em duplicidade.',
          '"O boleto voltou com erro do banco" — Confira o cadastro do portador (convênio/carteira) e o CPF/CNPJ do cliente no boleto.'
        ],
        tips: ['Mantenha esta lista de perguntas frequentes sempre atualizada com os chamados mais recorrentes do mês.'],
        alert: '—'
      }
    ]
  },
  {
    id: 'perguntas-gerais',
    name: 'Perguntas e Respostas — Geral',
    icon: '💬',
    desc: 'Banco de revisão com perguntas e respostas gerais de conhecimento do sistema TGA ERP.',
    topics: [
      {
        title: '1 — Significado dos principais acrósticos do sistema',
        desc: 'NCM: Nomenclatura Comum do Mercosul. CEST: Código Especificador da Substituição Tributária. CFOP: Código Fiscal de Operações e Prestações. CST: Código da Situação Tributária. CSOSN: Código da Situação de Operação do Simples Nacional. MVA: Margem de Valor Agregado. CNAE: Classificação Nacional das Atividades Econômicas. DANFE: Documento Auxiliar de Nota Fiscal Eletrônica.',
        steps: [], tips: [], alert: '—'
      },
      {
        title: '2 — Qual a diferença entre Condição de Pagamento e Forma de Pagamento?',
        desc: 'Condição de Pagamento é o prazo para pagamento (à vista, 30/60/90 dias). Forma de Pagamento refere-se ao meio pelo qual o pagamento será realizado (dinheiro, cartão, boleto, PIX).',
        steps: [], tips: [], alert: '—'
      },
      {
        title: '3 — Numeração correta dos tipos de movimento no cadastro do sistema',
        desc: 'Cada tipo de operação segue uma faixa de numeração padrão no TGA:',
        steps: [
          'Venda de Mercadoria: 2.2.XX',
          'Compra de Mercadoria: 1.2.XX',
          'Orçamento: 2.1.XX',
          'Devolução de Compra: 1.3.XX',
          'Ajuste de Saldo: 4.1.XX',
          'Cotação: 1.1.XX'
        ],
        tips: [], alert: '—'
      },
      {
        title: '4 — Do que se trata a Contrassenha do sistema TGA?',
        desc: 'É a validação da situação financeira da empresa na base de dados da TGA (se há débitos em aberto ou não) e/ou se a empresa está ativa na base de dados da TGA para utilização do sistema.',
        steps: [], tips: [], alert: '—'
      },
      {
        title: '5 — Venda no cartão de crédito: em qual data a empresa recebe o valor?',
        desc: 'Se for realizada uma venda no cartão de crédito no dia 10/10, por exemplo, e o cartão do cliente vence todo dia 25, o prazo padrão de recebimento pela empresa é de 30 dias após a operação — porém há possibilidade de antecipação, e algumas máquinas de cartão já realizam a antecipação automaticamente no momento da venda.',
        steps: [], tips: [], alert: '—'
      },
      {
        title: '6 — Conceitos de Preço de Compra, Custo Unitário, Custo Médio e Ponto de Equilíbrio',
        desc: 'Definições utilizadas na formação de preço no estoque:',
        steps: [
          'Preço de Compra: preço de fábrica.',
          'Custo Unitário: valor da mercadoria considerando impostos e outras despesas até chegar à empresa.',
          'Custo Médio: custo médio da mercadoria em estoque.',
          'Ponto de Equilíbrio: valor mínimo de venda que não gera nem prejuízo nem lucro.'
        ],
        tips: [], alert: '—'
      },
      {
        title: '7 — Para que serve o campo Conceito no cadastro do cliente?',
        desc: 'É utilizado, normalmente, para validação da situação cadastral/financeira do cliente na liberação ou não de vendas a prazo.',
        steps: [], tips: [], alert: '—'
      },
      {
        title: '8 — Para que serve o Centro de Custos?',
        desc: 'Serve para controlar despesas, receitas e investimentos, agrupando-os por tipo/área da empresa.',
        steps: [], tips: [], alert: '—'
      },
      {
        title: '9 — Quais dados aparecem no Dashboard BI do módulo Estoque?',
        desc: 'O Dashboard BI do módulo Estoque reúne análise de Vendas, Estoque, NF-e e NFC-e por status, e Ordens de Serviço.',
        steps: [], tips: [], alert: '—'
      },
      {
        title: '10 — Como funciona o flag "Usa Regra de Preço" no cadastro do produto (aba Preços)?',
        desc: 'Serve para tratar o preço do item de acordo com a quantidade vendida (preços diferenciados por faixa de quantidade).',
        steps: [], tips: [], alert: '—'
      },
      {
        title: '11 — Qual o prazo para cancelamento de NF-e e NFC-e?',
        desc: 'NF-e: até 8 horas após a autorização. NFC-e: até 30 minutos após a autorização.',
        steps: [], tips: [], alert: 'Após o prazo, o cancelamento direto não é mais possível — é necessário avaliar outras alternativas fiscais (carta de correção ou nota de devolução/ajuste, conforme o caso).'
      },
      {
        title: '12 — Como abrir dois módulos (Financeiro/Estoque) ao mesmo tempo?',
        desc: 'Instalando e utilizando Múltiplas Instâncias do sistema na mesma estação.',
        steps: [], tips: [], alert: '—'
      },
      {
        title: '13 — Nomes das principais tabelas do banco de dados',
        desc: 'Assim como Produto está para TPRODUTO e Clientes está para FCFO, outras tabelas importantes são:',
        steps: [
          'Movimento: TMOV',
          'Itens: TMOVITENS',
          'Lançamentos: FLAN',
          'Extratos: FEXTRATO',
          'Grupo de Produtos: TGRUPO'
        ],
        tips: [], alert: '—'
      },
      {
        title: '14 — Sequência correta de atualização de versão do sistema',
        desc: 'Ao atualizar a versão do sistema (e do banco de dados), a ordem correta das etapas é:',
        steps: [
          'Parar o Firebird',
          'Renomear a pasta do Banco de Dados',
          'Iniciar o Firebird',
          'Renomear o inicializador do sistema (gerenciador de Bases)',
          'Executar o Instalador',
          'Executar o Atualizador',
          'Nomear a pasta do Banco de Dados'
        ],
        tips: ['Sempre garanta um backup válido do banco antes de iniciar qualquer etapa de atualização de versão.'],
        alert: 'Pular etapas ou executá-las fora de ordem pode corromper a base de dados ou deixar o sistema apontando para o banco antigo.'
      },
      {
        title: '15 — NF-e com status "Denegada", o que quer dizer?',
        desc: 'Significa que há um problema na situação fiscal do emitente ou do destinatário perante a SEFAZ (por exemplo, irregularidade cadastral), impedindo a autorização normal do documento.',
        steps: [], tips: [], alert: '—'
      },
      {
        title: '16 — O que é preciso para configurar o protocolo TCP-IP no servidor e nos terminais?',
        desc: 'É preciso criar no Firewall as regras do Firebird para os dois protocolos (TCP e UDP), nas duas direções (Entrada e Saída), utilizando a porta 3050.',
        steps: [], tips: [], alert: '—'
      }
    ]
  },
  {
    id: 'perguntas-fiscais',
    name: 'Perguntas e Respostas — Fiscal',
    icon: '📑',
    desc: 'Revisão descritiva sobre NFC-e, NF-e, CST/CSOSN, NCM/CEST e trocas de regime tributário.',
    topics: [
      {
        title: '01 — Descreva o significado de NFC-e e sua finalidade',
        desc: 'Nota Fiscal de Consumidor Eletrônica, modelo 65, veio para substituir o Cupom Fiscal. Serve como comprovante fiscal da compra, sendo utilizada para vendas de varejo e entregas a domicílio, com preenchimento simplificado de dados.',
        steps: [], tips: [], alert: '—'
      },
      {
        title: '02 — Descreva o significado de NF-e e sua finalidade',
        desc: 'Nota Fiscal Eletrônica, modelo 55, com informações mais detalhadas. Serve como comprovante fiscal da compra, usada no transporte de mercadorias para atender e facilitar a fiscalização, e também por empresas devido ao aproveitamento de créditos. Este modelo também pode ser emitido para pessoa física.',
        steps: [], tips: [], alert: '—'
      },
      {
        title: '03 — Diferença entre CST e CSOSN e regimes aplicáveis',
        desc: 'CST (Código de Situação Tributária) é utilizado por empresas do regime normal (Lucro Presumido, Lucro Real, Simples Nacional com excesso de sub-limite de receita estadual), havendo exceções em que empresas do Simples Nacional também usam CST (por exemplo, venda de gás e emissões de CT-e). CSOSN (Código de Situação da Operação no Simples Nacional) é utilizado por empresas do Simples Nacional.',
        steps: [], tips: [], alert: '—'
      },
      {
        title: '04 — O que significa NCM e qual sua importância?',
        desc: 'Nomenclatura Comum do Mercosul: código que identifica os produtos, sua categoria e composição entre os países do Mercosul, sendo também utilizado para definição de tributações.',
        steps: [], tips: [], alert: '—'
      },
      {
        title: '05 — O que significa CEST e qual sua importância?',
        desc: 'Código Especificador da Substituição Tributária: utilizado junto ao NCM para identificar produtos sujeitos à Substituição Tributária nas operações estaduais e interestaduais, e para o cálculo da MVA.',
        steps: [], tips: [], alert: '—'
      },
      {
        title: '14 — Erro identificado em questionário fiscal preenchido pela contabilidade: qual o procedimento correto?',
        desc: 'Informar ao cliente que o questionário está incompleto ou com informações aparentemente erradas, devolvendo-o à contabilidade para correção. Caso necessário, o próprio suporte técnico pode entrar em contato diretamente com a contabilidade para explicar a situação e otimizar o tempo de atendimento.',
        steps: [], tips: [], alert: '—'
      }
    ]
  },
  {
    id: 'reforma-tributaria',
    name: 'Reforma Tributária (IBS/CBS)',
    icon: '🏛️',
    desc: 'Cronograma da Reforma Tributária do Consumo e passo a passo de configuração do sistema TGA para IBS e CBS.',
    topics: [
      {
        title: 'Introdução à Reforma Tributária do Consumo',
        desc: 'A Reforma Tributária do Consumo tem como objetivo simplificar o sistema tributário brasileiro, unificando os tributos incidentes sobre bens e serviços em dois novos impostos: IBS e CBS. A soma dos dois forma o IVA (Imposto sobre Valor Agregado), modelo já utilizado em diversos países, que busca reduzir a burocracia, aumentar a transparência e tornar a tributação mais justa. A implementação será gradual, entre 2026 e 2033.',
        steps: [
          'CBS — Contribuição sobre Bens e Serviços: competência federal, substituirá o PIS e a COFINS.',
          'IBS — Imposto sobre Bens e Serviços: competência estadual e municipal, substituirá o ICMS e o ISS.'
        ],
        tips: ['Trate a reforma como um projeto contínuo de vários anos — os cadastros e parametrizações do sistema serão ajustados em etapas, acompanhando o cronograma legal.'],
        alert: '—'
      },
      {
        title: 'Cronograma de Transição (2026 – 2033)',
        desc: 'A transição para o novo modelo ocorre de forma gradual, conforme o cronograma abaixo.',
        steps: [
          '2026: ano de testes e validação da CBS (0,9%) e do IBS (0,1%). Os tributos devem ser destacados nas notas fiscais, mas ainda sem recolhimento obrigatório.',
          '2027: início da cobrança integral da CBS e extinção do PIS e da COFINS.',
          '2029 a 2032: entrada gradual do IBS, com redução anual de 10% do ICMS e do ISS.',
          '2033: extinção total de PIS, COFINS, ICMS e ISS, com adoção definitiva do modelo CBS + IBS.'
        ],
        tips: ['Consulte sempre o site da Receita Federal para acompanhar eventuais atualizações do cronograma e das alíquotas de teste.'],
        alert: '—'
      },
      {
        title: 'O que muda no sistema TGA',
        desc: 'Para garantir o correto cálculo, destaque e geração de XML, será necessário realizar novos cadastros e ajustes de parametrização no sistema TGA.',
        steps: [
          'Cadastro dos novos tributos (CBS, IBSUF e IBSMUN).',
          'Cadastro da fórmula de base de cálculo do IBS/CBS.',
          'Cadastro e importação da Classificação Tributária (CCLASSTRIB).',
          'Definição do Tipo de Operação Tributária.',
          'Nova parametrização do Tipo de Movimento.',
          'Nova parametrização do cadastro de NCM.',
          'Nova parametrização do cadastro de Produtos.'
        ],
        tips: [],
        alert: 'Referente à CCLASSTRIB: oriente o cliente a levantar se existem produtos com classificação diferente de 000001. Caso algum produto ou movimento deva destacar os tributos e não esteja destacando, encaminhe a solicitação de ajuste ao suporte TGA para análise da parametrização.'
      },
      {
        title: 'Atualização do Sistema',
        desc: 'É obrigatório atualizar o sistema para a versão 25.11.01 ou superior antes de iniciar qualquer configuração da Reforma Tributária — essa etapa é essencial para o funcionamento correto das novas rotinas.',
        steps: ['Solicite/baixe a atualização junto ao setor responsável.', 'Atualize o sistema antes de iniciar os demais passos deste tutorial.'],
        tips: [],
        alert: 'Sem a versão mínima instalada, os campos e tributos da Reforma não estarão disponíveis no sistema.'
      },
      {
        title: 'Cadastro da Fórmula 200 — Base de Cálculo IBS/CBS',
        desc: 'Cadastre a fórmula 200 no sistema, responsável por calcular corretamente a base de cálculo do IBS e do CBS a partir do valor do item, frete e demais tributos já destacados.',
        code: [{ label: 'Fórmula 200 (Base de Cálculo IBS/CBS)', content: `SETVAR(RATEIO,N,FORMULA(9));
SETVAR(TOTITEM,N,((ITQT * ITPU)-ITDC) - (MVVD * RATEIO));
SETVAR(FRETE,N,SE(MVTF="1",MVVF,0));
SETVAR(TOTITEM,N,TOTITEM + (FRETE * RATEIO));
SETVAR(VPIS,N,ITTV(PIS));
SETVAR(VCOFINS,N,ITTV(COFINS));
SETVAR(VICMS,N,ITTV(ICMS));
SETVAR(VICMSST,N,ITTV(ICMSST));
SETVAR(VFCP,N,ITTV(FCP));
SETVAR(VFCPST,N,ITTV(FCPST));
SETVAR(VISS,N,ITTV(ISS));
TOTITEM - VPIS - VCOFINS - VICMS - VICMSST - VFCP - VFCPST - VISS` }],
        steps: [], tips: [], alert: '—'
      },
      {
        title: 'Importação da Classificação Tributária (CCLASSTRIB)',
        desc: 'A Classificação Tributária deve ser importada via API antes de configurar os tributos, pois é ela que orienta o sistema sobre qual tratamento tributário aplicar a cada produto/operação.',
        steps: ['Acesse: Módulo Estoque → Fiscais → Classificação Tributária → Importar via API.', 'Aguarde a conclusão da importação e confira se os códigos foram carregados corretamente.'],
        tips: [], alert: '—'
      },
      {
        title: 'Criação e Vínculo dos Tributos (Execute Block)',
        desc: 'Rotina técnica executada diretamente no banco de dados para criar os tributos CBS, IBSUF e IBSMUN e vinculá-los automaticamente aos tipos de movimento elegíveis, respeitando as exceções configuradas.',
        code: [{ label: 'Execute Block — Criação e vínculo dos tributos', content: `EXECUTE BLOCK
AS
  DECLARE VARIABLE VCODTMV    VARCHAR(20);
  DECLARE VARIABLE VSEQ       INTEGER;
  DECLARE VARIABLE VFORM      INTEGER;
  DECLARE VARIABLE VGERASPED  VARCHAR(1);
  DECLARE VARIABLE VINATIVO   VARCHAR(1);
BEGIN

  INSERT INTO TTRIBUTO
    (CODEMPRESA, CODTRB, DESCRICAO, INCIDENCIA,
     TIPOTRIBFISC, ABRANGENCIA, FATOR, ALIQUOTA)
  VALUES
    (1, 'CBS', 'CBS', 'A', 41, 'F', 0.0, 0.0);

  INSERT INTO TTRIBUTO
    (CODEMPRESA, CODTRB, DESCRICAO, INCIDENCIA,
     TIPOTRIBFISC, ABRANGENCIA, FATOR, ALIQUOTA)
  VALUES
    (1, 'IBSUF', 'IBSUF', 'A', 42, 'E', 0.0, 0.0);

  INSERT INTO TTRIBUTO
    (CODEMPRESA, CODTRB, DESCRICAO, INCIDENCIA,
     TIPOTRIBFISC, ABRANGENCIA, FATOR, ALIQUOTA)
  VALUES
    (1, 'IBSMUN', 'IBSMUN', 'A', 43, 'M', 0.0, 0.0);

  UPDATE TREGRATRIBUTACAOOPERACOES R
     SET R.USAIMPORTADORXML = 'T',
         R.CODCCLASTRIB     = '000001';

  FOR
    SELECT DISTINCT T.CODTMV
      FROM TTRBTMV T
      INTO :VCODTMV
  DO
  BEGIN

    SELECT T2.GERAESCRITURACAOFISC
      FROM TTIPOMOV2 T2
     WHERE T2.CODTIPOMOV = :VCODTMV
      INTO :VGERASPED;

    SELECT T.INATIVO
      FROM TTIPOMOV T
     WHERE T.CODTIPOMOV = :VCODTMV
      INTO :VINATIVO;

    IF (
         (VCODTMV IN (
           '1.2.02','1.2.13','1.2.14','1.2.15','1.2.16',
           '1.3.05','1.3.06',
           '2.2.18','2.2.19','2.2.21','2.3.06',
           '2.2.20','2.2.22',
           '1.2.11','1.2.13'
         ))
         OR (VGERASPED = 'F')
         OR (VINATIVO  = 'T')
       )
    THEN
    BEGIN
      /* NÃO INSERE TRIBUTOS */
    END
    ELSE
    BEGIN

      SELECT MAX(T.SEQUENCIAL)
        FROM TTRBTMV T
       WHERE T.CODTMV = :VCODTMV
        INTO :VSEQ;

      IF (VSEQ IS NULL) THEN
        VSEQ = 0;

      VSEQ = VSEQ + 1;
      VFORM = 200;

      INSERT INTO TTRBTMV
        (CODEMPRESA, CODTMV, ITEMOUMOVIMENTO, CODTRB,
         ALIQUOTATRIBUTO, CODFRMBCTRIBUTO,
         ACHARALIQUOTA, SEQUENCIAL, NAOIMPORTARXML)
      VALUES
        (1, :VCODTMV, 'I', 'IBSUF',
         '0.00', :VFORM,
         'T', :VSEQ, 'F');

      VSEQ = VSEQ + 1;

      INSERT INTO TTRBTMV
        (CODEMPRESA, CODTMV, ITEMOUMOVIMENTO, CODTRB,
         ALIQUOTATRIBUTO, CODFRMBCTRIBUTO,
         ACHARALIQUOTA, SEQUENCIAL, NAOIMPORTARXML)
      VALUES
        (1, :VCODTMV, 'I', 'IBSMUN',
         '0.00', :VFORM,
         'T', :VSEQ, 'F');

      VSEQ = VSEQ + 1;

      INSERT INTO TTRBTMV
        (CODEMPRESA, CODTMV, ITEMOUMOVIMENTO, CODTRB,
         ALIQUOTATRIBUTO, CODFRMBCTRIBUTO,
         ACHARALIQUOTA, SEQUENCIAL, NAOIMPORTARXML)
      VALUES
        (1, :VCODTMV, 'I', 'CBS',
         '0.00', :VFORM,
         'T', :VSEQ, 'F');

    END
  END
END` }],
        steps: [],
        tips: ['Revise cuidadosamente a lista de movimentos que NÃO devem receber os novos tributos antes de executar o bloco.'],
        alert: 'Esta é uma rotina técnica que altera diretamente o banco de dados — execute apenas com backup recente e, preferencialmente, com acompanhamento do suporte técnico/DBA.'
      },
      {
        title: 'Operações com Incidência de IBS e CBS',
        desc: 'A aplicação dos tributos da Reforma (IBS e CBS) ocorre somente em operações onerosas. Abaixo estão os principais cenários contemplados.',
        steps: [
          'Vendas: venda com NF-e/NFC-e, devolução de venda, venda com entrega futura, emissão de CT-e, emissão de MDF-e, venda de ativo imobilizado e outras operações onerosas vinculadas a vendas.',
          'Compras: compra para revenda, devolução de compra, compra para uso e consumo, compra de ativo imobilizado, aquisição de serviços (transporte, energia, água, comunicação) e outras operações onerosas vinculadas a compras.'
        ],
        tips: [],
        alert: 'Movimentos de remessas, transferências entre filiais, bonificações e outras saídas sem caráter oneroso NÃO devem ter os tributos IBS e CBS incluídos.'
      },
      {
        title: 'Confirmação dos Tributos Cadastrados',
        desc: 'Após a criação dos tributos, confirme se eles aparecem corretamente cadastrados no sistema.',
        steps: [
          'Acesse: Cadastros → Fiscais → Tributos.',
          'Verifique se aparecem: CBS – Federal (PIS/COFINS), IBSUF – Estadual (ICMS) e IBSMUN – Municipal (ISS).'
        ],
        tips: [], alert: '—'
      },
      {
        title: 'Ajuste do Tipo de Operação Tributária',
        desc: 'Cada tipo de movimento deve ter seu Tipo de Operação Tributária revisado para refletir corretamente vendas, compras para comercialização, uso e consumo ou ativo imobilizado.',
        steps: ['Acesse: Edição do Movimento → Identificação → Dados Fiscais → Tipo de Operação / Regra de Tributação por Produto.', 'Ajuste conforme o tipo de operação: Vendas, Compras para comercialização, Uso e consumo ou Ativo Imobilizado.'],
        tips: [], alert: '—'
      },
      {
        title: 'Atualização das Datas da Reforma e Marcação do Sistema',
        desc: 'Após concluir os cadastros, é necessário informar as datas de homologação/produção da reforma na filial e marcar o sistema como configurado.',
        code: [
          { label: 'Atualização das datas da reforma', content: `UPDATE GFILIAL
SET DATAHOMOLOGACAOREFORMA = '01.09.2025',
    DATAPRODUCAOREFORMA = 'dia/mes/ano';` },
          { label: 'Marcar sistema como configurado', content: `UPDATE GFILIAL
SET DESTACATAGVITEMREFORMA = 'T';` }
        ],
        steps: [],
        tips: [],
        alert: 'A data de produção deve refletir o momento real em que a filial passará a operar oficialmente com os novos tributos — ajuste com cautela.'
      },
      {
        title: 'Validação do XML da Nota (IBS/CBS)',
        desc: 'Após configurar tudo, gere uma nota de teste e valide se o XML contém corretamente as tags de IBS e CBS.',
        code: [{ label: 'Exemplo de grupo IBS/CBS no XML', content: `<IBSCBSTot>
<vBCIBSCBS>2.20</vBCIBSCBS>
<gIBS>
  <gIBSUF>
    <vDif>0.00</vDif>
    <vDevTrib>0.00</vDevTrib>
    <vIBSUF>0.01</vIBSUF>
  </gIBSUF>
  <gIBSMun>
    <vDif>0.00</vDif>
    <vDevTrib>0.00</vDevTrib>
    <vIBSMun>0.00</vIBSMun>
  </gIBSMun>
  <vIBS>0.01</vIBS>
  <vCredPres>0.00</vCredPres>
  <vCredPresCondSus>0.00</vCredPresCondSus>
</gIBS>
<gCBS>
  <vDif>0.00</vDif>
  <vDevTrib>0.00</vDevTrib>
  <vCBS>0.02</vCBS>
  <vCredPres>0.00</vCredPres>
  <vCredPresCondSus>0.00</vCredPresCondSus>
</gCBS>
</IBSCBSTot>
<vNFTot>3.11</vNFTot>
</total>` }],
        steps: [], tips: [], alert: '—'
      },
      {
        title: 'Observações sobre a DANFE e Texto Complementar',
        desc: 'O sistema já gera corretamente no XML da nota o grupo de IBS (estadual e municipal), o grupo de CBS, os códigos específicos de CST/CSOSN para IBS e CBS, e os valores de base de cálculo, alíquota e tributo. Porém, a DANFE oficial nacional segue um leiaute definido por norma técnica que, até o momento, não foi atualizado para exibir campos específicos de IBS e CBS. Assim que o novo layout oficial for publicado, o sistema TGA será ajustado para atendê-lo.',
        code: [{ label: 'Fórmula opcional — exibir IBS/CBS em texto complementar', content: `SETVAR(
  TEXTOIR,
  C,
  "SELECT
   'Valor total do CBS dos produtos: R$ ' ||
   REPLACE(
     CAST(
       COALESCE(SUM(CASE WHEN CODTRB='CBS' THEN VALOR ELSE 0 END),0)
       AS NUMERIC(9,2)
     ),'.',','
   ) ||
   ' Valor total do IBS dos produtos: R$ ' ||
   REPLACE(
     CAST(
       COALESCE(SUM(CASE WHEN CODTRB='IBSUF' THEN VALOR ELSE 0 END),0)
       AS NUMERIC(9,2)
     ),'.',','
   )
   FROM TTRBMOV
   WHERE IDMOV=" + MVID
);

SETVAR(TEXTOIR2, C, FSQL(TEXTOIR));
TEXTOIR2` }],
        steps: [],
        tips: ['Essa exibição em texto complementar é apenas informativa e não substitui o leiaute oficial da DANFE — use somente em caso de necessidade operacional ou solicitação do cliente.'],
        alert: 'Não existe, até o momento, campo padronizado no DANFE nacional para exibir IBS e CBS.'
      }
    ]
  }
];

/* ==========================================================================
   2. BANCO DE QUESTÕES DO QUIZ
   ========================================================================== */
const QUIZ_QUESTIONS = [
  // Introdução
  { category: 'introducao', question: 'Qual é o principal benefício de um sistema ERP para uma empresa?', options: ['Substituir a necessidade de funcionários', 'Integrar processos em uma única base de dados, evitando retrabalho', 'Eliminar totalmente a necessidade de backup', 'Funcionar apenas offline'], correct: 1, explanation: 'O ERP centraliza as informações de diversos setores em uma única base, garantindo consistência entre módulos como estoque, financeiro e fiscal.' },
  { category: 'introducao', question: 'Qual banco de dados o TGA ERP utiliza?', options: ['MySQL', 'SQL Server', 'Firebird', 'MongoDB'], correct: 2, explanation: 'O TGA ERP utiliza o Firebird como sistema gerenciador de banco de dados (SGBD).' },
  { category: 'introducao', question: 'Qual é a porta padrão utilizada pelo Firebird e que deve ser liberada no firewall?', options: ['80', '443', '3050', '3306'], correct: 2, explanation: 'A porta TCP 3050 é a porta padrão de comunicação do Firebird e precisa estar liberada para as estações se conectarem ao servidor.' },
  { category: 'introducao', question: 'O Firebird deve ser instalado no servidor como:', options: ['Aplicação comum, iniciada manualmente', 'Serviço do Windows (Server Component)', 'Extensão do navegador', 'Serviço de impressão'], correct: 1, explanation: 'Instalar como serviço garante que o banco continue rodando mesmo sem um usuário logado no servidor.' },
  { category: 'introducao', question: 'Para que serve a "contrassenha" no TGA ERP?', options: ['Login inicial do sistema', 'Senha do banco de dados Firebird', 'Validar a situação financeira/cadastral da empresa junto à TGA e liberar o uso do sistema', 'Senha de acesso à internet'], correct: 2, explanation: 'A contrassenha valida se há débitos em aberto e/ou se a empresa está ativa na base da TGA, liberando o sistema para uso após a regularização.' },
  { category: 'introducao', question: 'De acordo com a LGPD, qual prática é recomendada no cadastro de clientes?', options: ['Cadastrar o máximo de dados pessoais possível, "por garantia"', 'Cadastrar apenas os dados pessoais necessários para a operação', 'Compartilhar os dados livremente entre setores', 'Exportar dados pessoais para planilhas soltas sempre que possível'], correct: 1, explanation: 'A LGPD exige que apenas dados necessários à finalidade sejam coletados e tratados, reduzindo riscos de exposição.' },
  { category: 'introducao', question: 'Qual é a boa prática de backup conhecida como regra "3-2-1"?', options: ['3 backups por dia, 2 servidores, 1 técnico responsável', '3 cópias, em 2 mídias diferentes, sendo 1 fora do local físico', '3 minutos de backup, 2 vezes por semana, 1 vez por mês', '3 usuários autorizados, 2 senhas, 1 backup'], correct: 1, explanation: 'A regra 3-2-1 recomenda manter três cópias dos dados, em dois tipos de mídia diferentes, com pelo menos uma fora do local físico da empresa.' },
  { category: 'introducao', question: 'Por que usuários genéricos compartilhados (ex.: "caixa1") são desaconselhados?', options: ['Porque ocupam mais espaço no banco', 'Porque impedem identificar quem realizou cada operação', 'Porque deixam o sistema mais lento', 'Porque não é possível fazer login com eles'], correct: 1, explanation: 'Usuários individuais garantem rastreabilidade: é possível saber exatamente quem fez cada lançamento ou alteração.' },
  // Cadastros Gerais
  { category: 'cadastros', question: 'O que são "Campos Complementares" no TGA ERP?', options: ['Campos obrigatórios definidos pela Receita Federal', 'Campos personalizados adicionados a um cadastro padrão', 'Relatórios extras do sistema', 'Um tipo de backup adicional'], correct: 1, explanation: 'Campos complementares permitem adicionar informações extras e específicas a um cadastro, sem alterar a estrutura padrão do sistema.' },
  { category: 'cadastros', question: 'Qual é a finalidade do Centro de Custo no plano financeiro?', options: ['Definir o preço de venda dos produtos', 'Indicar em qual setor/área ocorreu a receita ou despesa', 'Substituir o plano de contas', 'Cadastrar fornecedores'], correct: 1, explanation: 'O centro de custo permite segmentar as movimentações financeiras por área ou departamento da empresa, enriquecendo a análise gerencial.' },
  { category: 'cadastros', question: 'Antes de cadastrar um novo cliente ou produto, qual prática evita duplicidade?', options: ['Cadastrar direto e corrigir depois', 'Pesquisar se o registro já existe na base', 'Perguntar a outro colega', 'Cadastrar com nome diferente para não conflitar'], correct: 1, explanation: 'A pesquisa prévia evita a criação de registros duplicados, que geram inconsistência em relatórios e movimentações futuras.' },
  // Estoque Entrada
  { category: 'estoque-entrada', question: 'O que deve ser cadastrado ANTES do produto, para uma correta configuração tributária?', options: ['Relatório de vendas', 'Grupo de Tributação', 'Ordem de Serviço', 'Sessão de caixa'], correct: 1, explanation: 'O Grupo de Tributação define os impostos padrão que serão herdados pelo produto, por isso deve ser configurado previamente.' },
  { category: 'estoque-entrada', question: 'O que é o Manifesto do Destinatário?', options: ['Um relatório de vendas por vendedor', 'A confirmação eletrônica perante a SEFAZ sobre uma NF-e emitida contra o CNPJ da empresa', 'Um tipo de etiqueta de produto', 'Um cadastro de funcionário'], correct: 1, explanation: 'O Manifesto do Destinatário permite dar ciência, confirmar, desconhecer ou informar não realização da operação relatada em uma NF-e destinada à empresa.' },
  { category: 'estoque-entrada', question: 'Qual das opções abaixo é uma manifestação possível no Manifesto do Destinatário?', options: ['Aprovação de crédito', 'Ciência da Operação', 'Emissão de boleto', 'Ajuste de saldo'], correct: 1, explanation: 'As manifestações possíveis incluem Ciência da Operação, Confirmação, Desconhecimento e Não Realização da Operação.' },
  { category: 'estoque-entrada', question: 'Para que serve a rotina de Ajuste de Saldos no estoque?', options: ['Gerar nota fiscal de venda', 'Corrigir divergências entre o saldo físico e o saldo do sistema', 'Cadastrar um novo fornecedor', 'Emitir boletos'], correct: 1, explanation: 'O ajuste de saldo corrige, para mais ou para menos, divergências identificadas entre a contagem física (inventário) e o saldo registrado no sistema.' },
  { category: 'estoque-entrada', question: 'A Devolução de Compras deve ser vinculada a quê?', options: ['À nota de entrada original', 'Ao relatório de comissão', 'Ao cadastro de funcionários', 'À sessão de caixa'], correct: 0, explanation: 'Vincular a devolução à nota original mantém a rastreabilidade fiscal e de estoque da operação.' },
  // Estoque Saída
  { category: 'estoque-saida', question: 'Qual documento é usado para enviar mercadoria ao cliente sujeita a devolução, sem efeito de venda definitiva?', options: ['NF-e de venda', 'Condicional', 'CT-e', 'MDF-e'], correct: 1, explanation: 'O condicional é usado para envio de mercadoria que pode retornar, mantendo controle do que está temporariamente fora do estoque físico da loja.' },
  { category: 'estoque-saida', question: 'Na Ordem de Serviço, o que deve ser cadastrado para permitir o histórico de atendimentos?', options: ['O Objeto (equipamento/veículo) do cliente', 'O plano de contas', 'O grupo de tributação', 'A condição de pagamento'], correct: 0, explanation: 'O cadastro do objeto atendido permite rastrear o histórico de serviços realizados sobre aquele bem específico do cliente.' },
  { category: 'estoque-saida', question: 'O que acontece na etapa de Faturamento?', options: ['Apenas o orçamento é criado', 'O pedido/OS é convertido em documento fiscal definitivo, baixando o estoque e gerando o financeiro', 'O produto é cadastrado pela primeira vez', 'O backup do sistema é realizado'], correct: 1, explanation: 'O faturamento converte o pedido/OS em nota fiscal definitiva, efetivando a baixa de estoque e o lançamento financeiro a receber.' },
  { category: 'estoque-saida', question: 'Por que é importante fechar a apuração de comissão somente após o fechamento das devoluções do mês?', options: ['Para evitar pagar comissão sobre venda que foi devolvida/estornada', 'Porque o sistema não permite calcular antes', 'Para atrasar o pagamento aos vendedores', 'Não há relação entre os dois processos'], correct: 0, explanation: 'Calcular a comissão antes do fechamento das devoluções pode gerar pagamento indevido sobre vendas que foram posteriormente estornadas.' },
  { category: 'estoque-saida', question: 'O que os perfis de bloqueio por Atraso, Conceito e Limite de Crédito ajudam a controlar?', options: ['O estoque físico da loja', 'O risco de inadimplência nas vendas a prazo', 'A emissão de etiquetas', 'O backup do banco de dados'], correct: 1, explanation: 'Esses perfis alertam ou bloqueiam vendas para clientes com histórico de atraso, mau conceito de crédito ou limite excedido, reduzindo o risco de inadimplência.' },
  // Fiscal
  { category: 'fiscal', question: 'Qual código é usado por empresas do Simples Nacional para identificar a situação tributária?', options: ['CST', 'CSOSN', 'CFOP', 'NCM'], correct: 1, explanation: 'O CSOSN é utilizado especificamente por empresas optantes pelo Simples Nacional; o CST é usado por Lucro Presumido/Real.' },
  { category: 'fiscal', question: 'O que o CFOP indica em uma nota fiscal?', options: ['O valor do frete', 'A finalidade/natureza da operação (venda, compra, transferência, etc.)', 'O nome do transportador', 'A alíquota de ISS'], correct: 1, explanation: 'O CFOP (Código Fiscal de Operações e Prestações) identifica a natureza da operação praticada, como venda, compra ou devolução.' },
  { category: 'fiscal', question: 'Qual documento efetivamente possui validade fiscal: o XML ou o DANFE impresso?', options: ['Apenas o DANFE impresso', 'O XML', 'Nenhum dos dois, apenas o carimbo do fiscal', 'Ambos têm o mesmo valor, mas o papel é preferido'], correct: 1, explanation: 'O XML é o documento eletrônico autorizado pela SEFAZ e possui validade fiscal; o DANFE é apenas sua representação impressa auxiliar.' },
  { category: 'fiscal', question: 'O que é o CEST?', options: ['Um tipo de imposto federal', 'Um código que identifica mercadorias sujeitas à Substituição Tributária', 'Um relatório de vendas', 'Um tipo de certificado digital'], correct: 1, explanation: 'O CEST identifica, junto ao NCM, mercadorias sujeitas ao regime de Substituição Tributária, exigindo o cálculo da MVA correspondente.' },
  { category: 'fiscal', question: 'Qual documento acoberta a prestação de serviço de transporte de cargas?', options: ['NFS-e', 'CT-e', 'MDF-e', 'NFC-e'], correct: 1, explanation: 'O CT-e (Conhecimento de Transporte Eletrônico) é o documento fiscal que acoberta a prestação de serviço de transporte de cargas.' },
  { category: 'fiscal', question: 'Para que serve o MDF-e?', options: ['Emitir nota de serviço', 'Agrupar e vincular diversas NF-e/CT-e transportadas em um mesmo veículo', 'Substituir o certificado digital', 'Calcular o ISS devido'], correct: 1, explanation: 'O MDF-e agrupa os documentos fiscais (NF-e/CT-e) transportados em um mesmo veículo, sendo exigido em determinadas operações.' },
  { category: 'fiscal', question: 'Qual tabela é usada para classificar corretamente um serviço tributado pelo ISS?', options: ['NCM', 'Lista de Serviços LC 116/03', 'CFOP', 'CEST'], correct: 1, explanation: 'A Lista de Serviços da LC 116/03 é a referência nacional para classificar os serviços tributados pelo ISS na emissão da NFS-e.' },
  // Configurador
  { category: 'configurador', question: 'O que controlam as "Séries e Numerações" no Configurador?', options: ['O valor dos impostos', 'A sequência numérica dos documentos fiscais e internos', 'O layout de impressão dos relatórios', 'As permissões de usuário'], correct: 1, explanation: 'As séries e numerações garantem uma sequência correta e sem duplicidade para documentos como NF-e, orçamentos e ordens de serviço.' },
  { category: 'configurador', question: 'Por que se deve ter cuidado ao alterar Parâmetros Gerais do sistema?', options: ['Porque eles não podem ser alterados depois de definidos', 'Porque a mudança afeta o comportamento do sistema para todos os usuários', 'Porque geram cobrança extra', 'Porque somente o SYSDBA pode acessá-los'], correct: 1, explanation: 'Os parâmetros gerais têm efeito global, impactando o comportamento do sistema para todas as estações e usuários simultaneamente.' },
  { category: 'configurador', question: 'O que define um "Tipo de Movimento" no TGA ERP?', options: ['Apenas o nome do relatório de vendas', 'Se a operação soma ou subtrai saldo de estoque e se gera título financeiro', 'A senha de acesso ao sistema', 'O certificado digital da empresa'], correct: 1, explanation: 'O tipo de movimento define o comportamento da operação no estoque e financeiro, incluindo o sinal do saldo e a geração de títulos.' },
  // Financeiro
  { category: 'financeiro', question: 'O que é necessário configurar corretamente no cadastro de Portadores para emissão de boletos?', options: ['O NCM do produto', 'Os dados de convênio, carteira e código do cliente junto ao banco', 'A lista de serviços LC 116/03', 'O CFOP de devolução'], correct: 1, explanation: 'Dados de convênio incorretos no portador fazem com que o banco não reconheça ou não processe corretamente os boletos gerados.' },
  { category: 'financeiro', question: 'Qual é a diferença entre Remessa e Retorno de boletos?', options: ['Remessa envia dados ao banco; Retorno traz a confirmação de pagamentos/ocorrências', 'São a mesma coisa com nomes diferentes', 'Remessa é usada apenas para PIX', 'Retorno é usado apenas para cancelar boletos'], correct: 0, explanation: 'O arquivo de Remessa envia ao banco os boletos a registrar; o de Retorno traz informações sobre pagamentos, baixas e ocorrências.' },
  { category: 'financeiro', question: 'Para que serve a Compensação de Extratos?', options: ['Cadastrar um novo cliente', 'Vincular lançamentos do extrato bancário real às baixas já registradas no sistema', 'Emitir a NF-e de venda', 'Gerar etiquetas de produto'], correct: 1, explanation: 'A compensação garante que o saldo do sistema bata com o saldo real do banco, identificando divergências não conciliadas.' },
  { category: 'financeiro', question: 'O que deve ser feito ao final do expediente em relação à Sessão de Caixa?', options: ['Deixar aberta para o próximo turno usar', 'Fechar a sessão conferindo o valor físico com o valor apurado pelo sistema', 'Excluir a sessão do sistema', 'Gerar um novo usuário de caixa'], correct: 1, explanation: 'O fechamento da sessão de caixa deve conferir o valor físico em caixa com o valor apurado pelo sistema, registrando eventuais diferenças.' },
  { category: 'financeiro', question: 'Qual é o risco de estornar uma baixa que já foi conciliada no extrato bancário?', options: ['Nenhum risco, é uma operação sempre segura', 'Pode gerar divergência na conciliação bancária', 'O sistema trava automaticamente', 'O cliente é bloqueado para novas compras'], correct: 1, explanation: 'Estornar uma baixa já conciliada desfaz um vínculo que já havia sido confirmado com o extrato real, exigindo nova conciliação cuidadosa.' },
  // FAQ / Atendimento
  { category: 'faq', question: 'Ao abrir um chamado de suporte, qual informação é essencial para agilizar o diagnóstico?', options: ['Apenas o nome do usuário', 'O passo a passo exato realizado até o erro aparecer, com prints/mensagens', 'O horário de almoço do usuário', 'O nome do concorrente utilizado antes'], correct: 1, explanation: 'Descrever o passo a passo e anexar evidências (prints, XML, mensagens de erro) agiliza significativamente o diagnóstico do problema.' },
  { category: 'faq', question: 'O que NUNCA se deve fazer durante um atendimento remoto ao cliente?', options: ['Pedir print do erro', 'Pedir a senha do usuário do cliente para "testar" diretamente', 'Reproduzir o erro em ambiente de teste', 'Classificar a urgência do chamado'], correct: 1, explanation: 'Solicitar a senha do usuário do cliente é uma prática insegura; deve-se usar acesso remoto autorizado ou ambiente de homologação.' },
  { category: 'faq', question: 'Se uma nota fiscal foi rejeitada pela SEFAZ, qual é o primeiro passo recomendado?', options: ['Reemitir imediatamente sem alterações', 'Consultar o motivo da rejeição e corrigir o dado indicado antes de reemitir', 'Cancelar definitivamente a venda', 'Ignorar, pois a rejeição não tem efeito'], correct: 1, explanation: 'É preciso identificar o motivo exato da rejeição (ex.: CST, NCM, dados do destinatário) e corrigi-lo antes de tentar uma nova emissão.' },
  // Regimes Tributários / Reforma Tributária
  { category: 'fiscal', question: 'Uma empresa passou por uma troca de regime tributário. Onde, dentro do sistema, deve ser alterado o Código de Regime Tributário (CRT)?', options: ['Dentro do cadastro de Empresas, na aba "Outros dados"', 'Dentro do cadastro da Filial, na aba "Outros dados"', 'No cadastro de "Tributos"', 'Dentro do cadastro da Filial, na aba "Dados Fiscais"'], correct: 3, explanation: 'O CRT é um dado fiscal da Filial e deve ser alterado na aba "Dados Fiscais" do cadastro da Filial.' },
  { category: 'fiscal', question: 'Uma empresa passou a ser tributada no regime Lucro Real. Quais alíquotas de PIS/COFINS são corretas para os produtos tributados (regime não-cumulativo)?', options: ['1,65% para o PIS e 7,00% para o COFINS', '0,65% para o PIS e 3,00% para o COFINS', '1,65% para o PIS e 7,60% para o COFINS', '0,65% para o PIS e 7,60% para o COFINS'], correct: 2, explanation: 'No regime não-cumulativo (Lucro Real), as alíquotas padrão são 1,65% de PIS e 7,60% de COFINS. Já 0,65%/3,00% são as alíquotas do regime cumulativo (Lucro Presumido).' },
  { category: 'fiscal', question: 'Em uma nota com ICMS, COFINS, ICMS-ST, IPI e PIS, qual a ordem de cálculo correta desses tributos na configuração do movimento, para evitar erros automáticos?', options: ['ICMS, COFINS, IPI e PIS', 'IPI, ICMS, ICMS-ST, PIS e COFINS', 'IPI, PIS, COFINS e ICMS', 'PIS, COFINS, ICMS e IPI'], correct: 1, explanation: 'A ordem lógica de dependência é: IPI primeiro (pode afetar a base), depois ICMS, em seguida ICMS-ST (que depende do ICMS calculado), e por fim PIS e COFINS.' },
  { category: 'fiscal', question: 'É correto destacar PIS e COFINS em uma nota fiscal de bonificação, sem valor comercial?', options: ['Sim, sempre', 'Não'], correct: 1, explanation: 'Bonificação não gera receita, e a base de cálculo do PIS/COFINS é a receita bruta — por isso, itens bonificados não devem compor o destaque desses tributos.' },
  { category: 'fiscal', question: 'Uma empresa saiu do Simples Nacional e passou para o Simples Nacional Excesso de Sub-limite de Receita. Qual tributo ela passará a destacar separadamente nas notas fiscais?', options: ['PIS/COFINS', 'CSLL, IRPJ', 'ICMS', 'IPI'], correct: 2, explanation: 'No excesso de sub-limite de receita bruta, a empresa continua no Simples para os tributos federais, mas passa a recolher o ICMS (e/ou ISS) por fora, destacado separadamente.' },
  { category: 'fiscal', question: 'Em um questionário fiscal de uma empresa do Simples Nacional, qual é a combinação correta de CFOP de venda e situação tributária?', options: ['CFOP 5.102 com CST 00', 'CFOP 5.102 com CSOSN 900', 'CFOP 5.102 com CSOSN 101/102', 'CFOP 5.405 com CST 00'], correct: 2, explanation: 'Empresas do Simples Nacional utilizam CSOSN (não CST). Para uma venda tributada padrão, o CFOP 5.102 combinado com CSOSN 101 ou 102 é o correto.' },
  { category: 'fiscal', question: 'Sobre notas fiscais de devolução de venda, assinale a alternativa correta:', options: ['O CFOP deverá ser o mesmo da nota de venda, servindo apenas para dar entrada no estoque fiscal', 'Apenas a empresa emissora da nota de venda poderá fazer a nota de devolução', 'Os tributos devem sair todos zerados, pois a devolução cancela a operação de venda', 'O CFOP deve ser de devolução de venda de mercadoria, de acordo com a situação tributária do produto, destacando os tributos envolvidos na venda e, em alguns casos, o PIS/COFINS'], correct: 3, explanation: 'A devolução deve usar um CFOP próprio de devolução, respeitando a tributação do produto e destacando os tributos pertinentes, podendo incluir PIS/COFINS conforme o caso.' },
  { category: 'fiscal', question: 'Qual o principal objetivo da Lei nº 12.741/2012 (Lei da Transparência Fiscal)?', options: ['Simplificar o cálculo de impostos para as empresas', 'Trazer um valor simbólico e sem relevância, opcional para a empresa', 'Informar o consumidor sobre a carga tributária aproximada incidente sobre o que está adquirindo', 'Definir o valor do ICMS sobre o total dos itens tributados da NF-e'], correct: 2, explanation: 'A Lei da Transparência Fiscal exige que o consumidor seja informado sobre o valor aproximado dos tributos incidentes sobre produtos e serviços.' },
  { category: 'reforma-tributaria', question: 'Quais são os dois novos tributos criados pela Reforma Tributária do Consumo, cuja soma forma o IVA brasileiro?', options: ['ICMS e ISS', 'IBS e CBS', 'PIS e COFINS', 'IPI e ISS'], correct: 1, explanation: 'O IVA brasileiro é formado pela soma do IBS (estadual/municipal) com o CBS (federal).' },
  { category: 'reforma-tributaria', question: 'A CBS (Contribuição sobre Bens e Serviços) substituirá quais tributos federais?', options: ['ICMS e ISS', 'IPI e ICMS', 'PIS e COFINS', 'IRPJ e CSLL'], correct: 2, explanation: 'A CBS, de competência federal, substituirá o PIS e a COFINS.' },
  { category: 'reforma-tributaria', question: 'No sistema TGA, qual versão mínima é necessária para configurar a Reforma Tributária (IBS/CBS)?', options: ['24.01.01', '25.11.01', '20.10.05', 'Não é necessário atualizar'], correct: 1, explanation: 'É obrigatório atualizar o sistema para a versão 25.11.01 ou superior antes de iniciar a configuração da Reforma Tributária.' },
  { category: 'reforma-tributaria', question: 'Segundo o cronograma de transição, o que ocorre em 2033?', options: ['Início dos testes de CBS e IBS', 'Extinção da CBS apenas', 'Extinção total de PIS, COFINS, ICMS e ISS, com adoção definitiva do modelo CBS + IBS', 'Redução de 10% do ICMS apenas'], correct: 2, explanation: 'Em 2033 ocorre a extinção total dos tributos antigos (PIS, COFINS, ICMS, ISS) e a adoção definitiva e integral do modelo CBS + IBS.' },
  { category: 'reforma-tributaria', question: 'Quais tipos de movimento NÃO devem receber os tributos IBS e CBS?', options: ['Vendas com NF-e/NFC-e', 'Compras para revenda', 'Remessas, transferências entre filiais e bonificações, por não terem caráter oneroso', 'Devoluções de venda'], correct: 2, explanation: 'IBS e CBS incidem apenas sobre operações onerosas. Remessas, transferências entre filiais e bonificações não devem receber esses tributos.' },
  // ===== Lote 2 de perguntas (gerado a partir do conteúdo já existente na Base de Conhecimento) =====
  // Introdução ao Sistema
  { category: 'introducao', question: 'O que deve ser configurado nas estações clientes durante a instalação do TGA ERP?', options: ['O tema visual do sistema', 'O IP/nome do servidor de banco de dados', 'A impressora padrão do Windows', 'O idioma do teclado'], correct: 1, explanation: 'Cada estação cliente precisa apontar para o IP/nome do servidor onde o banco de dados Firebird está hospedado.' },
  { category: 'introducao', question: 'O que NÃO deve ser feito ao executar rotinas de manutenção/reindexação do banco de dados pelo Menu Utilitários?', options: ['Garantir um backup recente antes', 'Executar em horário de baixo movimento', 'Executar com usuários ainda conectados ao sistema', 'Consultar o log de auditoria depois'], correct: 2, explanation: 'Rotinas de reindexação/recompactação do banco não devem ser executadas com usuários conectados ao sistema, sob risco de corrupção de dados.' },
  { category: 'introducao', question: 'Por que memorizar atalhos de teclado é recomendado no dia a dia do TGA?', options: ['Porque o mouse não funciona no sistema', 'Porque agiliza muito o atendimento em casos de lentidão de rede', 'Porque os menus são removidos a cada atualização', 'O sistema não possui atalhos de teclado'], correct: 1, explanation: 'Depender apenas do caminho do mouse pode ser mais lento em cenários de rede lenta — os atalhos de teclado agilizam a navegação diária.' },
  // Cadastros Gerais
  { category: 'cadastros', question: 'Por que desativar o acesso de um funcionário desligado no mesmo dia do desligamento é importante?', options: ['Para liberar espaço em disco no servidor', 'Porque manter o acesso ativo é uma falha de segurança', 'Porque o sistema cobra por usuário ativo', 'Não é necessário, o sistema desativa sozinho'], correct: 1, explanation: 'Funcionário desligado com usuário ainda ativo é uma falha de segurança — o acesso deve ser desativado no mesmo dia.' },
  { category: 'cadastros', question: 'Qual o risco de configurar campos complementares em excesso como obrigatórios?', options: ['Deixa o cadastro mais lento de preencher', 'Impede o cadastro de produtos definitivamente', 'Substitui os campos padrão do sistema', 'Gera cobrança adicional'], correct: 0, explanation: 'Excesso de campos obrigatórios deixa o cadastro mais lento — o ideal é criar apenas os campos complementares realmente necessários.' },
  { category: 'cadastros', question: 'Nas telas de Pesquisa do TGA, qual prática ajuda a localizar um registro quando não se lembra do nome completo?', options: ['Cadastrar um novo registro parecido', 'Utilizar filtro por parte do nome combinado com outros critérios', 'Ligar para o suporte técnico', 'Reiniciar o sistema'], correct: 1, explanation: 'Utilizar filtros combinados (como nome parcial + cidade) e pesquisar por parte do nome amplia os resultados e agiliza a localização do registro.' },
  { category: 'cadastros', question: 'Para que servem os Departamentos cadastrados no sistema?', options: ['Substituir o cadastro de funcionários', 'Organizar a empresa por área e segmentar relatórios e permissões', 'Emitir boletos automaticamente', 'Definir o CFOP padrão de vendas'], correct: 1, explanation: 'Departamentos organizam a empresa por área (Vendas, Financeiro, Estoque, Oficina) e são usados para segmentar relatórios e permissões.' },
  { category: 'cadastros', question: 'Os Tipos de Documento cadastrados no sistema servem para identificar:', options: ['A natureza do arquivo/registro usado em processos internos (NF-e, boleto, contrato, recibo)', 'O regime tributário da empresa', 'O saldo atual do estoque', 'A senha de acesso do usuário'], correct: 0, explanation: 'Os tipos de documento identificam a natureza do arquivo/registro utilizado em processos internos e de anexação no sistema.' },
  // Estoque — Entrada
  { category: 'estoque-entrada', question: 'O que deve ser verificado antes de cadastrar um novo Cliente/Fornecedor no sistema?', options: ['Se o CPF/CNPJ já existe cadastrado, para evitar duplicidade', 'Se o produto está com o preço correto', 'Se a sessão de caixa está aberta', 'Se o backup foi feito hoje'], correct: 0, explanation: 'Pesquisar o CPF/CNPJ antes de cadastrar evita a criação de registros duplicados na base de clientes/fornecedores.' },
  { category: 'estoque-entrada', question: 'Qual a vantagem de cadastrar condições de pagamento padrão (à vista, 30 dias, 30/60/90)?', options: ['Não há vantagem prática', 'Agilizar a digitação de pedidos de compra e venda', 'Reduzir o estoque automaticamente', 'Gerar a NF-e automaticamente'], correct: 1, explanation: 'Ter condições de pagamento padrão já cadastradas agiliza a digitação no momento do pedido de compra ou venda.' },
  { category: 'estoque-entrada', question: 'Antes de configurar o layout de uma Emissão de Etiqueta de Produtos, o que deve ser conferido?', options: ['O regime tributário da empresa', 'O modelo da impressora utilizada (térmica x jato de tinta)', 'O centro de custo do produto', 'A condição de pagamento do cliente'], correct: 1, explanation: 'Conferir o modelo da impressora antes de configurar o layout evita distorção do código de barras na etiqueta gerada.' },
  // Estoque — Saída
  { category: 'estoque-saida', question: 'O que caracteriza o funcionamento do PDV Offline (PDV OFF)?', options: ['Permite continuar vendendo mesmo com a conexão ao servidor instável, sincronizando depois', 'Substitui totalmente a necessidade de nota fiscal', 'Funciona apenas para vendas a prazo', 'Fecha automaticamente o caixa da loja'], correct: 0, explanation: 'O PDV OFF permite que a frente de caixa continue vendendo com a conexão instável ou indisponível, sincronizando as vendas quando a conexão volta.' },
  { category: 'estoque-saida', question: 'Vendas realizadas em modo PDV OFF só se tornam definitivas quando:', options: ['O cliente confirma o recebimento por e-mail', 'Ocorre a sincronização das vendas com o servidor', 'O caixa é fechado ao final do dia', 'Elas nunca se tornam definitivas'], correct: 1, explanation: 'Vendas feitas em modo offline só se tornam definitivas após a sincronização — por isso não se deve finalizar o caixa sem confirmar que tudo foi sincronizado.' },
  { category: 'estoque-saida', question: 'Para que serve o relatório de curva ABC mencionado no Relatório de Estoque?', options: ['Calcular o ISS devido pela empresa', 'Priorizar os itens de maior impacto financeiro no estoque', 'Emitir boletos em lote', 'Cadastrar novos fornecedores'], correct: 1, explanation: 'O relatório de curva ABC ajuda a priorizar os itens de maior impacto financeiro dentro da posição de estoque.' },
  { category: 'estoque-saida', question: 'Além da emissão da nota fiscal de devolução, o que pode ser necessário tratar em uma Devolução de Vendas?', options: ['O certificado digital da empresa', 'O estorno do título financeiro (crédito/reembolso)', 'A liberação de porta no firewall', 'O cadastro de novos departamentos'], correct: 1, explanation: 'Além de emitir a nota fiscal de devolução, pode ser necessário tratar o estorno do título financeiro gerado pela venda original.' },
  // Módulo Fiscal
  { category: 'fiscal', question: 'O que a configuração correta dos Tributos por grupo de produto/serviço ajuda a evitar?', options: ['Recolhimento a menor (multa) ou a maior (prejuízo) de impostos', 'Erros no saldo de estoque físico', 'Erros no cadastro de departamentos', 'Perda do arquivo de backup'], correct: 0, explanation: 'Uma configuração tributária incorreta pode gerar recolhimento a menor (com risco de multa) ou a maior (com prejuízo) de impostos.' },
  { category: 'fiscal', question: 'O leiaute da NFS-e é definido conforme as regras de quem?', options: ['Apenas da Receita Federal', 'Da prefeitura do município', 'Do fabricante do sistema', 'Do banco do cliente'], correct: 1, explanation: 'A NFS-e é emitida conforme o layout definido pela prefeitura do município, que pode variar de cidade para cidade.' },
  // Módulo Configurador
  { category: 'configurador', question: 'Para que servem as Ações de Impressão no Configurador?', options: ['Definir quais documentos são impressos/disponibilizados automaticamente após uma rotina', 'Calcular o ICMS-ST dos produtos', 'Cadastrar centros de custo', 'Liberar portas no firewall'], correct: 0, explanation: 'As Ações de Impressão definem quais documentos (orçamento, cupom, DANFE, etiqueta) são impressos automaticamente após uma rotina.' },
  { category: 'configurador', question: 'Antes de liberar um novo modelo de impressão em produção, o que é recomendado?', options: ['Liberar direto, sem testes', 'Testar em ambiente de homologação', 'Avisar a SEFAZ previamente', 'Desligar o servidor de banco de dados'], correct: 1, explanation: 'Testar a impressão em ambiente de homologação antes de liberar em produção evita problemas de layout no dia a dia.' },
  { category: 'configurador', question: 'O que acontece se a numeração do sistema divergir da numeração autorizada na SEFAZ?', options: ['Nada, o sistema ajusta automaticamente', 'A emissão de novos documentos fiscais fica travada', 'O backup do banco é apagado', 'O cliente é bloqueado por contrassenha'], correct: 1, explanation: 'Divergência entre a numeração do sistema e a autorizada na SEFAZ trava a emissão de novos documentos fiscais.' },
  { category: 'configurador', question: 'Os "Outros Movimentos" configurados no Tipo de Movimento incluem, por exemplo:', options: ['Venda no PDV e faturamento de OS', 'Transferência entre filiais, remessa para conserto e bonificação', 'Emissão de boletos bancários', 'Backup do banco de dados'], correct: 1, explanation: 'Os "Outros Movimentos" englobam operações como transferência entre filiais, remessa para conserto e bonificação.' },
  { category: 'configurador', question: 'Por que alterar Parâmetros Gerais durante o horário de movimento é arriscado?', options: ['Porque pode gerar comportamento inesperado para quem já está com a tela aberta', 'Porque desliga o serviço do Firebird automaticamente', 'Porque cancela vendas em andamento automaticamente', 'Não há risco nenhum'], correct: 0, explanation: 'Como os parâmetros gerais afetam todo o sistema, alterá-los durante o movimento pode gerar comportamento inesperado para usuários já conectados.' },
  // Módulo Financeiro
  { category: 'financeiro', question: 'Por que é recomendado separar "PIX" e "Dinheiro" como formas de pagamento distintas, mesmo sendo ambas à vista?', options: ['Para facilitar a conciliação de caixa', 'Porque o sistema não aceita PIX como forma de pagamento', 'Para gerar boletos automaticamente', 'Não há motivo, elas devem ser a mesma forma'], correct: 0, explanation: 'Separar as formas de pagamento, mesmo sendo ambas à vista, facilita a conciliação de caixa por forma de recebimento.' },
  { category: 'financeiro', question: 'O que pode gerar divergência entre financeiro e fiscal ao usar a Manutenção de Lançamentos?', options: ['Cadastrar um novo centro de custo', 'Alterar valor ou vencimento de um título já vinculado a uma nota fiscal', 'Apenas consultar um lançamento existente', 'Gerar um relatório de lançamentos'], correct: 1, explanation: 'Alterar valor ou vencimento de um título já vinculado a uma nota fiscal pode gerar divergência entre os setores financeiro e fiscal.' },
  { category: 'financeiro', question: 'A configuração de "Registradora" no TGA está relacionada a quê?', options: ['Ao cadastro de clientes e fornecedores', 'À integração com o equipamento de frente de caixa (impressora fiscal/SAT/ECF)', 'Ao cálculo do ISS devido', 'À liberação de portas de rede no firewall'], correct: 1, explanation: 'A Registradora trata da integração entre o sistema e o equipamento de frente de caixa usado na emissão de cupons no PDV.' },
  { category: 'financeiro', question: 'O que a Integração Bancária automatiza no TGA?', options: ['A troca de arquivos de cobrança (remessa/retorno) com o banco', 'A emissão de etiquetas de produto', 'O cadastro de novos funcionários', 'A criação de tipos de movimento'], correct: 0, explanation: 'A Integração Bancária automatiza a comunicação com o banco para troca de arquivos de remessa/retorno e, em alguns casos, extrato automático.' },
  { category: 'financeiro', question: 'Para que servem os Relatórios Pagar/Receber?', options: ['Consolidar títulos por vencimento, fornecedor/cliente e status, auxiliando o planejamento de caixa', 'Emitir diretamente a NF-e de venda', 'Configurar o servidor Firebird', 'Cadastrar novos produtos'], correct: 0, explanation: 'Os Relatórios Pagar/Receber consolidam a posição de títulos por vencimento e status, servindo de base para o planejamento do fluxo de caixa.' },
  // Atendimento ao Cliente & FAQ
  { category: 'faq', question: 'Antes de propor qualquer solução a um cliente durante o atendimento, o que deve ser feito?', options: ['Cobrar pelo atendimento antecipadamente', 'Ouvir/ler o relato completo e confirmar o entendimento do problema', 'Encerrar o chamado imediatamente', 'Pedir para reinstalar o sistema do zero'], correct: 1, explanation: 'O atendimento deve sempre confirmar o entendimento do problema relatado antes de propor qualquer solução ao cliente.' },
  { category: 'faq', question: 'Antes de escalonar um chamado ao nível técnico responsável, o que deve ser feito?', options: ['Nada, pode escalonar direto sem detalhes', 'Verificar se as informações mínimas foram coletadas e descrever as tentativas já realizadas', 'Cancelar o chamado do cliente', 'Solicitar a senha do usuário do cliente'], correct: 1, explanation: 'Escalonar sem contexto suficiente apenas transfere o retrabalho para o próximo nível — é preciso descrever as tentativas de solução já realizadas.' },
  // Perguntas e Respostas — Geral
  { category: 'perguntas-gerais', question: 'Qual a faixa de numeração correta para o tipo de movimento "Venda de Mercadoria"?', options: ['1.2.XX', '2.2.XX', '2.1.XX', '4.1.XX'], correct: 1, explanation: 'A Venda de Mercadoria segue a faixa de numeração 2.2.XX no cadastro de tipos de movimento do TGA.' },
  { category: 'perguntas-gerais', question: 'Qual a faixa de numeração correta para o tipo de movimento "Compra de Mercadoria"?', options: ['1.2.XX', '2.2.XX', '1.3.XX', '1.1.XX'], correct: 0, explanation: 'A Compra de Mercadoria segue a faixa de numeração 1.2.XX no cadastro de tipos de movimento do TGA.' },
  { category: 'perguntas-gerais', question: 'O que representa o Ponto de Equilíbrio na formação de preço de um produto?', options: ['O preço de fábrica do produto', 'O valor mínimo de venda que não gera nem prejuízo nem lucro', 'O custo médio do produto em estoque', 'O valor do frete pago na compra'], correct: 1, explanation: 'O Ponto de Equilíbrio é o valor mínimo de venda que não gera nem prejuízo nem lucro para a empresa.' },
  { category: 'perguntas-gerais', question: 'Para que serve, normalmente, o campo Conceito no cadastro do cliente?', options: ['Validar a situação cadastral/financeira do cliente na liberação de vendas a prazo', 'Definir o preço de venda dos produtos', 'Gerar boletos automaticamente', 'Cadastrar o CFOP padrão de vendas'], correct: 0, explanation: 'O campo Conceito é utilizado, normalmente, para validar a situação cadastral/financeira do cliente na liberação ou não de vendas a prazo.' },
  { category: 'perguntas-gerais', question: 'Quais dados o Dashboard BI do módulo Estoque reúne?', options: ['Apenas o saldo de caixa da empresa', 'Análise de Vendas, Estoque, NF-e/NFC-e por status e Ordens de Serviço', 'Somente o backup do banco de dados', 'Somente o cadastro de funcionários'], correct: 1, explanation: 'O Dashboard BI do módulo Estoque reúne análise de Vendas, Estoque, NF-e e NFC-e por status, e Ordens de Serviço.' },
  { category: 'perguntas-gerais', question: 'Qual o prazo para cancelamento de uma NFC-e após a autorização?', options: ['8 horas', '24 horas', '30 minutos', '7 dias'], correct: 2, explanation: 'A NFC-e pode ser cancelada em até 30 minutos após a autorização.' },
  { category: 'perguntas-gerais', question: 'Qual o prazo para cancelamento de uma NF-e após a autorização?', options: ['30 minutos', '8 horas', '48 horas', 'Não há prazo definido'], correct: 1, explanation: 'A NF-e pode ser cancelada em até 8 horas após a autorização.' },
  { category: 'perguntas-gerais', question: 'Como é possível abrir os módulos Financeiro e Estoque ao mesmo tempo na mesma estação?', options: ['Não é possível abrir os dois ao mesmo tempo', 'Instalando e utilizando Múltiplas Instâncias do sistema', 'Apenas reiniciando o computador', 'Usando dois usuários diferentes na mesma tela'], correct: 1, explanation: 'É possível abrir dois módulos ao mesmo tempo instalando e utilizando Múltiplas Instâncias do sistema na mesma estação.' },
  { category: 'perguntas-gerais', question: 'O que significa uma NF-e com status "Denegada"?', options: ['A nota foi autorizada normalmente pela SEFAZ', 'Há um problema na situação fiscal do emitente ou destinatário perante a SEFAZ', 'A nota foi paga em dinheiro', 'O produto vendido está sem estoque'], correct: 1, explanation: 'O status "Denegada" indica um problema na situação fiscal do emitente ou do destinatário perante a SEFAZ, impedindo a autorização normal.' },
  // Perguntas e Respostas — Fiscal
  { category: 'perguntas-fiscais', question: 'Qual é o modelo de documento fiscal correspondente à NFC-e?', options: ['Modelo 55', 'Modelo 65', 'Modelo 21', 'Modelo 57'], correct: 1, explanation: 'A NFC-e (Nota Fiscal de Consumidor Eletrônica) é o modelo 65.' },
  { category: 'perguntas-fiscais', question: 'Qual é o modelo de documento fiscal correspondente à NF-e?', options: ['Modelo 65', 'Modelo 55', 'Modelo 06', 'Modelo 22'], correct: 1, explanation: 'A NF-e (Nota Fiscal Eletrônica) é o modelo 55, com informações mais detalhadas que a NFC-e.' },
  { category: 'perguntas-fiscais', question: 'A NFC-e veio para substituir qual documento?', options: ['O Cupom Fiscal', 'O CT-e', 'O MDF-e', 'A NFS-e'], correct: 0, explanation: 'A NFC-e veio para substituir o Cupom Fiscal, simplificando o preenchimento de dados nas vendas de varejo.' },
  { category: 'perguntas-fiscais', question: 'Em quais casos uma empresa do Simples Nacional também pode utilizar CST em vez de CSOSN?', options: ['Nunca — o Simples Nacional só utiliza CSOSN', 'Em exceções como venda de gás e emissões de CT-e', 'Apenas em vendas interestaduais', 'Apenas para produtos importados'], correct: 1, explanation: 'Há exceções em que empresas do Simples Nacional também usam CST, como na venda de gás e em emissões de CT-e.' },
  // Reforma Tributária (IBS/CBS)
  { category: 'reforma-tributaria', question: 'Qual fórmula deve ser cadastrada no TGA para calcular a base de cálculo do IBS/CBS?', options: ['Fórmula 100', 'Fórmula 200', 'Fórmula 300', 'Não é necessário cadastrar fórmula'], correct: 1, explanation: 'A Fórmula 200 é a responsável por calcular corretamente a base de cálculo do IBS e do CBS no sistema TGA.' },
  { category: 'reforma-tributaria', question: 'Onde deve ser feita a importação da Classificação Tributária (CCLASSTRIB)?', options: ['Módulo Financeiro → Bancos', 'Módulo Estoque → Fiscais → Classificação Tributária → Importar via API', 'Módulo Configurador → Parâmetros', 'Cadastros → Funcionários'], correct: 1, explanation: 'A Classificação Tributária deve ser importada via API em Módulo Estoque → Fiscais → Classificação Tributária.' },
  // ===== Lote 3 de perguntas (gerado a partir do conteúdo já existente na Base de Conhecimento) =====
  // Introdução ao Sistema
  { category: 'introducao', question: 'O que fica impedido de ser emitido caso o certificado digital da empresa esteja vencido?', options: ['A licença do Windows', 'NF-e/NFC-e', 'O relatório de estoque', 'O plano de contas'], correct: 1, explanation: 'Certificado digital vencido impede a emissão de NF-e/NFC-e — sua validade deve ser monitorada com antecedência.' },
  { category: 'introducao', question: 'Qual princípio de segurança deve orientar a definição de permissões de um perfil de acesso?', options: ['Dar acesso total a todos os usuários por padrão', 'O princípio do menor privilégio (acesso apenas ao necessário para a função)', 'Nunca revisar os perfis após criados', 'Compartilhar o mesmo perfil entre todos os setores'], correct: 1, explanation: 'Deve-se aplicar o princípio do menor privilégio: o usuário tem acesso apenas ao que precisa para exercer sua função.' },
  // Cadastros Gerais
  { category: 'cadastros', question: 'Como deve ser estruturado um bom Plano de Contas?', options: ['Em uma única conta genérica chamada "Diversos"', 'Em níveis, com contas sintéticas e analíticas', 'Sem nenhum vínculo com centro de custo', 'Apenas para despesas, nunca para receitas'], correct: 1, explanation: 'Um plano de contas bem estruturado, em níveis (sintéticas e analíticas), é a base para relatórios gerenciais confiáveis.' },
  { category: 'cadastros', question: 'Além dos dados pessoais, a que o cadastro de Funcionários serve de base no sistema?', options: ['Apenas ao cadastro de produtos', 'Ao comissionamento e às Ordens de Serviço', 'Ao cálculo de ICMS', 'À emissão de CT-e'], correct: 1, explanation: 'O cadastro de funcionários é base para comissionamento e Ordens de Serviço, além de armazenar dados pessoais e de cargo.' },
  // Estoque — Entrada
  { category: 'estoque-entrada', question: 'Para que serve o Pedido de Compras mesmo antes da chegada física da mercadoria e da nota fiscal?', options: ['Para gerar o pagamento imediatamente ao fornecedor', 'Como controle de previsão de estoque', 'Para cancelar a compra automaticamente', 'Para gerar a devolução da nota anterior'], correct: 1, explanation: 'O pedido de compra pode ser usado como controle de previsão de estoque, mesmo antes da nota fiscal chegar.' },
  { category: 'estoque-entrada', question: 'O que é recomendado comparar com o Relatório de Compras do mesmo período?', options: ['O relatório de vendas, para avaliar o giro de estoque', 'O relatório de comissão de vendedores', 'O cadastro de funcionários', 'A sessão de caixa'], correct: 0, explanation: 'Comparar o relatório de compras com o de vendas do mesmo período ajuda a avaliar o giro de estoque.' },
  // Estoque — Saída
  { category: 'estoque-saida', question: 'No PDV, qual prática reduz erros de digitação na inclusão de itens?', options: ['Digitar sempre o código manualmente', 'Usar a leitura de código de barras sempre que possível', 'Perguntar o preço ao cliente', 'Aplicar desconto máximo em toda venda'], correct: 1, explanation: 'No PDV, usar a leitura de código de barras sempre que possível reduz erros de digitação de item.' },
  { category: 'estoque-saida', question: 'Ao comparar períodos equivalentes (mês a mês, ano a ano) nos Relatórios de Vendas/Faturamento, o que se busca identificar?', options: ['Sazonalidade nas vendas', 'O valor do ICMS-ST', 'A necessidade de backup do banco', 'Novos vendedores a contratar'], correct: 0, explanation: 'Comparar períodos equivalentes nos relatórios de vendas ajuda a identificar sazonalidade no negócio.' },
  // Módulo Fiscal
  { category: 'fiscal', question: 'O que a Natureza de Operação define em um documento fiscal?', options: ['A senha de acesso do usuário', 'A finalidade da movimentação (venda, compra, transferência, devolução)', 'O saldo do caixa da loja', 'O layout da etiqueta de produto'], correct: 1, explanation: 'A Natureza de Operação define a finalidade da movimentação e está diretamente ligada ao CFOP utilizado na nota.' },
  { category: 'fiscal', question: 'O MD-e (Manifesto do Destinatário Eletrônico) trata de qual processo?', options: ['Emissão de boletos bancários', 'Manifestação eletrônica do destinatário perante a SEFAZ sobre NF-e emitidas contra seu CNPJ', 'Cálculo do ISS devido pela empresa', 'Configuração do serviço do Firebird'], correct: 1, explanation: 'O MD-e refere-se à manifestação eletrônica do destinatário (ciência, confirmação, desconhecimento ou não realização) sobre NF-e emitidas contra seu CNPJ.' },
  // Módulo Configurador
  { category: 'configurador', question: 'Antes de criar um novo Tipo de Movimento no Configurador, o que deve ser verificado?', options: ['Se um tipo de movimento já existente não atende à necessidade', 'Se o Firebird está na versão mais recente', 'Se a contrassenha está liberada', 'Se há espaço em disco no servidor'], correct: 0, explanation: 'Antes de criar um novo tipo de movimento, deve-se verificar se um já existente não atende à necessidade, evitando fragmentar relatórios.' },
  // Módulo Financeiro
  { category: 'financeiro', question: 'O cadastro de Contas/Caixas é a base para quê no sistema?', options: ['Para o cadastro de produtos', 'Para todo lançamento de pagar/receber e controle de saldo', 'Para a emissão de CT-e', 'Para o cadastro de departamentos'], correct: 1, explanation: 'O cadastro de Contas/Caixas representa as contas bancárias e caixas físicos da empresa, base para lançamentos e controle de saldo.' },
  { category: 'financeiro', question: 'Por que é recomendado importar o extrato bancário com frequência (diária/semanal) na Manutenção de Extratos?', options: ['Para não acumular grande volume de conciliação pendente', 'Porque o sistema apaga extratos antigos automaticamente', 'Para gerar boletos automaticamente', 'Não há necessidade de frequência definida'], correct: 0, explanation: 'Importar o extrato com frequência evita acumular um volume grande de conciliação pendente.' },
  { category: 'financeiro', question: 'O que o Extrato de Conta/Caixa exibe em um determinado período?', options: ['Apenas o saldo final, sem detalhes', 'Todas as movimentações (entradas e saídas), com saldo inicial e final', 'Somente os boletos emitidos no período', 'Somente o cadastro do portador'], correct: 1, explanation: 'O Extrato de Conta/Caixa mostra todas as movimentações de um período, com saldo inicial e final.' },
  { category: 'financeiro', question: 'O que acompanhar nos Relatórios de Caixa pode indicar necessidade de treinamento de um operador?', options: ['A recorrência de sobras/faltas de caixa por operador', 'O nome do fabricante da registradora', 'O CFOP utilizado na venda', 'O certificado digital da filial'], correct: 0, explanation: 'Acompanhar a recorrência de sobras/faltas por operador pode indicar necessidade de treinamento ou atenção adicional.' },
  // Atendimento ao Cliente & FAQ
  { category: 'faq', question: 'Além da descrição do problema, o que mais deve constar na abertura de um chamado de suporte?', options: ['Apenas o nome do cliente', 'Empresa/filial, usuário identificador, passo a passo e classificação de urgência', 'O valor da mensalidade do sistema', 'O histórico de compras do cliente'], correct: 1, explanation: 'Todo chamado deve registrar empresa/filial, usuário, passo a passo exato até o erro e a classificação de urgência.' },
  { category: 'faq', question: 'Segundo o FAQ, se o sistema não conecta ao banco de dados, o que deve ser verificado primeiro?', options: ['Se o serviço do Firebird está ativo e se a porta 3050 está liberada', 'Se o certificado digital está vencido', 'Se a contrassenha foi informada', 'Se o CFOP da nota está correto'], correct: 0, explanation: 'O primeiro passo é verificar se o serviço do Firebird está ativo no servidor e se a porta 3050 está liberada.' },
  { category: 'faq', question: 'Ao tentar recuperar um backup, qual é a prática recomendada segundo o FAQ?', options: ['Restaurar direto em produção, sem testar antes', 'Restaurar em ambiente de teste primeiro, apontando para o backup válido mais recente', 'Apagar o banco atual antes de testar a restauração', 'Ignorar a validação do arquivo de backup'], correct: 1, explanation: 'A rotina de restauração deve apontar para o backup mais recente válido, sempre testada em ambiente de teste primeiro.' },
  { category: 'faq', question: 'Quais situações costumam explicar por que o estoque não bate com a contagem física, segundo o FAQ?', options: ['Ajustes de saldo não documentados, devoluções não lançadas ou vendas lançadas em duplicidade', 'Apenas falhas no servidor de banco de dados', 'Falta de backup recente', 'Erro no cadastro de departamentos'], correct: 0, explanation: 'Divergências de estoque costumam vir de ajustes não documentados, devoluções não lançadas ou vendas lançadas em duplicidade.' },
  // Perguntas e Respostas — Geral
  { category: 'perguntas-gerais', question: 'Qual a faixa de numeração correta para o tipo de movimento "Orçamento"?', options: ['1.1.XX', '2.1.XX', '2.2.XX', '4.1.XX'], correct: 1, explanation: 'O Orçamento segue a faixa de numeração 2.1.XX no cadastro de tipos de movimento.' },
  { category: 'perguntas-gerais', question: 'Qual a faixa de numeração correta para o tipo de movimento "Ajuste de Saldo"?', options: ['4.1.XX', '1.2.XX', '2.2.XX', '1.1.XX'], correct: 0, explanation: 'O Ajuste de Saldo segue a faixa de numeração 4.1.XX.' },
  { category: 'perguntas-gerais', question: 'Qual a faixa de numeração correta para o tipo de movimento "Cotação"?', options: ['1.1.XX', '1.3.XX', '2.1.XX', '4.1.XX'], correct: 0, explanation: 'A Cotação segue a faixa de numeração 1.1.XX.' },
  { category: 'perguntas-gerais', question: 'O que representa o Custo Unitário na formação de preço de um produto?', options: ['O preço de fábrica do produto', 'O valor da mercadoria considerando impostos e outras despesas até chegar à empresa', 'O valor mínimo de venda sem lucro nem prejuízo', 'O custo médio da mercadoria em estoque'], correct: 1, explanation: 'O Custo Unitário é o valor da mercadoria considerando impostos e demais despesas até chegar à empresa.' },
  { category: 'perguntas-gerais', question: 'Assim como Produto está para TPRODUTO, qual é a tabela correspondente a Lançamentos?', options: ['TMOV', 'FLAN', 'FEXTRATO', 'TGRUPO'], correct: 1, explanation: 'A tabela de Lançamentos no banco de dados é a FLAN.' },
  { category: 'perguntas-gerais', question: 'Na sequência correta de atualização de versão do sistema, qual é o primeiro passo?', options: ['Executar o Instalador', 'Iniciar o Firebird', 'Parar o Firebird', 'Executar o Atualizador'], correct: 2, explanation: 'O primeiro passo da sequência de atualização é parar o Firebird, antes de renomear a pasta do banco de dados.' },
  // Perguntas e Respostas — Fiscal
  { category: 'perguntas-fiscais', question: 'Qual a importância do NCM para um produto?', options: ['Identificar o produto, sua categoria e composição entre os países do Mercosul, além de definir tributações', 'Definir apenas a cor da etiqueta do produto', 'Substituir o cadastro de cliente', 'Calcular o ISS de um serviço prestado'], correct: 0, explanation: 'O NCM identifica o produto, sua categoria e composição entre os países do Mercosul, sendo usado também para definição de tributações.' },
  { category: 'perguntas-fiscais', question: 'Para que o CEST é utilizado, em conjunto com o NCM?', options: ['Para identificar produtos sujeitos à Substituição Tributária e calcular a MVA', 'Para cadastrar o centro de custo do produto', 'Para emitir o CT-e de transporte', 'Para liberar a contrassenha do sistema'], correct: 0, explanation: 'O CEST é utilizado junto ao NCM para identificar produtos sujeitos à Substituição Tributária e para o cálculo da MVA.' },
  { category: 'perguntas-fiscais', question: 'Ao identificar um erro em um questionário fiscal preenchido pela contabilidade durante uma troca de regime, qual o procedimento correto do suporte técnico?', options: ['Corrigir por conta própria sem avisar ninguém', 'Informar ao cliente e devolver à contabilidade para correção, podendo o suporte contatá-la diretamente se necessário', 'Ignorar o erro e prosseguir com a troca de regime mesmo assim', 'Cancelar definitivamente o atendimento'], correct: 1, explanation: 'O correto é informar ao cliente que o questionário está incompleto/errado e devolvê-lo à contabilidade, podendo o suporte contatá-la diretamente para otimizar o tempo.' },
  // Reforma Tributária (IBS/CBS)
  { category: 'reforma-tributaria', question: 'Em 2026, segundo o cronograma de testes da Reforma Tributária, quais alíquotas devem ser destacadas nas notas fiscais?', options: ['CBS 0,9% e IBS 0,1%', 'CBS 7,60% e IBS 18%', 'CBS 1,65% e IBS 12%', 'Nenhuma alíquota — só a partir de 2027'], correct: 0, explanation: 'Em 2026, ano de testes, as alíquotas destacadas são CBS 0,9% e IBS 0,1%, ainda sem recolhimento obrigatório.' },
  { category: 'reforma-tributaria', question: 'O que ocorre em 2027, segundo o cronograma da Reforma Tributária?', options: ['Início dos testes de CBS e IBS', 'Início da cobrança integral da CBS e extinção do PIS e da COFINS', 'Extinção total do ICMS e do ISS', 'Redução de 10% do IBS apenas'], correct: 1, explanation: 'Em 2027 começa a cobrança integral da CBS, com a extinção do PIS e da COFINS.' },
  { category: 'reforma-tributaria', question: 'Antes de executar o Execute Block de criação e vínculo dos tributos diretamente no banco de dados, o que é essencial?', options: ['Nenhum cuidado especial é necessário', 'Ter um backup recente e, preferencialmente, acompanhamento do suporte técnico/DBA', 'Desligar o Firebird definitivamente', 'Cancelar todas as notas fiscais já emitidas'], correct: 1, explanation: 'Por alterar diretamente o banco de dados, essa rotina exige backup recente e, de preferência, acompanhamento técnico especializado.' },
  { category: 'reforma-tributaria', question: 'Onde deve ser ajustado o Tipo de Operação Tributária de um movimento (Vendas, Compras para comercialização, Uso e consumo, Ativo Imobilizado)?', options: ['Em Cadastros → Funcionários', 'Em Edição do Movimento → Identificação → Dados Fiscais → Tipo de Operação/Regra de Tributação por Produto', 'No Firewall do servidor', 'No cadastro de Centro de Custos'], correct: 1, explanation: 'O ajuste é feito em Edição do Movimento → Identificação → Dados Fiscais → Tipo de Operação/Regra de Tributação por Produto.' },
  { category: 'reforma-tributaria', question: 'Por que o DANFE nacional ainda não exibe campos específicos de IBS e CBS?', options: ['Porque o sistema TGA não gera essas informações no XML', 'Porque o leiaute oficial da DANFE ainda não foi atualizado pela norma técnica nacional', 'Porque IBS e CBS ainda não existem oficialmente', 'Porque são tributos exclusivamente municipais'], correct: 1, explanation: 'O sistema já gera corretamente as informações de IBS/CBS no XML, mas o leiaute oficial da DANFE nacional ainda não foi atualizado para exibi-las.' },
  // PDV OFF
  { category: 'estoque-saida', question: 'Durante a instalação do Serviço de Servidor do PDV OFF, qual serviço é instalado automaticamente e deve ser conferido nos Serviços do Windows?', options: ['TGA – Server Integrador PDV', 'TGA – Firebird Manager', 'TGA – Backup Automático', 'TGA – Impressora Fiscal'], correct: 0, explanation: 'Durante a instalação do servidor, o serviço "TGA – Server Integrador PDV" é instalado — confirme se está em execução na área de Serviços do Windows.' },
  { category: 'estoque-saida', question: 'No cadastro do Ponto de Venda, o que acontece se o campo Vendedor for deixado em branco?', options: ['O caixa fica bloqueado para uso', 'Outro funcionário pode usar o caixa, e as vendas passam a ser vinculadas pelo usuário logado', 'O sistema cadastra um vendedor genérico automaticamente', 'A venda é cancelada automaticamente'], correct: 1, explanation: 'O campo Vendedor é opcional — se vazio, as vendas passam a se vincular com base no usuário logado, e não no funcionário associado ao ponto de venda.' },
  { category: 'estoque-saida', question: 'A partir do PDV OFF, as configurações de TEF passam a ser utilizadas a partir de onde?', options: ['Do Tipo de Movimento', 'Da Conta Caixa vinculada ao Ponto de Venda', 'Do cadastro de Funcionários', 'Do Configurador de Impressão'], correct: 1, explanation: 'A utilização do TEF no PDV OFF passa a utilizar as configurações da Conta Caixa, e não mais as do movimento.' },
  { category: 'estoque-saida', question: 'O que deve ser feito se a Série Fiscal de um novo Ponto de Venda nunca tiver sido utilizada?', options: ['Informar 0 (zero) em sua numeração no Configurador', 'Copiar a numeração de outro ponto de venda', 'Deixar o campo de numeração em branco', 'Excluir a série e recriar depois'], correct: 0, explanation: 'Caso a série nunca tenha sido utilizada, deve-se informar 0 (zero) em sua numeração no Configurador.' },
  { category: 'estoque-saida', question: 'Por que é obrigatório criar um Tipo de Movimento Fiscal exclusivo (NFC-e) para cada Ponto de Venda?', options: ['Para reduzir o número de cadastros no sistema', 'Para evitar erros de duplicidade ao salvar o movimento no banco de dados', 'Porque o sistema não aceita movimentos compartilhados', 'Para acelerar a emissão de boletos'], correct: 1, explanation: 'Cada Ponto de Venda precisa de seu próprio Tipo de Movimento Fiscal e Série Pré-Venda para evitar erros de duplicidade no banco de dados.' },
  { category: 'estoque-saida', question: 'No cadastro de Usuários do PDV OFF, qual caminho deve ser seguido para parametrizar Empresa e Conta/Caixa?', options: ['Estoque > Cadastros > Segurança > Usuários', 'Financeiro > Relatórios > Usuários', 'Configurador > Parâmetros > Usuários', 'Fiscal > Tributos > Usuários'], correct: 0, explanation: 'A parametrização de Empresa e Conta/Caixa do operador e do supervisor é feita em Estoque > Cadastros > Segurança > Usuários.' },
  { category: 'estoque-saida', question: 'O TGA-PDV valida o acesso do operador de caixa com base em qual senha?', options: ['A senha definida no cadastro de Usuários', 'A senha do banco de dados Firebird', 'A contrassenha do sistema', 'A senha do e-mail do funcionário'], correct: 0, explanation: 'O TGA-PDV valida apenas a senha definida no cadastro de Usuários.' },
  { category: 'estoque-saida', question: 'Na configuração de Formas de Pagamento TEF, qual Tipo Primitivo deve ser selecionado para cartão?', options: ['1 - Dinheiro', '2 - Boleto', '3 - Cartão', '4 - PIX'], correct: 2, explanation: 'O Tipo Primitivo "3 - Cartão" deve ser selecionado para formas de pagamento via TEF.' },
  { category: 'estoque-saida', question: 'Por que é obrigatório informar o Cód. Cli/Forn na Forma de Pagamento TEF?', options: ['Para calcular o desconto do cliente', 'Porque essa informação é usada na tag CNPJ do XML, referente à adquirente/subadquirente', 'Para gerar o boleto automaticamente', 'Não é obrigatório, é apenas informativo'], correct: 1, explanation: 'O Cód. Cli/Forn do participante do pagamento eletrônico (adquirente/subadquirente) é usado na tag CNPJ do XML da nota.' },
  { category: 'estoque-saida', question: 'Para habilitar o PIX como forma de pagamento no PDV OFF, quais opções devem ser marcadas no cadastro da Forma de Pagamento?', options: ['Apenas "Usa Integração PIX"', '"Pagamento Instantâneo (PIX)" e "Usa Integração PIX"', 'Apenas o Tipo Primitivo Cartão', 'Nenhuma, o PIX é automático'], correct: 1, explanation: 'É necessário marcar tanto "Pagamento Instantâneo (PIX)" quanto "Usa Integração PIX" para liberar a aba de Configuração Pix.' },
  { category: 'estoque-saida', question: 'O TGA-PDV é compatível com a emissão de qual documento fiscal?', options: ['Apenas NF-e', 'Apenas NFC-e', 'NFC-e e NFS-e', 'Apenas CT-e'], correct: 1, explanation: 'O TGA-PDV é compatível apenas com a emissão de NFC-e; empresas com módulo ECF precisam estar credenciadas à NFC-e.' },
  { category: 'estoque-saida', question: 'No Concentrador TGAPDV, qual é o caminho padrão do banco de dados do servidor?', options: ['C:\\TGA\\Dados\\tga.fdb', 'C:\\Windows\\System32\\tga.fdb', 'D:\\Backup\\tga.fdb', 'C:\\Program Files\\TGA\\tga.exe'], correct: 0, explanation: 'O caminho padrão do servidor configurado no Concentrador é C:\\TGA\\Dados\\tga.fdb.' },
  { category: 'estoque-saida', question: 'No Concentrador, o que deve ser feito antes de pesquisar o Ponto de Venda?', options: ['Reiniciar a estação', 'Importar o Ponto de Venda, para exibir os cadastros disponíveis', 'Desligar o servidor', 'Excluir o cadastro anterior'], correct: 1, explanation: 'Antes de pesquisar o Ponto de Venda no Concentrador, é necessário importá-lo para que os cadastros disponíveis sejam exibidos.' },
  { category: 'estoque-saida', question: 'Ao usar a flag "Utilizar último número informado" para corrigir a numeração de NFC-e/Pré-Venda, qual cuidado deve ser tomado?', options: ['Importar o Ponto de Venda novamente após salvar', 'Apenas salvar, sem importar novamente o Ponto de Venda', 'Excluir o Ponto de Venda e recriar', 'Reiniciar o Firebird logo em seguida'], correct: 1, explanation: 'Após marcar a flag e alterar a numeração, deve-se apenas salvar — não se deve importar o Ponto de Venda novamente.' },
  { category: 'estoque-saida', question: 'O que deve ser feito após concluir toda a parametrização do Ponto de Venda no Concentrador?', options: ['Nada, a configuração já está completa', 'Realizar a Carga Completa para integrar todos os dados', 'Reinstalar o TGAPDV', 'Desativar o serviço de integração'], correct: 1, explanation: 'Após parametrizar o Ponto de Venda, é necessário realizar a Carga Completa para integrar todos os dados entre servidor e estação.' }
];

/* ==========================================================================
   3. ESTADO GLOBAL & PERSISTÊNCIA (LocalStorage)
   ========================================================================== */
const STORAGE_KEYS = {
  theme: 'tga_theme',
  userName: 'tga_username',
  studiedCategories: 'tga_studied_categories',
  ranking: 'tga_ranking',
  lastAccess: 'tga_last_access',
  customQuestions: 'tga_custom_questions'
};

const ADMIN_PASSWORD = 'soueu';

const state = {
  currentPage: 'home',
  currentCategoryId: null,
  quiz: {
    questions: [],
    currentIndex: 0,
    score: 0,
    answered: false,
    timerEnabled: false,
    timerInterval: null,
    timeLeft: 30,
    playerName: '',
    answers: []
  }
};

function loadStudiedCategories() {
  try { return JSON.parse(localStorage.getItem(STORAGE_KEYS.studiedCategories)) || []; }
  catch { return []; }
}
function markCategoryStudied(id) {
  const list = loadStudiedCategories();
  if (!list.includes(id)) {
    list.push(id);
    localStorage.setItem(STORAGE_KEYS.studiedCategories, JSON.stringify(list));
    updateProgressIndicators();
  }
}
function loadRanking() {
  try { return JSON.parse(localStorage.getItem(STORAGE_KEYS.ranking)) || []; }
  catch { return []; }
}
function saveRankingEntry(entry) {
  const list = loadRanking();
  list.push(entry);
  localStorage.setItem(STORAGE_KEYS.ranking, JSON.stringify(list));
}
function clearRankingStorage() {
  localStorage.removeItem(STORAGE_KEYS.ranking);
}
function loadCustomQuestions() {
  try { return JSON.parse(localStorage.getItem(STORAGE_KEYS.customQuestions)) || []; }
  catch { return []; }
}
function saveCustomQuestions(list) {
  localStorage.setItem(STORAGE_KEYS.customQuestions, JSON.stringify(list));
}

/* ==========================================================================
   4. UTILITÁRIOS
   ========================================================================== */
function $(sel) { return document.querySelector(sel); }
function $all(sel) { return Array.from(document.querySelectorAll(sel)); }

function showToast(message) {
  const toast = $('#toast');
  toast.textContent = message;
  toast.classList.add('show');
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => toast.classList.remove('show'), 2600);
}

function formatDate(iso) {
  const d = new Date(iso);
  return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

function getCategoryById(id) {
  return KNOWLEDGE_BASE.find(c => c.id === id);
}

function classificationFor(percent) {
  if (percent >= 90) return { label: 'Excelente', emoji: '🏆' };
  if (percent >= 70) return { label: 'Bom', emoji: '🎯' };
  if (percent >= 50) return { label: 'Regular', emoji: '📘' };
  return { label: 'Precisa Estudar', emoji: '📚' };
}

/* ==========================================================================
   5. NAVEGAÇÃO (SPA sem reload)
   ========================================================================== */
const PAGE_TITLES = {
  home: 'Início',
  knowledge: 'Base de Conhecimento',
  category: 'Base de Conhecimento',
  search: 'Pesquisa',
  quiz: 'Teste de Conhecimento',
  ranking: 'Ranking',
  dashboard: 'Dashboard',
  admin: 'Administração'
};

const LOCKED_PAGES = ['home', 'knowledge', 'search'];

function navigateTo(page) {
  if (LOCKED_PAGES.includes(page)) {
    showToast('Em desenvolvimento — disponível em breve.');
    return;
  }

  const quizInProgress = state.currentPage === 'quiz' && !$('#quizPlaying').classList.contains('hidden');
  if (quizInProgress && page !== 'quiz') {
    const confirmLeave = confirm('Você tem um teste em andamento. Se sair agora, o progresso será perdido. Deseja continuar?');
    if (!confirmLeave) return;
    clearInterval(state.quiz.timerInterval);
  }

  state.currentPage = page;
  $all('.page').forEach(p => p.classList.remove('active'));
  const target = $(`#page-${page}`);
  if (target) target.classList.add('active');

  $all('.nav-item').forEach(btn => btn.classList.toggle('active', btn.dataset.page === page));
  $('#pageTitle').textContent = PAGE_TITLES[page] || 'TGA ERP';

  closeSidebarMobile();
  window.scrollTo({ top: 0, behavior: 'smooth' });

  if (page === 'ranking') renderRankingWithSkeleton();
  if (page === 'dashboard') renderDashboard();
  if (page === 'home') renderHome();
  if (page === 'admin') renderAdmin();
  if (page === 'quiz') resetQuizToSetup();
}

function openCategoryPage(categoryId) {
  state.currentCategoryId = categoryId;
  renderCategoryDetail(categoryId);
  state.currentPage = 'category';
  $all('.page').forEach(p => p.classList.remove('active'));
  $('#page-category').classList.add('active');
  $all('.nav-item').forEach(btn => btn.classList.toggle('active', btn.dataset.page === 'knowledge'));
  $('#pageTitle').textContent = getCategoryById(categoryId)?.name || 'Módulo';
  window.scrollTo({ top: 0, behavior: 'smooth' });
  markCategoryStudied(categoryId);
}

function closeSidebarMobile() {
  $('#sidebar').classList.remove('open');
  $('#sidebarOverlay').classList.remove('show');
}

/* ==========================================================================
   6. RENDERIZAÇÃO — HOME
   ========================================================================== */
function renderHome() {
  const studied = loadStudiedCategories();
  const total = KNOWLEDGE_BASE.length;
  const percent = total ? Math.round((studied.length / total) * 100) : 0;

  $('#heroProgressFill').style.width = percent + '%';
  $('#heroProgressLabel').textContent = `${percent}% do conteúdo estudado`;
  $('#homeStatCategorias').textContent = `${studied.length}/${total}`;

  const ranking = loadRanking();
  $('#homeStatTestes').textContent = ranking.length;
  const best = ranking.length ? Math.max(...ranking.map(r => r.percent)) : 0;
  $('#homeStatMelhor').textContent = best + '%';
  const lastAccess = localStorage.getItem(STORAGE_KEYS.lastAccess);
  $('#homeStatAcesso').textContent = lastAccess ? formatDate(lastAccess) : '-';

  const grid = $('#homeCategoryGrid');
  grid.innerHTML = skeletonCategoryCards(KNOWLEDGE_BASE.length);
  setTimeout(() => {
    grid.innerHTML = KNOWLEDGE_BASE.map(cat => categoryCardHTML(cat, studied)).join('');
  }, 300);
}

function skeletonCategoryCards(count) {
  return Array.from({ length: count }).map(() => '<div class="category-card skeleton skeleton-card"></div>').join('');
}

function skeletonRankingRows(count) {
  const widths = [20, 130, 110, 70, 90];
  return Array.from({ length: count }).map(() => `
    <tr class="skeleton-row">
      ${widths.map(w => `<td><div class="skeleton skeleton-line" style="width:${w}px;"></div></td>`).join('')}
    </tr>`).join('');
}

function categoryCardHTML(cat, studiedList) {
  const isStudied = studiedList.includes(cat.id);
  return `
    <article class="category-card" data-open-category="${cat.id}">
      <div class="cat-icon">${cat.icon}</div>
      <h4>${cat.name}</h4>
      <p>${cat.desc}</p>
      <div class="cat-meta">
        <span class="cat-badge">${cat.topics.length} tópicos</span>
        ${isStudied ? '<span class="cat-check">✓ Estudado</span>' : '<span>Não iniciado</span>'}
      </div>
    </article>`;
}

function updateProgressIndicators() {
  const studied = loadStudiedCategories();
  const total = KNOWLEDGE_BASE.length;
  const percent = total ? Math.round((studied.length / total) * 100) : 0;
  $('#sidebarProgressFill').style.width = percent + '%';
  $('#sidebarProgressLabel').textContent = percent + '%';
  if (state.currentPage === 'home') renderHome();
}

/* ==========================================================================
   7. RENDERIZAÇÃO — BASE DE CONHECIMENTO
   ========================================================================== */
function renderKnowledgeList() {
  const studied = loadStudiedCategories();
  const grid = $('#knowledgeCategoryGrid');
  grid.innerHTML = skeletonCategoryCards(KNOWLEDGE_BASE.length);
  setTimeout(() => {
    grid.innerHTML = KNOWLEDGE_BASE.map(cat => categoryCardHTML(cat, studied)).join('');
  }, 300);
}

function iconSvg(name) {
  const icons = {
    tip: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.2 1 2.3h6c0-1.1.4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>',
    alert: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.9 18a2 2 0 0 0 1.7 3h16.8a2 2 0 0 0 1.7-3L14.7 3.9a2 2 0 0 0-3.4 0z"/></svg>',
    chevron: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>',
    steps: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01"/></svg>'
  };
  return icons[name] || '';
}

function renderCategoryDetail(categoryId) {
  const cat = getCategoryById(categoryId);
  if (!cat) return;

  $('#categoryHeader').innerHTML = `
    <div class="cat-icon" style="display:inline-flex;margin-right:14px;vertical-align:middle;">${cat.icon}</div>
    <div style="display:inline-block;vertical-align:middle;">
      <h2>${cat.name}</h2>
      <p class="muted">${cat.desc}</p>
    </div>`;

  $('#categoryTopics').innerHTML = cat.topics.map((t, i) => topicItemHTML(t, i, categoryId)).join('');

  $all('#categoryTopics .topic-header').forEach(header => {
    header.addEventListener('click', () => {
      header.closest('.topic-item').classList.toggle('open');
    });
  });
}

function escapeHtml(str) {
  return String(str).replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
}

function topicItemHTML(topic, index, categoryId) {
  const hasAlert = topic.alert && topic.alert !== '—';
  const hasSteps = topic.steps && topic.steps.length;
  const hasTips = topic.tips && topic.tips.length;
  const hasCode = topic.code && topic.code.length;
  const hasLink = !!topic.link;
  return `
    <div class="topic-item" data-topic-index="${index}">
      <button class="topic-header">
        <span class="topic-num">${index + 1}</span>
        <h4>${topic.title}</h4>
        ${iconSvg('chevron')}
      </button>
      <div class="topic-body">
        <div class="topic-image">${categoryIconFor(categoryId)}</div>
        <p class="topic-desc">${topic.desc}</p>
        ${hasSteps ? `
        <div class="topic-block">
          <h5>${iconSvg('steps')} Passo a passo</h5>
          <ol>${topic.steps.map(s => `<li>${s}</li>`).join('')}</ol>
        </div>` : ''}
        ${hasCode ? topic.code.map(c => `
        <div class="topic-block">
          <h5>${c.label}</h5>
          <pre class="code-block">${escapeHtml(c.content)}</pre>
        </div>`).join('') : ''}
        ${hasTips ? `
        <div class="topic-block">
          <h5>Dicas importantes</h5>
          <div class="tip-box">${iconSvg('tip')}<span>${topic.tips.join(' ')}</span></div>
        </div>` : ''}
        ${hasAlert ? `
        <div class="topic-block">
          <h5>Alerta</h5>
          <div class="alert-box">${iconSvg('alert')}<span>${topic.alert}</span></div>
        </div>` : ''}
        ${hasLink ? `
        <div class="topic-block">
          <a class="link-box" href="${topic.link.url}" target="_blank" rel="noopener noreferrer">🔗 ${topic.link.label}</a>
        </div>` : ''}
      </div>
    </div>`;
}

function categoryIconFor(categoryId) {
  const cat = getCategoryById(categoryId);
  return `<span style="font-size:38px;">${cat ? cat.icon : '📄'}</span>`;
}

/* ==========================================================================
   8. PESQUISA
   ========================================================================== */
function buildSearchIndex() {
  const index = [];
  KNOWLEDGE_BASE.forEach(cat => {
    cat.topics.forEach(topic => {
      index.push({
        categoryId: cat.id,
        categoryName: cat.name,
        title: topic.title,
        desc: topic.desc,
        haystack: (topic.title + ' ' + topic.desc + ' ' + (topic.steps || []).join(' ') + ' ' + (topic.tips || []).join(' ')).toLowerCase()
      });
    });
  });
  return index;
}
const SEARCH_INDEX = buildSearchIndex();

function highlight(text, term) {
  if (!term) return text;
  const re = new RegExp('(' + term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig');
  return text.replace(re, '<mark>$1</mark>');
}

function runSearch(term) {
  const box = $('#searchResults');
  const clean = term.trim().toLowerCase();
  if (!clean) {
    box.innerHTML = `<p class="empty-state">Digite algo para começar a pesquisar.</p>`;
    return;
  }
  const results = SEARCH_INDEX.filter(item => item.haystack.includes(clean));
  if (!results.length) {
    box.innerHTML = `<p class="empty-state">Nenhum resultado encontrado para "${term}".</p>`;
    return;
  }
  box.innerHTML = results.map(r => `
    <article class="search-result-item" data-open-category="${r.categoryId}">
      <span class="sr-cat">${r.categoryName}</span>
      <h4>${highlight(r.title, clean)}</h4>
      <p>${highlight(r.desc, clean)}</p>
    </article>`).join('');
}

/* ==========================================================================
   9. QUIZ
   ========================================================================== */
function populateQuizCategorySelect() {
  const select = $('#quizCategorySelect');
  select.innerHTML = `<option value="all">Todos os módulos</option>` +
    KNOWLEDGE_BASE.map(cat => `<option value="${cat.id}">${cat.name}</option>`).join('');
}

function getQuizPoolSize(categoryValue) {
  const allQuestions = QUIZ_QUESTIONS.concat(loadCustomQuestions());
  const pool = categoryValue === 'all' ? allQuestions : allQuestions.filter(q => q.category === categoryValue);
  return pool.length;
}

function updateQuizAmountOptions() {
  const categoryValue = $('#quizCategorySelect').value;
  const total = getQuizPoolSize(categoryValue);
  const amountSelect = $('#quizAmountSelect');

  $all('#quizAmountSelect option').forEach(opt => {
    if (opt.value === '999') {
      opt.textContent = `Todas (${total})`;
    } else {
      opt.disabled = parseInt(opt.value, 10) > total;
    }
  });

  if (amountSelect.selectedOptions[0] && amountSelect.selectedOptions[0].disabled) {
    amountSelect.value = '999';
  }
}

function shuffleArray(arr) {
  const a = arr.slice();
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

function startQuiz() {
  const name = $('#quizPlayerName').value.trim();
  if (!name) { showToast('Informe seu nome para iniciar o teste.'); return; }
  localStorage.setItem(STORAGE_KEYS.userName, name);
  $('#topbarUserName').textContent = name;

  const categoryValue = $('#quizCategorySelect').value;
  const amount = parseInt($('#quizAmountSelect').value, 10);
  const timerEnabled = $('#quizTimerToggle').checked;

  const allQuestions = QUIZ_QUESTIONS.concat(loadCustomQuestions());
  let pool = categoryValue === 'all'
    ? allQuestions
    : allQuestions.filter(q => q.category === categoryValue);

  if (!pool.length) { showToast('Não há perguntas cadastradas para este módulo.'); return; }

  pool = shuffleArray(pool).slice(0, Math.min(amount, pool.length));

  state.quiz.questions = pool;
  state.quiz.currentIndex = 0;
  state.quiz.score = 0;
  state.quiz.timerEnabled = timerEnabled;
  state.quiz.playerName = name;
  state.quiz.answers = [];

  $('#quizSetup').classList.add('hidden');
  $('#quizResult').classList.add('hidden');
  $('#reviewSection').classList.add('hidden');
  $('#reviewSection').innerHTML = '';
  $('#quizPlaying').classList.remove('hidden');
  $('#quizTimerBox').classList.toggle('hidden', !timerEnabled);

  renderQuestion();
}

function renderQuestion() {
  const { questions, currentIndex } = state.quiz;
  const q = questions[currentIndex];
  state.quiz.answered = false;

  $('#quizCounter').textContent = `Pergunta ${currentIndex + 1} de ${questions.length}`;
  $('#quizProgressFill').style.width = `${(currentIndex / questions.length) * 100}%`;
  renderQuizDots();

  const cat = getCategoryById(q.category);
  $('#questionTag').textContent = cat ? cat.name : 'Geral';
  $('#questionText').textContent = q.question;

  const letters = ['A', 'B', 'C', 'D'];
  $('#optionsList').innerHTML = q.options.map((opt, i) => `
    <button class="option-btn" data-option-index="${i}">
      <span class="opt-letter">${letters[i]}</span>
      <span>${escapeHtml(opt)}</span>
    </button>`).join('');

  $all('#optionsList .option-btn').forEach(btn => {
    btn.addEventListener('click', () => selectOption(parseInt(btn.dataset.optionIndex, 10)));
  });

  $('#explanationBox').classList.add('hidden');
  $('#btnNextQuestion').classList.add('hidden');

  if (state.quiz.timerEnabled) startTimer();
}

const QUIZ_DOTS_MAX = 30;

function renderQuizDots() {
  const dotsBox = $('#quizDots');
  const { questions, currentIndex, answers } = state.quiz;
  if (questions.length > QUIZ_DOTS_MAX) {
    dotsBox.innerHTML = '';
    return;
  }
  dotsBox.innerHTML = questions.map((_, i) => {
    let cls = 'quiz-dot';
    const answer = answers[i];
    if (answer) cls += answer.isCorrect ? ' correct' : ' wrong';
    if (i === currentIndex) cls += ' current';
    return `<span class="${cls}"></span>`;
  }).join('');
}

function startTimer() {
  clearInterval(state.quiz.timerInterval);
  state.quiz.timeLeft = 30;
  $('#quizTimerValue').textContent = state.quiz.timeLeft;
  state.quiz.timerInterval = setInterval(() => {
    state.quiz.timeLeft--;
    $('#quizTimerValue').textContent = state.quiz.timeLeft;
    if (state.quiz.timeLeft <= 0) {
      clearInterval(state.quiz.timerInterval);
      if (!state.quiz.answered) selectOption(-1);
    }
  }, 1000);
}

function selectOption(selectedIndex) {
  if (state.quiz.answered) return;
  state.quiz.answered = true;
  clearInterval(state.quiz.timerInterval);

  const q = state.quiz.questions[state.quiz.currentIndex];
  const buttons = $all('#optionsList .option-btn');
  buttons.forEach(b => b.disabled = true);

  const isCorrect = selectedIndex === q.correct;
  if (isCorrect) state.quiz.score++;

  buttons[q.correct].classList.add('correct');
  if (selectedIndex !== -1 && selectedIndex !== q.correct) {
    buttons[selectedIndex].classList.add('wrong');
  }

  state.quiz.answers[state.quiz.currentIndex] = {
    question: q.question,
    options: q.options,
    correctIndex: q.correct,
    selectedIndex,
    isCorrect,
    explanation: q.explanation,
    category: q.category
  };
  renderQuizDots();

  $('#explanationTitle').textContent = isCorrect ? 'Resposta correta!' : (selectedIndex === -1 ? 'Tempo esgotado!' : 'Resposta incorreta.');
  $('#explanationText').textContent = q.explanation;
  $('#explanationBox').classList.remove('hidden');
  $('#btnNextQuestion').classList.remove('hidden');
  $('#btnNextQuestion').textContent = (state.quiz.currentIndex === state.quiz.questions.length - 1) ? 'Ver resultado' : 'Próxima pergunta';
}

function nextQuestion() {
  state.quiz.currentIndex++;
  if (state.quiz.currentIndex >= state.quiz.questions.length) {
    finishQuiz();
  } else {
    renderQuestion();
  }
}

const QUIZ_KEY_TO_OPTION = { '1': 0, '2': 1, '3': 2, '4': 3, 'a': 0, 'b': 1, 'c': 2, 'd': 3 };

function handleQuizKeydown(e) {
  const playingVisible = !$('#quizPlaying').classList.contains('hidden');
  if (!playingVisible) return;

  const tag = (e.target.tagName || '').toLowerCase();
  if (tag === 'input' || tag === 'select' || tag === 'textarea') return;

  if (!state.quiz.answered) {
    const optionIndex = QUIZ_KEY_TO_OPTION[e.key.toLowerCase()];
    if (optionIndex !== undefined) {
      const buttons = $all('#optionsList .option-btn');
      if (buttons[optionIndex] && !buttons[optionIndex].disabled) {
        e.preventDefault();
        selectOption(optionIndex);
      }
      return;
    }
  }

  if (e.key === 'Enter') {
    const nextBtn = $('#btnNextQuestion');
    if (nextBtn && !nextBtn.classList.contains('hidden')) {
      e.preventDefault();
      nextBtn.click();
    }
  }
}

function finishQuiz() {
  $('#quizPlaying').classList.add('hidden');
  $('#quizResult').classList.remove('hidden');

  const total = state.quiz.questions.length;
  const score = state.quiz.score;
  const percent = Math.round((score / total) * 100);
  const { label, emoji } = classificationFor(percent);

  $('#resultBadge').textContent = emoji;
  $('#resultClassification').textContent = label;
  $('#resultPlayerName').textContent = state.quiz.playerName;
  $('#resultScore').textContent = `${score}/${total}`;
  $('#resultPercent').textContent = `${percent}%`;
  $('#btnShowCertificate').classList.toggle('hidden', percent < 80);

  const wrongAnswers = state.quiz.answers.filter(a => a && !a.isCorrect);
  $('#btnToggleReview').classList.toggle('hidden', wrongAnswers.length === 0);
  $('#btnToggleReview').textContent = 'Ver Revisão';
  $('#reviewSection').classList.add('hidden');
  $('#reviewSection').innerHTML = '';
  state.lastWrongAnswers = wrongAnswers;

  const entry = {
    name: state.quiz.playerName,
    date: new Date().toISOString(),
    score: `${score}/${total}`,
    percent
  };
  saveRankingEntry(entry);
  state.lastQuizResult = entry;

  updateProgressIndicators();
}

function renderReview() {
  const wrongAnswers = state.lastWrongAnswers || [];
  const letters = ['A', 'B', 'C', 'D'];
  $('#reviewSection').innerHTML = wrongAnswers.map(a => {
    const cat = getCategoryById(a.category);
    const yourAnswer = a.selectedIndex === -1 ? 'Não respondida (tempo esgotado)' : `${letters[a.selectedIndex]} — ${escapeHtml(a.options[a.selectedIndex])}`;
    return `
      <div class="review-item">
        <span class="question-tag">${escapeHtml(cat ? cat.name : 'Geral')}</span>
        <h4>${escapeHtml(a.question)}</h4>
        <div class="review-answer wrong"><strong>Sua resposta:</strong><span>${yourAnswer}</span></div>
        <div class="review-answer correct"><strong>Resposta correta:</strong><span>${letters[a.correctIndex]} — ${escapeHtml(a.options[a.correctIndex])}</span></div>
        <div class="explanation-box"><strong>Explicação</strong><p>${escapeHtml(a.explanation)}</p></div>
      </div>`;
  }).join('');
}

function toggleReview() {
  const section = $('#reviewSection');
  const isHidden = section.classList.contains('hidden');
  if (isHidden) {
    if (!section.innerHTML) renderReview();
    section.classList.remove('hidden');
    $('#btnToggleReview').textContent = 'Ocultar Revisão';
  } else {
    section.classList.add('hidden');
    $('#btnToggleReview').textContent = 'Ver Revisão';
  }
}

function resetQuizToSetup() {
  clearInterval(state.quiz.timerInterval);
  $('#quizResult').classList.add('hidden');
  $('#quizPlaying').classList.add('hidden');
  $('#quizSetup').classList.remove('hidden');
  updateQuizAmountOptions();
}

/* ==========================================================================
   10. RANKING
   ========================================================================== */
function renderRanking() {
  const ranking = loadRanking().slice().sort((a, b) => b.percent - a.percent || new Date(b.date) - new Date(a.date));
  const body = $('#rankingBody');
  const empty = $('#rankingEmpty');

  if (!ranking.length) {
    body.innerHTML = '';
    empty.classList.remove('hidden');
    return;
  }
  empty.classList.add('hidden');
  const medals = ['🥇', '🥈', '🥉'];
  body.innerHTML = ranking.map((r, i) => `
    <tr>
      <td>${medals[i] ? `<span class="rank-medal">${medals[i]}</span>` : i + 1}</td>
      <td>${escapeHtml(r.name)}</td>
      <td>${formatDate(r.date)}</td>
      <td>${r.score}</td>
      <td>${r.percent}%</td>
    </tr>`).join('');
}

function renderRankingWithSkeleton() {
  const body = $('#rankingBody');
  $('#rankingEmpty').classList.add('hidden');
  body.innerHTML = skeletonRankingRows(4);
  setTimeout(renderRanking, 300);
}

/* ==========================================================================
   11. DASHBOARD
   ========================================================================== */
function renderDashboard() {
  const studied = loadStudiedCategories();
  const ranking = loadRanking().slice().sort((a, b) => new Date(b.date) - new Date(a.date));

  $('#dashModulos').textContent = `${studied.length}/${KNOWLEDGE_BASE.length}`;
  $('#dashTestes').textContent = ranking.length;

  const best = ranking.length ? Math.max(...ranking.map(r => r.percent)) : 0;
  const avg = ranking.length ? Math.round(ranking.reduce((s, r) => s + r.percent, 0) / ranking.length) : 0;
  $('#dashMelhor').textContent = best + '%';
  $('#dashMedia').textContent = avg + '%';

  const lastAccess = localStorage.getItem(STORAGE_KEYS.lastAccess);
  $('#dashUltimoAcesso').textContent = lastAccess ? formatDate(lastAccess) : 'Nenhum acesso anterior registrado.';

  const historyBody = $('#dashHistoryBody');
  if (!ranking.length) {
    historyBody.innerHTML = '<tr><td colspan="3" class="empty-state">Nenhum teste realizado ainda.</td></tr>';
  } else {
    historyBody.innerHTML = ranking.slice(0, 10).map(r => `
      <tr><td>${formatDate(r.date)}</td><td>${r.score}</td><td>${r.percent}%</td></tr>`).join('');
  }
}

/* ==========================================================================
   12. CERTIFICADO
   ========================================================================== */
let certLastFocusedElement = null;

function getFocusableInModal() {
  return $all('#certificateModal button, #certificateModal a[href]').filter(el => !el.disabled && el.offsetParent !== null);
}

function trapCertificateFocus(e) {
  if (e.key !== 'Tab') return;
  const focusable = getFocusableInModal();
  if (!focusable.length) return;
  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  if (e.shiftKey && document.activeElement === first) {
    e.preventDefault();
    last.focus();
  } else if (!e.shiftKey && document.activeElement === last) {
    e.preventDefault();
    first.focus();
  }
}

function openCertificate() {
  const result = state.lastQuizResult;
  if (!result) return;
  $('#certName').textContent = result.name;
  $('#certPercent').textContent = result.percent + '%';
  $('#certDate').textContent = formatDate(result.date);
  certLastFocusedElement = document.activeElement;
  $('#certificateModal').classList.remove('hidden');
  document.addEventListener('keydown', trapCertificateFocus);
  $('#closeCertificate').focus();
}
function closeCertificate() {
  $('#certificateModal').classList.add('hidden');
  document.removeEventListener('keydown', trapCertificateFocus);
  if (certLastFocusedElement) certLastFocusedElement.focus();
}
function printCertificate() {
  document.body.classList.add('printing-cert');
  window.print();
}
window.addEventListener('afterprint', () => document.body.classList.remove('printing-cert'));

/* ==========================================================================
   12.1 ADMINISTRAÇÃO — CADASTRO DE NOVAS PERGUNTAS
   ========================================================================== */
let adminUnlocked = false;

function populateAdminCategorySelect() {
  const select = $('#adminQuestionCategory');
  select.innerHTML = KNOWLEDGE_BASE.map(cat => `<option value="${cat.id}">${cat.name}</option>`).join('');
}

function unlockAdmin() {
  const value = $('#adminPasswordInput').value;
  if (value !== ADMIN_PASSWORD) {
    showToast('Senha incorreta.');
    return;
  }
  adminUnlocked = true;
  $('#adminGate').classList.add('hidden');
  $('#adminPanel').classList.remove('hidden');
  renderAdminQuestions();
}

function renderAdmin() {
  populateAdminCategorySelect();
  if (adminUnlocked) {
    $('#adminGate').classList.add('hidden');
    $('#adminPanel').classList.remove('hidden');
    renderAdminQuestions();
  } else {
    $('#adminGate').classList.remove('hidden');
    $('#adminPanel').classList.add('hidden');
    $('#adminPasswordInput').value = '';
  }
}

function renderAdminQuestions() {
  const list = loadCustomQuestions();
  const body = $('#adminQuestionsBody');
  const empty = $('#adminQuestionsEmpty');
  if (!list.length) {
    body.innerHTML = '';
    empty.classList.remove('hidden');
    return;
  }
  empty.classList.add('hidden');
  const letters = ['A', 'B', 'C', 'D'];
  body.innerHTML = list.map(q => {
    const cat = getCategoryById(q.category);
    return `
      <tr>
        <td>${escapeHtml(cat ? cat.name : q.category)}</td>
        <td>${escapeHtml(q.question)}</td>
        <td>${letters[q.correct]}</td>
        <td><button class="btn btn-ghost" data-delete-question="${q.id}">Excluir</button></td>
      </tr>`;
  }).join('');

  $all('[data-delete-question]').forEach(btn => {
    btn.addEventListener('click', () => deleteCustomQuestion(btn.dataset.deleteQuestion));
  });
}

function addCustomQuestion() {
  const category = $('#adminQuestionCategory').value;
  const question = $('#adminQuestionText').value.trim();
  const options = [0, 1, 2, 3].map(i => $(`#adminOption${i}`).value.trim());
  const correct = parseInt($('#adminCorrectOption').value, 10);
  const explanation = $('#adminExplanation').value.trim();

  if (!question || options.some(o => !o)) {
    showToast('Preencha a pergunta e as 4 alternativas antes de adicionar.');
    return;
  }

  const list = loadCustomQuestions();
  list.push({
    id: 'custom_' + Date.now(),
    category, question, options, correct,
    explanation: explanation || 'Resposta cadastrada pelo administrador.'
  });
  saveCustomQuestions(list);

  $('#adminQuestionText').value = '';
  options.forEach((_, i) => { $(`#adminOption${i}`).value = ''; });
  $('#adminExplanation').value = '';

  renderAdminQuestions();
  showToast('Pergunta adicionada ao banco do Teste de Conhecimento.');
}

function deleteCustomQuestion(id) {
  const list = loadCustomQuestions().filter(q => q.id !== id);
  saveCustomQuestions(list);
  renderAdminQuestions();
}

/* ==========================================================================
   13. TEMA (Dark/Light Mode)
   ========================================================================== */
function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  localStorage.setItem(STORAGE_KEYS.theme, theme);
  $('#themeIconSun').style.display = theme === 'dark' ? 'none' : 'block';
  $('#themeIconMoon').style.display = theme === 'dark' ? 'block' : 'none';
}
function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme');
  applyTheme(current === 'dark' ? 'light' : 'dark');
}

/* ==========================================================================
   14. INICIALIZAÇÃO & EVENTOS
   ========================================================================== */
function initEventListeners() {
  $all('.nav-item').forEach(btn => btn.addEventListener('click', () => navigateTo(btn.dataset.page)));
  $all('[data-back]').forEach(btn => btn.addEventListener('click', () => navigateTo(btn.dataset.back)));

  $('#btnComecar').addEventListener('click', () => navigateTo('knowledge'));

  document.addEventListener('click', (e) => {
    const card = e.target.closest('[data-open-category]');
    if (card) openCategoryPage(card.dataset.openCategory);
  });

  $('#menuToggle').addEventListener('click', () => {
    $('#sidebar').classList.add('open');
    $('#sidebarOverlay').classList.add('show');
  });
  $('#sidebarClose').addEventListener('click', closeSidebarMobile);
  $('#sidebarOverlay').addEventListener('click', closeSidebarMobile);

  $('#themeToggle').addEventListener('click', toggleTheme);

  $('#searchInput').addEventListener('input', (e) => runSearch(e.target.value));

  document.addEventListener('keydown', handleQuizKeydown);

  $('#quizCategorySelect').addEventListener('change', updateQuizAmountOptions);
  $('#btnStartQuiz').addEventListener('click', startQuiz);
  $('#btnNextQuestion').addEventListener('click', nextQuestion);
  $('#btnRedoQuiz').addEventListener('click', resetQuizToSetup);
  $('#btnToggleReview').addEventListener('click', toggleReview);
  $('#btnShowCertificate').addEventListener('click', openCertificate);
  $('#closeCertificate').addEventListener('click', closeCertificate);
  $('#btnPrintCertificate').addEventListener('click', printCertificate);
  $('#certificateModal').addEventListener('click', (e) => { if (e.target.id === 'certificateModal') closeCertificate(); });

  $('#btnAdminUnlock').addEventListener('click', unlockAdmin);
  $('#adminPasswordInput').addEventListener('keydown', (e) => { if (e.key === 'Enter') unlockAdmin(); });
  $('#btnAddQuestion').addEventListener('click', addCustomQuestion);

  $('#btnClearRanking').addEventListener('click', () => {
    if (confirm('Deseja realmente limpar todo o ranking registrado neste dispositivo?')) {
      clearRankingStorage();
      renderRanking();
      renderDashboard();
      showToast('Ranking limpo com sucesso.');
    }
  });

  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeCertificate(); });
}

function init() {
  const savedTheme = localStorage.getItem(STORAGE_KEYS.theme) ||
    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  applyTheme(savedTheme);

  const savedName = localStorage.getItem(STORAGE_KEYS.userName);
  if (savedName) {
    $('#topbarUserName').textContent = savedName;
    $('#quizPlayerName').value = savedName;
  }

  populateQuizCategorySelect();
  renderKnowledgeList();
  runSearch('');
  updateProgressIndicators();
  renderHome();

  initEventListeners();
  navigateTo('quiz');

  localStorage.setItem(STORAGE_KEYS.lastAccess, new Date().toISOString());
}

document.addEventListener('DOMContentLoaded', init);
