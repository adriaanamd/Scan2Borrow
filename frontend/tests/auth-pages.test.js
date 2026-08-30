import test from 'node:test';
import assert from 'node:assert/strict';
import { AuthService } from '../features/auth/services/auth.service.js';
import { LoginPage } from '../features/auth/pages/login/login.page.js';
import { RegistrationPage } from '../features/auth/pages/register/register.page.js';
import { OtpPage } from '../features/auth/pages/otp/otp.page.js';
import { GuestRegistrationPage } from '../features/auth/pages/guest-registration/guest-registration.page.js';
import { GuestOtpPage } from '../features/auth/pages/guest-otp/guest-otp.page.js';
import { ProfileOtpPage } from '../features/auth/pages/profile-otp/profile-otp.page.js';
import { boot as bootGuestRegistration } from '../features/auth/pages/guest-registration/entry.js';
import { boot as bootGuestOtp } from '../features/auth/pages/guest-otp/entry.js';
import { boot as bootProfileOtp } from '../features/auth/pages/profile-otp/entry.js';

test('AuthService preserves borrower and staff login endpoints', async () => {
  const calls = [];
  const auth = new AuthService({
    api: {
      post: async (path, body) => {
        calls.push({ path, body });
        return { ok: true, data: { redirect: '/scan2borrow/student/dashboard' } };
      },
    },
  });

  await auth.loginBorrower({ barcode: 'S-1' });
  await auth.loginStaff({ barcode: 'L-1', password: 'secret' });

  assert.deepEqual(calls, [
    { path: '/scan2borrow/api/auth/borrower/login', body: { barcode: 'S-1' } },
    { path: '/scan2borrow/api/auth/staff/login', body: { barcode: 'L-1', password: 'secret' } },
  ]);
});

test('LoginPage submits the bounded form and follows the API redirect', async () => {
  let submit;
  const form = {
    id: 'borrower-login-form',
    addEventListener(name, callback) { if (name === 'submit') submit = callback; },
  };
  const errors = { hidden: true, textContent: '' };
  const root = {
    querySelector(selector) {
      if (selector === '#borrower-login-form, #staff-login-form') return form;
      if (selector === '#login-error') return errors;
      return null;
    },
  };
  let redirected = '';
  const auth = { loginBorrower: async () => ({ ok: true, data: { redirect: '/next' } }) };
  const page = new LoginPage(root, {
    auth,
    window: { location: { set href(value) { redirected = value; } } },
    formDataFactory: () => ({ barcode: 'S-1' }),
  });

  page.start();
  await submit({ preventDefault() {} });
  assert.equal(redirected, '/next');
});

test('RegistrationPage preserves preselected role and details step', () => {
  const form = { addEventListener() {}, reportValidity: () => true };
  const role = { value: '' };
  const document = {
    querySelector: () => null,
    querySelectorAll: () => [],
    getElementById(id) {
      if (id === 'reg-form') return form;
      if (id === 'role_select') return role;
      return null;
    },
  };
  const page = new RegistrationPage({ querySelector: () => null }, {
    document,
    window: { location: { search: '?role=teacher' } },
    auth: { register: async () => ({ ok: true }) },
  });

  assert.equal(page.currentStep, 'details');
  assert.equal(role.value, 'teacher');
});

