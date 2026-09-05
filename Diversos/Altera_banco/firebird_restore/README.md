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
Windows). Nenhuma dependência externa é necessária nesta fase.

## O que esta primeira versão já faz (Fases 1–9 do escopo)

1. **Detecta o Firebird automaticamente** — procura `gbak.exe` em
   `C:\Program Files\Firebird`, `C:\Program Files (x86)\Firebird`, no Registro
   do Windows e no PATH; lê a versão real rodando `gbak -z`. Se houver mais de
   uma instalação, todas aparecem no seletor.
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
7. **Valida o banco depois de restaurado** — confere o código de saída do
   gbak, o tamanho do arquivo criado e faz uma conexão de teste via `isql`
   (`SELECT 1 FROM RDB$DATABASE`) antes de declarar sucesso.
8. **Interface não trava** — a restauração roda em uma thread separada; a
   janela permanece responsiva e mostra o progresso linha a linha (saída do
   `gbak -v`) na área "Detalhes".
9. **Log em arquivo** — `logs/restore_AAAAMMDD.log`, com timestamp em cada
   linha. Senhas nunca são gravadas (mascaradas por `logger.mask_secrets`).

## O que ficou para depois (conforme o escopo faseado)

- Restauração em lote / localizar backup mais recente / arrastar-e-soltar
  (Fases de "futuro" no escopo original).
- Tela de diagnóstico para suporte remoto.
- Função de "substituir banco original" (deliberadamente não implementada —
  é a operação mais perigosa do escopo e exige confirmação explícita e backup
  de segurança prévio).
- Empacotamento com PyInstaller (Fase 12) — o código já roda como script puro;
  falta gerar o `.exe` quando o fluxo estiver validado com backups reais do
  ambiente de produção.

## O que eu não sabia sobre a rotina atual de backup

Não tive acesso ao script que gera os `.fbk` — a única evidência disponível
foi uma captura de tela do painel de manutenção já existente. Duas suposições
ficaram registradas no código e devem ser confirmadas com um backup real:

- Formato do nome: `backup_AAAAMMDD_HHMMSS.fbk.gz` (comprimido). Se a rotina
  real usar outro padrão, a única parte que depende disso é a sugestão
  automática de nome de destino (`_sugerir_destino_a_partir_do_backup` em
  `ui/main_window.py`) — cosmética, não bloqueia a restauração.
- Versão/parâmetros do Firebird usados para gerar o backup: desconhecidos.
  Por isso a detecção de instalação é sempre feita em runtime e qualquer
  incompatibilidade é reportada pelo próprio `gbak`, nunca assumida.

## Testes

```
cd firebird_restore
python -m unittest discover -s tests -v
```

24 testes cobrindo: arquivo inexistente, arquivo vazio, extensão inválida,
caminho com espaços/acentos, `.fbk.gz` válido e corrompido, conteúdo que não é
um backup real (texto, PNG), destino já existente (não deve autorizar
sobrescrita), sugestão de nome paralelo, estimativa de espaço em disco e
tradução de mensagens de erro do gbak.

Além disso, o fluxo completo foi validado manualmente de ponta a ponta nesta
máquina: backup gerado com `gbak -b` a partir de um banco de exemplo,
comprimido em `.gz`, e restaurado com sucesso pela ferramenta (descompactação
+ `gbak -c -v` real + validação via `isql`).

## Estrutura

```
firebird_restore/
├── main.py           # ponto de entrada
├── restore.py        # orquestra o fluxo de restauração
├── firebird.py        # detecção de instalações/gbak e versão
├── validator.py       # validações de arquivo, destino e espaço em disco
├── config.py           # settings.json (nunca grava senha)
├── logger.py            # log em arquivo + distribuição em tempo real p/ UI
├── ui/main_window.py     # interface Tkinter
├── tests/                # testes automatizados (unittest, sem dependências)
├── logs/                 # gerado em runtime
└── config/settings.json  # gerado em runtime
```
