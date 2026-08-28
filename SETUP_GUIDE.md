# Scan2Borrow - Advanced Features Setup Guide

## Overview
This guide covers the implementation of advanced notification and automation features for Scan2Borrow.

---

## 1. Database Setup

### Step 1: Run SQL Files
Execute these SQL files in phpMyAdmin or via MySQL command line in order:

```bash
# 1. First, run the base database (if not already done)
mysql -u root -p < database.sql

# 2. Run upgrade files (if not already done)
mysql -u root -p < upgrade.sql
mysql -u root -p < upgrade_approval_system.sql

# 3. NEW: Run the notification system upgrade
mysql -u root -p < upgrade_notification_system.sql
```

### What Gets Created:
- **sms_logs** - Tracks all SMS sent to prevent duplicates
- **otp_codes** - Stores OTP for registration verification
- **return_notifications** - Tracks return confirmations for admin dashboard
- **borrowing_status** column in users table (for enabling/disabling borrowing)

---

## 2. SMS Configuration

### Update config/.env File
Add these lines to your `.env` file:

```env
# SMS Configuration (TextBee)
SMS_ENABLED=true
SMS_PROVIDER=textbee
SMS_API_KEY=your_api_key_here
SMS_DEVICE_ID=your_device_id_here
SMS_SENDER=Scan2Borrow
```

### Get TextBee Credentials:
1. Sign up at https://textbee.ph
2. Create a device
3. Copy the API key and Device ID
4. Update the `.env` file

---

## 3. Feature 1: Automatic Due Date SMS Reminder

### Setup Windows Task Scheduler:

