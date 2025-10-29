<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    public $conn;
    
    public function __construct() {
        $this->host = getenv('MYSQLHOST') ?: 'localhost';
        $this->db_name = getenv('MYSQL_DATABASE') ?: 'railway';
        $this->username = getenv('MYSQLUSER') ?: 'root';
        $this->password = getenv('MYSQLPASSWORD') ?: '';
        $this->port = getenv('MYSQLPORT') ?: '3306';
    }
    
    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host=" . $this->host . 
                   ";port=" . $this->port . 
                   ";dbname=" . $this->db_name;
            
            $this->conn = new PDO(
                $dsn,
                $this->username,
                $this->password,
                array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4")
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
        } catch(PDOException $exception) {
            // NO USAR ECHO AQUÍ - Solo log
            error_log("Database connection error: " . $exception->getMessage());
            // Opcionalmente puedes lanzar la excepción
            throw $exception;
        }
        return $this->conn;
    }
}
?>
