# TGA API Explorer

Console de testes para as APIs da TGA Sistemas — autenticação, exploração de
endpoints, visualização de request/response e histórico de sessão.

API base: `https://api.tgasistemas.io/v1`
Documentação oficial: `https://api.tgasistemas.io/v1/docs/#/`

## Funcionalidades

- **Login**: autentica em `POST /usuarios-api/login`, guarda o token em
  `sessionStorage` (nunca em `localStorage`) e mostra tempo restante de
  validade lendo o payload do JWT.
- **Explorador de endpoints**: 51 endpoints em 8 categorias (Autenticação,
  Misc, Configurações, Clientes, Propriedades, Produtos, Movimentos, Ordem de
  Serviço, Checklist), todos descritos por configuração em
  [js/endpoints.js](js/endpoints.js) e renderizados por um motor genérico em
  [js/explorer.js](js/explorer.js) — adicionar um endpoint novo não exige
  escrever HTML.
- **Request/Response viewer** com quatro abas: JSON, Tabela, Ficha (cartões de
  registro com rótulo legível e valor formatado — moeda, percentual, data) e
  Raw. Campos de imagem em Base64 (ex.: foto de produto) são detectados e
  exibidos como miniatura clicável em vez de texto cru.
- **Gerador de código**: exemplo pronto em JavaScript/fetch, PHP/cURL e cURL
  de terminal a partir da última requisição executada.
- **Exportar CSV** da lista de registros da resposta atual.
- **Histórico** de requisições em memória (some ao fechar a aba).
- **Tema claro/escuro**, persistido em `localStorage`.
- Suporte a endpoints com parâmetros de URL, corpo JSON editável e aviso
  visual para métodos que gravam dados (POST/PUT/DELETE).

## Estrutura de arquivos

```text
swagger/
├── index.php
├── css/
│   └── app.css        # tokens de tema (escuro padrão + claro), todo o visual
├── js/
│   ├── app.js          # orquestração / eventos da UI
│   ├── theme.js         # alternância de tema escuro/claro (localStorage)
│   ├── storage.js       # token em sessionStorage
│   ├── api.js           # wrapper de fetch, medição de tempo, base URL
│   ├── auth.js           # fluxo de login
│   ├── ui.js             # renderização (viewers, badges, histórico, máscaras)
│   ├── endpoints.js       # registro (config) dos endpoints
│   ├── explorer.js        # motor genérico: monta card/form a partir da config
│   ├── jwt.js              # decodifica payload do JWT (só leitura, sem checar assinatura)
│   └── codegen.js           # gera exemplos JS/PHP/cURL a partir do request executado
└── README.md
```

Não uso backend próprio além do `include` de controle de acesso no topo do
`index.php` — falo direto com `api.tgasistemas.io` (CORS liberado pela
própria API).

### Como adicionar um novo endpoint

Basta adicionar um objeto ao array `TGA_ENDPOINTS` em
[js/endpoints.js](js/endpoints.js):

```javascript
{
  id: 'meu-endpoint',              // único, usado internamente
  category: 'misc',                 // categoria existente ou uma nova
  categoryLabel: 'Misc',             // rótulo do grupo na sidebar (livre)
  menuLabel: 'Nome na sidebar',
  method: 'GET',                     // GET | POST | PUT | PATCH | DELETE
  path: '/meu-endpoint/{id}',         // relativo a /v1; {token} vira campo obrigatório
  title: 'Título do card',
  description: 'Texto explicativo.',
  requiresAuth: true,                 // false só para rotas públicas como /status
  pathParams: [                        // opcional — um item por {token} no path
    { name: 'id', label: 'ID do registro', placeholder: 'ex: 123' },
  ],
  params: TGA_LIST_PARAMS,             // reaproveita page/q/order/orderby/filter,
                                        // ou defina um array próprio no mesmo formato
  body: { CAMPO: '' },                  // opcional — vira textarea JSON editável (POST/PUT/DELETE)
}
```

Um `category`/`categoryLabel` novo já cria um grupo sozinho na sidebar. Deixo
o motor em [js/explorer.js](js/explorer.js) cuidar do resto: form, header
`Authorization`, request/response viewer, histórico e tratamento de 401. Não
preciso de tratamento especial para endpoints com corpo (POST/PUT) ou
parâmetro na URL — valido os campos obrigatórios e o JSON do corpo antes de
disparar a requisição, e mostro o aviso "grava dados reais" automaticamente
sempre que `method !== 'GET'`.

## Como rodar localmente

Meu `index.php` inclui `../../verifica_acesso.php` (controle de acesso do
sistema principal), então preciso mesmo de PHP para rodar — não dá para abrir
direto por `file://` nem servir só como estático:

```bash
cd swagger
php -S localhost:8090
```

Depois acesse `http://localhost:8090/`. Em produção, é só a mesma pasta
publicada dentro do sistema, no caminho de onde `verifica_acesso.php` é
alcançável em `../../`.

## Notas sobre a API da TGA

- **Header de autenticação**: apesar da documentação da rota de login dizer
  apenas "enviado no header `authorization`", o formato exigido de fato é
  `Authorization: Bearer <token>`. Sem o prefixo `Bearer`, a API responde como
  se nenhum token tivesse sido enviado. Implementado em [js/api.js](js/api.js).
- **`GET /v1/status`** não exige token na prática (mesmo o spec listando
  `security` pra essa rota) — por isso é usado tanto no teste de
  conectividade quanto como endpoint público de exemplo.
- **CORS está liberado** (`access-control-allow-origin: *`), então fiz tudo
  client-side, sem proxy — só uso `fetch` simples (sem `OPTIONS`) para não
  disparar preflight à toa.

## Segurança — o que apliquei

- Nenhuma credencial fixa no código.
- Senha nunca é impressa no console nem persistida (nem `sessionStorage`,
  nem `localStorage`) — o campo é limpo após cada tentativa de login.
- Token vive apenas em `sessionStorage` (não sobrevive ao fechar o navegador).
- Token nunca aparece completo por padrão nos viewers — sempre mascarado, com
  ação explícita ("Mostrar") para revelar.
- Token nunca é colocado em URL.

## Possíveis melhorias futuras

- Favoritar endpoints usados com frequência (persistir em `localStorage`).
- Suporte a mais endpoints com corpo/parâmetro de URL fora do padrão de
  listagem simples (hoje meu motor genérico só cobre GET com paginação; para
  rotas fora desse padrão preciso estender a config em `js/endpoints.js`).
