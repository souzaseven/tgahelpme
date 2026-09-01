/* =========================================================================
   Meu IP — logica do painel
   -------------------------------------------------------------------------
   Regras:
   - Dados de IP publico / geolocalizacao vem de APIs publicas.
   - Dados de navegador/dispositivo vem do objeto `navigator`/`screen`.
   - Dados reais da maquina (hostname, usuario, interfaces) vem do backend
     Python em /api/system. Se ele nao responder, mostramos instrucoes —
     nunca inventamos os valores.
   ========================================================================= */
"use strict";

const INDEFINIDO = Symbol("indefinido");

/* ---- utilidades de DOM ------------------------------------------------- */
function el(tag, attrs = {}, filhos = []) {
  const node = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) {
    if (k === "class") node.className = v;
    else if (k === "text") node.textContent = v;
    else node.setAttribute(k, v);
  }
  for (const f of [].concat(filhos)) node.append(f);
  return node;
}

function toast(msg) {
  let t = document.querySelector(".toast");
  if (!t) {
    t = el("div", { class: "toast" });
    document.body.append(t);
  }
  t.textContent = msg;
  t.classList.add("visivel");
  clearTimeout(toast._id);
  toast._id = setTimeout(() => t.classList.remove("visivel"), 1600);
}

/* ---- Hints explicativos --------------------------------------------- */
/**
 * Texto de ajuda por rotulo/titulo. A chave e exatamente o texto exibido.
 * Se nao houver entrada, o rotulo simplesmente nao ganha o "?".
 */
