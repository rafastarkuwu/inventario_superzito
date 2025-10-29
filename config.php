<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    public $conn;
    
    public function __construct() {
        // Railway proporciona estas variables automáticamente cuando vinculas MySQL
        $this->host = getenv('MYSQLHOST') ?: 'localhost';
        $this->db_name = getenv('MYSQL_DATABASE') ?: 'inventario_superzito';  // CAMBIO AQUÍ
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
                $this->password
            );
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
        } catch(PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
            error_log("Database connection error: " . $exception->getMessage());
        }
        return $this->conn;
    }
}
?>
