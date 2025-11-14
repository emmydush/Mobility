<?php
// Test Docker setup

echo "<h1>Docker Setup Test</h1>";

// Test PHP version
echo "<p>PHP Version: " . phpversion() . "</p>";

// Test PostgreSQL extension
if (extension_loaded('pdo_pgsql')) {
    echo "<p style='color: green;'>PDO PostgreSQL extension is loaded</p>";
} else {
    echo "<p style='color: red;'>PDO PostgreSQL extension is NOT loaded</p>";
}

if (extension_loaded('pgsql')) {
    echo "<p style='color: green;'>PostgreSQL extension is loaded</p>";
} else {
    echo "<p style='color: red;'>PostgreSQL extension is NOT loaded</p>";
}

// Test database connection
echo "<h2>Database Connection Test</h2>";

try {
    // Parse the DATABASE_URL from environment
    $databaseUrl = getenv('DATABASE_URL') ?: 'postgresql://mobility_db_user:243j1kW3g4rlkksNDdMehLHpplQVRJTa@db:5432/mobility_db';
    
    $url = parse_url($databaseUrl);
    $host = $url['host'];
    $username = $url['user'];
    $password = $url['pass'];
    $database = substr($url['path'], 1);
    $port = isset($url['port']) ? $url['port'] : 5432;
    
    $dsn = "pgsql:host={$host};port={$port};dbname={$database};";
    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "<p style='color: green;'>Database connection successful!</p>";
    echo "<p>Host: {$host}</p>";
    echo "<p>Database: {$database}</p>";
    echo "<p>Username: {$username}</p>";
    
    // Test a simple query
    $stmt = $conn->query("SELECT version()");
    $version = $stmt->fetch();
    echo "<p>PostgreSQL Version: " . $version['version'] . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Database connection failed: " . $e->getMessage() . "</p>";
}

echo "<h2>Environment Variables</h2>";
echo "<pre>";
print_r($_ENV);
echo "</pre>";

echo "<h2>Loaded Extensions</h2>";
echo "<pre>";
print_r(get_loaded_extensions());
echo "</pre>";
?>