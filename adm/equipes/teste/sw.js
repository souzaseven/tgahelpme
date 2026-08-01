self.addEventListener("install", event => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener("activate", event => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener("notificationclick", event => {
    event.notification.close();

    const destino = event.notification?.data?.url || "painel/index.php";

    event.waitUntil((async () => {
        const janelas = await self.clients.matchAll({ type: "window", includeUncontrolled: true });

        for (const janela of janelas) {
            if ("focus" in janela) {
                await janela.focus();
                if ("navigate" in janela) {
                    try {
                        await janela.navigate(destino);
                    } catch (_) {}
                }
                return;
            }
        }

        if (self.clients.openWindow) {
            await self.clients.openWindow(destino);
        }
    })());
});