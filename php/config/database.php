<?php
// php/config/database.php
class Database {
    // School server database configuration
    private $host = 'localhost'; // or the school's database server
    private $db_name = 'webtech_2025A_foureiratou_idi';
    private $username = 'foureiratou.idi';
    private $password = 'Fouri@2025SQL';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            // Most likely MySQL for web hosting
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
        } catch(PDOException $exception) {
            // Try SQL Server if MySQL fails
            try {
                $this->conn = new PDO("sqlsrv:Server=" . $this->host . ";Database=" . $this->db_name, $this->username, $this->password);
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch(PDOException $e) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Database connection failed: ' . $e->getMessage(),
                    'debug' => [
                        'host' => $this->host,
                        'dbname' => $this->db_name,
                        'username' => $this->username
                    ]
                ]);
                exit;
            }
        }
        return $this->conn;
    }
}
?>