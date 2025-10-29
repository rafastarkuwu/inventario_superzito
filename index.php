<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';
$database = new Database();
$db = $database->getConnection();

// Obtener estadísticas
try {
    // Total de productos
    $query = "SELECT COUNT(*) as total FROM Productos";
    $stmt = $db->query($query);
    $total_productos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Productos con stock bajo
    $query = "SELECT COUNT(*) as total FROM Inventario WHERE stock_actual <= stock_minimo";
    $stmt = $db->query($query);
    $productos_bajo_stock = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Ventas del día
    $query = "SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as monto 
             FROM Ventas 
             WHERE DATE(fecha_venta) = CURDATE()";
    $stmt = $db->query($query);
    $ventas_hoy = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ventas del mes
    $query = "SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as monto 
             FROM Ventas 
             WHERE MONTH(fecha_venta) = MONTH(CURDATE()) 
             AND YEAR(fecha_venta) = YEAR(CURDATE())";
    $stmt = $db->query($query);
    $ventas_mes = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch(Exception $e) {
    $total_productos = 0;
    $productos_bajo_stock = 0;
    $ventas_hoy = ['total' => 0, 'monto' => 0];
    $ventas_mes = ['total' => 0, 'monto' => 0];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Inventario SuperZito</title>
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
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: #667eea;
            font-size: 2.5em;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-info p {
            color: #666;
            margin-bottom: 10px;
        }
        
        .user-info strong {
            color: #333;
        }
        
        .btn-logout {
            padding: 10px 20px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-logout:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }
        
        .stat-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .stat-card.warning .stat-value {
            color: #ffc107;
        }
        
        .stat-card.success .stat-value {
            color: #28a745;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .action-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }
        
        .action-icon {
            font-size: 60px;
            margin-bottom: 15px;
        }
        
        .action-card h3 {
            color: #667eea;
            font-size: 1.5em;
            margin-bottom: 10px;
        }
        
        .action-card p {
            color: #666;
            font-size: 14px;
        }
        
        .action-card.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .action-card.primary h3 {
            color: white;
        }
        
        .action-card.primary p {
            color: rgba(255,255,255,0.9);
        }
        
        .action-card.success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .action-card.success h3 {
            color: white;
        }
        
        .action-card.success p {
            color: rgba(255,255,255,0.9);
        }
        
        .badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .badge-danger {
            background: #dc3545;
            color: white;
        }
        
        .badge-info {
            background: #17a2b8;
            color: white;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .user-info {
                text-align: center;
                margin-top: 20px;
            }
            
            .stats-grid, .actions-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="header">
        <div>
            <h1>🏪 Inventario SuperZito</h1>
            <p style="color: #666; margin-top: 10px;">Sistema de Gestión de Inventario</p>
        </div>
        <div class="user-info">
            <p>👤 Bienvenido, <strong><?php echo htmlspecialchars($_SESSION['nombre']); ?></strong></p>
            <p>
                <span class="badge <?php echo $_SESSION['tipo'] == 'encargado' ? 'badge-danger' : 'badge-info'; ?>">
                    <?php echo $_SESSION['tipo'] == 'encargado' ? '🔑 Encargado' : '👷 Trabajador'; ?>
                </span>
            </p>
            <a href="logout.php" class="btn-logout">🚪 Cerrar Sesión</a>
        </div>
    </div>
    
    <!-- Estadísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📦</div>
            <div class="stat-value"><?php echo $total_productos; ?></div>
            <div class="stat-label">Total de Productos</div>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-icon">⚠️</div>
            <div class="stat-value"><?php echo $productos_bajo_stock; ?></div>
            <div class="stat-label">Productos con Stock Bajo</div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-icon">💰</div>
            <div class="stat-value">$<?php echo number_format($ventas_hoy['monto'], 2); ?></div>
            <div class="stat-label">Ventas Hoy (<?php echo $ventas_hoy['total']; ?> tickets)</div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-icon">📊</div>
            <div class="stat-value">$<?php echo number_format($ventas_mes['monto'], 2); ?></div>
            <div class="stat-label">Ventas del Mes (<?php echo $ventas_mes['total']; ?> tickets)</div>
        </div>
    </div>
    
    <!-- Acciones Rápidas -->
    <div class="actions-grid">
        <!-- NUEVO: Punto de Venta -->
        <a href="vender.php" class="action-card primary">
            <div class="action-icon">💰</div>
            <h3>Punto de Venta</h3>
            <p>Realizar ventas y cobrar productos</p>
        </a>
        
        <!-- NUEVO: Entrada de Mercancía (solo encargados) -->
        <?php if ($_SESSION['tipo'] == 'encargado'): ?>
        <a href="entrada_mercancia.php" class="action-card success">
            <div class="action-icon">📥</div>
            <h3>Entrada de Mercancía</h3>
            <p>Aumentar stock de productos</p>
        </a>
        <?php endif; ?>
        
        <!-- Agregar Producto (solo encargados) -->
        <?php if ($_SESSION['tipo'] == 'encargado'): ?>
        <a href="agregar_producto.php" class="action-card">
            <div class="action-icon">➕</div>
            <h3>Agregar Producto</h3>
            <p>Registrar nuevos productos al inventario</p>
        </a>
        <?php endif; ?>
        
        <!-- NUEVO: Historial de Ventas -->
        <a href="historial_ventas.php" class="action-card">
            <div class="action-icon">📋</div>
            <h3>Historial de Ventas</h3>
            <p>Ver todas las ventas realizadas</p>
        </a>
        
        <!-- NUEVO: Cierre de Caja (solo encargados) -->
        <?php if ($_SESSION['tipo'] == 'encargado'): ?>
        <a href="cierre_caja.php" class="action-card">
            <div class="action-icon">💵</div>
            <h3>Cierre de Caja</h3>
            <p>Realizar arqueo y cierre de caja</p>
        </a>
        <?php endif; ?>
        
        <!-- Escanear (antiguo) -->
        <a href="escanear.php" class="action-card">
            <div class="action-icon">🔫</div>
            <h3>Escáner de Códigos</h3>
            <p>Consultar productos por código de barras</p>
        </a>
    </div>
</div>

</body>
</html>
