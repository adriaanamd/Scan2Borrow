export class GuestRequestsPage {
  constructor(root = globalThis.document, { service } = {}) { this.root = root; this.service = service; }

  start() { return this.load(); }

  async load() {
    const response = await this.service?.load();
    if (response) this.render(response.data?.requests || []);
    return response;
  }

  render(rows) {
    const body = this.root.querySelector?.('table tbody');
    if (!body) return;
    const media = (value) => globalThis.Scan2BorrowMedia?.resolve(value || '') || value || '';
    const badge = this.root.querySelector?.('.guest-requests__count');
    if (badge) badge.textContent = String(rows.length);
    body.innerHTML = rows.length ? rows.map((row) => {
      const visitorPhoto = media(row.visitor_photo);
      const verificationPhoto = media(row.verification_photo);
      return `<tr>
        <td>${visitorPhoto ? `<img src="${this.escape(visitorPhoto)}" class="rounded-circle me-2" style="width:38px;height:38px;object-fit:cover" alt="Guest photo">` : ''}<strong>${this.escape(row.name)}</strong><br><span class="text-muted small">${this.escape(row.visitor_number || '—')} · ${this.escape(row.id_barcode)}</span></td>
        <td>${this.escape(row.title)}<br><span class="text-muted small">${this.escape(row.author || '—')}</span></td>
        <td><code>${this.escape(row.accession)}</code></td>
        <td>${this.escape(row.requested_at || row.created_at || '')}</td>
        <td>${verificationPhoto ? `<button class="btn btn-outline-primary btn-sm" data-photo="${this.escape(verificationPhoto)}" data-name="${this.escape(row.name)}" data-book="${this.escape(row.title)}" data-photo-view>View</button>` : '<span class="text-muted">—</span>'}</td>
        <td><button class="btn btn-success btn-sm" data-id="${this.escape(row.id)}" data-name="${this.escape(row.name)}" data-photo="${this.escape(visitorPhoto)}" data-visno="${this.escape(row.visitor_number || '')}" data-idbarcode="${this.escape(row.id_barcode || '')}" data-title="${this.escape(row.title)}" data-author="${this.escape(row.author || '')}" data-accession="${this.escape(row.accession)}" data-verif="${this.escape(verificationPhoto)}" data-review-request>Review</button></td>
      </tr>`;
    }).join('') : '<tr><td colspan="6" class="text-center text-muted py-4">No pending guest borrow requests.</td></tr>';
    this.bindGuestReview();
  }

  bindGuestReview() {
    const media = (value) => globalThis.Scan2BorrowMedia?.resolve(value || '') || value || '';
    this.root.querySelectorAll?.('[data-review-request]').forEach((button) => button.addEventListener('click', () => {
      const values = {
        '#review-id': button.dataset.id || '',
        '#review-name': button.dataset.name || '',
        '#review-visno': button.dataset.visno || '',
        '#review-idbarcode': button.dataset.idbarcode || '',
        '#review-title': button.dataset.title || '',
        '#review-author': button.dataset.author || '',
        '#review-accession': button.dataset.accession || '',
      };
      Object.entries(values).forEach(([selector, value]) => {
        const node = this.root.querySelector?.(selector);
        if (node) {
          if (selector === '#review-id') node.value = value;
          else node.textContent = value;
        }
      });
      const photo = this.root.querySelector?.('#review-photo');
      const verification = this.root.querySelector?.('#review-verif');
      if (photo) photo.src = media(button.dataset.photo);
      if (verification) verification.src = media(button.dataset.verif);
      globalThis.bootstrap?.Modal?.getOrCreateInstance(this.root.querySelector?.('#reviewModal'))?.show();
    }));

    this.root.querySelectorAll?.('[data-photo-view]').forEach((button) => button.addEventListener('click', () => {
      const photo = this.root.querySelector?.('#photoViewer');
      const caption = this.root.querySelector?.('#photoCaption');
      if (photo) photo.src = media(button.dataset.photo);
      if (caption) caption.textContent = `${button.dataset.name || ''} holding "${button.dataset.book || ''}"`;
      globalThis.bootstrap?.Modal?.getOrCreateInstance(this.root.querySelector?.('#viewPhotoModal'))?.show();
    }));

    const form = this.root.querySelector?.('#reviewModal form');
    if (form && !form.dataset.bound) {
      form.dataset.bound = 'true';
      form.addEventListener('submit', async (event) => {
        const confirmationAction = event.submitter?.dataset?.confirmAction || form.dataset.confirmAction;
        if (confirmationAction && form.dataset.confirmBypass !== 'true') return;
        event.preventDefault();
        const notes = this.root.querySelector?.('#review-notes')?.value || '';
        const action = event.submitter?.value || 'approve';
        try {
          await this.service?.review(form.elements.id.value, action, notes);
          globalThis.bootstrap?.Modal?.getInstance(this.root.querySelector?.('#reviewModal'))?.hide();
          await this.load();
          this.showFeedback('success', action === 'approve' ? 'Guest request approved and released.' : 'Guest request rejected.');
        } catch (error) {
          this.showFeedback('error', error?.message || 'Could not save guest request decision.');
        }
      });
    }
  }

  showFeedback(kind, message) {
    const success = this.root.querySelector?.('.alert.alert-success');
    const error = this.root.querySelector?.('.alert.alert-danger');
    [success, error].forEach((node) => node?.classList.add('d-none'));
    const target = kind === 'success' ? success : error;
    if (!target) return;
    target.textContent = message;
    target.classList.remove('d-none');
  }

  escape(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character])); }
}
