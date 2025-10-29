<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';
$database = new Database();
$db = $database->getConnection();

$mensaje = '';
$tipo_mensaje = '';
$producto = null;

// Procesar escaneo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['codigo_barras'])) {
    $codigo = trim($_POST['codigo_barras']);
    
    try {
        // Buscar producto por código de barras
        $query = "SELECT p.*, i.stock_actual, i.stock_minimo, i.id_inventario
                 FROM Productos p 
                 JOIN Inventario i ON p.id_inventario = i.id_inventario 
                 WHERE p.codigo_barras = :codigo";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':codigo', $codigo);
        $stmt->execute();
        
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$producto) {
            $mensaje = "⚠️ Producto no encontrado con código: " . htmlspecialchars($codigo);
            $tipo_mensaje = 'warning';
        }
        
    } catch(Exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

// Procesar entrada de mercancía
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_stock'])) {
    $id_inventario = $_POST['id_inventario'];
    $cantidad = intval($_POST['cantidad']);
    
    try {
        $query = "UPDATE Inventario SET stock_actual = stock_actual + :cantidad WHERE id_inventario = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':cantidad', $cantidad);
        $stmt->bindParam(':id', $id_inventario);
        $stmt->execute();
        
        $mensaje = "✅ Se agregaron $cantidad unidades al inventario correctamente";
        $tipo_mensaje = 'success';
        $producto = null;
        
    } catch(Exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

// Procesar venta
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['vender'])) {
    $id_inventario = $_POST['id_inventario'];
    $id_producto = $_POST['id_producto'];
    $cantidad = intval($_POST['cantidad']);
    $precio = floatval($_POST['precio']);
    $stock_actual = intval($_POST['stock_actual']);
    
    if ($cantidad > $stock_actual) {
        $mensaje = "❌ No hay suficiente stock. Disponible: $stock_actual unidades";
        $tipo_mensaje = 'error';
    } else {
        try {
            $db->beginTransaction();
            
            // Restar del inventario
            $query = "UPDATE Inventario SET stock_actual = stock_actual - :cantidad WHERE id_inventario = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':cantidad', $cantidad);
            $stmt->bindParam(':id', $id_inventario);
            $stmt->execute();
            
            // Registrar venta
            $total = $cantidad * $precio;
            $query = "INSERT INTO Ventas (cantidad, total, id_encargado) VALUES (:cantidad, :total, :id_encargado)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':cantidad', $cantidad);
            $stmt->bindParam(':total', $total);
            $id_encargado = $_SESSION['id'];
            $stmt->bindParam(':id_encargado', $id_encargado);
            $stmt->execute();
            
            $db->commit();
            
            $mensaje = "✅ Venta registrada: $cantidad unidades por $" . number_format($total, 2);
            $tipo_mensaje = 'success';
            $producto = null;
            
        } catch(Exception $e) {
            $db->rollBack();
            $mensaje = "Error: " . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escáner de Códigos - Inventario_Superzito</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        h1 {
            color: #667eea;
            text-align: center;
            margin-bottom: 10px;
            font-size: 2.5em;
        }
        
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 1.1em;
        }
        
        .scanner-icon {
            text-align: center;
            font-size: 80px;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .scan-input {
            position: relative;
            margin-bottom: 30px;
        }
        
        .scan-input input {
            width: 100%;
            padding: 20px;
            font-size: 24px;
            border: 3px solid #667eea;
            border-radius: 15px;
            text-align: center;
            font-weight: bold;
            letter-spacing: 2px;
        }
        
        .scan-input input:focus {
            outline: none;
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 16px;
            animation: slideIn 0.3s;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success {
            background: #d4edda;
            border-left: 5px solid #28a745;
            color: #155724;
        }
        
        .alert-warning {
            background: #fff3cd;
            border-left: 5px solid #ffc107;
            color: #856404;
        }
        
        .alert-error {
            background: #f8d7da;
            border-left: 5px solid #dc3545;
            color: #721c24;
        }
        
        .producto-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            border: 3px solid #667eea;
        }
        
        .producto-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            background: white;
            padding: 15px;
            border-radius: 10px;
        }
        
        .info-item label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .info-item .value {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        
        .stock-alert {
            background: #dc3545;
            color: white;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .stock-ok {
            background: #28a745;
            color: white;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .action-form {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 2px solid #e0e0e0;
        }
        
        .action-form h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-back {
            display: inline-block;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background: #5a6268;
        }
        
        .instructions {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .instructions h3 {
            color: #2196F3;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .instructions ol {
            margin-left: 20px;
            color: #333;
        }
        
        .instructions li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="btn-back">← Volver al Dashboard</a>
    
    <div class="scanner-icon">🔫</div>
    <h1>Escáner de Códigos de Barras</h1>
    <p class="subtitle">Escanea el código para agregar o vender productos</p>
    
    <div class="instructions">
        <h3>📋 Instrucciones:</h3>
        <ol>
            <li>Haz clic en el campo de texto de abajo</li>
            <li>Escanea el código de barras con la pistola</li>
            <li>El producto aparecerá automáticamente</li>
            <li>Elige si quieres agregar stock o vender</li>
        </ol>
    </div>
    
    <?php if($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" id="scanForm">
        <div class="scan-input">
            <input 
                type="text" 
                name="codigo_barras" 
                id="codigoInput"
                placeholder="Escanea el código de barras aquí..."
                autofocus
                autocomplete="off">
        </div>
    </form>
    
    <?php if($producto): ?>
        <div class="producto-card">
            <h2 style="color: #667eea; margin-bottom: 20px;">
                📦 <?php echo htmlspecialchars($producto['nombre_producto']); ?>
            </h2>
            
            <?php if($producto['stock_actual'] <= $producto['stock_minimo']): ?>
                <div class="stock-alert">
                    ⚠️ STOCK BAJO - Requiere reabastecimiento
                </div>
            <?php else: ?>
                <div class="stock-ok">
                    ✅ Stock disponible
                </div>
            <?php endif; ?>
            
            <div class="producto-info">
                <div class="info-item">
                    <label>Código de Barras</label>
                    <div class="value"><?php echo htmlspecialchars($producto['codigo_barras']); ?></div>
                </div>
                <div class="info-item">
                    <label>Precio de Venta</label>
                    <div class="value">$<?php echo number_format($producto['precio_venta'], 2); ?></div>
                </div>
                <div class="info-item">
                    <label>Stock Actual</label>
                    <div class="value"><?php echo $producto['stock_actual']; ?> unidades</div>
                </div>
                <div class="info-item">
                    <label>Stock Mínimo</label>
                    <div class="value"><?php echo $producto['stock_minimo']; ?> unidades</div>
                </div>
            </div>
            
            <div class="actions">
                <!-- Agregar Stock -->
                <div class="action-form">
                    <h3>📥 Entrada de Mercancía</h3>
                    <form method="POST">
                        <input type="hidden" name="id_inventario" value="<?php echo $producto['id_inventario']; ?>">
                        <div class="form-group">
                            <label>Cantidad a agregar:</label>
                            <input type="number" name="cantidad" min="1" value="1" required>
                        </div>
                        <button type="submit" name="agregar_stock" class="btn btn-success">
                            ➕ Agregar al Inventario
                        </button>
                    </form>
                </div>
                
                <!-- Vender -->
                <div class="action-form">
                    <h3>💰 Registrar Venta</h3>
                    <form method="POST">
                        <input type="hidden" name="id_inventario" value="<?php echo $producto['id_inventario']; ?>">
                        <input type="hidden" name="id_producto" value="<?php echo $producto['id_producto']; ?>">
                        <input type="hidden" name="precio" value="<?php echo $producto['precio_venta']; ?>">
                        <input type="hidden" name="stock_actual" value="<?php echo $producto['stock_actual']; ?>">
                        <div class="form-group">
                            <label>Cantidad a vender:</label>
                            <input type="number" name="cantidad" min="1" max="<?php echo $producto['stock_actual']; ?>" value="1" required>
                        </div>
                        <button type="submit" name="vender" class="btn btn-primary">
                            💵 Vender Producto
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Auto-submit al escanear (la pistola envía Enter automáticamente)
document.getElementById('codigoInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('scanForm').submit();
    }
});

// Mantener el foco en el input
setInterval(function() {
    if (!document.querySelector('input[type="number"]:focus')) {
        document.getElementById('codigoInput').focus();
    }
}, 100);
</script>

</body>
</html>