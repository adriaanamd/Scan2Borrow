<?php
require_once __DIR__ . '/includes/auth.php';
$token = $_SESSION['guest_otp_token'] ?? '';
if ($token === '') redirect('guest_registration.php');
$pdo = db(); $error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['resend_otp'])) {
        $success = resend_otp($pdo, $token) ? 'A new OTP has been sent.' : 'Please wait before requesting another code.';
    } else {
        $otp = trim($_POST['otp'] ?? '');
        if (!preg_match('/^\d{6}$/', $otp)) $error = 'OTP must be a 6-digit number.';
        else {
            $visitor = verify_otp($pdo, $token, $otp);
            if (!$visitor || ($visitor['registration_type'] ?? '') !== 'guest') $error = 'Invalid or expired OTP code.';
            else {
                try {
                    $photo = save_captured_photo($visitor['photo_data'], $visitor['id_barcode']);
                    if ($photo === null) throw new RuntimeException('The visitor photo could not be processed.');
                    $stmt = $pdo->prepare('INSERT INTO visitors (firstname,middlename,lastname,suffix,gender,birthdate,contact_no,email,house_no,street,barangay,municipality,province,purpose,purpose_other,id_type,id_barcode,photo,is_verified,verified_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW())');
                    $stmt->execute([$visitor['firstname'],$visitor['middlename'] ?: null,$visitor['lastname'],$visitor['suffix'] ?: null,$visitor['gender'],$visitor['birthdate'],$visitor['contact_no'],$visitor['email'] ?: null,$visitor['house_no'],$visitor['street'],$visitor['barangay'],$visitor['municipality'],$visitor['province'],$visitor['purpose'],$visitor['purpose']==='Others'?$visitor['purpose_other']:null,$visitor['id_type'],$visitor['id_barcode'],$photo]);
                    $visitorId = (int) $pdo->lastInsertId();
                    $visitorNumber = 'VIS-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
                    $qrToken = bin2hex(random_bytes(16));
                    $pdo->prepare('UPDATE visitors SET visitor_number = ?, qr_token = ?, registration_expires_at = DATE_ADD(CURDATE(), INTERVAL 1 YEAR), account_status = "Active", last_login_at = NOW() WHERE id = ?')
                        ->execute([$visitorNumber, $qrToken, $visitorId]);
                    $pdo->prepare('INSERT INTO visitor_security_logs (visitor_id, activity, details) VALUES (?, ?, ?)')
                        ->execute([$visitorId, 'registration', 'Guest registration verified with SMS OTP.']);
                    $pdo->prepare('INSERT INTO visitor_visit_history (visitor_id, time_in) VALUES (?, NOW())')
                        ->execute([$visitorId]);
                    $pdo->prepare('DELETE FROM otp_codes WHERE barcode = ?')->execute([$token]); unset($_SESSION['guest_otp_token']);
                    login_guest(['id'=>$visitorId,'firstname'=>$visitor['firstname'],'lastname'=>$visitor['lastname']]); redirect('guest_dashboard.php');
                } catch (PDOException $e) { $error = $e->getCode() === '23000' ? 'This government ID barcode has already been registered.' : 'Registration could not be completed.'; }
                catch (Throwable $e) { $error = $e->getMessage(); }
            }
        }
    }
}
$q=$pdo->prepare('SELECT expires_at FROM otp_codes WHERE barcode=? AND is_used=0 ORDER BY id DESC LIMIT 1');$q->execute([$token]);$row=$q->fetch();$expiresIn=$row?max(0,strtotime($row['expires_at'])-time()):0;$canResend=can_resend_otp($pdo,$token);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Verify Guest OTP | Scan2Borrow</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/style.css" rel="stylesheet"></head><body><div class="auth-wrap"><div class="auth-card" style="max-width:480px;"><div class="auth-head"><h3>Guest SMS Verification</h3><p class="mb-0">Enter the OTP sent to your mobile number.</p></div><div class="auth-body"><?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?><?php if($success): ?><div class="alert alert-success"><?=e($success)?></div><?php endif; ?><form method="POST"><?=csrf_field()?><input name="otp" class="form-control form-control-lg text-center mb-3" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" placeholder="000000" required><button class="btn btn-success w-100">Verify OTP &amp; Register</button></form><hr><?php if($canResend): ?><form method="POST" class="text-center"><?=csrf_field()?><button name="resend_otp" class="btn btn-outline-primary btn-sm">Resend OTP</button></form><?php else: ?><p class="text-center text-muted small">Resend available shortly.</p><?php endif; ?><p class="text-center small mt-3">OTP expires in <?=ceil($expiresIn/60)?> minute(s).</p></div></div></div></body></html>
