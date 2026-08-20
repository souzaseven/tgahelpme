# TGA API Explorer

Console de testes para as APIs da TGA Sistemas — autenticação, exploração de
endpoints, visualização de request/response e histórico de sessão.

API base: `https://api.tgasistemas.io/v1`
Documentação oficial: `https://api.tgasistemas.io/v1/docs/#/`

## Status do desenvolvimento

- [x] **Fase 1 — Login**: formulário, POST `/usuarios-api/login`, captura de
      resposta, tratamento de erro 401.
- [x] **Fase 2 — Token**: sessão em `sessionStorage`, exibir/ocultar/copiar/limpar,
      máscara de token no viewer, histórico em memória, teste de conectividade.
- [x] **Fase 3 — Primeiro endpoint Misc**: `GET /v1/regioes`, com paginação/
      ordenação/filtro, header `Authorization: Bearer` automático e tratamento
      de sessão expirada (401 → botão "Autenticar novamente").
- [x] **Fase 4 — Componente genérico**: 9 endpoints Misc (status + 8 de
      listagem) descritos por configuração em [js/endpoints.js](js/endpoints.js)
      e renderizados por um único motor em [js/explorer.js](js/explorer.js) —
      zero HTML hardcoded por endpoint. Sidebar lista todos, clicáveis.
- [x] **Visual**: tema escuro como padrão (com opção de tema claro,
      persistida em `localStorage`), layout reorganizado em seções.
- [x] **Indicador de validade do token**: decodifica o JWT (só o payload,
      sem verificar assinatura) e mostra "Expira em..." na Sessão da API,
      com expiração proativa a cada 30s — não depende de levar um 401.
- [x] **Visualização em tabela/Raw da resposta**: abas JSON / Tabela / Raw no
      Response Viewer, com busca que filtra linhas da tabela.
- [x] **Gerador de código**: JavaScript/fetch, PHP/cURL e cURL de terminal a
      partir da última requisição executada. Senha nunca aparece (nem sob
      pedido); token só aparece completo com opt-in explícito (checkbox).
- [x] **Exportar CSV**: botão no Response Viewer baixa a lista de registros
      da resposta (mesma detecção da aba Tabela) em CSV compatível com Excel.
- [x] **Categoria Configurações (TGA ERP)**: `GET /v1/config/parametros`,
      `/config/empresas`, `/config/filiais`. Sidebar agora agrupa por
      categoria dinamicamente (não é mais fixo em "Misc").
- [x] **Motor estendido para POST/PUT/DELETE com corpo e parâmetros de URL**:
      o componente genérico agora suporta `pathParams` (ex.: `{codcfo}` na
      URL, com validação — não deixa executar com campo vazio) e `body`
      (textarea de JSON pré-preenchido e editável, com validação de sintaxe
      antes de enviar). Endpoints que gravam dados (método ≠ GET) mostram um
      aviso visual (⚠) no card.
- [x] **51 endpoints ao todo**, em 8 categorias: Autenticação (1), Misc (11),
      Configurações (3), Clientes (14), Propriedades (2), Produtos (8),
      Movimentos (9), Ordem de Serviço (1), Checklist (3).
- [x] **Aba "Ficha" no Response Viewer**: dados exibidos como cartões de
      registro (título + selo de código, campos com rótulo legível e valor
      formatado — moeda em R$, percentual, data em pt-BR), em vez de só JSON
      ou tabela de planilha. Funciona tanto pra listas quanto pra um único
      registro (`{data: {...}}`).
- [x] **Botão "Expandir"**: abre a Tabela/Ficha/JSON num modal de tela cheia
      (o mesmo elemento é movido no DOM, sem duplicar lógica) — resolve o
      corte de conteúdo quando a resposta é larga e o painel divide espaço
      com o Request. Fecha com o botão, clique fora ou Esc.
