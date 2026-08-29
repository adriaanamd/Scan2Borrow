export class StaffApprovalService {
  constructor({ api }) { this.api = api; }

  submit(action, borrowingId) {
    return this.api.post('/scan2borrow/api/staff/borrowing-action', {
      action,
      borrowing_id: borrowingId,
    });
  }
}
