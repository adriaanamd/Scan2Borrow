export class TeacherSettingsService {
  constructor({ api }) {
    this.api = api;
  }

  load() {
    return this.api.get('/scan2borrow/api/teacher/dashboard', {});
  }

  profile() {
    return this.api.get('/scan2borrow/api/teacher/settings', {});
  }

  submitProfile(formData) {
    return this.api.post('/scan2borrow/api/teacher/settings', formData);
  }
}
