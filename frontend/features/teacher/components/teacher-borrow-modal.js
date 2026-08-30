export function installTeacherBorrowModal(documentRef = document) {
  const modal = documentRef.getElementById("borrowModal");
  if (!modal || modal.dataset.teacherModalReady === "true") return modal;
  modal.dataset.teacherModalReady = "true";
  let lastTrigger = null;

  const focusable = () => [
    modal.querySelector('[data-bs-dismiss="modal"]'),
    documentRef.getElementById("bulk-scan-barcode"),
    documentRef.getElementById("bulk-scan-add"),
    modal.querySelector('input[name="due_date"]'),
    modal.querySelector('button[type="submit"]'),
    ...modal.querySelectorAll("[data-cart-action]"),
  ].filter((element) => element && !element.disabled);

  const close = () => {
    if (!modal.classList.contains("is-open")) return;
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
    documentRef.body.classList.remove("teacher-borrow-modal-open");
    modal.dispatchEvent(new Event("hidden.bs.modal"));
    lastTrigger?.focus?.();
    lastTrigger = null;
  };

  const open = (trigger) => {
    lastTrigger = trigger;
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    documentRef.body.classList.add("teacher-borrow-modal-open");
    const event = new Event("show.bs.modal");
    Object.defineProperty(event, "relatedTarget", { value: trigger });
    modal.dispatchEvent(event);
    documentRef.getElementById("bulk-scan-barcode")?.focus();
  };

  documentRef.addEventListener("click", (event) => {
    const trigger = event.target.closest?.('[data-bs-toggle="modal"][data-bs-target="#borrowModal"]');
    if (!trigger) return;
    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation?.();
    open(trigger);
  }, true);

  modal.addEventListener("click", (event) => {
    const dismiss = event.target.closest?.('[data-bs-dismiss="modal"]');
    if (dismiss) {
      event.preventDefault();
      event.stopPropagation();
      event.stopImmediatePropagation?.();
      close();
      return;
    }
    if (event.target === modal) close();
  }, true);

  modal.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      event.preventDefault();
      close();
      return;
    }
    if (event.key !== "Tab") return;
    const elements = focusable();
    if (!elements.length) return;
    const currentIndex = elements.indexOf(documentRef.activeElement);
    const nextIndex = currentIndex < 0
      ? 0
      : (currentIndex + (event.shiftKey ? -1 : 1) + elements.length) % elements.length;
    event.preventDefault();
    elements[nextIndex].focus();
  });

  return { modal, open, close };
}
