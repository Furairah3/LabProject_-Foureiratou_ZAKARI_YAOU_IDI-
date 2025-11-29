<?php
// php/api/available_courses.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// FIXED PATH: Go up one level then into config
include_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Get all active courses that students can enroll in
    $query = "SELECT c.*, d.name as department_name, 
                     u.first_name as faculty_first_name, u.last_name as faculty_last_name,
                     (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id AND status = 'active') as enrolled_students
              FROM courses c 
              LEFT JOIN departments d ON c.department_id = d.id 
              LEFT JOIN users u ON c.faculty_intern_id = u.id 
              WHERE c.is_active = TRUE 
              ORDER BY c.course_code";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $courses = [];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $courses[] = $row;
    }
    
    echo json_encode(['success' => true, 'courses' => $courses]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>