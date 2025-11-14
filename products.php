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
                $sku = $_POST['sku'] ?? '';
                $barcode = $_POST['barcode'] ?? '';
                $category_id = $_POST['category_id'] ?? 0;
                $price = $_POST['price'] ?? 0;
                $cost_price = $_POST['cost_price'] ?? 0;
                $stock = $_POST['stock'] ?? 0;
                $expiry_date = $_POST['expiry_date'] ?? null;
                
                // Handle image upload
                $image_url = null;
                if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                    $upload_dir = 'product_images/';
                    // Create directory if it doesn't exist
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $file_name = uniqid() . '_' . basename($_FILES['image']['name']);
                    $target_file = $upload_dir . $file_name;
                    
                    // Check if image file is actual image
                    $check = getimagesize($_FILES['image']['tmp_name']);
                    if ($check !== false) {
                        // Move uploaded file
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                            $image_url = $target_file;
                        }
                    }
                }
                
                if (!empty($name) && !empty($sku) && !empty($category_id) && is_numeric($price) && is_numeric($cost_price) && is_numeric($stock)) {
                    if ($image_url) {
                        $query = "INSERT INTO products (tenant_id, name, sku, barcode, category_id, price, cost_price, stock_quantity, expiry_date, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($query);
                        if ($stmt) {
                            $stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
                            $stmt->bindParam(2, $name, PDO::PARAM_STR);
                            $stmt->bindParam(3, $sku, PDO::PARAM_STR);
                            $stmt->bindParam(4, $barcode, PDO::PARAM_STR);
                            $stmt->bindParam(5, $category_id, PDO::PARAM_INT);
                            $stmt->bindParam(6, $price, PDO::PARAM_STR);
                            $stmt->bindParam(7, $cost_price, PDO::PARAM_STR);
                            $stmt->bindParam(8, $stock, PDO::PARAM_INT);
                            $stmt->bindParam(9, $expiry_date, PDO::PARAM_STR);
                            $stmt->bindParam(10, $image_url, PDO::PARAM_STR);
                        }
                    } else {
                        $query = "INSERT INTO products (tenant_id, name, sku, barcode, category_id, price, cost_price, stock_quantity, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($query);
                        if ($stmt) {
                            $stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
                            $stmt->bindParam(2, $name, PDO::PARAM_STR);
                            $stmt->bindParam(3, $sku, PDO::PARAM_STR);
                            $stmt->bindParam(4, $barcode, PDO::PARAM_STR);
                            $stmt->bindParam(5, $category_id, PDO::PARAM_INT);
                            $stmt->bindParam(6, $price, PDO::PARAM_STR);
                            $stmt->bindParam(7, $cost_price, PDO::PARAM_STR);
                            $stmt->bindParam(8, $stock, PDO::PARAM_INT);
                            $stmt->bindParam(9, $expiry_date, PDO::PARAM_STR);
                        }
                    }
                    
                    if ($stmt && $stmt->execute()) {
                        $message = "Product added successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error adding product";
                        $message_type = "error";
                    }
                } else {
                    $message = "All fields are required and price/cost/stock must be numeric.";
                    $message_type = "error";
                }
                break;
                
            case 'edit':
                $id = $_POST['id'] ?? 0;
                $name = $_POST['name'] ?? '';
                $sku = $_POST['sku'] ?? '';
                $barcode = $_POST['barcode'] ?? '';
                $category_id = $_POST['category_id'] ?? 0;
                $price = $_POST['price'] ?? 0;
                $cost_price = $_POST['cost_price'] ?? 0;
                $stock = $_POST['stock'] ?? 0;
                $expiry_date = $_POST['expiry_date'] ?? null;
                
                // Handle image upload
                $image_url = null;
                if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                    $upload_dir = 'product_images/';
                    // Create directory if it doesn't exist
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $file_name = uniqid() . '_' . basename($_FILES['image']['name']);
                    $target_file = $upload_dir . $file_name;
                    
                    // Check if image file is actual image
                    $check = getimagesize($_FILES['image']['tmp_name']);
                    if ($check !== false) {
                        // Move uploaded file
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                            $image_url = $target_file;
                        }
                    }
                }
                
                if (!empty($id) && !empty($name) && !empty($sku) && !empty($category_id) && is_numeric($price) && is_numeric($cost_price) && is_numeric($stock)) {
                    // First verify that the product belongs to the current tenant
                    $verify_query = "SELECT id FROM products WHERE id = ? AND tenant_id = ?";
                    $verify_stmt = $conn->prepare($verify_query);
                    $verify_stmt->bindParam(1, $id, PDO::PARAM_INT);
                    $verify_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                    $verify_stmt->execute();
                    $verify_result = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($verify_result) > 0) {
                        if ($image_url) {
                            // Update with new image
                            $query = "UPDATE products SET name = ?, sku = ?, barcode = ?, category_id = ?, price = ?, cost_price = ?, stock_quantity = ?, expiry_date = ?, image_url = ? WHERE id = ? AND tenant_id = ?";
                            $stmt = $conn->prepare($query);
                            if ($stmt) {
                                $stmt->bindParam(1, $name, PDO::PARAM_STR);
                                $stmt->bindParam(2, $sku, PDO::PARAM_STR);
                                $stmt->bindParam(3, $barcode, PDO::PARAM_STR);
                                $stmt->bindParam(4, $category_id, PDO::PARAM_INT);
                                $stmt->bindParam(5, $price, PDO::PARAM_STR);
                                $stmt->bindParam(6, $cost_price, PDO::PARAM_STR);
                                $stmt->bindParam(7, $stock, PDO::PARAM_INT);
                                $stmt->bindParam(8, $expiry_date, PDO::PARAM_STR);
                                $stmt->bindParam(9, $image_url, PDO::PARAM_STR);
                                $stmt->bindParam(10, $id, PDO::PARAM_INT);
                                $stmt->bindParam(11, $_SESSION['tenant_id'], PDO::PARAM_INT);
                            }
                        } else {
                            // Update without changing image
                            $query = "UPDATE products SET name = ?, sku = ?, barcode = ?, category_id = ?, price = ?, cost_price = ?, stock_quantity = ?, expiry_date = ? WHERE id = ? AND tenant_id = ?";
                            $stmt = $conn->prepare($query);
                            if ($stmt) {
                                $stmt->bindParam(1, $name, PDO::PARAM_STR);
                                $stmt->bindParam(2, $sku, PDO::PARAM_STR);
                                $stmt->bindParam(3, $barcode, PDO::PARAM_STR);
                                $stmt->bindParam(4, $category_id, PDO::PARAM_INT);
                                $stmt->bindParam(5, $price, PDO::PARAM_STR);
                                $stmt->bindParam(6, $cost_price, PDO::PARAM_STR);
                                $stmt->bindParam(7, $stock, PDO::PARAM_INT);
                                $stmt->bindParam(8, $expiry_date, PDO::PARAM_STR);
                                $stmt->bindParam(9, $id, PDO::PARAM_INT);
                                $stmt->bindParam(10, $_SESSION['tenant_id'], PDO::PARAM_INT);
                            }
                        }
                        
                        if ($stmt && $stmt->execute()) {
                            $message = "Product updated successfully!";
                            $message_type = "success";
                        } else {
                            $message = "Error updating product";
                            $message_type = "error";
                        }
                    } else {
                        $message = "Product not found or you don't have permission to edit it.";
                        $message_type = "error";
                    }
                } else {
                    $message = "All fields are required and price/cost/stock must be numeric.";
                    $message_type = "error";
                }
                break;
                
            case 'delete':
                $id = $_POST['id'] ?? 0;
                if (is_numeric($id) && $id > 0) {
                    // First verify that the product belongs to the current tenant
                    $verify_query = "SELECT id FROM products WHERE id = ? AND tenant_id = ?";
                    $verify_stmt = $conn->prepare($verify_query);
                    $verify_stmt->bindParam(1, $id, PDO::PARAM_INT);
                    $verify_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                    $verify_stmt->execute();
                    $verify_result = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($verify_result) > 0) {
                        // Check if product has any sales records
                        $check_sales_query = "SELECT COUNT(*) as sales_count FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE si.product_id = ? AND s.tenant_id = ?";
                        $check_sales_stmt = $conn->prepare($check_sales_query);
                        if ($check_sales_stmt) {
                            $check_sales_stmt->bindParam(1, $id, PDO::PARAM_INT);
                            $check_sales_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                            $check_sales_stmt->execute();
                            $sales_result = $check_sales_stmt->fetch(PDO::FETCH_ASSOC);
                            $sales_count = $sales_result['sales_count'];
                            
                            if ($sales_count > 0) {
                                // Instead of deleting, mark as inactive
                                $update_query = "UPDATE products SET status = 'inactive' WHERE id = ? AND tenant_id = ?";
                                $update_stmt = $conn->prepare($update_query);
                                if ($update_stmt) {
                                    $update_stmt->bindParam(1, $id, PDO::PARAM_INT);
                                    $update_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                                    if ($update_stmt->execute()) {
                                        $message = "Product marked as inactive. Product has $sales_count associated sales record(s) and cannot be permanently deleted.";
                                        $message_type = "success";
                                    } else {
                                        $message = "Error updating product status";
                                        $message_type = "error";
                                    }
                                } else {
                                    $message = "Database error";
                                    $message_type = "error";
                                }
                            } else {
                                // No sales records, safe to delete
                                $delete_query = "DELETE FROM products WHERE id = ? AND tenant_id = ?";
                                $delete_stmt = $conn->prepare($delete_query);
                                if ($delete_stmt) {
                                    $delete_stmt->bindParam(1, $id, PDO::PARAM_INT);
                                    $delete_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                                    if ($delete_stmt->execute()) {
                                        $message = "Product deleted successfully!";
                                        $message_type = "success";
                                    } else {
                                        $message = "Error deleting product";
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
                        $message = "Product not found or you don't have permission to delete it.";
                        $message_type = "error";
                    }
                } else {
                    $message = "Invalid product ID.";
                    $message_type = "error";
                }
                break;
        }
    }
}

// Fetch all products for the current tenant
$products = array();
$query = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.tenant_id = ? ORDER BY p.name";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($result as $row) {
        $products[] = $row;
    }
}

// Fetch all categories for the current tenant
$categories = array();
$category_query = "SELECT id, name FROM categories WHERE tenant_id = ? ORDER BY name";
$category_stmt = $conn->prepare($category_query);
if ($category_stmt) {
    $category_stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $category_stmt->execute();
    $category_result = $category_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($category_result as $row) {
        $categories[] = $row;
    }
}

$conn = null;
?>