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

echo "<h1>🔐 Actualizando contraseñas...</h1>";

try {
    // Cambiar contraseña de Alfredo
    $query1 = "UPDATE Encargado SET password = 'Caballero1984' WHERE usuario = 'alfredo'";
    $db->exec($query1);
    echo "<div class='success'>✅ Contraseña de Alfredo actualizada a: <strong>Caballero1984</strong></div>";
    
    // Cambiar contraseña de Daniel
    $query2 = "UPDATE Encargado SET password = 'Superzito2025' WHERE usuario = 'daniel'";
    $db->exec($query2);
    echo "<div class='success'>✅ Contraseña de Daniel actualizada a: <strong>Superzito2025</strong></div>";
    
    echo "<hr>";
    echo "<h2>🎉 ¡Contraseñas actualizadas exitosamente!</h2>";
    echo "<p><strong>Nuevas credenciales:</strong></p>";
    echo "<ul>";
    echo "<li>👔 Alfredo: <code>alfredo</code> / <code>Caballero1984</code></li>";
    echo "<li>👔 Daniel: <code>daniel</code> / <code>Superzito2025</code></li>";
    echo "</ul>";
    echo "<br><a href='login.php' style='background:#667eea;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>🚀 Ir al Login</a>";
    echo "<br><br><p style='color:red;'><strong>⚠️ ELIMINA este archivo después de usarlo</strong></p>";
    
} catch(Exception $e) {
    echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
}
?>
