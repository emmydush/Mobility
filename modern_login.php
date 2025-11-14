<?php
// Include bootstrap
require_once __DIR__ . '/bootstrap.php';

// Include database connection
use Mobility\Database\Connection;
$db = Connection::getInstance();
$conn = $db->getConnection();

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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mobility Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(-45deg, #1e3a8a, #3b82f6, #60a5fa, #93c5fd);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .floating {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
    </style>
</head>
<body class="min-h-screen gradient-bg">
    <div class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
        <!-- Animated background elements -->
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute top-1/4 left-1/4 w-64 h-64 bg-white opacity-10 rounded-full floating"></div>
            <div class="absolute top-3/4 right-1/4 w-48 h-48 bg-green-200 opacity-20 rounded-full floating" style="animation-delay: -2s;"></div>
            <div class="absolute top-1/2 left-3/4 w-32 h-32 bg-green-300 opacity-15 rounded-full floating" style="animation-delay: -4s;"></div>
        </div>
        
        <!-- Login Form -->
        <div class="w-full max-w-md z-10">
            <div class="bg-white bg-opacity-90 backdrop-blur-lg rounded-2xl shadow-2xl p-8">
                <!-- Logo/Icon -->
                <div class="flex justify-center mb-6">
                    <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-green-700 rounded-xl flex items-center justify-center">
                        <i class="fas fa-boxes text-white text-2xl"></i>
                    </div>
                </div>
                
                <h1 class="text-3xl font-bold text-center text-gray-800 mb-2">
                    Mobility <span class="text-transparent bg-clip-text bg-gradient-to-r from-green-600 to-green-700">Inventory</span>
                </h1>
                
                <p class="text-gray-600 text-center mb-8">Sign in to your account</p>
                
                <?php if (!empty($error_message)): ?>
                    <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                    <div class="mb-4 p-3 bg-green-100 text-green-700 rounded-lg">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="modern_login.php" class="space-y-6">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username or Email</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                required
                                class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                placeholder="Enter your username or email"
                            >
                        </div>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                required
                                class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                placeholder="Enter your password"
                            >
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input 
                                id="remember-me" 
                                name="remember-me" 
                                type="checkbox" 
                                class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded"
                            >
                            <label for="remember-me" class="ml-2 block text-sm text-gray-700">
                                Remember me
                            </label>
                        </div>
                        
                        <div class="text-sm">
                            <a href="#" class="font-medium text-green-600 hover:text-green-500">
                                Forgot password?
                            </a>
                        </div>
                    </div>
                    
                    <div>
                        <button 
                            type="submit"
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-200"
                        >
                            Sign in
                        </button>
                    </div>
                </form>
                
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        Don't have an account? 
                        <a href="#" class="font-medium text-green-600 hover:text-green-500">
                            Contact your administrator
                        </a>
                    </p>
                </div>
            </div>
            
            <!-- Footer text -->
            <div class="text-center mt-6 text-white text-opacity-80">
                <p>© 2025 Mobility Inventory System. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>