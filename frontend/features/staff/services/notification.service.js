export class StaffNotificationService {
  constructor({ api, window = globalThis.window }) {
    this.api = api;
    this.window = window;
    this.pollId = null;
  }

  load(params = { action: 'pending_approvals' }) {
    return this.api.get('/scan2borrow/api/staff/notifications', { ...params });
  }

  markViewed(notificationId, notificationType) {
    return this.api.post('/scan2borrow/api/staff/notifications/viewed', {
      notification_id: notificationId,
      notification_type: notificationType,
    });
  }

  send(userId, channel) {
    return this.api.post('/scan2borrow/api/staff/notify', { user_id: userId, channel });
  }

  start(callback, interval = 5000) {
    this.stop();
    this.pollId = this.window.setInterval(async () => callback(await this.load()), interval);
    return this.pollId;
  }

  stop() {
    if (this.pollId !== null) this.window.clearInterval(this.pollId);
    this.pollId = null;
  }
}
