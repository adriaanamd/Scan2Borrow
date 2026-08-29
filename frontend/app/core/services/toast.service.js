export class ToastService {
  constructor({ document = globalThis.document } = {}) {
    this.document = document;
  }

  show(message, type = 'info') {
    const host = this.document.getElementById('toast-host');
    if (!host) return null;

    const toast = this.document.createElement('div');
    toast.className = 'toast align-items-center text-bg-' + type + ' border-0';
    toast.setAttribute('role', 'alert');
    toast.innerHTML = '<div class="d-flex"><div class="toast-body"></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
    toast.querySelector('.toast-body').textContent = String(message ?? '');
    host.appendChild(toast);
    return toast;
  }

  hideAll() {
    this.document.querySelectorAll('#toast-host .toast').forEach((toast) => toast.remove());
  }
}
