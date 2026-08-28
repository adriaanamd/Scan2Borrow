<?php
require_once __DIR__ . '/includes/auth.php';
require_staff();

refresh_overdue_status();

$pdo = db();
$msg = '';
$err = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $borrowingId = (int) ($_POST['borrowing_id'] ?? 0);
    
    if ($borrowingId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM borrowing WHERE id = ? LIMIT 1');
        $stmt->execute([$borrowingId]);
        $borrowing = $stmt->fetch();
        
        if ($borrowing) {
            if ($action === 'approve') {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('UPDATE borrowing SET approval_status = "approved", status = "Borrowed", approved_at = NOW(), approved_by = ? WHERE id = ?')
                        ->execute([$_SESSION['user_id'], $borrowingId]);
                    $pdo->prepare('UPDATE books SET status = "Borrowed" WHERE id = ?')
                        ->execute([$borrowing['book_id']]);
                    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE related_id = ? AND user_id = ? AND type = "borrow_request"')
                        ->execute([$borrowingId, $_SESSION['user_id']]);
                    
                    // Create borrow notification for staff
                    create_borrow_notification($pdo, $borrowingId, $borrowing['user_id'], $borrowing['book_id']);
                    
                    $pdo->commit();
                    audit_log($pdo, $_SESSION['user_id'], 'borrow_approve', "Approved borrowing ID: $borrowingId");
                    // Use POST-Redirect-GET to refresh the list and avoid showing stale pending items
                    redirect('adboard.php');
                } catch (Throwable $ex) {
                    $pdo->rollBack();
                    $err = 'Approval failed: ' . $ex->getMessage();
                }
            } elseif ($action === 'reject') {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('UPDATE borrowing SET approval_status = "rejected", approved_at = NOW(), approved_by = ? WHERE id = ?')
                        ->execute([$_SESSION['user_id'], $borrowingId]);
                    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE related_id = ? AND user_id = ?')
                        ->execute([$borrowingId, $_SESSION['user_id']]);
                    $pdo->commit();
                    audit_log($pdo, $_SESSION['user_id'], 'borrow_reject', "Rejected borrowing ID: $borrowingId");
                    // Redirect to refresh page state after POST
                    redirect('adboard.php');
                } catch (Throwable $ex) {
                    $pdo->rollBack();
                    $err = 'Rejection failed: ' . $ex->getMessage();
                }
            }
        }
    }
}

$count = function (string $sql) use ($pdo): int {
    return (int) $pdo->query($sql)->fetchColumn();
};

$totalBooks     = $count("SELECT COUNT(*) FROM books WHERE deleted_at IS NULL");
$availableBooks = $count("SELECT COUNT(*) FROM books WHERE deleted_at IS NULL AND status = 'Available'");
$borrowedBooks  = $count("SELECT COUNT(*) FROM books WHERE deleted_at IS NULL AND status = 'Borrowed'");
$borrowers      = $count("SELECT COUNT(*) FROM users WHERE role IN ('student','teacher')");
$activeLoans    = $count("SELECT COUNT(*) FROM borrowing WHERE return_date IS NULL");
$overdueLoans   = $count("SELECT COUNT(*) FROM borrowing WHERE return_date IS NULL AND status = 'Overdue'");
$pendingApprovals = $count("SELECT COUNT(*) FROM borrowing WHERE approval_status = 'pending' AND return_date IS NULL");

