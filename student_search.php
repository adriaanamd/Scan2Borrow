<?php
require_once __DIR__ . '/includes/auth.php';
require_borrower();

function build_cover_url(string $coverValue, array $server): string
{
    $value = trim($coverValue);
    if ($value === '') {
        return '';
    }

    $normalized = str_replace('\\', '/', $value);
    if (preg_match('#^(https?:)?//|^data:image/#i', $normalized)) {
        return $value;
    }

    $basePath = rtrim(str_replace('\\', '/', dirname($server['SCRIPT_NAME'] ?? '/')), '/');
    $baseRoot = $basePath !== '' && $basePath !== '/' ? $basePath : '';

    if (preg_match('#(?:^|/)(uploads/.+)$#i', $normalized, $m)) {
        $publicPath = '/' . ltrim($m[1], '/');
    } elseif (preg_match('#(?:^|/)(assets/.+)$#i', $normalized, $m)) {
        $publicPath = '/' . ltrim($m[1], '/');
    } elseif (preg_match('#^[A-Za-z]:/#', $normalized)) {
        $publicPath = '/' . ltrim($normalized, '/');
    } else {
        $publicPath = '/' . ltrim($normalized, '/');
    }

    $scheme = (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $server['HTTP_HOST'] ?? 'localhost';

    return $scheme . $host . $baseRoot . $publicPath;
}

$pdo = db();
$uid = is_logged_in() ? (int) $_SESSION['user_id'] : null;

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$category_name = trim($_GET['category_name'] ?? '');
$status = trim($_GET['status'] ?? '');
$sort = trim($_GET['sort'] ?? 'title');
$floor = trim($_GET['floor'] ?? '');

// Log search history for recommendations
if ($search !== '' && is_logged_in()) {
	log_search($pdo, $uid, $search);
}

// Get available categories for filter dropdown
$categoryStmt = $pdo->query("
	SELECT DISTINCT category_name FROM books 
	WHERE deleted_at IS NULL AND category_name IS NOT NULL AND category_name != ''
	ORDER BY category_name ASC
");
$categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);

// Get available floors for filter dropdown
$floorStmt = $pdo->query("
	SELECT DISTINCT floor_no FROM books 
	WHERE deleted_at IS NULL AND floor_no IS NOT NULL
	ORDER BY floor_no ASC
");
$floors = $floorStmt->fetchAll(PDO::FETCH_COLUMN);

// Get user's currently borrowed books
$myBooks = [];
if ($uid) {
	$stmt = $pdo->prepare("
		SELECT book_id FROM borrowing 
		WHERE user_id = ? AND return_date IS NULL
	");
	$stmt->execute([$uid]);
	$myBooks = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Build search query
$books = [];
$totalBooks = 0;
$whereConditions = ['b.deleted_at IS NULL'];
$params = [];

// Search text
if ($search !== '') {
	$whereConditions[] = "(
		b.title LIKE ? OR b.author LIKE ? OR b.category_name LIKE ? 
		OR b.barcode LIKE ? OR k.name LIKE ?
	)";
	$like = "%$search%";
	$params = array_merge($params, [$like, $like, $like, $like, $like]);
}

// Category filter
if ($category_name !== '') {
	$whereConditions[] = "b.category_name = ?";
	$params[] = $category_name;
}

// Status filter
if ($status !== '') {
	$whereConditions[] = "b.status = ?";
	$params[] = $status;
}

// Floor filter
if ($floor !== '') {
	$whereConditions[] = "b.floor_no = ?";
	$params[] = (int) $floor;
}

$whereClause = implode(' AND ', $whereConditions);

// Determine sort order
$orderClause = 'b.title ASC';
if ($sort === 'author') {
	$orderClause = 'b.author ASC, b.title ASC';
} elseif ($sort === 'category_name') {
	$orderClause = 'b.category_name ASC, b.title ASC';
} elseif ($sort === 'status') {
	$orderClause = 'b.status ASC, b.title ASC';
} elseif ($sort === 'newest') {
	$orderClause = 'b.created_at DESC, b.title ASC';
}

// Fetch books
if ($search !== '' || $category_name !== '' || $status !== '' || $floor !== '') {
	$stmt = $pdo->prepare("
		SELECT DISTINCT b.*
		FROM books b
		LEFT JOIN book_keywords bk ON bk.book_id = b.id
		LEFT JOIN keywords k ON k.id = bk.keyword_id
		WHERE $whereClause
		ORDER BY $orderClause
		LIMIT 200
	");
	$stmt->execute($params);
	$books = $stmt->fetchAll();
	$totalBooks = count($books);
} else {
	// Show trending books (most borrowed in last 30 days)
	$stmt = $pdo->query("
		SELECT DISTINCT b.*, COUNT(br.id) AS borrow_count
		FROM books b
		LEFT JOIN borrowing br ON b.id = br.book_id AND br.borrow_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
		WHERE b.deleted_at IS NULL
		GROUP BY b.id
		ORDER BY borrow_count DESC, b.title ASC
		LIMIT 50
	");
	$books = $stmt->fetchAll();
	$totalBooks = count($books);
}

$pageTitle = 'Search Books';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
	<div>
		<h3 class="mb-1">Book Catalog</h3>
		<p class="text-muted mb-0">Search and discover available books.</p>
	</div>
</div>

<!-- Search & Filter Section -->
<div class="soft-card mb-4">
	<form method="GET" id="searchForm">
		<!-- Main Search Bar -->
		<div class="mb-3">
			<div class="input-group input-group-lg">
				<span class="input-group-text" style="background:none;border-right:none;"><strong>🔍</strong></span>
				<input type="text" name="search" class="form-control" placeholder="Search by title, author, or keyword..." 
					   value="<?= e($search) ?>" autofocus>
				<button type="submit" class="btn btn-primary fw-semibold">Search</button>
			</div>
		</div>

		<!-- Advanced Filters -->
		<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
			<!-- Category Filter -->
			<div style="flex:1;min-width:150px;">
				<label class="form-label small fw-semibold">Category</label>
				<select name="category_name" class="form-select form-select-sm" onchange="document.getElementById('searchForm').submit()">
					<option value="">All Categories</option>
					<?php foreach ($categories as $cat): ?>
						<option value="<?= e($cat) ?>" <?= $category_name === $cat ? 'selected' : '' ?>>
							<?= e($cat) ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<!-- Status Filter -->
			<div style="flex:1;min-width:150px;">
				<label class="form-label small fw-semibold">Availability</label>
				<select name="status" class="form-select form-select-sm" onchange="document.getElementById('searchForm').submit()">
					<option value="">All Status</option>
					<option value="Available" <?= $status === 'Available' ? 'selected' : '' ?>>Available</option>
					<option value="Borrowed" <?= $status === 'Borrowed' ? 'selected' : '' ?>>Borrowed</option>
					<option value="Overdue" <?= $status === 'Overdue' ? 'selected' : '' ?>>Overdue</option>
				</select>
			</div>

			<!-- Floor Filter -->
			<div style="flex:1;min-width:150px;">
				<label class="form-label small fw-semibold">Floor</label>
				<select name="floor" class="form-select form-select-sm" onchange="document.getElementById('searchForm').submit()">
					<option value="">All Floors</option>
					<?php foreach ($floors as $f): ?>
						<option value="<?= e($f) ?>" <?= $floor === (string)$f ? 'selected' : '' ?>>
							Floor <?= e($f) ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<!-- Sort Options -->
			<div style="flex:1;min-width:150px;">
				<label class="form-label small fw-semibold">Sort By</label>
				<select name="sort" class="form-select form-select-sm" onchange="document.getElementById('searchForm').submit()">
					<option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Title A-Z</option>
					<option value="author" <?= $sort === 'author' ? 'selected' : '' ?>>Author A-Z</option>
					<option value="category_name" <?= $sort === 'category_name' ? 'selected' : '' ?>>Category</option>
					<option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Status</option>
					<option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Recently Added</option>
				</select>
			</div>

			<!-- Clear Filters -->
			<?php if ($search !== '' || $category_name !== '' || $status !== '' || $floor !== ''): ?>
				<a href="student_search.php" class="btn btn-outline-secondary btn-sm" style="align-self:flex-end;">
					✕ Clear Filters
				</a>
			<?php endif; ?>
		</div>

		<!-- Active Filters Display -->
		<?php if ($search !== '' || $category_name !== '' || $status !== '' || $floor !== ''): ?>
			<div style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;">
				<?php if ($search !== ''): ?>
					<span class="badge bg-primary">🔍 <?= e($search) ?></span>
				<?php endif; ?>
				<?php if ($category_name !== ''): ?>
					<span class="badge bg-info">📚 <?= e($category_name) ?></span>
				<?php endif; ?>
				<?php if ($status !== ''): ?>
					<span class="badge bg-secondary"><?= e($status) ?></span>
				<?php endif; ?>
				<?php if ($floor !== ''): ?>
					<span class="badge bg-warning text-dark">🏢 Floor <?= e($floor) ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</form>
</div>

<!-- Results Section -->
<div class="soft-card">
	<div class="d-flex justify-content-between align-items-center mb-3" style="border-bottom:1px solid #e9ecef;padding-bottom:12px;">
		<div>
			<strong><?= $totalBooks ?></strong> 
			<span class="text-muted">book<?= $totalBooks != 1 ? 's' : '' ?> found</span>
			<?php if ($search === '' && $category_name === '' && $status === '' && $floor === ''): ?>
				<br><small class="text-muted">📊 Showing trending books</small>
			<?php endif; ?>
		</div>
		<?php if ($totalBooks > 0): ?>
			<div class="text-muted small">
				<?php if ($status === 'Available'): ?>
					<span class="badge bg-success">✓ Available to Borrow</span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<?php if (!$books): ?>
		<div class="text-center py-5">
			<div style="font-size:48px;margin-bottom:12px;">📭</div>
			<strong>No books found</strong>
			<p class="text-muted small">Try adjusting your search or filters.</p>
		</div>
	<?php else: ?>
		<div class="row g-4">
			<?php foreach ($books as $b): ?>
				<?php $isAlreadyBorrowed = in_array($b['id'], $myBooks); ?>
				<?php $coverImage = trim((string) ($b['cover_file'] ?? $b['cover_image'] ?? '')); ?>
				<?php $coverUrl = build_cover_url($coverImage, $_SERVER); ?>
				<div class="col-xl-4 col-lg-6 col-md-6">
					<div class="book-card-shell">
						<div class="book-card">
							<div class="book-face book-face-front">
								<div class="book-cover<?= $coverUrl === '' ? ' book-cover-fallback' : '' ?>">
									<?php if ($coverUrl !== ''): ?>
										<img src="<?= e($coverUrl) ?>" alt="<?= e($b['title']) ?>" class="book-cover-img" onerror="this.style.display='none'; this.closest('.book-cover').classList.add('book-cover-fallback');">
									<?php endif; ?>
									<div class="book-cover-content">
										<span class="badge bg-light text-dark mb-3"><?= e($b['category_name'] ?: 'Library') ?></span>
										<h4 class="fw-bold text-white mb-2"><?= e($b['title']) ?></h4>
										<p class="text-white-50 small mb-0"><?= e($b['author'] ?: 'Unknown Author') ?></p>
									</div>
								</div>
							</div>
							<div class="book-face book-face-back">
								<div class="book-back-content">
									<div class="d-flex justify-content-between align-items-start mb-3">
										<div>
											<h5 class="fw-bold mb-1"><?= e($b['title']) ?></h5>
											<p class="text-muted small mb-0"><?= e($b['author'] ?: 'Unknown Author') ?></p>
										</div>
										<?= status_badge($b['status']) ?>
									</div>
									<p class="text-muted small mb-3"><?= e($b['description'] ?? 'No description available') ?></p>
									<div class="small text-muted mb-3">
										<div><strong>Publisher:</strong> <?= e($b['publisher'] ?? 'N/A') ?></div>
										<div><strong>Location:</strong> Floor <?= e($b['floor_no']) ?> · Shelf <?= e($b['shelf_no']) ?> · Row <?= e($b['row_no']) ?></div>
									</div>
									<?php if ($isAlreadyBorrowed): ?>
										<span class="badge bg-info w-100 py-2">📖 You have this</span>
									<?php elseif ($b['status'] === 'Available'): ?>
										<button type="button" class="btn btn-primary w-100" data-bs-toggle="modal"
												data-bs-target="#borrowModal" data-book-barcode="<?= e($b['barcode']) ?>"
												data-book-title="<?= e($b['title']) ?>" title="Borrow this book">
											Borrow Book
										</button>
									<?php else: ?>
										<button class="btn btn-outline-secondary w-100" disabled>Unavailable</button>
									<?php endif; ?>
								</div>
							</div>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>


<!-- Borrow Modal -->
<div class="modal fade" id="borrowModal" tabindex="-1">
  <div class="modal-dialog">
	<div class="modal-content">
	  <form method="POST" id="borrowFormModal" <?= current_role() === 'teacher' ? 'action="teachersboard.php"' : 'action="studhome.php"' ?>>
		<?= csrf_field() ?>
		<input type="hidden" name="book_barcode" id="modal-book-barcode">
		<input type="hidden" name="borrow" value="1">
		<?php if (current_role() === 'teacher'): ?>
			<input type="hidden" name="from_search" value="1">
		<?php endif; ?>
		
		<div class="modal-header text-white" style="background:var(--primary);">
		  <h5 class="modal-title">Borrow Book</h5>
		  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
		</div>
		<div class="modal-body">
		  <div class="mb-3">
			<label class="form-label fw-semibold">Book Title</label>
			<p id="modal-book-title" style="font-size:14px;color:#666;"></p>
		  </div>

		  <?php if (current_role() === 'teacher'): ?>
			  <label class="form-label fw-semibold">Preferred Return Date</label>
			  <input type="date" name="due_date" class="form-control mb-2">
			  <div class="form-text small text-muted">Teachers can borrow for up to <?= TEACHER_MAX_DAYS ?> days.</div>
		  <?php endif; ?>
		</div>
		<div class="modal-footer">
		  <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
		  <button type="submit" class="btn btn-primary fw-semibold">Borrow Now</button>
		</div>
	  </form>
	</div>
  </div>
</div>

<style>
.book-card-shell {
	perspective: 1600px;
	min-height: 360px;
}
.book-card {
	position: relative;
	height: 360px;
	transform-style: preserve-3d;
	transition: transform .8s cubic-bezier(.2,.8,.2,1);
}
.book-card:hover {
	transform: rotateY(-18deg) rotateX(3deg);
}
.book-face {
	position: absolute;
	inset: 0;
	border-radius: 22px;
	overflow: hidden;
	backface-visibility: hidden;
	box-shadow: 0 18px 40px rgba(15,23,42,.18);
}
.book-face-front {
	transform: rotateY(0deg);
}
.book-face-back {
	transform: rotateY(180deg);
	background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 100%);
	padding: 1.15rem;
}
.book-card:hover .book-face-front {
	transform: rotateY(-180deg);
}
.book-card:hover .book-face-back {
	transform: rotateY(0deg);
}
.book-cover {
	position: relative;
	height: 100%;
	padding: 1.25rem;
	display: flex;
	align-items: flex-end;
	border: 1px solid rgba(255,255,255,.16);
	overflow: hidden;
	background: linear-gradient(135deg, #11223d 0%, #2d4a6d 45%, #7b5f2f 100%);
}
.book-cover-fallback {
	background: linear-gradient(135deg, #11223d 0%, #2d4a6d 45%, #7b5f2f 100%);
}
.book-cover-img {
	position: absolute;
	inset: 0;
	width: 100%;
	height: 100%;
	object-fit: cover;
	display: block;
	z-index: 0;
}
.book-cover::before {
	content: '';
	position: absolute;
	inset: 0;
	background: linear-gradient(180deg, rgba(255,255,255,0.04) 0%, rgba(0,0,0,.55) 100%);
	z-index: 1;
}
.book-cover-content {
	position: relative;
	z-index: 2;
}
.book-back-content {
	height: 100%;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
}
@media (max-width: 576px) {
	.book-card, .book-card-shell { height: 320px; }
}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
	// Borrow modal data binding
	const borrowModal = document.getElementById('borrowModal');
	borrowModal?.addEventListener('show.bs.modal', function(e) {
		const btn = e.relatedTarget;
		document.getElementById('modal-book-barcode').value = btn.dataset.bookBarcode;
		document.getElementById('modal-book-title').textContent = btn.dataset.bookTitle;
	});

	// Reset modal on close
	borrowModal?.addEventListener('hidden.bs.modal', function() {
		document.getElementById('borrowFormModal').reset();
	});
});
</script>


