<?php
// php/test_school_db.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Display all errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo json_encode(['step' => 'Starting test...']);

// Test database connection
include_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo json_encode(['step' => 'Database connection successful']);
    
    // Test basic query
    $stmt = $db->query("SELECT 1 as test_value");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['step' => 'Basic query executed', 'test_value' => $result['test_value']]);
    
    // Try to get table count
    try {
        $tablesStmt = $db->query("SHOW TABLES");
        $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode([
            'success' => true, 
            'message' => 'School database connection successful!',
            'tables_count' => count($tables),
            'tables' => $tables
        ]);
    } catch (Exception $e) {
        // Alternative for SQL Server
        $tablesStmt = $db->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'");
        $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode([
            'success' => true, 
            'message' => 'School database connection successful! (SQL Server)',
            'tables_count' => count($tables),
            'tables' => $tables
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database connection failed',
        'error' => $e->getMessage(),
        'error_code' => $e->getCode(),
        'debug_info' => [
            'pdo_drivers' => PDO::getAvailableDrivers(),
            'host' => 'localhost',
            'db_name' => 'webtech_2025A_foureiratou_idi',
            'username' => 'foureiratou.idi'
        ]
    ]);
}
?>