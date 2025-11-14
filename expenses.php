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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $expense_number = $_POST['expense_number'] ?? '';
                $category = $_POST['category'] ?? '';
                $description = $_POST['description'] ?? '';
                $amount = $_POST['amount'] ?? 0;
                $payment_method = $_POST['payment_method'] ?? 'cash';
                $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
                $notes = $_POST['notes'] ?? '';
                
                // Validate required fields
                if (!empty($expense_number) && !empty($category) && is_numeric($amount) && $amount > 0) {
                    try {
                        // Begin transaction
                        $conn->beginTransaction();
                        
                        // Insert expense record with tenant_id
                        $query = "INSERT INTO expenses (tenant_id, expense_number, category, description, amount, payment_method, payment_status, expense_date, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, 'completed', ?, ?, ?)";
                        $stmt = $conn->prepare($query);
                        if ($stmt) {
                            $stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
                            $stmt->bindParam(2, $expense_number, PDO::PARAM_STR);
                            $stmt->bindParam(3, $category, PDO::PARAM_STR);
                            $stmt->bindParam(4, $description, PDO::PARAM_STR);
                            $stmt->bindParam(5, $amount, PDO::PARAM_STR);
                            $stmt->bindParam(6, $payment_method, PDO::PARAM_STR);
                            $stmt->bindParam(7, $expense_date, PDO::PARAM_STR);
                            $stmt->bindParam(8, $notes, PDO::PARAM_STR);
                            $stmt->bindParam(9, $_SESSION['user_id'], PDO::PARAM_INT);
                            if ($stmt->execute()) {
                                $conn->commit();
                                $message = "Expense added successfully!";
                                $message_type = "success";
                            } else {
                                throw new Exception("Error adding expense");
                            }
                        } else {
                            throw new Exception("Error preparing expense statement");
                        }
                    } catch (Exception $e) {
                        $conn->rollback();
                        $message = "Error adding expense: " . $e->getMessage();
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
                    try {
                        // Begin transaction
                        $conn->beginTransaction();
                        
                        // Delete expense with tenant filtering
                        $query = "DELETE FROM expenses WHERE id = ? AND tenant_id = ?";
                        $stmt = $conn->prepare($query);
                        if ($stmt) {
                            $stmt->bindParam(1, $id, PDO::PARAM_INT);
                            $stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                            if ($stmt->execute()) {
                                $conn->commit();
                                $message = "Expense deleted successfully!";
                                $message_type = "success";
                            } else {
                                throw new Exception("Error deleting expense");
                            }
                        } else {
                            throw new Exception("Error preparing delete statement");
                        }
                    } catch (Exception $e) {
                        $conn->rollback();
                        $message = "Error deleting expense: " . $e->getMessage();
                        $message_type = "error";
                    }
                } else {
                    $message = "Invalid expense ID.";
                    $message_type = "error";
                }
                break;
        }
    }
}

// Fetch all expenses for current tenant only
$expenses = array();
$query = "SELECT * FROM expenses WHERE tenant_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($result as $row) {
        $expenses[] = $row;
    }
}

// Fetch all expense categories
$categories = array();
$category_query = "SELECT name FROM expense_categories ORDER BY name";
$category_stmt = $conn->prepare($category_query);
if ($category_stmt) {
    $category_stmt->execute();
    $category_result = $category_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($category_result as $row) {
        $categories[] = $row;
    }
}

// Generate a unique expense number
function generateExpenseNumber($conn, $tenant_id) {
    $prefix = "EXP-" . date("Y") . date("m");
    $query = "SELECT COUNT(*) as count FROM expenses WHERE expense_number LIKE ? AND tenant_id = ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $search = $prefix . "%";
        $stmt->bindParam(1, $search, PDO::PARAM_STR);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = $result['count'] + 1;
        return $prefix . str_pad($count, 4, "0", STR_PAD_LEFT);
    }
    return $prefix . "0001";
}

$expense_number = generateExpenseNumber($conn, $_SESSION['tenant_id']);
?>