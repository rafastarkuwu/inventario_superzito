<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nombre = $_SESSION['usuario_nombre'];

// Procesar venta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalizar_venta'])) {
    $carrito = json_decode($_POST['carrito'], true);
    
    if (!empty($carrito)) {
        try {
            $pdo->beginTransaction();
            
            $total = 0;
            foreach ($carrito as $item) {
                $total += floatval($item['precio']) * intval($item['cantidad']);
            }
            
            // Insertar en tabla Ventas
            $stmt = $pdo->prepare("INSERT INTO Ventas (id_encargado, fecha_venta, total) VALUES (:id_encargado, NOW(), :total)");
            $stmt->execute([
                ':id_encargado' => $usuario_id,
                ':total' => $total
            ]);
            
            $venta_id = $pdo->lastInsertId();
            
            // Insertar detalle y actualizar inventario
            foreach ($carrito as $item) {
                // Insertar en Detalle_Venta
                $stmt = $pdo->prepare("INSERT INTO Detalle_Venta (id_venta, id_producto, cantidad, precio_unitario) VALUES (:venta_id, :producto_id, :cantidad, :precio)");
                $stmt->execute([
                    ':venta_id' => $venta_id,
                    ':producto_id' => $item['id'],
                    ':cantidad' => $item['cantidad'],
                    ':precio' => $item['precio']
                ]);
                
                // Actualizar stock en Inventario
                $stmt = $pdo->prepare("UPDATE Inventario SET stock_actual = stock_actual - :cantidad WHERE id_producto = :id");
                $stmt->execute([
                    ':cantidad' => $item['cantidad'],
                    ':id' => $item['id']
                ]);
            }
            
            $pdo->commit();
            
            echo "<script>
                alert('✅ VENTA COBRADA\\nTotal: $" . number_format($total, 2) . "\\nVenta #" . $venta_id . "');
                window.location.href = 'vender.php';
            </script>";
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Buscar producto por código
$producto = null;
if (isset($_GET['codigo']) && !empty($_GET['codigo'])) {
    $stmt = $pdo->prepare("
        SELECT p.*, i.stock_actual as stock 
        FROM Productos p 
        INNER JOIN Inventario i ON p.id_inventario = i.id_inventario 
        WHERE p.codigo_barras = :codigo AND i.stock_actual > 0
    ");
    $stmt->execute([':codigo' => $_GET['codigo']]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caja Registradora - SuperZito</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
        }
        .header {
            background: #667eea;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            font-size: 24px;
        }
        .btn-volver {
            background: white;
            color: #667eea;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .container {
            max-width: 900px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .scanner-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .scanner-box h2 {
            color: #333;
            margin-bottom: 15px;
        }
        .scanner-input {
            display: flex;
            gap: 10px;
        }
        .scanner-input input {
            flex: 1;
            padding: 15px;
            border: 3px solid #667eea;
            border-radius: 8px;
            font-size: 18px;
        }
        .scanner-input button {
            padding: 15px 30px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
        }
        .carrito-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            min-height: 300px;
        }
        .carrito-item {
            display: flex;
            justify-content: space-between;
            padding: 15px;
            border-bottom: 1px solid #eee;
            align-items: center;
        }
        .carrito-item:last-child {
            border-bottom: none;
        }
        .item-nombre {
            font-weight: bold;
            color: #333;
            flex: 1;
        }
        .item-cantidad {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 20px;
        }
        .btn-cant {
            width: 35px;
            height: 35px;
            border: none;
            background: #667eea;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
        }
        .cant-display {
            font-size: 20px;
            font-weight: bold;
            min-width: 40px;
            text-align: center;
        }
        .item-precio {
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
            min-width: 100px;
            text-align: right;
        }
        .btn-eliminar {
            background: #ff4444;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 15px;
        }
        .total-box {
            background: #667eea;
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-top: 20px;
            text-align: center;
        }
        .total-label {
            font-size: 20px;
            margin-bottom: 10px;
        }
        .total-amount {
            font-size: 48px;
            font-weight: bold;
        }
        .btn-cobrar {
            width: 100%;
            padding: 20px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 20px;
        }
        .btn-cobrar:hover {
            background: #45a049;
        }
        .btn-limpiar {
            width: 100%;
            padding: 15px;
            background: #ff4444;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
        }
        .vacio {
            text-align: center;
            padding: 60px;
            color: #999;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>💰 CAJA REGISTRADORA</h1>
            <div style="font-size: 14px;">Vendedor: <?php echo htmlspecialchars($usuario_nombre); ?></div>
        </div>
        <a href="index.php" class="btn-volver">← Volver</a>
    </div>

    <div class="container">
        <div class="scanner-box">
            <h2>🔫 Escanear Código de Barras</h2>
            <form method="GET" class="scanner-input" id="formScanner">
                <input type="text" 
                       name="codigo" 
                       id="codigoInput"
                       placeholder="Escanea o ingresa el código de barras..."
                       autofocus
                       autocomplete="off">
                <button type="submit">AGREGAR</button>
            </form>
        </div>

        <div class="carrito-box">
            <h2>🛒 PRODUCTOS</h2>
            <div id="listaProductos">
                <div class="vacio">
                    Escanea productos para agregarlos
                </div>
            </div>
        </div>

        <div class="total-box" id="totalBox" style="display: none;">
            <div class="total-label">TOTAL A PAGAR:</div>
            <div class="total-amount" id="totalMonto">$0.00</div>
        </div>

        <form method="POST" id="formVenta">
            <input type="hidden" name="carrito" id="carritoData">
            <button type="submit" name="finalizar_venta" class="btn-cobrar" id="btnCobrar" style="display: none;">
                💵 COBRAR
            </button>
        </form>
        
        <button type="button" class="btn-limpiar" id="btnLimpiar" style="display: none;" onclick="limpiarTodo()">
            🗑️ LIMPIAR TODO
        </button>
    </div>

    <script>
        let carrito = [];

        <?php if ($producto): ?>
            // Agregar producto escaneado automáticamente
            agregarProducto(<?php echo json_encode($producto); ?>);
            // Limpiar URL
            window.history.replaceState({}, document.title, 'vender.php');
            // Volver a poner foco en el scanner
            setTimeout(() => document.getElementById('codigoInput').focus(), 100);
        <?php endif; ?>

        function agregarProducto(producto) {
            const index = carrito.findIndex(p => p.id === producto.id_producto);
            
            if (index !== -1) {
                if (carrito[index].cantidad < producto.stock) {
                    carrito[index].cantidad++;
                } else {
                    alert('⚠️ No hay más stock disponible');
                    return;
                }
            } else {
                carrito.push({
                    id: producto.id_producto,
                    nombre: producto.nombre_producto,
                    precio: parseFloat(producto.precio_venta),
                    cantidad: 1,
                    stock: producto.stock
                });
            }
            
            actualizarVista();
        }

        function cambiarCantidad(index, delta) {
            const item = carrito[index];
            const nueva = item.cantidad + delta;
            
            if (nueva > 0 && nueva <= item.stock) {
                carrito[index].cantidad = nueva;
                actualizarVista();
            } else if (nueva > item.stock) {
                alert('⚠️ No hay suficiente stock');
            }
        }

        function eliminarProducto(index) {
            carrito.splice(index, 1);
            actualizarVista();
        }

        function actualizarVista() {
            const lista = document.getElementById('listaProductos');
            const totalBox = document.getElementById('totalBox');
            const btnCobrar = document.getElementById('btnCobrar');
            const btnLimpiar = document.getElementById('btnLimpiar');
            
            if (carrito.length === 0) {
                lista.innerHTML = '<div class="vacio">Escanea productos para agregarlos</div>';
                totalBox.style.display = 'none';
                btnCobrar.style.display = 'none';
                btnLimpiar.style.display = 'none';
                return;
            }
            
            let html = '';
            let total = 0;
            
            carrito.forEach((item, i) => {
                const subtotal = item.precio * item.cantidad;
                total += subtotal;
                
                html += `
                    <div class="carrito-item">
                        <div class="item-nombre">${item.nombre}</div>
                        <div class="item-cantidad">
                            <button class="btn-cant" onclick="cambiarCantidad(${i}, -1)">-</button>
                            <span class="cant-display">${item.cantidad}</span>
                            <button class="btn-cant" onclick="cambiarCantidad(${i}, 1)">+</button>
                        </div>
                        <div class="item-precio">$${subtotal.toFixed(2)}</div>
                        <button class="btn-eliminar" onclick="eliminarProducto(${i})">✕</button>
                    </div>
                `;
            });
            
            lista.innerHTML = html;
            document.getElementById('totalMonto').textContent = '$' + total.toFixed(2);
            document.getElementById('carritoData').value = JSON.stringify(carrito);
            totalBox.style.display = 'block';
            btnCobrar.style.display = 'block';
            btnLimpiar.style.display = 'block';
        }

        function limpiarTodo() {
            if (confirm('¿Limpiar toda la venta?')) {
                carrito = [];
                actualizarVista();
                document.getElementById('codigoInput').value = '';
                document.getElementById('codigoInput').focus();
            }
        }

        // Validar antes de cobrar
        document.getElementById('formVenta').addEventListener('submit', function(e) {
            const total = carrito.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
            if (!confirm('💵 COBRAR $' + total.toFixed(2) + '?')) {
                e.preventDefault();
            }
        });

        // Auto-focus en scanner después de agregar
        document.getElementById('formScanner').addEventListener('submit', function() {
            setTimeout(() => {
                document.getElementById('codigoInput').value = '';
                document.getElementById('codigoInput').focus();
            }, 500);
        });
    </script>
</body>
</html>
