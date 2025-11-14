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

// Check if user has permission to view reports
$user_permissions = getUserPermissions($conn, $_SESSION['user_id']);
if (!in_array('view_reports', $user_permissions)) {
    header("Location: dashboard.php?lang=" . $current_lang);
    exit();
}

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'cashier';

// Check if user has a tenant
if (!isset($_SESSION['tenant_id']) || empty($_SESSION['tenant_id'])) {
    die("Access denied. No tenant assigned to this user.");
}

$tenant_id = $_SESSION['tenant_id'];

// Set default date range
$startDate = date('Y-m-01'); // First day of current month
$endDate = date('Y-m-t');   // Last day of current month
$comparisonStartDate = date('Y-m-01', strtotime('-1 month')); // First day of previous month
$comparisonEndDate = date('Y-m-t', strtotime('-1 month'));   // Last day of previous month

// Handle date filter
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $startDate = $_GET['start_date'];
    $endDate = $_GET['end_date'];
    
    // Calculate comparison period (previous period of same length)
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $interval = $start->diff($end);
    $comparisonEndDate = $start->format('Y-m-d');
    $comparisonStart = clone $start;
    $comparisonStart->sub($interval);
    $comparisonStartDate = $comparisonStart->format('Y-m-d');
}

// Initialize all report data arrays
$inventory_summary = [
    'total_products' => 0,
    'low_stock_items' => 0,
    'out_of_stock_items' => 0,
    'total_inventory_value' => 0
];

$sales_summary = [
    'total_sales' => 0,
    'total_transactions' => 0,
    'average_transaction_value' => 0,
    'total_customers' => 0
];

$comparison_sales_summary = [
    'total_sales' => 0,
    'total_transactions' => 0,
    'average_transaction_value' => 0
];

$sales_by_category = [];
$top_products = [];
$customer_analysis = [];
$payment_methods = [];
$low_stock_alerts = [];
$out_of_stock_items = [];
$sales_trend = [];
$hourly_sales = [];
$product_performance = [];
$category_performance = [];
$staff_performance = [];

// Error handling for database operations
$database_error = false;
$error_message = '';

