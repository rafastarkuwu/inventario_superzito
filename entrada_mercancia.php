<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'encargado') {
    header("Location: index.php");
    exit();
}

$mensaje = '';
$tipo_mensaje = '';

// Procesar entrada de mercancía
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_stock'])) {
    $producto_id = intval($_POST['producto_id']);
    $cantidad = intval($_POST['cantidad']);
    
    if ($cantidad <= 0) {
        $mensaje = "La cantidad debe ser mayor a 0";
        $tipo_mensaje = 'error';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Obtener id_inventario del producto
            $stmt = $pdo->prepare("SELECT id_inventario FROM Productos WHERE id_producto = :id");
            $stmt->execute([':id' => $producto_id]);
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($producto) {
                // Actualizar stock en Inventario
                $stmt = $pdo->prepare("UPDATE Inventario SET stock_actual = stock_actual + :cantidad WHERE id_inventario = :id_inv");
                $stmt->execute([
                    ':cantidad' => $cantidad,
                    ':id_inv' => $producto['id_inventario']
                ]);
                
                $pdo->commit();
                $mensaje = "✅ Stock actualizado: +$cantidad unidades";
                $tipo_mensaje = 'success';
            } else {
                $mensaje = "❌ Producto no encontrado";
                $tipo_mensaje = 'error';
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = "Error: " . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    }
}

// Buscar productos
$productos = [];
$busqueda = '';
if (isset($_GET['buscar'])) {
    $busqueda = trim($_GET['buscar']);
    if (!empty($busqueda)) {
        $termino = '%' . $busqueda . '%';
        $stmt = $pdo->prepare("
            SELECT p.id_producto, p.nombre_producto, p.codigo_barras, p.precio_venta, i.stock_actual, i.stock_minimo
            FROM Productos p
            INNER JOIN Inventario i ON p.id_inventario = i.id_inventario
            WHERE p.nombre_producto LIKE :termino OR p.codigo_barras LIKE :termino
            ORDER BY p.nombre_producto
            LIMIT 50
        ");
        $stmt->execute([':termino' => $termino]);
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrada de Mercancía</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            color: #4CAF50;
            font-size: 28px;
        }
        .btn-volver {
            background: #666;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
        }
        .mensaje {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .mensaje.success {
            background: #d4edda;
            color: #155724;
        }
        .mensaje.error {
            background: #f8d7da;
            color: #721c24;
        }
        .busqueda-box {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .busqueda-box h2 {
            margin-bottom: 15px;
            color: #333;
        }
        .busqueda-form {
            display: flex;
            gap: 10px;
        }
        .busqueda-form input {
            flex: 1;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .busqueda-form button {
            padding: 12px 30px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
        }
        .productos-box {
            background: white;
            padding: 25px;
            border-radius: 10px;
        }
        .producto-item {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 150px;
            gap: 15px;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 15px;
            align-items: center;
        }
        .producto-item:hover {
            border-color: #4CAF50;
            background: #f0fff4;
        }
        .producto-info h3 {
            color: #333;
            margin-bottom: 5px;
        }
        .producto-info p {
            color: #666;
            font-size: 14px;
        }
        .stock-info {
            text-align: center;
        }
        .stock-actual {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
        }
        .stock-label {
            font-size: 12px;
            color: #666;
        }
        .stock-bajo {
            color: #ff4444 !important;
        }
        .precio-info {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            color: #667eea;
        }
        .btn-agregar-stock {
            background: #4CAF50;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        .btn-agregar-stock:hover {
            background: #45a049;
        }
        .vacio {
            text-align: center;
            padding: 60px;
            color: #999;
            font-size: 18px;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal-content {
            background: white;
            max-width: 500px;
            margin: 100px auto;
            padding: 30px;
            border-radius: 10px;
        }
        .modal-content h2 {
            color: #4CAF50;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .modal-buttons {
            display: flex;
            gap: 10px;
        }
        .modal-buttons button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
        .btn-confirmar {
            background: #4CAF50;
            color: white;
        }
        .btn-cancelar {
            background: #666;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>📦 Entrada de Mercancía</h1>
                <p style="color: #666;">Aumentar stock de productos</p>
            </div>
            <a href="index.php" class="btn-volver">← Volver</a>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="busqueda-box">
            <h2>🔍 Buscar Producto</h2>
            <form method="GET" class="busqueda-form">
                <input type="text" name="buscar" placeholder="Nombre o código de barras..." 
                       value="<?php echo htmlspecialchars($busqueda); ?>" autofocus>
                <button type="submit">Buscar</button>
            </form>
        </div>

        <div class="productos-box">
            <?php if (!empty($productos)): ?>
                <?php foreach ($productos as $prod): ?>
                    <div class="producto-item">
                        <div class="producto-info">
                            <h3><?php echo htmlspecialchars($prod['nombre_producto']); ?></h3>
                            <p>📦 Código: <?php echo htmlspecialchars($prod['codigo_barras']); ?></p>
                        </div>
                        
                        <div class="stock-info">
                            <div class="stock-actual <?php echo ($prod['stock_actual'] <= $prod['stock_minimo']) ? 'stock-bajo' : ''; ?>">
                                <?php echo $prod['stock_actual']; ?>
                            </div>
                            <div class="stock-label">Stock Actual</div>
                        </div>
                        
                        <div class="precio-info">
                            $<?php echo number_format($prod['precio_venta'], 2); ?>
                        </div>
                        
                        <button class="btn-agregar-stock" onclick="abrirModal(<?php echo $prod['id_producto']; ?>, '<?php echo htmlspecialchars($prod['nombre_producto']); ?>')">
                            ➕ Agregar Stock
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="vacio">
                    <?php if (!empty($busqueda)): ?>
                        😔 No se encontraron productos
                    <?php else: ?>
                        🔍 Busca un producto para agregar stock
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal" id="modalStock">
        <div class="modal-content">
            <h2>📦 Agregar Stock</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Producto:</label>
                    <input type="text" id="modalProductoNombre" readonly>
                </div>
                
                <div class="form-group">
                    <label>Cantidad a agregar:</label>
                    <input type="number" name="cantidad" id="modalCantidad" min="1" required autofocus>
                </div>
                
                <input type="hidden" name="producto_id" id="modalProductoId">
                
                <div class="modal-buttons">
                    <button type="submit" name="agregar_stock" class="btn-confirmar">✅ Confirmar</button>
                    <button type="button" class="btn-cancelar" onclick="cerrarModal()">❌ Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModal(id, nombre) {
            document.getElementById('modalProductoId').value = id;
            document.getElementById('modalProductoNombre').value = nombre;
            document.getElementById('modalCantidad').value = '';
            document.getElementById('modalStock').style.display = 'block';
            setTimeout(() => document.getElementById('modalCantidad').focus(), 100);
        }

        function cerrarModal() {
            document.getElementById('modalStock').style.display = 'none';
        }

        // Cerrar modal al hacer click fuera
        window.onclick = function(event) {
            const modal = document.getElementById('modalStock');
            if (event.target == modal) {
                cerrarModal();
            }
        }
    </script>
</body>
</html>
