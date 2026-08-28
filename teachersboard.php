<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_borrower();

// Only teachers can access this dashboard
if (current_role() !== 'teacher') {
	redirect('studhome.php');
}

refresh_overdue_status();

$uid = (int) $_SESSION['user_id'];
$pdo = db();

$borrowMsg = $borrowErr = '';
$returnMsg = $returnErr = '';
$openModal = '';

// Get teacher info
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$uid]);
$teacher = $stmt->fetch();

// Handle teacher borrow
if (isset($_POST['borrow'])) {
	csrf_check();
	$openModal = 'borrow';
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
			// Validate teacher's due date (max 30 days)
			$dueDate = $requestedDue ? $requestedDue : date('Y-m-d', strtotime('+' . TEACHER_MAX_DAYS . ' days'));
			$now = new DateTime();
			$due = new DateTime($dueDate);
			$diff = $due->diff($now)->days;
			
			if ($due < $now) {
				$borrowErr = 'Due date cannot be in the past.';
			} elseif ($diff > TEACHER_MAX_DAYS) {
				$borrowErr = 'Due date cannot exceed ' . TEACHER_MAX_DAYS . ' days from today.';
			} else {
				$txnCode = generate_transaction_code();
				$approvalStatus = REQUIRE_APPROVAL ? 'pending' : 'approved';
				$borrowStatus = REQUIRE_APPROVAL ? 'Pending' : 'Borrowed';

				try {
					$pdo->beginTransaction();
					$pdo->prepare('
						INSERT INTO borrowing (transaction_code, user_id, book_id, processed_by, borrow_date, due_date, status, approval_status)
						VALUES (:code, :uid, :bid, NULL, NOW(), :due, :status, :approval)
					')->execute([':code' => $txnCode, ':uid' => $uid, ':bid' => $book['id'], ':due' => $dueDate, ':status' => $borrowStatus, ':approval' => $approvalStatus]);
					
					if (!REQUIRE_APPROVAL) {
						$pdo->prepare('UPDATE books SET status = "Borrowed" WHERE id = ?')->execute([$book['id']]);
					}
					$pdo->commit();
					audit_log($pdo, $uid, 'book_borrow_teacher', "Book: " . $book['title'] . ", Due: $dueDate, Code: $txnCode");
					
					if (REQUIRE_APPROVAL) {
						send_borrow_request_notification($pdo, $uid, $book['title'], $txnCode);
						$borrowMsg = 'Request submitted for: ' . $book['title'] . ' (awaiting approval)';
					} else {
						$borrowMsg = 'Successfully borrowed: ' . $book['title'] . ' | Due: ' . date('M d, Y', strtotime($dueDate));
						send_borrow_sms_notification($pdo, $uid, $book['id'], (int)$pdo->lastInsertId(), $txnCode, $dueDate);
					}
				} catch (Throwable $ex) {
					$pdo->rollBack();
					$borrowErr = 'Borrow failed: ' . $ex->getMessage();
				}
			}
		}
	}
}

