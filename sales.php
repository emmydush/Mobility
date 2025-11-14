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

// Handle form submissions
$message = '';
$message_type = '';

// Generate a unique invoice number
function generateInvoiceNumber($conn, $tenant_id) {
    $prefix = "INV-" . date("Ymd");
    $query = "SELECT COUNT(*) as count FROM sales WHERE invoice_number LIKE ? AND tenant_id = ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $search = $prefix . "%";
        $stmt->bindParam(1, $search, PDO::PARAM_STR);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $row = $result[0];
        $count = $row['count'] + 1;
        $stmt->closeCursor();
        return $prefix . "-" . str_pad($count, 4, "0", STR_PAD_LEFT);
    }
    return $prefix . "-0001";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $customer_id = $_POST['customer_id'] ?? 0;
                $product_id = $_POST['product_id'] ?? 0;
                $quantity = $_POST['quantity'] ?? 0;
                $payment_method = $_POST['payment_method'] ?? '';
                
                if (is_numeric($customer_id) && $customer_id > 0 && 
                    is_numeric($product_id) && $product_id > 0 && 
                    is_numeric($quantity) && $quantity > 0 && 
                    !empty($payment_method)) {
                    
                    // Verify that the customer belongs to the current tenant
                    $customer_verify_query = "SELECT id FROM customers WHERE id = ? AND tenant_id = ?";
                    $customer_verify_stmt = $conn->prepare($customer_verify_query);
                    $customer_verify_stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
                    $customer_verify_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                    $customer_verify_stmt->execute();
                    $customer_verify_result = $customer_verify_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($customer_verify_result) == 0) {
                        $message = "Customer not found or you don't have permission to use this customer.";
                        $message_type = "error";
                        $customer_verify_stmt->closeCursor();
                        break;
                    }
                    $customer_verify_stmt->closeCursor();
                    
                    // Verify that the product belongs to the current tenant
                    $product_verify_query = "SELECT id, name, price, stock_quantity FROM products WHERE id = ? AND tenant_id = ?";
                    $product_verify_stmt = $conn->prepare($product_verify_query);
                    $product_verify_stmt->bindParam(1, $product_id, PDO::PARAM_INT);
                    $product_verify_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                    $product_verify_stmt->execute();
                    $product_verify_result = $product_verify_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($product_verify_result) == 0) {
                        $message = "Product not found or you don't have permission to use this product.";
                        $message_type = "error";
                        $product_verify_stmt->closeCursor();
                        break;
                    }
                    
                    $product = $product_verify_result[0];
                    $product_verify_stmt->closeCursor();
                    
                    // Check if enough stock is available
                    if ($product['stock_quantity'] >= $quantity) {
                        $unit_price = $product['price'];
                        $total_price = $unit_price * $quantity;
                        
                        // Get tax rate from settings
                        $tax_rate = 0;
                        $tax_query = "SELECT setting_value FROM settings WHERE setting_key = 'tax_rate'";
                        $tax_stmt = $conn->prepare($tax_query);
                        if ($tax_stmt) {
                            $tax_stmt->execute();
                            $tax_result = $tax_stmt->fetchAll(PDO::FETCH_ASSOC);
                            if (count($tax_result) > 0) {
                                $tax_rate = $tax_result[0]['setting_value'] / 100;
                            }
                            $tax_stmt->closeCursor();
                        } else {
                            // Log error but continue with 0 tax rate
                            error_log("Error preparing tax query: " . $conn->error);
                        }
                        
                        $tax_amount = $total_price * $tax_rate;
                        $final_total = $total_price + $tax_amount;
                        
                        // Generate invoice number
                        $invoice_number = generateInvoiceNumber($conn, $_SESSION['tenant_id']);
                        
                        // Begin transaction
                        $conn->beginTransaction();
                        
                        try {
                            // Insert sale record
                            $sale_query = "INSERT INTO sales (tenant_id, invoice_number, customer_id, subtotal, tax_amount, total_amount, payment_method, payment_status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?)";
                            $sale_stmt = $conn->prepare($sale_query);
                            
                            if ($sale_stmt === false) {
                                throw new Exception("Error preparing sale statement.");
                            }
                            
                            $sale_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
                            $sale_stmt->bindParam(2, $invoice_number, PDO::PARAM_STR);
                            $sale_stmt->bindParam(3, $customer_id, PDO::PARAM_INT);
                            $sale_stmt->bindParam(4, $total_price, PDO::PARAM_STR);
                            $sale_stmt->bindParam(5, $tax_amount, PDO::PARAM_STR);
                            $sale_stmt->bindParam(6, $final_total, PDO::PARAM_STR);
                            $sale_stmt->bindParam(7, $payment_method, PDO::PARAM_STR);
                            $sale_stmt->bindParam(8, $_SESSION['user_id'], PDO::PARAM_INT);
                            
                            if ($sale_stmt->execute()) {
                                $sale_id = $conn->lastInsertId();
                                
                                // Insert sale item
                                $item_stmt = $conn->prepare($item_query);
                                
                                if ($item_stmt === false) {
                                    throw new Exception("Error preparing sale item statement.");
                                }
                                
                                $item_stmt->bindParam(1, $sale_id, PDO::PARAM_INT);
                                $item_stmt->bindParam(2, $product_id, PDO::PARAM_INT);
                                $item_stmt->bindParam(3, $quantity, PDO::PARAM_INT);
                                $item_stmt->bindParam(4, $unit_price, PDO::PARAM_STR);
                                $item_stmt->bindParam(5, $total_price, PDO::PARAM_STR);
                                
                                if ($item_stmt->execute()) {
                                    // Update product stock
                                    $update_stmt = $conn->prepare($update_query);
                                    
                                    if ($update_stmt === false) {
                                        throw new Exception("Error preparing update statement.");
                                    }
                                    
                                    $update_stmt->bindParam(1, $quantity, PDO::PARAM_INT);
                                    $update_stmt->bindParam(2, $product_id, PDO::PARAM_INT);
                                    $update_stmt->bindParam(3, $_SESSION['tenant_id'], PDO::PARAM_INT);
                                    
                                    if ($update_stmt->execute()) {
                                        // Update customer loyalty points
                                        $points_earned = floor($final_total / 100); // 1 point per 100 units
                                        $customer_update_stmt = $conn->prepare($customer_update_query);
                                        
                                        if ($customer_update_stmt === false) {
                                            throw new Exception("Error preparing customer update statement.");
                                        }
                                        
                                        $customer_update_stmt->bindParam(1, $points_earned, PDO::PARAM_INT);
                                        $customer_update_stmt->bindParam(2, $final_total, PDO::PARAM_STR);
                                        $customer_update_stmt->bindParam(3, $customer_id, PDO::PARAM_INT);
                                        $customer_update_stmt->bindParam(4, $_SESSION['tenant_id'], PDO::PARAM_INT);
                                        
                                        if ($customer_update_stmt->execute()) {
                                            $conn->commit();
                                            $message = "Sale completed successfully! Invoice: " . $invoice_number;
                                            $message_type = "success";
                                        } else {
                                            throw new Exception("Error updating customer loyalty points.");
                                        }
                                        $customer_update_stmt->closeCursor();
                                    } else {
                                        throw new Exception("Error updating product stock.");
                                    }
                                    $update_stmt->closeCursor();
                                } else {
                                    throw new Exception("Error adding sale item.");
                                }
                                $item_stmt->closeCursor();
                            } else {
                                throw new Exception("Error creating sale record.");
                            }
                            $sale_stmt->closeCursor();
                        } catch (Exception $e) {
                            $conn->rollback();
                            $message = "Error processing sale: " . $e->getMessage();
                            $message_type = "error";
                        }
                    } else {
                        $message = "Insufficient stock. Available: " . $product['stock_quantity'];
                        $message_type = "error";
                    }
                } else {
                    $message = "All fields are required and must be valid.";
                    $message_type = "error";
                }
                break;
                
            case 'delete':
                $sale_id = $_POST['sale_id'] ?? 0;
                
                if (is_numeric($sale_id) && $sale_id > 0) {
                    // First verify that the sale belongs to the current tenant
                    $verify_query = "SELECT id, customer_id FROM sales WHERE id = ? AND tenant_id = ?";
                    $verify_stmt = $conn->prepare($verify_query);
                    $verify_stmt->bindParam(1, $sale_id, PDO::PARAM_INT);
                    $verify_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                    $verify_stmt->execute();
                    $verify_result = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($verify_result) > 0) {
                        $sale_data = $verify_result[0];
                        $customer_id = $sale_data['customer_id'];
                        $verify_stmt->closeCursor();
                        
                        // Begin transaction
                        $conn->beginTransaction();
                        
                        try {
                            // Get sale details before deleting
                            $sale_query = "SELECT * FROM sales WHERE id = ? AND tenant_id = ?";
                            $sale_stmt = $conn->prepare($sale_query);
                            
                            if ($sale_stmt === false) {
                                throw new Exception("Error preparing sale query.");
                            }
                            
                            $sale_stmt->bindParam(1, $sale_id, PDO::PARAM_INT);
                            $sale_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                            $sale_stmt->execute();
                            $sale_result = $sale_stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (count($sale_result) > 0) {
                                $sale = $sale_result[0];
                                
                                // Get sale items to restore stock
                                $items_query = "SELECT product_id, quantity FROM sale_items WHERE sale_id = ?";
                                $items_stmt = $conn->prepare($items_query);
                                
                                if ($items_stmt === false) {
                                    throw new Exception("Error preparing items query.");
                                }
                                
                                $items_stmt->bindParam(1, $sale_id, PDO::PARAM_INT);
                                $items_stmt->execute();
                                $items_result = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Restore product stock
                                foreach ($items_result as $item) {
                                    $update_query = "UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ? AND tenant_id = ?";
                                    $update_stmt = $conn->prepare($update_query);
                                    
                                    if ($update_stmt === false) {
                                        throw new Exception("Error preparing update statement.");
                                    }
                                    
                                    $update_stmt->bindParam(1, $item['quantity'], PDO::PARAM_INT);
                                    $update_stmt->bindParam(2, $item['product_id'], PDO::PARAM_INT);
                                    $update_stmt->bindParam(3, $_SESSION['tenant_id'], PDO::PARAM_INT);
                                    
                                    if (!$update_stmt->execute()) {
                                        throw new Exception("Error restoring product stock.");
                                    }
                                    $update_stmt->closeCursor();
                                }
                                $items_stmt->closeCursor();
                                
                                // If customer exists, remove loyalty points
                                if ($sale['customer_id']) {
                                    $points_removed = floor($sale['total_amount'] / 100);
                                    $customer_update_query = "UPDATE customers SET loyalty_points = loyalty_points - ?, total_purchases = total_purchases - ? WHERE id = ? AND tenant_id = ?";
                                    $customer_update_stmt = $conn->prepare($customer_update_query);
                                    
                                    if ($customer_update_stmt === false) {
                                        throw new Exception("Error preparing customer update statement.");
                                    }
                                    
                                    $customer_update_stmt->bindParam(1, $points_removed, PDO::PARAM_INT);
                                    $customer_update_stmt->bindParam(2, $sale['total_amount'], PDO::PARAM_STR);
                                    $customer_update_stmt->bindParam(3, $sale['customer_id'], PDO::PARAM_INT);
                                    $customer_update_stmt->bindParam(4, $_SESSION['tenant_id'], PDO::PARAM_INT);
                                    
                                    if (!$customer_update_stmt->execute()) {
                                        throw new Exception("Error updating customer loyalty points.");
                                    }
                                    $customer_update_stmt->closeCursor();
                                }
                                
                                // Delete sale items
                                $delete_items_query = "DELETE FROM sale_items WHERE sale_id = ?";
                                $delete_items_stmt = $conn->prepare($delete_items_query);
                                
                                if ($delete_items_stmt === false) {
                                    throw new Exception("Error preparing delete items statement.");
                                }
                                
                                $delete_items_stmt->bindParam(1, $sale_id, PDO::PARAM_INT);
                                
                                if (!$delete_items_stmt->execute()) {
                                    throw new Exception("Error deleting sale items.");
                                }
                                $delete_items_stmt->closeCursor();
                                
                                // Delete sale record with tenant filtering
                                $delete_sale_query = "DELETE FROM sales WHERE id = ? AND tenant_id = ?";
                                $delete_sale_stmt = $conn->prepare($delete_sale_query);
                                
                                if ($delete_sale_stmt === false) {
                                    throw new Exception("Error preparing delete sale statement.");
                                }
                                
                                $delete_sale_stmt->bindParam(1, $sale_id, PDO::PARAM_INT);
                                $delete_sale_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                                
                                if ($delete_sale_stmt->execute()) {
                                    $conn->commit();
                                    $message = "Sale deleted successfully!";
                                    $message_type = "success";
                                } else {
                                    throw new Exception("Error deleting sale record.");
                                }
                                $delete_sale_stmt->closeCursor();
                            } else {
                                throw new Exception("Sale not found.");
                            }
                            $sale_stmt->closeCursor();
                        } catch (Exception $e) {
                            $conn->rollback();
                            $message = "Error deleting sale: " . $e->getMessage();
                            $message_type = "error";
                        }
                    } else {
                        $message = "Sale not found or you don't have permission to delete this sale.";
                        $message_type = "error";
                    }
                } else {
                    $message = "Invalid sale ID.";
                    $message_type = "error";
                }
                break;
                
            case 'refund':
                $sale_id = $_POST['sale_id'] ?? 0;
                
                if (is_numeric($sale_id) && $sale_id > 0) {
                    // Begin transaction
                    $conn->beginTransaction();
                    
                    try {
                        // Get sale details with tenant filtering
                        $sale_query = "SELECT * FROM sales WHERE id = ? AND tenant_id = ?";
                        $sale_stmt = $conn->prepare($sale_query);
                        
                        if ($sale_stmt === false) {
                            throw new Exception("Error preparing sale query.");
                        }
                        
                        $sale_stmt->bindParam(1, $sale_id, PDO::PARAM_INT);
                        $sale_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                        $sale_stmt->execute();
                        $sale_result = $sale_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($sale_result) > 0) {
                            $sale = $sale_result[0];
                            
                            // Check if sale is already refunded
                            if ($sale['payment_status'] === 'refunded') {
                                throw new Exception("Sale is already refunded.");
                            }
                            
                            // Get sale items to restore stock
                            $items_query = "SELECT product_id, quantity FROM sale_items WHERE sale_id = ?";
                            $items_stmt = $conn->prepare($items_query);
                            
                            if ($items_stmt === false) {
                                throw new Exception("Error preparing items query.");
                            }
                            
                            $items_stmt->bindParam(1, $sale_id, PDO::PARAM_INT);
                            $items_stmt->execute();
                            $items_result = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Restore product stock with tenant filtering
                            foreach ($items_result as $item) {
                                $update_query = "UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ? AND tenant_id = ?";
                                $update_stmt = $conn->prepare($update_query);
                                
                                if ($update_stmt === false) {
                                    throw new Exception("Error preparing update statement.");
                                }
                                
                                $update_stmt->bindParam(1, $item['quantity'], PDO::PARAM_INT);
                                $update_stmt->bindParam(2, $item['product_id'], PDO::PARAM_INT);
                                $update_stmt->bindParam(3, $_SESSION['tenant_id'], PDO::PARAM_INT);
                                
                                if (!$update_stmt->execute()) {
                                    throw new Exception("Error restoring product stock.");
                                }
                                $update_stmt->closeCursor();
                            }
                            $items_stmt->closeCursor();
                            
                            // If customer exists, remove loyalty points with tenant filtering
                            if ($sale['customer_id']) {
                                $points_removed = floor($sale['total_amount'] / 100);
                                $customer_update_query = "UPDATE customers SET loyalty_points = loyalty_points - ?, total_purchases = total_purchases - ? WHERE id = ? AND tenant_id = ?";
                                $customer_update_stmt = $conn->prepare($customer_update_query);
                                
                                if ($customer_update_stmt === false) {
                                    throw new Exception("Error preparing customer update statement.");
                                }
                                
                                $customer_update_stmt->bindParam(1, $points_removed, PDO::PARAM_INT);
                                $customer_update_stmt->bindParam(2, $sale['total_amount'], PDO::PARAM_STR);
                                $customer_update_stmt->bindParam(3, $sale['customer_id'], PDO::PARAM_INT);
                                $customer_update_stmt->bindParam(4, $_SESSION['tenant_id'], PDO::PARAM_INT);
                                
                                if (!$customer_update_stmt->execute()) {
                                    throw new Exception("Error updating customer loyalty points.");
                                }
                                $customer_update_stmt->closeCursor();
                            }
                            
                            // Update sale status to refunded with tenant filtering
                            $refund_query = "UPDATE sales SET payment_status = 'refunded', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?";
                            $refund_stmt = $conn->prepare($refund_query);
                            
                            if ($refund_stmt === false) {
                                throw new Exception("Error preparing refund statement.");
                            }
                            
                            $refund_stmt->bindParam(1, $sale_id, PDO::PARAM_INT);
                            $refund_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                            
                            if ($refund_stmt->execute()) {
                                $conn->commit();
                                $message = "Sale refunded successfully!";
                                $message_type = "success";
                            } else {
                                throw new Exception("Error refunding sale.");
                            }
                            $refund_stmt->closeCursor();
                        } else {
                            throw new Exception("Sale not found.");
                        }
                        $sale_stmt->closeCursor();
                    } catch (Exception $e) {
                        $conn->rollback();
                        $message = "Error refunding sale: " . $e->getMessage();
                        $message_type = "error";
                    }
                } else {
                    $message = "Invalid sale ID.";
                    $message_type = "error";
                }
                break;
        }
    }
}

