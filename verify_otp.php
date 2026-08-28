<?php
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$error = '';
$success = '';
$barcode = trim(urldecode($_GET['barcode'] ?? ''));

if (empty($barcode)) {
    redirect('register.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    
    // Handle resend OTP
    if (isset($_POST['resend_otp'])) {
        $newOtp = resend_otp($pdo, $barcode);
        if ($newOtp) {
            $success = 'New OTP code sent successfully!';
        } else {
            $error = 'Unable to resend OTP. Please try again later.';
        }
    } else {
        // Handle OTP verification
        $otp = trim($_POST['otp'] ?? '');
        
        if (empty($otp)) {
            $error = 'Please enter the OTP code.';
        } elseif (strlen($otp) !== 6 || !ctype_digit($otp)) {
            $error = 'OTP must be a 6-digit number.';
        } else {
            $userData = verify_otp($pdo, $barcode, trim($otp));
            
            if ($userData) {
                // OTP is valid, create the user account
                try {
                    $photoPath = save_captured_photo($userData['photo_data'] ?? '', $userData['barcode']);
                    // Insert into `users` table for both students and teachers
                    $stmt = $pdo->prepare('
                        INSERT INTO users
                            (barcode, firstname, middlename, lastname, course, year_level, email, contact_no, role, photo, status, department, position, password_hash)
                        VALUES
                            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "active", ?, ?, NULL)
                    ');

                    $stmt->execute([
                        $userData['barcode'],
                        $userData['firstname'],
                        $userData['middlename'] ?? null,
                        $userData['lastname'],
                        $userData['course'] ?? null,
                        $userData['year_level'] ?? null,
                        $userData['email'] ?? null,
                        $userData['contact_no'] ?? ($userData['mobile'] ?? null),
                        $userData['role'],
                        $photoPath,
                        $userData['department'] ?? null,
                        $userData['position'] ?? null
                    ]);
                    
                    // Clean up OTP record
                    $pdo->prepare('DELETE FROM otp_codes WHERE barcode = ?')->execute([$barcode]);
                    
                    $success = 'Registration successful! You can now use your Barcode ID to log in.';
                    
                    // Redirect to login after 3 seconds
                    header("Refresh: 3; URL=index.php");
                    
                } catch (Throwable $e) {
                    $error = 'Registration failed: ' . $e->getMessage();
                }
            } else {
                $error = 'Invalid or expired OTP code. Please try again.';
            }
        }
    }
}

// Get remaining time for OTP
$stmt = $pdo->prepare('
    SELECT expires_at FROM otp_codes 
    WHERE barcode = ? AND is_used = 0 
    ORDER BY id DESC LIMIT 1
');
$stmt->execute([$barcode]);
$otpRecord = $stmt->fetch();

$expiresIn = 0;
if ($otpRecord) {
    $expiresIn = max(0, strtotime($otpRecord['expires_at']) - time());
}

$canResend = can_resend_otp($pdo, $barcode);

$pageTitle = 'Verify OTP';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-card" style="max-width:480px;">
        <div class="auth-head">
            <h3 class="mb-1">OTP Verification</h3>
            <p class="text-muted mb-0">Enter the 6-digit code sent to your phone</p>
        </div>

        <div class="auth-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success !== ''): ?>
                <div class="alert alert-success">
                    <?= e($success) ?>
                    <br><small>Redirecting to login page...</small>
                </div>
            <?php else: ?>
                <form method="POST" id="otpForm">
                    <?= csrf_field() ?>
                    
                    <div class="text-center mb-4">
                        <div class="otp-icon mb-3">&#128274;</div>
                        <p class="text-muted small">
                            We've sent a 6-digit verification code to your phone number.
                            <br>Please enter it below to complete your registration.
                        </p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Enter OTP Code</label>
                        <input type="text" 
                               name="otp" 
                               class="form-control form-control-lg text-center" 
                               placeholder="000000" 
                               maxlength="6" 
                               pattern="[0-9]{6}"
                               inputmode="numeric"
                               autocomplete="one-time-code"
                               required
                               style="letter-spacing: 8px; font-size: 24px; font-weight: bold;">
                    </div>

                    <?php if ($expiresIn > 0): ?>
                        <div class="alert alert-info">
                            <small>
                                &#128336; Code expires in: <span id="countdown"><?= ceil($expiresIn / 60) ?>:<?= str_pad($expiresIn % 60, 2, '0', STR_PAD_LEFT) ?></span>
                            </small>
                        </div>
                    <?php endif; ?>

                    <button type="submit" name="verify_otp" class="btn btn-accent w-100 py-2 fw-semibold">
                        Verify OTP
                    </button>
                </form>

                <hr class="my-4">

                <div class="text-center">
                    <p class="text-muted small mb-2">Didn't receive the code?</p>
                    
                    <?php if ($canResend): ?>
                        <form method="POST" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="resend_otp" value="1">
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                &#128266; Resend OTP
                            </button>
                        </form>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm" disabled id="resendBtn">
                            &#128266; Resend OTP (<span id="resendCountdown">60</span>s)
                        </button>
                    <?php endif; ?>
                </div>

                <div class="text-center mt-3">
                    <a href="register.php" class="text-decoration-none small">
                        &#8592; Back to Registration
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!$success): ?>
<script>
(function () {
    var form = document.getElementById('otpForm');
    var otpInput = form.querySelector('input[name="otp"]');
    
    // Auto-submit when 6 digits are entered
    otpInput.addEventListener('input', function (e) {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length === 6) {
            form.submit();
        }
    });
    
    // Countdown timer for OTP expiration
    var countdownEl = document.getElementById('countdown');
    var resendCountdownEl = document.getElementById('resendCountdown');
    var resendBtn = document.getElementById('resendBtn');
    
    var expiresIn = <?= $expiresIn ?>;
    var canResend = <?= $canResend ? 'false' : 'true' ?>;
    
    function updateCountdown() {
        if (expiresIn > 0) {
            expiresIn--;
            var minutes = Math.floor(expiresIn / 60);
            var seconds = expiresIn % 60;
            if (countdownEl) {
                countdownEl.textContent = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
            }
        }
        
        if (!canResend && expiresIn <= 0) {
            canResend = true;
            if (resendBtn) {
                resendBtn.disabled = false;
                resendBtn.classList.remove('btn-outline-secondary');
                resendBtn.classList.add('btn-outline-primary');
            }
            if (resendCountdownEl) {
                resendCountdownEl.textContent = '0';
            }
        }
    }
    
    setInterval(updateCountdown, 1000);
    
    // Handle resend OTP
    <?php if (!$canResend): ?>
    var resendSeconds = 60;
    function updateResendCountdown() {
        resendSeconds--;
        if (resendCountdownEl) {
            resendCountdownEl.textContent = resendSeconds;
        }
        if (resendSeconds <= 0) {
            canResend = true;
            if (resendBtn) {
                resendBtn.disabled = false;
                resendBtn.classList.remove('btn-outline-secondary');
                resendBtn.classList.add('btn-outline-primary');
            }
        }
    }
    setInterval(updateResendCountdown, 1000);
    <?php endif; ?>
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>