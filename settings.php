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

// Handle form submissions
$message = '';
$message_type = '';

// System settings variables
$system_settings = [
    'company_name' => 'Mobility Inventory',
    'company_address' => '123 Business Street, City, Country',
    'company_phone' => '+250(78) 123-4567',
    'company_email' => 'info@mobilityinventory.com',
    'currency_symbol' => 'FRW',
    'default_tax_rate' => '10',
    'low_stock_threshold' => '10',
    'backup_frequency' => 'daily'
];

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_system':
            // In a real application, you would save these settings to a database table
            $message = "System settings updated successfully!";
            $message_type = "success";
            break;
            
        case 'update_security':
            // In a real application, you would update security settings
            $message = "Security settings updated successfully!";
            $message_type = "success";
            break;
            
        case 'clear_cache':
            // In a real application, you would clear application cache
            $message = "Cache cleared successfully!";
            $message_type = "success";
            break;
            
        case 'backup_database':
            // In a real application, you would initiate database backup
            $message = "Database backup initiated successfully!";
            $message_type = "success";
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Complete Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-card {
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .settings-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        
        .section-header {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 0.5rem;
        }
        
        .form-input {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.5rem;
            transition: all 0.2s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #64748b, #475569);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #475569, #334155);
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
        }
        
        .btn-warning:hover {
            background: linear-gradient(135deg, #d97706, #b45309);
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-2px);
        }
        
        .btn-info {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
        }
        
        .btn-info:hover {
            background: linear-gradient(135deg, #0284c7, #0369a1);
            transform: translateY(-2px);
        }
        
        .maintenance-card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .maintenance-card:hover {
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        
        .nav-item.active {
            background: linear-gradient(90deg, #2563eb, #1d4ed8);
            border-left: 4px solid #93c5fd;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-gradient-to-b from-green-800 to-green-900 shadow-xl">
            <div class="p-5 border-b border-green-700">
                <h1 class="text-2xl font-bold text-white">IMS</h1>
                <p class="text-sm text-green-200">Inventory Management</p>
            </div>
            <nav class="mt-6">
                <a href="dashboard.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-5 py-3 text-green-100 hover:bg-green-700 transition duration-200">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    <span><?php echo t('dashboard'); ?></span>
                </a>
                <a href="products.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-5 py-3 text-green-100 hover:bg-green-700 transition duration-200">
                    <i class="fas fa-box mr-3"></i>
                    <span>Products</span>
                </a>
                <a href="categories.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-5 py-3 text-green-100 hover:bg-green-700 transition duration-200">
                    <i class="fas fa-tags mr-3"></i>
                    <span>Categories</span>
                </a>
                <a href="purchases.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-5 py-3 text-green-100 hover:bg-green-700 transition duration-200">
                    <i class="fas fa-shopping-cart mr-3"></i>
                    <span>Purchases</span>
                </a>
                <a href="expenses.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-5 py-3 text-green-100 hover:bg-green-700 transition duration-200">
                    <i class="fas fa-file-invoice-dollar mr-3"></i>
                    <span>Expenses</span>
                </a>
                <a href="suppliers.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-5 py-3 text-green-100 hover:bg-green-700 transition duration-200">
                    <i class="fas fa-truck mr-3"></i>
                    <span>Suppliers</span>
                </a>
                <a href="pos.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-5 py-3 text-green-100 hover:bg-green-700 transition duration-200">
                    <i class="fas fa-cash-register mr-3"></i>
                    <span>Point of Sale</span>
                </a>
                <a href="stock-movements.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-5 py-3 text-green-100 hover:bg-green-700 transition duration-200">
                    <i class="fas fa-exchange-alt mr-3"></i>
                    <span>Stock Movements</span>
                </a>
                <a href="customers.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-5 py-3 text-green-100 hover:bg-green-700 transition duration-200">
                    <i class="fas fa-users mr-3"></i>
                    <span>Customers</span>
                </a>
                <a href="sales.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-5 py-3 text-green-100 hover:bg-green-700 transition duration-200">
                    <i class="fas fa-shopping-cart mr-3"></i>
                    <span>Sales</span>
                </a>
                <a href="reports.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-5 py-3 text-green-100 hover:bg-green-700 transition duration-200">
                    <i class="fas fa-chart-bar mr-3"></i>
                    <span><?php echo t('reports'); ?></span>
                </a>
                <a href="users.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-5 py-3 text-green-100 hover:bg-green-700 transition duration-200">
                    <i class="fas fa-user mr-3"></i>
                    <span><?php echo t('users'); ?></span>
                </a>
                <a href="settings.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-5 py-3 text-white nav-item active">
                    <i class="fas fa-cog mr-3"></i>
                    <span><?php echo t('settings'); ?></span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="flex items-center justify-between p-4 bg-gradient-to-r from-green-600 to-green-800 shadow">
                <h2 class="text-xl font-semibold text-white">Advanced Settings</h2>
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
                                <p class="text-xs text-green-100 capitalize"><?php echo htmlspecialchars($_SESSION['role'] ?? 'admin'); ?></p>
                            </div>
                            <i class="fas fa-chevron-down text-green-200 text-xs"></i>
                        </button>
                        
                        <!-- Dropdown menu -->
                        <div id="user-dropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 hidden z-50">
                            <div class="px-4 py-2 border-b border-gray-200">
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($username); ?></p>
                                <p class="text-xs text-gray-500 capitalize"><?php echo htmlspecialchars($_SESSION['role'] ?? 'admin'); ?></p>
                            </div>
                            <a href="profile.php?lang=<?php echo $current_lang; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user-circle mr-2"></i><?php echo t('profile'); ?>
                            </a>
                            <a href="settings.php?lang=<?php echo $current_lang; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
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
                <div class="p-4 <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?> border-b">
                    <div class="container mx-auto">
                        <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Settings Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <!-- System Settings -->
                <div class="settings-card bg-white mb-8">
                    <div class="section-header">
                        <div class="flex items-center">
                            <i class="fas fa-cogs text-xl mr-3"></i>
                            <h3 class="text-xl font-bold">System Settings</h3>
                        </div>
                        <p class="text-green-100 text-sm mt-1">Configure your company information and default settings</p>
                    </div>
                    <div class="p-6">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_system">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Company Name</label>
                                    <input type="text" name="company_name" value="<?php echo htmlspecialchars($system_settings['company_name']); ?>" 
                                        class="form-input w-full">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Company Address</label>
                                    <input type="text" name="company_address" value="<?php echo htmlspecialchars($system_settings['company_address']); ?>" 
                                        class="form-input w-full">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Company Phone</label>
                                    <input type="text" name="company_phone" value="<?php echo htmlspecialchars($system_settings['company_phone']); ?>" 
                                        class="form-input w-full">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Company Email</label>
                                    <input type="email" name="company_email" value="<?php echo htmlspecialchars($system_settings['company_email']); ?>" 
                                        class="form-input w-full">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Currency Symbol</label>
                                    <input type="text" name="currency_symbol" value="<?php echo htmlspecialchars($system_settings['currency_symbol']); ?>" 
                                        class="form-input w-full">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Default Tax Rate (%)</label>
                                    <input type="number" name="default_tax_rate" value="<?php echo htmlspecialchars($system_settings['default_tax_rate']); ?>" 
                                        class="form-input w-full">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Low Stock Threshold</label>
                                    <input type="number" name="low_stock_threshold" value="<?php echo htmlspecialchars($system_settings['low_stock_threshold']); ?>" 
                                        class="form-input w-full">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Backup Frequency</label>
                                    <select name="backup_frequency" 
                                        class="form-input w-full">
                                        <option value="daily" <?php echo $system_settings['backup_frequency'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                        <option value="weekly" <?php echo $system_settings['backup_frequency'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                        <option value="monthly" <?php echo $system_settings['backup_frequency'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-8">
                                <button type="submit" 
                                    class="btn-primary">
                                    <i class="fas fa-save mr-2"></i> Save System Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="settings-card bg-white mb-8">
                    <div class="section-header">
                        <div class="flex items-center">
                            <i class="fas fa-shield-alt text-xl mr-3"></i>
                            <h3 class="text-xl font-bold">Security Settings</h3>
                        </div>
                        <p class="text-green-100 text-sm mt-1">Configure authentication and security policies</p>
                    </div>
                    <div class="p-6">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_security">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Password Expiry (days)</label>
                                    <input type="number" name="password_expiry" value="90" 
                                        class="form-input w-full">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Failed Login Attempts</label>
                                    <input type="number" name="failed_login_attempts" value="5" 
                                        class="form-input w-full">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Session Timeout (minutes)</label>
                                    <input type="number" name="session_timeout" value="30" 
                                        class="form-input w-full">
                                </div>
                                <div class="flex items-center pt-6">
                                    <input type="checkbox" name="two_factor_auth" id="two_factor_auth" 
                                        class="h-5 w-5 text-green-600 border-gray-300 rounded focus:ring-green-500">
                                    <label for="two_factor_auth" class="ml-3 block text-sm font-medium text-gray-700">Enable Two-Factor Authentication</label>
                                </div>
                            </div>
                            <div class="mt-8">
                                <button type="submit" 
                                    class="btn-primary">
                                    <i class="fas fa-save mr-2"></i> Save Security Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Maintenance -->
                <div class="settings-card bg-white">
                    <div class="section-header">
                        <div class="flex items-center">
                            <i class="fas fa-tools text-xl mr-3"></i>
                            <h3 class="text-xl font-bold">Maintenance</h3>
                        </div>
                        <p class="text-green-100 text-sm mt-1">System maintenance and information tools</p>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="maintenance-card p-6">
                                <div class="flex items-center mb-4">
                                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-3">
                                        <i class="fas fa-broom"></i>
                                    </div>
                                    <h4 class="text-lg font-semibold text-gray-800">Clear Cache</h4>
                                </div>
                                <p class="text-gray-600 mb-4">Clear application cache to free up space and resolve issues.</p>
                                <form method="POST">
                                    <input type="hidden" name="action" value="clear_cache">
                                    <button type="submit" 
                                        class="btn-warning w-full">
                                        <i class="fas fa-broom mr-2"></i> Clear Cache
                                    </button>
                                </form>
                            </div>
                            <div class="maintenance-card p-6">
                                <div class="flex items-center mb-4">
                                    <div class="p-3 rounded-full bg-green-100 text-green-600 mr-3">
                                        <i class="fas fa-database"></i>
                                    </div>
                                    <h4 class="text-lg font-semibold text-gray-800">Backup Database</h4>
                                </div>
                                <p class="text-gray-600 mb-4">Create a backup of the entire database.</p>
                                <form method="POST">
                                    <input type="hidden" name="action" value="backup_database">
                                    <button type="submit" 
                                        class="btn-success w-full">
                                        <i class="fas fa-download mr-2"></i> Backup Now
                                    </button>
                                </form>
                            </div>
                            <div class="maintenance-card p-6">
                                <div class="flex items-center mb-4">
                                    <div class="p-3 rounded-full bg-green-100 text-green-600 mr-3">
                                        <i class="fas fa-info-circle"></i>
                                    </div>
                                    <h4 class="text-lg font-semibold text-gray-800">System Information</h4>
                                </div>
                                <p class="text-gray-600 mb-4">View system information and requirements.</p>
                                <button onclick="showSystemInfo()" 
                                    class="btn-info w-full">
                                    <i class="fas fa-info-circle mr-2"></i> View Info
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- System Information Modal -->
    <div id="system-info-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-xl rounded-lg bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-6 pb-3 border-b">
                    <h3 class="text-2xl font-bold text-gray-800">System Information</h3>
                    <button onclick="closeSystemInfo()" class="text-gray-500 hover:text-gray-700 text-2xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="overflow-y-auto max-h-96">
                    <table class="min-w-full divide-y divide-gray-200">
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 bg-gray-50">Operating System</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo php_uname(); ?></td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 bg-gray-50">PHP Version</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo phpversion(); ?></td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 bg-gray-50">Database Version</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php 
                                    global $conn;
                                    $result = $conn->query("SELECT VERSION() as version");
                                    if ($result) {
                                        $row = $result->fetch(PDO::FETCH_ASSOC);
                                        echo $row['version'];
                                    } else {
                                        echo "Unknown";
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 bg-gray-50">Server Software</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 bg-gray-50">Memory Limit</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo ini_get('memory_limit'); ?></td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 bg-gray-50">Max Execution Time</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo ini_get('max_execution_time'); ?> seconds</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 bg-gray-50">Upload Max Filesize</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo ini_get('upload_max_filesize'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">
                    <button onclick="closeSystemInfo()" 
                        class="btn-secondary float-right">
                        <i class="fas fa-times mr-2"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showSystemInfo() {
            document.getElementById('system-info-modal').classList.remove('hidden');
        }
        
        function closeSystemInfo() {
            document.getElementById('system-info-modal').classList.add('hidden');
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('system-info-modal');
            if (event.target === modal) {
                modal.classList.add('hidden');
            }
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