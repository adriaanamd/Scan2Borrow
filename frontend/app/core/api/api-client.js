import { ApiError } from './api-error.js';

export class ApiClient {
  constructor({ baseUrl = '', fetchImpl = globalThis.fetch, csrf = '' } = {}) {
    this.baseUrl = baseUrl;
    this.fetchImpl = fetchImpl;
    this.csrf = csrf;
  }

  async request(path, { method = 'GET', body = null, headers = {}, signal } = {}) {
    const requestHeaders = { Accept: 'application/json', ...headers };
    let requestBody = body;

    if (body instanceof FormData) {
      body.append('csrf', this.csrf);
    } else if (body && typeof body === 'object') {
      requestHeaders['Content-Type'] = 'application/x-www-form-urlencoded';
      requestBody = new URLSearchParams({ ...body, csrf: this.csrf });
    }

    const response = await this.fetchImpl(this.baseUrl + path, {
      method,
      body: requestBody,
      headers: requestHeaders,
      credentials: 'same-origin',
      signal,
    });
    const payload = await response.json();

    if (!response.ok || payload.ok === false || payload.success === false) {
      throw new ApiError(payload.message || payload.errors?.[0] || 'Request failed.', {
        status: response.status,
        payload,
      });
    }

    return payload;
  }

  get(path, params = {}) {
    const query = new URLSearchParams(params).toString();
    return this.request(query ? path + '?' + query : path);
  }

  post(path, body) {
    return this.request(path, { method: 'POST', body });
  }
}
