<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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
$role = $_SESSION['role'] ?? 'cashier';

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Handle profile picture upload
    $profile_picture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $upload_dir = 'profile_pictures/';
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Validate file size (max 2MB)
        if ($_FILES['profile_picture']['size'] > 2097152) {
            $message = "File size exceeds 2MB limit.";
            $message_type = "error";
        } else {
            // Check if file is an image
            $check = getimagesize($_FILES['profile_picture']['tmp_name']);
            if ($check !== false) {
                // Generate unique filename
                $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                
                // Validate file extension
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                if (in_array($file_extension, $allowed_extensions)) {
                    $file_name = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
                    $target_file = $upload_dir . $file_name;
                    
                    // Move uploaded file
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                        $profile_picture = $target_file;
                    } else {
                        $message = "Error uploading profile picture.";
                        $message_type = "error";
                    }
                } else {
                    $message = "Only JPG, JPEG, PNG & GIF files are allowed.";
                    $message_type = "error";
                }
            } else {
                $message = "File is not an image.";
                $message_type = "error";
            }
        }
    }
    
    if (!empty($email) && empty($message)) { // Only proceed if no upload error
        // Check if email already exists for other users
        $check_query = "SELECT id FROM users WHERE email = ? AND id != ?";
        $check_stmt = $conn->prepare($check_query);
        if ($check_stmt) {
            $check_stmt->bindParam(1, $email, PDO::PARAM_STR);
            $check_stmt->bindParam(2, $user_id, PDO::PARAM_INT);
            $check_stmt->execute();
            $check_result = $check_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($check_result) > 0) {
                $message = "Email already exists for another user.";
                $message_type = "error";
            } else {
                // Update user
                if (!empty($password)) {
                    if ($password !== $confirm_password) {
                        $message = "Passwords do not match.";
                        $message_type = "error";
                    } else {
                        // Update with new password and profile picture if uploaded
                        if ($profile_picture) {
                            $update_query = "UPDATE users SET email = ?, password = ?, phone = ?, address = ?, profile_picture = ? WHERE id = ?";
                            $update_stmt = $conn->prepare($update_query);
                            $update_stmt->bindParam(1, $email, PDO::PARAM_STR);
                            $update_stmt->bindParam(2, password_hash($password, PASSWORD_DEFAULT), PDO::PARAM_STR);
                            $update_stmt->bindParam(3, $phone, PDO::PARAM_STR);
                            $update_stmt->bindParam(4, $address, PDO::PARAM_STR);
                            $update_stmt->bindParam(5, $profile_picture, PDO::PARAM_STR);
                            $update_stmt->bindParam(6, $user_id, PDO::PARAM_INT);
                        } else {
                            $update_query = "UPDATE users SET email = ?, password = ?, phone = ?, address = ? WHERE id = ?";
                            $update_stmt = $conn->prepare($update_query);
                            $update_stmt->bindParam(1, $email, PDO::PARAM_STR);
                            $update_stmt->bindParam(2, password_hash($password, PASSWORD_DEFAULT), PDO::PARAM_STR);
                            $update_stmt->bindParam(3, $phone, PDO::PARAM_STR);
                            $update_stmt->bindParam(4, $address, PDO::PARAM_STR);
                            $update_stmt->bindParam(5, $user_id, PDO::PARAM_INT);
                        }
                    }
                } else {
                    // Update without changing password
                    if ($profile_picture) {
                        $update_query = "UPDATE users SET email = ?, phone = ?, address = ?, profile_picture = ? WHERE id = ?";
                        $update_stmt = $conn->prepare($update_query);
                        $update_stmt->bindParam(1, $email, PDO::PARAM_STR);
                        $update_stmt->bindParam(2, $phone, PDO::PARAM_STR);
                        $update_stmt->bindParam(3, $address, PDO::PARAM_STR);
                        $update_stmt->bindParam(4, $profile_picture, PDO::PARAM_STR);
                        $update_stmt->bindParam(5, $user_id, PDO::PARAM_INT);
                    } else {
                        $update_query = "UPDATE users SET email = ?, phone = ?, address = ? WHERE id = ?";
                        $update_stmt = $conn->prepare($update_query);
                        $update_stmt->bindParam(1, $email, PDO::PARAM_STR);
                        $update_stmt->bindParam(2, $phone, PDO::PARAM_STR);
                        $update_stmt->bindParam(3, $address, PDO::PARAM_STR);
                        $update_stmt->bindParam(4, $user_id, PDO::PARAM_INT);
                    }
                }
                
                if (isset($update_stmt) && $update_stmt->execute()) {
                    // Update session variables if username or email changed
                    $_SESSION['email'] = $email;
                    
                    $message = "Profile updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating profile.";
                    $message_type = "error";
                }
                if (isset($update_stmt)) {
                    $update_stmt->closeCursor();
                }
            }
            $check_stmt->closeCursor();
        } else {
            $message = "Database error.";
            $message_type = "error";
        }
    } else if (empty($message)) { // Only show this error if no upload error
        $message = "Email is required.";
        $message_type = "error";
    }
}

