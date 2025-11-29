<?php
// php/api/student_dashboard_debug.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// FIXED PATH: Go up one level then into config
include_once __DIR__ . '/../config/database.php';

// Simple test without database first
if (!isset($_GET['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required', 'debug' => 'No student_id parameter']);
    exit;
}

$studentId = $_GET['student_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Database connection successful in dashboard',
        'student_id' => $studentId,
        'debug' => 'All systems working'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error in dashboard',
        'error' => $e->getMessage(),
        'student_id' => $studentId
    ]);
}
?>