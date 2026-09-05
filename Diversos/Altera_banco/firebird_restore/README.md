# Restaurador Firebird

Ferramenta desktop para restaurar backups Firebird (`.fbk` ou `.fbk.gz`) em um
**novo** arquivo `.fdb`, usando o `gbak.exe` oficial. Não é um conversor de
extensão: executa uma restauração real e valida o resultado.

## Como rodar

```
cd firebird_restore
python main.py
```

Requer apenas Python 3.9+ com Tkinter (já incluído no instalador padrão do
Windows). Nenhuma dependência externa é necessária para rodar como script.

## Levar para outra máquina (sem instalar Python lá)

```
python -m pip install pyinstaller
cd firebird_restore
python -m PyInstaller RestauradorFirebird.spec --noconfirm
```

Gera **um único arquivo**, `dist/RestauradorFirebird.exe` (~12 MB) — é só
copiar esse arquivo para a outra máquina e dar duplo clique; não precisa de
Python nem de nenhum outro arquivo junto. A máquina de destino só precisa ter
o Firebird instalado (a ferramenta detecta automaticamente).

O `.exe` grava `config/settings.json` e `logs/` **na mesma pasta onde ele
está**, não em uma pasta temporária — por isso preferências e logs
sobrevivem entre execuções mesmo rodando só o `.exe` sem o resto do projeto.

Sem certificado de assinatura de código, o Windows SmartScreen provavelmente
vai alertar como "editor desconhecido" no primeiro uso em uma máquina nova —
é esperado, não indica corrupção do arquivo; basta "Mais informações" →
"Executar assim mesmo".

## O que a ferramenta já faz

1. **Detecta o Firebird automaticamente** — procura `gbak.exe` em
   `C:\Program Files\Firebird`, `C:\Program Files (x86)\Firebird`, no Registro
   do Windows (`HKEY_LOCAL_MACHINE` e `HKEY_CURRENT_USER`) e no PATH; lê a
   versão real rodando `gbak -z`. Se houver mais de uma instalação, todas
   aparecem no seletor.
2. **Aceita `.fbk` e `.fbk.gz`** — o painel de manutenção existente gera
   backups comprimidos (`backup_AAAAMMDD_HHMMSS.fbk.gz`); a ferramenta
   descompacta para um arquivo temporário antes de restaurar e sempre apaga
   esse temporário ao final (sucesso ou erro).
3. **Nunca sobrescreve um `.fdb` existente** — se o destino já existir, abre
   um diálogo com três opções: Cancelar, Escolher outro nome, ou Criar
   restauração paralela (sugestão automática `NOME_RESTAURADO_AAAAMMDD_HHMMSS.FDB`).
4. **Valida antes de restaurar**: arquivo existe, extensão correta, tamanho >
   0, permissão de leitura/escrita, espaço em disco estimado (configurável em
   `config/settings.json`), indícios de que o conteúdo é binário plausível
   (rejeita texto puro, PNG, ZIP, etc. — checagem best-effort; a confirmação
   definitiva de um backup Firebird válido é a própria tentativa do `gbak`).
5. **Restaura com `subprocess` seguro** — argumentos sempre em lista
   (`subprocess.Popen([...])`), nunca `shell=True`, nunca concatenação de
   string com entrada do usuário.
6. **Captura e traduz erros do gbak** — mensagens técnicas conhecidas (login
   inválido, sem permissão, incompatibilidade de versão, disco cheio, destino
   já existente) viram uma frase compreensível; o texto técnico completo fica
   disponível em "Ver detalhes técnicos", sempre com a senha mascarada.
