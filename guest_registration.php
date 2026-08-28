<?php
require_once __DIR__ . '/includes/auth.php';

if (is_guest_logged_in()) {
    redirect('guest_dashboard.php');
}

$error = '';
$idTypes = ['National ID', 'Driver\'s License', 'Passport', 'UMID', 'PRC ID', 'Postal ID', 'PhilHealth ID', 'Voter\'s ID', 'Senior Citizen ID', 'Other Government-Issued ID'];
$purposes = ['Research', 'Reading', 'Thesis', 'Review', 'Others'];
$genders = ['Male', 'Female', 'Prefer not to say'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $fields = [
        'firstname' => trim($_POST['firstname'] ?? ''), 'middlename' => trim($_POST['middlename'] ?? ''),
        'lastname' => trim($_POST['lastname'] ?? ''), 'suffix' => trim($_POST['suffix'] ?? ''),
        'gender' => trim($_POST['gender'] ?? ''), 'birthdate' => trim($_POST['birthdate'] ?? ''),
        'contact_no' => trim($_POST['contact_no'] ?? ''), 'email' => trim($_POST['email'] ?? ''),
        'house_no' => trim($_POST['house_no'] ?? ''), 'street' => trim($_POST['street'] ?? ''),
        'barangay' => trim($_POST['barangay'] ?? ''), 'municipality' => trim($_POST['municipality'] ?? ''),
        'province' => trim($_POST['province'] ?? ''), 'purpose' => trim($_POST['purpose'] ?? ''),
        'purpose_other' => trim($_POST['purpose_other'] ?? ''), 'id_type' => trim($_POST['id_type'] ?? ''),
        'id_barcode' => trim($_POST['id_barcode'] ?? ''), 'photo_data' => trim($_POST['photo_data'] ?? ''),
    ];
    $required = ['firstname', 'lastname', 'gender', 'birthdate', 'contact_no', 'house_no', 'street', 'barangay', 'municipality', 'province', 'purpose', 'id_type', 'id_barcode'];
    foreach ($required as $field) {
        if ($fields[$field] === '') { $error = 'Please complete all required fields.'; break; }
    }
    if ($error === '' && !in_array($fields['gender'], $genders, true)) $error = 'Please select a valid gender.';
    if ($error === '' && !in_array($fields['purpose'], $purposes, true)) $error = 'Please select a valid purpose of visit.';
    if ($error === '' && $fields['purpose'] === 'Others' && $fields['purpose_other'] === '') $error = 'Please specify the other purpose of visit.';
    if ($error === '' && !in_array($fields['id_type'], $idTypes, true)) $error = 'Please select a valid government-issued ID type.';
    if ($error === '' && !preg_match('/^[0-9+\-\s()]{7,15}$/', $fields['contact_no'])) $error = 'Please enter a valid mobile number.';
    if ($error === '' && $fields['email'] !== '' && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) $error = 'Please enter a valid email address.';
    $birthdate = DateTime::createFromFormat('Y-m-d', $fields['birthdate']);
    if ($error === '' && (!$birthdate || $birthdate->format('Y-m-d') !== $fields['birthdate'] || $birthdate > new DateTime('today'))) $error = 'Please enter a valid birthdate in the past.';
    if ($error === '' && save_captured_photo($fields['photo_data'], $fields['id_barcode']) === null) $error = 'A live visitor photo is required. Start the camera and capture your photo.';

    if ($error === '') {
        $pdo = db();
        $check = $pdo->prepare('SELECT id FROM visitors WHERE id_barcode = ? LIMIT 1');
        $check->execute([$fields['id_barcode']]);
        if ($check->fetch()) {
            $error = 'This government ID barcode has already been registered.';
        } else {
            $fields['registration_type'] = 'guest';
            $fields['barcode'] = 'GUEST-' . strtoupper(bin2hex(random_bytes(12)));
            save_otp_registration($pdo, $fields, $fields['contact_no']);
            $_SESSION['guest_otp_token'] = $fields['barcode'];
            redirect('guest_verify_otp.php');
        }
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Guest Registration | Scan2Borrow</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/style.css" rel="stylesheet"></head><body>
<div class="auth-wrap"><div class="auth-card" style="max-width:760px;"><div class="auth-head"><h3 class="mb-1">Guest Registration</h3><p class="mb-0">Visitor information, ID verification, and SMS confirmation</p></div><div class="auth-body">
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="POST" id="guest-reg-form" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="photo_data" id="photo_data">
<div class="text-center mb-3"><label class="form-label d-block fw-semibold">Visitor Photo *</label><video id="cam" autoplay playsinline muted style="width:320px;max-width:100%;border-radius:8px;background:#0f172a;aspect-ratio:4/3;object-fit:cover;"></video><canvas id="snap" class="d-none"></canvas><img id="preview" class="d-none img-fluid rounded" style="width:320px;max-width:100%;" alt="Captured visitor photo"><div id="cam-msg" class="small text-muted mt-1"></div><div class="mt-2"><button type="button" id="btn-start" class="btn btn-outline-primary btn-sm">Start Camera</button><button type="button" id="btn-capture" class="btn btn-success btn-sm d-none">Capture</button><button type="button" id="btn-retake" class="btn btn-outline-secondary btn-sm d-none">Retake</button></div></div>
<h6 class="border-bottom pb-2 mb-3">Personal Information</h6><div class="row g-2 mb-3"><div class="col-md-4"><input name="firstname" class="form-control" placeholder="First Name *" required value="<?= e($_POST['firstname'] ?? '') ?>"></div><div class="col-md-4"><input name="middlename" class="form-control" placeholder="Middle Name" value="<?= e($_POST['middlename'] ?? '') ?>"></div><div class="col-md-4"><input name="lastname" class="form-control" placeholder="Last Name *" required value="<?= e($_POST['lastname'] ?? '') ?>"></div><div class="col-md-3"><input name="suffix" class="form-control" placeholder="Suffix" value="<?= e($_POST['suffix'] ?? '') ?>"></div><div class="col-md-3"><select name="gender" class="form-select" required><option value="">Gender *</option><?php foreach ($genders as $gender): ?><option value="<?= e($gender) ?>"<?= ($_POST['gender'] ?? '') === $gender ? ' selected' : '' ?>><?= e($gender) ?></option><?php endforeach; ?></select></div><div class="col-md-3"><input type="date" name="birthdate" class="form-control" required value="<?= e($_POST['birthdate'] ?? '') ?>"></div><div class="col-md-3"><input name="contact_no" class="form-control" placeholder="Mobile Number *" required value="<?= e($_POST['contact_no'] ?? '') ?>"></div><div class="col-12"><input type="email" name="email" class="form-control" placeholder="Email Address (optional)" value="<?= e($_POST['email'] ?? '') ?>"></div></div>
<h6 class="border-bottom pb-2 mb-3">Address</h6><div class="row g-2 mb-3"><div class="col-md-3"><input name="house_no" class="form-control" placeholder="House No. *" required value="<?= e($_POST['house_no'] ?? '') ?>"></div><div class="col-md-3"><input name="street" class="form-control" placeholder="Street *" required value="<?= e($_POST['street'] ?? '') ?>"></div><div class="col-md-3"><input name="barangay" class="form-control" placeholder="Barangay *" required value="<?= e($_POST['barangay'] ?? '') ?>"></div><div class="col-md-3"><input name="municipality" class="form-control" placeholder="Municipality/City *" required value="<?= e($_POST['municipality'] ?? '') ?>"></div><div class="col-12"><input name="province" class="form-control" placeholder="Province *" required value="<?= e($_POST['province'] ?? '') ?>"></div></div>
<h6 class="border-bottom pb-2 mb-3">Purpose of Visit</h6><div class="row g-2 mb-3"><div class="col-md-6"><select name="purpose" id="purpose" class="form-select" required><option value="">Select purpose *</option><?php foreach ($purposes as $purpose): ?><option value="<?= e($purpose) ?>"<?= ($_POST['purpose'] ?? '') === $purpose ? ' selected' : '' ?>><?= e($purpose) ?></option><?php endforeach; ?></select></div><div class="col-md-6" id="otherPurposeWrap"><input name="purpose_other" class="form-control" placeholder="Specify other purpose" value="<?= e($_POST['purpose_other'] ?? '') ?>"></div></div>
<h6 class="border-bottom pb-2 mb-3">Government-Issued ID Verification</h6><p class="small text-muted">Present a valid ID. Its barcode can be scanned or entered manually.</p><div class="row g-2"><div class="col-md-6"><select name="id_type" class="form-select" required><option value="">ID Type *</option><?php foreach ($idTypes as $idType): ?><option value="<?= e($idType) ?>"<?= ($_POST['id_type'] ?? '') === $idType ? ' selected' : '' ?>><?= e($idType) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><input name="id_barcode" class="form-control" placeholder="ID Barcode Number *" required value="<?= e($_POST['id_barcode'] ?? '') ?>"></div></div><button type="submit" class="btn btn-success w-100 mt-4 py-2 fw-semibold">Send SMS OTP</button></form><hr><a href="register.php" class="text-decoration-none d-block text-center">← Back to registration options</a></div></div></div>
<script>
const purpose=document.getElementById('purpose'),otherWrap=document.getElementById('otherPurposeWrap');function toggleOtherPurpose(){otherWrap.classList.toggle('d-none',purpose.value!=='Others')}purpose.addEventListener('change',toggleOtherPurpose);toggleOtherPurpose();
(()=>{const v=document.getElementById('cam'),c=document.getElementById('snap'),p=document.getElementById('preview'),f=document.getElementById('photo_data'),m=document.getElementById('cam-msg'),s=document.getElementById('btn-start'),x=document.getElementById('btn-capture'),r=document.getElementById('btn-retake');let stream=null;const stop=()=>{if(stream){stream.getTracks().forEach(t=>t.stop());stream=null}};s.addEventListener('click',()=>{if(!navigator.mediaDevices?.getUserMedia){m.textContent='Camera not supported.';return}navigator.mediaDevices.getUserMedia({video:{facingMode:'user'},audio:false}).then(a=>{stream=a;v.srcObject=a;s.classList.add('d-none');x.classList.remove('d-none');m.textContent='Position yourself, then capture.'}).catch(()=>m.textContent='Could not access the camera.');});x.addEventListener('click',()=>{if(!stream)return;c.width=v.videoWidth||320;c.height=v.videoHeight||240;c.getContext('2d').drawImage(v,0,0,c.width,c.height);f.value=c.toDataURL('image/jpeg',.85);p.src=f.value;p.classList.remove('d-none');v.classList.add('d-none');x.classList.add('d-none');r.classList.remove('d-none');stop();m.textContent='Photo captured.'});r.addEventListener('click',()=>{f.value='';p.classList.add('d-none');v.classList.remove('d-none');r.classList.add('d-none');s.classList.remove('d-none');s.click();});addEventListener('beforeunload',stop)})();
</script></body></html>
