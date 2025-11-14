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
        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            $query = "SELECT * FROM customers WHERE id = ? AND tenant_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $id, PDO::PARAM_INT);
            $stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
        } else {
            $query = "SELECT * FROM customers WHERE tenant_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $customers = array();
        
        foreach ($result as $row) {
            array_push($customers, $row);
        }
        
        echo json_encode(array("success" => true, "data" => $customers));
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"));
        
        if (!empty($data->name)) {
            $query = "INSERT INTO customers (tenant_id, name, email, phone, address) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $_SESSION['tenant_id'], PDO::PARAM_INT);
            $stmt->bindParam(2, $data->name, PDO::PARAM_STR);
            $stmt->bindParam(3, $data->email ?? null, PDO::PARAM_STR);
            $stmt->bindParam(4, $data->phone ?? null, PDO::PARAM_STR);
            $stmt->bindParam(5, $data->address ?? null, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode(array(
                    "success" => true,
                    "message" => "Customer created successfully",
                    "id" => $conn->lastInsertId()
                ));
            } else {
                http_response_code(500);
                echo json_encode(array("success" => false, "message" => "Unable to create customer"));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("success" => false, "message" => "Customer name is required"));
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));
        
        if (!empty($data->id)) {
            // First verify that the customer belongs to the current tenant
            $verify_query = "SELECT id FROM customers WHERE id = ? AND tenant_id = ?";
            $verify_stmt = $conn->prepare($verify_query);
            $verify_stmt->bindParam(1, $data->id, PDO::PARAM_INT);
            $verify_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($verify_result) > 0) {
                $query = "UPDATE customers SET name = ?, email = ?, phone = ?, address = ? WHERE id = ? AND tenant_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $data->name, PDO::PARAM_STR);
                $stmt->bindParam(2, $data->email ?? null, PDO::PARAM_STR);
                $stmt->bindParam(3, $data->phone ?? null, PDO::PARAM_STR);
                $stmt->bindParam(4, $data->address ?? null, PDO::PARAM_STR);
                $stmt->bindParam(5, $data->id, PDO::PARAM_INT);
                $stmt->bindParam(6, $_SESSION['tenant_id'], PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    echo json_encode(array("success" => true, "message" => "Customer updated successfully"));
                } else {
                    http_response_code(500);
                    echo json_encode(array("success" => false, "message" => "Unable to update customer"));
                }
            } else {
                http_response_code(404);
                echo json_encode(array("success" => false, "message" => "Customer not found or you don't have permission to update it"));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("success" => false, "message" => "Customer ID is required"));
        }
        break;

    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"));
        
        if (!empty($data->id)) {
            // First verify that the customer belongs to the current tenant
            $verify_query = "SELECT id FROM customers WHERE id = ? AND tenant_id = ?";
            $verify_stmt = $conn->prepare($verify_query);
            $verify_stmt->bindParam(1, $data->id, PDO::PARAM_INT);
            $verify_stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($verify_result) > 0) {
                $query = "DELETE FROM customers WHERE id = ? AND tenant_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $data->id, PDO::PARAM_INT);
                $stmt->bindParam(2, $_SESSION['tenant_id'], PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    echo json_encode(array("success" => true, "message" => "Customer deleted successfully"));
                } else {
                    http_response_code(500);
                    echo json_encode(array("success" => false, "message" => "Unable to delete customer"));
                }
            } else {
                http_response_code(404);
                echo json_encode(array("success" => false, "message" => "Customer not found or you don't have permission to delete it"));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("success" => false, "message" => "Customer ID is required"));
        }
        break;
}
?>