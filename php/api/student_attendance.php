<?php
// php/api/student_attendance.php
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
$courseId = $_GET['course_id'] ?? null; // Optional: filter by course
$month = $_GET['month'] ?? null; // Optional: filter by month
$year = $_GET['year'] ?? null; // Optional: filter by year

$database = new Database();
$db = $database->getConnection();

try {
    $query = "SELECT ar.*, cs.session_date, cs.start_time, cs.end_time, cs.topic, cs.location,
                     c.course_code, c.course_name,
                     CASE 
                         WHEN ar.status = 'present' THEN 'Present'
                         WHEN ar.status = 'absent' THEN 'Absent'
                         WHEN ar.status = 'late' THEN 'Late'
                         WHEN ar.status = 'excused' THEN 'Excused'
                         ELSE 'Not Marked'
                     END as status_text,
                     TIME_FORMAT(cs.start_time, '%h:%i %p') as formatted_time
              FROM attendance_records ar
              JOIN class_sessions cs ON ar.session_id = cs.id
              JOIN courses c ON cs.course_id = c.id
              JOIN course_enrollments ce ON c.id = ce.course_id
              WHERE ce.student_id = ? AND ce.status = 'active'";
    
    $params = [$studentId];
    
    if ($courseId) {
        $query .= " AND c.id = ?";
        $params[] = $courseId;
    }
    
    if ($month && $year) {
        $query .= " AND MONTH(cs.session_date) = ? AND YEAR(cs.session_date) = ?";
        $params[] = $month;
        $params[] = $year;
    }
    
    $query .= " ORDER BY cs.session_date DESC, cs.start_time DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    $attendance = [];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $attendance[] = $row;
    }
    
    // Get attendance summary
    $summaryQuery = "SELECT 
                        COUNT(*) as total_sessions,
                        SUM(CASE WHEN ar.status IN ('present', 'late') THEN 1 ELSE 0 END) as attended_sessions,
                        SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_sessions,
                        SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_sessions,
                        SUM(CASE WHEN ar.status = 'excused' THEN 1 ELSE 0 END) as excused_sessions
                     FROM attendance_records ar
                     JOIN class_sessions cs ON ar.session_id = cs.id
                     JOIN course_enrollments ce ON cs.course_id = ce.course_id
                     WHERE ce.student_id = ? AND ce.status = 'active'";
    
    $summaryParams = [$studentId];
    
    if ($courseId) {
        $summaryQuery .= " AND cs.course_id = ?";
        $summaryParams[] = $courseId;
    }
    
    if ($month && $year) {
        $summaryQuery .= " AND MONTH(cs.session_date) = ? AND YEAR(cs.session_date) = ?";
        $summaryParams[] = $month;
        $summaryParams[] = $year;
    }
    
    $summaryStmt = $db->prepare($summaryQuery);
    $summaryStmt->execute($summaryParams);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
    
    $attendanceRate = $summary['total_sessions'] > 0 ? 
        round(($summary['attended_sessions'] / $summary['total_sessions']) * 100) : 0;
    
    echo json_encode([
        'success' => true,
        'attendance' => $attendance,
        'summary' => [
            'total_sessions' => $summary['total_sessions'],
            'attended_sessions' => $summary['attended_sessions'],
            'absent_sessions' => $summary['absent_sessions'],
            'late_sessions' => $summary['late_sessions'],
            'excused_sessions' => $summary['excused_sessions'],
            'attendance_rate' => $attendanceRate
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>