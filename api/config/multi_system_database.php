<?php
// Multi-System Database Configuration

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
    // Parse the DATABASE_URL for multi-system database
    $url = parse_url($_ENV['DATABASE_URL']);
    $host = $url['host'];
    $username = $url['user'];
    $password = $url['pass'];
    // Use multi-system database name
    $database = "mobility_multi_db";
    $port = isset($url['port']) ? $url['port'] : 5432; // Default PostgreSQL port
} else {
    // Fallback to default configuration
    $host = "localhost";
    $username = "mobility_db_user";
    $password = "243j1kW3g4rlkksNDdMehLHpplQVRJTa";
    $database = "mobility_multi_db";
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

// Function to get the current user ID from session
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}
?>