- [x] **Tabela: primeira coluna fixa + aviso de scroll**: em tabelas com
      muitas colunas, a primeira coluna (geralmente o campo mais
      identificador) fica fixa ao rolar pros lados, e um aviso "role para o
      lado" aparece automaticamente só quando a tabela realmente não cabe na
      largura disponível (detectado via `scrollWidth`, não é um palpite).
- [x] **Gerador de código recolhido por padrão**: o painel some do fluxo
      normal da página (só o título + botão "Mostrar" ficam visíveis) —
      continua funcionando igual quando aberto, só não fica ocupando espaço
      sem necessidade.

## Descobertas técnicas confirmadas na API real

Testado diretamente contra `api.tgasistemas.io` antes de codificar (via `curl`):

- **CORS está liberado** (`access-control-allow-origin: *` em todas as respostas,
  inclusive preflight `OPTIONS`). Por isso a Fase 1 e 2 foram implementadas em
  **frontend puro**, sem necessidade do proxy PHP previsto como plano B. Se
  endpoints futuros (ex.: upload de imagens) mostrarem comportamento diferente,
  reavaliar a necessidade do proxy.
- **Erro 401 real**, formato confirmado:
  ```json
  {"ok":false,"status":401,"error":true,"error_code":"E_UNAUTHORIZED","message":"Usuário e/ou senha incorreto(s)"}
  ```
- **Sucesso 200 (conforme spec OpenAPI da própria API)**:
  ```json
  {"ok": true, "error": false, "token": "..."}
  ```
- **Header de autenticação — CONFIRMADO na Fase 3**: apesar da descrição da
  rota de login dizer apenas *"enviado no header `authorization`"* (sem
  mencionar prefixo), o teste empírico contra `GET /v1/regioes` mostrou que
  isso é impreciso. Testamos 3 variações com token inválido, comparando a
  `reason` devolvida pela API:
  | Header enviado | `reason` devolvida |
  |---|---|
  | `authorization: token-fake` (sem prefixo) | `"chave da API não informada"` — a API se comporta como se **nada** tivesse sido enviado |
  | `Authorization: Bearer token-fake` | `"token inválido"` — a API reconheceu o formato e tentou validar |
  | `X-API-Key: token-fake` | `"chave da API inválida"` — caminho alternativo (`api_key` scheme) |

  Conclusão: o formato correto é **`Authorization: Bearer <token>`**, seguindo
  o `securityScheme` `bearerAuth` do spec (que já indicava `scheme: bearer`).
  Implementado em [js/api.js](js/api.js). O viewer de request mostra o
  prefixo `Bearer` visível e mascara só o token.
- **`GET /v1/status` não exige token na prática**, mesmo o spec listando
  `security` para essa rota — responde `200 OK` publicamente com
  `{"ok":true,"message":"API está funcionando",...}`. Por isso o teste de
  conectividade usa esse endpoint, e ele também é o candidato natural para o
  primeiro endpoint Misc da Fase 3 (não precisa de parâmetros nem de login
  prévio para validar o fluxo de request/response genérico).
- **Bug encontrado e corrigido**: o teste de conectividade inicial usava
  `fetch(url, {method: "OPTIONS"})`. Isso dispara um preflight do próprio
  navegador antes da requisição — e como o servidor nunca lista `OPTIONS` em
  `access-control-allow-methods` (só `GET,HEAD,PUT,PATCH,POST,DELETE`), o
  preflight falha e o `fetch` lança erro, mesmo com a API 100% online. Corrigido
  trocando para `GET /v1/status` (requisição simples, sem headers
  customizados, não dispara preflight). Ver [js/api.js](js/api.js).

## Estrutura de arquivos

