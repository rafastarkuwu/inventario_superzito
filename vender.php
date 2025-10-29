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

// Inicializar carrito en sesión
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Procesar escaneo y agregar al carrito
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
        
        if ($producto) {
            // Verificar si ya está en el carrito
            $encontrado = false;
            foreach ($_SESSION['carrito'] as &$item) {
                if ($item['id_producto'] == $producto['id_producto']) {
                    if ($item['cantidad'] < $producto['stock_actual']) {
                        $item['cantidad']++;
                        $mensaje = "✅ Cantidad actualizada: " . htmlspecialchars($producto['nombre_producto']);
                        $tipo_mensaje = 'success';
                    } else {
                        $mensaje = "⚠️ No hay más stock disponible";
                        $tipo_mensaje = 'warning';
                    }
                    $encontrado = true;
                    break;
                }
            }
            
            if (!$encontrado) {
                $_SESSION['carrito'][] = [
                    'id_producto' => $producto['id_producto'],
                    'id_inventario' => $producto['id_inventario'],
                    'nombre' => $producto['nombre_producto'],
                    'precio' => $producto['precio_venta'],
                    'cantidad' => 1,
                    'stock_disponible' => $producto['stock_actual']
                ];
                $mensaje = "✅ Producto agregado: " . htmlspecialchars($producto['nombre_producto']);
                $tipo_mensaje = 'success';
            }
        } else {
            $mensaje = "⚠️ Producto no encontrado: " . htmlspecialchars($codigo);
            $tipo_mensaje = 'warning';
        }
    } catch(Exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

// Modificar cantidad
if (isset($_POST['modificar_cantidad'])) {
    $index = $_POST['index'];
    $nueva_cantidad = max(1, intval($_POST['cantidad']));
    
    if ($nueva_cantidad <= $_SESSION['carrito'][$index]['stock_disponible']) {
        $_SESSION['carrito'][$index]['cantidad'] = $nueva_cantidad;
        $mensaje = "✅ Cantidad actualizada";
        $tipo_mensaje = 'success';
    } else {
        $mensaje = "⚠️ Stock insuficiente";
        $tipo_mensaje = 'warning';
    }
}

// Eliminar producto del carrito
if (isset($_POST['eliminar_producto'])) {
    $index = $_POST['index'];
    array_splice($_SESSION['carrito'], $index, 1);
    $mensaje = "✅ Producto eliminado del carrito";
    $tipo_mensaje = 'success';
}

// Procesar venta
if (isset($_POST['finalizar_venta']) && count($_SESSION['carrito']) > 0) {
    try {
        $db->beginTransaction();
        
        // Calcular total
        $total = 0;
        foreach ($_SESSION['carrito'] as $item) {
            $total += $item['precio'] * $item['cantidad'];
        }
        
        // Insertar venta
        $query = "INSERT INTO Ventas (total, id_encargado) VALUES (:total, :id_encargado)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':total', $total);
        $id_encargado = $_SESSION['id'];
        $stmt->bindParam(':id_encargado', $id_encargado);
        $stmt->execute();
        
        $id_venta = $db->lastInsertId();
        
        // Insertar detalles y actualizar inventario
        foreach ($_SESSION['carrito'] as $item) {
            // Insertar detalle
            $subtotal = $item['precio'] * $item['cantidad'];
            $query = "INSERT INTO Detalle_Venta (id_venta, id_producto, cantidad, precio_unitario, subtotal) 
                     VALUES (:id_venta, :id_producto, :cantidad, :precio, :subtotal)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id_venta', $id_venta);
            $stmt->bindParam(':id_producto', $item['id_producto']);
            $stmt->bindParam(':cantidad', $item['cantidad']);
            $stmt->bindParam(':precio', $item['precio']);
            $stmt->bindParam(':subtotal', $subtotal);
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
        
        $mensaje = "✅ Venta registrada exitosamente. Total: $" . number_format($total, 2) . " - Ticket #" . $id_venta;
        $tipo_mensaje = 'success';
        
        // Limpiar carrito
        $_SESSION['carrito'] = [];
        
    } catch(Exception $e) {
        $db->rollBack();
        $mensaje = "Error al procesar venta: " . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

// Calcular total del carrito
$total_carrito = 0;
foreach ($_SESSION['carrito'] as $item) {
    $total_carrito += $item['precio'] * $item['cantidad'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta - SuperZito</title>
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
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        
        .panel {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        h1 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 2em;
        }
        
        h2 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.5em;
        }
        
        .scanner-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        
        .scanner-input input {
            width: 100%;
            padding: 15px;
            font-size: 20px;
            border: 3px solid #667eea;
            border-radius: 10px;
            text-align: center;
            font-weight: bold;
        }
        
        .scanner-input input:focus {
            outline: none;
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.4);
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
        
        .carrito-vacio {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .carrito-vacio .icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        .producto-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: center;
        }
        
        .producto-nombre {
            font-weight: bold;
            color: #333;
        }
        
        .cantidad-control {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .cantidad-control input {
            width: 60px;
            padding: 8px;
            text-align: center;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .resumen {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            margin-top: 20px;
        }
        
        .resumen-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .resumen-total {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 3px solid #667eea;
        }
        
        .btn-finalizar {
            width: 100%;
            padding: 20px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s;
        }
        
        .btn-finalizar:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(40, 167, 69, 0.4);
        }
        
        .btn-finalizar:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
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
        
        .btn-back:hover {
            background: #5a6268;
        }
        
        .precio {
            font-weight: bold;
            color: #28a745;
        }
        
        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
            }
            
            .producto-item {
                grid-template-columns: 1fr;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Panel izquierdo: Escaneo y productos -->
    <div class="panel">
        <a href="index.php" class="btn-back">← Volver al Dashboard</a>
        
        <h1>💰 Punto de Venta</h1>
        
        <div class="scanner-section">
            <h2>🔫 Escanear Producto</h2>
            <form method="POST" id="scanForm">
                <div class="scanner-input">
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
        
        <?php if($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <h2>🛒 Productos en el Carrito</h2>
        
        <?php if (count($_SESSION['carrito']) == 0): ?>
            <div class="carrito-vacio">
                <div class="icon">🛒</div>
                <p>El carrito está vacío</p>
                <p>Escanea un producto para comenzar</p>
            </div>
        <?php else: ?>
            <?php foreach ($_SESSION['carrito'] as $index => $item): ?>
                <div class="producto-item">
                    <div class="producto-nombre"><?php echo htmlspecialchars($item['nombre']); ?></div>
                    
                    <div class="precio">$<?php echo number_format($item['precio'], 2); ?></div>
                    
                    <form method="POST" class="cantidad-control">
                        <input type="hidden" name="index" value="<?php echo $index; ?>">
                        <input type="number" name="cantidad" value="<?php echo $item['cantidad']; ?>" 
                               min="1" max="<?php echo $item['stock_disponible']; ?>" required>
                        <button type="submit" name="modificar_cantidad" class="btn btn-primary">✓</button>
                    </form>
                    
                    <div class="precio">$<?php echo number_format($item['precio'] * $item['cantidad'], 2); ?></div>
                    
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="index" value="<?php echo $index; ?>">
                        <button type="submit" name="eliminar_producto" class="btn btn-danger">🗑️</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Panel derecho: Resumen y total -->
    <div class="panel">
        <h2>📊 Resumen de Venta</h2>
        
        <div class="resumen">
            <div class="resumen-item">
                <span>Productos:</span>
                <span><?php echo count($_SESSION['carrito']); ?></span>
            </div>
            
            <div class="resumen-item">
                <span>Unidades:</span>
                <span>
                    <?php 
                    $total_unidades = 0;
                    foreach ($_SESSION['carrito'] as $item) {
                        $total_unidades += $item['cantidad'];
                    }
                    echo $total_unidades;
                    ?>
                </span>
            </div>
            
            <div class="resumen-total">
                <div style="display: flex; justify-content: space-between;">
                    <span>TOTAL:</span>
                    <span>$<?php echo number_format($total_carrito, 2); ?></span>
                </div>
            </div>
        </div>
        
        <form method="POST">
            <button type="submit" name="finalizar_venta" class="btn-finalizar"
                    <?php echo count($_SESSION['carrito']) == 0 ? 'disabled' : ''; ?>>
                💵 FINALIZAR VENTA
            </button>
        </form>
        
        <div style="margin-top: 20px; text-align: center; color: #666;">
            <p>👤 Vendedor: <?php echo htmlspecialchars($_SESSION['nombre']); ?></p>
        </div>
    </div>
</div>

<script>
// Auto-submit al escanear
document.getElementById('codigoInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('scanForm').submit();
    }
});

// Mantener foco en input del escáner
setInterval(function() {
    if (!document.querySelector('input[type="number"]:focus')) {
        document.getElementById('codigoInput').focus();
    }
}, 100);

// Limpiar input después de escanear
window.addEventListener('load', function() {
    document.getElementById('codigoInput').value = '';
});
</script>

</body>
</html>
