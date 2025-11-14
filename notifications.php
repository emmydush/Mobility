<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
include_once 'api/config/database.php';

// Include language functions
include_once 'api/config/languages.php';

// Set language if specified
if (isset($_GET['lang'])) {
    setCurrentLanguage($_GET['lang']);
}

// Get current language
$current_lang = getCurrentLanguage();

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'cashier';

// Get user permissions
$user_permissions = getUserPermissions($conn, $_SESSION['user_id']);

// Get tenant information
$tenant_info = getCurrentTenantInfo($conn);
$business_name = $tenant_info['business_name'] ?? 'No Business';

// Handle mark as read action
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    markNotificationAsRead($conn, $notification_id, $_SESSION['user_id'], $_SESSION['tenant_id']);
    header("Location: notifications.php?lang=$current_lang");
    exit();
}

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notification_id = (int)$_GET['delete'];
    deleteNotification($conn, $notification_id, $_SESSION['user_id'], $_SESSION['tenant_id']);
    header("Location: notifications.php?lang=$current_lang");
    exit();
}

// Handle mark all as read action
if (isset($_GET['mark_all_read'])) {
    markAllNotificationsAsRead($conn, $_SESSION['user_id'], $_SESSION['tenant_id']);
    header("Location: notifications.php?lang=$current_lang");
    exit();
}

// Get all notifications for the user
$notifications = getAllNotifications($conn, $_SESSION['user_id'], $_SESSION['tenant_id'], 50); // Limit to 50 notifications

$conn = null;
?>