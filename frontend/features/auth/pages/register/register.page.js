export class RegistrationPage {
  constructor(root, {
    document = globalThis.document,
    window = globalThis.window,
    api = null,
    auth,
    camera = null,
    formDataFactory = (form) => new FormData(form),
  }) {
    this.root = root;
    this.document = document;
    this.window = window;
    this.api = api;
    this.auth = auth;
    this.camera = camera;
    this.formDataFactory = formDataFactory;
    this.form = document.getElementById('reg-form');
    this.role = document.getElementById('role_select');
    this.emailInput = document.querySelector?.('input[name="email"]');
    this.phoneInput = document.querySelector?.('input[name="contact_no"]');
    this.courseSelect = document.querySelector?.('select[name="course"]');
    this.otpChannelInputs = [...(document.querySelectorAll?.('input[name="otp_channel"]') || [])];
    this.currentStep = 'role';
    this.listeners = [];
    this.bindRoleSelection();
    this.listen(this.form, 'submit', (event) => this.submit(event));
    this.otpChannelInputs.forEach((input) => this.listen(input, 'change', () => this.syncOtpChannel()));
    this.listen(this.emailInput, 'input', () => this.syncOtpChannel());
    this.listen(this.phoneInput, 'input', () => this.syncOtpChannel());

    const preselectedRole = new URLSearchParams(window.location.search).get('role');
    if (preselectedRole) {
      this.role.value = preselectedRole;
      this.toggleFields();
      this.showStep('details');
    } else {
      this.toggleFields();
      this.showStep('role');
    }
    this.syncOtpChannel();
  }

  start() {
    this.loadCourses();
    return this;
  }

  async loadCourses() {
    if (!this.courseSelect || !this.api) return;
    try {
      const response = await this.api.get('/scan2borrow/api/courses', {});
      const courses = Array.isArray(response?.data?.courses) ? response.data.courses : [];
      const selected = this.courseSelect.value;
      this.courseSelect.replaceChildren();
      const placeholder = this.document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = 'Select Course';
      this.courseSelect.appendChild(placeholder);
      courses.forEach((course) => {
        const option = this.document.createElement('option');
        option.value = String(course.name || '');
        option.textContent = String(course.name || '');
        this.courseSelect.appendChild(option);
      });
      this.courseSelect.value = courses.some((course) => course.name === selected) ? selected : '';
      this.courseSelect.disabled = false;
    } catch {
      this.courseSelect.disabled = true;
      this.message('form-error', 'Unable to load the course list. Please try again.');
    }
  }

  listen(target, eventName, callback) {
    if (!target?.addEventListener) return;
    target.addEventListener(eventName, callback);
    this.listeners.push(() => target.removeEventListener?.(eventName, callback));
  }

  bindRoleSelection() {
    this.listen(this.role, 'change', () => this.toggleFields());
    this.listen(this.document.getElementById('chooseStudent'), 'click', () => this.selectRole('student'));
    this.listen(this.document.getElementById('chooseTeacher'), 'click', () => this.selectRole('teacher'));
    this.listen(this.document.getElementById('chooseGuest'), 'click', () => {
      this.window.location.href = '/scan2borrow/guest/registration';
    });
    this.listen(this.document.getElementById('registration-continue'), 'click', () => {
      if (!this.role?.value) {
        this.message('form-error', 'Choose an account type to continue.');
        return;
      }
      this.showStep('details');
    });
    this.listen(this.document.getElementById('registration-back'), 'click', () => this.showStep('role'));
    this.listen(this.document.getElementById('registration-details-continue'), 'click', () => {
      if (this.form?.reportValidity()) this.showStep('photo');
    });
    this.listen(this.document.getElementById('registration-photo-back'), 'click', () => this.showStep('details'));
  }

  selectRole(role) {
    if (!this.role) return;
    this.role.value = role;
    this.toggleFields();
  }

  toggleFields() {
    const isStudent = this.role?.value === 'student';
    const isTeacher = this.role?.value === 'teacher';
    this.document.querySelectorAll('.student-only').forEach((field) => {
      field.classList.toggle('d-none', !isStudent);
    });
    this.document.querySelectorAll('.teacher-only').forEach((field) => {
      field.classList.toggle('d-none', !isTeacher);
    });
    if (this.courseSelect) this.courseSelect.required = isStudent;
    this.document.querySelectorAll('.registration-role-choice').forEach((choice) => {
      const selected = choice.dataset.role === this.role?.value;
      choice.classList.toggle('is-selected', selected);
      choice.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
    const label = this.document.getElementById('selected-role-label');
    if (label) label.textContent = isStudent ? 'Student' : isTeacher ? 'Teacher' : 'Select a role';
  }

  syncOtpChannel() {
    const hasEmail = this.emailInput?.value.trim() !== '';
    const hasPhone = this.phoneInput?.value.trim() !== '';
    let selected = this.otpChannelInputs.find((input) => input.checked);

    if (!selected && hasEmail !== hasPhone) {
      const preferred = hasEmail ? 'email' : 'phone';
      this.otpChannelInputs.forEach((input) => { input.checked = input.value === preferred; });
      selected = this.otpChannelInputs.find((input) => input.checked);
    }

    const channel = selected?.value || '';
    if (this.emailInput) this.emailInput.required = channel === 'email';
    if (this.phoneInput) this.phoneInput.required = channel === 'phone';
    this.otpChannelInputs.forEach((input, index) => {
      input.required = channel === '' && index === 0;
    });
  }

  showStep(step) {
    const steps = ['role', 'details', 'photo'];
    const currentIndex = steps.indexOf(step);
    if (currentIndex < 0) return;
    this.currentStep = step;
    this.document.querySelectorAll('[data-registration-step]').forEach((section) => {
      const active = section.dataset.registrationStep === step;
      section.hidden = !active;
      section.classList.toggle('d-none', !active);
    });
    this.document.querySelectorAll('[data-progress-step]').forEach((indicator) => {
      const indicatorIndex = steps.indexOf(indicator.dataset.progressStep);
      indicator.classList.toggle('is-current', indicatorIndex === currentIndex);
      indicator.classList.toggle('is-complete', indicatorIndex < currentIndex);
    });
    this.document.getElementById('form-error')?.setAttribute('hidden', '');
    if (step === 'details') {
      this.document.querySelector('input[name="barcode"]')?.focus({ preventScroll: true });
    }
  }

  async submit(event) {
    event.preventDefault();
    try {
      const response = await this.auth.register(this.formDataFactory(this.form));
      if (!response.ok) throw new Error(response.errors?.[0] || 'Registration failed.');
      const redirect = response.data?.redirect || '/scan2borrow/verify-otp';
      const channel = ['email', 'phone'].includes(response.data?.channel) ? response.data.channel : '';
      const suffix = channel === '' ? '' : `${redirect.includes('?') ? '&' : '?'}channel=${encodeURIComponent(channel)}`;
      this.window.location.href = redirect + suffix;
    } catch (error) {
      this.message('form-error', error.message);
    }
  }

  message(id, text) {
    const box = this.document.getElementById(id);
    if (!box) return;
    box.hidden = false;
    box.textContent = text;
  }

  destroy() {
    this.listeners.splice(0).forEach((remove) => remove());
    this.camera?.destroy?.();
  }
}
