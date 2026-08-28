<?php
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/html; charset=utf-8');

function row(string $label, bool $ok, string $detail = ''): void
{
    $color = $ok ? '#16a34a' : '#dc2626';
    $mark  = $ok ? 'PASS' : 'FAIL';
    echo '<tr><td style="padding:6px 12px;border-bottom:1px solid #eee;">' . htmlspecialchars($label) . '</td>';
    echo '<td style="padding:6px 12px;border-bottom:1px solid #eee;color:' . $color . ';font-weight:700;">' . $mark . '</td>';
    echo '<td style="padding:6px 12px;border-bottom:1px solid #eee;color:#555;">' . htmlspecialchars($detail) . '</td></tr>';
}

echo '<html><body style="font-family:system-ui,Arial,sans-serif;max-width:760px;margin:30px auto;">';
echo '<h2>Scan2Borrow &mdash; Photo Diagnostic</h2>';
echo '<table style="border-collapse:collapse;width:100%;background:#fff;border:1px solid #eee;">';

row('PHP gd / fileinfo available', function_exists('finfo_open'),
    'fileinfo ' . (function_exists('finfo_open') ? 'loaded' : 'MISSING - enable extension=fileinfo in php.ini'));

$dbOk = false; $dbDetail = '';
try {
    db()->query('SELECT 1');
    $dbOk = true; $dbDetail = 'Connected to ' . DB_NAME . ' @ ' . DB_HOST . ':' . DB_PORT;
} catch (Throwable $e) {
    $dbDetail = $e->getMessage();
}
row('Database connection', $dbOk, $dbDetail);

$colOk = false; $colDetail = 'column not found - run upgrade.sql';
if ($dbOk) {
    try {
        $col = db()->query("SHOW COLUMNS FROM users LIKE 'photo'")->fetch();
        if ($col) {
            $type   = strtolower((string) $col['Type']);
            $colOk  = (strpos($type, 'text') !== false || strpos($type, 'blob') !== false);
            $colDetail = 'type = ' . $col['Type'] . ($colOk ? '' : ' (too small - run the MODIFY in upgrade.sql to MEDIUMTEXT)');
        }
    } catch (Throwable $e) {
        $colDetail = $e->getMessage();
    }
}
row('users.photo column present & MEDIUMTEXT', $colOk, $colDetail);

$writeOk = false; $writeDetail = '';
if ($colOk) {
    $tinyPng = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    $val = save_captured_photo($tinyPng);
    if ($val !== null) {
        try {
            db()->exec("CREATE TABLE IF NOT EXISTS _photo_selftest (id INT PRIMARY KEY, p MEDIUMTEXT)");
            db()->prepare("REPLACE INTO _photo_selftest (id, p) VALUES (1, ?)")->execute([$val]);
            $back = db()->query("SELECT p FROM _photo_selftest WHERE id=1")->fetchColumn();
            $writeOk = ($back === $val);
            $writeDetail = $writeOk ? 'Stored & read back ' . strlen($val) . ' chars OK' : 'Read-back mismatch';
            db()->exec("DROP TABLE _photo_selftest");
        } catch (Throwable $e) {
            $writeDetail = $e->getMessage();
        }
    } else {
        $writeDetail = 'save_captured_photo() rejected a valid test image (old functions.php?)';
    }
}
row('Store + read photo in DB', $writeOk, $writeDetail);

if ($dbOk && $colOk) {
    echo '</table><h3 style="margin-top:24px;">Borrowers and their photo status</h3>';
    echo '<table style="border-collapse:collapse;width:100%;background:#fff;border:1px solid #eee;">';
    echo '<tr style="background:#f8fafc;"><th style="text-align:left;padding:6px 12px;">ID</th><th style="text-align:left;padding:6px 12px;">Barcode</th><th style="text-align:left;padding:6px 12px;">Name</th><th style="text-align:left;padding:6px 12px;">Photo?</th></tr>';
    $rows = db()->query("SELECT id, barcode, firstname, lastname, photo FROM users ORDER BY id")->fetchAll();
    foreach ($rows as $r) {
        $has = !empty($r['photo']);
        echo '<tr><td style="padding:6px 12px;border-bottom:1px solid #eee;">' . (int) $r['id'] . '</td>';
        echo '<td style="padding:6px 12px;border-bottom:1px solid #eee;">' . htmlspecialchars($r['barcode']) . '</td>';
        echo '<td style="padding:6px 12px;border-bottom:1px solid #eee;">' . htmlspecialchars($r['firstname'] . ' ' . $r['lastname']) . '</td>';
        echo '<td style="padding:6px 12px;border-bottom:1px solid #eee;color:' . ($has ? '#16a34a' : '#dc2626') . ';font-weight:700;">' . ($has ? 'YES (' . strlen($r['photo']) . ' chars)' : 'no') . '</td></tr>';
    }
}
echo '</table>';

echo '<p style="margin-top:24px;color:#555;">If every check is PASS but a borrower still shows initials, that borrower simply has no photo yet &mdash; re-register them with a captured photo, or upload one from the admin Borrower page. Delete this file when done.</p>';
echo '</body></html>';
