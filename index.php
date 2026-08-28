<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(home_for_role(current_role()));
}
if (is_guest_logged_in()) {
    redirect('guest_dashboard.php');
}

$error = '';
$showRegisterModal = false;
$detectedRole = '';

if (isset($_POST['login'])) {
    csrf_check();

    $barcode = trim($_POST['barcode'] ?? '');

    if ($barcode === '') {
        $error = 'Please scan your Student ID barcode.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE barcode = ? LIMIT 1');
        $stmt->execute([$barcode]);
        $user = $stmt->fetch();

        if (!$user) {
            // Guest government-ID barcodes are intentionally stored outside `users`.
            $visitorStmt = db()->prepare('SELECT * FROM visitors WHERE id_barcode = ? LIMIT 1');
            $visitorStmt->execute([$barcode]);
            $visitor = $visitorStmt->fetch();

            if ($visitor) {
                if (!(int) $visitor['is_verified']) {
                    $error = 'This guest account has not been verified yet.';
                } elseif (($visitor['account_status'] ?? 'Active') === 'Suspended') {
                    $error = 'This guest account is suspended. Please see the librarian.';
                } else {
                    login_guest($visitor);
                    $pdo = db();
                    try {
                        $pdo->prepare('UPDATE visitors SET last_login_at = NOW() WHERE id = ?')->execute([(int) $visitor['id']]);
                        $pdo->prepare('INSERT INTO visitor_visit_history (visitor_id, time_in) VALUES (?, NOW())')->execute([(int) $visitor['id']]);
                        $pdo->prepare('INSERT INTO visitor_security_logs (visitor_id, activity, details) VALUES (?, ?, ?)')->execute([(int) $visitor['id'], 'login', 'Guest signed in using government ID barcode.']);
                    } catch (Throwable $e) {
                        // A verified guest may still sign in if optional visitor audit tables are unavailable.
                    }
                    redirect('guest_dashboard.php');
                }
            } else {
                // Detect likely role: if barcode contains any letters, assume teacher; otherwise student.
                $detectedRole = preg_match('/[A-Za-z]/', $barcode) ? 'teacher' : 'student';
                $showRegisterModal = true;
                $error = '';
            }
        } elseif ($user['status'] !== 'active') {
            $error = 'This account is inactive. Please see the librarian.';
        } elseif (!in_array($user['role'], ['student', 'teacher'], true)) {
            $error = 'Staff accounts must use the Staff Login page.';
        } else {
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
<title>Student Login | Scan2Borrow</title>
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
            <div class="logo">&#128218;</div>
            <h2 class="mb-1">Scan2Borrow</h2>
            <p class="mb-0">School Library</p>
        </div>

        <div class="auth-body">

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <?= csrf_field() ?>

                <label class="form-label fw-semibold">Scan ID Barcode</label>
                <input type="text" name="barcode" id="barcode" class="form-control form-control-lg mb-2"
                       placeholder="Scan your ID" autofocus required>
                <p class="scan-hint mb-3">Hold your ID under the barcode scanner.</p>

                <button type="submit" name="login" class="btn btn-accent w-100 py-2 fw-semibold">
                    Login
                </button>
            </form>

                        <!-- Registration Modals -->
                        <!-- Student Registration Modal -->
                        <div class="modal fade" id="studentRegisterModal" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content">
                                    <form method="POST" action="register.php" id="studentRegForm">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="role" value="student">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Student Registration</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row g-2">
                                                <div class="col-12"><input type="text" name="barcode" class="form-control" placeholder="Student Barcode ID" required></div>
                                                <div class="col-md-4"><input type="text" name="firstname" class="form-control" placeholder="First Name" required></div>
                                                <div class="col-md-4"><input type="text" name="middlename" class="form-control" placeholder="Middle Name"></div>
                                                <div class="col-md-4"><input type="text" name="lastname" class="form-control" placeholder="Last Name" required></div>
                                                <div class="col-md-6"><input type="text" name="course" class="form-control" placeholder="Course" required></div>
                                                <div class="col-md-6"><input type="text" name="year_level" class="form-control" placeholder="Year Level" required></div>
                                                <div class="col-md-6"><input type="email" name="email" class="form-control" placeholder="Email Address"></div>
                                                <div class="col-md-6"><input type="text" name="contact_no" class="form-control" placeholder="Contact Number" required></div>
                                                <div class="col-12 text-center mt-2">
                                                    <label class="form-label d-block fw-semibold">Capture ID Photo</label>
                                                    <video id="modal_cam" autoplay playsinline muted style="width:320px;height:240px;border-radius:8px;background:#0f172a;object-fit:cover;"></video>
                                                    <canvas id="modal_snap" class="d-none"></canvas>
                                                    <img id="modal_preview" class="d-none img-fluid rounded" style="width:320px;" alt="Captured photo">
                                                    <input type="hidden" name="photo_data" id="modal_photo_data">
                                                    <div class="mt-2">
                                                        <button type="button" id="modal_start" class="btn btn-outline-primary btn-sm">Start Camera</button>
                                                        <button type="button" id="modal_capture" class="btn btn-accent btn-sm d-none">Capture</button>
                                                        <button type="button" id="modal_retake" class="btn btn-outline-secondary btn-sm d-none">Retake</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="register" class="btn btn-primary">Register Student</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Teacher Registration Modal -->
                        <div class="modal fade" id="teacherRegisterModal" tabindex="-1">
                            <div class="modal-dialog modal-md modal-dialog-centered">
                                <div class="modal-content">
                                    <form method="POST" action="register.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="role" value="teacher">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Teacher Registration</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row g-2">
                                                <div class="col-12"><input type="text" name="barcode" class="form-control" placeholder="Teacher Barcode ID" required></div>
                                                <div class="col-md-4"><input type="text" name="firstname" class="form-control" placeholder="First Name" required></div>
                                                <div class="col-md-4"><input type="text" name="middlename" class="form-control" placeholder="Middle Name"></div>
                                                <div class="col-md-4"><input type="text" name="lastname" class="form-control" placeholder="Last Name" required></div>
                                                <div class="col-md-6"><input type="text" name="department" class="form-control" placeholder="Department" required></div>
                                                <div class="col-md-6">
                          <select name="position" class="form-select" required>
                            <option value="">Select Position</option>
                            <option value="College Teacher">College Teacher</option>
                            <option value="Part-Time Teacher">Part-Time Teacher</option>
                            <option value="Academic Dean">Academic Dean</option>
                            <option value="College Dean">College Dean</option>
                            <option value="Elementary Teacher">Elementary Teacher</option>
                            <option value="Senior High Teacher">Senior High Teacher</option>
                          </select>
                        </div>
                                                <div class="col-md-6"><input type="email" name="email" class="form-control" placeholder="Email Address"></div>
                                                <div class="col-md-6"><input type="text" name="contact_no" class="form-control" placeholder="Contact Number" required></div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="register" class="btn btn-primary">Register Teacher</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>


            <hr>
            <p class="text-center mb-2">First time using Scan2Borrow?</p>
            <a href="register.php" class="btn btn-primary w-100 mb-3">Register New Borrower</a>

            <div class="text-center">
                <a href="staff_login.php" class="text-muted small">&#128274; Staff / Librarian Login</a>
            </div>

        </div>
    </div>
</div>
        <script>
        // Show register modal if needed
        <?php if ($showRegisterModal): ?>
            (function () {
                var role = '<?= e($detectedRole) ?>';
                if (role === 'teacher') {
                    var m = new bootstrap.Modal(document.getElementById('teacherRegisterModal'));
                    m.show();
                } else {
                    var m2 = new bootstrap.Modal(document.getElementById('studentRegisterModal'));
                    m2.show();
                }
            })();
        <?php endif; ?>

        // Camera handling for student modal
        (function () {
            var cam = document.getElementById('modal_cam');
            var snap = document.getElementById('modal_snap');
            var preview = document.getElementById('modal_preview');
            var field = document.getElementById('modal_photo_data');
            var bStart = document.getElementById('modal_start');
            var bCap = document.getElementById('modal_capture');
            var bRetake = document.getElementById('modal_retake');
            var stream = null;

            function stopStream() { if (stream) { stream.getTracks().forEach(t=>t.stop()); stream=null;} }

            if (bStart) bStart.addEventListener('click', function () {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return alert('Camera not supported');
                navigator.mediaDevices.getUserMedia({video:{facingMode:'user'}, audio:false}).then(function(s){
                    stream = s; cam.srcObject = s; cam.classList.remove('d-none'); preview.classList.add('d-none'); bStart.classList.add('d-none'); bCap.classList.remove('d-none'); bRetake.classList.add('d-none');
                }).catch(function(){ alert('Could not access camera'); });
            });

            if (bCap) bCap.addEventListener('click', function () {
                if (!stream) return;
                var w = cam.videoWidth || 320, h = cam.videoHeight || 240; snap.width = w; snap.height = h; snap.getContext('2d').drawImage(cam,0,0,w,h);
                var data = snap.toDataURL('image/jpeg',0.85); field.value = data; preview.src = data; preview.classList.remove('d-none'); cam.classList.add('d-none'); bCap.classList.add('d-none'); bRetake.classList.remove('d-none'); stopStream();
            });
            if (bRetake) bRetake.addEventListener('click', function () { field.value=''; preview.classList.add('d-none'); bRetake.classList.add('d-none'); bStart.classList.remove('d-none'); bStart.click(); });
            window.addEventListener('beforeunload', stopStream);
        })();
        </script>
        </body>
        </html>
