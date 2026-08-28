<?php
require_once __DIR__ . '/includes/auth.php';
require_staff();

refresh_overdue_status();

$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT u.*,
        (SELECT COUNT(*) FROM borrowing br
          WHERE br.user_id = u.id AND br.return_date IS NULL) AS active_loans,
        (SELECT COUNT(*) FROM borrowing br
          WHERE br.user_id = u.id AND br.return_date IS NULL AND br.status = 'Overdue') AS overdue_loans
    FROM users u
    WHERE u.role IN ('student','teacher')
";
$params = [];
if ($search !== '') {
    $sql .= " AND (u.barcode LIKE :q OR CONCAT(u.firstname,' ',u.lastname) LIKE :q OR u.course LIKE :q)";
    $params[':q'] = "%$search%";
}
$sql .= " ORDER BY u.lastname ASC, u.firstname ASC";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'Borrowers';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div><p>Manage students, and monitor their borrowing activity.</p></div>
</div>

<div class="table-card">
    <form method="GET" class="mb-3" style="max-width:380px;">
        <div class="input-group">
            <input type="text" name="search" class="form-control"
                   placeholder="Search by name, ID or course..." value="<?= e($search) ?>">
            <button class="btn btn-primary">Search</button>
            <?php if ($search !== ''): ?>
                <a href="adstud.php" class="btn btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>ID Barcode</th><th>Name</th><th>Role</th><th>Department</th><th>Position</th><th>Course</th>
                    <th>Year</th><th>Active</th><th>Status</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="10" class="text-center text-muted">No borrowers found.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $u): ?>
                <tr>
                    <td><?= e($u['barcode']) ?></td>
                    <td><?= e(full_name($u)) ?></td>
                    <td><?= e(ucfirst($u['role'])) ?></td>
                    <td><?= e($u['department'] ?? '—') ?></td>
                    <td><?= e($u['position'] ?? '—') ?></td>
                    <td><?= e($u['course']) ?></td>
                    <td><?= e($u['year_level']) ?></td>
                    <td>
                        <span class="badge bg-primary"><?= (int) $u['active_loans'] ?></span>
                        <?php if ((int) $u['overdue_loans'] > 0): ?>
                            <span class="badge bg-danger"><?= (int) $u['overdue_loans'] ?> overdue</span>
                        <?php endif; ?>
                    </td>
                    <td><?= status_badge($u['status']) ?></td>
                    <td class="text-nowrap">
                        <a href="view_student.php?id=<?= (int) $u['id'] ?>" class="btn btn-primary btn-sm">View</a>
                        <a href="send_notification.php?id=<?= (int) $u['id'] ?>" class="btn btn-warning btn-sm">Notify</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
