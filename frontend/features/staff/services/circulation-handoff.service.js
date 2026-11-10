export const CIRCULATION_HANDOFF_KEY = 'scan2borrow.circulation.handoff';

export function saveCirculationHandoff(storage, { borrowerBarcode = '', copyBarcodes = [] } = {}) {
  const borrower = String(borrowerBarcode || '').trim();
  if (!storage || !borrower) return false;

  const copies = Array.from(new Set(
    (Array.isArray(copyBarcodes) ? copyBarcodes : [])
      .map((barcode) => String(barcode || '').trim())
      .filter(Boolean),
  ));

  try {
    storage.setItem(CIRCULATION_HANDOFF_KEY, JSON.stringify({
      borrowerBarcode: borrower,
      copyBarcodes: copies,
    }));
    return true;
  } catch {
    return false;
  }
}

export function readCirculationHandoff(storage) {
  if (!storage) return null;

  try {
    const raw = storage.getItem(CIRCULATION_HANDOFF_KEY);
    if (!raw) return null;
    const value = JSON.parse(raw);
    const borrowerBarcode = String(value?.borrowerBarcode || '').trim();
    if (!borrowerBarcode) return null;
    const copyBarcodes = Array.from(new Set(
      (Array.isArray(value?.copyBarcodes) ? value.copyBarcodes : [])
        .map((barcode) => String(barcode || '').trim())
        .filter(Boolean),
    ));
    return { borrowerBarcode, copyBarcodes };
  } catch {
    return null;
  }
}

export function clearCirculationHandoff(storage) {
  try {
    storage?.removeItem?.(CIRCULATION_HANDOFF_KEY);
  } catch {
    // Storage can be unavailable in privacy-restricted browser contexts.
  }
}
