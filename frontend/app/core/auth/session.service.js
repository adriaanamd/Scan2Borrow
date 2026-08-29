export class SessionService {
  constructor({ api, document, storage = null }) {
    this.api = api;
    this.document = document;
    this.storage = storage;
    this.value = null;
  }

  async load() {
    const response = await this.api.get('/scan2borrow/api/auth/session');
    this.value = response.ok === false ? null : (response.data || response);
    return this.value;
  }

  current() {
    return this.value;
  }

  csrf() {
    return this.document.querySelector('meta[name="csrf"]')?.content || '';
  }

  clear() {
    this.value = null;
    this.storage?.removeItem('scan2borrow.session');
  }
}
