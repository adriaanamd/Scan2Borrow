export class GuestOtpPage {
  constructor(root, {
    auth,
    document = globalThis.document,
    window = globalThis.window,
    formDataFactory = (form) => new FormData(form),
    formId = 'guest-otp-form',
    redirect = '/scan2borrow/guest/dashboard',
    resendMessage = 'Please wait before requesting another code.',
  }) {
    this.root = root;
    this.auth = auth;
    this.document = document;
    this.window = window;
    this.formDataFactory = formDataFactory;
    this.form = document.getElementById(formId);
    this.resend = document.getElementById('resend-form');
    this.redirect = redirect;
    this.resendMessage = resendMessage;
    this.expiresIn = 60;
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
    this.listen(this.resend, 'submit', (event) => this.resendOtp(event));
  }

  start() {
    return this;
  }

  startCountdown() {
    this.renderCountdown();
    if (typeof this.window?.setInterval !== 'function') return;
    this.intervalId = this.window.setInterval(() => {
      if (this.expiresIn > 0) this.expiresIn -= 1;
      this.renderCountdown();
    }, 1000);
  }

  renderCountdown() {
    const node = this.document.getElementById('expires-in');
    if (!node) return;
    const minutes = Math.floor(this.expiresIn / 60);
    const seconds = String(this.expiresIn % 60).padStart(2, '0');
    node.textContent = `${minutes}:${seconds}`;
  }

  async submit(event) {
    event.preventDefault();
    try {
      const response = await this.auth.verifyGuestOtp(this.formDataFactory(this.form));
      if (!response.ok) throw new Error(response.errors?.[0] || 'Invalid or expired OTP code.');
      this.window.location.href = response.data?.redirect || this.redirect;
    } catch (error) {
      this.message('form-error', error.message);
    }
  }

  async resendOtp(event) {
    event.preventDefault();
    const response = await this.auth.resendGuestOtp(this.formDataFactory(this.resend));
    if (response.ok) {
      this.expiresIn = 60;
      this.renderCountdown();
    }
    this.message(
      response.ok ? 'form-success' : 'form-error',
      response.message || response.errors?.[0] || this.resendMessage,
    );
  }

  message(id, text) {
    const box = this.document.getElementById(id);
    if (!box) return;
    box.hidden = false;
    box.textContent = text;
  }

  destroy() {
    this.listeners.splice(0).forEach((remove) => remove());
    if (this.intervalId !== null) this.window.clearInterval?.(this.intervalId);
  }
}
