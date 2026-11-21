/**
 * Same-origin JSON API helper. No bearer tokens, offline queue or local storage.
 * P2 will add CSRF/session handling and protected endpoint policies.
 */
export class ApiError extends Error {
    constructor(message, status, code) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.code = code;
    }
}

export async function apiGet(baseUrl, endpoint, { signal, timeoutMs = 9000 } = {}) {
    if (!/^api\/v1\/[a-z0-9/-]+$/.test(endpoint)) {
        throw new ApiError('Invalid API endpoint.', 0, 'invalid_endpoint');
    }

    const controller = new AbortController();
    const abort = () => controller.abort();
    signal?.addEventListener('abort', abort, { once: true });
    const timer = window.setTimeout(abort, timeoutMs);

    try {
        const response = await fetch(new URL(endpoint, baseUrl), {
            method: 'GET',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            cache: 'no-store',
            signal: controller.signal,
        });
        const type = response.headers.get('content-type') || '';
        if (!type.includes('application/json')) {
            throw new ApiError('The server did not return JSON.', response.status, 'invalid_response');
        }
        const payload = await response.json();
        if (!response.ok || payload?.success !== true) {
            throw new ApiError(
                payload?.error?.message || 'The request could not be completed.',
                response.status,
                payload?.error?.code || 'request_failed'
            );
        }
        return payload.data;
    } catch (error) {
        if (error instanceof ApiError) throw error;
        if (error.name === 'AbortError') {
            throw new ApiError('The request took too long. Try again.', 0, 'timeout');
        }
        throw new ApiError('Unable to connect. Check your internet and try again.', 0, 'network_error');
    } finally {
        window.clearTimeout(timer);
        signal?.removeEventListener('abort', abort);
    }
}
