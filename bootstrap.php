<?php

// Bootstrap file for the Mobility Inventory Management System

// Include Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Register our custom autoloader
use Mobility\Util\Autoloader;
Autoloader::register();

// Start session
session_start();

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

// Set default timezone
date_default_timezone_set('UTC');

// Define constants
define('APP_ROOT', __DIR__);
define('APP_ENV', $_ENV['APP_ENV'] ?? 'dev');