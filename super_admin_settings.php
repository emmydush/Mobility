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
            case 'update_system_settings':
                // Save general settings
                $settings_updated = true;
                
                // Save email configuration if provided
                if (isset($_POST['smtp_host'])) {
                    $email_config = [
                        'smtp_host' => $_POST['smtp_host'],
                        'smtp_port' => $_POST['smtp_port'],
                        'smtp_username' => $_POST['smtp_username'],
                        'smtp_password' => $_POST['smtp_password'],
                        'encryption' => $_POST['encryption']
                    ];
                    
                    $settings_updated = saveEmailConfig($conn, $email_config, $_SESSION['user_id']);
                }
                
                if ($settings_updated) {
                    $message = t('settings_saved');
                    $message_type = "success";
                } else {
                    $message = t('error_saving_settings') . $conn->error;
                    $message_type = "error";
                }
                break;
                
            case 'backup_database':
                // In a real implementation, you would perform database backup
                $message = t('backup_initiated');
                $message_type = "success";
                break;
                
            case 'clear_cache':
                // In a real implementation, you would clear system cache
                $message = t('cache_cleared');
                $message_type = "success";
                break;
        }
    }
}

// Fetch system information
$system_info = [
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'database_version' => $conn->server_info,
    'total_tenants' => 0,
    'total_users' => 0
];

// Fetch email configuration
$email_config = getEmailConfig($conn);

// Get total tenants
$tenants_query = "SELECT COUNT(*) as count FROM tenants";
$tenants_result = $conn->query($tenants_query);
if ($tenants_result) {
    $system_info['total_tenants'] = $tenants_result->fetch_assoc()['count'];
}

