/**
 * P2-WP02 cookie-session client. No localStorage, IndexedDB, bearer token,
 * password persistence, automatic mutation replay, or offline queue.
 * Use only with a deliberate sign-in form in WP03.
 */
export class PortalAuthClient {
  constructor(baseUrl = '/') {
    this.base = new URL(baseUrl, window.location.origin);
    if (this.base.origin !== window.location.origin) {
      throw new Error('Authentication API must be same-origin.');
    }
    this.csrf = null;
  }

  url(path) {
    return new URL(path.replace(/^\//, ''), this.base).toString();
  }

  async request(path, { method = 'GET', body = undefined, csrf = false } = {}) {
    const headers = { Accept: 'application/json' };
    if (body !== undefined) headers['Content-Type'] = 'application/json';
    if (csrf) {
      if (!this.csrf) throw new Error('Fetch a CSRF token before submitting.');
      headers[this.csrf.header] = this.csrf.token;
    }
    const response = await fetch(this.url(path), {
      method,
      credentials: 'same-origin',
      mode: 'same-origin',
      cache: 'no-store',
      redirect: 'error',
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    });
    const data = await response.json();
    if (!data || typeof data.success !== 'boolean') throw new Error('Unexpected API response.');
    return { status: response.status, ...data };
  }

  async refreshCsrf() {
    const result = await this.request('api/v1/auth/csrf');
    if (!result.success || !result.data?.header || !result.data?.token) {
      throw new Error('Security token unavailable.');
    }
    this.csrf = { header: result.data.header, token: result.data.token };
    return this.csrf;
  }

  async login(identifier, password) {
    if (!this.csrf) await this.refreshCsrf();
    // Never automatically replay POST if CSRF expires; show error and let user retry.
    const result = await this.request('api/v1/auth/login', {
      method: 'POST', body: { identifier, password }, csrf: true,
    });
    this.csrf = null; // Shield regenerates the session/CSRF after successful login.
    return result;
  }

  async logout() {
    if (!this.csrf) await this.refreshCsrf();
    const result = await this.request('api/v1/auth/logout', {
      method: 'POST', body: {}, csrf: true,
    });
    this.csrf = null;
    return result;
  }

  session() {
    return this.request('api/v1/auth/session');
  }
}
