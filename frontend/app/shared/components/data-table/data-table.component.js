export class DataTableComponent {
  constructor(root, {
    columns = [],
    renderRow,
    emptyMessage = 'No results found.',
    document = globalThis.document,
  }) {
    this.root = root;
    this.columns = columns;
    this.renderRow = renderRow;
    this.emptyMessage = emptyMessage;
    this.document = document;
  }

  render(rows = []) {
    const body = this.root.querySelector('tbody') || this.root;
    body.replaceChildren();
    if (!rows.length) {
      const empty = this.document.createElement('div');
      empty.className = 'text-center text-muted py-4';
      empty.textContent = this.emptyMessage;
      body.appendChild(empty);
      return;
    }

    rows.forEach((row) => body.appendChild(this.renderRow(row, this.columns)));
  }

  destroy() {}
}
