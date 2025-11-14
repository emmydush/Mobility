<?php
// Render entry point

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

// If PORT is set (Render specific), use it
$port = getenv('PORT') ?: 8000;

// Start the PHP built-in server
echo "Starting server on port {$port}...\n";
exec("php -S 0.0.0.0:{$port} -t " . __DIR__);
?>