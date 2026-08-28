<?php
require_once __DIR__ . '/includes/visitor_portal.php'; 
$pdo=db();
$visitor=current_visitor($pdo);
$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){csrf_check();
$f=['contact_no'=>trim($_POST['contact_no']??''),'email'=>trim($_POST['email']??''),'house_no'=>trim($_POST['house_no']??''),'street'=>trim($_POST['street']??''),'barangay'=>trim($_POST['barangay']??''),'municipality'=>trim($_POST['municipality']??''),'province'=>trim($_POST['province']??''),'purpose'=>trim($_POST['purpose']??''),'purpose_other'=>trim($_POST['purpose_other']??'')];
if(in_array('',[$f['contact_no'],$f['house_no'],$f['street'],$f['barangay'],$f['municipality'],$f['province'],$f['purpose']],true))$error='Please complete all required fields.';
elseif(!preg_match('/^[0-9+\-\s()]{7,15}$/',$f['contact_no']))$error='Please enter a valid mobile number.';
elseif($f['email']!==''&&!filter_var($f['email'],FILTER_VALIDATE_EMAIL))$error='Please enter a valid email address.';
elseif(!in_array($f['purpose'],['Research','Reading','Thesis','Review','Others'],true)||($f['purpose']==='Others'&&$f['purpose_other']===''))$error='Please select or specify a valid purpose.';
else{if($f['contact_no']!==$visitor['contact_no']){$f['flow']='guest_profile_update';
$f['visitor_id']=$visitor['id'];
$f['barcode']='GUEST-UPD-'.strtoupper(bin2hex(random_bytes(10)));
save_otp_registration($pdo,$f,$f['contact_no']);
$_SESSION['guest_profile_otp_token']=$f['barcode'];redirect('guest_profile_verify_otp.php');
    }
$s=$pdo->prepare('UPDATE visitors SET email=?,house_no=?,street=?,barangay=?,municipality=?,province=?,purpose=?,purpose_other=? WHERE id=?');
$s->execute([$f['email']?:null,$f['house_no'],$f['street'],$f['barangay'],$f['municipality'],$f['province'],$f['purpose'],$f['purpose']==='Others'?$f['purpose_other']:null,$visitor['id']]);
visitor_log($pdo,$visitor['id'],'profile_update','Visitor updated profile details.');
$success='Profile updated.';$visitor=current_visitor($pdo);
        }
    }
visitor_portal_header('My Profile',$visitor);
?><h2 class="mb-3">Settings</h2><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;
?>
<?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;
?><div class="card"><div class="card-body"><div class="alert alert-info small">Government ID type, ID barcode, visitor number, and verification status can only be changed by a librarian.</div><form method="post"><?=csrf_field()?><div class="row g-3"><div class="col-md-6"><label class="form-label">Contact Number *</label><input class="form-control" name="contact_no" required value="<?=e($visitor['contact_no'])?>"></div><div class="col-md-6"><label class="form-label">Email</label><input class="form-control" name="email" type="email" value="<?=e($visitor['email'])?>"></div><div class="col-md-3"><label class="form-label">House No. *</label><input class="form-control" name="house_no" required value="<?=e($visitor['house_no'])?>"></div><div class="col-md-3"><label class="form-label">Street *</label><input class="form-control" name="street" required value="<?=e($visitor['street'])?>"></div><div class="col-md-3"><label class="form-label">Barangay *</label><input class="form-control" name="barangay" required value="<?=e($visitor['barangay'])?>"></div><div class="col-md-3"><label class="form-label">Municipality/City *</label><input class="form-control" name="municipality" required value="<?=e($visitor['municipality'])?>"></div><div class="col-md-6"><label class="form-label">Province *</label><input class="form-control" name="province" required value="<?=e($visitor['province'])?>"></div><div class="col-md-3"><label class="form-label">Purpose *</label><select name="purpose" class="form-select"><?php foreach(['Research','Reading','Thesis','Review','Others'] as $p):?><option<?=$visitor['purpose']===$p?' selected':''?>><?=e($p)?></option><?php endforeach;
?></select></div><div class="col-md-3"><label class="form-label">Other purpose</label><input class="form-control" name="purpose_other" value="<?=e($visitor['purpose_other'])?>"></div></div><button class="btn btn-success mt-4">Save Changes</button></form></div></div><?php visitor_portal_footer(); 
?>
