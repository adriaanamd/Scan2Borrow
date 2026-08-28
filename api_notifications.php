<?php
require_once __DIR__ . '/includes/auth.php';
            $html = '';
            if (!$pending) {
                $html = '<div class="text-center text-muted py-5">'
                    . '<div style="font-size: 48px;">✅</div>'
                    . '<p class="mt-3">No pending approval requests at this time.</p>'
                . '</div>';
            } else {
                foreach ($pending as $req) {
                    $verification = '';
                    // attempt to use verification photo or book cover if available
                    if (!empty($req['verification_photo'])) {
                        $verification = '<img src="' . e($req['verification_photo']) . '" alt="Verification photo">';
                    } elseif (!empty($req['book_cover'])) {
                        $verification = '<img src="' . e($req['book_cover']) . '" alt="Book cover">';
                    } else {
                        $verification = '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700">No Photo</div>';
                    }

                                        $html .= '<div class="approval-card mb-3">'
                                                . '<div class="infos">'
                                                    . '<div class="image">' . $verification . '</div>'
                                                    . '<div class="info">'
                                                        . '<div><p class="name">' . e($req['title']) . '</p><p class="function">by ' . e($req['author']) . ' | Barcode: ' . e($req['book_barcode']) . '</p></div>'
                                                        . '<div class="details" style="font-size:0.95rem;color:rgba(156,163,175,1);margin-top:6px;">'
                                                            . '<p style="margin:0"><strong>Student:</strong> ' . e($req['firstname'] . ' ' . $req['lastname']) . '</p>'
                                                            . '<p style="margin:0"><strong>ID:</strong> ' . e($req['student_barcode']) . '</p>'
                                                            . (!empty($req['course']) ? '<p style="margin:0"><strong>Course:</strong> ' . e($req['course']) . ' - Year ' . e($req['year_level']) . '</p>' : '')
                                                            . '<p style="margin:0"><strong>Due Date:</strong> ' . e(date('M d, Y', strtotime($req['due_date']))) . '</p>'
                                                            . '<small class="text-muted">Requested: ' . e(date('M d, Y g:i A', strtotime($req['requested_at']))) . '</small>'
                                                        . '</div>'
                                                    . '</div>'
                                                . '</div>'
                                                . '<div class="approval-actions">'
                                                    . '<form method="POST" action="adboard.php" class="flex-fill" onsubmit="return confirm(\'Approve this borrow request?\');">' . csrf_field()
                                                        . '<input type="hidden" name="action" value="approve">'
                                                        . '<input type="hidden" name="borrowing_id" value="' . (int) $req['id'] . '">' 
                                                        . '<button type="submit" class="request">Accept</button>'
                                                    . '</form>'
                                                    . '<form method="POST" action="adboard.php" class="flex-fill" onsubmit="return confirm(\'Reject this borrow request?\');">' . csrf_field()
                                                        . '<input type="hidden" name="action" value="reject">'
                                                        . '<input type="hidden" name="borrowing_id" value="' . (int) $req['id'] . '">' 
                                                        . '<button type="submit" class="request">Reject</button>'
                                                    . '</form>'
                                                . '</div>'
                                        . '</div>';
                }
            }
                                        <strong>Due Date:</strong> ' . date('M d, Y', strtotime($req['due_date'])) . '
                                    </p>
                                    <small class="text-muted">
                                        Requested: ' . date('M d, Y g:i A', strtotime($req['requested_at'])) . '
                                    </small>
                                </div>
                                <div class="col-md-4 text-end">
                                    <form method="POST" action="adboard.php" class="d-grid gap-2" onsubmit="return confirm(\'Approve this borrow request?\');">
                                        ' . csrf_field() . '
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="borrowing_id" value="' . (int) $req['id'] . '">
                                        <button type="submit" class="btn btn-success btn-sm">✓ Approve</button>
                                    </form>
                                    <form method="POST" action="adboard.php" class="d-grid gap-2 mt-2" onsubmit="return confirm(\'Reject this borrow request?\');">
                                        ' . csrf_field() . '
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="borrowing_id" value="' . (int) $req['id'] . '">
                                        <button type="submit" class="btn btn-danger btn-sm">✗ Reject</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>';
                }
            }
            
            echo json_encode([
                'success' => true,
                'count' => $count,
                'html' => $html
            ]);
            break;
            
        case 'return_notifications':
            // Get unviewed return notifications
            $notifications = get_unviewed_return_notifications($pdo, $staffId);
            
            if (empty($notifications)) {
                echo json_encode(['success' => true, 'notifications' => []]);
                break;
            }
            
            $result = [];
            foreach ($notifications as $notif) {
                $result[] = [
                    'id' => $notif['id'],
                    'message' => $notif['message'],
                    'created_at' => date('M d, Y g:i A', strtotime($notif['created_at']))
                ];
            }
            
            echo json_encode([
                'success' => true,
                'notifications' => $result
            ]);
            break;
            
        case 'mark_notification_viewed':
            $notificationId = (int) ($_POST['notification_id'] ?? 0);
            $notificationType = $_POST['notification_type'] ?? 'return';
            
            if ($notificationId > 0) {
                if ($notificationType === 'borrow') {
                    mark_borrow_notification_viewed($pdo, $notificationId, $staffId);
                } else {
                    mark_return_notification_viewed($pdo, $notificationId, $staffId);
                }
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
            }
            break;
            
        case 'borrow_notifications':
            // Get unviewed borrow notifications
            $notifications = get_unviewed_borrow_notifications($pdo, $staffId);
            
            if (empty($notifications)) {
                echo json_encode(['success' => true, 'notifications' => []]);
                break;
            }
            
            $result = [];
            foreach ($notifications as $notif) {
                $result[] = [
                    'id' => $notif['id'],
                    'title' => $notif['title'],
                    'message' => $notif['message'],
                    'created_at' => date('M d, Y g:i A', strtotime($notif['created_at']))
                ];
            }
            
            echo json_encode([
                'success' => true,
                'notifications' => $result
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}