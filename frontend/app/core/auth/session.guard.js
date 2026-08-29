export class SessionGuard {
  constructor({ session, window, publicPaths = [] }) {
    this.session = session;
    this.window = window;
    this.publicPaths = publicPaths;
  }

  async boot() {
    if (this.publicPaths.includes(this.window.location.pathname)) {
      return null;
    }

    try {
      return await this.session.load();
    } catch (error) {
      if (error.status === 401 || error.status === 403) {
        this.session.clear();
        this.window.location.href = '/scan2borrow/login';
        return null;
      }

      throw error;
    }
  }
}
