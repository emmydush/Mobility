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

// Check if user has a tenant assigned
if (!isset($_SESSION['tenant_id']) || !$_SESSION['tenant_id']) {
    die("No business account assigned to your user. Please contact support.");
}

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'cashier';

// Fetch inventory summary data for current tenant only
$total_products = 0;
$low_stock_items = 0;
$out_of_stock_items = 0;
$total_inventory_value = 0;

// Total products
$product_count_query = "SELECT COUNT(*) as count FROM products WHERE tenant_id = ?";
$product_count_stmt = $conn->prepare($product_count_query);
if ($product_count_stmt) {
    $product_count_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $product_count_stmt->execute();
    $product_count_result = $product_count_stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_products = $product_count_result[0]['count'];
    $product_count_stmt->closeCursor();
}

// Low stock items (below minimum stock)
$low_stock_query = "SELECT COUNT(*) as count FROM products WHERE stock_quantity <= minimum_stock AND stock_quantity > 0 AND tenant_id = ?";
$low_stock_stmt = $conn->prepare($low_stock_query);
if ($low_stock_stmt) {
    $low_stock_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $low_stock_stmt->execute();
    $low_stock_result = $low_stock_stmt->fetchAll(PDO::FETCH_ASSOC);
    $low_stock_items = $low_stock_result[0]['count'];
    $low_stock_stmt->closeCursor();
}

// Out of stock items
$out_of_stock_query = "SELECT COUNT(*) as count FROM products WHERE stock_quantity = 0 AND tenant_id = ?";
$out_of_stock_stmt = $conn->prepare($out_of_stock_query);
if ($out_of_stock_stmt) {
    $out_of_stock_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $out_of_stock_stmt->execute();
    $out_of_stock_result = $out_of_stock_stmt->fetchAll(PDO::FETCH_ASSOC);
    $out_of_stock_items = $out_of_stock_result[0]['count'];
    $out_of_stock_stmt->closeCursor();
}

// Total inventory value
$inventory_value_query = "SELECT SUM(selling_price * stock_quantity) as total_value FROM products WHERE tenant_id = ?";
$inventory_value_stmt = $conn->prepare($inventory_value_query);
if ($inventory_value_stmt) {
    $inventory_value_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $inventory_value_stmt->execute();
    $inventory_value_result = $inventory_value_stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_inventory_value = $inventory_value_result[0]['total_value'] ?? 0;
    $inventory_value_stmt->closeCursor();
}

// Fetch sales summary data for current tenant only
$daily_sales = 0;
$weekly_sales = 0;
$monthly_sales = 0;
$total_customers = 0;

// Daily sales
$daily_sales_query = "SELECT COALESCE(SUM(total_amount), 0) as daily_sales FROM sales WHERE DATE(created_at) = CURRENT_DATE AND tenant_id = ?";
$daily_sales_stmt = $conn->prepare($daily_sales_query);
if ($daily_sales_stmt) {
    $daily_sales_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $daily_sales_stmt->execute();
    $daily_sales_result = $daily_sales_stmt->fetchAll(PDO::FETCH_ASSOC);
    $daily_sales = $daily_sales_result[0]['daily_sales'];
    $daily_sales_stmt->closeCursor();
}

// Weekly sales
$weekly_sales_query = "SELECT COALESCE(SUM(total_amount), 0) as weekly_sales FROM sales WHERE EXTRACT('year' FROM created_at) = EXTRACT('year' FROM CURRENT_DATE) AND EXTRACT('week' FROM created_at) = EXTRACT('week' FROM CURRENT_DATE) AND tenant_id = ?";
$weekly_sales_stmt = $conn->prepare($weekly_sales_query);
if ($weekly_sales_stmt) {
    $weekly_sales_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $weekly_sales_stmt->execute();
    $weekly_sales_result = $weekly_sales_stmt->fetchAll(PDO::FETCH_ASSOC);
    $weekly_sales = $weekly_sales_result[0]['weekly_sales'];
    $weekly_sales_stmt->closeCursor();
}

