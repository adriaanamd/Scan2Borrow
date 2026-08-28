<?php
require_once __DIR__ . '/includes/auth.php';
require_staff();

$pdo = db();
ensure_book_accession_column($pdo); // self-healing column

$error = $success = '';

// Approve / Reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    $req = $pdo->prepare('SELECT vb.*, v.firstname, v.lastname, v.visitor_number, v.photo AS visitor_photo, v.id_barcode, v.contact_no, v.email,
                          b.title, b.author, b.accession_no, b.barcode, b.isbn, b.category_name, b.floor_no, b.section_name, b.shelf_no, b.row_no
                          FROM visitor_borrowing vb
                          JOIN visitors v ON v.id = vb.visitor_id
                          JOIN books b ON b.id = vb.book_id
                          WHERE vb.id = ?');
    $req->execute([$id]);
    $r = $req->fetch();

    if (!$r) {
        $error = 'Request not found.';
    } elseif ($action === 'approve') {
        $pdo->prepare('UPDATE visitor_borrowing SET request_status = "Released", released_at = NOW(), review_notes = ? WHERE id = ?')->execute([$notes ?: null, $id]);
        $pdo->prepare('INSERT INTO visitor_notifications (visitor_id, title, message) VALUES (?, ?, ?)')
            ->execute([$r['visitor_id'], 'Borrow request approved', '"'.$r['title'].'" has been approved and released. Due on '.date('M d, Y', strtotime($r['due_date'])).'.']);
        visitor_log($pdo, (int)$r['visitor_id'], 'borrow_approved', 'Request approved by staff for '.$r['title']);
        audit_log($pdo, (int)$_SESSION['user_id'], 'guest_borrow_approve', 'Approved guest request ID: '.$id.' ('.full_name($r).')');
        $success = 'Approved — the guest can now view and print their receipt.';
    } elseif ($action === 'reject') {
        if ($notes === '') {
            $error = 'A reason is required to reject a request.';
        } else {
            $pdo->prepare('UPDATE visitor_borrowing SET request_status = "Rejected", review_notes = ? WHERE id = ?')->execute([$notes, $id]);
            $pdo->prepare('INSERT INTO visitor_notifications (visitor_id, title, message) VALUES (?, ?, ?)')
                ->execute([$r['visitor_id'], 'Borrow request rejected', 'Your request for "'.$r['title'].'" was rejected. Reason: '.$notes]);
            visitor_log($pdo, (int)$r['visitor_id'], 'borrow_rejected', 'Request rejected by staff for '.$r['title']);
            audit_log($pdo, (int)$_SESSION['user_id'], 'guest_borrow_reject', 'Rejected guest request ID: '.$id);
            $success = 'Request rejected.';
        }
    }
}

// Pending list
$pending = $pdo->query('SELECT vb.*, v.firstname, v.lastname, v.visitor_number, v.photo AS visitor_photo, v.id_barcode,
                        b.title, b.author, b.accession_no, b.barcode
                        FROM visitor_borrowing vb
                        JOIN visitors v ON v.id = vb.visitor_id
                        JOIN books b ON b.id = vb.book_id
                        WHERE vb.request_status = "Pending"
                        ORDER BY vb.requested_at ASC')->fetchAll();

$pageTitle = 'Guest Requests';
$csrfToken = csrf_token();
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div><p>Approve or reject guest book borrow requests after reviewing the captured verification photo.</p></div>
</div>

<?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="table-card">
    <div class="section-title"><span class="dot"></span> Pending Guest Requests <span class="badge bg-warning text-dark ms-1"><?= count($pending) ?></span></div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Guest</th><th>Book</th><th>Accession</th><th>Requested</th><th>Verification</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$pending): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No pending guest borrow requests.</td></tr>
            <?php endif; ?>
            <?php foreach ($pending as $p): ?>
                <tr>
                    <td>
                        <?php if ($p['visitor_photo']): ?><img src="<?= e($p['visitor_photo']) ?>" class="rounded-circle me-2" style="width:38px;height:38px;object-fit:cover" alt="Guest photo"><?php endif; ?>
                        <strong><?= e(full_name($p)) ?></strong><br>
                        <span class="text-muted small"><?= e($p['visitor_number'] ?: '—') ?> · <?= e($p['id_barcode']) ?></span>
                    </td>
                    <td><?= e($p['title']) ?><br><span class="text-muted small"><?= e($p['author']) ?></span></td>
                    <td><code><?= e($p['accession_no'] ?: $p['barcode']) ?></code></td>
                    <td><?= e(date('M d, Y g:i A', strtotime($p['requested_at'] ?: $p['created_at']))) ?></td>
                    <td>
                        <?php if ($p['verification_photo']): ?>
                            <a href="#" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#viewPhotoModal" data-photo="<?= e($p['verification_photo']) ?>" data-name="<?= e(full_name($p)) ?>" data-book="<?= e($p['title']) ?>">View</a>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#reviewModal"
                                data-id="<?= (int)$p['id'] ?>"
                                data-name="<?= e(full_name($p)) ?>"
                                data-photo="<?= e($p['visitor_photo']) ?>"
                                data-visno="<?= e($p['visitor_number'] ?: '—') ?>"
                                data-idbarcode="<?= e($p['id_barcode']) ?>"
                                data-title="<?= e($p['title']) ?>"
                                data-author="<?= e($p['author']) ?>"
                                data-accession="<?= e($p['accession_no'] ?: $p['barcode']) ?>"
                                data-verif="<?= e($p['verification_photo']) ?>">Review</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Photo viewer modal -->
