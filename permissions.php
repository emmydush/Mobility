<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    // Redirect to dashboard if not admin
    header("Location: dashboard.php");
    exit();
}

// Include database connection with robust path resolution
$rootPath = __DIR__;
$databasePath = $rootPath . '/api/config/database.php';

// If the direct path doesn't work, try alternative paths
if (!file_exists($databasePath)) {
    $databasePath = $rootPath . '/../api/config/database.php';
}

if (!file_exists($databasePath)) {
    $databasePath = $rootPath . '/../../api/config/database.php';
}

if (file_exists($databasePath)) {
    include_once $databasePath;
} else {
    die("Database configuration file not found");
}

// Include language functions
include_once 'api/config/languages.php';
include_once 'api/config/user_functions.php';

// Set language if specified
if (isset($_GET['lang'])) {
    setCurrentLanguage($_GET['lang']);
}

// Get current language
$current_lang = getCurrentLanguage();

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'admin';

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_user_permissions':
                $user_id = $_POST['user_id'] ?? 0;
                $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
                
                if (is_numeric($user_id) && $user_id > 0) {
                    // Verify that the user belongs to the current tenant
                    $verify_query = "SELECT id FROM users WHERE id = ? AND tenant_id = ?";
                    $verify_stmt = $conn->prepare($verify_query);
                    if ($verify_stmt) {
                        $verify_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
                        $verify_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                        $verify_stmt->execute();
                        $verify_result = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($verify_result) > 0) {
                            // Update user permissions
                            if (assignUserPermissions($conn, $user_id, $permissions)) {
                                // Log the activity
                                $activity_query = "INSERT INTO activity_log (user_id, action_type, table_name, record_id, new_values) VALUES (?, 'update_permissions', 'users', ?, ?)";
                                $activity_stmt = $conn->prepare($activity_query);
                                if ($activity_stmt) {
                                    $new_values = json_encode(['permissions' => $permissions]);
                                    $activity_stmt->bindParam(1, $_SESSION['user_id'], PDO::PARAM_INT);
                                    $activity_stmt->bindParam(2, $user_id, PDO::PARAM_INT);
                                    $activity_stmt->bindParam(3, $new_values, PDO::PARAM_STR);
                                    $activity_stmt->execute();
                                }
                                
                                $message = "User permissions updated successfully!";
                                $message_type = "success";
                            } else {
                                $message = "Error updating user permissions.";
                                $message_type = "error";
                            }
                        } else {
                            $message = "Invalid user.";
                            $message_type = "error";
                        }
                    } else {
                        $message = "Database error";
                        $message_type = "error";
                    }
                } else {
                    $message = "Invalid user ID.";
                    $message_type = "error";
                }
                break;
        }
    }
}

// Fetch all permissions
$permissions = [];
$permissions_query = "SELECT id, name, description, module FROM permissions ORDER BY module, name";
$permissions_result = $conn->query($permissions_query);
if ($permissions_result) {
    $permissions = $permissions_result->fetchAll(PDO::FETCH_ASSOC);
}

// Group permissions by module for easier display
$grouped_permissions = [];
foreach ($permissions as $permission) {
    $module = $permission['module'] ?: 'General';
    if (!isset($grouped_permissions[$module])) {
        $grouped_permissions[$module] = [];
    }
    $grouped_permissions[$module][] = $permission;
}

// Fetch all users for permission management within the current tenant
$users = [];
$users_query = "SELECT id, username, role FROM users WHERE tenant_id = ? ORDER BY username";
$users_stmt = $conn->prepare($users_query);
if ($users_stmt) {
    $users_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $users_stmt->execute();
    $users_result = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($users_result) {
        foreach ($users_result as $row) {
            $users[] = $row;
        }
    }
}

$conn = null;
?>