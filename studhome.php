<?php
// Start session BEFORE any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_borrower();

refresh_overdue_status();

$uid = (int) $_SESSION['user_id'];
$pdo = db();

$borrowMsg = $borrowErr = '';
$returnMsg = $returnErr = '';
$openModal = '';      
$txnCode   = '';
$borrowedBooks = [];
$bulkReturnMsg = $bulkReturnErr = '';
$showReceipt = false;
$receiptTxnCode = '';
$receiptBookCount = 0;
$receiptBooks = [];

// Start or continue a borrowing session
if (!isset($_SESSION['current_borrow_txn'])) {
    $_SESSION['current_borrow_txn'] = null;
}
if (!isset($_SESSION['current_borrow_books'])) {
    $_SESSION['current_borrow_books'] = [];
}

// Handle AJAX single book borrow (for continuous borrowing)
if (isset($_POST['ajax_borrow'])) {
    header('Content-Type: application/json');
    csrf_check();
    
    $bookBarcode = trim($_POST['book_barcode'] ?? '');
    $response = ['success' => false, 'message' => ''];
    
    if ($bookBarcode === '') {
        $response['message'] = 'Please enter a book barcode.';
    } else {
        $bstmt = $pdo->prepare('SELECT * FROM books WHERE barcode = ? AND deleted_at IS NULL LIMIT 1');
        $bstmt->execute([$bookBarcode]);
        $book = $bstmt->fetch();
        
        if (!$book) {
            $response['message'] = 'Book not found.';
        } elseif ($book['status'] !== 'Available') {
            $response['message'] = 'Book is not available.';
        } else {
            $cstmt = $pdo->prepare('SELECT COUNT(*) FROM borrowing WHERE user_id = ? AND return_date IS NULL AND approval_status = "approved"');
            $cstmt->execute([$uid]);
            $currentCount = (int) $cstmt->fetchColumn();
            
            if ($currentCount >= MAX_BOOKS_PER_USER) {
                $response['message'] = 'Maximum books reached.';
            } else {
                // Start new session or continue existing
                if ($_SESSION['current_borrow_txn'] === null) {
                    $_SESSION['current_borrow_txn'] = generate_transaction_code();
                    $_SESSION['current_borrow_books'] = [];
                }
                
                $txnCode = $_SESSION['current_borrow_txn'];
                $dueDate = compute_due_date();
                $approvalStatus = REQUIRE_APPROVAL ? 'pending' : 'approved';
                
                $pdo->beginTransaction();
                try {
                    $borrowStatus = REQUIRE_APPROVAL ? 'Pending' : 'Borrowed';
                    $pdo->prepare('
                        INSERT INTO borrowing (transaction_code, user_id, book_id, processed_by, borrow_date, due_date, status, approval_status)
                        VALUES (:code, :uid, :bid, NULL, NOW(), :due, :status, :approval)
                    ')->execute([':code' => $txnCode, ':uid' => $uid, ':bid' => $book['id'], ':due' => $dueDate, ':status' => $borrowStatus, ':approval' => $approvalStatus]);
                    
                    if (!REQUIRE_APPROVAL) {
                        $pdo->prepare('UPDATE books SET status = "Borrowed" WHERE id = ?')->execute([$book['id']]);
                    }
                    $pdo->commit();
                    
                    $_SESSION['current_borrow_books'][] = $book['title'];
                    audit_log($pdo, $uid, 'book_borrow_ajax', "Book: " . $book['title'] . ", Code: $txnCode, Status: $borrowStatus, Approval: $approvalStatus");
                    
                    if (REQUIRE_APPROVAL) {
                        // Send notification to all staff
                        send_borrow_request_notification($pdo, $uid, $book['title'], $txnCode);
                        $response['message'] = 'Request submitted for: ' . $book['title'] . ' (awaiting approval)';
                    } else {
                        $response['message'] = 'Added: ' . $book['title'];
                        // Send SMS notification for direct borrowing (no approval required)
                        $borrowId = (int) $pdo->lastInsertId();
                        send_borrow_sms_notification($pdo, $uid, $book['id'], $borrowId, $txnCode, $dueDate);
                    }
                    
                    $response['success'] = true;
                    $response['book'] = $book['title'];
                    $response['txn_code'] = $txnCode;
                    $response['total_books'] = count($_SESSION['current_borrow_books']);
                    $response['requires_approval'] = REQUIRE_APPROVAL;
                } catch (Throwable $ex) {
                    $pdo->rollBack();
                    $response['message'] = 'Failed: ' . $ex->getMessage();
                }
            }
        }
    }
    
    echo json_encode($response);
    exit();
}

// Handle end borrowing session
if (isset($_POST['end_borrow_session'])) {
    csrf_check();
    $txnCode = $_SESSION['current_borrow_txn'];
    $books = $_SESSION['current_borrow_books'];
    
    // Clear session
    $_SESSION['current_borrow_txn'] = null;
    $_SESSION['current_borrow_books'] = [];
    
    $borrowMsg = 'Successfully borrowed ' . count($books) . ' book(s)!';
    $borrowedBooks = $books;
    $openModal = 'borrow';
}

if (isset($_POST['borrow'])) {
    csrf_check();
    $openModal   = 'borrow';
    $bookBarcode = trim($_POST['book_barcode'] ?? '');
    $requestedDue = trim($_POST['due_date'] ?? '');

    $bstmt = $pdo->prepare('SELECT * FROM books WHERE barcode = ? AND deleted_at IS NULL LIMIT 1');
    $bstmt->execute([$bookBarcode]);
    $book = $bstmt->fetch();

    if ($bookBarcode === '') {
        $borrowErr = 'Please scan or enter a book barcode.';
    } elseif (!$book) {
        $borrowErr = 'Book not found. Please check the barcode.';
    } elseif ($book['status'] !== 'Available') {
        $borrowErr = 'Sorry, "' . $book['title'] . '" is currently ' . strtolower($book['status']) . '.';
    } else {
        $cstmt = $pdo->prepare('SELECT COUNT(*) FROM borrowing WHERE user_id = ? AND return_date IS NULL AND approval_status = "approved"');
        $cstmt->execute([$uid]);
        if ((int) $cstmt->fetchColumn() >= MAX_BOOKS_PER_USER) {
            $borrowErr = 'You already have the maximum of ' . MAX_BOOKS_PER_USER . ' borrowed books.';
        } else {
            $txnCode = generate_transaction_code();
            // Determine due date depending on role and request
            if (current_role() === 'teacher') {
                $maxDays = TEACHER_MAX_DAYS;
                if ($requestedDue !== '') {
                    $rd = date('Y-m-d', strtotime($requestedDue));
                    $today = date('Y-m-d');
                    $diffDays = (int) floor((strtotime($rd) - strtotime($today)) / 86400);
                    if ($diffDays < 0) {
                        $borrowErr = 'Preferred return date cannot be in the past.';
                    } elseif ($diffDays > $maxDays) {
                        $borrowErr = 'Preferred return date cannot exceed ' . $maxDays . ' days.';
                    } else {
                        $dueDate = $rd;
                    }
                } else {
                    $dueDate = date('Y-m-d', strtotime('+' . $maxDays . ' days'));
                }
            } else {
                $dueDate = compute_due_date();
            }
            if (!empty($borrowErr)) {
                // validation error for due date
                // leave earlier checks intact and do not proceed
                goto _end_borrow;
            }
            $approvalStatus = REQUIRE_APPROVAL ? 'pending' : 'approved';
            $borrowStatus = REQUIRE_APPROVAL ? 'Pending' : 'Borrowed';
            $pdo->beginTransaction();
            try {
                $pdo->prepare('
                    INSERT INTO borrowing (transaction_code, user_id, book_id, processed_by, borrow_date, due_date, status, approval_status)
                    VALUES (:code, :uid, :bid, NULL, NOW(), :due, :status, :approval)
                ')->execute([':code' => $txnCode, ':uid' => $uid, ':bid' => $book['id'], ':due' => $dueDate, ':status' => $borrowStatus, ':approval' => $approvalStatus]);
                
                if (!REQUIRE_APPROVAL) {
                    $pdo->prepare('UPDATE books SET status = "Borrowed" WHERE id = ?')->execute([$book['id']]);
                }
                $pdo->commit();
                audit_log($pdo, $uid, 'book_borrow', "Book: " . $book['title'] . ", Code: $txnCode, Status: $borrowStatus, Approval: $approvalStatus");
                
                if (REQUIRE_APPROVAL) {
                    send_borrow_request_notification($pdo, $uid, $book['title'], $txnCode);
                    $borrowMsg = 'Request submitted for "' . $book['title'] . '". Awaiting staff approval.';
                } else {
                    $borrowMsg = 'You borrowed "' . $book['title'] . '". Due ' . date('M d, Y', strtotime($dueDate)) . '.';
                    $showReceipt = true;
                    $receiptTxnCode = $txnCode;
                    $receiptBookCount = 1;
                    $receiptBooks = [$book['title']];
                    // Send SMS notification
                    $borrowId = (int) $pdo->lastInsertId();
                    send_borrow_sms_notification($pdo, $uid, $book['id'], $borrowId, $txnCode, $dueDate);
                }
            } catch (Throwable $ex) {
                $pdo->rollBack();
                $borrowErr = 'Borrow failed: ' . $ex->getMessage();
            }
        }
    }
}
_end_borrow:;

// Handle multi-book borrowing
if (isset($_POST['borrow_multiple'])) {
    csrf_check();
    $openModal   = 'borrow';
    $barcodes    = array_filter(array_map('trim', explode("\n", $_POST['book_barcodes'] ?? '')));
    
    if (empty($barcodes)) {
        $borrowErr = 'Please enter at least one book barcode.';
    } else {
        $cstmt = $pdo->prepare('SELECT COUNT(*) FROM borrowing WHERE user_id = ? AND return_date IS NULL AND approval_status = "approved"');
        $cstmt->execute([$uid]);
        $currentCount = (int) $cstmt->fetchColumn();
        
        if ($currentCount + count($barcodes) > MAX_BOOKS_PER_USER) {
            $borrowErr = 'You can only borrow ' . MAX_BOOKS_PER_USER . ' books at a time. You currently have ' . $currentCount . ' active loan(s).';
        } else {
            $txnCode = generate_transaction_code();
            $dueDate = compute_due_date();
            $approvalStatus = REQUIRE_APPROVAL ? 'pending' : 'approved';
            $borrowStatus = REQUIRE_APPROVAL ? 'Pending' : 'Borrowed';
            $borrowedBooks = [];
            $pdo->beginTransaction();
            try {
                foreach ($barcodes as $barcode) {
                    $bstmt = $pdo->prepare('SELECT * FROM books WHERE barcode = ? AND deleted_at IS NULL LIMIT 1');
                    $bstmt->execute([$barcode]);
                    $book = $bstmt->fetch();
                    
                    if (!$book) {
                        throw new Exception("Book with barcode '$barcode' not found.");
                    }
                    if ($book['status'] !== 'Available') {
                        throw new Exception("Book '" . $book['title'] . "' is not available.");
                    }
                    
                    $pdo->prepare('
                        INSERT INTO borrowing (transaction_code, user_id, book_id, processed_by, borrow_date, due_date, status, approval_status)
                        VALUES (:code, :uid, :bid, NULL, NOW(), :due, :status, :approval)
                    ')->execute([':code' => $txnCode, ':uid' => $uid, ':bid' => $book['id'], ':due' => $dueDate, ':status' => $borrowStatus, ':approval' => $approvalStatus]);
                    
                    if (!REQUIRE_APPROVAL) {
                        $pdo->prepare('UPDATE books SET status = "Borrowed" WHERE id = ?')->execute([$book['id']]);
                    }
                    $borrowedBooks[] = $book['title'];
                }
                $pdo->commit();
                audit_log($pdo, $uid, 'book_borrow_multiple', "Books: " . implode(', ', $borrowedBooks) . ", Code: $txnCode, Status: $borrowStatus, Approval: $approvalStatus");
                
                if (REQUIRE_APPROVAL) {
                    send_borrow_request_notification($pdo, $uid, implode(', ', $borrowedBooks), $txnCode);
                    $borrowMsg = 'Request submitted for ' . count($borrowedBooks) . ' book(s). Awaiting staff approval.';
                } else {
                    $borrowMsg = 'Successfully borrowed ' . count($borrowedBooks) . ' book(s) with transaction code: <strong>' . $txnCode . '</strong>';
                    $showReceipt = true;
                    $receiptTxnCode = $txnCode;
                    $receiptBookCount = count($borrowedBooks);
                    $receiptBooks = $borrowedBooks;
                    // Send SMS notification for the last borrowed book
                    $lastBookBarcode = end($barcodes);
                    $bstmt = $pdo->prepare('SELECT id FROM books WHERE barcode = ? LIMIT 1');
                    $bstmt->execute([$lastBookBarcode]);
                    $lastBook = $bstmt->fetch();
                    if ($lastBook) {
                        $borrowId = (int) $pdo->lastInsertId();
                        send_borrow_sms_notification($pdo, $uid, $lastBook['id'], $borrowId, $txnCode, $dueDate);
                    }
                }
            } catch (Throwable $ex) {
                $pdo->rollBack();
                $borrowErr = 'Borrow failed: ' . $ex->getMessage();
            }
        }
    }
}

// Handle unified return (barcode or transaction code)
if (isset($_POST['return_unified'])) {
    csrf_check();
    $openModal   = 'return';
    $input       = trim($_POST['return_input'] ?? '');
    
    if ($input === '') {
        $returnErr = 'Please enter a book barcode or transaction code.';
    } else {
        // Try to find by transaction code first
        $lstmt = $pdo->prepare("
            SELECT br.*, b.title, b.author, b.barcode as book_barcode
            FROM borrowing br
            JOIN books b ON b.id = br.book_id
            WHERE br.transaction_code = ? AND br.user_id = ? AND br.return_date IS NULL
        ");
        $lstmt->execute([$input, $uid]);
        $loans = $lstmt->fetchAll();
        
        if ($loans) {
            // Bulk return by transaction code
            $totalFine = 0;
            $pdo->beginTransaction();
            try {
                foreach ($loans as $loan) {
                    $late = days_overdue($loan['due_date'], null);
                    $fine = compute_fine($late);
                    $totalFine += $fine;
                    
                    $pdo->prepare('
                        UPDATE borrowing SET return_date = NOW(), status = "Returned", fine_amount = :fine WHERE id = :id
                    ')->execute([':fine' => $fine, ':id' => $loan['id']]);
                    $pdo->prepare('UPDATE books SET status = "Available" WHERE id = ?')->execute([$loan['book_id']]);
                    
                    // Create return notification for staff
                    create_return_notification($pdo, $loan['id'], $uid, $loan['book_id']);
                }
                $pdo->commit();
                audit_log($pdo, $uid, 'book_return_bulk', "Transaction: $input, Books: " . count($loans));
                $returnMsg = 'Successfully returned ' . count($loans) . ' book(s) using transaction code.';
                if ($totalFine > 0) {
                    $returnMsg .= ' Total fine: ' . strip_tags(peso($totalFine)) . '.';
                }
            } catch (Throwable $ex) {
                $pdo->rollBack();
                $returnErr = 'Return failed: ' . $ex->getMessage();
            }
        } else {
            // Try single book return by barcode
            $bstmt = $pdo->prepare('SELECT * FROM books WHERE barcode = ? LIMIT 1');
            $bstmt->execute([$input]);
            $book = $bstmt->fetch();
            
            if (!$book) {
                $returnErr = 'No book or transaction found for: ' . $input;
            } else {
                $lstmt = $pdo->prepare("
                    SELECT * FROM borrowing
                    WHERE book_id = ? AND user_id = ? AND return_date IS NULL
                    ORDER BY id DESC LIMIT 1
                ");
                $lstmt->execute([$book['id'], $uid]);
                $loan = $lstmt->fetch();
                
                if (!$loan) {
                    $returnErr = 'You have no active borrowed for this book.';
                } else {
                    $late = days_overdue($loan['due_date'], null);
                    $fine = compute_fine($late);
                    $pdo->beginTransaction();
                    try {
                        $pdo->prepare('
                            UPDATE borrowing SET return_date = NOW(), status = "Returned", fine_amount = :fine WHERE id = :id
                        ')->execute([':fine' => $fine, ':id' => $loan['id']]);
                        $pdo->prepare('UPDATE books SET status = "Available" WHERE id = ?')->execute([$book['id']]);
                        $pdo->commit();
                        audit_log($pdo, $uid, 'book_return', "Book: " . $book['title'] . ", Code: " . $loan['transaction_code']);
                        $returnMsg = 'You returned "' . $book['title'] . '".';
                        if ($late > 0) {
                            $returnMsg .= ' It was ' . $late . ' day(s) overdue. Fine: ' . strip_tags(peso($fine)) . '.';
                        }
                    } catch (Throwable $ex) {
                        $pdo->rollBack();
                        $returnErr = 'Return failed: ' . $ex->getMessage();
                    }
                }
            }
        }
    }
}

if (isset($_POST['return_book'])) {
    csrf_check();
    $openModal   = 'return';
    $bookBarcode = trim($_POST['barcode'] ?? '');

    $bstmt = $pdo->prepare('SELECT * FROM books WHERE barcode = ? LIMIT 1');
    $bstmt->execute([$bookBarcode]);
    $book = $bstmt->fetch();

    if ($bookBarcode === '') {
        $returnErr = 'Please scan or enter a book barcode.';
    } elseif (!$book) {
        $returnErr = 'Book not found. Please check the barcode.';
    } else {
        $lstmt = $pdo->prepare("
            SELECT * FROM borrowing
            WHERE book_id = ? AND user_id = ? AND return_date IS NULL
            ORDER BY id DESC LIMIT 1
        ");
        $lstmt->execute([$book['id'], $uid]);
        $loan = $lstmt->fetch();

        if (!$loan) {
            $returnErr = 'You have no active borrowed for this book.';
        } else {
            $late = days_overdue($loan['due_date'], null);
            $fine = compute_fine($late);
            $pdo->beginTransaction();
            try {
                $pdo->prepare('
                    UPDATE borrowing SET return_date = NOW(), status = "Returned", fine_amount = :fine WHERE id = :id
                ')->execute([':fine' => $fine, ':id' => $loan['id']]);
                $pdo->prepare('UPDATE books SET status = "Available" WHERE id = ?')->execute([$book['id']]);
                $pdo->commit();
                audit_log($pdo, $uid, 'book_return', "Book: " . $book['title'] . ", Code: " . $loan['transaction_code']);
                $returnMsg = 'You returned "' . $book['title'] . '".';
                if ($late > 0) {
                    $returnMsg .= ' It was ' . $late . ' day(s) overdue. Fine: ' . strip_tags(peso($fine)) . '.';
                }
                
                // Create return notification for staff
                create_return_notification($pdo, $loan['id'], $uid, $book['id']);
            } catch (Throwable $ex) {
                $pdo->rollBack();
                $returnErr = 'Return failed: ' . $ex->getMessage();
            }
        }
    }
}

$ustmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$ustmt->execute([$uid]);
$student = $ustmt->fetch();

$active = (int) (function () use ($pdo, $uid) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM borrowing WHERE user_id = ? AND return_date IS NULL");
    $s->execute([$uid]);
    return $s->fetchColumn();
})();

$overdue = (int) (function () use ($pdo, $uid) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM borrowing WHERE user_id = ? AND return_date IS NULL AND status = 'Overdue'");
    $s->execute([$uid]);
    return $s->fetchColumn();
})();

$fstmt = $pdo->prepare("SELECT COALESCE(SUM(fine_amount),0) FROM borrowing WHERE user_id = ? AND return_date IS NULL");
$fstmt->execute([$uid]);
$fines = (float) $fstmt->fetchColumn();

$cstmt = $pdo->prepare("
    SELECT br.transaction_code, b.title, b.author, br.borrow_date, br.due_date, br.status
    FROM borrowing br
    JOIN books b ON b.id = br.book_id
    WHERE br.user_id = ? AND br.return_date IS NULL
    ORDER BY br.due_date ASC
");
$cstmt->execute([$uid]);
$currentLoans = $cstmt->fetchAll();

$statRow = (function () use ($pdo, $uid) {
    $s = $pdo->prepare("
        SELECT
            COUNT(*)                                                   AS total,
            SUM(return_date IS NOT NULL)                               AS returned,
            SUM(return_date IS NOT NULL AND DATE(return_date) <= due_date) AS on_time,
            SUM(status = 'Overdue' OR (return_date IS NOT NULL AND DATE(return_date) > due_date)) AS ever_late
        FROM borrowing WHERE user_id = ?
    ");
    $s->execute([$uid]);
    return $s->fetch() ?: [];
})();
$totalBorrowed = (int) ($statRow['total'] ?? 0);
$totalReturned = (int) ($statRow['returned'] ?? 0);
$onTime        = (int) ($statRow['on_time'] ?? 0);
$everLate      = (int) ($statRow['ever_late'] ?? 0);
$onTimeRate    = $totalReturned > 0 ? round($onTime / $totalReturned * 100) : 100;

$catStmt = $pdo->prepare("
    SELECT b.category_name, COUNT(*) AS n
    FROM borrowing br JOIN books b ON b.id = br.book_id
    WHERE br.user_id = ? AND b.category_name IS NOT NULL AND b.category_name <> ''
    GROUP BY b.category_name ORDER BY n DESC
");
$catStmt->execute([$uid]);
$catRows       = $catStmt->fetchAll();
$categoriesN   = count($catRows);
$favCategory   = $catRows[0]['category'] ?? '';

// Smart recommendations based on search history + book views + keywords
$recommended = get_recommended_books($pdo, $uid, 4);

$dueSoon = [];
foreach ($currentLoans as $c) {
    if ($c['status'] === 'Overdue') { continue; }
    $daysLeft = (int) floor((strtotime($c['due_date'] . ' 23:59:59') - time()) / 86400);
    if ($daysLeft <= 3) {
        $dueSoon[] = ['title' => $c['title'], 'days' => $daysLeft];
    }
}

$achievements = [
    ['First Chapter',   'Borrowed your first book',        '&#128075;', $totalBorrowed >= 1],
    ['Bookworm',        'Borrowed 5 or more books',        '&#128027;', $totalBorrowed >= 5],
    ['Explorer',        'Read across 3+ categories',        '&#129517;', $categoriesN >= 3],
    ['On-Time Pro',     'Returned books, never late',       '&#9201;',   $totalReturned >= 1 && $everLate === 0],
    ['Marathon Reader', 'Returned 10 or more books',        '&#127942;', $totalReturned >= 10],
    ['Spotless',        'Zero outstanding fines',           '&#10024;',  $fines == 0.0],
];
$unlocked = count(array_filter($achievements, fn ($a) => $a[3]));

$slotPct = MAX_BOOKS_PER_USER > 0 ? min(100, round($active / MAX_BOOKS_PER_USER * 100)) : 0;

$pageTitle = 'My Dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="hero-card mb-4">
    <div class="d-flex align-items-center flex-wrap gap-4">
        <?php render_user_avatar($student); ?>
        <div class="flex-grow-1">
            <div class="text-muted" style="font-size:13px;letter-spacing:1px;text-transform:uppercase;">Welcome back</div>
            <h2 class="mb-1" style="font-weight:800;"><?= e(full_name($student)) ?></h2>
            <div class="text-muted">
                <?= e($student['barcode']) ?>
                <?php if ($student['course']): ?> &middot; <?= e($student['course']) ?><?php endif; ?>
                <?php if ($student['year_level']): ?> &middot; Year <?= e($student['year_level']) ?><?php endif; ?>
            </div>
            <div class="d-flex gap-2 flex-wrap mt-3">
                <button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#borrowModal">&#128229; Borrow a Book</button>
                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#returnModal">&#128228; Return a Book</button>
            </div>
        </div>

        <div class="libcard">
            <div class="libcard-top"><span> Library Card</span><span>S2B</span></div>
            <svg id="lib-barcode"></svg>
            <div class="libcard-id"><?= e($student['barcode']) ?></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-3 col-6"><div class="stat-card"><div class="icon">&#128229;</div><div><div class="label">Currently Borrowed</div><div class="value"><?= $active ?></div></div></div></div>
    <div class="col-lg-3 col-6"><div class="stat-card stat-danger"><div class="icon">&#9888;</div><div><div class="label">Overdue</div><div class="value"><?= $overdue ?></div></div></div></div>
    <div class="col-lg-3 col-6"><div class="stat-card stat-warning"><div class="icon">&#128176;</div><div><div class="label">Pending Fines</div><div class="value"><?= peso($fines) ?></div></div></div></div>
    <div class="col-lg-3 col-6"><div class="stat-card stat-success"><div class="icon">&#9989;</div><div><div class="label">On-Time Rate</div><div class="value"><?= $onTimeRate ?>%</div></div></div></div>
</div>

<?php if ($overdue > 0): ?>
    <div class="alert alert-danger">You have <?= $overdue ?> overdue book(s). Please return them to avoid further fines.</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="soft-card">
            <div class="section-title"><span class="dot"></span> Book Capacity</div>
            <div class="ring-wrap">
                <div class="ring" style="--val:<?= $slotPct ?>;--ring-color:<?= $active >= MAX_BOOKS_PER_USER ? 'var(--danger)' : 'var(--primary)' ?>;">
                    <div class="ring-hole"><div><b><?= $active ?>/<?= MAX_BOOKS_PER_USER ?></b><br><small>slots used</small></div></div>
                </div>
                <div>
                    <div class="fw-semibold mb-1"><?= max(0, MAX_BOOKS_PER_USER - $active) ?> slot(s)</div>
                    <div class="text-muted small">You can borrow <?= MAX_BOOKS_PER_USER ?> books at a time.</div>
                </div>
            </div>

            <hr class="my-3">
            <div class="section-title"><span class="dot"></span> Due Soon</div>
            <?php if (!$dueSoon): ?>
                <div class="text-muted small">Nothing due in the next 3 days. You're all caught up.</div>
            <?php else: ?>
                <div class="d-flex flex-column gap-2">
                <?php foreach ($dueSoon as $d): ?>
                    <span class="due-chip <?= $d['days'] <= 0 ? 'is-today' : ($d['days'] >= 3 ? 'is-ok' : '') ?>">
                        &#9200; <strong><?= e($d['title']) ?></strong>
                        &middot; <?= $d['days'] <= 0 ? 'due today' : 'in ' . $d['days'] . ' day' . ($d['days'] == 1 ? '' : 's') ?>
                    </span>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="soft-card">
            <div class="section-title"><span class="dot"></span> Recommended for You</div>
            <div class="text-muted small mb-2">
                <?php
                $recReason = 'Popular available titles';
                if ($favCategory !== '') {
                    $recReason = 'Because you enjoy <strong>' . e($favCategory) . '</strong>';
                } elseif (!empty($recommended)) {
                    $recReason = 'Based on your recent searches and interests';
                }
                echo $recReason;
                ?>
            </div>
            <?php if (!$recommended): ?>
                <div class="text-muted small">No available books to recommend right now. Try searching for something you like!</div>
            <?php else: ?>
                <?php foreach ($recommended as $r): ?>
                    <a href="student_search.php?search=<?= urlencode($r['title']) ?>" class="rec" style="text-decoration:none;color:inherit;">
                        <div class="rec-cover"><?= e(strtoupper(substr($r['title'], 0, 1))) ?></div>
                        <div class="flex-grow-1">
                            <div class="rec-t"><?= e($r['title']) ?></div>
                            <div class="rec-m"><?= e($r['author']) ?> &middot; <?= e($r['category']) ?></div>
                        </div>
                        <span class="badge bg-light text-muted border">&#128205; Flr <?= e($r['floor_no']) ?></span>
                    </a>
                <?php endforeach; ?>
                <a href="student_search.php" class="btn btn-outline-primary btn-sm w-100 mt-3">Browse full catalog</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="soft-card">
            <div class="section-title"><span class="dot"></span> Achievements <span class="badge bg-light text-muted border ms-auto"><?= $unlocked ?>/<?= count($achievements) ?></span></div>
            <div class="badge-grid">
                <?php foreach ($achievements as [$title, $desc, $icon, $got]): ?>
                    <div class="ach <?= $got ? '' : 'locked' ?>" title="<?= e($desc) ?>">
                        <div class="ach-ic"><?= $got ? $icon : '&#128274;' ?></div>
                        <div>
                            <div class="ach-t"><?= e($title) ?></div>
                            <div class="ach-d"><?= e($desc) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="table-card">
    <div class="section-title"><span class="dot"></span> My Books</div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr><th>Book</th><th>Borrowed</th><th>Due</th><th>Status</th><th>Receipt</th></tr>
            </thead>
            <tbody>
            <?php if (!$currentLoans): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">You have no active borrowed. Tap <strong>Borrow a Book</strong> to get started.</td></tr>
            <?php endif; ?>
            <?php foreach ($currentLoans as $c): ?>
                <tr class="<?= $c['status'] === 'Overdue' ? 'row-overdue' : '' ?>">
                    <td><?= e($c['title']) ?><br><span class="text-muted small"><?= e($c['author']) ?></span></td>
                    <td><?= e(date('M d, Y', strtotime($c['borrow_date']))) ?></td>
                    <td><?= e(date('M d, Y', strtotime($c['due_date']))) ?></td>
                    <td><?= status_badge($c['status']) ?></td>
                    <td><a href="receipt.php?code=<?= urlencode($c['transaction_code']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="borrowModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" id="borrowForm">
        <?= csrf_field() ?>
        <div class="modal-header text-white" style="background:var(--primary);">
          <h5 class="modal-title">&#128229; Borrow Books</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label class="form-label fw-semibold">Scan or Enter Book Barcode</label>
          <div class="input-group mb-3">
            <input type="text" name="book_barcode" class="form-control" placeholder="Scan barcode or type here..." autofocus>
            <button type="button" class="btn btn-outline-secondary" data-scan-target="book_barcode">Scan</button>
          </div>
          <?php if ($borrowErr): ?>
            <div class="alert alert-danger"><?= e($borrowErr) ?></div>
          <?php endif; ?>
          <?php if ($borrowMsg): ?>
            <div class="alert alert-success"><?= $borrowMsg ?></div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="submit" name="borrow" class="btn btn-primary fw-semibold">Borrow</button>
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header text-white" style="background:var(--success);">
        <h5 class="modal-title">&#10003; Borrowing Complete</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-success">
          <h4 id="successMessage"></h4>
          <hr>
          <p><strong>Transaction Code:</strong> <code id="successTxnCode"></code></p>
          <p><strong>Total Books:</strong> <span id="successBookCount"></span></p>
          <a href="#" id="successReceiptLink" target="_blank" class="btn btn-outline-success">View Receipt</a>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="returnModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <div class="modal-header text-white" style="background:var(--primary);">
          <h5 class="modal-title">&#128228; Return a Book</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php if ($returnMsg !== ''): ?><div class="alert alert-success"><?= e($returnMsg) ?></div><?php endif; ?>
          <?php if ($returnErr !== ''): ?><div class="alert alert-danger"><?= e($returnErr) ?></div><?php endif; ?>

          <label class="form-label fw-semibold">Book Barcode or Transaction Code</label>
          <div class="input-group">
            <input type="text" name="return_input" id="return_input" class="form-control" placeholder="Scan barcode or enter transaction code" required>
            <button type="button" class="btn btn-outline-secondary" data-scan-target="return_input">Scan</button>
          </div>

          <div class="alert alert-info mt-3">
            <strong>💡 Smart Return:</strong> 
            <ul class="mb-0 mt-2">
              <li>Enter a <strong>book barcode</strong> to return a single book</li>
              <li>Enter a <strong>transaction code</strong> to return all books from that borrowing session</li>
              <li>The system automatically detects which one you entered</li>
            </ul>
          </div>

          <p class="text-muted small mt-2">Transaction codes look like: <code>S2B-20250624-ABC123</code></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="return_unified" class="btn btn-primary fw-semibold">Process Return</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($openModal !== ''): ?>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var id = '<?= $openModal === 'borrow' ? 'borrowModal' : 'returnModal' ?>';
    new bootstrap.Modal(document.getElementById(id)).show();
  });
</script>
<?php endif; ?>

<?php if ($showReceipt): ?>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('successMessage').textContent = 'Successfully borrowed <?= $receiptBookCount ?> book(s)!';
    document.getElementById('successTxnCode').textContent = '<?= e($receiptTxnCode) ?>';
    document.getElementById('successBookCount').textContent = '<?= $receiptBookCount ?>';
    document.getElementById('successReceiptLink').href = 'receipt.php?code=<?= urlencode($receiptTxnCode) ?>';
    new bootstrap.Modal(document.getElementById('successModal')).show();
    
    // Send email notification
    const bookList = <?= json_encode($receiptBooks) ?>.join('\n');
    const emailMessage = `Dear <?= e($student['firstname']) ?>,

You have successfully borrowed <?= $receiptBookCount ?> book(s):

${bookList}

Transaction Code: <?= e($receiptTxnCode) ?>

Please return the books on or before the due date.

Thank you!`;
    
    fetch('send_notification.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        to: '<?= e($student['email']) ?>',
        subject: 'Books Borrowed Successfully',
        message: emailMessage
      })
    }).catch(err => console.log('Email notification failed:', err));
  });
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js" defer></script>
<script>
  window.addEventListener('load', function () {
    if (window.JsBarcode) {
      JsBarcode('#lib-barcode', '<?= e($student['barcode']) ?>', {
        format: 'CODE128', displayValue: false, margin: 0, height: 60, width: 2, lineColor: '#0f172a'
      });
    }
  });
</script>
<script src="assets/js/scanner.js" defer></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
