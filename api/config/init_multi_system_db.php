<?php
// Multi-System Database Initialization Script
// This script creates a comprehensive database for multi-system inventory management

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Load environment variables
$env_file = __DIR__ . '/../../.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Database configuration for PostgreSQL
if (isset($_ENV['DATABASE_URL'])) {
    // Parse the DATABASE_URL
    $url = parse_url($_ENV['DATABASE_URL']);
    $host = $url['host'];
    $username = $url['user'];
    $password = $url['pass'];
    $database = substr($url['path'], 1); // Remove leading slash
    $port = isset($url['port']) ? $url['port'] : 5432; // Default PostgreSQL port
} else {
    // Fallback to default configuration
    $host = "localhost";
    $username = "mobility_db_user";
    $password = "243j1kW3g4rlkksNDdMehLHpplQVRJTa";
    $database = "mobility_db";
    $port = 5432;
}

// Connect to PostgreSQL using PDO
try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$database};";
    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die(json_encode([
        "success" => false,
        "message" => "Connection failed: " . $e->getMessage()
    ]));
}

// Array of table creation queries for multi-system support (PostgreSQL compatible)
$tables = [
    // System table to support multiple business systems
    "CREATE TABLE IF NOT EXISTS systems (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        status VARCHAR(10) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // Users table with roles and permissions
    "CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        system_id INTEGER NOT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        phone VARCHAR(20),
        address TEXT,
        role VARCHAR(20) NOT NULL,
        status VARCHAR(10) DEFAULT 'active',
        last_login TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INTEGER,
        FOREIGN KEY (system_id) REFERENCES systems(id) ON DELETE CASCADE
    )",

    // User sessions for multi-device login management
    "CREATE TABLE IF NOT EXISTS user_sessions (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL,
        token VARCHAR(255) NOT NULL,
        device_info TEXT,
        ip_address VARCHAR(45),
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    // Activity log for audit trail across systems
    "CREATE TABLE IF NOT EXISTS activity_log (
        id SERIAL PRIMARY KEY,
        system_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        table_name VARCHAR(50) NOT NULL,
        record_id INTEGER,
        old_values TEXT,
        new_values TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (system_id) REFERENCES systems(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    // Settings table for system configuration
    "CREATE TABLE IF NOT EXISTS settings (
        id SERIAL PRIMARY KEY,
        system_id INTEGER NOT NULL,
        setting_key VARCHAR(50) NOT NULL UNIQUE,
        setting_value TEXT,
        setting_type VARCHAR(20) DEFAULT 'string',
        description TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_by INTEGER,
        FOREIGN KEY (system_id) REFERENCES systems(id) ON DELETE CASCADE
    )",

    // Categories for products
    "CREATE TABLE IF NOT EXISTS categories (
        id SERIAL PRIMARY KEY,
        system_id INTEGER NOT NULL,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INTEGER,
        FOREIGN KEY (system_id) REFERENCES systems(id) ON DELETE CASCADE
    )",

    // Suppliers table
    "CREATE TABLE IF NOT EXISTS suppliers (
        id SERIAL PRIMARY KEY,
        system_id INTEGER NOT NULL,
        name VARCHAR(100) NOT NULL,
        contact_person VARCHAR(100),
        email VARCHAR(100),
        phone VARCHAR(20),
        address TEXT,
        status VARCHAR(10) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INTEGER,
        FOREIGN KEY (system_id) REFERENCES systems(id) ON DELETE CASCADE
    )",

    // Customers with loyalty system
    "CREATE TABLE IF NOT EXISTS customers (
        id SERIAL PRIMARY KEY,
        system_id INTEGER NOT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(20),
        address TEXT,
        loyalty_points INTEGER DEFAULT 0,
        total_purchases DECIMAL(12,2) DEFAULT 0,
        balance DECIMAL(12,2) DEFAULT 0,
        credit_limit DECIMAL(12,2) DEFAULT 0,
        status VARCHAR(10) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INTEGER,
        FOREIGN KEY (system_id) REFERENCES systems(id) ON DELETE CASCADE
    )",

    // Products table with categories and suppliers
    "CREATE TABLE IF NOT EXISTS products (
        id SERIAL PRIMARY KEY,
        system_id INTEGER NOT NULL,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        sku VARCHAR(50) UNIQUE,
        barcode VARCHAR(50) UNIQUE,
        category_id INTEGER,
        supplier_id INTEGER,
        price DECIMAL(12,2) NOT NULL,
        cost_price DECIMAL(12,2) NOT NULL,
        wholesale_price DECIMAL(12,2),
        stock_quantity INTEGER NOT NULL DEFAULT 0,
        minimum_stock INTEGER NOT NULL DEFAULT 10,
        maximum_stock INTEGER,
        expiry_date DATE,
        status VARCHAR(10) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INTEGER,
        FOREIGN KEY (system_id) REFERENCES systems(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES categories(id),
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
    )",

    // Warehouses for multi-location inventory
    "CREATE TABLE IF NOT EXISTS warehouses (
        id SERIAL PRIMARY KEY,
        system_id INTEGER NOT NULL,
        name VARCHAR(100) NOT NULL,
        location VARCHAR(255),
        manager_id INTEGER,
        capacity INTEGER,
        status VARCHAR(10) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INTEGER,
        FOREIGN KEY (system_id) REFERENCES systems(id) ON DELETE CASCADE,
        FOREIGN KEY (manager_id) REFERENCES users(id)
    )",

    // Stock movements table
    "CREATE TABLE IF NOT EXISTS stock_movements (
        id SERIAL PRIMARY KEY,
        system_id INTEGER NOT NULL,
        product_id INTEGER NOT NULL,
        warehouse_id INTEGER,
        movement_type VARCHAR(20) NOT NULL,
        quantity INTEGER NOT NULL,
        unit_cost DECIMAL(12,2),
        reference VARCHAR(100),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INTEGER,
        FOREIGN KEY (system_id) REFERENCES systems(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id),
        FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    )",

    // Purchases table
    "CREATE TABLE IF NOT EXISTS purchases (
        id SERIAL PRIMARY KEY,
        system_id INTEGER NOT NULL,
        supplier_id INTEGER,
        warehouse_id INTEGER,
        invoice_number VARCHAR(50),
        purchase_date DATE NOT NULL,
        due_date DATE,
        total_amount DECIMAL(12,2) NOT NULL,
        paid_amount DECIMAL(12,2) DEFAULT 0,
        status VARCHAR(20) DEFAULT 'pending',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INTEGER,
        FOREIGN KEY (system_id) REFERENCES systems(id) ON DELETE CASCADE,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
        FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    )",

    // Purchase items table
    "CREATE TABLE IF NOT EXISTS purchase_items (
        id SERIAL PRIMARY KEY,
        purchase_id INTEGER NOT NULL,
        product_id INTEGER NOT NULL,
        quantity INTEGER NOT NULL,
        unit_price DECIMAL(12,2) NOT NULL,
        total_price DECIMAL(12,2) NOT NULL,
        FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )",

    // Sales table
    "CREATE TABLE IF NOT EXISTS sales (
        id SERIAL PRIMARY KEY,
        system_id INTEGER NOT NULL,
        customer_id INTEGER,
        warehouse_id INTEGER,
        invoice_number VARCHAR(50),
        sale_date DATE NOT NULL,
        due_date DATE,
        total_amount DECIMAL(12,2) NOT NULL,
        paid_amount DECIMAL(12,2) DEFAULT 0,
        discount_amount DECIMAL(12,2) DEFAULT 0,
        tax_amount DECIMAL(12,2) DEFAULT 0,
        status VARCHAR(20) DEFAULT 'pending',
        payment_method VARCHAR(20),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INTEGER,
        FOREIGN KEY (system_id) REFERENCES systems(id) ON DELETE CASCADE,
        FOREIGN KEY (customer_id) REFERENCES customers(id),
        FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    )",

    // Sales items table
    "CREATE TABLE IF NOT EXISTS sale_items (
        id SERIAL PRIMARY KEY,
        sale_id INTEGER NOT NULL,
        product_id INTEGER NOT NULL,
        quantity INTEGER NOT NULL,
        unit_price DECIMAL(12,2) NOT NULL,
        discount_amount DECIMAL(12,2) DEFAULT 0,
        tax_amount DECIMAL(12,2) DEFAULT 0,
        total_price DECIMAL(12,2) NOT NULL,
        FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )",

    // Expenses table
    "CREATE TABLE IF NOT EXISTS expenses (
        id SERIAL PRIMARY KEY,
        system_id INTEGER NOT NULL,
        category VARCHAR(50) NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        expense_date DATE NOT NULL,
        description TEXT,
        receipt_path VARCHAR(255),
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INTEGER,
        FOREIGN KEY (system_id) REFERENCES systems(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )",

    // Notifications table
    "CREATE TABLE IF NOT EXISTS notifications (
        id SERIAL PRIMARY KEY,
        system_id INTEGER NOT NULL,
        user_id INTEGER,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(20) DEFAULT 'info',
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (system_id) REFERENCES systems(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    // Permissions table
    "CREATE TABLE IF NOT EXISTS permissions (
        id SERIAL PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE,
        description TEXT
    )",

    // Role permissions table
    "CREATE TABLE IF NOT EXISTS role_permissions (
        id SERIAL PRIMARY KEY,
        role VARCHAR(20) NOT NULL,
        permission_id INTEGER NOT NULL,
        FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
    )"
];

