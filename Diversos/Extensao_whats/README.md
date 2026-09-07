# TGameAjuda Edita Whats

Camada **leve** de produtividade para o **WhatsApp Web**. Não substitui a
interface — só adiciona um botão discreto com **respostas rápidas** que você
insere no campo de mensagem com um clique (ou digitando `/atalho`).

> A extensão **nunca envia mensagens sozinha**. Ela só preenche o campo;
> quem revisa e envia é você.

---

## O que já funciona (MVP — Fase 1)

| Recurso | Detalhe |
|---|---|
| Botão discreto | Padrão: encaixado **na barra de ícones do WhatsApp** (à esquerda). Alternativas em Config: flutuante nos cantos. Se a barra não for detectada, cai para "lateral direita" e volta sozinho quando ela aparece. **Pode ser arrastado** para qualquer lugar da tela (posição salva; "Voltar para automática" em Config) |
| Painel pequeno | Abas **Respostas** e **Config**; fecha com `✕`, `ESC` ou clique fora |
| Respostas rápidas | Criar / editar / excluir, com título e atalho opcional (`/horario`) |
| Busca instantânea | Filtra por atalho, título ou texto |
| Sugestão ao digitar `/` | Digite `/hor` no campo do WhatsApp → caixinha com as respostas que casam. `↑`/`↓` navega, `Enter` insere (sem enviar), `Esc` fecha |
| Notas por conversa | Aba **Notas**: um bloco de texto por conversa, salvo automaticamente (local). A conversa é identificada pelo JID (`…@c.us`/`@g.us`) extraído do `data-id` das mensagens; troca de conversa recarrega a nota. Ponto verde na aba quando a conversa atual tem nota ou tag |
| Tags internas | Aba **Notas**, acima do bloco: chips coloridas (Novo cliente, Aguardando retorno, Financeiro, Suporte, Resolvido) que ligam/desligam por conversa. `＋` cria tag personalizada; "editar tags" remove do catálogo. Só da extensão, não altera o WhatsApp |
| Lembretes / follow-up | Aba **Lembretes**: texto + atalhos de horário (Em 1h, Hoje 18h, Amanhã 9h…) ou data/hora livre; opção de vincular à conversa atual. Vencidos aparecem num aviso na tela enquanto o WhatsApp está aberto (sem permissão nativa); "+1 h" adia, "concluir"/"remover". Contador vermelho na aba com os vencidos |
| Saudação por horário | Aba **Respostas**, no topo: botão "Bom dia / Boa tarde / Boa noite" conforme o relógio local — insere `Saudação! ` no campo (sem enviar, sem supor o nome) |
| Categorias nas respostas | Campo "Categoria" (opcional) no formulário, com sugestão das já usadas. Aba **Respostas**: chips de filtro (Todas / Atendimento / Suporte…) e lista **agrupada** por categoria. Busca ignora o filtro |
| Variáveis nas respostas | `{{nome}}` (1º nome do contato), `{{contato}}` (nome completo), `{{saudacao}}`, `{{data}}`, `{{hora}}`, `{{diasemana}}` — trocadas no momento de inserir (botão "Usar" e sugestão `/atalho`). Chips no formulário inserem a tag no cursor. Avisa se `{{nome}}` ficou vazio (sem conversa) |
| Favoritos de conversas | Aba **Favoritos**: lista própria (não mexe no WhatsApp). Botão favoritar/desfavoritar a conversa atual; "Abrir" preenche a busca do WhatsApp com o nome pra você clicar; estrela na aba quando a conversa atual é favorita |
| Nova conversa por número | Aba **Favoritos**, no topo: DDI + DDD/número (+ mensagem inicial opcional) → "Abrir conversa" abre direto o chat mesmo com número **não salvo** (usa `web.whatsapp.com/send?phone=`, recarrega o WhatsApp); "Copiar link" gera um `wa.me/…`. O DDI fica lembrado |
| Privacidade de tela | **Config → Privacidade de tela**: desfoca nomes / fotos / prévias / mensagens da conversa / campo de digitação **na sua tela** (só CSS — passe o mouse por cima para ver). Nada é enviado, ninguém do outro lado é afetado |
| Assinatura automática | **Config**: acrescenta um texto no fim das respostas rápidas ao usar (opcional) |
| Ordenar respostas | Aba **Respostas**: manual / **mais usadas primeiro** (conta os usos) / alfabética. Botão **Duplicar** em cada resposta |
| Fundo da conversa | **Config**: cor sólida ou imagem por URL (https) atrás das mensagens — só CSS, best-effort |
| Estatísticas | **Config**: contagem dos dados da extensão (respostas, usos, notas, tags, favoritos, lembretes) + resposta mais usada |
| Modo compacto | **Config**: painel com menos espaçamento |
| Atalhos no painel | `Alt+1`…`Alt+6` troca de aba com o painel aberto |
| Prévia da formatação | Aba **Texto**: mostra `*negrito*` `_itálico_` `~tachado~` `` `código` `` já renderizados |
| Apagar tudo | **Config**: zera todos os dados da extensão (2 toques) |
| Ferramentas de texto | Aba **Texto**: MAIÚSCULAS / minúsculas / capitalização por palavra e por frase, remover espaços duplos, reduzir linhas em branco, juntar linhas, limpar caracteres invisíveis; formatação `*negrito*` `_itálico_` `~tachado~` `` `código` `` (alterna); **formatar CPF / CNPJ / Telefone / CEP / R$** (reconhece o valor no texto, formata no lugar); **calculadora** (expressão sem `eval` + "X % de Y" com acréscimo/desconto). Contador de caracteres/palavras/linhas. "Pegar do WhatsApp" traz o texto do campo; "Usar no WhatsApp" devolve (sem enviar); "Copiar"; "Desfazer" (1 nível) |
| Inserir no campo | 3 estratégias de fallback; nunca envia |
| Tema claro/escuro | `Automático` acompanha o WhatsApp |
| Backup | Exportar / importar `.json` (validado na importação) |
| Armazenamento | 100% local (`chrome.storage.local`). Nada sai do navegador |
| À prova de falha | Se um seletor do WhatsApp quebrar, a função para — o WhatsApp continua normal |

