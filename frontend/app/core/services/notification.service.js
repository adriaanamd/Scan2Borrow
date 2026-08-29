export class NotificationService {
  constructor({
    api,
    setIntervalImpl = globalThis.setInterval,
    clearIntervalImpl = globalThis.clearInterval,
  }) {
    this.api = api;
    this.setIntervalImpl = setIntervalImpl;
    this.clearIntervalImpl = clearIntervalImpl;
    this.timer = null;
  }

  poll() {
    return this.api.get('/scan2borrow/api/staff/notifications');
  }

  start(onUpdate, interval = 5000) {
    this.stop();
    this.timer = this.setIntervalImpl(async () => {
      const payload = await this.poll();
      onUpdate(payload);
    }, interval);
    return this.timer;
  }

  stop() {
    if (this.timer !== null) {
      this.clearIntervalImpl(this.timer);
      this.timer = null;
    }
  }
}
