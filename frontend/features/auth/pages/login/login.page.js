export class LoginPage {
  constructor(root, {
    auth,
    window = globalThis.window,
    formDataFactory = (form) => new FormData(form),
  }) {
    this.root = root;
    this.auth = auth;
    this.window = window;
    this.formDataFactory = formDataFactory;
    this.form = null;
    this.submitHandler = null;
  }

  start() {
    this.form = this.root.querySelector('#borrower-login-form, #staff-login-form');
    if (!this.form) return;

    this.submitHandler = (event) => this.submit(event);
    this.form.addEventListener('submit', this.submitHandler);
  }

  async submit(event) {
    event.preventDefault();
    const body = this.formDataFactory(this.form);
    const request = this.form.id === 'staff-login-form'
      ? this.auth.loginStaff(body)
      : this.auth.loginBorrower(body);

    try {
      const response = await request;
      if (!response.ok) {
        if (response.data?.registration_required) {
          this.redirectToRegistration(response.data.role);
          return;
        }
        throw new Error(response.errors?.[0] || 'Login failed.');
      }
      this.window.location.href = response.data.redirect;
    } catch (error) {
      this.showMessage('login-error', error.message);
    }
  }

  redirectToRegistration(role) {
    const selectedRole = role === 'teacher' ? 'teacher' : 'student';
    this.window.location.href = '/scan2borrow/register?role=' + encodeURIComponent(selectedRole);
  }

  showMessage(id, message) {
    const node = this.root.querySelector('#' + id);
    if (!node) return;
    node.hidden = false;
    node.textContent = message;
  }

  destroy() {
    if (this.form && this.submitHandler) {
      this.form.removeEventListener?.('submit', this.submitHandler);
    }
  }
}
