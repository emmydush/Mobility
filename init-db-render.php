<?php
// Database initialization script for Render deployment

echo "Initializing database for Render deployment...\n";

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
} else {
    die("DATABASE_URL environment variable not set\n");
}

try {
    // Create PostgreSQL connection using PDO
    $dsn = "pgsql:host={$host};port={$port};dbname={$database};";
    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    echo "Connected to database successfully\n";
    
    // Check if tables already exist
    $stmt = $conn->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) > 0) {
        echo "Database already initialized with " . count($tables) . " tables. Skipping initialization.\n";
        exit(0);
    }
    
    echo "Creating database tables...\n";
    
    // Read and execute the initialization script
    $init_script = file_get_contents(__DIR__ . '/init-scripts/init-db.sql');
    
    // Split the script into individual statements
    $statements = explode(';', $init_script);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $conn->exec($statement);
                echo "Executed statement: " . substr($statement, 0, 50) . "...\n";
            } catch (PDOException $e) {
                echo "Error executing statement: " . $e->getMessage() . "\n";
                // Continue with other statements
            }
        }
    }
    
    echo "Database initialization completed successfully\n";
    
} catch (PDOException $e) {
    die("Database connection error: " . $e->getMessage() . "\n");
}

$conn = null;
?>