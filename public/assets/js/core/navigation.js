/**
 * Small first-party History API router. Public preview routes only.
 * Protected destinations must be introduced in P2 with server-side policies.
 */
export const allowedRoutes = Object.freeze([
    '/',
    '/preview/member',
    '/preview/branch',
    '/preview/committee',
    '/preview/technical',
]);

export function createNavigation(baseUrl, onChange) {
    const base = new URL(baseUrl, window.location.origin);
    const basePath = base.pathname.replace(/\/+$/, '') + '/';

    function routeFromLocation() {
        const current = window.location.pathname;
        const prefix = basePath.replace(/\/$/, '');
        const suffix = current.startsWith(prefix + '/')
            ? current.slice(prefix.length)
            : current === prefix || current === basePath ? '/' : '';
        const normalized = suffix.replace(/\/+$/, '') || '/';
        return allowedRoutes.includes(normalized) ? normalized : '/';
    }

    function href(path) {
        if (!allowedRoutes.includes(path)) {
            throw new Error('Unknown portal preview route.');
        }
        return new URL(path === '/' ? '' : path.slice(1), base).pathname;
    }

    function go(path) {
        if (!allowedRoutes.includes(path)) return false;
        const target = href(path);
        if (window.location.pathname !== target) {
            window.history.pushState({ portalPreview: true }, '', target);
        }
        onChange(path);
        window.scrollTo({ top: 0, behavior: 'instant' });
        return true;
    }

    function handlePopState() {
        onChange(routeFromLocation());
    }

    window.addEventListener('popstate', handlePopState);

    return {
        initial: routeFromLocation(),
        href,
        go,
        destroy() { window.removeEventListener('popstate', handlePopState); },
    };
}
