<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");
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
        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            $query = "SELECT sm.*, p.name as product_name FROM stock_movements sm 
                     JOIN products p ON sm.product_id = p.id 
                     WHERE sm.id = ? AND sm.tenant_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $id, PDO::PARAM_INT);
            $stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
        } else {
            $query = "SELECT sm.*, p.name as product_name FROM stock_movements sm 
                     JOIN products p ON sm.product_id = p.id 
                     WHERE sm.tenant_id = ?
                     ORDER BY sm.created_at DESC";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $movements = array();
        
        foreach ($result as $row) {
            array_push($movements, $row);
        }
        
        echo json_encode(array("success" => true, "data" => $movements));
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"));
        
        if (!empty($data->product_id) && !empty($data->type) && !empty($data->quantity)) {
            // First verify that the product belongs to the current tenant
            $verify_query = "SELECT id FROM products WHERE id = ? AND tenant_id = ?";
            $verify_stmt = $conn->prepare($verify_query);
            $verify_stmt->bindParam(1, $data->product_id, PDO::PARAM_INT);
            $verify_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($verify_result) == 0) {
                http_response_code(404);
                echo json_encode(array("success" => false, "message" => "Product not found or you don't have permission to use it"));
                exit();
            }
            
            try {
                // Begin transaction
                $conn->beginTransaction();
                
                // Insert stock movement with all available fields
                $query = "INSERT INTO stock_movements (tenant_id, product_id, type, quantity, unit_price, total_value, reference_number, notes, created_by) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $total_value = $data->quantity * $data->unit_price;
                
                // Set optional fields
                $reference_number = $data->reference_number ?? null;
                $notes = $data->notes ?? null;
                $created_by = $data->created_by ?? null;
                
                $stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
                $stmt->bindParam(2, $data->product_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $data->type, PDO::PARAM_STR);
                $stmt->bindParam(4, $data->quantity, PDO::PARAM_INT);
                $stmt->bindParam(5, $data->unit_price, PDO::PARAM_STR);
                $stmt->bindParam(6, $total_value, PDO::PARAM_STR);
                $stmt->bindParam(7, $reference_number, PDO::PARAM_STR);
                $stmt->bindParam(8, $notes, PDO::PARAM_STR);
                $stmt->bindParam(9, $created_by, PDO::PARAM_INT);
                
                $stmt->execute();
                
                // Update product stock
                $stock_change = $data->type === 'in' ? $data->quantity : -$data->quantity;
                
                $update_query = "UPDATE products SET stock_quantity = stock_quantity + ? 
                               WHERE id = ? AND tenant_id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bindParam(1, $stock_change, PDO::PARAM_INT);
                $update_stmt->bindParam(2, $data->product_id, PDO::PARAM_INT);
                $update_stmt->bindParam(3, $_SESSION['tenant_id'], PDO::PARAM_INT);
                $update_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                http_response_code(201);
                echo json_encode(array(
                    "success" => true,
                    "message" => "Stock movement recorded successfully",
                    "id" => $conn->lastInsertId()
                ));
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                http_response_code(500);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Error recording stock movement: " . $e->getMessage()
                ));
            }
        } else {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "Product ID, type, and quantity are required"
            ));
        }
        break;
}
?>