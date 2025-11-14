<?php
session_start();

// Include database connection
include 'api/config/database.php';

// Initialize variables
$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Business information
    $business_name = trim($_POST['business_name'] ?? '');
    $business_type = trim($_POST['business_type'] ?? '');
    $business_email = trim($_POST['business_email'] ?? '');
    $business_phone = trim($_POST['business_phone'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Admin information
    $admin_name = trim($_POST['admin_name'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($business_name) || empty($business_type) || empty($business_email) || 
        empty($admin_name) || empty($admin_email) || empty($password)) {
        $message = "All fields are required.";
        $message_type = "error";
    } elseif (!filter_var($business_email, FILTER_VALIDATE_EMAIL) || !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter valid email addresses.";
        $message_type = "error";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = "error";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters long.";
        $message_type = "error";
    } else {
        // Check if business email or admin email already exists
        $check_query = "SELECT id FROM tenants WHERE business_email = ? UNION SELECT id FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_query);
        if ($check_stmt) {
            $check_stmt->bindParam(1, $business_email, PDO::PARAM_STR);
            $check_stmt->bindParam(2, $admin_email, PDO::PARAM_STR);
            $check_stmt->execute();
            $check_result = $check_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($check_result) > 0) {
                $message = "Business email or admin email already exists.";
                $message_type = "error";
            } else {
                // Generate unique tenant ID
                $tenant_id = 'tenant_' . time() . '_' . rand(100, 999);
                
                // Begin transaction
                $conn->beginTransaction();
                
                try {
                    // Insert tenant
                    $tenant_query = "INSERT INTO tenants (tenant_id, business_name, business_type, business_email, business_phone, country, city, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $tenant_stmt = $conn->prepare($tenant_query);
                    if (!$tenant_stmt) {
                        throw new Exception("Error preparing tenant statement.");
                    }
                    
                    $tenant_stmt->bindParam(1, $tenant_id, PDO::PARAM_STR);
                    $tenant_stmt->bindParam(2, $business_name, PDO::PARAM_STR);
                    $tenant_stmt->bindParam(3, $business_type, PDO::PARAM_STR);
                    $tenant_stmt->bindParam(4, $business_email, PDO::PARAM_STR);
                    $tenant_stmt->bindParam(5, $business_phone, PDO::PARAM_STR);
                    $tenant_stmt->bindParam(6, $country, PDO::PARAM_STR);
                    $tenant_stmt->bindParam(7, $city, PDO::PARAM_STR);
                    $tenant_stmt->bindParam(8, $address, PDO::PARAM_STR);
                    
                    if (!$tenant_stmt->execute()) {
                        throw new Exception("Error creating tenant.");
                    }
                    
                    $tenant_db_id = $conn->lastInsertId();
                    $tenant_stmt->closeCursor();
                    
                    // Insert admin user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $username = str_replace(' ', '_', strtolower($admin_name)) . '_' . $tenant_db_id;
                    
                    $user_query = "INSERT INTO users (tenant_id, username, password, email, phone, address, role, created_at) VALUES (?, ?, ?, ?, ?, ?, 'admin', NOW())";
                    $user_stmt = $conn->prepare($user_query);
                    if (!$user_stmt) {
                        throw new Exception("Error preparing user statement.");
                    }
                    
                    $user_stmt->bindParam(1, $tenant_db_id, PDO::PARAM_INT);
                    $user_stmt->bindParam(2, $username, PDO::PARAM_STR);
                    $user_stmt->bindParam(3, $hashed_password, PDO::PARAM_STR);
                    $user_stmt->bindParam(4, $admin_email, PDO::PARAM_STR);
                    $user_stmt->bindParam(5, $business_phone, PDO::PARAM_STR);
                    $user_stmt->bindParam(6, $address, PDO::PARAM_STR);
                    
                    if (!$user_stmt->execute()) {
                        throw new Exception("Error creating admin user.");
                    }
                    
                    $user_stmt->closeCursor();
                    
                    // Commit transaction
                    $conn->commit();
                    
                    $message = "Tenant registered successfully! You can now log in.";
                    $message_type = "success";
                    
                    // Redirect to login page after successful registration
                    header("refresh:3;url=login.php");
                } catch (Exception $e) {
                    // Rollback transaction
                    $conn->rollback();
                    $message = "Registration failed: " . $e->getMessage();
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
    <title>Register Business - Complete Inventory Management System</title>
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
        <div class="max-w-2xl w-full space-y-8">
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-green-600 to-green-800 px-6 py-4">
                    <h2 class="text-2xl font-bold text-white text-center">Register Your Business</h2>
                    <p class="text-green-200 text-center mt-1">Create your business account to get started</p>
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
                    <form method="POST" class="space-y-6">
                        <div class="border-b border-gray-200 pb-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Business Information</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="business_name">
                                        Business Name *
                                    </label>
                                    <input type="text" id="business_name" name="business_name" required
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="business_type">
                                        Business Type *
                                    </label>
                                    <select id="business_type" name="business_type" required
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                        <option value="">Select Business Type</option>
                                        <option value="Retail">Retail</option>
                                        <option value="Wholesale">Wholesale</option>
                                        <option value="Restaurant">Restaurant</option>
                                        <option value="Manufacturing">Manufacturing</option>
                                        <option value="Service">Service</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="business_email">
                                        Business Email *
                                    </label>
                                    <input type="email" id="business_email" name="business_email" required
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="business_phone">
                                        Business Phone Number
                                    </label>
                                    <input type="text" id="business_phone" name="business_phone"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="country">
                                    Country
                                </label>
                                <input type="text" id="country" name="country"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="city">
                                        City
                                    </label>
                                    <input type="text" id="city" name="city"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="address">
                                        Address
                                    </label>
                                    <input type="text" id="address" name="address"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                </div>
                            </div>
                        </div>
                        
                        <div class="border-b border-gray-200 pb-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Admin Information</h3>
                            
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="admin_name">
                                    Admin Full Name *
                                </label>
                                <input type="text" id="admin_name" name="admin_name" required
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="admin_email">
                                    Admin Email (used for login) *
                                </label>
                                <input type="email" id="admin_email" name="admin_email" required
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
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
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <a href="login.php" class="text-green-600 hover:text-green-800">
                                <i class="fas fa-arrow-left mr-1"></i> Back to Login
                            </a>
                            <button type="submit"
                                class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 focus:outline-none focus:shadow-outline">
                                Register Business
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="text-center text-gray-600 text-sm">
                <p>Already have a tenant account? <a href="register_user.php" class="text-green-600 hover:text-green-800">Register as a user</a></p>
            </div>
        </div>
    </div>
</body>
</html>