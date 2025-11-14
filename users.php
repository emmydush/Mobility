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

// Set language if specified
if (isset($_GET['lang'])) {
    setCurrentLanguage($_GET['lang']);
}

// Get current language
$current_lang = getCurrentLanguage();

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'user'; // Default role if not set in session

// If role is not in session, fetch it from database
if ($role === 'user' && isset($_SESSION['user_id'])) {
    $role_query = "SELECT role FROM users WHERE id = ?";
    $role_stmt = $conn->prepare($role_query);
    if ($role_stmt) {
        $role_stmt->bind_param("i", $_SESSION['user_id']);
        $role_stmt->execute();
        $role_result = $role_stmt->get_result();
        if ($role_result->num_rows > 0) {
            $role_row = $role_result->fetch_assoc();
            $role = $role_row['role'];
            // Update session with role
            $_SESSION['role'] = $role;
        }
        $role_stmt->close();
    }
}

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $username = $_POST['username'] ?? '';
                $email = $_POST['email'] ?? '';
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? 'cashier';
                $phone = $_POST['phone'] ?? '';
                $address = $_POST['address'] ?? '';
                
                if (!empty($username) && !empty($email) && !empty($password)) {
                    // Check if username or email already exists within the tenant
                    $check_query = "SELECT id FROM users WHERE (username = ? OR email = ?) AND tenant_id = ?";
                    $check_stmt = $conn->prepare($check_query);
                    if ($check_stmt) {
                        $check_stmt->bind_param("ssi", $username, $email, $_SESSION['tenant_id']);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                        
                        if ($check_result->num_rows > 0) {
                            $message = "Username or email already exists.";
                            $message_type = "error";
                        } else {
                            // Create new user
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            
                            $insert_query = "INSERT INTO users (tenant_id, username, password, email, role, phone, address, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                            $insert_stmt = $conn->prepare($insert_query);
                            if ($insert_stmt) {
                                $insert_stmt->bind_param("issssssi", $_SESSION['tenant_id'], $username, $hashed_password, $email, $role, $phone, $address, $_SESSION['user_id']);
                                
                                if ($insert_stmt->execute()) {
                                    // Log the activity
                                    $activity_query = "INSERT INTO activity_log (user_id, action_type, table_name, record_id, new_values) VALUES (?, 'create', 'users', ?, ?)";
                                    $activity_stmt = $conn->prepare($activity_query);
                                    if ($activity_stmt) {
                                        $new_values = json_encode(['username' => $username, 'email' => $email, 'role' => $role, 'phone' => $phone, 'address' => $address]);
                                        $activity_stmt->bind_param("iis", $_SESSION['user_id'], $insert_stmt->insert_id, $new_values);
                                        $activity_stmt->execute();
                                        $activity_stmt->close();
                                    }
                                    
                                    $message = "User created successfully!";
                                    $message_type = "success";
                                } else {
                                    $message = "Error creating user: " . $conn->error;
                                    $message_type = "error";
                                }
                                $insert_stmt->close();
                            } else {
                                $message = "Database error: " . $conn->error;
                                $message_type = "error";
                            }
                        }
                        $check_stmt->close();
                    } else {
                        $message = "Database error: " . $conn->error;
                        $message_type = "error";
                    }
                } else {
                    $message = "Username, email, and password are required.";
                    $message_type = "error";
                }
                break;
                
            case 'edit':
                $id = $_POST['id'] ?? 0;
                $username = $_POST['username'] ?? '';
                $email = $_POST['email'] ?? '';
                $role = $_POST['role'] ?? 'cashier';
                $status = $_POST['status'] ?? 'active';
                $password = $_POST['password'] ?? '';
                $phone = $_POST['phone'] ?? '';
                $address = $_POST['address'] ?? '';
                
                // Handle permissions if provided
                $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
                
                if (is_numeric($id) && $id > 0 && !empty($username) && !empty($email)) {
                    // Check if username or email already exists for other users within the tenant
                    $check_query = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ? AND tenant_id = ?";
                    $check_stmt = $conn->prepare($check_query);
                    if ($check_stmt) {
                        $check_stmt->bind_param("ssii", $username, $email, $id, $_SESSION['tenant_id']);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                        
                        if ($check_result->num_rows > 0) {
                            $message = "Username or email already exists for another user.";
                            $message_type = "error";
                        } else {
                            // Update user
                            if (!empty($password)) {
                                // Update with new password
                                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                $update_query = "UPDATE users SET username = ?, email = ?, role = ?, status = ?, password = ?, phone = ?, address = ? WHERE id = ? AND tenant_id = ?";
                                $update_stmt = $conn->prepare($update_query);
                                $update_stmt->bind_param("sssssssii", $username, $email, $role, $status, $hashed_password, $phone, $address, $id, $_SESSION['tenant_id']);
                            } else {
                                // Update without changing password
                                $update_query = "UPDATE users SET username = ?, email = ?, role = ?, status = ?, phone = ?, address = ? WHERE id = ? AND tenant_id = ?";
                                $update_stmt = $conn->prepare($update_query);
                                $update_stmt->bind_param("sssssii", $username, $email, $role, $status, $phone, $address, $id, $_SESSION['tenant_id']);
                            }
                            
                            if ($update_stmt->execute()) {
                                // Update user permissions
                                assignUserPermissions($conn, $id, $permissions);
                                
                                // Log the activity
                                $activity_query = "INSERT INTO activity_log (user_id, action_type, table_name, record_id, new_values) VALUES (?, 'update', 'users', ?, ?)";
                                $activity_stmt = $conn->prepare($activity_query);
                                if ($activity_stmt) {
                                    $new_values = json_encode(['username' => $username, 'email' => $email, 'role' => $role, 'status' => $status, 'phone' => $phone, 'address' => $address]);
                                    $activity_stmt->bind_param("iis", $_SESSION['user_id'], $id, $new_values);
                                    $activity_stmt->execute();
                                    $activity_stmt->close();
                                }
                                
                                $message = "User updated successfully!";
                                $message_type = "success";
                            } else {
                                $message = "Error updating user: " . $conn->error;
                                $message_type = "error";
                            }
                            $update_stmt->close();
                        }
                        $check_stmt->close();
                    } else {
                        $message = "Database error: " . $conn->error;
                        $message_type = "error";
                    }
                } else {
                    $message = "Invalid user data.";
                    $message_type = "error";
                }
                break;
                
            case 'delete':
                $id = $_POST['id'] ?? 0;
                // Prevent deleting the current user
                if ($id == $_SESSION['user_id']) {
                    $message = "You cannot delete your own account.";
                    $message_type = "error";
                } else if (is_numeric($id) && $id > 0) {
                    // Get user data for logging
                    $user_query = "SELECT username, email, role FROM users WHERE id = ? AND tenant_id = ?";
                    $user_stmt = $conn->prepare($user_query);
                    $user_data = [];
                    if ($user_stmt) {
                        $user_stmt->bind_param("ii", $id, $_SESSION['tenant_id']);
                        $user_stmt->execute();
                        $user_result = $user_stmt->get_result();
                        if ($user_result->num_rows > 0) {
                            $user_data = $user_result->fetch_assoc();
                        }
                        $user_stmt->close();
                    }
                    
                    $query = "DELETE FROM users WHERE id = ? AND tenant_id = ?";
                    $stmt = $conn->prepare($query);
                    if ($stmt) {
                        $stmt->bind_param("ii", $id, $_SESSION['tenant_id']);
                        if ($stmt->execute()) {
                            // Log the activity
                            $activity_query = "INSERT INTO activity_log (user_id, action_type, table_name, record_id, old_values) VALUES (?, 'delete', 'users', ?, ?)";
                            $activity_stmt = $conn->prepare($activity_query);
                            if ($activity_stmt) {
                                $old_values = json_encode($user_data);
                                $activity_stmt->bind_param("iis", $_SESSION['user_id'], $id, $old_values);
                                $activity_stmt->execute();
                                $activity_stmt->close();
                            }
                            
                            $message = "User deleted successfully!";
                            $message_type = "success";
                        } else {
                            $message = "Error deleting user: " . $conn->error;
                            $message_type = "error";
                        }
                        $stmt->close();
                    } else {
                        $message = "Database error: " . $conn->error;
                        $message_type = "error";
                    }
                } else {
                    $message = "Invalid user ID.";
                    $message_type = "error";
                }
                break;
                
            case 'toggle_status':
                $id = $_POST['id'] ?? 0;
                $current_status = $_POST['current_status'] ?? 'active';
                
                if (is_numeric($id) && $id > 0) {
                    $new_status = ($current_status === 'active') ? 'inactive' : 'active';
                    
                    $query = "UPDATE users SET status = ? WHERE id = ? AND tenant_id = ?";
                    $stmt = $conn->prepare($query);
                    if ($stmt) {
                        $stmt->bind_param("sii", $new_status, $id, $_SESSION['tenant_id']);
                        if ($stmt->execute()) {
                            // Log the activity
                            $activity_query = "INSERT INTO activity_log (user_id, action_type, table_name, record_id, old_values, new_values) VALUES (?, 'update_status', 'users', ?, ?, ?)";
                            $activity_stmt = $conn->prepare($activity_query);
                            if ($activity_stmt) {
                                $old_values = json_encode(['status' => $current_status]);
                                $new_values = json_encode(['status' => $new_status]);
                                $activity_stmt->bind_param("iiss", $_SESSION['user_id'], $id, $old_values, $new_values);
                                $activity_stmt->execute();
                                $activity_stmt->close();
                            }
                            
                            $message = "User status updated successfully!";
                            $message_type = "success";
                        } else {
                            $message = "Error updating user status: " . $conn->error;
                            $message_type = "error";
                        }
                        $stmt->close();
                    } else {
                        $message = "Database error: " . $conn->error;
                        $message_type = "error";
                    }
                } else {
                    $message = "Invalid user ID.";
                    $message_type = "error";
                }
                break;
                
            case 'reset_password':
                $id = $_POST['id'] ?? 0;
                if (is_numeric($id) && $id > 0) {
                    // Generate a random password
                    $new_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10);
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $query = "UPDATE users SET password = ? WHERE id = ? AND tenant_id = ?";
                    $stmt = $conn->prepare($query);
                    if ($stmt) {
                        $stmt->bind_param("sii", $hashed_password, $id, $_SESSION['tenant_id']);
                        if ($stmt->execute()) {
                            // Log the activity
                            $activity_query = "INSERT INTO activity_log (user_id, action_type, table_name, record_id, new_values) VALUES (?, 'reset_password', 'users', ?, ?)";
                            $activity_stmt = $conn->prepare($activity_query);
                            if ($activity_stmt) {
                                $new_values = json_encode(['new_password_length' => strlen($new_password)]);
                                $activity_stmt->bind_param("iis", $_SESSION['user_id'], $id, $new_values);
                                $activity_stmt->execute();
                                $activity_stmt->close();
                            }
                            
                            $message = "Password reset successfully! New password: " . $new_password;
                            $message_type = "success";
                        } else {
                            $message = "Error resetting password: " . $conn->error;
                            $message_type = "error";
                        }
                        $stmt->close();
                    } else {
                        $message = "Database error: " . $conn->error;
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

// Fetch all users with session information for the current tenant
$users = array();
$query = "SELECT u.*, COUNT(us.id) as active_sessions 
          FROM users u 
          LEFT JOIN user_sessions us ON u.id = us.user_id AND us.expires_at > NOW()
          WHERE u.tenant_id = ?
          GROUP BY u.id 
          ORDER BY u.username";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("i", $_SESSION['tenant_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();
}

