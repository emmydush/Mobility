<?php
// Health check script for the Mobility Inventory Management System

header('Content-Type: application/json');

$response = [
    'status' => 'ok',
    'timestamp' => date('c'),
    'services' => []
];

// Check PHP
$response['services']['php'] = [
    'status' => 'ok',
    'version' => phpversion()
];

// Check required extensions
$required_extensions = ['pdo', 'pdo_pgsql', 'pgsql', 'mbstring', 'gd'];
$missing_extensions = [];

foreach ($required_extensions as $extension) {
    if (!extension_loaded($extension)) {
        $missing_extensions[] = $extension;
    }
}

if (empty($missing_extensions)) {
    $response['services']['extensions'] = [
        'status' => 'ok',
        'details' => 'All required extensions are loaded'
    ];
} else {
    $response['services']['extensions'] = [
        'status' => 'error',
        'details' => 'Missing extensions: ' . implode(', ', $missing_extensions)
    ];
    $response['status'] = 'error';
}

// Check database connection
try {
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
    
    // Test a simple query
    $stmt = $conn->query("SELECT 1 as test");
    $result = $stmt->fetch();
    
    $response['services']['database'] = [
        'status' => 'ok',
        'details' => 'Connected to database: ' . $database
    ];
    
} catch (Exception $e) {
    $response['services']['database'] = [
        'status' => 'error',
        'details' => 'Database connection failed: ' . $e->getMessage()
    ];
    $response['status'] = 'error';
}

// Check file permissions
$writable_dirs = ['profile_pictures', 'product_images'];
$permission_issues = [];

foreach ($writable_dirs as $dir) {
    if (!is_writable($dir)) {
        $permission_issues[] = $dir;
    }
}

if (empty($permission_issues)) {
    $response['services']['filesystem'] = [
        'status' => 'ok',
        'details' => 'All required directories are writable'
    ];
} else {
    $response['services']['filesystem'] = [
        'status' => 'warning',
        'details' => 'Directories not writable: ' . implode(', ', $permission_issues)
    ];
    // Don't set overall status to error for permission issues
}

http_response_code($response['status'] === 'ok' ? 200 : 500);
echo json_encode($response, JSON_PRETTY_PRINT);
?>