<?php
session_start();

// Verificar si está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

$usuario = $_SESSION['usuario'];
$nombre = $_SESSION['nombre'];
$tipo = $_SESSION['tipo'];

// Obtener datos para el dashboard
$queryProductos = "SELECT COUNT(*) as total FROM Productos";
$stmtProductos = $db->query($queryProductos);
$totalProductos = $stmtProductos->fetch(PDO::FETCH_ASSOC)['total'];

$queryBajoStock = "SELECT COUNT(*) as total FROM Inventario WHERE stock_actual <= stock_minimo";
$stmtBajoStock = $db->query($queryBajoStock);
$productosBajoStock = $stmtBajoStock->fetch(PDO::FETCH_ASSOC)['total'];

$queryClientes = "SELECT COUNT(*) as total FROM Clientes";
$stmtClientes = $db->query($queryClientes);
$totalClientes = $stmtClientes->fetch(PDO::FETCH_ASSOC)['total'];

// Obtener productos
$queryProductosList = "SELECT p.*, i.stock_actual, i.stock_minimo, i.fecha_actualizacion 
                       FROM Productos p 
                       LEFT JOIN Inventario i ON p.id_inventario = i.id_inventario 
                       ORDER BY p.id_producto DESC";
$stmtProductosList = $db->query($queryProductosList);
$productos = $stmtProductosList->fetchAll(PDO::FETCH_ASSOC);

// Obtener productos con stock bajo
$queryBajoStockList = "SELECT p.nombre_producto, i.stock_actual, i.stock_minimo, i.fecha_actualizacion 
                       FROM Productos p 
                       JOIN Inventario i ON p.id_inventario = i.id_inventario 
                       WHERE i.stock_actual <= i.stock_minimo";