test('RegistrationPage selects and validates the OTP delivery channel', async () => {
  let submit;
  let emailInputListener;
  let phoneInputListener;
  let submittedBody;
  const form = {
    addEventListener(name, callback) { if (name === 'submit') submit = callback; },
    reportValidity: () => true,
  };
  const role = { value: 'student', addEventListener() {} };
  const email = {
    value: '',
    required: false,
    addEventListener(name, callback) { if (name === 'input') emailInputListener = callback; },
  };
  const phone = {
    value: '09170000004',
    required: false,
    addEventListener(name, callback) { if (name === 'input') phoneInputListener = callback; },
  };
  const channels = ['email', 'phone'].map((value) => ({ value, checked: false, listeners: [],
    addEventListener(name, callback) { if (name === 'change') this.listeners.push(callback); },
    click() {
      channels.forEach((channel) => { channel.checked = false; });
      this.checked = true;
      this.listeners.forEach((listener) => listener());
    },
  }));
  const messages = { hidden: true, textContent: '', setAttribute() {} };
  const document = {
    querySelector(selector) {
      if (selector === 'input[name="email"]') return email;
      if (selector === 'input[name="contact_no"]') return phone;
      return null;
    },
    querySelectorAll(selector) {
      if (selector === 'input[name="otp_channel"]') return channels;
      return [];
    },
    getElementById(id) {
      if (id === 'reg-form') return form;
      if (id === 'role_select') return role;
      if (id === 'form-error') return messages;
      return null;
    },
  };
  let redirected = '';
  const page = new RegistrationPage({}, {
    document,
    window: { location: { search: '', set href(value) { redirected = value; } } },
    auth: { register: async (body) => { submittedBody = body; return { ok: true, data: { redirect: '/verify-otp', channel: 'phone' } }; } },
    formDataFactory: () => ({ otp_channel: 'phone' }),
  });

  assert.equal(channels[1].checked, true);
  assert.equal(phone.required, true);
  assert.equal(email.required, false);

  channels[0].click();
  assert.equal(email.required, true);
  assert.equal(phone.required, false);

  email.value = 'lia@example.test';
  phoneInputListener();
  emailInputListener();
  await submit({ preventDefault() {} });
  assert.deepEqual(submittedBody, { otp_channel: 'phone' });
  assert.equal(redirected, '/verify-otp?channel=phone');
  page.destroy();
});

test('OtpPage identifies the selected OTP delivery channel', () => {
  const makePage = (search) => {
    const copy = { textContent: '' };
    const input = { addEventListener() {}, value: '' };
    const form = { querySelector: () => input, addEventListener() {} };
    const resendForm = { addEventListener() {} };
    const document = {
      getElementById(id) {
        if (id === 'otpForm') return form;
        if (id === 'resend-form') return resendForm;
        if (id === 'otp-channel-copy') return copy;
        return null;
      },
    };
    new OtpPage({}, {
      auth: { verifyOtp: async () => ({ ok: true }) },
      document,
      window: { location: { search }, setInterval: () => 1, clearInterval() {} },
    });
    return copy.textContent;
  };

  assert.equal(makePage('?channel=email'), 'email address');
  assert.equal(makePage('?channel=phone'), 'cellphone number');
  assert.equal(makePage(''), 'selected contact method');
});

test('OtpPage verifies, resends, sanitizes input, and counts down', async () => {
  let verifyBody;
  let resendBody;
  let submit;
  let resendSubmit;
  let inputListener;
  let intervalCallback;
  const input = {
    value: '12a',
    addEventListener(name, callback) { if (name === 'input') inputListener = callback; },
    requestSubmit() { submit({ preventDefault() {} }); },
  };
  const form = {
    querySelector() { return input; },
    addEventListener(name, callback) { if (name === 'submit') submit = callback; },
    requestSubmit() { submit({ preventDefault() {} }); },
  };
  const resendForm = {
    addEventListener(name, callback) { if (name === 'submit') resendSubmit = callback; },
  };
  const countdown = { textContent: '5:00' };
  const messages = new Map([
    ['form-error', { hidden: true, textContent: '' }],
    ['form-success', { hidden: true, textContent: '' }],
  ]);
  const document = {
    getElementById(id) {
      if (id === 'otpForm') return form;
      if (id === 'resend-form') return resendForm;
      if (id === 'countdown') return countdown;
      return messages.get(id) || null;
    },
  };
  const auth = {
    verifyOtp: async (body) => { verifyBody = body; return { ok: true, data: { redirect: '/login' } }; },
    resendOtp: async (body) => { resendBody = body; return { ok: true, message: 'Sent again.' }; },
  };
  const page = new OtpPage({}, {
    auth,
    document,
    window: { setInterval(callback) { intervalCallback = callback; return 7; }, clearInterval() {} },
    formDataFactory: (value) => ({ form: value }),
  });

  inputListener();
  assert.equal(input.value, '12');
  input.value = '123456';
  inputListener();
  await submit({ preventDefault() {} });
  await resendSubmit({ preventDefault() {} });
  intervalCallback();

  assert.deepEqual(verifyBody, { form });
  assert.deepEqual(resendBody, { form: resendForm });
  assert.equal(countdown.textContent, '4:59');
  assert.equal(messages.get('form-success').textContent, 'Sent again.');
  page.destroy();
});

