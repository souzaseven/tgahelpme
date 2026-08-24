/**
 * sw.js
 * Service worker leve: permite instalar o app e mantém uma cópia de
 * reserva do "shell" estático (HTML/CSS/JS/ícones) para funcionar se a
 * rede cair. NÃO intercepta a BrasilAPI nem qualquer origem externa — as
 * consultas de CNPJ sempre vão direto para a rede, nunca para o cache.
 *
 * Estratégia: rede primeiro, cache como reserva. Importante manter assim
 * (e não "cache primeiro") — com cache primeiro, qualquer atualização do
 * HTML/CSS/JS ficaria presa na versão antiga até o cache expirar.
 *
 * IMPORTANTE: sempre que o conteúdo do app mudar de forma que precise
 * "furar" a cópia de reserva antiga, incremente o número da versão abaixo.
 */
const CACHE_NAME = "consulta-cnpj-v4";

const ASSETS = [
  "./",
  "./index.html",
  "./css/style.css?v=4",
  "./js/utils.js?v=4",
  "./js/storage.js?v=4",
  "./js/api.js?v=4",
  "./js/ui.js?v=4",
  "./js/app.js?v=4",
  "./manifest.json",
  "./icons/icon.svg",
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches
      .open(CACHE_NAME)
      .then((cache) => cache.addAll(ASSETS))
      .catch(() => {
        // Falha ao pré-cachear não deve impedir a instalação do service worker.
      })
  );
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
  );
  self.clients.claim();
});

self.addEventListener("fetch", (event) => {
  const url = new URL(event.request.url);

  // Só o próprio app é cacheável. Qualquer requisição de outra origem
  // (BrasilAPI incluída) passa direto, sem interferência do service worker.
  if (url.origin !== self.location.origin) return;
  if (event.request.method !== "GET") return;

  // Rede primeiro: sempre tenta buscar a versão mais recente. Só usa a
  // cópia em cache se a rede falhar (ex.: sem conexão).
  event.respondWith(
    fetch(event.request)
      .then((response) => {
        const clone = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
        return response;
      })
      .catch(() => caches.match(event.request).then((cached) => cached || caches.match("./index.html")))
  );
});
