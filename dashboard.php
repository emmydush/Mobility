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

// Get user permissions
$user_permissions = getUserPermissions($conn, $_SESSION['user_id']);

// Get tenant information
$tenant_info = getCurrentTenantInfo($conn);
$business_name = $tenant_info['business_name'] ?? 'No Business';

// Fetch dashboard data with tenant filtering
$total_products = 0;
$stock_in = 0;
$stock_out = 0;
$total_customers = 0;
$daily_sales = 0;
$daily_profit = 0;
$total_profit = 0; // New variable for total profit
$expired_products = 0;
$stock_movements_data = [];

// Total products with tenant filtering
$product_count_query = "SELECT COUNT(*) as count FROM products WHERE tenant_id = ?";
$product_count_stmt = $conn->prepare($product_count_query);
if ($product_count_stmt) {
    $product_count_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $product_count_stmt->execute();
    $product_count_result = $product_count_stmt->fetch(PDO::FETCH_ASSOC);
    $total_products = $product_count_result['count'];
}

// Stock in movements with tenant filtering
$stock_in_query = "SELECT SUM(quantity) as total FROM stock_movements WHERE type = 'in' AND tenant_id = ?";
$stock_in_stmt = $conn->prepare($stock_in_query);
if ($stock_in_stmt) {
    $stock_in_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $stock_in_stmt->execute();
    $stock_in_result = $stock_in_stmt->fetch(PDO::FETCH_ASSOC);
    $stock_in = $stock_in_result['total'] ?? 0;
}

// Stock out movements with tenant filtering
$stock_out_query = "SELECT SUM(quantity) as total FROM stock_movements WHERE type = 'out' AND tenant_id = ?";
$stock_out_stmt = $conn->prepare($stock_out_query);
if ($stock_out_stmt) {
    $stock_out_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $stock_out_stmt->execute();
    $stock_out_result = $stock_out_stmt->fetch(PDO::FETCH_ASSOC);
    $stock_out = $stock_out_result['total'] ?? 0;
}

// Total customers with tenant filtering
$customer_count_query = "SELECT COUNT(*) as count FROM customers WHERE tenant_id = ?";
$customer_count_stmt = $conn->prepare($customer_count_query);
if ($customer_count_stmt) {
    $customer_count_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $customer_count_stmt->execute();
    $customer_count_result = $customer_count_stmt->fetch(PDO::FETCH_ASSOC);
    $total_customers = $customer_count_result['count'];
}

// Daily sales (today's sales) with tenant filtering
$daily_sales_query = "SELECT COALESCE(SUM(total_amount), 0) as daily_sales FROM sales WHERE DATE(created_at) = CURDATE() AND tenant_id = ?";
$daily_sales_stmt = $conn->prepare($daily_sales_query);
if ($daily_sales_stmt) {
    $daily_sales_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $daily_sales_stmt->execute();
    $daily_sales_result = $daily_sales_stmt->fetch(PDO::FETCH_ASSOC);
    $daily_sales = $daily_sales_result['daily_sales'];
}

// Daily profit calculation with tenant filtering
// Profit = Sales - Cost of goods sold
$daily_profit_query = "SELECT 
    COALESCE(SUM(si.total_price), 0) as total_sales,
    COALESCE(SUM(si.quantity * p.cost_price), 0) as total_cost
FROM sales s
JOIN sale_items si ON s.id = si.sale_id
JOIN products p ON si.product_id = p.id
WHERE DATE(s.created_at) = CURDATE() AND s.tenant_id = ?";

$daily_profit_stmt = $conn->prepare($daily_profit_query);
if ($daily_profit_stmt) {
    $daily_profit_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $daily_profit_stmt->execute();
    $daily_profit_result = $daily_profit_stmt->fetch(PDO::FETCH_ASSOC);
    $row = $daily_profit_result;
    $daily_profit = $row['total_sales'] - $row['total_cost'];
}

// Total profit calculation with tenant filtering
// Profit = Total Sales - Total Cost of goods sold
$total_profit_query = "SELECT 
    COALESCE(SUM(si.total_price), 0) as total_sales,
    COALESCE(SUM(si.quantity * p.cost_price), 0) as total_cost
