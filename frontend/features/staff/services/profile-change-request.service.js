export class ProfileChangeRequestService {
  constructor({ api }) { this.api = api; }
  list() { return this.api.get('/scan2borrow/api/admin/profile-change-requests', {}); }
  action(action, requestId, reviewNote = '') { return this.api.post('/scan2borrow/api/admin/profile-change-request-action', { action, request_id: requestId, review_note: reviewNote }); }
}
