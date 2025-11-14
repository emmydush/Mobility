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
                $purchase_number = $_POST['purchase_number'] ?? '';
                $supplier_id = $_POST['supplier_id'] ?? 0;
                $subtotal = $_POST['subtotal'] ?? 0;
                $tax_amount = $_POST['tax_amount'] ?? 0;
                $discount_amount = $_POST['discount_amount'] ?? 0;
                $total_amount = $_POST['total_amount'] ?? 0;
                $amount_paid = $_POST['amount_paid'] ?? 0;
                $amount_due = $_POST['amount_due'] ?? 0;
                $payment_method = $_POST['payment_method'] ?? 'cash';
                $notes = $_POST['notes'] ?? '';
                
                // Validate required fields
                if (!empty($purchase_number) && !empty($supplier_id) && is_numeric($subtotal) && is_numeric($total_amount)) {
                    $conn->begin_transaction();
                    try {
                        // Insert purchase record
                        $query = "INSERT INTO purchases (purchase_number, supplier_id, subtotal, tax_amount, discount_amount, total_amount, amount_paid, amount_due, payment_method, payment_status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?)";
                        $stmt = $conn->prepare($query);
                        if ($stmt) {
                            $payment_status = ($amount_paid >= $total_amount) ? 'completed' : (($amount_paid > 0) ? 'partial' : 'pending');
                            $stmt->bindParam(1, $purchase_number, PDO::PARAM_STR);
                            $stmt->bindParam(2, $supplier_id, PDO::PARAM_INT);
                            $stmt->bindParam(3, $subtotal, PDO::PARAM_STR);
                            $stmt->bindParam(4, $tax_amount, PDO::PARAM_STR);
                            $stmt->bindParam(5, $discount_amount, PDO::PARAM_STR);
                            $stmt->bindParam(6, $total_amount, PDO::PARAM_STR);
                            $stmt->bindParam(7, $amount_paid, PDO::PARAM_STR);
                            $stmt->bindParam(8, $amount_due, PDO::PARAM_STR);
                            $stmt->bindParam(9, $payment_method, PDO::PARAM_STR);
                            $stmt->bindParam(10, $notes, PDO::PARAM_STR);
                            $stmt->bindParam(11, $_SESSION['user_id'], PDO::PARAM_INT);
                            if ($stmt->execute()) {
                                $purchase_id = $conn->lastInsertId();
                                
                                // Process purchase items
                                if (isset($_POST['items']) && is_array($_POST['items'])) {
                                    foreach ($_POST['items'] as $item) {
                                        $product_id = $item['product_id'] ?? 0;
                                        $quantity = $item['quantity'] ?? 0;
                                        $unit_cost = $item['unit_cost'] ?? 0;
                                        $total_cost = $item['total_cost'] ?? 0;
                                        $item_discount = $item['discount'] ?? 0;
                                        
                                        if ($product_id > 0 && $quantity > 0 && $unit_cost > 0) {
                                            // Insert purchase item
                                            $item_query = "INSERT INTO purchase_items (purchase_id, product_id, quantity, unit_cost, total_cost, discount_amount) VALUES (?, ?, ?, ?, ?, ?)";
                                            $item_stmt = $conn->prepare($item_query);
                                            if ($item_stmt) {
                                                $item_stmt->bindParam(1, $purchase_id, PDO::PARAM_INT);
                                                $item_stmt->bindParam(2, $product_id, PDO::PARAM_INT);
                                                $item_stmt->bindParam(3, $quantity, PDO::PARAM_INT);
                                                $item_stmt->bindParam(4, $unit_cost, PDO::PARAM_STR);
                                                $item_stmt->bindParam(5, $total_cost, PDO::PARAM_STR);
                                                $item_stmt->bindParam(6, $item_discount, PDO::PARAM_STR);
                                                if (!$item_stmt->execute()) {
                                                    throw new Exception("Error adding purchase item.");
                                                }
                                                $item_stmt->closeCursor();
                                                
                                                // Update product stock and cost price
                                                $update_query = "UPDATE products SET stock_quantity = stock_quantity + ?, cost_price = ? WHERE id = ?";
                                                $update_stmt = $conn->prepare($update_query);
                                                if ($update_stmt) {
                                                    $update_stmt->bindParam(1, $quantity, PDO::PARAM_INT);
                                                    $update_stmt->bindParam(2, $unit_cost, PDO::PARAM_STR);
                                                    $update_stmt->bindParam(3, $product_id, PDO::PARAM_INT);
                                                    if (!$update_stmt->execute()) {
                                                        throw new Exception("Error updating product stock.");
                                                    }
                                                    $update_stmt->closeCursor();
                                                } else {
                                                    throw new Exception("Error preparing product update statement.");
                                                }
                                            } else {
                                                throw new Exception("Error preparing purchase item statement.");
                                            }
                                        }
                                    }
                                }
                                
                                $conn->commit();
                                $message = "Purchase added successfully!";
                                $message_type = "success";
                            } else {
                                throw new Exception("Error adding purchase.");
                            }
                            $stmt->close();
                        } else {
                            throw new Exception("Error preparing purchase statement.");
                        }
                    } catch (Exception $e) {
                        $conn->rollback();
                        $message = "Error adding purchase: " . $e->getMessage();
                        $message_type = "error";
                    }
                } else {
                    $message = "Please fill in all required fields.";
                    $message_type = "error";
                }
                break;
                
            case 'delete':
                $id = $_POST['id'] ?? 0;
                if (is_numeric($id) && $id > 0) {
                    $conn->begin_transaction();
                    try {
                        // Get purchase details before deleting
                        $purchase_query = "SELECT * FROM purchases WHERE id = ?";
                        $purchase_stmt = $conn->prepare($purchase_query);
                        if ($purchase_stmt) {
                            $purchase_stmt->bindParam(1, $id, PDO::PARAM_INT);
                            $purchase_stmt->execute();
                            $purchase_result = $purchase_stmt->fetchAll(PDO::FETCH_ASSOC);
                            if (count($purchase_result) > 0) {
                                $purchase = $purchase_result[0];
                                $purchase_stmt->closeCursor();
                                
                                // Delete purchase (this will cascade delete purchase items)
                                $query = "DELETE FROM purchases WHERE id = ?";
                                $stmt = $conn->prepare($query);
                                if ($stmt) {
                                    $stmt->bindParam(1, $id, PDO::PARAM_INT);
                                    if ($stmt->execute()) {
                                        $stmt->closeCursor();
                                        $conn->commit();
                                        $message = "Purchase deleted successfully!";
                                        $message_type = "success";
                                    } else {
                                        throw new Exception("Error deleting purchase.");
                                    }
                                } else {
                                    throw new Exception("Error preparing delete statement.");
                                }
                            } else {
                                throw new Exception("Purchase not found.");
                            }
                        } else {
                            throw new Exception("Error preparing purchase query.");
                        }
                    } catch (Exception $e) {
                        $conn->rollback();
                        $message = "Error deleting purchase: " . $e->getMessage();
                        $message_type = "error";
                    }
                } else {
                    $message = "Invalid purchase ID.";
                    $message_type = "error";
                }
                break;
        }
    }
}