FROM sales s
JOIN sale_items si ON s.id = si.sale_id
JOIN products p ON si.product_id = p.id
WHERE s.tenant_id = ?";

$total_profit_stmt = $conn->prepare($total_profit_query);
if ($total_profit_stmt) {
    $total_profit_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $total_profit_stmt->execute();
    $total_profit_result = $total_profit_stmt->fetch(PDO::FETCH_ASSOC);
    $row = $total_profit_result;
    $total_profit = $row['total_sales'] - $row['total_cost'];
}

// Expired products count with tenant filtering
$expired_products_query = "SELECT COUNT(*) as expired_count FROM products WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE() AND stock_quantity > 0 AND tenant_id = ?";
$expired_products_stmt = $conn->prepare($expired_products_query);
if ($expired_products_stmt) {
    $expired_products_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $expired_products_stmt->execute();
    $expired_products_result = $expired_products_stmt->fetch(PDO::FETCH_ASSOC);
    $expired_products = $expired_products_result['expired_count'];
}

// Fetch stock movements data for the last 7 days with tenant filtering
$stock_movements_query = "SELECT 
    DATE(created_at) as date,
    SUM(CASE WHEN type = 'in' THEN quantity ELSE 0 END) as stock_in,
    SUM(CASE WHEN type = 'out' THEN quantity ELSE 0 END) as stock_out
FROM stock_movements 
WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND tenant_id = ?
GROUP BY DATE(created_at)
ORDER BY DATE(created_at)";

$stock_movements_stmt = $conn->prepare($stock_movements_query);
if ($stock_movements_stmt) {
    $stock_movements_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $stock_movements_stmt->execute();
    $stock_movements_result = $stock_movements_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($stock_movements_result as $row) {
        // Ensure we have valid data
        if ($row['date'] !== null) {
            $stock_movements_data[] = $row;
        }
    }
} else {
    // Handle query preparation error
    error_log("Failed to prepare stock movements query");
}

// Fetch monthly sales data for performance chart with tenant filtering
$monthly_sales_data = [];
$monthly_sales_query = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    SUM(total_amount) as monthly_sales
FROM sales 
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND tenant_id = ?
GROUP BY DATE_FORMAT(created_at, '%Y-%m')
ORDER BY DATE_FORMAT(created_at, '%Y-%m')";

$monthly_sales_stmt = $conn->prepare($monthly_sales_query);
if ($monthly_sales_stmt) {
    $monthly_sales_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $monthly_sales_stmt->execute();
    $monthly_sales_result = $monthly_sales_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($monthly_sales_result as $row) {
        $monthly_sales_data[] = $row;
    }
}

// Generate system notifications
// Check for low stock and expired products
// This should be called periodically, perhaps once per day or on specific events
// For demonstration, we'll call it on dashboard load
// In production, this should be scheduled or triggered by events
// generateSystemNotifications($conn, $_SESSION['tenant_id']);

// Fetch recent activities for the tenant
$recent_activities = [];
$activities_query = "SELECT 
    'sale' as type,
    id as reference_id,
    total_amount as amount,
    created_at
FROM sales 
WHERE tenant_id = ? AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
UNION ALL
SELECT 
    'purchase' as type,
    id as reference_id,
    total_amount as amount,
    created_at
FROM purchases 
WHERE tenant_id = ? AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
ORDER BY created_at DESC 
LIMIT 10";

$activities_stmt = $conn->prepare($activities_query);
if ($activities_stmt) {
    $activities_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $activities_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $activities_stmt->execute();
    $activities_result = $activities_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($activities_result as $row) {
        $recent_activities[] = $row;
    }
}

