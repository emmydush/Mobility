<?php
// Simple database connection test

// Load environment variables
$env_file = __DIR__ . '/.env';
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
    $port = isset($url['port']) ? $url['port'] : 5432;
    
    echo "Database configuration:\n";
    echo "Host: $host\n";
    echo "Port: $port\n";
    echo "Database: $database\n";
    echo "Username: $username\n";
    
    try {
        // Create PostgreSQL connection using PDO
        $dsn = "pgsql:host={$host};port={$port};dbname={$database};";
        $conn = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        
        echo "✓ Connected to database successfully\n";
        
        // Test if tenants table exists
        $stmt = $conn->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'tenants'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($tables) > 0) {
            echo "✓ Tenants table exists\n";
        } else {
            echo "✗ Tenants table does not exist\n";
        }
        
        // Test if users table exists
        $stmt = $conn->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'users'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($tables) > 0) {
            echo "✓ Users table exists\n";
        } else {
            echo "✗ Users table does not exist\n";
        }
        
    } catch (PDOException $e) {
        echo "✗ Database connection error: " . $e->getMessage() . "\n";
    }
} else {
    echo "✗ DATABASE_URL environment variable not set\n";
}

$conn = null;
?>