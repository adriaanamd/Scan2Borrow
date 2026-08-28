class GuestPassController {
  constructor() {
    this.load();
  }
  load() {
    fetch("/scan2borrow/api/guest/pass", {
      headers: { "X-Requested-With": "fetch" },
    })
      .then((response) => response.json())
      .then((response) => {
        if (!response.ok) return;
        const data = response.data || {};
        document.getElementById("visitor-name").textContent = data.name || "";
        document.getElementById("id-type").textContent = data.id_type || "";
        document.getElementById("registered-date").textContent =
          data.registered_date || "";
        document.getElementById("id-barcode-value").textContent =
          data.id_barcode || "";
        if (window.JsBarcode)
          window.JsBarcode("#id-barcode", data.id_barcode, {
            format: "CODE128",
            width: 1.5,
            height: 55,
            displayValue: false,
          });
      });
  }
}
window.addEventListener("DOMContentLoaded", () => new GuestPassController());