// Monthly sales
$monthly_sales_query = "SELECT COALESCE(SUM(total_amount), 0) as monthly_sales FROM sales WHERE EXTRACT('year' FROM created_at) = EXTRACT('year' FROM CURRENT_DATE) AND EXTRACT('month' FROM created_at) = EXTRACT('month' FROM CURRENT_DATE) AND tenant_id = ?";
$monthly_sales_stmt = $conn->prepare($monthly_sales_query);
if ($monthly_sales_stmt) {
    $monthly_sales_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $monthly_sales_stmt->execute();
    $monthly_sales_result = $monthly_sales_stmt->fetchAll(PDO::FETCH_ASSOC);
    $monthly_sales = $monthly_sales_result[0]['monthly_sales'];
    $monthly_sales_stmt->closeCursor();
}

// Total customers
$customer_count_query = "SELECT COUNT(*) as count FROM customers WHERE tenant_id = ?";
$customer_count_stmt = $conn->prepare($customer_count_query);
if ($customer_count_stmt) {
    $customer_count_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $customer_count_stmt->execute();
    $customer_count_result = $customer_count_stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_customers = $customer_count_result[0]['count'];
    $customer_count_stmt->closeCursor();
}

// Fetch top selling products for current tenant only
$top_products = array();
$top_products_query = "SELECT p.name, c.name as category_name, SUM(si.quantity) as units_sold, SUM(si.total_price) as revenue 
                      FROM sale_items si
                      JOIN products p ON si.product_id = p.id 
                      JOIN categories c ON p.category_id = c.id
                      JOIN sales s ON si.sale_id = s.id
                      WHERE s.payment_status = 'completed' AND p.tenant_id = ? AND s.tenant_id = ?
                      GROUP BY si.product_id 
                      ORDER BY revenue DESC 
                      LIMIT 5";
$top_products_stmt = $conn->prepare($top_products_query);
if ($top_products_stmt) {
    $top_products_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $top_products_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $top_products_stmt->execute();
    $top_products_result = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($top_products_result as $row) {
        $top_products[] = $row;
    }
    $top_products_stmt->closeCursor();
}

// Sales trend data for chart (for current tenant only)
$sales_trend = array();
$sales_trend_query = "SELECT 
    DATE(created_at) as date,
    SUM(total_amount) as daily_sales
FROM sales 
WHERE created_at >= CURRENT_DATE - INTERVAL '7 days' AND payment_status = 'completed' AND tenant_id = ?
GROUP BY DATE(created_at)
ORDER BY DATE(created_at)";

$sales_trend_stmt = $conn->prepare($sales_trend_query);
if ($sales_trend_stmt) {
    $sales_trend_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $sales_trend_stmt->execute();
    $sales_trend_result = $sales_trend_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sales_trend_result as $row) {
        $sales_trend[] = $row;
    }
    $sales_trend_stmt->closeCursor();
}

