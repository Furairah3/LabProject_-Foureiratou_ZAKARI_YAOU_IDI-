<?php
// php/api/intern_dashboard.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

include_once __DIR__ . '/../config/database.php';

// Check if intern ID is provided
if (!isset($_GET['intern_id'])) {
    echo json_encode(['success' => false, 'message' => 'Intern ID is required']);
    exit;
}

$internId = $_GET['intern_id'];
$database = new Database();
$db = $database->getConnection();

try {
    // Get intern basic info
    $internQuery = "SELECT u.*, d.name as department_name, 
                     u.assigned_department as assigned_dept_id,
                     ad.name as assigned_department_name
                     FROM users u 
                     LEFT JOIN departments d ON u.department_id = d.id 
                     LEFT JOIN departments ad ON u.assigned_department = ad.id 
                     WHERE u.id = ? AND u.role = 'intern'";
    
    $internStmt = $db->prepare($internQuery);
    $internStmt->execute([$internId]);
    $intern = $internStmt->fetch(PDO::FETCH_ASSOC);

    if (!$intern) {
        echo json_encode(['success' => false, 'message' => 'Intern not found']);
        exit;
    }

    // Get assigned courses count (courses in their assigned department)
    $coursesQuery = "SELECT COUNT(*) as courses_count 
                     FROM courses c 
                     WHERE c.department_id = ? AND c.is_active = TRUE";
    $coursesStmt = $db->prepare($coursesQuery);
    $coursesStmt->execute([$intern['assigned_department']]);
    $coursesCount = $coursesStmt->fetch(PDO::FETCH_ASSOC)['courses_count'];

    // Get total students in assigned department
    $studentsQuery = "SELECT COUNT(DISTINCT ce.student_id) as students_count 
                      FROM course_enrollments ce 
                      JOIN courses c ON ce.course_id = c.id 
                      WHERE c.department_id = ? AND ce.status = 'active'";
    $studentsStmt = $db->prepare($studentsQuery);
    $studentsStmt->execute([$intern['assigned_department']]);
    $studentsCount = $studentsStmt->fetch(PDO::FETCH_ASSOC)['students_count'];

    // Get pending enrollment requests in assigned department
    $pendingQuery = "SELECT COUNT(*) as pending_requests 
                     FROM enrollment_requests er 
                     JOIN courses c ON er.course_id = c.id 
                     WHERE c.department_id = ? AND er.status = 'pending'";
    $pendingStmt = $db->prepare($pendingQuery);
    $pendingStmt->execute([$intern['assigned_department']]);
    $pendingRequests = $pendingStmt->fetch(PDO::FETCH_ASSOC)['pending_requests'];

    // Get today's sessions in assigned department
    $todaySessionsQuery = "SELECT COUNT(*) as today_sessions 
                           FROM class_sessions cs 
                           JOIN courses c ON cs.course_id = c.id 
                           WHERE c.department_id = ? AND cs.session_date = CURDATE() AND cs.is_active = TRUE";
    $todaySessionsStmt = $db->prepare($todaySessionsQuery);
    $todaySessionsStmt->execute([$intern['assigned_department']]);
    $todaySessions = $todaySessionsStmt->fetch(PDO::FETCH_ASSOC)['today_sessions'];

    // Get recent courses in assigned department
    $recentCoursesQuery = "SELECT c.*, 
                           u.first_name as faculty_first_name, u.last_name as faculty_last_name,
                           (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id AND status = 'active') as enrolled_students,
                           (SELECT COUNT(*) FROM class_sessions WHERE course_id = c.id AND session_date = CURDATE()) as today_sessions
                           FROM courses c 
                           LEFT JOIN users u ON c.faculty_intern_id = u.id 
                           WHERE c.department_id = ? AND c.is_active = TRUE 
                           ORDER BY c.created_at DESC 
                           LIMIT 5";
    
    $recentCoursesStmt = $db->prepare($recentCoursesQuery);
    $recentCoursesStmt->execute([$intern['assigned_department']]);
    $recentCourses = $recentCoursesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get pending enrollment requests with details in assigned department
    $enrollmentRequestsQuery = "SELECT er.*, 
                               c.course_code, c.course_name,
                               u.first_name as student_first_name, u.last_name as student_last_name,
                               u.email as student_email, u.user_id as student_user_id,
                               fac.first_name as faculty_first_name, fac.last_name as faculty_last_name
                               FROM enrollment_requests er
                               JOIN courses c ON er.course_id = c.id
                               JOIN users u ON er.student_id = u.id
                               JOIN users fac ON c.faculty_intern_id = fac.id
                               WHERE c.department_id = ? AND er.status = 'pending'
                               ORDER BY er.requested_at DESC 
                               LIMIT 5";
    
    $enrollmentRequestsStmt = $db->prepare($enrollmentRequestsQuery);
    $enrollmentRequestsStmt->execute([$intern['assigned_department']]);
    $enrollmentRequests = $enrollmentRequestsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get today's sessions with details in assigned department
    $todayDetailedQuery = "SELECT cs.*, c.course_name, c.course_code,
                          fac.first_name as faculty_first_name, fac.last_name as faculty_last_name,
                          (SELECT COUNT(*) FROM attendance_records WHERE session_id = cs.id) as attendance_marked
                          FROM class_sessions cs
                          JOIN courses c ON cs.course_id = c.id
                          JOIN users fac ON cs.created_by = fac.id
                          WHERE c.department_id = ? AND cs.session_date = CURDATE() AND cs.is_active = TRUE
                          ORDER BY cs.start_time ASC";
    
    $todayDetailedStmt = $db->prepare($todayDetailedQuery);
    $todayDetailedStmt->execute([$intern['assigned_department']]);
    $todayDetailedSessions = $todayDetailedStmt->fetchAll(PDO::FETCH_ASSOC);

    // Return intern dashboard data
    echo json_encode([
        'success' => true,
        'dashboard' => [
            'intern' => [
                'id' => $intern['id'],
                'name' => $intern['first_name'] . ' ' . $intern['last_name'],
                'email' => $intern['email'],
                'user_id' => $intern['user_id'],
                'department' => $intern['department_name'],
                'assigned_department' => $intern['assigned_department_name'],
                'designation' => $intern['designation'],
                'start_date' => $intern['start_date'],
                'end_date' => $intern['end_date']
            ],
            'stats' => [
                'department_courses' => $coursesCount,
                'department_students' => $studentsCount,
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