```text
swagger/
├── index.html
├── css/
│   └── app.css        # tokens de tema (escuro padrão + claro), todo o visual
├── js/
│   ├── app.js          # orquestração / eventos da UI
│   ├── theme.js         # alternância de tema escuro/claro (localStorage)
│   ├── storage.js       # token em sessionStorage
│   ├── api.js           # wrapper de fetch, medição de tempo, base URL
│   ├── auth.js           # fluxo de login
│   ├── ui.js             # renderização (viewers, badges, histórico, máscaras)
│   ├── endpoints.js       # registro (config) dos endpoints Misc — Fase 4
│   ├── explorer.js        # motor genérico: monta card/form a partir da config
│   ├── jwt.js              # decodifica payload do JWT (só leitura, sem checar assinatura)
│   └── codegen.js           # gera exemplos JS/PHP/cURL a partir do request executado
└── README.md
```

Nenhum backend é necessário nesta fase (CORS liberado). Uma pasta `api/` com
proxy PHP só deve ser criada se algum endpoint futuro bloquear CORS.

### Como adicionar um novo endpoint Misc

Não é preciso escrever HTML novo. Basta adicionar um objeto ao array
`TGA_ENDPOINTS` em [js/endpoints.js](js/endpoints.js):

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

Um `category`/`categoryLabel` novo já cria um grupo sozinho na sidebar —
[js/explorer.js](js/explorer.js) agrupa dinamicamente, sem precisar tocar em
HTML. Endpoints com `body` ou `pathParams` não precisam de nenhum tratamento
especial além disso: o motor valida os campos obrigatórios da URL e o JSON do
corpo antes de disparar a requisição, e mostra um aviso "grava dados reais"
automaticamente sempre que `method !== 'GET'`.

O motor em [js/explorer.js](js/explorer.js) cuida do resto: form, header
`Authorization`, request/response viewer, histórico e tratamento de 401. Isso
só vale para endpoints de **leitura simples** (GET, mesmo padrão de
paginação); rotas com corpo (POST/PUT), parâmetro na URL (ex.:
`/cidades/{state}`) ou schema de resposta muito diferente (ex.: `/alteracoes`)
ainda precisam de tratamento específico — ver "Futuro" abaixo.

## Como testar localmente

Como o app faz `fetch` para `https://api.tgasistemas.io`, é preciso servir os
arquivos por HTTP (não abrir o `index.html` direto por `file://`, para evitar
restrições de alguns navegadores). Qualquer servidor estático simples resolve:

```bash
# Opção 1 — Python (já vem instalado na maioria dos sistemas)
cd swagger
python -m http.server 8090

# Opção 2 — PHP
php -S localhost:8090
```

Depois acesse: `http://localhost:8090/`

## Roteiro de testes — Fases 1 e 2

### Teste 1 — Login com credenciais inválidas (já validado contra a API real)

1. Preencha Usuário e Senha com qualquer valor incorreto.
2. Clique em **Autenticar**.

**Esperado:**
- Painel **Request** mostra `POST https://api.tgasistemas.io/v1/usuarios-api/login`
  com o body `{"username": "...", "password": "••••••••"}` (senha mascarada).
- Painel **Response** mostra badge vermelho `401 Unauthorized`.
- Bloco de interpretação de erro exibe **"401 — Não autorizado"** com sugestão
  "Faça uma nova autenticação."
- Mensagem abaixo do formulário: a mensagem de erro devolvida pela API.
- **Sessão da API** permanece "Não autenticado", nenhum token é salvo.
- Uma linha aparece no **Histórico** com status 401.

### Teste 2 — Login com credenciais válidas

*(requer usuário/senha reais fornecidos pela TGA — não incluídos no código)*

1. Preencha Usuário e Senha válidos.
2. Clique em **Autenticar**.

**Esperado:**
- Badge de resposta `200 OK`.
- Mensagem verde: "Autenticação realizada com sucesso."
- Painel **Sessão da API**: status muda para "Autenticado", token mascarado
  aparece (ex.: `abc123••••••••••••••••••••wxyz`).
- Botões Mostrar / Copiar / Limpar sessão ficam habilitados.
- Recarregar a página **mantém** a sessão autenticada (token em `sessionStorage`)
  até a aba ser fechada ou "Limpar sessão" ser clicado.