// Fetch current user data
$current_user = null;
$query = "SELECT username, email, phone, address, profile_picture FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bindParam(1, $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $current_user = $result[0] ?? null;
    $stmt->closeCursor();
}
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Complete Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
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
                <a href="users.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-user mr-3"></i>
                    <span><?php echo t('users'); ?></span>
                </a>
                <a href="settings.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-cog mr-3"></i>
                    <span><?php echo t('settings'); ?></span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="flex items-center justify-between p-4 bg-gradient-to-r from-green-600 to-green-800 shadow">
                <h2 class="text-xl font-semibold text-white">My Profile</h2>
                <div class="flex items-center space-x-4">
                    <!-- Language Selector -->
                    <div class="relative">
                        <select onchange="window.location.href=this.value" class="bg-green-600 text-white rounded px-2 py-1 text-sm">
                            <option value="?lang=en" <?php echo ($current_lang == 'en') ? 'selected' : ''; ?>>English</option>
                            <option value="?lang=fr" <?php echo ($current_lang == 'fr') ? 'selected' : ''; ?>>Français</option>
                            <option value="?lang=rw" <?php echo ($current_lang == 'rw') ? 'selected' : ''; ?>>Kinyarwanda</option>
                        </select>
                    </div>
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

            <!-- Profile Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <div class="max-w-2xl mx-auto">
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-800">Edit Profile</h3>
                        </div>
                        <div class="p-6">
                            <form method="POST" class="space-y-6" enctype="multipart/form-data">
                                <div class="flex flex-col items-center mb-6">
                                    <div class="mb-4">
                                        <?php 
                                        $profile_picture = getCurrentUserProfilePicture($conn, $_SESSION['user_id']);
                                        if (!empty($profile_picture)): ?>
                                            <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture" class="w-24 h-24 rounded-full object-cover border-2 border-gray-300">
                                        <?php else: ?>
                                            <div class="w-24 h-24 rounded-full bg-gray-200 flex items-center justify-center border-2 border-gray-300">
                                                <i class="fas fa-user text-gray-500 text-2xl"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="profile_picture">
                                            Profile Picture
                                        </label>
                                        <input type="file" id="profile_picture" name="profile_picture" accept="image/*"
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                        <p class="text-gray-500 text-xs mt-1">JPEG, PNG, GIF (Max 2MB)</p>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="username">
                                        Username
                                    </label>
                                    <input type="text" id="username" value="<?php echo htmlspecialchars($current_user['username'] ?? ''); ?>" readonly
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100">
                                    <p class="text-gray-500 text-xs mt-1">Username cannot be changed</p>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="email">
                                        Email *
                                    </label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($current_user['email'] ?? ''); ?>" required
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="phone">
                                        Phone
                                    </label>
                                    <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="address">
                                        Address
                                    </label>
                                    <textarea id="address" name="address" rows="3"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo htmlspecialchars($current_user['address'] ?? ''); ?></textarea>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                                        New Password
                                    </label>
                                    <input type="password" id="password" name="password"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                        placeholder="Leave blank to keep current password">
                                    <p class="text-gray-500 text-xs mt-1">Leave blank to keep current password</p>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="confirm_password">
                                        Confirm New Password
                                    </label>
                                    <input type="password" id="confirm_password" name="confirm_password"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                        placeholder="Leave blank to keep current password">
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <button type="button" onclick="window.location.href='dashboard.php'" 
                                        class="px-4 py-2 text-sm bg-gray-600 text-white rounded hover:bg-gray-700">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                        class="px-4 py-2 text-sm bg-green-600 text-white rounded hover:bg-green-700">
                                        Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // User profile dropdown toggle
        document.addEventListener('DOMContentLoaded', function() {
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
    </script>
</body>
</html>