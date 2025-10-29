<?php
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

echo "<style>
body { font-family: Arial; padding: 20px; background: #f5f5f5; }
.success { color: green; padding: 5px; }
.error { color: red; padding: 5px; background: #fee; border-left: 3px solid red; margin: 5px 0; }
.info { color: blue; padding: 5px; }
h1 { color: #667eea; }
</style>";

echo "<h1>🚀 Inicializando Base de Datos...</h1>";

$queries = [
    "DROP TABLE IF EXISTS Ventas",
    "DROP TABLE IF EXISTS Clientes",
    "DROP TABLE IF EXISTS Productos",
    "DROP TABLE IF EXISTS Inventario",
    "DROP TABLE IF EXISTS Trabajadores",
    "DROP TABLE IF EXISTS Encargado",
    "DROP TABLE IF EXISTS Persona",
    
    "CREATE TABLE Persona (
        id_persona INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        apellido VARCHAR(100) NOT NULL,
        telefono VARCHAR(20),
        email VARCHAR(100),
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE TABLE Encargado (
        id_encargado INT AUTO_INCREMENT PRIMARY KEY,
        id_persona INT NOT NULL,
        usuario VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_persona) REFERENCES Persona(id_persona) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE TABLE Trabajadores (
        id_trabajador INT AUTO_INCREMENT PRIMARY KEY,
        id_persona INT NOT NULL,
        usuario VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        fecha_contratacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_persona) REFERENCES Persona(id_persona) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE TABLE Inventario (
        id_inventario INT AUTO_INCREMENT PRIMARY KEY,
        stock_actual INT NOT NULL DEFAULT 0,
        stock_minimo INT NOT NULL DEFAULT 10,
        id_encargado INT NOT NULL,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (id_encargado) REFERENCES Encargado(id_encargado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE TABLE Productos (
        id_producto INT AUTO_INCREMENT PRIMARY KEY,
        nombre_producto VARCHAR(200) NOT NULL,
        precio_venta DECIMAL(10,2) NOT NULL,
        codigo_barras VARCHAR(50) UNIQUE,
        id_inventario INT NOT NULL,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_inventario) REFERENCES Inventario(id_inventario) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE TABLE Clientes (
        id_cliente INT AUTO_INCREMENT PRIMARY KEY,
        id_persona INT NOT NULL,
        puntos_acumulados INT DEFAULT 0,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_persona) REFERENCES Persona(id_persona) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE TABLE Ventas (
        id_venta INT AUTO_INCREMENT PRIMARY KEY,
        cantidad INT NOT NULL,
        total DECIMAL(10,2) NOT NULL,
        id_encargado INT NOT NULL,
        fecha_venta TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_encargado) REFERENCES Encargado(id_encargado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE INDEX idx_codigo_barras ON Productos(codigo_barras)",
    "CREATE INDEX idx_stock ON Inventario(stock_actual, stock_minimo)",
    "CREATE INDEX idx_fecha_venta ON Ventas(fecha_venta)",
    
    "INSERT INTO Persona (nombre, apellido, telefono, email) 
     VALUES ('Alfredo', 'Mendoza', '5551111111', 'alfredo.mendoza@superzito.com')",
    "INSERT INTO Encargado (id_persona, usuario, password) 
     VALUES (LAST_INSERT_ID(), 'alfredo', 'alfredo123')",
    
    "INSERT INTO Persona (nombre, apellido, telefono, email) 
     VALUES ('Daniel', 'Mendoza', '5552222222', 'daniel.mendoza@superzito.com')",
    "INSERT INTO Encargado (id_persona, usuario, password) 
     VALUES (LAST_INSERT_ID(), 'daniel', 'daniel123')",
    
    "INSERT INTO Persona (nombre, apellido, telefono, email) 
     VALUES ('Luis', 'Mendoza', '5553333333', 'luis.mendoza@superzito.com')",
    "INSERT INTO Trabajadores (id_persona, usuario, password) 
     VALUES (LAST_INSERT_ID(), 'luis', 'luis123')",
    
    "INSERT INTO Persona (nombre, apellido, telefono, email) 
     VALUES ('Blanca', 'García', '5554444444', 'blanca.garcia@superzito.com')",
    "INSERT INTO Trabajadores (id_persona, usuario, password) 
     VALUES (LAST_INSERT_ID(), 'blanca', 'blanca123')"
];

$success = 0;
$errors = 0;

foreach($queries as $i => $query) {
    $query = trim($query);
    if (!empty($query)) {
        try {
            $db->exec($query);
            $success++;
            echo "<div class='success'>✅ Query " . ($i + 1) . " ejecutado</div>";
        } catch(Exception $e) {
            $errors++;
            echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
        }
    }
}

echo "<hr><h2>📊 Resumen:</h2>";
echo "<div class='info'>✅ Exitosos: $success | ❌ Errores: $errors</div>";
echo "<hr><h3>👥 Usuarios creados:</h3>";
echo "<ul>";
echo "<li>👔 Encargado: <strong>alfredo</strong> / alfredo123</li>";
echo "<li>👔 Encargado: <strong>daniel</strong> / daniel123</li>";
echo "<li>👷 Trabajador: <strong>luis</strong> / luis123</li>";
echo "<li>👷 Trabajador: <strong>blanca</strong> / blanca123</li>";
echo "</ul>";
echo "<br><a href='test.php' style='background:#2196F3;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Verificar</a> ";
echo "<a href='login.php' style='background:#667eea;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Login</a>";
?>
```

---

## 🚀 Ejecuta:
```
https://inventariosuperzito-production.up.railway.app/init_db.php
