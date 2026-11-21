import { createNavigation } from './navigation.js';
import { apiGet } from './api.js';
import { navigationItems, previewCards, workspaces } from './workspaces.js';

const root = document.getElementById('app');
const errorPanel = document.getElementById('frontend-error');

function showFatal(message) {
    if (root) root.hidden = true;
    if (errorPanel) {
        errorPanel.hidden = false;
        const text = errorPanel.querySelector('span');
        if (text) text.textContent = message;
    }
    console.error(message);
}

if (!root || !window.Vue || window.Vue.version !== '3.5.24' || !window.Vue.createApp || !window.Vue.ref) {
    showFatal('The locally hosted Vue 3 production asset is missing or invalid.');
} else {
    try {
        const { createApp, ref, computed, onMounted, onUnmounted } = window.Vue;
        const baseUrl = root.dataset.baseUrl;
        if (!baseUrl) throw new Error('Portal base URL is not configured.');

        const app = createApp({
            setup() {
                const route = ref('/');
                const connection = ref(navigator.onLine ? 'checking' : 'offline');
                const checking = ref(false);
                const nav = createNavigation(baseUrl, (path) => {
                    route.value = path;
                    document.title = `${workspaces[path].title} | IEDPU Northern Zone`;
                });
                route.value = nav.initial;
                const current = computed(() => workspaces[route.value] || workspaces['/']);
                const connectionLabel = computed(() => ({
                    online: 'Connected',
                    checking: 'Checking connection',
                    offline: 'Waiting for internet',
                    error: 'Connection unavailable',
                })[connection.value]);
                const connectionClass = computed(() => `connection-${connection.value}`);

                async function refreshHealth() {
                    if (!navigator.onLine) {
                        connection.value = 'offline';
                        return;
                    }
                    if (checking.value) return;
                    checking.value = true;
                    connection.value = 'checking';
                    try {
                        const result = await apiGet(baseUrl, 'api/v1/health');
                        connection.value = result?.status === 'ok' ? 'online' : 'error';
                    } catch {
                        connection.value = navigator.onLine ? 'error' : 'offline';
                    } finally {
                        checking.value = false;
                    }
                }

                const onOffline = () => { connection.value = 'offline'; };
                const onOnline = () => { refreshHealth(); };
                onMounted(() => {
                    document.title = `${current.value.title} | IEDPU Northern Zone`;
                    window.addEventListener('offline', onOffline);
                    window.addEventListener('online', onOnline);
                    refreshHealth();
                });
                onUnmounted(() => {
                    nav.destroy();
                    window.removeEventListener('offline', onOffline);
                    window.removeEventListener('online', onOnline);
                });

                return {
                    route, current, navigationItems, previewCards,
                    mobileItems: navigationItems,
                    connectionLabel, connectionClass, checking, refreshHealth,
                    year: new Date().getFullYear(),
                    href: nav.href, navigate: nav.go,
                };
            },
        });

        app.mount(root);
    } catch (error) {
        showFatal('The portal interface could not initialise. Please reload the page.');
    }
}
