export class AdminStaffService {
  constructor({ api }) { this.api = api; }

  list(search = '') { return this.api.get('/scan2borrow/api/admin/staff', { bsearch: search }); }

  action(action, userId, values = {}) { return this.api.post('/scan2borrow/api/admin/staff-action', { ...values, action, user_id: userId }); }
}
