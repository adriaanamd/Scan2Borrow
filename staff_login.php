<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(home_for_role(current_role()));
}

$error = '';

if (isset($_POST['login'])) {
    csrf_check();

    $barcode  = trim($_POST['barcode'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($barcode === '' || $password === '') {
        $error = 'Enter your staff ID and password.';
    } elseif (is_account_locked(db(), $barcode)) {
        $error = 'Account temporarily locked due to too many failed attempts. Please try again later.';
        audit_log(db(), null, 'login_locked', "Locked barcode: $barcode");
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE barcode = ? LIMIT 1');
        $stmt->execute([$barcode]);
        $user = $stmt->fetch();

        if (!$user || !in_array($user['role'], ['admin', 'librarian'], true)) {
            $error = 'No staff account found for that ID.';
            record_login_attempt(db(), null, $barcode, false);
        } elseif ($user['status'] !== 'active') {
            $error = 'This account is inactive.';
            record_login_attempt(db(), (int)$user['id'], $barcode, false);
        } elseif (!password_verify($password, (string) $user['password_hash'])) {
            $error = 'Invalid staff password.';
            record_login_attempt(db(), (int)$user['id'], $barcode, false);
            if ((int)$user['failed_attempts'] + 1 >= 5) {
                lock_account(db(), $barcode, 15);
                $error = 'Too many failed attempts. Account locked for 15 minutes.';
                audit_log(db(), (int)$user['id'], 'account_locked', "Barcode: $barcode");
            }
        } else {
            record_login_attempt(db(), (int)$user['id'], $barcode, true);
            audit_log(db(), (int)$user['id'], 'login_success', "Barcode: $barcode");
            login_user($user);
            redirect(home_for_role($user['role']));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Login | Scan2Borrow</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">

        <div class="auth-head">
            <div class="logo">&#128274;</div>
            <h2 class="mb-1">Staff Portal</h2>
            <p class="mb-0">Librarian / Administrator Login</p>
        </div>

        <div class="auth-body">

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <?= csrf_field() ?>

                <label class="form-label fw-semibold">Staff ID Barcode</label>
                <input type="text" name="barcode" class="form-control mb-3"
                       placeholder="Scan or enter staff ID" autofocus required>

                <label class="form-label fw-semibold">Password</label>
                <input type="password" name="password" class="form-control mb-3"
                       placeholder="Enter your password" required>

                <button type="submit" name="login" class="btn btn-gradient w-100 py-2 fw-semibold">
                    Login
                </button>
            </form>

            <hr>
            <div class="text-center">
                <a href="index.php" class="text-muted small">&#8592; Back to Student Login</a>
            </div>

        </div>
    </div>
</div>
</body>
</html>
