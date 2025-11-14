<?php
session_start();

// Check if user is logged in and is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: login.php");
    exit();
}

// Include database connection
include_once 'api/config/database.php';
include_once 'api/config/user_functions.php';

// Set language if specified
if (isset($_GET['lang'])) {
    setCurrentLanguage($_GET['lang']);
}

// Get current language
$current_lang = getCurrentLanguage();

// Get user profile
$user_profile = getCurrentUserProfile($conn, $_SESSION['user_id']);
$username = $user_profile['username'] ?? 'Super Admin';

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_user':
                $username = $_POST['username'] ?? '';
                $email = $_POST['email'] ?? '';
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? '';
                $tenant_id = $_POST['tenant_id'] ?? null;
                $status = $_POST['status'] ?? 'active';
                
                if (!empty($username) && !empty($email) && !empty($password) && !empty($role)) {
                    // Check if username or email already exists
                    $check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
                    $check_stmt = $conn->prepare($check_query);
                    $check_stmt->bind_param("ss", $username, $email);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        $message = t('user_already_exists');
                        $message_type = "error";
                    } else {
                        // Hash password
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        
                        // Insert new user
                        $insert_query = "INSERT INTO users (username, password, email, role, tenant_id, status) VALUES (?, ?, ?, ?, ?, ?)";
                        $insert_stmt = $conn->prepare($insert_query);
                        $insert_stmt->bind_param("ssssis", $username, $hashed_password, $email, $role, $tenant_id, $status);
                        
                        if ($insert_stmt->execute()) {
                            $message = t('user_added_successfully');
                            $message_type = "success";
                        } else {
                            $message = t('error_adding_user') . $conn->error;
                            $message_type = "error";
                        }
                        $insert_stmt->close();
                    }
                    $check_stmt->close();
                } else {
                    $message = t('please_fill_required_fields');
                    $message_type = "error";
                }
                break;
                
            case 'update_user':
                $id = $_POST['id'] ?? 0;
                $username = $_POST['username'] ?? '';
                $email = $_POST['email'] ?? '';
                $role = $_POST['role'] ?? '';
                $tenant_id = $_POST['tenant_id'] ?? null;
                $status = $_POST['status'] ?? 'active';
                
                if (!empty($id) && !empty($username) && !empty($email) && !empty($role)) {
                    // Check if username or email already exists for other users
                    $check_query = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
                    $check_stmt = $conn->prepare($check_query);
                    $check_stmt->bind_param("ssi", $username, $email, $id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        $message = t('another_user_exists');
                        $message_type = "error";
                    } else {
                        // Update user
                        $update_query = "UPDATE users SET username = ?, email = ?, role = ?, tenant_id = ?, status = ? WHERE id = ?";
                        $update_stmt = $conn->prepare($update_query);
                        $update_stmt->bind_param("sssssi", $username, $email, $role, $tenant_id, $status, $id);
                        
                        if ($update_stmt->execute()) {
                            $message = t('user_updated_successfully');
                            $message_type = "success";
                        } else {
                            $message = t('error_updating_user') . $conn->error;
                            $message_type = "error";
                        }
                        $update_stmt->close();
                    }
                    $check_stmt->close();
                } else {
                    $message = t('please_fill_required_fields');
                    $message_type = "error";
                }
                break;
                
            case 'delete_user':
                $id = $_POST['id'] ?? 0;
                
                if (!empty($id)) {
                    // Delete user
                    $delete_query = "DELETE FROM users WHERE id = ?";
                    $delete_stmt = $conn->prepare($delete_query);
                    $delete_stmt->bind_param("i", $id);
                    
                    if ($delete_stmt->execute()) {
                        $message = t('user_deleted_successfully');
                        $message_type = "success";
                    } else {
                        $message = t('error_deleting_user') . $conn->error;
                        $message_type = "error";
                    }
                    $delete_stmt->close();
                } else {
                    $message = t('invalid_user_id');
                    $message_type = "error";
                }
                break;
                
            case 'reset_password':
                $id = $_POST['id'] ?? 0;
                $new_password = $_POST['new_password'] ?? '';
                
                if (!empty($id) && !empty($new_password)) {
                    // Hash new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update user password
                    $update_query = "UPDATE users SET password = ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("si", $hashed_password, $id);
                    
                    if ($update_stmt->execute()) {
                        $message = t('password_reset_successfully');
                        $message_type = "success";
                    } else {
                        $message = t('error_resetting_password') . $conn->error;
                        $message_type = "error";
                    }
                    $update_stmt->close();
                } else {
                    $message = t('please_provide_new_password');
                    $message_type = "error";
                }
                break;
        }
    }
}