7. **Distingue falha fatal de erro de índice não-fatal** — um erro de
   infraestrutura (permissão, disco, versão incompatível) OU indícios de que
   o **arquivo de backup em si** está corrompido/truncado ("unexpected end
   of file", "database file appears corrupt"...) interrompe a restauração e
   é reportado como falha, mesmo que um `.fdb` parcial já tenha sido criado
   antes do gbak abortar. Já um erro do tipo "cannot commit index" /
   "violation of FOREIGN KEY constraint" ao ativar um índice (dados órfãos
   dentro de um backup íntegro) não impede a conclusão — os dados são
   restaurados normalmente, e a contagem desses erros aparece sempre no
   resumo (mesmo sem marcar "Validação completa"), nunca fica escondida.
   Essa lógica é uma função pura (`restore.classificar_resultado_gbak`)
   coberta por testes dedicados.
8. **Valida o banco depois de restaurado** — confere o código de saída do
   gbak, o tamanho do arquivo criado e faz uma conexão de teste via `isql`
   (`SELECT 1 FROM RDB$DATABASE`) antes de declarar sucesso.
9. **Interface não trava** — a restauração roda em uma thread separada; a
   janela permanece responsiva e mostra o progresso linha a linha (saída do
   `gbak -v`) na área "Detalhes", com as linhas de erro do gbak destacadas em
   vermelho.
10. **Log em arquivo** — `logs/restore_AAAAMMDD.log`, com timestamp em cada
    linha. Senhas nunca são gravadas (mascaradas por `logger.mask_secrets`).
    Logs com mais de 90 dias são apagados automaticamente ao abrir o programa.
11. **Progresso percentual (aproximado)** — prioriza contar as tabelas do
    backup (`gbak -m`, rápido) para uma barra "tabela X de Y"; se isso falhar,
    cai para uma estimativa por crescimento do arquivo `.fdb` de destino.
    Fica sempre claro que é uma aproximação.
12. **Painel de detalhes com três atalhos**: "⚠ Ver somente os erros" (janela
    à parte, só com as linhas de nível ERRO já registradas), "💾 Salvar como
    .txt" (grava o acompanhamento inteiro em arquivo) e "👾 Modo hacker" (visual
    alternativo — terminal preto/verde — puramente estético, com a escolha
    lembrada entre execuções).
13. **Resumo completo ao final** — banco original (extraído do backup) e
    banco novo, Firebird usado, versão do formato do backup, ODS version e
    Page Size do banco restaurado, dialeto, tamanhos, horário de início/fim,
    duração total, contagem de erros do gbak (item 7) e (se a opção
    "Validação completa" for marcada) contagem de erros/avisos de uma passada
    `gfix -v -full`. Botão "📂 Abrir pasta" leva direto ao Explorer com o
    `.fdb` recém-criado já selecionado.
14. **Contagem de registros restaurados e tabelas com problema** — a partir
    da própria saída do `gbak` (sempre disponível), o resumo mostra quantos
    registros foram restaurados e em quantas tabelas. Se a "Validação
    completa" for marcada, o `gfix -v -full` aponta especificamente quais
    tabelas tiveram registros corrompidos/perdidos.
15. **Comparação de tamanho com o banco original** — campo opcional para
    apontar o `.fdb` de antes do backup; o resumo mostra a diferença e explica
    por que o banco restaurado pode ficar maior (backup guarda só os dados
    lógicos; ao restaurar, o Firebird recria páginas e reconstrói todos os
    índices do zero — isso muda o tamanho físico, mas não indica perda).
16. **Dica em cada campo/botão da tela** — basta passar o mouse sobre
    qualquer campo, botão ou opção para ver uma explicação do que ele faz.
17. **Múltiplas versões do Firebird lado a lado (2.5 a 5.0)** — a detecção
    não é específica de uma versão: qualquer `gbak.exe`/`gfix.exe`/`gstat.exe`
    encontrado (Program Files, Registro, PATH) vira uma opção selecionável.
18. **Page Size configurável** — a rotina de manutenção usada até hoje
    (`ANTIGO/BKP_Fb_5.0 3/BKP-RESTORE 5.0.BAT`) sempre restaura forçando
    `-p 16384`, independente do page size original do backup — isso é a causa
    mais provável de o banco restaurado ficar maior/menor que o original.
    Virou um campo configurável na tela (sugestão padrão: 16384, igual à
    rotina antiga), com opção de usar o page size original do backup.
