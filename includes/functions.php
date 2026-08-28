<?php
require_once __DIR__ . '/../config/db.php';
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header("Location: $path");
    exit();
}

function full_name(array $user): string
{
    $parts = array_filter([
        $user['firstname'] ?? '',
        $user['middlename'] ?? '',
        $user['lastname'] ?? '',
    ]);
    return trim(implode(' ', $parts));
}

function generate_transaction_code(): string
{
    return 'S2B-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function ensure_book_accession_column(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    try {
        $pdo->query('SELECT accession_no FROM books LIMIT 1');
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            $pdo->exec("ALTER TABLE books ADD COLUMN accession_no VARCHAR(50) DEFAULT NULL AFTER barcode, ADD UNIQUE KEY uq_books_accession (accession_no)");
        } else {
            throw $e;
        }
    }
    $checked = true;
}

function save_captured_photo(string $dataUrl, string $barcode = ''): ?string
{
    if ($dataUrl === '' || !preg_match('#^data:image/(png|jpe?g|webp);base64,#i', $dataUrl, $m)) {
        return null;
    }

    $base64 = substr($dataUrl, strpos($dataUrl, ',') + 1);
    $binary = base64_decode($base64, true);
    if ($binary === false || $binary === '' || strlen($binary) > 4 * 1024 * 1024) {
        return null;
    }

    $mime = strtolower($m[1]) === 'jpg' ? 'jpeg' : strtolower($m[1]);
    return 'data:image/' . $mime . ';base64,' . base64_encode($binary);
}

function save_uploaded_photo(array $file, string $barcode = ''): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
        return null;
    }
    if (($file['size'] ?? 0) <= 0 || $file['size'] > 4 * 1024 * 1024) {
        return null;
    }

    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $mime  = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowed, true)) {
        $name = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $mime = match ($name) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => null,
        };
    }

    if (!in_array($mime, $allowed, true)) {
        return null;
    }

    $targetDir = __DIR__ . '/../uploads/photos';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
        return null;
    }

    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        default => 'bin',
    };

    $safeBarcode = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim((string) $barcode));
    $prefix = $safeBarcode !== '' ? $safeBarcode : 'book';
    $filename = $prefix . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = $targetDir . '/' . $filename;

    $saved = is_uploaded_file($file['tmp_name']) ? move_uploaded_file($file['tmp_name'], $targetPath) : copy($file['tmp_name'], $targetPath);
    if ($saved === false) {
        return null;
    }

    return 'uploads/photos/' . $filename;
}

function compute_due_date(?string $borrowDate = null): string
{
    $base = $borrowDate ? strtotime($borrowDate) : time();
    return date('Y-m-d', strtotime('+' . LOAN_DAYS . ' days', $base));
}

function days_overdue(?string $dueDate, ?string $returnDate = null): int
{
    if (!empty($returnDate) || empty($dueDate)) {
        return 0;
    }
    $due   = strtotime($dueDate . ' 23:59:59');
    $delta = time() - $due;
    return $delta > 0 ? (int) floor($delta / 86400) : 0;
}

function compute_fine(int $overdueDays): float
{
    return round($overdueDays * FINE_PER_DAY, 2);
}

function peso(float $amount): string
{
    return '&#8369;' . number_format($amount, 2);
}

function status_badge(string $status): string
{
    $map = [
        'Available' => 'success',
        'Borrowed'  => 'danger',
        'Reserved'  => 'warning text-dark',
        'Returned'  => 'success',
        'Overdue'   => 'danger',
        'Pending'   => 'warning text-dark',
        'active'    => 'success',
        'inactive'  => 'secondary',
    ];
    $class = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $class . '">' . e(ucfirst($status)) . '</span>';
}