// Handle teacher return
if (isset($_POST['return_unified'])) {
	csrf_check();
	$openModal = 'return';
	$input = trim($_POST['return_input'] ?? '');
	
	if ($input === '') {
		$returnErr = 'Please enter a book barcode or transaction code.';
	} else {
		// Try transaction code first
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
					create_return_notification($pdo, $loan['id'], $uid, $loan['book_id']);
				}
				$pdo->commit();
				audit_log($pdo, $uid, 'book_return_teacher', "Transaction: $input, Books: " . count($loans));
				$returnMsg = 'Successfully returned ' . count($loans) . ' book(s).';
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
					$returnErr = 'No active borrowing found for this book.';
				} else {
					$late = days_overdue($loan['due_date'], null);
					$fine = compute_fine($late);
					
					try {
						$pdo->beginTransaction();
						$pdo->prepare('
							UPDATE borrowing SET return_date = NOW(), status = "Returned", fine_amount = :fine WHERE id = :id
						')->execute([':fine' => $fine, ':id' => $loan['id']]);
						$pdo->prepare('UPDATE books SET status = "Available" WHERE id = ?')->execute([$book['id']]);
						create_return_notification($pdo, $loan['id'], $uid, $book['id']);
						$pdo->commit();
						audit_log($pdo, $uid, 'book_return_teacher', "Book: " . $book['title']);
						$returnMsg = 'Successfully returned: ' . $book['title'];
						if ($fine > 0) {
							$returnMsg .= ' | Fine: ' . strip_tags(peso($fine));
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

// Get current active loans
$active = (function() use ($pdo, $uid) {
	$s = $pdo->prepare("SELECT COUNT(*) FROM borrowing WHERE user_id = ? AND return_date IS NULL AND approval_status = 'approved'");
	$s->execute([$uid]);
	return (int) $s->fetchColumn();
})();

$overdue = (function() use ($pdo, $uid) {
	$s = $pdo->prepare("SELECT COUNT(*) FROM borrowing WHERE user_id = ? AND return_date IS NULL AND status = 'Overdue'");
	$s->execute([$uid]);
	return (int) $s->fetchColumn();
})();

$fines = (function() use ($pdo, $uid) {
	$s = $pdo->prepare("SELECT COALESCE(SUM(fine_amount), 0) FROM borrowing WHERE user_id = ? AND fine_amount > 0");
	$s->execute([$uid]);
	return (float) $s->fetchColumn();
})();

$cstmt = $pdo->prepare("
	SELECT br.transaction_code, b.title, b.author, br.borrow_date, br.due_date, br.status
	FROM borrowing br
	JOIN books b ON b.id = br.book_id
	WHERE br.user_id = ? AND br.return_date IS NULL
	ORDER BY br.due_date ASC
");
$cstmt->execute([$uid]);
$currentLoans = $cstmt->fetchAll();

$statRow = (function() use ($pdo, $uid) {
	$s = $pdo->prepare("
		SELECT
			COUNT(*) AS total,
			SUM(return_date IS NOT NULL) AS returned,
			SUM(return_date IS NOT NULL AND DATE(return_date) <= due_date) AS on_time,
			SUM(status = 'Overdue' OR (return_date IS NOT NULL AND DATE(return_date) > due_date)) AS ever_late
		FROM borrowing WHERE user_id = ?
	");
	$s->execute([$uid]);
	return $s->fetch() ?: [];
})();

$totalBorrowed = (int) ($statRow['total'] ?? 0);
$totalReturned = (int) ($statRow['returned'] ?? 0);
$onTime = (int) ($statRow['on_time'] ?? 0);
$everLate = (int) ($statRow['ever_late'] ?? 0);
$onTimeRate = $totalReturned > 0 ? round($onTime / $totalReturned * 100) : 100;

$dueSoon = [];
foreach ($currentLoans as $c) {
	if ($c['status'] === 'Overdue') { continue; }
	$daysLeft = (int) floor((strtotime($c['due_date'] . ' 23:59:59') - time()) / 86400);
	if ($daysLeft <= 3) {
		$dueSoon[] = ['title' => $c['title'], 'days' => $daysLeft];
	}
}

$slotPct = MAX_BOOKS_PER_USER > 0 ? min(100, round($active / MAX_BOOKS_PER_USER * 100)) : 0;

// Advanced analytics for teachers
$categoryPrefs = (function() use ($pdo, $uid) {
	$s = $pdo->prepare("
		SELECT b.category_name, COUNT(*) AS cnt
		FROM borrowing br
		JOIN books b ON b.id = br.book_id
		WHERE br.user_id = ? AND b.category_name IS NOT NULL
		GROUP BY b.category_name
		ORDER BY cnt DESC
		LIMIT 5
	");
	$s->execute([$uid]);
	return $s->fetchAll();
})();

// Borrowing frequency (books per month in last 6 months)
$borrowingVelocity = (function() use ($pdo, $uid) {
	$s = $pdo->prepare("
		SELECT COUNT(*) AS borrow_count,
			ROUND(COUNT(*) / NULLIF(DATEDIFF(MAX(borrow_date), MIN(borrow_date)) / 30, 0), 2) AS books_per_month
		FROM borrowing
		WHERE user_id = ? AND borrow_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
	");
	$s->execute([$uid]);
	$row = $s->fetch();
	return $row ? $row : ['borrow_count' => 0, 'books_per_month' => 0];
})();

// Average days books are kept
$avgDaysKept = (function() use ($pdo, $uid) {
	$s = $pdo->prepare("
		SELECT ROUND(AVG(DATEDIFF(return_date, borrow_date)), 1) AS avg_days
		FROM borrowing
		WHERE user_id = ? AND return_date IS NOT NULL
	");
	$s->execute([$uid]);
	$row = $s->fetch();
	return $row ? (float)$row['avg_days'] : 0;
})();

// Return rate percentage
$returnRate = $totalReturned > 0 ? round(($totalReturned / $totalBorrowed) * 100) : 0;

// Overdue rate percentage
$overdueRate = $totalBorrowed > 0 ? round(($everLate / $totalBorrowed) * 100) : 0;

// Fine risk prediction (based on current active loans nearing due date)
$fineRiskPrediction = (function() use ($pdo, $uid) {
	$s = $pdo->prepare("
		SELECT 
			SUM(CASE WHEN DATE(due_date) < CURDATE() THEN 1 ELSE 0 END) AS already_overdue,
			SUM(CASE WHEN DATE(due_date) <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND DATE(due_date) >= CURDATE() THEN 1 ELSE 0 END) AS due_soon,
			ROUND(AVG(DATEDIFF(due_date, CURDATE())), 1) AS avg_days_left
		FROM borrowing
		WHERE user_id = ? AND return_date IS NULL
	");
	$s->execute([$uid]);
	return $s->fetch() ?: ['already_overdue' => 0, 'due_soon' => 0, 'avg_days_left' => 0];
})();

// Department stats
$deptStats = (function() use ($pdo, $teacher) {
	if (empty($teacher['department'])) return null;
	$s = $pdo->prepare("
		SELECT COUNT(DISTINCT user_id) AS dept_members, COUNT(*) AS dept_active_borrows
		FROM borrowing
		WHERE user_id IN (
			SELECT id FROM users WHERE department = ? AND return_date IS NULL
		)
		LIMIT 1
	");
	$s->execute([$teacher['department']]);
	return $s->fetch();
})();

// Next due book
$nextDueBook = (function() use ($pdo, $uid) {
	$s = $pdo->prepare("
		SELECT b.title, br.due_date, DATEDIFF(br.due_date, CURDATE()) AS days_left
		FROM borrowing br
		JOIN books b ON b.id = br.book_id
		WHERE br.user_id = ? AND br.return_date IS NULL
		ORDER BY br.due_date ASC
		LIMIT 1
	");
	$s->execute([$uid]);
	return $s->fetch();
})();

$pageTitle = 'My Dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="hero-card mb-4">
	<div class="d-flex align-items-center flex-wrap gap-4">
		<?php if (!empty($teacher['photo'])): ?>
			<img src="<?= e($teacher['photo']) ?>" alt="ID photo" class="profile-avatar"
				 style="object-fit:cover;padding:0;">
		<?php else: ?>
			<div class="profile-avatar">
				👨‍🏫
			</div>
		<?php endif; ?>
		<div class="flex-grow-1">
			<div class="text-muted" style="font-size:13px;letter-spacing:1px;text-transform:uppercase;">Welcome back</div>
			<h2 class="mb-1" style="font-weight:800;"><?= e(full_name($teacher)) ?></h2>
			<div class="text-muted">
				<?= e($teacher['barcode']) ?>
				<?php if ($teacher['department']): ?> &middot; <?= e($teacher['department']) ?><?php endif; ?>
				<?php if ($teacher['position']): ?> &middot; <?= e($teacher['position']) ?><?php endif; ?>
			</div>
			<div class="d-flex gap-2 flex-wrap mt-3">
				<button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#borrowModal">&#128229; Borrow a Book</button>
				<button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#returnModal">&#128228; Return a Book</button>
			</div>
		</div>

		<div class="libcard">
			<div class="libcard-top"><span>&#128218; Teacher Card</span><span>S2B</span></div>
			<svg id="lib-barcode"></svg>
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
			<div class="section-title"><span class="dot"></span> Reading Velocity</div>
			<div class="text-center mb-3">
				<div style="font-size:32px;font-weight:700;color:var(--primary);"><?= number_format($borrowingVelocity['books_per_month'], 1) ?></div>
				<small class="text-muted">books per month (6-month trend)</small>
			</div>
			<div style="border-top:1px solid #e9ecef;padding-top:12px;">
				<div class="d-flex justify-content-between align-items-center mb-2">
					<span class="small">Avg. Days Kept</span>
					<strong><?= $avgDaysKept ?> days</strong>
				</div>
				<div class="d-flex justify-content-between align-items-center">
					<span class="small">Return Rate</span>
					<strong class="text-success"><?= $returnRate ?>%</strong>
				</div>
			</div>
		</div>
	</div>

	<div class="col-lg-4">
		<div class="soft-card">
			<div class="section-title"><span class="dot"></span> ⚡ Fine Risk Prediction</div>
			<?php 
				$riskLevel = 'low';
				$riskColor = 'success';
				if ($fineRiskPrediction['already_overdue'] > 0) {
					$riskLevel = 'critical';
					$riskColor = 'danger';
				} elseif ($fineRiskPrediction['due_soon'] > 1) {
					$riskLevel = 'high';
					$riskColor = 'warning';
				} elseif ($fineRiskPrediction['due_soon'] > 0 || $fineRiskPrediction['avg_days_left'] < 5) {
					$riskLevel = 'medium';
					$riskColor = 'info';
				}
			?>
			<div class="d-flex align-items-center mb-2">
				<div style="width:60px;height:60px;border-radius:50%;background:var(--<?= $riskColor ?>);opacity:0.15;display:flex;align-items:center;justify-content:center;margin-right:12px;">
					<strong style="color:var(--<?= $riskColor ?>);font-size:24px;">
						<?php if ($riskLevel === 'critical'): ?>⚠️
						<?php elseif ($riskLevel === 'high'): ?>⏱️
						<?php elseif ($riskLevel === 'medium'): ?>📅
						<?php else: ?>✓<?php endif; ?>
					</strong>
				</div>
				<div>
					<div class="small text-muted">Risk Level</div>
					<strong><?= ucfirst($riskLevel) ?></strong>
				</div>
			</div>
			<div style="border-top:1px solid #e9ecef;padding-top:12px;font-size:12px;">
				<?php if ($fineRiskPrediction['already_overdue'] > 0): ?>
					<div class="mb-1"><span class="badge bg-danger">🔴 <?= $fineRiskPrediction['already_overdue'] ?> overdue</span></div>
				<?php endif; ?>
				<?php if ($fineRiskPrediction['due_soon'] > 0): ?>
					<div class="mb-1"><span class="badge bg-warning">🟡 <?= $fineRiskPrediction['due_soon'] ?> due in 3 days</span></div>
				<?php endif; ?>
				<div class="text-muted">⌛ Avg. <?= $fineRiskPrediction['avg_days_left'] ?> days left</div>
			</div>
		</div>
	</div>
</div>

<div class="row g-3 mb-4">
	<div class="col-lg-6">
		<div class="soft-card">
			<div class="section-title"><span class="dot"></span> Subject Expertise</div>
			<?php if (empty($categoryPrefs)): ?>
				<div class="text-muted small">Borrow books to discover your reading patterns.</div>
			<?php else: ?>
				<div class="d-flex flex-column gap-2">
					<?php foreach ($categoryPrefs as $idx => $cat): ?>
						<div class="d-flex align-items-center">
							<div style="width:32px;height:32px;border-radius:50%;background:rgba(<?= [72,199,142,182,124,237,242,113,220][($idx % 9)] ?>,0.15);display:flex;align-items:center;justify-content:center;font-size:14px;margin-right:10px;">
								<?= $idx + 1 ?>
							</div>
							<div class="flex-grow-1">
								<strong class="d-block"><?= e($cat['category_name']) ?></strong>
								<small class="text-muted"><?= $cat['cnt'] ?> book<?= $cat['cnt'] != 1 ? 's' : '' ?></small>
							</div>
							<div style="height:3px;width:<?= min(100, $cat['cnt'] * 15) ?>px;background:var(--primary);border-radius:2px;"></div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="col-lg-6">
		<div class="soft-card">
			<div class="section-title"><span class="dot"></span> 🏫 Teacher Profile</div>
			<div class="text-muted small">
				<div class="mb-3">
					<div class="small text-muted" style="letter-spacing:1px;text-transform:uppercase;font-size:11px;">Department</div>
					<strong style="font-size:16px;color:#000;"><?= !empty($teacher['department']) ? e($teacher['department']) : '<span class="text-muted">Not specified</span>' ?></strong>
				</div>
				<div class="mb-3">
					<div class="small text-muted" style="letter-spacing:1px;text-transform:uppercase;font-size:11px;">Position</div>
					<strong style="font-size:16px;color:#000;"><?= !empty($teacher['position']) ? e($teacher['position']) : '<span class="text-muted">Not specified</span>' ?></strong>
				</div>
				<?php if (!empty($deptStats)): ?>
					<div style="border-top:1px solid #e9ecef;padding-top:12px;">
						<div class="d-flex justify-content-between align-items-center mb-1">
							<span class="small">Department Members</span>
							<strong><?= $deptStats['dept_members'] ?? 1 ?></strong>
						</div>
						<div class="d-flex justify-content-between align-items-center">
							<span class="small">Dept. Active Borrows</span>
							<strong><?= $deptStats['dept_active_borrows'] ?? 0 ?></strong>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<?php if ($nextDueBook): ?>
<div style="background:linear-gradient(135deg, var(--primary), rgba(72,199,142,0.8));border-radius:8px;padding:16px;color:white;margin-bottom:24px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
	<div style="display:flex;align-items:center;gap:16px;">
		<div style="font-size:32px;opacity:0.8;">📚</div>
		<div class="flex-grow-1">
			<strong>Next Book Due</strong><br>
			<div style="font-size:18px;font-weight:700;margin-top:4px;"><?= e($nextDueBook['title']) ?></div>
			<small style="opacity:0.9;">Due in <?= $nextDueBook['days_left'] ?> day<?= $nextDueBook['days_left'] != 1 ? 's' : '' ?> • <?= date('M d, Y', strtotime($nextDueBook['due_date'])) ?></small>
		</div>
		<button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#returnModal">Return Now</button>
	</div>
</div>
<?php endif; ?>

<div class="soft-card" style="background:rgba(244,246,249,0.8);border:1px solid #e9ecef;">
	<div class="section-title"><span class="dot"></span> 💡 Smart Insights</div>
	<div class="text-muted small">
		<?php 
			$insights = [];
			if ($borrowingVelocity['books_per_month'] > 2) {
				$insights[] = '📖 You\'re an avid reader! At your current pace, you borrow over 2 books monthly.';
			}
			if ($returnRate === 100 && $totalReturned > 0) {
				$insights[] = '✨ Perfect return record! You\'ve never missed a deadline.';
			}
			if ($onTimeRate >= 90) {
				$insights[] = '⏱️ Excellent on-time return rate. Keep it up!';
			}
			if (!empty($categoryPrefs)) {
				$topCategory = $categoryPrefs[0]['category_name'];
				$insights[] = '🎯 Your favorite category is ' . e($topCategory) . '. Consider exploring related titles.';
			}
			if ($active >= MAX_BOOKS_PER_USER) {
				$insights[] = '⚠️ Your book slots are full. Return a book to borrow more.';
			}
			if (empty($insights)) {
				$insights[] = '📊 Start borrowing books to see personalized insights.';
			}
		?>
		<ul class="mb-0 mt-2" style="list-style:none;padding-left:0;">
			<?php foreach (array_slice($insights, 0, 3) as $insight): ?>
				<li style="padding:8px 0;border-bottom:1px solid #e9ecef;"><?= $insight ?></li>
			<?php endforeach; ?>
		</ul>
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
				<tr><td colspan="5" class="text-center text-muted py-4">You have no active borrowed books. Tap <strong>Borrow a Book</strong> to get started.</td></tr>
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

<?php require __DIR__ . '/includes/footer.php'; ?>

<!-- Borrow Modal -->
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

		  <label class="form-label fw-semibold">Preferred Return Date</label>
		  <input type="date" name="due_date" class="form-control mb-3">
		  <div class="form-text small text-muted mb-3">Teachers can borrow books for up to <?= TEACHER_MAX_DAYS ?> days. If no date is selected, it defaults to <?= TEACHER_MAX_DAYS ?> days from today.</div>

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

<!-- Return Modal -->
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
		  <button type="submit" name="return_unified" class="btn btn-primary fw-semibold">Return</button>
		</div>
	  </form>
	</div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
	<?php if ($openModal === 'borrow'): ?>
		const borrowModal = new bootstrap.Modal(document.getElementById('borrowModal'));
		borrowModal.show();
	<?php elseif ($openModal === 'return'): ?>
		const returnModal = new bootstrap.Modal(document.getElementById('returnModal'));
		returnModal.show();
	<?php endif; ?>

	// Draw barcode
	const barcode = '<?= e($teacher['barcode']) ?>';
	JsBarcode("#lib-barcode", barcode, { format: "CODE128", width: 2, height: 40 });

	// Scan handler
	document.querySelectorAll('[data-scan-target]').forEach(btn => {
		btn.addEventListener('click', function(e) {
			e.preventDefault();
			const inputId = this.dataset.scanTarget;
			document.getElementById(inputId).focus();
		});
	});
});
</script>