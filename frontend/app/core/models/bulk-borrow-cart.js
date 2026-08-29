export class BulkBorrowCart {
  constructor() {
    this.lines = new Map();
  }

  addTitle(book, quantity = 1, barcode = "") {
    const titleId = Number(book?.id ?? book?.title_id ?? 0);
    if (!titleId) return false;
    const available = Math.max(0, Number(book?.available_quantity ?? book?.quantity ?? 0));
    const line = this.lines.get(titleId) || {
      title_id: titleId,
      title: book.title || "",
      author: book.author || "",
      available_quantity: available,
      quantity: 0,
      barcodes: [],
    };
    line.available_quantity = available || line.available_quantity;
    const copyBarcode = String(barcode || "").trim();
    if (copyBarcode) {
      if (line.barcodes.includes(copyBarcode)) return false;
      if (line.quantity >= line.available_quantity) return false;
      line.barcodes.push(copyBarcode);
      line.quantity += 1;
    } else {
      line.quantity = Math.min(line.available_quantity, line.quantity + Math.max(0, Number(quantity) || 0));
    }
    if (line.quantity === 0) return false;
    this.lines.set(titleId, line);
    return true;
  }

  setQuantity(titleId, quantity) {
    const line = this.lines.get(Number(titleId));
    if (!line) return false;
    const next = Math.max(0, Math.min(line.available_quantity, Number(quantity) || 0));
    line.quantity = next;
    line.barcodes = line.barcodes.slice(0, next);
    if (next === 0) this.lines.delete(Number(titleId));
    return true;
  }

  removeTitle(titleId) {
    return this.lines.delete(Number(titleId));
  }

  clear() {
    this.lines.clear();
  }

  has(titleId) {
    return this.lines.has(Number(titleId));
  }

  totalQuantity() {
    return Array.from(this.lines.values()).reduce((total, line) => total + line.quantity, 0);
  }

  items() {
    return Array.from(this.lines.values()).map(({ title_id, quantity, barcodes }) => ({
      title_id,
      quantity,
      barcodes: [...barcodes],
    }));
  }

  linesForDisplay() {
    return Array.from(this.lines.values()).map((line) => ({ ...line, barcodes: [...line.barcodes] }));
  }
}
