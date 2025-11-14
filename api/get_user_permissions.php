<?php
// API endpoint to get user permissions
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit();
}

// Include database connection
include_once '../config/database.php';
include_once '../config/user_functions.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header("HTTP/1.1 403 Forbidden");
    exit();
}

// Get user ID from query parameter
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    header("HTTP/1.1 400 Bad Request");
    exit();
}

// Verify that the user belongs to the current tenant
$verify_query = "SELECT id FROM users WHERE id = ? AND tenant_id = ?";
$verify_stmt = $conn->prepare($verify_query);
if ($verify_stmt) {
    $verify_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $verify_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($verify_result) == 0) {
        header("HTTP/1.1 403 Forbidden");
        exit();
    }
} else {
    header("HTTP/1.1 500 Internal Server Error");
    exit();
}

// Get user permissions
$permissions = getUserPermissions($conn, $user_id);

// Return as JSON
header('Content-Type: application/json');
echo json_encode($permissions);

$conn = null;
?>