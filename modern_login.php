<?php
session_start();

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

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        // Check if user exists and get tenant information
        $query = "SELECT u.id, u.username, u.password, u.role, t.tenant_id, t.business_name FROM users u LEFT JOIN tenants t ON u.tenant_id = t.id WHERE u.username = ? OR u.email = ?";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bindParam(1, $username, PDO::PARAM_STR);
            $stmt->bindParam(2, $username, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($result) > 0) {
                $user = $result[0];
                if (password_verify($password, $user['password'])) {
                    // Check if user is a super admin
                    if ($user['role'] === 'super_admin') {
                        // Super admin login successful
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        
                        // Redirect to super admin dashboard
                        header("Location: super_admin_dashboard.php");
                        exit();
                    }
                    // Check if tenant is active
                    else if ($user['tenant_id'] && !$user['business_name']) {
                        $error_message = "Your business account is inactive. Please contact support.";
                    } else if (!$user['tenant_id']) {
                        $error_message = "No business account assigned to your user. Please contact support.";
                    } else {
                        // Regular user login successful
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['tenant_id'] = $user['tenant_id'];
                        
                        // Redirect to dashboard
                        header("Location: dashboard.php");
                        exit();
                    }
                } else {
                    $error_message = "Invalid password";
                }
            } else {
                $error_message = "User not found";
            }
        } else {
            $error_message = "Database error";
        }
    } else {
        $error_message = "Username and password are required";
    }
}

$conn = null;
?>