// Function to generate PDF report
function generatePDFReport($conn, $tenant_id) {
    // Fetch all data needed for the report
    // Inventory summary
    $product_count_query = "SELECT COUNT(*) as count FROM products WHERE tenant_id = ?";
    $product_count_stmt = $conn->prepare($product_count_query);
    $product_count_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $product_count_stmt->execute();
    $product_count_result = $product_count_stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_products = $product_count_result[0]['count'];
    $product_count_stmt->closeCursor();
    
    // Low stock items
    $low_stock_query = "SELECT COUNT(*) as count FROM products WHERE stock_quantity <= minimum_stock AND stock_quantity > 0 AND tenant_id = ?";
    $low_stock_stmt = $conn->prepare($low_stock_query);
    $low_stock_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $low_stock_stmt->execute();
    $low_stock_result = $low_stock_stmt->fetchAll(PDO::FETCH_ASSOC);
    $low_stock_items = $low_stock_result[0]['count'];
    $low_stock_stmt->closeCursor();
    
    // Out of stock items
    $out_of_stock_query = "SELECT COUNT(*) as count FROM products WHERE stock_quantity = 0 AND tenant_id = ?";
    $out_of_stock_stmt = $conn->prepare($out_of_stock_query);
    $out_of_stock_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $out_of_stock_stmt->execute();
    $out_of_stock_result = $out_of_stock_stmt->fetchAll(PDO::FETCH_ASSOC);
    $out_of_stock_items = $out_of_stock_result[0]['count'];
    $out_of_stock_stmt->closeCursor();
    
    // Total inventory value
    $inventory_value_query = "SELECT SUM(selling_price * stock_quantity) as total_value FROM products WHERE tenant_id = ?";
    $inventory_value_stmt = $conn->prepare($inventory_value_query);
    $inventory_value_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $inventory_value_stmt->execute();
    $inventory_value_result = $inventory_value_stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_inventory_value = $inventory_value_result[0]['total_value'] ?? 0;
    $inventory_value_stmt->closeCursor();
    
    // Sales summary
    $daily_sales_query = "SELECT COALESCE(SUM(total_amount), 0) as daily_sales FROM sales WHERE DATE(created_at) = CURRENT_DATE AND tenant_id = ?";
    $daily_sales_stmt = $conn->prepare($daily_sales_query);
    $daily_sales_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $daily_sales_stmt->execute();
    $daily_sales_result = $daily_sales_stmt->fetchAll(PDO::FETCH_ASSOC);
    $daily_sales = $daily_sales_result[0]['daily_sales'];
    $daily_sales_stmt->closeCursor();
    
    $weekly_sales_query = "SELECT COALESCE(SUM(total_amount), 0) as weekly_sales FROM sales WHERE EXTRACT('year' FROM created_at) = EXTRACT('year' FROM CURRENT_DATE) AND EXTRACT('week' FROM created_at) = EXTRACT('week' FROM CURRENT_DATE) AND tenant_id = ?";
    $weekly_sales_stmt = $conn->prepare($weekly_sales_query);
    $weekly_sales_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $weekly_sales_stmt->execute();
    $weekly_sales_result = $weekly_sales_stmt->fetchAll(PDO::FETCH_ASSOC);
    $weekly_sales = $weekly_sales_result[0]['weekly_sales'];
    $weekly_sales_stmt->closeCursor();
    
    $monthly_sales_query = "SELECT COALESCE(SUM(total_amount), 0) as monthly_sales FROM sales WHERE EXTRACT('year' FROM created_at) = EXTRACT('year' FROM CURRENT_DATE) AND EXTRACT('month' FROM created_at) = EXTRACT('month' FROM CURRENT_DATE) AND tenant_id = ?";
    $monthly_sales_stmt = $conn->prepare($monthly_sales_query);
    $monthly_sales_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $monthly_sales_stmt->execute();
    $monthly_sales_result = $monthly_sales_stmt->fetchAll(PDO::FETCH_ASSOC);
    $monthly_sales = $monthly_sales_result[0]['monthly_sales'];
    $monthly_sales_stmt->closeCursor();
    
    // Customer count
    $customer_count_query = "SELECT COUNT(*) as count FROM customers WHERE tenant_id = ?";
    $customer_count_stmt = $conn->prepare($customer_count_query);
    $customer_count_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $customer_count_stmt->execute();
    $customer_count_result = $customer_count_stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_customers = $customer_count_result[0]['count'];
    $customer_count_stmt->closeCursor();
    
    // Top selling products
    $top_products = array();
    $top_products_query = "SELECT p.name, c.name as category_name, SUM(si.quantity) as units_sold, SUM(si.total_price) as revenue 
                          FROM sale_items si
                          JOIN products p ON si.product_id = p.id 
                          JOIN categories c ON p.category_id = c.id
                          JOIN sales s ON si.sale_id = s.id
                          WHERE s.payment_status = 'completed' AND p.tenant_id = ? AND s.tenant_id = ?
                          GROUP BY si.product_id 
                          ORDER BY revenue DESC 
                          LIMIT 5";
    $top_products_stmt = $conn->prepare($top_products_query);
    $top_products_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $top_products_stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $top_products_stmt->execute();
    $top_products_result = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($top_products_result as $row) {
        $top_products[] = $row;
    }
    $top_products_stmt->closeCursor();
    
    // Return all data as an array
    return array(
        'total_products' => $total_products,
        'low_stock_items' => $low_stock_items,
        'out_of_stock_items' => $out_of_stock_items,
        'total_inventory_value' => $total_inventory_value,
        'daily_sales' => $daily_sales,
        'weekly_sales' => $weekly_sales,
        'monthly_sales' => $monthly_sales,
        'total_customers' => $total_customers,
        'top_products' => $top_products
    );
}

