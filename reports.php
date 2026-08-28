<?php
require_once __DIR__ . '/includes/auth.php';
require_staff();

refresh_overdue_status();

$type = $_GET['type'] ?? 'borrowed';
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

$reports = [
    'borrowed'  => 'Borrowed Books',
    'returned'  => 'Returned Books',
    'overdue'   => 'Overdue Books',
    'inventory' => 'Inventory Status',
];
if (!isset($reports[$type])) {
    $type = 'borrowed';
}

function build_report(string $type, string $from, string $to): array
{
    $pdo = db();

    if ($type === 'inventory') {
        $rows = $pdo->query("
            SELECT barcode, title, author, category, status,
                   CONCAT('Floor ', floor_no, ' / ', section_name, ' / Shelf ', shelf_no) AS location
            FROM books WHERE deleted_at IS NULL ORDER BY title ASC
        ")->fetchAll();
        $headers = ['Barcode', 'Title', 'Author', 'Category', 'Status', 'Location'];
        $data = array_map(fn($r) => [
            $r['barcode'], $r['title'], $r['author'], $r['category'], $r['status'], $r['location'],
        ], $rows);
        return [$headers, $data];
    }

    $where = [];
    $params = [];
    if ($type === 'returned') {
        $where[] = "br.return_date IS NOT NULL";
        $dateCol = 'br.return_date';
    } elseif ($type === 'overdue') {
        $where[] = "br.return_date IS NULL AND br.status = 'Overdue'";
        $dateCol = 'br.borrow_date';
    } else { 
        $dateCol = 'br.borrow_date';
    }
    if ($from !== '') { $where[] = "DATE($dateCol) >= :from"; $params[':from'] = $from; }
    if ($to !== '')   { $where[] = "DATE($dateCol) <= :to";   $params[':to']   = $to; }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("
        SELECT br.transaction_code, CONCAT(u.firstname,' ',u.lastname) AS borrower, u.barcode AS id_barcode,
               b.title, br.borrow_date, br.due_date, br.return_date, br.status, br.fine_amount
        FROM borrowing br
        JOIN users u ON u.id = br.user_id
        JOIN books b ON b.id = br.book_id
        $whereSql
        ORDER BY br.id DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $headers = ['Code', 'Borrower', 'ID', 'Book', 'Borrowed', 'Due', 'Returned', 'Status', 'Fine'];
    $data = array_map(fn($r) => [
        $r['transaction_code'], $r['borrower'], $r['id_barcode'], $r['title'],
        date('Y-m-d', strtotime($r['borrow_date'])),
        date('Y-m-d', strtotime($r['due_date'])),
        $r['return_date'] ? date('Y-m-d', strtotime($r['return_date'])) : '',
        $r['status'], number_format((float) $r['fine_amount'], 2),
    ], $rows);
    return [$headers, $data];
}

[$headers, $data] = build_report($type, $from, $to);

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="scan2borrow_' . $type . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($data as $line) {
        fputcsv($out, $line);
    }
    fclose($out);
    exit();
}

$qs = http_build_query(['type' => $type, 'from' => $from, 'to' => $to]);

if (isset($_GET['print'])) {
    $period = ($from !== '' || $to !== '')
        ? (($from !== '' ? $from : '...') . ' to ' . ($to !== '' ? $to : '...'))
        : 'All dates';
    $staff = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? ''));
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <title>Scan2Borrow Report - <?= e($reports[$type]) ?></title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #0f172a; margin: 40px; }
        .rpt-head { border-bottom: 3px solid #6366F1; padding-bottom: 14px; margin-bottom: 20px; }
        .rpt-head h1 { margin: 0; font-size: 22px; }
        .rpt-head .sys { color: #6366F1; font-weight: 800; letter-spacing: 1px; }
        .rpt-meta { display: flex; flex-wrap: wrap; gap: 8px 28px; font-size: 13px; color: #475569; margin-bottom: 18px; }
        .rpt-meta b { color: #0f172a; }
        table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        th { background: #1e2350; color: #fff; text-align: left; padding: 8px 10px; }
        td { padding: 7px 10px; border-bottom: 1px solid #e2e8f0; }
        tr:nth-child(even) td { background: #f8fafc; }
        .rpt-foot { margin-top: 26px; font-size: 11px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 10px; }
        @media print { .noprint { display: none; } body { margin: 12mm; } }
    </style>
    </head>
    <body onload="window.print()">
        <div class="rpt-head">
            <div class="sys">&#128218; SCAN2BORROW</div>
            <h1><?= e($reports[$type]) ?> Report</h1>
        </div>
        <div class="rpt-meta">
            <div><b>Period:</b> <?= e($period) ?></div>
            <div><b>Total Records:</b> <?= count($data) ?></div>
            <div><b>Generated:</b> <?= e(date('M d, Y g:i A')) ?></div>
            <div><b>Generated by:</b> <?= e($staff !== '' ? $staff : 'Staff') ?></div>
        </div>
        <table>
            <thead><tr><?php foreach ($headers as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php if (!$data): ?>
                <tr><td colspan="<?= count($headers) ?>" style="text-align:center;color:#94a3b8;">No records.</td></tr>
            <?php endif; ?>
            <?php foreach ($data as $line): ?>
                <tr><?php foreach ($line as $cell): ?><td><?= e($cell) ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="rpt-foot">Scan2Borrow &mdash; Automated Library Borrowing &amp; Return System</div>
        <div class="noprint" style="text-align:center;margin-top:18px;">
            <button onclick="window.print()" style="padding:8px 18px;">Print</button>
            <button onclick="window.close()" style="padding:8px 18px;">Close</button>
        </div>
    </body>
    </html>
    <?php
    exit();
}

$pageTitle = 'Reports';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div><p>Generate, print, and export library reports.</p></div>
    <div class="d-flex gap-2">
        <a href="reports.php?<?= e($qs) ?>&print=1" target="_blank" class="btn btn-gradient">&#128424; Generate Report</a>
        <a href="reports.php?<?= e($qs) ?>&export=1" class="btn btn-accent">&#11015; Export CSV</a>
    </div>
</div>

<div class="table-card mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Report Type</label>
            <select name="type" class="form-select" onchange="this.form.submit()">
                <?php foreach ($reports as $k => $label): ?>
                    <option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($type !== 'inventory'): ?>
            <div class="col-md-3">
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100">Filter</button>
            </div>
        <?php endif; ?>
    </form>
</div>

<div class="table-card">
    <h5 class="mb-3"><?= e($reports[$type]) ?> <span class="text-muted fs-6">(<?= count($data) ?> records)</span></h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr><?php foreach ($headers as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
            <?php if (!$data): ?>
                <tr><td colspan="<?= count($headers) ?>" class="text-center text-muted">No records.</td></tr>
            <?php endif; ?>
            <?php foreach ($data as $line): ?>
                <tr><?php foreach ($line as $cell): ?><td><?= e($cell) ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
