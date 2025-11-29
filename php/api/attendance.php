<?php
// php/api/attendance.php
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
        if(isset($_GET['session_id'])) {
            getSessionAttendance($db, $_GET['session_id']);
        } else if(isset($_GET['course_id'])) {
            getCourseAttendance($db, $_GET['course_id']);
        } else if(isset($_GET['student_id'])) {
            getStudentAttendance($db, $_GET['student_id']);
        }
        break;
    case 'POST':
        markAttendance($db);
        break;
    case 'PUT':
        updateAttendance($db);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

function getSessionAttendance($db, $sessionId) {
    try {
        // Get session details
        $sessionQuery = "SELECT cs.*, c.course_code, c.course_name 
                        FROM class_sessions cs
                        JOIN courses c ON cs.course_id = c.id
                        WHERE cs.id = ? AND cs.is_active = TRUE";
        $sessionStmt = $db->prepare($sessionQuery);
        $sessionStmt->execute([$sessionId]);
        $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            echo json_encode(['success' => false, 'message' => 'Session not found']);
            return;
        }
        
        // Get attendance records for this session
        $attendanceQuery = "SELECT ar.*, 
                           u.id as student_id, u.first_name, u.last_name, u.user_id, u.email,
                           m.name as major_name
                           FROM attendance_records ar
                           JOIN users u ON ar.student_id = u.id
                           LEFT JOIN majors m ON u.major_id = m.id
                           WHERE ar.session_id = ?
                           ORDER BY u.first_name, u.last_name";
        
        $attendanceStmt = $db->prepare($attendanceQuery);
        $attendanceStmt->execute([$sessionId]);
        $attendance = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate attendance summary
        $totalStudents = count($attendance);
        $presentCount = 0;
        $absentCount = 0;
        $lateCount = 0;
        $excusedCount = 0;
        
        foreach ($attendance as $record) {
            switch ($record['status']) {
                case 'present':
                    $presentCount++;
                    break;
                case 'absent':
                    $absentCount++;
                    break;
                case 'late':
                    $lateCount++;
                    break;
                case 'excused':
                    $excusedCount++;
                    break;
            }
        }
        
        echo json_encode([
            'success' => true,
            'session' => $session,
            'attendance' => $attendance,
            'summary' => [
                'total_students' => $totalStudents,
                'present' => $presentCount,
                'absent' => $absentCount,
                'late' => $lateCount,
                'excused' => $excusedCount,
                'attendance_rate' => $totalStudents > 0 ? round(($presentCount + $lateCount) / $totalStudents * 100) : 0
            ]
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getCourseAttendance($db, $courseId) {
    try {
        // Get course details
        $courseQuery = "SELECT c.*, u.first_name as faculty_first_name, u.last_name as faculty_last_name
                       FROM courses c
                       JOIN users u ON c.faculty_intern_id = u.id
                       WHERE c.id = ? AND c.is_active = TRUE";
        $courseStmt = $db->prepare($courseQuery);
        $courseStmt->execute([$courseId]);
        $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$course) {
            echo json_encode(['success' => false, 'message' => 'Course not found']);
            return;
        }
        
        // Get all sessions for this course
        $sessionsQuery = "SELECT cs.*,
                         (SELECT COUNT(*) FROM attendance_records WHERE session_id = cs.id AND status IN ('present', 'late')) as present_count,
                         (SELECT COUNT(*) FROM attendance_records WHERE session_id = cs.id) as total_students
                         FROM class_sessions cs
                         WHERE cs.course_id = ? AND cs.is_active = TRUE
                         ORDER BY cs.session_date DESC";
        
        $sessionsStmt = $db->prepare($sessionsQuery);
        $sessionsStmt->execute([$courseId]);
        $sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get enrolled students with their overall attendance
        $studentsQuery = "SELECT u.id, u.first_name, u.last_name, u.user_id, u.email,
                                 m.name as major_name,
                                 (SELECT COUNT(*) FROM attendance_records ar 
                                  JOIN class_sessions cs ON ar.session_id = cs.id 
                                  WHERE ar.student_id = u.id AND cs.course_id = ? AND ar.status IN ('present', 'late')) as attended_sessions,
                                 (SELECT COUNT(*) FROM class_sessions WHERE course_id = ?) as total_sessions
                          FROM course_enrollments ce
                          JOIN users u ON ce.student_id = u.id
                          LEFT JOIN majors m ON u.major_id = m.id
                          WHERE ce.course_id = ? AND ce.status = 'active'
                          ORDER BY u.first_name, u.last_name";
        
        $studentsStmt = $db->prepare($studentsQuery);
        $studentsStmt->execute([$courseId, $courseId, $courseId]);
        $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate course attendance summary
        $totalSessions = count($sessions);
        $totalPossibleAttendance = 0;
        $totalActualAttendance = 0;
        
        foreach ($students as &$student) {
            $attendanceRate = $student['total_sessions'] > 0 ? 
                round(($student['attended_sessions'] / $student['total_sessions']) * 100) : 0;
            $student['attendance_rate'] = $attendanceRate;
            
            $totalPossibleAttendance += $student['total_sessions'];
            $totalActualAttendance += $student['attended_sessions'];
        }
        
        $overallAttendanceRate = $totalPossibleAttendance > 0 ? 
            round(($totalActualAttendance / $totalPossibleAttendance) * 100) : 0;
        
        echo json_encode([
            'success' => true,
            'course' => $course,
            'sessions' => $sessions,
            'students' => $students,
            'summary' => [
                'total_sessions' => $totalSessions,
                'total_students' => count($students),
                'overall_attendance_rate' => $overallAttendanceRate
            ]
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function markAttendance($db) {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $sessionId = $data['session_id'] ?? '';
    $studentId = $data['student_id'] ?? '';
    $status = $data['status'] ?? ''; // 'present', 'absent', 'late', 'excused'
    $markedBy = $data['marked_by'] ?? ''; // Faculty ID
    $notes = $data['notes'] ?? '';
    
    // Validation
    if (empty($sessionId) || empty($studentId) || empty($status) || empty($markedBy)) {
        echo json_encode(['success' => false, 'message' => 'Session ID, student ID, status, and marked_by are required']);
        return;
    }
    
    if (!in_array($status, ['present', 'absent', 'late', 'excused'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status. Must be: present, absent, late, or excused']);
        return;
    }

    try {
        // Check if faculty owns the session
        $checkQuery = "SELECT cs.created_by, c.course_name 
                      FROM class_sessions cs
                      JOIN courses c ON cs.course_id = c.id
                      WHERE cs.id = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$sessionId]);
        $session = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            echo json_encode(['success' => false, 'message' => 'Session not found']);
            return;
        }
        
        if ($session['created_by'] != $markedBy) {
            echo json_encode(['success' => false, 'message' => 'You are not authorized to mark attendance for this session']);
            return;
        }
        
        // Check if student is enrolled in the course
        $enrollmentQuery = "SELECT ce.id 
                           FROM course_enrollments ce
                           JOIN class_sessions cs ON ce.course_id = cs.course_id
                           WHERE ce.student_id = ? AND cs.id = ? AND ce.status = 'active'";
        $enrollmentStmt = $db->prepare($enrollmentQuery);
        $enrollmentStmt->execute([$studentId, $sessionId]);
        
        if (!$enrollmentStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Student is not enrolled in this course']);
            return;
        }
        
        // Check if attendance record already exists
        $existingQuery = "SELECT id FROM attendance_records WHERE session_id = ? AND student_id = ?";
        $existingStmt = $db->prepare($existingQuery);
        $existingStmt->execute([$sessionId, $studentId]);
        $existingRecord = $existingStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingRecord) {
            // Update existing record
            $updateQuery = "UPDATE attendance_records 
                           SET status = ?, marked_by = ?, marked_at = NOW(), notes = ?
                           WHERE session_id = ? AND student_id = ?";
            $updateStmt = $db->prepare($updateQuery);
            
            if ($updateStmt->execute([$status, $markedBy, $notes, $sessionId, $studentId])) {
                echo json_encode(['success' => true, 'message' => 'Attendance updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update attendance']);
            }
        } else {
            // Create new attendance record
            $insertQuery = "INSERT INTO attendance_records (session_id, student_id, status, marked_by, notes) 
                           VALUES (?, ?, ?, ?, ?)";
            $insertStmt = $db->prepare($insertQuery);
            
            if ($insertStmt->execute([$sessionId, $studentId, $status, $markedBy, $notes])) {
                echo json_encode(['success' => true, 'message' => 'Attendance marked successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to mark attendance']);
            }
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateAttendance($db) {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $attendanceId = $data['attendance_id'] ?? '';
    $status = $data['status'] ?? '';
    $markedBy = $data['marked_by'] ?? '';
    $notes = $data['notes'] ?? '';
    
    if (empty($attendanceId) || empty($status) || empty($markedBy)) {
        echo json_encode(['success' => false, 'message' => 'Attendance ID, status, and marked_by are required']);
        return;
    }
    
    if (!in_array($status, ['present', 'absent', 'late', 'excused'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        return;
    }

    try {
        // Check if faculty owns the session
        $checkQuery = "SELECT cs.created_by 
                      FROM attendance_records ar
                      JOIN class_sessions cs ON ar.session_id = cs.id
                      WHERE ar.id = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$attendanceId]);
        $attendance = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$attendance) {
            echo json_encode(['success' => false, 'message' => 'Attendance record not found']);
            return;
        }
        
        if ($attendance['created_by'] != $markedBy) {
            echo json_encode(['success' => false, 'message' => 'You are not authorized to update this attendance record']);
            return;
        }
        
        // Update attendance record
        $updateQuery = "UPDATE attendance_records 
                       SET status = ?, marked_by = ?, marked_at = NOW(), notes = ?
                       WHERE id = ?";
        $updateStmt = $db->prepare($updateQuery);
        
        if ($updateStmt->execute([$status, $markedBy, $notes, $attendanceId])) {
            echo json_encode(['success' => true, 'message' => 'Attendance updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update attendance']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getStudentAttendance($db, $studentId) {
    try {
        // Get student details
        $studentQuery = "SELECT u.*, m.name as major_name 
                        FROM users u 
                        LEFT JOIN majors m ON u.major_id = m.id 
                        WHERE u.id = ? AND u.role = 'student'";
        $studentStmt = $db->prepare($studentQuery);
        $studentStmt->execute([$studentId]);
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            return;
        }
        
        // Get student's enrolled courses with attendance summary
        $coursesQuery = "SELECT c.*,
                                (SELECT COUNT(*) FROM class_sessions WHERE course_id = c.id) as total_sessions,
                                (SELECT COUNT(*) FROM attendance_records ar 
                                 JOIN class_sessions cs ON ar.session_id = cs.id 
                                 WHERE ar.student_id = ? AND cs.course_id = c.id AND ar.status IN ('present', 'late')) as attended_sessions
                         FROM course_enrollments ce
                         JOIN courses c ON ce.course_id = c.id
                         WHERE ce.student_id = ? AND ce.status = 'active'";
        
        $coursesStmt = $db->prepare($coursesQuery);
        $coursesStmt->execute([$studentId, $studentId]);
        $courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate attendance rates for each course
        foreach ($courses as &$course) {
            $attendanceRate = $course['total_sessions'] > 0 ? 
                round(($course['attended_sessions'] / $course['total_sessions']) * 100) : 0;
            $course['attendance_rate'] = $attendanceRate;
        }
        
        // Get recent attendance records
        $recentQuery = "SELECT ar.*, cs.session_date, cs.topic, c.course_code, c.course_name
                       FROM attendance_records ar
                       JOIN class_sessions cs ON ar.session_id = cs.id
                       JOIN courses c ON cs.course_id = c.id
                       WHERE ar.student_id = ?
                       ORDER BY cs.session_date DESC, ar.marked_at DESC
                       LIMIT 10";
        
        $recentStmt = $db->prepare($recentQuery);
        $recentStmt->execute([$studentId]);
        $recentAttendance = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'student' => $student,
            'courses' => $courses,
            'recent_attendance' => $recentAttendance
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>