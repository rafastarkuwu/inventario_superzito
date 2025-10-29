<?php
require_once 'config.php';

echo "<h2>Estructura de tabla Encargado:</h2>";
$stmt = $pdo->query("DESCRIBE Encargado");
$columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($columnas);
echo "</pre>";
?>
