<?php
require_once __DIR__ . '/includes/auth.php';
require_staff();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['user_id']) || !isset($input['borrowing_status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$userId = (int) $input['user_id'];
$newStatus = $input['borrowing_status'];

// Validate status
if (!in_array($newStatus, ['active', 'inactive'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    $pdo = db();
    
    // Check if user exists and is a student or teacher
    $stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ? AND role IN (?, ?)');
    $stmt->execute([$userId, 'student', 'teacher']);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    // Update borrowing status
    $stmt = $pdo->prepare('UPDATE users SET borrowing_status = ? WHERE id = ?');
    $stmt->execute([$newStatus, $userId]);
    
    // Log the action
    $adminId = (int) ($_SESSION['user_id'] ?? 0);
    $action = $newStatus === 'inactive' ? 'disable_borrowing' : 'enable_borrowing';
    audit_log($pdo, $adminId, $action, "User ID: $userId, New Status: $newStatus");
    
    echo json_encode([
        'success' => true, 
        'message' => $newStatus === 'inactive' ? 'Borrowing disabled' : 'Borrowing enabled'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}