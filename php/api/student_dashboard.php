<?php
// php/api/student_dashboard.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// FIXED PATH: Go up one level then into config
include_once __DIR__ . '/../config/database.php';

// Check if student ID is provided
if (!isset($_GET['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit;
}

$studentId = $_GET['student_id'];
$database = new Database();
$db = $database->getConnection();

try {
    // Get student basic info
    $studentQuery = "SELECT u.*, m.name as major_name, d.name as department_name 
                     FROM users u 
                     LEFT JOIN majors m ON u.major_id = m.id 
                     LEFT JOIN departments d ON u.department_id = d.id 
                     WHERE u.id = ? AND u.role = 'student'";
    
    $studentStmt = $db->prepare($studentQuery);
    $studentStmt->execute([$studentId]);
    $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }

    // Get enrolled courses count
    $coursesQuery = "SELECT COUNT(*) as course_count 
                     FROM course_enrollments 
                     WHERE student_id = ? AND status = 'active'";
    $coursesStmt = $db->prepare($coursesQuery);
    $coursesStmt->execute([$studentId]);
    $coursesCount = $coursesStmt->fetch(PDO::FETCH_ASSOC)['course_count'];

    // Get total sessions attended
    $attendanceQuery = "SELECT COUNT(*) as sessions_attended 
                        FROM attendance_records 
                        WHERE student_id = ? AND status IN ('present', 'late')";
    $attendanceStmt = $db->prepare($attendanceQuery);
    $attendanceStmt->execute([$studentId]);
    $sessionsAttended = $attendanceStmt->fetch(PDO::FETCH_ASSOC)['sessions_attended'];

    // Get total sessions available (from enrolled courses)
    $totalSessionsQuery = "SELECT COUNT(*) as total_sessions 
                           FROM class_sessions cs 
                           JOIN course_enrollments ce ON cs.course_id = ce.course_id 
                           WHERE ce.student_id = ? AND ce.status = 'active' 
                           AND cs.session_date <= CURDATE()";
    $totalSessionsStmt = $db->prepare($totalSessionsQuery);
    $totalSessionsStmt->execute([$studentId]);
    $totalSessions = $totalSessionsStmt->fetch(PDO::FETCH_ASSOC)['total_sessions'];

    // Calculate attendance rate
    $attendanceRate = $totalSessions > 0 ? round(($sessionsAttended / $totalSessions) * 100) : 0;

    // Get upcoming sessions (next 7 days)
    $upcomingQuery = "SELECT cs.*, c.course_name, c.course_code 
                      FROM class_sessions cs 
                      JOIN course_enrollments ce ON cs.course_id = ce.course_id 
                      JOIN courses c ON cs.course_id = c.id 
                      WHERE ce.student_id = ? AND ce.status = 'active' 
                      AND cs.session_date >= CURDATE() 
                      AND cs.session_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                      ORDER BY cs.session_date, cs.start_time 
                      LIMIT 5";
    
    $upcomingStmt = $db->prepare($upcomingQuery);
    $upcomingStmt->execute([$studentId]);
    $upcomingSessions = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent activity (last 5 attendance records)
    $recentActivityQuery = "SELECT ar.*, cs.session_date, cs.topic, c.course_name, 
                                   TIME_FORMAT(cs.start_time, '%h:%i %p') as session_time
                            FROM attendance_records ar 
                            JOIN class_sessions cs ON ar.session_id = cs.id 
                            JOIN courses c ON cs.course_id = c.id 
                            WHERE ar.student_id = ? 
                            ORDER BY cs.session_date DESC, ar.marked_at DESC 
                            LIMIT 5";
    
    $recentActivityStmt = $db->prepare($recentActivityQuery);
    $recentActivityStmt->execute([$studentId]);
    $recentActivity = $recentActivityStmt->fetchAll(PDO::FETCH_ASSOC);

    // Format recent activity for frontend
    $formattedActivity = [];
    foreach ($recentActivity as $activity) {
        $formattedActivity[] = [
            'type' => 'attendance',
            'status' => $activity['status'],
            'course' => $activity['course_name'],
            'date' => $activity['session_date'],
            'time' => $activity['session_time'],
            'topic' => $activity['topic']
        ];
    }

    // Return dashboard data
    echo json_encode([
        'success' => true,
        'dashboard' => [
            'student' => [
                'id' => $student['id'],
                'name' => $student['first_name'] . ' ' . $student['last_name'],
                'email' => $student['email'],
                'user_id' => $student['user_id'],
                'major' => $student['major_name'],
                'year_of_study' => $student['year_of_study']
            ],
            'stats' => [
                'total_courses' => $coursesCount,
                'sessions_attended' => $sessionsAttended,
                'attendance_rate' => $attendanceRate,
                'upcoming_sessions' => count($upcomingSessions)
            ],
            'upcoming_sessions' => $upcomingSessions,
            'recent_activity' => $formattedActivity
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>