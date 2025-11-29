<?php
// php/api/mark_attendance.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// FIXED PATH: Go up one level then into config
include_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    markAttendance($db);
} else {
    echo json_encode(['success' => false, 'message' => 'Only POST method allowed']);
}

function markAttendance($db) {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $studentId = $data['student_id'] ?? '';
    $attendanceCode = $data['attendance_code'] ?? '';
    
    if (empty($studentId) || empty($attendanceCode)) {
        echo json_encode(['success' => false, 'message' => 'Student ID and Attendance Code are required']);
        return;
    }

    try {
        // Find active session with the provided code
        $sessionQuery = "SELECT cs.*, c.course_name 
                         FROM class_sessions cs
                         JOIN courses c ON cs.course_id = c.id
                         WHERE cs.attendance_code = ? 
                         AND cs.is_active = TRUE 
                         AND cs.session_date = CURDATE()";
        
        $sessionStmt = $db->prepare($sessionQuery);
        $sessionStmt->execute([$attendanceCode]);
        $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            echo json_encode(['success' => false, 'message' => 'Invalid attendance code or no active session found']);
            return;
        }

        // Check if student is enrolled in the course
        $enrollmentQuery = "SELECT id FROM course_enrollments 
                           WHERE student_id = ? AND course_id = ? AND status = 'active'";
        $enrollmentStmt = $db->prepare($enrollmentQuery);
        $enrollmentStmt->execute([$studentId, $session['course_id']]);
        
        if (!$enrollmentStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'You are not enrolled in this course']);
            return;
        }

        // Check if attendance already marked
        $existingQuery = "SELECT id FROM attendance_records 
                         WHERE session_id = ? AND student_id = ?";
        $existingStmt = $db->prepare($existingQuery);
        $existingStmt->execute([$session['id'], $studentId]);
        
        if ($existingStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Attendance already marked for this session']);
            return;
        }

        // Check if session is within time (mark as late if after start time)
        $currentTime = date('H:i:s');
        $sessionStart = $session['start_time'];
        $status = ($currentTime > $sessionStart) ? 'late' : 'present';

        // Insert attendance record
        $insertQuery = "INSERT INTO attendance_records 
                       (session_id, student_id, status, marked_with_code, marked_at) 
                       VALUES (?, ?, ?, ?, NOW())";
        
        $insertStmt = $db->prepare($insertQuery);
        
        if ($insertStmt->execute([$session['id'], $studentId, $status, $attendanceCode])) {
            echo json_encode([
                'success' => true, 
                'message' => 'Attendance marked successfully as ' . $status,
                'attendance' => [
                    'session_id' => $session['id'],
                    'course_name' => $session['course_name'],
                    'session_date' => $session['session_date'],
                    'topic' => $session['topic'],
                    'status' => $status,
                    'marked_at' => date('Y-m-d H:i:s')
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to mark attendance']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>