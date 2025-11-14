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

// Fetch report data
$total_sales = 0;
$total_revenue = 0;
$total_products = 0;
$total_customers = 0;

// Get total sales count
$sales_count_query = "SELECT COUNT(*) as count FROM sales";
$sales_count_result = $conn->query($sales_count_query);
if ($sales_count_result) {
    $row = $sales_count_result->fetch(PDO::FETCH_ASSOC);
    $total_sales = $row['count'];
}

// Get total revenue
$revenue_query = "SELECT SUM(total_amount) as total FROM sales";
$revenue_result = $conn->query($revenue_query);
if ($revenue_result) {
    $row = $revenue_result->fetch(PDO::FETCH_ASSOC);
    $total_revenue = $row['total'] ?? 0;
}

// Get total products
$products_query = "SELECT COUNT(*) as count FROM products";
$products_result = $conn->query($products_query);
if ($products_result) {
    $row = $products_result->fetch(PDO::FETCH_ASSOC);
    $total_products = $row['count'];
}

// Get total customers
$customers_query = "SELECT COUNT(*) as count FROM customers";
$customers_result = $conn->query($customers_query);
if ($customers_result) {
    $row = $customers_result->fetch(PDO::FETCH_ASSOC);
    $total_customers = $row['count'];
}

// Fetch sales data for chart
$sales_data = [];
$sales_chart_query = "SELECT DATE(created_at) as date, COUNT(*) as count FROM sales 
                      WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                      GROUP BY DATE(created_at)
                      ORDER BY DATE(created_at)";
$sales_chart_result = $conn->query($sales_chart_query);
if ($sales_chart_result) {
    while ($row = $sales_chart_result->fetch(PDO::FETCH_ASSOC)) {
        $sales_data[] = $row;
    }
}

// Fetch top selling products
$top_products = [];
$top_products_query = "SELECT p.name, SUM(si.quantity) as total_sold
                       FROM sale_items si
                       JOIN products p ON si.product_id = p.id
                       JOIN sales s ON si.sale_id = s.id
                       GROUP BY p.id, p.name
                       ORDER BY total_sold DESC
                       LIMIT 5";
$top_products_result = $conn->query($top_products_query);
if ($top_products_result) {
    while ($row = $top_products_result->fetch(PDO::FETCH_ASSOC)) {
        $top_products[] = $row;
    }
}

// Fetch tenant performance
$tenant_performance = [];
$tenant_performance_query = "SELECT t.business_name, COUNT(s.id) as sales_count, SUM(s.total_amount) as revenue
                             FROM tenants t
                             LEFT JOIN sales s ON t.id = s.tenant_id
                             GROUP BY t.id, t.business_name
                             ORDER BY revenue DESC";
