<?php
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

echo "<style>
body { font-family: Arial; padding: 20px; background: #f5f5f5; }
.success { color: green; padding: 5px; }
.error { color: red; padding: 5px; background: #fee; border-left: 3px solid red; margin: 5px 0; }
.info { color: blue; padding: 5px; }
</style>";

echo "<h1>🚀 Ejecutando Schema SQL...</h1>";

// SQL Schema completo
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
    
    // DATOS DE PRUEBA - Alfredo Mendoza
    "INSERT INTO Persona (nombre, apellido, telefono, email) 
     VALUES ('Alfredo', 'Mendoza', '5551111111', 'alfredo.mendoza@superzito.com')",
    "INSERT INTO Encargado (id_persona, usuario, password) 
     VALUES (LAST_INSERT_ID(), 'alfredo', 'alfredo123')",
    
    // Daniel Mendoza
    "INSERT INTO Persona (nombre, apellido, telefono, email) 
     VALUES ('Daniel', 'Mendoza', '5552222222', 'daniel.mendoza@superzito.com')",
    "INSERT INTO Encargado (id_persona, usuario, password) 
     VALUES (LAST_INSERT_ID(), 'daniel', 'daniel123')",
    
    // Luis Mendoza
    "INSERT INTO Persona (nombre, apellido, telefono, email) 
     VALUES ('Luis', 'Mendoza', '5553333333', 'luis.mendoza@superzito.com')",
    "INSERT INTO Trabajadores (id_persona, usuario, password) 
     VALUES (LAST_INSERT_ID(), 'luis', 'luis123')",
    
    // Blanca García
    "INSERT INTO Persona (nombre, apellido, telefono, email) 
     VALUES ('Blanca', 'García', '5554444444', 'blanca.garcia@superzito.com')",
    "INSERT INTO Trabajadores (id_persona, usuario, password) 
     VALUES (LAST_INSERT_ID(), 'blanca', 'blanca123')"
];

$success_count = 0;
$error_count = 0;

foreach($queries as $index => $query) {
    $query = trim($query);
    if (!empty($query)) {
        try {
            $db->exec($query);
            $success_count++;
            $preview = substr($query, 0, 60);
            echo "<div class='success'>✅ Query " . ($index + 1) . ": $preview...</div>";
        } catch(Exception $e) {
            $error_count++;
            echo "<div class='error'>❌ Error en query " . ($index + 1) . ": " . $e->getMessage() . "</div>";
        }
    }
}

echo "<hr>";
echo "<h2>📊 Resumen:</h2>";
echo "<div class='info'>✅ Exitosos: $success_count</div>";
echo "<div class='info'>❌ Errores: $error_count</div>";
echo "<hr>";
echo "<h3>🎉 ¡Proceso completado!</h3>";
echo "<p><strong>Credenciales de acceso:</strong></p>";
echo "<ul>";
echo "<li>👔 Encargado 1: <strong>alfredo</strong> / <strong>alfredo123</strong></li>";
echo "<li>👔 Encargado 2: <strong>daniel</strong> / <strong>daniel123</strong></li>";
echo "<li>👷 Trabajador 1: <strong>luis</strong> / <strong>luis123</strong></li>";
echo "<li>👷 Trabajador 2: <strong>blanca</strong> / <strong>blanca123</strong></li>";
echo "</ul>";
echo "<br><a href='login.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Ir al Login</a>";
echo "<br><br><p style='color: red;'><strong>⚠️ IMPORTANTE: Elimina este archivo (ejecutar_schema.php) después de usarlo por seguridad.</strong></p>";
?>
