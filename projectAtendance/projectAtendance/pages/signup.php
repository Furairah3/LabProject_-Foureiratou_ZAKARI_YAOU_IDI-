<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();

    // Sanitize input data
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $user_id = sanitizeInput($_POST['user_id']);
    $dob = sanitizeInput($_POST['dob']);
    $role = sanitizeInput($_POST['role']);

    try {
        // Check if email already exists
        $check_email_query = "SELECT user_id FROM users WHERE email = :email";
        $check_stmt = $db->prepare($check_email_query);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->execute();

        if ($check_stmt->rowCount() > 0) {
            $error = "Email already registered!";
        } else {
            // Check if user ID already exists
            $check_id_query = "SELECT user_id FROM users WHERE user_id = :user_id";
            $check_id_stmt = $db->prepare($check_id_query);
            $check_id_stmt->bindParam(':user_id', $user_id);
            $check_id_stmt->execute();

            if ($check_id_stmt->rowCount() > 0) {
                $error = "User ID already exists!";
            } else {
                // Hash password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                // Insert into users table
                $user_query = "INSERT INTO users (user_id, first_name, last_name, email, password_hash, role, dob) 
                              VALUES (:user_id, :first_name, :last_name, :email, :password_hash, :role, :dob)";
                
                $user_stmt = $db->prepare($user_query);
                $user_stmt->bindParam(':user_id', $user_id);
                $user_stmt->bindParam(':first_name', $first_name);
                $user_stmt->bindParam(':last_name', $last_name);
                $user_stmt->bindParam(':email', $email);
                $user_stmt->bindParam(':password_hash', $password_hash);
                $user_stmt->bindParam(':role', $role);
                $user_stmt->bindParam(':dob', $dob);

                if ($user_stmt->execute()) {
                    // Insert into role-specific tables
                    if ($role === 'student') {
                        $major_id = sanitizeInput($_POST['major_id']);
                        $year_of_study = sanitizeInput($_POST['year_of_study']);
                        
                        $student_query = "INSERT INTO students (student_id, major_id, year_of_study) 
                                        VALUES (:student_id, :major_id, :year_of_study)";
                        $student_stmt = $db->prepare($student_query);
                        $student_stmt->bindParam(':student_id', $user_id);
                        $student_stmt->bindParam(':major_id', $major_id);
                        $student_stmt->bindParam(':year_of_study', $year_of_study);
                        $student_stmt->execute();

                    } elseif ($role === 'faculty') {
                        $department_id = sanitizeInput($_POST['department_id']);
                        $designation = sanitizeInput($_POST['designation']);
                        
                        $faculty_query = "INSERT INTO faculty (faculty_id, department_id, designation) 
                                        VALUES (:faculty_id, :department_id, :designation)";
                        $faculty_stmt = $db->prepare($faculty_query);
                        $faculty_stmt->bindParam(':faculty_id', $user_id);
                        $faculty_stmt->bindParam(':department_id', $department_id);
                        $faculty_stmt->bindParam(':designation', $designation);
                        $faculty_stmt->execute();

                    } elseif ($role === 'intern') {
                        $assigned_department = sanitizeInput($_POST['assigned_department']);
                        $start_date = sanitizeInput($_POST['start_date']);
                        $end_date = sanitizeInput($_POST['end_date']);
                        
                        $intern_query = "INSERT INTO interns (intern_id, assigned_department, start_date, end_date) 
                                       VALUES (:intern_id, :assigned_department, :start_date, :end_date)";
                        $intern_stmt = $db->prepare($intern_query);
                        $intern_stmt->bindParam(':intern_id', $user_id);
                        $intern_stmt->bindParam(':assigned_department', $assigned_department);
                        $intern_stmt->bindParam(':start_date', $start_date);
                        $intern_stmt->bindParam(':end_date', $end_date);
                        $intern_stmt->execute();
                    }

                    $success = "Registration successful! You can now login.";
                } else {
                    $error = "Registration failed. Please try again.";
                }
            }
        }
    } catch(PDOException $exception) {
        $error = "Database error: " . $exception->getMessage();
    }
}

