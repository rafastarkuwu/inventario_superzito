<?php
// ARCHIVO TEMPORAL - EJECUTAR UNA VEZ Y LUEGO ELIMINAR
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("No se pudo conectar a la base de datos");
}

try {
    // Leer el archivo SQL (ajusta el nombre si usas schemma.sql)
    $sql = file_get_contents('schema.sql'); // o 'schemma.sql'
    
    if ($sql === false) {
        die("No se pudo leer schema.sql");
    }
    
    // Separar las consultas
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );
    
    echo "<h2>Ejecutando script de inicialización...</h2>";
    echo "<pre>";
    
    foreach ($statements as $statement) {
        if (trim($statement)) {
            try {
                $db->exec($statement);
                echo "✅ Ejecutado correctamente\n";
            } catch (PDOException $e) {
                echo "⚠️  " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n✅ Base de datos inicializada\n";
    echo "\nCredenciales de prueba:\n";
    echo "- Encargado: usuario='admin', password='admin123'\n";
    echo "- Trabajador: usuario='trabajador1', password='trabajador123'\n";
    echo "</pre>";
    
    echo "<h3 style='color: red;'>⚠️ ELIMINA init_db.php después de ejecutarlo</h3>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>