// Debug: Print the stock movements data for troubleshooting
// Uncomment the following lines for debugging
/*
echo "<!-- Stock Movements Data: ";
print_r($stock_movements_data);
echo " -->";
*/
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('dashboard'); ?> - Complete Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .sidebar {
                width: 16rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
                position: fixed;
                height: 100vh;
                z-index: 1000;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: flex;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-container {
                flex-direction: column;
            }
            
            .header-title {
                font-size: 1.25rem;
            }
            
            .user-info {
                display: none;
            }
            
            .mobile-user-info {
                display: block;
            }
            
            /* Performance charts responsive adjustments */
            .grid.grid-cols-1.lg\:grid-cols-2.gap-4.mt-4 {
                grid-template-columns: 1fr;
            }
            
            .grid.grid-cols-1.lg\:grid-cols-3.gap-4 {
                grid-template-columns: 1fr;
            }
            
            .lg\:col-span-2 {
                grid-column: span 1;
            }
            
            /* Ensure charts resize properly on mobile */
            canvas {
                max-width: 100%;
                height: auto !important;
            }
        }
        
        @media (max-width: 480px) {
            .header-actions {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .language-selector select {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
            
            .add-user-btn, .show-all-users-btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
            
            .stats-grid {
                gap: 0.5rem;
            }
            
            .stat-card {
                padding: 0.75rem;
            }
            
            .stat-value {
                font-size: 1.25rem;
            }
            
            .stat-label {
                font-size: 0.75rem;
            }
            
            /* Additional mobile adjustments */
            body {
                padding: 10px;
            }
            
            .p-4 {
                padding: 1rem;
            }
            
            .gap-4 {
                gap: 1rem;
            }
            
            /* Performance chart adjustments for small screens */
            #monthlySalesChart, #stockMovementsChart {
                height: 150px !important;
            }
            
            .text-sm {
                font-size: 0.875rem;
            }
            
            .text-xs {
                font-size: 0.75rem;
            }
        }
        
        /* Extra small devices (phones, 320px and down) */
        @media (max-width: 320px) {
            .p-4 {
                padding: 0.75rem;
            }
            
            .gap-4 {
                gap: 0.75rem;
            }
            
            #monthlySalesChart, #stockMovementsChart {
                height: 120px !important;
            }
            
            .stat-card {
                padding: 0.5rem;
            }
            
            .stat-value {
                font-size: 1.125rem;
            }
            
            .stat-label {
                font-size: 0.625rem;
            }
        }
        
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(0, 0, 0, 0.3);
            align-items: center;
            justify-content: center;
        }
        
        .menu-toggle:hover {
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
        
        /* Ensure proper mobile menu behavior */
        .sidebar {
            will-change: transform;
            backface-visibility: hidden;
            transform: translateZ(0);
        }
        
        .mobile-user-info {
            display: none;
        }
        
        /* Real-time dashboard animations */
        .summary-card {
            transition: all 0.3s ease;
        }
        
        .real-time-indicator {
            display: inline-flex;
            align-items: center;
        }
        
        .real-time-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #10B981;
            margin-right: 6px;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: pulseDot 2s infinite;
        }
        
        @keyframes pulseDot {
            0% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }
            70% {
                box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }
    </style>