const HINTS = {
  // ---- titulos de cards ----
  "Rede — IP público":
    "Informações obtidas de APIs públicas a partir do IP que a internet enxerga.",
  "Segurança da conexão":
    "Sinais heurísticos de que o tráfego pode estar passando por VPN, proxy ou datacenter.",
  "Navegador e dispositivo":
    "Tudo o que o próprio navegador revela via JavaScript, sem precisar de servidor.",
  "IP local (WebRTC)":
    "Tentativa de descobrir o IP da sua rede interna usando o mecanismo de WebRTC.",
  "Latência e velocidade":
    "Tempo de resposta a servidores públicos e um teste rápido de download.",
  "Máquina local — backend Python":
    "Dados reais do computador, disponíveis apenas com o servidor Python em execução.",

  // ---- IP público ----
  "IP público":
    "Endereço que a internet vê. É o do seu roteador/provedor e é compartilhado por todos os aparelhos da sua rede.",
  "IPv4":
    "Formato clássico de IP: quatro números de 0 a 255 (ex.: 189.4.22.10). Está se esgotando no mundo.",
  "IPv6":
    "Formato novo e muito maior (ex.: 2804:14d:...). Se aparecer \"não disponível\", seu provedor não te forneceu um.",
  "Tipo": "Indica se o IP mostrado é IPv4 ou IPv6.",
  "Cidade / região":
    "Localização estimada pelo banco de dados do IP. Costuma acertar a cidade/região, nunca o endereço exato.",
  "País": "País associado ao IP, com o código de duas letras (ISO 3166).",
  "CEP aproximado":
    "Código postal estimado para a região do IP. Frequentemente impreciso ou vazio.",
  "Coordenadas":
    "Latitude e longitude aproximadas do centro da região do IP — não da sua casa.",
  "Provedor (ISP)":
    "Empresa que fornece o seu acesso à internet (ex.: Vivo, Claro, provedor regional).",
  "Organização":
    "Organização dona do bloco de IPs. Normalmente é a mesma empresa do provedor.",
  "ASN":
    "Autonomous System Number: identificador da rede do provedor na internet (ex.: AS28573). Cada operadora tem o seu.",
  "Fuso (IP)":
    "Fuso horário deduzido pela localização do IP (ex.: America/Sao_Paulo).",
  "UTC (IP)":
    "Diferença desse fuso em relação ao horário de Greenwich (ex.: -03:00).",

  // ---- Segurança da conexão ----
  "Reverse DNS (PTR)":
    "Nome de domínio ligado ao IP no sentido inverso. Provedores usam padrões como \"b1-10.dsl.telecom.net.br\".",
  "VPN suspeita":
    "Heurística indicando que o IP pertence a um serviço de VPN conhecido.",
  "Proxy suspeito":
    "Heurística indicando que o tráfego pode estar passando por um proxy público.",
  "Rede Tor":
    "Indica se o IP é um nó de saída da rede de anonimato Tor.",
  "Datacenter / hosting":
    "IP de servidor/nuvem (AWS, Google Cloud, etc.), não de conexão residencial. Comum em VPNs.",
  "IP em listas de abuso":
    "O IP aparece em listas públicas de spam, ataques ou comportamento abusivo.",
  "Bogon (IP inválido)":
    "IP de faixa reservada e não roteável na internet pública. Um \"sim\" indica erro na detecção.",
  "Cloudflare WARP":
    "VPN da Cloudflare. \"on\" significa que a Cloudflare detectou o WARP ativo na sua conexão agora.",
  "Operadora (ipapi.is)":
    "Nome da empresa dona do IP segundo o serviço ipapi.is (segunda fonte, para conferência).",
  "ASN (cross-check)":
    "Compara o ASN informado por duas fontes diferentes. \"(bate)\" = as duas concordam.",
  "País (trace CF)":
    "País que a rede da Cloudflare associa à sua conexão neste momento.",
  "Datacenter CF (colo)":
    "Código do data center da Cloudflare que te atendeu (ex.: GRU = Guarulhos/São Paulo).",

  // ---- Navegador e dispositivo ----
  "Navegador": "Nome e versão do navegador, deduzidos do texto \"user agent\".",
  "Sistema (via UA)":
    "Sistema operacional informado pelo navegador. Pode vir genérico por privacidade.",
  "Plataforma":
    "Valor de navigator.platform (ex.: Win32). Está sendo descontinuado pelos navegadores.",
  "Plataforma (UA-CH)":
    "Sistema e versão via User-Agent Client Hints, API mais nova e precisa (Chrome/Edge).",
  "Arquitetura (UA-CH)":
    "Arquitetura do processador (x86, arm) e se é 32 ou 64 bits, via Client Hints.",
  "Idioma": "Idioma principal configurado no navegador.",
  "Idiomas": "Lista de idiomas preferidos, em ordem de prioridade.",
  "Resolução da tela": "Tamanho total do monitor, em pixels.",
  "Área útil":
    "Área da tela descontando a barra de tarefas e docas do sistema.",
  "Janela": "Tamanho atual da área visível da aba (viewport).",
  "Densidade de pixels":
    "Quantos pixels físicos equivalem a 1 pixel de CSS. Telas \"retina\" têm 2 ou mais.",
  "Profundidade de cor":
    "Bits por pixel usados para cor (24 = cerca de 16 milhões de cores).",
  "Núcleos lógicos (CPU)":
    "Número de threads da CPU que o navegador expõe. Pode ser limitado por privacidade.",
  "Memória do dispositivo":
    "Estimativa grosseira da RAM em GB, arredondada pelo navegador.",
  "Toque (max points)":
    "Máximo de toques simultâneos na tela. 0 costuma indicar dispositivo sem touch.",
  "Fuso do navegador":
    "Fuso horário configurado no sistema operacional, lido pelo JavaScript.",
  "Cookies habilitados": "Se o site pode gravar cookies neste navegador.",
  "Do Not Track":
    "Preferência antiga de \"não me rastreie\". \"1\" = ativada; hoje é pouco respeitada.",
  "Conexão (efetiva)":
    "Estimativa da qualidade da conexão (slow-2g, 2g, 3g, 4g) feita pelo navegador.",
  "Downlink estimado":
    "Velocidade de download estimada pelo navegador, em Mbps. Bem aproximada.",
  "Status": "Se o navegador considera que há conexão com a internet.",
  "User agent":
    "Texto de identificação que o navegador envia em toda requisição HTTP.",

  // ---- Latência ----
  "Destino": "Servidor público usado como referência para medir o tempo de resposta.",
  "RTT mín.":
    "Menor tempo de ida e volta em 4 tentativas. Reflete melhor a latência real da rede.",
  "Mediana":
    "Valor central das 4 medições; menos sensível a picos isolados.",

  // ---- Máquina local (backend) ----
  "Nome do computador":
    "Nome da máquina na rede (NetBIOS), definido nas configurações do Windows.",
  "Hostname": "Nome de host do sistema. Costuma ser igual ao nome do computador.",
  "FQDN":
    "Nome completo com o domínio, quando a máquina faz parte de um domínio de rede.",
  "Usuário": "Conta do sistema operacional que está executando o servidor Python.",
  "Sistema": "Família do sistema operacional (Windows, Linux, Darwin).",
  "Descrição": "Resumo com versão e service pack, montado pelo Python.",
  "Release": "Versão \"comercial\" do sistema (ex.: 11 para o Windows 11).",
  "Versão": "Número de build detalhado do sistema operacional.",
  "Edição (Windows)":
    "Edição instalada (Home, Pro, CoreSingleLanguage, etc.).",
  "Build (Windows)": "Número de compilação do Windows (ex.: 10.0.26200).",
  "Python": "Versão do interpretador Python que está servindo a API.",
  "Ligado desde": "Data e hora do último boot da máquina.",
  "Uptime": "Há quanto tempo a máquina está ligada sem reiniciar.",
  "Arquitetura": "Arquitetura da CPU informada pelo SO (ex.: AMD64 = x86-64).",
  "Bits do processo": "Se o processo Python roda em 32 ou 64 bits.",
  "Bits do Python": "Arquitetura do binário do Python instalado.",
  "Processador": "Identificação da CPU informada pelo sistema.",
  "Núcleos lógicos":
    "Total de threads (núcleos físicos multiplicados pelo hyper-threading).",
  "Núcleos físicos": "Núcleos reais da CPU, sem contar hyper-threading.",
  "Memória total": "RAM física instalada na máquina.",
  "Memória disponível": "RAM livre para novos programas neste momento.",
  "Uso de memória": "Percentual da RAM em uso.",
  "IP local":
    "Endereço da sua máquina na rede interna, usado para falar com o roteador.",
  "Gateway padrão":
    "IP do roteador: o caminho por onde todo o tráfego sai para a internet.",
  "Servidores DNS":
    "Servidores que traduzem nomes de site (ex.: google.com) em endereços IP.",
  "Interfaces de rede":
    "Cada placa de rede (Wi-Fi, Ethernet, virtuais) e os endereços atribuídos a ela.",
};

