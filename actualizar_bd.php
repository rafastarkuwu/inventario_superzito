<?php
require_once 'config.php';

echo "<style>
body { font-family: Arial; padding: 20px; background: #f5f5f5; }
.success { color: green; padding: 10px; background: #d4edda; border-left: 4px solid #28a745; margin: 10px 0; }
.error { color: red; padding: 10px; background: #f8d7da; border-left: 4px solid #dc3545; margin: 10px 0; }
h1 { color: #667eea; }
</style>";

echo "<h1>🔄 Actualizando estructura de base de datos...</h1>";

try {
    // Eliminar tabla Ventas antigua
    $pdo->exec("DROP TABLE IF EXISTS Venta_Detalle");
    echo "<div class='success'>✅ Tabla Venta_Detalle eliminada</div>";
    
    $pdo->exec("DROP TABLE IF EXISTS Ventas");
    echo "<div class='success'>✅ Tabla Ventas eliminada</div>";
    
    // Crear nueva tabla Ventas (cabecera)
    $query = "CREATE TABLE Ventas (
        id_venta INT AUTO_INCREMENT PRIMARY KEY,
        total DECIMAL(10,2) NOT NULL,
        id_encargado INT NOT NULL,
        fecha_venta TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        estado VARCHAR(20) DEFAULT 'completada',
        FOREIGN KEY (id_encargado) REFERENCES Encargado(id_encargado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($query);
    echo "<div class='success'>✅ Tabla Ventas creada</div>";
    
    // Crear tabla Venta_Detalle (productos vendidos)
    $query = "CREATE TABLE Venta_Detalle (
        id_detalle INT AUTO_INCREMENT PRIMARY KEY,
        id_venta INT NOT NULL,
        id_producto INT NOT NULL,
        cantidad INT NOT NULL,
        precio_unitario DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (id_venta) REFERENCES Ventas(id_venta) ON DELETE CASCADE,
        FOREIGN KEY (id_producto) REFERENCES Productos(id_producto)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($query);
    echo "<div class='success'>✅ Tabla Venta_Detalle creada</div>";
    
    // Crear tabla Cierre_Caja
    $query = "CREATE TABLE Cierre_Caja (
        id_cierre INT AUTO_INCREMENT PRIMARY KEY,
        id_encargado INT NOT NULL,
        fecha_apertura TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_cierre TIMESTAMP NULL,
        monto_inicial DECIMAL(10,2) DEFAULT 0,
        monto_ventas DECIMAL(10,2) DEFAULT 0,
        monto_final DECIMAL(10,2) DEFAULT 0,
        estado VARCHAR(20) DEFAULT 'abierta',
        FOREIGN KEY (id_encargado) REFERENCES Encargado(id_encargado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($query);
    echo "<div class='success'>✅ Tabla Cierre_Caja creada</div>";
    
    echo "<hr>";
    echo "<h2>🎉 ¡Base de datos actualizada correctamente!</h2>";
    echo "<br><a href='index.php' style='background:#667eea;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Volver al Dashboard</a>";
    echo "<br><br><p style='color:red;'><strong>⚠️ ELIMINA este archivo después de usarlo</strong></p>";
    
} catch(Exception $e) {
    echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
}
?>
```
https://inventariosuperzito-production.up.railway.app/actualizar_bd.php
