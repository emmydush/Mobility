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

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $product_id = $_POST['product_id'] ?? 0;
                $type = $_POST['type'] ?? '';
                $quantity = $_POST['quantity'] ?? 0;
                $reason = $_POST['reason'] ?? '';
                
                if (is_numeric($product_id) && $product_id > 0 && !empty($type) && is_numeric($quantity) && $quantity > 0 && !empty($reason)) {
                    // First verify that the product belongs to the current tenant
                    $verify_query = "SELECT id, price, cost_price FROM products WHERE id = ? AND tenant_id = ?";
                    $verify_stmt = $conn->prepare($verify_query);
                    $verify_stmt->bindParam(1, $product_id, PDO::PARAM_INT);
                    $verify_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                    $verify_stmt->execute();
                    $verify_result = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($verify_result) > 0) {
                        $product = $verify_result[0];
                        // Use cost_price for stock-in movements, selling price for stock-out
                        $unit_price = ($type === 'in') ? $product['cost_price'] : $product['price'];
                        $total_value = $unit_price * $quantity;
                        
                        // Insert stock movement
                        $query = "INSERT INTO stock_movements (tenant_id, product_id, type, quantity, unit_price, total_value, notes) VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($query);
                        if ($stmt) {
                            $stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
                            $stmt->bindParam(2, $product_id, PDO::PARAM_INT);
                            $stmt->bindParam(3, $type, PDO::PARAM_STR);
                            $stmt->bindParam(4, $quantity, PDO::PARAM_INT);
                            $stmt->bindParam(5, $unit_price, PDO::PARAM_STR);
                            $stmt->bindParam(6, $total_value, PDO::PARAM_STR);
                            $stmt->bindParam(7, $reason, PDO::PARAM_STR);
                            if ($stmt->execute()) {
                                // Update product stock
                                if ($type === 'in') {
                                    $update_query = "UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ? AND tenant_id = ?";
                                } else {
                                    $update_query = "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND tenant_id = ?";
                                }
                                
                                $update_stmt = $conn->prepare($update_query);
                                if ($update_stmt) {
                                    $update_stmt->bindParam(1, $quantity, PDO::PARAM_INT);
                                    $update_stmt->bindParam(2, $product_id, PDO::PARAM_INT);
                                    $update_stmt->bindParam(3, $_SESSION['tenant_id'], PDO::PARAM_INT);
                                    $update_stmt->execute();
                                    $update_stmt->closeCursor();
                                }
                                
                                $message = "Stock movement added successfully!";
                                $message_type = "success";
                            } else {
                                $message = "Error adding stock movement.";
                                $message_type = "error";
                            }
                            $stmt->closeCursor();
                        } else {
                            $message = "Database error.";
                            $message_type = "error";
                        }
                    } else {
                        $message = "Product not found or you don't have permission to use it.";
                        $message_type = "error";
                    }
                    $verify_stmt->closeCursor();
                } else {
                    $message = "All fields are required and product/quantity must be valid.";
                    $message_type = "error";
                }
                break;
        }
    }
}

// Fetch all stock movements with product names for the current tenant
$movements = array();
$query = "SELECT sm.*, p.name as product_name FROM stock_movements sm LEFT JOIN products p ON sm.product_id = p.id WHERE sm.tenant_id = ? ORDER BY sm.created_at DESC";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($result as $row) {
        $movements[] = $row;
    }
    $stmt->closeCursor();
}

// Fetch all products for the dropdown for the current tenant
$products = array();
$product_query = "SELECT id, name FROM products WHERE tenant_id = ? ORDER BY name";
$product_stmt = $conn->prepare($product_query);
if ($product_stmt) {
    $product_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $product_stmt->execute();
    $product_result = $product_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($product_result as $row) {
        $products[] = $row;
    }
    $product_stmt->closeCursor();
}
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Movements - Complete Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                <a href="stock-movements.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-white bg-gradient-to-r from-green-500 to-green-600 border-l-4 border-green-300">
                    <i class="fas fa-exchange-alt mr-3"></i>
                    <span>Stock Movements</span>
                </a>
                <a href="customers.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-users mr-3"></i>
                    <span>Customers</span>
                </a>
                <a href="sales.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-cash-register mr-3"></i>
                    <span>Sales</span>
                </a>
                <a href="reports.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
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
                <h2 class="text-xl font-semibold text-white">Stock Movements</h2>
                <div class="flex items-center space-x-4">
                    <button id="add-movement-btn" class="px-4 py-2 text-sm bg-white text-green-600 rounded hover:bg-green-50 transition duration-200">
                        <i class="fas fa-plus mr-1"></i> Add Movement
                    </button>
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

            <!-- Message Display -->
            <?php if ($message): ?>
                <div class="p-4 <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                    <div class="container mx-auto">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stock Movements Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-800">Movement History</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (count($movements) > 0): ?>
                                    <?php foreach ($movements as $movement): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($movement['product_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?php echo $movement['type'] === 'in' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                    <?php echo $movement['type'] === 'in' ? 'IN' : 'OUT'; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $movement['quantity']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                FRW<?php echo number_format($movement['total_value'], 2); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($movement['notes']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M j, Y', strtotime($movement['created_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                            No stock movements found
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Movement Modal -->
    <div id="movement-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add Stock Movement</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="movement-product">
                            Product
                        </label>
                        <select id="movement-product" name="product_id" required
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <option value="">Select Product</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="movement-type">
                            Type
                        </label>
                        <select id="movement-type" name="type" required
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <option value="">Select Type</option>
                            <option value="in">Stock In</option>
                            <option value="out">Stock Out</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="movement-quantity">
                            Quantity
                        </label>
                        <input type="number" id="movement-quantity" name="quantity" required
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="movement-reason">
                            Reason
                        </label>
                        <select id="movement-reason" name="reason" required
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <option value="">Select Reason</option>
                            <option value="purchase">Purchase</option>
                            <option value="sale">Sale</option>
                            <option value="return">Return</option>
                            <option value="damage">Damage</option>
                            <option value="adjustment">Adjustment</option>
                        </select>
                    </div>
                    <div class="flex items-center justify-between mt-6">
                        <button type="button" id="cancel-movement-btn"
                            class="px-4 py-2 text-sm bg-gray-600 text-white rounded hover:bg-gray-700">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 text-sm bg-blue-600 text-white rounded hover:bg-blue-700">
                            Save Movement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Simple JavaScript for modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('movement-modal');
            const addMovementBtn = document.getElementById('add-movement-btn');
            const cancelBtn = document.getElementById('cancel-movement-btn');
            
            addMovementBtn.addEventListener('click', function() {
                modal.classList.remove('hidden');
            });
            
            cancelBtn.addEventListener('click', function() {
                modal.classList.add('hidden');
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.classList.add('hidden');
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