### Teste 3 — Sessão (token)

1. Após um login válido, clique em **Mostrar** → o token completo aparece.
2. Clique em **Ocultar** → volta a ficar mascarado.
3. Clique em **Copiar** → botão exibe "Copiado!" por instantes; cole em outro
   lugar para confirmar que o token foi copiado.
4. Clique em **Limpar sessão** → status volta a "Não autenticado", token some,
   botões desabilitam.

### Teste 4 — Teste de conectividade

1. Ao carregar a página, observe o badge no canto superior direito.

**Esperado:** `API Online` (a página faz `GET /v1/status`, que é público, ao
carregar). Desligue a internet e recarregue para ver `Falha de comunicação`.

## Roteiro de testes — Fases 3 e 4 (Explorador de API — Misc)

### Teste 5 — Executar `GET /v1/regioes` autenticado

*(requer login válido feito antes — Teste 2)*

1. Após autenticar, na sidebar, clique em **Regiões** (dentro do grupo Misc).
   O card muda dinamicamente para `GET /v1/regioes`. (O endpoint selecionado
   por padrão ao abrir a página é **Status**, que é público — não exige login.)
2. Deixe os campos padrão (Página 1, 50 registros) e clique em **Executar**.

**Esperado:**
- Painel **Request** mostra `GET https://api.tgasistemas.io/v1/regioes?page=1&q=50`
  com o header `authorization: Bearer eyJhbG••••••••••••••••••••XXXX` (token mascarado,
  prefixo `Bearer` visível).
- Painel **Response** mostra badge verde `200 OK` com a lista de regiões em JSON.
- Uma linha aparece no **Histórico** com `GET /regioes` e status 200. Clicar na
  linha reabre os viewers com aquele request/response.

### Teste 6 — Filtro e ordenação

1. Preencha **Campo de ordenação** com um campo válido (ex.: `DESCRICAO`) e
   **Ordenação** como Ascendente.
2. Clique em **Executar**.

**Esperado:** a URL na aba Request reflete `orderby=DESCRICAO&order=ASC` e a
resposta vem ordenada de acordo.

### Teste 7 — Sessão expirada durante uma consulta

*(difícil de forçar sem esperar o token expirar de verdade; para simular,
abra o DevTools → Application → Session Storage → edite o valor de
`tga_api_token` para algo inválido, ex.: `token-quebrado`)*

1. Com o token adulterado, selecione qualquer endpoint autenticado (ex.:
   Regiões) na sidebar e clique em **Executar**.

**Esperado:**
- Badge de resposta `401 Unauthorized`.
- Bloco de interpretação exibe "401 — Não autorizado".
- Painel **Sessão da API**: status muda para "Sessão expirada" (vermelho),
  token é removido do `sessionStorage`.
- Botão **Autenticar novamente** aparece; clicar nele rola a página até o
  formulário de login e foca no campo Usuário.
- Botão **Executar** do card selecionado fica desabilitado até um novo login.

### Teste 8 — Trocar de endpoint na sidebar

1. Com ou sem login, clique em diferentes itens do grupo **Misc** na sidebar
   (Portadores, Vendedores, Promoções, etc.).

**Esperado:** o card principal troca de conteúdo (método, path, descrição,
campos) instantaneamente, sem recarregar a página. O item clicado fica
destacado na sidebar. Endpoints que exigem token mostram o botão **Executar**
desabilitado se não houver sessão ativa; **Status** (público) fica sempre
habilitado.

### Teste 9 — Alternar tema

1. Ao abrir a página pela primeira vez (sem preferência salva), confirme que
   abre no **tema escuro**.
2. Clique no botão de tema (🌙) no topbar.

**Esperado:** interface muda para tema claro instantaneamente, o ícone vira
☀️ e o texto muda para "Claro". Recarregue a página — o tema claro
**permanece** (persistido em `localStorage`, diferente do token que é só de
sessão). Clique de novo para voltar ao escuro.