// Fetch departments and majors for dropdowns
$database = new Database();
$db = $database->getConnection();

$departments = [];
$majors = [];

try {
    $dept_query = "SELECT * FROM departments";
    $dept_stmt = $db->prepare($dept_query);
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

    $major_query = "SELECT * FROM majors";
    $major_stmt = $db->prepare($major_query);
    $major_stmt->execute();
    $majors = $major_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $exception) {
    // Handle error silently for now
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - MY ATTENDANCE SYSTEM</title>
    <link rel="stylesheet" href="../assets/css/mystyle.css">
</head>
<body>
    <div class="container">
        <img src="../assets/images/Logo.png" alt="Logo" class="logo">
        <h1>Furairah's Attendance Management System</h1>
        <h2>Sign Up</h2>

        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>

        <form id="signupForm" method="POST">
            <div class="form-group">
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" value="<?php echo $_POST['first_name'] ?? ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" value="<?php echo $_POST['last_name'] ?? ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo $_POST['email'] ?? ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirmPassword">Confirm Password:</label>
                <input type="password" id="confirmPassword" name="confirmPassword" required>
                <small id="passwordMessage" class="error-message">Passwords do not match!</small>
            </div>

            <div class="form-group">
                <label for="user_id">User ID:</label>
                <input type="text" id="user_id" name="user_id" value="<?php echo $_POST['user_id'] ?? ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="dob">Date of Birth:</label>
                <input type="date" id="dob" name="dob" value="<?php echo $_POST['dob'] ?? ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="role">Role:</label>
                <select id="role" name="role" required>
                    <option value="">Select your role</option>
                    <option value="student" <?php echo ($_POST['role'] ?? '') === 'student' ? 'selected' : ''; ?>>Student</option>
                    <option value="faculty" <?php echo ($_POST['role'] ?? '') === 'faculty' ? 'selected' : ''; ?>>Faculty</option>
                    <option value="intern" <?php echo ($_POST['role'] ?? '') === 'intern' ? 'selected' : ''; ?>>Faculty Intern</option>
                </select>
            </div>

            <!-- Student-specific fields -->
            <div id="studentFields" class="role-specific-fields">
                <div class="form-group">
                    <label for="major_id">Major:</label>
                    <select id="major_id" name="major_id">
                        <option value="">Select your major</option>
                        <?php foreach($majors as $major): ?>
                            <option value="<?php echo $major['major_id']; ?>" 
                                <?php echo ($_POST['major_id'] ?? '') == $major['major_id'] ? 'selected' : ''; ?>>
                                <?php echo $major['major_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="year_of_study">Year of Study:</label>
                    <input type="number" id="year_of_study" name="year_of_study" value="<?php echo $_POST['year_of_study'] ?? ''; ?>" min="1" max="5">
                </div>
            </div>

            <!-- Faculty-specific fields -->
            <div id="facultyFields" class="role-specific-fields">
                <div class="form-group">
                    <label for="department_id">Department:</label>
                    <select id="department_id" name="department_id">
                        <option value="">Select your department</option>
                        <?php foreach($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>" 
                                <?php echo ($_POST['department_id'] ?? '') == $dept['department_id'] ? 'selected' : ''; ?>>
                                <?php echo $dept['department_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="designation">Designation:</label>
                    <input type="text" id="designation" name="designation" value="<?php echo $_POST['designation'] ?? ''; ?>">
                </div>
            </div>

            <!-- Intern-specific fields -->
            <div id="internFields" class="role-specific-fields">
                <div class="form-group">
                    <label for="assigned_department">Assigned Department:</label>
                    <select id="assigned_department" name="assigned_department">
                        <option value="">Select assigned department</option>
                        <?php foreach($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>" 
                                <?php echo ($_POST['assigned_department'] ?? '') == $dept['department_id'] ? 'selected' : ''; ?>>
                                <?php echo $dept['department_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="start_date">Start Date:</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo $_POST['start_date'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="end_date">End Date:</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo $_POST['end_date'] ?? ''; ?>">
                </div>
            </div>

            <button type="submit">Register</button>
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </form>
    </div>

    <script src="../assets/js/signup.js"></script>
</body>
</html>