// Fetch all permissions
$all_permissions = array();
$perm_query = "SELECT * FROM permissions ORDER BY module, name";
$perm_result = $conn->query($perm_query);
if ($perm_result) {
    while ($row = $perm_result->fetch_assoc()) {
        $all_permissions[] = $row;
    }
}

// Fetch user activity for the selected user (if any) within the tenant
$user_activity = array();
if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    // First verify that the user belongs to the current tenant
    $verify_query = "SELECT id FROM users WHERE id = ? AND tenant_id = ?";
    $verify_stmt = $conn->prepare($verify_query);
    if ($verify_stmt) {
        $verify_stmt->bind_param("ii", $_GET['user_id'], $_SESSION['tenant_id']);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows > 0) {
            // User belongs to this tenant, fetch activity
            $activity_query = "SELECT * FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
            $activity_stmt = $conn->prepare($activity_query);
            if ($activity_stmt) {
                $activity_stmt->bind_param("i", $_GET['user_id']);
                $activity_stmt->execute();
                $activity_result = $activity_stmt->get_result();
                while ($row = $activity_result->fetch_assoc()) {
                    $user_activity[] = $row;
                }
                $activity_stmt->close();
            }
        }
        $verify_stmt->close();
    }
}

// Fetch user statistics
$total_users = count($users);
$active_users = 0;
$inactive_users = 0;
$admin_users = 0;
$manager_users = 0;
$cashier_users = 0;