function criarHint(chave) {
  const txt = HINTS[chave];
  if (!txt) return null;
  const s = el("span", { class: "hint", tabindex: "0", role: "note", "data-hint": txt });
  s.setAttribute("aria-label", "Ajuda: " + txt);
  s.textContent = "?";
  return s;
}

/** <dt> de rotulo, ja com o "?" de ajuda quando houver hint cadastrado. */
function criarRotulo(texto, classe = "lista__rotulo") {
  const dt = el("dt", { class: classe, text: texto });
  const h = criarHint(texto);
  if (h) dt.append(h);
  return dt;
}

/** Adiciona o "?" aos titulos <h2> dos cards (feito uma vez no carregamento). */
function hintarCabecalhos() {
  for (const h2 of document.querySelectorAll(".card__cabecalho h2")) {
    if (h2.querySelector(".hint")) continue;
    const h = criarHint(h2.textContent.trim());
    if (h) h2.append(h);
  }
}

/**
 * Renderiza pares rotulo/valor dentro de um <dl>.
 * @param {HTMLElement} alvo
 * @param {Array<[string, any, {classe?: string}?]>} pares
 */
function renderLista(alvo, pares) {
  alvo.replaceChildren();
  for (const [rotulo, valor, meta] of pares) {
    const definido = valor !== INDEFINIDO && valor !== undefined && valor !== null && valor !== "";
    const extra = definido && meta && meta.classe ? " " + meta.classe : "";
    const vNode = el("dd", {
      class: "lista__valor" + (definido ? "" : " lista__valor--indef") + extra,
      text: definido ? String(valor) : "não disponível",
    });
    if (definido) {
      vNode.title = "clique para copiar";
      vNode.addEventListener("click", () => {
        navigator.clipboard?.writeText(String(valor)).then(
          () => toast("copiado: " + rotulo),
          () => toast("não foi possível copiar")
        );
      });
    }
    alvo.append(
      el("div", { class: "lista__linha" }, [criarRotulo(rotulo), vNode])
    );
  }
}

function erroLista(alvo, msg) {
  alvo.replaceChildren(el("div", { class: "lista__vazio lista__erro", text: msg }));
}

/* ---- fetch com timeout ---------------------------------------------- */
async function pegarJSON(url, { timeout = 8000, ...opts } = {}) {
  const ctrl = new AbortController();
  const id = setTimeout(() => ctrl.abort(), timeout);
  try {
    const r = await fetch(url, { signal: ctrl.signal, ...opts });
    if (!r.ok) throw new Error("HTTP " + r.status + " em " + url);
    return await r.json();
  } finally {
    clearTimeout(id);
  }
}

async function pegarTexto(url, { timeout = 6000, ...opts } = {}) {
  const ctrl = new AbortController();
  const id = setTimeout(() => ctrl.abort(), timeout);
  try {
    const r = await fetch(url, { signal: ctrl.signal, ...opts });
    if (!r.ok) throw new Error("HTTP " + r.status + " em " + url);
    return await r.text();
  } finally {
    clearTimeout(id);
  }
}

/* =====================================================================
   1. IP PUBLICO + GEOLOCALIZACAO
   ===================================================================== */
async function carregarIPPublico() {
  const alvo = document.getElementById("lista-publico");

  const [geoRes, v4Res, v6Res] = await Promise.allSettled([
    pegarJSON("https://ipwho.is/"),
    pegarJSON("https://api.ipify.org?format=json", { timeout: 6000 }),
    pegarJSON("https://api64.ipify.org?format=json", { timeout: 6000 }),
  ]);

  let geo = geoRes.status === "fulfilled" ? geoRes.value : null;
  if (geo && geo.success === false) geo = null;

  // fallback de geolocalizacao
  if (!geo) {
    try {
      const alt = await pegarJSON("https://ipapi.co/json/");
      geo = {
        ip: alt.ip,
        type: alt.version,
        city: alt.city,
        region: alt.region,
        country: alt.country_name,
        postal: alt.postal,
        latitude: alt.latitude,
        longitude: alt.longitude,
        timezone: { id: alt.timezone, utc: alt.utc_offset },
        connection: { isp: alt.org, org: alt.org, asn: alt.asn },
      };
    } catch { /* segue sem geo */ }
  }

  const v4 = v4Res.status === "fulfilled" ? v4Res.value.ip : INDEFINIDO;
  const v6raw = v6Res.status === "fulfilled" ? v6Res.value.ip : INDEFINIDO;
  const v6 = typeof v6raw === "string" && v6raw.includes(":") ? v6raw : INDEFINIDO;

  if (!geo && v4 === INDEFINIDO) {
    erroLista(alvo, "Não foi possível consultar as APIs públicas (offline ou bloqueadas).");
    return null;
  }

  const g = geo || {};
  const conn = g.connection || {};
  const tz = g.timezone || {};
  const cidade = [g.city, g.region].filter(Boolean).join(" / ");

  renderLista(alvo, [
    ["IP público", g.ip ?? (v4 !== INDEFINIDO ? v4 : INDEFINIDO)],
    ["IPv4", v4],
    ["IPv6", v6],
    ["Tipo", g.type ?? INDEFINIDO],
    ["Cidade / região", cidade || INDEFINIDO],
    ["País", [g.country, g.country_code].filter(Boolean).join(" ") || INDEFINIDO],
    ["CEP aproximado", g.postal ?? INDEFINIDO],
    ["Coordenadas", g.latitude != null ? `${g.latitude}, ${g.longitude}` : INDEFINIDO],
    ["Provedor (ISP)", conn.isp ?? INDEFINIDO],
    ["Organização", conn.org ?? INDEFINIDO],
    ["ASN", conn.asn ? "AS" + String(conn.asn).replace(/^AS/i, "") : INDEFINIDO],
    ["Fuso (IP)", tz.id ?? INDEFINIDO],
    ["UTC (IP)", tz.utc ?? INDEFINIDO],
  ]);

  const ipPublico = g.ip || (v4 !== INDEFINIDO ? v4 : null);
  checarMudancaIP(ipPublico);
  renderMapa(g.latitude, g.longitude);
  carregarSeguranca(ipPublico, g);
  return ipPublico;
}