1. Open **Task Scheduler** (taskschd.msc)
2. Create Basic Task → Name: "Scan2Borrow Due Date Reminders"
3. Trigger: Daily at 8:00 AM
4. Action: Start a Program
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\scan2borrow\send_due_reminders.php`
   - Start in: `C:\xampp\htdocs\scan2borrow`
5. Check "Open the Properties dialog" → OK
6. In Properties:
   - Check "Run whether user is logged on or not"
   - Check "Run with highest privileges"
   - Click OK

### How It Works:
- Script runs daily at 8:00 AM
- Finds all books due tomorrow
- Sends SMS reminder to students
- Logs all activities to `logs/due_reminders.log`
- Prevents duplicate reminders (only sends once per borrowing)

### Manual Test:
```bash
cd C:\xampp\htdocs\scan2borrow
C:\xampp\php\php.exe send_due_reminders.php
```

---

## 4. Feature 2: SMS Notification After Borrowing

### Implementation:
- **File Modified:** `studhome.php`
- **Function Used:** `send_borrow_sms_notification()`

### How It Works:
1. Student borrows a book (single or multiple)
2. Database transaction commits successfully
3. SMS is sent automatically with:
   - Student name
   - Book title
   - Borrow date
   - Due date
4. If SMS fails, borrowing record remains saved
5. Prevents duplicate SMS (tracked in sms_logs table)

### No Additional Setup Required:
- Works automatically when `SMS_ENABLED=true`
- Only sends when approval is NOT required (direct borrowing)

---

## 5. Feature 3: OTP Verification During Registration

### Files Created:
- `verify_otp.php` - OTP verification page
- Modified: `register.php` - Redirects to OTP page

### How It Works:
1. Student fills registration form
2. System generates 6-digit OTP
3. OTP sent via SMS to student's phone
4. Student redirected to `verify_otp.php`
5. Student enters OTP
6. System verifies OTP (valid for 5 minutes)
7. Account created if valid
8. Resend OTP available after 60 seconds

### Features:
- ✅ 6-digit random OTP
- ✅ 5-minute expiration
- ✅ Auto-submit when 6 digits entered
- ✅ Countdown timer
- ✅ Resend after 60 seconds
- ✅ Prevents OTP reuse
- ✅ Stores registration data temporarily

### No Additional Setup Required:
- Works automatically
- OTP sent via same SMS provider

---

## 6. Feature 4: Return Confirmation Modal (Admin Dashboard)

### Files Modified:
- `adboard.php` - Added AJAX polling and modal
- `api_notifications.php` - API endpoint for notifications
- `studhome.php` - Creates notification on return

### How It Works:
1. Student returns a book
2. System creates notification in `return_notifications` table
3. Admin dashboard polls API every 5 seconds
4. Modal appears automatically with:
   - Student name
   - Book title
   - Return date/time
5. Modal shows only once per transaction
6. Marked as viewed when closed

### Features:
- ✅ Real-time detection (5-second polling)
- ✅ Bootstrap modal popup
- ✅ No page refresh required
- ✅ Shows once per return
- ✅ Auto-mark as viewed

### No Additional Setup Required:
- Works automatically
- AJAX polling built into adboard.php

---

## 7. Feature 5: Real-Time Pending Approval Requests

### Files Modified:
- `adboard.php` - Enhanced with AJAX polling
- `api_notifications.php` - API endpoint

### How It Works:
1. Student submits borrow request
2. Admin dashboard polls API every 5 seconds
3. If new request exists:
   - Updates Pending Approvals counter
   - Refreshes only the Pending Approval table
   - Shows Bootstrap toast notification
   - Optional: Play notification sound

### Features:
- ✅ AJAX polling every 5 seconds
- ✅ Updates counter badge
- ✅ Refreshes table without page reload
- ✅ Toast notification for new requests
- ✅ No manual refresh needed

### No Additional Setup Required:
- Works automatically
- Built into existing approval modal

---

## 8. File Structure

### New Files Created:
```
scan2borrow/
├── upgrade_notification_system.sql    # Database tables
├── send_due_reminders.php             # Cron job script
├── verify_otp.php                     # OTP verification page
├── api_notifications.php              # AJAX API endpoint
├── logs/
│   └── due_reminders.log              # Reminder logs (auto-created)
└── SETUP_GUIDE.md                     # This file
```

### Modified Files:
```
scan2borrow/
├── includes/functions.php             # Added SMS, OTP, notification functions
├── studhome.php                       # Added SMS on borrow, return notifications
├── adboard.php                        # Added AJAX polling, modals
├── register.php                       # Redirects to OTP verification
└── config/config.php                  # Already has SMS config (no changes needed)
```

---

## 9. Testing Checklist

### Test SMS Notifications:
- [ ] Enable SMS in `.env` (SMS_ENABLED=true)
- [ ] Borrow a book (no approval required)
- [ ] Check if SMS received
- [ ] Verify SMS logged in `sms_logs` table
- [ ] Try borrowing again - should NOT send duplicate SMS

### Test Due Date Reminder:
- [ ] Create a book due tomorrow
- [ ] Run script manually: `php send_due_reminders.php`
- [ ] Check `logs/due_reminders.log`
- [ ] Verify SMS sent
- [ ] Run again - should NOT send duplicate

### Test OTP Registration:
- [ ] Go to registration page
- [ ] Fill form with phone number
- [ ] Submit and check for OTP SMS
- [ ] Enter correct OTP → Account created
- [ ] Try wrong OTP → Error message
- [ ] Wait 5 minutes → OTP expires
- [ ] Test resend button (60-second cooldown)

### Test Return Notification:
- [ ] Login as student, return a book
- [ ] Login as admin (different browser/incognito)
- [ ] Wait 5 seconds
- [ ] Modal should appear automatically
- [ ] Close modal
- [ ] Return another book
- [ ] Modal should appear again

### Test Pending Approvals:
- [ ] Enable approval requirement (REQUIRE_APPROVAL=true)
- [ ] Student submits borrow request
- [ ] Admin dashboard shows toast notification
- [ ] Counter updates automatically
- [ ] Table refreshes without page reload

---

## 10. Important Notes

### SMS Provider (TextBee):
- Requires internet connection
- SMS credits required (check TextBee pricing)
- Phone numbers must be in Philippines format (09XXXXXXXXX or +639XXXXXXXXX)

### Cron Job / Task Scheduler:
- Server must be running for scheduled tasks
- Check logs for errors: `logs/due_reminders.log`
- Test manually before scheduling

### Database:
- All new tables use foreign keys with CASCADE DELETE
- Indexes added for performance
- No existing data affected

### Security:
- OTP codes expire after 5 minutes
- OTP cannot be reused after verification
- SMS logs prevent duplicate sends
- All API endpoints require authentication

---

## 11. Troubleshooting

### SMS Not Sending:
1. Check `.env` file credentials
2. Verify SMS_ENABLED=true
3. Check internet connection
4. Verify TextBee account has credits
5. Check `sms_logs` table for errors

### OTP Not Received:
1. Verify phone number format
2. Check SMS credits
3. Check `otp_codes` table for record
4. Verify `contact_no` is not empty

### Due Reminder Not Working:
1. Check Task Scheduler is enabled
2. Verify PHP path is correct
3. Check log file for errors
4. Test manually first

### AJAX Notifications Not Appearing:
1. Check browser console for errors
2. Verify `api_notifications.php` is accessible
3. Check if staff is logged in
4. Verify database tables exist

---

## 12. Configuration Options

### In config/config.php:
```php
// SMS Settings
define('SMS_ENABLED', true);              // Enable/disable SMS
define('SMS_PROVIDER', 'textbee');        // SMS provider
define('SMS_API_KEY', 'your_key');        // TextBee API key
define('SMS_DEVICE_ID', 'your_device');   // TextBee device ID
define('SMS_SENDER', 'Scan2Borrow');      // Sender name

// Loan Settings
define('LOAN_DAYS', 7);                   // Days before due
define('FINE_PER_DAY', 5.00);             // Fine amount
define('MAX_BOOKS_PER_USER', 3);          // Max concurrent loans
define('REQUIRE_APPROVAL', true);         // Require staff approval
```

### Polling Intervals:
In `adboard.php`, modify these values:
```javascript
// Pending approvals polling (currently 5000ms = 5 seconds)
setInterval(function() { ... }, 5000);

// Return notifications polling (currently 5000ms = 5 seconds)
setInterval(function() { ... }, 5000);
```

---

## 13. Support

For issues or questions:
1. Check log files: `logs/due_reminders.log`
2. Check browser console for JavaScript errors
3. Verify database tables exist
4. Test SMS provider API independently

---

## Implementation Complete! ✅

All features have been successfully implemented:
- ✅ Automatic due date SMS reminders
- ✅ SMS notification after borrowing
- ✅ OTP verification during registration
- ✅ Return confirmation modal with AJAX
- ✅ Real-time pending approval requests
- ✅ Complete database schema
- ✅ Production-ready code