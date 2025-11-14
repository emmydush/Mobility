<?php
header('Content-Type: application/json');

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Include database connection
include_once '../../api/config/database.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['user_id']) || !isset($input['tenant_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$user_id = (int)$input['user_id'];
$tenant_id = (int)$input['tenant_id'];

// Verify that the user and tenant match the session
if ($user_id !== $_SESSION['user_id'] || $tenant_id !== $_SESSION['tenant_id']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Mark all notifications as read
$result = markAllNotificationsAsRead($conn, $user_id, $tenant_id);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to mark notifications as read']);
}

$conn = null;
?>