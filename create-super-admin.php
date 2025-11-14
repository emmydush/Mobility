<?php
// Create a super admin user

// Include database connection
include 'api/config/database.php';

echo "Creating super admin user...\n";

// Super admin credentials
$username = 'superadmin';
$email = 'admin@mobility.com';
$password = password_hash('superadmin123', PASSWORD_DEFAULT);
$role = 'super_admin';

try {
    // Check if super admin already exists
    $check_query = "SELECT id FROM users WHERE role = 'super_admin'";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->execute();
    $result = $check_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($result) > 0) {
        echo "Super admin user already exists.\n";
    } else {
        // Insert super admin user
        $query = "INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, 'active')";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bindParam(1, $username, PDO::PARAM_STR);
            $stmt->bindParam(2, $email, PDO::PARAM_STR);
            $stmt->bindParam(3, $password, PDO::PARAM_STR);
            $stmt->bindParam(4, $role, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $user_id = $conn->lastInsertId();
                echo "✓ Super admin user created successfully!\n";
                echo "User ID: $user_id\n";
                echo "Username: $username\n";
                echo "Email: $email\n";
                echo "Role: $role\n";
                echo "\nLogin credentials:\n";
                echo "Username: $username\n";
                echo "Password: superadmin123\n";
                
                // Assign all permissions to super admin
                $permissions_query = "SELECT id FROM permissions";
                $permissions_stmt = $conn->prepare($permissions_query);
                $permissions_stmt->execute();
                $permissions = $permissions_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($permissions as $permission) {
                    $perm_id = $permission['id'];
                    $perm_query = "INSERT INTO user_permissions (user_id, permission_id, granted) VALUES (?, ?, true)";
                    $perm_stmt = $conn->prepare($perm_query);
                    $perm_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
                    $perm_stmt->bindParam(2, $perm_id, PDO::PARAM_INT);
                    $perm_stmt->execute();
                }
                
                echo "✓ All permissions assigned to super admin\n";
            } else {
                echo "✗ Error creating super admin user.\n";
            }
        } else {
            echo "✗ Error preparing statement.\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Error creating super admin user: " . $e->getMessage() . "\n";
}

$conn = null;
?>