// Fetch all purchases with supplier names
$purchases = array();
$query = "SELECT p.*, s.name as supplier_name FROM purchases p LEFT JOIN suppliers s ON p.supplier_id = s.id ORDER BY p.created_at DESC";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($result as $row) {
        $purchases[] = $row;
    }
    $stmt->closeCursor();
}

// Fetch all suppliers for the dropdown
$suppliers = array();
$supplier_query = "SELECT id, name FROM suppliers WHERE status = 'active' ORDER BY name";
$supplier_stmt = $conn->prepare($supplier_query);
if ($supplier_stmt) {
    $supplier_stmt->execute();
    $supplier_result = $supplier_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($supplier_result as $row) {
        $suppliers[] = $row;
    }
    $supplier_stmt->closeCursor();
}

// Fetch all products for the dropdown
$products = array();
$product_query = "SELECT id, name, cost_price FROM products ORDER BY name";
$product_stmt = $conn->prepare($product_query);
if ($product_stmt) {
    $product_stmt->execute();
    $product_result = $product_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($product_result as $row) {
        $products[] = $row;
    }
    $product_stmt->closeCursor();
}

// Generate a unique purchase number
function generatePurchaseNumber($conn) {
    $prefix = "PUR-" . date("Y") . date("m");
    $query = "SELECT COUNT(*) as count FROM purchases WHERE purchase_number LIKE ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $search = $prefix . "%";
        $stmt->bindParam(1, $search, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $row = $result[0];
        $count = $row['count'] + 1;
        $stmt->closeCursor();
        return $prefix . str_pad($count, 4, "0", STR_PAD_LEFT);
    }
    return $prefix . "0001";
}