try {
    // Fetch inventory summary data with tenant filtering
    $inventory_summary['total_products'] = 0;
    $inventory_summary['low_stock_items'] = 0;
    $inventory_summary['out_of_stock_items'] = 0;
    $inventory_summary['total_inventory_value'] = 0;
    
    // Total products
    $product_count_query = "SELECT COUNT(*) as count FROM products WHERE tenant_id = ?";
    $product_count_stmt = $conn->prepare($product_count_query);
    if ($product_count_stmt) {
        $product_count_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $product_count_stmt->execute();
        $product_count_result = $product_count_stmt->fetch(PDO::FETCH_ASSOC);
        $inventory_summary['total_products'] = $product_count_result['count'] ?? 0;
    }
    
    // Low stock items (below minimum stock)
    $low_stock_query = "SELECT COUNT(*) as count FROM products WHERE stock_quantity <= minimum_stock AND stock_quantity > 0 AND tenant_id = ?";
    $low_stock_stmt = $conn->prepare($low_stock_query);
    if ($low_stock_stmt) {
        $low_stock_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $low_stock_stmt->execute();
        $low_stock_result = $low_stock_stmt->fetch(PDO::FETCH_ASSOC);
        $inventory_summary['low_stock_items'] = $low_stock_result['count'] ?? 0;
    }
    
    // Out of stock items
    $out_of_stock_query = "SELECT COUNT(*) as count FROM products WHERE stock_quantity = 0 AND tenant_id = ?";
    $out_of_stock_stmt = $conn->prepare($out_of_stock_query);
    if ($out_of_stock_stmt) {
        $out_of_stock_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $out_of_stock_stmt->execute();
        $out_of_stock_result = $out_of_stock_stmt->fetch(PDO::FETCH_ASSOC);
        $inventory_summary['out_of_stock_items'] = $out_of_stock_result['count'] ?? 0;
    }
    
    // Total inventory value
    $inventory_value_query = "SELECT SUM(price * stock_quantity) as total_value FROM products WHERE tenant_id = ?";
    $inventory_value_stmt = $conn->prepare($inventory_value_query);
    if ($inventory_value_stmt) {
        $inventory_value_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $inventory_value_stmt->execute();
        $inventory_value_result = $inventory_value_stmt->fetch(PDO::FETCH_ASSOC);
        $inventory_summary['total_inventory_value'] = $inventory_value_result['total_value'] ?? 0;
    }
    
    // Fetch sales summary data for current period with tenant filtering
    $sales_summary['total_sales'] = 0;
    $sales_summary['total_transactions'] = 0;
    $sales_summary['average_transaction_value'] = 0;
    $sales_summary['total_customers'] = 0;
    
    // Total sales and transactions for current period
    $sales_query = "SELECT 
                        COALESCE(SUM(total_amount), 0) as total_sales,
                        COUNT(*) as total_transactions
                    FROM sales 
                    WHERE DATE(created_at) BETWEEN ? AND ? AND payment_status = 'completed' AND tenant_id = ?";
    $sales_stmt = $conn->prepare($sales_query);
    if ($sales_stmt) {
        $sales_stmt->bindParam(1, $startDate, PDO::PARAM_STR);
        $sales_stmt->bindParam(2, $endDate, PDO::PARAM_STR);
        $sales_stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $sales_stmt->execute();
        $sales_result = $sales_stmt->fetch(PDO::FETCH_ASSOC);
        $sales_data = $sales_result;
        $sales_summary['total_sales'] = $sales_data['total_sales'] ?? 0;
        $sales_summary['total_transactions'] = $sales_data['total_transactions'] ?? 0;
        $sales_summary['average_transaction_value'] = $sales_summary['total_transactions'] > 0 ? 
            $sales_summary['total_sales'] / $sales_summary['total_transactions'] : 0;
    }
    
    // Total sales and transactions for comparison period
    $comparison_sales_query = "SELECT 
                        COALESCE(SUM(total_amount), 0) as total_sales,
                        COUNT(*) as total_transactions
                    FROM sales 
                    WHERE DATE(created_at) BETWEEN ? AND ? AND payment_status = 'completed' AND tenant_id = ?";
    $comparison_sales_stmt = $conn->prepare($comparison_sales_query);
    if ($comparison_sales_stmt) {
        $comparison_sales_stmt->bindParam(1, $comparisonStartDate, PDO::PARAM_STR);
        $comparison_sales_stmt->bindParam(2, $comparisonEndDate, PDO::PARAM_STR);
        $comparison_sales_stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $comparison_sales_stmt->execute();
        $comparison_sales_result = $comparison_sales_stmt->fetch(PDO::FETCH_ASSOC);
        $comparison_sales_data = $comparison_sales_result;
        $comparison_sales_summary['total_sales'] = $comparison_sales_data['total_sales'] ?? 0;
        $comparison_sales_summary['total_transactions'] = $comparison_sales_data['total_transactions'] ?? 0;
        $comparison_sales_summary['average_transaction_value'] = $comparison_sales_summary['total_transactions'] > 0 ? 
            $comparison_sales_summary['total_sales'] / $comparison_sales_summary['total_transactions'] : 0;
    }
    
    // Total customers with tenant filtering
    $customer_count_query = "SELECT COUNT(*) as count FROM customers WHERE tenant_id = ?";
    $customer_count_stmt = $conn->prepare($customer_count_query);
    if ($customer_count_stmt) {
        $customer_count_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $customer_count_stmt->execute();
        $customer_count_result = $customer_count_stmt->fetch(PDO::FETCH_ASSOC);
        $sales_summary['total_customers'] = $customer_count_result['count'] ?? 0;
    }
    
    // Sales by category with tenant filtering
    $sales_by_category_query = "SELECT 
                                    c.name as category_name,
                                    SUM(si.quantity) as units_sold,
                                    SUM(si.total_price) as revenue
                                FROM sale_items si
                                JOIN products p ON si.product_id = p.id 
                                JOIN categories c ON p.category_id = c.id
                                JOIN sales s ON si.sale_id = s.id
                                WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.payment_status = 'completed' AND s.tenant_id = ?
                                GROUP BY c.id, c.name
                                ORDER BY revenue DESC";
    $sales_by_category_stmt = $conn->prepare($sales_by_category_query);
    if ($sales_by_category_stmt) {
        $sales_by_category_stmt->bindParam(1, $startDate, PDO::PARAM_STR);
        $sales_by_category_stmt->bindParam(2, $endDate, PDO::PARAM_STR);
        $sales_by_category_stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $sales_by_category_stmt->execute();
        $sales_by_category_result = $sales_by_category_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($sales_by_category_result as $row) {
            $sales_by_category[] = $row;
        }
    }
    
    // Top selling products with tenant filtering
    $top_products_query = "SELECT 
                                p.name,
                                c.name as category_name,
                                SUM(si.quantity) as units_sold,
                                SUM(si.total_price) as revenue
                            FROM sale_items si
                            JOIN products p ON si.product_id = p.id 
                            JOIN categories c ON p.category_id = c.id
                            JOIN sales s ON si.sale_id = s.id
                            WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.payment_status = 'completed' AND s.tenant_id = ?
                            GROUP BY si.product_id, p.name, c.name
                            ORDER BY revenue DESC 
                            LIMIT 10";
    $top_products_stmt = $conn->prepare($top_products_query);
    if ($top_products_stmt) {
        $top_products_stmt->bindParam(1, $startDate, PDO::PARAM_STR);
        $top_products_stmt->bindParam(2, $endDate, PDO::PARAM_STR);
        $top_products_stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $top_products_stmt->execute();
        $top_products_result = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($top_products_result as $row) {
            $top_products[] = $row;
        }
    }
    
    // Customer analysis with tenant filtering
    $customer_analysis_query = "SELECT 
                                    COUNT(DISTINCT s.customer_id) as active_customers,
                                    AVG(s.total_amount) as avg_customer_spending
                                FROM sales s
                                WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.payment_status = 'completed' AND s.customer_id IS NOT NULL AND s.tenant_id = ?";
    $customer_analysis_stmt = $conn->prepare($customer_analysis_query);
    if ($customer_analysis_stmt) {
        $customer_analysis_stmt->bindParam(1, $startDate, PDO::PARAM_STR);
        $customer_analysis_stmt->bindParam(2, $endDate, PDO::PARAM_STR);
        $customer_analysis_stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $customer_analysis_stmt->execute();
        $customer_analysis_result = $customer_analysis_stmt->fetch(PDO::FETCH_ASSOC);
        $customer_analysis = $customer_analysis_result;
    }
    
    // Payment methods analysis with tenant filtering
    $payment_methods_query = "SELECT 
                                    payment_method,
                                    COUNT(*) as transaction_count,
                                    SUM(total_amount) as total_amount
                                FROM sales
                                WHERE DATE(created_at) BETWEEN ? AND ? AND payment_status = 'completed' AND tenant_id = ?
                                GROUP BY payment_method
                                ORDER BY total_amount DESC";
    $payment_methods_stmt = $conn->prepare($payment_methods_query);
    if ($payment_methods_stmt) {
        $payment_methods_stmt->bindParam(1, $startDate, PDO::PARAM_STR);
        $payment_methods_stmt->bindParam(2, $endDate, PDO::PARAM_STR);
        $payment_methods_stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $payment_methods_stmt->execute();
        $payment_methods_result = $payment_methods_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($payment_methods_result as $row) {
            $payment_methods[] = $row;
        }
    }
    
    // Low stock alerts with tenant filtering
    $low_stock_alerts_query = "SELECT 
                                    name,
                                    stock_quantity,
                                    minimum_stock
                                FROM products 
                                WHERE stock_quantity <= minimum_stock AND stock_quantity > 0 AND tenant_id = ?
                                ORDER BY stock_quantity ASC
                                LIMIT 10";
    $low_stock_alerts_stmt = $conn->prepare($low_stock_alerts_query);
    if ($low_stock_alerts_stmt) {
        $low_stock_alerts_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $low_stock_alerts_stmt->execute();
        $low_stock_alerts_result = $low_stock_alerts_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($low_stock_alerts_result as $row) {
            $low_stock_alerts[] = $row;
        }
    }
    
    // Out of stock items with tenant filtering
    $out_of_stock_items_query = "SELECT 
                                    name,
                                    price
                                FROM products 
                                WHERE stock_quantity = 0 AND tenant_id = ?
                                ORDER BY name ASC";
    $out_of_stock_items_stmt = $conn->prepare($out_of_stock_items_query);
    if ($out_of_stock_items_stmt) {
        $out_of_stock_items_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $out_of_stock_items_stmt->execute();
        $out_of_stock_items_result = $out_of_stock_items_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($out_of_stock_items_result as $row) {
            $out_of_stock_items[] = $row;
        }
    }
    
    // Sales trend data for chart with tenant filtering
    $sales_trend_query = "SELECT 
                            DATE(created_at) as date,
                            SUM(total_amount) as daily_sales
                        FROM sales 
                        WHERE DATE(created_at) BETWEEN ? AND ? AND payment_status = 'completed' AND tenant_id = ?
                        GROUP BY DATE(created_at)
                        ORDER BY DATE(created_at)";
    $sales_trend_stmt = $conn->prepare($sales_trend_query);
    if ($sales_trend_stmt) {
        $sales_trend_stmt->bindParam(1, $startDate, PDO::PARAM_STR);
        $sales_trend_stmt->bindParam(2, $endDate, PDO::PARAM_STR);
        $sales_trend_stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $sales_trend_stmt->execute();
        $sales_trend_result = $sales_trend_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($sales_trend_result as $row) {
            $sales_trend[] = $row;
        }
        $sales_trend_stmt->closeCursor();
    }
    
    // Hourly sales analysis with tenant filtering
    $hourly_sales_query = "SELECT 
                            EXTRACT(HOUR FROM created_at) as hour,
                            COUNT(*) as transaction_count,
                            SUM(total_amount) as hourly_sales
                        FROM sales 
                        WHERE DATE(created_at) BETWEEN ? AND ? AND payment_status = 'completed' AND tenant_id = ?
                        GROUP BY EXTRACT(HOUR FROM created_at)
                        ORDER BY EXTRACT(HOUR FROM created_at)";
    $hourly_sales_stmt = $conn->prepare($hourly_sales_query);
    if ($hourly_sales_stmt) {
        $hourly_sales_stmt->bindParam(1, $startDate, PDO::PARAM_STR);
        $hourly_sales_stmt->bindParam(2, $endDate, PDO::PARAM_STR);
        $hourly_sales_stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $hourly_sales_stmt->execute();
        $hourly_sales_result = $hourly_sales_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($hourly_sales_result as $row) {
            $hourly_sales[] = $row;
        }
        $hourly_sales_stmt->closeCursor();
    }
    
    // Product performance analysis with tenant filtering
    $product_performance_query = "SELECT 
                                    p.name,
                                    c.name as category,
                                    SUM(si.quantity) as units_sold,
                                    SUM(si.total_price) as revenue,
                                    AVG(si.total_price / si.quantity) as avg_selling_price,
                                    COUNT(DISTINCT s.id) as transaction_count
                                FROM sale_items si
                                JOIN products p ON si.product_id = p.id
                                JOIN categories c ON p.category_id = c.id
                                JOIN sales s ON si.sale_id = s.id
                                WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.payment_status = 'completed' AND s.tenant_id = ?
                                GROUP BY si.product_id, p.name, c.name
                                ORDER BY revenue DESC
                                LIMIT 15";
    $product_performance_stmt = $conn->prepare($product_performance_query);
    if ($product_performance_stmt) {
        $product_performance_stmt->bindParam(1, $startDate, PDO::PARAM_STR);
        $product_performance_stmt->bindParam(2, $endDate, PDO::PARAM_STR);
        $product_performance_stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $product_performance_stmt->execute();
        $product_performance_result = $product_performance_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($product_performance_result as $row) {
            $product_performance[] = $row;
        }
        $product_performance_stmt->closeCursor();
    }
    
    // Category performance analysis with tenant filtering
    $category_performance_query = "SELECT 
                                    c.name as category,
                                    COUNT(si.id) as items_sold,
                                    SUM(si.quantity) as units_sold,
                                    SUM(si.total_price) as revenue,
                                    AVG(si.total_price / si.quantity) as avg_selling_price,
                                    COUNT(DISTINCT s.id) as transaction_count
                                FROM sale_items si
                                JOIN products p ON si.product_id = p.id
                                JOIN categories c ON p.category_id = c.id
                                JOIN sales s ON si.sale_id = s.id
                                WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.payment_status = 'completed' AND s.tenant_id = ?
                                GROUP BY c.id, c.name
                                ORDER BY revenue DESC";
    $category_performance_stmt = $conn->prepare($category_performance_query);
    if ($category_performance_stmt) {
        $category_performance_stmt->bindParam(1, $startDate, PDO::PARAM_STR);
        $category_performance_stmt->bindParam(2, $endDate, PDO::PARAM_STR);
        $category_performance_stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $category_performance_stmt->execute();
        $category_performance_result = $category_performance_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($category_performance_result as $row) {
            $category_performance[] = $row;
        }
        $category_performance_stmt->closeCursor();
    }
    
    // Staff performance analysis with tenant filtering
    $staff_performance_query = "SELECT 
                                    u.username as staff_name,
                                    u.role as staff_role,
                                    COUNT(s.id) as transactions_handled,
                                    SUM(s.total_amount) as total_sales,
                                    AVG(s.total_amount) as avg_transaction_value
                                FROM sales s
                                JOIN users u ON s.created_by = u.id
                                WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.payment_status = 'completed' AND s.tenant_id = ?
                                GROUP BY u.id, u.username, u.role
                                ORDER BY total_sales DESC";
    $staff_performance_stmt = $conn->prepare($staff_performance_query);
    if ($staff_performance_stmt) {
        $staff_performance_stmt->bindParam(1, $startDate, PDO::PARAM_STR);
        $staff_performance_stmt->bindParam(2, $endDate, PDO::PARAM_STR);
        $staff_performance_stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $staff_performance_stmt->execute();
        $staff_performance_result = $staff_performance_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($staff_performance_result as $row) {
            $staff_performance[] = $row;
        }
        $staff_performance_stmt->closeCursor();
    }
    
} catch (Exception $e) {
    $database_error = true;
    $error_message = "Database error: " . $e->getMessage();
} catch (Error $e) {
    $database_error = true;
    $error_message = "System error: " . $e->getMessage();
}

