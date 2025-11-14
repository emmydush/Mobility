// Fetch all permissions
$permissions = [];
$permissions_query = "SELECT id, name, description, module FROM permissions ORDER BY module, name";
$permissions_result = $conn->query($permissions_query);
if ($permissions_result) {
    while ($row = $permissions_result->fetch_assoc()) {
        $permissions[] = $row;
    }
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

// Fetch all users for permission management
$users = [];
$users_query = "SELECT id, username, role FROM users ORDER BY username";
$users_result = $conn->query($users_query);
if ($users_result) {
    while ($row = $users_result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('security_management'); ?> - Super Admin Dashboard</title>
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
                <a href="super_admin_users.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-users mr-3"></i>
                    <span><?php echo t('users_management'); ?></span>
                </a>
                <a href="super_admin_security.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-white bg-gradient-to-r from-green-600 to-green-700 border-l-4 border-green-300">
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
                <h2 class="text-xl font-semibold text-white"><?php echo t('security_management'); ?></h2>
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

            <!-- Security Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <!-- Security Overview Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-100 text-red-600 mr-4">
                                <i class="fas fa-user-shield text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm"><?php echo t('active_sessions'); ?></p>
                                <p class="text-2xl font-bold">24</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                                <i class="fas fa-key text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm"><?php echo t('failed_logins'); ?></p>
                                <p class="text-2xl font-bold">3</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                                <i class="fas fa-user-check text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm"><?php echo t('permissions'); ?></p>
                                <p class="text-2xl font-bold"><?php echo count($permissions); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="mb-6 border-b border-gray-200">
                    <nav class="flex space-x-8">
                        <button class="tab-button py-4 px-1 border-b-2 border-green-500 text-green-600 font-medium text-sm" data-tab="activity">
                            <?php echo t('user_activity'); ?>
                        </button>
                        <button class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm" data-tab="permissions">
                            <?php echo t('permissions'); ?>
                        </button>
                        <button class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm" data-tab="password-policy">
                            <?php echo t('password_policy'); ?>
                        </button>
                        <button class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm" data-tab="audit-logs">
                            <?php echo t('audit_logs'); ?>
                        </button>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div class="tab-content" id="activity-tab">
                    <!-- Recent Activity -->
                    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-800"><?php echo t('recent_user_activity'); ?></h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('user'); ?></th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('role'); ?></th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('business'); ?></th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('last_login'); ?></th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($recent_activity)): ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                                <?php echo t('no_recent_activity'); ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_activity as $activity): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($activity['username']); ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 capitalize">
                                                        <?php echo htmlspecialchars($activity['role']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($activity['business_name'] ?? t('no_tenant')); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo date('M j, Y g:i A', strtotime($activity['last_login'])); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="action" value="revoke_session">
                                                        <input type="hidden" name="user_id" value="<?php echo $activity['id']; ?>">
                                                        <button type="submit" class="text-red-600 hover:text-red-900" 
                                                                onclick="return confirm('<?php echo t('revoke_session_confirmation'); ?>')">
                                                            <?php echo t('revoke_session'); ?>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="tab-content hidden" id="permissions-tab">
                    <!-- Permissions Management -->
                    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-800"><?php echo t('user_permissions_management'); ?></h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div>
                                    <h4 class="text-md font-medium text-gray-700 mb-4"><?php echo t('select_user'); ?></h4>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_user_permissions">
                                        <div class="mb-4">
                                            <select id="user-select" name="user_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                                <option value=""><?php echo t('select_user'); ?></option>
                                                <?php foreach ($users as $user): ?>
                                                    <option value="<?php echo $user['id']; ?>">
                                                        <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['role']); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <button type="button" id="load-permissions-btn" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                                                <?php echo t('load_user_permissions'); ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                
                                <div>
                                    <h4 class="text-md font-medium text-gray-700 mb-4"><?php echo t('manage_permissions'); ?></h4>
                                    <form method="POST" id="permissions-form">
                                        <input type="hidden" name="action" value="update_user_permissions">
                                        <input type="hidden" name="user_id" id="permissions-user-id">
                                        
                                        <div class="max-h-96 overflow-y-auto">
                                            <?php foreach ($grouped_permissions as $module => $module_permissions): ?>
                                                <div class="mb-4">
                                                    <h5 class="font-medium text-gray-800 mb-2"><?php echo htmlspecialchars($module); ?></h5>
                                                    <?php foreach ($module_permissions as $permission): ?>
                                                        <div class="flex items-center mb-2">
                                                            <input type="checkbox" 
                                                                   id="perm_<?php echo $permission['id']; ?>" 
                                                                   name="permissions[]" 
                                                                   value="<?php echo $permission['name']; ?>"
                                                                   class="h-4 w-4 text-green-600 rounded">
                                                            <label for="perm_<?php echo $permission['id']; ?>" class="ml-2 text-sm text-gray-700">
                                                                <?php echo htmlspecialchars($permission['name']); ?>
                                                                <span class="text-gray-500 text-xs block"><?php echo htmlspecialchars($permission['description']); ?></span>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <div class="mt-4">
                                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                                                <?php echo t('update_permissions'); ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-content hidden" id="password-policy-tab">
                    <!-- Password Policy -->
                    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-800"><?php echo t('password_policy_settings'); ?></h3>
                        </div>
                        <div class="p-6">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_password_policy">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="min-length">
                                            <?php echo t('minimum_password_length'); ?>
                                        </label>
                                        <input type="number" id="min-length" name="min_length" min="6" max="128" value="8"
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                        <p class="text-gray-500 text-xs mt-1"><?php echo t('minimum_password_length_desc'); ?></p>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="expiry-days">
                                            <?php echo t('password_expiry_days'); ?>
                                        </label>
                                        <input type="number" id="expiry-days" name="expiry_days" min="1" max="365" value="90"
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                        <p class="text-gray-500 text-xs mt-1"><?php echo t('password_expiry_days_desc'); ?></p>
                                    </div>
                                </div>
                                
                                <div class="mb-6">
                                    <label class="block text-gray-700 text-sm font-bold mb-2">
                                        <?php echo t('password_requirements'); ?>
                                    </label>
                                    <div class="space-y-2">
                                        <div class="flex items-center">
                                            <input type="checkbox" id="require-uppercase" name="require_uppercase" checked
                                                class="h-4 w-4 text-green-600 rounded">
                                            <label for="require-uppercase" class="ml-2 text-gray-700"><?php echo t('require_uppercase_letters'); ?></label>
                                        </div>
                                        <div class="flex items-center">
                                            <input type="checkbox" id="require-lowercase" name="require_lowercase" checked
                                                class="h-4 w-4 text-green-600 rounded">
                                            <label for="require-lowercase" class="ml-2 text-gray-700"><?php echo t('require_lowercase_letters'); ?></label>
                                        </div>
                                        <div class="flex items-center">
                                            <input type="checkbox" id="require-numbers" name="require_numbers" checked
                                                class="h-4 w-4 text-green-600 rounded">
                                            <label for="require-numbers" class="ml-2 text-gray-700"><?php echo t('require_numbers'); ?></label>
                                        </div>
                                        <div class="flex items-center">
                                            <input type="checkbox" id="require-special" name="require_special" checked
                                                class="h-4 w-4 text-green-600 rounded">
                                            <label for="require-special" class="ml-2 text-gray-700"><?php echo t('require_special_characters'); ?></label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-6">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="history-count">
                                        <?php echo t('password_history'); ?>
                                    </label>
                                    <input type="number" id="history-count" name="history_count" min="1" max="24" value="5"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    <p class="text-gray-500 text-xs mt-1"><?php echo t('number_of_previous_passwords'); ?></p>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                                        <?php echo t('update_password_policy'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="tab-content hidden" id="audit-logs-tab">
                    <!-- Audit Logs -->
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-800"><?php echo t('security_audit_logs'); ?></h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('timestamp'); ?></th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('user'); ?></th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('action'); ?></th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('ip_address'); ?></th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('status'); ?></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">2023-06-15 14:30:22</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">admin_user</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo t('user_login'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">192.168.1.100</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                <?php echo t('success'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">2023-06-15 14:25:17</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">john_doe</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo t('password_change'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">192.168.1.105</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                <?php echo t('success'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">2023-06-15 13:45:33</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">jane_smith</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo t('failed_login_attempt'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">192.168.1.110</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                <?php echo t('failed'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">2023-06-15 12:15:44</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">super_admin</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo t('user_permission_update'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">192.168.1.50</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                <?php echo t('success'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">2023-06-15 11:30:12</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">admin_user</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo t('user_created'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">192.168.1.100</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                <?php echo t('success'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="px-6 py-4 border-t border-gray-200">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700">
                                    <?php echo t('showing_results', ['start' => '1', 'end' => '5', 'total' => '24']); ?>
                                </div>
                                <div class="flex space-x-2">
                                    <button class="px-3 py-1 text-sm bg-gray-200 rounded hover:bg-gray-300"><?php echo t('previous'); ?></button>
                                    <button class="px-3 py-1 text-sm bg-green-600 text-white rounded hover:bg-green-700"><?php echo t('next'); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Tab switching functionality
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons and tabs
                document.querySelectorAll('.tab-button').forEach(btn => {
                    btn.classList.remove('border-green-500', 'text-green-600');
                    btn.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
                });
                
                document.querySelectorAll('.tab-content').forEach(tab => {
                    tab.classList.add('hidden');
                });
                
                // Add active class to clicked button
                this.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
                this.classList.add('border-green-500', 'text-green-600');
                
                // Show corresponding tab
                const tabId = this.getAttribute('data-tab') + '-tab';
                document.getElementById(tabId).classList.remove('hidden');
            });
        });
        
        // Load user permissions
        document.getElementById('load-permissions-btn').addEventListener('click', function() {
            const userId = document.getElementById('user-select').value;
            if (!userId) {
                alert('<?php echo t('please_select_user'); ?>');
                return;
            }
            
            // In a real implementation, you would fetch the user's current permissions via AJAX
            // For now, we'll just set the user ID in the form
            document.getElementById('permissions-user-id').value = userId;
            alert('<?php echo t('user_selected_permissions'); ?>');
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