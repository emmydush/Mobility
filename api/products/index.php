<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
    http_response_code(401);
    echo json_encode(array("success" => false, "message" => "Unauthorized"));
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get all products or single product if ID is provided
        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            $query = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ? AND p.tenant_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $id, PDO::PARAM_INT);
            $stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
        } else {
            $query = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.tenant_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $products = array();
        
        foreach ($result as $row) {
            array_push($products, $row);
        }
        
        echo json_encode(array("success" => true, "data" => $products));
        break;

    case 'POST':
        // Create new product
        $data = json_decode(file_get_contents("php://input"));
        
        if (!empty($data->name) && !empty($data->price)) {
            $query = "INSERT INTO products (tenant_id, name, description, sku, barcode, category_id, supplier_id, price, cost_price, stock_quantity, minimum_stock, maximum_stock, expiry_date, status, created_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            // Set default values for optional fields
            $sku = $data->sku ?? null;
            $barcode = $data->barcode ?? null;
            $description = $data->description ?? '';
            $supplier_id = $data->supplier_id ?? null;
            $cost_price = $data->cost_price ?? 0;
            $stock_quantity = $data->stock_quantity ?? 0;
            $minimum_stock = $data->minimum_stock ?? 10;
            $maximum_stock = $data->maximum_stock ?? null;
            $expiry_date = $data->expiry_date ?? null;
            $status = $data->status ?? 'active';
            $created_by = $data->created_by ?? null;
            
            $stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
            $stmt->bindParam(2, $data->name, PDO::PARAM_STR);
            $stmt->bindParam(3, $description, PDO::PARAM_STR);
            $stmt->bindParam(4, $sku, PDO::PARAM_STR);
            $stmt->bindParam(5, $barcode, PDO::PARAM_STR);
            $stmt->bindParam(6, $data->category_id, PDO::PARAM_INT);
            $stmt->bindParam(7, $supplier_id, PDO::PARAM_INT);
            $stmt->bindParam(8, $data->price, PDO::PARAM_STR);
            $stmt->bindParam(9, $cost_price, PDO::PARAM_STR);
            $stmt->bindParam(10, $stock_quantity, PDO::PARAM_INT);
            $stmt->bindParam(11, $minimum_stock, PDO::PARAM_INT);
            $stmt->bindParam(12, $maximum_stock, PDO::PARAM_INT);
            $stmt->bindParam(13, $expiry_date, PDO::PARAM_STR);
            $stmt->bindParam(14, $status, PDO::PARAM_STR);
            $stmt->bindParam(15, $created_by, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode(array(
                    "success" => true,
                    "message" => "Product created successfully",
                    "id" => $conn->lastInsertId()
                ));
            } else {
                http_response_code(500);
                echo json_encode(array("success" => false, "message" => "Unable to create product"));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("success" => false, "message" => "Name and price are required"));
        }
        break;

    case 'PUT':
        // Update product
        $data = json_decode(file_get_contents("php://input"));
        
        if (!empty($data->id)) {
            // First verify that the product belongs to the current tenant
            $verify_query = "SELECT id FROM products WHERE id = ? AND tenant_id = ?";
            $verify_stmt = $conn->prepare($verify_query);
            $verify_stmt->bindParam(1, $data->id, PDO::PARAM_INT);
            $verify_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($verify_result) > 0) {
                $query = "UPDATE products SET 
                         name = ?, 
                         description = ?, 
                         sku = ?,
                         barcode = ?,
                         category_id = ?,
                         supplier_id = ?,
                         price = ?,
                         cost_price = ?,
                         stock_quantity = ?,
                         minimum_stock = ?,
                         maximum_stock = ?,
                         expiry_date = ?,
                         status = ?,
                         created_by = ?
                         WHERE id = ? AND tenant_id = ?";
                
                $stmt = $conn->prepare($query);
                // Set default values for optional fields
                $sku = $data->sku ?? null;
                $barcode = $data->barcode ?? null;
                $description = $data->description ?? '';
                $supplier_id = $data->supplier_id ?? null;
                $cost_price = $data->cost_price ?? 0;
                $stock_quantity = $data->stock_quantity ?? 0;
                $minimum_stock = $data->minimum_stock ?? 10;
                $maximum_stock = $data->maximum_stock ?? null;
                $expiry_date = $data->expiry_date ?? null;
                $status = $data->status ?? 'active';
                $created_by = $data->created_by ?? null;
                
                $stmt->bindParam(1, $data->name, PDO::PARAM_STR);
                $stmt->bindParam(2, $description, PDO::PARAM_STR);
                $stmt->bindParam(3, $sku, PDO::PARAM_STR);
                $stmt->bindParam(4, $barcode, PDO::PARAM_STR);
                $stmt->bindParam(5, $data->category_id, PDO::PARAM_INT);
                $stmt->bindParam(6, $supplier_id, PDO::PARAM_INT);
                $stmt->bindParam(7, $data->price, PDO::PARAM_STR);
                $stmt->bindParam(8, $cost_price, PDO::PARAM_STR);
                $stmt->bindParam(9, $stock_quantity, PDO::PARAM_INT);
                $stmt->bindParam(10, $minimum_stock, PDO::PARAM_INT);
                $stmt->bindParam(11, $maximum_stock, PDO::PARAM_INT);
                $stmt->bindParam(12, $expiry_date, PDO::PARAM_STR);
                $stmt->bindParam(13, $status, PDO::PARAM_STR);
                $stmt->bindParam(14, $created_by, PDO::PARAM_INT);
                $stmt->bindParam(15, $data->id, PDO::PARAM_INT);
                $stmt->bindParam(16, $_SESSION['tenant_id'], PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    echo json_encode(array("success" => true, "message" => "Product updated successfully"));
                } else {
                    http_response_code(500);
                    echo json_encode(array("success" => false, "message" => "Unable to update product"));
                }
            } else {
                http_response_code(404);
                echo json_encode(array("success" => false, "message" => "Product not found or you don't have permission to update it"));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("success" => false, "message" => "Product ID is required"));
        }
        break;

    case 'DELETE':
        // Delete product
        $data = json_decode(file_get_contents("php://input"));
        
        if (!empty($data->id)) {
            // First verify that the product belongs to the current tenant
            $verify_query = "SELECT id FROM products WHERE id = ? AND tenant_id = ?";
            $verify_stmt = $conn->prepare($verify_query);
            $verify_stmt->bindParam(1, $data->id, PDO::PARAM_INT);
            $verify_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($verify_result) > 0) {
                $query = "DELETE FROM products WHERE id = ? AND tenant_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $data->id, PDO::PARAM_INT);
                $stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    echo json_encode(array("success" => true, "message" => "Product deleted successfully"));
                } else {
                    http_response_code(500);
                    echo json_encode(array("success" => false, "message" => "Unable to delete product"));
                }
            } else {
                http_response_code(404);
                echo json_encode(array("success" => false, "message" => "Product not found or you don't have permission to delete it"));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("success" => false, "message" => "Product ID is required"));
        }
        break;
}
?>