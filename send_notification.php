<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';
require_staff();

refresh_overdue_status();

$id = (int) ($_GET['id'] ?? 0);

$ustmt = db()->prepare("SELECT * FROM users WHERE id = ? AND role IN ('student','teacher') LIMIT 1");
$ustmt->execute([$id]);
$student = $ustmt->fetch();

if (!$student) {
    die('Borrower not found.');
}

$studentName  = full_name($student);
$studentEmail = $student['email'];

$lstmt = db()->prepare("
    SELECT b.title, br.borrow_date, br.due_date, br.return_date, br.status, br.fine_amount
    FROM borrowing br
    JOIN books b ON b.id = br.book_id
    WHERE br.user_id = ? AND br.return_date IS NULL
    ORDER BY br.due_date ASC
");
$lstmt->execute([$id]);
$loans = $lstmt->fetchAll();

$result = null;
$smsResult = null;

// Calculate total fine (used by both email and SMS)
$totalFine = 0.0;
foreach ($loans as $loan) {
    $totalFine += (float) $loan['fine_amount'];
}

if (isset($_POST['send'])) {
    csrf_check();

    if (!$studentEmail) {
        $result = ['ok' => false, 'error' => 'This borrower has no email address on file.'];
    } else {
        $rowsHtml = '';
        foreach ($loans as $loan) {
            $late = days_overdue($loan['due_date'], $loan['return_date']);
            $statusColor = $loan['status'] === 'Overdue' ? '#EF4444' : '#1E3A5F';
            $rowsHtml .= "<tr>
                <td style='border:1px solid #ddd;padding:8px;'>" . e($loan['title']) . "</td>
                <td style='border:1px solid #ddd;padding:8px;'>" . date('M d, Y', strtotime($loan['borrow_date'])) . "</td>
                <td style='border:1px solid #ddd;padding:8px;'>" . date('M d, Y', strtotime($loan['due_date'])) . "</td>
                <td style='border:1px solid #ddd;padding:8px;color:$statusColor;'>" . e($loan['status']) .
                    ($late > 0 ? " ($late d late)" : '') . "</td>
            </tr>";
        }
        if ($rowsHtml === '') {
            $rowsHtml = "<tr><td colspan='4' style='border:1px solid #ddd;padding:8px;text-align:center;'>No active borrowed books.</td></tr>";
        }

        $inner = "
            <p>Dear <b>" . e($studentName) . "</b>,</p>
            <p>This is an official notification from the Scan2Borrow Library regarding your borrowed books.</p>
            <table style='border-collapse:collapse;width:100%;margin-top:10px;'>
                <tr style='background:#f4f4f4;'>
                    <th style='border:1px solid #ddd;padding:8px;text-align:left;'>Book Title</th>
                    <th style='border:1px solid #ddd;padding:8px;text-align:left;'>Borrow Date</th>
                    <th style='border:1px solid #ddd;padding:8px;text-align:left;'>Due Date</th>
                    <th style='border:1px solid #ddd;padding:8px;text-align:left;'>Status</th>
                </tr>
                $rowsHtml
            </table>" .
            ($totalFine > 0 ? "<p style='margin-top:14px;color:#EF4444;'><b>Outstanding fines: " . strip_tags(peso($totalFine)) . "</b></p>" : '') .
            "<p style='margin-top:14px;'>Kindly return all borrowed books on or before their due dates to avoid additional fines.</p>
             <p>Regards,<br><b>Library Management Office</b></p>";

        $result = send_mail(
            $studentEmail,
            $studentName,
            'Scan2Borrow - Your Borrowed Book Record',
            mail_template('Borrowed Books Notification', $inner)
        );
    }
}

if (isset($_POST['send_sms'])) {
    csrf_check();

    if (empty($student['contact_no'])) {
        $smsResult = ['ok' => false, 'error' => 'This borrower has no contact number on file.'];
    } elseif (!SMS_ENABLED) {
        $smsResult = ['ok' => false, 'error' => 'SMS is not enabled. Please configure SMS settings in .env file.'];
    } else {
        // Build SMS message
        $smsMessage = "Scan2Borrow Library\n\n";
        $smsMessage .= "Dear {$studentName},\n\n";
        $smsMessage .= "This is a reminder of your borrowed books:\n\n";

        if (empty($loans)) {
            $smsMessage .= "No active borrowed books.\n";
        } else {
            foreach ($loans as $idx => $loan) {
                $smsMessage .= ($idx + 1) . ". " . $loan['title'] . "\n";
                $smsMessage .= "   Due: " . date('M d, Y', strtotime($loan['due_date'])) . "\n";
                if ($loan['status'] === 'Overdue') {
                    $smsMessage .= "   Status: OVERDUE\n";
                }
            }
        }

        if ($totalFine > 0) {
            $smsMessage .= "\nOutstanding fines: " . strip_tags(peso($totalFine)) . "\n";
        }

        $smsMessage .= "\nPlease return books on time to avoid penalties.\n";
        $smsMessage .= "Thank you!";

        // Send SMS
        $sent = send_sms_notification($student['contact_no'], $smsMessage);

        if ($sent) {
            // Log SMS
            $pdo = db();
            log_sms($pdo, (int) $student['id'], null, 'borrow_notification', $student['contact_no'], $smsMessage, 'sent');
            $smsResult = ['ok' => true, 'message' => 'SMS sent successfully to ' . $student['contact_no']];
        } else {
            // Log failed SMS
            $pdo = db();
            log_sms($pdo, (int) $student['id'], null, 'borrow_notification', $student['contact_no'], $smsMessage, 'failed');
            $smsResult = ['ok' => false, 'error' => 'Failed to send SMS. Please try again.'];
        }
    }
}

$pageTitle = 'Send Notification';
require __DIR__ . '/includes/header.php';
?>

<a href="adstud.php" class="btn btn-outline-secondary btn-sm mb-3">&larr; Back to Borrowers</a>

<div class="table-card" style="max-width:720px;">
    <h5>Email Notification</h5>

    <?php if ($result !== null): ?>
        <?php if ($result['ok']): ?>
            <div class="alert alert-success">Notification sent to <?= e($studentEmail) ?>.</div>
        <?php else: ?>
            <div class="alert alert-danger">Could not send email: <?= e($result['error']) ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <p class="mb-1"><strong>Borrower:</strong> <?= e($studentName) ?> (<?= e($student['barcode']) ?>)</p>
    <p class="mb-1"><strong>Email:</strong> <?= $studentEmail ? e($studentEmail) : '<span class="text-danger">No email on file</span>' ?></p>
    <p class="mb-1"><strong>Contact Number:</strong> <?= $student['contact_no'] ? e($student['contact_no']) : '<span class="text-danger">No contact number on file</span>' ?></p>
    <p class="mb-3"><strong>Active loans:</strong> <?= count($loans) ?></p>

    <?php if (!$studentEmail): ?>
        <div class="alert alert-warning">Add an email address for this borrower before sending a notification.</div>
    <?php endif; ?>

    <?php if (MAIL_USERNAME === ''): ?>
        <div class="alert alert-info">
            SMTP is not configured yet. Set <code>MAIL_USERNAME</code> / <code>MAIL_PASSWORD</code> in your <code>.env</code> file to enable real email sending.
        </div>
    <?php endif; ?>

    <form method="POST" class="d-inline">
        <?= csrf_field() ?>
        <button type="submit" name="send" class="btn btn-accent" <?= $studentEmail ? '' : 'disabled' ?>>
            📧 Send Email Notification
        </button>
    </form>

    <hr class="my-4">

    <h5>SMS Notification</h5>

    <?php if ($smsResult !== null): ?>
        <?php if ($smsResult['ok']): ?>
            <div class="alert alert-success"><?= e($smsResult['message']) ?></div>
        <?php else: ?>
            <div class="alert alert-danger">Could not send SMS: <?= e($smsResult['error']) ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (empty($student['contact_no'])): ?>
        <div class="alert alert-warning">Add a contact number for this borrower before sending SMS.</div>
    <?php endif; ?>

    <?php if (!SMS_ENABLED): ?>
        <div class="alert alert-info">
            SMS is not enabled. Set <code>SMS_ENABLED=true</code> and configure <code>SMS_API_KEY</code> / <code>SMS_DEVICE_ID</code> in your <code>.env</code> file.
        </div>
    <?php endif; ?>

    <form method="POST" class="d-inline">
        <?= csrf_field() ?>
        <button type="submit" name="send_sms" class="btn btn-success" 
                <?= ($student['contact_no'] && SMS_ENABLED) ? '' : 'disabled' ?>>
            📱 Send SMS Notification
        </button>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
