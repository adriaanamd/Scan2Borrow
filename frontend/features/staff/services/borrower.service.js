export class BorrowerService {
  constructor({ api }) { this.api = api; }

  search(search = '') { return this.api.get('/scan2borrow/api/staff/borrowers', { search }); }

  details(id) { return this.api.get('/scan2borrow/api/staff/borrower', { id }); }

  updatePhoto(userId, photoData) { return this.api.post('/scan2borrow/api/staff/borrower/photo', { user_id: userId, photo_data: photoData }); }

  notify(userId, channel) { return this.api.post('/scan2borrow/api/staff/notify', { user_id: userId, channel }); }
}
