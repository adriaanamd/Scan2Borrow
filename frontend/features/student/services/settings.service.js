export class StudentSettingsService {
  constructor({ api }) {
    this.api = api;
  }

  load() {
    return this.api.get('/scan2borrow/api/student/dashboard', {});
  }
}