### Teste 10 — Expiração do token

1. Após um login válido, observe o texto abaixo do token na **Sessão da API**.

**Esperado:** algo como `Expira em 7 dias (25/08/2026 20:31:47)` — calculado
a partir do claim `exp` do JWT devolvido no login (sem depender de nenhuma
chamada extra à API). Esse texto se atualiza sozinho a cada 30s; se o relógio
do navegador ultrapassar o horário de expiração, a sessão vira "Sessão
expirada" automaticamente, sem esperar a próxima requisição levar um 401.

### Teste 11 — Tabela / Raw / busca na resposta

*(requer uma resposta 200 com lista de registros — ex.: Teste 5)*

1. Após executar um endpoint com sucesso, clique na aba **Tabela** no
   Response Viewer.

**Esperado:** a resposta vira uma tabela HTML com uma coluna por campo. Um
campo de busca aparece acima; digitar filtra as linhas em tempo real
(case-insensitive, procura em qualquer coluna).

2. Clique em **Raw** → mostra o JSON em uma linha só (sem indentação).
3. Clique em **JSON** → volta ao formato indentado original.
4. Selecione o endpoint **Status** (não devolve lista) e vá para **Tabela**.

**Esperado no passo 4:** mensagem "Não há uma lista de registros nesta
resposta para exibir em tabela." em vez de uma tabela vazia ou erro.

### Teste 12 — Gerador de código

*(já validado com dados simulados via Node — ver "Descobertas técnicas";
este teste é só para conferir a integração visual)*

1. Execute qualquer requisição (login ou um endpoint Misc).
2. Role até o painel **Gerador de código**, clique nas abas **JavaScript**,
   **PHP** e **cURL**.

**Esperado:** cada aba mostra um exemplo de código funcional na respectiva
linguagem, com o header `authorization` como `Bearer SEU_TOKEN_AQUI`
(placeholder). Se a requisição foi o login, o campo `password` aparece como
`"••••••••"` em todas as abas.

3. Marque a caixa **Incluir token real neste exemplo**.

**Esperado:** o token completo aparece no lugar do placeholder (em todas as
abas) — mas a senha continua mascarada mesmo assim, mesmo que a última
requisição tenha sido o login.

4. Clique em **Copiar código** → botão vira "Copiado!" por instantes; cole em
   outro lugar para conferir que o texto exato foi copiado.

### Teste 13 — Exportar CSV

