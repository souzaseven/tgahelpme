# Migrador Firebird

Ferramenta desktop para **migrar um banco Firebird 2.5 para o Firebird 5.0**
pelo caminho correto — não é renomear/copiar `.fdb`:

```
banco 2.5 ──(gbak -b, Firebird 2.5)──▶ .fbk ──(gbak -c, Firebird 5.0)──▶ .fdb 5.0
                                                        │
                                        validação + comparação de contagens + relatório
```

O banco de origem **nunca** é alterado. O destino **nunca** é sobrescrito.

Este é o MVP (fase 1). Só faz o sentido **2.5 → 5.0 (upgrade)**. As demais
direções (3.0/4.0 → 5.0, downgrade, migração lógica, modo em lote) estão
previstas para fases seguintes — veja o fim deste arquivo.

## Como rodar

```
cd migrador
python -m pip install -r requirements.txt
python main.py
```

- Python 3.10+ (testado no 3.14).
- A interface usa **PySide6** (única dependência). O wheel `abi3` do PySide6
  6.11 funciona no Python 3.14. Se em outra máquina o `pip` não achar wheel
  para a versão do Python instalada:

  ```
  py -3.12 -m venv .venv
  .venv\Scripts\activate
  pip install -r requirements.txt
  python main.py
  ```

- A máquina precisa ter **o Firebird 5.0 instalado** (para o restore) e
  **o Firebird 2.5** (para o backup). A detecção é automática (Program Files,
  Registro do Windows, PATH). Se não houver um Firebird 2.5 nesta máquina:
  - use o botão **"Apontar gbak.exe do Firebird 2.5…"** na aba 1 (serve um
    Firebird 2.5 portátil descompactado numa pasta), **ou**
  - gere o `.fbk` na máquina que ainda roda o servidor 2.5 — nesse caso o
    MVP atual ainda espera um `.fdb` como entrada; suporte a `.fbk` pronto
    entra numa próxima iteração.

## O fluxo, aba por aba

| Aba | O que faz |
|---|---|
| **1 · Origem** | Escolher o `.fdb` 2.5, usuário/senha, e qual instalação do Firebird usar para backup (2.5) e restore (5.0). |
| **2 · Análise** | `gstat -h` + `isql` na origem: ODS, page size, dialeto, charset, contagens (tabelas, views, procedures, triggers, generators, domains, índices, FKs), lista de tabelas, UDFs, tabelas externas. A versão é deduzida da **ODS**, não da extensão do arquivo. |
| **3 · Compatibilidade** | Avisos para a direção 2.5→5.0: UDFs (o FB5 desativa UDF por padrão), charset `NONE`/`ASCII`, nomes de objeto que viraram palavra reservada, tabelas externas, dialeto 1, page size < 4096. Um aviso de **bloqueio** (ex.: a origem não é ODS 11.x) desabilita o avanço. |
| **4 · Migração** | Pasta e nomes de destino (`NOME_FB25_AAAAMMDD_HHMMSS.fbk` / `NOME_FB50_AAAAMMDD_HHMMSS.FDB`), opções avançadas de restore, checagem de espaço em disco, log ao vivo e barra de progresso. Cancelável. |
| **5 · Resultado** | `CONCLUÍDA` / `CONCLUÍDA COM AVISOS` / `FALHOU`, texto do relatório (salvo automaticamente ao lado do banco migrado), botões para abrir a pasta, ver o log e salvar o relatório. |

## Comandos executados

Backup (Firebird de **origem**):

```
gbak -b -v -g -l -user <U> -password <P>  <origem.fdb>  <destino.fbk>
```

`-g` inibe a coleta de lixo (não escreve no arquivo de origem); `-l` ignora
transações em limbo. Há a opção de fazer o backup sobre uma **cópia** da
origem, sem tocar no arquivo real.

Restore (Firebird de **destino**, 5.0):

