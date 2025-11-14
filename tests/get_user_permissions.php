<?php
// API endpoint to get user permissions
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit();
}

// Include database connection
include_once 'api/config/database.php';

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

// Get user permissions
$permissions = getUserPermissions($conn, $user_id);

// Return as JSON
header('Content-Type: application/json');
echo json_encode($permissions);

$conn->close();
?>