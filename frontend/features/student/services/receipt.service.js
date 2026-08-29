export class ReceiptService {
  constructor({ api }) {
    this.api = api;
  }

  load(code) {
    return this.api.get('/scan2borrow/api/receipt', this.compatibilityPayload(code));
  }

  compatibilityPayload(code) {
    return { code: String(code || '') };
  }
}
