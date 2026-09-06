# Segurança

Estado da lista de auditoria do projeto.

| Item | Como é tratado |
|---|---|
| **SSRF** | `app/core/security.py`: host por allowlist exata (`instagram.com`, `www.instagram.com`, `m.instagram.com`); IP literal recusado; DNS resolvido e todos os IPs precisam ser públicos (bloqueia loopback, RFC 1918, `169.254/16` incl. metadata cloud, CGNAT, reservado, multicast, IPv4-mapeado). Só `https`. |
| **Path traversal** | `sanitize_filename()` (allowlist de caracteres) + `safe_join()` (resultado precisa ficar dentro de `storage/temp`). Cada operação usa um diretório `uuid4`. Parâmetros do usuário nunca compõem caminho. |
| **Command injection** | FFmpeg só via `subprocess.run([...])` com lista de argumentos, sem `shell=True`. yt-dlp é chamado pela API Python, não por linha de comando. |
| **XSS** | API devolve JSON. O front usa `textContent` (nunca `innerHTML`) para dados vindos do backend. CSP `script-src 'self'`. |
| **CSRF** | Endpoints são JSON sem cookies/sessão — não há estado de autenticação para forjar. `Content-Type: application/json` exigido pelo schema. |
| **Rate limiting** | `slowapi` por IP: `/api/analyze` 20/min, `/api/download` 5/min (configurável; `RATE_LIMIT_ENABLED`). Excesso → `429`. |
| **DoS (corpo grande)** | Middleware recusa `Content-Length` > `MAX_REQUEST_BYTES` (32 KB) em `/api/*`. URL limitada a 2048 chars no schema. |
| **DoS (mídia grande)** | `MAX_MEDIA_SIZE_MB` passado ao yt-dlp (`max_filesize`) + verificação do tamanho final. `http_download` aborta em streaming. |
| **DoS (disco)** | `storage/temp` varrido no boot e a cada `CLEANUP_INTERVAL`; teto `MAX_TEMP_TOTAL_MB`. Job dir apagado logo após o envio. Jobs de download em memória expiram (TTL) e são varridos junto. |
| **DoS (SSE)** | O stream de progresso tem prazo máximo (`_EVENT_TIMEOUT`, 15 min) e encerra ao fim do job; `POST /api/download` continua sob rate limit. `GET .../events` e `.../file` operam sobre um job id opaco. |
| **Timeouts** | `REQUEST_TIMEOUT` em yt-dlp (`socket_timeout`) e `httpx`; FFmpeg com `timeout` no `subprocess`. |
| **Headers** | `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer`, `X-Frame-Options: DENY`, `Cross-Origin-Opener-Policy: same-origin`, CSP restrita (exceto `/docs`). |
| **CORS** | Desligado por padrão (mesma origem). `CORS_ORIGINS` habilita origens explícitas. |
| **Host header** | `ALLOWED_HOSTS` ativa `TrustedHostMiddleware` em produção. |
| **Vazamento de erro** | Handlers centrais: nenhuma resposta expõe stack trace; `stderr` do FFmpeg e mensagens do yt-dlp ficam só no log. |
| **Logs** | Sem cookies, tokens ou URLs sensíveis; apenas plataforma, operação, `reason` curto e tempos. |
| **Segredos** | `.env` (git-ignored). `.gitignore` cobre `cookies.txt`, `*.cookies`, `storage/temp`. Nada de segredo no JavaScript. |

## Pendências conhecidas

- Guarda de corpo por `Content-Length` não cobre requisições `chunked` sem esse header (mitigado pelos limites de schema).
- Rate limit é em memória (por processo). Com múltiplos workers, usar storage compartilhado (Redis) — ver `slowapi` `storage_uri`.
- Acesso ao Instagram sem cookies é bloqueado pela própria plataforma; uso legítimo depende de `INSTAGRAM_COOKIES_FILE` da conta do próprio operador.
