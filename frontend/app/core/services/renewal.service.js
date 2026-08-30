export class RenewalService {
  constructor({ api, role = 'student' }) {
    this.api = api;
    this.role = role === 'teacher' ? 'teacher' : 'student';
  }

  list() { return this.api.get(`/scan2borrow/api/${this.role}/renewals`); }

  request(loanId, reason = '') {
    return this.api.post(`/scan2borrow/api/${this.role}/renewals`, { loan_id: Number(loanId), reason });
  }
}
