export class AdminStaffPage {
  constructor(root = globalThis.document, { service, profileService, window = globalThis.window } = {}) { this.root = root; this.service = service; this.profileService = profileService; this.window = window; this.profileChanges = []; this.selectedProfileChange = null; }

  start() { return this.load(); }

  async load() {
    const search = new URLSearchParams(this.window.location.search).get('bsearch') || '';
    const response = await this.service?.list(search);
    if (response) this.render(response.data || {});
    if (this.profileService) {
      try {
        const profileResponse = await this.profileService.list();
        this.profileChanges = profileResponse?.data?.requests || [];
        this.renderProfileChangeRequests(this.profileChanges);
      } catch (error) {
        this.showFeedback('error', error.message || 'Could not load profile change requests.');
      }
    }
    return response;
  }

  render(data) {
    const tables = this.root.querySelectorAll?.('table tbody') || [];
    if (tables[0]) tables[0].innerHTML = this.staffRows(data.staff || []);
    if (tables[1]) tables[1].innerHTML = this.borrowerRows(data.borrowers || []);
    this.bindAdminActions();
    this.renderProfileChangeRequests(data.profile_change_requests || this.profileChanges);
  }

  staffRows(rows) {
    return rows.length ? rows.map((row) => {
      const status = this.staffStatusClass(row.status);
      return `<tr><td><code class="staff-management__barcode">${this.escape(row.barcode)}</code></td><td><strong>${this.escape(row.name)}</strong></td><td><span class="staff-management__role">${this.escape(row.role)}</span></td><td class="text-muted small">${this.escape(row.email || '')}</td><td><span class="staff-management__status staff-management__status--${status}">${this.escape(row.status || '')}</span></td><td><div class="staff-management__actions"><button class="btn btn-outline-secondary btn-sm" data-reset-user="${this.escape(row.id)}" data-name="${this.escape(row.name)}" data-bs-toggle="modal" data-bs-target="#pwModal">Reset password</button><button class="btn btn-outline-warning btn-sm" data-toggle-user="${this.escape(row.id)}">${status === 'active' ? 'Disable' : 'Enable'}</button><button class="btn btn-outline-danger btn-sm" data-demote-user="${this.escape(row.id)}">Demote</button></div></td></tr>`;
    }).join('') : '<tr><td colspan="6" class="text-center text-muted">No staff accounts.</td></tr>';
  }

