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

// API para buscar productos por nombre (AJAX)
if (isset($_GET['api']) && $_GET['api'] === 'buscar_nombre' && isset($_GET['nombre'])) {
    header('Content-Type: application/json');
    
    $nombre = trim($_GET['nombre']);
    $termino = '%' . $nombre . '%';
    $stmt = $pdo->prepare("
        SELECT p.id_producto as id, p.nombre_producto as nombre, p.precio_venta as precio, p.codigo_barras, i.stock_actual as stock 
        FROM Productos p 
        INNER JOIN Inventario i ON p.id_inventario = i.id_inventario 
        WHERE p.nombre_producto LIKE :termino AND i.stock_actual > 0
        LIMIT 10
    ");
    $stmt->execute([':termino' => $termino]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($productos);
    exit();
}

// Procesar venta - CÓDIGO CORREGIDO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalizar_venta'])) {
    $carrito = json_decode($_POST['carrito'], true);
    
    if (!empty($carrito)) {
        try {
            $pdo->beginTransaction();
            
            $total = 0;
            foreach ($carrito as $item) {
                $total += floatval($item['subtotal']);
            }
            
            // Insertar venta - CORREGIDO: usar tabla 'ventas' en minúsculas
            $stmt = $pdo->prepare("
                INSERT INTO ventas (usuario_nombre, fecha_venta, total) 
                VALUES (:usuario_nombre, NOW(), :total)
            ");
            $stmt->execute([
                ':usuario_nombre' => $usuario_nombre,
                ':total' => $total
            ]);
            
            $venta_id = $pdo->lastInsertId();
            
            // Insertar detalle - CORREGIDO: usar tabla 'venta_detalle' y agregar subtotal
            foreach ($carrito as $item) {
                $stmt = $pdo->prepare("
                    INSERT INTO venta_detalle (venta_id, producto_id, cantidad, precio_unitario, subtotal) 
                    VALUES (:venta_id, :producto_id, :cantidad, :precio_unitario, :subtotal)
                ");
                $stmt->execute([
                    ':venta_id' => $venta_id,
                    ':producto_id' => $item['id'],
                    ':cantidad' => $item['cantidad'],
                    ':precio_unitario' => $item['precio'],
                    ':subtotal' => $item['subtotal']
                ]);
                
                // Solo actualizar stock si es producto por unidad (cantidad entera)
                if ($item['cantidad'] == intval($item['cantidad'])) {
                    $stmt = $pdo->prepare("
                        UPDATE Inventario 
                        SET stock_actual = stock_actual - :cantidad 
                        WHERE id_inventario = (
                            SELECT id_inventario 
                            FROM Productos 
                            WHERE id_producto = :id
                        )
                    ");
                    $stmt->execute([
                        ':cantidad' => intval($item['cantidad']),
                        ':id' => $item['id']
                    ]);
                }
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
        .item-cantidad {
            background: #667eea;
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: bold;
            margin: 0 10px;
            min-width: 80px;
            text-align: center;
            font-size: 14px;
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
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
        }
        .modal-content {
            background: white;
            max-width: 500px;
            margin: 80px auto;
            padding: 30px;
            border-radius: 15px;
        }
        .modal-content h2 {
            color: #4CAF50;
            margin-bottom: 20px;
            text-align: center;
        }
        .form-group { margin-bottom: 20px; }
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
            font-size: 18px;
        }
        .form-group input:focus {
            border-color: #4CAF50;
            outline: none;
        }
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
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .modal-buttons { display: flex; gap: 10px; margin-top: 25px; }
        .modal-buttons button {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
        }
        .btn-confirmar { background: #4CAF50; color: white; }
        .btn-cancelar { background: #666; color: white; }
        .precio-info {
            background: #f0f4ff;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }
        .precio-info .label { font-size: 14px; color: #666; }
        .precio-info .valor {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
            margin-top: 5px;
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
        <!-- Escáner de códigos -->
        <div class="scanner-box">
            <div class="scanner-icon">🔫</div>
            <h2>Escanear Código de Barras</h2>
            <input type="text" id="scanInput" placeholder="Escanea aquí..." autocomplete="off">
        </div>

        <!-- Búsqueda manual para granel -->
        <div class="buscar-box">
            <div class="buscar-icon">🔍</div>
            <h2>Buscar Producto (A granel o manual)</h2>
            <input type="text" id="buscarInput" placeholder="Escribe el nombre del producto..." autocomplete="off">
            <div id="resultadosBusqueda" class="resultados-busqueda"></div>
        </div>

        <!-- Carrito -->
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

    <!-- Modal para cantidad/precio -->
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
                <button type="button" class="btn-cancelar" onclick="cerrarModal()">❌ Cancelar</button>
            </div>
        </div>
    </div>

    <script>
        let carrito = [];
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
            document.getElementById('modalGranel').style.display = 'block';
            
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
            cerrarModal();
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

        function cerrarModal() {
            document.getElementById('modalGranel').style.display = 'none';
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
            let total = 0;
            
            carrito.forEach((item, i) => {
                total += item.subtotal;
                
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
                buscarInput.value = '';
                document.getElementById('resultadosBusqueda').innerHTML = '';
                scanInput.focus();
            }
        }

        // Validar antes de cobrar
        document.getElementById('formVenta').addEventListener('submit', function(e) {
            const total = carrito.reduce((sum, item) => sum + item.subtotal, 0);
            if (!confirm('💵 COBRAR  + total.toFixed(2) + '?')) {
                e.preventDefault();
            }
        });

        // Cerrar modal con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModal();
            }
        });

        // Mantener foco
        window.addEventListener('load', () => scanInput.focus());
    </script>
</body>
</html>
