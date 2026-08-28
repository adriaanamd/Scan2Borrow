<?php
require_once __DIR__ . '/functions.php';

secure_session_start();

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function is_guest_logged_in(): bool
{
    return isset($_SESSION['visitor_id']);
}

function login_guest(array $visitor): void
{
    session_regenerate_id(true);
    $_SESSION['visitor_id'] = (int) $visitor['id'];
    $_SESSION['visitor_firstname'] = $visitor['firstname'];
    $_SESSION['visitor_lastname'] = $visitor['lastname'];
}

function require_guest_login(): void
{
    if (!is_guest_logged_in()) {
        redirect('guest_registration.php');
    }
}

function current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

function is_staff(): bool
{
    return in_array(current_role(), ['admin', 'librarian'], true);
}

function is_admin(): bool
{
    return current_role() === 'admin';
}

function is_borrower(): bool
{
    return in_array(current_role(), ['student', 'teacher'], true);
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('index.php');
    }
}

function require_staff(): void
{
    if (!is_logged_in()) {
        redirect('staff_login.php');
    }
    if (!is_staff()) {
        redirect('studhome.php');
    }
}

function require_admin(): void
{
    if (!is_logged_in()) {
        redirect('staff_login.php');
    }
    if (!is_admin()) {
        redirect('adboard.php');
    }
}

function require_borrower(): void
{
    require_login();
    if (!is_borrower()) {
        redirect('adboard.php');
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['barcode']   = $user['barcode'];
    $_SESSION['firstname'] = $user['firstname'];
    $_SESSION['lastname']  = $user['lastname'];
    $_SESSION['role']      = $user['role'];
}

function home_for_role(string $role): string
{
    if (in_array($role, ['admin', 'librarian'], true)) {
        return 'adboard.php';
    } elseif ($role === 'teacher') {
        return 'teachersboard.php';
    } else {
        return 'studhome.php';
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        die('Invalid or expired form token. Please go back and try again.');
    }
}
