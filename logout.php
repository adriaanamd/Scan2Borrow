<?php
require_once __DIR__ . '/includes/auth.php';
if (is_guest_logged_in()) {
    $visitorId = (int) $_SESSION['visitor_id'];
    try {
        $pdo = db();
        $pdo->prepare('UPDATE visitor_visit_history SET time_out = NOW() WHERE visitor_id = ? AND time_out IS NULL ORDER BY id DESC LIMIT 1')
            ->execute([$visitorId]);
        $pdo->prepare('INSERT INTO visitor_security_logs (visitor_id, activity, details) VALUES (?, ?, ?)')
            ->execute([$visitorId, 'logout', 'Visitor checked out of the portal.']);
    } catch (Throwable $e) {
        // Preserve logout behavior even if optional visitor audit tables are unavailable.
    }
}
$_SESSION = [];
session_destroy();
redirect('index.php');