foreach ($users as $user) {
    if ($user['status'] === 'active') {
        $active_users++;
    } else {
        $inactive_users++;
    }
    
    switch ($user['role']) {
        case 'admin':
            $admin_users++;
            break;
        case 'manager':
            $manager_users++;
            break;
        case 'cashier':
            $cashier_users++;
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>"
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Complete Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Custom styles for the user form */
        .permission-group {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }
        
        .permission-group:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .select-all-permissions {
            background-color: #eff6ff;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
        }
        
        .select-all-permissions:hover {
            background-color: #dbeafe;
        }
        
        /* Scrollbar styling for permissions container */
        .permissions-container::-webkit-scrollbar {
            width: 6px;
        }
        
        .permissions-container::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .permissions-container::-webkit-scrollbar-thumb {
            background: #c5c5c5;
            border-radius: 3px;
        }
        
        .permissions-container::-webkit-scrollbar-thumb:hover {
            background: #a0a0a0;
        }
        
        /* Enhanced tab styling */
        .tab-button.active {
            border-bottom: 2px solid #3b82f6;
            color: #3b82f6;
            font-weight: 600;
        }
        
        .tab-button:not(.active) {
            color: #6b7280;
        }
        
        .tab-button:not(.active):hover {
            color: #374151;
            border-bottom: 2px solid #d1d5db;
        }
        
        /* Compact permission items */
        .permission-item {
            padding: 0.25rem 0;
        }
        
        /* Search box styling */
        .search-box {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.5rem;
            margin-bottom: 1rem;
        }
        
        /* Collapsible sections */
        .collapsible-header {
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            background-color: #f9fafb;
            border-radius: 0.375rem;
            margin-bottom: 0.5rem;
        }
        
        .collapsible-content {
            padding: 0.5rem;
        }
        
        /* Modal responsive adjustments */
        @media (max-width: 768px) {
            #user-modal .relative {
                top: 5%;
                max-height: 90vh;
                width: 95%;
            }
            
            #user-modal .grid-cols-1.md\:grid-cols-2 {
                grid-template-columns: 1fr;
            }
        }
        
        @media (min-width: 769px) and (max-width: 1024px) {
            #user-modal .relative {
                width: 80%;
            }
        }
        
        @media (min-width: 1025px) {
            #user-modal .relative {
                width: 70%; /* Increased width */
            }
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .sidebar {
                width: 16rem;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
                position: fixed;
                height: 100vh;
                z-index: 1000;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .header-title {
                font-size: 1.25rem;
            }
            
            .user-info {
                display: none;
            }
            
            .mobile-user-info {
                display: block;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .add-user-btn, .show-all-users-btn {
                width: 100%;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .header-actions {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .language-selector select {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
            
            .add-user-btn, .show-all-users-btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
            
            .table th, .table td {
                padding: 0.5rem;
                font-size: 0.75rem;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .action-buttons button, .action-buttons form {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
            
            .form-group input,
            .form-group select,
            .form-group textarea {
                padding: 0.4rem;
                font-size: 0.875rem;
            }
            
            .form-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                padding: 0.5rem;
                font-size: 0.875rem;
            }
            
            .stats-grid {
                gap: 0.5rem;
            }
            
            .stat-card {
                padding: 0.75rem;
            }
            
            .stat-value {
                font-size: 1.25rem;
            }
            
            .stat-label {
                font-size: 0.75rem;
            }
        }
        
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
        
        .mobile-user-info {
            display: none;
        }
    </style>
</head>
<body>
    <!-- Mobile menu toggle button and overlay -->
    <button class="menu-toggle fixed top-4 left-4 z-50" id="menu-toggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="overlay" id="overlay"></div>
    
    <div class="flex h-screen bg-gray-50">
        <!-- Sidebar -->
        <div class="w-64 bg-gradient-to-b from-green-700 to-green-900 shadow-lg">
            <div class="p-4 border-b border-green-600">
                <h1 class="text-xl font-bold text-white">IMS</h1>
                <p class="text-sm text-green-200">Inventory Management</p>
            </div>
            <nav class="mt-4">
                <a href="dashboard.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    <span><?php echo t('dashboard'); ?></span>
                </a>
                <a href="products.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-box mr-3"></i>
                    <span>Products</span>
                </a>
                <a href="categories.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-tags mr-3"></i>
                    <span>Categories</span>
                </a>
                <a href="purchases.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-shopping-cart mr-3"></i>
                    <span>Purchases</span>
                </a>
                <a href="expenses.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-file-invoice-dollar mr-3"></i>
                    <span>Expenses</span>
                </a>
                <a href="suppliers.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-truck mr-3"></i>
                    <span>Suppliers</span>
                </a>
                <a href="pos.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-cash-register mr-3"></i>
                    <span>Point of Sale</span>
                </a>
                <a href="stock-movements.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-exchange-alt mr-3"></i>
                    <span>Stock Movements</span>
                </a>
                <a href="customers.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-users mr-3"></i>
                    <span>Customers</span>
                </a>
                <a href="sales.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-shopping-cart mr-3"></i>
                    <span>Sales</span>
                </a>
                <a href="reports.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-chart-bar mr-3"></i>
                    <span><?php echo t('reports'); ?></span>
                </a>
                <a href="users.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-white bg-gradient-to-r from-green-500 to-green-600 border-l-4 border-green-300">
                    <i class="fas fa-user mr-3"></i>
                    <span><?php echo t('users'); ?></span>
                </a>
                <a href="settings.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-cog mr-3"></i>
                    <span><?php echo t('settings'); ?></span>
                </a>
                <a href="permissions.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-user-shield mr-3"></i>
                    <span>Permissions</span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="flex items-center justify-between p-4 bg-gradient-to-r from-green-600 to-green-800 shadow">
                <h2 class="text-xl font-semibold text-white"><?php echo t('users_management'); ?></h2>
                <div class="flex items-center space-x-4">
                    <!-- Language Selector -->
                    <div class="relative">
                        <select onchange="window.location.href=this.value" class="bg-green-600 text-white rounded px-2 py-1 text-sm">
                            <option value="?lang=en" <?php echo ($current_lang == 'en') ? 'selected' : ''; ?>>English</option>
                            <option value="?lang=fr" <?php echo ($current_lang == 'fr') ? 'selected' : ''; ?>>Français</option>
                            <option value="?lang=rw" <?php echo ($current_lang == 'rw') ? 'selected' : ''; ?>>Kinyarwanda</option>
                        </select>
                    </div>
                    <button id="add-user-btn" class="px-4 py-2 text-sm bg-white text-green-600 rounded hover:bg-green-50 transition duration-200">
                        <i class="fas fa-plus mr-1"></i> Add User
                    </button>
                    <a href="show_all_users.php?lang=<?php echo $current_lang; ?>" class="px-4 py-2 text-sm bg-green-600 text-white rounded hover:bg-green-500 transition duration-200">
                        <i class="fas fa-list mr-1"></i> Show All Users
                    </a>
                    <!-- User Profile Dropdown -->
                    <div class="relative">
                        <button id="user-menu-button" class="flex items-center space-x-2 text-white focus:outline-none">
                            <?php 
                            $profile_picture = getCurrentUserProfilePicture($conn, $_SESSION['user_id']);
                            if (!empty($profile_picture)): ?>
                                <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile" class="w-8 h-8 rounded-full object-cover">
                            <?php else: ?>
                                <div class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center">
                                    <i class="fas fa-user text-white"></i>
                                </div>
                            <?php endif; ?>
                            <div class="text-left hidden md:block">
                                <p class="text-sm font-medium text-white"><?php echo htmlspecialchars($username); ?></p>
                                <p class="text-xs text-green-100 capitalize"><?php echo htmlspecialchars($role); ?></p>
                            </div>
                            <i class="fas fa-chevron-down text-green-200 text-xs"></i>
                        </button>
                        
                        <!-- Dropdown menu -->
                        <div id="user-dropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 hidden z-50">
                            <div class="px-4 py-2 border-b border-gray-200">
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($username); ?></p>
                                <p class="text-xs text-gray-500 capitalize"><?php echo htmlspecialchars($role); ?></p>
                            </div>
                            <a href="profile.php?lang=<?php echo $current_lang; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user-circle mr-2"></i><?php echo t('profile'); ?>
                            </a>
                            <a href="settings.php?lang=<?php echo $current_lang; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-cog mr-2"></i><?php echo t('settings'); ?>
                            </a>
                            <a href="logout.php?lang=<?php echo $current_lang; ?>" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2"></i><?php echo t('logout'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Message Display -->
            <?php if ($message): ?>
                <div class="p-4 <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                    <div class="container mx-auto">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Users Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <!-- User Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                    <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow p-4 text-white">
                        <div class="text-green-100 text-sm">Total Users</div>
                        <div class="text-2xl font-bold"><?php echo $total_users; ?></div>
                    </div>
                    <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow p-4 text-white">
                        <div class="text-green-100 text-sm">Active Users</div>
                        <div class="text-2xl font-bold"><?php echo $active_users; ?></div>
                    </div>
                    <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-lg shadow p-4 text-white">
                        <div class="text-red-100 text-sm">Inactive Users</div>
                        <div class="text-2xl font-bold"><?php echo $inactive_users; ?></div>
                    </div>
                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg shadow p-4 text-white">
                        <div class="text-purple-100 text-sm">Admins</div>
                        <div class="text-2xl font-bold"><?php echo $admin_users; ?></div>
                    </div>
                    <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow p-4 text-white">
                        <div class="text-indigo-100 text-sm">Managers/Cashiers</div>
                        <div class="text-2xl font-bold"><?php echo ($manager_users + $cashier_users); ?></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-lg shadow overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-800">User List</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sessions</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (count($users) > 0): ?>
                                            <?php foreach ($users as $user): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['username']); ?></div>
                                                        <div class="text-sm text-gray-500">
                                                            <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <div><?php echo htmlspecialchars($user['email']); ?></div>
                                                        <?php if (!empty($user['phone'])): ?>
                                                            <div><?php echo htmlspecialchars($user['phone']); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                            <?php echo $user['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 
                                                                  ($user['role'] === 'manager' ? 'bg-green-100 text-green-800' : 
                                                                   ($user['role'] === 'supervisor' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800')); ?>">
                                                            <?php echo ucfirst($user['role']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <form method="POST" class="inline">
                                                            <input type="hidden" name="action" value="toggle_status">
                                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                            <input type="hidden" name="current_status" value="<?php echo $user['status']; ?>">
                                                            <button type="submit" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                                <?php echo $user['status'] === 'active' ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-red-100 text-red-800 hover:bg-red-200'; ?>">
                                                                <?php echo ucfirst($user['status']); ?>
                                                            </button>
                                                        </form>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                            <?php echo $user['active_sessions']; ?> active
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <button class="text-green-600 hover:text-green-900 mr-3" 
                                                                onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo $user['role']; ?>', '<?php echo $user['status']; ?>', '<?php echo htmlspecialchars($user['phone'] ?? ''); ?>', '<?php echo htmlspecialchars($user['address'] ?? ''); ?>')">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                        <button class="text-yellow-600 hover:text-yellow-900 mr-3"
                                                                onclick="resetPassword(<?php echo $user['id']; ?>)">
                                                            <i class="fas fa-key"></i> Reset
                                                        </button>
                                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                                <button type="submit" class="text-red-600 hover:text-red-900">
                                                                    <i class="fas fa-trash"></i> Delete
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                                    No users found
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- User Activity Panel -->
                    <div>
                        <div class="bg-white rounded-lg shadow overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-800">User Activity</h3>
                            </div>
                            <div class="p-4 max-h-96 overflow-y-auto">
                                <?php if (count($user_activity) > 0): ?>
                                    <?php foreach ($user_activity as $activity): ?>
                                        <div class="mb-3 p-3 bg-gray-50 rounded">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($activity['action_type']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                            </div>
                                            <?php if (!empty($activity['table_name'])): ?>
                                                <div class="text-xs text-gray-600">
                                                    Table: <?php echo htmlspecialchars($activity['table_name']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-gray-500 text-center py-4">No activity found</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- User Statistics -->
                        <div class="bg-white rounded-lg shadow overflow-hidden mt-6">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-800">User Statistics</h3>
                            </div>
                            <div class="p-4">
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Admin</span>
                                        <span class="text-sm font-medium"><?php echo $admin_users; ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Manager</span>
                                        <span class="text-sm font-medium"><?php echo $manager_users; ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Cashier</span>
                                        <span class="text-sm font-medium"><?php echo $cashier_users; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add/Edit User Modal -->
    <div id="user-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 mx-auto p-4 border shadow-lg rounded-md bg-white max-h-[90vh] overflow-y-auto">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4" id="modal-title">Add User</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add" id="form-action">
                    <input type="hidden" name="id" value="" id="user-id">
                    
                    <!-- Tabs for User Details and Permissions -->
                    <div class="mb-4 border-b border-gray-200">
                        <nav class="flex space-x-8">
                            <button type="button" class="tab-button py-2 px-1 text-green-600 font-medium text-sm focus:outline-none active" data-tab="details">
                                User Details
                            </button>
                            <button type="button" class="tab-button py-2 px-1 text-gray-500 hover:text-gray-700 font-medium text-sm focus:outline-none" data-tab="permissions">
                                Permissions
                            </button>
                        </nav>
                    </div>
                    
                    <!-- User Details Tab -->
                    <div class="tab-content" id="details-tab">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="user-name">
                                    Username *
                                </label>
                                <input type="text" id="user-name" name="username" required
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="user-email">
                                    Email *
                                </label>
                                <input type="email" id="user-email" name="email" required
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="user-phone">
                                    Phone
                                </label>
                                <input type="text" id="user-phone" name="phone"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="user-role">
                                    Role *
                                </label>
                                <select id="user-role" name="role" required
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    <option value="">Select Role</option>
                                    <option value="admin">Admin</option>
                                    <option value="manager">Manager</option>
                                    <option value="cashier">Cashier</option>
                                    <option value="supervisor">Supervisor</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="user-status">
                                    Status
                                </label>
                                <select id="user-status" name="status"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="user-password">
                                    Password *
                                </label>
                                <input type="password" id="user-password" name="password" required
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                    placeholder="Enter password">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="user-address">
                                Address
                            </label>
                            <textarea id="user-address" name="address" rows="2"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                        </div>
                    </div>
                    
                    <!-- Permissions Tab -->
                    <div class="tab-content hidden" id="permissions-tab">
                        <div class="mb-4">
                            <p class="text-sm text-gray-600 mb-3">Select the permissions you want to grant to this user. Admin users automatically have all permissions.</p>
                            
                            <!-- Permission Search -->
                            <div class="search-box">
                                <input type="text" id="permission-search" placeholder="Search permissions..." class="w-full p-2 border rounded">
                            </div>
                            
                            <!-- Permission Groups -->
                            <div class="border rounded-lg p-3 permissions-container max-h-96 overflow-y-auto">
                                <?php 
                                // Group permissions by module
                                $grouped_permissions = [];
                                foreach ($all_permissions as $permission) {
                                    $module = $permission['module'];
                                    if (!isset($grouped_permissions[$module])) {
                                        $grouped_permissions[$module] = [];
                                    }
                                    $grouped_permissions[$module][] = $permission;
                                }
                                
                                // Display permissions grouped by module
                                foreach ($grouped_permissions as $module => $perms): ?>
                                    <div class="permission-group">
                                        <div class="collapsible-header">
                                            <h4 class="font-medium text-gray-800 capitalize"><?php echo htmlspecialchars(str_replace('_', ' ', $module)); ?></h4>
                                            <div class="flex items-center space-x-2">
                                                <button type="button" class="text-xs text-green-600 select-all-permissions" data-module="<?php echo $module; ?>">
                                                    Select All
                                                </button>
                                                <i class="fas fa-chevron-down text-gray-500"></i>
                                            </div>
                                        </div>
                                        <div class="collapsible-content">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-1">
                                                <?php foreach ($perms as $permission): ?>
                                                    <div class="permission-item flex items-center">
                                                        <input type="checkbox" name="permissions[]" value="<?php echo $permission['name']; ?>" 
                                                            id="perm_<?php echo $permission['name']; ?>"
                                                            class="permission-checkbox rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50 permission-search-item"
                                                            data-description="<?php echo strtolower(htmlspecialchars($permission['description'])); ?>"
                                                            data-module="<?php echo $module; ?>">
                                                        <label for="perm_<?php echo $permission['name']; ?>" class="ml-2 text-sm text-gray-700">
                                                            <?php echo htmlspecialchars($permission['description']); ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between mt-6">
                        <button type="button" id="cancel-user-btn"
                            class="px-4 py-2 text-sm bg-gray-600 text-white rounded hover:bg-gray-700">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 text-sm bg-green-600 text-white rounded hover:bg-green-700">
                            Save User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reset Password Confirmation Modal -->
    <div id="reset-password-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Reset Password</h3>
                <p class="text-gray-600 mb-4">Are you sure you want to reset this user's password? A new password will be generated and displayed.</p>
                <form method="POST" id="reset-password-form">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="id" value="" id="reset-user-id">
                    <div class="flex items-center justify-between mt-6">
                        <button type="button" id="cancel-reset-btn"
                            class="px-4 py-2 text-sm bg-gray-600 text-white rounded hover:bg-gray-700">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 text-sm bg-yellow-600 text-white rounded hover:bg-yellow-700">
                            Reset Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // JavaScript for modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('user-modal');
            const resetModal = document.getElementById('reset-password-modal');
            const addUserBtn = document.getElementById('add-user-btn');
            const cancelBtn = document.getElementById('cancel-user-btn');
            const cancelResetBtn = document.getElementById('cancel-reset-btn');
            const modalTitle = document.getElementById('modal-title');
            const formAction = document.getElementById('form-action');
            
            // Tab functionality
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const tab = this.getAttribute('data-tab');
                    
                    // Update active tab button
                    tabButtons.forEach(btn => {
                        if (btn === this) {
                            btn.classList.remove('text-gray-500', 'hover:text-gray-700');
                            btn.classList.add('text-green-600', 'active');
                        } else {
                            btn.classList.remove('text-green-600', 'active');
                            btn.classList.add('text-gray-500', 'hover:text-gray-700');
                        }
                    });
                    
                    // Show active tab content
                    tabContents.forEach(content => {
                        if (content.id === tab + '-tab') {
                            content.classList.remove('hidden');
                        } else {
                            content.classList.add('hidden');
                        }
                    });
                });
            });
            
            // Collapsible sections
            document.querySelectorAll('.collapsible-header').forEach(header => {
                header.addEventListener('click', function() {
                    const content = this.nextElementSibling;
                    const icon = this.querySelector('i');
                    content.classList.toggle('hidden');
                    if (content.classList.contains('hidden')) {
                        icon.classList.remove('fa-chevron-up');
                        icon.classList.add('fa-chevron-down');
                    } else {
                        icon.classList.remove('fa-chevron-down');
                        icon.classList.add('fa-chevron-up');
                    }
                });
            });
            
            // Select all permissions for a module
            document.querySelectorAll('.select-all-permissions').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const module = this.getAttribute('data-module');
                    const checkboxes = document.querySelectorAll(`input.permission-checkbox[data-module="${module}"]`);
                    
                    // Check if all are currently selected
                    const allSelected = Array.from(checkboxes).every(checkbox => checkbox.checked);
                    
                    // Toggle selection
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = !allSelected;
                    });
                });
            });
            
            // Permission search functionality
            const permissionSearch = document.getElementById('permission-search');
            if (permissionSearch) {
                permissionSearch.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const permissionItems = document.querySelectorAll('.permission-search-item');
                    
                    permissionItems.forEach(item => {
                        const description = item.getAttribute('data-description');
                        const module = item.getAttribute('data-module');
                        const moduleHeader = item.closest('.permission-group').querySelector('.collapsible-header h4');
                        const moduleName = moduleHeader.textContent.toLowerCase();
                        
                        if (searchTerm === '' || 
                            description.includes(searchTerm) || 
                            moduleName.includes(searchTerm)) {
                            item.closest('.permission-item').style.display = '';
                            // Show the parent module section
                            item.closest('.permission-group').style.display = '';
                        } else {
                            item.closest('.permission-item').style.display = 'none';
                        }
                    });
                    
                    // Hide modules with no matching permissions
                    document.querySelectorAll('.permission-group').forEach(group => {
                        const visibleItems = group.querySelectorAll('.permission-item:not([style*="display: none"])');
                        if (searchTerm !== '' && visibleItems.length === 0) {
                            group.style.display = 'none';
                        } else {
                            group.style.display = '';
                        }
                    });
                });
            }
            
            // Add user button
            addUserBtn.addEventListener('click', function() {
                // Reset form
                document.getElementById('user-id').value = '';
                document.getElementById('user-name').value = '';
                document.getElementById('user-email').value = '';
                document.getElementById('user-phone').value = '';
                document.getElementById('user-address').value = '';
                document.getElementById('user-role').value = '';
                document.getElementById('user-status').value = 'active';
                document.getElementById('user-password').value = '';
                document.getElementById('user-password').required = true;
                
                // Switch to details tab
                document.querySelector('[data-tab="details"]').click();
                
                // Uncheck all permissions
                const permissionCheckboxes = document.querySelectorAll('.permission-checkbox');
                permissionCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                
                // Expand all collapsible sections
                document.querySelectorAll('.collapsible-content').forEach(content => {
                    content.classList.remove('hidden');
                    const icon = content.previousElementSibling.querySelector('i');
                    if (icon) {
                        icon.classList.remove('fa-chevron-down');
                        icon.classList.add('fa-chevron-up');
                    }
                });
                
                // Clear permission search
                if (permissionSearch) {
                    permissionSearch.value = '';
                    // Show all items
                    document.querySelectorAll('.permission-item').forEach(item => {
                        item.style.display = '';
                    });
                    document.querySelectorAll('.permission-group').forEach(group => {
                        group.style.display = '';
                    });
                }
                
                // Set modal for adding
                modalTitle.textContent = 'Add User';
                formAction.value = 'add';
                
                modal.classList.remove('hidden');
            });
            
            // Cancel button
            cancelBtn.addEventListener('click', function() {
                modal.classList.add('hidden');
            });
            
            // Cancel reset button
            cancelResetBtn.addEventListener('click', function() {
                resetModal.classList.add('hidden');
            });
            
            // Close modals when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.classList.add('hidden');
                }
                if (event.target === resetModal) {
                    resetModal.classList.add('hidden');
                }
            });
            
            // User profile dropdown toggle
            const userMenuButton = document.getElementById('user-menu-button');
            const userDropdown = document.getElementById('user-dropdown');
            
            if (userMenuButton && userDropdown) {
                userMenuButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userDropdown.classList.toggle('hidden');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function() {
                    userDropdown.classList.add('hidden');
                });
                
                // Prevent closing when clicking inside dropdown
                userDropdown.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
        });
        
        // Function to edit user
        function editUser(id, username, email, role, status, phone, address) {
            const modal = document.getElementById('user-modal');
            const modalTitle = document.getElementById('modal-title');
            const formAction = document.getElementById('form-action');
            
            // Fill form with user data
            document.getElementById('user-id').value = id;
            document.getElementById('user-name').value = username;
            document.getElementById('user-email').value = email;
            document.getElementById('user-phone').value = phone;
            document.getElementById('user-address').value = address;
            document.getElementById('user-role').value = role;
            document.getElementById('user-status').value = status;
            document.getElementById('user-password').value = '';
            document.getElementById('user-password').required = false;
            
            // Update password placeholder for editing
            document.getElementById('user-password').placeholder = 'Leave blank to keep current password';
            
            // Switch to details tab
            document.querySelector('[data-tab="details"]').click();
            
            // Uncheck all permissions first
            const permissionCheckboxes = document.querySelectorAll('.permission-checkbox');
            permissionCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Expand all collapsible sections
            document.querySelectorAll('.collapsible-content').forEach(content => {
                content.classList.remove('hidden');
                const icon = content.previousElementSibling.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                }
            });
            
            // Clear permission search
            const permissionSearch = document.getElementById('permission-search');
            if (permissionSearch) {
                permissionSearch.value = '';
                // Show all items
                document.querySelectorAll('.permission-item').forEach(item => {
                    item.style.display = '';
                });
                document.querySelectorAll('.permission-group').forEach(group => {
                    group.style.display = '';
                });
            }
            
            // Fetch user permissions and check the appropriate checkboxes
            fetch(`get_user_permissions.php?user_id=${id}`)
                .then(response => response.json())
                .then(permissions => {
                    permissions.forEach(permission => {
                        const checkbox = document.querySelector(`input[name="permissions[]"][value="${permission}"]`);
                        if (checkbox) {
                            checkbox.checked = true;
                        }
                    });
                })
                .catch(error => console.error('Error fetching user permissions:', error));
            
            // Set modal for editing
            modalTitle.textContent = 'Edit User';
            formAction.value = 'edit';
            
            modal.classList.remove('hidden');
        }
        
        // Function to reset password
        function resetPassword(id) {
            const resetModal = document.getElementById('reset-password-modal');
            document.getElementById('reset-user-id').value = id;
            resetModal.classList.remove('hidden');
        }
        
        // Toggle mobile menu
        document.getElementById('menu-toggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            sidebar.classList.toggle('open');
            overlay.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
        });
        
        // Close mobile menu when clicking on overlay
        document.getElementById('overlay').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            sidebar.classList.remove('open');
            overlay.style.display = 'none';
        });
        
        // User dropdown toggle
        document.getElementById('user-menu-button').addEventListener('click', function() {
            const dropdown = document.getElementById('user-dropdown');
            dropdown.classList.toggle('hidden');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('user-dropdown');
            const button = document.getElementById('user-menu-button');
            
            if (!button.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });
    </script>
</body>
</html>