?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('reports'); ?> - Complete Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="flex h-screen bg-gray-50">
        <!-- Sidebar -->
        <div class="w-64 bg-gradient-to-b from-green-700 to-green-900 shadow-lg">
            <div class="p-4 border-b border-green-600">
                <h1 class="text-xl font-bold text-white">IMS</h1>
                <p class="text-sm text-blue-200">Inventory Management</p>
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
                <a href="reports.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-white bg-gradient-to-r from-green-500 to-green-600 border-l-4 border-green-300">
                    <i class="fas fa-chart-bar mr-3"></i>
                    <span><?php echo t('reports'); ?></span>
                </a>
                <a href="advanced_reports.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-chart-line mr-3"></i>
                    <span>Advanced Reports</span>
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
                <h2 class="text-xl font-semibold text-white"><?php echo t('reports'); ?></h2>
                <div class="flex items-center space-x-4">
                    <!-- Language Selector -->
                    <div class="relative">
                        <select onchange="window.location.href=this.value" class="bg-green-600 text-white rounded px-2 py-1 text-sm">
                            <option value="?lang=en" <?php echo ($current_lang == 'en') ? 'selected' : ''; ?>>English</option>
                            <option value="?lang=fr" <?php echo ($current_lang == 'fr') ? 'selected' : ''; ?>>Français</option>
                            <option value="?lang=rw" <?php echo ($current_lang == 'rw') ? 'selected' : ''; ?>>Kinyarwanda</option>
                        </select>
                    </div>
                    <a href="?download=pdf" class="px-4 py-2 text-sm bg-white text-green-600 rounded hover:bg-green-50 transition duration-200">
                        <i class="fas fa-download mr-1"></i> Download Report
                    </a>
                    <!-- User Profile Dropdown -->
                    <div class="relative">
                        <button id="user-menu-button" class="flex items-center space-x-2 text-white focus:outline-none">
                            <div class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center">
                                <i class="fas fa-user text-white"></i>
                            </div>
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

            <!-- Reports Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div class="bg-white p-6 rounded-lg shadow">
                        <h3 class="text-lg font-semibold mb-4">Inventory Summary</h3>
                        <div class="space-y-4">
                            <div class="flex justify-between">
                                <span>Total Products</span>
                                <span class="font-medium"><?php echo $total_products; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Low Stock Items</span>
                                <span class="font-medium <?php echo $low_stock_items > 0 ? 'text-red-600' : ''; ?>"><?php echo $low_stock_items; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Out of Stock Items</span>
                                <span class="font-medium <?php echo $out_of_stock_items > 0 ? 'text-red-600' : ''; ?>"><?php echo $out_of_stock_items; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Total Inventory Value</span>
                                <span class="font-medium">FRW<?php echo number_format($total_inventory_value, 2); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow">
                        <h3 class="text-lg font-semibold mb-4">Sales Summary</h3>
                        <div class="space-y-4">
                            <div class="flex justify-between">
                                <span>Today's Sales</span>
                                <span class="font-medium">FRW<?php echo number_format($daily_sales, 2); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>This Week</span>
                                <span class="font-medium">FRW<?php echo number_format($weekly_sales, 2); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>This Month</span>
                                <span class="font-medium">FRW<?php echo number_format($monthly_sales, 2); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Total Customers</span>
                                <span class="font-medium"><?php echo $total_customers; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow mb-6">
                    <h3 class="text-lg font-semibold mb-4">Top Selling Products</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Units Sold</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (count($top_products) > 0): ?>
                                    <?php foreach ($top_products as $product): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($product['category_name']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $product['units_sold']; ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">FRW<?php echo number_format($product['revenue'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                            No sales data available
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-semibold mb-4">Sales Trend (Last 7 Days)</h3>
                    <div class="h-64">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Sales chart
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('salesChart').getContext('2d');
            
            // Prepare data for the chart
            var labels = [];
            var salesData = [];
            
            // PHP data to JavaScript
            var salesTrendData = <?php echo json_encode($sales_trend); ?>;
            
            // Process data
            salesTrendData.forEach(function(item) {
                labels.push(item.date);
                salesData.push(parseFloat(item.daily_sales) || 0);
            });
            
            // If no data, add some default values for demonstration
            if (labels.length === 0) {
                var today = new Date();
                for (var i = 6; i >= 0; i--) {
                    var date = new Date();
                    date.setDate(today.getDate() - i);
                    labels.push(date.toISOString().split('T')[0]);
                    salesData.push(0);
                }
            }
            
            var salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Daily Sales (FRW)',
                        data: salesData,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.1
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
            
            // User profile dropdown toggle
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