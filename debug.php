<?php
echo "<h1>Debug PHP</h1>";
echo "<p>Versión de PHP: " . phpversion() . "</p>";
echo "<p>Extensiones cargadas:</p>";
echo "<pre>";
print_r(get_loaded_extensions());
echo "</pre>";

echo "<h2>PDO Drivers disponibles:</h2>";
echo "<pre>";
if (extension_loaded('pdo')) {
    print_r(PDO::getAvailableDrivers());
} else {
    echo "PDO no está instalado";
}
echo "</pre>";

echo "<h2>Variables de entorno MySQL:</h2>";
echo "<pre>";
echo "MYSQLHOST: " . (getenv('MYSQLHOST') ? 'SI' : 'NO') . "\n";
echo "MYSQL_DATABASE: " . (getenv('MYSQL_DATABASE') ? 'SI' : 'NO') . "\n";
echo "MYSQLUSER: " . (getenv('MYSQLUSER') ? 'SI' : 'NO') . "\n";
echo "MYSQLPASSWORD: " . (getenv('MYSQLPASSWORD') ? 'SI' : 'NO') . "\n";
echo "MYSQLPORT: " . (getenv('MYSQLPORT') ? 'SI' : 'NO') . "\n";
echo "</pre>";
?>