// Function to generate PDF report
function generatePDFReport($conn, $startDate, $endDate, $comparisonStartDate, $comparisonEndDate) {
    // Implementation would go here for PDF generation
    // For now, we'll just return a placeholder
    return "PDF generation functionality would be implemented here";
}

// Handle PDF download
if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
    echo generatePDFReport($conn, $startDate, $endDate, $comparisonStartDate, $comparisonEndDate);
    exit;
}

// Calculate growth percentages
$sales_growth = 0;
$transactions_growth = 0;
$avg_transaction_growth = 0;

if ($comparison_sales_summary['total_sales'] > 0) {
    $sales_growth = (($sales_summary['total_sales'] - $comparison_sales_summary['total_sales']) / $comparison_sales_summary['total_sales']) * 100;
}

if ($comparison_sales_summary['total_transactions'] > 0) {
    $transactions_growth = (($sales_summary['total_transactions'] - $comparison_sales_summary['total_transactions']) / $comparison_sales_summary['total_transactions']) * 100;
}

if ($comparison_sales_summary['average_transaction_value'] > 0) {
    $avg_transaction_growth = (($sales_summary['average_transaction_value'] - $comparison_sales_summary['average_transaction_value']) / $comparison_sales_summary['average_transaction_value']) * 100;
}
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Reports - Complete Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .growth-positive {
            color: #10B981;
        }
        .growth-negative {
            color: #EF4444;
        }
        .comparison-card {
            transition: transform 0.3s ease;
        }
        .comparison-card:hover {
            transform: translateY(-5px);
        }
        .data-table {
            max-height: 400px;
            overflow-y: auto;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
    </style>
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
                <a href="advanced_reports.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-white bg-gradient-to-r from-green-500 to-green-600 border-l-4 border-green-300">
                    <i class="fas fa-chart-line mr-3"></i>
                    <span>Advanced Reports</span>
                </a>
                <!-- Removed role restriction - all users can now access Users and Settings -->
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
                    <a href="?download=pdf&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" class="px-4 py-2 text-sm bg-green-600 text-white rounded hover:bg-green-700 transition duration-200">
                        <i class="fas fa-download mr-1"></i> Export PDF
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
                            <a href="logout.php?lang=<?php echo $current_lang; ?>" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2"></i><?php echo t('logout'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Error Message Display -->
            <?php if ($database_error): ?>
                <div class="p-4 bg-red-100 text-red-700">
                    <div class="container mx-auto">
                        <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Date Filter -->
            <div class="p-4 bg-white border-b">
                <form method="GET" class="flex flex-wrap items-center gap-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">Start Date</label>
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" 
                            class="px-3 py-2 border rounded text-sm">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">End Date</label>
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" 
                            class="px-3 py-2 border rounded text-sm">
                    </div>
                    <div class="self-end">
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm">
                            <i class="fas fa-filter mr-1"></i> Filter
                        </button>
                    </div>
                    <div class="self-end text-sm text-gray-600 ml-4">
                        Comparison period: <?php echo date('M j, Y', strtotime($comparisonStartDate)); ?> - <?php echo date('M j, Y', strtotime($comparisonEndDate)); ?>
                    </div>
                </form>
            </div>

            <!-- Reports Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <!-- Summary Cards with Growth Indicators -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white p-6 rounded-lg shadow comparison-card">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                                    <i class="fas fa-dollar-sign text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-sm">Total Sales</p>
                                    <p class="text-2xl font-bold">FRW<?php echo number_format($sales_summary['total_sales'], 2); ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-sm <?php echo $sales_growth >= 0 ? 'growth-positive' : 'growth-negative'; ?>">
                                    <?php echo $sales_growth >= 0 ? '+' : ''; ?><?php echo number_format($sales_growth, 1); ?>%
                                </span>
                                <p class="text-xs text-gray-500">vs prev period</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow comparison-card">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                                    <i class="fas fa-receipt text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-sm">Transactions</p>
                                    <p class="text-2xl font-bold"><?php echo number_format($sales_summary['total_transactions']); ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-sm <?php echo $transactions_growth >= 0 ? 'growth-positive' : 'growth-negative'; ?>">
                                    <?php echo $transactions_growth >= 0 ? '+' : ''; ?><?php echo number_format($transactions_growth, 1); ?>%
                                </span>
                                <p class="text-xs text-gray-500">vs prev period</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow comparison-card">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                                    <i class="fas fa-shopping-cart text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-sm">Avg Transaction</p>
                                    <p class="text-2xl font-bold">FRW<?php echo number_format($sales_summary['average_transaction_value'], 2); ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-sm <?php echo $avg_transaction_growth >= 0 ? 'growth-positive' : 'growth-negative'; ?>">
                                    <?php echo $avg_transaction_growth >= 0 ? '+' : ''; ?><?php echo number_format($avg_transaction_growth, 1); ?>%
                                </span>
                                <p class="text-xs text-gray-500">vs prev period</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow comparison-card">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                                <i class="fas fa-users text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Total Customers</p>
                                <p class="text-2xl font-bold"><?php echo number_format($sales_summary['total_customers']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sales Summary and Payment Methods -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white p-6 rounded-lg shadow lg:col-span-2">
                        <h3 class="text-lg font-semibold mb-4">Sales Summary (<?php echo date('M j, Y', strtotime($startDate)); ?> - <?php echo date('M j, Y', strtotime($endDate)); ?>)</h3>
                        <div class="space-y-4">
                            <div class="flex justify-between">
                                <span>Total Sales</span>
                                <span class="font-medium">FRW<?php echo number_format($sales_summary['total_sales'], 2); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Total Transactions</span>
                                <span class="font-medium"><?php echo number_format($sales_summary['total_transactions']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Average Transaction Value</span>
                                <span class="font-medium">FRW<?php echo number_format($sales_summary['average_transaction_value'], 2); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Total Customers</span>
                                <span class="font-medium"><?php echo number_format($sales_summary['total_customers']); ?></span>
                            </div>
                            <?php if (!empty($customer_analysis)): ?>
                            <div class="flex justify-between">
                                <span>Active Customers</span>
                                <span class="font-medium"><?php echo number_format($customer_analysis['active_customers'] ?? 0); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Avg Customer Spending</span>
                                <span class="font-medium">FRW<?php echo number_format($customer_analysis['avg_customer_spending'] ?? 0, 2); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow">
                        <h3 class="text-lg font-semibold mb-4">Payment Methods</h3>
                        <div class="space-y-3">
                            <?php if (!empty($payment_methods)): ?>
                                <?php foreach ($payment_methods as $method): ?>
                                    <div>
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="capitalize"><?php echo str_replace('_', ' ', htmlspecialchars($method['payment_method'])); ?></span>
                                            <span>FRW<?php echo number_format($method['total_amount'], 2); ?></span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-green-600 h-2 rounded-full" 
                                                style="width: <?php echo $sales_summary['total_sales'] > 0 ? min(100, ($method['total_amount'] / $sales_summary['total_sales']) * 100) : 0; ?>%"></div>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            <?php echo number_format($method['transaction_count']); ?> transactions
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-gray-500 text-center py-4">No payment data available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Sales Trend Chart -->
                    <div class="bg-white p-6 rounded-lg shadow">
                        <h3 class="text-lg font-semibold mb-4">Sales Trend</h3>
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Hourly Sales Analysis -->
                    <div class="bg-white p-6 rounded-lg shadow">
                        <h3 class="text-lg font-semibold mb-4">Peak Sales Hours</h3>
                        <div class="chart-container">
                            <canvas id="hourlySalesChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Data Tables Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Sales by Category -->
                    <div class="bg-white p-6 rounded-lg shadow">
                        <h3 class="text-lg font-semibold mb-4">Sales by Category</h3>
                        <div class="data-table">
                            <?php if (!empty($sales_by_category)): ?>
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Units Sold</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($sales_by_category as $category): ?>
                                            <tr>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($category['category_name']); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($category['units_sold']); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">FRW<?php echo number_format($category['revenue'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-gray-500 text-center py-8">No sales data available for selected period</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Top Selling Products -->
                    <div class="bg-white p-6 rounded-lg shadow">
                        <h3 class="text-lg font-semibold mb-4">Top Selling Products</h3>
                        <div class="data-table">
                            <?php if (!empty($top_products)): ?>
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Units Sold</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($top_products as $product): ?>
                                            <tr>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($product['category_name']); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($product['units_sold']); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">FRW<?php echo number_format($product['revenue'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-gray-500 text-center py-8">No product sales data available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Advanced Analytics Section -->
                <div class="grid grid-cols-1 gap-6 mb-6">
                    <!-- Product Performance Analysis -->
                    <div class="bg-white p-6 rounded-lg shadow">
                        <h3 class="text-lg font-semibold mb-4">Product Performance Analysis</h3>
                        <div class="data-table">
                            <?php if (!empty($product_performance)): ?>
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Units Sold</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Price</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($product_performance as $product): ?>
                                            <tr>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($product['category']); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($product['units_sold']); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">FRW<?php echo number_format($product['revenue'], 2); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">FRW<?php echo number_format($product['avg_selling_price'], 2); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($product['transaction_count']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-gray-500 text-center py-8">No product performance data available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Category Performance Analysis -->
                    <div class="bg-white p-6 rounded-lg shadow">
                        <h3 class="text-lg font-semibold mb-4">Category Performance Analysis</h3>
                        <div class="data-table">
                            <?php if (!empty($category_performance)): ?>
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items Sold</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Units Sold</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Price</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($category_performance as $category): ?>
                                            <tr>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($category['category']); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($category['items_sold']); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($category['units_sold']); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">FRW<?php echo number_format($category['revenue'], 2); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">FRW<?php echo number_format($category['avg_selling_price'], 2); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($category['transaction_count']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-gray-500 text-center py-8">No category performance data available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Staff Performance Analysis -->
                    <div class="bg-white p-6 rounded-lg shadow">
                        <h3 class="text-lg font-semibold mb-4">Staff Performance Analysis</h3>
                        <div class="data-table">
                            <?php if (!empty($staff_performance)): ?>
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff Name</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Sales</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Transaction</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($staff_performance as $staff): ?>
                                            <tr>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($staff['staff_name']); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500 capitalize"><?php echo htmlspecialchars($staff['staff_role']); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($staff['transactions_handled']); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">FRW<?php echo number_format($staff['total_sales'], 2); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">FRW<?php echo number_format($staff['avg_transaction_value'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-gray-500 text-center py-8">No staff performance data available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Inventory Alerts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Low Stock Alerts -->
                    <div class="bg-white p-6 rounded-lg shadow">
                        <h3 class="text-lg font-semibold mb-4">Low Stock Alerts</h3>
                        <div class="data-table">
                            <?php if (!empty($low_stock_alerts)): ?>
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Stock</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Minimum Stock</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($low_stock_alerts as $item): ?>
                                            <tr>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['name']); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($item['stock_quantity']); ?></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($item['minimum_stock']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-gray-500 text-center py-8">No low stock items</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Out of Stock Items -->
                    <div class="bg-white p-6 rounded-lg shadow">
                        <h3 class="text-lg font-semibold mb-4">Out of Stock Items</h3>
                        <div class="data-table">
                            <?php if (!empty($out_of_stock_items)): ?>
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Stock</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($out_of_stock_items as $item): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['name']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($item['stock_quantity']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-gray-500 text-center py-8">No out of stock items</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Sales chart
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Sales trend chart
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
            
            // Hourly sales chart
            var hourlyCtx = document.getElementById('hourlySalesChart').getContext('2d');
            
            // Prepare data for the hourly chart
            var hourlyLabels = [];
            var hourlyTransactionData = [];
            var hourlySalesData = [];
            
            // PHP data to JavaScript
            var hourlySalesTrendData = <?php echo json_encode($hourly_sales); ?>;
            
            // Process data
            hourlySalesTrendData.forEach(function(item) {
                hourlyLabels.push(item.hour + ':00');
                hourlyTransactionData.push(parseInt(item.transaction_count) || 0);
                hourlySalesData.push(parseFloat(item.hourly_sales) || 0);
            });
            
            var hourlySalesChart = new Chart(hourlyCtx, {
                type: 'bar',
                data: {
                    labels: hourlyLabels,
                    datasets: [
                        {
                            label: 'Transactions',
                            data: hourlyTransactionData,
                            backgroundColor: 'rgba(99, 102, 241, 0.7)',
                            borderColor: 'rgb(99, 102, 241)',
                            borderWidth: 1
                        },
                        {
                            label: 'Sales (FRW)',
                            data: hourlySalesData,
                            backgroundColor: 'rgba(16, 185, 129, 0.7)',
                            borderColor: 'rgb(16, 185, 129)',
                            borderWidth: 1,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Transactions'
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Sales (FRW)'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>