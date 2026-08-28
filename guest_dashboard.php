<?php
require_once __DIR__ . '/includes/visitor_portal.php';
$pdo = db(); $visitor = current_visitor($pdo); $id = (int) $visitor['id'];
$pdo->prepare('UPDATE visitors SET last_login_at = NOW() WHERE id = ?')->execute([$id]);
$summary = $pdo->prepare('SELECT SUM(return_date IS NULL) active, SUM(return_date IS NOT NULL) returned, SUM(return_date IS NULL AND due_date < CURDATE()) overdue, COUNT(*) total FROM visitor_borrowing WHERE visitor_id=?'); $summary->execute([$id]); $stats = $summary->fetch() ?: [];
$activity = $pdo->prepare('SELECT b.title, b.category_name, vb.borrow_date FROM visitor_borrowing vb JOIN books b ON b.id=vb.book_id WHERE vb.visitor_id=? ORDER BY vb.borrow_date DESC LIMIT 1'); $activity->execute([$id]); $recentBook=$activity->fetch();
$category = $pdo->prepare('SELECT b.category_name, COUNT(*) c FROM visitor_borrowing vb JOIN books b ON b.id=vb.book_id WHERE vb.visitor_id=? GROUP BY b.category_name ORDER BY c DESC LIMIT 1'); $category->execute([$id]); $favorite=$category->fetch();
$visits=$pdo->prepare('SELECT time_in,time_out FROM visitor_visit_history WHERE visitor_id=? ORDER BY time_in DESC LIMIT 5');$visits->execute([$id]);$visits=$visits->fetchAll();
$logs=$pdo->prepare('SELECT activity,details,created_at FROM visitor_security_logs WHERE visitor_id=? ORDER BY created_at DESC LIMIT 5');$logs->execute([$id]);$logs=$logs->fetchAll();
$days = $visitor['registration_expires_at'] ? max(0, (int) floor((strtotime($visitor['registration_expires_at']) - strtotime('today')) / 86400)) : 0;
if ($visitor['registration_expires_at'] && strtotime($visitor['registration_expires_at']) < strtotime('today')) { $visitor['account_status']='Expired'; $pdo->prepare('UPDATE visitors SET account_status="Expired" WHERE id=?')->execute([$id]); }
elseif (($stats['active'] ?? 0) > 0 && $visitor['account_status'] === 'Active') { $visitor['account_status']='Borrowing'; }
visitor_portal_header('Guest Dashboard', $visitor);
?>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h2 class="mb-1">Guest Dashboard</h2><p class="text-muted mb-0">Your verified visitor account</p></div><div class="d-flex gap-2"><button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#borrowModal">Borrow a Book</button><button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#returnModal">Return a Book</button></div></div>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><div class="row align-items-center"><div class="col-md-auto text-center mb-3 mb-md-0"><?php if ($visitor['photo']): ?><img src="<?= e($visitor['photo']) ?>" class="rounded-circle" style="width:110px;height:110px;object-fit:cover" alt="Visitor photo"><?php endif; ?></div><div class="col"><h3><?= e(full_name($visitor)) ?></h3><div class="text-muted">Visitor No. <?= e($visitor['visitor_number'] ?: 'Pending') ?> · Registered <?= e(date('M d, Y', strtotime($visitor['created_at']))) ?></div><div class="mt-2"><span class="badge bg-success">Verified</span> <span class="badge <?= $visitor['account_status'] === 'Expired' ? 'bg-danger' : 'bg-primary' ?>"><?= e($visitor['account_status']) ?></span></div></div><div class="col-md-auto text-md-end"><div class="small text-muted">Registration valid until</div><strong><?= e($visitor['registration_expires_at'] ?: '—') ?></strong><div class="<?= $days <= 30 ? 'text-danger' : 'text-success' ?> small"><?= $days ?> day(s) remaining</div></div></div></div></div>
<div class="row g-3 mb-4"><?php foreach ([['Currently Borrowed',$stats['active'] ?? 0,'primary'],['Total Returned',$stats['returned'] ?? 0,'success'],['Overdue Books',$stats['overdue'] ?? 0,'danger'],['Days Remaining',$days,'warning']] as [$label,$value,$color]): ?><div class="col-sm-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small"><?= e($label) ?></div><div class="display-6 text-<?= e($color) ?> fw-bold"><?= (int)$value ?></div></div></div></div><?php endforeach; ?></div>
<div class="row g-3"><div class="col-lg-6"><div class="card h-100"><div class="card-body"><h5>Reading Activity</h5><p class="mb-2"><strong>Total borrowed:</strong> <?= (int)($stats['total'] ?? 0) ?></p><p class="mb-2"><strong>Favorite category:</strong> <?= e($favorite['category_name'] ?? 'No activity yet') ?></p><p class="mb-0"><strong>Most recently borrowed:</strong> <?= e($recentBook['title'] ?? 'No activity yet') ?></p></div></div></div><div class="col-lg-6"><div class="card h-100"><div class="card-body"><h5>Recent Visit History</h5><?php if (!$visits): ?><p class="text-muted mb-0">No visit records yet.</p><?php else: ?><ul class="list-group list-group-flush"><?php foreach($visits as $visit): ?><li class="list-group-item px-0"><strong><?= e(date('M d, Y g:i A', strtotime($visit['time_in']))) ?></strong><span class="text-muted"> · Out: <?= e($visit['time_out'] ? date('g:i A',strtotime($visit['time_out'])) : 'Currently in library') ?></span></li><?php endforeach; ?></ul><?php endif; ?></div></div></div><div class="col-12"><div class="card"><div class="card-body"><h5>Security Log</h5><?php if(!$logs): ?><p class="text-muted mb-0">No account activity yet.</p><?php else: ?><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Activity</th><th>Details</th><th>When</th></tr></thead><tbody><?php foreach($logs as $log): ?><tr><td><?= e(ucwords(str_replace('_',' ',$log['activity']))) ?></td><td><?= e($log['details']) ?></td><td><?= e(date('M d, Y g:i A',strtotime($log['created_at']))) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div></div></div>
<div class="modal fade" id="borrowModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="get" action="guest_borrow_request.php">
        <div class="modal-header text-white" style="background:var(--primary);">
          <h5 class="modal-title">&#128229; Borrow a Book</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label class="form-label fw-semibold">Scan or Enter Book Barcode</label>
          <div class="input-group mb-3">
            <input type="text" name="book_barcode" id="guest_borrow_barcode" class="form-control" placeholder="Scan barcode or type here..." autofocus required>
            <button type="button" class="btn btn-outline-secondary" data-scan-target="guest_borrow_barcode">Scan</button>
          </div>
          <p class="small text-muted mt-2">You will then capture a live verification photo holding the book.</p>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success fw-semibold">Continue</button>
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
        </div>
      </form>
    </div>
  </div>
</div>
<div class="modal fade" id="returnModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="guest_return_book.php">
        <?= csrf_field() ?>
        <div class="modal-header text-white" style="background:var(--primary);">
          <h5 class="modal-title">&#128228; Return a Book</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label class="form-label fw-semibold">Scan or Enter Book Barcode</label>
          <div class="input-group mb-3">
            <input type="text" name="book_barcode" id="guest_return_barcode" class="form-control" placeholder="Scan barcode or type here..." required>
            <button type="button" class="btn btn-outline-secondary" data-scan-target="guest_return_barcode">Scan</button>
          </div>
          <p class="small text-muted mt-2">You will capture a live return verification photo on the next screen.</p>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success fw-semibold">Continue</button>
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script src="assets/js/scanner.js" defer></script>
<?php visitor_portal_footer(); ?>
