<?php
session_start();
require_once 'config.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nombre = $_SESSION['usuario_nombre'];
$mensaje = '';
$tipo_mensaje = '';

// Procesar venta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalizar_venta'])) {
    $carrito = json_decode($_POST['carrito'], true);
    
    if (!empty($carrito)) {
        try {
            $pdo->beginTransaction();
            
            // Calcular total
            $total = 0;
            foreach ($carrito as $item) {
                $total += floatval($item['precio']) * intval($item['cantidad']);
            }
            
            // Insertar venta
            $stmt = $pdo->prepare("INSERT INTO ventas (usuario_id, usuario_nombre, total, fecha_venta) VALUES (:usuario_id, :usuario_nombre, :total, NOW())");
            $stmt->execute([
                ':usuario_id' => $usuario_id,
                ':usuario_nombre' => $usuario_nombre,
                ':total' => $total
            ]);
            
            $venta_id = $pdo->lastInsertId();
            
            // Insertar detalle y actualizar stock
            $stmt_detalle = $pdo->prepare("
                INSERT INTO venta_detalle (venta_id, producto_id, cantidad, precio_unitario, subtotal) 
                VALUES (:venta_id, :producto_id, :cantidad, :precio_unitario, :subtotal)
            ");
            
            $stmt_stock = $pdo->prepare("UPDATE productos SET stock = stock - :cantidad WHERE id = :id");
            
            $stmt_historial = $pdo->prepare("
                INSERT INTO historial_stock (producto_id, tipo, cantidad, usuario_nombre, fecha) 
                VALUES (:producto_id, 'venta', :cantidad, :usuario_nombre, NOW())
            ");
            
            foreach ($carrito as $item) {
                $cantidad = intval($item['cantidad']);
                $precio = floatval($item['precio']);
                $producto_id = intval($item['id']);
                $subtotal = $precio * $cantidad;
                
                // Insertar detalle de venta con valores explícitos
                $stmt_detalle->execute([
                    ':venta_id' => $venta_id,
                    ':producto_id' => $producto_id,
                    ':cantidad' => $cantidad,
                    ':precio_unitario' => $precio,
                    ':subtotal' => $subtotal
                ]);
                
                // Actualizar stock
                $stmt_stock->execute([
                    ':cantidad' => $cantidad,
                    ':id' => $producto_id
                ]);
                
                // Registrar en historial
                $stmt_historial->execute([
                    ':producto_id' => $producto_id,
                    ':cantidad' => $cantidad,
                    ':usuario_nombre' => $usuario_nombre
                ]);
            }
            
            $pdo->commit();
            $mensaje = "✅ Venta realizada exitosamente. Total: $" . number_format($total, 2);
            $tipo_mensaje = 'success';
            
            // Redirigir al detalle de la venta
            echo "<script>
                alert('✅ Venta realizada exitosamente!\\nTotal: $" . number_format($total, 2) . "');
                window.location.href = 'detalle_venta.php?id=" . $venta_id . "';
            </script>";
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = "❌ Error al procesar venta: " . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje = "⚠️ El carrito está vacío";
        $tipo_mensaje = 'warning';
    }
}

// Buscar productos
$productos = [];
if (isset($_GET['buscar']) && !empty($_GET['buscar'])) {
    $buscar = '%' . $_GET['buscar'] . '%';
    $stmt = $pdo->prepare("SELECT * FROM productos WHERE (codigo_barras LIKE :buscar OR nombre LIKE :buscar) AND stock > 0 LIMIT 20");
    $stmt->execute([':buscar' => $buscar]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta - SCAV</title>
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
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            color: #667eea;
            font-size: 28px;
        }

        .user-info {
            color: #666;
            font-size: 14px;
        }

        .mensaje {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .mensaje.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .mensaje.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .mensaje.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 20px;
        }

        .seccion {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .seccion h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 22px;
        }

        .busqueda {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .busqueda input {
            flex: 1;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
        }

        .busqueda button {
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }

        .busqueda button:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .productos-lista {
            max-height: 500px;
            overflow-y: auto;
        }

        .producto-item {
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }

        .producto-item:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .producto-info h4 {
            color: #333;
            margin-bottom: 5px;
        }

        .producto-info p {
            color: #666;
            font-size: 14px;
        }

        .producto-precio {
            font-size: 20px;
            font-weight: bold;
            color: #667eea;
            margin-right: 15px;
        }

        .btn-agregar {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-agregar:hover {
            background: #45a049;
            transform: scale(1.05);
        }

        .carrito-item {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .carrito-item:last-child {
            border-bottom: none;
        }

        .item-info h4 {
            color: #333;
            margin-bottom: 5px;
            font-size: 16px;
        }

        .item-cantidad {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
        }

        .btn-cantidad {
            background: #667eea;
            color: white;
            width: 30px;
            height: 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
        }

        .btn-cantidad:hover {
            background: #5568d3;
        }

        .cantidad-input {
            width: 60px;
            text-align: center;
            padding: 5px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
        }

        .item-precio {
            text-align: right;
        }

        .precio-unitario {
            color: #666;
            font-size: 14px;
        }

        .precio-total {
            color: #667eea;
            font-size: 18px;
            font-weight: bold;
            margin-top: 5px;
        }

        .btn-eliminar {
            background: #ff6b6b;
            color: white;
            width: 30px;
            height: 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 18px;
            margin-left: 10px;
        }

        .btn-eliminar:hover {
            background: #ff5252;
        }

        .carrito-vacio {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .total-section {
            border-top: 2px solid #667eea;
            margin-top: 20px;
            padding-top: 20px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }

        .botones-carrito {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-finalizar {
            flex: 1;
            background: #4CAF50;
            color: white;
            padding: 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            transition: all 0.3s;
        }

        .btn-finalizar:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .btn-limpiar {
            background: #ff6b6b;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }

        .btn-limpiar:hover {
            background: #ff5252;
        }

        .btn-volver {
            background: #666;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            margin-top: 20px;
        }

        .btn-volver:hover {
            background: #555;
        }

        .stock-bajo {
            color: #ff6b6b;
            font-size: 12px;
            font-weight: bold;
        }

        @media (max-width: 1024px) {
            .layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .producto-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>💰 Punto de Venta</h1>
                <div class="user-info">👤 Vendedor: <strong><?php echo htmlspecialchars($usuario_nombre); ?></strong></div>
            </div>
            <a href="index.php" class="btn-volver">⬅️ Volver</a>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="layout">
            <!-- Panel de búsqueda y productos -->
            <div class="seccion">
                <h2>🔍 Buscar Productos</h2>
                <form method="GET" class="busqueda">
                    <input type="text" 
                           name="buscar" 
                           placeholder="Código de barras o nombre del producto..." 
                           value="<?php echo isset($_GET['buscar']) ? htmlspecialchars($_GET['buscar']) : ''; ?>"
                           autofocus>
                    <button type="submit">🔍 Buscar</button>
                </form>

                <div class="productos-lista">
                    <?php if (!empty($productos)): ?>
                        <?php foreach ($productos as $producto): ?>
                            <div class="producto-item">
                                <div class="producto-info">
                                    <h4><?php echo htmlspecialchars($producto['nombre']); ?></h4>
                                    <p>📦 Código: <?php echo htmlspecialchars($producto['codigo_barras']); ?></p>
                                    <p>Stock: <?php echo $producto['stock']; ?> 
                                        <?php if ($producto['stock'] <= $producto['stock_minimo']): ?>
                                            <span class="stock-bajo">⚠️ Stock bajo</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div style="display: flex; align-items: center;">
                                    <span class="producto-precio">$<?php echo number_format($producto['precio'], 2); ?></span>
                                    <button type="button" 
                                            class="btn-agregar" 
                                            onclick="agregarAlCarrito(<?php echo htmlspecialchars(json_encode($producto)); ?>)">
                                        ➕ Agregar
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <?php if (isset($_GET['buscar'])): ?>
                                😔 No se encontraron productos
                            <?php else: ?>
                                🔍 Usa el buscador para encontrar productos
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Carrito -->
            <div class="seccion">
                <h2>🛒 Carrito de Venta</h2>
                <div id="carrito">
                    <div class="carrito-vacio">
                        🛒 El carrito está vacío<br>
                        Agrega productos para comenzar
                    </div>
                </div>

                <div class="total-section" id="totalSection" style="display: none;">
                    <div class="total-row">
                        <span>TOTAL:</span>
                        <span id="totalCarrito">$0.00</span>
                    </div>
                </div>

                <form method="POST" id="formVenta">
                    <input type="hidden" name="carrito" id="carritoData">
                    <div class="botones-carrito" id="botonesCarrito" style="display: none;">
                        <button type="submit" name="finalizar_venta" class="btn-finalizar">
                            ✅ Finalizar Venta
                        </button>
                        <button type="button" class="btn-limpiar" onclick="limpiarCarrito()">
                            🗑️ Limpiar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let carrito = [];

        function agregarAlCarrito(producto) {
            const index = carrito.findIndex(item => item.id === producto.id);
            
            if (index !== -1) {
                if (carrito[index].cantidad < producto.stock) {
                    carrito[index].cantidad++;
                } else {
                    alert('⚠️ No hay suficiente stock disponible');
                    return;
                }
            } else {
                carrito.push({
                    id: producto.id,
                    nombre: producto.nombre,
                    precio: parseFloat(producto.precio),
                    cantidad: 1,
                    stock: producto.stock
                });
            }
            
            actualizarCarrito();
        }

        function cambiarCantidad(index, cambio) {
            const item = carrito[index];
            const nuevaCantidad = item.cantidad + cambio;
            
            if (nuevaCantidad > 0 && nuevaCantidad <= item.stock) {
                carrito[index].cantidad = nuevaCantidad;
                actualizarCarrito();
            } else if (nuevaCantidad > item.stock) {
                alert('⚠️ No hay suficiente stock disponible');
            }
        }

        function eliminarDelCarrito(index) {
            if (confirm('¿Eliminar este producto del carrito?')) {
                carrito.splice(index, 1);
                actualizarCarrito();
            }
        }

        function actualizarCarrito() {
            const carritoDiv = document.getElementById('carrito');
            const totalSection = document.getElementById('totalSection');
            const botonesCarrito = document.getElementById('botonesCarrito');
            const carritoData = document.getElementById('carritoData');
            
            if (carrito.length === 0) {
                carritoDiv.innerHTML = '<div class="carrito-vacio">🛒 El carrito está vacío<br>Agrega productos para comenzar</div>';
                totalSection.style.display = 'none';
                botonesCarrito.style.display = 'none';
                return;
            }
            
            let html = '';
            let total = 0;
            
            carrito.forEach((item, index) => {
                const subtotal = item.precio * item.cantidad;
                total += subtotal;
                
                html += `
                    <div class="carrito-item">
                        <div class="item-info">
                            <h4>${item.nombre}</h4>
                            <div class="item-cantidad">
                                <button type="button" class="btn-cantidad" onclick="cambiarCantidad(${index}, -1)">-</button>
                                <input type="number" class="cantidad-input" value="${item.cantidad}" min="1" max="${item.stock}" 
                                       onchange="actualizarCantidadDirecta(${index}, this.value)" readonly>
                                <button type="button" class="btn-cantidad" onclick="cambiarCantidad(${index}, 1)">+</button>
                                <button type="button" class="btn-eliminar" onclick="eliminarDelCarrito(${index})">🗑️</button>
                            </div>
                        </div>
                        <div class="item-precio">
                            <div class="precio-unitario">$${item.precio.toFixed(2)} c/u</div>
                            <div class="precio-total">$${subtotal.toFixed(2)}</div>
                        </div>
                    </div>
                `;
            });
            
            carritoDiv.innerHTML = html;
            document.getElementById('totalCarrito').textContent = '$' + total.toFixed(2);
            totalSection.style.display = 'block';
            botonesCarrito.style.display = 'flex';
            
            // Actualizar campo hidden con datos del carrito
            carritoData.value = JSON.stringify(carrito);
        }

        function actualizarCantidadDirecta(index, valor) {
            const cantidad = parseInt(valor);
            const item = carrito[index];
            
            if (cantidad > 0 && cantidad <= item.stock) {
                carrito[index].cantidad = cantidad;
                actualizarCarrito();
            } else {
                alert('⚠️ Cantidad no válida');
                actualizarCarrito();
            }
        }

        function limpiarCarrito() {
            if (confirm('¿Limpiar todo el carrito?')) {
                carrito = [];
                actualizarCarrito();
            }
        }

        // Validar antes de enviar
        document.getElementById('formVenta').addEventListener('submit', function(e) {
            if (carrito.length === 0) {
                e.preventDefault();
                alert('⚠️ El carrito está vacío');
                return false;
            }
            
            const total = carrito.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
            
            if (!confirm(`¿Finalizar venta por $${total.toFixed(2)}?`)) {
                e.preventDefault();
                return false;
            }
        });

        // Auto-focus en búsqueda al cargar
        window.addEventListener('load', function() {
            document.querySelector('input[name="buscar"]').focus();
        });
    </script>
</body>
</html>
