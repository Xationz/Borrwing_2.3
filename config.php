<?php
$dbname = 'equipment_borrowing';
$username = 'root';

// Common local development connection attempts
$connection_configs = [
    // 1. MAMP with port 8889 and password 'root'
    ['host' => '127.0.0.1', 'port' => '8889', 'password' => 'root'],
    ['host' => 'localhost', 'port' => '8889', 'password' => 'root'],
    // 2. Standard MySQL / XAMPP with port 3306 and empty password
    ['host' => '127.0.0.1', 'port' => '3306', 'password' => ''],
    ['host' => 'localhost', 'port' => '3306', 'password' => ''],
    // 3. MySQL with port 3306 and password 'root'
    ['host' => '127.0.0.1', 'port' => '3306', 'password' => 'root'],
    ['host' => 'localhost', 'port' => '3306', 'password' => 'root'],
];

$pdo = null;
$error_messages = [];

// Google Apps Script Web App endpoint for borrowing sync.
// Replace the empty string with your deployed Web App URL:
// https://script.google.com/macros/s/DEPLOYMENT_ID/exec
if (!defined('GOOGLE_APPS_SCRIPT_WEB_APP_URL')) {
    define('GOOGLE_APPS_SCRIPT_WEB_APP_URL', 'https://script.google.com/macros/s/AKfycbzi6LzIYyY7jhD8ybYz9XNL9fmc-uQ68C7p4j-TRYMAwZOP-JAb4fk_rOTbmW-zHEgrDA/exec');
}

foreach ($connection_configs as $config) {
    try {
        // Connect to MySQL server without dbname first to ensure database exists or can be created
        $dsn_no_db = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
        $temp_pdo = new PDO($dsn_no_db, $username, $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2 // 2 seconds connection timeout
        ]);
        
        // Create database if not exists
        $temp_pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;");
        
        // Reconnect with dbname
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$dbname};charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 2,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        
        // Check if database is empty by checking if table 'users' exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() == 0) {
            // Database is empty, auto-import SQL dump
            $sql_path = __DIR__ . '/Database/127_0_0_1 (3).sql';
            if (file_exists($sql_path)) {
                $sql = file_get_contents($sql_path);
                try {
                    $pdo->exec($sql);
                } catch (PDOException $qe) {
                    // Ignore import errors for safety if partially imported
                }
            }
        }
        
        break;
    } catch (PDOException $e) {
        $error_messages[] = "Host {$config['host']}:{$config['port']} (password: '{$config['password']}') - " . $e->getMessage();
    }
}

if (!$pdo) {
    die("Database connection and setup failed. Tried multiple configurations:<br><br>" . implode("<br>", $error_messages));
}
?>
