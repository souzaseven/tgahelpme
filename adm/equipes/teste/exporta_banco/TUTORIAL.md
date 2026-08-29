# Tutorial — Como usar o Inspetor de Banco Firebird

Este guia é focado no **uso do dia a dia** da ferramenta, passo a passo, tela
por tela. Para instalação, dependências e arquitetura, veja o
[README.md](README.md).

---

## 1. Iniciando a ferramenta

Na pasta do projeto, com o ambiente virtual ativado:

```bash
python run.py
```

O navegador abre sozinho em `http://127.0.0.1:8000`. Se não abrir, entre
manualmente nesse endereço. Deixe essa janela do terminal aberta — é ela que
mantém o servidor rodando; fechá-la encerra a ferramenta.

> 💡 Sempre que eu (ou você) alterar o código, é preciso parar (`Ctrl+C` no
> terminal) e rodar `python run.py` de novo para os arquivos `.py` mudarem
> de verdade. Mudanças em HTML/CSS/JS não precisam disso — só um
> `Ctrl+Shift+R` no navegador já resolve.

---

## 2. Conectando a um banco

A primeira tela é a de conexão:

| Campo | O que preencher |
|---|---|
| **Banco de dados** | Caminho completo do `.FDB` no servidor Firebird. Pode digitar direto ou clicar em **Abrir...** para navegar pelas pastas visualmente. |
| **Servidor** | `localhost` se o Firebird está na mesma máquina; caso contrário, o IP/nome da máquina do servidor. |
| **Porta** | `3050` é o padrão do Firebird. |
| **Usuário** | Ex: `SYSDBA`. |
| **Senha** | A senha desse usuário (ex: `masterkey` no usuário padrão). |
| **Charset** | **`WIN1252` é o padrão** (é o que este banco usa); troque para `UTF8` só se o seu banco for diferente e os textos aparecerem com acentuação estranha. |
| **Role** | Deixe em branco, a menos que seu Firebird use roles de segurança específicas. |

### 2.1 Usando o botão "Abrir..."

Clique em **Abrir...** ao lado do campo do banco para abrir um explorador de
arquivos simplificado:

