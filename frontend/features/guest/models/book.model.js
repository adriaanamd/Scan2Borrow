const text = (value) => value == null ? '' : String(value);

export function normalizeGuestBook(value = {}) {
  return {
    id: text(value.id),
    title: text(value.title),
    author: text(value.author),
    category_name: text(value.category_name),
    isbn: text(value.isbn),
    call_number: text(value.call_number),
    cover_file: text(value.cover_file),
    status: text(value.status),
  };
}
