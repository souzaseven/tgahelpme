/**
 * data.js
 * ------------------------------------------------------------------
 * Base de dados da Central de Configuração de Boletos via API.
 *
 * IMPORTANTE (regra crítica do projeto): nenhum campo aqui foi inventado.
 * Os campos da tela "Portador" (portadorCamposBase) foram extraídos
 * diretamente das imagens enviadas pelo usuário (telas: Dados do Cedente,
 * Instruções para o Banco, Impressão, Remessa/Retorno). Onde o significado
 * de um campo é uma inferência (não confirmada explicitamente na imagem),
 * isso está marcado com `inferido: true` e um texto de observação.
 *
 * Campos específicos de API de cada banco (Client ID, Client Secret,
 * certificado, etc.) que NÃO apareceram nas imagens estão marcados com
 * `origem: "publica"` (conhecimento público de mercado/documentação do
 * banco) e devem ser CONFIRMADOS pelo time antes de virar verdade absoluta
 * no painel. Nunca marcados como `origem: "imagem"` sem terem vindo das
 * telas fornecidas.
 *
 * Todo o painel (dashboard, cards, página de banco) é renderizado a
 * partir deste arquivo — para adicionar um banco novo, basta acrescentar
 * um objeto no array `bancos`, sem alterar HTML/CSS.
 * ------------------------------------------------------------------
 */