Atalho de teclado padrão: **`Alt+R`** abre/fecha o painel (o clique no ícone da
extensão também). Pode ser trocado em `chrome://extensions/shortcuts`.

---

## Como instalar para testar (Chrome / Edge)

1. Abra `chrome://extensions` (ou `edge://extensions`).
2. Ative o **Modo do desenvolvedor** (canto superior direito).
3. Clique em **Carregar sem compactação** / **Load unpacked**.
4. Selecione esta pasta (a que contém o `manifest.json`).
5. Abra <https://web.whatsapp.com/> e aguarde carregar. O botão redondo
   aparece no canto inferior direito.

Ao recarregar o código: volte em `chrome://extensions` e clique no ícone de
**recarregar** do cartão da extensão; depois recarregue a aba do WhatsApp.

---

## Estrutura

```
manifest.json                 Manifest V3 — permissões mínimas (só "storage")
icons/                         16 / 32 / 48 / 128 px
src/
  content/
    loader.js                 content script clássico: importa o módulo ES principal
    content.js                orquestrador (amarra as peças)
  background/
    service-worker.js         instalação + atalho de teclado + clique no ícone
  whatsapp/                    >>> CAMADA DE COMPATIBILIDADE <<<
    selectors.js              TODOS os seletores do WhatsApp ficam aqui
    adapter.js                API estável: getComposer(), getNavRail(), waitForApp()...
    composer.js               inserir texto SEM enviar (com fallbacks)
  features/
    quick-replies.js          lógica pura (sem DOM): busca, match de atalho, validação
  storage/
    storage.js                única porta do chrome.storage.local; normaliza tudo
  ui/
    styles.js                 CSS como string, injetado no Shadow DOM
    dom.js                    criação de elementos SEM innerHTML
    theme.js                  detecta/acompanha tema claro/escuro
    panel.js                  botão flutuante + painel (Shadow DOM)
    suggestions.js            caixinha de sugestão ao digitar "/atalho" (Shadow DOM)
```

### Se o WhatsApp mudar o layout

Quase sempre basta ajustar **`src/whatsapp/selectors.js`** (lista de candidatos
por seletor, do mais estável para o mais frágil). Se a forma de inserir texto
mudar, ajuste **`src/whatsapp/composer.js`**. Nenhum outro arquivo fala com o
DOM do WhatsApp.

Hooks usados hoje na barra lateral (UI nova): `[data-testid="navbar-primary-section"]`
(a coluna) e `button[data-navbar-item="true"]` (cada ícone) — o botão da extensão
é posicionado logo abaixo do último item, sem injetar nada na árvore do WhatsApp.

---

## Decisões de projeto

- **JavaScript puro + Manifest V3**, zero dependências, sem build.
- **Shadow DOM** nos dois pontos de UI: o CSS do WhatsApp não afeta a extensão
  e vice-versa. Ainda assim, todas as classes usam o prefixo `tgaw-`.
- **Sem `innerHTML`** com dado do usuário — só `textContent` (ver `ui/dom.js`).
- **Sem polling agressivo / sem MutationObserver pesado**: a sugestão usa 1
  listener `input` (debounced 90ms) e 1 listener `keydown` de captura ligado
  ao próprio campo. O tema observa apenas atributos do `<html>`.
- **Permissões mínimas**: só `storage` + `host_permissions` para
  `https://web.whatsapp.com/*`. Sem `<all_urls>`, sem `tabs` além do necessário.
- `DEBUG = false` em `src/core/debug.js` — console limpo em produção.

---

## Roteiro de testes manuais

- [ ] WhatsApp abrindo do zero / já carregado
- [ ] Trocar de conversa (contato, grupo, conversa sem nome)
- [ ] Tema claro e escuro (e opção `Automático`)
- [ ] Janela estreita / zoom do navegador
- [ ] Criar, editar, excluir e buscar resposta
- [ ] `/atalho` no campo → sugestão → `Enter` insere e **não** envia
- [ ] Botão "Usar" no painel insere e fecha
- [ ] `Alt+R` e clique no ícone abrem/fecham o painel
- [ ] Exportar backup, reinstalar, importar backup
- [ ] Reload da aba / logout / novo login
- [ ] Sem travamento perceptível (CPU/RAM estáveis)

---

## Privacidade

- Tudo é salvo **apenas neste navegador** (`chrome.storage.local`).
- Nenhum servidor externo, nenhum código remoto, nenhuma conta.
- A extensão **não** lê histórico de conversas, **não** exporta contatos,
  **não** acessa cookies/sessão e **não** envia mensagens automaticamente.

---

## Próximas fases (não implementadas)

- **Fase 2**: ~~notas por conversa~~ (feito), favoritos, categorias/modelos.
- **Fase 3**: ~~ferramentas de texto, contador de caracteres, formatação~~ (feito);
  falta saudação por horário, atalhos de teclado configuráveis.
- **Fase 4**: ~~tags internas~~, ~~lembretes/follow-up~~ (feito). Possível evolução: notificação nativa via `chrome.alarms` + `chrome.notifications` (exige as 2 permissões).
- **Fase 5**: avaliar backend para sincronização/equipe/integração ERP-CRM.