$recent = $pdo->query("
    SELECT br.transaction_code, br.borrow_date, br.due_date, br.status,
           b.title, CONCAT(u.firstname, ' ', u.lastname) AS borrower
    FROM borrowing br
    JOIN books b  ON b.id = br.book_id
    JOIN users u  ON u.id = br.user_id
    ORDER BY br.id DESC
    LIMIT 8
")->fetchAll();

$pageTitle = 'Dashboard';
$showBorrowNotification = false;
$borrowNotificationData = null;

// Check if we just approved a borrow request
if ($msg !== '' && strpos($msg, 'approved successfully') !== false && isset($borrowing)) {
    $showBorrowNotification = true;
    
    // Get book title from books table
    $bookStmt = $pdo->prepare('SELECT title FROM books WHERE id = ? LIMIT 1');
    $bookStmt->execute([$borrowing['book_id']]);
    $bookData = $bookStmt->fetch();
    
    $borrowNotificationData = [
        'student_name' => full_name($pdo->query("SELECT firstname, lastname FROM users WHERE id = " . (int)$borrowing['user_id'])->fetch()),
        'book_title' => $bookData['title'] ?? 'Unknown Book',
        'borrow_date' => date('M d, Y'),
        'due_date' => date('M d, Y', strtotime($borrowing['due_date'] ?? 'now'))
    ];
}

require __DIR__ . '/includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="icon">&#128218;</div>
            <div><div class="label">Total Books</div><div class="value"><?= $totalBooks ?></div></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card stat-accent">
            <div class="icon">&#9989;</div>
            <div><div class="label">Available</div><div class="value"><?= $availableBooks ?></div></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card stat-danger">
            <div class="icon">&#128214;</div>
            <div><div class="label">Borrowed</div><div class="value"><?= $borrowedBooks ?></div></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="icon">&#128100;</div>
            <div><div class="label">Borrowers</div><div class="value"><?= $borrowers ?></div></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card stat-warning">
            <div class="icon">&#128229;</div>
            <div><div class="label">Active Borrowed</div><div class="value"><?= $activeLoans ?></div></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card stat-danger">
            <div class="icon">&#9888;</div>
            <div><div class="label">Overdue</div><div class="value"><?= $overdueLoans ?></div></div>
        </div>
    </div>
</div>

<div class="table-card">
    <h5 class="mb-3">Recent Transactions</h5>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Code</th><th>Borrower</th><th>Book</th>
                    <th>Borrowed</th><th>Due</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$recent): ?>
                <tr><td colspan="6" class="text-center text-muted">No transactions yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($recent as $r): ?>
                <tr>
                    <td><code><?= e($r['transaction_code']) ?></code></td>
                    <td><?= e($r['borrower']) ?></td>
                    <td><?= e($r['title']) ?></td>
                    <td><?= e(date('M d, Y', strtotime($r['borrow_date']))) ?></td>
                    <td><?= e(date('M d, Y', strtotime($r['due_date']))) ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

