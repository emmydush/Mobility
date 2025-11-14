<?php
session_start();

// Include database connection
include 'api/config/database.php';

// Initialize variables
$message = '';
$message_type = '';
$tenant_name = '';

// Check if tenant admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // If not logged in as admin, show tenant ID input form
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['tenant_id'])) {
        // Show tenant ID input form
        include 'tenant_id_form.php';
        exit();
    } else {
        // Validate tenant ID
        $tenant_id = trim($_POST['tenant_id'] ?? '');
        if (empty($tenant_id)) {
            $message = "Tenant ID is required.";
            $message_type = "error";
        } else {
            // Get tenant information
            $tenant_query = "SELECT id, business_name FROM tenants WHERE tenant_id = ? AND status = 'active'";
            $tenant_stmt = $conn->prepare($tenant_query);
            if ($tenant_stmt) {
                $tenant_stmt->bindParam(1, $tenant_id, PDO::PARAM_STR);
                $tenant_stmt->execute();
                $tenant_result = $tenant_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($tenant_result) > 0) {
                    $tenant = $tenant_result[0];
                    $_SESSION['tenant_id'] = $tenant['id'];
                    $tenant_name = $tenant['business_name'];
                } else {
                    $message = "Invalid or inactive tenant ID.";
                    $message_type = "error";
                }
                $tenant_stmt->closeCursor();
            } else {
                $message = "Database error.";
                $message_type = "error";
            }
        }
    }
} else {
    // Admin is logged in, get tenant from session
    $tenant_query = "SELECT t.id, t.business_name FROM tenants t JOIN users u ON t.id = u.tenant_id WHERE u.id = ?";
    $tenant_stmt = $conn->prepare($tenant_query);
    if ($tenant_stmt) {
        $tenant_stmt->bindParam(1, $_SESSION['user_id'], PDO::PARAM_INT);
        $tenant_stmt->execute();
        $tenant_result = $tenant_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($tenant_result) > 0) {
            $tenant = $tenant_result[0];
            $_SESSION['tenant_id'] = $tenant['id'];
            $tenant_name = $tenant['business_name'];
        }
        $tenant_stmt->closeCursor();
    }
}

// Handle user registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_user']) && !empty($_SESSION['tenant_id'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($full_name) || empty($email) || empty($role) || empty($password)) {
        $message = "All fields except phone are required.";
        $message_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = "error";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = "error";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters long.";
        $message_type = "error";
    } else {
        // Check if email already exists
        $check_query = "SELECT id FROM users WHERE email = ? AND tenant_id = ?";
        $check_stmt = $conn->prepare($check_query);
        if ($check_stmt) {
            $check_stmt->bindParam(1, $email, PDO::PARAM_STR);
            $check_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
            $check_stmt->execute();
            $check_result = $check_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($check_result) > 0) {
                $message = "Email already exists for this tenant.";
                $message_type = "error";
            } else {
                // Insert user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $username = str_replace(' ', '_', strtolower($full_name)) . '_' . time();
                
                $user_query = "INSERT INTO users (tenant_id, username, password, email, phone, role, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
                $user_stmt = $conn->prepare($user_query);
                if ($user_stmt) {
                    $user_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
                    $user_stmt->bindParam(2, $username, PDO::PARAM_STR);
                    $user_stmt->bindParam(3, $hashed_password, PDO::PARAM_STR);
                    $user_stmt->bindParam(4, $email, PDO::PARAM_STR);
                    $user_stmt->bindParam(5, $phone, PDO::PARAM_STR);
                    $user_stmt->bindParam(6, $role, PDO::PARAM_STR);
                    
                    if ($user_stmt->execute()) {
                        $message = "User registered successfully!";
                        $message_type = "success";
                        // Clear form fields
                        $full_name = $email = $phone = '';
                    } else {
                        $message = "Error creating user.";
                        $message_type = "error";
                    }
                    $user_stmt->closeCursor();
                } else {
                    $message = "Database error.";
                    $message_type = "error";
                }
            }
            $check_stmt->closeCursor();
        } else {
            $message = "Database error.";
            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register User - Complete Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-gradient-to-r from-green-600 to-green-800 shadow-lg fixed top-0 w-full z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-boxes text-white text-xl mr-2"></i>
                    <span class="text-white text-xl font-bold">Mobility Inventory</span>
                </div>
                <nav>
                    <a href="login.php" class="text-green-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Login</a>
                </nav>
            </div>
        </div>
    </header>
    
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8" style="padding-top: 5rem;">
        <div class="max-w-md w-full space-y-8">
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-green-600 to-green-800 px-6 py-4">
                    <h2 class="text-2xl font-bold text-white text-center">Register User</h2>
                    <?php if (!empty($tenant_name)): ?>
                        <p class="text-green-200 text-center mt-1">For <?php echo htmlspecialchars($tenant_name); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Message Display -->
                <?php if ($message): ?>
                    <div class="p-4 <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                        <div class="container mx-auto">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="px-6 py-6">
                    <?php if (empty($_SESSION['tenant_id'])): ?>
                        <form method="POST" class="space-y-4">
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="tenant_id">
                                    Tenant ID *
                                </label>
                                <input type="text" id="tenant_id" name="tenant_id" required
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                    placeholder="Enter your tenant ID">
                                <p class="text-gray-500 text-xs mt-1">Provided when your business was registered</p>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <a href="register_tenant.php" class="text-green-600 hover:text-green-800">
                                    <i class="fas fa-building mr-1"></i> Register Business
                                </a>
                                <button type="submit"
                                    class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 focus:outline-none focus:shadow-outline">
                                    Continue
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="register_user" value="1">
                            
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="full_name">
                                    Full Name *
                                </label>
                                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name ?? ''); ?>" required
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="email">
                                    Email *
                                </label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="phone">
                                    Phone Number
                                </label>
                                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($phone ?? ''); ?>"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="role">
                                    Role *
                                </label>
                                <select id="role" name="role" required
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    <option value="">Select Role</option>
                                    <option value="manager" <?php echo (isset($_POST['role']) && $_POST['role'] === 'manager') ? 'selected' : ''; ?>>Manager</option>
                                    <option value="cashier" <?php echo (isset($_POST['role']) && $_POST['role'] === 'cashier') ? 'selected' : ''; ?>>Cashier</option>
                                    <option value="stock_keeper" <?php echo (isset($_POST['role']) && $_POST['role'] === 'stock_keeper') ? 'selected' : ''; ?>>Stock Keeper</option>
                                    <option value="staff" <?php echo (isset($_POST['role']) && $_POST['role'] === 'staff') ? 'selected' : ''; ?>>Staff</option>
                                </select>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                                        Password *
                                    </label>
                                    <input type="password" id="password" name="password" required
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    <p class="text-gray-500 text-xs mt-1">At least 6 characters</p>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="confirm_password">
                                        Confirm Password *
                                    </label>
                                    <input type="password" id="confirm_password" name="confirm_password" required
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin'): ?>
                                    <a href="dashboard.php" class="text-green-600 hover:text-green-800">
                                        <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
                                    </a>
                                <?php else: ?>
                                    <a href="login.php" class="text-green-600 hover:text-green-800">
                                        <i class="fas fa-arrow-left mr-1"></i> Back to Login
                                    </a>
                                <?php endif; ?>
                                <button type="submit"
                                    class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 focus:outline-none focus:shadow-outline">
                                    Register User
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="text-center text-gray-600 text-sm">
                <p>Need to register a business? <a href="register_tenant.php" class="text-green-600 hover:text-green-800">Register Business</a></p>
            </div>
        </div>
    </div>
</body>
</html>