<?php
// php/api/enrollment_requests.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// FIXED PATH: Go up one level then into config
include_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        getEnrollmentRequests($db);
        break;
    case 'POST':
        createEnrollmentRequest($db);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

function getEnrollmentRequests($db) {
    if (!isset($_GET['student_id'])) {
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }

    $studentId = $_GET['student_id'];

    try {
        // Get pending enrollment requests
        $query = "SELECT er.*, c.course_code, c.course_name, c.description, 
                         u.first_name as faculty_first_name, u.last_name as faculty_last_name
                  FROM enrollment_requests er
                  JOIN courses c ON er.course_id = c.id
                  JOIN users u ON c.faculty_intern_id = u.id
                  WHERE er.student_id = ?
                  ORDER BY er.requested_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$studentId]);
        
        $requests = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $requests[] = $row;
        }
        
        echo json_encode(['success' => true, 'enrollment_requests' => $requests]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function createEnrollmentRequest($db) {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $studentId = $data['student_id'] ?? '';
    $courseId = $data['course_id'] ?? '';
    
    if (empty($studentId) || empty($courseId)) {
        echo json_encode(['success' => false, 'message' => 'Student ID and Course ID are required']);
        return;
    }

    try {
        // Check if request already exists
        $checkQuery = "SELECT id FROM enrollment_requests WHERE student_id = ? AND course_id = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$studentId, $courseId]);
        
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Enrollment request already exists']);
            return;
        }

        // Check if already enrolled
        $enrollmentQuery = "SELECT id FROM course_enrollments WHERE student_id = ? AND course_id = ?";
        $enrollmentStmt = $db->prepare($enrollmentQuery);
        $enrollmentStmt->execute([$studentId, $courseId]);
        
        if ($enrollmentStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Already enrolled in this course']);
            return;
        }

        // Create enrollment request
        $insertQuery = "INSERT INTO enrollment_requests (student_id, course_id, status) VALUES (?, ?, 'pending')";
        $insertStmt = $db->prepare($insertQuery);
        
        if ($insertStmt->execute([$studentId, $courseId])) {
            echo json_encode(['success' => true, 'message' => 'Enrollment request submitted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to submit enrollment request']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>