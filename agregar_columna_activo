<?php
require_once 'config.php';

echo "<style>
body { font-family: Arial; padding: 20px; background: #f5f5f5; }
.success { color: green; padding: 10px; background: #d4edda; margin: 10px 0; }
</style>";

try {
    $pdo->exec("ALTER TABLE Productos ADD COLUMN activo TINYINT(1) DEFAULT 1");
    echo "<div class='success'>✅ Columna 'activo' agregada correctamente</div>";
    echo "<a href='gestionar_productos.php'>Ir a Gestionar Productos</a>";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
