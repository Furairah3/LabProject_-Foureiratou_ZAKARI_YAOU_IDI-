<?php
// database_setup.php
header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'attendance_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
    $pdo->exec("USE $dbname");
    
    // Complete database schema in PHP array
    $sqlStatements = [
        // Users table (extends your existing structure)
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            user_id VARCHAR(50) UNIQUE NOT NULL,
            dob DATE NOT NULL,
            role ENUM('student', 'faculty', 'intern') NOT NULL,
            major_id INT NULL,
            year_of_study INT NULL,
            department_id INT NULL,
            designation VARCHAR(100) NULL,
            assigned_department INT NULL,
            start_date DATE NULL,
            end_date DATE NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        // Departments table
        "CREATE TABLE IF NOT EXISTS departments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            code VARCHAR(10) UNIQUE NOT NULL,
            description TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        // Majors table
        "CREATE TABLE IF NOT EXISTS majors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            code VARCHAR(10) UNIQUE NOT NULL,
            department_id INT,
            description TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (department_id) REFERENCES departments(id)
        )",
        
        // Courses table
        "CREATE TABLE IF NOT EXISTS courses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_code VARCHAR(20) UNIQUE NOT NULL,
            course_name VARCHAR(200) NOT NULL,
            description TEXT,
            credits INT DEFAULT 3,
            faculty_intern_id INT NOT NULL,
            department_id INT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (faculty_intern_id) REFERENCES users(id),
            FOREIGN KEY (department_id) REFERENCES departments(id)
        )",
        
        // Course enrollment requests
        "CREATE TABLE IF NOT EXISTS enrollment_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            course_id INT NOT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            reviewed_by INT NULL,
            reviewed_at TIMESTAMP NULL,
            notes TEXT,
            FOREIGN KEY (student_id) REFERENCES users(id),
            FOREIGN KEY (course_id) REFERENCES courses(id),
            FOREIGN KEY (reviewed_by) REFERENCES users(id),
            UNIQUE KEY unique_enrollment_request (student_id, course_id)
        )",
        
        // Course enrollments (approved enrollments)
        "CREATE TABLE IF NOT EXISTS course_enrollments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            course_id INT NOT NULL,
            enrolled_by INT NOT NULL,
            enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('active', 'completed', 'dropped') DEFAULT 'active',
            FOREIGN KEY (student_id) REFERENCES users(id),
            FOREIGN KEY (course_id) REFERENCES courses(id),
            FOREIGN KEY (enrolled_by) REFERENCES users(id),
            UNIQUE KEY unique_enrollment (student_id, course_id)
        )",
        
        // Class sessions table
        "CREATE TABLE IF NOT EXISTS class_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_id INT NOT NULL,
            session_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            topic VARCHAR(200),
            location VARCHAR(100),
            attendance_code VARCHAR(10) UNIQUE,
            is_active BOOLEAN DEFAULT TRUE,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (course_id) REFERENCES courses(id),
            FOREIGN KEY (created_by) REFERENCES users(id)
        )",
        
        // Attendance records table
        "CREATE TABLE IF NOT EXISTS attendance_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            student_id INT NOT NULL,
            status ENUM('present', 'absent', 'late', 'excused') DEFAULT 'absent',
            marked_by INT NULL,
            marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            marked_with_code VARCHAR(10) NULL,
            notes TEXT,
            FOREIGN KEY (session_id) REFERENCES class_sessions(id),
            FOREIGN KEY (student_id) REFERENCES users(id),
            FOREIGN KEY (marked_by) REFERENCES users(id),
            UNIQUE KEY unique_attendance (session_id, student_id)
        )",
        
        // FI assigned courses
        "CREATE TABLE IF NOT EXISTS fi_course_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            faculty_intern_id INT NOT NULL,
            course_id INT NOT NULL,
            assigned_by INT NOT NULL,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE,
            FOREIGN KEY (faculty_intern_id) REFERENCES users(id),
            FOREIGN KEY (course_id) REFERENCES courses(id),
            FOREIGN KEY (assigned_by) REFERENCES users(id),
            UNIQUE KEY unique_fi_assignment (faculty_intern_id, course_id)
        )",
        
        // System settings table
        "CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            description TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    ];
    
    // Execute all SQL statements
    foreach ($sqlStatements as $sql) {
        $pdo->exec($sql);
    }
    
    // Insert sample data
    $sampleData = [
        // Departments
        "INSERT IGNORE INTO departments (name, code, description) VALUES 
        ('Computer Science', 'CS', 'Department of Computer Science'),
        ('Electrical Engineering', 'EE', 'Department of Electrical Engineering'),
        ('Mechanical Engineering', 'ME', 'Department of Mechanical Engineering'),
        ('Business Administration', 'BA', 'Department of Business Administration'),
        ('Mathematics', 'MATH', 'Department of Mathematics')",
        
        // Majors
        "INSERT IGNORE INTO majors (name, code, department_id, description) VALUES 
        ('Computer Science', 'CS', 1, 'Bachelor of Science in Computer Science'),
        ('Software Engineering', 'SE', 1, 'Bachelor of Science in Software Engineering'),
        ('Electrical Engineering', 'EE', 2, 'Bachelor of Science in Electrical Engineering'),
        ('Mechanical Engineering', 'ME', 3, 'Bachelor of Science in Mechanical Engineering'),
        ('Business Management', 'BM', 4, 'Bachelor of Business Administration')",
        
        // System settings
        "INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES 
        ('attendance_code_length', '6', 'Length of auto-generated attendance codes'),
        ('session_duration_default', '90', 'Default session duration in minutes'),
        ('max_attendance_delay', '15', 'Maximum minutes late before marked as absent')"
    ];
    
    foreach ($sampleData as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Ignore duplicate entry errors for sample data
            if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                throw $e;
            }
        }
    }
    
    // Create indexes for better performance
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)",
        "CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)",
        "CREATE INDEX IF NOT EXISTS idx_courses_faculty ON courses(faculty_intern_id)",
        "CREATE INDEX IF NOT EXISTS idx_sessions_course_date ON class_sessions(course_id, session_date)",
        "CREATE INDEX IF NOT EXISTS idx_attendance_session ON attendance_records(session_id)",
        "CREATE INDEX IF NOT EXISTS idx_attendance_student ON attendance_records(student_id)",
        "CREATE INDEX IF NOT EXISTS idx_enrollment_student ON course_enrollments(student_id)",
        "CREATE INDEX IF NOT EXISTS idx_enrollment_course ON course_enrollments(course_id)",
        "CREATE INDEX IF NOT EXISTS idx_requests_status ON enrollment_requests(status)"
    ];
    
    foreach ($indexes as $indexSql) {
        try {
            $pdo->exec($indexSql);
        } catch (PDOException $e) {
            // Ignore errors if indexes already exist
            error_log("Index creation warning: " . $e->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Database schema created successfully with all tables and sample data'
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>