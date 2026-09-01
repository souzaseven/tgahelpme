# Meu IP — Painel de rede e sistema

Painel web que mostra, de forma clara e **sem inventar dados**, informações de
rede e do computador. Funciona em dois modos:

| Modo | Como abrir | O que mostra |
|------|-----------|--------------|
| **Só navegador** | abrir `index.html` direto, ou hospedar o site | IP público, geolocalização aproximada, provedor/ASN, mini-mapa, detecção de VPN/proxy/hosting, reverse DNS, aviso de mudança de IP, navegador, tela, idioma, fuso, status online/offline, tentativa de IP local via WebRTC, latência (RTT) e teste de download |
| **Com backend Python** | `python app.py` e abrir `http://127.0.0.1:5000` | tudo acima **+** nome do computador, hostname, usuário, versão do Windows, arquitetura, IP local real, gateway, DNS e interfaces de rede |

Cada valor exibido carrega uma etiqueta de origem: **API pública**, **Navegador**
ou **Backend local**. Campos que o ambiente não fornece aparecem como
*"não disponível"* — nunca preenchidos com chute.

## Por que o modo navegador é limitado

Um site aberto pela internet, usando só HTML + CSS + JavaScript, **não tem
acesso** a nome real da máquina, usuário do Windows, lista de interfaces, serial
ou arquivos locais. Isso é uma proteção intencional do navegador. Para esses
dados é preciso um programa rodando na própria máquina — aqui, o backend Flask.

```
Windows
   ↓
Python (Flask)  ->  http://127.0.0.1:5000/api/system
   ↓
JavaScript (fetch)
   ↓
Dashboard
```

## Requisitos

- Python 3.9+ (usa `platform.win32_edition`, disponível desde o 3.8)
- Pip

## Como rodar (modo completo)

```bash
pip install -r requirements.txt
python app.py
```

Depois abra <http://127.0.0.1:5000>.

Variáveis de ambiente opcionais:

| Variável | Padrão | Efeito |
|----------|--------|--------|
| `MEU_IP_HOST` | `127.0.0.1` | interface de escuta |
| `MEU_IP_PORT` | `5000` | porta |
| `MEU_IP_DEBUG` | *(vazio)* | qualquer valor liga o modo debug do Flask |

> `psutil` é opcional. Sem ele o servidor continua funcionando, mas sem
> memória, uptime e a lista de interfaces.

## Estrutura

```
meu_ip/
├── app.py               # servidor Flask: estáticos + /api/system + /api/ping
├── requirements.txt
├── index.html           # dashboard
└── static/
    ├── css/style.css
    └── js/app.js
```

## Endpoints

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/` | serve o `index.html` |
| GET | `/api/ping` | checagem leve usada pelo frontend para detectar o backend |
| GET | `/api/system` | JSON com identificação, SO, hardware e rede da máquina |

CORS liberado apenas em `/api/*`, para permitir abrir o `index.html` como
arquivo e ainda assim consultar o backend em `127.0.0.1:5000`.

## APIs públicas usadas pelo frontend

- `ipwho.is` — IP, geolocalização, ISP, organização, ASN, fuso (primária)
- `ipapi.co` — mesma função, usada como fallback
- `api.ipify.org` / `api64.ipify.org` — IPv4 e IPv6
- `api.ipapi.is` — flags de VPN / proxy / Tor / datacenter / abuso (free tier, sem chave)
- `dns.google` — reverse DNS (PTR) do IP público via DNS-over-HTTPS
- `cloudflare.com/cdn-cgi/trace` — cross-check de país, datacenter (colo) e WARP
- `speed.cloudflare.com/__down` — bytes para o teste de download
- `cloudflare.com`, `google.com`, `api.github.com`, `pt.wikipedia.org` — alvos do teste de latência

Nenhuma exige chave. Se estiverem bloqueadas ou offline, o card mostra o aviso
correspondente. O aviso de "mudança de IP" usa apenas `localStorage` do próprio
navegador — nada é enviado a lugar nenhum.

## Privacidade

Todo o processamento é local ou direto entre o seu navegador e as APIs
públicas. Este projeto não guarda logs, não tem banco de dados e não envia seus
dados para nenhum servidor de terceiros além das APIs de IP listadas acima.
