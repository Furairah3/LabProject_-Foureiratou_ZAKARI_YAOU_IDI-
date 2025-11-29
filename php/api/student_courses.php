<?php
// php/api/student_courses.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// FIXED PATH: Go up one level then into config
include_once __DIR__ . '/../config/database.php';

if (!isset($_GET['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit;
}

$studentId = $_GET['student_id'];
$database = new Database();
$db = $database->getConnection();

try {
    // Get enrolled courses
    $query = "SELECT c.*, d.name as department_name, 
                     u.first_name as faculty_first_name, u.last_name as faculty_last_name,
                     (SELECT COUNT(*) FROM class_sessions WHERE course_id = c.id AND session_date <= CURDATE()) as total_sessions,
                     (SELECT COUNT(*) FROM attendance_records ar 
                      JOIN class_sessions cs ON ar.session_id = cs.id 
                      WHERE cs.course_id = c.id AND ar.student_id = ? AND ar.status IN ('present', 'late')) as attended_sessions
              FROM courses c 
              JOIN course_enrollments ce ON c.id = ce.course_id 
              LEFT JOIN departments d ON c.department_id = d.id 
              LEFT JOIN users u ON c.faculty_intern_id = u.id 
              WHERE ce.student_id = ? AND ce.status = 'active' 
              AND c.is_active = TRUE";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$studentId, $studentId]);
    
    $courses = [];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $totalSessions = $row['total_sessions'];
        $attendedSessions = $row['attended_sessions'];
        $attendanceRate = $totalSessions > 0 ? round(($attendedSessions / $totalSessions) * 100) : 0;
        
        $courses[] = [
            'id' => $row['id'],
            'course_code' => $row['course_code'],
            'course_name' => $row['course_name'],
            'description' => $row['description'],
            'credits' => $row['credits'],
            'department_name' => $row['department_name'],
            'faculty_name' => $row['faculty_first_name'] . ' ' . $row['faculty_last_name'],
            'attendance_rate' => $attendanceRate,
            'attended_sessions' => $attendedSessions,
            'total_sessions' => $totalSessions
        ];
    }
    
    echo json_encode(['success' => true, 'courses' => $courses]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>