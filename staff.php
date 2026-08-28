<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$pdo = db();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);
    $borrowingId = (int) ($_POST['borrowing_id'] ?? 0);

    if ($userId === (int) $_SESSION['user_id']) {
        $err = 'You cannot change your own account here.';
    } elseif ($action === 'promote') {
        $password = (string) ($_POST['password'] ?? '');
        $role     = $_POST['role'] === 'admin' ? 'admin' : 'librarian';
        if (strlen($password) < 6) {
            $err = 'Password must be at least 6 characters.';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET role = ?, password_hash = ?, status = 'active' WHERE id = ?");
            $stmt->execute([$role, password_hash($password, PASSWORD_DEFAULT), $userId]);
            $msg = 'Account promoted to ' . ucfirst($role) . '. They can now use the Staff Login.';
        }
    } elseif ($action === 'reset_password') {
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 6) {
            $err = 'Password must be at least 6 characters.';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
            $msg = 'Password updated.';
        }
    } elseif ($action === 'demote') {
        $stmt = $pdo->prepare("UPDATE users SET role = 'student', password_hash = NULL WHERE id = ? AND role IN ('admin','librarian')");
        $stmt->execute([$userId]);
        $msg = 'Account changed back to Borrower.';
    } elseif ($action === 'toggle_status') {
        $stmt = $pdo->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?");
        $stmt->execute([$userId]);
        $msg = 'Account status updated.';
    } elseif (($action === 'approve' || $action === 'reject') && $borrowingId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM borrowing WHERE id = ? LIMIT 1');
        $stmt->execute([$borrowingId]);
        $borrowing = $stmt->fetch();
        
        if ($borrowing) {
            if ($action === 'approve') {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('UPDATE borrowing SET approval_status = "approved", approved_at = NOW(), approved_by = ? WHERE id = ?')
                        ->execute([$_SESSION['user_id'], $borrowingId]);
                    $pdo->prepare('UPDATE books SET status = "Borrowed" WHERE id = ?')
                        ->execute([$borrowing['book_id']]);
                    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE related_id = ? AND user_id = ?')
                        ->execute([$borrowingId, $_SESSION['user_id']]);
                    $pdo->commit();
                    audit_log($pdo, $_SESSION['user_id'], 'borrow_approve', "Approved borrowing ID: $borrowingId");
                    $msg = 'Borrow request approved successfully.';
                } catch (Throwable $ex) {
                    $pdo->rollBack();
                    $err = 'Approval failed: ' . $ex->getMessage();
                }
            } elseif ($action === 'reject') {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('UPDATE borrowing SET approval_status = "rejected", approved_at = NOW(), approved_by = ? WHERE id = ?')
                        ->execute([$_SESSION['user_id'], $borrowingId]);
                    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE related_id = ? AND user_id = ?')
                        ->execute([$borrowingId, $_SESSION['user_id']]);
                    $pdo->commit();
                    audit_log($pdo, $_SESSION['user_id'], 'borrow_reject', "Rejected borrowing ID: $borrowingId");
                    $msg = 'Borrow request rejected.';
                } catch (Throwable $ex) {
                    $pdo->rollBack();
                    $err = 'Rejection failed: ' . $ex->getMessage();
                }
            }
        }
    }
}

$staff = $pdo->query("
    SELECT * FROM users WHERE role IN ('admin','librarian') ORDER BY role ASC, lastname ASC
")->fetchAll();

$search = trim($_GET['bsearch'] ?? '');
$bsql = "SELECT * FROM users WHERE role IN ('student','teacher')";
$bparams = [];
if ($search !== '') {
    $bsql .= " AND (barcode LIKE :q OR CONCAT(firstname,' ',lastname) LIKE :q)";
    $bparams[':q'] = "%$search%";
}
$bsql .= " ORDER BY lastname ASC LIMIT 25";
$bstmt = $pdo->prepare($bsql);
$bstmt->execute($bparams);
$borrowers = $bstmt->fetchAll();

$pageTitle = 'Staff';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-1">Staff Management</h3>
            <p class="text-muted mb-0">Manage librarian / administrator accounts and assign staff roles.</p>
        </div>
        <div>
        </div>
    </div>
</div>

<?php if ($msg !== ''): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err !== ''): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>

