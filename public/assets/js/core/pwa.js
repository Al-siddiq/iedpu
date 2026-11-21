import { initialisePublicStorage } from './offline-store.js';
const root = document.getElementById('app');
const panel = document.getElementById('pwa-notice');
const message = document.getElementById('pwa-message');
const update = document.getElementById('pwa-update');
const install = document.getElementById('pwa-install');
let promptEvent;
let acceptedUpdate = false;
document.getElementById('pwa-dismiss').addEventListener('click', () => { panel.hidden = true; });
function announce(text) { panel.hidden = false; message.textContent = text; }
window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault();
    promptEvent = event;
    install.hidden = false;
    announce('You can install the portal on this device.');
});
install.addEventListener('click', async () => {
    if (!promptEvent) return;
    const current = promptEvent; promptEvent = null; install.hidden = true;
    try { await current.prompt(); await current.userChoice; } catch { announce('Use your browser menu to install the portal.'); }
});
window.addEventListener('appinstalled', () => { install.hidden = true; announce('Portal installed on this device.'); });
async function start() {
    if (!window.isSecureContext || !('serviceWorker' in navigator)) return;
    const base = new URL(root.dataset.baseUrl, location.href);
    if (base.origin !== location.origin) return;
    try {
        const registration = await navigator.serviceWorker.register(new URL('sw.js', base), {scope:base.pathname, updateViaCache:'none'});
        const waiting = () => {
            if (!registration.waiting || !navigator.serviceWorker.controller) return;
            announce('A new portal version is ready. Refresh when convenient.');
            update.hidden = false;
        };
        waiting();
        registration.addEventListener('updatefound', () => {
            const worker = registration.installing;
            worker?.addEventListener('statechange', () => { if (worker.state === 'installed') waiting(); });
        });
        update.addEventListener('click', () => {
            if (!registration.waiting) return;
            acceptedUpdate = true; update.disabled = true;
            registration.waiting.postMessage({type:'IEDPU_APPLY_UPDATE'});
        });
        navigator.serviceWorker.addEventListener('controllerchange', () => { if (acceptedUpdate) location.reload(); });
        await initialisePublicStorage().catch(() => false);
    } catch {
        announce('Offline setup is unavailable. You can still use the portal online.');
    }
}
start();