/* =====================================================================
   1b. MUDANCA DE IP (localStorage) + MINI-MAPA
   ===================================================================== */
const CHAVE_ULTIMO_IP = "meuip:ultimo";

function checarMudancaIP(ipAtual) {
  if (!ipAtual) return;
  let anterior = null;
  try {
    anterior = JSON.parse(localStorage.getItem(CHAVE_ULTIMO_IP) || "null");
  } catch { /* storage indisponivel */ }

  if (anterior && anterior.ip && anterior.ip !== ipAtual) {
    const banner = document.getElementById("banner-ip");
    const quando = anterior.ts
      ? new Date(anterior.ts).toLocaleString("pt-BR")
      : "visita anterior";
    banner.replaceChildren(
      el("button", { type: "button", "aria-label": "fechar", text: "×" }),
      document.createTextNode("Seu IP público mudou desde "),
      el("span", { text: quando }),
      document.createTextNode(": era "),
      el("strong", { text: anterior.ip }),
      document.createTextNode(", agora é "),
      el("strong", { text: ipAtual }),
      document.createTextNode(".")
    );
    banner.querySelector("button").addEventListener("click", () => (banner.hidden = true));
    banner.hidden = false;
  }

  try {
    localStorage.setItem(
      CHAVE_ULTIMO_IP,
      JSON.stringify({ ip: ipAtual, ts: new Date().toISOString() })
    );
  } catch { /* ignora */ }
}

function renderMapa(lat, lon) {
  const box = document.getElementById("mapa");
  if (lat == null || lon == null || Number.isNaN(+lat) || Number.isNaN(+lon)) {
    box.hidden = true;
    return;
  }
  const d = 0.05;
  const bbox = [+lon - d, +lat - d, +lon + d, +lat + d].join(",");
  box.replaceChildren(
    el("iframe", {
      loading: "lazy",
      referrerpolicy: "no-referrer",
      title: "Localização aproximada por IP",
      src:
        "https://www.openstreetmap.org/export/embed.html?bbox=" +
        encodeURIComponent(bbox) +
        "&layer=mapnik&marker=" +
        encodeURIComponent(lat + "," + lon),
    }),
    el("a", {
      class: "mapa__link",
      target: "_blank",
      rel: "noreferrer",
      href: `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lon}#map=11/${lat}/${lon}`,
      text: "abrir mapa maior ↗",
    })
  );
  box.hidden = false;
}

/* =====================================================================
   1c. SEGURANCA DA CONEXAO — VPN / proxy / hosting / rDNS
   ===================================================================== */
function ptrQueryName(ip) {
  if (typeof ip !== "string") return null;
  if (/^\d{1,3}(\.\d{1,3}){3}$/.test(ip)) {
    return ip.split(".").reverse().join(".") + ".in-addr.arpa";
  }
  return null; // IPv6 nao consultado aqui
}

function parseTrace(txt) {
  const obj = {};
  for (const linha of txt.trim().split("\n")) {
    const i = linha.indexOf("=");
    if (i > 0) obj[linha.slice(0, i)] = linha.slice(i + 1);
  }
  return obj;
}