// Fetch all users with tenant information
$users = [];
$users_query = "SELECT u.id, u.username, u.email, u.role, u.status, u.created_at, t.business_name 
                FROM users u 
                LEFT JOIN tenants t ON u.tenant_id = t.id 
                ORDER BY u.created_at DESC";
$users_result = $conn->query($users_query);
if ($users_result) {
    while ($row = $users_result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Fetch all tenants for the dropdown
$tenants = [];
$tenants_query = "SELECT id, business_name FROM tenants WHERE status = 'active' ORDER BY business_name";
$tenants_result = $conn->query($tenants_query);
if ($tenants_result) {
    while ($row = $tenants_result->fetch_assoc()) {
        $tenants[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('users_management'); ?> - Super Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-gradient-to-b from-green-700 to-green-900 shadow-lg">
            <div class="p-4 border-b border-green-700">
                <h1 class="text-xl font-bold text-white">IMS Super Admin</h1>
                <p class="text-sm text-green-200"><?php echo t('system_management'); ?></p>
            </div>
            <nav class="mt-4">
                <a href="super_admin_dashboard.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    <span><?php echo t('dashboard'); ?></span>
                </a>
                <a href="super_admin_tenants.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-building mr-3"></i>
                    <span><?php echo t('tenants_management'); ?></span>
                </a>
                <a href="super_admin_users.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-white bg-gradient-to-r from-green-600 to-green-700 border-l-4 border-green-300">
                    <i class="fas fa-users mr-3"></i>
                    <span><?php echo t('users_management'); ?></span>
                </a>
                <a href="super_admin_security.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-shield-alt mr-3"></i>
                    <span><?php echo t('security'); ?></span>
                </a>
                <a href="super_admin_settings.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-cog mr-3"></i>
                    <span><?php echo t('settings'); ?></span>
                </a>
                <a href="super_admin_reports.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-chart-bar mr-3"></i>
                    <span><?php echo t('reports'); ?></span>
                </a>
                <a href="logout.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    <span><?php echo t('logout'); ?></span>
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
                        <i class="fas fa-plus mr-1"></i> <?php echo t('add_user'); ?>
                    </button>
                    <!-- User Profile Dropdown -->
                    <div class="relative">
                        <button id="user-menu-button" class="flex items-center space-x-2 text-white focus:outline-none">
                            <div class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center">
                                <i class="fas fa-user text-white"></i>
                            </div>
                            <div class="text-left hidden md:block">
                                <p class="text-sm font-medium text-white"><?php echo htmlspecialchars($username); ?></p>
                                <p class="text-xs text-green-100 capitalize"><?php echo t('super_admin'); ?></p>
                            </div>
                            <i class="fas fa-chevron-down text-green-200 text-xs"></i>
                        </button>
                        
                        <!-- Dropdown menu -->
                        <div id="user-dropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 hidden z-50">
                            <div class="px-4 py-2 border-b border-gray-200">
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($username); ?></p>
                                <p class="text-xs text-gray-500 capitalize"><?php echo t('super_admin'); ?></p>
                            </div>
                            <a href="profile.php?lang=<?php echo $current_lang; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user-circle mr-2"></i><?php echo t('profile'); ?>
                            </a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
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
                <!-- Add User Modal -->
                <div id="user-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
                    <div class="relative top-10 mx-auto p-4 border w-full max-w-2xl shadow-lg rounded-md bg-white">
                        <div class="mt-3">
                            <h3 class="text-lg font-medium text-gray-900 mb-4"><?php echo t('add_new_user'); ?></h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_user">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="username">
                                            <?php echo t('username'); ?> *
                                        </label>
                                        <input type="text" id="username" name="username" required
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="email">
                                            <?php echo t('email'); ?> *
                                        </label>
                                        <input type="email" id="email" name="email" required
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="password">
                                            <?php echo t('password'); ?> *
                                        </label>
                                        <input type="password" id="password" name="password" required
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="role">
                                            <?php echo t('role'); ?> *
                                        </label>
                                        <select id="role" name="role" required
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                            <option value="super_admin"><?php echo t('super_admin'); ?></option>
                                            <option value="admin"><?php echo t('admin'); ?></option>
                                            <option value="manager"><?php echo t('manager'); ?></option>
                                            <option value="cashier"><?php echo t('cashier'); ?></option>
                                            <option value="supervisor"><?php echo t('supervisor'); ?></option>
                                            <option value="staff"><?php echo t('staff'); ?></option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="tenant-id">
                                            <?php echo t('tenant'); ?>
                                        </label>
                                        <select id="tenant-id" name="tenant_id"
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                            <option value=""><?php echo t('no_tenant'); ?></option>
                                            <?php foreach ($tenants as $tenant): ?>
                                                <option value="<?php echo $tenant['id']; ?>"><?php echo htmlspecialchars($tenant['business_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="status">
                                            <?php echo t('status'); ?>
                                        </label>
                                        <select id="status" name="status"
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                            <option value="active"><?php echo t('active'); ?></option>
                                            <option value="inactive"><?php echo t('inactive'); ?></option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="flex items-center justify-between mt-6">
                                    <button type="button" id="cancel-user-btn"
                                        class="px-4 py-2 text-sm bg-gray-600 text-white rounded hover:bg-gray-700">
                                        <?php echo t('cancel'); ?>
                                    </button>
                                    <button type="submit"
                                        class="px-4 py-2 text-sm bg-green-600 text-white rounded hover:bg-green-700">
                                        <?php echo t('add_user'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Users List -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-800"><?php echo t('users_list'); ?></h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('user'); ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('role'); ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('tenant'); ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('status'); ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('created'); ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                            <?php echo t('no_users_found'); ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['username']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 capitalize">
                                                    <?php echo htmlspecialchars($user['role']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($user['business_name'] ?? t('no_tenant')); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?php echo $user['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                    <?php echo ucfirst($user['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button class="edit-user-btn text-green-600 hover:text-green-900 mr-3" 
                                                        data-id="<?php echo $user['id']; ?>"
                                                        data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                        data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                        data-role="<?php echo htmlspecialchars($user['role']); ?>"
                                                        data-tenant-id="<?php echo htmlspecialchars($user['tenant_id'] ?? ''); ?>"
                                                        data-status="<?php echo htmlspecialchars($user['status']); ?>">
                                                    <?php echo t('edit'); ?>
                                                </button>
                                                <button class="reset-password-btn text-yellow-600 hover:text-yellow-900 mr-3"
                                                        data-id="<?php echo $user['id']; ?>"
                                                        data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                                    <?php echo t('reset_password'); ?>
                                                </button>
                                                <form method="POST" class="inline" onsubmit="return confirm('<?php echo t('delete_confirmation'); ?>')">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-900"><?php echo t('delete'); ?></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Toggle add user modal
        document.getElementById('add-user-btn').addEventListener('click', function() {
            document.getElementById('user-modal').classList.remove('hidden');
        });
        
        document.getElementById('cancel-user-btn').addEventListener('click', function() {
            document.getElementById('user-modal').classList.add('hidden');
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('user-modal');
            if (event.target === modal) {
                modal.classList.add('hidden');
            }
        });
        
        // Edit user functionality
        document.querySelectorAll('.edit-user-btn').forEach(button => {
            button.addEventListener('click', function() {
                // Get user data from button attributes
                const id = this.getAttribute('data-id');
                const username = this.getAttribute('data-username');
                const email = this.getAttribute('data-email');
                const role = this.getAttribute('data-role');
                const tenantId = this.getAttribute('data-tenant-id');
                const status = this.getAttribute('data-status');
                
                // Populate form fields
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="id" value="${id}">
                    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                        <div class="relative top-10 mx-auto p-4 border w-full max-w-2xl shadow-lg rounded-md bg-white">
                            <div class="mt-3">
                                <h3 class="text-lg font-medium text-gray-900 mb-4"><?php echo t('edit_user'); ?></h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="edit-username">
                                            <?php echo t('username'); ?> *
                                        </label>
                                        <input type="text" id="edit-username" name="username" value="${username}" required
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="edit-email">
                                            <?php echo t('email'); ?> *
                                        </label>
                                        <input type="email" id="edit-email" name="email" value="${email}" required
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="edit-role">
                                            <?php echo t('role'); ?> *
                                        </label>
                                        <select id="edit-role" name="role" required
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                            <option value="super_admin" ${role === 'super_admin' ? 'selected' : ''}>${"<?php echo t('super_admin'); ?>"}</option>
                                            <option value="admin" ${role === 'admin' ? 'selected' : ''}>${"<?php echo t('admin'); ?>"}</option>
                                            <option value="manager" ${role === 'manager' ? 'selected' : ''}>${"<?php echo t('manager'); ?>"}</option>
                                            <option value="cashier" ${role === 'cashier' ? 'selected' : ''}>${"<?php echo t('cashier'); ?>"}</option>
                                            <option value="supervisor" ${role === 'supervisor' ? 'selected' : ''}>${"<?php echo t('supervisor'); ?>"}</option>
                                            <option value="staff" ${role === 'staff' ? 'selected' : ''}>${"<?php echo t('staff'); ?>"}</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="edit-tenant-id">
                                            <?php echo t('tenant'); ?>
                                        </label>
                                        <select id="edit-tenant-id" name="tenant_id"
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                            <option value=""><?php echo t('no_tenant'); ?></option>
                                            <?php foreach ($tenants as $tenant): ?>
                                                <option value="<?php echo $tenant['id']; ?>" ${tenantId == <?php echo $tenant['id']; ?> ? 'selected' : ''}>
                                                    <?php echo htmlspecialchars($tenant['business_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="edit-status">
                                            <?php echo t('status'); ?>
                                        </label>
                                        <select id="edit-status" name="status"
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                            <option value="active" ${status === 'active' ? 'selected' : ''}>${"<?php echo t('active'); ?>"}</option>
                                            <option value="inactive" ${status === 'inactive' ? 'selected' : ''}>${"<?php echo t('inactive'); ?>"}</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="flex items-center justify-between mt-6">
                                    <button type="button" onclick="this.closest('.fixed').remove()"
                                        class="px-4 py-2 text-sm bg-gray-600 text-white rounded hover:bg-gray-700">
                                        <?php echo t('cancel'); ?>
                                    </button>
                                    <button type="submit"
                                        class="px-4 py-2 text-sm bg-green-600 text-white rounded hover:bg-green-700">
                                        <?php echo t('update_user'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(form);
            });
        });
        
        // Reset password functionality
        document.querySelectorAll('.reset-password-btn').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const username = this.getAttribute('data-username');
                
                // Create password reset form
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="id" value="${id}">
                    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                        <div class="relative top-10 mx-auto p-4 border w-full max-w-md shadow-lg rounded-md bg-white">
                            <div class="mt-3">
                                <h3 class="text-lg font-medium text-gray-900 mb-4"><?php echo t('reset_password_for'); ?> ${username}</h3>
                                
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-1" for="new-password">
                                        <?php echo t('new_password'); ?> *
                                    </label>
                                    <input type="password" id="new-password" name="new_password" required
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                </div>
                                
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-1" for="confirm-password">
                                        <?php echo t('confirm_password'); ?> *
                                    </label>
                                    <input type="password" id="confirm-password" required
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                </div>
                                
                                <div class="flex items-center justify-between mt-6">
                                    <button type="button" onclick="this.closest('.fixed').remove()"
                                        class="px-4 py-2 text-sm bg-gray-600 text-white rounded hover:bg-gray-700">
                                        <?php echo t('cancel'); ?>
                                    </button>
                                    <button type="submit"
                                        class="px-4 py-2 text-sm bg-green-600 text-white rounded hover:bg-green-700"
                                        onclick="return validatePasswordReset(event)">
                                        <?php echo t('reset_password'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(form);
            });
        });
        
        // Validate password reset
        function validatePasswordReset(event) {
            const form = event.target.closest('form');
            const newPassword = form.querySelector('#new-password').value;
            const confirmPassword = form.querySelector('#confirm-password').value;
            
            if (newPassword !== confirmPassword) {
                alert('<?php echo t('passwords_do_not_match'); ?>');
                event.preventDefault();
                return false;
            }
            
            return true;
        }
        
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