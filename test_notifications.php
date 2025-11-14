<?php
session_start();

// Include database connection
include_once 'api/config/database.php';

// Test adding a notification
if (isset($_SESSION['user_id']) && isset($_SESSION['tenant_id'])) {
    // Add a test notification
    $result = addNotification(
        $conn,
        $_SESSION['user_id'],
        $_SESSION['tenant_id'],
        "Test Notification",
        "This is a test notification to verify the notification system is working correctly.",
        "info"
    );
    
    if ($result) {
        echo "Test notification added successfully!";
    } else {
        echo "Failed to add test notification.";
    }
} else {
    echo "User not logged in. Please log in first.";
}

$conn->close();
?>