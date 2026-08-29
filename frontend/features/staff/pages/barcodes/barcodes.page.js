export class BarcodePrintPage {
  constructor({ fetcher = fetch, printer = () => window.print(), windowObject = window } = {}) {
    this.fetcher = fetcher;
    this.printer = printer;
    this.windowObject = windowObject;
    this.labels = document.getElementById("barcode-labels");
    this.subtitle = document.getElementById("print-subtitle");
    this.error = document.getElementById("print-error");
    this.printButton = document.getElementById("print-pdf");
    this.printButton?.addEventListener("click", () => this.printer());
    document.getElementById("close-print")?.addEventListener("click", () => this.close());
  }

  async load(token = new URLSearchParams(this.windowObject.location.search).get("batch_token")) {
    if (!token || !/^[a-f0-9]{32}$/.test(token)) {
      this.fail("This barcode export link is missing or invalid.");
      return null;
    }
    try {
      const response = await this.fetcher(`/scan2borrow/api/barcode-print-batches?batch_token=${encodeURIComponent(token)}`, { headers: { Accept: "application/json" } });
      const payload = await response.json();
      if (!response.ok || payload.ok === false) throw new Error(payload.message || payload.errors?.[0] || "Barcode export was not found.");
      this.render(payload.data);
      return payload.data;
    } catch (error) {
      this.fail(error.message || "Barcode export was not found.");
      return null;
    }
  }

  render(batch) {
    const labels = Array.isArray(batch?.labels) ? batch.labels : [];
    this.subtitle.textContent = `${this.escape(batch?.title || "Book title")} · ${labels.length} label${labels.length === 1 ? "" : "s"} · generated ${this.escape(batch?.created_at || "")}`;
    this.labels.innerHTML = labels.length
      ? labels.map((label, index) => `<article class="barcode-label"><div class="barcode-label-title">${this.escape(label.title)}</div><div class="barcode-label-author">${this.escape(label.author || "Author not recorded")}</div><svg id="barcode-${index}" role="img" aria-label="Barcode ${this.escape(label.barcode)}"></svg><div class="barcode-label-meta"><strong>${this.escape(label.barcode)}</strong>${label.accession_no ? ` · Accession ${this.escape(label.accession_no)}` : ""}</div><div class="barcode-label-meta">${this.location(label)}</div></article>`).join("")
      : '<div class="barcode-loading">This batch contains no labels.</div>';
    labels.forEach((label, index) => {
      const element = document.getElementById(`barcode-${index}`);
      if (element && this.windowObject.JsBarcode) this.windowObject.JsBarcode(element, String(label.barcode || ""), { format: "CODE128", displayValue: true, fontSize: 12, height: 48, margin: 0 });
    });
    if (this.printButton) this.printButton.disabled = labels.length === 0;
  }

  location(label) {
    return [label.floor_no && `Floor ${label.floor_no}`, label.section_name, label.shelf_no && `Shelf ${label.shelf_no}`, label.row_no && `Row ${label.row_no}`].filter(Boolean).map((value) => this.escape(value)).join(" · ") || "Location not recorded";
  }

  close() {
    this.windowObject.close();
    if (!this.windowObject.closed) this.windowObject.history.back();
  }

  fail(message) {
    this.error.textContent = message;
    this.error.classList.remove("d-none");
    this.labels.innerHTML = "";
    if (this.printButton) this.printButton.disabled = true;
  }

  escape(value) {
    const node = document.createElement("span");
    node.textContent = value == null ? "" : String(value);
    return node.innerHTML;
  }
}

if (typeof document !== "undefined") document.addEventListener("DOMContentLoaded", () => new BarcodePrintPage().load());
