<?php
require_once __DIR__ . '/includes/auth.php';
require_staff();

$pageTitle = 'Book Inventory';
$csrfToken = csrf_token();
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div><p>Manage your library collection. Add, edit, archive and restore books without leaving the page.</p></div>
    <button id="btn-add" class="btn btn-accent">&#10133; Add New Book</button>
</div>

<div class="table-card">

    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="input-group" style="max-width:320px;">
            <span class="input-group-text bg-white">&#128269;</span>
            <input type="text" id="inv-search" class="form-control" placeholder="Live search title, author, barcode...">
        </div>
        <select id="inv-status" class="form-select" style="max-width:180px;">
            <option value="">All statuses</option>
            <option value="Available">Available</option>
            <option value="Borrowed">Borrowed</option>
            <option value="Reserved">Reserved</option>
        </select>
        <div class="form-check form-switch ms-1">
            <input class="form-check-input" type="checkbox" id="inv-view">
            <label class="form-check-label" for="inv-view">Show archived</label>
        </div>
        <span id="inv-count" class="text-muted ms-auto small"></span>
    </div>

    <div id="inv-bulkbar" class="alert alert-secondary align-items-center gap-2 py-2" style="display:none;">
        <strong><span id="inv-bulkcount">0</span> selected</strong>
        <button class="btn btn-warning btn-sm" data-bulk="archive">Archive selected</button>
        <button class="btn btn-success btn-sm" data-bulk="restore">Restore selected</button>
        <button class="btn btn-outline-danger btn-sm" data-bulk="delete">Delete selected</button>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th style="width:38px;"><input type="checkbox" id="inv-select-all" class="form-check-input"></th>
                    <th data-sort="barcode">Barcode<span class="sort-arrow"></span></th>
                    <th data-sort="title">Title<span class="sort-arrow"></span></th>
                    <th data-sort="author">Author<span class="sort-arrow"></span></th>
                    <th data-sort="acc_no">Accesion Number<span class="sort-arrow"></span></th>
                    <th data-sort="publisher">Publisher<span class="sort-arrow"></span></th>
                    <th data-sort="description">Description<span class="sort-arrow"></span></th>
                    <th data-sort="category_name">Category<span class="sort-arrow"></span></th>
                    <th data-sort="status"> Status <span class="sort-arrow"></span></th>
                    <th>Due / Return</th>
                    <th data-sort="location">Location</th>
                    <th data-sort="action">Action</th>
                </tr>
            </thead>
            <tbody id="inv-body">
                <tr><td colspan="11" class="text-center text-muted py-4">Loading...</td></tr>
            </tbody>
        </table>
    </div>

    <nav><ul class="pagination justify-content-center mb-0" id="inv-pager"></ul></nav>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="bookDrawer" style="width:550px;">
    <div class="offcanvas-header text-white" style="background:var(--primary);">
        <h5 class="offcanvas-title" id="drawer-title">Add New Book</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <form id="book-form" enctype="multipart/form-data">
            <input type="hidden" id="book-id" name="id">

            <label class="form-label">Book Barcode</label>
            <div class="input-group mb-3">
                <input type="text" name="barcode" id="barcode" class="form-control" placeholder="Scan or type barcode" required>
                <button type="button" class="btn btn-outline-secondary" data-scan-target="barcode">Scan</button>
            </div>

            <label class="form-label">ISBN</label>
            <input type="text" name="isbn" class="form-control mb-3">

            <label class="form-label">Title *</label>
            <input type="text" name="title" class="form-control mb-3" required>

            <label class="form-label">Author</label>
            <input type="text" name="author" class="form-control mb-3">

            <label class="form-label">Publisher</label>
            <input type="text" name="publisher" class="form-control mb-3">

            <label class="form-label">Description</label>
            <textarea name="description" class="form-control mb-3" rows="3" placeholder="Short summary of the book"></textarea>

            <label class="form-label">Book Cover Photo</label>
            <input type="file" name="cover_file" id="cover-file" class="form-control mb-3" accept="image/*">
            <div id="cover-preview-wrap" class="mb-3" style="display:none;">
                <img id="cover-preview" src="" alt="Book cover preview" class="img-fluid rounded border" style="max-height:180px;object-fit:cover;">
            </div>

            <label class="form-label">Category</label>
            <select name="category_name" class="form-select" required>
        <option value="">-- Select Category --</option>
        <option value="General Works">General Works</option>
        <option value="Philosophy">Philosophy</option>
        <option value="Religion">Religion</option>
        <option value="Social Sciences">Social Sciences</option>
        <option value="Language">Language</option>
        <option value="Science">Science</option>
        <option value="Technology">Technology</option>
        <option value="Arts and Recreation">Arts and Recreation</option>
        <option value="Literature">Literature</option>
        <option value="History and Geography">History and Geography</option>
        <option value="Computer Science">Computer Science</option>
        <option value="Information Technology">Information Technology</option>
        <option value="Programming">Programming</option>
        <option value="Engineering">Engineering</option>
        <option value="Mathematics">Mathematics</option>
        <option value="Business">Business</option>
        <option value="Accounting">Accounting</option>
        <option value="Education">Education</option>
        <option value="Psychology">Psychology</option>
        <option value="Research">Research</option>
        <option value="Reference">Reference</option>
        <option value="Fiction">Fiction</option>
        <option value="Non-Fiction">Non-Fiction</option>
        <option value="Children's Books">Children's Books</option>
        <option value="Thesis">Thesis</option>
        <option value="Journal">Journal</option>
        <option value="Magazine">Magazine</option>
    </select>

            <label class="form-label">Keywords</label>
            <input type="text" name="keywords" class="form-control mb-3" placeholder="e.g. cooking, pastry, C++ (comma-separated)">
            <small class="text-muted">Help students find this book by adding relevant keywords.</small>

            <label class="form-label">Status</label>
            <select name="status" class="form-select mb-3">
                <option value="Available">Available</option>
                <option value="Borrowed">Borrowed</option>
                <option value="Reserved">Reserved</option>
            </select>

            <div class="row g-2 mb-3">
                <div class="col-6"><label class="form-label">Due Date</label><input type="date" name="due_date" class="form-control"></div>
                <div class="col-6"><label class="form-label">Return Date</label><input type="date" name="return_date" class="form-control"></div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6"><label class="form-label">Floor</label><input type="text" name="floor_no" class="form-control"></div>
                <div class="col-6"><label class="form-label">Section</label><input type="text" name="section_name" class="form-control"></div>
                <div class="col-6"><label class="form-label">Shelf</label><input type="text" name="shelf_no" class="form-control"></div>
                <div class="col-6"><label class="form-label">Row</label><input type="text" name="row_no" class="form-control"></div>
            </div>

            <button type="submit" class="btn btn-accent w-100 py-2 fw-semibold">Save Book</button>
        </form>
    </div>
</div>

<div id="toast-host" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1090;"></div>

<script src="assets/js/scanner.js" defer></script>
<script src="assets/js/inventory.js" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
