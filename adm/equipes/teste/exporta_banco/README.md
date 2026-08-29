# Inspetor de Banco Firebird

Ferramenta local para conectar em um banco Firebird (`.FDB`) e inspecionar
toda a sua estrutura: tabelas, campos, tipos, chaves primárias e
estrangeiras, índices, triggers, procedures, views e generators/sequences —
além de um console de consultas somente leitura e busca rápida na estrutura.

Arquitetura: **Python (FastAPI) + Firebird + dashboard HTML/CSS/JS**, servido
localmente em `http://127.0.0.1:8000`.

> 📘 Este README cobre instalação e arquitetura. Para o passo a passo de uso
> (telas, botões, exemplos de consulta), veja o [TUTORIAL.md](TUTORIAL.md).

## 1. Pré-requisitos

- Python 3.10+
- Cliente Firebird instalado na máquina (o arquivo `fbclient.dll` precisa
  estar acessível — normalmente já vem com a instalação do Firebird Server
  ou pode ser baixado avulso em https://firebirdsql.org/en/firebird-clients/).
- Um servidor Firebird rodando (local ou em rede) com acesso ao banco, por
  exemplo `C:\TGA\Dados\TGA.FDB` em `localhost:3050`.

## 2. Instalação

```bash
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
```

## 3. Executando

```bash
python run.py
```

O navegador abrirá automaticamente em `http://127.0.0.1:8000`. Se não abrir,
acesse manualmente esse endereço.

## 4. Tela de conexão

Preencha:

| Campo    | Exemplo               |
|----------|------------------------|
| Banco    | `C:\TGA\Dados\TGA.FDB` |
| Servidor | `localhost`            |
| Porta    | `3050`                 |
| Usuário  | `SYSDBA`               |
| Senha    | `masterkey`            |
| Charset  | `WIN1252` (padrão) / `UTF8` / `NONE` |
| Role     | (opcional)             |

O caminho do banco é sempre resolvido **no servidor Firebird**, não na
máquina que roda esta ferramenta (a não ser que sejam a mesma máquina).

## 5. O que a ferramenta mostra

- **Visão Geral**: dashboard com contagem de tabelas/views/procedures/
  triggers/generators e, em bancos com o padrão de ERP comercial
  (`TPRODUTO`, `FCFO`, `TMOV`, `FLAN`), cards de Produtos & Serviços,
  Clientes & Fornecedores (com quebra por situação financeira — vencidos/
  vencendo hoje/a vencer/sem lançamento/inativos), Sistema Estoque
  (movimentos com quebra por tipo de documento — Orçamento, Pedido de
  Venda, PDV, Compra, Ordem de Serviço, etc. —, ordens de serviço, e
  documentos fiscais eletrônicos incluindo manifestação do destinatário de
  MD-e/DF-e de terceiros) e Sistema Financeiro — todos clicáveis, com
  atalhos de navegação e filtro de período (Hoje/Ontem/7 dias/Este mês/
  Este ano ou intervalo customizado).
- **💵 Análise Financeira**: saldo por caixa/conta, total a pagar/receber
  hoje e em atraso, uma "aging list" de contas a pagar/receber por faixa
  de dias, e rankings dos Top 10 clientes e Top 10 fornecedores com maior
  valor em atraso.
- **📦 Análise Estoque**: saldo físico total, produtos abaixo do mínimo/
  acima do máximo, custo/valor parado em estoque e margem potencial;
  quantidade de produtos com saldo negativo/zerado/positivo; produtos
  parados (sem giro há N dias); Top produtos por valor em estoque; ranking
  de custo por Grupo e por Fabricante (quantidade de linhas ajustável) —
  todos os cards e itens clicáveis levam direto à lista de produtos
  correspondente.
- **Recursos transversais** (Visão Geral, Análise Financeira, Análise
  Estoque): favoritar cards (⭐, fixam no topo da Visão Geral), atualizar
  uma seção sem trocar de aba (🔄) com indicador de "Atualizado às HH:MM:SS"
  (🕒), modo "somente números" que esconde tabelas/rankings e mantém só os
  cards (🔢), e exportar rankings/tabelas para CSV (⬇️).
- **Tabelas & Views**: lista completa; ao clicar em uma tabela, mostra
  campos (nome, tipo, tamanho, NULL/NOT NULL, default, domínio), chave
  primária, chaves estrangeiras (com destino), tabelas que referenciam a
  atual, índices, triggers e uma amostra dos dados (com editor de SQL).
  Views mostram também o SQL de definição.
- **Procedures**: lista, parâmetros de entrada/saída e código-fonte.
- **Generators/Sequences**: nome e valor atual.
- **🆕 Mudanças**: compara a estrutura atual com uma referência salva
  anteriormente — mostra tabelas/campos/procedures/triggers/generators
  novos, removidos ou alterados desde a última vez.
- **Busca**: pesquisa por nome em tabelas, campos, procedures, triggers e
  generators simultaneamente. Atalho `/` ou `Ctrl+K` leva direto para a
  caixa de busca.
- **Console SQL**: executa comandos `SELECT` (ou `WITH ... SELECT`)
  somente leitura, com filtro nos resultados, visualização expandida e
  opção de salvar consultas usadas com frequência.

Guia completo, tela por tela, no [TUTORIAL.md](TUTORIAL.md).

## 6. Segurança

O console de consultas bloqueia por padrão qualquer comando que não seja
`SELECT`/`WITH` (INSERT, UPDATE, DELETE, DROP, ALTER, EXECUTE, etc.) na
camada da aplicação. Para garantia real de somente-leitura, recomenda-se
conectar com um usuário/role do Firebird que tenha apenas permissão de
`SELECT` nas tabelas do banco.

## 7. Gerando um executável (opcional)

Para distribuir sem exigir Python instalado:

```bash
pip install pyinstaller
pyinstaller --name InspetorFirebird --onefile --add-data "frontend;frontend" run.py
```

O executável ficará em `dist/InspetorFirebird.exe`.

## 8. Compartilhando/rodando em outra máquina

Existe uma cópia pronta para compartilhar em `../InspetorFirebird_Compartilhar`
(pasta irmã deste projeto) — contém só o código-fonte, sem `.venv/`,
`logs/`, `snapshots/` ou cache. É só copiar essa pasta inteira (pen-drive,
rede, zip) para a outra máquina e, lá, seguir os passos 1-3 deste README
(instalar Python 3.10+, cliente Firebird, `pip install -r requirements.txt`,
`python run.py`). Cada máquina gera seus próprios `logs/` e `snapshots/`
na primeira execução.

Se você alterar o código-fonte deste projeto depois, gere essa pasta de
novo (ou copie os arquivos alterados) antes de compartilhar — ela não se
atualiza sozinha.

## 9. Estrutura do projeto

```
exporta_banco/
├── backend/
│   ├── main.py           # rotas da API (FastAPI)
│   ├── db.py             # gerenciamento da conexão Firebird
│   ├── metadata.py       # consultas às tabelas RDB$... (metadados)
│   ├── security.py       # validação de consultas somente leitura
│   ├── logging_config.py # configuração do log em arquivo (logs/app.log)
│   └── schema_snapshot.py # salva/compara "fotografias" da estrutura (aba Mudanças)
├── frontend/
│   ├── index.html
│   ├── style.css
│   └── app.js
├── logs/                 # gerado automaticamente ao rodar (não versionado)
├── snapshots/            # referências de estrutura salvas (não versionado)
├── run.py                # ponto de entrada (escolhe porta livre, abre o navegador)
├── requirements.txt
├── README.md
└── TUTORIAL.md           # guia de uso passo a passo
```
