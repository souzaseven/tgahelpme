/* ===== DADOS ESTÁTICOS DO VALIDADOR DE BOLETO ===== */
/* Este arquivo não depende de nada e não deve tocar o DOM.               */
/* Deve ser carregado ANTES de boleto.js e script.js.                     */

/* ===== MAPA DE BANCOS (código de compensação -> nome) ===== */
/* Lista conservadora: só entram códigos amplamente confirmados em fontes */
/* públicas (Bacen/FEBRABAN). Um código não mapeado NÃO é um erro — a     */
/* ferramenta exibe "banco não identificado no mapa local" nesse caso, em */
/* vez de arriscar um nome errado. */
// Atribuído explicitamente em "window" (em vez de "const"/"let" soltos no
// topo do arquivo) porque uma declaração const/let de nível superior NÃO
// vira propriedade de window em um <script> comum — e boleto.js precisa
// enxergar esse objeto através de window.BANCOS.
// Lista ampliada e cross-referenciada em várias fontes públicas (listas de
// código COMPE de bancos, incluindo material institucional de universidades
// e o próprio blog da Asaas) em agosto/2026. Códigos onde as fontes
// divergiam entre si sobre o nome do banco (ex.: 085, 280, 349, 654) foram
// propositalmente deixados de fora — mesma regra de sempre: melhor não
// mapear do que arriscar um nome errado.
window.BANCOS = {
    '001': 'Banco do Brasil',
    '003': 'Banco da Amazônia',
    '004': 'Banco do Nordeste do Brasil',
    '007': 'BNDES',
    '010': 'Credicoamo Crédito Rural Cooperativa',
    '021': 'Banestes',
    '025': 'Banco Alfa',
    '033': 'Santander',
    '037': 'Banco do Estado do Pará (Banpará)',
    '041': 'Banrisul',
    '047': 'Banco do Estado de Sergipe (Banese)',
    '051': 'Banco de Desenvolvimento do Espírito Santo',
    '069': 'Banco Crefisa',
    '070': 'BRB - Banco de Brasília',
    '074': 'Banco J. Safra',
    '077': 'Banco Inter',
    '082': 'Banco Topázio',
    '091': 'Unicred Central RS',
    '094': 'Banco Finaxis',
    '096': 'Banco B3',
    '102': 'XP Investimentos',
    '104': 'Caixa Econômica Federal',
    '107': 'Banco BOCOM BBM',
    '120': 'Banco Rodobens',
    '121': 'Banco Agibank',
    '128': 'Banco Braza',
    '136': 'Unicred do Brasil',
    '172': 'Albatross Corretora de Câmbio e Valores',
    '184': 'Banco Itaú BBA',
    '197': 'Stone Pagamentos',
    '204': 'Banco Bradesco Cartões',
    '208': 'Banco BTG Pactual',
    '212': 'Banco Original',
    '213': 'Banco Arbi',
    '217': 'Banco John Deere',
    '218': 'Banco BS2',
    '222': 'Banco Credit Agricole Brasil',
    '224': 'Banco Fibra',
    '233': 'Banco Cifra',
    '237': 'Bradesco',
    '241': 'Banco Clássico',
    '246': 'Banco ABC Brasil',
    '254': 'Paraná Banco',
    '260': 'Nu Pagamentos (Nubank)',
    '265': 'Banco Fator',
    '266': 'Banco Cédula',
    '269': 'Banco HSBC',
    '290': 'PagBank (PagSeguro Internet)',
    '299': 'Sorocred Crédito',
    '313': 'Amazônia Corretora de Câmbio',
    '318': 'Banco BMG',
    '323': 'Mercado Pago',
    '335': 'Banco Digio',
    '336': 'Banco C6 (C6 Bank)',
    '340': 'Superdigital',
    '341': 'Itaú Unibanco',
    '366': 'Société Générale Brasil',
    '367': 'Vitreo Distribuidora de Títulos e Valores Mobiliários',
    '370': 'Banco Mizuho do Brasil',
    '376': 'Banco J.P. Morgan',
    '380': 'PicPay Serviços',
    '389': 'Banco Mercantil do Brasil',
    '394': 'Banco Bradesco Financiamentos',
    '399': 'Kirton Bank (antigo HSBC Brasil)',
    '412': 'Banco Capital',
    '422': 'Banco Safra',
    '456': 'Banco MUFG Brasil',
    '461': 'Asaas',
    '464': 'Banco Sumitomo Mitsui Brasileiro',
    '473': 'Banco Caixa Geral Brasil',
    '479': 'ItaúBank',
    '487': 'Deutsche Bank',
    '505': 'Banco Credit Suisse (Brasil)',
    '600': 'Banco Luso Brasileiro',
    '604': 'Banco Industrial do Brasil',
    '610': 'Banco VR',
    '611': 'Banco Paulista',
    '612': 'Banco Guanabara',
    '613': 'Omni Banco',
    '623': 'Banco Pan',
    '626': 'Banco Ficsa',
    '630': 'Letsbank / Smartbank',
    '633': 'Banco Rendimento',
    '634': 'Banco Triângulo',
    '641': 'Banco Alvorada',
    '643': 'Banco Pine',
    '652': 'Itaú Unibanco Holding',
    '653': 'Banco Indusval',
    '655': 'Banco Votorantim',
    '707': 'Banco Daycoval',
    '712': 'Banco Ourinvest',
    '720': 'Banco Maxinvest',
    '735': 'Neon Pagamentos',
    '739': 'Banco Cetelem',
    '741': 'Banco Ribeirão Preto',
    '743': 'Banco Semear',
    '745': 'Banco Citibank',
    '746': 'Banco Modal',
    '747': 'Banco Rabobank International do Brasil',
    '748': 'Banco Cooperativo Sicredi',
    '751': 'Scotiabank Brasil',
    '752': 'Banco BNP Paribas Brasil',
    '755': 'Bank of America Merrill Lynch',
    '756': 'Sicoob (Bancoob)',
    '757': 'Banco KEB Hana do Brasil'
};

/* ===== CÓDIGOS DE MOEDA (posição 4 da linha/código de barras) ===== */
window.MOEDAS = {
    '9': 'Real (BRL)'
    /* O código "9" é o único em uso corrente desde o Plano Real. */
};
