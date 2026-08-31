export class StudentProfileChangeService {
  constructor({ api }) { this.api = api; }
  load() { return this.api.get('/scan2borrow/api/student/settings', {}); }
  submit(formData) { return this.api.post('/scan2borrow/api/student/settings', formData); }
}