  borrowerRows(rows) {
    return rows.length ? rows.map((row) => `<tr><td><code class="staff-management__barcode">${this.escape(row.barcode)}</code></td><td><strong>${this.escape(row.name)}</strong></td><td class="text-muted">${this.escape(row.course || '')}</td><td><button class="btn btn-primary btn-sm staff-management__promote-action" data-promote-user="${this.escape(row.id)}" data-name="${this.escape(row.name)}" data-bs-toggle="modal" data-bs-target="#promoteModal">Promote to librarian</button></td></tr>`).join('') : '<tr><td colspan="4" class="text-center text-muted">No borrowers found.</td></tr>';
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
      this.showFeedback('success', this.actionMessage(action));
    } catch (error) {
      this.showFeedback('error', error.message || 'Could not save staff changes.');
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

  actionMessage(action) {
    return {
      promote: 'Staff role assigned successfully.',
      reset_password: 'Password updated successfully.',
      toggle_status: 'Account status updated successfully.',
      demote: 'Staff account demoted successfully.',
    }[action] || 'Staff account updated successfully.';
  }

  staffStatusClass(status) {
    return String(status || '').toLowerCase() === 'inactive' ? 'inactive' : 'active';
  }

  renderProfileChangeRequests(rows = []) {
    const body = this.root.querySelector?.('#profile-change-requests-body');
    if (!body) return;
    body.innerHTML = rows.length ? rows.map((row) => {
      const changes = Object.keys(row.requested_values || {});
      if (row.requested_photo) changes.push('photo');
      return `<tr><td><strong>${this.escape(row.user_name)}</strong><br><span class="text-muted small">${this.escape(row.barcode)}</span></td><td>${this.escape(row.role)}</td><td class="text-muted small">${this.escape(row.requested_at)}</td><td>${this.escape(changes.join(', '))}</td><td><button type="button" class="btn btn-primary btn-sm" data-profile-request-id="${this.escape(row.id)}">Review</button></td></tr>`;
    }).join('') : '<tr><td colspan="5" class="text-center text-muted py-4">No pending profile change requests.</td></tr>';
    this.bindProfileChangeActions();
  }

  profileChangeDetail(request) {
    const rows = Object.keys(request.requested_values || {}).map((field) => `<div class="profile-review-row"><span>${this.escape(this.label(field))}</span><span>${this.escape(request.original_values?.[field] || '(empty)')} → ${this.escape(request.requested_values?.[field] || '(empty)')}</span></div>`);
    if (request.requested_photo) {
      const originalPhoto = this.safePhotoPath(request.original_photo);
      const requestedPhoto = this.safePhotoPath(request.requested_photo);
      rows.push(`<div class="profile-review-row"><span>Profile photo</span><span class="profile-review-photos">${originalPhoto ? `<img src="${this.escape(originalPhoto)}" alt="Current profile photo">` : '<span>(none)</span>'}<span aria-hidden="true">→</span><img src="${this.escape(requestedPhoto)}" alt="Requested profile photo"></span></div>`);
    }
    return rows.join('') || '<p class="text-muted">No text fields changed.</p>';
  }

  bindProfileChangeActions() {
    this.root.querySelectorAll?.('[data-profile-request-id]').forEach((button) => button.addEventListener('click', () => {
      this.selectedProfileChange = this.profileChanges.find((row) => String(row.id) === String(button.dataset.profileRequestId)) || null;
      if (!this.selectedProfileChange) return;
      const title = this.root.querySelector?.('#profileChangeModalTitle');
      const detail = this.root.querySelector?.('#profile-change-detail');
      if (title) title.textContent = `Review ${this.selectedProfileChange.user_name || 'profile'}'s request`;
      if (detail) detail.innerHTML = this.profileChangeDetail(this.selectedProfileChange);
      const modal = this.window?.bootstrap?.Modal?.getOrCreateInstance(this.root.querySelector?.('#profileChangeModal'));
      modal?.show();
    }));
    this.root.querySelectorAll?.('[data-profile-decision]').forEach((button) => button.addEventListener('click', () => this.decideProfileChange(button.dataset.profileDecision)));
  }

  async decideProfileChange(action) {
    if (!this.selectedProfileChange || !this.profileService) return;
    const note = this.root.querySelector?.('#profile-change-review-note')?.value || '';
    const confirmation = this.window?.Scan2BorrowConfirmation;
    const proceed = () => this.profileService.action(action, this.selectedProfileChange.id, note).then(() => this.load());
    try {
      if (confirmation?.confirm) await confirmation.confirm({ title: action === 'approve' ? 'Approve profile change?' : 'Reject profile change?', message: `This will ${action} the requested account changes.`, confirmLabel: action === 'approve' ? 'Approve' : 'Reject', confirmClass: action === 'approve' ? 'btn-primary' : 'btn-danger', onConfirm: proceed });
      else await proceed();
      this.showFeedback('success', action === 'approve' ? 'Profile change approved.' : 'Profile change rejected.');
    } catch (error) {
      this.showFeedback('error', error.message || 'Could not save profile change decision.');
    }
  }

  label(field) { return String(field || '').replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase()); }
  safePhotoPath(path) { if (!path || typeof path !== 'string') return ''; return path.startsWith('/scan2borrow/uploads/') || path.startsWith('uploads/') ? (path.startsWith('uploads/') ? `/scan2borrow/${path}` : path) : ''; }

  escape(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character])); }
}
