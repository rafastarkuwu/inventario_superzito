<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$mensaje = '';
$tipo_mensaje = '';

// Procesar edición de producto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_producto'])) {
    try {
        $id = intval($_POST['id_producto']);
        $nombre = trim($_POST['nombre_producto']);
        $precio_compra = floatval($_POST['precio_compra']);
        $precio_venta = floatval($_POST['precio_venta']);
        $codigo_barras = trim($_POST['codigo_barras']);
        
        $stmt = $pdo->prepare("
            UPDATE Productos 
            SET nombre_producto = ?, precio_compra = ?, precio_venta = ?, codigo_barras = ?
            WHERE id_producto = ?
        ");
        $stmt->execute([$nombre, $precio_compra, $precio_venta, $codigo_barras, $id]);
        
        $mensaje = "✅ Producto actualizado correctamente";
        $tipo_mensaje = "success";
    } catch (Exception $e) {
        $mensaje = "❌ Error al actualizar: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Procesar dar de baja producto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['baja_producto'])) {
    try {
        $id = intval($_POST['id_producto']);
        
        // Opción 1: Eliminar producto (comentado por seguridad)
        // $stmt = $pdo->prepare("DELETE FROM Productos WHERE id_producto = ?");
        
        // Opción 2: Marcar como inactivo (recomendado)
        $stmt = $pdo->prepare("
            UPDATE Productos 
            SET activo = 0
            WHERE id_producto = ?
        ");
        $stmt->execute([$id]);
        
        $mensaje = "✅ Producto dado de baja correctamente";
        $tipo_mensaje = "success";
    } catch (Exception $e) {
        $mensaje = "❌ Error al dar de baja: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Procesar reactivar producto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activar_producto'])) {
    try {
        $id = intval($_POST['id_producto']);
        
        $stmt = $pdo->prepare("
            UPDATE Productos 
            SET activo = 1
            WHERE id_producto = ?
        ");
        $stmt->execute([$id]);
        
        $mensaje = "✅ Producto reactivado correctamente";
        $tipo_mensaje = "success";
    } catch (Exception $e) {
        $mensaje = "❌ Error al reactivar: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Filtros
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : 'activos';
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

// Construir consulta
$where = [];
$params = [];

if ($filtro_estado === 'activos') {
    $where[] = "p.activo = 1";
} elseif ($filtro_estado === 'inactivos') {
    $where[] = "p.activo = 0";
}

if ($busqueda !== '') {
    $where[] = "(p.nombre_producto LIKE ? OR p.codigo_barras LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

$sql = "
    SELECT p.id_producto, p.nombre_producto, p.precio_compra, p.precio_venta, 
           p.codigo_barras, p.activo, COALESCE(i.stock_actual, 0) as stock
    FROM Productos p
    LEFT JOIN Inventario i ON p.id_inventario = i.id_inventario
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY p.nombre_producto ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Productos</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { color: #667eea; font-size: 28px; }
        .btn-volver {
            background: #666;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .filtros-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .filtros-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filtro-grupo {
            display: flex;
            gap: 10px;
        }
        .btn-filtro {
            padding: 10px 20px;
            border: 2px solid #667eea;
            background: white;
            color: #667eea;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
        }
        .btn-filtro.active {
            background: #667eea;
            color: white;
        }
        .buscar-input {
            flex: 1;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .mensaje {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .mensaje.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .mensaje.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .tabla-box {
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead {
            background: #667eea;
            color: white;
        }
        th {
            padding: 15px;
            text-align: left;
            font-weight: bold;
        }
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        tr:hover {
            background: #f8f9ff;
        }
        .producto-nombre {
            font-weight: bold;
            color: #333;
        }
        .producto-codigo {
            font-size: 12px;
            color: #666;
        }
        .precio {
            font-weight: bold;
        }
        .precio-compra {
            color: #ff9800;
        }
        .precio-venta {
            color: #4CAF50;
        }
        .stock {
            text-align: center;
            font-weight: bold;
            color: #667eea;
        }
        .stock.bajo {
            color: #ff4444;
        }
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge.activo {
            background: #d4edda;
            color: #155724;
        }
        .badge.inactivo {
            background: #f8d7da;
            color: #721c24;
        }
        .acciones {
            display: flex;
            gap: 8px;
        }
        .btn-accion {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }
        .btn-editar {
            background: #2196F3;
            color: white;
        }
        .btn-baja {
            background: #ff4444;
            color: white;
        }
        .btn-activar {
            background: #4CAF50;
            color: white;
        }
        
        /* Modal */
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
            max-width: 600px;
            width: 90%;
            padding: 30px;
            border-radius: 15px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-content h2 {
            color: #667eea;
            margin-bottom: 25px;
            font-size: 24px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }
        .form-group input:focus {
            border-color: #667eea;
            outline: none;
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
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
        .btn-guardar {
            background: #4CAF50;
            color: white;
        }
        .btn-cancelar {
            background: #666;
            color: white;
        }
        .empty-state {
            text-align: center;
            padding: 60px;
            color: #999;
        }
        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 Gestionar Productos</h1>
            <a href="index.php" class="btn-volver">← Volver al Dashboard</a>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="filtros-box">
            <form method="GET" class="filtros-row">
                <div class="filtro-grupo">
                    <a href="?estado=activos" class="btn-filtro <?php echo $filtro_estado === 'activos' ? 'active' : ''; ?>">
                        ✅ Activos
                    </a>
                    <a href="?estado=inactivos" class="btn-filtro <?php echo $filtro_estado === 'inactivos' ? 'active' : ''; ?>">
                        ❌ Inactivos
                    </a>
                    <a href="?estado=todos" class="btn-filtro <?php echo $filtro_estado === 'todos' ? 'active' : ''; ?>">
                        📋 Todos
                    </a>
                </div>
                <input type="text" name="buscar" class="buscar-input" placeholder="🔍 Buscar por nombre o código..." value="<?php echo htmlspecialchars($busqueda); ?>">
                <input type="hidden" name="estado" value="<?php echo $filtro_estado; ?>">
            </form>
        </div>

        <!-- Tabla de productos -->
        <div class="tabla-box">
            <?php if (count($productos) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Precio Compra</th>
                            <th>Precio Venta</th>
                            <th>Stock</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $prod): ?>
                            <tr>
                                <td>
                                    <div class="producto-nombre"><?php echo htmlspecialchars($prod['nombre_producto']); ?></div>
                                    <?php if ($prod['codigo_barras']): ?>
                                        <div class="producto-codigo">Código: <?php echo htmlspecialchars($prod['codigo_barras']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="precio precio-compra">$<?php echo number_format($prod['precio_compra'], 2); ?></td>
                                <td class="precio precio-venta">$<?php echo number_format($prod['precio_venta'], 2); ?></td>
                                <td class="stock <?php echo $prod['stock'] < 10 ? 'bajo' : ''; ?>">
                                    <?php echo number_format($prod['stock'], 0); ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $prod['activo'] ? 'activo' : 'inactivo'; ?>">
                                        <?php echo $prod['activo'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="acciones">
                                        <button class="btn-accion btn-editar" onclick='editarProducto(<?php echo json_encode($prod); ?>)'>
                                            ✏️ Editar
                                        </button>
                                        <?php if ($prod['activo']): ?>
                                            <button class="btn-accion btn-baja" onclick="darDeBaja(<?php echo $prod['id_producto']; ?>, '<?php echo htmlspecialchars($prod['nombre_producto']); ?>')">
                                                ❌ Baja
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-accion btn-activar" onclick="activarProducto(<?php echo $prod['id_producto']; ?>, '<?php echo htmlspecialchars($prod['nombre_producto']); ?>')">
                                                ✅ Activar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No hay productos</h3>
                    <p>No se encontraron productos con los filtros seleccionados</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Editar Producto -->
    <div class="modal" id="modalEditar">
        <div class="modal-content">
            <h2>✏️ Editar Producto</h2>
            <form method="POST">
                <input type="hidden" name="id_producto" id="edit_id">
                
                <div class="form-group">
                    <label>Nombre del Producto:</label>
                    <input type="text" name="nombre_producto" id="edit_nombre" required>
                </div>
                
                <div class="form-group">
                    <label>Código de Barras:</label>
                    <input type="text" name="codigo_barras" id="edit_codigo">
                </div>
                
                <div class="form-group">
                    <label>Precio de Compra:</label>
                    <input type="number" name="precio_compra" id="edit_precio_compra" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label>Precio de Venta:</label>
                    <input type="number" name="precio_venta" id="edit_precio_venta" step="0.01" min="0" required>
                </div>

                <div class="modal-buttons">
                    <button type="submit" name="editar_producto" class="btn-guardar">💾 Guardar Cambios</button>
                    <button type="button" class="btn-cancelar" onclick="cerrarModal()">❌ Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Formularios ocultos para dar de baja/activar -->
    <form method="POST" id="formBaja" style="display: none;">
        <input type="hidden" name="id_producto" id="baja_id">
        <input type="hidden" name="baja_producto" value="1">
    </form>

    <form method="POST" id="formActivar" style="display: none;">
        <input type="hidden" name="id_producto" id="activar_id">
        <input type="hidden" name="activar_producto" value="1">
    </form>

    <script>
        function editarProducto(producto) {
            document.getElementById('edit_id').value = producto.id_producto;
            document.getElementById('edit_nombre').value = producto.nombre_producto;
            document.getElementById('edit_codigo').value = producto.codigo_barras || '';
            document.getElementById('edit_precio_compra').value = producto.precio_compra;
            document.getElementById('edit_precio_venta').value = producto.precio_venta;
            
            document.getElementById('modalEditar').classList.add('active');
        }

        function cerrarModal() {
            document.getElementById('modalEditar').classList.remove('active');
        }

        function darDeBaja(id, nombre) {
            if (confirm('¿Dar de baja el producto "' + nombre + '"?\n\nEsto ocultará el producto pero mantendrá su historial.')) {
                document.getElementById('baja_id').value = id;
                document.getElementById('formBaja').submit();
            }
        }

        function activarProducto(id, nombre) {
            if (confirm('¿Reactivar el producto "' + nombre + '"?')) {
                document.getElementById('activar_id').value = id;
                document.getElementById('formActivar').submit();
            }
        }

        // Cerrar modal con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModal();
            }
        });

        // Auto-enviar búsqueda al escribir
        let timeoutBusqueda;
        document.querySelector('.buscar-input').addEventListener('input', function() {
            clearTimeout(timeoutBusqueda);
            timeoutBusqueda = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    </script>
</body>
</html>
