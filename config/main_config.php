<?php

$lines = file(__DIR__ . '/.env');
foreach ($lines as $line) {
    putenv(trim($line));
}

/**
 * BitChat Unified Configuration
 * 
 * This file consolidates all configuration settings for the BitChat project
 */

// Database credentials - unified approach
define('DB_HOST', getenv('DB_HOST'));
define('DB_NAME', getenv('DB_NAME'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));


// Session configuration
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Create MySQLi connection (for general app functionality)
function getMySQLiConnection() {
    static $mysqli_conn = null;
    
    if ($mysqli_conn === null) {
        try {
            $mysqli_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($mysqli_conn->connect_error) {
                throw new Exception("MySQLi Connection failed: " . $mysqli_conn->connect_error);
            }
            
            $mysqli_conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            die("Database connection error. Please check configuration.");
        }
    }
    
    return $mysqli_conn;
}

// Create PDO connection (for API functionality)
function getPDOConnection() {
    static $pdo_conn = null;
    
    if ($pdo_conn === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $pdo_conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            return null;
        }
    }
    
    return $pdo_conn;
}

// Set response headers for JSON
function setJSONHeaders() {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// Send JSON response
function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

// Send error response
function sendError($message, $statusCode = 400) {
    sendJSON(['success' => false, 'error' => $message], $statusCode);
}

// Send success response
function sendSuccess($data = []) {
    sendJSON(array_merge(['success' => true], $data));
}

// Initialize database connection
$conn = getMySQLiConnection();
$pdo = getPDOConnection();

// Function for API compatibility
function getDBConnection() {
    return getPDOConnection();
}

?>