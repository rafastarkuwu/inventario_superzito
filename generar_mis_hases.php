<?php
// GENERADOR DE HASHES PARA TUS ENCARGADOS
// Sube este archivo, ejecútalo UNA VEZ, y bórralo

echo "<h1>Hashes para tus encargados</h1>";
echo "<style>body{font-family:Arial;padding:20px;background:#f0f0f0;} .hash{background:white;padding:15px;margin:10px 0;border-radius:8px;word-break:break-all;}</style>";

// Encargado 1: alfredo / Caballero1984
$hash1 = password_hash('Caballero1984', PASSWORD_DEFAULT);
echo "<div class='hash'>";
echo "<h3>Encargado 1 - Alfredo</h3>";
echo "<strong>Usuario:</strong> alfredo<br>";
echo "<strong>Contraseña:</strong> Caballero1984<br>";
echo "<strong>Hash:</strong> <code>$hash1</code>";
echo "</div>";

// Encargado 2: daniel / Superzito2025
$hash2 = password_hash('Superzito2025', PASSWORD_DEFAULT);
echo "<div class='hash'>";
echo "<h3>Encargado 2 - Daniel</h3>";
echo "<strong>Usuario:</strong> daniel<br>";
echo "<strong>Contraseña:</strong> Superzito2025<br>";
echo "<strong>Hash:</strong> <code>$hash2</code>";
echo "</div>";

echo "<hr><h2>SQL para copiar:</h2>";
echo "<textarea style='width:100%;height:200px;font-family:monospace;padding:15px;'>";
echo "-- Encargado 1\n";
echo "INSERT INTO usuarios (nombre, usuario, password, rol) VALUES ('Alfredo', 'alfredo', '$hash1', 'encargado');\n\n";
echo "-- Encargado 2\n";
echo "INSERT INTO usuarios (nombre, usuario, password, rol) VALUES ('Daniel', 'daniel', '$hash2', 'encargado');\n";
echo "</textarea>";

echo "<hr><p style='color:red;font-weight:bold;'>⚠️ BORRA ESTE ARCHIVO DESPUÉS DE USARLO</p>";
?>
