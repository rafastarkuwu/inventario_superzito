<?php
require_once 'config.php';

echo "<h1>Diagnóstico de Login</h1>";
echo "<style>body{font-family:Arial;padding:20px;background:#f0f0f0;} .box{background:white;padding:15px;margin:10px 0;border-radius:8px;} code{background:#f8f8f8;padding:2px 5px;border-radius:3px;}</style>";

try {
    // Probar conexión
    echo "<div class='box'><h3>✅ Conexión a BD: OK</h3></div>";
    
    // Ver estructura de Encargado
    echo "<div class='box'><h3>Tabla Encargado:</h3>";
    $stmt = $pdo->query("SELECT id_encargado, id_persona, usuario, password FROM Encargado");
    $encargados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;width:100%;'>";
    echo "<tr><th>ID</th><th>Usuario</th><th>Password</th></tr>";
    foreach ($encargados as $enc) {
        echo "<tr>";
        echo "<td>" . $enc['id_encargado'] . "</td>";
        echo "<td><code>" . htmlspecialchars($enc['usuario']) . "</code></td>";
        echo "<td><code>" . htmlspecialchars($enc['password']) . "</code></td>";
        echo "</tr>";
    }
    echo "</table></div>";
    
    // Ver estructura de Trabajadores
    echo "<div class='box'><h3>Tabla Trabajadores:</h3>";
    $stmt = $pdo->query("SELECT id_trabajador, id_persona, usuario, password FROM Trabajadores");
    $trabajadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;width:100%;'>";
    echo "<tr><th>ID</th><th>Usuario</th><th>Password</th></tr>";
    foreach ($trabajadores as $trab) {
        echo "<tr>";
        echo "<td>" . $trab['id_trabajador'] . "</td>";
        echo "<td><code>" . htmlspecialchars($trab['usuario']) . "</code></td>";
        echo "<td><code>" . htmlspecialchars($trab['password']) . "</code></td>";
        echo "</tr>";
    }
    echo "</table></div>";
    
    // Ver tabla Persona
    echo "<div class='box'><h3>Tabla Persona:</h3>";
    $stmt = $pdo->query("SELECT id_persona, nombre, apellidoP FROM Persona LIMIT 10");
    $personas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;width:100%;'>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Apellido</th></tr>";
    foreach ($personas as $per) {
        echo "<tr>";
        echo "<td>" . $per['id_persona'] . "</td>";
        echo "<td>" . htmlspecialchars($per['nombre']) . "</td>";
        echo "<td>" . htmlspecialchars($per['apellidoP']) . "</td>";
        echo "</tr>";
    }
    echo "</table></div>";
    
    // Probar login manualmente
    echo "<div class='box'><h3>Prueba de Login Manual:</h3>";
    
    $test_usuario = 'alfredo';
    $test_password = 'Caballero1984';
    
    echo "<p>Probando: usuario=<code>$test_usuario</code>, password=<code>$test_password</code></p>";
    
    $stmt = $pdo->prepare("SELECT id_encargado as id, id_persona, usuario, password FROM Encargado WHERE usuario = :usuario");
    $stmt->execute([':usuario' => $test_usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "<p>✅ Usuario encontrado en Encargado</p>";
        echo "<p>Password en BD: <code>" . htmlspecialchars($user['password']) . "</code></p>";
        echo "<p>Password ingresado: <code>" . htmlspecialchars($test_password) . "</code></p>";
        
        if ($user['password'] === $test_password) {
            echo "<p style='color:green;font-weight:bold;'>✅ CONTRASEÑA CORRECTA - Login debería funcionar</p>";
        } else {
            echo "<p style='color:red;font-weight:bold;'>❌ CONTRASEÑA INCORRECTA</p>";
            echo "<p>Comparación: '" . $user['password'] . "' vs '" . $test_password . "'</p>";
            echo "<p>Longitud BD: " . strlen($user['password']) . " | Longitud ingresada: " . strlen($test_password) . "</p>";
        }
    } else {
        echo "<p style='color:red;'>❌ Usuario NO encontrado</p>";
    }
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='box' style='background:#f8d7da;'>";
    echo "<h3>❌ ERROR:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "<hr><p style='color:red;font-weight:bold;'>⚠️ BORRA ESTE ARCHIVO DESPUÉS DE USARLO</p>";
?>
