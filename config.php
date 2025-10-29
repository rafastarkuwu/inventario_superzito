<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    public $conn;
    
    public function __construct() {
        // Variables correctas de Railway MySQL
        $this->host = getenv('MYSQL_HOST') ?: getenv('MYSQLHOST') ?: 'localhost';
        $this->db_name = getenv('MYSQL_DATABASE') ?: 'railway';
        $this->username = getenv('MYSQL_USER') ?: getenv('MYSQLUSER') ?: 'root';
        $this->password = getenv('MYSQL_PASSWORD') ?: getenv('MYSQLPASSWORD') ?: '';
        $this->port = getenv('MYSQL_PORT') ?: getenv('MYSQLPORT') ?: '3306';
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
            echo "Error de conexión: " . $exception->getMessage();
            error_log("Database connection error: " . $exception->getMessage());
        }
        return $this->conn;
    }
}
?>
```

**Nota**: He puesto ambas opciones (`MYSQL_HOST` y `MYSQLHOST`) por si Railway usa una versión diferente. El operador `?:` probará la primera, y si no existe, usará la segunda.

---

## 🔍 Verifica las variables en Railway:

1. Ve a tu proyecto en Railway
2. Click en tu servicio **MySQL**
3. Pestaña **"Variables"** o **"Connect"**
4. Copia los nombres exactos de las variables

Las variables deberían verse así:
```
MYSQL_DATABASE=railway
MYSQL_HOST=monorail.proxy.rlwy.net
MYSQL_PASSWORD=tu_password_aqui
MYSQL_PORT=12345
MYSQL_USER=root
```

---

## 📝 Pasos finales:

1. **Actualiza config.php** con el código de arriba
2. **Sube el archivo** a Railway
3. **Espera el redeploy** (Railway se redesplegará automáticamente)
4. **Prueba nuevamente**:
```
   https://inventariosuperzito-production.up.railway.app/test.php
