<?php
require_once __DIR__ . '/includes/auth.php';
require_borrower();

refresh_overdue_status();

$uid = (int) $_SESSION['user_id'];

$stmt = db()->prepare("
    SELECT br.*, b.title, b.author
    FROM borrowing br
    JOIN books b ON b.id = br.book_id
    WHERE br.user_id = ?
    ORDER BY br.borrow_date DESC
");
$stmt->execute([$uid]);
$history = $stmt->fetchAll();

$pageTitle = 'My History';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div><p>Your complete borrowing record.</p></div>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr><th>Code</th><th>Book</th><th>Borrowed</th><th>Due</th><th>Returned</th><th>Status</th><th>Fine</th></tr>
            </thead>
            <tbody>
            <?php if (!$history): ?>
                <tr><td colspan="7" class="text-center text-muted">No borrowing history yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($history as $h): ?>
                <tr class="<?= (!$h['return_date'] && $h['status'] === 'Overdue') ? 'row-overdue' : '' ?>">
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

<?php require __DIR__ . '/includes/footer.php'; ?>
