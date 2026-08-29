import { GuestOtpPage } from '../guest-otp/guest-otp.page.js';

export class ProfileOtpPage extends GuestOtpPage {
  constructor(root, options) {
    super(root, {
      ...options,
      formId: 'profile-otp-form',
      redirect: '/scan2borrow/guest/profile',
      resendMessage: 'Please wait before requesting another code.',
    });
  }
}
