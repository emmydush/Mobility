<?php
// Register a new pharmacy tenant to demonstrate multi-business functionality
include_once 'api/config/database.php';

echo "Registering a new pharmacy business...\n";

// Create a new tenant for a pharmacy business
$business_name = "CITY PHARMACY";
$business_type = "Retail"; // Pharmacy is a type of retail business
$business_email = "info@citypharmacy.com";
$business_phone = "+250780000004";
$country = "Rwanda";
$city = "Kigali";
$address = "123 Main Street, Kigali";

// Generate a unique tenant_id
$tenant_id = "tenant_" . time() . "_" . rand(100, 999);

$insert_query = "INSERT INTO tenants (tenant_id, business_name, business_type, business_email, business_phone, country, city, address, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')";

$stmt = $conn->prepare($insert_query);
if ($stmt) {
    $stmt->bindParam(1, $tenant_id, PDO::PARAM_STR);
    $stmt->bindParam(2, $business_name, PDO::PARAM_STR);
    $stmt->bindParam(3, $business_type, PDO::PARAM_STR);
    $stmt->bindParam(4, $business_email, PDO::PARAM_STR);
    $stmt->bindParam(5, $business_phone, PDO::PARAM_STR);
    $stmt->bindParam(6, $country, PDO::PARAM_STR);
    $stmt->bindParam(7, $city, PDO::PARAM_STR);
    $stmt->bindParam(8, $address, PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        $new_tenant_id = $conn->lastInsertId();
        echo "✓ Successfully registered new tenant:\n";
        echo "  Business: $business_name\n";
        echo "  Type: $business_type\n";
        echo "  Tenant ID: $new_tenant_id\n";
        
        // Create an admin user for this tenant
        $username = "pharmacy_admin";
        $email = "admin@citypharmacy.com";
        $password = password_hash("pharmacy123", PASSWORD_DEFAULT);
        $role = "admin";
        
        $user_query = "INSERT INTO users (username, email, password, role, tenant_id) VALUES (?, ?, ?, ?, ?)";
        $user_stmt = $conn->prepare($user_query);
        if ($user_stmt) {
            $user_stmt->bindParam(1, $username, PDO::PARAM_STR);
            $user_stmt->bindParam(2, $email, PDO::PARAM_STR);
            $user_stmt->bindParam(3, $password, PDO::PARAM_STR);
            $user_stmt->bindParam(4, $role, PDO::PARAM_STR);
            $user_stmt->bindParam(5, $new_tenant_id, PDO::PARAM_INT);
            
            if ($user_stmt->execute()) {
                $new_user_id = $conn->lastInsertId();
                echo "✓ Created admin user for pharmacy:\n";
                echo "  Username: $username\n";
                echo "  User ID: $new_user_id\n";
                
                // Assign permissions to the new user
                $permissions = ['view_dashboard', 'manage_products', 'manage_customers', 'process_sales', 'view_reports', 'manage_inventory'];
                
                foreach ($permissions as $permission_name) {
                    // Get permission ID
                    $perm_query = "SELECT id FROM permissions WHERE name = ?";
                    $perm_stmt = $conn->prepare($perm_query);
                    $perm_stmt->bindParam(1, $permission_name, PDO::PARAM_STR);
                    $perm_stmt->execute();
                    $perm_result = $perm_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($perm_result) > 0) {
                        $permission_id = $perm_result[0]['id'];
                        
                        // Insert the permission assignment
                        $insert_perm_query = "INSERT INTO user_permissions (user_id, permission_id, granted) VALUES (?, ?, 1)";
                        $insert_perm_stmt = $conn->prepare($insert_perm_query);
                        $insert_perm_stmt->bindParam(1, $new_user_id, PDO::PARAM_INT);
                        $insert_perm_stmt->bindParam(2, $permission_id, PDO::PARAM_INT);
                        $insert_perm_stmt->execute();
                        $insert_perm_stmt->closeCursor();
                    }
                    $perm_stmt->closeCursor();
                }
                
                echo "✓ Assigned permissions to pharmacy admin\n";
            } else {
                echo "✗ Error creating user.\n";
            }
            $user_stmt->closeCursor();
        }
    } else {
        echo "✗ Error creating tenant.\n";
    }
    $stmt->closeCursor();
} else {
    echo "✗ Error preparing statement.\n";
}

$conn = null;

echo "\nNew pharmacy business registered successfully!\n";
echo "Login credentials:\n";
echo "Username: pharmacy_admin\n";
echo "Password: pharmacy123\n";
?>