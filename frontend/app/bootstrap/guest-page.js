import { createPageContext } from './page-context.js';
import { AuthService } from '../../features/auth/services/auth.service.js';
import { GuestOtpPage } from '../../features/auth/pages/guest-otp/guest-otp.page.js';
import { GuestRegistrationPage } from '../../features/auth/pages/guest-registration/guest-registration.page.js';
import { ProfileOtpPage } from '../../features/auth/pages/profile-otp/profile-otp.page.js';
import { bootDocument, registerPage } from './page-registry.js';

export const guestPageNames = [
  'guest-registration',
  'guest-otp',
  'profile-otp',
  'guest-dashboard',
  'guest-profile',
  'guest-browse',
  'guest-borrowed',
  'guest-history',
  'guest-borrow-request',
  'guest-return',
  'guest-pass',
  'guest-receipt',
];

const authFactory = (Page) => (context) => ({
  start: () => new Page(context.document, {
    auth: new AuthService({ api: context.api, window: context.window }),
    document: context.document,
    window: context.window,
  }).start(),
});

const lazyFactory = (modulePath, exportName) => (context) => ({
  start: async () => {
    const module = await import(modulePath);
    const Page = module[exportName];
    return new Page(context.document, context).start();
  },
});

registerPage('guest-registration', authFactory(GuestRegistrationPage));
registerPage('guest-otp', authFactory(GuestOtpPage));
registerPage('profile-otp', authFactory(ProfileOtpPage));
registerPage('guest-dashboard', lazyFactory('../../features/guest/pages/dashboard/guest-dashboard.page.js', 'GuestDashboardPage'));
registerPage('guest-profile', lazyFactory('../../features/guest/pages/profile/guest-profile.page.js', 'GuestProfilePage'));
registerPage('guest-browse', lazyFactory('../../features/guest/pages/browse/guest-browse.page.js', 'GuestBrowsePage'));
registerPage('guest-borrowed', lazyFactory('../../features/guest/pages/borrowed/guest-borrowed.page.js', 'GuestBorrowedPage'));
registerPage('guest-history', lazyFactory('../../features/guest/pages/history/guest-history.page.js', 'GuestHistoryPage'));
registerPage('guest-borrow-request', lazyFactory('../../features/guest/pages/borrow-request/guest-borrow-request.page.js', 'GuestBorrowRequestPage'));
registerPage('guest-return', lazyFactory('../../features/guest/pages/return/guest-return.page.js', 'GuestReturnPage'));
registerPage('guest-pass', lazyFactory('../../features/guest/pages/pass/guest-pass.page.js', 'GuestPassPage'));
registerPage('guest-receipt', lazyFactory('../../features/guest/pages/receipt/guest-receipt.page.js', 'GuestReceiptPage'));

export function boot(document = globalThis.document, contextFactory) {
  return bootDocument(document, contextFactory || ((currentDocument) => createPageContext({ document: currentDocument })));
}

if (typeof document !== 'undefined') {
  document.addEventListener('DOMContentLoaded', () => boot(), { once: true });
}