19. **Cronômetro de tempo restante confiável** — usa uma média móvel da
    velocidade de progresso e nunca exibe um tempo maior que o do tick
    anterior. Restaurar mais de uma vez na mesma sessão não deixa cronômetros
    de execuções antigas rodando em paralelo (cada novo início cancela
    explicitamente o anterior — sem isso, ticks concorrentes faziam o valor
    exibido "pular para cima" às vezes).
20. **Card de status de integridade sempre visível no resumo** — fixo no
    topo da janela de resumo (não dentro da área rolável): erro do gbak
    (item 7) tem prioridade; senão, resultado da validação completa se ela
    foi executada; senão, confirmação de que o gbak não reportou nada.
21. **Versão do sistema TGA** — quando o banco restaurado tem a tabela
    `GDIVERSOS`, consulta `VERSAO_BASE`, `DATA_ATUALIZACAO`, `VERSAO_MOBILE` e
    `TGA_START`, filtrando por `WHERE TGA_START IS NULL` (o registro vigente,
    não um registro histórico de migração); cai automaticamente para uma
    consulta sem esse filtro em bancos mais antigos que não têm a coluna.
    Diferente da ODS version (que é da estrutura do Firebird, não do
    aplicativo). Silenciosamente omitido em bancos que não são do TGA.
22. **Buscar/filtrar** dentro de qualquer área de log (painel principal,
    "Ver somente os erros", histórico da sessão) — uma barra de busca destaca
    (em amarelo) as ocorrências do termo digitado, navegáveis com Enter ou
    ◀ ▶.
23. **Histórico da sessão** — botão "📋 Histórico desta sessão" mostra um
    relatório consolidado de todas as restaurações feitas desde que o
    programa foi aberto, agrupado por arquivo de backup de origem (útil
    quando o mesmo backup é restaurado mais de uma vez, ex.: um conflito de
    nome levou a criar um segundo banco com sufixo `_RESTAURADO`), incluindo
    erros do gbak (item 7) entre os avisos. Também pode ser salvo como .txt.
24. **Cancelar restauração em andamento** — botão "❌ Cancelar restauração"
    mata o processo do `gbak` com segurança (mesmo se ele estiver "quieto",
    sem imprimir novas linhas) e remove o arquivo de destino parcial, para
    nunca deixar um `.fdb` incompleto passando por válido. Pede confirmação
    antes de agir.
25. **Avisos de conclusão configuráveis** — som nativo do Windows
    (`winsound`, checkbox "🔊 Tocar som ao concluir", marcado por padrão) e/ou
    notificação toast do Windows (`notificacoes.py`, via PowerShell + WinRT,
    sem dependência externa; checkbox "🔔 Notificação do Windows ao concluir",
    também marcado por padrão) — útil para bancos grandes que demoram
    minutos, inclusive com a janela minimizada.
26. **Versão visível** — aparece no título da janela e no rodapé
    (`Restaurador Firebird vX.Y.Z`, definida em `version.py`), para facilitar
    dizer "qual versão eu tenho" ao reportar um problema.
27. **Charset customizado (avançado)** — campo com `-fix_fss_data` /
    `-fix_fss_metadata` do gbak, que corrige dados/metadados gravados com
    charset malformado (comum em bases antigas migradas do InterBase, ou
    quando o backup tem charset `NONE`). Sem isso, colunas com acentuação
    podem falhar com "cannot transliterate" no meio da restauração.
28. **Recuperar banco corrompido** (`recuperacao.py` + botão
    "🩹 Recuperar banco corrompido") — para quando o problema é no **banco
    original** (`.fdb`), não no backup: roda `gfix -mend -full` (marca
    estruturas corrompidas para serem puladas), `gfix -sweep` (limpeza) e
    `gbak -b -g -l -ignore` (gera um backup ignorando erros de
    página/checksum) **sempre sobre uma cópia temporária** — o arquivo
    original nunca é aberto em modo de escrita. Pode ser cancelada a
    qualquer momento, mesmo no meio de uma etapa longa (mata o processo
    gfix/gbak em execução, não espera ele terminar sozinho). Ao final,
    oferece restaurar o backup gerado com o fluxo normal já existente. É
    recuperação parcial: páginas/registros realmente danificados são
    descartados do resultado, não recuperados — quando isso não é
    suficiente, a orientação é buscar suporte especializado em recuperação
    de banco de dados.
