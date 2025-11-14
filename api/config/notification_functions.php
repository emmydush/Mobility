<?php
// Notification Functions for Mobility Inventory Management System

/**
 * Add a new notification for a user
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID to send notification to
 * @param int $tenant_id Tenant ID
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $type Notification type (info, success, warning, error)
 * @return bool Success status
 */
function addNotification($conn, $user_id, $tenant_id, $title, $message, $type = 'info') {
    $query = "INSERT INTO notifications (user_id, tenant_id, title, message, type, is_read) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $is_read = 0; // Default to unread
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $title, PDO::PARAM_STR);
        $stmt->bindParam(4, $message, PDO::PARAM_STR);
        $stmt->bindParam(5, $type, PDO::PARAM_STR);
        $stmt->bindParam(6, $is_read, PDO::PARAM_INT);
        $result = $stmt->execute();
        return $result;
    }
    
    return false;
}

/**
 * Get unread notifications for a user
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @param int $tenant_id Tenant ID
 * @return array Array of notifications
 */
function getUnreadNotifications($conn, $user_id, $tenant_id) {
    $query = "SELECT * FROM notifications WHERE user_id = ? AND tenant_id = ? AND is_read = 0 ORDER BY created_at DESC";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $notifications;
    }
    
    return [];
}

/**
 * Get all notifications for a user
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @param int $tenant_id Tenant ID
 * @param int $limit Number of notifications to retrieve (default: 10)
 * @return array Array of notifications
 */
function getAllNotifications($conn, $user_id, $tenant_id, $limit = 10) {
    $query = "SELECT * FROM notifications WHERE user_id = ? AND tenant_id = ? ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $notifications;
    }
    
    return [];
}

/**
 * Mark a notification as read
 * 
 * @param PDO $conn Database connection
 * @param int $notification_id Notification ID
 * @param int $user_id User ID
 * @param int $tenant_id Tenant ID
 * @return bool Success status
 */
function markNotificationAsRead($conn, $notification_id, $user_id, $tenant_id) {
    $query = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ? AND tenant_id = ?";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bindParam(1, $notification_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $user_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $result = $stmt->execute();
        return $result;
    }
    
    return false;
}

/**
 * Mark all notifications as read for a user
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @param int $tenant_id Tenant ID
 * @return bool Success status
 */
function markAllNotificationsAsRead($conn, $user_id, $tenant_id) {
    $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND tenant_id = ? AND is_read = 0";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $result = $stmt->execute();
        return $result;
    }
    
    return false;
}

/**
 * Delete a notification
 * 
 * @param PDO $conn Database connection
 * @param int $notification_id Notification ID
 * @param int $user_id User ID
 * @param int $tenant_id Tenant ID
 * @return bool Success status
 */
function deleteNotification($conn, $notification_id, $user_id, $tenant_id) {
    $query = "DELETE FROM notifications WHERE id = ? AND user_id = ? AND tenant_id = ?";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bindParam(1, $notification_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $user_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $result = $stmt->execute();
        return $result;
    }
    
    return false;
}

/**
 * Get notification count for a user
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @param int $tenant_id Tenant ID
 * @param bool $unread_only Only count unread notifications
 * @return int Notification count
 */
function getNotificationCount($conn, $user_id, $tenant_id, $unread_only = true) {
    if ($unread_only) {
        $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND tenant_id = ? AND is_read = 0";
    } else {
        $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND tenant_id = ?";
    }
    
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'];
    }
    
    return 0;
}

/**
 * Generate system notifications based on business events
 * 
 * @param PDO $conn Database connection
 * @param int $tenant_id Tenant ID
 */
function generateSystemNotifications($conn, $tenant_id) {
    // Check for low stock products
    $low_stock_query = "SELECT id, name, stock_quantity FROM products WHERE tenant_id = ? AND stock_quantity <= 5 AND stock_quantity > 0";
    $stmt = $conn->prepare($low_stock_query);
    
    if ($stmt) {
        $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $stmt->execute();
        $low_stock_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // For each low stock product, create a notification for the tenant admin
        foreach ($low_stock_products as $product) {
            $title = "Low Stock Alert";
            $message = "Product '{$product['name']}' is running low. Only {$product['stock_quantity']} units remaining.";
            // Assuming tenant admin user_id is 1, you might want to modify this logic
            addNotification($conn, 1, $tenant_id, $title, $message, 'warning');
        }
    }
    
    // Check for expired products
    $expired_query = "SELECT id, name, expiry_date FROM products WHERE tenant_id = ? AND expiry_date <= CURDATE() AND expiry_date IS NOT NULL";
    $stmt = $conn->prepare($expired_query);
    
    if ($stmt) {
        $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $stmt->execute();
        $expired_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // For each expired product, create a notification for the tenant admin
        foreach ($expired_products as $product) {
            $title = "Expired Product Alert";
            $message = "Product '{$product['name']}' has expired on {$product['expiry_date']}.";
            // Assuming tenant admin user_id is 1, you might want to modify this logic
            addNotification($conn, 1, $tenant_id, $title, $message, 'error');
        }
    }
}

/**
 * Send email notification for critical alerts
 * 
 * @param string $email Recipient email
 * @param string $subject Email subject
 * @param string $message Email message
 * @return bool Success status
 */
function sendEmailNotification($email, $subject, $message) {
    // In a real implementation, you would use a proper email library like PHPMailer
    // This is a simplified example
    $headers = "From: noreply@mobilitysystem.com\r\n";
    $headers .= "Reply-To: noreply@mobilitysystem.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($email, $subject, $message, $headers);
}
?>