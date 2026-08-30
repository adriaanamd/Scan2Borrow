export class RegistrationPage {
  constructor(root, {
    document = globalThis.document,
    window = globalThis.window,
    navigator = globalThis.navigator,
    auth,
    formDataFactory = (form) => new FormData(form),
  }) {
    this.root = root;
    this.document = document;
    this.window = window;
    this.navigator = navigator;
    this.auth = auth;
    this.formDataFactory = formDataFactory;
    this.form = document.getElementById('reg-form');
    this.role = document.getElementById('role_select');
    this.emailInput = document.querySelector?.('input[name="email"]');
    this.phoneInput = document.querySelector?.('input[name="contact_no"]');
    this.otpChannelInputs = [...(document.querySelectorAll?.('input[name="otp_channel"]') || [])];
    this.currentStep = 'role';
    this.listeners = [];
    this.cameraCleanup = null;
    this.bindRoleSelection();
    this.bindCamera();
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
    return this;
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

  bindCamera() {
    const video = this.document.getElementById('cam');
    const canvas = this.document.getElementById('snap');
    const preview = this.document.getElementById('preview');
    const field = this.document.getElementById('photo_data');
    const message = this.document.getElementById('cam-msg');
    const start = this.document.getElementById('btn-start');
    const capture = this.document.getElementById('btn-capture');
    const retake = this.document.getElementById('btn-retake');
    if (!video || !canvas || !preview || !field || !message || !start || !capture || !retake) return;

    let stream = null;
    const stop = () => {
      if (!stream) return;
      stream.getTracks().forEach((track) => track.stop());
      stream = null;
    };
    this.listen(start, 'click', () => {
      if (!this.navigator.mediaDevices?.getUserMedia) {
        message.textContent = 'Camera not supported on this browser.';
        return;
      }
      message.textContent = 'Starting camera...';
      this.navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
        .then((nextStream) => {
          stream = nextStream;
          video.srcObject = stream;
          video.classList.remove('d-none');
          preview.classList.add('d-none');
          start.classList.add('d-none');
          capture.classList.remove('d-none');
          retake.classList.add('d-none');
          message.textContent = 'Position your face in the frame, then Capture.';
        })
        .catch(() => {
          message.textContent = 'Could not access the camera. Please allow camera permission.';
        });
    });
    this.listen(capture, 'click', () => {
      if (!stream) return;
      const width = video.videoWidth || 320;
      const height = video.videoHeight || 240;
      canvas.width = width;
      canvas.height = height;
      canvas.getContext('2d').drawImage(video, 0, 0, width, height);
      field.value = canvas.toDataURL('image/jpeg', 0.85);
      preview.src = field.value;
      preview.classList.remove('d-none');
      video.classList.add('d-none');
      capture.classList.add('d-none');
      retake.classList.remove('d-none');
      stop();
      message.textContent = 'Photo captured. Click Retake to redo.';
    });
    this.listen(retake, 'click', () => {
      field.value = '';
      preview.classList.add('d-none');
      retake.classList.add('d-none');
      start.classList.remove('d-none');
      start.click();
    });
    this.cameraCleanup = () => {
      stop();
      this.window.removeEventListener?.('beforeunload', stop);
    };
    this.window.addEventListener?.('beforeunload', stop);
  }

  async submit(event) {
    event.preventDefault();
    try {
      const response = await this.auth.register(this.formDataFactory(this.form));
      if (!response.ok) throw new Error(response.errors?.[0] || 'Registration failed.');
      this.window.location.href = response.data.redirect || '/scan2borrow/verify-otp';
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
    this.cameraCleanup?.();
  }
}
