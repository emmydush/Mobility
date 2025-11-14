<?php
// API endpoint to get all users for the current tenant
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit();
}

// Include database connection
include_once '../api/config/database.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header("HTTP/1.1 403 Forbidden");
    exit();
}

// Fetch all users with session information for the current tenant
$users = array();
$query = "SELECT u.id, u.username, u.email, u.phone, u.role, u.status, u.created_at, 
          COUNT(us.id) as active_sessions 
          FROM users u 
          LEFT JOIN user_sessions us ON u.id = us.user_id AND us.expires_at > NOW()
          WHERE u.tenant_id = ?
          GROUP BY u.id 
          ORDER BY u.username";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($result as $row) {
        // Format the data for JSON output
        $row['active_sessions'] = (int)$row['active_sessions'];
        $users[] = $row;
    }
}

// Return as JSON
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'data' => $users,
    'count' => count($users)
]);

$conn = null;
?>