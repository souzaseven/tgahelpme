/**
 * server.js
 * Servidor Express que:
 *  1) serve os arquivos estáticos do site (index.html, css/, js/);
 *  2) expõe um proxy /api/cnpj/:cnpj para a BrasilAPI.
 *
 * Por quê: consultar https://brasilapi.com.br diretamente do navegador faz
 * cada visitante bater na API pública com o próprio IP. Quando a BrasilAPI
 * responde 429 (limite excedido), a resposta de erro não traz o header
 * Access-Control-Allow-Origin — o navegador então bloqueia a leitura por
 * CORS, mascarando o problema real (rate limit) como se fosse falha de CORS.
 *
 * Com o proxy, o navegador só fala com a própria origem (mesmo domínio),
 * então CORS deixa de existir para essa chamada. O cache em memória abaixo
 * também reduz o número de idas à BrasilAPI quando vários visitantes
 * consultam o mesmo CNPJ em um curto intervalo, diminuindo a chance de
 * atingir o limite de requisições.
 *
 * Documentação oficial usada como base (nenhum endpoint inventado):
 *   GET https://brasilapi.com.br/api/cnpj/v1/{cnpj}
 */

const express = require('express');
const path = require('path');

const app = express();

const PORT = process.env.PORT || 3000;
const BRASILAPI_BASE_URL = 'https://brasilapi.com.br/api/cnpj/v1';
const REQUEST_TIMEOUT_MS = 15000;
const CACHE_TTL_MS = 10 * 60 * 1000; // 10 minutos — mesmo TTL do cache local do frontend
const CACHE_MAX_ENTRIES = 500;

// Serve o próprio site estático (index.html, css/, js/) pela mesma origem,
// assim o frontend chama "/api/cnpj/..." sem precisar de CORS.
app.use(express.static(path.join(__dirname)));

// ---------- CACHE EM MEMÓRIA (reduz chamadas repetidas à BrasilAPI) ----------

const cache = new Map(); // cnpj -> { data, expiresAt }

function getCached(cnpj) {
  const entry = cache.get(cnpj);
  if (!entry) return null;
  if (Date.now() > entry.expiresAt) {
    cache.delete(cnpj);
    return null;
  }
  return entry.data;
}

function setCached(cnpj, data) {
  if (cache.size >= CACHE_MAX_ENTRIES) {
    const oldestKey = cache.keys().next().value;
    cache.delete(oldestKey);
  }
  cache.set(cnpj, { data, expiresAt: Date.now() + CACHE_TTL_MS });
}

// ---------- PROXY ----------

app.get('/api/cnpj/:cnpj', async (req, res) => {
  const cnpj = String(req.params.cnpj || '').replace(/\D/g, '');

  if (cnpj.length !== 14) {
    return res.status(400).json({ error: 'CNPJ inválido.' });
  }

  const cached = getCached(cnpj);
  if (cached) {
    return res.json(cached);
  }

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);

  try {
    const upstream = await fetch(`${BRASILAPI_BASE_URL}/${cnpj}`, {
      headers: {
        Accept: 'application/json',
        // Sem um User-Agent "de navegador", a Cloudflare na frente da
        // BrasilAPI responde 403 ao fetch nativo do Node (undici) — o
        // valor em si não importa, só precisa parecer tráfego de browser.
        'User-Agent': 'Mozilla/5.0 (compatible; ConsultaCNPJ-Proxy/1.0; +https://tgameajuda.com)',
      },
      signal: controller.signal,
    });

    if (upstream.status === 404) {
      return res.status(404).json({ error: 'Empresa não encontrada.' });
    }

    if (upstream.status === 400) {
      return res.status(400).json({ error: 'CNPJ inválido.' });
    }

    if (upstream.status === 429) {
      return res
        .status(429)
        .json({ error: 'Limite de consultas à BrasilAPI foi atingido. Tente novamente em instantes.' });
    }

    if (!upstream.ok) {
      return res.status(502).json({ error: 'A BrasilAPI está indisponível no momento.' });
    }

    const data = await upstream.json();
    setCached(cnpj, data);
    res.json(data);
  } catch (err) {
    if (err.name === 'AbortError') {
      return res.status(504).json({ error: 'A consulta à BrasilAPI demorou demais para responder.' });
    }
    console.error('Erro ao consultar a BrasilAPI:', err);
    res.status(502).json({ error: 'Não foi possível consultar o CNPJ neste momento.' });
  } finally {
    clearTimeout(timer);
  }
});

app.listen(PORT, () => {
  console.log(`Consulta CNPJ rodando em http://localhost:${PORT}`);
});
