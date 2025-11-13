<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    Auth::redirectBasedOnRole();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();

    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];

    try {
        $query = "SELECT u.user_id, u.email, u.password_hash, u.role, u.first_name, u.last_name 
                  FROM users u 
                  WHERE u.email = :email";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $user['password_hash'])) {
                // Login successful
                Auth::login($user['user_id'], $user['email'], $user['role'], $user['first_name'], $user['last_name']);
                
                // Redirect based on role
                Auth::redirectBasedOnRole();
            } else {
                $error = "Invalid email or password!";
            }
        } else {
            $error = "Invalid email or password!";
        }
    } catch(PDOException $exception) {
        $error = "Database error: " . $exception->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MY ATTENDANCE SYSTEM</title>
    <link rel="stylesheet" href="../assets/css/mystyle.css">
</head>
<body>
    <div class="container">
        <img src="../assets/images/Logo.png" alt="Logo" class="logo">
        <h1>Furairah's Attendance Management System</h1>
        <h2>Login</h2>

        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo $_POST['email'] ?? ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <input type="checkbox" id="rememberMe" name="rememberMe">
                <label for="rememberMe">Remember me</label>
            </div>

            <button type="submit">Login</button>
            <p>Don't have an account? <a href="signup.php">Sign Up here</a></p>
            <p><a href="#forgot-password">Forgot Password?</a></p>
        </form>
    </div>

    <script src="../assets/js/login.js"></script>
</body>
</html>