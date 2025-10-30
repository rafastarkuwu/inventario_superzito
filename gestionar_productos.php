<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_nombre = $_SESSION['usuario_nombre'];
$mensaje = '';

// Capturar mensajes de sesión
if (isset($_SESSION['mensaje_exito'])) {
    $mensaje = $_SESSION['mensaje_exito'];
    unset($_SESSION['mensaje_exito']);
}
if (isset($_SESSION['mensaje_error'])) {
    $mensaje = $_SESSION['mensaje_error'];
    unset($_SESSION['mensaje_error']);
}

// Obtener todos los productos con su inventario
try {
    $stmt = $pdo->query("
        SELECT 
            p.id_producto,
            p.nombre,
            p.codigo_barras,
            p.precio_venta,
            p.activo,
            COALESCE(i.stock_actual, 0) as stock_actual,
            COALESCE(i.stock_minimo, 0) as stock_minimo
        FROM Productos p
        LEFT JOIN Inventario i ON p.id_producto = i.id_producto
        ORDER BY p.nombre ASC
    ");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $mensaje = "Error al cargar productos: " . $e->getMessage();
    $productos = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Productos - SuperZito</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .header-left h1 {
            color: #667eea;
            font-size: 28px;
            margin-bottom: 5px;
        }
        .header-right {
            display: flex;
            gap: 15px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .search-bar {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .search-bar input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border 0.3s;
        }
        .search-bar input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-mini {
            background: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .stat-mini-value {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        .stat-mini-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .productos-table {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: bold;
        }
        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        tr:hover {
            background: #f8f9ff;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        .badge-activo {
            background: #d4edda;
            color: #155724;
        }
        .badge-inactivo {
            background: #f8d7da;
            color: #721c24;
        }
        .badge-bajo {
            background: #fff3cd;
            color: #856404;
        }
        .badge-ok {
            background: #d4edda;
            color: #155724;
        }
        
        .acciones {
            display: flex;
            gap: 8px;
        }
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-editar {
            background: #2196F3;
            color: white;
        }
        .btn-toggle {
            background: #FF9800;
            color: white;
        }
        .btn-small:hover {
            transform: scale(1.05);
        }
        
        .mensaje {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .productos-table {
                overflow-x: auto;
            }
            .acciones {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <h1>📦 Gestión de Productos</h1>
            </div>
            <div class="header-right">
                <a href="agregar_producto.php" class="btn btn-primary">➕ Nuevo Producto</a>
                <a href="index.php" class="btn btn-secondary">🏠 Volver al Inicio</a>
            </div>
        </div>

        <?php if ($mensaje): ?>
        <div class="mensaje"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-mini">
                <div class="stat-mini-value"><?php echo count($productos); ?></div>
                <div class="stat-mini-label">Total Productos</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-value" style="color: #4CAF50;">
                    <?php echo count(array_filter($productos, fn($p) => $p['activo'] == 1)); ?>
                </div>
                <div class="stat-mini-label">Activos</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-value" style="color: #ff4444;">
                    <?php echo count(array_filter($productos, fn($p) => $p['activo'] == 0)); ?>
                </div>
                <div class="stat-mini-label">Inactivos</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-value" style="color: #FF9800;">
                    <?php echo count(array_filter($productos, fn($p) => $p['stock_actual'] <= $p['stock_minimo'])); ?>
                </div>
                <div class="stat-mini-label">Stock Bajo</div>
            </div>
        </div>

        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="🔍 Buscar producto por nombre o código de barras..." onkeyup="filtrarProductos()">
        </div>

        <div class="productos-table">
            <?php if (empty($productos)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <h2>No hay productos registrados</h2>
                    <p>Comienza agregando tu primer producto</p>
                </div>
            <?php else: ?>
                <table id="productosTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Código de Barras</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Estado Stock</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $prod): ?>
                        <tr>
                            <td><?php echo $prod['id_producto']; ?></td>
                            <td><strong><?php echo htmlspecialchars($prod['nombre']); ?></strong></td>
                            <td><?php echo htmlspecialchars($prod['codigo_barras']); ?></td>
                            <td>$<?php echo number_format($prod['precio_venta'], 2); ?></td>
                            <td>
                                <?php echo $prod['stock_actual']; ?> 
                                <span style="color: #999; font-size: 12px;">(mín: <?php echo $prod['stock_minimo']; ?>)</span>
                            </td>
                            <td>
                                <?php if ($prod['stock_actual'] <= $prod['stock_minimo']): ?>
                                    <span class="badge badge-bajo">⚠️ Stock Bajo</span>
                                <?php else: ?>
                                    <span class="badge badge-ok">✅ OK</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($prod['activo']): ?>
                                    <span class="badge badge-activo">✅ Activo</span>
                                <?php else: ?>
                                    <span class="badge badge-inactivo">❌ Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="acciones">
                                    <a href="editar_producto.php?id=<?php echo $prod['id_producto']; ?>" 
                                       class="btn-small btn-editar">✏️ Editar</a>
                                    <button onclick="toggleEstado(<?php echo $prod['id_producto']; ?>, <?php echo $prod['activo']; ?>)" 
                                            class="btn-small btn-toggle">
                                        <?php echo $prod['activo'] ? '🔒 Desactivar' : '🔓 Activar'; ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function filtrarProductos() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('productosTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const tdNombre = tr[i].getElementsByTagName('td')[1];
                const tdCodigo = tr[i].getElementsByTagName('td')[2];
                
                if (tdNombre || tdCodigo) {
                    const txtNombre = tdNombre.textContent || tdNombre.innerText;
                    const txtCodigo = tdCodigo.textContent || tdCodigo.innerText;
                    
                    if (txtNombre.toUpperCase().indexOf(filter) > -1 || 
                        txtCodigo.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = '';
                    } else {
                        tr[i].style.display = 'none';
                    }
                }
            }
        }

        function toggleEstado(idProducto, estadoActual) {
            const accion = estadoActual ? 'desactivar' : 'activar';
            
            if (confirm(`¿Estás seguro de ${accion} este producto?`)) {
                window.location.href = `agregar_columna_activo.php?id=${idProducto}&accion=${accion}`;
            }
        }
    </script>
</body>
</html>