$purchase_number = generatePurchaseNumber($conn);
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchases - Complete Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                <a href="purchases.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-white bg-gradient-to-r from-green-500 to-green-600 border-l-4 border-green-300">
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
                <h2 class="text-xl font-semibold text-white">Purchase Management</h2>
                <div class="flex items-center space-x-4">
                    <!-- Language Selector -->
                    <div class="relative">
                        <select onchange="window.location.href=this.value" class="bg-green-600 text-white rounded px-2 py-1 text-sm">
                            <option value="?lang=en" <?php echo ($current_lang == 'en') ? 'selected' : ''; ?>>English</option>
                            <option value="?lang=fr" <?php echo ($current_lang == 'fr') ? 'selected' : ''; ?>>Français</option>
                            <option value="?lang=rw" <?php echo ($current_lang == 'rw') ? 'selected' : ''; ?>>Kinyarwanda</option>
                        </select>
                    </div>
                    <button id="add-purchase-btn" class="px-4 py-2 text-sm bg-white text-green-600 rounded hover:bg-green-50 transition duration-200">
                        <i class="fas fa-plus mr-1"></i> Add Purchase
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

            <!-- Purchases Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-800">Purchase List</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purchase #</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (count($purchases) > 0): ?>
                                    <?php foreach ($purchases as $purchase): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($purchase['purchase_number']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($purchase['supplier_name'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                FRW<?php echo number_format($purchase['total_amount'], 2); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?php echo $purchase['payment_status'] === 'completed' ? 'bg-green-100 text-green-800' : ($purchase['payment_status'] === 'partial' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                                    <?php echo ucfirst($purchase['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo date('M j, Y', strtotime($purchase['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button class="text-green-600 hover:text-green-900 mr-3">View</button>
                                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this purchase?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $purchase['id']; ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                            No purchases found
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

    <!-- Add Purchase Modal -->
    <div id="purchase-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 mx-auto p-4 border w-full max-w-4xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add New Purchase</h3>
                <form method="POST" id="purchase-form">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1" for="purchase-number">
                                Purchase Number *
                            </label>
                            <input type="text" id="purchase-number" name="purchase_number" value="<?php echo htmlspecialchars($purchase_number); ?>" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm" readonly>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1" for="supplier-id">
                                Supplier *
                            </label>
                            <select id="supplier-id" name="supplier_id" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>"><?php echo htmlspecialchars($supplier['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Purchase Items -->
                    <div class="mb-4">
                        <h4 class="text-md font-medium text-gray-800 mb-2">Purchase Items</h4>
                        <div id="purchase-items-container">
                            <div class="purchase-item grid grid-cols-1 md:grid-cols-12 gap-2 mb-2">
                                <div class="md:col-span-4">
                                    <label class="block text-gray-700 text-xs mb-1">Product *</label>
                                    <select name="items[0][product_id]" class="item-product shadow appearance-none border rounded w-full py-1 px-2 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm" required>
                                        <option value="">Select Product</option>
                                        <?php foreach ($products as $product): ?>
                                            <option value="<?php echo $product['id']; ?>" data-cost="<?php echo $product['cost_price']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-gray-700 text-xs mb-1">Quantity *</label>
                                    <input type="number" name="items[0][quantity]" class="item-quantity shadow appearance-none border rounded w-full py-1 px-2 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm" min="1" value="1" required>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-gray-700 text-xs mb-1">Unit Cost *</label>
                                    <input type="number" name="items[0][unit_cost]" class="item-unit-cost shadow appearance-none border rounded w-full py-1 px-2 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm" step="0.01" min="0" value="0" required>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-gray-700 text-xs mb-1">Total</label>
                                    <input type="number" name="items[0][total_cost]" class="item-total-cost shadow appearance-none border rounded w-full py-1 px-2 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm" step="0.01" min="0" value="0" readonly>
                                </div>
                                <div class="md:col-span-1">
                                    <label class="block text-gray-700 text-xs mb-1">Discount</label>
                                    <input type="number" name="items[0][discount]" class="item-discount shadow appearance-none border rounded w-full py-1 px-2 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm" step="0.01" min="0" value="0">
                                </div>
                                <div class="md:col-span-1 flex items-end">
                                    <button type="button" class="remove-item-btn bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded text-xs">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button type="button" id="add-item-btn" class="mt-2 bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-3 rounded text-sm">
                            <i class="fas fa-plus mr-1"></i> Add Item
                        </button>
                    </div>
                    
                    <!-- Financial Details -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1" for="subtotal">
                                Subtotal
                            </label>
                            <input type="number" id="subtotal" name="subtotal" step="0.01" min="0" value="0" readonly
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1" for="tax-amount">
                                Tax Amount
                            </label>
                            <input type="number" id="tax-amount" name="tax_amount" step="0.01" min="0" value="0"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1" for="discount-amount">
                                Discount
                            </label>
                            <input type="number" id="discount-amount" name="discount_amount" step="0.01" min="0" value="0"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1" for="total-amount">
                                Total Amount *
                            </label>
                            <input type="number" id="total-amount" name="total_amount" step="0.01" min="0" value="0" required readonly
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1" for="amount-paid">
                                Amount Paid
                            </label>
                            <input type="number" id="amount-paid" name="amount_paid" step="0.01" min="0" value="0"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1" for="amount-due">
                                Amount Due
                            </label>
                            <input type="number" id="amount-due" name="amount_due" step="0.01" min="0" value="0" readonly
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1" for="payment-method">
                                Payment Method
                            </label>
                            <select id="payment-method" name="payment_method"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="credit">Credit</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-1" for="notes">
                            Notes
                        </label>
                        <textarea id="notes" name="notes" rows="2"
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm"></textarea>
                    </div>
                    
                    <div class="flex items-center justify-between mt-6">
                        <button type="button" id="cancel-purchase-btn"
                            class="px-4 py-2 text-sm bg-gray-600 text-white rounded hover:bg-gray-700">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 text-sm bg-blue-600 text-white rounded hover:bg-blue-700">
                            Save Purchase
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Simple JavaScript for modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('purchase-modal');
            const addPurchaseBtn = document.getElementById('add-purchase-btn');
            const cancelBtn = document.getElementById('cancel-purchase-btn');
            
            addPurchaseBtn.addEventListener('click', function() {
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
            
            // Purchase item functionality
            let itemIndex = 1;
            
            // Add new item
            document.getElementById('add-item-btn').addEventListener('click', function() {
                const container = document.getElementById('purchase-items-container');
                const newItem = document.createElement('div');
                newItem.className = 'purchase-item grid grid-cols-1 md:grid-cols-12 gap-2 mb-2';
                newItem.innerHTML = `
                    <div class="md:col-span-4">
                        <label class="block text-gray-700 text-xs mb-1">Product *</label>
                        <select name="items[${itemIndex}][product_id]" class="item-product shadow appearance-none border rounded w-full py-1 px-2 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm" required>
                            <option value="">Select Product</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>" data-cost="<?php echo $product['cost_price']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-gray-700 text-xs mb-1">Quantity *</label>
                        <input type="number" name="items[${itemIndex}][quantity]" class="item-quantity shadow appearance-none border rounded w-full py-1 px-2 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm" min="1" value="1" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-gray-700 text-xs mb-1">Unit Cost *</label>
                        <input type="number" name="items[${itemIndex}][unit_cost]" class="item-unit-cost shadow appearance-none border rounded w-full py-1 px-2 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm" step="0.01" min="0" value="0" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-gray-700 text-xs mb-1">Total</label>
                        <input type="number" name="items[${itemIndex}][total_cost]" class="item-total-cost shadow appearance-none border rounded w-full py-1 px-2 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm" step="0.01" min="0" value="0" readonly>
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-gray-700 text-xs mb-1">Discount</label>
                        <input type="number" name="items[${itemIndex}][discount]" class="item-discount shadow appearance-none border rounded w-full py-1 px-2 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-sm" step="0.01" min="0" value="0">
                    </div>
                    <div class="md:col-span-1 flex items-end">
                        <button type="button" class="remove-item-btn bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded text-xs">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `;
                container.appendChild(newItem);
                itemIndex++;
                
                // Add event listeners to new elements
                addEventListenersToItem(newItem);
                calculateTotals();
            });
            
            // Add event listeners to existing item
            document.querySelectorAll('.purchase-item').forEach(item => {
                addEventListenersToItem(item);
            });
            
            // Function to add event listeners to item elements
            function addEventListenersToItem(item) {
                const productSelect = item.querySelector('.item-product');
                const quantityInput = item.querySelector('.item-quantity');
                const unitCostInput = item.querySelector('.item-unit-cost');
                const discountInput = item.querySelector('.item-discount');
                const totalInput = item.querySelector('.item-total-cost');
                const removeBtn = item.querySelector('.remove-item-btn');
                
                // Set unit cost when product is selected
                productSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const cost = selectedOption.getAttribute('data-cost') || 0;
                    unitCostInput.value = cost;
                    calculateItemTotal(item);
                });
                
                // Calculate item total when quantity, unit cost, or discount changes
                quantityInput.addEventListener('input', function() {
                    calculateItemTotal(item);
                });
                
                unitCostInput.addEventListener('input', function() {
                    calculateItemTotal(item);
                });
                
                discountInput.addEventListener('input', function() {
                    calculateItemTotal(item);
                });
                
                // Remove item
                removeBtn.addEventListener('click', function() {
                    if (document.querySelectorAll('.purchase-item').length > 1) {
                        item.remove();
                        calculateTotals();
                    } else {
                        alert('You must have at least one item in the purchase.');
                    }
                });
            }
            
            // Calculate item total
            function calculateItemTotal(item) {
                const quantity = parseFloat(item.querySelector('.item-quantity').value) || 0;
                const unitCost = parseFloat(item.querySelector('.item-unit-cost').value) || 0;
                const discount = parseFloat(item.querySelector('.item-discount').value) || 0;
                const total = (quantity * unitCost) - discount;
                item.querySelector('.item-total-cost').value = total.toFixed(2);
                calculateTotals();
            }
            
            // Calculate overall totals
            function calculateTotals() {
                let subtotal = 0;
                
                document.querySelectorAll('.purchase-item').forEach(item => {
                    const total = parseFloat(item.querySelector('.item-total-cost').value) || 0;
                    subtotal += total;
                });
                
                const taxAmount = parseFloat(document.getElementById('tax-amount').value) || 0;
                const discountAmount = parseFloat(document.getElementById('discount-amount').value) || 0;
                const totalAmount = subtotal + taxAmount - discountAmount;
                const amountPaid = parseFloat(document.getElementById('amount-paid').value) || 0;
                const amountDue = totalAmount - amountPaid;
                
                document.getElementById('subtotal').value = subtotal.toFixed(2);
                document.getElementById('total-amount').value = totalAmount.toFixed(2);
                document.getElementById('amount-due').value = amountDue.toFixed(2);
            }
            
            // Update totals when financial fields change
            document.getElementById('tax-amount').addEventListener('input', calculateTotals);
            document.getElementById('discount-amount').addEventListener('input', calculateTotals);
            document.getElementById('amount-paid').addEventListener('input', calculateTotals);
        });
    </script>
</body>
</html>