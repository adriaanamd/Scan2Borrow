<?php
require_once __DIR__ . '/auth.php';

function current_visitor(PDO $pdo): array
{
    require_guest_login();
    $stmt = $pdo->prepare('SELECT * FROM visitors WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['visitor_id']]);
    $visitor = $stmt->fetch();
    if (!$visitor) {
        $_SESSION = [];
        session_destroy();
        redirect('guest_registration.php');
    }

    // Backfill records created before visitor identity/validity fields were introduced.
    if (empty($visitor['visitor_number']) || empty($visitor['qr_token']) || empty($visitor['registration_expires_at'])) {
        $visitorNumber = $visitor['visitor_number'] ?: ('VIS-' . date('Y', strtotime($visitor['created_at'])) . '-' . str_pad((string) $visitor['id'], 6, '0', STR_PAD_LEFT));
        $qrToken = $visitor['qr_token'] ?: bin2hex(random_bytes(16));
        $expiresAt = $visitor['registration_expires_at'] ?: date('Y-m-d', strtotime($visitor['created_at'] . ' +1 year'));
        $pdo->prepare('UPDATE visitors SET visitor_number = ?, qr_token = ?, registration_expires_at = ? WHERE id = ?')
            ->execute([$visitorNumber, $qrToken, $expiresAt, (int) $visitor['id']]);
        $stmt->execute([(int) $_SESSION['visitor_id']]);
        $visitor = $stmt->fetch();
    }
    return $visitor;
}

function visitor_log(PDO $pdo, int $visitorId, string $activity, string $details = ''): void
{
    $pdo->prepare('INSERT INTO visitor_security_logs (visitor_id, activity, details) VALUES (?, ?, ?)')
        ->execute([$visitorId, $activity, $details ?: null]);
}

function visitor_portal_header(string $title, array $visitor): void
{
    $pageTitle = $title;
    require __DIR__ . '/header.php';
}

function visitor_portal_footer(): void
{
    require __DIR__ . '/footer.php';
}
