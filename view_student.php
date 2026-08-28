<?php
require_once __DIR__ . '/includes/auth.php';
require_staff();

refresh_overdue_status();

$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare("SELECT * FROM users WHERE id = ? AND role IN ('student','teacher') LIMIT 1");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    die('Borrower not found.');
}

$photoMsg = $photoErr = '';
if (isset($_POST['update_photo'])) {
    csrf_check();
    if (!is_admin()) {
        $photoErr = 'Only an administrator can change a borrower photo.';
    } else {
        $path = save_uploaded_photo($_FILES['photo_file'] ?? [], $student['barcode']);
        if ($path === null) {
            $photoErr = 'Please choose a valid image file (JPG, PNG, WEBP or GIF, max 4 MB).';
        } else {
            db()->prepare('UPDATE users SET photo = ? WHERE id = ?')->execute([$path, $id]);
            $student['photo'] = $path;
            $photoMsg = 'ID photo updated.';
        }
    }
}

$hstmt = db()->prepare("
    SELECT br.*, b.title, b.author
    FROM borrowing br
    JOIN books b ON b.id = br.book_id
    WHERE br.user_id = ?
    ORDER BY br.borrow_date DESC
");
$hstmt->execute([$id]);
$history = $hstmt->fetchAll();

$activeCount = $returnedCount = $overdueCount = 0;
$totalFine = 0.0;
foreach ($history as $h) {
    if ($h['return_date']) {
        $returnedCount++;
    } else {
        $activeCount++;
        if ($h['status'] === 'Overdue') {
            $overdueCount++;
            $totalFine += (float) $h['fine_amount'];
        }
    }
}
$accountStatus = $student['status'];

$pageTitle = 'Borrower Details';
require __DIR__ . '/includes/header.php';
?>

<a href="adstud.php" class="btn btn-outline-secondary btn-sm mb-3">&larr; Back to Borrowers</a>

<div class="table-card mb-4">
    <div class="d-flex align-items-center flex-wrap gap-3">
        <div class="text-center">
            <?php if (!empty($student['photo'])): ?>
                <img src="<?= e($student['photo']) ?>" alt="ID photo" class="profile-avatar"
                     style="object-fit:cover;padding:0;">
            <?php else: ?>
                <div class="profile-avatar">
                    <?= e(strtoupper(substr($student['firstname'], 0, 1) . substr($student['lastname'], 0, 1))) ?>
                </div>
            <?php endif; ?>
            <?php if (is_admin()): ?>
                <button type="button" class="btn btn-outline-primary btn-sm mt-2 py-0 px-2" style="font-size:12px;"
                        data-bs-toggle="modal" data-bs-target="#photoModal">
                    <?= !empty($student['photo']) ? 'Change Photo' : 'Upload Photo' ?>
                </button>
            <?php endif; ?>
        </div>
        <div class="flex-grow-1">
            <h3 class="mb-1"><?= e(full_name($student)) ?></h3>
            <div class="text-muted">
                ID: <?= e($student['barcode']) ?> &middot; <?= e(ucfirst($student['role'])) ?>
                <?php if ($student['course']): ?> &middot; <?= e($student['course']) ?><?php endif; ?>
                <?php if ($student['year_level']): ?> &middot; Year <?= e($student['year_level']) ?><?php endif; ?>
            </div>
            <div class="text-muted">
                <?= e($student['email']) ?><?php if ($student['contact_no']): ?> &middot; <?= e($student['contact_no']) ?><?php endif; ?>
            </div>
        </div>
        <div class="text-end">
            <?= status_badge($accountStatus) ?>
            <?php if ($overdueCount > 0): ?>
                <span class="badge bg-danger"><?= $overdueCount ?> Overdue</span>
            <?php endif; ?>
            <div class="mt-2">
                <a href="send_notification.php?id=<?= (int) $student['id'] ?>" class="btn btn-warning btn-sm">Notify by Email</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6"><div class="stat-card"><div class="icon"></div><div><div class="label">Currently Borrowed</div><div class="value"><?= $activeCount ?></div></div></div></div>
    <div class="col-md-3 col-6"><div class="stat-card stat-accent"><div class="icon"></div><div><div class="label">Returned</div><div class="value"><?= $returnedCount ?></div></div></div></div>
    <div class="col-md-3 col-6"><div class="stat-card stat-danger"><div class="icon"></div><div><div class="label">Overdue</div><div class="value"><?= $overdueCount ?></div></div></div></div>
    <div class="col-md-3 col-6"><div class="stat-card stat-warning"><div class="icon"></div><div><div class="label">Unpaid Fines</div><div class="value"><?= peso($totalFine) ?></div></div></div></div>
</div>

<div class="table-card">
    <h5 class="mb-3">Borrowing History <span class="text-muted fs-6">(<?= count($history) ?> records)</span></h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Code</th><th>Book</th><th>Borrowed</th><th>Due</th>
                    <th>Returned</th><th>Status</th><th>Fine</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$history): ?>
                <tr><td colspan="7" class="text-center text-muted">No borrowing history found.</td></tr>
            <?php endif; ?>
            <?php foreach ($history as $h):
                $overdue = !$h['return_date'] && $h['status'] === 'Overdue'; ?>
                <tr class="<?= $overdue ? 'row-overdue' : '' ?>">
                    <td><code><?= e($h['transaction_code']) ?></code></td>
                    <td><?= e($h['title']) ?><br><span class="text-muted small"><?= e($h['author']) ?></span></td>
                    <td><?= e(date('M d, Y', strtotime($h['borrow_date']))) ?></td>
                    <td><?= e(date('M d, Y', strtotime($h['due_date']))) ?></td>
                    <td><?= $h['return_date'] ? e(date('M d, Y', strtotime($h['return_date']))) : '<span class="text-muted">&mdash;</span>' ?></td>
                    <td><?= status_badge($h['status']) ?></td>
                    <td><?= ((float) $h['fine_amount'] > 0) ? peso((float) $h['fine_amount']) : '&mdash;' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (is_admin()): ?>
<div class="modal fade" id="photoModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="modal-header text-white" style="background:var(--primary);">
          <h5 class="modal-title">Borrower ID Photo</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body text-center">
          <?php if ($photoMsg !== ''): ?><div class="alert alert-success"><?= e($photoMsg) ?></div><?php endif; ?>
          <?php if ($photoErr !== ''): ?><div class="alert alert-danger"><?= e($photoErr) ?></div><?php endif; ?>

          <img id="preview"
               src="<?= !empty($student['photo']) ? e($student['photo']) : '' ?>"
               class="img-fluid rounded mx-auto mb-2 <?= !empty($student['photo']) ? '' : 'd-none' ?>"
               style="max-width:240px;aspect-ratio:4/3;object-fit:cover;" alt="Photo preview">

          <label class="form-label fw-semibold d-block text-start">Choose a photo to upload</label>
          <input type="file" name="photo_file" id="photo_file" accept="image/*" class="form-control" required>
          <div class="form-text text-start">JPG, PNG, WEBP or GIF &middot; max 4 MB.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="update_photo" class="btn btn-primary fw-semibold">Upload Photo</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($photoMsg !== '' || $photoErr !== ''): ?>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    new bootstrap.Modal(document.getElementById('photoModal')).show();
  });
</script>
<?php endif; ?>

<script>
(function () {
    var input   = document.getElementById('photo_file');
    var preview = document.getElementById('preview');
    if (!input || !preview) { return; }
    input.addEventListener('change', function () {
        var f = input.files && input.files[0];
        if (!f) { return; }
        preview.src = URL.createObjectURL(f);
        preview.classList.remove('d-none');
    });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