<script>
  // Auto-open approval modal if there are pending approvals or after action
  document.addEventListener('DOMContentLoaded', function () {
    <?php if ($msg !== '' || $err !== '' || $pendingApprovals > 0): ?>
    var approvalModal = new bootstrap.Modal(document.getElementById('approvalModal'));
    approvalModal.show();
    <?php endif; ?>
    
    // Show borrow notification if just approved
    <?php if ($showBorrowNotification && $borrowNotificationData): ?>
    var borrowModal = document.getElementById('borrowNotificationModal');
    var borrowModalBody = document.querySelector('#borrowNotificationModal .modal-body');
    if (borrowModalBody) {
      borrowModalBody.innerHTML = '<strong>Student:</strong> <?= e($borrowNotificationData['student_name']) ?><br><br>' +
        '<strong>Book:</strong> <?= e($borrowNotificationData['book_title']) ?><br>' +
        '<strong>Borrowed:</strong> <?= e($borrowNotificationData['borrow_date']) ?><br>' +
        '<strong>Due Date:</strong> <?= e($borrowNotificationData['due_date']) ?><br><br>' +
        'The book has been successfully borrowed and is now marked as "Borrowed".';
    }
    var bsBorrowModal = new bootstrap.Modal(borrowModal);
    bsBorrowModal.show();
    <?php endif; ?>
    
    // Poll for pending approvals every 5 seconds
    setInterval(function() {
      fetch('api_notifications.php?action=pending_approvals')
        .then(response => response.json())
        .then(data => {
          if (!data || !data.success) return;

          // Update the badge in the modal (if any)
          const badge = document.querySelector('#approvalModal .badge');
          if (badge) {
            badge.textContent = data.count || 0;
          }

          // Update approval list only (preserve modal header/styles)
          const approvalList = document.getElementById('approvalList');
          if (approvalList && typeof data.html === 'string') {
            approvalList.innerHTML = data.html;
          }

          // Update button badge
          const btnBadge = document.querySelector('button[data-bs-target="#approvalModal"] .badge');
          if (btnBadge) {
            btnBadge.textContent = data.count || 0;
          }

          // Show toast notification if there are new pending requests and modal is not open
          if ((data.count || 0) > 0) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('approvalModal'));
            if (!modal || !modal._isShown) {
              showToast('New Borrow Request', 'You have ' + data.count + ' pending approval request(s).', 'warning');
            }
          }
        })
        .catch(error => console.log('Error fetching pending approvals:', error));
    }, 5000); // Check every 5 seconds
    
    // Poll for return notifications every 5 seconds
    setInterval(function() {
      fetch('api_notifications.php?action=return_notifications')
        .then(response => response.json())
        .then(data => {
          if (data.success && data.notifications && data.notifications.length > 0) {
            // Show the first unviewed notification
            const notif = data.notifications[0];
            showReturnNotification(notif);
          }
        })
        .catch(error => console.log('Error fetching return notifications:', error));
    }, 5000); // Check every 5 seconds
    
    // Poll for borrow notifications every 5 seconds
    setInterval(function() {
      fetch('api_notifications.php?action=borrow_notifications')
        .then(response => response.json())
        .then(data => {
          if (data.success && data.notifications && data.notifications.length > 0) {
            // Show the first unviewed notification
            const notif = data.notifications[0];
            showBorrowNotification(notif);
          }
        })
        .catch(error => console.log('Error fetching borrow notifications:', error));
    }, 5000); // Check every 5 seconds
  });
  
  // Show return notification modal
  function showReturnNotification(notification) {
    const modal = document.getElementById('returnNotificationModal');
    const modalBody = document.querySelector('#returnNotificationModal .modal-body');
    
    if (modalBody && notification.message) {
      modalBody.innerHTML = '<pre style="white-space: pre-wrap; font-family: inherit;">' + notification.message + '</pre>';
    }
    
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    // Mark as viewed when modal is closed
    modal.addEventListener('hidden.bs.modal', function() {
      fetch('api_notifications.php?action=mark_notification_viewed', {
        method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'notification_id=' + notification.id
          });
        }, { once: true });
  }
  
  // Show borrow notification modal
  function showBorrowNotification(notification) {
    const modal = document.getElementById('borrowNotificationModal');
    const modalBody = document.querySelector('#borrowNotificationModal .modal-body');
    
    if (modalBody && notification.message) {
      modalBody.innerHTML = '<pre style="white-space: pre-wrap; font-family: inherit;">' + notification.message + '</pre>';
    }
    
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    // Mark as viewed when modal is closed
    modal.addEventListener('hidden.bs.modal', function() {
      fetch('api_notifications.php?action=mark_notification_viewed', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'notification_id=' + notification.id + '&notification_type=borrow'
      });
    }, { once: true });
  }
  
  // Show toast notification
  function showToast(title, message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
      // Create toast container if it doesn't exist
      const container = document.createElement('div');
      container.id = 'toastContainer';
      container.className = 'toast-container position-fixed top-0 end-0 p-3';
      container.style.zIndex = '9999';
      document.body.appendChild(container);
    }
    
    const toastId = 'toast-' + Date.now();
    const toastHtml = `
      <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body">
            <strong>${title}</strong><br>
            ${message}
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    `;
    
    document.getElementById('toastContainer').insertAdjacentHTML('beforeend', toastHtml);
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 5000 });
    toast.show();
    
    // Remove toast element after it's hidden
    toastElement.addEventListener('hidden.bs.toast', function() {
      this.remove();
    });
  }
</script>

<!-- Borrow Notification Modal -->
<div class="modal fade" id="borrowNotificationModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header text-white" style="background:var(--primary);">
        <h5 class="modal-title">&#128229; New Book Borrowed</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Return Notification Modal -->
<div class="modal fade" id="returnNotificationModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header text-white" style="background:var(--success);">
        <h5 class="modal-title">&#128230; Book Returned Successfully</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Toast Container for notifications -->
<div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