$stmtBajoStockList = $db->query($queryBajoStockList);
$productosBajoStockList = $stmtBajoStockList->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Inventario</title>
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
        
        .user-info {
            background: white;
            max-width: 1400px;
            margin: 0 auto 20px;
            padding: 15px 30px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .user-info .welcome {
            font-size: 16px;
            color: #333;
        }
        
        .user-info .welcome strong {
            color: #667eea;
        }
        
        .badge-role {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .badge-encargado {
            background: #667eea;
            color: white;
        }
        
        .badge-trabajador {
            background: #28a745;
            color: white;
        }
        
        .btn-logout {
            padding: 8px 20px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-logout:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        h1 {
            color: #667eea;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.5em;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 12px 24px;
            background: #f5f5f5;
            border: none;
            border-radius: 10px 10px 0 0;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .tab:hover {
            background: #e0e0e0;
        }
        
        .tab.active {
            background: #667eea;
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        th {
            background: #667eea;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
        }
        
        .alert-success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-danger {
            background: #dc3545;
            color: white;
        }
        
        .badge-warning {
            background: #ffc107;
            color: #000;
        }
        
        .badge-success {
            background: #28a745;
            color: white;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
            font-weight: 600;
            margin: 5px;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .stat-card h3 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .stat-card .value {
            font-size: 36px;
            font-weight: bold;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 600;
        }
        
        input, select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .restricted {
            opacity: 0.5;
            pointer-events: none;
        }
    </style>
</head>
<body>

<div class="user-info">
    <div class="welcome">
        👋 Bienvenido, <strong><?php echo htmlspecialchars($nombre); ?></strong>
        <span class="badge-role <?php echo $tipo == 'encargado' ? 'badge-encargado' : 'badge-trabajador'; ?>">
            <?php echo $tipo == 'encargado' ? '🔑 Encargado' : '👷 Trabajador'; ?>
        </span>
    </div>
    <a href="logout.php" class="btn-logout">🚪 Cerrar Sesión</a>
</div>

<div class="container">
    <h1>🏪 Sistema de Inventario</h1>
    
    <div class="tabs">
        <button class="tab active" onclick="showTab('dashboard')">📊 Dashboard</button>
        <button class="tab" onclick="showTab('productos')">📦 Productos</button>
        <?php if($tipo == 'encargado'): ?>
        <button class="tab" onclick="showTab('agregar')">➕ Agregar Producto</button>
        <?php endif; ?>
    </div>
    
    <!-- Dashboard -->
    <div id="dashboard" class="tab-content active">
        <div class="stats">
            <div class="stat-card">
                <h3>Total Productos</h3>
                <div class="value"><?php echo $totalProductos; ?></div>
            </div>
            <div class="stat-card">
                <h3>Productos Bajo Stock</h3>
                <div class="value"><?php echo $productosBajoStock; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Clientes</h3>
                <div class="value"><?php echo $totalClientes; ?></div>
            </div>
        </div>
        
        <?php if($productosBajoStock > 0): ?>
        <div class="alert alert-warning">
            <strong>⚠️ Atención:</strong> Hay <?php echo $productosBajoStock; ?> productos con stock bajo el mínimo.
        </div>
        
        <h2>Productos con Stock Crítico</h2>
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Stock Actual</th>
                    <th>Stock Mínimo</th>
                    <th>Estado</th>
                    <th>Última Actualización</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($productosBajoStockList as $producto): ?>
                <tr>
                    <td><?php echo htmlspecialchars($producto['nombre_producto']); ?></td>
                    <td><?php echo $producto['stock_actual']; ?></td>
                    <td><?php echo $producto['stock_minimo']; ?></td>
                    <td>
                        <span class="badge <?php echo $producto['stock_actual'] == 0 ? 'badge-danger' : 'badge-warning'; ?>">
                            <?php echo $producto['stock_actual'] == 0 ? 'Agotado' : 'Bajo'; ?>
                        </span>
                    </td>
                    <td><?php echo date('d/m/Y H:i', strtotime($producto['fecha_actualizacion'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <!-- Productos -->
    <div id="productos" class="tab-content">
        <h2>Lista de Productos</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Precio Venta</th>
                    <th>Código Barras</th>
                    <th>Stock</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($productos as $producto): ?>
                <tr>
                    <td><?php echo $producto['id_producto']; ?></td>
                    <td><?php echo htmlspecialchars($producto['nombre_producto']); ?></td>
                    <td>$<?php echo number_format($producto['precio_venta'], 2); ?></td>
                    <td><?php echo htmlspecialchars($producto['codigo_barras']); ?></td>
                    <td><?php echo $producto['stock_actual']; ?></td>
                    <td>
                        <span class="badge <?php echo ($producto['stock_actual'] <= $producto['stock_minimo']) ? 'badge-danger' : 'badge-success'; ?>">
                            <?php echo ($producto['stock_actual'] <= $producto['stock_minimo']) ? 'Bajo Stock' : 'OK'; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Agregar Producto (Solo Encargados) -->
    <?php if($tipo == 'encargado'): ?>
    <div id="agregar" class="tab-content">
        <h2>Agregar Nuevo Producto</h2>
        <div class="alert alert-success">
            ✅ Como <strong>Encargado</strong>, tienes permisos para agregar productos.
        </div>
        <form method="POST" action="agregar_producto.php">
            <div class="form-group">
                <label>Nombre del Producto:</label>
                <input type="text" name="nombre_producto" required>
            </div>
            <div class="form-group">
                <label>Precio de Venta:</label>
                <input type="number" step="0.01" name="precio_venta" required>
            </div>
            <div class="form-group">
                <label>Código de Barras:</label>
                <input type="text" name="codigo_barras">
            </div>
            <div class="form-group">
                <label>Stock Inicial:</label>
                <input type="number" name="stock_inicial" value="0" required>
            </div>
            <div class="form-group">
                <label>Stock Mínimo:</label>
                <input type="number" name="stock_minimo" value="10" required>
            </div>
            <button type="submit" class="btn btn-primary">💾 Guardar Producto</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
function showTab(tabName) {
    const tabs = document.querySelectorAll('.tab');
    const contents = document.querySelectorAll('.tab-content');
    
    tabs.forEach(tab => tab.classList.remove('active'));
    contents.forEach(content => content.classList.remove('active'));
    
    event.target.classList.add('active');
    document.getElementById(tabName).classList.add('active');
}
</script>

</body>
</html>