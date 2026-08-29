export class StaffDashboardService {
  constructor({ api, window = globalThis.window }) {
    this.api = api;
    this.window = window;
    this.pollId = null;
  }

  load() {
    return this.api.get('/scan2borrow/api/staff/dashboard', {});
  }

  notifications() {
    return this.api.get('/scan2borrow/api/staff/notifications', { action: 'pending_approvals' });
  }

  startPolling(callback, interval = 5000) {
    this.stopPolling();
    this.pollId = this.window.setInterval(async () => callback(await this.notifications()), interval);
    return this.pollId;
  }

  stopPolling() {
    if (this.pollId !== null) this.window.clearInterval(this.pollId);
    this.pollId = null;
  }
}
