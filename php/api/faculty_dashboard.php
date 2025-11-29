<?php
// php/api/faculty_dashboard.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

include_once __DIR__ . '/../config/database.php';

// Check if faculty ID is provided
if (!isset($_GET['faculty_id'])) {
    echo json_encode(['success' => false, 'message' => 'Faculty ID is required']);
    exit;
}

$facultyId = $_GET['faculty_id'];
$database = new Database();
$db = $database->getConnection();

try {
    // Get faculty basic info
    $facultyQuery = "SELECT u.*, d.name as department_name 
                     FROM users u 
                     LEFT JOIN departments d ON u.department_id = d.id 
                     WHERE u.id = ? AND u.role IN ('faculty', 'intern')";
    
    $facultyStmt = $db->prepare($facultyQuery);
    $facultyStmt->execute([$facultyId]);
    $faculty = $facultyStmt->fetch(PDO::FETCH_ASSOC);

    if (!$faculty) {
        echo json_encode(['success' => false, 'message' => 'Faculty member not found']);
        exit;
    }

    // Get courses count
    $coursesQuery = "SELECT COUNT(*) as courses_count 
                     FROM courses 
                     WHERE faculty_intern_id = ? AND is_active = TRUE";
    $coursesStmt = $db->prepare($coursesQuery);
    $coursesStmt->execute([$facultyId]);
    $coursesCount = $coursesStmt->fetch(PDO::FETCH_ASSOC)['courses_count'];

    // Get total students enrolled
    $studentsQuery = "SELECT COUNT(DISTINCT ce.student_id) as students_count 
                      FROM course_enrollments ce 
                      JOIN courses c ON ce.course_id = c.id 
                      WHERE c.faculty_intern_id = ? AND ce.status = 'active'";
    $studentsStmt = $db->prepare($studentsQuery);
    $studentsStmt->execute([$facultyId]);
    $studentsCount = $studentsStmt->fetch(PDO::FETCH_ASSOC)['students_count'];

    // Get pending enrollment requests
    $pendingQuery = "SELECT COUNT(*) as pending_requests 
                     FROM enrollment_requests er 
                     JOIN courses c ON er.course_id = c.id 
                     WHERE c.faculty_intern_id = ? AND er.status = 'pending'";
    $pendingStmt = $db->prepare($pendingQuery);
    $pendingStmt->execute([$facultyId]);
    $pendingRequests = $pendingStmt->fetch(PDO::FETCH_ASSOC)['pending_requests'];

    // Get today's sessions
    $todaySessionsQuery = "SELECT COUNT(*) as today_sessions 
                           FROM class_sessions 
                           WHERE created_by = ? AND session_date = CURDATE() AND is_active = TRUE";
    $todaySessionsStmt = $db->prepare($todaySessionsQuery);
    $todaySessionsStmt->execute([$facultyId]);
    $todaySessions = $todaySessionsStmt->fetch(PDO::FETCH_ASSOC)['today_sessions'];

    // Get recent courses with basic info
    $recentCoursesQuery = "SELECT c.*, 
                           (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id AND status = 'active') as enrolled_students,
                           (SELECT COUNT(*) FROM class_sessions WHERE course_id = c.id AND session_date = CURDATE()) as today_sessions
                           FROM courses c 
                           WHERE c.faculty_intern_id = ? AND c.is_active = TRUE 
                           ORDER BY c.created_at DESC 
                           LIMIT 5";
    
    $recentCoursesStmt = $db->prepare($recentCoursesQuery);
    $recentCoursesStmt->execute([$facultyId]);
    $recentCourses = $recentCoursesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get pending enrollment requests with details
    $enrollmentRequestsQuery = "SELECT er.*, 
                               c.course_code, c.course_name,
                               u.first_name as student_first_name, u.last_name as student_last_name,
                               u.email as student_email
                               FROM enrollment_requests er
                               JOIN courses c ON er.course_id = c.id
                               JOIN users u ON er.student_id = u.id
                               WHERE c.faculty_intern_id = ? AND er.status = 'pending'
                               ORDER BY er.requested_at DESC 
                               LIMIT 5";
    
    $enrollmentRequestsStmt = $db->prepare($enrollmentRequestsQuery);
    $enrollmentRequestsStmt->execute([$facultyId]);
    $enrollmentRequests = $enrollmentRequestsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get today's sessions with details
    $todayDetailedQuery = "SELECT cs.*, c.course_name, c.course_code,
                          (SELECT COUNT(*) FROM attendance_records WHERE session_id = cs.id) as attendance_marked
                          FROM class_sessions cs
                          JOIN courses c ON cs.course_id = c.id
                          WHERE cs.created_by = ? AND cs.session_date = CURDATE() AND cs.is_active = TRUE
                          ORDER BY cs.start_time ASC";
    
    $todayDetailedStmt = $db->prepare($todayDetailedQuery);
    $todayDetailedStmt->execute([$facultyId]);
    $todayDetailedSessions = $todayDetailedStmt->fetchAll(PDO::FETCH_ASSOC);

    // Return faculty dashboard data
    echo json_encode([
        'success' => true,
        'dashboard' => [
            'faculty' => [
                'id' => $faculty['id'],
                'name' => $faculty['first_name'] . ' ' . $faculty['last_name'],
                'email' => $faculty['email'],
                'user_id' => $faculty['user_id'],
                'department' => $faculty['department_name'],
                'designation' => $faculty['designation'],
                'role' => $faculty['role']
            ],
            'stats' => [
                'total_courses' => $coursesCount,
                'total_students' => $studentsCount,
                'pending_requests' => $pendingRequests,
                'today_sessions' => $todaySessions
            ],
            'recent_courses' => $recentCourses,
            'pending_enrollments' => $enrollmentRequests,
            'today_sessions' => $todayDetailedSessions
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>