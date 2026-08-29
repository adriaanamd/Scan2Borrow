export class AdminStaffPage {
  constructor(root = globalThis.document, { service, window = globalThis.window } = {}) { this.root = root; this.service = service; this.window = window; }

  start() { return this.load(); }

  async load() {
    const search = new URLSearchParams(this.window.location.search).get('bsearch') || '';
    const response = await this.service?.list(search);
    if (response) this.render(response.data || {});
    return response;
  }

  render(data) {
    const tables = this.root.querySelectorAll?.('table tbody') || [];
    if (tables[0]) tables[0].innerHTML = this.staffRows(data.staff || []);
    if (tables[1]) tables[1].innerHTML = this.borrowerRows(data.borrowers || []);
    this.bindAdminActions();
  }

  staffRows(rows) {
    return rows.length ? rows.map((row) => `<tr><td>${this.escape(row.barcode)}</td><td>${this.escape(row.name)}</td><td><span class="badge bg-primary">${this.escape(row.role)}</span></td><td class="text-muted small">${this.escape(row.email || '')}</td><td>${this.escape(row.status || '')}</td><td class="text-nowrap"><button class="btn btn-outline-secondary btn-sm" data-reset-user="${this.escape(row.id)}" data-name="${this.escape(row.name)}" data-bs-toggle="modal" data-bs-target="#pwModal">Reset Password</button><button class="btn btn-outline-warning btn-sm" data-toggle-user="${this.escape(row.id)}">Toggle Status</button><button class="btn btn-outline-danger btn-sm" data-demote-user="${this.escape(row.id)}">Demote</button></td></tr>`).join('') : '<tr><td colspan="6" class="text-center text-muted">No staff accounts.</td></tr>';
  }

  borrowerRows(rows) {
    return rows.length ? rows.map((row) => `<tr><td>${this.escape(row.barcode)}</td><td>${this.escape(row.name)}</td><td class="text-muted">${this.escape(row.course || '')}</td><td><button class="btn btn-gradient btn-sm" data-promote-user="${this.escape(row.id)}" data-name="${this.escape(row.name)}" data-bs-toggle="modal" data-bs-target="#promoteModal">&#128081; Promote to Librarian</button></td></tr>`).join('') : '<tr><td colspan="4" class="text-center text-muted">No borrowers found.</td></tr>';
  }

  bindAdminActions() {
    this.root.querySelectorAll?.('[data-reset-user]').forEach((button) => button.addEventListener('click', () => {
      const id = this.root.querySelector?.('#pw_uid');
      const name = this.root.querySelector?.('#pw_name');
      if (id) id.value = button.dataset.resetUser;
      if (name) name.textContent = button.dataset.name;
    }));
    this.root.querySelectorAll?.('[data-promote-user]').forEach((button) => button.addEventListener('click', () => {
      const id = this.root.querySelector?.('#promote_uid');
      const name = this.root.querySelector?.('#promote_name');
      if (id) id.value = button.dataset.promoteUser;
      if (name) name.textContent = button.dataset.name;
    }));
    this.root.querySelectorAll?.('[data-toggle-user], [data-demote-user]').forEach((button) => button.addEventListener('click', () => this.action(button.dataset.toggleUser ? 'toggle_status' : 'demote', button.dataset.toggleUser || button.dataset.demoteUser)));
    this.root.querySelectorAll?.('#promoteModal form, #pwModal form').forEach((form) => {
      if (form.dataset.bound) return;
      form.dataset.bound = 'true';
      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const values = Object.fromEntries(new FormData(form));
        await this.action(values.action, values.user_id, values);
      });
    });
  }

  async action(action, userId, values = {}) {
    try {
      await this.service?.action(action, userId, values);
      await this.load();
    } catch (error) {
      const node = this.root.querySelector?.('.alert.alert-danger');
      if (node) { node.textContent = error.message || 'Could not save staff changes.'; node.classList.remove('d-none'); }
    }
  }

  escape(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character])); }
}
