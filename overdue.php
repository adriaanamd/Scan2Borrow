<?php
require_once __DIR__ . '/includes/auth.php';
require_staff();

refresh_overdue_status();

$rows = db()->query("
    SELECT br.*, b.title, b.barcode AS book_barcode,
           u.id AS user_id, u.barcode AS id_barcode, u.email,
           CONCAT(u.firstname, ' ', u.lastname) AS borrower
    FROM borrowing br
    JOIN books b ON b.id = br.book_id
    JOIN users u ON u.id = br.user_id
    WHERE br.return_date IS NULL AND br.status = 'Overdue'
    ORDER BY br.due_date ASC
")->fetchAll();

$totalFine = 0.0;
foreach ($rows as $r) {
    $totalFine += (float) $r['fine_amount'];
}

$pageTitle = 'Overdue Books';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div><p>Borrowed books past their due date. Fines update automatically each day.</p></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6"><div class="stat-card stat-danger"><div class="icon">&#9888;</div><div><div class="label">Overdue Borrowed Books</div><div class="value"><?= count($rows) ?></div></div></div></div>
    <div class="col-md-3 col-6"><div class="stat-card stat-warning"><div class="icon">&#128176;</div><div><div class="label">Total Fines</div><div class="value"><?= peso($totalFine) ?></div></div></div></div>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Borrower</th><th>Book</th><th>Due Date</th>
                    <th>Days Late</th><th>Fine</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="text-center text-muted">No overdue books. &#127881;</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r):
                $late = days_overdue($r['due_date'], null); ?>
                <tr class="row-overdue">
                    <td><?= e($r['borrower']) ?><br><span class="text-muted small"><?= e($r['id_barcode']) ?></span></td>
                    <td><?= e($r['title']) ?></td>
                    <td><?= e(date('M d, Y', strtotime($r['due_date']))) ?></td>
                    <td><span class="badge bg-danger"><?= $late ?> day(s)</span></td>
                    <td><?= peso((float) $r['fine_amount']) ?></td>
                    <td><a href="send_notification.php?id=<?= (int) $r['user_id'] ?>" class="btn btn-warning btn-sm">Email Reminder</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
