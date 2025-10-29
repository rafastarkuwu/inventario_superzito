<?php
require_once 'config.php';

echo "<style>
body { font-family: Arial; padding: 20px; background: #f5f5f5; }
.success { color: green; padding: 10px; background: #d4edda; border-left: 4px solid #28a745; margin: 10px 0; }
.error { color: red; padding: 10px; background: #f8d7da; border-left: 4px solid #dc3545; margin: 10px 0; }
h1 { color: #667eea; }
</style>";

echo "<h1>🔄 Agregando columnas de métodos de pago...</h1>";

try {
    // Agregar columnas a la tabla Ventas
    $pdo->exec("ALTER TABLE Ventas 
        ADD COLUMN metodo_pago VARCHAR(20) DEFAULT 'efectivo',
        ADD COLUMN monto_recibido DECIMAL(10,2) NULL,
        ADD COLUMN cambio DECIMAL(10,2) NULL
    ");
    
    echo "<div class='success'>✅ Columnas agregadas correctamente</div>";
    echo "<hr>";
    echo "<h2>🎉 ¡Actualización completada!</h2>";
    echo "<br><a href='vender.php' style='background:#667eea;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Ir a Ventas</a>";
    echo "<br><br><p style='color:red;'><strong>⚠️ ELIMINA este archivo después de usarlo</strong></p>";
    
} catch(Exception $e) {
    echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
}
?>
