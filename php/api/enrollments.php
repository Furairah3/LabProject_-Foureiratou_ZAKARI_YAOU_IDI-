<?php
// php/api/enrollments.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

include_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        if(isset($_GET['faculty_id'])) {
            getPendingEnrollments($db, $_GET['faculty_id']);
        } else if(isset($_GET['course_id'])) {
            getCourseEnrollments($db, $_GET['course_id']);
        }
        break;
    case 'POST':
        processEnrollment($db);
        break;
    case 'PUT':
        updateEnrollmentStatus($db);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

function getPendingEnrollments($db, $facultyId) {
    try {
        $query = "SELECT er.*, 
                         c.course_code, c.course_name,
                         u.first_name as student_first_name, u.last_name as student_last_name,
                         u.email as student_email, u.user_id as student_user_id,
                         u.major_id, m.name as major_name
                  FROM enrollment_requests er
                  JOIN courses c ON er.course_id = c.id
                  JOIN users u ON er.student_id = u.id
                  LEFT JOIN majors m ON u.major_id = m.id
                  WHERE c.faculty_intern_id = ? AND er.status = 'pending'
                  ORDER BY er.requested_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$facultyId]);
        
        $enrollments = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $enrollments[] = $row;
        }
        
        echo json_encode(['success' => true, 'pending_enrollments' => $enrollments]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getCourseEnrollments($db, $courseId) {
    try {
        $query = "SELECT ce.*,
                         u.first_name, u.last_name, u.email, u.user_id,
                         u.major_id, m.name as major_name
                  FROM course_enrollments ce
                  JOIN users u ON ce.student_id = u.id
                  LEFT JOIN majors m ON u.major_id = m.id
                  WHERE ce.course_id = ? AND ce.status = 'active'
                  ORDER BY ce.enrolled_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$courseId]);
        
        $enrollments = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $enrollments[] = $row;
        }
        
        echo json_encode(['success' => true, 'enrollments' => $enrollments]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function processEnrollment($db) {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $requestId = $data['request_id'] ?? '';
    $action = $data['action'] ?? ''; // 'approve' or 'reject'
    $facultyId = $data['faculty_id'] ?? '';
    $notes = $data['notes'] ?? '';
    
    if (empty($requestId) || empty($action) || empty($facultyId)) {
        echo json_encode(['success' => false, 'message' => 'Request ID, action, and faculty ID are required']);
        return;
    }

    if (!in_array($action, ['approve', 'reject'])) {
        echo json_encode(['success' => false, 'message' => 'Action must be either "approve" or "reject"']);
        return;
    }

    try {
        // Start transaction
        $db->beginTransaction();
        
        // Get enrollment request details
        $getRequestQuery = "SELECT er.*, c.faculty_intern_id 
                           FROM enrollment_requests er
                           JOIN courses c ON er.course_id = c.id
                           WHERE er.id = ? AND er.status = 'pending'";
        $getRequestStmt = $db->prepare($getRequestQuery);
        $getRequestStmt->execute([$requestId]);
        $request = $getRequestStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Enrollment request not found or already processed']);
            return;
        }
        
        // Check if faculty owns the course
        if ($request['faculty_intern_id'] != $facultyId) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'You are not authorized to process this enrollment']);
            return;
        }
        
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        
        // Update enrollment request status
        $updateRequestQuery = "UPDATE enrollment_requests 
                              SET status = ?, reviewed_by = ?, reviewed_at = NOW(), notes = ?
                              WHERE id = ?";
        $updateRequestStmt = $db->prepare($updateRequestQuery);
        $updateRequestStmt->execute([$newStatus, $facultyId, $notes, $requestId]);
        
        if ($action === 'approve') {
            // Check if student is already enrolled
            $checkEnrollmentQuery = "SELECT id FROM course_enrollments 
                                    WHERE student_id = ? AND course_id = ? AND status = 'active'";
            $checkEnrollmentStmt = $db->prepare($checkEnrollmentQuery);
            $checkEnrollmentStmt->execute([$request['student_id'], $request['course_id']]);
            
            if (!$checkEnrollmentStmt->fetch()) {
                // Add to course enrollments
                $enrollQuery = "INSERT INTO course_enrollments (student_id, course_id, enrolled_by) 
                               VALUES (?, ?, ?)";
                $enrollStmt = $db->prepare($enrollQuery);
                $enrollStmt->execute([$request['student_id'], $request['course_id'], $facultyId]);
            }
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Enrollment request ' . $action . 'd successfully'
        ]);
        
    } catch (PDOException $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateEnrollmentStatus($db) {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $enrollmentId = $data['enrollment_id'] ?? '';
    $status = $data['status'] ?? ''; // 'active', 'completed', 'dropped'
    $facultyId = $data['faculty_id'] ?? '';
    
    if (empty($enrollmentId) || empty($status) || empty($facultyId)) {
        echo json_encode(['success' => false, 'message' => 'Enrollment ID, status, and faculty ID are required']);
        return;
    }

    if (!in_array($status, ['active', 'completed', 'dropped'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        return;
    }

    try {
        // Check if faculty owns the course
        $checkQuery = "SELECT c.faculty_intern_id 
                      FROM course_enrollments ce
                      JOIN courses c ON ce.course_id = c.id
                      WHERE ce.id = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$enrollmentId]);
        $enrollment = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$enrollment) {
            echo json_encode(['success' => false, 'message' => 'Enrollment not found']);
            return;
        }
        
        if ($enrollment['faculty_intern_id'] != $facultyId) {
            echo json_encode(['success' => false, 'message' => 'You are not authorized to update this enrollment']);
            return;
        }
        
        // Update enrollment status
        $updateQuery = "UPDATE course_enrollments SET status = ? WHERE id = ?";
        $updateStmt = $db->prepare($updateQuery);
        
        if($updateStmt->execute([$status, $enrollmentId])) {
            echo json_encode(['success' => true, 'message' => 'Enrollment status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update enrollment status']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>