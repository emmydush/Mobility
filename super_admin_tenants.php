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
            case 'add_tenant':
                $tenant_id = $_POST['tenant_id'] ?? '';
                $business_name = $_POST['business_name'] ?? '';
                $business_type = $_POST['business_type'] ?? '';
                $business_email = $_POST['business_email'] ?? '';
                $business_phone = $_POST['business_phone'] ?? '';
                $country = $_POST['country'] ?? '';
                $city = $_POST['city'] ?? '';
                $address = $_POST['address'] ?? '';
                $status = $_POST['status'] ?? 'active';
                
                if (!empty($tenant_id) && !empty($business_name) && !empty($business_email)) {
                    // Check if tenant_id already exists
                    $check_query = "SELECT id FROM tenants WHERE tenant_id = ?";
                    $check_stmt = $conn->prepare($check_query);
                    $check_stmt->bind_param("s", $tenant_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        $message = t('tenant_already_exists');
                        $message_type = "error";
                    } else {
                        // Insert new tenant
                        $insert_query = "INSERT INTO tenants (tenant_id, business_name, business_type, business_email, business_phone, country, city, address, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $insert_stmt = $conn->prepare($insert_query);
                        $insert_stmt->bind_param("sssssssss", $tenant_id, $business_name, $business_type, $business_email, $business_phone, $country, $city, $address, $status);
                        
                        if ($insert_stmt->execute()) {
                            $message = t('tenant_added_successfully');
                            $message_type = "success";
                        } else {
                            $message = t('error_adding_tenant') . $conn->error;
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
                
            case 'update_tenant':
                $id = $_POST['id'] ?? 0;
                $business_name = $_POST['business_name'] ?? '';
                $business_type = $_POST['business_type'] ?? '';
                $business_email = $_POST['business_email'] ?? '';
                $business_phone = $_POST['business_phone'] ?? '';
                $country = $_POST['country'] ?? '';
                $city = $_POST['city'] ?? '';
                $address = $_POST['address'] ?? '';
                $status = $_POST['status'] ?? 'active';
                
                if (!empty($id) && !empty($business_name) && !empty($business_email)) {
                    // Update tenant
                    $update_query = "UPDATE tenants SET business_name = ?, business_type = ?, business_email = ?, business_phone = ?, country = ?, city = ?, address = ?, status = ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("ssssssssi", $business_name, $business_type, $business_email, $business_phone, $country, $city, $address, $status, $id);
                    
                    if ($update_stmt->execute()) {
                        $message = t('tenant_updated_successfully');
                        $message_type = "success";
                    } else {
                        $message = t('error_updating_tenant') . $conn->error;
                        $message_type = "error";
                    }
                    $update_stmt->close();
                } else {
                    $message = t('please_fill_required_fields');
                    $message_type = "error";
                }
                break;
                
            case 'delete_tenant':
                $id = $_POST['id'] ?? 0;
                
                if (!empty($id)) {
                    // Delete tenant
                    $delete_query = "DELETE FROM tenants WHERE id = ?";
                    $delete_stmt = $conn->prepare($delete_query);
                    $delete_stmt->bind_param("i", $id);
                    
                    if ($delete_stmt->execute()) {
                        $message = t('tenant_deleted_successfully');
                        $message_type = "success";
                    } else {
                        $message = t('error_deleting_tenant') . $conn->error;
                        $message_type = "error";
                    }
                    $delete_stmt->close();
                } else {
                    $message = t('invalid_tenant_id');
                    $message_type = "error";
                }
                break;
        }
    }
}

