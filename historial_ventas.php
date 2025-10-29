<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Parámetros de filtrado
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$por_pagina = 50;
$offset = ($pagina - 1) * $por_pagina;

// Construir consulta con filtros
$where_conditions = [];
$params = [];

if (!empty($fecha_inicio)) {
    $where_conditions[] = "DATE(v.fecha_venta) >= :fecha_inicio";
    $params[':fecha_inicio'] = $fecha_inicio;
}

if (!empty($fecha_fin)) {
    $where_conditions[] = "DATE(v.fecha_venta) <= :fecha_fin";
    $params[':fecha_fin'] = $fecha_fin;
}

if (!empty($buscar)) {
    $where_conditions[] = "(v.id_venta LIKE :buscar OR p.nombre LIKE :buscar2)";
    $params[':buscar'] = '%' . $buscar . '%';
    $params[':buscar2'] = '%' . $buscar . '%';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Contar total de ventas
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT v.id_venta) as total
        FROM Ventas v
        LEFT JOIN Encargado e ON v.id_encargado = e.id_encargado
        LEFT JOIN Persona p ON e.id_persona = p.id_persona
        $where_clause
    ");
    $stmt->execute($params);
    $total_ventas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_paginas = ceil($total_ventas / $por_pagina);
    
    // Obtener ventas con paginación
    $stmt = $pdo->prepare("
        SELECT v.id_venta, v.fecha_venta, v.total, 
               CONCAT(p.nombre, ' ', p.apellido) as vendedor
        FROM Ventas v
        LEFT JOIN Encargado e ON v.id_encargado = e.id_encargado
        LEFT JOIN Persona p ON e.id_persona = p.id_persona
        $where_clause
        ORDER BY v.fecha_venta DESC
        LIMIT :limit OFFSET :offset
    ");
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas generales (con filtros aplicados)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT v.id_venta) as total_tickets,
            SUM(v.total) as total_ventas,
            AVG(v.total) as promedio_ticket,
            MIN(v.fecha_venta) as primera_venta,
            MAX(v.fecha_venta) as ultima_venta
        FROM Ventas v
        LEFT JOIN Encargado e ON v.id_encargado = e.id_encargado
        LEFT JOIN Persona p ON e.id_persona = p.id_persona
        $where_clause
    ");
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $ventas = [];
    $stats = [
        'total_tickets' => 0,
        'total_ventas' => 0,
        'promedio_ticket' => 0,
        'primera_venta' => null,
        'ultima_venta' => null
    ];
    $total_ventas = 0;
    $total_paginas = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Ventas</title>
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
        }
        
        /* Estadísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #666;
            font-size: 13px;
        }
        
        /* Filtros */
        .filtros-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .filtros-buttons {
            display: flex;
            gap: 10px;
        }
        .btn-filtrar {
            flex: 1;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-limpiar {
            padding: 12px 20px;
            background: #666;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        /* Tabla */
        .ventas-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            overflow-x: auto;
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
        tbody tr:hover {
            background: #f8f9ff;
        }
        .venta-id {
            font-weight: bold;
            color: #667eea;
        }
        .venta-total {
            font-weight: bold;
            color: #4CAF50;
            font-size: 16px;
        }
        .btn-ver {
            background: #667eea;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
        }
        .btn-ver:hover {
            background: #5568d3;
        }
        
        /* Paginación */
        .paginacion {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding: 20px;
            background: white;
            border-radius: 10px;
        }
        .pag-btn {
            padding: 10px 15px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
        }
        .pag-btn:hover {
            background: #5568d3;
        }
        .pag-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .pag-info {
            color: #666;
            font-weight: bold;
        }
        
        .vacio {
            text-align: center;
            padding: 60px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>📋 Historial de Ventas</h1>
                <p style="color: #666; font-size: 14px; margin-top: 5px;">
                    Registro completo de todas las ventas
                </p>
            </div>
            <a href="index.php" class="btn-volver">← Volver</a>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_tickets']); ?></div>
                <div class="stat-label">Total de Ventas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #4CAF50;">
                    $<?php echo number_format($stats['total_ventas'], 2); ?>
                </div>
                <div class="stat-label">Monto Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #FF9800;">
                    $<?php echo number_format($stats['promedio_ticket'], 2); ?>
                </div>
                <div class="stat-label">Ticket Promedio</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="font-size: 16px; color: #666;">
                    <?php 
                    if ($stats['primera_venta']) {
                        echo date('d/m/Y', strtotime($stats['primera_venta']));
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </div>
                <div class="stat-label">Primera Venta</div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filtros-box">
            <form method="GET">
                <div class="filtros-grid">
                    <div class="form-group">
                        <label>📅 Fecha Inicio:</label>
                        <input type="date" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>">
                    </div>
                    <div class="form-group">
                        <label>📅 Fecha Fin:</label>
                        <input type="date" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin); ?>">
                    </div>
                    <div class="form-group">
                        <label>🔍 Buscar:</label>
                        <input type="text" name="buscar" placeholder="ID venta o vendedor..." 
                               value="<?php echo htmlspecialchars($buscar); ?>">
                    </div>
                </div>
                <div class="filtros-buttons">
                    <button type="submit" class="btn-filtrar">🔍 Filtrar</button>
                    <a href="historial_ventas.php" class="btn-limpiar" style="text-decoration:none; text-align:center; line-height:1;">
                        🗑️ Limpiar
                    </a>
                </div>
            </form>
        </div>

        <!-- Tabla de ventas -->
        <div class="ventas-box">
            <?php if (!empty($ventas)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID Venta</th>
                            <th>Fecha y Hora</th>
                            <th>Vendedor</th>
                            <th>Total</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ventas as $venta): ?>
                            <tr>
                                <td class="venta-id">#<?php echo $venta['id_venta']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha_venta'])); ?></td>
                                <td><?php echo htmlspecialchars($venta['vendedor']); ?></td>
                                <td class="venta-total">$<?php echo number_format($venta['total'], 2); ?></td>
                                <td>
                                    <a href="detalle_venta.php?id=<?php echo $venta['id_venta']; ?>" class="btn-ver">
                                        👁️ Ver Detalle
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>
                    <div class="paginacion">
                        <?php if ($pagina > 1): ?>
                            <a href="?pagina=<?php echo $pagina - 1; ?>&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&buscar=<?php echo urlencode($buscar); ?>" 
                               class="pag-btn">← Anterior</a>
                        <?php else: ?>
                            <button class="pag-btn" disabled>← Anterior</button>
                        <?php endif; ?>

                        <span class="pag-info">
                            Página <?php echo $pagina; ?> de <?php echo $total_paginas; ?>
                        </span>

                        <?php if ($pagina < $total_paginas): ?>
                            <a href="?pagina=<?php echo $pagina + 1; ?>&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&buscar=<?php echo urlencode($buscar); ?>" 
                               class="pag-btn">Siguiente →</a>
                        <?php else: ?>
                            <button class="pag-btn" disabled>Siguiente →</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="vacio">
                    <div style="font-size: 64px; margin-bottom: 15px;">📭</div>
                    <h2>No hay ventas registradas</h2>
                    <p>Aún no se han realizado ventas<?php echo !empty($fecha_inicio) || !empty($fecha_fin) ? ' en el periodo seleccionado' : ''; ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
