<?php
// php/api/courses.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

include_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        if(isset($_GET['faculty_id'])) {
            getFacultyCourses($db, $_GET['faculty_id']);
        } else if(isset($_GET['course_id'])) {
            getCourseDetails($db, $_GET['course_id']);
        } else {
            getAllCourses($db);
        }
        break;
    case 'POST':
        createCourse($db);
        break;
    case 'PUT':
        updateCourse($db);
        break;
    case 'DELETE':
        deleteCourse($db);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

function getFacultyCourses($db, $facultyId) {
    try {
        $query = "SELECT c.*, d.name as department_name, 
                         (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id AND status = 'active') as enrolled_students,
                         (SELECT COUNT(*) FROM class_sessions WHERE course_id = c.id) as total_sessions
                  FROM courses c 
                  LEFT JOIN departments d ON c.department_id = d.id 
                  WHERE c.faculty_intern_id = ? AND c.is_active = TRUE
                  ORDER BY c.created_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$facultyId]);
        
        $courses = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $courses[] = $row;
        }
        
        echo json_encode(['success' => true, 'courses' => $courses]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function createCourse($db) {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $courseCode = $data['course_code'] ?? '';
    $courseName = $data['course_name'] ?? '';
    $description = $data['description'] ?? '';
    $credits = $data['credits'] ?? 3;
    $facultyInternId = $data['faculty_intern_id'] ?? '';
    $departmentId = $data['department_id'] ?? null;
    
    // Validation
    if (empty($courseCode) || empty($courseName) || empty($facultyInternId)) {
        echo json_encode(['success' => false, 'message' => 'Course code, name, and faculty ID are required']);
        return;
    }

    try {
        // Check if course code already exists
        $checkQuery = "SELECT id FROM courses WHERE course_code = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$courseCode]);
        
        if($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Course code already exists']);
            return;
        }
        
        // Insert new course
        $query = "INSERT INTO courses (course_code, course_name, description, credits, faculty_intern_id, department_id) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($query);
        
        if($stmt->execute([$courseCode, $courseName, $description, $credits, $facultyInternId, $departmentId])) {
            $courseId = $db->lastInsertId();
            
            // Get the created course details
            $getQuery = "SELECT c.*, d.name as department_name FROM courses c 
                        LEFT JOIN departments d ON c.department_id = d.id 
                        WHERE c.id = ?";
            $getStmt = $db->prepare($getQuery);
            $getStmt->execute([$courseId]);
            $course = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Course created successfully',
                'course' => $course
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create course']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateCourse($db) {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $courseId = $data['course_id'] ?? '';
    $courseCode = $data['course_code'] ?? '';
    $courseName = $data['course_name'] ?? '';
    $description = $data['description'] ?? '';
    $credits = $data['credits'] ?? 3;
    $departmentId = $data['department_id'] ?? null;
    
    if (empty($courseId) || empty($courseCode) || empty($courseName)) {
        echo json_encode(['success' => false, 'message' => 'Course ID, code, and name are required']);
        return;
    }

    try {
        // Check if course code already exists (excluding current course)
        $checkQuery = "SELECT id FROM courses WHERE course_code = ? AND id != ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$courseCode, $courseId]);
        
        if($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Course code already exists']);
            return;
        }
        
        // Update course
        $query = "UPDATE courses SET course_code = ?, course_name = ?, description = ?, 
                  credits = ?, department_id = ?, updated_at = NOW() 
                  WHERE id = ?";
        
        $stmt = $db->prepare($query);
        
        if($stmt->execute([$courseCode, $courseName, $description, $credits, $departmentId, $courseId])) {
            echo json_encode(['success' => true, 'message' => 'Course updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update course']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function deleteCourse($db) {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $courseId = $data['course_id'] ?? '';
    
    if (empty($courseId)) {
        echo json_encode(['success' => false, 'message' => 'Course ID is required']);
        return;
    }

    try {
        // Soft delete - set is_active to FALSE
        $query = "UPDATE courses SET is_active = FALSE, updated_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if($stmt->execute([$courseId])) {
            echo json_encode(['success' => true, 'message' => 'Course deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete course']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getCourseDetails($db, $courseId) {
    try {
        $query = "SELECT c.*, d.name as department_name, 
                         u.first_name as faculty_first_name, u.last_name as faculty_last_name,
                         (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id AND status = 'active') as enrolled_students,
                         (SELECT COUNT(*) FROM class_sessions WHERE course_id = c.id) as total_sessions
                  FROM courses c 
                  LEFT JOIN departments d ON c.department_id = d.id 
                  LEFT JOIN users u ON c.faculty_intern_id = u.id 
                  WHERE c.id = ? AND c.is_active = TRUE";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$courseId]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($course) {
            echo json_encode(['success' => true, 'course' => $course]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Course not found']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getAllCourses($db) {
    try {
        $query = "SELECT c.*, d.name as department_name, 
                         u.first_name as faculty_first_name, u.last_name as faculty_last_name
                  FROM courses c 
                  LEFT JOIN departments d ON c.department_id = d.id 
                  LEFT JOIN users u ON c.faculty_intern_id = u.id 
                  WHERE c.is_active = TRUE 
                  ORDER BY c.created_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $courses = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $courses[] = $row;
        }
        
        echo json_encode(['success' => true, 'courses' => $courses]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>