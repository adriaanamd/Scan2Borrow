export class TeacherProfileChangeService {
  constructor({ api }) { this.api = api; }
  load() { return this.api.get('/scan2borrow/api/teacher/settings', {}); }
  submit(formData) { return this.api.post('/scan2borrow/api/teacher/settings', formData); }
}
