<?php
require_once __DIR__ . '/includes/auth.php';

$preRole = trim((string)($_GET['role'] ?? ''));

$error = '';
$success = '';

if (isset($_POST['register'])) {
    csrf_check();

    $fields = [
        'barcode'    => trim($_POST['barcode'] ?? ''),
        'firstname'  => trim($_POST['firstname'] ?? ''),
        'middlename' => trim($_POST['middlename'] ?? ''),
        'lastname'   => trim($_POST['lastname'] ?? ''),
        'department' => trim($_POST['department'] ?? ''),
        'position'   => trim($_POST['position'] ?? ''),
        'course'     => trim($_POST['course'] ?? ''),
        'year_level' => trim($_POST['year_level'] ?? ''),
        'email'      => trim($_POST['email'] ?? ''),
        'contact_no' => trim($_POST['contact_no'] ?? ''),
        'role'       => $_POST['role'] ?? '',
    ];

    if ($fields['barcode'] === '' || $fields['firstname'] === '' || $fields['lastname'] === '') {
        $error = 'Barcode, first name and last name are required.';
    } elseif (!in_array($fields['role'], ['student', 'teacher'], true)) {
        $error = 'Please select a valid role.';
    } elseif ($fields['role'] === 'student' && ($fields['course'] === '' || $fields['year_level'] === '')) {
        $error = 'Please select course and year level for students.';
    } elseif ($fields['role'] === 'teacher' && ($fields['department'] === '' || $fields['position'] === '')) {
        $error = 'Please enter department and position for teachers.';
    } elseif ($fields['email'] !== '' && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($fields['contact_no'] !== '' && !preg_match('/^[0-9+\-\s()]{7,15}$/', $fields['contact_no'])) {
        $error = 'Please enter a valid contact number (7-15 digits, may include +, -, spaces, or parentheses).';
    } else {
        $check = db()->prepare('SELECT id FROM users WHERE barcode = ? LIMIT 1');
        $check->execute([$fields['barcode']]);

        if ($check->fetch()) {
            $error = 'This Barcode ID is already registered.';
        } else {
            $rawPhoto  = trim($_POST['photo_data'] ?? '');
            $photoPath = save_captured_photo($rawPhoto, $fields['barcode']);

            if ($rawPhoto !== '' && $photoPath === null) {
                $error = 'The captured photo could not be processed. Please click Start Camera, then Capture, and try again.';
            } else {
                $pdo = db();
                
                // Store registration data temporarily and send OTP
                $userData = [
                    'barcode'    => $fields['barcode'],
                    'firstname'  => $fields['firstname'],
                    'middlename' => $fields['middlename'],
                    'lastname'   => $fields['lastname'],
                    'department' => $fields['department'],
                    'position'   => $fields['position'],
                    'course'     => $fields['course'],
                    'year_level' => $fields['year_level'],
                    'email'      => $fields['email'],
                    'contact_no' => $fields['contact_no'],
                    'role'       => $fields['role'],
                    'photo_data' => $photoPath
                ];
                
                // Save OTP and redirect to verification page
                $otp = save_otp_registration($pdo, $userData, $fields['contact_no']);
                
                // Store barcode in session for verification page
                $_SESSION['registration_barcode'] = $fields['barcode'];
                
                redirect('verify_otp.php?barcode=' . urlencode($fields['barcode']));
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register | Scan2Borrow</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card" style="max-width:560px;">

        <div class="auth-head">
            <h3 class="mb-1">Library Borrower Registration</h3>
        </div>

        <div class="auth-body">

                    <!-- Role selection modal -->
                    <div class="modal fade" id="roleModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-sm modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-body text-center">
                                    <h5 class="mb-3">Register as</h5>
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-outline-primary" id="chooseStudent">Student</button>
                                        <button type="button" class="btn btn-outline-secondary" id="chooseTeacher">Teacher</button>
                                        <button type="button" class="btn btn-outline-success" id="chooseGuest">Guest / Visitor</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>
            <?php if ($success !== ''): ?>
                <div class="alert alert-success"><?= e($success) ?></div>
            <?php endif; ?>

            <form method="POST" id="reg-form">
                <?= csrf_field() ?>
                <input type="hidden" name="photo_data" id="photo_data">

                <div class="text-center mb-3">
                    <label class="form-label d-block fw-semibold">ID Photo</label>
                    <div class="photo-capture mx-auto" style="max-width:260px;">
                        <video id="cam" autoplay playsinline muted
                               style="width:100%;border-radius:12px;background:#0f172a;aspect-ratio:4/3;object-fit:cover;"></video>
                        <canvas id="snap" class="d-none"></canvas>
                        <img id="preview" class="d-none img-fluid rounded" style="aspect-ratio:4/3;object-fit:cover;width:100%;" alt="Captured photo">
                        <div id="cam-msg" class="text-muted small mt-1"></div>
                        <div class="d-flex gap-2 mt-2 justify-content-center">
                            <button type="button" id="btn-start" class="btn btn-outline-primary btn-sm">Start Camera</button>
                            <button type="button" id="btn-capture" class="btn btn-accent btn-sm d-none">Capture</button>
                            <button type="button" id="btn-retake" class="btn btn-outline-secondary btn-sm d-none">Retake</button>
                        </div>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-12">
                        <input type="text" name="barcode" class="form-control" placeholder="School ID Number" required>
                    </div>
                    <div class="col-md-4"><input type="text" name="firstname" class="form-control" placeholder="First Name" required></div>
                    <div class="col-md-4"><input type="text" name="middlename" class="form-control" placeholder="Middle Name"></div>
                    <div class="col-md-4"><input type="text" name="lastname" class="form-control" placeholder="Last Name" required></div>
                    <div class="col-md-6 student-only">
                        <select name="course" class="form-select">
                            <option value="">Select Course</option>
                            <option value="Bachelor of Science in Information Technology">Bachelor of Science in Information Technology</option>
                            <option value="Bachelor of Science in Business Management">Bachelor of Science in Business Management</option>
                            <option value="Bachelor of Science in Education">Bachelor of Science in Education</option>
                            <option value="Bachelor of Science in Accountancy">Bachelor of Science in Accountancy</option>
                        </select>
                    </div>
                    <div class="col-md-6 student-only">
                        <select name="year_level" class="form-select">
                            <option value="">Select Year</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                        </select>
                    </div>
                    <div class="col-md-6"><input type="email" name="email" class="form-control" placeholder="Email Address"></div>
                    <div class="col-md-6"><input type="text" name="contact_no" class="form-control" placeholder="Contact Number"></div>

                    <div class="col-md-6 teacher-only d-none">
                      <select name="department" class="form-select">
                        <option value="">Select Department</option>
                        <option value="CABAIT">CABAIT</option>
                        <option value="EDUCATION">EDUCATION</option>
                      </select>
                    </div>
                    <div class="col-md-6 teacher-only d-none">
                      <select name="position" class="form-select">
                        <option value="">Select Position</option>
                        <option value="College Teacher">College Teacher</option>
                        <option value="Part-Time Teacher">Part-Time Teacher</option>
                        <option value="Academic Dean">Academic Dean</option>
                        <option value="College Dean">College Dean</option>
                        <option value="Elementary Teacher">Elementary Teacher</option>
                        <option value="Senior High Teacher">Senior High Teacher</option>
                      </select>
                    </div>
                    <div class="col-12">
                        <select name="role" id="role_select" class="form-select" required>
                            <option value="">Select Role</option>
                            <option value="student"<?= $preRole === 'student' ? ' selected' : '' ?>>Student</option>
                            <option value="teacher"<?= $preRole === 'teacher' ? ' selected' : '' ?>>Teacher</option>
                        </select>
                    </div>
                </div>

                <button type="submit" name="register" class="btn btn-accent w-100 mt-3 py-2 fw-semibold">
                    Register Account
                </button>
            </form>

            <hr>
            <a href="index.php" class="text-decoration-none d-block text-center">
                Already registered? Go to Login
            </a>

        </div>
    </div>
</div>

<script>
(function () {
    var video   = document.getElementById('cam');
    var canvas  = document.getElementById('snap');
    var preview = document.getElementById('preview');
    var field   = document.getElementById('photo_data');
    var msg     = document.getElementById('cam-msg');
    var bStart  = document.getElementById('btn-start');
    var bCap    = document.getElementById('btn-capture');
    var bRetake = document.getElementById('btn-retake');
    var stream  = null;

    function stopStream() {
        if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
    }

    bStart.addEventListener('click', function () {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            msg.textContent = 'Camera not supported on this browser.';
            return;
        }
        msg.textContent = 'Starting camera...';
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
            .then(function (s) {
                stream = s;
                video.srcObject = s;
                video.classList.remove('d-none');
                preview.classList.add('d-none');
                bStart.classList.add('d-none');
                bCap.classList.remove('d-none');
                bRetake.classList.add('d-none');
                msg.textContent = 'Position your face in the frame, then Capture.';
            })
            .catch(function () {
                msg.textContent = 'Could not access the camera. Please allow camera permission.';
            });
    });

    bCap.addEventListener('click', function () {
        if (!stream) { return; }
        var w = video.videoWidth || 320, h = video.videoHeight || 240;
        canvas.width = w; canvas.height = h;
        canvas.getContext('2d').drawImage(video, 0, 0, w, h);
        var data = canvas.toDataURL('image/jpeg', 0.85);
        field.value = data;
        preview.src = data;
        preview.classList.remove('d-none');
        video.classList.add('d-none');
        bCap.classList.add('d-none');
        bRetake.classList.remove('d-none');
        stopStream();
        msg.textContent = 'Photo captured. Click Retake to redo.';
    });

    bRetake.addEventListener('click', function () {
        field.value = '';
        preview.classList.add('d-none');
        bRetake.classList.add('d-none');
        bStart.click();
    });

    window.addEventListener('beforeunload', stopStream);
})();
</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function () {
        var roleSel = document.getElementById('role_select');
        var studentFields = document.querySelectorAll('.student-only');
        var teacherFields = document.querySelectorAll('.teacher-only');

        function toggleStudentFields() {
            var isStudent = roleSel.value === 'student';
            var isTeacher = roleSel.value === 'teacher';
            studentFields.forEach(function (el) {
                if (isStudent) el.classList.remove('d-none'); else el.classList.add('d-none');
            });
            teacherFields.forEach(function (el) {
                if (isTeacher) el.classList.remove('d-none'); else el.classList.add('d-none');
            });
        }

        roleSel.addEventListener('change', function () {
            toggleStudentFields();
        });

        // Modal behavior
        var preRole = '<?= $preRole ?>';
        var roleModal = new bootstrap.Modal(document.getElementById('roleModal'));

        document.getElementById('chooseStudent').addEventListener('click', function () {
            roleSel.value = 'student';
            toggleStudentFields();
            roleModal.hide();
        });
        document.getElementById('chooseTeacher').addEventListener('click', function () {
            roleSel.value = 'teacher';
            toggleStudentFields();
            roleModal.hide();
        });
        document.getElementById('chooseGuest').addEventListener('click', function () {
            roleModal.hide();
            window.location.href = 'guest_registration.php';
        });

        // Show modal on first visit if role is not preselected
        if (!preRole) {
            roleModal.show();
        } else {
            // apply preselected role
            toggleStudentFields();
        }
    })();
    </script>
</body>
</html>
