export class ReservationQueueComponent {
  constructor(root, { service, onChanged = () => {} } = {}) {
    this.root = root;
    this.service = service;
    this.onChanged = onChanged;
    this.boundClick = (event) => this.handleClick(event);
    this.root?.addEventListener?.('click', this.boundClick);
  }

  async load() {
    this.renderLoading();
    try {
      const response = await this.service.list();
      const holds = response?.data?.holds || [];
      this.render(holds);
      return holds;
    } catch (error) {
      this.root.innerHTML = '<section class="reservation-queue reservation-queue--error"><p>Holds are unavailable right now. Please try again.</p></section>';
      return [];
    }
  }

  renderLoading() {
    this.root.innerHTML = '<section class="reservation-queue"><p class="reservation-queue__loading">Loading holds…</p></section>';
  }

  render(holds) {
    if (!holds.length) {
      this.root.innerHTML = '<section class="reservation-queue"><div class="reservation-queue__header"><span class="reservation-queue__eyebrow">Circulation</span><h2>Your holds</h2></div><p class="reservation-queue__empty">You are not waiting for any unavailable titles.</p></section>';
      return;
    }

    const cards = holds.map((hold) => {
      const position = Number(hold.queue_position || 0);
      const action = hold.status === 'offered'
        ? `<button type="button" class="reservation-queue__button reservation-queue__button--primary" data-reservation-action="claim" data-hold-id="${this.escape(hold.id)}">Claim hold</button>`
        : '';
      const cancel = ['queued', 'offered', 'claimed'].includes(hold.status)
        ? `<button type="button" class="reservation-queue__button" data-reservation-action="cancel" data-hold-id="${this.escape(hold.id)}">Cancel</button>`
        : '';
      const expiry = hold.hold_expires_at ? `<p class="reservation-queue__expiry">Collect by ${this.escape(hold.hold_expires_at)}</p>` : '';
      return `<article class="reservation-queue__item"><div class="reservation-queue__rail"><span class="reservation-queue__node">${position > 0 ? String(position).padStart(2, '0') : '—'}</span></div><div class="reservation-queue__content"><p class="reservation-queue__position">${position > 0 ? `Queue ${String(position).padStart(2, '0')}` : 'Hold'}</p><h3>${this.escape(hold.title)}</h3><p class="reservation-queue__author">${this.escape(hold.author || 'Author unavailable')}</p><span class="reservation-queue__status">${this.escape(hold.status_label || hold.status || 'Pending')}</span>${expiry}<div class="reservation-queue__actions">${action}${cancel}</div></div></article>`;
    }).join('');

    this.root.innerHTML = `<section class="reservation-queue"><div class="reservation-queue__header"><span class="reservation-queue__eyebrow">Circulation</span><h2>Your holds</h2><p>Fair queue order, with a 24-hour collection window when a copy becomes available.</p></div><div class="reservation-queue__list">${cards}</div></section>`;
  }

  async handleClick(event) {
    const button = event.target?.closest?.('[data-reservation-action]');
    if (!button) return;
    button.disabled = true;
    try {
      await this.service.action(button.dataset.holdId, button.dataset.reservationAction);
      await this.load();
      this.onChanged();
    } catch {
      button.disabled = false;
    }
  }

  escape(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[character]);
  }

  destroy() {
    this.root?.removeEventListener?.('click', this.boundClick);
  }
}
