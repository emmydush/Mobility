<?php
// Initialize default permissions in the database

// Include database connection
include 'api/config/database.php';

echo "Initializing default permissions...\n";

// Default permissions data
$permissions = [
    ['name' => 'view_dashboard', 'description' => 'View dashboard', 'module' => 'Dashboard'],
    ['name' => 'manage_products', 'description' => 'Manage products', 'module' => 'Products'],
    ['name' => 'manage_customers', 'description' => 'Manage customers', 'module' => 'Customers'],
    ['name' => 'process_sales', 'description' => 'Process sales', 'module' => 'Sales'],
    ['name' => 'view_reports', 'description' => 'View reports', 'module' => 'Reports'],
    ['name' => 'manage_inventory', 'description' => 'Manage inventory', 'module' => 'Inventory'],
    ['name' => 'manage_users', 'description' => 'Manage users', 'module' => 'Users'],
    ['name' => 'manage_settings', 'description' => 'Manage settings', 'module' => 'Settings'],
    ['name' => 'view_audit_log', 'description' => 'View audit log', 'module' => 'Security'],
    ['name' => 'manage_tenants', 'description' => 'Manage tenants', 'module' => 'Tenants']
];

try {
    // Insert permissions
    foreach ($permissions as $permission) {
        $query = "INSERT INTO permissions (name, description, module) VALUES (?, ?, ?) ON CONFLICT (name) DO NOTHING";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bindParam(1, $permission['name'], PDO::PARAM_STR);
            $stmt->bindParam(2, $permission['description'], PDO::PARAM_STR);
            $stmt->bindParam(3, $permission['module'], PDO::PARAM_STR);
            $stmt->execute();
            echo "✓ Permission '{$permission['name']}' added\n";
        }
    }
    
    echo "Permissions initialized successfully!\n";
    
} catch (PDOException $e) {
    echo "Error initializing permissions: " . $e->getMessage() . "\n";
}

$conn = null;
?>