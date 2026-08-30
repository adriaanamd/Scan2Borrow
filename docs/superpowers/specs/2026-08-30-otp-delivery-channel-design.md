# OTP Delivery Channel Selection

## Goal

Allow registrants to choose whether their verification OTP is sent by email or cellphone number, while keeping the selected channel consistent through verification and resend.

## User experience

- Registration includes a “Send verification code via” choice with `Email` and `Cellphone` options.
- The selected contact method is required and uses the existing email or cellphone validation rules.
- If only one contact method is provided, that option is selected automatically.
- If neither contact method is provided, registration stops with a clear validation message.
- The verification page identifies the selected destination without asking the user to choose again.
- Resend keeps the original channel and remains subject to the existing 60-second cooldown.

## Backend design

- Accept an `otp_channel` value of `email` or `phone` in registration.
- Validate that the selected channel has a valid, non-empty destination.
- Reject missing or unsupported channels with a registration validation error.
- Route OTP delivery from the explicit channel rather than inferring from whichever field happens to be populated.
- Persist the selected channel in the OTP payload used by the verification flow so the frontend can describe the destination consistently.
- Preserve the current five-minute OTP lifetime and 60-second resend cooldown.
- Do not expose OTP values or SMTP credentials in API responses or logs.

## Frontend design

- Add an accessible radio group to the registration form.
- Toggle field requirements and visibility/help text based on the selected channel.
- Include `otp_channel` in the registration request.
- Render channel-specific wording on the verification page using the registration session state or response data.
- Keep the current six-digit input and countdown behavior unchanged.

## Testing

- Registration succeeds with email selected and sends through the email sender.
- Registration succeeds with cellphone selected and sends through the SMS sender.
- Missing selected destination is rejected.
- Unsupported or missing `otp_channel` is rejected.
- Resend retains the selected channel and cooldown behavior.
- Verification-page wording distinguishes email from cellphone delivery.
- Existing registration, guest OTP, mailer, and frontend auth tests remain passing.

## Scope boundaries

This change does not add a new provider, alter the Gmail SMTP configuration, change OTP duration, or change the existing resend cooldown.