</head>
<body>
    <!-- Debug information - remove in production -->
    <div style="display: none;">
        Stock Movements Data: <?php echo json_encode($stock_movements_data); ?>
    </div>
    
    <button class="menu-toggle" id="menu-toggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="overlay" id="overlay"></div>
    
    <div class="flex h-screen bg-gray-50">
        <!-- Sidebar -->
        <div class="sidebar w-64 bg-gradient-to-b from-green-700 to-green-900 shadow-lg" id="sidebar">
            <div class="p-4 border-b border-green-600">
                <h1 class="text-xl font-bold text-white"><?php echo t('ims'); ?></h1>
                <p class="text-sm text-green-200"><?php echo t('inventory_management'); ?></p>
            </div>
            <nav class="mt-4 sidebar-nav">
                <a href="dashboard.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-white bg-gradient-to-r from-green-500 to-green-600 border-l-4 border-green-300 sidebar-menu-item">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    <span><?php echo t('dashboard'); ?></span>
                </a>
                <?php if (in_array('manage_products', $user_permissions) || in_array('manage_categories', $user_permissions)): ?>
                <a href="products.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200 sidebar-menu-item">
                    <i class="fas fa-box mr-3"></i>
                    <span><?php echo t('products'); ?></span>
                </a>
                <?php endif; ?>
                <?php if (in_array('manage_categories', $user_permissions)): ?>
                <a href="categories.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200 sidebar-menu-item">
                    <i class="fas fa-tags mr-3"></i>
                    <span><?php echo t('categories'); ?></span>
                </a>
                <?php endif; ?>
                <?php if (in_array('manage_purchases', $user_permissions)): ?>
                <a href="purchases.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200 sidebar-menu-item">
                    <i class="fas fa-shopping-cart mr-3"></i>
                    <span><?php echo t('purchases'); ?></span>
                </a>
                <?php endif; ?>
                <?php if (in_array('manage_expenses', $user_permissions)): ?>
                <a href="expenses.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200 sidebar-menu-item">
                    <i class="fas fa-file-invoice-dollar mr-3"></i>
                    <span><?php echo t('expenses'); ?></span>
                </a>
                <?php endif; ?>
                <?php if (in_array('manage_suppliers', $user_permissions)): ?>
                <a href="suppliers.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200 sidebar-menu-item">
                    <i class="fas fa-truck mr-3"></i>
                    <span><?php echo t('suppliers'); ?></span>
                </a>
                <?php endif; ?>
                <?php if (in_array('access_pos', $user_permissions)): ?>
                <a href="pos.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200 sidebar-menu-item">
                    <i class="fas fa-cash-register mr-3"></i>
                    <span><?php echo t('point_of_sale'); ?></span>
                </a>
                <?php endif; ?>
                <?php if (in_array('view_stock_movements', $user_permissions)): ?>
                <a href="stock-movements.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200 sidebar-menu-item">
                    <i class="fas fa-exchange-alt mr-3"></i>
                    <span><?php echo t('stock_movements'); ?></span>
                </a>
                <?php endif; ?>
                <?php if (in_array('manage_customers', $user_permissions)): ?>
                <a href="customers.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200 sidebar-menu-item">
                    <i class="fas fa-users mr-3"></i>
                    <span><?php echo t('customers'); ?></span>
                </a>
                <?php endif; ?>
                <?php if (in_array('manage_sales', $user_permissions)): ?>
                <a href="sales.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200 sidebar-menu-item">
                    <i class="fas fa-shopping-cart mr-3"></i>
                    <span><?php echo t('sales'); ?></span>
                </a>
                <?php endif; ?>
                <?php if (in_array('view_reports', $user_permissions)): ?>
                <a href="advanced_reports.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200 sidebar-menu-item">
                    <i class="fas fa-chart-line mr-3"></i>
                    <span><?php echo t('advanced_reports'); ?></span>
                </a>
                <?php endif; ?>
                <?php if (in_array('manage_users', $user_permissions)): ?>
                <a href="users.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200 sidebar-menu-item">
                    <i class="fas fa-user mr-3"></i>
                    <span><?php echo t('users'); ?></span>
                </a>
                <?php endif; ?>
                <?php if (in_array('manage_settings', $user_permissions)): ?>
                <a href="settings.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200 sidebar-menu-item">
                    <i class="fas fa-cog mr-3"></i>
                    <span><?php echo t('settings'); ?></span>
                </a>
                <?php endif; ?>
                
                <!-- Notifications link -->
                <a href="notifications.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200 sidebar-menu-item">
                    <i class="fas fa-bell mr-3"></i>
                    <span><?php echo t('notifications'); ?></span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden main-content">
            <!-- Header -->
            <header class="flex items-center justify-between p-4 bg-gradient-to-r from-green-600 to-green-800 shadow">
                <div class="flex items-center">
                    <div>
                        <h2 class="header-title text-xl font-semibold text-white"><?php echo t('dashboard'); ?> - <?php echo htmlspecialchars($business_name); ?></h2>
                        <p class="text-green-100 text-sm mt-1">Welcome, <?php echo htmlspecialchars($username); ?>!</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4 header-actions">
                    <!-- Notifications -->
                    <div class="relative">
                        <button id="notifications-button" class="text-white relative focus:outline-none">
                            <i class="fas fa-bell text-xl"></i>
                            <?php 
                            $unread_count = getNotificationCount($conn, $_SESSION['user_id'], $_SESSION['tenant_id']);
                            if ($unread_count > 0): 
                            ?>
                                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                    <?php echo min($unread_count, 99); ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        
                        <!-- Notifications dropdown -->
                        <div id="notifications-dropdown" class="absolute right-0 mt-2 w-80 bg-white rounded-md shadow-lg py-1 hidden z-50">
                            <div class="px-4 py-2 border-b border-gray-200 flex justify-between items-center">
                                <h3 class="text-sm font-medium text-gray-900"><?php echo t('notifications'); ?></h3>
                                <?php if ($unread_count > 0): ?>
                                    <button id="mark-all-read" class="text-xs text-green-600 hover:text-green-800">
                                        <?php echo t('mark_all_as_read'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="max-h-96 overflow-y-auto">
                                <?php 
                                $notifications = getAllNotifications($conn, $_SESSION['user_id'], $_SESSION['tenant_id'], 10);
                                if (empty($notifications)): 
                                ?>
                                    <div class="px-4 py-3 text-center text-gray-500 text-sm">
                                        <?php echo t('no_notifications'); ?>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="px-4 py-3 border-b border-gray-100 hover:bg-gray-50 <?php echo !$notification['is_read'] ? 'bg-blue-50' : ''; ?>">
                                            <div class="flex justify-between">
                                                <h4 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($notification['title']); ?></h4>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="h-2 w-2 rounded-full bg-green-500"></span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <div class="flex justify-between items-center mt-2">
                                                <span class="text-xs text-gray-500">
                                                    <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                                </span>
                                                <div class="flex space-x-2">
                                                    <?php if (!$notification['is_read']): ?>
                                                        <button class="mark-read text-xs text-green-600 hover:text-green-800" data-id="<?php echo $notification['id']; ?>">
                                                            <?php echo t('mark_as_read'); ?>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="delete-notification text-xs text-red-600 hover:text-red-800" data-id="<?php echo $notification['id']; ?>">
                                                        <?php echo t('delete'); ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="px-4 py-2 border-t border-gray-200 text-center">
                                <a href="notifications.php?lang=<?php echo $current_lang; ?>" class="text-sm text-green-600 hover:text-green-800">
                                    <?php echo t('view_all_notifications'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Language Selector -->
                    <div class="relative language-selector">
                        <select onchange="window.location.href=this.value" class="bg-green-600 text-white rounded px-2 py-1 text-sm">
                            <option value="?lang=en" <?php echo ($current_lang == 'en') ? 'selected' : ''; ?>><?php echo t('english'); ?></option>
                            <option value="?lang=fr" <?php echo ($current_lang == 'fr') ? 'selected' : ''; ?>><?php echo t('french'); ?></option>
                            <option value="?lang=rw" <?php echo ($current_lang == 'rw') ? 'selected' : ''; ?>><?php echo t('kinyarwanda'); ?></option>
                        </select>
                    </div>
                    
                    <!-- User Profile Dropdown -->
                    <div class="relative">
                        <button id="user-menu-button" class="flex items-center space-x-2 text-white focus:outline-none">
                            <div class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center">
                                <i class="fas fa-user text-white"></i>
                            </div>
                            <div class="text-left user-info">
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

            <!-- Dashboard Content -->
            <main class="flex-1 overflow-y-auto p-4">
                <!-- Essential Dashboard Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div class="bg-gradient-to-r from-green-500 to-green-700 rounded-lg shadow overflow-hidden">
                        <div class="p-3">
                            <div class="flex items-center">
                                <div class="p-2 rounded-full bg-white bg-opacity-20 text-white">
                                    <i class="fas fa-box text-lg"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-xs text-white text-opacity-80"><?php echo t('total_products'); ?></p>
                                    <h3 class="text-xl font-bold text-white"><?php echo $total_products; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-r from-purple-500 to-purple-700 rounded-lg shadow overflow-hidden">
                        <div class="p-3">
                            <div class="flex items-center">
                                <div class="p-2 rounded-full bg-white bg-opacity-20 text-white">
                                    <i class="fas fa-users text-lg"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-xs text-white text-opacity-88"><?php echo t('customers'); ?></p>
                                    <h3 class="text-xl font-bold text-white"><?php echo $total_customers; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-r from-orange-500 to-orange-700 rounded-lg shadow overflow-hidden">
                        <div class="p-3">
                            <div class="flex items-center">
                                <div class="p-2 rounded-full bg-white bg-opacity-20 text-white">
                                    <i class="fas fa-exclamation-triangle text-lg"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-xs text-white text-opacity-80"><?php echo t('expired_products'); ?></p>
                                    <h3 class="text-xl font-bold text-white"><?php echo $expired_products; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Real-time Sales Dashboard -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <!-- Sales Summary Cards -->
                    <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="summary-card bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow overflow-hidden">
                            <div class="p-3">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-xs opacity-80"><?php echo t('todays_sales'); ?></p>
                                        <p class="text-lg font-bold text-white">FRW<?php echo number_format($daily_sales, 2); ?></p>
                                    </div>
                                    <i class="fas fa-shopping-cart text-base opacity-80"></i>
                                </div>
                                <div class="mt-2 text-xs opacity-90">
                                    <span class="flex items-center">
                                        <i class="fas fa-arrow-up mr-1 text-xs"></i>
                                        <?php echo t('increased_from_yesterday'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="summary-card bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg shadow overflow-hidden">
                            <div class="p-3">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-xs opacity-80"><?php echo t('todays_profit'); ?></p>
                                        <p class="text-lg font-bold text-white">FRW<?php echo number_format($daily_profit, 2); ?></p>
                                    </div>
                                    <i class="fas fa-chart-line text-base opacity-80"></i>
                                </div>
                                <div class="mt-2 text-xs opacity-90">
                                    <span class="flex items-center">
                                        <i class="fas fa-arrow-up mr-1 text-xs"></i>
                                        <?php echo t('profit_margin'); ?>: <?php echo $daily_sales > 0 ? number_format(($daily_profit / $daily_sales) * 100, 1) : '0'; ?>%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activities -->
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="p-3 border-b border-gray-200">
                            <h3 class="text-sm font-medium text-gray-800"><?php echo t('recent_activities'); ?></h3>
                        </div>
                        <div class="p-3">
                            <div class="space-y-3 max-h-48 overflow-y-auto">
                                <?php if (empty($recent_activities)): ?>
                                    <p class="text-gray-500 text-center py-3 text-sm"><?php echo t('no_recent_activities'); ?></p>
                                <?php else: ?>
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <div class="flex items-start pb-2 last:pb-0">
                                            <div class="flex-shrink-0 mt-1">
                                                <div class="w-6 h-6 rounded-full flex items-center justify-center 
                                                    <?php echo $activity['type'] === 'sale' ? 'bg-green-100 text-green-600' : 'bg-blue-100 text-blue-600'; ?>">
                                                    <i class="fas fa-<?php echo $activity['type'] === 'sale' ? 'shopping-cart' : 'box'; ?> text-xs"></i>
                                                </div>
                                            </div>
                                            <div class="ml-2 flex-1">
                                                <p class="text-xs font-medium text-gray-900">
                                                    <?php echo $activity['type'] === 'sale' ? t('sale_transaction') : t('purchase_transaction'); ?>
                                                    #<?php echo $activity['reference_id']; ?>
                                                </p>
                                                <p class="text-xs text-gray-600">FRW<?php echo number_format($activity['amount'], 2); ?></p>
                                                <p class="text-xs text-gray-500"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Business Performance Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
                    <!-- Monthly Sales Chart -->
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="p-3 border-b border-gray-200">
                            <h3 class="text-sm font-medium text-gray-800"><?php echo t('monthly_sales_trend'); ?></h3>
                        </div>
                        <div class="p-3">
                            <canvas id="monthlySalesChart" height="200" class="w-full"></canvas>
                        </div>
                    </div>
                    
                    <!-- Stock Movements Chart -->
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="p-3 border-b border-gray-200">
                            <h3 class="text-sm font-medium text-gray-800"><?php echo t('stock_movements'); ?> (<?php echo t('last_7_days'); ?>)</h3>
                        </div>
                        <div class="p-3">
                            <canvas id="stockMovementsChart" height="200" class="w-full"></canvas>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        // Toggle mobile menu
        document.getElementById('menu-toggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            sidebar.classList.toggle('open');
            overlay.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
        });
        
        // Close mobile menu when clicking on overlay
        document.getElementById('overlay').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            sidebar.classList.remove('open');
            overlay.style.display = 'none';
        });
        
        // User dropdown toggle
        document.getElementById('user-menu-button').addEventListener('click', function() {
            const dropdown = document.getElementById('user-dropdown');
            dropdown.classList.toggle('hidden');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userDropdown = document.getElementById('user-dropdown');
            const userButton = document.getElementById('user-menu-button');
            const notificationsDropdown = document.getElementById('notifications-dropdown');
            const notificationsButton = document.getElementById('notifications-button');
            
            // Close user dropdown if clicked outside
            if (!userButton.contains(event.target) && !userDropdown.contains(event.target)) {
                userDropdown.classList.add('hidden');
            }
            
            // Close notifications dropdown if clicked outside
            if (!notificationsButton.contains(event.target) && !notificationsDropdown.contains(event.target)) {
                notificationsDropdown.classList.add('hidden');
            }
        });
        
        // Notifications dropdown toggle
        document.getElementById('notifications-button').addEventListener('click', function() {
            const dropdown = document.getElementById('notifications-dropdown');
            const userDropdown = document.getElementById('user-dropdown');
            
            // Close user dropdown if open
            userDropdown.classList.add('hidden');
            
            // Toggle notifications dropdown
            dropdown.classList.toggle('hidden');
        });
        
        // Mark notification as read
        document.querySelectorAll('.mark-read').forEach(button => {
            button.addEventListener('click', function() {
                const notificationId = this.getAttribute('data-id');
                
                // Send AJAX request to mark as read
                fetch('api/notifications/mark_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: notificationId,
                        user_id: <?php echo $_SESSION['user_id']; ?>,
                        tenant_id: <?php echo $_SESSION['tenant_id']; ?>
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the notification from the list or update UI
                        this.closest('.px-4').classList.remove('bg-blue-50');
                        this.remove();
                        
                        // Update notification count
                        updateNotificationCount();
                    }
                })
                .catch(error => console.error('Error:', error));
            });
        });
        
        // Delete notification
        document.querySelectorAll('.delete-notification').forEach(button => {
            button.addEventListener('click', function() {
                const notificationId = this.getAttribute('data-id');
                
                // Send AJAX request to delete notification
                fetch('api/notifications/delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: notificationId,
                        user_id: <?php echo $_SESSION['user_id']; ?>,
                        tenant_id: <?php echo $_SESSION['tenant_id']; ?>
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the notification from the list
                        this.closest('.px-4').remove();
                        
                        // Update notification count
                        updateNotificationCount();
                    }
                })
                .catch(error => console.error('Error:', error));
            });
        });
        
        // Mark all as read
        const markAllReadButton = document.getElementById('mark-all-read');
        if (markAllReadButton) {
            markAllReadButton.addEventListener('click', function() {
                // Send AJAX request to mark all as read
                fetch('api/notifications/mark_all_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: <?php echo $_SESSION['user_id']; ?>,
                        tenant_id: <?php echo $_SESSION['tenant_id']; ?>
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update UI to show all notifications as read
                        document.querySelectorAll('.mark-read').forEach(button => {
                            button.closest('.px-4').classList.remove('bg-blue-50');
                            button.remove();
                        });
                        
                        // Update notification count
                        updateNotificationCount();
                        
                        // Hide the mark all read button
                        this.remove();
                    }
                })
                .catch(error => console.error('Error:', error));
            });
        }
        
        // Update notification count
        function updateNotificationCount() {
            fetch('api/notifications/count.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: <?php echo $_SESSION['user_id']; ?>,
                    tenant_id: <?php echo $_SESSION['tenant_id']; ?>
                })
            })
            .then(response => response.json())
            .then(data => {
                const countElement = document.querySelector('#notifications-button .absolute');
                if (data.count > 0) {
                    if (!countElement) {
                        // Create count element if it doesn't exist
                        const countSpan = document.createElement('span');
                        countSpan.className = 'absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center';
                        countSpan.textContent = Math.min(data.count, 99);
                        document.getElementById('notifications-button').appendChild(countSpan);
                    } else {
                        countElement.textContent = Math.min(data.count, 99);
                    }
                } else if (countElement) {
                    countElement.remove();
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Real-time data refresh for summary cards
        function refreshDashboardData() {
            // Add a subtle animation to the summary cards
            var summaryCards = document.querySelectorAll('.summary-card');
            summaryCards.forEach(function(card) {
                card.classList.add('animate-pulse');
                setTimeout(function() {
                    card.classList.remove('animate-pulse');
                }, 1000);
            });
        }
        
        // Refresh data every 10 seconds
        setInterval(refreshDashboardData, 10000);
        
        // Initialize performance charts
        document.addEventListener('DOMContentLoaded', function() {
            // Monthly Sales Chart
            var monthlyCtx = document.getElementById('monthlySalesChart').getContext('2d');
            
            // Prepare data for monthly sales chart
            var monthlyLabels = [];
            var monthlySalesData = [];
            
            // PHP data to JavaScript
            var monthlySales = <?php echo json_encode($monthly_sales_data); ?>;
            
            // Process data
            monthlySales.forEach(function(item) {
                monthlyLabels.push(item.month);
                monthlySalesData.push(parseFloat(item.monthly_sales) || 0);
            });
            
            // If no data, add some default values for demonstration
            if (monthlyLabels.length === 0) {
                var today = new Date();
                for (var i = 5; i >= 0; i--) {
                    var date = new Date();
                    date.setMonth(today.getMonth() - i);
                    monthlyLabels.push(date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0'));
                    monthlySalesData.push(0);
                }
            }
            
            var monthlySalesChart = new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        label: '<?php echo t('monthly_sales'); ?> (FRW)',
                        data: monthlySalesData,
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'FRW' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
            
            // Stock Movements Chart
            var stockCtx = document.getElementById('stockMovementsChart').getContext('2d');
            
            // Prepare data for stock movements chart
            var stockLabels = [];
            var stockInData = [];
            var stockOutData = [];
            
            // PHP data to JavaScript
            var stockMovements = <?php echo json_encode($stock_movements_data); ?>;
            
            // Process data
            stockMovements.forEach(function(item) {
                stockLabels.push(item.date);
                stockInData.push(parseInt(item.stock_in) || 0);
                stockOutData.push(parseInt(item.stock_out) || 0);
            });
            
            // If no data, add some default values for demonstration
            if (stockLabels.length === 0) {
                var today = new Date();
                for (var i = 6; i >= 0; i--) {
                    var date = new Date();
                    date.setDate(today.getDate() - i);
                    stockLabels.push(date.toISOString().split('T')[0]);
                    stockInData.push(0);
                    stockOutData.push(0);
                }
            }
            
            var stockMovementsChart = new Chart(stockCtx, {
                type: 'bar',
                data: {
                    labels: stockLabels,
                    datasets: [
                        {
                            label: '<?php echo t('stock_in'); ?>',
                            data: stockInData,
                            backgroundColor: 'rgba(34, 197, 94, 0.7)',
                            borderColor: 'rgb(34, 197, 94)',
                            borderWidth: 1
                        },
                        {
                            label: '<?php echo t('stock_out'); ?>',
                            data: stockOutData,
                            backgroundColor: 'rgba(239, 68, 68, 0.7)',
                            borderColor: 'rgb(239, 68, 68)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });

    </script>
</body>
</html>