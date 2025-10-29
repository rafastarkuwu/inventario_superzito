<?php
session_start();
require_once 'config.php';

// Verificar si el usuario está autenticado y es encargado
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'encargado') {
    header("Location: index.php");
    exit();
}

$usuario_nombre = $_SESSION['usuario_nombre'];
$fecha_actual = date('Y-m-d');

// Obtener ventas del día agrupadas por vendedor
$stmt_vendedores = $pdo->prepare("
    SELECT 
        usuario_nombre,
        COUNT(*) as num_ventas,
        SUM(total) as total_vendido
    FROM ventas 
    WHERE DATE(fecha_venta) = :fecha
    GROUP BY usuario_nombre
    ORDER BY total_vendido DESC
");
$stmt_vendedores->execute([':fecha' => $fecha_actual]);
$ventas_por_vendedor = $stmt_vendedores->fetchAll(PDO::FETCH_ASSOC);

// Obtener total del día
$stmt_total = $pdo->prepare("
    SELECT 
        COUNT(*) as total_ventas,
        SUM(total) as total_dinero,
        MIN(fecha_venta) as primera_venta,
        MAX(fecha_venta) as ultima_venta
    FROM ventas 
    WHERE DATE(fecha_venta) = :fecha
");
$stmt_total->execute([':fecha' => $fecha_actual]);
$resumen_dia = $stmt_total->fetch(PDO::FETCH_ASSOC);

// Obtener productos más vendidos del día
$stmt_productos = $pdo->prepare("
    SELECT 
        p.nombre,
        p.codigo_barras,
        SUM(vd.cantidad) as cantidad_vendida,
        SUM(vd.subtotal) as total_vendido
    FROM venta_detalle vd
    INNER JOIN ventas v ON vd.venta_id = v.id
    LEFT JOIN productos p ON vd.producto_id = p.id
    WHERE DATE(v.fecha_venta) = :fecha
    GROUP BY vd.producto_id, p.nombre, p.codigo_barras
    ORDER BY cantidad_vendida DESC
    LIMIT 10
");
$stmt_productos->execute([':fecha' => $fecha_actual]);
$productos_vendidos = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);

// Obtener entradas de mercancía del día
$stmt_entradas = $pdo->prepare("
    SELECT 
        usuario_nombre,
        COUNT(*) as num_entradas,
        SUM(cantidad) as total_unidades
    FROM historial_stock 
    WHERE DATE(fecha) = :fecha AND tipo = 'entrada'
    GROUP BY usuario_nombre
");
$stmt_entradas->execute([':fecha' => $fecha_actual]);
$entradas_dia = $stmt_entradas->fetchAll(PDO::FETCH_ASSOC);

// Calcular total de artículos vendidos
$total_articulos_vendidos = 0;
foreach ($productos_vendidos as $prod) {
    $total_articulos_vendidos += $prod['cantidad_vendida'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cierre de Caja - SCAV</title>
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
            text-align: center;
        }

        .header h1 {
            color: #667eea;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .fecha-actual {
            color: #666;
            font-size: 18px;
            margin-bottom: 5px;
        }

        .user-info {
            color: #4CAF50;
            font-size: 14px;
        }

        .resumen-principal {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card-resumen {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            text-align: center;
        }

        .card-resumen .icono {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .card-resumen .label {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .card-resumen .valor {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }

        .card-resumen .extra {
            color: #999;
            font-size: 12px;
            margin-top: 5px;
        }

        .seccion {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .seccion h2 {
            color: #667eea;
            font-size: 22px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9ff;
            color: #667eea;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }

        tr:hover {
            background: #f8f9ff;
        }

        .vendedor-destacado {
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            text-align: center;
        }

        .vendedor-destacado .titulo {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .vendedor-destacado .nombre {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }

        .vendedor-destacado .monto {
            font-size: 32px;
            font-weight: bold;
            color: #ff6b6b;
            margin-top: 5px;
        }

        .botones {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .btn {
            padding: 15px 40px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-weight: 600;
        }

        .btn-imprimir {
            background: #4CAF50;
            color: white;
        }

        .btn-imprimir:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .btn-volver {
            background: #666;
            color: white;
        }

        .btn-volver:hover {
            background: #555;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .sin-datos {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 16px;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .botones {
                display: none;
            }

            .seccion {
                page-break-inside: avoid;
            }
        }

        @media (max-width: 768px) {
            .resumen-principal {
                grid-template-columns: 1fr;
            }

            .botones {
                flex-direction: column;
            }

            table {
                font-size: 12px;
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
            <h1>💵 Cierre de Caja</h1>
            <div class="fecha-actual">
                📅 <?php echo date('l, d \d\e F \d\e Y', strtotime($fecha_actual)); ?>
            </div>
            <div class="user-info">
                ⭐ Encargado: <strong><?php echo htmlspecialchars($usuario_nombre); ?></strong>
            </div>
        </div>

        <!-- Resumen Principal -->
        <div class="resumen-principal">
            <div class="card-resumen">
                <div class="icono">💰</div>
                <div class="label">Total en Caja</div>
                <div class="valor">$<?php echo number_format($resumen_dia['total_dinero'] ?? 0, 2); ?></div>
            </div>
            <div class="card-resumen">
                <div class="icono">🧾</div>
                <div class="label">Total de Ventas</div>
                <div class="valor"><?php echo $resumen_dia['total_ventas'] ?? 0; ?></div>
                <?php if ($resumen_dia['total_ventas'] > 0): ?>
                <div class="extra">
                    Promedio: $<?php echo number_format($resumen_dia['total_dinero'] / $resumen_dia['total_ventas'], 2); ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-resumen">
                <div class="icono">📦</div>
                <div class="label">Artículos Vendidos</div>
                <div class="valor"><?php echo $total_articulos_vendidos; ?></div>
            </div>
            <div class="card-resumen">
                <div class="icono">👥</div>
                <div class="label">Vendedores Activos</div>
                <div class="valor"><?php echo count($ventas_por_vendedor); ?></div>
            </div>
        </div>

        <!-- Horario de Operación -->
        <?php if ($resumen_dia['total_ventas'] > 0): ?>
        <div class="seccion">
            <h2>🕐 Horario de Operación</h2>
            <p style="font-size: 16px; color: #666;">
                <strong>Primera Venta:</strong> <?php echo date('H:i:s', strtotime($resumen_dia['primera_venta'])); ?>
                &nbsp;&nbsp;|&nbsp;&nbsp;
                <strong>Última Venta:</strong> <?php echo date('H:i:s', strtotime($resumen_dia['ultima_venta'])); ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- Vendedor Destacado -->
        <?php if (count($ventas_por_vendedor) > 0): ?>
        <div class="vendedor-destacado">
            <div class="titulo">🏆 VENDEDOR DEL DÍA</div>
            <div class="nombre"><?php echo htmlspecialchars($ventas_por_vendedor[0]['usuario_nombre']); ?></div>
            <div class="monto">$<?php echo number_format($ventas_por_vendedor[0]['total_vendido'], 2); ?></div>
            <div style="margin-top: 10px; color: #666;">
                <?php echo $ventas_por_vendedor[0]['num_ventas']; ?> ventas realizadas
            </div>
        </div>
        <?php endif; ?>

        <!-- Ventas por Vendedor -->
        <div class="seccion">
            <h2>👥 Ventas por Vendedor</h2>
            <?php if (count($ventas_por_vendedor) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>🏅 Posición</th>
                        <th>👤 Vendedor</th>
                        <th>🧾 No. Ventas</th>
                        <th>💰 Total Vendido</th>
                        <th>📊 Promedio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventas_por_vendedor as $index => $vendedor): ?>
                    <tr>
                        <td>
                            <?php 
                            if ($index === 0) echo '🥇';
                            elseif ($index === 1) echo '🥈';
                            elseif ($index === 2) echo '🥉';
                            else echo ($index + 1);
                            ?>
                        </td>
                        <td><strong><?php echo htmlspecialchars($vendedor['usuario_nombre']); ?></strong></td>
                        <td><?php echo $vendedor['num_ventas']; ?></td>
                        <td><strong>$<?php echo number_format($vendedor['total_vendido'], 2); ?></strong></td>
                        <td>$<?php echo number_format($vendedor['total_vendido'] / $vendedor['num_ventas'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="sin-datos">😔 No hay ventas registradas el día de hoy</div>
            <?php endif; ?>
        </div>

        <!-- Productos Más Vendidos -->
        <div class="seccion">
            <h2>🔥 Top 10 Productos Más Vendidos</h2>
            <?php if (count($productos_vendidos) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>🏅</th>
                        <th>📦 Producto</th>
                        <th>🔢 Código</th>
                        <th>📊 Cantidad</th>
                        <th>💰 Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos_vendidos as $index => $producto): ?>
                    <tr>
                        <td>
                            <?php 
                            if ($index === 0) echo '🥇';
                            elseif ($index === 1) echo '🥈';
                            elseif ($index === 2) echo '🥉';
                            else echo ($index + 1);
                            ?>
                        </td>
                        <td><strong><?php echo htmlspecialchars($producto['nombre'] ?? 'Producto desconocido'); ?></strong></td>
                        <td><?php echo htmlspecialchars($producto['codigo_barras'] ?? 'N/A'); ?></td>
                        <td><?php echo $producto['cantidad_vendida']; ?> unidades</td>
                        <td><strong>$<?php echo number_format($producto['total_vendido'], 2); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="sin-datos">😔 No hay productos vendidos el día de hoy</div>
            <?php endif; ?>
        </div>

        <!-- Entradas de Mercancía -->
        <?php if (count($entradas_dia) > 0): ?>
        <div class="seccion">
            <h2>📥 Entradas de Mercancía del Día</h2>
            <table>
                <thead>
                    <tr>
                        <th>👤 Usuario</th>
                        <th>📋 No. Entradas</th>
                        <th>📦 Total Unidades</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entradas_dia as $entrada): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($entrada['usuario_nombre']); ?></strong></td>
                        <td><?php echo $entrada['num_entradas']; ?></td>
                        <td><?php echo $entrada['total_unidades']; ?> unidades</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Botones -->
        <div class="botones">
            <button onclick="window.print()" class="btn btn-imprimir">
                🖨️ Imprimir Cierre
            </button>
            <a href="index.php" class="btn btn-volver">
                ⬅️ Volver al Inicio
            </a>
        </div>

        <div style="text-align: center; margin-top: 30px; color: white; font-size: 14px;">
            <p>Reporte generado: <?php echo date('d/m/Y H:i:s'); ?></p>
        </div>
    </div>

    <script>
        // Atajo para imprimir
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>
