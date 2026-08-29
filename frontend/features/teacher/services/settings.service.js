export class TeacherSettingsService {
  constructor({ api }) {
    this.api = api;
  }

  load() {
    return this.api.get('/scan2borrow/api/teacher/dashboard', {});
  }
}
