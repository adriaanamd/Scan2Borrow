export class StudentSettingsService {
  constructor({ api }) {
    this.api = api;
  }

  load() {
    return this.api.get('/scan2borrow/api/student/dashboard', {});
  }

  profile() {
    return this.api.get('/scan2borrow/api/student/settings', {});
  }

  submitProfile(formData) {
    return this.api.post('/scan2borrow/api/student/settings', formData);
  }
}
