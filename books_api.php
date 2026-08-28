<?php
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_staff()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized.']);
    exit();
}

$pdo    = db();
ensure_cover_file_column($pdo);
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

function respond(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit();
}

function api_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        respond(['ok' => false, 'message' => 'Invalid or expired session token. Refresh the page.'], 419);
    }
}

function ensure_cover_file_column(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    try {
        $pdo->query('SELECT cover_file FROM books LIMIT 1');
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            $pdo->exec('ALTER TABLE books ADD COLUMN cover_file VARCHAR(255) DEFAULT NULL');
        } else {
            throw $e;
        }
    }
    $checked = true;
}

function book_input(): array
{
    return [
        'barcode'  => trim($_POST['barcode'] ?? ''),
        'accession_no' => trim($_POST['accession_no'] ?? ''),
        'isbn'     => trim($_POST['isbn'] ?? ''),
        'title'     => trim($_POST['title'] ?? ''),
        'author'    => trim($_POST['author'] ?? ''),
        'publisher' => trim($_POST['publisher'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'cover_file_path' => trim($_POST['cover_file'] ?? ''),
        'cover_file' => $_FILES['cover_file'] ?? null,
        'category_name'  => trim($_POST['category_name'] ?? ''),
        'floor'    => trim($_POST['floor_no'] ?? ''),
        'section'  => trim($_POST['section_name'] ?? ''),
        'shelf'    => trim($_POST['shelf_no'] ?? ''),
        'row'      => trim($_POST['row_no'] ?? ''),
        'due_date'    => trim($_POST['due_date'] ?? ''),
        'return_date' => trim($_POST['return_date'] ?? ''),
        'status'   => $_POST['status'] ?? 'Available',
        'keywords' => array_values(array_filter(array_map('trim', explode(',', $_POST['keywords'] ?? '')), fn($v) => $v !== '')),
    ];
}

function id_list(): array
{
    $ids = $_POST['ids'] ?? ($_POST['id'] ?? []);
    if (!is_array($ids)) {
        $ids = explode(',', (string) $ids);
    }
    $ids = array_values(array_filter(array_map('intval', $ids)));
    return $ids;
}

switch ($action) {

    case 'list':
        $search   = trim($_GET['search'] ?? '');
        $status   = $_GET['status'] ?? '';
        $archived = ($_GET['archived'] ?? '0') === '1';
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $perPage  = min(50, max(5, (int) ($_GET['per_page'] ?? 10)));
        $offset   = ($page - 1) * $perPage;

        $sortable = ['title', 'author', 'publisher', 'category_name', 'status', 'barcode', 'accession_no', 'created_at'];
        $sort = in_array($_GET['sort'] ?? '', $sortable, true) ? $_GET['sort'] : 'created_at';
        $dir  = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $where  = [$archived ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL'];
        $params = [];
        if ($search !== '') {
            $where[] = '(b.barcode LIKE :q OR b.accession_no LIKE :q OR b.title LIKE :q OR b.author LIKE :q OR b.category_name LIKE :q OR k.name LIKE :q)';
            $params[':q'] = "%$search%";
        }
        if (in_array($status, ['Available', 'Borrowed', 'Reserved'], true)) {
            $where[] = 'status = :st';
            $params[':st'] = $status;
        }
        $joinSql = $search !== '' ? "LEFT JOIN book_keywords bk ON bk.book_id = b.id LEFT JOIN keywords k ON k.id = bk.keyword_id" : "";
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM books b $joinSql $whereSql");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT DISTINCT b.* FROM books b $joinSql $whereSql ORDER BY $sort $dir LIMIT :lim OFFSET :off");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        respond([
            'ok'       => true,
            'data'     => $stmt->fetchAll(),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => max(1, (int) ceil($total / $perPage)),
        ]);
        break;

    case 'create':
        if (!$isPost) respond(['ok' => false, 'message' => 'POST required.'], 405);
        api_csrf();
        $d = book_input();

        if ($d['barcode'] === '' || $d['title'] === '') {
            respond(['ok' => false, 'message' => 'Barcode and title are required.'], 422);
        }
        $dup = $pdo->prepare('SELECT id FROM books WHERE barcode = ? LIMIT 1');
        $dup->execute([$d['barcode']]);
        if ($dup->fetch()) {
            respond(['ok' => false, 'message' => 'A book with this barcode already exists.'], 422);
        }
        if ($d['accession_no'] !== '') {
            $dupAcc = $pdo->prepare('SELECT id FROM books WHERE accession_no = ? LIMIT 1');
            $dupAcc->execute([$d['accession_no']]);
            if ($dupAcc->fetch()) {
                respond(['ok' => false, 'message' => 'A book with this accession number already exists.'], 422);
            }
        }

        $coverValue = '';
        $coverErr = '';
        if ($d['cover_file'] && (($d['cover_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK)) {
            $path = save_uploaded_photo($d['cover_file'], $d['barcode']);
            if ($path === null) {
                $coverErr = 'Please choose a valid image file (JPG, PNG, WEBP or GIF, max 4 MB).';
            } else {
                $coverValue = $path;
            }
        } elseif ($d['cover_file_path'] !== '') {
            $coverValue = $d['cover_file_path'];
        }
        if ($coverErr !== '') {
            respond(['ok' => false, 'message' => $coverErr], 422);
        }

        try {
            $stmt = $pdo->prepare('
                INSERT INTO books (barcode, accession_no, isbn, title, author, publisher, description, cover_file, category_name, floor_no, section_name, shelf_no, row_no, due_date, return_date, status)
                VALUES (:barcode, :accession_no, :isbn, :title, :author, :publisher, :description, :cover_file, :category_name, :floor, :section, :shelf, :row, :due_date, :return_date, :status)
            ');
            $stmt->execute([
                ':barcode' => $d['barcode'], ':accession_no' => $d['accession_no'] !== '' ? $d['accession_no'] : null, ':isbn' => $d['isbn'], ':title' => $d['title'],
                ':author' => $d['author'], ':publisher' => $d['publisher'], ':description' => $d['description'], ':cover_file' => $coverValue, ':category_name' => $d['category_name'], ':floor' => $d['floor'],
                ':section' => $d['section'], ':shelf' => $d['shelf'], ':row' => $d['row'],
                ':due_date' => $d['due_date'] !== '' ? $d['due_date'] : null,
                ':return_date' => $d['return_date'] !== '' ? $d['return_date'] : null,
                ':status' => in_array($d['status'], ['Available', 'Borrowed', 'Reserved'], true) ? $d['status'] : 'Available',
            ]);
            $bookId = (int) $pdo->lastInsertId();
            set_book_keywords($pdo, $bookId, $d['keywords']);
            audit_log($pdo, (int)$_SESSION['user_id'] ?? null, 'book_create', "Book ID: $bookId, Title: " . $d['title']);
            respond(['ok' => true, 'message' => 'Book added successfully.']);
        } catch (PDOException $e) {
            respond(['ok' => false, 'message' => 'Unable to save book: ' . $e->getMessage()], 500);
        }
        break;

    case 'update':
        if (!$isPost) respond(['ok' => false, 'message' => 'POST required.'], 405);
        api_csrf();
        $id = (int) ($_POST['id'] ?? 0);
        $d  = book_input();

        if ($id <= 0) respond(['ok' => false, 'message' => 'Invalid book id.'], 422);
        if ($d['barcode'] === '' || $d['title'] === '') {
            respond(['ok' => false, 'message' => 'Barcode and title are required.'], 422);
        }
        $dup = $pdo->prepare('SELECT id FROM books WHERE barcode = ? AND id <> ? LIMIT 1');
        $dup->execute([$d['barcode'], $id]);
        if ($dup->fetch()) {
            respond(['ok' => false, 'message' => 'Another book already uses this barcode.'], 422);
        }

        $coverValue = '';
        $coverErr = '';
        if ($d['cover_file'] && (($d['cover_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK)) {
            $path = save_uploaded_photo($d['cover_file'], $d['barcode']);
            if ($path === null) {
                $coverErr = 'Please choose a valid image file (JPG, PNG, WEBP or GIF, max 4 MB).';
            } else {
                $coverValue = $path;
            }
        } elseif ($d['cover_file_path'] !== '') {
            $coverValue = $d['cover_file_path'];
        }
        if ($coverErr !== '') {
            respond(['ok' => false, 'message' => $coverErr], 422);
        }

        try {
            $stmt = $pdo->prepare('
                UPDATE books SET barcode=:barcode, isbn=:isbn, title=:title, author=:author,
                    publisher=:publisher, description=:description, cover_file=:cover_file, category_name=:category_name, floor_no=:floor, section_name=:section, shelf_no=:shelf,
                    row_no=:row, due_date=:due_date, return_date=:return_date, status=:status
                WHERE id=:id
            ');
            $stmt->execute([
                ':barcode' => $d['barcode'], ':isbn' => $d['isbn'], ':title' => $d['title'],
                ':author' => $d['author'], ':publisher' => $d['publisher'], ':description' => $d['description'], ':cover_file' => $coverValue, ':category_name' => $d['category_name'], ':floor' => $d['floor'],
                ':section' => $d['section'], ':shelf' => $d['shelf'], ':row' => $d['row'],
                ':due_date' => $d['due_date'] !== '' ? $d['due_date'] : null,
                ':return_date' => $d['return_date'] !== '' ? $d['return_date'] : null,
                ':status' => in_array($d['status'], ['Available', 'Borrowed', 'Reserved'], true) ? $d['status'] : 'Available',
                ':id' => $id,
            ]);
            set_book_keywords($pdo, $id, $d['keywords']);
            audit_log($pdo, (int)$_SESSION['user_id'] ?? null, 'book_update', "Book ID: $id, Title: " . $d['title']);
            respond(['ok' => true, 'message' => 'Book updated successfully.']);
        } catch (PDOException $e) {
            respond(['ok' => false, 'message' => 'Unable to save book: ' . $e->getMessage()], 500);
        }
        break;

    case 'archive':
        if (!$isPost) respond(['ok' => false, 'message' => 'POST required.'], 405);
        api_csrf();
        $ids = id_list();
        if (!$ids) respond(['ok' => false, 'message' => 'No books selected.'], 422);

        $in = implode(',', array_fill(0, count($ids), '?'));
        $busy = $pdo->prepare("SELECT COUNT(*) FROM borrowing WHERE return_date IS NULL AND book_id IN ($in)");
        $busy->execute($ids);
        if ((int) $busy->fetchColumn() > 0) {
            respond(['ok' => false, 'message' => 'Cannot archive: one or more selected books have active loans.'], 409);
        }
        $pdo->prepare("UPDATE books SET deleted_at = NOW() WHERE id IN ($in)")->execute($ids);
        audit_log($pdo, (int)$_SESSION['user_id'] ?? null, 'book_archive', "IDs: " . implode(',', $ids));
        respond(['ok' => true, 'message' => count($ids) . ' book(s) archived.']);
        break;

    case 'restore':
        if (!$isPost) respond(['ok' => false, 'message' => 'POST required.'], 405);
        api_csrf();
        $ids = id_list();
        if (!$ids) respond(['ok' => false, 'message' => 'No books selected.'], 422);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE books SET deleted_at = NULL WHERE id IN ($in)")->execute($ids);
        audit_log($pdo, (int)$_SESSION['user_id'] ?? null, 'book_restore', "IDs: " . implode(',', $ids));
        respond(['ok' => true, 'message' => count($ids) . ' book(s) restored.']);
        break;

    case 'delete':
        if (!$isPost) respond(['ok' => false, 'message' => 'POST required.'], 405);
        api_csrf();
        $ids = id_list();
        if (!$ids) respond(['ok' => false, 'message' => 'No books selected.'], 422);
        $in = implode(',', array_fill(0, count($ids), '?'));

        $busy = $pdo->prepare("SELECT COUNT(*) FROM borrowing WHERE return_date IS NULL AND book_id IN ($in)");
        $busy->execute($ids);
        if ((int) $busy->fetchColumn() > 0) {
            respond(['ok' => false, 'message' => 'Cannot delete: one or more selected books have active loans.'], 409);
        }
        $pdo->prepare("DELETE FROM books WHERE id IN ($in)")->execute($ids);
        audit_log($pdo, (int)$_SESSION['user_id'] ?? null, 'book_delete', "IDs: " . implode(',', $ids));
        respond(['ok' => true, 'message' => count($ids) . ' book(s) permanently deleted.']);
        break;

    default:
        respond(['ok' => false, 'message' => 'Unknown action.'], 400);
}