$tenant_performance_result = $conn->query($tenant_performance_query);
if ($tenant_performance_result) {
    while ($row = $tenant_performance_result->fetch(PDO::FETCH_ASSOC)) {
        $tenant_performance[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('system_reports'); ?> - <?php echo t('super_admin'); ?> <?php echo t('dashboard'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-gradient-to-b from-green-700 to-green-900 shadow-lg">
            <div class="p-4 border-b border-green-700">
                <h1 class="text-xl font-bold text-white">IMS <?php echo t('super_admin'); ?></h1>
                <p class="text-sm text-green-200"><?php echo t('system_management'); ?></p>
            </div>
            <nav class="mt-4">
                <a href="super_admin_dashboard.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    <span><?php echo t('dashboard'); ?></span>
                </a>
                <a href="super_admin_tenants.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-building mr-3"></i>
                    <span><?php echo t('tenants'); ?></span>
                </a>
                <a href="super_admin_users.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-users mr-3"></i>
                    <span><?php echo t('users'); ?></span>
                </a>
                <a href="super_admin_security.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-shield-alt mr-3"></i>
                    <span><?php echo t('security'); ?></span>
                </a>
                <a href="super_admin_settings.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-cog mr-3"></i>
                    <span><?php echo t('settings'); ?></span>
                </a>
                <a href="super_admin_reports.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-white bg-gradient-to-r from-green-600 to-green-700 border-l-4 border-green-300">
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
                <h2 class="text-xl font-semibold text-white"><?php echo t('system_reports'); ?></h2>
                <div class="flex items-center space-x-4">
                    <!-- Language Selector -->
                    <div class="relative">
                        <select onchange="window.location.href=this.value" class="bg-green-600 text-white px-3 py-1 rounded text-sm focus:outline-none">
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

            <!-- Reports Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <!-- Report Overview Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                                <i class="fas fa-shopping-cart text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm"><?php echo t('total_sales'); ?></p>
                                <p class="text-2xl font-bold"><?php echo number_format($total_sales); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                                <i class="fas fa-dollar-sign text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm"><?php echo t('total_revenue'); ?></p>
                                <p class="text-2xl font-bold">$<?php echo number_format($total_revenue, 2); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                                <i class="fas fa-box text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm"><?php echo t('products'); ?></p>
                                <p class="text-2xl font-bold"><?php echo number_format($total_products); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                                <i class="fas fa-users text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm"><?php echo t('customers'); ?></p>
                                <p class="text-2xl font-bold"><?php echo number_format($total_customers); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts and Reports -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Sales Chart -->
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-800"><?php echo t('sales_trend'); ?></h3>
                        </div>
                        <div class="p-6">
                            <canvas id="salesChart" height="300"></canvas>
                        </div>
                    </div>
                    
                    <!-- Top Selling Products -->
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-800"><?php echo t('top_selling_products'); ?></h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('products'); ?></th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('units_sold'); ?></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($top_products)): ?>
                                        <tr>
                                            <td colspan="2" class="px-6 py-4 text-center text-gray-500">
                                                <?php echo t('no_sales_data'); ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($top_products as $product): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($product['name']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo number_format($product['total_sold']); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tenant Performance -->
                <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-800"><?php echo t('tenant_performance'); ?></h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('tenants'); ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('sales_count'); ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('revenue'); ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php echo t('performance'); ?></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($tenant_performance)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                            <?php echo t('no_tenants_found'); ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tenant_performance as $tenant): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($tenant['business_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo number_format($tenant['sales_count']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                $<?php echo number_format($tenant['revenue'] ?? 0, 2); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="w-full bg-gray-200 rounded-full h-2">
                                                    <div class="bg-green-600 h-2 rounded-full" 
                                                         style="width: <?php echo min(100, ($tenant['revenue'] ?? 0) > 0 ? ($tenant['revenue'] / max(array_column($tenant_performance, 'revenue')) * 100) : 0); ?>%"></div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Report Generation -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-800"><?php echo t('generate_reports'); ?></h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="border border-gray-200 rounded-lg p-4 text-center">
                                <div class="text-green-600 text-2xl mb-2">
                                    <i class="fas fa-file-invoice"></i>
                                </div>
                                <h4 class="font-medium text-gray-800 mb-2"><?php echo t('sales_report'); ?></h4>
                                <button class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                                    <?php echo t('generate'); ?>
                                </button>
                            </div>
                            
                            <div class="border border-gray-200 rounded-lg p-4 text-center">
                                <div class="text-green-600 text-2xl mb-2">
                                    <i class="fas fa-boxes"></i>
                                </div>
                                <h4 class="font-medium text-gray-800 mb-2"><?php echo t('inventory_report'); ?></h4>
                                <button class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                                    <?php echo t('generate'); ?>
                                </button>
                            </div>
                            
                            <div class="border border-gray-200 rounded-lg p-4 text-center">
                                <div class="text-purple-600 text-2xl mb-2">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h4 class="font-medium text-gray-800 mb-2"><?php echo t('customer_report'); ?></h4>
                                <button class="px-3 py-1 bg-purple-600 text-white text-sm rounded hover:bg-purple-700">
                                    <?php echo t('generate'); ?>
                                </button>
                            </div>
                            
                            <div class="border border-gray-200 rounded-lg p-4 text-center">
                                <div class="text-yellow-600 text-2xl mb-2">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <h4 class="font-medium text-gray-800 mb-2"><?php echo t('financial_report'); ?></h4>
                                <button class="px-3 py-1 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700">
                                    <?php echo t('generate'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Initialize sales chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('salesChart').getContext('2d');
            
            // Prepare data for chart
            const dates = <?php echo json_encode(array_column($sales_data, 'date')); ?>;
            const counts = <?php echo json_encode(array_column($sales_data, 'count')); ?>;
            
            const salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [{
                        label: '<?php echo t('sales'); ?>',
                        data: counts,
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
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