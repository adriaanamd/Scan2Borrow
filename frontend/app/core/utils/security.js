export function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  })[character]);
}

export function safePath(path) {
  const value = String(path ?? '');
  return value.startsWith('/') && !value.startsWith('//') ? value : '#';
}
