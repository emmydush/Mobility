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

// Fetch system statistics
$total_tenants = 0;
$total_users = 0;
$active_tenants = 0;
$total_sales = 0;

// Enhanced health checks with more details
function getDatabaseDetails($conn) {
    $details = [];
    try {
        // Get database version
        $version_result = $conn->query("SELECT VERSION() as version");
        if ($version_result) {
            $details['version'] = $version_result->fetch_assoc()['version'];
        }
        
        // Get database size
        $size_result = $conn->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'DB Size in MB' FROM information_schema.tables WHERE table_schema = DATABASE()");
        if ($size_result) {
            $details['size'] = $size_result->fetch_assoc()['DB Size in MB'] . ' MB';
        }
        
        // Get connection status
        $details['status'] = 'Connected';
        $details['healthy'] = true;
    } catch (Exception $e) {
        $details['status'] = 'Error: ' . $e->getMessage();
        $details['healthy'] = false;
    }
    return $details;
}

function getServerDetails() {
    $details = [];
    $details['software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
    $details['php_version'] = phpversion();
    $details['uptime'] = 'Unknown'; // Would need system commands to get real uptime
    $details['healthy'] = true;
    return $details;
}

function getNetworkDetails() {
    $details = [];
    // Check multiple services
    $services = [
        'Google' => 'www.google.com',
        'Cloudflare' => '1.1.1.1'
    ];
    
    $reachable = [];
    $unreachable = [];
    
    foreach ($services as $name => $host) {
        $connected = @fsockopen($host, 80, $errno, $errstr, 3);
        if ($connected) {
            fclose($connected);
            $reachable[] = $name;
        } else {
            $unreachable[] = $name;
        }
    }
    
    $details['reachable'] = $reachable;
    $details['unreachable'] = $unreachable;
    $details['healthy'] = count($reachable) > 0;
    return $details;
}

// Perform health checks
$database_health = checkDatabaseHealth($conn);
$server_health = checkServerHealth();
$network_health = checkNetworkHealth();

// Get detailed information
$database_details = getDatabaseDetails($conn);
$server_details = getServerDetails();
$network_details = getNetworkDetails();

// Determine health status and colors
$database_status = $database_health ? t('operational') : t('down');
$database_color = $database_health ? 'green' : 'red';
$server_status = $server_health ? t('operational') : t('down');
$server_color = $server_health ? 'green' : 'red';
$network_status = $network_health ? t('operational') : t('down');
$network_color = $network_health ? 'green' : 'red';

// Get total tenants
$tenants_query = "SELECT COUNT(*) as count FROM tenants";
$tenants_result = $conn->query($tenants_query);
if ($tenants_result) {
    $total_tenants = $tenants_result->fetch_assoc()['count'];
}

// Get total users
$users_query = "SELECT COUNT(*) as count FROM users";
$users_result = $conn->query($users_query);
if ($users_result) {
    $total_users = $users_result->fetch_assoc()['count'];
}

// Get active tenants
$active_tenants_query = "SELECT COUNT(*) as count FROM tenants WHERE status = 'active'";
$active_tenants_result = $conn->query($active_tenants_query);
if ($active_tenants_result) {
    $active_tenants = $active_tenants_result->fetch_assoc()['count'];
}

// Get total sales across all tenants
$sales_query = "SELECT COUNT(*) as count FROM sales";
$sales_result = $conn->query($sales_query);
if ($sales_result) {
    $total_sales = $sales_result->fetch_assoc()['count'];
}

// Fetch recent activity
$recent_activity = [];
$activity_query = "SELECT u.username, u.role, t.business_name, u.last_login 
                   FROM users u 
                   LEFT JOIN tenants t ON u.tenant_id = t.id 
                   WHERE u.last_login IS NOT NULL 
                   ORDER BY u.last_login DESC 
                   LIMIT 5";
$activity_stmt = $conn->prepare($activity_query);
if ($activity_stmt) {
    $activity_stmt->execute();
    $activity_result = $activity_stmt->get_result();
    while ($row = $activity_result->fetch_assoc()) {
        $recent_activity[] = $row;
    }
    $activity_stmt->close();
}

// Fetch tenants list
$tenants = [];
$tenants_query = "SELECT id, tenant_id, business_name, business_type, business_email, status, created_at 
                  FROM tenants 
                  ORDER BY created_at DESC";
$tenants_result = $conn->query($tenants_query);
if ($tenants_result) {
    while ($row = $tenants_result->fetch_assoc()) {
        $tenants[] = $row;
    }
}

// System Health Checks
function checkDatabaseHealth($conn) {
    try {
        // Check if we can query the database
        $result = $conn->query("SELECT 1");
        return $result !== false;
    } catch (Exception $e) {
        return false;
    }
}

function checkServerHealth() {
    // Check if the server is responding
    return true; // Server is running if we reached this point
}

function checkNetworkHealth() {
    // Simple network check - try to connect to a well-known service
    $connected = @fsockopen("www.google.com", 80, $errno, $errstr, 5);
    if ($connected) {
        fclose($connected);
        return true;
    }
    return false;
}

// Perform health checks
$database_health = checkDatabaseHealth($conn);
$server_health = checkServerHealth();
$network_health = checkNetworkHealth();

// Determine health status and colors
$database_status = $database_health ? t('operational') : t('down');
$database_color = $database_health ? 'green' : 'red';
$server_status = $server_health ? t('operational') : t('down');
$server_color = $server_health ? 'green' : 'red';
$network_status = $network_health ? t('operational') : t('down');
$network_color = $network_health ? 'green' : 'red';
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('system_dashboard'); ?> - <?php echo t('super_admin'); ?> <?php echo t('dashboard'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Sidebar menu animations */
        .sidebar-menu-item {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .sidebar-menu-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: all 0.6s ease;
        }
        
        .sidebar-menu-item:hover::before {
            left: 100%;
        }
        
        .sidebar-menu-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-menu-item.active {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(76, 175, 80, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(76, 175, 80, 0);
            }
        }
        
        /* Subtle fade-in animation for sidebar items */
        .sidebar-nav {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-gradient-to-b from-green-800 to-green-900 shadow-lg">
            <div class="p-4 border-b border-green-700">
                <h1 class="text-xl font-bold text-white">IMS Super Admin</h1>
                <p class="text-sm text-green-200">System Management</p>
            </div>
            <nav class="mt-4 sidebar-nav">
                <a href="super_admin_dashboard.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-white bg-gradient-to-r from-green-600 to-green-700 border-l-4 border-green-300 sidebar-menu-item">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    <span><?php echo t('dashboard'); ?></span>
                </a>
                <a href="super_admin_tenants.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200 sidebar-menu-item">
                    <i class="fas fa-building mr-3"></i>
                    <span><?php echo t('tenants_management'); ?></span>
                </a>
                <a href="super_admin_users.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200 sidebar-menu-item">
                    <i class="fas fa-users mr-3"></i>
                    <span><?php echo t('users_management'); ?></span>
                </a>
                <a href="super_admin_security.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200 sidebar-menu-item">
                    <i class="fas fa-shield-alt mr-3"></i>
                    <span><?php echo t('security'); ?></span>
                </a>
                <a href="super_admin_settings.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200 sidebar-menu-item">
                    <i class="fas fa-cog mr-3"></i>
                    <span><?php echo t('settings'); ?></span>
                </a>
                <a href="super_admin_reports.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200 sidebar-menu-item">
                    <i class="fas fa-chart-bar mr-3"></i>
                    <span><?php echo t('reports'); ?></span>
                </a>
                <a href="logout.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200 sidebar-menu-item">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    <span><?php echo t('logout'); ?></span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="flex items-center justify-between p-4 bg-gradient-to-r from-green-700 to-green-800 shadow">
                <h2 class="text-xl font-semibold text-white"><?php echo t('system_dashboard'); ?></h2>
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

            <!-- Dashboard Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                                <i class="fas fa-building text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm"><?php echo t('total_tenants'); ?></p>
                                <p class="text-2xl font-bold"><?php echo $total_tenants; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                                <i class="fas fa-users text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm"><?php echo t('total_users'); ?></p>
                                <p class="text-2xl font-bold"><?php echo $total_users; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                                <i class="fas fa-store text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm"><?php echo t('active_tenants'); ?></p>
                                <p class="text-2xl font-bold"><?php echo $active_tenants; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                                <i class="fas fa-shopping-cart text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm"><?php echo t('total_sales'); ?></p>
                                <p class="text-2xl font-bold"><?php echo $total_sales; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity and Tenants -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Recent Activity -->
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-800"><?php echo t('recent_activity'); ?></h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('username'); ?></th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('business'); ?></th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('last_login'); ?></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($recent_activity)): ?>
                                        <tr>
                                            <td colspan="3" class="px-6 py-4 text-center text-gray-500">
                                                <?php echo t('no_recent_activity'); ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_activity as $activity): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($activity['username']); ?></div>
                                                    <div class="text-sm text-gray-500 capitalize"><?php echo htmlspecialchars($activity['role']); ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($activity['business_name'] ?? 'N/A'); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo date('M j, Y g:i A', strtotime($activity['last_login'])); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('status'); ?></th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('created'); ?></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($tenants)): ?>
                                        <tr>
                                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                                <?php echo t('no_tenants_found'); ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($tenants as $tenant): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($tenant['business_name']); ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($tenant['business_email']); ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($tenant['business_type']); ?>
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
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- System Health -->
                <div class="mt-6 bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-800"><?php echo t('system_health'); ?></h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="p-2 rounded-full bg-<?php echo $database_color; ?>-100 text-<?php echo $database_color; ?>-600 mr-3">
                                        <i class="fas fa-database"></i>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 text-sm"><?php echo t('database'); ?></p>
                                        <p class="font-medium"><?php echo $database_status; ?></p>
                                        <?php if (isset($database_details['version'])): ?>
                                            <p class="text-xs text-gray-500 mt-1">Version: <?php echo htmlspecialchars($database_details['version']); ?></p>
                                        <?php endif; ?>
                                        <?php if (isset($database_details['size'])): ?>
                                            <p class="text-xs text-gray-500">Size: <?php echo htmlspecialchars($database_details['size']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="p-2 rounded-full bg-<?php echo $server_color; ?>-100 text-<?php echo $server_color; ?>-600 mr-3">
                                        <i class="fas fa-server"></i>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 text-sm"><?php echo t('server'); ?></p>
                                        <p class="font-medium"><?php echo $server_status; ?></p>
                                        <?php if (isset($server_details['software'])): ?>
                                            <p class="text-xs text-gray-500 mt-1">Software: <?php echo htmlspecialchars($server_details['software']); ?></p>
                                        <?php endif; ?>
                                        <?php if (isset($server_details['php_version'])): ?>
                                            <p class="text-xs text-gray-500">PHP: <?php echo htmlspecialchars($server_details['php_version']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="p-2 rounded-full bg-<?php echo $network_color; ?>-100 text-<?php echo $network_color; ?>-600 mr-3">
                                        <i class="fas fa-network-wired"></i>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 text-sm"><?php echo t('network'); ?></p>
                                        <p class="font-medium"><?php echo $network_status; ?></p>
                                        <?php if (isset($network_details['reachable']) && !empty($network_details['reachable'])): ?>
                                            <p class="text-xs text-gray-500 mt-1">Reachable: <?php echo implode(', ', $network_details['reachable']); ?></p>
                                        <?php endif; ?>
                                        <?php if (isset($network_details['unreachable']) && !empty($network_details['unreachable'])): ?>
                                            <p class="text-xs text-gray-500 text-red-500">Unreachable: <?php echo implode(', ', $network_details['unreachable']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
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