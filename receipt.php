<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

ensure_book_accession_column(db()); // self-healing column

$code = trim($_GET['code'] ?? '');
$txn = null;

if ($code !== '') {
    $stmt = db()->prepare("
        SELECT br.*, b.title, b.author, b.isbn, b.accession_no, b.barcode AS book_barcode,
               b.floor_no, b.section_name, b.shelf_no, b.row_no,
               u.id AS borrower_id, u.barcode AS id_barcode,
               CONCAT(u.firstname, ' ', u.lastname) AS full_name
        FROM borrowing br
        JOIN books b ON b.id = br.book_id
        JOIN users u ON u.id = br.user_id
        WHERE br.transaction_code = ? LIMIT 1
    ");
    $stmt->execute([$code]);
    $txn = $stmt->fetch();
}

if ($txn && !is_staff() && (int) $txn['borrower_id'] !== (int) $_SESSION['user_id']) {
    $txn = null;
}

$back = is_staff() ? 'adboard.php' : 'studhome.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Borrowing Receipt | Scan2Borrow</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
<style>
    body { background: var(--bg); padding: 30px 16px; }
    .receipt { max-width: 480px; margin: auto; }
    @media print { .no-print { display: none; } body { background: #fff; } }
</style>
</head>
<body>
<div class="receipt">
    <div class="card p-4">
        <div class="text-center mb-3">
            <div style="font-size:42px;">&#129534;</div>
            <h3 class="mb-0">Borrowing Receipt</h3>
            <div class="text-muted">Scan2Borrow Library</div>
        </div>

        <?php if (!$txn): ?>
            <div class="alert alert-danger">Receipt not found. Invalid transaction code.</div>
        <?php else:
            $validFrom = date('Y-m-d', strtotime($txn['borrow_date']));
            $validTo = $txn['return_date'] ? date('Y-m-d', strtotime($txn['return_date'])) : $txn['due_date'];
            $validDays = max(0, (int) round((strtotime($validTo) - strtotime($validFrom)) / 86400));
        ?>
            <div class="alert alert-<?= $txn['status'] === 'Returned' ? 'success' : ($txn['status'] === 'Overdue' ? 'danger' : 'info') ?> text-center">
                Status: <strong><?= e($txn['status']) ?></strong>
            </div>

            <table class="table table-sm">
                <tr><th>Transaction</th><td><code><?= e($txn['transaction_code']) ?></code></td></tr>
                <tr><th>Borrower</th><td><?= e($txn['full_name']) ?> (<?= e($txn['id_barcode']) ?>)</td></tr>
                <tr><th>Book</th><td><?= e($txn['title']) ?><br><span class="text-muted small">by <?= e($txn['author']) ?></span></td></tr>
                <tr><th>Accession Number</th><td><?= e($txn['accession_no'] ?: $txn['book_barcode']) ?></td></tr>
                <tr><th>ISBN</th><td><?= e($txn['isbn'] ?: '—') ?></td></tr>
                <tr><th>Location</th><td>Floor <?= e($txn['floor_no']) ?> &middot; <?= e($txn['section_name']) ?> &middot; Shelf <?= e($txn['shelf_no']) ?> &middot; Row <?= e($txn['row_no']) ?></td></tr>
                <tr><th>Borrowed</th><td><?= e(date('M d, Y g:i A', strtotime($txn['borrow_date']))) ?></td></tr>
                <tr><th>Due Date</th><td><?= e(date('M d, Y', strtotime($txn['due_date']))) ?></td></tr>
                <tr><th>Validity of the Book</th><td><?= e(date('M d, Y', strtotime($validFrom))) ?> — <?= e(date('M d, Y', strtotime($validTo))) ?> (<?= $validDays ?> day<?= $validDays == 1 ? '' : 's' ?>)</td></tr>
                <?php if ($txn['return_date']): ?>
                    <tr><th>Returned</th><td><?= e(date('M d, Y g:i A', strtotime($txn['return_date']))) ?></td></tr>
                <?php endif; ?>
                <?php if ((float) $txn['fine_amount'] > 0): ?>
                    <tr><th>Fine</th><td class="text-danger"><?= peso((float) $txn['fine_amount']) ?></td></tr>
                <?php endif; ?>
            </table>
        <?php endif; ?>

        <div class="no-print d-flex gap-2 mt-2">
            <a href="<?= e($back) ?>" class="btn btn-outline-secondary w-100">Back</a>
            <?php if ($txn): ?>
                <button onclick="window.print()" class="btn btn-primary w-100">Print</button>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
