const NOME_DO_CACHE = 'meu_app_v1';
const ASSETS_PARA_CACHE = [
    '/meu-app',
    // Adicione aqui outros arquivos CSS ou JS que queira salvar offline
];

/**
 * Evento de Instalação do SW
 */
self.addEventListener('install', (evento) => {
    evento.waitUntil(
        caches.open(NOME_DO_CACHE)
            .then((cache) => {
                return cache.addAll(ASSETS_PARA_CACHE);
            })
    );
});

/**
 * Evento de Fetch (Intercepta requisições)
 */
self.addEventListener('fetch', (evento) => {
    evento.respondWith(
        caches.match(evento.request)
            .then((resposta) => {
                // Retorna do cache se existir, senão busca na rede
                return resposta || fetch(evento.request);
            })
    );
});