<div class="modal fade" id="viewPhotoModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Verification Photo</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body text-center">
        <img id="photoViewer" src="" alt="Verification photo" class="img-fluid rounded border" style="max-height:60vh;width:auto">
        <div class="small text-muted mt-2" id="photoCaption"></div>
    </div>
</div></div></div>

<!-- Advanced review/approval modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content">
    <div class="modal-header text-white" style="background:var(--primary);">
        <h5 class="modal-title">&#128203; Review Guest Borrow Request</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <div class="row g-4">
            <!-- Verification photo -->
            <div class="col-md-5 text-center">
                <div class="small text-muted text-uppercase fw-bold mb-2">Live Verification Photo</div>
                <img id="review-verif" src="" alt="Verification photo" class="img-fluid rounded border" style="max-height:340px">
                <div class="small text-muted mt-2">The guest must be holding the requested book while facing the camera.</div>
            </div>
            <!-- Book + borrower info -->
            <div class="col-md-7">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img id="review-photo" src="" alt="Borrower photo" class="rounded-circle" style="width:76px;height:76px;object-fit:cover">
                    <div>
                        <h4 class="mb-0" id="review-name"></h4>
                        <div class="text-muted small" id="review-visno"></div>
                        <div class="text-muted small" id="review-idbarcode"></div>
                    </div>
                </div>
                <table class="table table-sm mb-3">
                    <tr><th>Book Title</th><td id="review-title"></td></tr>
                    <tr><th>Author</th><td id="review-author"></td></tr>
                    <tr><th>Accession No.</th><td id="review-accession"></td></tr>
                </table>
                <label class="form-label fw-semibold">Notes / Reason <span class="text-muted small">(required when rejecting)</span></label>
                <textarea id="review-notes" class="form-control mb-3" rows="2" placeholder="Optional approval note, or rejection reason..."></textarea>
                <form method="post" class="d-flex gap-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" id="review-id">
                    <button class="btn btn-danger flex-fill" name="action" value="reject" onclick="return document.getElementById('review-notes').value.trim() !== '' || confirm('Reject without a reason?')">&#10008; Reject</button>
                    <button class="btn btn-success flex-fill" name="action" value="approve">&#10003; Approve & Release</button>
                </form>
            </div>
        </div>
    </div>
</div></div></div>

<script src="assets/js/scanner.js" defer></script>
<script>
(function () {
    var review = document.getElementById('reviewModal');
    if (!review) return;
    review.addEventListener('show.bs.modal', function (e) {
        var b = e.relatedTarget;
        document.getElementById('review-id').value = b.dataset.id;
        document.getElementById('review-name').textContent = b.dataset.name;
        document.getElementById('review-visno').textContent = 'Visitor No. ' + b.dataset.visno;
        document.getElementById('review-idbarcode').textContent = 'ID Barcode: ' + b.dataset.idbarcode;
        document.getElementById('review-title').textContent = b.dataset.title;
        document.getElementById('review-author').textContent = b.dataset.author || '—';
        document.getElementById('review-accession').textContent = b.dataset.accession;
        document.getElementById('review-notes').value = '';
        document.getElementById('review-photo').src = b.dataset.photo || '';
        document.getElementById('review-verif').src = b.dataset.verif || '';
    });

    var viewer = document.getElementById('viewPhotoModal');
    if (viewer) {
        viewer.addEventListener('show.bs.modal', function (e) {
            var b = e.relatedTarget;
            document.getElementById('photoViewer').src = b.dataset.photo;
            document.getElementById('photoCaption').textContent = (b.dataset.name || '') + (b.dataset.book ? ' holding "' + b.dataset.book + '"' : '');
        });
    }
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
