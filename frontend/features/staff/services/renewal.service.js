export class StaffRenewalService {
  constructor({ api }) { this.api = api; }
  list() { return this.api.get('/scan2borrow/api/staff/renewals'); }
  decide(renewalId, action, note = '') { return this.api.post('/scan2borrow/api/staff/renewals/action', { renewal_id: Number(renewalId), action, note }); }
}
