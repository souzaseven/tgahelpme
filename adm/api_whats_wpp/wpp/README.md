# Painel WhatsApp — API Brasil (WPP)

Painel para conectar e enviar mensagens via WhatsApp usando a **API Brasil**
(`https://gateway.apibrasil.io/api/v2`). Front-end em HTML/CSS/JS puro,
backend em PHP puro (sem framework).

## Estrutura

```
api_whats_wpp/
├── .env                  # credenciais reais (NÃO versionar)
├── .env.example           # modelo do .env
├── config/config.php      # carrega o .env e define constantes globais
├── src/
│   ├── ApiBrasilClient.php  # cliente HTTP genérico (auth, timeout, erros)
│   ├── WhatsAppService.php  # regras de domínio: start(), sendText()
│   └── StateStore.php       # cache local em JSON (data/session_state.json)
├── api/
│   ├── start.php          # POST — inicia sessão / pede QR Code
│   ├── send.php           # POST — envia mensagem de texto
│   ├── state.php          # GET  — estado atual (usado pelo polling do JS)
│   └── webhook/            # recebem callbacks da API Brasil (wh_status, wh_qrcode, wh_connect, wh_message)
├── data/                   # armazenamento local (gitignored)
├── assets/css/style.css
├── assets/js/app.js
└── index.html
```

## Como rodar localmente

Requer PHP 8.1+ com extensão `curl` habilitada.

```bash
php -S localhost:8000
```

Acesse `http://localhost:8000`.

## Configuração

As credenciais já estão no `.env` (Bearer Token + Device Token que você
forneceu). **Nunca** commite esse arquivo — ele já está no `.gitignore`.
Se for versionar o projeto no Git, confirme que `.env` não aparece em
`git status` antes do primeiro commit.

## O que já está confirmado

- **Endpoint `start`** (`POST /whatsapp/start`): request 100% mapeado em
  `WhatsAppService::start()`, incluindo `session`, `qrcode`, `number`,
  webhooks (`wh_status`, `wh_message`, `wh_connect`, `wh_qrcode`), etc.
- **Formato de erro padrão da API**: `{ "error": true, "message": "...", "code": "..." }`
  — já tratado em `ApiBrasilClient::post()` e propagado como `ApiBrasilException`.

## O que falta confirmar (pendências)

1. **Response de sucesso do `start`** — ainda não vimos o JSON real de
   retorno (onde vem o QR Code). O front-end (`extractQrCode()` em
   `assets/js/app.js`) tenta adivinhar o campo (`qrcode`, `qr_code`,
   `base64`, `qr`...) — **ajustar assim que tivermos um exemplo real**.
2. **Endpoint de envio de texto** — `WhatsAppService::sendText()` está
   chamando `whatsapp/sendText` com `{ session, number, text }` como
   estimativa. Precisa confirmar o nome do endpoint e os campos exatos.
3. **Endpoint de status/conexão** (opcional) — se existir um
   `status`/`checkConnection`, dá pra usar como alternativa/reforço aos
   webhooks.

## Sobre os webhooks

Os campos `wh_status`, `wh_qrcode`, `wh_connect`, `wh_message` do `start()`
apontam pra URLs públicas que a API Brasil vai chamar (ex:
`https://seudominio.com/api/webhook/qrcode.php`). **Isso não funciona com
`localhost`** — para testar webhooks em desenvolvimento, exponha o
`localhost:8000` com um túnel (ex: `ngrok http 8000`) e use a URL gerada
nesses campos.

Sem webhook público configurado, o painel ainda funciona: o QR Code deve
vir diretamente na resposta do `start()` (assim que confirmarmos o campo),
e o botão de conectar cobre o caso de uso principal.

## Segurança

- Tokens só existem no backend PHP (`.env` → `config.php`), nunca expostos
  no HTML/JS enviado ao navegador.
- Se o Bearer Token vazar publicamente em algum momento (ex: colado em
  chat, repositório), regenere-o no painel da API Brasil.
