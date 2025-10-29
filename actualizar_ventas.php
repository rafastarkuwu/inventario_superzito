<?php
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

echo "<style>
body { font-family: Arial; padding: 20px; background: #f5f5f5; }
.success { color: green; padding: 10px; background: #d4edda; border-left: 4px solid #28a745; margin: 10px 0; }
.error { color: red; padding: 10px; background: #f8d7da; border-left: 4px solid #dc3545; margin: 10px 0; }
h1 { color: #667eea; }
</style>";

echo "<h1>🔄 Actualizando estructura de ventas...</h1>";

try {
    // Crear tabla detalle de ventas
    $query1 = "CREATE TABLE IF NOT EXISTS Detalle_Venta (
        id_detalle INT AUTO_INCREMENT PRIMARY KEY,
        id_venta INT NOT NULL,
        id_producto INT NOT NULL,
        cantidad INT NOT NULL,
        precio_unitario DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (id_venta) REFERENCES Ventas(id_venta) ON DELETE CASCADE,
        FOREIGN KEY (id_producto) REFERENCES Productos(id_producto)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($query1);
    echo "<div class='success'>✅ Tabla Detalle_Venta creada</div>";
    
    // Agregar columna fecha_hora a Ventas si no existe
    $query2 = "ALTER TABLE Ventas MODIFY COLUMN fecha_venta TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    $db->exec($query2);
    echo "<div class='success'>✅ Tabla Ventas actualizada</div>";
    
    echo "<hr>";
    echo "<h2>🎉 ¡Base de datos actualizada exitosamente!</h2>";
    echo "<br><a href='login.php' style='background:#667eea;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Ir al Login</a>";
    echo "<br><br><p style='color:red;'><strong>⚠️ ELIMINA este archivo después de usarlo</strong></p>";
    
} catch(Exception $e) {
    echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
}
?>
