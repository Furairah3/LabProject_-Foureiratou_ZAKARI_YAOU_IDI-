<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a student
Auth::redirectIfNotLoggedIn();
if (Auth::getUserRole() !== 'student') {
    header("Location: dashboard.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get student data
$student_id = $_SESSION['user_id'];
$student_data = [];
$courses = [];
$sessions = [];

try {
    // Get student details
    $student_query = "SELECT s.*, m.major_name, u.first_name, u.last_name, u.email, u.dob 
                     FROM students s 
                     JOIN users u ON s.student_id = u.user_id 
                     LEFT JOIN majors m ON s.major_id = m.major_id 
                     WHERE s.student_id = :student_id";
    $student_stmt = $db->prepare($student_query);
    $student_stmt->bindParam(':student_id', $student_id);
    $student_stmt->execute();
    $student_data = $student_stmt->fetch(PDO::FETCH_ASSOC);

    // Get enrolled courses
    $courses_query = "SELECT c.*, u.first_name, u.last_name as faculty_name 
                     FROM course_student_list csl 
                     JOIN courses c ON csl.course_id = c.course_id 
                     JOIN users u ON c.faculty_id = u.user_id 
                     WHERE csl.student_id = :student_id AND csl.status = 'enrolled'";
    $courses_stmt = $db->prepare($courses_query);
    $courses_stmt->bindParam(':student_id', $student_id);
    $courses_stmt->execute();
    $courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get upcoming sessions
    $sessions_query = "SELECT s.*, c.course_code, c.course_name 
                      FROM sessions s 
                      JOIN courses c ON s.course_id = c.course_id 
                      JOIN course_student_list csl ON c.course_id = csl.course_id 
                      WHERE csl.student_id = :student_id 
                      AND s.date >= CURDATE() 
                      ORDER BY s.date, s.start_time 
                      LIMIT 5";
    $sessions_stmt = $db->prepare($sessions_query);
    $sessions_stmt->bindParam(':student_id', $student_id);
    $sessions_stmt->execute();
    $sessions = $sessions_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $exception) {
    $error = "Database error: " . $exception->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="../assets/css/mystyle.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">
            <img src="../assets/images/Logo.png" alt="Logo" class="logo">
            <h2>Student Dashboard</h2>
        </div>
        <ul>
            <li><a href="#" class="nav-link active" data-page="courses">My Courses</a></li>
            <li><a href="#" class="nav-link" data-page="sessions">Sessions Schedule</a></li>
            <li><a href="#" class="nav-link" data-page="grades">Grades/Reports</a></li>
            <li><a href="#" class="nav-link" data-page="profile">Profile</a></li>
            <li><a href="logout.php" class="nav-link">Logout</a></li>
        </ul>
    </nav>

    <main class="dashboard-content">
        <section class="welcome-section">
            <h1>Welcome, <?php echo htmlspecialchars($student_data['first_name'] . ' ' . $student_data['last_name']); ?></h1>
            <div class="student-info">
                <p>Student ID: <?php echo htmlspecialchars($student_data['student_id']); ?></p>
                <p>Major: <?php echo htmlspecialchars($student_data['major_name'] ?? 'Not assigned'); ?></p>
                <p>Year: <?php echo htmlspecialchars($student_data['year_of_study'] ?? 'Not specified'); ?></p>
            </div>
        </section>

        <section class="course-list dashboard-section" id="courses-section">
            <h2>My Courses</h2>
            <div class="courses-container" id="coursesContainer">
                <?php if (empty($courses)): ?>
                    <p>No courses enrolled.</p>
                <?php else: ?>
                    <?php foreach($courses as $course): ?>
                        <div class="course-item">
                            <h3><?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?></h3>
                            <p>Faculty: <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['faculty_name']); ?></p>
                            <p>Credit Hours: <?php echo htmlspecialchars($course['credit_hours']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="session-schedule dashboard-section" id="sessions-section" style="display: none;">
            <h2>Session Schedule</h2>
            <div class="sessions-container" id="sessionsContainer">
                <?php if (empty($sessions)): ?>
                    <p>No upcoming sessions.</p>
                <?php else: ?>
                    <?php foreach($sessions as $session): ?>
                        <div class="session-item">
                            <strong><?php echo date('l, M j', strtotime($session['date'])); ?>:</strong> 
                            <?php echo htmlspecialchars($session['course_code']); ?> 
                            (<?php echo date('g:i A', strtotime($session['start_time'])); ?>)
                            <br><small>Topic: <?php echo htmlspecialchars($session['topic']); ?></small>
                            <br><small>Location: <?php echo htmlspecialchars($session['location']); ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button class="join-btn" id="joinSessionBtn">Join as Student/Participant</button>
        </section>

        <!-- Other sections remain similar but with PHP data integration -->
    </main>

    <script src="../assets/js/student-dashboard.js"></script>
</body>
</html>