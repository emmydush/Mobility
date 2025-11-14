<?php
// Allow cross-origin requests
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
    $database = "emmy_db";
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

// Add balance column if it doesn't exist
try {
    // Check if balance column exists
    $check_balance_column = "SELECT column_name FROM information_schema.columns WHERE table_name = 'customers' AND column_name = 'balance'";
    $result = $conn->query($check_balance_column);
    
    if ($result->rowCount() == 0) {
        $add_balance_column = "ALTER TABLE customers ADD COLUMN balance DECIMAL(10,2) DEFAULT 0";
        $conn->exec($add_balance_column);
        echo json_encode([
            "success" => true,
            "message" => "Balance column added successfully to customers table"
        ]);
    } else {
        echo json_encode([
            "success" => true,
            "message" => "Balance column already exists in customers table"
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error checking/adding balance column: " . $e->getMessage()
    ]);
}

$conn = null;
?>