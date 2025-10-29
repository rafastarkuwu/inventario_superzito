<?php
// Archivo de diagnóstico - ver_usuarios.php
require_once 'config.php';

echo "<h1>Diagnóstico de Usuarios</h1>";
echo "<style>body{font-family:Arial;padding:20px;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#667eea;color:white;}</style>";

try {
    // Ver todos los usuarios
    $stmt = $pdo->query("SELECT id, nombre, usuario, rol FROM usuarios");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>✅ Conexión a base de datos: OK</h2>";
    echo "<h3>Usuarios en la base de datos:</h3>";
    
    if (count($usuarios) > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Usuario</th><th>Rol</th></tr>";
        foreach ($usuarios as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . htmlspecialchars($user['nombre']) . "</td>";
            echo "<td><strong>" . htmlspecialchars($user['usuario']) . "</strong></td>";
            echo "<td>" . $user['rol'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<br><h3>📋 PRUEBA ESTOS USUARIOS:</h3>";
        echo "<ul>";
        foreach ($usuarios as $user) {
            echo "<li><strong>Usuario:</strong> " . htmlspecialchars($user['usuario']) . "</li>";
        }
        echo "</ul>";
        
    } else {
        echo "<p style='color:red;font-weight:bold;'>❌ NO HAY USUARIOS EN LA BASE DE DATOS</p>";
        echo "<p>Necesitas ejecutar el SQL para crear usuarios.</p>";
    }
    
    // Ver estructura de la tabla
    echo "<br><h3>Estructura de la tabla usuarios:</h3>";
    $stmt = $pdo->query("DESCRIBE usuarios");
    $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Campo</th><th>Tipo</th></tr>";
    foreach ($columnas as $col) {
        echo "<tr><td>" . $col['Field'] . "</td><td>" . $col['Type'] . "</td></tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color:red;font-weight:bold;'>❌ ERROR: " . $e->getMessage() . "</p>";
}

echo "<br><hr><p><strong>⚠️ IMPORTANTE:</strong> Borra este archivo después de usarlo por seguridad.</p>";
?>