// Create tables
$success_count = 0;
$error_count = 0;
$errors = [];

foreach ($tables as $index => $query) {
    try {
        $conn->exec($query);
        $success_count++;
    } catch (PDOException $e) {
        $error_count++;
        $errors[] = "Error creating table " . ($index + 1) . ": " . $e->getMessage();
    }
}

// Insert default permissions
$permissions = [
    ['name' => 'view_dashboard', 'description' => 'View dashboard'],
    ['name' => 'manage_products', 'description' => 'Manage products'],
    ['name' => 'manage_categories', 'description' => 'Manage categories'],
    ['name' => 'manage_suppliers', 'description' => 'Manage suppliers'],
    ['name' => 'manage_customers', 'description' => 'Manage customers'],
    ['name' => 'manage_purchases', 'description' => 'Manage purchases'],
    ['name' => 'manage_sales', 'description' => 'Manage sales'],
    ['name' => 'manage_expenses', 'description' => 'Manage expenses'],
    ['name' => 'view_reports', 'description' => 'View reports'],
    ['name' => 'manage_users', 'description' => 'Manage users'],
    ['name' => 'manage_settings', 'description' => 'Manage settings']
];

foreach ($permissions as $permission) {
    try {
        $stmt = $conn->prepare("INSERT INTO permissions (name, description) VALUES (?, ?) ON CONFLICT (name) DO NOTHING");
        $stmt->execute([$permission['name'], $permission['description']]);
    } catch (PDOException $e) {
        // Ignore duplicate key errors
    }
}

