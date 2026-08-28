<?php
require_once __DIR__ . '/includes/visitor_portal.php';
$pdo=db(); $visitor=current_visitor($pdo); ensure_book_accession_column($pdo);
$bookId=(int)($_GET['book_id']??$_POST['book_id']??0);
if(!$bookId && !empty($_GET['book_barcode'])){$lookup=$pdo->prepare('SELECT id FROM books WHERE (barcode=? OR accession_no=?) LIMIT 1');$lookup->execute([trim($_GET['book_barcode']),trim($_GET['book_barcode'])]);$bookId=(int)$lookup->fetchColumn();}
$q=$pdo->prepare('SELECT * FROM books WHERE id=? AND status="Available" AND deleted_at IS NULL'); $q->execute([$bookId]); $book=$q->fetch(); if(!$book) redirect('guest_browse_books.php'); $error='';
if($_SERVER['REQUEST_METHOD']==='POST') { csrf_check(); $governmentIdBarcode=trim($_POST['government_id_barcode']??''); $photo=save_captured_photo(trim($_POST['verification_photo']??''),'guest-borrow-'.$bookId);
    if($governmentIdBarcode==='' || !hash_equals((string)$visitor['id_barcode'],$governmentIdBarcode)) $error='Scan the same government-issued ID barcode used during registration.';
    elseif(!$photo) $error='Capture a clear live verification photo before submitting.';
    elseif(in_array($visitor['account_status'],['Expired','Suspended'],true)) $error='Your registration is not eligible for borrowing.';
    else { $count=$pdo->prepare('SELECT COUNT(*) FROM visitor_borrowing WHERE visitor_id=? AND return_date IS NULL AND request_status IN ("Pending","Ready for Release","Released")'); $count->execute([$visitor['id']]);
        if((int)$count->fetchColumn()>=MAX_BOOKS_PER_USER) $error='You have reached the borrowing limit.';
        else { $pdo->prepare('INSERT INTO visitor_borrowing(visitor_id,book_id,borrow_date,due_date,request_status,verification_photo,requested_at) VALUES(?,?,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 7 DAY),"Pending",?,NOW())')->execute([$visitor['id'],$bookId,$photo]);
            // Notify guest
            $pdo->prepare('INSERT INTO visitor_notifications(visitor_id,title,message) VALUES(?,?,?)')->execute([$visitor['id'],'Borrow request submitted','Your request for '.$book['title'].' is now pending staff approval.']);
            // Notify staff
            $staff=$pdo->query("SELECT id FROM users WHERE role IN ('admin','librarian') AND status='active'")->fetchAll();
            $notif=$pdo->prepare('INSERT INTO notifications(user_id,type,title,message,related_id) VALUES(?, "borrow_request",?,?,?)');
            $vbId=(int)$pdo->lastInsertId();
            $msg='Guest <strong>'.e(full_name($visitor)).'</strong> ('.e($visitor['visitor_number'] ?: 'VIS-Pending').') requested to borrow <strong>'.e($book['title']).'</strong> (Accession: '.e($book['accession_no'] ?: $book['barcode']).'). Review the verification photo and approve or reject the request.';
            foreach($staff as $s){ $notif->execute([$s['id'],'New Guest Borrow Request',$msg,$vbId]); }
            visitor_log($pdo,$visitor['id'],'borrow_request','Submitted request for '.$book['title'].' (Pending)'); redirect('guest_borrowing_history.php'); }
    }
}
visitor_portal_header('Borrow Request',$visitor);
?>
<img id="pose-example" src="assets/images/book-capture-pose-guide.svg" class="d-none" alt="Correct pose example: face camera and hold the book below the chest with its cover visible">
<style>#captureGuideModal .modal-body .col-md-5 .bg-dark{display:none!important}#cam+div{display:none!important}.position-relative:has(> #cam){display:block;width:min(100%,300px);margin:0 auto;text-align:center;line-height:0}#cam{width:100%!important;height:min(62vh,400px)!important;max-width:300px!important;max-height:400px!important;aspect-ratio:3/4;object-fit:cover;background:#111}.position-relative:has(> #cam)::before{content:'';position:absolute;z-index:2;pointer-events:none;left:9%;top:4%;width:82%;height:91%;border:3px dashed rgba(255,255,255,.95);border-radius:48% 48% 30% 30%;box-shadow:0 0 0 2px rgba(38,199,83,.55) inset}.position-relative:has(> #cam)::after{content:'Place the book here';position:absolute;z-index:3;pointer-events:none;left:18%;bottom:20%;width:64%;height:19%;display:flex;align-items:center;justify-content:center;border:3px dashed #35c759;border-radius:12px;color:#fff;background:rgba(10,35,20,.38);font-weight:700;font-size:12px;letter-spacing:.4px;line-height:1.2}</style>
<script>window.addEventListener('load',()=>{const img=document.getElementById('pose-example');const copy=img.cloneNode();copy.className='img-fluid rounded border mb-3 d-block mx-auto';copy.style.maxHeight='330px';const target=document.querySelector('#captureGuideModal .modal-body .col-md-5');if(target){target.prepend(copy);}new bootstrap.Modal(document.getElementById('captureGuideModal')).show();})</script>
<div class="modal fade" id="captureGuideModal" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h4>How to Pose for Your Book Capture</h4></div><div class="modal-body"><div class="row g-4"><div class="col-md-4"><h5>Follow these steps</h5><ol><li><strong>Face the camera</strong><br><small>Look directly at it.</small></li><li><strong>Hold the book</strong><br><small>Front cover faces the camera.</small></li><li><strong>Position the book</strong><br><small>Below your chest, inside the guide.</small></li><li><strong>Use good lighting</strong><br><small>Face and cover must be clear.</small></li><li><strong>Remove obstructions</strong><br><small>No hats, masks, or covered face.</small></li></ol></div><div class="col-md-5"><div class="bg-dark rounded position-relative text-center text-white d-flex align-items-center justify-content-center" style="height:330px"><div class="position-absolute border border-3 border-success rounded" style="width:75%;height:75%;animation:guidePulse 1.5s infinite"></div><div><div style="font-size:90px">👤</div><div class="border border-success rounded p-3">Hold book cover here</div></div></div><p class="text-center fw-semibold mt-2">Keep your face and the full book cover visible.</p></div><div class="col-md-3"><h6 class="text-success">✓ Good</h6><p class="small">Face and book cover clearly visible.</p><h6 class="text-danger">✕ Avoid</h6><p class="small">Blocked face, hidden book, or looking away.</p></div></div></div><div class="modal-footer"><button class="btn btn-primary btn-lg" data-bs-dismiss="modal">I Understand — Start Capture</button></div></div></div></div><style>@keyframes guidePulse{50%{opacity:.35;transform:scale(.97)}}</style>
<h2>Borrow Request</h2><div class="card"><div class="card-body"><h4><?=e($book['title'])?></h4><p class="text-muted"><?=e($book['author'])?> · Accession <?=e($book['accession_no'] ?: $book['barcode'])?></p><div class="alert alert-info"><strong>Step 1:</strong> Scan the government-issued ID used during registration. <strong>Step 2:</strong> Capture a live photo while holding this book. Your request will be <strong>pending staff approval</strong>.</div><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><form method="post" id="borrowForm"><input type="hidden" name="book_id" value="<?=$bookId?>"><?=csrf_field()?><label class="form-label fw-semibold">Government ID Barcode</label><input class="form-control mb-3" name="government_id_barcode" required autofocus placeholder="Scan or enter your registered government ID barcode"><input type="hidden" id="verification_photo" name="verification_photo"><div class="position-relative text-center"><video id="cam" autoplay playsinline muted style="width:420px;max-width:100%;background:#111;border-radius:10px"></video><div class="position-absolute top-50 start-50 translate-middle border border-3 border-warning rounded" style="width:55%;height:34%;pointer-events:none;animation:pulse 1.5s infinite"></div><div id="camMsg" class="small text-danger mb-2 d-none"></div><canvas id="snap" class="d-none"></canvas><img id="preview" class="d-none img-fluid rounded border" style="max-width:300px;margin:0 auto" alt="Verification preview"></div><div class="d-flex gap-2 justify-content-center my-3"><button type="button" id="start" class="btn btn-outline-primary">Start Camera</button><button type="button" id="capture" class="btn btn-success d-none">Capture</button><button type="button" id="retake" class="btn btn-outline-secondary d-none">Retake</button></div><button type="submit" id="submitBtn" class="btn btn-success w-100 fw-semibold" disabled>Submit Request</button></form></div></div><style>@keyframes pulse{50%{opacity:.35}}</style>


