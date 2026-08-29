import { bootDocument } from './page-registry.js';

export function boot(document = globalThis.document, contextFactory) {
  return bootDocument(document, contextFactory);
}

if (typeof document !== 'undefined') {
  document.addEventListener('DOMContentLoaded', () => boot(), { once: true });
}
