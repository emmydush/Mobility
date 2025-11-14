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
                $description = $_POST['description'] ?? '';
                
                if (!empty($name)) {
                    $query = "INSERT INTO categories (tenant_id, name, description, created_by) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    
                    if ($stmt === false) {
                        $message = "Error preparing statement";
                        $message_type = "error";
                    } else {
                        $stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
                        $stmt->bindParam(2, $name, PDO::PARAM_STR);
                        $stmt->bindParam(3, $description, PDO::PARAM_STR);
                        $stmt->bindParam(4, $_SESSION['user_id'], PDO::PARAM_INT);
                        if ($stmt->execute()) {
                            $message = "Category added successfully!";
                            $message_type = "success";
                        } else {
                            $message = "Error adding category";
                            $message_type = "error";
                        }
                    }
                } else {
                    $message = "Category name is required.";
                    $message_type = "error";
                }
                break;
                
            case 'edit':
                $id = $_POST['id'] ?? 0;
                $name = $_POST['name'] ?? '';
                $description = $_POST['description'] ?? '';
                
                if (is_numeric($id) && $id > 0 && !empty($name)) {
                    // First verify that the category belongs to the current tenant
                    $verify_query = "SELECT id FROM categories WHERE id = ? AND tenant_id = ?";
                    $verify_stmt = $conn->prepare($verify_query);
                    $verify_stmt->bindParam(1, $id, PDO::PARAM_INT);
                    $verify_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                    $verify_stmt->execute();
                    $verify_result = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($verify_result) > 0) {
                        $query = "UPDATE categories SET name = ?, description = ? WHERE id = ? AND tenant_id = ?";
                        $stmt = $conn->prepare($query);
                        
                        if ($stmt === false) {
                            $message = "Error preparing statement";
                            $message_type = "error";
                        } else {
                            $stmt->bindParam(1, $name, PDO::PARAM_STR);
                            $stmt->bindParam(2, $description, PDO::PARAM_STR);
                            $stmt->bindParam(3, $id, PDO::PARAM_INT);
                            $stmt->bindParam(4, $_SESSION['tenant_id'], PDO::PARAM_INT);
                            if ($stmt->execute()) {
                                $message = "Category updated successfully!";
                                $message_type = "success";
                            } else {
                                $message = "Error updating category";
                                $message_type = "error";
                            }
                        }
                    } else {
                        $message = "Category not found or you don't have permission to edit it.";
                        $message_type = "error";
                    }
                } else {
                    $message = "Invalid category ID or name is required.";
                    $message_type = "error";
                }
                break;
                
            case 'delete':
                $id = $_POST['id'] ?? 0;
                
                if (is_numeric($id) && $id > 0) {
                    // First verify that the category belongs to the current tenant
                    $verify_query = "SELECT id FROM categories WHERE id = ? AND tenant_id = ?";
                    $verify_stmt = $conn->prepare($verify_query);
                    $verify_stmt->bindParam(1, $id, PDO::PARAM_INT);
                    $verify_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                    $verify_stmt->execute();
                    $verify_result = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($verify_result) > 0) {
                        // Check if category is used by any products
                        $check_query = "SELECT COUNT(*) as count FROM products WHERE category_id = ? AND tenant_id = ?";
                        $check_stmt = $conn->prepare($check_query);
                        
                        if ($check_stmt === false) {
                            $message = "Error preparing statement";
                            $message_type = "error";
                        } else {
                            $check_stmt->bindParam(1, $id, PDO::PARAM_INT);
                            $check_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                            $check_stmt->execute();
                            $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($result['count'] > 0) {
                                $message = "Cannot delete category. It is used by " . $result['count'] . " product(s).";
                                $message_type = "error";
                            } else {
                                // Safe to delete
                                $query = "DELETE FROM categories WHERE id = ? AND tenant_id = ?";
                                $stmt = $conn->prepare($query);
                                
                                if ($stmt === false) {
                                    $message = "Error preparing statement";
                                    $message_type = "error";
                                } else {
                                    $stmt->bindParam(1, $id, PDO::PARAM_INT);
                                    $stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                                    if ($stmt->execute()) {
                                        $message = "Category deleted successfully!";
                                        $message_type = "success";
                                    } else {
                                        $message = "Error deleting category";
                                        $message_type = "error";
                                    }
                                }
                            }
                        }
                    } else {
                        $message = "Category not found or you don't have permission to delete it.";
                        $message_type = "error";
                    }
                } else {
                    $message = "Invalid category ID.";
                    $message_type = "error";
                }
                break;
        }
    }
}

// Fetch all categories for the current tenant
$categories = array();
$query = "SELECT c.*, u.username as created_by_name FROM categories c LEFT JOIN users u ON c.created_by = u.id WHERE c.tenant_id = ? ORDER BY c.name";
$stmt = $conn->prepare($query);

if ($stmt === false) {
    $message = "Error preparing categories query";
    $message_type = "error";
} else {
    $stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($result as $row) {
        $categories[] = $row;
    }
}
?>