29. **Restaurar em lote** (`ui/janela_fila_restauracao.py` + botão
    "📚 Restaurar em lote") — adicione vários backups de uma vez (seleção
    múltipla de arquivos) e restaure todos em sequência, com a mesma
    configuração (Firebird, usuário, senha, page size, charset, validação
    completa) para todos os itens. Cada item usa `restore.executar_restauracao()`
    — a mesma função da restauração individual — então a regra de nunca
    sobrescrever um destino existente continua valendo; como não há como
    abrir um diálogo de conflito no meio de um lote sem supervisão, um
    destino que já existir ao adicionar à fila já recebe automaticamente o
    sufixo `_RESTAURADO_<timestamp>`. Suporta cancelar a fila inteira (o
    item atual é interrompido, os seguintes não são processados) e, ao
    final, mostra o mesmo relatório consolidado do "Histórico desta sessão".

## O que ficou para depois

- Localizar backup mais recente automaticamente / arrastar-e-soltar para a
  fila de restauração em lote.
- Modo de linha de comando (sem abrir a GUI), para agendar restaurações de
  teste via Tarefas Agendadas do Windows.
- Tela de diagnóstico para suporte remoto.
- Função de "substituir banco original" (deliberadamente não implementada —
  é a operação mais perigosa do escopo e exige confirmação explícita e backup
  de segurança prévio).
- Assinatura digital do `.exe` (evitaria o alerta do SmartScreen).

## Como a rotina de backup/restore funcionava antes (`ANTIGO/`)

O usuário adicionou a pasta `ANTIGO/BKP_Fb_5.0 3/` com o script usado até
hoje (`BKP-RESTORE 5.0.BAT`) e os executáveis/DLLs que ele chamava. Análise:

```bat
ren TGA.FDB TGAANTIGO.FDB
gfix -v -full TGAANTIGO.FDB -user sysdba -password masterkey
gfix -v -n TGAANTIGO.FDB -user sysdba -password masterkey
gfix -v -n -i TGAANTIGO.FDB -user sysdba -password masterkey
gfix -me -i TGAANTIGO.FDB -user sysdba -password masterkey
gfix -sweep TGAANTIGO.FDB -user sysdba -password masterkey
gbak -b -v -g -l TGAANTIGO.FDB TGA.fbk -user sysdba -password masterkey
gbak -create -v -p 16384 TGA.fbk TGA.FDB -user sysdba -password masterkey
gfix -v -full TGA.FDB -user sysdba -password masterkey
```

- **Usuário/senha**: sempre `sysdba`/`masterkey` — já é o padrão desta ferramenta.
- **`-p 16384` no restore**: o achado mais importante. A rotina antiga sempre
  força o page size em 16384 bytes, **independente do page size gravado no
  backup original** — essa é a explicação mais provável para "o banco
  restaurado fica maior que o original antes do backup". Virou um campo
  configurável na tela (ver item 18 acima), com 16384 como sugestão padrão.
- **`gfix -me -i` (mend) + `gfix -sweep` antes do backup**: a rotina antiga
  tentava reparar/limpar o banco original *antes* de gerar o `.fbk`, para o
  backup sair "limpo". Esta ferramenta não faz backup (só restore), então
  essa etapa não se aplica aqui — mas explica por que a validação de
  integridade (`gfix -v -full`) é uma etapa nativa do fluxo Firebird, não algo
  inventado por esta ferramenta.
