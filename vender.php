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

// Inicializar carrito
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Buscar producto por código de barras
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['codigo_barras'])) {
    $codigo = trim($_POST['codigo_barras']);
    
    try {
        $query = "SELECT p.*, i.stock_actual, i.id_inventario
                 FROM Productos p 
                 JOIN Inventario i ON p.id_inventario = i.id_inventario 
                 WHERE p.codigo_barras = :codigo";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':codigo', $codigo);
        $stmt->execute();
        
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$producto) {
            $mensaje = "⚠️ Producto no encontrado: " . htmlspecialchars($codigo);
            $tipo_mensaje = 'warning';
        }
    } catch(Exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

// Agregar al carrito
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_carrito'])) {
    $id_producto = $_POST['id_producto'];
    $cantidad = intval($_POST['cantidad']);
    $nombre = $_POST['nombre'];
    $precio = floatval($_POST['precio']);
    $stock = intval($_POST['stock']);
    $id_inventario = $_POST['id_inventario'];
    
    // Verificar stock disponible
    $cantidad_en_carrito = 0;
    foreach ($_SESSION['carrito'] as $item) {
        if ($item['id_producto'] == $id_producto) {
            $cantidad_en_carrito = $item['cantidad'];
        }
    }
    
    if (($cantidad + $cantidad_en_carrito) > $stock) {
        $mensaje = "❌ Stock insuficiente. Disponible: " . ($stock - $cantidad_en_carrito);
        $tipo_mensaje = 'error';
    } else {
        // Verificar si ya está en el carrito
        $encontrado = false;
        foreach ($_SESSION['carrito'] as &$item) {
            if ($item['id_producto'] == $id_producto) {
                $item['cantidad'] += $cantidad;
                $item['subtotal'] = $item['cantidad'] * $item['precio'];
                $encontrado = true;
                break;
            }
        }
        
        if (!$encontrado) {
            $_SESSION['carrito'][] = [
                'id_producto' => $id_producto,
                'id_inventario' => $id_inventario,
                'nombre' => $nombre,
                'precio' => $precio,
                'cantidad' => $cantidad,
                'subtotal' => $cantidad * $precio
            ];
        }
        
        $mensaje = "✅ $cantidad x $nombre agregado al carrito";
        $tipo_mensaje = 'success';
    }
    $producto = null;
}

// Eliminar del carrito
if (isset($_GET['eliminar'])) {
    $index = intval($_GET['eliminar']);
    if (isset($_SESSION['carrito'][$index])) {
        unset($_SESSION['carrito'][$index]);
        $_SESSION['carrito'] = array_values($_SESSION['carrito']); // Reindexar
        $mensaje = "✅ Producto eliminado del carrito";
        $tipo_mensaje = 'success';
    }
}

// Procesar venta
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['finalizar_venta'])) {
    if (empty($_SESSION['carrito'])) {
        $mensaje = "❌ El carrito está vacío";
        $tipo_mensaje = 'error';
    } else {
        try {
            $db->beginTransaction();
            
            // Calcular total
            $total = 0;
            $cantidad_total = 0;
            foreach ($_SESSION['carrito'] as $item) {
                $total += $item['subtotal'];
                $cantidad_total += $item['cantidad'];
            }
            
            // Insertar venta
            $query = "INSERT INTO Ventas (cantidad, total, id_encargado) 
                     VALUES (:cantidad, :total, :id_encargado)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':cantidad', $cantidad_total);
            $stmt->bindParam(':total', $total);
            $id_encargado = $_SESSION['id'];
            $stmt->bindParam(':id_encargado', $id_encargado);
            $stmt->execute();
            
            $id_venta = $db->lastInsertId();
            
            // Insertar detalle y actualizar inventario
            foreach ($_SESSION['carrito'] as $item) {
                // Insertar detalle
                $query = "INSERT INTO Detalle_Venta (id_venta, id_producto, cantidad, precio_unitario, subtotal)
                         VALUES (:id_venta, :id_producto, :cantidad, :precio, :subtotal)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id_venta', $id_venta);
                $stmt->bindParam(':id_producto', $item['id_producto']);
                $stmt->bindParam(':cantidad', $item['cantidad']);
                $stmt->bindParam(':precio', $item['precio']);
                $stmt->bindParam(':subtotal', $item['subtotal']);
                $stmt->execute();
                
                // Actualizar inventario
                $query = "UPDATE Inventario SET stock_actual = stock_actual - :cantidad 
                         WHERE id_inventario = :id_inventario";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':cantidad', $item['cantidad']);
                $stmt->bindParam(':id_inventario', $item['id_inventario']);
                $stmt->execute();
            }
            
            $db->commit();
            
            $mensaje = "✅ Venta #$id_venta completada. Total: $" . number_format($total, 2);
            $tipo_mensaje = 'success';
            $_SESSION['carrito'] = []; // Limpiar carrito
            
        } catch(Exception $e) {
            $db->rollBack();
            $mensaje = "❌ Error: " . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    }
}

