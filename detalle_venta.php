<?php
session_start();
require_once 'config.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_nombre = $_SESSION['usuario_nombre'];
$venta_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($venta_id === 0) {
    header("Location: historial_ventas.php");
    exit();
}

// Obtener datos de la venta
$stmt = $pdo->prepare("SELECT * FROM ventas WHERE id = :id");
$stmt->execute([':id' => $venta_id]);
$venta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$venta) {
    header("Location: historial_ventas.php");
    exit();
}

// Obtener detalle de productos
$stmt_detalle = $pdo->prepare("
    SELECT vd.*, p.nombre, p.codigo_barras 
    FROM venta_detalle vd
    LEFT JOIN productos p ON vd.producto_id = p.id
    WHERE vd.venta_id = :venta_id
    ORDER BY vd.id
");
$stmt_detalle->execute([':venta_id' => $venta_id]);
$detalles = $stmt_detalle->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle Venta #<?php echo $venta_id; ?> - SCAV</title>
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
            max-width: 900px;
            margin: 0 auto;
        }

        .ticket {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .ticket-header {
            text-align: center;
            border-bottom: 2px dashed #667eea;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .ticket-header h1 {
            color: #667eea;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .ticket-header p {
            color: #666;
            font-size: 14px;
        }

        .info-venta {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9ff;
            border-radius: 10px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-item label {
            color: #666;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .info-item strong {
            color: #333;
            font-size: 16px;
        }

        .productos-header {
            color: #667eea;
            font-size: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .producto-item {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
        }

        .producto-item:last-child {
            border-bottom: none;
        }

        .producto-info {
            display: flex;
            flex-direction: column;
        }

        .producto-nombre {
            font-weight: bold;
            color: #333;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .producto-codigo {
            color: #666;
            font-size: 12px;
        }

        .producto-precio {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .cantidad {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .precio {
            font-weight: bold;
            color: #667eea;
            font-size: 18px;
        }

        .totales {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9ff;
            border-radius: 10px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 16px;
        }

        .total-final {
            border-top: 2px dashed #667eea;
            margin-top: 10px;
            padding-top: 15px;
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }

        .botones {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-imprimir {
            background: #4CAF50;
            color: white;
        }

        .btn-imprimir:hover {
            background: #45a049;
            transform: translateY(-2px);
        }

        .btn-volver {
            background: #666;
            color: white;
        }

        .btn-volver:hover {
            background: #555;
            transform: translateY(-2px);
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .botones {
                display: none;
            }

            .ticket {
                box-shadow: none;
                page-break-after: always;
            }
        }

        @media (max-width: 768px) {
            .info-venta {
                grid-template-columns: 1fr;
            }

            .botones {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="ticket">
            <div class="ticket-header">
                <h1>🧾 Ticket de Venta</h1>
                <p>Sistema de Control de Artículos y Ventas</p>
            </div>

            <div class="info-venta">
                <div class="info-item">
                    <label>🔢 No. Venta:</label>
                    <strong><?php echo str_pad($venta['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                </div>
                <div class="info-item">
                    <label>📅 Fecha:</label>
                    <strong><?php echo date('d/m/Y H:i', strtotime($venta['fecha_venta'])); ?></strong>
                </div>
                <div class="info-item">
                    <label>👤 Vendedor:</label>
                    <strong><?php echo htmlspecialchars($venta['usuario_nombre']); ?></strong>
                </div>
                <div class="info-item">
                    <label>📦 Total Artículos:</label>
                    <strong><?php 
                        $total_articulos = 0;
                        foreach ($detalles as $det) {
                            $total_articulos += $det['cantidad'];
                        }
                        echo $total_articulos;
                    ?></strong>
                </div>
            </div>

            <div class="productos-header">
                📦 Productos Vendidos
            </div>

            <?php foreach ($detalles as $detalle): ?>
            <div class="producto-item">
                <div class="producto-info">
                    <div class="producto-nombre">
                        <?php echo htmlspecialchars($detalle['nombre'] ?? 'Producto no encontrado'); ?>
                    </div>
                    <div class="producto-codigo">
                        Código: <?php echo htmlspecialchars($detalle['codigo_barras'] ?? 'N/A'); ?>
                    </div>
                </div>
                <div class="producto-precio">
                    <div class="cantidad">
                        <?php echo $detalle['cantidad']; ?> × $<?php echo number_format($detalle['precio_unitario'], 2); ?>
                    </div>
                    <div class="precio">
                        $<?php echo number_format($detalle['subtotal'], 2); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="totales">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <strong>$<?php echo number_format($venta['total'], 2); ?></strong>
                </div>
                <div class="total-row total-final">
                    <span>💰 TOTAL:</span>
                    <span>$<?php echo number_format($venta['total'], 2); ?></span>
                </div>
            </div>

            <div style="text-align: center; margin-top: 30px; color: #666; font-size: 14px;">
                <p>¡Gracias por su compra!</p>
                <p style="margin-top: 10px; font-size: 12px;">
                    Este ticket fue generado electrónicamente<br>
                    <?php echo date('d/m/Y H:i:s'); ?>
                </p>
            </div>
        </div>

        <div class="botones">
            <button onclick="window.print()" class="btn btn-imprimir">
                🖨️ Imprimir Ticket
            </button>
            <a href="historial_ventas.php" class="btn btn-volver">
                ⬅️ Volver al Historial
            </a>
        </div>
    </div>

    <script>
        // Atajo de teclado para imprimir (Ctrl+P)
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>
