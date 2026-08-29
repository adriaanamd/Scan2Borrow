export class OtpPage {
  constructor(root, {
    auth,
    document = globalThis.document,
    window = globalThis.window,
    formDataFactory = (form) => new FormData(form),
  }) {
    this.root = root;
    this.auth = auth;
    this.document = document;
    this.window = window;
    this.formDataFactory = formDataFactory;
    this.form = document.getElementById('otpForm');
    this.resendForm = document.getElementById('resend-form');
    this.input = this.form?.querySelector('input[name="otp"]');
    this.expiresIn = 300;
    this.intervalId = null;
    this.listeners = [];
    this.bindEvents();
    this.startCountdown();
  }

  listen(target, eventName, callback) {
    if (!target?.addEventListener) return;
    target.addEventListener(eventName, callback);
    this.listeners.push(() => target.removeEventListener?.(eventName, callback));
  }

  bindEvents() {
    this.listen(this.form, 'submit', (event) => this.submit(event));
    this.listen(this.resendForm, 'submit', (event) => this.resend(event));
    this.listen(this.input, 'input', () => {
      this.input.value = this.input.value.replace(/[^0-9]/g, '');
      if (this.input.value.length === 6) this.form.requestSubmit();
    });
  }

  startCountdown() {
    const node = this.document.getElementById('countdown');
    this.intervalId = this.window.setInterval(() => {
      if (this.expiresIn > 0) this.expiresIn -= 1;
      const minutes = Math.floor(this.expiresIn / 60);
      const seconds = String(this.expiresIn % 60).padStart(2, '0');
      if (node) node.textContent = `${minutes}:${seconds}`;
    }, 1000);
  }

  async submit(event) {
    event.preventDefault();
    try {
      const response = await this.auth.verifyOtp(this.formDataFactory(this.form));
      if (!response.ok) throw new Error(response.errors?.[0] || 'Invalid or expired OTP code.');
      this.window.location.href = response.data.redirect || '/scan2borrow/login';
    } catch (error) {
      this.message('form-error', error.message);
    }
  }

  async resend(event) {
    event.preventDefault();
    const response = await this.auth.resendOtp(this.formDataFactory(this.resendForm));
    this.message(
      response.ok ? 'form-success' : 'form-error',
      response.message || response.errors?.[0] || 'Unable to resend OTP.',
    );
  }

  message(id, text) {
    const node = this.document.getElementById(id);
    if (!node) return;
    node.hidden = false;
    node.textContent = text;
  }

  destroy() {
    this.listeners.splice(0).forEach((remove) => remove());
    if (this.intervalId !== null) this.window.clearInterval(this.intervalId);
  }
}
