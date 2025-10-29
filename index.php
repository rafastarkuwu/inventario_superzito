<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nombre = $_SESSION['usuario_nombre'];
$rol = $_SESSION['rol'];

// Verificar si hay una caja abierta para este usuario
$stmt = $pdo->prepare("
    SELECT * FROM Cajas 
    WHERE id_encargado = ? AND estado = 'abierta' 
    ORDER BY fecha_apertura DESC 
    LIMIT 1
");
$stmt->execute([$usuario_id]);
$caja_abierta = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener estadísticas
try {
    // Total productos
    $stmt = $pdo->query("SELECT COUNT(*) FROM Productos");
    $total_productos = $stmt->fetchColumn();
    
    // Productos con stock bajo
    $stmt = $pdo->query("SELECT COUNT(*) FROM Inventario WHERE stock_actual <= stock_minimo");
    $productos_stock_bajo = $stmt->fetchColumn();
    
    // Ventas de hoy
    $stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM Ventas WHERE DATE(fecha_venta) = CURDATE()");
    $ventas_hoy = $stmt->fetchColumn();
    
    // Contar tickets de hoy
    $stmt = $pdo->query("SELECT COUNT(*) FROM Ventas WHERE DATE(fecha_venta) = CURDATE()");
    $tickets_hoy = $stmt->fetchColumn();
    
    // Ventas del mes
    $stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM Ventas WHERE YEAR(fecha_venta) = YEAR(CURDATE()) AND MONTH(fecha_venta) = MONTH(CURDATE())");
    $ventas_mes = $stmt->fetchColumn();
    
    // Contar tickets del mes
    $stmt = $pdo->query("SELECT COUNT(*) FROM Ventas WHERE YEAR(fecha_venta) = YEAR(CURDATE()) AND MONTH(fecha_venta) = MONTH(CURDATE())");
    $tickets_mes = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $total_productos = 0;
    $productos_stock_bajo = 0;
    $ventas_hoy = 0;
    $tickets_hoy = 0;
    $ventas_mes = 0;
    $tickets_mes = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario SuperZito</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
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
            font-size: 32px;
            margin-bottom: 5px;
        }
        .header-left p {
            color: #666;
            font-size: 14px;
        }
        .header-right {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .user-badge {
            background: #f0f4ff;
            padding: 10px 20px;
            border-radius: 25px;
            color: #667eea;
            font-weight: bold;
        }
        .btn-logout {
            background: #ff4444;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
        }
        
        /* Alerta de caja */
        .alerta-caja {
            background: linear-gradient(135deg, #FFA726 0%, #FB8C00 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(255, 167, 38, 0.3);
        }
        .alerta-caja h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .alerta-caja p {
            margin-bottom: 15px;
            font-size: 16px;
        }
        .btn-abrir-caja {
            background: white;
            color: #FB8C00;
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-abrir-caja:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        /* Info de caja abierta */
        .info-caja-abierta {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        .info-caja-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .info-caja-item {
            background: rgba(255,255,255,0.2);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        .info-caja-item .label {
            font-size: 12px;
            margin-bottom: 5px;
            opacity: 0.9;
        }
        .info-caja-item .value {
            font-size: 20px;
            font-weight: bold;
        }
        
        /* Tarjetas de estadísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.2s;
            color: inherit;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.15);
        }
        .stat-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .stat-value {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        /* Tarjetas de ventas con censura */
        .ventas-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
            cursor: pointer;
            user-select: none;
            transition: all 0.3s;
            position: relative;
        }
        .ventas-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.15);
        }
        .ventas-card.revelado {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
        }
        .ventas-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .ventas-value {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .ventas-value.censurado {
            color: #ddd;
            letter-spacing: 2px;
        }
        .ventas-value.revelado {
            color: white;
        }
        .ventas-label {
            font-size: 14px;
            margin-top: 5px;
        }
        .ventas-card:not(.revelado) .ventas-label {
            color: #666;
        }
        .ventas-card.revelado .ventas-label {
            color: rgba(255,255,255,0.9);
        }
        .click-hint {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
        }
        .ventas-card.revelado .click-hint {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        /* Módulos de acción */
        .modulos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        .modulo {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s;
        }
        .modulo:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .modulo-icon {
            font-size: 64px;
            margin-bottom: 15px;
        }
        .modulo-titulo {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
        }
        .modulo-descripcion {
            color: #666;
            font-size: 14px;
        }
        
        /* Colores de módulos */
        .modulo-venta { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .modulo-venta .modulo-titulo, .modulo-venta .modulo-descripcion { color: white; }
        
        .modulo-entrada { background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; }
        .modulo-entrada .modulo-titulo, .modulo-entrada .modulo-descripcion { color: white; }
        
        .modulo-agregar { background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%); color: white; }
        .modulo-agregar .modulo-titulo, .modulo-agregar .modulo-descripcion { color: white; }

        .modulo-retiro { background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%); color: white; }
        .modulo-retiro .modulo-titulo, .modulo-retiro .modulo-descripcion { color: white; }
        
        .modulo-historial:hover { border-color: #667eea; }
        .modulo-cierre:hover { border-color: #4CAF50; }
        .modulo-scanner:hover { border-color: #FF9800; }

        .modulo-deshabilitado {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .modulos-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <h1>🏪 Inventario SuperZito</h1>
                <p>Sistema de Gestión de Inventario</p>
            </div>
            <div class="header-right">
                <div class="user-badge">
                    👤 Bienvenido, <?php echo htmlspecialchars($usuario_nombre); ?>
                </div>
                <a href="logout.php" class="btn-logout">🚪 Cerrar Sesión</a>
            </div>
        </div>

        <?php if (!$caja_abierta): ?>
        <!-- ALERTA: NO HAY CAJA ABIERTA -->
        <div class="alerta-caja">
            <h2>⚠️ No tienes una caja abierta</h2>
            <p>Debes abrir una caja antes de poder realizar ventas o retiros</p>
            <a href="apertura_caja.php" class="btn-abrir-caja">
                🔓 Abrir Caja Ahora
            </a>
        </div>
        <?php else: ?>
        <!-- INFO: CAJA ABIERTA -->
        <div class="info-caja-abierta">
            <h2 style="text-align: center; margin-bottom: 10px;">✅ Caja Abierta - Turno Activo</h2>
            <div class="info-caja-grid">
                <div class="info-caja-item">
                    <div class="label">🕐 Hora Apertura</div>
                    <div class="value"><?php echo date('H:i', strtotime($caja_abierta['fecha_apertura'])); ?></div>
                </div>
                <div class="info-caja-item">
                    <div class="label">💵 Monto Inicial</div>
                    <div class="value">$<?php echo number_format($caja_abierta['monto_inicial'], 2); ?></div>
                </div>
                <div class="info-caja-item">
                    <div class="label">📅 Fecha</div>
                    <div class="value"><?php echo date('d/m/Y', strtotime($caja_abierta['fecha_apertura'])); ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-value"><?php echo $total_productos; ?></div>
                <div class="stat-label">Total de Productos</div>
            </div>
            
            <a href="stock_bajo.php" class="stat-card" style="text-decoration: none; cursor: pointer;" title="Ver productos con stock bajo">
                <div class="stat-icon">⚠️</div>
                <div class="stat-value" style="color: <?php echo $productos_stock_bajo > 0 ? '#ff4444' : '#4CAF50'; ?>;">
                    <?php echo $productos_stock_bajo; ?>
                </div>
                <div class="stat-label">Productos con Stock Bajo</div>
            </a>
            
            <!-- Ventas de hoy - CENSURADO -->
            <div class="ventas-card" id="ventasHoy" onclick="revelarVentas('hoy')">
                <span class="click-hint">👁️ Click</span>
                <div class="ventas-icon">💰</div>
                <div class="ventas-value censurado" id="valorHoy">$•••.••</div>
                <div class="ventas-label">Ventas Hoy (<?php echo $tickets_hoy; ?> tickets)</div>
            </div>
            
            <!-- Ventas del mes - CENSURADO -->
            <div class="ventas-card" id="ventasMes" onclick="revelarVentas('mes')">
                <span class="click-hint">👁️ Click</span>
                <div class="ventas-icon">📊</div>
                <div class="ventas-value censurado" id="valorMes">$•••.••</div>
                <div class="ventas-label">Ventas del Mes (<?php echo $tickets_mes; ?> tickets)</div>
            </div>
        </div>

        <!-- Módulos principales -->
        <div class="modulos-grid">
            <a href="vender.php" class="modulo modulo-venta <?php echo !$caja_abierta ? 'modulo-deshabilitado' : ''; ?>">
                <div class="modulo-icon">💰</div>
                <div class="modulo-titulo">Punto de Venta</div>
                <div class="modulo-descripcion">Realizar ventas y cobrar productos</div>
                <?php if (!$caja_abierta): ?>
                    <div style="margin-top: 10px; font-size: 12px; color: rgba(255,255,255,0.8);">⚠️ Requiere caja abierta</div>
                <?php endif; ?>
            </a>

            <a href="entrada_mercancia.php" class="modulo modulo-entrada">
                <div class="modulo-icon">📦</div>
                <div class="modulo-titulo">Entrada de Mercancía</div>
                <div class="modulo-descripcion">Aumentar stock de productos</div>
            </a>

            <a href="agregar_producto.php" class="modulo modulo-agregar">
                <div class="modulo-icon">➕</div>
                <div class="modulo-titulo">Agregar Producto</div>
                <div class="modulo-descripcion">Registrar nuevos productos al inventario</div>
            </a>

            <?php if ($caja_abierta): ?>
            <a href="retiro_efectivo.php" class="modulo modulo-retiro">
                <div class="modulo-icon">💸</div>
                <div class="modulo-titulo">Retiro de Efectivo</div>
                <div class="modulo-descripcion">Sacar dinero de la caja</div>
            </a>
            <?php endif; ?>

            <a href="historial_ventas.php" class="modulo modulo-historial">
                <div class="modulo-icon">📋</div>
                <div class="modulo-titulo">Historial de Ventas</div>
                <div class="modulo-descripcion">Ver todas las ventas realizadas</div>
            </a>

            <a href="cierre_caja.php" class="modulo modulo-cierre <?php echo !$caja_abierta ? 'modulo-deshabilitado' : ''; ?>">
                <div class="modulo-icon">💵</div>
                <div class="modulo-titulo">Cierre de Caja</div>
                <div class="modulo-descripcion">Realizar arqueo y cierre de caja</div>
                <?php if (!$caja_abierta): ?>
                    <div style="margin-top: 10px; font-size: 12px; color: #999;">⚠️ Requiere caja abierta</div>
                <?php endif; ?>
            </a>

            <a href="escanear.php" class="modulo modulo-scanner">
                <div class="modulo-icon">🔫</div>
                <div class="modulo-titulo">Escáner de Códigos</div>
                <div class="modulo-descripcion">Consultar productos por código de barras</div>
            </a>
        </div>
    </div>

    <script>
        // Valores reales (PHP)
        const ventasHoy = <?php echo $ventas_hoy; ?>;
        const ventasMes = <?php echo $ventas_mes; ?>;
        
        // Estados de revelación
        let hoyRevelado = false;
        let mesRevelado = false;

        function revelarVentas(tipo) {
            if (tipo === 'hoy') {
                const card = document.getElementById('ventasHoy');
                const valor = document.getElementById('valorHoy');
                
                if (!hoyRevelado) {
                    card.classList.add('revelado');
                    valor.classList.remove('censurado');
                    valor.classList.add('revelado');
                    valor.textContent = '$' + ventasHoy.toFixed(2);
                    hoyRevelado = true;
                } else {
                    card.classList.remove('revelado');
                    valor.classList.remove('revelado');
                    valor.classList.add('censurado');
                    valor.textContent = '$•••.••';
                    hoyRevelado = false;
                }
            } else if (tipo === 'mes') {
                const card = document.getElementById('ventasMes');
                const valor = document.getElementById('valorMes');
                
                if (!mesRevelado) {
                    card.classList.add('revelado');
                    valor.classList.remove('censurado');
                    valor.classList.add('revelado');
                    valor.textContent = '$' + ventasMes.toFixed(2);
                    mesRevelado = true;
                } else {
                    card.classList.remove('revelado');
                    valor.classList.remove('revelado');
                    valor.classList.add('censurado');
                    valor.textContent = '$•••.••';
                    mesRevelado = false;
                }
            }
        }
    </script>
</body>
</html>