1. A primeira tela mostra as **unidades de disco** (`C:\`, `D:\`, ...).
2. Clique em uma pasta (📁) para entrar nela, ou em **.. (voltar)** para
   subir um nível.
3. Arquivos `.FDB`/`.GDB` aparecem em destaque (🗄️); clique neles para
   selecionar — o caminho completo é preenchido automaticamente no
   formulário e o explorador fecha sozinho.

> Esse explorador lista pastas **da máquina onde o servidor da ferramenta
> está rodando** — que normalmente é a sua própria máquina.

### 2.2 Testando antes de conectar

Clique em **Testar conexão** para validar usuário, senha e caminho sem sair
da tela de login. Você recebe:

- ✅ **Conexão bem-sucedida! Firebird versão X.X.X** — pode conectar.
- ❌ Uma mensagem específica do erro (senha errada, banco não encontrado,
  servidor fora do ar, etc.).

### 2.3 Conectando de verdade

Clique em **Conectar**. Se tudo der certo, a tela de login desaparece e o
painel principal (dashboard) aparece.

> 💡 Depois da primeira conexão bem-sucedida, a ferramenta lembra o banco,
> servidor, porta, usuário e charset usados (a senha nunca é salva) e
> preenche esses campos automaticamente na próxima vez que você abrir a
> tela de login no mesmo navegador.

---

## 3. Visão Geral (dashboard)

A aba **🏠 Visão Geral** é a primeira da barra lateral e a tela inicial
depois de conectar. Ela reúne:

- A **versão do Firebird** e o banco conectado, no topo.
- Cartões com a contagem de **Tabelas, Views, Procedures, Triggers e
  Generators**.
- Se o banco seguir o padrão de tabelas `TPRODUTO` (produtos/serviços) e
  `FCFO` (clientes/fornecedores) — comum em ERPs comerciais brasileiros —
  duas seções extras aparecem automaticamente, cada uma dividida lado a
  lado por categoria:
  - **Produtos & Serviços**: um lado para "Produtos" e outro para
    "Serviços", cada um com seu próprio Total, Ativos, Inativos, Com saldo
    e Sem saldo.
  - **Clientes & Fornecedores**: três lados — "Clientes", "Fornecedores" e
    "Ambos" (cadastros que são as duas coisas) — cada um com seu próprio
    Total, Ativos e Inativos.
  - **Clientes por Situação Financeira** (se o banco também tiver `FLAN`):
    cruza cada cliente ativo com os lançamentos a receber em aberto dele e
    mostra em qual situação está — **Com Lançamentos Vencidos**,
    **Com Lançamentos Vencendo Hoje**, **Com Lançamentos a Vencer**,
    **Sem Lançamentos em Aberto** ou **Clientes Inativos**. Cada cliente
    entra em uma única categoria (a mais urgente ganha das outras); só
    considera lançamentos a receber (contas de cliente).
- Se o banco tiver as tabelas `TMOV` e `FLAN` (comum em sistemas de
  estoque/financeiro), mais seções aparecem, organizadas por sistema:
  - **📦 Sistema Estoque**: status dos **Movimentos** (Normal, Faturado,
    A Faturar, Parc. Quitado, Quitado, Cancelado) e, logo abaixo, uma
    tabela **"Por Tipo de Documento"** — os 15 tipos mais usados, com o
    nome real cadastrado no TGA (`TTIPOMOV`): Orçamento, Pedido de Venda,
    PDV Fiscal/Não Fiscal, Compra de Mercadorias, Ordem de Serviço, Ajuste
    de Saldo Físico, etc., com quantidade e % do total. Clique no nome do
    tipo pra ver só os movimentos daquele tipo. Depois vêm as **Ordens de
    Serviço** (Em Aberto, Em Serviço, Encerrado) e os **Documentos Fiscais
    Eletrônicos**, cada tipo com seu próprio detalhamento de status (igual
    ao padrão usado em Produtos & Serviços):
    - **NF-e** e **NFC-e** (separadas quando o modelo do documento está
      identificado): Em Digitação, Autorizada, Em Processamento, Rejeitada,
      Cancelada, Denegada, Inutilizada.
    - **NFS-e**: Em Digitação, Em Processamento, Autorizada, Rejeitada, Cancelada.
    - **CT-e**: Em Digitação, Em Processamento, Autorizado, Rejeitado, Cancelado.
    - **MDF-e**: Em Digitação, Em Processamento, Autorizado, Encerrado,
      Rejeitado, Cancelado.
    - **MD-e / DF-e de terceiros** (documentos onde a sua empresa é a
      destinatária): status de **Manifestação do Destinatário** — Não
      Manifestadas, Confirmação da Operação, Ciência da Emissão, Operação
      Desconhecida, Operação Não Realizada — mais um bloco separado de
      **Download do XML** (Realizado/Pendente). "Já Importada" (documento
      convertido em movimento/entrada) ficou de fora: não encontrei no
      banco um campo confiável para isso.

    As cores desses status seguem a mesma convenção do TGA: 🔵 azul = Em
    Digitação / Não Manifestada, 🟡 amarelo/âmbar = Em Processamento /
    Ciência da Emissão, 🟢 verde = Autorizada(o) / Encerrado / Confirmação
    da Operação, 🔴 vermelho = Rejeitada(o) / Operação Não Realizada, ⚫
    cinza = Cancelada(o) / Inutilizada / Operação Desconhecida, 🟠 laranja
    = Denegada.
  - **💰 Sistema Financeiro**: um bloco de **Valores** — total **a pagar**
    e **a receber** em aberto, e total **recebido**/**pago** dentro de um
    período (veja os botões de período abaixo) — e o status dos
    **Lançamentos** (Aberto, Faturado, Baixado, Cancelado).

  Essa tela vai crescendo aos poucos — outros status do sistema (itens de
  movimento, pedidos, etc.) entram em rodadas futuras.

  Em bancos que não têm essas tabelas, as seções correspondentes
  simplesmente não aparecem — o resto do dashboard funciona normalmente.

  **Barra de atalhos**: no topo da Visão Geral (fica fixa ao rolar a
  página) aparece uma fileira de botões — um para cada seção que existir
  no seu banco (Estrutura, Produtos & Serviços, Clientes & Fornecedores,
  Sistema Estoque, Sistema Financeiro). Clique em qualquer um para pular
  direto para aquela parte da tela, sem precisar rolar manualmente.

  **Botões de período** (Hoje, Ontem, 7 dias, Este mês, Este ano): existem
  em **dois lugares** — uma barra **global**, logo abaixo dos atalhos (vale
  para todos os cards com filtro de data), e uma **local**, dentro da seção
  "Valores" do Sistema Financeiro (útil se você quiser olhar só aquela
  seção com um período diferente do resto). Também dá pra digitar um
  intervalo de datas manualmente nos campos "De"/"Até" e clicar em
  **🔄 Atualizar**.

  **Todos os cards são clicáveis.** Os cards de Tabelas/Procedures/Triggers/
  Generators levam direto para a aba correspondente. Os demais levam direto
  para a tabela de origem já com os dados filtrados de acordo com o card —
  por exemplo, clicar em "Cancelado" nos Movimentos mostra só os registros
  com `STATUS = 'C'` em `TMOV`, com uma barra indicando o filtro aplicado e
  um botão para limpá-lo.

  As cores seguem um padrão fixo em todo o dashboard: **verde** = concluído
  com sucesso (Ativos, Com saldo, Quitado, Baixado), **vermelho** =
  cancelado/negativo (Inativos, Sem saldo, Cancelado), e **azul, roxo,
  âmbar e ciano** identificam categorias e estados intermediários (em
  aberto, faturado, parcial, etc.) sem indicar "bom ou ruim".

A aba **📋 Tabelas & Views** agora é só para navegar pela estrutura — a
árvore de tabelas fica na lateral e o painel principal só mostra o
detalhe da tabela selecionada.

A barra lateral esquerda tem as abas: **Visão Geral**, **Tabelas & Views**,
**Procedures**, **Triggers**, **Generators**, **Análise Financeira**,
**Análise Estoque**, **Mudanças** e **Console SQL**.

---

## 4. Navegando pelas tabelas

Na aba **Tabelas & Views**, cada item da lista tem uma setinha (▸) à
esquerda:

- **Clique na setinha** para expandir a tabela ali mesmo, na barra lateral,
  mostrando rapidamente os campos e seus tipos (sem precisar abrir o
  painel principal). Um `*` ao lado do nome do campo indica `NOT NULL`.
- **Clique no nome da tabela** para abrir o **detalhe completo** no painel
  principal, com:
  - **Campos**: nome, tipo, tamanho, NULL/NOT NULL, valor padrão e domínio.
  - **Chave primária** (destacada com 🔑 na lista de campos).
  - **Chaves estrangeiras**: para qual tabela apontam, com regras de
    `ON UPDATE`/`ON DELETE`. Clique no nome da tabela referenciada para
    pular direto para ela.
  - **Referenciada por**: quais outras tabelas apontam para esta (o
    caminho inverso das chaves estrangeiras).
  - **Índices**: campos, se é único, ordem (ASC/DESC) e status.
  - **Triggers** daquela tabela especificamente.
  - **Quantidade de registros** (contagem em tempo real).
  - **Dados**: clique em **👁️ Ver dados (SELECT \* FROM tabela)** para ver
    os primeiros 100 registros de verdade, sem precisar digitar SQL — é
    literalmente essa consulta que roda por baixo dos panos. A consulta
    aparece num campo editável logo abaixo: altere o texto (troque o
    `WHERE`, escolha colunas específicas, adicione `ORDER BY`, etc.) e
    clique em **▶ Executar** (ou `Ctrl+Enter`) para rodar a nova versão,
    sem sair da tela da tabela.
  - **Filtrar e expandir os resultados**: toda tabela de resultados (na
    seção "Dados" e também no Console SQL) tem uma caixa **🔍 Filtrar nos
    resultados** — digite qualquer texto para esconder na hora as linhas
    que não contêm esse termo em nenhuma coluna, sem precisar de uma nova
    consulta. O botão **⛶ Expandir** ao lado abre a mesma tabela numa
    janela bem maior (quase a tela toda), útil quando vêm muitas colunas
    ou linhas; feche com **✕ Fechar** ou `Esc`.

Views aparecem marcadas com a etiqueta **VIEW** e mostram o **SQL de
definição** no lugar da contagem de registros.

---

## 5. Procedures

Na aba **Procedures**, clique em qualquer uma da lista para ver:

- Parâmetros de **entrada** e de **saída**, com nome e tipo de cada um.
- O **código-fonte** completo da procedure.

---

## 6. Triggers

Na aba **Triggers**, você vê todas as triggers do banco (a etiqueta ao lado
do nome mostra a tabela dona da trigger, ou "DB" se for uma trigger de
banco de dados). Clique em uma para ver:

- A **tabela** associada (clicável, pula direto para o detalhe dela).
- O **evento** (ex: `BEFORE INSERT`, `AFTER UPDATE OR DELETE`).
- A **ordem de disparo** e o **status** (ativa/inativa).
- O **código-fonte**.

> Triggers chamadas `CHECK_1`, `CHECK_2`, etc. são geradas automaticamente
> pelo Firebird para constraints `CHECK` — é normal elas aparecerem sem
> código-fonte legível ("sem fonte disponível").

---

## 7. Generators / Sequences

A aba **Generators** mostra uma lista simples: nome do generator e seu
**valor atual** (útil para saber qual será o próximo ID gerado em tabelas
que os usam como chave primária).

---

## 8. Análise Financeira

A aba **💵 Análise Financeira** é uma tela dedicada, inspirada em
dashboards financeiros de ERP, disponível quando o banco tem a tabela
`FLAN`. Mostra:

- **Hoje & Em Atraso no Mês**: valor total **a receber hoje**, **a pagar
  hoje**, **a receber em atraso** (vencido dentro do mês atual) e **a pagar
  em atraso** (idem).
- **Saldo por Caixa/Conta** (se o banco tiver a tabela `FCAIXA`): lista
  cada caixa/conta com saldo inicial, saldo atual e status, mais o total
  geral.
- **Posição dos Lançamentos ("Aging List")**: uma tabela clássica de
  contas a pagar/receber, com o valor em aberto agrupado por faixa de
  dias — Hoje, 01 a 07, 08 a 15, 16 a 30, 31 a 60, 61 a 90 e Mais de 90
  dias — separando o que já **venceu** do que ainda **vai vencer**, tanto
  para Receber quanto para Pagar.
- **Top 10 Clientes com Maior Valor em Atraso** e **Top 10 Fornecedores
  com Maior Valor em Atraso** (se o banco também tiver `FCFO`): dois
  rankings espelhados — contas a receber (clientes) e contas a pagar
  (fornecedores) — cada um mostrando quem tem mais dinheiro vencido em
  aberto: nome, valor total em atraso, quantos lançamentos vencidos e há
  quantos dias está o lançamento mais antigo. O nome é um link que abre o
  cadastro na grade de dados.

Os cards de valores também são clicáveis e levam para os lançamentos
correspondentes em `FLAN`.

**📅 Posição em**: no topo da tela, escolha **Hoje**, **Ontem** ou digite
qualquer data no campo e clique em **🔄 Atualizar** — toda a tela (Hoje &
Em Atraso, Aging List) é recalculada como se aquele dia fosse "hoje".
Útil para ver como estava a situação financeira numa data específica no
passado.

---

## 9. Análise de Estoque

A aba **📦 Análise Estoque** é uma tela dedicada, disponível quando o banco
tem a tabela `TPRODUTO`. No topo há o quadro **"📊 Mostrar"**, com dois
seletores que controlam a tela inteira:

- **Top**: quantas linhas aparecem em cada ranking/tabela (10, 20, 50 ou
  100). Vale para Top Produtos, Produtos Parados, Grupo e Fabricante.
- **Dias sem giro**: a partir de quantos dias sem nenhuma movimentação um
  produto com saldo em estoque é considerado "parado" (30, 60, 90 ou 180).

Trocar qualquer um dos dois recarrega a tela automaticamente com o novo
valor. A tela mostra:

- **Resumo**: saldo físico total do estoque, quantidade de produtos
  **abaixo do mínimo** e **acima do máximo** cadastrados, o **custo**
  (a preço de custo) e **valor** (a preço de venda) de tudo que está
  parado no estoque, e a **Margem Potencial** (valor − custo), ou seja,
  quanto de lucro bruto existe "parado" no estoque atual se tudo for
  vendido pelo preço cadastrado.
- **Saldo Físico dos Produtos**: três cards — **Saldo Negativo**
  (vermelho), **Saldo Zerado** (amarelo) e **Saldo Positivo** (verde) —
  mostrando quantos produtos estão em cada situação. Quando existe saldo
  negativo, aparece um aviso explicando que isso costuma indicar venda
  registrada sem entrada de estoque correspondente.
- **Produtos Parados (sem giro)**: card com o total de produtos com saldo
  que não tiveram nenhuma movimentação (venda, entrada, ajuste — qualquer
  lançamento em `TMOVITENS`) dentro do prazo escolhido em "Dias sem giro",
  e uma tabela com os produtos há mais tempo parados (código, nome, saldo,
  custo, data da última movimentação — ou "nunca" — e quantos dias fazem).
  Útil para achar mercadoria encalhada.
- **Top Produtos por Valor em Estoque**: tabela com os produtos que mais
  concentram dinheiro parado (saldo × custo médio), com código, nome,
  saldo, custo unitário e custo total. O nome do produto é um link que
  abre direto o registro dele na grade de dados.
- **Custo do Estoque por Grupo** e **por Fabricante**: um ranking em barras
  mostrando onde está concentrado o dinheiro parado em estoque.

> Os cards "Abaixo do Mínimo", "Acima do Máximo", os três cards de "Saldo
> Físico", o card "Sem giro" e os produtos das tabelas são todos clicáveis
> e levam direto para a lista/registro correspondente na grade de dados.
> As tabelas de Top Produtos, Produtos Parados, Grupo e Fabricante têm um
> botão **⬇️ CSV** para exportar a lista exatamente como está na tela (veja
> a seção 16).

**O que ficou de fora:** "Compras x Vendas", "Ranking de Fornecedores" e
"Pedidos de Compra pendentes" — não encontrei no banco uma tabela que
classifique os movimentos como compra/venda com segurança, nem uma tabela
de pedidos de compra. Prefiro deixar de fora a mostrar um número
adivinhado; se você souber onde essas informações ficam neste banco, me
diga o nome da tabela/campo e eu incluo.

---

## 10. Busca rápida

Digite na caixa de busca no topo da barra lateral (mínimo 2 caracteres).

> 💡 **Atalho:** aperte **`/`** (fora de qualquer campo de texto) ou
> **`Ctrl+K`** (de qualquer lugar da tela) para pular direto para a caixa
> de busca, sem precisar clicar nela.

A busca é simultânea em:

- Nomes de **tabelas e views**
- Nomes de **campos** (mostrando a tabela dona)
- Nomes de **procedures**
- Nomes de **triggers** (mostrando a tabela dona)
- Nomes de **generators**

Clique em qualquer resultado para ser levado direto ao detalhe correspondente.

---

## 11. Console SQL (somente leitura)

Na aba **Console SQL**, digite uma consulta `SELECT` (ou `WITH ... SELECT`)
e clique em **Executar** (ou `Ctrl+Enter`).

Exemplos úteis:

```sql
-- Ver os primeiros registros de uma tabela
SELECT FIRST 100 * FROM ACULTURA

-- Contar registros que atendem uma condição
SELECT COUNT(*) FROM AFAZENDA WHERE CODEMPRESA = 1

-- Consultar via join simples
SELECT c.NOME, c.CODCULTURA
FROM ACULTIVAR c
WHERE c.CODEMPRESA = 1
```

Por segurança, a ferramenta **bloqueia** qualquer comando que não seja
leitura (`INSERT`, `UPDATE`, `DELETE`, `DROP`, `ALTER`, `EXECUTE`, etc.) e
limita o resultado a 500 linhas por consulta.

### 💾 Salvando consultas

Depois de escrever uma consulta, clique em **💾 Salvar consulta** e dê um
nome pra ela — fica guardada como um "chip" logo abaixo do botão, na
mesma aba. Clique no chip pra carregar aquela consulta de volta no editor
(sem precisar digitar tudo de novo); clique no **×** do chip pra
removê-la. As consultas salvas ficam no navegador (não são enviadas para
o banco nem para outra máquina) e continuam lá mesmo depois de fechar e
abrir a ferramenta de novo.

---

## 12. Desconectando / trocando de banco

Clique em **Desconectar** no topo da tela para encerrar a conexão atual e
voltar à tela de login — útil para inspecionar outro banco sem reiniciar a
ferramenta.

---

## 13. Mudanças na Estrutura

A aba **🆕 Mudanças** compara a estrutura atual do banco com uma "fotografia"
salva anteriormente — pensada para o dia em que o sistema/ERP for
atualizado e você quiser saber exatamente **o que mudou** (tabelas novas,
campos novos, campos que mudaram de tipo/tamanho, procedures/triggers/
generators novos ou removidos).

Como usar:

1. Na primeira vez, clique em **💾 Salvar situação atual como referência**
   — isso grava a estrutura de hoje localmente (na pasta `snapshots/` do
   projeto, um arquivo por banco; nada é enviado para fora da sua máquina).
2. Depois de uma atualização do sistema (ou a qualquer momento), volte
   nessa aba e clique em **🔄 Verificar agora** — a tela mostra:
   - 🆕 Tabelas e campos **novos**.
   - 🗑️ Tabelas e campos **removidos**.
   - ✏️ Campos que **mudaram de tipo ou tamanho** (com o "antes" e "depois").
   - O mesmo para Procedures, Triggers e Generators.
   - Se nada mudou, aparece **"✅ Nenhuma mudança na estrutura desde a
     última referência"**.
3. Quando quiser considerar a estrutura atual como o novo "normal" (por
   exemplo, depois de já ter revisado as mudanças), clique em
   **💾 Atualizar referência** para começar a comparar a partir de agora.

Nomes de tabela nos resultados são clicáveis e levam direto ao detalhe
daquela tabela.

---

## 14. Problemas comuns

| Sintoma | Causa provável | O que fazer |
|---|---|---|
| Tela de login não sai do lugar depois de clicar em "Conectar" | Navegador com página em cache | `Ctrl+Shift+R` para recarregar |
| "Erro 500" ao abrir uma tabela | Servidor foi reiniciado e a aba do navegador ficou com a versão antiga carregada | `Ctrl+Shift+R` e conecte novamente |
| "Não foi possível conectar ao servidor Firebird" | Serviço do Firebird parado, ou servidor/porta errados | Confirme que o Firebird Server está rodando e que a porta é `3050` |
| "Usuário ou senha inválidos" | Credenciais erradas | Confira usuário e senha; teste com **Testar conexão** primeiro |
| Falha ao instalar dependências (erro de compilação Rust/`pydantic-core`) | Versão do Python muito nova sem wheel pré-compilada | Use uma versão do Python mais estável (3.11/3.12) ou instale sem fixar versões no `requirements.txt` |
| Ao iniciar, aparece "porta 8000 já está em uso — usando a porta X" | Já existe um `python run.py` rodando em outra janela | Normal — a ferramenta já resolve sozinha, subindo na próxima porta livre. Feche a instância antiga se quiser voltar a usar a 8000. |
| "Ver amostra" ou o console SQL retornam erro de decodificação UTF-8 (`'utf-8' codec can't decode byte...`) | O banco tem textos gravados em `WIN1252` (comum em bancos mais antigos), mas você conectou com charset `UTF8` | Desconecte e reconecte escolhendo **WIN1252** no campo Charset da tela de login |
| Algo deu errado e não aparece detalhe nenhum na tela | Erro interno não previsto | Veja o arquivo `logs/app.log` na pasta do projeto — todo erro é registrado ali com detalhes, mesmo quando a tela só mostra uma mensagem genérica |

---

## 15. Dica: gerando o `.exe`

Se quiser distribuir a ferramenta para alguém sem Python instalado, veja a
seção "Gerando um executável" no [README.md](README.md#7-gerando-um-executável-opcional).

---

## 16. Recursos que valem em qualquer tela

Estes recursos não são de uma aba específica — funcionam em várias telas
(Visão Geral, Análise Financeira, Análise Estoque):

### ⭐ Favoritos

Todo card clicável (os que levam para uma lista filtrada) tem uma
estrelinha ☆ no canto superior direito — passe o mouse sobre o card para
vê-la. Clique nela para favoritar (fica ★); os favoritos aparecem fixados
numa seção **⭐ Favoritos** no topo da aba **Visão Geral**, sempre visível
assim que você entra na tela, sem precisar caçar o card de novo em outra
aba. Clicar no favorito leva direto para a mesma lista filtrada do card
original. Clique na estrela de novo (no card original ou no próprio
favorito) para desfavoritar. Os favoritos ficam salvos no navegador
(`localStorage`) — são por computador/navegador, não vão para o banco de
dados nem são compartilhados entre máquinas.

### 🔄 Atualizar

As telas **Visão Geral**, **Análise Financeira**, **Análise Estoque** e
**Mudanças** têm um botão de atualizar no cabeçalho (🔄 Atualizar / 🔄
Verificar agora) que recarrega só aquela seção com os dados mais recentes
do banco, sem precisar trocar de aba e voltar.

### 🕒 "Atualizado às"

Ao lado do botão de atualizar dessas mesmas quatro telas, aparece um texto
discreto **"Atualizado às HH:MM:SS"** — mostra o horário em que aquela
tela buscou os dados pela última vez. Como os números não mudam sozinhos
na tela (só quando você clica em Atualizar, troca de aba ou recarrega a
página), esse indicador ajuda a saber se vale a pena clicar em Atualizar
antes de confiar num número.

### 🔢 Somente números

Botão no topo da janela (ao lado de "Desconectar"). Quando ativado, some
com as tabelas e os rankings em barra de todas as telas, deixando visíveis
só os cards com os números principais — útil para uma visão rápida, tipo
"bati o olho e já vi o essencial", ou para captura de tela mais limpa. Fica
salvo no navegador; continua ativo mesmo depois de fechar e abrir a
ferramenta de novo, até você desativar.

### ⬇️ Exportar CSV

As tabelas de ranking (Top Produtos, Produtos Parados, Custo por Grupo,
Custo por Fabricante, Saldo por Caixa, Aging List) têm um botão **⬇️ CSV**
que baixa exatamente o que está na tela naquele momento (respeitando o
"Top" escolhido) como um arquivo `.csv` separado por `;` (compatível com
Excel em português), pronto para abrir numa planilha ou levar para uma
reunião.
