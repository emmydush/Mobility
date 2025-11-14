<?php
include '../api/config/database.php';

echo "Listing all users in the database:\n";
echo "==================================\n";

// Get all users with their creation dates
$query = "SELECT id, username, email, role, created_at FROM users ORDER BY created_at";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($result) > 0) {
        foreach ($result as $user) {
            echo "ID: " . $user['id'] . "\n";
            echo "Username: " . $user['username'] . "\n";
            echo "Email: " . $user['email'] . "\n";
            echo "Role: " . $user['role'] . "\n";
            echo "Created: " . $user['created_at'] . "\n";
            echo "------------------------\n";
        }
    } else {
        echo "No users found in the database.\n";
    }
} else {
    echo "Database error\n";
}

$conn = null;
?>