async function carregarSeguranca(ip, geo) {
  const alvo = document.getElementById("lista-seguranca");
  const ptrName = ptrQueryName(ip);

  const [ipapiRes, ptrRes, traceRes] = await Promise.allSettled([
    pegarJSON("https://api.ipapi.is/", { timeout: 7000 }),
    ptrName
      ? pegarJSON(
          "https://dns.google/resolve?name=" + ptrName + "&type=PTR",
          { timeout: 6000 }
        )
      : Promise.reject(new Error("sem IPv4")),
    pegarTexto("https://cloudflare.com/cdn-cgi/trace", { timeout: 6000 }),
  ]);

  const s = ipapiRes.status === "fulfilled" ? ipapiRes.value : {};
  const trace = traceRes.status === "fulfilled" ? parseTrace(traceRes.value) : {};

  let ptr = INDEFINIDO;
  if (ptrRes.status === "fulfilled") {
    const ans = (ptrRes.value.Answer || []).find((a) => a.type === 12);
    ptr = ans ? ans.data.replace(/\.$/, "") : "sem registro PTR";
  } else if (!ptrName) {
    ptr = "não consultado (IPv6)";
  }

  const alerta = { classe: "lista__valor--alerta" };
  const ok = { classe: "lista__valor--ok" };
  const flag = (v) =>
    v === true ? ["sim", alerta] : v === false ? ["não", ok] : [INDEFINIDO];

  const asnCross =
    s.asn_num && geo && geo.connection && geo.connection.asn
      ? "AS" + s.asn_num + (String(geo.connection.asn) === String(s.asn_num) ? " (bate)" : " (≠ ipwho.is)")
      : s.asn_num
      ? "AS" + s.asn_num
      : INDEFINIDO;

  const warpTxt =
    trace.warp === "on"
      ? "on (Cloudflare WARP ativo)"
      : trace.warp === "plus"
      ? "plus"
      : trace.warp || INDEFINIDO;

  renderLista(alvo, [
    ["Reverse DNS (PTR)", ptr],
    ["VPN suspeita", ...flag(s.is_vpn)],
    ["Proxy suspeito", ...flag(s.is_proxy)],
    ["Rede Tor", ...flag(s.is_tor)],
    ["Datacenter / hosting", ...flag(s.is_datacenter)],
    ["IP em listas de abuso", ...flag(s.is_abuser)],
    ["Bogon (IP inválido)", ...flag(s.is_bogon)],
    ["Cloudflare WARP", warpTxt, trace.warp === "on" ? alerta : undefined],
    ["Operadora (ipapi.is)", s.company_name ?? s.asn_org ?? INDEFINIDO],
    ["ASN (cross-check)", asnCross],
    ["País (trace CF)", trace.loc ?? INDEFINIDO],
    ["Datacenter CF (colo)", trace.colo ?? INDEFINIDO],
  ]);
}

/* =====================================================================
   2. NAVEGADOR + DISPOSITIVO
   ===================================================================== */
function detectarOS(ua) {
  if (/Windows NT 10/.test(ua)) return "Windows 10/11";
  if (/Windows NT 6\.3/.test(ua)) return "Windows 8.1";
  if (/Windows/.test(ua)) return "Windows";
  if (/Android/.test(ua)) return (ua.match(/Android [\d.]+/) || ["Android"])[0];
  if (/iPhone|iPad|iPod/.test(ua)) return "iOS";
  if (/Mac OS X/.test(ua)) return "macOS";
  if (/Linux/.test(ua)) return "Linux";
  return "desconhecido";
}

