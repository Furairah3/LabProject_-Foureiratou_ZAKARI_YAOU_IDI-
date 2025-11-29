<?php
// php/api/sessions.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

include_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

switch($method) {
    case 'GET':
        if(isset($_GET['faculty_id'])) {
            getFacultySessions($db, $_GET['faculty_id']);
        } else if(isset($_GET['course_id'])) {
            getCourseSessions($db, $_GET['course_id']);
        } else {
            getActiveSessions($db);
        }
        break;
    case 'POST':
        createSession($db);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

function generateAttendanceCode($length = 6) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

function createSession($db) {
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Debug: Log received data
    error_log("Received data: " . print_r($data, true));
    
    $courseId = $data['course_id'] ?? '';
    $sessionDate = $data['session_date'] ?? '';
    $startTime = $data['start_time'] ?? '';
    $endTime = $data['end_time'] ?? '';
    $topic = $data['topic'] ?? '';
    $location = $data['location'] ?? '';
    $createdBy = $data['created_by'] ?? '';
    
    // Validation
    if (empty($courseId) || empty($sessionDate) || empty($startTime) || empty($endTime) || empty($createdBy)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Missing required fields',
            'debug' => [
                'course_id' => $courseId,
                'session_date' => $sessionDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'created_by' => $createdBy
            ]
        ]);
        return;
    }

    try {
        // First, check if class_sessions table exists and has data
        $testQuery = "SHOW TABLES LIKE 'class_sessions'";
        $testStmt = $db->query($testQuery);
        if (!$testStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'class_sessions table does not exist']);
            return;
        }

        // Check if faculty owns the course
        $checkCourseQuery = "SELECT faculty_intern_id FROM courses WHERE id = ?";
        $checkCourseStmt = $db->prepare($checkCourseQuery);
        $checkCourseStmt->execute([$courseId]);
        $course = $checkCourseStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$course) {
            echo json_encode(['success' => false, 'message' => 'Course not found']);
            return;
        }
        
        if ($course['faculty_intern_id'] != $createdBy) {
            echo json_encode(['success' => false, 'message' => 'You are not authorized to create sessions for this course']);
            return;
        }
        
        // Generate unique attendance code
        $attendanceCode = generateAttendanceCode();
        
        // Ensure code is unique
        $codeCheckQuery = "SELECT id FROM class_sessions WHERE attendance_code = ?";
        $codeCheckStmt = $db->prepare($codeCheckQuery);
        
        do {
            $attendanceCode = generateAttendanceCode();
            $codeCheckStmt->execute([$attendanceCode]);
        } while ($codeCheckStmt->fetch());
        
        // Insert new session
        $query = "INSERT INTO class_sessions (course_id, session_date, start_time, end_time, topic, location, attendance_code, created_by) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($query);
        
        if($stmt->execute([$courseId, $sessionDate, $startTime, $endTime, $topic, $location, $attendanceCode, $createdBy])) {
            $sessionId = $db->lastInsertId();
            
            error_log("Session created with ID: " . $sessionId);
            
            // Verify the session was actually created
            $verifyQuery = "SELECT * FROM class_sessions WHERE id = ?";
            $verifyStmt = $db->prepare($verifyQuery);
            $verifyStmt->execute([$sessionId]);
            $verifiedSession = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($verifiedSession) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Session created successfully',
                    'session_id' => $sessionId,
                    'attendance_code' => $attendanceCode,
                    'session' => $verifiedSession
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Session created but could not be verified',
                    'session_id' => $sessionId
                ]);
            }
        } else {
            $errorInfo = $stmt->errorInfo();
            echo json_encode([
                'success' => false, 
                'message' => 'Failed to create session',
                'error' => $errorInfo
            ]);
        }
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $e->getMessage(),
            'error_code' => $e->getCode()
        ]);
    }
}

function getFacultySessions($db, $facultyId) {
    try {
        $query = "SELECT cs.*, c.course_code, c.course_name
                  FROM class_sessions cs
                  JOIN courses c ON cs.course_id = c.id
                  WHERE cs.created_by = ?
                  ORDER BY cs.session_date DESC, cs.start_time DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$facultyId]);
        
        $sessions = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sessions[] = $row;
        }
        
        echo json_encode([
            'success' => true, 
            'sessions' => $sessions,
            'count' => count($sessions)
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getCourseSessions($db, $courseId) {
    try {
        $query = "SELECT cs.* FROM class_sessions cs WHERE cs.course_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$courseId]);
        
        $sessions = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sessions[] = $row;
        }
        
        echo json_encode(['success' => true, 'sessions' => $sessions]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getActiveSessions($db) {
    try {
        $query = "SELECT cs.*, c.course_code, c.course_name
                  FROM class_sessions cs
                  JOIN courses c ON cs.course_id = c.id
                  ORDER BY cs.session_date DESC, cs.start_time DESC
                  LIMIT 10";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $sessions = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sessions[] = $row;
        }
        
        echo json_encode(['success' => true, 'sessions' => $sessions]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>