// Get total users
$users_query = "SELECT COUNT(*) as count FROM users";
$users_result = $conn->query($users_query);
if ($users_result) {
    $system_info['total_users'] = $users_result->fetch_assoc()['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('system_settings'); ?> - Super Admin Dashboard</title>
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
                <a href="super_admin_security.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-shield-alt mr-3"></i>
                    <span><?php echo t('security'); ?></span>
                </a>
                <a href="super_admin_settings.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-white bg-gradient-to-r from-green-600 to-green-700 border-l-4 border-green-300">
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
                <h2 class="text-xl font-semibold text-white"><?php echo t('system_settings'); ?></h2>
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

            <!-- Settings Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <!-- System Information -->
                <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-800"><?php echo t('system_information'); ?></h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="text-gray-500 text-sm"><?php echo t('php_version'); ?></div>
                                <div class="font-medium"><?php echo $system_info['php_version']; ?></div>
                            </div>
                            
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="text-gray-500 text-sm"><?php echo t('server_software'); ?></div>
                                <div class="font-medium"><?php echo $system_info['server_software']; ?></div>
                            </div>
                            
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="text-gray-500 text-sm"><?php echo t('database_version'); ?></div>
                                <div class="font-medium"><?php echo $system_info['database_version']; ?></div>
                            </div>
                            
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="text-gray-500 text-sm"><?php echo t('total_tenants'); ?></div>
                                <div class="font-medium"><?php echo $system_info['total_tenants']; ?></div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="text-gray-500 text-sm"><?php echo t('total_users'); ?></div>
                                <div class="font-medium"><?php echo $system_info['total_users']; ?></div>
                            </div>
                            
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="text-gray-500 text-sm"><?php echo t('system_status'); ?></div>
                                <div class="font-medium text-green-600"><?php echo t('operational'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Configuration -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- General Settings -->
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-800"><?php echo t('general_settings'); ?></h3>
                        </div>
                        <div class="p-6">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_system_settings">
                                
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="system-name">
                                        <?php echo t('system_name'); ?>
                                    </label>
                                    <input type="text" id="system-name" name="system_name" value="Mobility Inventory Management System"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                </div>
                                
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="timezone">
                                        <?php echo t('timezone'); ?>
                                    </label>
                                    <select id="timezone" name="timezone"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                        <option value="UTC">UTC</option>
                                        <option value="America/New_York"><?php echo t('eastern_time'); ?></option>
                                        <option value="America/Chicago"><?php echo t('central_time'); ?></option>
                                        <option value="America/Denver"><?php echo t('mountain_time'); ?></option>
                                        <option value="America/Los_Angeles"><?php echo t('pacific_time'); ?></option>
                                        <option value="Europe/London">London</option>
                                        <option value="Europe/Paris">Paris</option>
                                        <option value="Asia/Tokyo">Tokyo</option>
                                        <option value="Asia/Shanghai">Shanghai</option>
                                    </select>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="language">
                                        <?php echo t('default_language'); ?>
                                    </label>
                                    <select id="language" name="language"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                        <option value="en">English</option>
                                        <option value="fr">Français</option>
                                        <option value="rw">Kinyarwanda</option>
                                    </select>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                                        <?php echo t('save_settings'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Email Configuration -->
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-800"><?php echo t('email_configuration'); ?></h3>
                        </div>
                        <div class="p-6">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_system_settings">
                                
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="smtp-host">
                                        <?php echo t('smtp_host'); ?>
                                    </label>
                                    <input type="text" id="smtp-host" name="smtp_host" value="<?php echo htmlspecialchars($email_config['smtp_host']); ?>"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="smtp-port">
                                            <?php echo t('smtp_port'); ?>
                                        </label>
                                        <input type="number" id="smtp-port" name="smtp_port" value="<?php echo htmlspecialchars($email_config['smtp_port']); ?>"
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="encryption">
                                            <?php echo t('encryption'); ?>
                                        </label>
                                        <select id="encryption" name="encryption"
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                            <option value="tls"<?php echo $email_config['encryption'] === 'tls' ? ' selected' : ''; ?>>TLS</option>
                                            <option value="ssl"<?php echo $email_config['encryption'] === 'ssl' ? ' selected' : ''; ?>>SSL</option>
                                            <option value="none"<?php echo $email_config['encryption'] === 'none' ? ' selected' : ''; ?>><?php echo t('none'); ?></option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="smtp-username">
                                        <?php echo t('smtp_username'); ?>
                                    </label>
                                    <input type="text" id="smtp-username" name="smtp_username" value="<?php echo htmlspecialchars($email_config['smtp_username']); ?>"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                </div>
                                
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="smtp-password">
                                        <?php echo t('smtp_password'); ?>
                                    </label>
                                    <input type="password" id="smtp-password" name="smtp_password" value="<?php echo htmlspecialchars($email_config['smtp_password']); ?>"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                                        <?php echo t('save_configuration'); ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Tools -->
                <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-800"><?php echo t('maintenance_tools'); ?></h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-medium text-gray-800 mb-2"><?php echo t('database_backup'); ?></h4>
                                <p class="text-gray-600 text-sm mb-3"><?php echo t('database_backup_desc'); ?></p>
                                <form method="POST">
                                    <input type="hidden" name="action" value="backup_database">
                                    <button type="submit" class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                                        <?php echo t('backup_now'); ?>
                                    </button>
                                </form>
                            </div>
                            
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-medium text-gray-800 mb-2"><?php echo t('clear_cache'); ?></h4>
                                <p class="text-gray-600 text-sm mb-3"><?php echo t('clear_cache_desc'); ?></p>
                                <form method="POST">
                                    <input type="hidden" name="action" value="clear_cache">
                                    <button type="submit" class="px-3 py-1 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700">
                                        <?php echo t('clear_cache'); ?>
                                    </button>
                                </form>
                            </div>
                            
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-medium text-gray-800 mb-2"><?php echo t('system_logs'); ?></h4>
                                <p class="text-gray-600 text-sm mb-3"><?php echo t('system_logs_desc'); ?></p>
                                <button class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                                    <?php echo t('view_logs'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notification Settings -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-800"><?php echo t('notification_settings'); ?></h3>
                    </div>
                    <div class="p-6">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_system_settings">
                            
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2">
                                    <?php echo t('system_notifications'); ?>
                                </label>
                                <div class="space-y-2">
                                    <div class="flex items-center">
                                        <input type="checkbox" id="notify-low-stock" name="notify_low_stock" checked
                                            class="h-4 w-4 text-green-600 rounded">
                                        <label for="notify-low-stock" class="ml-2 text-gray-700"><?php echo t('low_stock_alerts'); ?></label>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="checkbox" id="notify-expired-products" name="notify_expired_products" checked
                                            class="h-4 w-4 text-green-600 rounded">
                                        <label for="notify-expired-products" class="ml-2 text-gray-700"><?php echo t('expired_products_alerts'); ?></label>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="checkbox" id="notify-system-updates" name="notify_system_updates"
                                            class="h-4 w-4 text-green-600 rounded">
                                        <label for="notify-system-updates" class="ml-2 text-gray-700"><?php echo t('system_updates'); ?></label>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="checkbox" id="notify-security-events" name="notify_security_events" checked
                                            class="h-4 w-4 text-green-600 rounded">
                                        <label for="notify-security-events" class="ml-2 text-gray-700"><?php echo t('security_events'); ?></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2">
                                    <?php echo t('notification_methods'); ?>
                                </label>
                                <div class="space-y-2">
                                    <div class="flex items-center">
                                        <input type="checkbox" id="email-notifications" name="email_notifications" checked
                                            class="h-4 w-4 text-green-600 rounded">
                                        <label for="email-notifications" class="ml-2 text-gray-700"><?php echo t('email_notifications'); ?></label>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="checkbox" id="sms-notifications" name="sms_notifications"
                                            class="h-4 w-4 text-green-600 rounded">
                                        <label for="sms-notifications" class="ml-2 text-gray-700"><?php echo t('sms_notifications'); ?></label>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="checkbox" id="in-app-notifications" name="in_app_notifications" checked
                                            class="h-4 w-4 text-green-600 rounded">
                                        <label for="in-app-notifications" class="ml-2 text-gray-700"><?php echo t('in_app_notifications'); ?></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                                    <?php echo t('save_notification_settings'); ?>
                                </button>
                            </div>
                        </form>
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