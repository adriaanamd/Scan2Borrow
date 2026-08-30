export class ReservationService {
  constructor({ api, role = 'student' }) {
    this.api = api;
    this.role = role === 'teacher' ? 'teacher' : 'student';
  }

  list() {
    return this.api.get(`/scan2borrow/api/${this.role}/holds`);
  }

  join(titleId) {
    return this.api.post(`/scan2borrow/api/${this.role}/holds`, { title_id: Number(titleId) });
  }

  action(holdId, action) {
    return this.api.post(`/scan2borrow/api/${this.role}/holds/action`, {
      hold_id: Number(holdId),
      action,
    });
  }
}