// Fetch all tenants
$tenants = [];
$tenants_query = "SELECT id, tenant_id, business_name, business_type, business_email, business_phone, country, city, address, status, created_at FROM tenants ORDER BY created_at DESC";
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
    <title><?php echo t('tenants_management'); ?> - Super Admin Dashboard</title>
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
                <a href="super_admin_tenants.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-white bg-gradient-to-r from-green-600 to-green-700 border-l-4 border-green-300">
                    <i class="fas fa-building mr-3"></i>
                    <span><?php echo t('tenants_management'); ?></span>
                </a>
                <a href="super_admin_users.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
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
                <h2 class="text-xl font-semibold text-white"><?php echo t('tenants_management'); ?></h2>
                <div class="flex items-center space-x-4">
                    <!-- Language Selector -->
                    <div class="relative">
                        <select onchange="window.location.href=this.value" class="bg-green-600 text-white rounded px-2 py-1 text-sm">
                            <option value="?lang=en" <?php echo ($current_lang == 'en') ? 'selected' : ''; ?>>English</option>
                            <option value="?lang=fr" <?php echo ($current_lang == 'fr') ? 'selected' : ''; ?>>Français</option>
                            <option value="?lang=rw" <?php echo ($current_lang == 'rw') ? 'selected' : ''; ?>>Kinyarwanda</option>
                        </select>
                    </div>
                    <button id="add-tenant-btn" class="px-4 py-2 text-sm bg-white text-green-600 rounded hover:bg-green-50 transition duration-200">
                        <i class="fas fa-plus mr-1"></i> <?php echo t('add_tenant'); ?>
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

            <!-- Tenants Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <!-- Add Tenant Modal -->
                <div id="tenant-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
                    <div class="relative top-10 mx-auto p-4 border w-full max-w-2xl shadow-lg rounded-md bg-white">
                        <div class="mt-3">
                            <h3 class="text-lg font-medium text-gray-900 mb-4"><?php echo t('add_new_tenant'); ?></h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_tenant">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="tenant-id">
                                            <?php echo t('tenant_id'); ?> *
                                        </label>
                                        <input type="text" id="tenant-id" name="tenant_id" required
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="business-name">
                                            <?php echo t('business_name'); ?> *
                                        </label>
                                        <input type="text" id="business-name" name="business_name" required
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="business-type">
                                            <?php echo t('business_type'); ?>
                                        </label>
                                        <select id="business-type" name="business_type"
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                            <option value="Retail"><?php echo t('retail'); ?></option>
                                            <option value="Wholesale"><?php echo t('wholesale'); ?></option>
                                            <option value="Restaurant"><?php echo t('restaurant'); ?></option>
                                            <option value="Manufacturing"><?php echo t('manufacturing'); ?></option>
                                            <option value="Service"><?php echo t('service'); ?></option>
                                            <option value="Other"><?php echo t('other'); ?></option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="business-email">
                                            <?php echo t('business_email'); ?> *
                                        </label>
                                        <input type="email" id="business-email" name="business_email" required
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="business-phone">
                                            <?php echo t('business_phone'); ?>
                                        </label>
                                        <input type="text" id="business-phone" name="business_phone"
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
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
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="country">
                                            <?php echo t('country'); ?>
                                        </label>
                                        <input type="text" id="country" name="country"
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="city">
                                            <?php echo t('city'); ?>
                                        </label>
                                        <input type="text" id="city" name="city"
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-1" for="address">
                                        <?php echo t('address'); ?>
                                    </label>
                                    <textarea id="address" name="address" rows="2"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm"></textarea>
                                </div>
                                
                                <div class="flex items-center justify-between mt-6">
                                    <button type="button" id="cancel-tenant-btn"
                                        class="px-4 py-2 text-sm bg-gray-600 text-white rounded hover:bg-gray-700">
                                        <?php echo t('cancel'); ?>
                                    </button>
                                    <button type="submit"
                                        class="px-4 py-2 text-sm bg-green-600 text-white rounded hover:bg-green-700">
                                        <?php echo t('add_tenant'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Tenants List -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-800"><?php echo t('tenants_list'); ?></h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('business'); ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('type'); ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('contact'); ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('location'); ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('status'); ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('created'); ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($tenants)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                            <?php echo t('no_tenants_found'); ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tenants as $tenant): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($tenant['business_name']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($tenant['tenant_id']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($tenant['business_type']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($tenant['business_email']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($tenant['business_phone'] ?? 'N/A'); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($tenant['city'] ?? 'N/A'); ?>, <?php echo htmlspecialchars($tenant['country'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?php echo $tenant['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                    <?php echo ucfirst($tenant['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo date('M j, Y', strtotime($tenant['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button class="edit-tenant-btn text-green-600 hover:text-green-900 mr-3" 
                                                        data-id="<?php echo $tenant['id']; ?>"
                                                        data-tenant-id="<?php echo htmlspecialchars($tenant['tenant_id']); ?>"
                                                        data-business-name="<?php echo htmlspecialchars($tenant['business_name']); ?>"
                                                        data-business-type="<?php echo htmlspecialchars($tenant['business_type']); ?>"
                                                        data-business-email="<?php echo htmlspecialchars($tenant['business_email']); ?>"
                                                        data-business-phone="<?php echo htmlspecialchars($tenant['business_phone'] ?? ''); ?>"
                                                        data-country="<?php echo htmlspecialchars($tenant['country'] ?? ''); ?>"
                                                        data-city="<?php echo htmlspecialchars($tenant['city'] ?? ''); ?>"
                                                        data-address="<?php echo htmlspecialchars($tenant['address'] ?? ''); ?>"
                                                        data-status="<?php echo htmlspecialchars($tenant['status']); ?>">
                                                    <?php echo t('edit'); ?>
                                                </button>
                                                <form method="POST" class="inline" onsubmit="return confirm('<?php echo t('delete_confirmation'); ?>')">
                                                    <input type="hidden" name="action" value="delete_tenant">
                                                    <input type="hidden" name="id" value="<?php echo $tenant['id']; ?>">
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
        // Toggle add tenant modal
        document.getElementById('add-tenant-btn').addEventListener('click', function() {
            document.getElementById('tenant-modal').classList.remove('hidden');
        });
        
        document.getElementById('cancel-tenant-btn').addEventListener('click', function() {
            document.getElementById('tenant-modal').classList.add('hidden');
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('tenant-modal');
            if (event.target === modal) {
                modal.classList.add('hidden');
            }
        });
        
        // Edit tenant functionality
        document.querySelectorAll('.edit-tenant-btn').forEach(button => {
            button.addEventListener('click', function() {
                // Get tenant data from button attributes
                const id = this.getAttribute('data-id');
                const tenantId = this.getAttribute('data-tenant-id');
                const businessName = this.getAttribute('data-business-name');
                const businessType = this.getAttribute('data-business-type');
                const businessEmail = this.getAttribute('data-business-email');
                const businessPhone = this.getAttribute('data-business-phone');
                const country = this.getAttribute('data-country');
                const city = this.getAttribute('data-city');
                const address = this.getAttribute('data-address');
                const status = this.getAttribute('data-status');
                
                // Populate form fields
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_tenant">
                    <input type="hidden" name="id" value="${id}">
                    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                        <div class="relative top-10 mx-auto p-4 border w-full max-w-2xl shadow-lg rounded-md bg-white">
                            <div class="mt-3">
                                <h3 class="text-lg font-medium text-gray-900 mb-4"><?php echo t('edit_tenant'); ?></h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="edit-tenant-id">
                                            <?php echo t('tenant_id'); ?> *
                                        </label>
                                        <input type="text" id="edit-tenant-id" name="tenant_id" value="${tenantId}" required readonly
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm bg-gray-100">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="edit-business-name">
                                            <?php echo t('business_name'); ?> *
                                        </label>
                                        <input type="text" id="edit-business-name" name="business_name" value="${businessName}" required
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="edit-business-type">
                                            <?php echo t('business_type'); ?>
                                        </label>
                                        <select id="edit-business-type" name="business_type"
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                            <option value="Retail" ${businessType === 'Retail' ? 'selected' : ''}>${"<?php echo t('retail'); ?>"}</option>
                                            <option value="Wholesale" ${businessType === 'Wholesale' ? 'selected' : ''}>${"<?php echo t('wholesale'); ?>"}</option>
                                            <option value="Restaurant" ${businessType === 'Restaurant' ? 'selected' : ''}>${"<?php echo t('restaurant'); ?>"}</option>
                                            <option value="Manufacturing" ${businessType === 'Manufacturing' ? 'selected' : ''}>${"<?php echo t('manufacturing'); ?>"}</option>
                                            <option value="Service" ${businessType === 'Service' ? 'selected' : ''}>${"<?php echo t('service'); ?>"}</option>
                                            <option value="Other" ${businessType === 'Other' ? 'selected' : ''}>${"<?php echo t('other'); ?>"}</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="edit-business-email">
                                            <?php echo t('business_email'); ?> *
                                        </label>
                                        <input type="email" id="edit-business-email" name="business_email" value="${businessEmail}" required
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="edit-business-phone">
                                            <?php echo t('business_phone'); ?>
                                        </label>
                                        <input type="text" id="edit-business-phone" name="business_phone" value="${businessPhone}"
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                    </div>
                                    
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
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="edit-country">
                                            <?php echo t('country'); ?>
                                        </label>
                                        <input type="text" id="edit-country" name="country" value="${country}"
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-1" for="edit-city">
                                            <?php echo t('city'); ?>
                                        </label>
                                        <input type="text" id="edit-city" name="city" value="${city}"
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-1" for="edit-address">
                                        <?php echo t('address'); ?>
                                    </label>
                                    <textarea id="edit-address" name="address" rows="2"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">${address}</textarea>
                                </div>
                                
                                <div class="flex items-center justify-between mt-6">
                                    <button type="button" onclick="this.closest('.fixed').remove()"
                                        class="px-4 py-2 text-sm bg-gray-600 text-white rounded hover:bg-gray-700">
                                        <?php echo t('cancel'); ?>
                                    </button>
                                    <button type="submit"
                                        class="px-4 py-2 text-sm bg-green-600 text-white rounded hover:bg-green-700">
                                        <?php echo t('update_tenant'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(form);
            });
        });
        
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