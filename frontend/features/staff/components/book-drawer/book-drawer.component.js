export class BookDrawerComponent {
  constructor(root, { document = globalThis.document, offcanvasFactory } = {}) {
    this.root = root;
    this.document = document;
    this.form = root.querySelector?.('#book-form') || document.getElementById('book-form');
    this.coverPreview = root.querySelector?.('#cover-preview') || document.getElementById('cover-preview');
    const drawerElement = root.querySelector?.('#bookDrawer') || document.getElementById('bookDrawer');
    this.drawer = offcanvasFactory ? offcanvasFactory(drawerElement) : globalThis.bootstrap?.Offcanvas ? new globalThis.bootstrap.Offcanvas(drawerElement) : null;
  }

  open(book = null) {
    this.form?.reset();
    const id = this.form?.elements?.['book-id'] || this.document.getElementById('book-id');
    if (id) id.value = book?.id || '';
    if (this.form && book) {
      ['barcode', 'isbn', 'title', 'author', 'publisher', 'description', 'category_name', 'keywords', 'floor_no', 'section_name', 'shelf_no', 'row_no', 'due_date', 'return_date', 'status'].forEach((field) => {
        if (this.form.elements[field]) this.form.elements[field].value = book[field] || '';
      });
      if (this.coverPreview) this.coverPreview.src = book.cover_file || book.cover_image || '';
    }
    this.drawer?.show();
  }

  close() { this.drawer?.hide(); }
}
