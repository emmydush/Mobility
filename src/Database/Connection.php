<?php

namespace Mobility\Database;

use PDO;
use PDOException;

class Connection
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        $this->connect();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect()
    {
        // Load environment variables
        $env_file = __DIR__ . '/../../../.env';
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
            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new \Exception("Database connection error: " . $e->getMessage());
        }
    }

    public function getConnection()
    {
        return $this->connection;
    }
}