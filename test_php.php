<?php
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Listar todas las tablas
    $query = "SHOW TABLES";
    $stmt = $db->query($query);
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Tablas en la base de datos:</h2>";
    echo "<ul>";
    foreach($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
