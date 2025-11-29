<?php
// auth.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include the school database configuration
include_once 'config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Get the action from POST data
$action = $_POST['action'] ?? '';

if ($action === 'signup') {
    handleSignup($pdo);
} elseif ($action === 'login') {
    handleLogin($pdo);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function handleSignup($pdo) {
    // Get form data and convert empty strings to NULL for integer fields
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $userId = $_POST['user_id'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $role = $_POST['role'] ?? '';
    
    // Role-specific fields - convert empty strings to NULL
    $majorId = !empty($_POST['major_id']) ? $_POST['major_id'] : null;
    $yearOfStudy = !empty($_POST['year_of_study']) ? $_POST['year_of_study'] : null;
    $departmentId = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
    $designation = $_POST['designation'] ?? null;
    $assignedDepartment = !empty($_POST['assigned_department']) ? $_POST['assigned_department'] : null;
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;
    
    // Basic validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($userId) || empty($dob) || empty($role)) {
        echo json_encode(['success' => false, 'message' => 'All required fields are missing']);
        return;
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        return;
    }
    
    // Check if user ID already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'User ID already exists']);
        return;
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user into database with all fields
    try {
        $stmt = $pdo->prepare("INSERT INTO users 
            (first_name, last_name, email, password, user_id, dob, role, 
             major_id, year_of_study, department_id, designation, 
             assigned_department, start_date, end_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $firstName, $lastName, $email, $hashedPassword, $userId, $dob, $role,
            $majorId, $yearOfStudy, $departmentId, $designation,
            $assignedDepartment, $startDate, $endDate
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Registration successful']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
    }
}

function handleLogin($pdo) {
    // Get form data
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Basic validation
    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        return;
    }
    
    // Find user by email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = TRUE");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        // Remove password from user data before sending to frontend
        unset($user['password']);
        
        // Get additional user info based on role
        if ($user['role'] === 'student' && $user['major_id']) {
            $majorStmt = $pdo->prepare("SELECT name FROM majors WHERE id = ?");
            $majorStmt->execute([$user['major_id']]);
            $major = $majorStmt->fetch(PDO::FETCH_ASSOC);
            $user['major_name'] = $major['name'] ?? null;
        } else if (in_array($user['role'], ['faculty', 'intern']) && $user['department_id']) {
            $deptStmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
            $deptStmt->execute([$user['department_id']]);
            $department = $deptStmt->fetch(PDO::FETCH_ASSOC);
            $user['department_name'] = $department['name'] ?? null;
            
            // For interns, also get assigned department name
            if ($user['role'] === 'intern' && $user['assigned_department']) {
                $assignedDeptStmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
                $assignedDeptStmt->execute([$user['assigned_department']]);
                $assignedDepartment = $assignedDeptStmt->fetch(PDO::FETCH_ASSOC);
                $user['assigned_department_name'] = $assignedDepartment['name'] ?? null;
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Login successful', 
            'user' => $user
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    }
}
?>
