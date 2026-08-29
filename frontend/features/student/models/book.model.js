const text = (value) => value == null ? '' : String(value);

export function normalizeBook(value = {}) {
  return {
    barcode: text(value.barcode),
    title: text(value.title),
    author: text(value.author),
    category_name: text(value.category_name),
    status: text(value.status),
    description: text(value.description),
    publisher: text(value.publisher),
    floor_no: text(value.floor_no),
    shelf_no: text(value.shelf_no),
    row_no: text(value.row_no),
    cover_file: text(value.cover_file),
    cover_image: text(value.cover_image),
    already_borrowed: Boolean(value.already_borrowed),
  };
}
