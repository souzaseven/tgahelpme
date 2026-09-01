// Cloudflare Worker que esconde as chaves de API.
//
// Deploy:
//   cd worker
//   npx wrangler secret put OPENWEATHER_KEY
//   npx wrangler secret put IPGEO_KEY
//   npx wrangler deploy
//
// Depois, em assets/js/weather.js, defina PROXY_BASE com a URL do Worker
// (ex.: "https://hora-proxy.SEU-SUBDOMINIO.workers.dev").

const ALLOWED_ORIGINS = ['https://tgameajuda.com', 'http://localhost:3000', 'http://localhost:5000'];

function corsHeaders(origin) {
    const allow = ALLOWED_ORIGINS.includes(origin) ? origin : ALLOWED_ORIGINS[0];
    return {
        'Access-Control-Allow-Origin': allow,
        'Access-Control-Allow-Methods': 'GET, OPTIONS',
        'Cache-Control': 'public, max-age=300',
    };
}

async function proxyJson(apiUrl, headers) {
    const res = await fetch(apiUrl);
    const body = await res.text();
    return new Response(body, {
        status: res.status,
        headers: { ...headers, 'Content-Type': 'application/json; charset=utf-8' },
    });
}

export default {
    async fetch(request, env) {
        const url = new URL(request.url);
        const headers = corsHeaders(request.headers.get('Origin') || '');

        if (request.method === 'OPTIONS') {
            return new Response(null, { headers });
        }
        if (request.method !== 'GET') {
            return new Response('{"error":"método não suportado"}', { status: 405, headers });
        }

        try {
            if (url.pathname.endsWith('/geo')) {
                const ip = request.headers.get('CF-Connecting-IP') || '';
                return await proxyJson(
                    `https://api.ipgeolocation.io/ipgeo?apiKey=${env.IPGEO_KEY}&ip=${ip}`,
                    headers,
                );
            }

            if (url.pathname.endsWith('/weather')) {
                const lat = url.searchParams.get('lat');
                const lon = url.searchParams.get('lon');
                if (!lat || !lon) {
                    return new Response('{"error":"lat e lon são obrigatórios"}', { status: 400, headers });
                }
                const api =
                    `https://api.openweathermap.org/data/2.5/weather?lat=${encodeURIComponent(lat)}` +
                    `&lon=${encodeURIComponent(lon)}&units=metric&lang=pt_br&appid=${env.OPENWEATHER_KEY}`;
                return await proxyJson(api, headers);
            }

            return new Response('{"error":"rota desconhecida"}', { status: 404, headers });
        } catch (err) {
            return new Response(JSON.stringify({ error: String(err) }), { status: 502, headers });
        }
    },
};
