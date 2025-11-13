<?php
session_start();

class Auth {
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public static function getUserRole() {
        return $_SESSION['role'] ?? null;
    }

    public static function redirectIfNotLoggedIn() {
        if (!self::isLoggedIn()) {
            header("Location: ../pages/login.php");
            exit();
        }
    }

    public static function redirectBasedOnRole() {
        if (self::isLoggedIn()) {
            $role = self::getUserRole();
            switch($role) {
                case 'student':
                    header("Location: ../pages/student-dashboard.php");
                    break;
                case 'faculty':
                    header("Location: ../pages/faculty-dashboard.php");
                    break;
                case 'intern':
                    header("Location: ../pages/intern-dashboard.php");
                    break;
                default:
                    header("Location: ../pages/dashboard.php");
            }
            exit();
        }
    }

    public static function login($user_id, $email, $role, $first_name, $last_name) {
        $_SESSION['user_id'] = $user_id;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = $role;
        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name'] = $last_name;
        $_SESSION['logged_in'] = true;
    }

    public static function logout() {
        session_destroy();
        header("Location: ../pages/login.php");
        exit();
    }
}
?>