// Insert default role permissions
$role_permissions = [
    // Super Admin - all permissions
    ['role' => 'super_admin', 'permission_id' => 1],
    ['role' => 'super_admin', 'permission_id' => 2],
    ['role' => 'super_admin', 'permission_id' => 3],
    ['role' => 'super_admin', 'permission_id' => 4],
    ['role' => 'super_admin', 'permission_id' => 5],
    ['role' => 'super_admin', 'permission_id' => 6],
    ['role' => 'super_admin', 'permission_id' => 7],
    ['role' => 'super_admin', 'permission_id' => 8],
    ['role' => 'super_admin', 'permission_id' => 9],
    ['role' => 'super_admin', 'permission_id' => 10],
    ['role' => 'super_admin', 'permission_id' => 11],
    
    // Admin - most permissions except super admin specific
    ['role' => 'admin', 'permission_id' => 1],
    ['role' => 'admin', 'permission_id' => 2],
    ['role' => 'admin', 'permission_id' => 3],
    ['role' => 'admin', 'permission_id' => 4],
    ['role' => 'admin', 'permission_id' => 5],
    ['role' => 'admin', 'permission_id' => 6],
    ['role' => 'admin', 'permission_id' => 7],
    ['role' => 'admin', 'permission_id' => 8],
    ['role' => 'admin', 'permission_id' => 9],
    ['role' => 'admin', 'permission_id' => 10],
    ['role' => 'admin', 'permission_id' => 11],
    
    // Manager - operational permissions
    ['role' => 'manager', 'permission_id' => 1],
    ['role' => 'manager', 'permission_id' => 2],
    ['role' => 'manager', 'permission_id' => 3],
    ['role' => 'manager', 'permission_id' => 4],
    ['role' => 'manager', 'permission_id' => 5],
    ['role' => 'manager', 'permission_id' => 6],
    ['role' => 'manager', 'permission_id' => 7],
    ['role' => 'manager', 'permission_id' => 8],
    ['role' => 'manager', 'permission_id' => 9],
    
    // Cashier - limited permissions
    ['role' => 'cashier', 'permission_id' => 1],
    ['role' => 'cashier', 'permission_id' => 5],
    ['role' => 'cashier', 'permission_id' => 7],
    
    // Auditor - read-only permissions
    ['role' => 'auditor', 'permission_id' => 1],
    ['role' => 'auditor', 'permission_id' => 8],
    ['role' => 'auditor', 'permission_id' => 9]
];

foreach ($role_permissions as $role_permission) {
    try {
        $stmt = $conn->prepare("INSERT INTO role_permissions (role, permission_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
        $stmt->execute([$role_permission['role'], $role_permission['permission_id']]);
    } catch (PDOException $e) {
        // Ignore duplicate key errors
    }
}

// Response
if ($error_count == 0) {
    echo json_encode([
        "success" => true,
        "message" => "Database initialized successfully. $success_count tables created.",
        "tables_created" => $success_count
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "$error_count errors occurred while creating tables.",
        "errors" => $errors,
        "tables_created" => $success_count
    ]);
}

$conn = null;
?>