// ============================================================
// 1. MODELO BASE DO PORTADOR (tela real do ERP, comum a todos os bancos)
// ============================================================
// Este é o cadastro único ("Portador") onde QUALQUER banco é configurado.
// O campo que decide qual integração de API vai ser usada é o
// "Tipo Cobrança API" (aba Remessa/Retorno). Por isso cada banco no
// painel referencia este mesmo modelo, destacando só os campos que
// importam para ele.
const portadorCamposBase = {
  origemImagem: {
    tela: "Portador",
    abas: [
      "Dados do Cedente",
      "Instruções para o Banco",
      "Impressão",
      "Remessa/Retorno",
      "Outros Dados",
    ],
    observacao:
      'A aba "Outros Dados" apareceu na barra de abas mas não foi capturada em nenhuma imagem enviada — conteúdo pendente de documentação.',
  },

  abaDadosCedente: {
    titulo: "Dados do Cedente",
    campos: [
      { nome: "Código", obrigatorio: true, tipo: "texto",
        paraQueServe: "Código interno do portador dentro do ERP." },
      { nome: "Descrição", obrigatorio: true, tipo: "texto",
        paraQueServe: "Nome/descrição do portador, usado para identificá-lo nas listas de seleção." },
      { nome: "Inativo", obrigatorio: false, tipo: "checkbox",
        paraQueServe: "Inativa o portador sem excluí-lo do sistema." },
      { nome: "Conta/Caixa Vinculada", obrigatorio: true, tipo: "busca",
        paraQueServe: "Conta financeira do ERP para onde vão os valores recebidos/baixados por este portador." },
      { nome: "Nome do Cedente", obrigatorio: true, tipo: "texto",
        paraQueServe: "Razão social do cedente (beneficiário) impressa no boleto." },
      { nome: "Código do Cedente", obrigatorio: true, tipo: "texto",
        paraQueServe: "Código do beneficiário atribuído pelo banco (também chamado de Convênio/Código Cedente, dependendo do banco)." },
      { nome: "DV", obrigatorio: false, tipo: "texto",
        paraQueServe: "Dígito verificador do Código do Cedente." },
      { nome: "Carteira", obrigatorio: true, tipo: "texto",
        paraQueServe: "Código da carteira de cobrança definida junto ao banco." },
      { nome: "Var *", obrigatorio: false, tipo: "texto",
        paraQueServe: "Variação da carteira — 'exigido para alguns bancos' (nota de rodapé da própria tela).", inferido: true },
      { nome: "Tipo Inscrição", obrigatorio: true, tipo: "radio (CNPJ / CPF / Outros)",
        paraQueServe: "Define o tipo de documento do cedente informado a seguir." },
      { nome: "Nº Inscrição", obrigatorio: true, tipo: "texto (somente números)",
        paraQueServe: "CNPJ ou CPF do cedente, conforme o Tipo Inscrição selecionado." },
      { nome: "Nosso Número (Próx)", obrigatorio: true, tipo: "texto",
        paraQueServe: "Próximo número sequencial de 'Nosso Número' a ser usado na emissão do boleto." },
      { nome: "Tam", obrigatorio: false, tipo: "número",
        paraQueServe: "Quantidade de dígitos exigida pelo banco para o Nosso Número." },
      { nome: "Nº Convênio", obrigatorio: false, tipo: "texto",
        paraQueServe: "Número de convênio de cobrança do cedente junto ao banco (quando aplicável)." },
      { nome: "Aceite", obrigatorio: false, tipo: "dropdown",
        paraQueServe: "Indicador de aceite do título (campo padrão de layout de boleto/CNAB).", inferido: true },
      { nome: "Filial Defalt", obrigatorio: false, tipo: "busca",
        paraQueServe: "Filial padrão vinculada a este portador." },
      { nome: "Modalidade Cobrança (BB*)", obrigatorio: false, tipo: "dropdown", somenteBanco: "bb",
        paraQueServe: "Modalidade de cobrança — campo sinalizado na própria tela (BB*) como específico do Banco do Brasil. Não deve ser preenchido em portadores de outros bancos.",
        opcoesObservadas: ["Cobrança Simples", "Cobrança Vinculada", "Cobrança Caucionada", "Cobrança Descontada", "Cobrança Vendor"],
        opcoesExplicadas: {
          "Cobrança Simples": "O valor só é repassado ao cedente depois que o sacado paga o título — sem nenhuma vinculação a operação de crédito.",
          "Cobrança Vinculada": "O título fica vinculado a uma operação de crédito específica do cedente junto ao BB (o recebimento amortiza essa operação).",
          "Cobrança Caucionada": "O título é dado em caução (garantia) de um empréstimo/financiamento tomado pelo cedente junto ao banco.",
          "Cobrança Descontada": "O banco antecipa o valor do título ao cedente (com desconto de juros) antes do vencimento, assumindo o risco do recebimento.",
          "Cobrança Vendor": "Financia a compra do sacado junto ao banco, permitindo que o cedente (vendedor) receba à vista mesmo com o comprador pagando a prazo.",
        },
        observacaoOpcoes: "As explicações de cada modalidade (ver dica ao passar o mouse sobre cada opção) são definições gerais de mercado para esses termos bancários — não vieram de nenhuma tela do BB; confirmar terminologia exata com o banco/contrato do cliente antes de repassar como definitivo." },
      { nome: "Nº Operação", obrigatorio: false, tipo: "texto",
        paraQueServe: "Número da operação de cobrança, exigido por alguns bancos (ex.: Caixa Econômica)." , inferido: true },
      { nome: "Imprimir Boleto?", obrigatorio: false, tipo: "checkbox",
        paraQueServe: "Define se o boleto deste portador é impresso." },
      { nome: "Cobrança Registrada", obrigatorio: false, tipo: "checkbox",
        paraQueServe: "Indica se a cobrança é registrada no banco (vs. cobrança sem registro)." },
      { nome: "Protestar (dias corridos)", obrigatorio: false, tipo: "checkbox + número",
        paraQueServe: "Habilita protesto automático do título após N dias corridos de atraso." },
      { nome: "Pré Impresso", obrigatorio: false, tipo: "checkbox",
        paraQueServe: "Indica que o boleto será emitido em formulário pré-impresso do banco." },
    ],
  },

  abaInstrucoesBanco: {
    titulo: "Instruções para o Banco",
    campos: [
      { nome: "Instruções para Boleto", obrigatorio: false, tipo: "7 linhas de texto livre",
        paraQueServe: "Mensagens impressas no campo de instruções do boleto (juros, multa, desconto, observações)." },
    ],
    variaveisDisponiveis: [
      { tag: "[HIST]", significado: "Histórico" },
      { tag: "[VORI]", significado: "Valor Original" },
      { tag: "[PJUR]", significado: "% Juros Diário" },
      { tag: "[VJUR]", significado: "Valor Juros Diário" },
      { tag: "[PMUL]", significado: "% Multa" },
      { tag: "[VMUL]", significado: "Valor da Multa" },
      { tag: "[PDES]", significado: "% Desconto" },
      { tag: "[VDES]", significado: "Valor Desconto até Vencto" },
      { tag: "[VENC]", significado: "Data Vencimento" },
      { tag: "[EMIS]", significado: "Data Emissão" },
      { tag: "[OBSL]", significado: "Observação" },
      { tag: "[SEGN]", significado: "Segundo Número" },
    ],
  },

  abaImpressao: {
    titulo: "Impressão",
    campos: [
      { nome: "Impressora Padrão", obrigatorio: false, tipo: "texto + seleção",
        paraQueServe: "Impressora usada por padrão ao imprimir o boleto diretamente." },
      { nome: "Destino da Impressão", obrigatorio: false, tipo: "dropdown",
        paraQueServe: "Para onde o boleto é enviado ao imprimir (ex.: impressora física, PDF).", inferido: true },
      { nome: "Formato de Impressão", obrigatorio: false, tipo: "dropdown",
        paraQueServe: "Layout/modelo visual usado para impressão do boleto.", inferido: true },
      { nome: "Espécie de documento", obrigatorio: false, tipo: "dropdown",
        paraQueServe: "Espécie do título de cobrança (ex.: Duplicata Mercantil, Nota Promissória).", inferido: true },
      { nome: "Tipo Cooperativa \"CrediSIS\"", obrigatorio: false, tipo: "dropdown",
        paraQueServe: "Parâmetro específico para cooperativas de crédito do sistema CrediSIS." },
      { nome: "Endereço Impresso", obrigatorio: false, tipo: "dropdown",
        paraQueServe: "Qual endereço do cliente é impresso no boleto (exemplo visto: 'Cobrança')." },
      { nome: "Local de Pagamento", obrigatorio: false, tipo: "texto",
        paraQueServe: "Texto de 'local de pagamento' impresso no boleto (ex.: 'Pagável em qualquer banco até o vencimento')." },
      { nome: "Mensagem Envio whatsapp", obrigatorio: false, tipo: "texto",
        paraQueServe: "Mensagem padrão usada ao enviar o boleto por WhatsApp." },
    ],
  },

  abaRemessaRetorno: {
    titulo: "Remessa/Retorno",
    observacao:
      "As seções 'Caminho Arquivo' e 'Chave API', logo abaixo de Tipo Cobrança API/Código negativação/Dias Negativação, são MUTUAMENTE EXCLUSIVAS e trocam sozinhas conforme o Tipo Cobrança API escolhido: com 'Nenhum' selecionado aparece 'Caminho Arquivo' (pastas de remessa/retorno); com qualquer banco selecionado aparece 'Chave API' (credenciais), com campos extras que variam de banco para banco (ver chaveApiPorTipo).",
    campos: [
      { nome: "Layout CNAB", obrigatorio: true, tipo: "dropdown",
        paraQueServe: "Layout do arquivo de remessa/retorno (ex.: CNAB 240 ou CNAB 400)." },
      { nome: "Másc. Arq.Ret", obrigatorio: false, tipo: "texto",
        paraQueServe: "Máscara/padrão de nome usado para localizar o arquivo de retorno." },
      { nome: "Tipo de Emissão", obrigatorio: false, tipo: "dropdown",
        paraQueServe: "Quem emite o boleto — o próprio cedente ou o banco.", inferido: true },
      { nome: "Seq. Remessa", obrigatorio: false, tipo: "número",
        paraQueServe: "Sequencial do arquivo de remessa. O sistema controla automaticamente, mas o campo pode ser editado manualmente quando necessário." },
      { nome: "Gerar Extrato na Baixa", obrigatorio: false, tipo: "dropdown",
        paraQueServe: "Define se/como um extrato é gerado quando o título é baixado (pago)." , inferido: true },
      { nome: "Marcar Protesto", obrigatorio: false, tipo: "checkbox",
        paraQueServe: "Habilita instrução de protesto no arquivo de remessa." },
      { nome: "Tipo Cobrança API", obrigatorio: true, tipo: "dropdown", critico: true,
        paraQueServe: "CAMPO CHAVE: define qual integração de API/banco será usada por este portador — e também decide se aparece 'Caminho Arquivo' ou 'Chave API' logo abaixo.",
        opcoesObservadas: [
          "Nenhum",
          "Banco Do Brasil API",
          "Banco Do Brasil WS",
          "Banco Itaú",
          "Banco Inter",
          "Cooperativa Sicredi",
          "Sicoob / Bancoob",
          "Banco Santander",
          "Banco Caixa Econômico",
          "Banco Bradesco",
          "Banco Cresol",
          "Banco C6",
        ] },
      { nome: "Código negativação", obrigatorio: false, tipo: "dropdown",
        paraQueServe: "Código enviado ao banco para negativação do título em atraso." },
      { nome: "Dias Negativação", obrigatorio: false, tipo: "número",
        paraQueServe: "Quantidade de dias em atraso para acionar a negativação automática." },

      // ---- Sub-aba "Caminho Arquivo" (Tipo Cobrança API = "Nenhum") ----
      { nome: "Pasta do Arquivo de Remessa", obrigatorio: false, tipo: "texto + busca de pasta",
        paraQueServe: "Pasta local/rede onde o arquivo de remessa é salvo antes do envio ao banco.",
        inferido: true, observacaoInferencia: "Rótulo não estava legível na imagem original; inferido por simetria com 'Pasta do Arquivo de Retorno', logo abaixo." },
      { nome: "Pasta do Arquivo de Retorno", obrigatorio: false, tipo: "texto + busca de pasta",
        paraQueServe: "Pasta local/rede onde o sistema procura o arquivo de retorno do banco." },

      // ---- Sub-aba "Chave API" (Tipo Cobrança API = qualquer banco) ----
      // Campos comuns a praticamente todos os bancos com API.
      { nome: "Ambiente", obrigatorio: true, tipo: "dropdown (Homologação / Produção)",
        paraQueServe: "Define se as credenciais informadas abaixo são de homologação (testes) ou produção (emissão real). Nunca misturar as duas." },
      { nome: "Usa Pix", obrigatorio: false, tipo: "checkbox",
        paraQueServe: "Habilita a geração de QR Code Pix junto com o boleto, para bancos que suportam cobrança híbrida (boleto + Pix).", inferido: true },
      { nome: "Imprimir Sem Registrar", obrigatorio: false, tipo: "checkbox",
        paraQueServe: "Permite imprimir o boleto antes de registrá-lo no banco, para conferência prévia — só vale para pagamento depois de registrado.", inferido: true },
      { nome: "client_id", obrigatorio: true, tipo: "texto",
        paraQueServe: "Identificador da aplicação/cliente OAuth cadastrada no banco — gerado no portal de desenvolvedores do próprio banco." },
      { nome: "client_secret", obrigatorio: true, tipo: "senha",
        paraQueServe: "Chave secreta associada ao client_id, usada para autenticar e gerar o token de acesso à API do banco.",
        observacaoInferencia: "Não aparece para 'Sicoob / Bancoob' — esse banco autentica só com client_id + certificado." },
      // Campos extras, específicos de alguns bancos (ver chaveApiPorTipo).
      { nome: "Chave Pix", obrigatorio: false, tipo: "texto",
        paraQueServe: "Chave Pix do cedente cadastrada no banco, usada para compor o QR Code Pix do boleto (quando 'Usa Pix' está marcado)." },
      { nome: "workspace_id / DeveloperKey", obrigatorio: true, tipo: "texto",
        paraQueServe: "Identificador do workspace/aplicação (específico do Santander), usado junto das credenciais OAuth para autenticar na API.", inferido: true },
      { nome: "Nº Dias Agenda", obrigatorio: false, tipo: "número",
        paraQueServe: "Quantidade de dias que o boleto fica agendado antes de ser efetivamente registrado (específico do Banco Inter).", inferido: true },
      { nome: "Versão API", obrigatorio: false, tipo: "dropdown (V2 / V3)",
        paraQueServe: "Versão da API de cobrança a ser usada na integração (específico do Sicoob/Bancoob)." },
      { nome: "Carregar Certificado .KEY", obrigatorio: false, tipo: "botão de upload",
        paraQueServe: "Carrega o arquivo de chave privada (.KEY) do certificado digital exigido por este banco para autenticação mTLS." },
      { nome: "Carregar Certificado .CRT / .PEM", obrigatorio: false, tipo: "botão de upload",
        paraQueServe: "Carrega o arquivo de certificado público (.CRT ou .PEM), completando o par usado na autenticação mTLS." },
    ],

    // Quais campos extras da "Chave API" aparecem para cada valor de
    // Tipo Cobrança API. Ausente da lista (ou "Nenhum") = mostra
    // "Caminho Arquivo" em vez de "Chave API". `semClientSecret: true`
    // é o único caso (Sicoob/Bancoob) onde o client_secret não aparece.
    chaveApiPorTipo: {
      "Banco Do Brasil API": {},
      "Banco Do Brasil WS": {},
      "Banco Itaú": { chavePix: true, certificado: true },
      "Banco Inter": { nDiasAgenda: true, certificado: true },
      "Cooperativa Sicredi": {},
      "Sicoob / Bancoob": { certificado: true, versaoApi: true, semClientSecret: true },
      "Banco Santander": { workspaceId: true, chavePix: true, certificado: true },
      "Banco Caixa Econômico": {},
      "Banco Bradesco": { certificado: true },
      "Banco Cresol": {},
      "Banco C6": { certificado: true },
    },
  },

  abaOutrosDados: {
    titulo: "Outros Dados",
    observacao:
      "A maior parte dos campos desta aba é específica de UM banco só (CEF, Sicredi ou Sicoob) — o nome de cada campo abaixo indica entre parênteses a qual banco ele pertence, quando aplicável. Os demais (SPED Fiscal, checkboxes 'Usa X') são de uso mais geral.",
    campos: [
      { nome: "Acrescer % Multa", obrigatorio: false, tipo: "número (%)",
        paraQueServe: "Percentual adicional a ser acrescido no cálculo da multa do boleto.", inferido: true },
      { nome: "R$ Taxa Boleto", obrigatorio: false, tipo: "número (R$)",
        paraQueServe: "Valor de taxa cobrada por boleto emitido (tarifa bancária repassada ao cliente).", inferido: true },
      { nome: "Vl. Mínimo Boleto", obrigatorio: false, tipo: "número (R$)",
        paraQueServe: "Valor mínimo permitido para emissão de um boleto por este portador.", inferido: true },
      { nome: "Campo Extra", obrigatorio: false, tipo: "texto",
        paraQueServe: "Campo livre/genérico, de uso variável conforme a necessidade do cedente.", inferido: true },
      { nome: "Ultimo Boleto", obrigatorio: false, tipo: "número",
        paraQueServe: "Número do último boleto emitido por este portador — controle interno de sequência.", inferido: true },

      // ---- Grupo "Somente para Banco CEF" ----
      { nome: "Layout (CEF)", obrigatorio: false, tipo: "dropdown",
        paraQueServe: "Layout específico da Caixa Econômica Federal para este portador.",
        opcoesObservadas: ["1 - SICOOB", "2 - SIGCB"] },
      { nome: "Dia(s) p/ Dev. (CEF)", obrigatorio: false, tipo: "número",
        paraQueServe: "Quantidade de dias para devolução do título, específica do layout CEF.", inferido: true },

      // ---- Grupo "Somente para Banco Sicredi" ----
      { nome: "Posto (Sicredi)", obrigatorio: false, tipo: "texto",
        paraQueServe: "Código do posto de atendimento Sicredi vinculado à conta de cobrança.", inferido: true },
      { nome: "Ano(AA) (Sicredi)", obrigatorio: false, tipo: "texto",
        paraQueServe: "Dois dígitos do ano usados na composição do Nosso Número para carteiras Sicredi.", inferido: true },
      { nome: "Byte (Sicredi)", obrigatorio: false, tipo: "texto",
        paraQueServe: "Dígito de identificação (byte) exigido pelo layout de cobrança Sicredi.", inferido: true },
      { nome: "Nosso Número Composto", obrigatorio: false, tipo: "checkbox",
        paraQueServe: "Indica que o Nosso Número deste portador é composto (Posto + Ano + Byte + sequencial), conforme exigido pelo Sicredi.", inferido: true },

      { nome: "Razão Social", obrigatorio: false, tipo: "texto",
        paraQueServe: "Razão social do cedente informada nesta aba — usada por integrações específicas (ex.: SPED Fiscal) que pedem o dado separado do cadastro principal.", inferido: true },
      { nome: "CNPJ", obrigatorio: false, tipo: "texto",
        paraQueServe: "CNPJ do cedente informado nesta aba, para as mesmas integrações específicas que usam 'Razão Social' acima.", inferido: true },
      { nome: "Endereço", obrigatorio: false, tipo: "texto",
        paraQueServe: "Endereço do cedente usado por integrações/relatórios específicos desta aba.", inferido: true },

      // ---- Grupo "Banco Sicoob" ----
      { nome: "Nº Layout Lote", obrigatorio: false, tipo: "dropdown",
        paraQueServe: "Layout do lote de remessa específico do Sicoob para este portador.",
        opcoesObservadas: ["040", "045"] },
      { nome: "Desconsidera Data Juros e Multa", obrigatorio: false, tipo: "checkbox",
        paraQueServe: "Ignora a data configurada de juros/multa ao gerar o boleto para o Sicoob.", inferido: true },

      { nome: "Fone Beneficiário", obrigatorio: false, tipo: "texto",
        paraQueServe: "Telefone de contato do beneficiário (cedente), usado em alguns layouts de boleto/remessa." },
      { nome: "Cod. Avalista", obrigatorio: false, tipo: "busca",
        paraQueServe: "Código do avalista vinculado ao título, quando aplicável.", inferido: true },
      { nome: "C.Custo Taxa Adm", obrigatorio: false, tipo: "busca",
        paraQueServe: "Centro de custo para o qual a taxa administrativa da cobrança é lançada.", inferido: true },

      // ---- Grupo "SPED Fiscal - Registro 1601" ----
      { nome: "Cod. Participante", obrigatorio: false, tipo: "busca",
        paraQueServe: "Código do participante exigido no Registro 1601 do SPED Fiscal, para escrituração da cobrança." },
      { nome: "Cod. Intermediário", obrigatorio: false, tipo: "busca",
        paraQueServe: "Código do intermediário financeiro exigido no Registro 1601 do SPED Fiscal, quando a cobrança envolve intermediador de pagamento." },

      { nome: "Dias Min. Ven. Boleto", obrigatorio: false, tipo: "número",
        paraQueServe: "Quantidade mínima de dias entre a emissão e o vencimento exigida para gerar o boleto.", inferido: true },

      // ---- Checkboxes de uso geral ----
      { nome: "Usa Unicred", obrigatorio: false, tipo: "checkbox",
        paraQueServe: "Indica que este portador opera com a cooperativa Unicred.", inferido: true },
      { nome: "Barra Desconto Duplicata", obrigatorio: false, tipo: "checkbox",
        paraQueServe: "Habilita impressão de código de barras com desconto para duplicata.", inferido: true },
      { nome: "Usa Banco Fator FIDC", obrigatorio: false, tipo: "checkbox",
        paraQueServe: "Indica que a cobrança é operada através do Banco Fator (FIDC — Fundo de Investimento em Direitos Creditórios).", inferido: true },
      { nome: "Usa Banco IB Sigma", obrigatorio: false, tipo: "checkbox",
        paraQueServe: "Indica que a cobrança é operada através do Banco IB Sigma.", inferido: true },
      { nome: "Usa Cobrança Escritural", obrigatorio: false, tipo: "checkbox",
        paraQueServe: "Habilita cobrança escritural (sem emissão física de boleto, débito direto em conta).", inferido: true },
      { nome: "Usa Financeira NASA", obrigatorio: false, tipo: "checkbox",
        paraQueServe: "Indica que a cobrança é operada através da financeira NASA.", inferido: true },
      { nome: "Usar Endereço Portador", obrigatorio: false, tipo: "checkbox",
        paraQueServe: "Usa o endereço cadastrado no Portador (em vez do endereço do cliente) na impressão do boleto.", inferido: true },
    ],
  },
};

