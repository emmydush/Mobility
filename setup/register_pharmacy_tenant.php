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
    $stmt->bind_param("ssssssss", $tenant_id, $business_name, $business_type, $business_email, $business_phone, $country, $city, $address);
    
    if ($stmt->execute()) {
        $new_tenant_id = $conn->insert_id;
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
            $user_stmt->bind_param("ssssi", $username, $email, $password, $role, $new_tenant_id);
            
            if ($user_stmt->execute()) {
                $new_user_id = $user_stmt->insert_id;
                echo "✓ Created admin user for pharmacy:\n";
                echo "  Username: $username\n";
                echo "  User ID: $new_user_id\n";
                
                // Assign permissions to the new user
                $permissions = ['view_dashboard', 'manage_products', 'manage_customers', 'process_sales', 'view_reports', 'manage_inventory'];
                
                foreach ($permissions as $permission_name) {
                    // Get permission ID
                    $perm_query = "SELECT id FROM permissions WHERE name = ?";
                    $perm_stmt = $conn->prepare($perm_query);
                    $perm_stmt->bind_param("s", $permission_name);
                    $perm_stmt->execute();
                    $perm_result = $perm_stmt->get_result();
                    
                    if ($perm_result->num_rows > 0) {
                        $permission_id = $perm_result->fetch_assoc()['id'];
                        
                        // Insert the permission assignment
                        $insert_perm_query = "INSERT INTO user_permissions (user_id, permission_id, granted) VALUES (?, ?, 1)";
                        $insert_perm_stmt = $conn->prepare($insert_perm_query);
                        $insert_perm_stmt->bind_param("ii", $new_user_id, $permission_id);
                        $insert_perm_stmt->execute();
                        $insert_perm_stmt->close();
                    }
                    $perm_stmt->close();
                }
                
                echo "✓ Assigned permissions to pharmacy admin\n";
            } else {
                echo "✗ Error creating user: " . $user_stmt->error . "\n";
            }
            $user_stmt->close();
        }
    } else {
        echo "✗ Error creating tenant: " . $stmt->error . "\n";
    }
    $stmt->close();
} else {
    echo "✗ Error preparing statement: " . $conn->error . "\n";
}

$conn->close();

echo "\nNew pharmacy business registered successfully!\n";
echo "Login credentials:\n";
echo "Username: pharmacy_admin\n";
echo "Password: pharmacy123\n";
?>