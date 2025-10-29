<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$venta_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($venta_id <= 0) {
    header("Location: historial_ventas.php");
    exit();
}

try {
    // Obtener información de la venta
    $stmt = $pdo->prepare("
        SELECT id_venta, fecha_venta, total, id_encargado
        FROM Ventas
        WHERE id_venta = ?
    ");
    $stmt->execute([$venta_id]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$venta) {
        header("Location: historial_ventas.php");
        exit();
    }
    
    // Obtener detalle de productos usando el esquema correcto
    $stmt = $pdo->prepare("
        SELECT dv.cantidad, dv.precio_unitario, 
               (dv.cantidad * dv.precio_unitario) as subtotal,
               p.nombre_producto, p.codigo_barras
        FROM Detalle_Venta dv
        LEFT JOIN Productos p ON dv.id_producto = p.id_producto
        WHERE dv.id_venta = ?
        ORDER BY dv.id_detalle_venta
    ");
    $stmt->execute([$venta_id]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Error al cargar la venta: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Venta #<?php echo $venta_id; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container { max-width: 800px; margin: 0 auto; }
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
        .info-venta {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .info-item {
            padding: 15px;
            background: #f8f9ff;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .info-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .info-value {
            font-size: 18px;
            color: #333;
            font-weight: bold;
        }
        .productos-box {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .productos-box h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead {
            background: #f8f9ff;
        }
        th {
            padding: 12px;
            text-align: left;
            font-weight: bold;
            color: #667eea;
            border-bottom: 2px solid #667eea;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        .producto-nombre {
            font-weight: bold;
            color: #333;
        }
        .producto-codigo {
            font-size: 12px;
            color: #666;
        }
        .cantidad {
            text-align: center;
            font-weight: bold;
            color: #667eea;
        }
        .precio {
            text-align: right;
            color: #666;
        }
        .subtotal {
            text-align: right;
            font-weight: bold;
            color: #4CAF50;
        }
        .total-box {
            background: #667eea;
            color: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
        }
        .total-label {
            font-size: 18px;
            margin-bottom: 10px;
        }
        .total-value {
            font-size: 48px;
            font-weight: bold;
        }
        .error-box {
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        @media print {
            body { background: white; padding: 0; }
            .btn-volver, .btn-imprimir { display: none; }
        }
        .btn-imprimir {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>🧾 Venta #<?php echo $venta_id; ?></h1>
            </div>
            <div style="display: flex; gap: 10px;">
                <button onclick="window.print()" class="btn-imprimir">🖨️ Imprimir</button>
                <a href="historial_ventas.php" class="btn-volver">← Volver</a>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-box">
                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php else: ?>
            <!-- Información de la venta -->
            <div class="info-venta">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">📅 FECHA</div>
                        <div class="info-value">
                            <?php echo date('d/m/Y', strtotime($venta['fecha_venta'])); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">🕐 HORA</div>
                        <div class="info-value">
                            <?php echo date('H:i', strtotime($venta['fecha_venta'])); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">👤 VENDEDOR</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($venta['vendedor']); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Productos vendidos -->
            <div class="productos-box">
                <h2>🛒 Productos Vendidos</h2>
                <?php if (count($productos) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th style="text-align: center;">Cantidad</th>
                            <th style="text-align: right;">Precio Unit.</th>
                            <th style="text-align: right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $prod): ?>
                            <?php
                            // Detectar si es granel (cantidad con decimales)
                            $esGranel = ($prod['cantidad'] != intval($prod['cantidad']));
                            $cantidad_formato = $esGranel ? number_format($prod['cantidad'], 3) . ' kg' : number_format($prod['cantidad'], 0);
                            
                            // Si no hay nombre de producto (NULL), mostrar mensaje
                            $nombre_producto = $prod['nombre_producto'] ?? 'Producto no encontrado';
                            ?>
                            <tr>
                                <td>
                                    <div class="producto-nombre">
                                        <?php echo htmlspecialchars($nombre_producto); ?>
                                    </div>
                                    <?php if ($prod['codigo_barras']): ?>
                                        <?php if (!$esGranel): ?>
                                            <div class="producto-codigo">
                                                Código: <?php echo htmlspecialchars($prod['codigo_barras']); ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="producto-codigo" style="color: #F57C00;">
                                                ⚖️ Producto a granel
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="cantidad"><?php echo $cantidad_formato; ?></td>
                                <td class="precio">$<?php echo number_format($prod['precio_unitario'], 2); ?></td>
                                <td class="subtotal">$<?php echo number_format($prod['subtotal'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #999;">
                        No hay productos registrados en esta venta
                    </div>
                <?php endif; ?>
            </div>

            <!-- Total -->
            <div class="total-box">
                <div class="total-label">TOTAL PAGADO</div>
                <div class="total-value">$<?php echo number_format($venta['total'], 2); ?></div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
