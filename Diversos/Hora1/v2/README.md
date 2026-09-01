# Hora Certa

Página estática com design tipográfico minimalista (editorial) que mostra:

- **hora certa** (formato 12/24 h, segundos opcionais) e **data por extenso**,
  localizadas via `Intl`;
- **saudação** conforme o período do dia (madrugada / manhã / tarde / noite);
- **barra de progresso do dia** (0h → 24h);
- **localização** (GPS do dispositivo, com _fallback_ para IP) e **clima atual**:
  condição com glifo, sensação térmica, mínima/máxima e nascer/pôr do sol;
- **fuso horário** (IANA + GMT) do navegador.

Tem tema claro/escuro (persistido, segue `prefers-color-scheme`, sem _flash_),
funciona **offline** (service worker), é **instalável** (PWA) e pede
**consentimento** antes de carregar anúncios/métricas (LGPD).

## Design

- **Tipografia:** [Fraunces](https://fonts.google.com/specimen/Fraunces) (serifada) +
  [IBM Plex Mono](https://fonts.google.com/specimen/IBM+Plex+Mono) (números e rótulos),
  carregadas sem bloquear a renderização.
- **Cores:** paleta de dois tons (papel / tinta) + um acento, em _custom properties_.
- **Movimento:** entrada em cascata; tudo some sob `prefers-reduced-motion: reduce`.
- Suporta `prefers-contrast: more` e tem folha de estilo para impressão.

## Estrutura

```
v2/
├── index.html                 # página principal
├── privacidade.html           # política de privacidade (exigida pelo AdSense)
├── manifest.webmanifest       # metadados do PWA
├── sw.js                      # service worker (offline)
├── robots.txt / sitemap.xml
├── assets/
│   ├── favicon.svg
│   ├── og-image.svg           # fonte para gerar o og-image.png
│   ├── css/style.css
│   └── js/
│       ├── main.js            # ponto de entrada (liga os módulos)
│       ├── format.js          # funções puras (testadas)
│       ├── i18n.js            # textos de interface
│       ├── settings.js        # preferências do usuário
│       ├── theme.js           # alternância de tema
│       ├── clock.js           # relógio, saudação, data, progresso
│       ├── weather.js         # localização + clima (cache, timeout, refresh)
│       └── consent.js         # banner de consentimento + carga do Google
├── worker/                    # Cloudflare Worker que esconde as chaves de API
│   ├── hora-proxy.js
│   └── wrangler.toml
├── test/format.test.js        # Vitest
└── .github/workflows/ci.yml   # lint + testes + Lighthouse
```

## Como rodar

Site estático. Servir por HTTP local (necessário para módulos ES e service worker):

```bash
npm start          # usa "serve" na porta padrão
# ou: python -m http.server 8000
```

## Scripts

| Comando               | O que faz                                         |
| --------------------- | ------------------------------------------------- |
| `npm start`           | Servidor estático local                          |
| `npm run lint`        | ESLint + Stylelint + HTMLHint                    |
| `npm test`            | Vitest (funções puras de `format.js`)            |
| `npm run format`      | Prettier                                         |
| `npm run build:icons` | Gera `icon-192/512.png` e `og-image.png` dos SVG |

## APIs externas

| Serviço          | Uso                                | Observação                                  |
| ---------------- | ---------------------------------- | ------------------------------------------ |
| ipgeolocation.io | Cidade/país e lat/lon por IP       | Chave no cliente (ou via `worker/`)        |
| OpenWeatherMap   | Clima atual (`/data/2.5/weather`)  | Chave no cliente (ou via `worker/`)        |
| Google Fonts     | Fraunces + IBM Plex Mono           | Envia o IP do visitante ao Google          |
| Google AdSense   | Anúncios                          | Só carrega após consentimento             |
| Google Analytics | Métricas (`G-E7ZNTJSRYR`)          | Só carrega após consentimento, IP anônimo |

### Esconder as chaves (recomendado)

1. `cd worker && npx wrangler secret put OPENWEATHER_KEY` e `... IPGEO_KEY`;
2. `npx wrangler deploy`;
3. em `assets/js/weather.js`, preencha `PROXY_BASE` com a URL do Worker;
4. **rotacione** as chaves antigas — elas já foram versionadas.

Enquanto o proxy não existir, restrinja cada chave por _HTTP referrer_ / domínio
no painel do provedor.

## Pendências

- **Gerar os PNGs**: `npm run build:icons` cria `assets/icon-192.png`,
  `assets/icon-512.png` (instalação do PWA no Chrome/Android) e
  `assets/og-image.png` (prévia em redes sociais — o `.svg` não serve para isso).
  Publique o `og-image.png` no caminho referenciado nas metatags.
- **IDs do AdSense divergentes**: `<meta name="google-adsense-account">` usa
  `ca-pub-6176119186804571` e o `adsbygoogle.js` (em `consent.js`) usa
  `ca-pub-8542251167876044`. Confirme o correto e unifique.
- **CSP**: a `Content-Security-Policy` em `index.html` é permissiva o bastante para
  o AdSense atual; revise ao adicionar novos formatos de anúncio.
- **Auto-hospedar as fontes** (`@font-face` + `.woff2` locais) elimina o envio de
  IP ao Google e a dependência externa.
- **Consentimento**: hoje, quem recusa/ignora não vê anúncios. Para cobertura
  maior, avalie o Google Consent Mode v2.
- Se `v2/` for a versão definitiva, promover para a raiz do repositório.