// ============================================================
// 2. BANCOS
// ============================================================
// status: "configurado" | "em_documentacao" | "teste_disponivel"
const bancos = [
  {
    id: "bb",
    nome: "Banco do Brasil",
    codigoBanco: "001",
    status: "em_documentacao",
    resumo:
      "Portador aceita duas opções de integração distintas: 'Banco Do Brasil API' (API REST/OAuth atual) e 'Banco Do Brasil WS' (integração via Web Service, modelo mais antigo). A escolha é feita no campo Tipo Cobrança API.",
    tipoIntegracao: ["Banco Do Brasil API", "Banco Do Brasil WS"],
    autenticacao: "OAuth 2.0 — Client Credentials (Client ID + Client Secret)",
    certificado: "Não exigido para a API REST em condições normais; confirmar caso a caso com o cliente.",
    ambientes: ["Homologação", "Produção"],
    corDestaque: "#f7ce17",
    versao: "1.0",
    ultimaAtualizacao: "2026-08-22",

    camposPortadorRelevantes: [
      "Código do Cedente", "DV", "Carteira", "Var *", "Nosso Número (Próx)", "Tam",
      "Nº Convênio", "Modalidade Cobrança (BB*)", "Tipo Cobrança API",
    ],

    integracaoApi: {
      origem: "publica",
      observacao:
        "Os campos abaixo NÃO vieram das imagens do Portador — são os parâmetros publicamente documentados pelo Banco do Brasil para a API de Cobranças (Portal Developers BB). Confirmar sempre com a documentação oficial vigente e com o gerente/contrato do cliente antes de usar em produção.",
      campos: [
        { nome: "Client ID", obrigatorio: true, ambiente: "Homologação e Produção",
          paraQueServe: "Identifica a aplicação registrada no Portal Developers do BB.",
          ondeConseguir: "Portal Developers do Banco do Brasil, na aplicação criada para Cobranças.",
          exemplo: "eyJpZCI6IjEyMzQ1Njc4In0 (NUNCA usar credencial real em documentação/tela)" },
        { nome: "Client Secret", obrigatorio: true, ambiente: "Homologação e Produção",
          paraQueServe: "Chave secreta usada junto ao Client ID para gerar o token OAuth.",
          ondeConseguir: "Portal Developers do Banco do Brasil (gerada junto com o Client ID).",
          exemplo: "••••••••••• (nunca exibir/gravar em texto puro no frontend)" },
        { nome: "Application Key / Developer Key", obrigatorio: true, ambiente: "Homologação e Produção",
          paraQueServe: "Chave adicional exigida pelo BB em algumas APIs, enviada por header/query.",
          ondeConseguir: "Portal Developers do Banco do Brasil.",
          exemplo: "gw-dev-app-key" },
        { nome: "Número do Convênio", obrigatorio: true, ambiente: "Homologação e Produção",
          paraQueServe: "Vincula a cobrança ao contrato de cobrança do cliente com o BB — corresponde ao campo 'Nº Convênio' do Portador.",
          ondeConseguir: "Contrato de cobrança do cliente com o Banco do Brasil.",
          exemplo: "1234567" },
        { nome: "Carteira / Variação", obrigatorio: true, ambiente: "Homologação e Produção",
          paraQueServe: "Define a modalidade de cobrança — corresponde aos campos 'Carteira' e 'Var *' do Portador.",
          ondeConseguir: "Contrato de cobrança / carta de circular do BB para o cliente.",
          exemplo: "Carteira 17, Variação 019" },
      ],
    },

    dadosCliente: [
      "Agência e conta corrente",
      "Código do cedente / número de convênio junto ao BB",
      "Carteira e variação da carteira contratadas",
      "Client ID e Client Secret gerados no Portal Developers BB",
      "Application Key (se exigida pela API contratada)",
      "Confirmação se a integração será via 'Banco Do Brasil API' ou 'Banco Do Brasil WS'",
    ],

    checklist: [
      "Cliente possui contrato de cobrança ativo com o Banco do Brasil.",
      "Ficou definido com o cliente se a integração é via API REST ou via WS.",
      "Aplicação foi criada no Portal Developers do BB e as credenciais foram geradas.",
      "Número de convênio, carteira e variação foram confirmados.",
      "Ambiente (Homologação/Produção) foi identificado antes de preencher credenciais.",
    ],

    passoAPasso: [
      { passo: 1, titulo: "Abrir o cadastro do Portador", texto: "No ERP, acesse o cadastro de Portador (tela mostrada nas imagens de referência)." },
      { passo: 2, titulo: "Selecionar o Tipo Cobrança API", texto: "Na aba Remessa/Retorno, escolha 'Banco Do Brasil API' ou 'Banco Do Brasil WS', conforme definido com o cliente." },
      { passo: 3, titulo: "Preencher Dados do Cedente", texto: "Informe Código do Cedente, DV, Carteira, Var, Nosso Número (Próx), Tam e Nº Convênio." },
      { passo: 4, titulo: "Preencher Modalidade de Cobrança (BB*)", texto: "Selecione a modalidade indicada no contrato do cliente com o BB." },
      { passo: 5, titulo: "Salvar o portador", texto: "Clique em Salvar para gravar os dados básicos antes de testar a integração." },
      { passo: 6, titulo: "Executar o teste de autenticação", texto: "Assim que a Fase de Testes de API estiver disponível no painel, validar geração de token antes de emitir boletos reais." },
    ],

    testes: {
      status: "nao_integrado",
      itens: [
        { nome: "Geração de Token (OAuth)", status: "nao_testado" },
        { nome: "Consulta de Beneficiário", status: "nao_testado" },
        { nome: "Emissão de Boleto (teste)", status: "nao_testado" },
      ],
    },

    erros: [
      {
        codigo: "401",
        titulo: "Unauthorized",
        causas: ["Client ID incorreto", "Client Secret incorreto", "Token expirado", "Ambiente errado (credencial de Homologação usada em Produção, ou vice-versa)"],
      },
    ],
  },

  { id: "sicredi", nome: "Sicredi", codigoBanco: "748", status: "em_documentacao",
    resumo: "Aparece no Portador como 'Cooperativa Sicredi' no campo Tipo Cobrança API. Documentação detalhada pendente — aguardando imagens/dados específicos.",
    tipoIntegracao: ["Cooperativa Sicredi"], autenticacao: "A confirmar", certificado: "A confirmar",
    ambientes: ["Homologação", "Produção"], corDestaque: "#5cb130", versao: "0.1", ultimaAtualizacao: "2026-08-22",
    camposPortadorRelevantes: ["Tipo Cobrança API", "Posto (Sicredi)", "Ano(AA) (Sicredi)", "Byte (Sicredi)", "Nosso Número Composto"], dadosCliente: [], checklist: [], passoAPasso: [],
    testes: { status: "nao_integrado", itens: [] }, erros: [] },

  { id: "sicoob", nome: "Sicoob", codigoBanco: "756", status: "em_documentacao",
    resumo: "Aparece no Portador como 'Sicoob / Bancoob' no campo Tipo Cobrança API. Documentação detalhada pendente.",
    tipoIntegracao: ["Sicoob / Bancoob"], autenticacao: "A confirmar", certificado: "A confirmar",
    ambientes: ["Homologação", "Produção"], corDestaque: "#00995d", versao: "0.1", ultimaAtualizacao: "2026-08-22",
    camposPortadorRelevantes: ["Tipo Cobrança API", "Nº Layout Lote", "Desconsidera Data Juros e Multa"], dadosCliente: [], checklist: [], passoAPasso: [],
    testes: { status: "nao_integrado", itens: [] }, erros: [] },

  { id: "inter", nome: "Banco Inter", codigoBanco: "077", status: "em_documentacao",
    resumo: "Aparece no Portador como 'Banco Inter' no campo Tipo Cobrança API. Documentação detalhada pendente.",
    tipoIntegracao: ["Banco Inter"], autenticacao: "A confirmar", certificado: "A confirmar",
    ambientes: ["Homologação", "Produção"], corDestaque: "#ff7a00", versao: "0.1", ultimaAtualizacao: "2026-08-22",
    camposPortadorRelevantes: ["Tipo Cobrança API"], dadosCliente: [], checklist: [], passoAPasso: [],
    testes: { status: "nao_integrado", itens: [] }, erros: [] },

  { id: "santander", nome: "Santander", codigoBanco: "033", status: "em_documentacao",
    resumo: "Aparece no Portador como 'Banco Santander' no campo Tipo Cobrança API. Documentação detalhada pendente.",
    tipoIntegracao: ["Banco Santander"], autenticacao: "A confirmar", certificado: "A confirmar",
    ambientes: ["Homologação", "Produção"], corDestaque: "#ec0000", versao: "0.1", ultimaAtualizacao: "2026-08-22",
    camposPortadorRelevantes: ["Tipo Cobrança API"], dadosCliente: [], checklist: [], passoAPasso: [],
    testes: { status: "nao_integrado", itens: [] }, erros: [] },

  { id: "itau", nome: "Itaú", codigoBanco: "341", status: "em_documentacao",
    resumo: "Aparece no Portador como 'Banco Itaú' no campo Tipo Cobrança API. Documentação detalhada pendente.",
    tipoIntegracao: ["Banco Itaú"], autenticacao: "A confirmar", certificado: "A confirmar",
    ambientes: ["Homologação", "Produção"], corDestaque: "#ec7000", versao: "0.1", ultimaAtualizacao: "2026-08-22",
    camposPortadorRelevantes: ["Tipo Cobrança API"], dadosCliente: [], checklist: [], passoAPasso: [],
    testes: { status: "nao_integrado", itens: [] }, erros: [] },

  { id: "bradesco", nome: "Bradesco", codigoBanco: "237", status: "em_documentacao",
    resumo: "Confirmado: aparece no Portador como 'Banco Bradesco' no campo Tipo Cobrança API, com aba 'Chave API' exigindo client_id/client_secret e certificado digital (.KEY + .CRT/.PEM). Documentação detalhada de credenciais ainda pendente.",
    tipoIntegracao: ["Banco Bradesco"], autenticacao: "OAuth (client_id / client_secret) + certificado mTLS", certificado: "Exigido (.KEY + .CRT/.PEM)",
    ambientes: ["Homologação", "Produção"], corDestaque: "#cc092f", versao: "0.2", ultimaAtualizacao: "2026-08-23",
    camposPortadorRelevantes: ["Tipo Cobrança API"], dadosCliente: [], checklist: [], passoAPasso: [],
    testes: { status: "nao_integrado", itens: [] }, erros: [] },

  { id: "caixa", nome: "Caixa Econômica", codigoBanco: "104", status: "em_documentacao",
    resumo: "Aparece no Portador como 'Banco Caixa Econômico' no campo Tipo Cobrança API, com aba 'Chave API' exigindo apenas client_id/client_secret (sem certificado). Documentação detalhada pendente.",
    tipoIntegracao: ["Banco Caixa Econômico"], autenticacao: "OAuth (client_id / client_secret)", certificado: "Não exigido, conforme observado na tela",
    ambientes: ["Homologação", "Produção"], corDestaque: "#005ca9", versao: "0.2", ultimaAtualizacao: "2026-08-23",
    camposPortadorRelevantes: ["Tipo Cobrança API", "Nº Operação", "Layout (CEF)", "Dia(s) p/ Dev. (CEF)"], dadosCliente: [], checklist: [], passoAPasso: [],
    testes: { status: "nao_integrado", itens: [] }, erros: [] },

  { id: "cresol", nome: "Cresol", codigoBanco: "133", status: "em_documentacao",
    resumo: "Aparece no Portador como 'Banco Cresol' no campo Tipo Cobrança API, com aba 'Chave API' exigindo apenas client_id/client_secret (sem certificado). Documentação detalhada pendente.",
    tipoIntegracao: ["Banco Cresol"], autenticacao: "OAuth (client_id / client_secret)", certificado: "Não exigido, conforme observado na tela",
    ambientes: ["Homologação", "Produção"], corDestaque: "#3f7d20", versao: "0.1", ultimaAtualizacao: "2026-08-23",
    camposPortadorRelevantes: ["Tipo Cobrança API"], dadosCliente: [], checklist: [], passoAPasso: [],
    testes: { status: "nao_integrado", itens: [] }, erros: [] },

  { id: "c6", nome: "C6 Bank", codigoBanco: "336", status: "em_documentacao",
    resumo: "Aparece no Portador como 'Banco C6' no campo Tipo Cobrança API, com aba 'Chave API' exigindo client_id/client_secret e certificado digital (.KEY + .CRT/.PEM). Documentação detalhada pendente.",
    tipoIntegracao: ["Banco C6"], autenticacao: "OAuth (client_id / client_secret) + certificado mTLS", certificado: "Exigido (.KEY + .CRT/.PEM)",
    ambientes: ["Homologação", "Produção"], corDestaque: "#1c1c1c", versao: "0.1", ultimaAtualizacao: "2026-08-23",
    camposPortadorRelevantes: ["Tipo Cobrança API"], dadosCliente: [], checklist: [], passoAPasso: [],
    testes: { status: "nao_integrado", itens: [] }, erros: [] },
];

// Base de conhecimento de erros/soluções — gerais (não vinculados a 1 banco).
const baseConhecimentoGeral = [
  { erro: "401 — Unauthorized", causa: "Client ID/Secret incorretos, token expirado ou ambiente trocado (Homologação x Produção).",
    solucao: "Conferir credenciais no cadastro do Portador e confirmar se o Tipo Cobrança API selecionado corresponde ao ambiente correto.",
    observacao: "Padrão comum a integrações OAuth 2.0; validar mensagem exata retornada pelo banco em cada caso." },
  { erro: "Certificado inválido", causa: "Certificado vencido, senha incorreta, certificado trocado ou formato não suportado.",
    solucao: "Solicitar novo certificado ao cliente/banco e reconferir a senha cadastrada.",
    observacao: "Aplicável apenas a bancos cujo Tipo Cobrança API exija certificado digital." },
];

window.CentralBoletos = window.CentralBoletos || {};
window.CentralBoletos.portadorCamposBase = portadorCamposBase;
window.CentralBoletos.bancos = bancos;
window.CentralBoletos.baseConhecimentoGeral = baseConhecimentoGeral;