test('GuestRegistrationPage preserves purpose controls, photo steps, and redirect', async () => {
  let purposeChange;
  let submit;
  let redirected = '';
  const purposeWrap = { classList: { toggle(name, hidden) { purposeWrap.hidden = hidden; } } };
  const purpose = {
    value: 'Others',
    addEventListener(name, callback) { if (name === 'change') purposeChange = callback; },
  };
  const form = {
    reportValidity: () => true,
    addEventListener(name, callback) { if (name === 'submit') submit = callback; },
  };
  const sections = [
    { dataset: { guestRegistrationStep: 'details' } },
    { dataset: { guestRegistrationStep: 'photo' } },
  ];
  const document = {
    getElementById(id) {
      if (id === 'guest-reg-form') return form;
      if (id === 'purpose') return purpose;
      if (id === 'otherPurposeWrap') return purposeWrap;
      return null;
    },
    querySelectorAll(selector) {
      if (selector === '[data-guest-registration-step]') return sections;
      return [];
    },
  };
  const page = new GuestRegistrationPage({}, {
    document,
    window: { location: { set href(value) { redirected = value; } } },
    camera: { stop() {} },
    auth: { registerGuest: async () => ({ ok: true }) },
    formDataFactory: () => ({ form }),
  });

  assert.equal(page.currentStep, 'details');
  assert.equal(purposeWrap.hidden, false);
  purpose.value = 'Reading';
  purposeChange();
  assert.equal(purposeWrap.hidden, true);
  await submit({ preventDefault() {} });
  assert.equal(redirected, '/scan2borrow/guest/verify-otp');
});

test('Guest and profile OTP pages keep distinct form boundaries and redirects', async () => {
  let guestSubmit;
  let profileSubmit;
  let guestRedirect = '';
  let profileRedirect = '';
  const makeDocument = (formId, form) => ({
    getElementById(id) {
      if (id === formId) return form;
      if (id === 'resend-form') return { addEventListener() {} };
      if (id === 'form-error' || id === 'form-success') return { hidden: true, textContent: '' };
      return null;
    },
  });
  const guestForm = { addEventListener(name, callback) { if (name === 'submit') guestSubmit = callback; } };
  const profileForm = { addEventListener(name, callback) { if (name === 'submit') profileSubmit = callback; } };
  const guest = new GuestOtpPage({}, {
    document: makeDocument('guest-otp-form', guestForm),
    window: { location: { set href(value) { guestRedirect = value; } }, setInterval: () => 1, clearInterval() {} },
    auth: { verifyGuestOtp: async () => ({ ok: true }) },
    formDataFactory: () => ({ guestForm }),
  });
  const profile = new ProfileOtpPage({}, {
    document: makeDocument('profile-otp-form', profileForm),
    window: { location: { set href(value) { profileRedirect = value; } }, setInterval: () => 2, clearInterval() {} },
    auth: { verifyGuestOtp: async () => ({ ok: true, message: 'Profile updated.' }) },
    formDataFactory: () => ({ profileForm }),
  });

  await guestSubmit({ preventDefault() {} });
  await profileSubmit({ preventDefault() {} });
  assert.deepEqual(guest.formDataFactory(guestForm), { guestForm });
  assert.deepEqual(profile.formDataFactory(profileForm), { profileForm });
  assert.equal(guestRedirect, '/scan2borrow/guest/dashboard');
  assert.equal(profileRedirect, '/scan2borrow/guest/profile');
  guest.destroy();
  profile.destroy();
});

test('guest auth pages expose browser-loadable entry modules', () => {
  assert.equal(typeof bootGuestRegistration, 'function');
  assert.equal(typeof bootGuestOtp, 'function');
  assert.equal(typeof bootProfileOtp, 'function');
});