function detectarNavegador(ua) {
  const m =
    ua.match(/(Edg|OPR|Firefox|Chrome|Safari)\/([\d.]+)/) || [];
  const nomes = { Edg: "Edge", OPR: "Opera", Chrome: "Chrome", Firefox: "Firefox", Safari: "Safari" };
  if (/Edg\//.test(ua)) return "Edge " + (ua.match(/Edg\/([\d.]+)/) || [, "?"])[1];
  if (/OPR\//.test(ua)) return "Opera " + (ua.match(/OPR\/([\d.]+)/) || [, "?"])[1];
  if (/Firefox\//.test(ua)) return "Firefox " + (ua.match(/Firefox\/([\d.]+)/) || [, "?"])[1];
  if (/Chrome\//.test(ua) && !/Chromium/.test(ua)) return "Chrome " + (ua.match(/Chrome\/([\d.]+)/) || [, "?"])[1];
  if (/Safari\//.test(ua)) return "Safari " + (ua.match(/Version\/([\d.]+)/) || [, "?"])[1];
  return nomes[m[1]] ? nomes[m[1]] + " " + m[2] : "desconhecido";
}

async function carregarNavegador() {
  const alvo = document.getElementById("lista-navegador");
  const ua = navigator.userAgent;
  const s = window.screen || {};
  const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;

  let plataformaUAData = INDEFINIDO;
  let arquiteturaUAData = INDEFINIDO;
  if (navigator.userAgentData?.getHighEntropyValues) {
    try {
      const he = await navigator.userAgentData.getHighEntropyValues([
        "platformVersion", "architecture", "bitness", "model",
      ]);
      plataformaUAData = [navigator.userAgentData.platform, he.platformVersion]
        .filter(Boolean).join(" ");
      arquiteturaUAData = [he.architecture, he.bitness ? he.bitness + " bits" : ""]
        .filter(Boolean).join(" ");
    } catch { /* ignora */ }
  }

  renderLista(alvo, [
    ["Navegador", detectarNavegador(ua)],
    ["Sistema (via UA)", detectarOS(ua)],
    ["Plataforma", navigator.platform || INDEFINIDO],
    ["Plataforma (UA-CH)", plataformaUAData],
    ["Arquitetura (UA-CH)", arquiteturaUAData],
    ["Idioma", navigator.language || INDEFINIDO],
    ["Idiomas", (navigator.languages || []).join(", ") || INDEFINIDO],
    ["Resolução da tela", s.width ? `${s.width} × ${s.height}` : INDEFINIDO],
    ["Área útil", s.availWidth ? `${s.availWidth} × ${s.availHeight}` : INDEFINIDO],
    ["Janela", `${window.innerWidth} × ${window.innerHeight}`],
    ["Densidade de pixels", window.devicePixelRatio || INDEFINIDO],
    ["Profundidade de cor", s.colorDepth ? s.colorDepth + " bits" : INDEFINIDO],
    ["Núcleos lógicos (CPU)", navigator.hardwareConcurrency ?? INDEFINIDO],
    ["Memória do dispositivo", navigator.deviceMemory ? navigator.deviceMemory + " GB (aprox.)" : INDEFINIDO],
    ["Toque (max points)", navigator.maxTouchPoints ?? INDEFINIDO],
    ["Fuso do navegador", tz || INDEFINIDO],
    ["Cookies habilitados", String(navigator.cookieEnabled)],
    ["Do Not Track", navigator.doNotTrack ?? INDEFINIDO],
    ["Conexão (efetiva)", navigator.connection?.effectiveType ?? INDEFINIDO],
    ["Downlink estimado", navigator.connection?.downlink != null ? navigator.connection.downlink + " Mbps" : INDEFINIDO],
    ["Status", navigator.onLine ? "online" : "offline"],
    ["User agent", ua],
  ]);
}

/* =====================================================================
   3. IP LOCAL via WebRTC (best-effort)
   ===================================================================== */
function coletarIPsWebRTC(timeout = 2000) {
  return new Promise((resolve) => {
    const ips = new Set();
    let pc;
    try {
      pc = new RTCPeerConnection({ iceServers: [] });
    } catch {
      return resolve([]);
    }
    const finalizar = () => {
      clearTimeout(timer);
      try { pc.close(); } catch { /* nada */ }
      resolve([...ips]);
    };
    const timer = setTimeout(finalizar, timeout);
    pc.createDataChannel("x");
    pc.onicecandidate = (e) => {
      if (!e.candidate) return finalizar();
      const txt = e.candidate.candidate;
      const m = txt.match(
        /([0-9]{1,3}(?:\.[0-9]{1,3}){3}|[a-f0-9]{1,4}(?::[a-f0-9]{0,4}){2,7}|[0-9a-z-]+\.local)/i
      );
      if (m) ips.add(m[1]);
    };
    pc.createOffer().then((o) => pc.setLocalDescription(o)).catch(finalizar);
  });
}

async function carregarWebRTC() {
  const alvo = document.getElementById("lista-webrtc");
  const ips = await coletarIPsWebRTC();
  if (!ips.length) {
    erroLista(alvo, "Nenhum candidato ICE exposto (bloqueado pelo navegador ou sem rede).");
    return;
  }
  const pares = ips.map((ip, i) => {
    const rotulo = ip.endsWith(".local")
      ? `Candidato ${i + 1} (mDNS)`
      : ip.includes(":")
      ? `Candidato ${i + 1} (IPv6)`
      : `Candidato ${i + 1} (IPv4)`;
    return [rotulo, ip];
  });
  renderLista(alvo, pares);
}

/* =====================================================================
   4. BACKEND PYTHON — /api/system
   ===================================================================== */
const BASES_BACKEND = [
  "", // mesma origem (quando servido pelo Flask)
  "http://127.0.0.1:5000",
  "http://localhost:5000",
];

async function acharBackend() {
  for (const base of BASES_BACKEND) {
    try {
      const r = await pegarJSON(base + "/api/ping", { timeout: 1500 });
      if (r && r.ok) return base;
    } catch { /* tenta a proxima */ }
  }
  return null;
}

function linha(rotulo, valor) {
  const definido = valor !== undefined && valor !== null && valor !== "";
  return el("div", { class: "lista__linha" }, [
    criarRotulo(rotulo),
    el("dd", {
      class: "lista__valor" + (definido ? "" : " lista__valor--indef"),
      text: definido ? String(valor) : "não disponível",
    }),
  ]);
}

function subtitulo(texto) {
  const h3 = el("h3", { text: texto });
  const h = criarHint(texto);
  if (h) h3.append(h);
  return h3;
}

function bloco(titulo, linhas) {
  return el("div", { class: "sub" }, [
    subtitulo(titulo),
    el("dl", { class: "lista" }, linhas),
  ]);
}

function formatarUptime(seg) {
  if (seg == null) return null;
  const d = Math.floor(seg / 86400);
  const h = Math.floor((seg % 86400) / 3600);
  const m = Math.floor((seg % 3600) / 60);
  return `${d}d ${h}h ${m}min`;
}

function renderSistema(dados) {
  const alvo = document.getElementById("sistema-conteudo");
  const etiqueta = document.getElementById("etiqueta-backend");
  etiqueta.textContent = "conectado";
  etiqueta.classList.add("ativo");
  alvo.replaceChildren();

  const id = dados.identificacao || {};
  const so = dados.sistema_operacional || {};
  const hw = dados.hardware || {};
  const rede = dados.rede || {};
  const win = so.windows || {};

  const grade = el("div", { class: "subgrade" }, [
    bloco("Identificação", [
      linha("Nome do computador", id.nome_computador),
      linha("Hostname", id.hostname),
      linha("FQDN", id.fqdn),
      linha("Usuário", id.usuario),
    ]),
    bloco("Sistema operacional", [
      linha("Sistema", so.sistema),
      linha("Descrição", so.descricao),
      linha("Release", so.release),
      linha("Versão", so.versao),
      linha("Edição (Windows)", win.edicao),
      linha("Build (Windows)", win.build),
      linha("Python", so.python),
      linha("Ligado desde", so.ligado_desde ? new Date(so.ligado_desde).toLocaleString("pt-BR") : null),
      linha("Uptime", formatarUptime(so.uptime_segundos)),
    ]),
    bloco("Hardware", [
      linha("Arquitetura", hw.arquitetura),
      linha("Bits do processo", hw.bits_processo),
      linha("Bits do Python", hw.bits_python),
      linha("Processador", hw.processador),
      linha("Núcleos lógicos", hw.nucleos_logicos),
      linha("Núcleos físicos", hw.nucleos_fisicos),
      linha("Memória total", hw.memoria_total_gb != null ? hw.memoria_total_gb + " GB" : null),
      linha("Memória disponível", hw.memoria_disponivel_gb != null ? hw.memoria_disponivel_gb + " GB" : null),
      linha("Uso de memória", hw.memoria_uso_percent != null ? hw.memoria_uso_percent + " %" : null),
    ]),
    bloco("Rede local", [
      linha("IP local", rede.ip_local),
      linha("Gateway padrão", rede.gateway),
      linha("Servidores DNS", (rede.dns || []).join(", ")),
    ]),
  ]);
  alvo.append(grade);

  // tabela de interfaces
  const ifaces = rede.interfaces || [];
  if (ifaces.length) {
    const linhas = [];
    for (const it of ifaces) {
      for (const [i, addr] of (it.enderecos || []).entries()) {
        linhas.push(
          el("tr", { class: it.ativa ? "" : "inativa" }, [
            el("td", { text: i === 0 ? it.nome : "" }),
            el("td", { text: i === 0 ? (it.ativa ? "ativa" : "inativa") : "" }),
            el("td", { text: addr.tipo }),
            el("td", { text: addr.endereco }),
            el("td", { text: addr.mascara || "" }),
          ])
        );
      }
    }
    const tabela = el("table", { class: "tabela-if" }, [
      el("thead", {}, el("tr", {}, [
        el("th", { text: "Interface" }),
        el("th", { text: "Estado" }),
        el("th", { text: "Tipo" }),
        el("th", { text: "Endereço" }),
        el("th", { text: "Máscara" }),
      ])),
      el("tbody", {}, linhas),
    ]);
    alvo.append(
      subtitulo("Interfaces de rede"),
      el("div", { class: "wrap-tabela" }, tabela)
    );
  }

  if (dados.aviso_psutil) {
    alvo.append(el("p", { class: "card__nota", text: "⚠ " + dados.aviso_psutil }));
  }
  alvo.append(
    el("p", { class: "card__nota", text: "Coletado em " + new Date(dados.coletado_em).toLocaleString("pt-BR") })
  );
}

function renderSistemaAusente() {
  const alvo = document.getElementById("sistema-conteudo");
  const etiqueta = document.getElementById("etiqueta-backend");
  etiqueta.textContent = "offline";
  etiqueta.classList.remove("ativo");

  alvo.replaceChildren(
    el("div", { class: "instrucoes" }, [
      el("p", {}, [
        document.createTextNode("O backend Python não respondeu. Sem ele, o navegador "),
        el("strong", { text: "não" }),
        document.createTextNode(" expõe nome de máquina, usuário do Windows nem interfaces — isso é proteção do navegador, não uma falha."),
      ]),
      el("p", { text: "Para ver os dados reais da máquina, rode na pasta do projeto:" }),
      el("pre", {}, el("code", {
        text:
          "pip install -r requirements.txt\n" +
          "python app.py\n\n" +
          "# depois abra  http://127.0.0.1:5000",
      })),
    ])
  );
}

async function carregarSistema() {
  const base = await acharBackend();
  if (base === null) return renderSistemaAusente();
  try {
    const dados = await pegarJSON(base + "/api/system", { timeout: 10000 });
    if (dados.erro) throw new Error(dados.erro);
    renderSistema(dados);
  } catch (e) {
    const alvo = document.getElementById("sistema-conteudo");
    alvo.replaceChildren(el("div", { class: "lista__vazio lista__erro", text: "Backend encontrado, mas /api/system falhou: " + e.message }));
  }
}

/* =====================================================================
   5. LATENCIA (RTT) + TESTE DE DOWNLOAD
   ===================================================================== */
const ALVOS_LATENCIA = [
  { nome: "Cloudflare", url: "https://cloudflare.com/cdn-cgi/trace" },
  { nome: "Google", url: "https://www.google.com/generate_204" },
  { nome: "GitHub API", url: "https://api.github.com/meta" },
  { nome: "Wikipedia", url: "https://pt.wikipedia.org/static/favicon/wikipedia.ico" },
];

async function medirRTT(url, amostras = 4) {
  const tempos = [];
  for (let i = 0; i < amostras; i++) {
    const ini = performance.now();
    try {
      await fetch(url, { mode: "no-cors", cache: "no-store" });
    } catch {
      return null;
    }
    tempos.push(performance.now() - ini);
  }
  tempos.sort((a, b) => a - b);
  return { min: tempos[0], mediana: tempos[Math.floor(tempos.length / 2)] };
}

async function carregarLatencia() {
  const alvo = document.getElementById("tabela-latencia");
  const medicoes = await Promise.all(
    ALVOS_LATENCIA.map(async (a) => ({ ...a, r: await medirRTT(a.url) }))
  );
  const maxMin = Math.max(
    60,
    ...medicoes.map((m) => (m.r ? m.r.min : 0))
  );

  const linhas = medicoes.map((m) => {
    if (!m.r) {
      return el("tr", {}, [
        el("td", { text: m.nome }),
        el("td", { text: "falhou" }),
        el("td", { text: "—" }),
        el("td", {}, el("div", { class: "barra barra--falha", style: "width:100%" })),
      ]);
    }
    return el("tr", {}, [
      el("td", { text: m.nome }),
      el("td", { text: m.r.min.toFixed(0) + " ms" }),
      el("td", { text: m.r.mediana.toFixed(0) + " ms" }),
      el("td", {}, el("div", {
        class: "barra",
        style: "width:" + Math.max(2, (m.r.min / maxMin) * 100).toFixed(0) + "%",
      })),
    ]);
  });

  const th = (texto) => {
    const cell = el("th", { text: texto });
    const h = criarHint(texto);
    if (h) cell.append(h);
    return cell;
  };
  const tabela = el("table", { class: "tabela-lat" }, [
    el("thead", {}, el("tr", {}, [
      th("Destino"),
      th("RTT mín."),
      th("Mediana"),
      el("th", { text: "" }),
    ])),
    el("tbody", {}, linhas),
  ]);
  alvo.replaceChildren(el("div", { class: "wrap-tabela" }, tabela));
}

async function testarDownload(orcamentoMs = 6000) {
  const url = "https://speed.cloudflare.com/__down?bytes=100000000";
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), orcamentoMs);
  const ini = performance.now();
  let recebido = 0;
  try {
    const r = await fetch(url, { signal: ctrl.signal, cache: "no-store" });
    const reader = r.body.getReader();
    for (;;) {
      const { done, value } = await reader.read();
      if (done) break;
      recebido += value.length;
    }
  } catch (e) {
    if (e.name !== "AbortError") throw e;
  } finally {
    clearTimeout(timer);
  }
  const seg = (performance.now() - ini) / 1000;
  if (recebido < 50000) return null;
  return { mbps: (recebido * 8) / seg / 1e6, mb: recebido / 1e6, seg };
}

async function aoTestarVelocidade() {
  const btn = document.getElementById("btnVelocidade");
  const saida = document.getElementById("resultado-velocidade");
  btn.disabled = true;
  saida.classList.remove("forte");
  saida.textContent = "baixando…";
  try {
    const res = await testarDownload();
    if (!res) {
      saida.textContent = "não foi possível medir (bloqueado ou offline)";
    } else {
      saida.textContent =
        res.mbps.toFixed(1) + " Mbit/s  ·  " +
        res.mb.toFixed(1) + " MB em " + res.seg.toFixed(1) + " s";
      saida.classList.add("forte");
    }
  } catch (e) {
    saida.textContent = "erro: " + e.message;
  } finally {
    btn.disabled = false;
  }
}

/* =====================================================================
   Status online/offline + orquestracao
   ===================================================================== */
function atualizarStatusRede() {
  const pill = document.getElementById("statusRede");
  const txt = document.getElementById("statusRedeTexto");
  const online = navigator.onLine;
  pill.classList.toggle("pill--online", online);
  pill.classList.toggle("pill--offline", !online);
  pill.classList.remove("pill--neutro");
  txt.textContent = online ? "online" : "offline";
}

async function carregarTudo() {
  const btn = document.getElementById("btnAtualizar");
  btn.disabled = true;
  atualizarStatusRede();
  await Promise.allSettled([
    carregarIPPublico(),
    carregarNavegador(),
    carregarWebRTC(),
    carregarLatencia(),
    carregarSistema(),
  ]);
  btn.disabled = false;
}

window.addEventListener("online", atualizarStatusRede);
window.addEventListener("offline", atualizarStatusRede);
document.getElementById("btnAtualizar").addEventListener("click", carregarTudo);
document.getElementById("btnVelocidade").addEventListener("click", aoTestarVelocidade);

hintarCabecalhos();
carregarTudo();