```
gbak -c -v [-p <page_size>] [-fix_fss_data <cs> -fix_fss_metadata <cs>] [-par N] [-o] \
     -user <U> -password <P>  <destino.fbk>  <destino.fdb>
```

`-c` (create) falha se o destino já existir — é a garantia de nunca
sobrescrever. Por padrão o page size do backup é preservado.

Validação (Firebird 5.0): `gstat -h` no arquivo novo + `isql`
(`SELECT 1 FROM RDB$DATABASE` e recontagem dos metadados) + `gfix -v -full`
opcional.

## Segurança

- `subprocess` sempre com lista de argumentos, nunca `shell=True`, nunca
  concatenação com entrada do usuário.
- Senha só em memória; mascarada no log (`logger.mascarar_segredos`) e nunca
  gravada em `config/settings.json`.
- A ferramenta não para serviço, não instala/desinstala Firebird, não troca
  `fbclient.dll`.
- A classificação da saída do `gbak` distingue **falha fatal**
  (`expected backup description record`, backup truncado, ODS incompatível,
  disco cheio…) de **erro de índice não fatal** (violação de FK/PK ao
  reativar índices sobre dados órfãos). Um `.fdb` parcial de uma falha fatal
  é apagado.

## Testes

```
cd migrador
python -m unittest discover -s tests -v
```

47 testes de regras puras (sem Firebird, sem PySide6): parsing de `gstat -h`
e da saída do `isql`, regras de compatibilidade (palavras reservadas por
versão, UDF, charset, dialeto, ODS de bloqueio), classificação da saída do
`gbak` — incluindo o caso `expected backup description record` dos prints —,
matemática de espaço em disco, geração dos nomes de arquivo, e os agregados
da comparação.

`tests/test_integration_migracao.py` cria um banco Firebird 2.5 real, migra
para o 5.0 com `core.migracao.executar_migracao()` e confere as contagens.
É **pulado automaticamente** (não falha) se a máquina não tiver as duas
versões instaladas.

## Estrutura

```
migrador/
├── main.py                  # ponto de entrada (QApplication)
├── version.py
├── config.py                # settings.json — nunca grava senha
├── logger.py                # log em arquivo + tempo real; mascara senha
├── core/                    # núcleo, sem PySide6
│   ├── modelos.py           # dataclasses compartilhadas
│   ├── firebird.py          # detecção de instalações + versão via gbak -z
│   ├── analise.py           # gstat -h + isql nas RDB$ → ResultadoAnalise
│   ├── compatibilidade.py   # regras puras 2.5→5.0 → lista de Aviso
│   ├── migracao.py          # backup → restore; classificar_resultado_gbak
│   ├── validacao.py         # conexão/gstat/gfix no banco migrado
│   ├── comparacao.py        # COUNT(*) origem × destino, tabela a tabela
│   └── relatorio.py         # relatório final + .txt
├── ui/                      # PySide6
│   ├── janela_principal.py  # QTabWidget com as 5 etapas
│   ├── aba_*.py             # uma aba por etapa
│   ├── trabalhadores.py     # QThreads que rodam o core fora da UI
│   ├── estado.py            # estado compartilhado entre as abas
│   ├── widgets.py / estilos.py
├── tests/
├── logs/                    # runtime
└── config/settings.json     # runtime
```

## O que ficou para as próximas fases

- Entrada por `.fbk` já pronto (quando o backup 2.5 foi feito em outra máquina).
- Origens 3.0 e 4.0 para o destino 5.0 (mesmo fluxo backup/restore).
- **Downgrade** (5.0 → 4.0/3.0/2.5) e **migração lógica** (recriar schema e
  copiar dados objeto a objeto quando o restore direto não é possível).
- Comparações mais profundas (PK min/máx, somatórios, hash por lote, BLOB).
- Modo em lote (vários bancos de uma pasta).
- Empacotamento em `.exe` único (PyInstaller) e pasta `compartilhar/`.
- Etapa separada e explicitamente confirmada para substituir o banco em
  produção (fora do escopo por ser a operação mais perigosa).