- **DLLs e `gbak.exe`/`gfix.exe` embarcados na pasta**: não são necessários —
  esta ferramenta detecta e usa a instalação do Firebird já presente no
  sistema (Program Files, Registro, PATH), então essas cópias antigas
  (`GDS32.DLL`, `UNZDLL.DLL`, `ZIPDLL.DLL`, `udflex*.dll` de 2005-2007) podem
  ser **descartadas com segurança** — a lógica que valia a pena aproveitar
  (o `-p 16384` e o usuário/senha) já foi incorporada ao código. Mantida no
  repositório a pedido do usuário, como referência histórica.

O que continua desconhecido (a rotina de geração do `.fbk.gz` visto no painel
web de manutenção, com nome `backup_AAAAMMDD_HHMMSS.fbk.gz`) não tem relação
direta com este `.BAT` — são fluxos diferentes. A sugestão automática de nome
de destino em `_sugerir_destino_a_partir_do_backup` (`ui/main_window.py`)
lida com esse padrão de nome; se estiver errado, é só cosmético (não bloqueia
a restauração).

## Testes

```
cd firebird_restore
python -m unittest discover -s tests -v
```

68 testes cobrindo: arquivo inexistente, arquivo vazio, extensão inválida,
caminho com espaços/acentos, `.fbk.gz` válido e corrompido, conteúdo que não é
um backup real (texto, PNG), destino já existente (não deve autorizar
sobrescrita), sugestão de nome paralelo, estimativa de espaço em disco,
tradução de mensagens de erro do gbak, a classificação de erro fatal vs.
erro de índice não-fatal (`classificar_resultado_gbak`) — incluindo o caso de
um backup corrompido/truncado que já criou um `.fdb` parcial antes de abortar,
que precisa continuar sendo tratado como falha total, não como "sucesso com
avisos" —, o parsing da saída do `gbak -b` usado por `recuperacao.py`, e a
sugestão automática de destino da fila de restauração em lote (incluindo o
caso de conflito de nome, sem diálogo interativo).

`tests/test_integration_gbak.py` e `tests/test_integration_recuperacao.py`
fazem o mesmo tipo de teste, mas de ponta a ponta e de verdade: criam um
banco Firebird real e chamam `restore.executar_restauracao()` /
`recuperacao.recuperar_banco_corrompido()` — as mesmas funções que a
interface usa. São pulados automaticamente (não falham) se esta máquina não
tiver o Firebird instalado, ou se as credenciais padrão (`SYSDBA`/`masterkey`)
não funcionarem nela. O teste de recuperação valida a mecânica do fluxo
(gfix -mend + gbak -ignore rodam e produzem um backup íntegro) sobre um
banco saudável — não há como simular corrupção real de forma portátil só
para teste — e também que o cancelamento no meio de uma etapa (não só entre
etapas) interrompe o processo em segundos, não esperando o timeout de 30
minutos por etapa.

## Estrutura

```
firebird_restore/
├── main.py                     # ponto de entrada
├── restore.py                  # orquestra o fluxo de restauração + resumo
├── firebird.py                 # detecção de instalações/gbak/gfix/gstat e versão
├── validator.py                # validações de arquivo, destino, espaço em disco
├── stats.py                    # page size, ODS version, versão TGA e validação de integridade
├── config.py                   # settings.json (nunca grava senha)
├── logger.py                   # log em arquivo + distribuição em tempo real p/ UI
├── notificacoes.py              # notificação toast do Windows (PowerShell + WinRT)
├── recuperacao.py                # recuperação de banco corrompido (gfix -mend + gbak -ignore)
├── ui/
│   ├── main_window.py           # janela principal (Tkinter)
│   ├── dialogos.py               # janelas secundárias: resumo, histórico, erros...
│   ├── janela_recuperacao.py      # janela "Recuperar banco corrompido"
│   ├── janela_fila_restauracao.py  # janela "Restaurar em lote"
│   ├── widgets.py                 # tooltip, busca/filtro, salvar .txt, abrir pasta
│   └── estilos.py                 # paletas de cor (normal / modo hacker)
├── tests/                        # testes automatizados (unittest, sem dependências)
├── logs/                         # gerado em runtime (limpo automaticamente após 90 dias)
└── config/settings.json          # gerado em runtime
```
