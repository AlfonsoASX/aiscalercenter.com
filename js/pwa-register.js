const serviceWorkerUrl = new URL('../sw.js', import.meta.url);

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        void navigator.serviceWorker.register(serviceWorkerUrl).catch((error) => {
            console.error('No se pudo registrar el service worker de AiScaler.', error);
        });
    });
}
