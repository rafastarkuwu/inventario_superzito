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
if (isset($_GET['api']) && $_GET['api'] === 'buscar_codigo' && isset($_GET['codigo'])) {
    header('Content-Type: application/json');
    
    $codigo = trim($_GET['codigo']);
    $stmt = $pdo->prepare("
        SELECT p.id_producto as id, p.nombre_producto as nombre, p.precio_venta as precio, 
               p.codigo_barras, COALESCE(i.stock_actual, 999) as stock 
        FROM Productos p 
        LEFT JOIN Inventario i ON p.id_inventario = i.id_inventario 
        WHERE p.codigo_barras = :codigo
    ");
    $stmt->execute([':codigo' => $codigo]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode($producto ? $producto : ['error' => 'No encontrado']);
    exit();
}

// API para buscar productos por nombre (AJAX)
if (isset($_GET['api']) && $_GET['api'] === 'buscar_nombre' && isset($_GET['nombre'])) {
    header('Content-Type: application/json');
    
    $nombre = trim($_GET['nombre']);
    $termino = '%' . $nombre . '%';
    $stmt = $pdo->prepare("
        SELECT p.id_producto as id, p.nombre_producto as nombre, p.precio_venta as precio, 
               p.codigo_barras, COALESCE(i.stock_actual, 999) as stock 
        FROM Productos p 
        LEFT JOIN Inventario i ON p.id_inventario = i.id_inventario 
        WHERE p.nombre_producto LIKE :termino
        LIMIT 10
    ");
    $stmt->execute([':termino' => $termino]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($productos);
    exit();
}

// Procesar venta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalizar_venta'])) {
    $carrito = json_decode($_POST['carrito'], true);
    $metodo_pago = $_POST['metodo_pago'];
    $monto_recibido = isset($_POST['monto_recibido']) ? floatval($_POST['monto_recibido']) : null;
    $cambio = isset($_POST['cambio']) ? floatval($_POST['cambio']) : null;
    
    if (!empty($carrito)) {
        try {
            $pdo->beginTransaction();
            
            $total = 0;
            foreach ($carrito as $item) {
                $total += floatval($item['subtotal']);
            }
            
            // Insertar venta con método de pago
            $stmt = $pdo->prepare("
                INSERT INTO Ventas (id_encargado, fecha_venta, total, metodo_pago, monto_recibido, cambio) 
                VALUES (?, NOW(), ?, ?, ?, ?)
            ");
            $stmt->execute([$usuario_id, $total, $metodo_pago, $monto_recibido, $cambio]);
            
            $venta_id = $pdo->lastInsertId();
            
            // Insertar detalle
            foreach ($carrito as $item) {
                $stmt = $pdo->prepare("
                    INSERT INTO Venta_Detalle (id_venta, id_producto, cantidad, precio_unitario, subtotal) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $venta_id,
                    $item['id'],
                    $item['cantidad'],
                    $item['precio'],
                    $item['subtotal']
                ]);
                
                // Actualizar stock solo para productos unitarios
                if ($item['tipo'] === 'unitario') {
                    $stmt = $pdo->prepare("
                        UPDATE Inventario 
                        SET stock_actual = stock_actual - ? 
                        WHERE id_inventario = (
                            SELECT id_inventario 
                            FROM Productos 
                            WHERE id_producto = ?
                        )
                    ");
                    $stmt->execute([intval($item['cantidad']), $item['id']]);
                }
            }
            
            $pdo->commit();
            
            $mensaje = "✅ VENTA COBRADA\\n\\n";
            $mensaje .= "Total: $" . number_format($total, 2) . "\\n";
            $mensaje .= "Método: " . strtoupper($metodo_pago) . "\\n";
            if ($metodo_pago === 'efectivo' && $cambio > 0) {
                $mensaje .= "Recibido: $" . number_format($monto_recibido, 2) . "\\n";
                $mensaje .= "Cambio: $" . number_format($cambio, 2) . "\\n";
            }
            $mensaje .= "Venta #" . $venta_id;
            
            echo "<script>
                alert('$mensaje');
                window.location.href = 'vender.php';
            </script>";
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
        }
    }
}
?><?php echo '<!DOCTYPE html>
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
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }
        .scanner-box h2 {
            color: #333;
            margin-bottom: 10px;
            font-size: 18px;
        }
        .scanner-icon {
            text-align: center;
            font-size: 36px;
            margin-bottom: 10px;
        }
        #scanInput {
            width: 100%;
            padding: 12px;
            border: 3px solid #667eea;
            border-radius: 8px;
            font-size: 18px;
            text-align: center;
            background: #f0f4ff;
        }
        .buscar-box {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .buscar-box h2 {
            color: #333;
            margin-bottom: 10px;
            font-size: 18px;
        }
        .buscar-icon {
            text-align: center;
            font-size: 36px;
            margin-bottom: 10px;
        }
        #buscarInput {
            width: 100%;
            padding: 12px;
            border: 3px solid #4CAF50;
            border-radius: 8px;
            font-size: 18px;
        }
        .resultados-busqueda {
            margin-top: 15px;
            max-height: 200px;
            overflow-y: auto;
        }
        .resultado-item {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 8px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .resultado-item:hover {
            background: #e8f5e9;
            border-color: #4CAF50;
        }
        .resultado-nombre {
            font-weight: bold;
            color: #333;
        }
        .resultado-precio {
            color: #4CAF50;
            font-weight: bold;
            font-size: 18px;
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
            padding: 12px;
            border-bottom: 1px solid #eee;
            align-items: center;
            font-size: 14px;
        }
        .item-nombre {
            font-weight: bold;
            color: #333;
            flex: 1;
        }
        .item-detalle {
            color: #666;
            font-size: 12px;
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
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 8px;
            font-size: 14px;
        }
        .total-box {
            background: #667eea;
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-top: 20px;
            text-align: center;
        }
        .total-label { font-size: 20px; margin-bottom: 10px; }
        .total-amount { font-size: 48px; font-weight: bold; }
        
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
        .beep { animation: beep 0.2s; }
        @keyframes beep {
            0%, 100% { background: white; }
            50% { background: #90EE90; }
        }
        .error-beep { animation: error 0.3s; }
        @keyframes error {
            0%, 100% { background: white; }
            50% { background: #ffcccc; }
        }
        
        /* Modal estilos */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            max-width: 550px;
            width: 90%;
            padding: 35px;
            border-radius: 15px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-content h2 {
            color: #333;
            margin-bottom: 25px;
            text-align: center;
            font-size: 26px;
        }
        
        /* Métodos de pago */
        .metodos-pago {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 25px;
        }
        .metodo-btn {
            padding: 20px 10px;
            border: 3px solid #ddd;
            background: white;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        .metodo-btn:hover {
            border-color: #667eea;
            transform: translateY(-3px);
        }
        .metodo-btn.active {
            background: #667eea;
            border-color: #667eea;
            color: white;
        }
        .metodo-icon {
            font-size: 36px;
            margin-bottom: 8px;
        }
        .metodo-nombre {
            font-weight: bold;
            font-size: 14px;
        }
        
        /* Formularios de pago */
        .pago-content {
            display: none;
        }
        .pago-content.active {
            display: block;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
            font-size: 16px;
        }
        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 20px;
            text-align: center;
            font-weight: bold;
        }
        .form-group input:focus {
            border-color: #667eea;
            outline: none;
        }
        .precio-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 20px;
        }
        .precio-display .label {
            font-size: 16px;
            opacity: 0.9;
        }
        .precio-display .valor {
            font-size: 42px;
            font-weight: bold;
            margin-top: 8px;
        }
        .cambio-display {
            background: #d4edda;
            border: 3px solid #28a745;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin-top: 15px;
        }
        .cambio-display .label {
            font-size: 16px;
            color: #155724;
        }
        .cambio-display .valor {
            font-size: 36px;
            font-weight: bold;
            color: #28a745;
            margin-top: 8px;
        }
        .confirmacion-box {
            background: #fff3cd;
            border: 3px solid #ffc107;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
        }
        .confirmacion-box .icono {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .confirmacion-box .texto {
            font-size: 18px;
            color: #856404;
            margin-bottom: 15px;
        }
        .btn-confirmar-pago {
            width: 100%;
            padding: 18px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
        }
        .btn-confirmar-pago:hover {
            background: #218838;
        }
        .modal-buttons {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }
        .modal-buttons button {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 18px;
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
        
        /* Modal granel */
        .opcion-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .tab-btn {
            flex: 1;
            padding: 12px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
        }
        .tab-btn.active {
            background: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .precio-info {
            background: #f0f4ff;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }
        .precio-info .label {
            font-size: 14px;
            color: #666;
        }
        .precio-info .valor {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
            margin-top: 5px;
        }
    </style>
</head>'; ?><body>
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
            <h2>Escanear Código de Barras</h2>
            <input type="text" id="scanInput" placeholder="Escanea aquí..." autocomplete="off">
        </div>

        <div class="buscar-box">
            <div class="buscar-icon">🔍</div>
            <h2>Buscar Producto (A granel o manual)</h2>
            <input type="text" id="buscarInput" placeholder="Escribe el nombre del producto..." autocomplete="off">
            <div id="resultadosBusqueda" class="resultados-busqueda"></div>
        </div>

        <div class="carrito-box">
            <h2 style="margin-bottom: 15px;">🛒 PRODUCTOS</h2>
            <div id="listaProductos">
                <div class="vacio">Escanea o busca productos para comenzar</div>
            </div>
        </div>

        <div class="total-box" id="totalBox" style="display: none;">
            <div class="total-label">TOTAL A PAGAR:</div>
            <div class="total-amount" id="totalMonto">$0.00</div>
        </div>

        <button type="button" class="btn-cobrar" id="btnCobrar" style="display: none;" onclick="abrirModalPago()">
            💵 COBRAR
        </button>
        
        <button type="button" class="btn-limpiar" id="btnLimpiar" style="display: none;" onclick="limpiarTodo()">
            🗑️ LIMPIAR TODO
        </button>
    </div>

    <!-- Modal para cantidad/precio (granel) -->
    <div class="modal" id="modalGranel">
        <div class="modal-content">
            <h2 id="modalTitulo">🏪 Producto a Granel</h2>
            
            <div class="precio-info">
                <div class="label">Precio por kg/unidad:</div>
                <div class="valor" id="modalPrecioUnitario">$0.00</div>
            </div>

            <div class="opcion-tabs">
                <button type="button" class="tab-btn active" onclick="cambiarTab('cantidad')">📦 Por Cantidad</button>
                <button type="button" class="tab-btn" onclick="cambiarTab('precio')">💵 Por Precio</button>
            </div>

            <div id="tabCantidad" class="tab-content active">
                <div class="form-group">
                    <label>Cantidad (kg o unidades):</label>
                    <input type="number" id="inputCantidad" step="0.001" min="0.001" placeholder="Ej: 1.5">
                </div>
                <div class="precio-info" style="background: #e8f5e9;">
                    <div class="label">Total a pagar:</div>
                    <div class="valor" style="color: #4CAF50;" id="totalPorCantidad">$0.00</div>
                </div>
            </div>

            <div id="tabPrecio" class="tab-content">
                <div class="form-group">
                    <label>¿Cuánto llevó el cliente?</label>
                    <input type="number" id="inputPrecio" step="0.01" min="0.01" placeholder="Ej: 25.50">
                </div>
                <div class="precio-info" style="background: #fff3cd;">
                    <div class="label">Cantidad aproximada:</div>
                    <div class="valor" style="color: #ff9800;" id="cantidadPorPrecio">0 kg</div>
                </div>
            </div>

            <input type="hidden" id="modalProductoId">
            <input type="hidden" id="modalProductoNombre">
            <input type="hidden" id="modalProductoPrecio">

            <div class="modal-buttons">
                <button type="button" class="btn-confirmar" onclick="confirmarGranel()">✅ Agregar</button>
                <button type="button" class="btn-cancelar" onclick="cerrarModal('modalGranel')">❌ Cancelar</button>
            </div>
        </div>
    </div>

    <!-- Modal para método de pago -->
    <div class="modal" id="modalPago">
        <div class="modal-content">
            <h2>💳 Método de Pago</h2>
            
            <div class="precio-display">
                <div class="label">TOTAL A PAGAR</div>
                <div class="valor" id="totalPagoModal">$0.00</div>
            </div>

            <div class="metodos-pago">
                <div class="metodo-btn active" onclick="seleccionarMetodo('efectivo')">
                    <div class="metodo-icon">💵</div>
                    <div class="metodo-nombre">Efectivo</div>
                </div>
                <div class="metodo-btn" onclick="seleccionarMetodo('transferencia')">
                    <div class="metodo-icon">📱</div>
                    <div class="metodo-nombre">Transferencia</div>
                </div>
                <div class="metodo-btn" onclick="seleccionarMetodo('tarjeta')">
                    <div class="metodo-icon">💳</div>
                    <div class="metodo-nombre">Tarjeta</div>
                </div>
            </div>

            <!-- Contenido para EFECTIVO -->
            <div class="pago-content active" id="pagoEfectivo">
                <div class="form-group">
                    <label>💵 ¿Con cuánto paga el cliente?</label>
                    <input type="number" id="inputEfectivo" step="0.01" min="0" placeholder="0.00" oninput="calcularCambio()">
                </div>
                <div class="cambio-display" id="cambioDisplay" style="display: none;">
                    <div class="label">💰 CAMBIO A DAR:</div>
                    <div class="valor" id="valorCambio">$0.00</div>
                </div>
            </div>

            <!-- Contenido para TRANSFERENCIA -->
            <div class="pago-content" id="pagoTransferencia">
                <div class="confirmacion-box">
                    <div class="icono">📱</div>
                    <div class="texto">
                        <strong>Esperando transferencia...</strong><br>
                        Una vez que llegue la transferencia, presiona confirmar
                    </div>
                    <button type="button" class="btn-confirmar-pago" onclick="confirmarVenta()">
                        ✅ Transferencia Recibida
                    </button>
                </div>
            </div>

            <!-- Contenido para TARJETA -->
            <div class="pago-content" id="pagoTarjeta">
                <div class="confirmacion-box">
                    <div class="icono">💳</div>
                    <div class="texto">
                        <strong>Pase la tarjeta...</strong><br>
                        Una vez procesado el pago, presiona confirmar
                    </div>
                    <button type="button" class="btn-confirmar-pago" onclick="confirmarVenta()">
                        ✅ Pago Aprobado
                    </button>
                </div>
            </div>

            <div class="modal-buttons">
                <button type="button" class="btn-confirmar" id="btnConfirmarEfectivo" onclick="confirmarVenta()">
                    ✅ Confirmar
                </button>
                <button type="button" class="btn-cancelar" onclick="cerrarModal('modalPago')">
                    ❌ Cancelar
                </button>
            </div>
        </div>
    </div>

    <form method="POST" id="formVenta" style="display: none;">
        <input type="hidden" name="carrito" id="carritoData">
        <input type="hidden" name="metodo_pago" id="metodoPagoData">
        <input type="hidden" name="monto_recibido" id="montoRecibidoData">
        <input type="hidden" name="cambio" id="cambioData">
        <input type="hidden" name="finalizar_venta" value="1">
    </form>

    <script>
        let carrito = [];
        let totalVenta = 0;
        let metodoSeleccionado = 'efectivo';
        const scanInput = document.getElementById('scanInput');
        const buscarInput = document.getElementById('buscarInput');
        let tabActual = 'cantidad';
        
        // Capturar escaneo
        scanInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const codigo = this.value.trim();
                if (codigo) {
                    buscarPorCodigo(codigo);
                }
            }
        });

        // Buscar por nombre mientras escribe
        let timeoutBusqueda;
        buscarInput.addEventListener('input', function() {
            clearTimeout(timeoutBusqueda);
            const termino = this.value.trim();
            
            if (termino.length >= 2) {
                timeoutBusqueda = setTimeout(() => buscarPorNombre(termino), 300);
            } else {
                document.getElementById('resultadosBusqueda').innerHTML = '';
            }
        });

        async function buscarPorCodigo(codigo) {
            try {
                const response = await fetch('vender.php?api=buscar_codigo&codigo=' + encodeURIComponent(codigo));
                const producto = await response.json();
                
                if (producto.error) {
                    hacerErrorBeep();
                    alert('⚠️ Producto no encontrado');
                } else {
                    agregarProductoUnitario(producto);
                }
            } catch (error) {
                console.error('Error:', error);
            }
            
            scanInput.value = '';
            scanInput.focus();
        }

        async function buscarPorNombre(termino) {
            try {
                const response = await fetch('vender.php?api=buscar_nombre&nombre=' + encodeURIComponent(termino));
                const productos = await response.json();
                
                mostrarResultados(productos);
            } catch (error) {
                console.error('Error:', error);
            }
        }

        function mostrarResultados(productos) {
            const contenedor = document.getElementById('resultadosBusqueda');
            
            if (productos.length === 0) {
                contenedor.innerHTML = '<div style="padding:15px;text-align:center;color:#999;">No se encontraron productos</div>';
                return;
            }
            
            let html = '';
            productos.forEach(prod => {
                html += `
                    <div class="resultado-item" onclick='seleccionarProducto(${JSON.stringify(prod)})'>
                        <div>
                            <div class="resultado-nombre">${prod.nombre}</div>
                            <div style="font-size:12px;color:#666;">Stock: ${prod.stock}</div>
                        </div>
                        <div class="resultado-precio">$${parseFloat(prod.precio).toFixed(2)}/kg</div>
                    </div>
                `;
            });
            
            contenedor.innerHTML = html;
        }

        function seleccionarProducto(producto) {
            abrirModalGranel(producto);
            document.getElementById('resultadosBusqueda').innerHTML = '';
            buscarInput.value = '';
        }

        function abrirModalGranel(producto) {
            document.getElementById('modalProductoId').value = producto.id;
            document.getElementById('modalProductoNombre').value = producto.nombre;
            document.getElementById('modalProductoPrecio').value = producto.precio;
            document.getElementById('modalPrecioUnitario').textContent = '$' + parseFloat(producto.precio).toFixed(2);
            document.getElementById('modalTitulo').textContent = producto.nombre;
            
            // Limpiar inputs
            document.getElementById('inputCantidad').value = '';
            document.getElementById('inputPrecio').value = '';
            document.getElementById('totalPorCantidad').textContent = '$0.00';
            document.getElementById('cantidadPorPrecio').textContent = '0 kg';
            
            // Mostrar modal
            document.getElementById('modalGranel').classList.add('active');
            
            setTimeout(() => {
                if (tabActual === 'cantidad') {
                    document.getElementById('inputCantidad').focus();
                } else {
                    document.getElementById('inputPrecio').focus();
                }
            }, 100);
        }

        function cambiarTab(tab) {
            tabActual = tab;
            
            // Cambiar botones
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Cambiar contenido
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.add('active');
            
            // Focus en input correspondiente
            if (tab === 'cantidad') {
                document.getElementById('inputCantidad').focus();
            } else {
                document.getElementById('inputPrecio').focus();
            }
        }

        // Calcular total mientras escribe cantidad
        document.getElementById('inputCantidad').addEventListener('input', function() {
            const cantidad = parseFloat(this.value) || 0;
            const precio = parseFloat(document.getElementById('modalProductoPrecio').value);
            const total = cantidad * precio;
            document.getElementById('totalPorCantidad').textContent = '$' + total.toFixed(2);
        });

        // Calcular cantidad mientras escribe precio
        document.getElementById('inputPrecio').addEventListener('input', function() {
            const precioTotal = parseFloat(this.value) || 0;
            const precioUnitario = parseFloat(document.getElementById('modalProductoPrecio').value);
            const cantidad = precioTotal / precioUnitario;
            document.getElementById('cantidadPorPrecio').textContent = cantidad.toFixed(3) + ' kg';
        });

        function confirmarGranel() {
            const id = parseInt(document.getElementById('modalProductoId').value);
            const nombre = document.getElementById('modalProductoNombre').value;
            const precioUnitario = parseFloat(document.getElementById('modalProductoPrecio').value);
            
            let cantidad, subtotal;
            
            if (tabActual === 'cantidad') {
                cantidad = parseFloat(document.getElementById('inputCantidad').value);
                if (!cantidad || cantidad <= 0) {
                    alert('Por favor ingresa una cantidad válida');
                    return;
                }
                subtotal = cantidad * precioUnitario;
            } else {
                subtotal = parseFloat(document.getElementById('inputPrecio').value);
                if (!subtotal || subtotal <= 0) {
                    alert('Por favor ingresa un precio válido');
                    return;
                }
                cantidad = subtotal / precioUnitario;
            }
            
            // Agregar al carrito
            carrito.push({
                id: id,
                nombre: nombre,
                precio: precioUnitario,
                cantidad: cantidad,
                subtotal: subtotal,
                tipo: 'granel'
            });
            
            hacerBeep();
            cerrarModal('modalGranel');
            actualizarVista();
        }

        function agregarProductoUnitario(producto) {
            const index = carrito.findIndex(p => p.id === producto.id && p.tipo !== 'granel');
            
            if (index !== -1) {
                carrito[index].cantidad++;
                carrito[index].subtotal = carrito[index].cantidad * carrito[index].precio;
                hacerBeep();
            } else {
                carrito.push({
                    id: producto.id,
                    nombre: producto.nombre,
                    precio: parseFloat(producto.precio),
                    cantidad: 1,
                    subtotal: parseFloat(producto.precio),
                    tipo: 'unitario'
                });
                hacerBeep();
            }
            
            actualizarVista();
        }

        function cerrarModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            scanInput.focus();
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
                lista.innerHTML = '<div class="vacio">Escanea o busca productos para comenzar</div>';
                totalBox.style.display = 'none';
                btnCobrar.style.display = 'none';
                btnLimpiar.style.display = 'none';
                return;
            }
            
            let html = '';
            totalVenta = 0;
            
            carrito.forEach((item, i) => {
                totalVenta += item.subtotal;
                
                const detalle = item.tipo === 'granel' 
                    ? `${item.cantidad.toFixed(3)} kg × $${item.precio.toFixed(2)}`
                    : `× ${item.cantidad}`;
                
                html += `
                    <div class="carrito-item">
                        <div style="flex:1;">
                            <div class="item-nombre">${item.nombre}</div>
                            <div class="item-detalle">${detalle}</div>
                        </div>
                        <div class="item-precio">$${item.subtotal.toFixed(2)}</div>
                        <button class="btn-eliminar" onclick="eliminarProducto(${i})">✕</button>
                    </div>
                `;
            });
            
            lista.innerHTML = html;
            document.getElementById('totalMonto').textContent = '$' + totalVenta.toFixed(2);
            totalBox.style.display = 'block';
            btnCobrar.style.display = 'block';
            btnLimpiar.style.display = 'block';
        }

        function limpiarTodo() {
            if (confirm('¿Limpiar toda la venta?')) {
                carrito = [];
                actualizarVista();
                scanInput.value = '';
                buscarInput.value = '';
                document.getElementById('resultadosBusqueda').innerHTML = '';
                scanInput.focus();
            }
        }

        // FUNCIONES DE PAGO
        function abrirModalPago() {
            document.getElementById('totalPagoModal').textContent = '$' + totalVenta.toFixed(2);
            document.getElementById('modalPago').classList.add('active');
            
            // Resetear
            metodoSeleccionado = 'efectivo';
            document.getElementById('inputEfectivo').value = '';
            document.getElementById('cambioDisplay').style.display = 'none';
            
            // Seleccionar efectivo por defecto
            seleccionarMetodo('efectivo');
            
            setTimeout(() => {
                document.getElementById('inputEfectivo').focus();
            }, 200);
        }

        function seleccionarMetodo(metodo) {
            metodoSeleccionado = metodo;
            
            // Actualizar botones
            document.querySelectorAll('.metodo-btn').forEach(btn => btn.classList.remove('active'));
            event.target.closest('.metodo-btn').classList.add('active');
            
            // Actualizar contenido
            document.querySelectorAll('.pago-content').forEach(content => content.classList.remove('active'));
            
            if (metodo === 'efectivo') {
                document.getElementById('pagoEfectivo').classList.add('active');
                document.getElementById('btnConfirmarEfectivo').style.display = 'block';
                setTimeout(() => document.getElementById('inputEfectivo').focus(), 100);
            } else if (metodo === 'transferencia') {
                document.getElementById('pagoTransferencia').classList.add('active');
                document.getElementById('btnConfirmarEfectivo').style.display = 'none';
            } else if (metodo === 'tarjeta') {
                document.getElementById('pagoTarjeta').classList.add('active');
                document.getElementById('btnConfirmarEfectivo').style.display = 'none';
            }
        }

        function calcularCambio() {
            const efectivo = parseFloat(document.getElementById('inputEfectivo').value) || 0;
            const cambio = efectivo - totalVenta;
            
            if (efectivo > 0 && cambio >= 0) {
                document.getElementById('cambioDisplay').style.display = 'block';
                document.getElementById('valorCambio').textContent = '$' + cambio.toFixed(2);
            } else {
                document.getElementById('cambioDisplay').style.display = 'none';
            }
        }

        function confirmarVenta() {
            // Validaciones según método
            if (metodoSeleccionado === 'efectivo') {
                const efectivo = parseFloat(document.getElementById('inputEfectivo').value) || 0;
                
                if (efectivo < totalVenta) {
                    alert('⚠️ El monto recibido es menor al total');
                    return;
                }
                
                const cambio = efectivo - totalVenta;
                
                document.getElementById('metodoPagoData').value = 'efectivo';
                document.getElementById('montoRecibidoData').value = efectivo;
                document.getElementById('cambioData').value = cambio;
                
            } else {
                // Transferencia o tarjeta
                if (!confirm('¿Confirmar pago por ' + metodoSeleccionado.toUpperCase() + '?')) {
                    return;
                }
                
                document.getElementById('metodoPagoData').value = metodoSeleccionado;
                document.getElementById('montoRecibidoData').value = '';
                document.getElementById('cambioData').value = '';
            }
            
            // Enviar formulario
            document.getElementById('carritoData').value = JSON.stringify(carrito);
            document.getElementById('formVenta').submit();
        }

        // Cerrar modales con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModal('modalGranel');
                cerrarModal('modalPago');
            }
        });

        // Mantener foco
        window.addEventListener('load', () => scanInput.focus());
    </script>
</body>
</html>