<script>
(() => {
    const start = document.getElementById('start');
    const capture = document.getElementById('capture');
    const retake = document.getElementById('retake');
    const submitBtn = document.getElementById('submitBtn');
    const cam = document.getElementById('cam');
    const snap = document.getElementById('snap');
    const preview = document.getElementById('preview');
    const field = document.getElementById('verification_photo');
    const msg = document.getElementById('camMsg');
    let st = null;

    function setMsg(text) {
        if (!msg) return;
        msg.textContent = text || '';
        msg.classList.toggle('d-none', !text);
    }

    function stopStream() {
        if (!st) return;
        try { st.getTracks().forEach(t => t.stop()); } catch (e) {}
        st = null;
    }

    function resetCamera() {
        stopStream();
        if (cam) cam.classList.remove('d-none');
        if (preview) { preview.classList.add('d-none'); preview.src = ''; }
        if (field) field.value = '';
        if (start) start.classList.remove('d-none');
        if (capture) capture.classList.add('d-none');
        if (retake) retake.classList.add('d-none');
        if (submitBtn) submitBtn.disabled = true;
        setMsg('');
    }

    if (!start || !capture || !retake || !cam || !snap || !preview || !field) return;

    start.addEventListener('click', () => {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setMsg('Camera is not supported on this device.');
            return;
        }
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
            .then(s => {
                stopStream();
                st = s;
                try { cam.srcObject = s; cam.play().catch(() => {}); } catch (e) {}
                if (start) start.classList.add('d-none');
                if (capture) capture.classList.remove('d-none');
                setMsg('');
            })
            .catch(() => setMsg('Could not access the camera. Allow camera permission and try again.'));
    });

    capture.addEventListener('click', () => {
        if (!st) { setMsg('Camera is not active. Please start the camera first.'); return; }
        const width = cam.videoWidth || 640;
        const height = cam.videoHeight || 480;
        snap.width = width; snap.height = height;
        const ctx = snap.getContext('2d');
        ctx.drawImage(cam, 0, 0, width, height);
        const data = snap.toDataURL('image/jpeg', 0.85);
        field.value = data;
        preview.src = data; preview.classList.remove('d-none');
        cam.classList.add('d-none');
        stopStream();
        if (capture) capture.classList.add('d-none');
        if (retake) retake.classList.remove('d-none');
        if (submitBtn) submitBtn.disabled = false;
        setMsg('');
    });

    retake.addEventListener('click', () => {
        resetCamera();
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
                .then(s => { stopStream(); st = s; cam.srcObject = s; })
                .catch(() => setMsg('Unable to restart the camera. Please refresh the page.'));
        }
    });

    window.addEventListener('beforeunload', stopStream);
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalP = document.querySelector('#captureGuideModal .modal-body .col-md-5 p.fw-semibold');

    var camP = document.querySelector('#borrowForm .position-relative p.small.mb-2');
});
</script>

<?php visitor_portal_footer(); ?>
