<?php
/**
 * CLI Database Connection
 * Minimal database connection for command-line scripts
 * Avoids HTTP-specific error handling
 */

// Load environment
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Determine environment
function isRemote() {
    return isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], '127.0.0.1') === false;
}

// Database credentials
if (isRemote()) {
    $db_hostname = $_ENV['REMOTE_DB_HOSTNAME'];
    $db_database = $_ENV['REMOTE_DB_DATABASE'];
    $db_username = $_ENV['REMOTE_DB_USERNAME'];
    $db_password = $_ENV['REMOTE_DB_PASSWORD'];
} else {
    $db_hostname = $_ENV['DB_HOSTNAME'] ?? 'localhost';
    $db_database = $_ENV['DB_DATABASE'] ?? 'flasherpsystem';
    $db_username = $_ENV['DB_USERNAME'] ?? 'root';
    $db_password = $_ENV['DB_PASSWORD'] ?? 'hai@30';
}

// Create connection
$conn = new \App\Core\DynamicPrefixMysqli($db_hostname, $db_username, $db_password, $db_database);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error . "\n");
}

// Set global table prefix
$tbl_prefix = $_ENV['DB_PREFIX'] ?? 'erp_';
$GLOBALS['TBL']['PREFIX'] = $tbl_prefix;

// Set charset
$conn->set_charset("utf8mb4");

// Set timezone
$timezone = $_ENV['DB_TIMEZONE'] ?? '+03:00';
$conn->query("SET time_zone = '$timezone'");
