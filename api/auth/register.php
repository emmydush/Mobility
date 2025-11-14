<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->username) && !empty($data->password) && !empty($data->email) && !empty($data->role)) {
    $username = $data->username;
    $password = password_hash($data->password, PASSWORD_DEFAULT);
    $email = $data->email;
    $role = $data->role;
    
    // Check if username or email already exists
    $check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bindParam(1, $username, PDO::PARAM_STR);
    $check_stmt->bindParam(2, $email, PDO::PARAM_STR);
    $check_stmt->execute();
    $check_result = $check_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($check_result) > 0) {
        http_response_code(400);
        echo json_encode(array("success" => false, "message" => "Username or email already exists"));
        exit();
    }
    
    $query = "INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $username, PDO::PARAM_STR);
    $stmt->bindParam(2, $password, PDO::PARAM_STR);
    $stmt->bindParam(3, $email, PDO::PARAM_STR);
    $stmt->bindParam(4, $role, PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        $user_id = $conn->lastInsertId();
        http_response_code(201);
        echo json_encode(array(
            "success" => true,
            "message" => "User created successfully",
            "user" => array(
                "id" => $user_id,
                "username" => $username,
                "email" => $email,
                "role" => $role
            )
        ));
    } else {
        http_response_code(500);
        echo json_encode(array("success" => false, "message" => "Unable to create user"));
    }
} else {
    http_response_code(400);
    echo json_encode(array("success" => false, "message" => "All fields are required"));
}
?>