<!-- Approval Requests Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header text-white" style="background:var(--warning);">
        <h5 class="modal-title">📋 Pending Borrow Approvals</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
        <style>
          .approval-card { max-width: 100%; border-radius: 1rem; background-color: rgba(31,41,55,1); padding: 1rem; color: #fff; }
          .approval-card .infos { display: flex; flex-direction: row; align-items: flex-start; gap: 1rem; }
          .approval-card .image { flex: 0 0 7rem; height: 7rem; border-radius: 0.5rem; overflow: hidden; background: linear-gradient(to bottom right, rgb(118,36,194), rgb(185,128,240)); }
          .approval-card .image img { width: 100%; height: 100%; object-fit: cover; display: block; }
          .approval-card .info { height: 7rem; flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
          .approval-card .name { font-size: 1.125rem; line-height: 1.5rem; font-weight: 600; color: #fff; margin: 0; }
          .approval-card .function { font-size: 0.8rem; color: rgba(156,163,175,1); margin: 0; }
          .approval-card .stats { width: 100%; border-radius: 0.5rem; background-color: #fff; padding: 0.4rem; display: flex; align-items: center; justify-content: space-between; font-size: 0.75rem; color: #000; }
          .approval-card .flex { display:flex; flex-direction:column; align-items:center; margin: 0 6px; }
          .approval-card .state-value { font-weight:700; color: rgb(118,36,194); }
          .approval-card .request { margin-top: 0.75rem; width: 100%; border: 1px solid transparent; border-radius: 0.5rem; padding: 0.45rem 0.75rem; font-size: 0.95rem; line-height: 1.25rem; transition: all .2s ease; background: transparent; color: inherit; }
          .approval-card .request:hover { background-color: rgb(118,36,194); color: #fff; }
          .approval-actions { display:flex; gap:0.75rem; margin-top:0.75rem; }
        </style>

        <?php
        $pending = $pdo->query("
          SELECT br.*, b.title, b.author, b.barcode as book_barcode, b.cover_file as book_cover,
               u.firstname, u.lastname, u.barcode as student_barcode, u.course, u.year_level
          FROM borrowing br
          JOIN books b ON b.id = br.book_id
          JOIN users u ON u.id = br.user_id
          WHERE br.approval_status = 'pending'
            AND br.return_date IS NULL
          ORDER BY br.requested_at ASC
        ")->fetchAll();
        ?>

        <div id="approvalList">
        <?php if (empty($pending)): ?>
          <div class="text-center text-muted py-5">
            <div style="font-size: 48px;">✅</div>
            <p class="mt-3">No pending approval requests at this time.</p>
          </div>
        <?php else: ?>
          <?php foreach ($pending as $req): ?>
            <div class="approval-card mb-3">
              <div class="infos">
                <div class="image">
                  <?php if (!empty($req['verification_photo'])): ?>
                    <img src="<?= e($req['verification_photo']) ?>" alt="Verification photo">
                  <?php elseif (!empty($req['book_cover'])): ?>
                    <img src="<?= e($req['book_cover']) ?>" alt="Book cover">
                  <?php else: ?>
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700">No Photo</div>
                  <?php endif; ?>
                </div>
                <div class="info">
                  <div>
                    <p class="name"><?= e($req['title']) ?></p>
                    <p class="function">by <?= e($req['author']) ?> | Barcode: <?= e($req['book_barcode']) ?></p>
                  </div>
                  <div class="details" style="font-size:0.95rem;color:rgba(156,163,175,1);margin-top:6px;">
                    <p style="margin:0"><strong>Student:</strong> <?= e($req['firstname'] . ' ' . $req['lastname']) ?></p>
                    <p style="margin:0"><strong>ID:</strong> <?= e($req['student_barcode']) ?></p>
                    <?php if (!empty($req['course'])): ?>
                      <p style="margin:0"><strong>Course:</strong> <?= e($req['course']) ?> - Year <?= e($req['year_level']) ?></p>
                    <?php endif; ?>
                    <p style="margin:0"><strong>Due Date:</strong> <?= e(date('M d, Y', strtotime($req['due_date']))) ?></p>
                    <small class="text-muted">Requested: <?= e(date('M d, Y g:i A', strtotime($req['requested_at']))) ?></small>
                  </div>
                </div>
              </div>
              <div class="approval-actions">
                <form method="POST" class="flex-fill" onsubmit="return confirm('Approve this borrow request?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="approve">
                  <input type="hidden" name="borrowing_id" value="<?= (int) $req['id'] ?>">
                  <button type="submit" class="request">Accept</button>
                </form>
                <form method="POST" class="flex-fill" onsubmit="return confirm('Reject this borrow request?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="reject">
                  <input type="hidden" name="borrowing_id" value="<?= (int) $req['id'] ?>">
                  <button type="submit" class="request">Reject</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
        </div>
        </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