*(escaping já validado com dados simulados via Node — ver "Descobertas
técnicas"; este teste é só para conferir a integração/download real)*

1. Execute um endpoint que devolva uma lista (ex.: Regiões) com sucesso.
2. Clique em **Exportar CSV** no Response Viewer (funciona em qualquer aba
   ativa, não só na Tabela).

**Esperado:** o navegador baixa um arquivo `tga-regioes-AAAA-MM-DD-HH-MM-SS.csv`.
Abra no Excel/Google Sheets — colunas e acentuação devem aparecer corretas
(o arquivo inclui BOM UTF-8 para isso).

3. Selecione o endpoint **Status** (resposta sem lista) → botão **Exportar
   CSV** deve ficar desabilitado.

### Teste 14 — Parâmetros de URL, corpo JSON e aviso de gravação

**⚠️ Cuidado**: os passos abaixo usam endpoints POST/PUT/DELETE, que **gravam
dados reais** no TGA ERP se a requisição for enviada com sucesso. Faça esse
teste só em ambiente de homologação/teste, e prefira parar no passo de
validação (sem clicar em Executar de fato) a menos que você queira mesmo
criar/alterar um registro.

1. Selecione, por exemplo, **Clientes → Boletos em aberto** (`GET
   /clientes/{cgccfo}/boletos`) na sidebar.

**Esperado:** aparece um campo obrigatório "CPF/CNPJ (somente números)" com
`*` vermelho, separado dos parâmetros de consulta (Página, Filtro, etc.), sob
o rótulo "Parâmetros da URL".

2. Clique em **Executar** sem preencher esse campo.

**Esperado:** nada é enviado — aparece a mensagem "Preencha 'CPF/CNPJ...'
antes de executar." em vermelho no lugar do texto de última execução.

3. Selecione **Clientes → Cadastrar/atualizar** (`POST /clientes`).

**Esperado:** aparece o badge amarelo "⚠ Grava dados reais" no card e uma
caixa de aviso explicando que o endpoint altera dados de verdade. Abaixo dos
campos, um textarea com JSON pré-preenchido (schema de exemplo do cliente).

4. Apague uma chave do JSON (deixe sintaticamente inválido, ex.: remova uma
   `"`) e clique em **Executar**.

**Esperado:** nada é enviado — mensagem de erro específica ("JSON inválido no
corpo da requisição: ...") aparece em vermelho.

### Teste 15 — Aba "Ficha"

*(testado com dados simulados via Node — ver "Descobertas técnicas"; este
teste confere a integração visual real)*

1. Execute um endpoint que devolva uma lista (ex.: Vendedores) com sucesso e
   clique na aba **Ficha**.

**Esperado:** um cartão por registro, em grade — nome em destaque no topo,
código como selo ao lado, e os demais campos com rótulo em português (ex.:
"Cargo", "Comissão 1") em vez do nome cru da coluna (`CARGO`, `COMISSAO1`).
Campos vazios não aparecem.

2. Digite algo no campo de busca (ex.: um nome ou cargo).

**Esperado:** só os cartões com aquele texto continuam visíveis.

3. Execute um endpoint que devolva um único registro (ex.: `POST /movimentos`,
   que devolve `{"data": {"IDMOV": ...}}`) e vá para a aba Ficha.

**Esperado:** um único cartão maior, centralizado, mostrando o `IDMOV` como
selo — não uma grade de vários cartões pequenos.

## Segurança — o que foi aplicado

- Nenhuma credencial fixa no código.
- Senha nunca é impressa no console nem persistida (nem sessionStorage, nem
  localStorage) — o campo é limpo após cada tentativa de login.
- Token vive apenas em `sessionStorage` (não sobrevive ao fechar o navegador).
- Token nunca aparece completo por padrão nos viewers — sempre mascarado, com
  ação explícita ("Mostrar") para revelar.
- Token nunca é colocado em URL.

## Necessário agora

Rodar os Testes 5, 6, 8, 10, 11, 12, 13, 14 e 15 com um login real (a API da TGA estava
respondendo 504 no último teste — ver nota abaixo) para confirmar que os
outros 7 endpoints de listagem realmente respondem no mesmo formato de
`/v1/regioes`, e que a tabela renderiza os campos certos. Como todos passam
pelo mesmo motor genérico, validar 2–3 deles no navegador já dá confiança
razoável sobre os demais.

**Nota sobre o 504 observado**: numa consulta real anterior, `GET /v1/regioes`
devolveu `504 Gateway Timeout` (`"O servidor está demorando muito para
responder"`) — isso veio do próprio backend da TGA (ambiente parece ser de
homologação, a julgar pelo payload do token), não é um bug da ferramenta. O
tratamento de erro funcionou corretamente (badge vermelho, interpretação
"Erro do servidor", sugestão de tentar depois).

## Melhoria recomendada

- Favoritar endpoints usados com frequência (persistir em `localStorage`,
  não sensível) para não precisar rolar a sidebar toda vez.

## Futuro

Endpoints com corpo (POST/PUT — ex.: categoria Clientes, Movimentos),
parâmetro na URL (`/cidades/{state}`), coleções/favoritos de endpoints — ver
escopo completo no prompt original. O motor genérico atual (Fase 4) cobre só
GET de leitura simples com paginação; endpoints fora desse padrão vão exigir
estender a config de `js/endpoints.js` (ex.: suporte a `pathParams` e `body`)
antes de serem adicionados.
