<?php
require_once __DIR__ . '/includes/visitor_portal.php';
$pdo = db(); $visitor = current_visitor($pdo);
$borrowId = (int) ($_GET['id'] ?? 0);
$loan = null;
if ($borrowId > 0) {
    $s = $pdo->prepare('SELECT vb.*, b.title, b.author, b.isbn, b.accession_no, b.barcode, b.floor_no, b.section_name, b.shelf_no, b.row_no FROM visitor_borrowing vb JOIN books b ON b.id = vb.book_id WHERE vb.id = ? AND vb.visitor_id = ?');
    $s->execute([$borrowId, $visitor['id']]);
    $loan = $s->fetch() ?: null;
}
visitor_portal_header('Borrowing Receipt', $visitor);
?>
<style>
    .receipt { max-width: 500px; margin: 0 auto; }
    @media print { .sidebar, .topbar, .no-print { display: none !important; }
        .main { margin-left: 0 !important; }
        body { background: #fff !important; }
    }
</style>
<div class="receipt">
    <div class="card p-4">
        <div class="text-center mb-3">
            <div style="font-size:42px;">&#129534;</div>
            <h3 class="mb-0">Borrowing Receipt</h3>
            <div class="text-muted">Scan2Borrow Library</div>
        </div>

        <?php if (!$loan): ?>
            <div class="alert alert-danger">Receipt not found.</div>
        <?php else:
            $validFrom = $loan['borrow_date'];
            $validTo = $loan['return_date'] ?: $loan['due_date'];
            $validDays = max(0, (int) round((strtotime($validTo) - strtotime($validFrom)) / 86400));
        ?>
            <div class="alert alert-<?= $loan['request_status'] === 'Returned' ? 'success' : 'info' ?> text-center">
                Status: <strong><?= e($loan['request_status']) ?></strong>
            </div>

            <table class="table table-sm">
                <tr><th>Borrower</th><td><?= e(full_name($visitor)) ?> (<?= e($visitor['visitor_number'] ?: '—') ?>)</td></tr>
                <tr><th>Book</th><td><?= e($loan['title']) ?><br><span class="text-muted small">by <?= e($loan['author'] ?: 'Unknown') ?></span></td></tr>
                <tr><th>Accession Number</th><td><?= e($loan['accession_no'] ?: $loan['barcode']) ?></td></tr>
                <tr><th>ISBN</th><td><?= e($loan['isbn'] ?: '—') ?></td></tr>
                <tr><th>Location</th><td>Floor <?= e($loan['floor_no']) ?> &middot; <?= e($loan['section_name']) ?> &middot; Shelf <?= e($loan['shelf_no']) ?> &middot; Row <?= e($loan['row_no']) ?></td></tr>
                <tr><th>Borrowed</th><td><?= e(date('M d, Y', strtotime($loan['borrow_date']))) ?></td></tr>
                <tr><th>Due Date</th><td><?= e(date('M d, Y', strtotime($loan['due_date']))) ?></td></tr>
                <tr><th>Validity of the Book</th><td><?= e(date('M d, Y', strtotime($validFrom))) ?> — <?= e(date('M d, Y', strtotime($validTo))) ?> (<?= $validDays ?> day<?= $validDays == 1 ? '' : 's' ?>)</td></tr>
                <?php if ($loan['return_date']): ?>
                    <tr><th>Returned</th><td><?= e(date('M d, Y', strtotime($loan['return_date']))) ?></td></tr>
                <?php endif; ?>
            </table>
        <?php endif; ?>

        <div class="no-print d-flex gap-2 mt-2">
            <a href="guest_borrowing_history.php" class="btn btn-outline-secondary w-100">Back</a>
            <?php if ($loan): ?>
                <button onclick="window.print()" class="btn btn-success w-100">Print</button>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php visitor_portal_footer(); ?>
