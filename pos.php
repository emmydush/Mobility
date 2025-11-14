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

// Function to generate invoice number
function generateInvoiceNumber($conn, $tenant_id) {
    $prefix = "INV-" . date("Ymd");
    $query = "SELECT COUNT(*) as count FROM sales WHERE invoice_number LIKE ? AND tenant_id = ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $search = $prefix . "%";
        $stmt->bindParam(1, $search, PDO::PARAM_STR);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = $result['count'] + 1;
        return $prefix . "-" . str_pad($count, 4, "0", STR_PAD_LEFT);
    }
    return $prefix . "-0001";
}

// Generate invoice number for display
$invoice_number = generateInvoiceNumber($conn, $_SESSION['tenant_id']);

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'cashier';

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'process_sale') {
        $customer_id = $_POST['customer_id'] ?? null;
        $cart_items = json_decode($_POST['cart_items'], true);
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $amount_paid = floatval($_POST['amount_paid'] ?? 0);
        
        // Enable error reporting for debugging
        ini_set('display_errors', 1);
        error_reporting(E_ALL);
        
        if (!empty($cart_items) && is_array($cart_items)) {
            try {
                // Begin transaction
                $conn->beginTransaction();
                
                // Validate customer_id if provided (with tenant filtering)
                if ($customer_id !== null && $customer_id !== '') {
                    $customer_check_query = "SELECT id FROM customers WHERE id = ? AND tenant_id = ?";
                    $customer_check_stmt = $conn->prepare($customer_check_query);
                    if ($customer_check_stmt) {
                        $customer_check_stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
                        $customer_check_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                        $customer_check_stmt->execute();
                        $customer_result = $customer_check_stmt->fetchAll(PDO::FETCH_ASSOC);
                        if (count($customer_result) === 0) {
                            // Customer ID doesn't exist or doesn't belong to this tenant, set to null
                            $customer_id = null;
                        }
                    }
                } else {
                    // Ensure customer_id is null if empty string or not provided
                    $customer_id = null;
                }
                
                // Calculate totals
                $subtotal = 0;
                foreach ($cart_items as $item) {
                    $subtotal += $item['price'] * $item['quantity'];
                }
                
                // Get tax rate from settings (0% for now)
                $tax_rate = 0;
                $tax_amount = $subtotal * $tax_rate;
                $total_amount = $subtotal + $tax_amount;
                $amount_due = $total_amount - $amount_paid;
                
                // Generate invoice number
                $invoice_number = 'INV-' . date('Ymd') . '-' . rand(1000, 9999);
                
                // Insert sale record with tenant_id
                if ($customer_id !== null) {
                    $sale_query = "INSERT INTO sales (tenant_id, invoice_number, customer_id, subtotal, tax_amount, discount_amount, total_amount, amount_paid, amount_due, payment_method, payment_status, created_by) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, 'completed', ?)";
                    $sale_stmt = $conn->prepare($sale_query);
                    if ($sale_stmt === false) {
                        throw new Exception("Error preparing sale statement");
                    }
                    $sale_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
                    $sale_stmt->bindParam(2, $invoice_number, PDO::PARAM_STR);
                    $sale_stmt->bindParam(3, $customer_id, PDO::PARAM_INT);
                    $sale_stmt->bindParam(4, $subtotal, PDO::PARAM_STR);
                    $sale_stmt->bindParam(5, $tax_amount, PDO::PARAM_STR);
                    $sale_stmt->bindParam(6, $total_amount, PDO::PARAM_STR);
                    $sale_stmt->bindParam(7, $amount_paid, PDO::PARAM_STR);
                    $sale_stmt->bindParam(8, $amount_due, PDO::PARAM_STR);
                    $sale_stmt->bindParam(9, $payment_method, PDO::PARAM_STR);
                    $sale_stmt->bindParam(10, $_SESSION['user_id'], PDO::PARAM_INT);
                } else {
                    $sale_query = "INSERT INTO sales (tenant_id, invoice_number, subtotal, tax_amount, discount_amount, total_amount, amount_paid, amount_due, payment_method, payment_status, created_by) VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, 'completed', ?)";
                    $sale_stmt = $conn->prepare($sale_query);
                    if ($sale_stmt === false) {
                        throw new Exception("Error preparing sale statement");
                    }
                    $sale_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
                    $sale_stmt->bindParam(2, $invoice_number, PDO::PARAM_STR);
                    $sale_stmt->bindParam(3, $subtotal, PDO::PARAM_STR);
                    $sale_stmt->bindParam(4, $tax_amount, PDO::PARAM_STR);
                    $sale_stmt->bindParam(5, $total_amount, PDO::PARAM_STR);
                    $sale_stmt->bindParam(6, $amount_paid, PDO::PARAM_STR);
                    $sale_stmt->bindParam(7, $amount_due, PDO::PARAM_STR);
                    $sale_stmt->bindParam(8, $payment_method, PDO::PARAM_STR);
                    $sale_stmt->bindParam(9, $_SESSION['user_id'], PDO::PARAM_INT);
                }
                
                if ($sale_stmt->execute()) {
                    $sale_id = $conn->lastInsertId();
                    
                    // Process each cart item
                    foreach ($cart_items as $item) {
                        $product_id = $item['id'];
                        $quantity = $item['quantity'];
                        $unit_price = $item['price'];
                        $total_price = $unit_price * $quantity;
                        
                        // Insert sale item
                        $item_query = "INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)";
                        $item_stmt = $conn->prepare($item_query);
                        
                        if ($item_stmt === false) {
                            throw new Exception("Error preparing sale item statement");
                        }
                        
                        $item_stmt->bindParam(1, $sale_id, PDO::PARAM_INT);
                        $item_stmt->bindParam(2, $product_id, PDO::PARAM_INT);
                        $item_stmt->bindParam(3, $quantity, PDO::PARAM_INT);
                        $item_stmt->bindParam(4, $unit_price, PDO::PARAM_STR);
                        $item_stmt->bindParam(5, $total_price, PDO::PARAM_STR);
                        
                        if (!$item_stmt->execute()) {
                            throw new Exception("Error adding sale item");
                        }
                        
                        // Update product stock with tenant filtering
                        $update_query = "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND tenant_id = ?";
                        $update_stmt = $conn->prepare($update_query);
                        
                        if ($update_stmt === false) {
                            throw new Exception("Error preparing update statement");
                        }
                        
                        $update_stmt->bindParam(1, $quantity, PDO::PARAM_INT);
                        $update_stmt->bindParam(2, $product_id, PDO::PARAM_INT);
                        $update_stmt->bindParam(3, $_SESSION['tenant_id'], PDO::PARAM_INT);
                        
                        if (!$update_stmt->execute()) {
                            throw new Exception("Error updating product stock");
                        }
                    }
                    
                    // If customer exists, update loyalty points with tenant filtering
                    if ($customer_id) {
                        $points_earned = floor($total_amount / 100); // 1 point per 100 units
                        $customer_update_query = "UPDATE customers SET loyalty_points = loyalty_points + ?, total_purchases = total_purchases + ? WHERE id = ? AND tenant_id = ?";
                        $customer_update_stmt = $conn->prepare($customer_update_query);
                        
                        if ($customer_update_stmt) {
                            $customer_update_stmt->bindParam(1, $points_earned, PDO::PARAM_INT);
                            $customer_update_stmt->bindParam(2, $total_amount, PDO::PARAM_STR);
                            $customer_update_stmt->bindParam(3, $customer_id, PDO::PARAM_INT);
                            $customer_update_stmt->bindParam(4, $_SESSION['tenant_id'], PDO::PARAM_INT);
                            $customer_update_stmt->execute();
                        }
                    }
                    
                    $conn->commit();
                    $message = "Sale completed successfully! Invoice: " . $invoice_number;
                    $message_type = "success";
                } else {
                    throw new Exception("Error creating sale record");
                }
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $message = "Error processing sale: " . $e->getMessage();
                $message_type = "error";
                error_log("POS Error: " . $e->getMessage());
            }
        } else {
            $message = "Cart is empty";
            $message_type = "error";
        }
    }
}

// Fetch products for POS with tenant filtering
$products = [];
$search = isset($_GET['search']) ? $_GET['search'] : '';
if (!empty($search)) {
    $query = "SELECT id, name, price, stock_quantity FROM products WHERE (name LIKE ? OR barcode = ?) AND tenant_id = ? AND status = 'active' ORDER BY name";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $search_param = "%$search%";
        $stmt->bindParam(1, $search_param, PDO::PARAM_STR);
        $stmt->bindParam(2, $search, PDO::PARAM_STR);
        $stmt->bindParam(3, $_SESSION['tenant_id'], PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $row) {
            $products[] = $row;
        }
    }
}

// Fetch customers for dropdown with tenant filtering
$customers = [];
$customer_query = "SELECT id, name FROM customers WHERE tenant_id = ? AND status = 'active' ORDER BY name";
$customer_stmt = $conn->prepare($customer_query);
if ($customer_stmt) {
    $customer_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $customer_stmt->execute();
    $customer_result = $customer_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($customer_result as $row) {
        $customers[] = $row;
    }
}

$conn = null;
?>