// Calcular total del carrito
$total_carrito = 0;
foreach ($_SESSION['carrito'] as $item) {
    $total_carrito += $item['subtotal'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Ventas - SuperZito</title>
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
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 20px;
        }
        
        .panel {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        h1, h2 {
            color: #667eea;
            margin-bottom: 20px;
        }
        
        .scanner-section {
            margin-bottom: 30px;
        }
        
        .scan-input input {
            width: 100%;
            padding: 15px;
            font-size: 20px;
            border: 3px solid #667eea;
            border-radius: 10px;
            text-align: center;
            font-weight: bold;
        }
        
        .producto-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            border: 2px solid #667eea;
        }
        
        .producto-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .info-item {
            background: white;
            padding: 10px;
            border-radius: 8px;
        }
        
        .info-item label {
            font-size: 11px;
            color: #666;
            display: block;
            margin-bottom: 5px;
        }
        
        .info-item .value {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
            width: 100%;
            padding: 20px;
            font-size: 20px;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .btn-back {
            display: inline-block;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .carrito {
            position: sticky;
            top: 20px;
        }
        
        .carrito-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .carrito-header h2 {
            color: white;
            margin: 0;
        }
        
        .carrito-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #667eea;
        }
        
        .carrito-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .carrito-item-name {
            font-weight: bold;
            color: #333;
            flex: 1;
        }
        
        .carrito-item-details {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .carrito-item-subtotal {
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
            text-align: right;
        }
        
        .carrito-total {
            background: #28a745;
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: center;
        }
        
        .carrito-total-label {
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .carrito-total-amount {
            font-size: 36px;
            font-weight: bold;
        }
        
        .carrito-empty {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .carrito-empty-icon {
            font-size: 60px;
            margin-bottom: 10px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
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
        
        @media (max-width: 1024px) {
            .container {
                grid-template-columns: 1fr;
            }
            
            .carrito {
                position: relative;
                top: 0;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Panel izquierdo: Escáner -->
    <div class="panel">
        <a href="index.php" class="btn-back">← Volver al Dashboard</a>
        
        <h1>🔫 Sistema de Ventas</h1>
        
        <?php if($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <div class="scanner-section">
            <h2>Escanear Producto</h2>
            <form method="POST" id="scanForm">
                <div class="scan-input">
                    <input 
                        type="text" 
                        name="codigo_barras" 
                        id="codigoInput"
                        placeholder="Escanea el código de barras..."
                        autofocus
                        autocomplete="off">
                </div>
            </form>
        </div>
        
        <?php if($producto): ?>
            <div class="producto-card">
                <h3>📦 <?php echo htmlspecialchars($producto['nombre_producto']); ?></h3>
                
                <div class="producto-info">
                    <div class="info-item">
                        <label>Código</label>
                        <div class="value"><?php echo htmlspecialchars($producto['codigo_barras']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Precio</label>
                        <div class="value">$<?php echo number_format($producto['precio_venta'], 2); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Stock Disponible</label>
                        <div class="value"><?php echo $producto['stock_actual']; ?> unidades</div>
                    </div>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="id_producto" value="<?php echo $producto['id_producto']; ?>">
                    <input type="hidden" name="id_inventario" value="<?php echo $producto['id_inventario']; ?>">
                    <input type="hidden" name="nombre" value="<?php echo htmlspecialchars($producto['nombre_producto']); ?>">
                    <input type="hidden" name="precio" value="<?php echo $producto['precio_venta']; ?>">
                    <input type="hidden" name="stock" value="<?php echo $producto['stock_actual']; ?>">
                    
                    <div class="form-group">
                        <label>Cantidad:</label>
                        <input type="number" name="cantidad" min="1" max="<?php echo $producto['stock_actual']; ?>" value="1" required>
                    </div>
                    
                    <button type="submit" name="agregar_carrito" class="btn btn-primary">
                        ➕ Agregar al Carrito
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Panel derecho: Carrito -->
    <div class="carrito">
        <div class="panel">
            <div class="carrito-header">
                <h2>🛒 Carrito de Compra</h2>
                <div><?php echo count($_SESSION['carrito']); ?> productos</div>
            </div>
            
            <?php if (empty($_SESSION['carrito'])): ?>
                <div class="carrito-empty">
                    <div class="carrito-empty-icon">🛒</div>
                    <p>El carrito está vacío</p>
                    <p style="font-size: 12px; margin-top: 10px;">Escanea productos para agregarlos</p>
                </div>
            <?php else: ?>
                <?php foreach ($_SESSION['carrito'] as $index => $item): ?>
                    <div class="carrito-item">
                        <div class="carrito-item-header">
                            <div class="carrito-item-name"><?php echo htmlspecialchars($item['nombre']); ?></div>
                            <a href="?eliminar=<?php echo $index; ?>" class="btn btn-danger" onclick="return confirm('¿Eliminar este producto?')">🗑️</a>
                        </div>
                        <div class="carrito-item-details">
                            <span><?php echo $item['cantidad']; ?> x $<?php echo number_format($item['precio'], 2); ?></span>
                        </div>
                        <div class="carrito-item-subtotal">
                            $<?php echo number_format($item['subtotal'], 2); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="carrito-total">
                    <div class="carrito-total-label">TOTAL A PAGAR</div>
                    <div class="carrito-total-amount">$<?php echo number_format($total_carrito, 2); ?></div>
                </div>
                
                <form method="POST" onsubmit="return confirm('¿Confirmar venta por $<?php echo number_format($total_carrito, 2); ?>?')">
                    <button type="submit" name="finalizar_venta" class="btn btn-success">
                        💰 FINALIZAR VENTA
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Auto-submit al escanear
document.getElementById('codigoInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('scanForm').submit();
    }
});

// Mantener foco en el input
setInterval(function() {
    if (!document.querySelector('input[type="number"]:focus')) {
        document.getElementById('codigoInput').focus();
    }
}, 100);
</script>

</body>
</html>