function refresh_overdue_status(): int
{
    $pdo = db();
    $rows = $pdo->query("
        SELECT id, due_date, return_date
        FROM borrowing
        WHERE return_date IS NULL
          AND status IN ('Borrowed', 'Overdue')
    ")->fetchAll();

    $update = $pdo->prepare("
        UPDATE borrowing SET status = 'Overdue', fine_amount = :fine WHERE id = :id
    ");

    $touched = 0;
    foreach ($rows as $row) {
        $late = days_overdue($row['due_date'], $row['return_date']);
        if ($late > 0) {
            $update->execute([':fine' => compute_fine($late), ':id' => $row['id']]);
            $touched++;
        }
    }
    return $touched;
}

// ============================================================================
// Security Hardening: login attempt tracking, audit logging, session security
// ============================================================================

function record_login_attempt(PDO $pdo, ?int $userId, string $barcode, bool $success): void
{
    if ($success && $userId > 0) {
        $pdo->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?')
            ->execute([$userId]);
    } elseif (!$success) {
        if ($userId) {
            $pdo->prepare('UPDATE users SET failed_attempts = failed_attempts + 1 WHERE id = ?')
                ->execute([$userId]);
        } else {
            $pdo->prepare('UPDATE users SET failed_attempts = failed_attempts + 1 WHERE barcode = ?')
                ->execute([$barcode]);
        }
    }
}

function is_account_locked(PDO $pdo, string $barcode): bool
{
    $stmt = $pdo->prepare('SELECT locked_until, failed_attempts FROM users WHERE barcode = ? LIMIT 1');
    $stmt->execute([$barcode]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }
    if (!empty($row['locked_until']) && strtotime($row['locked_until']) > time()) {
        return true;
    }
    return false;
}

function lock_account(PDO $pdo, string $barcode, int $minutes = 15): void
{
    $until = date('Y-m-d H:i:s', time() + $minutes * 60);
    $pdo->prepare('UPDATE users SET locked_until = ? WHERE barcode = ?')
        ->execute([$until, $barcode]);
}

function audit_log(PDO $pdo, ?int $userId, string $action, ?string $details = null): void
{
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $pdo->prepare('INSERT INTO audit_log (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)')
            ->execute([$userId, $action, $details, $ip, $ua]);
    } catch (PDOException $e) {
        // Ignore logging failures so book saves are not blocked by missing audit table.
    }
}

function secure_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

// ============================================================================
// Smart Book Recommendations: keyword + search history helpers
// ============================================================================

function ensure_keyword_tables(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    try {
        $pdo->query('SELECT 1 FROM keywords LIMIT 1');
        $pdo->query('SELECT 1 FROM book_keywords LIMIT 1');
    } catch (PDOException $e) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS keywords (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS book_keywords (book_id INT NOT NULL, keyword_id INT NOT NULL, PRIMARY KEY (book_id, keyword_id))');
    }
    $checked = true;
}

function get_or_create_keyword(PDO $pdo, string $name): int
{
    ensure_keyword_tables($pdo);
    $name = strtolower(trim($name));
    if ($name === '') {
        return 0;
    }
    $stmt = $pdo->prepare('SELECT id FROM keywords WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $id = (int) $stmt->fetchColumn();
    if ($id > 0) {
        return $id;
    }
    $pdo->prepare('INSERT INTO keywords (name) VALUES (?)')->execute([$name]);
    return (int) $pdo->lastInsertId();
}

function set_book_keywords(PDO $pdo, int $bookId, array $keywords): void
{
    ensure_keyword_tables($pdo);
    $pdo->prepare('DELETE FROM book_keywords WHERE book_id = ?')->execute([$bookId]);
    foreach ($keywords as $kw) {
        $kw = trim($kw);
        if ($kw === '') {
            continue;
        }
        $kid = get_or_create_keyword($pdo, $kw);
        if ($kid <= 0) {
            continue;
        }
        $pdo->prepare('INSERT IGNORE INTO book_keywords (book_id, keyword_id) VALUES (?, ?)')
            ->execute([$bookId, $kid]);
    }
}

function log_search(PDO $pdo, int $userId, string $query): void
{
    $query = trim($query);
    if ($query === '' || $userId <= 0) {
        return;
    }
    $pdo->prepare('INSERT INTO search_history (user_id, search_query) VALUES (?, ?)')
        ->execute([$userId, $query]);
}

function log_book_view(PDO $pdo, int $userId, int $bookId): void
{
    if ($userId <= 0 || $bookId <= 0) {
        return;
    }
    $pdo->prepare('INSERT INTO book_views (user_id, book_id) VALUES (?, ?)')
        ->execute([$userId, $bookId]);
}

function get_user_search_keywords(PDO $pdo, int $userId, int $limit = 10): array
{
    $stmt = $pdo->prepare("
        SELECT search_query, COUNT(*) AS cnt
        FROM search_history
        WHERE user_id = ?
        GROUP BY search_query
        ORDER BY MAX(created_at) DESC, cnt DESC
        LIMIT $limit
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}


// ============================================================================
// Approval Request Notification System
// ============================================================================

function send_borrow_request_notification(PDO $pdo, int $userId, string $bookTitle, string $txnCode): void
{
    // Get student info
    $stmt = $pdo->prepare('SELECT firstname, lastname, barcode FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $student = $stmt->fetch();
    
    if (!$student) {
        return;
    }
    
    $studentName = full_name($student);
    
    // Get all staff members (admin and librarian) with their contact info
    $staff = $pdo->query("SELECT id, email, contact_no FROM users WHERE role IN ('admin', 'librarian') AND status = 'active'")->fetchAll();
    
    if (!$staff) {
        return;
    }
    
    $title = 'New Borrow Request';
    $message = "Student <strong>{$studentName}</strong> (ID: {$student['barcode']}) has requested to borrow:<br><br>";
    $message .= "<strong>Book:</strong> {$bookTitle}<br>";
    $message .= "<strong>Transaction Code:</strong> {$txnCode}<br><br>";
    $message .= "Please review and approve/reject this request in the staff dashboard.";
    
    // Insert notifications for all staff
    $notifStmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, related_id) VALUES (?, "borrow_request", ?, ?, ?)');
    
    // Get the borrowing ID for the related_id field
    $borrowStmt = $pdo->prepare('SELECT id FROM borrowing WHERE transaction_code = ? AND user_id = ? ORDER BY id DESC LIMIT 1');
    $borrowStmt->execute([$txnCode, $userId]);
    $borrowingId = (int) $borrowStmt->fetchColumn();
    
    foreach ($staff as $staffMember) {
        $notifStmt->execute([$staffMember['id'], $title, $message, $borrowingId]);
        
        // Send email notification if configured
        if (MAIL_USERNAME !== '' && MAIL_PASSWORD !== '' && !empty($staffMember['email'])) {
            $emailSubject = "New Borrow Request - {$bookTitle}";
            $emailBody = "Dear Librarian/Admin,\n\n";
            $emailBody .= "A new borrow request has been submitted:\n\n";
            $emailBody .= "Student: {$studentName} (ID: {$student['barcode']})\n";
            $emailBody .= "Book: {$bookTitle}\n";
            $emailBody .= "Transaction Code: {$txnCode}\n\n";
            $emailBody .= "Please log in to the staff dashboard to review and approve this request.\n\n";
            $emailBody .= "Best regards,\nScan2Borrow Library System";
            
            send_email_notification($staffMember['email'], $emailSubject, nl2br($emailBody));
        }
        
        // Send SMS notification if configured
        if (SMS_ENABLED && !empty($staffMember['contact_no'])) {
            $smsMessage = "Scan2Borrow: New borrow request from {$studentName} ({$student['barcode']}) for '{$bookTitle}'. Transaction: {$txnCode}. Please review in staff dashboard.";
            send_sms_notification($staffMember['contact_no'], $smsMessage);
        }
    }
}

function send_email_notification(string $to, string $subject, string $body): bool
{
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return false;
    }
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function send_sms_notification(string $phoneNumber, string $message): bool
{
    // Check if SMS is enabled and credentials are set
    if (!SMS_ENABLED) {
        error_log("SMS not sent: SMS_ENABLED is false");
        return false;
    }
    
    if (SMS_API_KEY === '') {
        error_log("SMS not sent: SMS_API_KEY is empty");
        return false;
    }
    
    if (SMS_DEVICE_ID === '') {
        error_log("SMS not sent: SMS_DEVICE_ID is empty");
        return false;
    }
    
    // Format phone number (remove non-numeric characters)
    $phone = preg_replace('/[^0-9]/', '', $phoneNumber);
    
    if (empty($phone)) {
        error_log("SMS not sent: Invalid phone number format - $phoneNumber");
        return false;
    }
    
    // Add Philippines country code if number starts with 0
    if (strpos($phone, '0') === 0) {
        $phone = '63' . substr($phone, 1);
    }
    
        // TextBee API endpoint
        $url = 'https://api.textbee.dev/api/v1/gateway/devices/6a3e5c4877015dcde17076d0/send-sms';
        
        $data = [
         "recipients" => ["+" . $phone],
         "message" => $message
    ];
        
        $headers = [
    "Content-Type: application/json",
    "x-api-key: " . "559d8a3f-0139-49e2-b8db-6ed034323c53"
];
        
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Log the response for debugging
        error_log("SMS API Response (HTTP $httpCode): $response");
        
        if ($error) {
            error_log("SMS cURL Error: $error");
            return false;
        }
        
        if ($httpCode !== 200 && $httpCode !== 201) {
            error_log("SMS not sent: HTTP error code $httpCode");
            return false;
        }
        
        // Check if response is successful
        $result = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("SMS not sent: Invalid JSON response - " . json_last_error_msg());
            return false;
        }

        // TextBee returns HTTP 201 and data.success = true when the SMS is queued successfully
        if (
            ($httpCode == 200 || $httpCode == 201) &&
            isset($result['data']['success']) &&
            $result['data']['success'] === true
        ) {
            return true;
        }

        return false;
    }
// ============================================================================
// SMS Notification Functions
// ============================================================================

function has_sms_been_sent(PDO $pdo, int $borrowingId, string $type): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sms_logs WHERE borrowing_id = ? AND type = ? AND status = "sent"');
    $stmt->execute([$borrowingId, $type]);
    return (int) $stmt->fetchColumn() > 0;
}

function log_sms(PDO $pdo, int $userId, ?int $borrowingId, string $type, string $phoneNumber, string $message, string $status = 'pending'): int
{
    $stmt = $pdo->prepare('
        INSERT INTO sms_logs (user_id, borrowing_id, type, phone_number, message, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$userId, $borrowingId, $type, $phoneNumber, $message, $status]);
    return (int) $pdo->lastInsertId();
}

function update_sms_status(PDO $pdo, int $smsLogId, string $status): void
{
    $sentAt = $status === 'sent' ? 'NOW()' : 'NULL';
    $pdo->prepare("UPDATE sms_logs SET status = ?, sent_at = $sentAt WHERE id = ?")
        ->execute([$status, $smsLogId]);
}

function send_borrow_sms_notification(PDO $pdo, int $userId, int $bookId, int $borrowingId, string $txnCode, string $dueDate): void
{
    // Check if SMS already sent for this borrowing
    if (has_sms_been_sent($pdo, $borrowingId, 'borrow_confirmation')) {
        return;
    }

    // Get student info
    $stmt = $pdo->prepare('SELECT firstname, lastname, contact_no FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $student = $stmt->fetch();

    if (!$student || empty($student['contact_no'])) {
        return;
    }

    // Get book info
    $stmt = $pdo->prepare('SELECT title FROM books WHERE id = ? LIMIT 1');
    $stmt->execute([$bookId]);
    $book = $stmt->fetch();

    if (!$book) {
        return;
    }

    $studentName = full_name($student);
    $borrowDate = date('M d, Y');
    $formattedDueDate = date('M d, Y', strtotime($dueDate));

    // Format message
    $message = "Scan2Borrow\n\n";
    $message .= "Hello, {$studentName}.\n\n";
    $message .= "You have successfully borrowed:\n\n";
    $message .= "Book:\n{$book['title']}\n\n";
    $message .= "Borrow Date:\n{$borrowDate}\n\n";
    $message .= "Return Due:\n{$formattedDueDate}\n\n";
    $message .= "Thank you for using our library.";

    // Log SMS attempt
    $smsLogId = log_sms($pdo, $userId, $borrowingId, 'borrow_confirmation', $student['contact_no'], $message);

    // Send SMS
    $sent = send_sms_notification($student['contact_no'], $message);

    // Update status
    update_sms_status($pdo, $smsLogId, $sent ? 'sent' : 'failed');
}

function send_due_date_reminder(PDO $pdo, int $borrowingId, int $userId, int $bookId, string $dueDate): void
{
    // Check if reminder already sent
    if (has_sms_been_sent($pdo, $borrowingId, 'due_date_reminder')) {
        return;
    }

    // Get student info
    $stmt = $pdo->prepare('SELECT firstname, lastname, contact_no FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $student = $stmt->fetch();

    if (!$student || empty($student['contact_no'])) {
        return;
    }

    // Get book info
    $stmt = $pdo->prepare('SELECT title FROM books WHERE id = ? LIMIT 1');
    $stmt->execute([$bookId]);
    $book = $stmt->fetch();

    if (!$book) {
        return;
    }

    $studentName = full_name($student);
    $formattedDueDate = date('M d, Y', strtotime($dueDate));

    // Format message
    $message = "Reminder from Scan2Borrow\n\n";
    $message .= "Hello, {$studentName}.\n\n";
    $message .= "The book \"{$book['title']}\" is due tomorrow ({$formattedDueDate}).\n\n";
    $message .= "Please return it on time to avoid overdue penalties.\n\n";
    $message .= "Thank you.";

    // Log SMS attempt
    $smsLogId = log_sms($pdo, $userId, $borrowingId, 'due_date_reminder', $student['contact_no'], $message);

    // Send SMS
    $sent = send_sms_notification($student['contact_no'], $message);

    // Update status
    update_sms_status($pdo, $smsLogId, $sent ? 'sent' : 'failed');
}

function process_due_date_reminders(): int
{
    $pdo = db();
    $count = 0;

    // Find all borrowings due tomorrow that haven't been reminded yet
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $stmt = $pdo->prepare('
        SELECT br.id, br.user_id, br.book_id, br.due_date
        FROM borrowing br
        LEFT JOIN sms_logs sl ON sl.borrowing_id = br.id AND sl.type = "due_date_reminder" AND sl.status = "sent"
        WHERE br.return_date IS NULL
          AND br.status IN ("Borrowed", "Overdue")
          AND br.due_date = ?
          AND sl.id IS NULL
    ');
    $stmt->execute([$tomorrow]);
    $reminders = $stmt->fetchAll();

    foreach ($reminders as $reminder) {
        send_due_date_reminder($pdo, $reminder['id'], $reminder['user_id'], $reminder['book_id'], $reminder['due_date']);
        $count++;
    }

    return $count;
}

// ============================================================================
// OTP Functions
// ============================================================================

function generate_otp(): string
{
    return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

function save_otp_registration(PDO $pdo, array $userData, string $phoneNumber): string
{
    // Clean up expired OTPs for this barcode
    $pdo->prepare('DELETE FROM otp_codes WHERE barcode = ? AND expires_at < NOW()')
        ->execute([$userData['barcode']]);

    // Generate new OTP
    $otp = generate_otp();
    $expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 minutes

    // Store OTP with user data
    $stmt = $pdo->prepare('
        INSERT INTO otp_codes (barcode, otp_code, phone_number, user_data, expires_at)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $userData['barcode'],
        $otp,
        $phoneNumber,
        json_encode($userData),
        $expiresAt
    ]);

    // Send OTP via SMS
    $message = "Scan2Borrow Registration\n\nYour OTP code is: {$otp}\n\nThis code will expire in 5 minutes.\n\nDo not share this code with anyone.";
    send_sms_notification($phoneNumber, $message);

    return $otp;
}

function verify_otp(PDO $pdo, string $barcode, string $otp): ?array
{
    $barcode = trim($barcode);
    $otp = trim($otp);
    
    $stmt = $pdo->prepare('
        SELECT * FROM otp_codes
        WHERE barcode = ? AND otp_code = ? AND is_used = 0 AND expires_at > NOW()
        ORDER BY id DESC LIMIT 1
    ');
    $stmt->execute([$barcode, $otp]);
    $record = $stmt->fetch();

    if (!$record) {
        return null;
    }

    // Mark as used
    $pdo->prepare('UPDATE otp_codes SET is_used = 1, is_verified = 1 WHERE id = ?')->execute([$record['id']]);

    // Return user data
    return json_decode($record['user_data'], true);
}

function can_resend_otp(PDO $pdo, string $barcode): bool
{
    $stmt = $pdo->prepare('
        SELECT created_at FROM otp_codes
        WHERE barcode = ? AND is_used = 0
        ORDER BY id DESC LIMIT 1
    ');
    $stmt->execute([$barcode]);
    $row = $stmt->fetch();

    if (!$row) {
        return true;
    }

    // Check if 60 seconds have passed
    $lastSent = strtotime($row['created_at']);
    return (time() - $lastSent) >= 60;
}

function resend_otp(PDO $pdo, string $barcode): ?string
{
    if (!can_resend_otp($pdo, $barcode)) {
        return null;
    }

    // Get the latest unused OTP record
    $stmt = $pdo->prepare('
        SELECT * FROM otp_codes
        WHERE barcode = ? AND is_used = 0
        ORDER BY id DESC LIMIT 1
    ');
    $stmt->execute([$barcode]);
    $record = $stmt->fetch();

    if (!$record) {
        return null;
    }

    // Generate new OTP
    $newOtp = generate_otp();
    $expiresAt = date('Y-m-d H:i:s', time() + 300);

    // Update record
    $pdo->prepare('
        UPDATE otp_codes SET otp_code = ?, expires_at = ? WHERE id = ?
    ')->execute([$newOtp, $expiresAt, $record['id']]);

    // Send new OTP
    $message = "Scan2Borrow Registration\n\nYour new OTP code is: {$newOtp}\n\nThis code will expire in 5 minutes.\n\nDo not share this code with anyone.";
    send_sms_notification($record['phone_number'], $message);

    return $newOtp;
}

// ============================================================================
// Return Notification Functions
// ============================================================================

function create_return_notification(PDO $pdo, int $borrowingId, int $userId, int $bookId): void
{
    // Get details
    $stmt = $pdo->prepare('
        SELECT u.firstname, u.lastname, b.title, br.return_date
        FROM borrowing br
        JOIN users u ON u.id = br.user_id
        JOIN books b ON b.id = br.book_id
        WHERE br.id = ? LIMIT 1
    ');
    $stmt->execute([$borrowingId]);
    $data = $stmt->fetch();

    if (!$data) {
        return;
    }

    $studentName = $data['firstname'] . ' ' . $data['lastname'];
    $returnDate = date('M d, Y', strtotime($data['return_date']));

    $message = "Book Successfully Returned\n\n";
    $message .= "Student:\n{$studentName}\n\n";
    $message .= "Book:\n{$data['title']}\n\n";
    $message .= "Returned:\n{$returnDate}";

    // Create notification for all staff
    $staff = $pdo->query("SELECT id FROM users WHERE role IN ('admin', 'librarian') AND status = 'active'")->fetchAll();

    $notifStmt = $pdo->prepare('
        INSERT INTO return_notifications (borrowing_id, user_id, book_id, message)
        VALUES (?, ?, ?, ?)
    ');

    foreach ($staff as $member) {
        $notifStmt->execute([$borrowingId, $member['id'], $bookId, $message]);
    }
}

function create_borrow_notification(PDO $pdo, int $borrowingId, int $userId, int $bookId): void
{
    // Get details
    $stmt = $pdo->prepare('
        SELECT u.firstname, u.lastname, b.title, br.borrow_date, br.due_date
        FROM borrowing br
        JOIN users u ON u.id = br.user_id
        JOIN books b ON b.id = br.book_id
        WHERE br.id = ? LIMIT 1
    ');
    $stmt->execute([$borrowingId]);
    $data = $stmt->fetch();

    if (!$data) {
        return;
    }

    $studentName = $data['firstname'] . ' ' . $data['lastname'];
    $borrowDate = date('M d, Y', strtotime($data['borrow_date']));
    $dueDate = date('M d, Y', strtotime($data['due_date']));

    $title = 'New Book Borrowed';
    $message = "Student <strong>{$studentName}</strong> has borrowed a book:<br><br>";
    $message .= "<strong>Book:</strong> {$data['title']}<br>";
    $message .= "<strong>Borrowed:</strong> {$borrowDate}<br>";
    $message .= "<strong>Due Date:</strong> {$dueDate}<br><br>";
    $message .= "The book has been successfully borrowed and is now marked as 'Borrowed'.";

    // Create notification for all staff
    $staff = $pdo->query("SELECT id FROM users WHERE role IN ('admin', 'librarian') AND status = 'active'")->fetchAll();

    $notifStmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, related_id) VALUES (?, "borrow_notification", ?, ?, ?)');

    foreach ($staff as $member) {
        $notifStmt->execute([$member['id'], $title, $message, $borrowingId]);
    }
}

function get_unviewed_return_notifications(PDO $pdo, int $staffId): array
{
    $stmt = $pdo->prepare('
        SELECT rn.*, u.firstname, u.lastname, b.title, b.barcode as book_barcode
        FROM return_notifications rn
        JOIN users u ON u.id = rn.user_id
        JOIN books b ON b.id = rn.book_id
        WHERE rn.user_id = ? AND rn.is_viewed = 0
        ORDER BY rn.created_at DESC
    ');
    $stmt->execute([$staffId]);
    return $stmt->fetchAll();
}

function get_unviewed_borrow_notifications(PDO $pdo, int $staffId): array
{
    $stmt = $pdo->prepare('
        SELECT n.*, u.firstname, u.lastname
        FROM notifications n
        JOIN users u ON u.id = n.user_id
        WHERE n.user_id = ? AND n.type = "borrow_notification" AND n.is_read = 0
        ORDER BY n.created_at DESC
    ');
    $stmt->execute([$staffId]);
    return $stmt->fetchAll();
}

function mark_return_notification_viewed(PDO $pdo, int $notificationId, int $staffId): void
{
    $pdo->prepare('UPDATE return_notifications SET is_viewed = 1, viewed_at = NOW() WHERE id = ? AND user_id = ?')
        ->execute([$notificationId, $staffId]);
}

function mark_borrow_notification_viewed(PDO $pdo, int $notificationId, int $staffId): void
{
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
        ->execute([$notificationId, $staffId]);
}

function get_pending_approval_count(PDO $pdo): int
{
    return (int) $pdo->query('
        SELECT COUNT(*) FROM borrowing 
        WHERE approval_status = "pending" AND return_date IS NULL
    ')->fetchColumn();
}

function get_recommended_books(PDO $pdo, int $userId, int $limit = 4): array
{
    // 1) From search history: find keywords the user searches for,
    //    then find books that have matching keywords.
    $searchKw = get_user_search_keywords($pdo, $userId, 10);
    $bookIds  = [];

    if ($searchKw) {
        $terms = [];
        $params = [$userId];
        foreach ($searchKw as $q => $cnt) {
            $terms[] = 'k.name LIKE ?';
            $params[] = '%' . $q . '%';
        }
        $in = implode(' OR ', $terms);
        $sql = "
            SELECT DISTINCT bk.book_id
            FROM book_keywords bk
            JOIN keywords k ON k.id = bk.keyword_id
            WHERE bk.book_id NOT IN (
                SELECT book_id FROM borrowing WHERE user_id = ? AND return_date IS NULL
            )
              AND ($in)
            ORDER BY bk.created_at DESC
            LIMIT $limit
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $bookIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // 2) From book views: books similar to ones the user viewed
    if (count($bookIds) < $limit) {
        $need = $limit - count($bookIds);
        $exclude = array_merge($bookIds, [0]);
        $inEx = implode(',', $exclude);
        $sql = "
            SELECT DISTINCT bk2.book_id
            FROM book_views bv
            JOIN book_keywords bk1 ON bk1.book_id = bv.book_id
            JOIN book_keywords bk2 ON bk2.keyword_id = bk1.keyword_id AND bk2.book_id <> bk1.book_id
            WHERE bv.user_id = ?
              AND bk2.book_id NOT IN ($inEx)
              AND bk2.book_id NOT IN (
                  SELECT book_id FROM borrowing WHERE user_id = ? AND return_date IS NULL
              )
            ORDER BY bv.created_at DESC
            LIMIT $need
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $userId]);
        $bookIds = array_merge($bookIds, $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    // 3) Fallback: If no personalized recommendations, get popular/random available books
    if (!$bookIds) {
        $sql = "
            SELECT id FROM books
            WHERE deleted_at IS NULL AND status = 'Available'
            ORDER BY created_at DESC
            LIMIT $limit
        ";
        $stmt = $pdo->query($sql);
        $bookIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    if (!$bookIds) {
        return [];
    }

    $in = implode(',', array_map('intval', $bookIds));
    $stmt = $pdo->prepare("
        SELECT * FROM books
        WHERE id IN ($in) AND deleted_at IS NULL AND status = 'Available'
        ORDER BY FIELD(id, $in)
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

// -------------------------------------------------------------------------
// User photo helpers
// -------------------------------------------------------------------------
function user_photo_src(array $user): string
{
    $photo = trim((string)($user['photo'] ?? ''));
    if ($photo === '') {
        return '';
    }
    // If already a data URL or absolute URL, return as-is
    if (str_starts_with($photo, 'data:') || preg_match('#^https?://#i', $photo)) {
        return $photo;
    }
    // Otherwise treat as site-relative path (uploads/photos/...)
    return $photo;
}

function render_user_avatar(array $user, string $class = 'profile-avatar'): void
{
    $src = user_photo_src($user);
    if ($src !== '') {
        echo '<img src="' . e($src) . '" alt="ID photo" class="' . e($class) . '" style="object-fit:cover;padding:0;">';
    } else {
        $initials = strtoupper(substr($user['firstname'] ?? '', 0, 1) . substr($user['lastname'] ?? '', 0, 1));
        echo '<div class="' . e($class) . '">' . e($initials) . '</div>';
    }
}