// Fetch all sales with customer and product details for current tenant only
$sales = array();
$query = "SELECT s.*, c.name as customer_name, p.name as product_name 
          FROM sales s 
          LEFT JOIN customers c ON s.customer_id = c.id AND c.tenant_id = s.tenant_id
          LEFT JOIN sale_items si ON s.id = si.sale_id 
          LEFT JOIN products p ON si.product_id = p.id AND p.tenant_id = s.tenant_id
          WHERE s.tenant_id = ?
          ORDER BY s.created_at DESC";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($result as $row) {
        $sales[] = $row;
    }
    $stmt->closeCursor();
} else {
    $message = "Error preparing sales query.";
    $message_type = "error";
}

// Fetch all customers for the dropdown (for current tenant only)
$customers = array();
$customer_query = "SELECT id, name FROM customers WHERE tenant_id = ? ORDER BY name";
$customer_stmt = $conn->prepare($customer_query);
if ($customer_stmt) {
    $customer_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $customer_stmt->execute();
    $customer_result = $customer_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($customer_result as $row) {
        $customers[] = $row;
    }
    $customer_stmt->closeCursor();
} else {
    $message = "Error preparing customers query.";
    $message_type = "error";
}

// Fetch all products for the dropdown (for current tenant only)
$products = array();
$product_query = "SELECT id, name, price, stock_quantity FROM products WHERE stock_quantity > 0 AND tenant_id = ? ORDER BY name";
$product_stmt = $conn->prepare($product_query);
if ($product_stmt) {
    $product_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $product_stmt->execute();
    $product_result = $product_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($product_result as $row) {
        $products[] = $row;
    }
    $product_stmt->closeCursor();
} else {
    $message = "Error preparing products query.";
    $message_type = "error";
}
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales - Complete Inventory Management System</title>
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
                <a href="stock-movements.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-exchange-alt mr-3"></i>
                    <span>Stock Movements</span>
                </a>
                <a href="customers.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-green-100 hover:bg-green-800 hover:text-white transition duration-200">
                    <i class="fas fa-users mr-3"></i>
                    <span>Customers</span>
                </a>
                <a href="sales.php?lang=<?php echo $current_lang; ?>" class="flex items-center px-4 py-3 text-white bg-gradient-to-r from-green-500 to-green-600 border-l-4 border-green-300">
                    <i class="fas fa-shopping-cart mr-3"></i>
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
                <h2 class="text-xl font-semibold text-white">Sales Management</h2>
                <div class="flex items-center space-x-4">
                    <!-- Language Selector -->
                    <div class="relative">
                        <select onchange="window.location.href=this.value" class="bg-green-600 text-white rounded px-2 py-1 text-sm">
                            <option value="?lang=en" <?php echo ($current_lang == 'en') ? 'selected' : ''; ?>>English</option>
                            <option value="?lang=fr" <?php echo ($current_lang == 'fr') ? 'selected' : ''; ?>>Français</option>
                            <option value="?lang=rw" <?php echo ($current_lang == 'rw') ? 'selected' : ''; ?>>Kinyarwanda</option>
                        </select>
                    </div>
                    <button id="add-sale-btn" class="px-4 py-2 text-sm bg-white text-green-600 rounded hover:bg-green-50 transition duration-200">
                        <i class="fas fa-plus mr-1"></i> New Sale
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

            <!-- Sales Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <!-- Add Sale Form (Hidden by default) -->
                <div id="add-sale-form" class="bg-white rounded-lg shadow mb-6 p-6 hidden">
                    <h3 class="text-lg font-medium text-gray-800 mb-4">New Sale</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="customer_id" class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                                <select id="customer_id" name="customer_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Customer</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?php echo $customer['id']; ?>"><?php echo htmlspecialchars($customer['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="product_id" class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                                <select id="product_id" name="product_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Product</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['price']; ?>" data-stock="<?php echo $product['stock_quantity']; ?>">
                                            <?php echo htmlspecialchars($product['name']); ?> (Price: <?php echo number_format($product['price'], 2); ?> FRW, Stock: <?php echo $product['stock_quantity']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                                <input type="number" id="quantity" name="quantity" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                                <select id="payment_method" name="payment_method" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Payment Method</option>
                                    <option value="cash">Cash</option>
                                    <option value="card">Card</option>
                                    <option value="mobile_money">Mobile Money</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" id="cancel-sale-btn" class="px-4 py-2 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition duration-200">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 transition duration-200">
                                Process Sale
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Sales List -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-800">Sales History</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($sales)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                            No sales records found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($sales as $sale): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($sale['invoice_number']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($sale['customer_name'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo htmlspecialchars($sale['product_name'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo number_format($sale['total_amount'], 2); ?> FRW
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?php 
                                                    if ($sale['payment_status'] === 'completed') {
                                                        echo 'bg-green-100 text-green-800';
                                                    } elseif ($sale['payment_status'] === 'refunded') {
                                                        echo 'bg-red-100 text-red-800';
                                                    } else {
                                                        echo 'bg-yellow-100 text-yellow-800';
                                                    }
                                                    ?>">
                                                    <?php 
                                                    if ($sale['payment_status'] === 'completed') {
                                                        echo ucfirst($sale['payment_method']);
                                                    } elseif ($sale['payment_status'] === 'refunded') {
                                                        echo 'Refunded';
                                                    } else {
                                                        echo ucfirst($sale['payment_status']);
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo date('M j, Y', strtotime($sale['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <?php if ($sale['payment_status'] !== 'refunded'): ?>
                                                    <button class="refund-sale-btn text-yellow-600 hover:text-yellow-900 mr-3" 
                                                            data-id="<?php echo $sale['id']; ?>"
                                                            data-invoice="<?php echo htmlspecialchars($sale['invoice_number']); ?>">
                                                        Refund
                                                    </button>
                                                <?php endif; ?>
                                                <button class="delete-sale-btn text-red-600 hover:text-red-900" 
                                                        data-id="<?php echo $sale['id']; ?>"
                                                        data-invoice="<?php echo htmlspecialchars($sale['invoice_number']); ?>">
                                                    Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Toggle add sale form
        document.getElementById('add-sale-btn').addEventListener('click', function() {
            document.getElementById('add-sale-form').classList.toggle('hidden');
        });
        
        document.getElementById('cancel-sale-btn').addEventListener('click', function() {
            document.getElementById('add-sale-form').classList.add('hidden');
        });
        
        // Update quantity max based on selected product stock
        document.getElementById('product_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const stock = selectedOption.getAttribute('data-stock');
            const quantityInput = document.getElementById('quantity');
            
            if (stock) {
                quantityInput.max = stock;
            }
        });
        
        // Refund sale functionality
        document.querySelectorAll('.refund-sale-btn').forEach(button => {
            button.addEventListener('click', function() {
                const saleId = this.getAttribute('data-id');
                const invoiceNumber = this.getAttribute('data-invoice');
                
                if (confirm(`Are you sure you want to refund sale ${invoiceNumber}? This will restore product stock and customer loyalty points.`)) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="refund">
                        <input type="hidden" name="sale_id" value="${saleId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
        
        // Delete sale functionality
        document.querySelectorAll('.delete-sale-btn').forEach(button => {
            button.addEventListener('click', function() {
                const saleId = this.getAttribute('data-id');
                const invoiceNumber = this.getAttribute('data-invoice');
                
                if (confirm(`Are you sure you want to delete sale ${invoiceNumber}? This will permanently remove the sale and restore product stock.`)) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="sale_id" value="${saleId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
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