<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nombre = $_SESSION['usuario_nombre'];

// API para buscar producto por código (AJAX)
if (isset($_GET['api']) && $_GET['api'] === 'buscar' && isset($_GET['codigo'])) {
    header('Content-Type: application/json');
    
    $codigo = trim($_GET['codigo']);
    $stmt = $pdo->prepare("
        SELECT p.id_producto as id, p.nombre_producto as nombre, p.precio_venta as precio, p.codigo_barras, i.stock_actual as stock 
        FROM Productos p 
        INNER JOIN Inventario i ON p.id_inventario = i.id_inventario 
        WHERE p.codigo_barras = :codigo AND i.stock_actual > 0
    ");
    $stmt->execute([':codigo' => $codigo]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode($producto ? $producto : ['error' => 'No encontrado']);
    exit();
}

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
            
            // Insertar venta
            $stmt = $pdo->prepare("INSERT INTO Ventas (id_encargado, fecha_venta, total) VALUES (:id_encargado, NOW(), :total)");
            $stmt->execute([
                ':id_encargado' => $usuario_id,
                ':total' => $total
            ]);
            
            $venta_id = $pdo->lastInsertId();
            
            // Insertar detalle y actualizar stock
            foreach ($carrito as $item) {
                $stmt = $pdo->prepare("INSERT INTO Detalle_Venta (id_venta, id_producto, cantidad, precio_unitario) VALUES (:venta_id, :producto_id, :cantidad, :precio)");
                $stmt->execute([
                    ':venta_id' => $venta_id,
                    ':producto_id' => $item['id'],
                    ':cantidad' => $item['cantidad'],
                    ':precio' => $item['precio']
                ]);
                
                $stmt = $pdo->prepare("UPDATE Inventario SET stock_actual = stock_actual - :cantidad WHERE id_inventario = (SELECT id_inventario FROM Productos WHERE id_producto = :id)");
                $stmt->execute([
                    ':cantidad' => $item['cantidad'],
                    ':id' => $item['id']
                ]);
            }
            
            $pdo->commit();
            
            echo "<script>
                alert('✅ VENTA COBRADA\\n\\nTotal: $" . number_format($total, 2) . "\\nVenta #" . $venta_id . "');
                window.location.href = 'vender.php';
            </script>";
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caja Registradora</title>
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
        .header h1 { font-size: 24px; }
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
            text-align: center;
        }
        .scanner-icon {
            text-align: center;
            font-size: 48px;
            margin-bottom: 15px;
        }
        #scanInput {
            width: 100%;
            padding: 15px;
            border: 3px solid #667eea;
            border-radius: 8px;
            font-size: 20px;
            text-align: center;
            background: #f0f4ff;
        }
        .carrito-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            min-height: 300px;
            max-height: 500px;
            overflow-y: auto;
        }
        .carrito-item {
            display: flex;
            justify-content: space-between;
            padding: 15px;
            border-bottom: 1px solid #eee;
            align-items: center;
        }
        .item-nombre {
            font-weight: bold;
            color: #333;
            flex: 1;
        }
        .item-cantidad {
            background: #667eea;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin: 0 15px;
            min-width: 50px;
            text-align: center;
            font-size: 18px;
        }
        .item-precio {
            font-size: 20px;
            font-weight: bold;
            color: #667eea;
            min-width: 120px;
            text-align: right;
        }
        .btn-eliminar {
            background: #ff4444;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 10px;
            font-size: 16px;
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
        .btn-cobrar:hover { background: #45a049; }
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
        .beep {
            animation: beep 0.2s;
        }
        @keyframes beep {
            0%, 100% { background: white; }
            50% { background: #90EE90; }
        }
        .error-beep {
            animation: error 0.3s;
        }
        @keyframes error {
            0%, 100% { background: white; }
            50% { background: #ffcccc; }
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
            <div class="scanner-icon">🔫</div>
            <h2>Escanea los códigos de barras</h2>
            <input type="text" id="scanInput" placeholder="Listo para escanear..." autocomplete="off">
        </div>

        <div class="carrito-box">
            <h2 style="margin-bottom: 15px;">🛒 PRODUCTOS</h2>
            <div id="listaProductos">
                <div class="vacio">Escanea productos para comenzar</div>
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
        const scanInput = document.getElementById('scanInput');
        
        // Capturar escaneo
        scanInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const codigo = this.value.trim();
                
                if (codigo) {
                    buscarProducto(codigo);
                }
            }
        });

        async function buscarProducto(codigo) {
            try {
                const response = await fetch('vender.php?api=buscar&codigo=' + encodeURIComponent(codigo));
                const producto = await response.json();
                
                if (producto.error) {
                    hacerErrorBeep();
                    alert('⚠️ Producto no encontrado o sin stock');
                } else {
                    agregarProducto(producto);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al buscar producto');
            }
            
            // Limpiar y reenfocar
            scanInput.value = '';
            scanInput.focus();
        }

        function agregarProducto(producto) {
            const index = carrito.findIndex(p => p.id === producto.id);
            
            if (index !== -1) {
                // Ya existe, aumentar cantidad
                if (carrito[index].cantidad < producto.stock) {
                    carrito[index].cantidad++;
                    hacerBeep();
                } else {
                    hacerErrorBeep();
                    alert('⚠️ No hay más stock disponible');
                    return;
                }
            } else {
                // Nuevo producto
                carrito.push({
                    id: producto.id,
                    nombre: producto.nombre,
                    precio: parseFloat(producto.precio),
                    cantidad: 1,
                    stock: producto.stock
                });
                hacerBeep();
            }
            
            actualizarVista();
        }

        function hacerBeep() {
            const box = document.querySelector('.scanner-box');
            box.classList.add('beep');
            setTimeout(() => box.classList.remove('beep'), 200);
        }

        function hacerErrorBeep() {
            const box = document.querySelector('.scanner-box');
            box.classList.add('error-beep');
            setTimeout(() => box.classList.remove('error-beep'), 300);
        }

        function eliminarProducto(index) {
            carrito.splice(index, 1);
            actualizarVista();
            scanInput.focus();
        }

        function actualizarVista() {
            const lista = document.getElementById('listaProductos');
            const totalBox = document.getElementById('totalBox');
            const btnCobrar = document.getElementById('btnCobrar');
            const btnLimpiar = document.getElementById('btnLimpiar');
            
            if (carrito.length === 0) {
                lista.innerHTML = '<div class="vacio">Escanea productos para comenzar</div>';
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
                        <div class="item-cantidad">× ${item.cantidad}</div>
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
                scanInput.value = '';
                scanInput.focus();
            }
        }

        // Validar antes de cobrar
        document.getElementById('formVenta').addEventListener('submit', function(e) {
            const total = carrito.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
            if (!confirm('💵 COBRAR $' + total.toFixed(2) + '?')) {
                e.preventDefault();
            }
        });

        // Mantener foco
        window.addEventListener('load', () => scanInput.focus());
        setInterval(() => {
            if (document.activeElement !== scanInput) {
                scanInput.focus();
            }
        }, 1000);
    </script>
</body>
</html>