<div class="table-card mb-4">
    <div class="section-title"><span class="dot"></span> Staff Accounts</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr><th>ID Barcode</th><th>Name</th><th>Role</th><th>Email</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (!$staff): ?>
                <tr><td colspan="6" class="text-center text-muted">No staff accounts.</td></tr>
            <?php endif; ?>
            <?php foreach ($staff as $s): ?>
                <?php $self = (int) $s['id'] === (int) $_SESSION['user_id']; ?>
                <tr>
                    <td><?= e($s['barcode']) ?></td>
                    <td><?= e(full_name($s)) ?><?php if ($self): ?> <span class="badge bg-light text-muted border">you</span><?php endif; ?></td>
                    <td><span class="badge bg-<?= $s['role'] === 'admin' ? 'dark' : 'primary' ?>"><?= e(ucfirst($s['role'])) ?></span></td>
                    <td class="text-muted small"><?= e($s['email']) ?></td>
                    <td><?= status_badge($s['status']) ?></td>
                    <td class="text-nowrap">
                        <?php if ($self): ?>
                            <span class="text-muted small">&mdash;</span>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal"
                                    data-bs-target="#pwModal" data-uid="<?= (int) $s['id'] ?>"
                                    data-name="<?= e(full_name($s)) ?>">Reset Password</button>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int) $s['id'] ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <button class="btn btn-outline-warning btn-sm"><?= $s['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                            </form>
                            <?php if ($s['role'] !== 'admin'): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Change this librarian back to a borrower?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int) $s['id'] ?>">
                                <input type="hidden" name="action" value="demote">
                                <button class="btn btn-outline-danger btn-sm">Demote</button>
                            </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="table-card">
    <div class="section-title"><span class="dot"></span> Assign Staff Role</div>
    <p class="text-muted small">Pick a registered borrower and promote them to Librarian. They will use the <strong>Staff Login</strong> with the password you set.</p>

    <form method="GET" class="mb-3" style="max-width:380px;">
        <div class="input-group">
            <input type="text" name="bsearch" class="form-control" placeholder="Search borrower by name or ID..." value="<?= e($search) ?>">
            <button class="btn btn-primary">Search</button>
            <?php if ($search !== ''): ?><a href="staff.php" class="btn btn-outline-secondary">Clear</a><?php endif; ?>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>ID Barcode</th><th>Name</th><th>Course</th><th>Action</th></tr></thead>
            <tbody>
            <?php if (!$borrowers): ?>
                <tr><td colspan="4" class="text-center text-muted">No borrowers found.</td></tr>
            <?php endif; ?>
            <?php foreach ($borrowers as $b): ?>
                <tr>
                    <td><?= e($b['barcode']) ?></td>
                    <td><?= e(full_name($b)) ?></td>
                    <td class="text-muted"><?= e($b['course']) ?></td>
                    <td>
                        <button class="btn btn-gradient btn-sm" data-bs-toggle="modal"
                                data-bs-target="#promoteModal" data-uid="<?= (int) $b['id'] ?>"
                                data-name="<?= e(full_name($b)) ?>">&#128081; Promote to Librarian</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="promoteModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="promote">
        <input type="hidden" name="user_id" id="promote_uid">
        <div class="modal-header text-white" style="background:var(--primary);">
          <h5 class="modal-title">&#128081; Promote to Staff</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2">Promoting <strong id="promote_name"></strong>.</p>
          <label class="form-label fw-semibold">Role</label>
          <select name="role" class="form-select mb-3">
              <option value="librarian">Librarian</option>
              <option value="admin">Administrator</option>
          </select>
          <label class="form-label fw-semibold">Set Login Password</label>
          <input type="text" name="password" class="form-control" placeholder="At least 6 characters" required minlength="6">
          <p class="scan-hint mt-2 mb-0">Share this password with the staff member; they sign in on the Staff Login page.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-gradient fw-semibold">Confirm Promotion</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="pwModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="user_id" id="pw_uid">
        <div class="modal-header text-white" style="background:var(--primary);">
          <h5 class="modal-title">Reset Password</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2">New password for <strong id="pw_name"></strong>.</p>
          <input type="text" name="password" class="form-control" placeholder="At least 6 characters" required minlength="6">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-semibold">Update Password</button>
        </div>
      </form>
    </div>
  </div>
</div>


<?php require __DIR__ . '/includes/footer.php'; ?>
