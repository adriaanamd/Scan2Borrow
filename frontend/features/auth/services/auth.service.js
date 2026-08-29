export class AuthService {
  constructor({ api, window = globalThis.window } = {}) {
    this.api = api;
    this.window = window;
  }

  loginBorrower(body) {
    return this.api.post('/scan2borrow/api/auth/borrower/login', body);
  }

  loginStaff(body) {
    return this.api.post('/scan2borrow/api/auth/staff/login', body);
  }

  register(body) {
    return this.api.post('/scan2borrow/api/auth/register', body);
  }

  verifyOtp(body) {
    return this.api.post('/scan2borrow/api/auth/otp', body);
  }

  resendOtp(body) {
    return this.api.post('/scan2borrow/api/auth/otp/resend', body);
  }

  registerGuest(body) {
    return this.api.post('/scan2borrow/api/auth/guest/register', body);
  }

  verifyGuestOtp(body) {
    return this.api.post('/scan2borrow/api/auth/guest/otp', body);
  }

  resendGuestOtp(body) {
    return this.api.post('/scan2borrow/api/auth/guest/otp/resend', body);
  }
}
