<?php
// Database Configuration for Mobility Inventory Management System

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

try {
    // Create PostgreSQL connection using PDO
    $dsn = "pgsql:host={$host};port={$port};dbname={$database};";
    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Database connection error: " . $e->getMessage());
}

// Include user functions
$userFunctionsPath = __DIR__ . '/user_functions.php';
if (file_exists($userFunctionsPath)) {
    include_once $userFunctionsPath;
}

// Include language functions
$languageFunctionsPath = __DIR__ . '/languages.php';
if (file_exists($languageFunctionsPath)) {
    include_once $languageFunctionsPath;
}

// Include notification functions
$notificationFunctionsPath = __DIR__ . '/notification_functions.php';
if (file_exists($notificationFunctionsPath)) {
    include_once $notificationFunctionsPath;
}

// Track if connection has been closed
$conn_closed = false;

// Function to safely close the database connection
function closeConnection() {
    global $conn, $conn_closed;
    // Check if connection exists and has not been closed yet
    if (isset($conn) && $conn instanceof PDO && !$conn_closed) {
        $conn = null;
        $conn_closed = true;
    }
}

// Function to get the current system ID from session or default to 1
function getCurrentSystemId() {
    // In a real implementation, this would come from the user's session
    // For now, we'll default to 1 (the main system)
    return isset($_SESSION['system_id']) ? $_SESSION['system_id'] : 1;
}

// Function to get the current tenant ID from session
function getCurrentTenantId() {
    return isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : null;
}

// Function to add tenant filter to queries
function addTenantFilter($query, $tenant_field = 'tenant_id') {
    $tenant_id = getCurrentTenantId();
    if ($tenant_id) {
        // Add tenant filter to the query
        if (stripos($query, 'WHERE') !== false) {
            // If query already has WHERE clause, add AND condition
            $query = str_replace('WHERE', "WHERE {$tenant_field} = {$tenant_id} AND ", $query);
        } else {
            // If no WHERE clause, add WHERE condition
            $query .= " WHERE {$tenant_field} = {$tenant_id}";
        }
    }
    return $query;
}

// Enhanced function to add tenant filter with better validation
function addTenantFilterSecure($query, $tenant_field = 'tenant_id', $allowed_tables = []) {
    $tenant_id = getCurrentTenantId();
    
    // Validate tenant ID
    if (!$tenant_id || !is_numeric($tenant_id)) {
        throw new Exception("Invalid tenant ID");
    }
    
    // Extract table name from query for validation
    if (!empty($allowed_tables)) {
        $table_found = false;
        foreach ($allowed_tables as $table) {
            if (stripos($query, $table) !== false) {
                $table_found = true;
                break;
            }
        }
        
        if (!$table_found) {
            throw new Exception("Table not allowed for tenant filtering");
        }
    }
    
    // Add tenant filter to the query
    if (stripos($query, 'WHERE') !== false) {
        // If query already has WHERE clause, add AND condition
        $query = str_replace('WHERE', "WHERE {$tenant_field} = {$tenant_id} AND ", $query);
    } else {
        // If no WHERE clause, add WHERE condition
        $query .= " WHERE {$tenant_field} = {$tenant_id}";
    }
    
    return $query;
}

// Function to get the current user ID from session
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

// Function to validate that a user belongs to the current tenant
function validateUserTenant($conn, $user_id) {
    $tenant_id = getCurrentTenantId();
    if (!$tenant_id) {
        return false;
    }
    
    $query = "SELECT id FROM users WHERE id = ? AND tenant_id = ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll();
        $valid = count($result) > 0;
        return $valid;
    }
    
    return false;
}

// Function to add tenant_id column to INSERT queries
function addTenantIdToInsertQuery($query, $tenant_id) {
    // This function adds tenant_id to INSERT queries
    if (stripos($query, 'INSERT INTO') !== false && $tenant_id) {
        // Extract table name
        preg_match('/INSERT INTO\s+([^\s\(]+)/i', $query, $matches);
        if (isset($matches[1])) {
            $table = $matches[1];
            
            // Check if tenant_id column exists in the table
            // For now, we'll assume it exists for tables that need it
            $tables_with_tenant = ['products', 'customers', 'sales', 'users', 'categories', 'suppliers'];
            if (in_array($table, $tables_with_tenant)) {
                // Add tenant_id to the column list
                $query = preg_replace('/INSERT INTO\s+' . $table . '\s*\(([^)]+)\)/i', 'INSERT INTO ' . $table . ' (tenant_id, $1)', $query);
                
                // Add tenant_id to the values list
                $query = preg_replace('/VALUES\s*\(([^)]+)\)/i', 'VALUES (' . $tenant_id . ', $1)', $query);
            }
        }
    }
    return $query;
}
?>