<?php
session_start();
require_once 'config.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_nombre = $_SESSION['usuario_nombre'];
$es_encargado = ($_SESSION['rol'] === 'encargado');

// Filtros
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d');
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$vendedor_filtro = isset($_GET['vendedor']) ? $_GET['vendedor'] : '';

// Obtener lista de vendedores para el filtro
$stmt_vendedores = $pdo->query("SELECT DISTINCT usuario_nombre FROM ventas ORDER BY usuario_nombre");
$vendedores = $stmt_vendedores->fetchAll(PDO::FETCH_COLUMN);

// Consultar ventas con filtros
$sql = "SELECT v.*, 
        (SELECT COUNT(*) FROM venta_detalle WHERE venta_id = v.id) as cantidad_productos,
        (SELECT SUM(cantidad) FROM venta_detalle WHERE venta_id = v.id) as total_articulos
        FROM ventas v 
        WHERE DATE(v.fecha_venta) BETWEEN :fecha_inicio AND :fecha_fin";

if ($vendedor_filtro && $es_encargado) {
    $sql .= " AND v.usuario_nombre = :vendedor";
}

$sql .= " ORDER BY v.fecha_venta DESC";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':fecha_inicio', $fecha_inicio);
$stmt->bindParam(':fecha_fin', $fecha_fin);
if ($vendedor_filtro && $es_encargado) {
    $stmt->bindParam(':vendedor', $vendedor_filtro);
}
$stmt->execute();
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales
$total_ventas = 0;
$total_cantidad = count($ventas);
foreach ($ventas as $venta) {
    $total_ventas += $venta['total'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Ventas - SCAV</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .header h1 {
            color: #667eea;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .user-info {
            color: #666;
            font-size: 14px;
        }

        .filtros {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .filtros h3 {
            color: #667eea;
            margin-bottom: 15px;
        }

        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group select {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .btn-filtrar {
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }

        .btn-filtrar:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .resumen {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .resumen-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .resumen-card h4 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .resumen-card .valor {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
        }

        .tabla-container {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
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
            background: #f8f9ff;
        }

        .btn-ver {
            background: #4CAF50;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }

        .btn-ver:hover {
            background: #45a049;
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

        .no-ventas {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 18px;
        }

        @media (max-width: 768px) {
            .filtros-grid {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 14px;
            }

            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Historial de Ventas</h1>
            <div class="user-info">
                👤 Usuario: <strong><?php echo htmlspecialchars($usuario_nombre); ?></strong>
                <?php if ($es_encargado): ?>
                    <span style="color: #4CAF50; margin-left: 10px;">⭐ Encargado</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filtros">
            <h3>🔍 Filtros de Búsqueda</h3>
            <form method="GET">
                <div class="filtros-grid">
                    <div class="form-group">
                        <label>📅 Fecha Inicio:</label>
                        <input type="date" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>📅 Fecha Fin:</label>
                        <input type="date" name="fecha_fin" value="<?php echo $fecha_fin; ?>" required>
                    </div>
                    <?php if ($es_encargado && count($vendedores) > 0): ?>
                    <div class="form-group">
                        <label>👤 Vendedor:</label>
                        <select name="vendedor">
                            <option value="">Todos los vendedores</option>
                            <?php foreach ($vendedores as $vendedor): ?>
                                <option value="<?php echo htmlspecialchars($vendedor); ?>" 
                                        <?php echo ($vendedor_filtro === $vendedor) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($vendedor); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn-filtrar">🔍 Buscar Ventas</button>
            </form>
        </div>

        <!-- Resumen -->
        <div class="resumen">
            <div class="resumen-card">
                <h4>💰 Total Vendido</h4>
                <div class="valor">$<?php echo number_format($total_ventas, 2); ?></div>
            </div>
            <div class="resumen-card">
                <h4>🧾 Número de Ventas</h4>
                <div class="valor"><?php echo $total_cantidad; ?></div>
            </div>
            <div class="resumen-card">
                <h4>📊 Promedio por Venta</h4>
                <div class="valor">$<?php echo $total_cantidad > 0 ? number_format($total_ventas / $total_cantidad, 2) : '0.00'; ?></div>
            </div>
        </div>

        <!-- Tabla de Ventas -->
        <div class="tabla-container">
            <?php if (count($ventas) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>🔢 ID</th>
                            <th>📅 Fecha</th>
                            <th>🕐 Hora</th>
                            <th>👤 Vendedor</th>
                            <th>📦 Productos</th>
                            <th>🔢 Artículos</th>
                            <th>💰 Total</th>
                            <th>⚙️ Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ventas as $venta): ?>
                        <tr>
                            <td><?php echo $venta['id']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($venta['fecha_venta'])); ?></td>
                            <td><?php echo date('H:i', strtotime($venta['fecha_venta'])); ?></td>
                            <td><?php echo htmlspecialchars($venta['usuario_nombre']); ?></td>
                            <td><?php echo $venta['cantidad_productos']; ?></td>
                            <td><?php echo $venta['total_articulos']; ?></td>
                            <td><strong>$<?php echo number_format($venta['total'], 2); ?></strong></td>
                            <td>
                                <a href="detalle_venta.php?id=<?php echo $venta['id']; ?>" class="btn-ver">
                                    👁️ Ver Detalle
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-ventas">
                    😔 No se encontraron ventas para el período seleccionado
                </div>
            <?php endif; ?>
        </div>

        <a href="index.php" class="btn-volver">⬅️ Volver al Inicio</a>
    </div>
</body>
</html>
