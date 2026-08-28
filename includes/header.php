<?php
require_once __DIR__ . '/auth.php';

$pageTitle = $pageTitle ?? 'Scan2Borrow';
if (is_guest_logged_in()) {
    $navItems = [
        ['guest_dashboard.php',         'My Dashboard',       '&#127968;'],
        ['guest_profile.php',           'Settings',         '&#128100;'],
        ['guest_browse_books.php',      'Browse Books',       '&#128269;'],
        ['guest_borrowed_books.php',    'Borrowed Books',     '&#128218;'],
        ['guest_borrowing_history.php', 'Borrowing History',  '&#128220;'],
        ['guest_pass.php',              'Government ID',      '&#127903;'],
    ];
} elseif (is_staff()) {
    $navItems = [
        ['adboard.php',          'Dashboard',      '&#128202;'],
        ['managebooks.php',      'Book Inventory', '&#128218;'],
        ['adstud.php',           'Borrowers',      '&#128100;'],
        ['overdue.php',          'Overdue',        '&#9888;'],
        ['reports.php',          'Reports',        '&#128203;'],
        ['guest_borrow_requests.php', 'Guest Requests', '&#128203;'],
    ];
    if (is_admin()) {
        $navItems[] = ['staff.php', 'Staff', '&#128081;'];
    }
} elseif (current_role() === 'teacher') {
    $navItems = [
        ['teachersboard.php',    'My Dashboard',   '&#127968;'],
        ['student_search.php',   'Search Books',   '&#128269;'],
        ['student_history.php',  'My History',     '&#128220;'],
    ];
} else {
    $navItems = [
        ['settings.php',        'Settings',       '&#9881;'],
        ['studhome.php',         'My Dashboard',   '&#127968;'],
        ['student_search.php',   'Search Books',   '&#128269;'],
        ['student_history.php',  'My History',     '&#128220;'],
    ];
}

$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php if (isset($csrfToken)): ?>
<meta name="csrf" content="<?= e($csrfToken) ?>">
<?php endif; ?>
<title><?= e($pageTitle) ?> | Scan2Borrow</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
</head>
<body>
<div class="app">

    <aside class="sidebar">
        <div class="sidebar-brand">
            <span class="brand-mark">&#128218;</span>
            <span>Scan2Borrow</span>
        </div>
        <nav class="sidebar-nav">
            <?php foreach ($navItems as [$href, $label, $icon]): ?>
                <a href="<?= e($href) ?>" class="<?= $current === $href ? 'active' : '' ?>">
                    <span class="nav-icon"><?= $icon ?></span><?= e($label) ?>
                </a>
            <?php endforeach; ?>
            <a href="logout.php" class="nav-logout">
                <span class="nav-icon">&#9211;</span>Logout
            </a>
        </nav>
    </aside>

    <main class="main">
        <header class="topbar">
            <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
            <div class="topbar-user">
                <span class="user-role"><?= e(is_guest_logged_in() ? 'Guest / Visitor' : ucfirst(current_role() ?? '')) ?></span>
                <span class="user-name">
                    <?= e(trim((is_guest_logged_in() ? ($_SESSION['visitor_firstname'] ?? '') : ($_SESSION['firstname'] ?? '')) . ' ' . (is_guest_logged_in() ? ($_SESSION['visitor_lastname'] ?? '') : ($_SESSION['lastname'] ?? '')))) ?>
                </span>
            </div>
        </header>
        <div class="content">
