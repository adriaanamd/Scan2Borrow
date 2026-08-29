export class GuestRegistrationPage {
  constructor(root, {
    auth,
    camera,
    document = globalThis.document,
    window = globalThis.window,
    formDataFactory = (form) => new FormData(form),
  }) {
    this.root = root;
    this.auth = auth;
    this.camera = camera;
    this.document = document;
    this.window = window;
    this.formDataFactory = formDataFactory;
    this.form = document.getElementById('guest-reg-form');
    this.purpose = document.getElementById('purpose');
    this.otherPurpose = document.getElementById('otherPurposeWrap');
    this.currentStep = 'details';
    this.listeners = [];
    this.bindEvents();
    this.bindStepNavigation();
    this.showStep('details');
  }

  listen(target, eventName, callback) {
    if (!target?.addEventListener) return;
    target.addEventListener(eventName, callback);
    this.listeners.push(() => target.removeEventListener?.(eventName, callback));
  }

  bindEvents() {
    this.listen(this.purpose, 'change', () => this.togglePurpose());
    this.togglePurpose();
    this.listen(this.form, 'submit', (event) => this.submit(event));
  }

  bindStepNavigation() {
    this.listen(this.document.getElementById('guest-details-continue'), 'click', () => {
      if (!this.form || !this.form.reportValidity()) return;
      this.showStep('photo');
    });
    this.listen(this.document.getElementById('guest-photo-back'), 'click', () => {
      this.camera?.stop();
      this.showStep('details');
    });
  }

  showStep(step) {
    this.currentStep = step;
    this.document.querySelectorAll('[data-guest-registration-step]').forEach((section) => {
      section.hidden = section.dataset.guestRegistrationStep !== step;
    });
    this.document.querySelectorAll('[data-guest-progress-step]').forEach((indicator) => {
      const isDetails = indicator.dataset.guestProgressStep === 'details';
      indicator.classList.toggle('is-current', indicator.dataset.guestProgressStep === step);
      indicator.classList.toggle('is-complete', step === 'photo' && isDetails);
    });
  }

  togglePurpose() {
    this.otherPurpose?.classList.toggle('d-none', this.purpose?.value !== 'Others');
  }

  async submit(event) {
    event.preventDefault();
    try {
      const response = await this.auth.registerGuest(this.formDataFactory(this.form));
      if (!response.ok) throw new Error(response.errors?.[0] || 'Registration failed.');
      this.window.location.href = '/scan2borrow/guest/verify-otp';
    } catch (error) {
      const errorBox = this.document.getElementById('form-error');
      if (!errorBox) return;
      errorBox.hidden = false;
      errorBox.textContent = error.message;
    }
  }

  destroy() {
    this.listeners.splice(0).forEach((remove) => remove());
    this.camera?.destroy?.();
  }
}
