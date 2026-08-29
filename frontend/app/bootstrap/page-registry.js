const pages = new Map();

export function registerPage(name, factory) {
  pages.set(name, factory);
}

export function pageNameFromDocument(document) {
  return document.body?.dataset?.appPage || '';
}

export async function bootPage(name, context = {}) {
  const factory = pages.get(name);
  if (!factory) {
    throw new Error('Unknown frontend page: ' + name);
  }

  return factory(context).start();
}
