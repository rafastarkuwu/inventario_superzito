<?php
echo "<style>
body { font-family: Arial; padding: 20px; background: #f5f5f5; }
h1 { color: #667eea; }
.info { background: #e7f3ff; padding: 10px; margin: 10px 0; border-left: 4px solid #2196F3; }
.success { background: #d4edda; padding: 10px; margin: 10px 0; border-left: 4px solid #28a745; color: #155724; }
.error { background: #f8d7da; padding: 10px; margin: 10px 0; border-left: 4px solid #dc3545; color: #721c24; }
</style>";

echo "<h1>🔍 Test de Conexión - Railway MySQL</h1>";

echo "<div class='info'>";
echo "<h2>Variables de entorno detectadas:</h2>";
echo "<strong>MYSQL_HOST:</strong> " . (getenv('MYSQL_HOST') ?: 'NO CONFIGURADO') . "<br>";
echo "<strong>MYSQL_DATABASE:</strong> " . (getenv('MYSQL_DATABASE') ?: 'NO CONFIGURADO') . "<br>";
echo "<strong>MYSQL_USER:</strong> " . (getenv('MYSQL_USER') ?: 'NO CONFIGURADO') . "<br>";
echo "<strong>MYSQL_PORT:</strong> " . (getenv('MYSQL_PORT') ?: 'NO CONFIGURADO') . "<br>";
echo "<strong>MYSQL_PASSWORD:</strong> " . (getenv('MYSQL_PASSWORD') ? '✅ Configurado' : '❌ NO CONFIGURADO') . "<br>";
echo "</div>";

echo "<hr>";

echo "<div class='info'>";
echo "<h2>Variables alternativas (sin guión bajo):</h2>";
echo "<strong>MYSQLHOST:</strong> " . (getenv('MYSQLHOST') ?: 'NO CONFIGURADO') . "<br>";
echo "<strong>MYSQLUSER:</strong> " . (getenv('MYSQLUSER') ?: 'NO CONFIGURADO') . "<br>";
echo "<strong>MYSQLPASSWORD:</strong> " . (getenv('MYSQLPASSWORD') ? '✅ Configurado' : '❌ NO CONFIGURADO') . "<br>";
echo "<strong>MYSQLPORT:</strong> " . (getenv('MYSQLPORT') ?: 'NO CONFIGURADO') . "<br>";
echo "</div>";

echo "<hr>";

try {
    require_once 'config.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if($db) {
        echo "<div class='success'>";
        echo "<h2>✅ Conexión exitosa a la base de datos!</h2>";
        echo "</div>";
        
        // Intentar listar tablas
        try {
            $query = "SHOW TABLES";
            $stmt = $db->query($query);
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo "<div class='info'>";
            echo "<h3>📊 Tablas en la base de datos (" . count($tables) . "):</h3>";
            if(count($tables) > 0) {
                echo "<ul>";
                foreach($tables as $table) {
                    echo "<li><strong>$table</strong></li>";
                }
                echo "</ul>";
            } else {
                echo "<p style='color: orange;'>⚠️ No hay tablas creadas aún. Necesitas ejecutar el schema.sql</p>";
            }
            echo "</div>";
        } catch(Exception $e) {
            echo "<div class='error'>";
            echo "<strong>Error al listar tablas:</strong> " . $e->getMessage();
            echo "</div>";
        }
        
    } else {
        echo "<div class='error'>";
        echo "<h2>❌ Error: No se pudo establecer conexión</h2>";
        echo "</div>";
    }
    
} catch(Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ Error al conectar:</h2>";
    echo "<p><strong>Mensaje:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Código:</strong> " . $e->getCode() . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='login.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ir al Login</a></p>";
?>
```

---

## 🚀 Pasos:

1. **Crea el archivo** `test.php` con el código de arriba
2. **Súbelo** a Railway (al mismo nivel que login.php)
3. **Accede** a:
```
   https://inventariosuperzito-production.up.railway.app/test.php
