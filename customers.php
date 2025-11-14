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
                $name = $_POST['name'] ?? '';
                $email = $_POST['email'] ?? '';
                $phone = $_POST['phone'] ?? '';
                $address = $_POST['address'] ?? '';
                
                if (!empty($name)) {
                    $query = "INSERT INTO customers (tenant_id, name, email, phone, address) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    if ($stmt) {
                        $stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
                        $stmt->bindParam(2, $name, PDO::PARAM_STR);
                        $stmt->bindParam(3, $email, PDO::PARAM_STR);
                        $stmt->bindParam(4, $phone, PDO::PARAM_STR);
                        $stmt->bindParam(5, $address, PDO::PARAM_STR);
                        if ($stmt->execute()) {
                            $message = "Customer added successfully!";
                            $message_type = "success";
                        } else {
                            $message = "Error adding customer";
                            $message_type = "error";
                        }
                    } else {
                        $message = "Database error";
                        $message_type = "error";
                    }
                } else {
                    $message = "Customer name is required.";
                    $message_type = "error";
                }
                break;
                
            case 'delete':
                $id = $_POST['id'] ?? 0;
                if (is_numeric($id) && $id > 0) {
                    // First verify that the customer belongs to the current tenant
                    $verify_query = "SELECT id FROM customers WHERE id = ? AND tenant_id = ?";
                    $verify_stmt = $conn->prepare($verify_query);
                    $verify_stmt->bindParam(1, $id, PDO::PARAM_INT);
                    $verify_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                    $verify_stmt->execute();
                    $verify_result = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($verify_result) > 0) {
                        // Check if customer has any sales records
                        $check_sales_query = "SELECT COUNT(*) as sales_count FROM sales WHERE customer_id = ? AND tenant_id = ?";
                        $check_sales_stmt = $conn->prepare($check_sales_query);
                        if ($check_sales_stmt) {
                            $check_sales_stmt->bindParam(1, $id, PDO::PARAM_INT);
                            $check_sales_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                            $check_sales_stmt->execute();
                            $sales_result = $check_sales_stmt->fetch(PDO::FETCH_ASSOC);
                            $sales_count = $sales_result['sales_count'];
                            
                            if ($sales_count > 0) {
                                // Instead of deleting, mark as inactive
                                $update_query = "UPDATE customers SET status = 'inactive' WHERE id = ? AND tenant_id = ?";
                                $update_stmt = $conn->prepare($update_query);
                                if ($update_stmt) {
                                    $update_stmt->bindParam(1, $id, PDO::PARAM_INT);
                                    $update_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                                    if ($update_stmt->execute()) {
                                        $message = "Customer marked as inactive. Customer has $sales_count associated sales record(s) and cannot be permanently deleted.";
                                        $message_type = "success";
                                    } else {
                                        $message = "Error updating customer status";
                                        $message_type = "error";
                                    }
                                } else {
                                    $message = "Database error";
                                    $message_type = "error";
                                }
                            } else {
                                // No sales records, safe to delete
                                $delete_query = "DELETE FROM customers WHERE id = ? AND tenant_id = ?";
                                $delete_stmt = $conn->prepare($delete_query);
                                if ($delete_stmt) {
                                    $delete_stmt->bindParam(1, $id, PDO::PARAM_INT);
                                    $delete_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                                    if ($delete_stmt->execute()) {
                                        $message = "Customer deleted successfully!";
                                        $message_type = "success";
                                    } else {
                                        $message = "Error deleting customer";
                                        $message_type = "error";
                                    }
                                } else {
                                    $message = "Database error";
                                    $message_type = "error";
                                }
                            }
                        } else {
                            $message = "Database error";
                            $message_type = "error";
                        }
                    } else {
                        $message = "Customer not found or you don't have permission to delete it.";
                        $message_type = "error";
                    }
                } else {
                    $message = "Invalid customer ID.";
                    $message_type = "error";
                }
                break;
        }
    }
}

// Fetch all customers (active and inactive) for the current tenant
$customers = array();
$query = "SELECT * FROM customers WHERE tenant_id = ? ORDER BY name";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($result as $row) {
        $customers[] = $row;
    }
}

// Fetch debtors (customers with negative balance) for the current tenant
$debtors = array();
$debtors_query = "SELECT * FROM customers WHERE balance < 0 AND tenant_id = ? ORDER BY balance ASC";
$debtors_stmt = $conn->prepare($debtors_query);
if ($debtors_stmt) {
    $debtors_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $debtors_stmt->execute();
    $debtors_result = $debtors_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($debtors_result as $row) {
        $debtors[] = $row;
    }
}

// Fetch creditors (customers with positive balance) for the current tenant
$creditors = array();
$creditors_query = "SELECT * FROM customers WHERE balance > 0 AND tenant_id = ? ORDER BY balance DESC";
$creditors_stmt = $conn->prepare($creditors_query);
if ($creditors_stmt) {
    $creditors_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $creditors_stmt->execute();
    $creditors_result = $creditors_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($creditors_result as $row) {
        $creditors[] = $row;
    }
}

// Calculate statistics
$total_customers = count($customers);
$total_debtors = count($debtors);
?>