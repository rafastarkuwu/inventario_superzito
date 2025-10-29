<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Obtener productos con stock bajo
try {
    $stmt = $pdo->query("
        SELECT p.id_producto, p.nombre_producto, p.codigo_barras, p.precio_venta, 
               i.stock_actual, i.stock_minimo
        FROM Productos p
        INNER JOIN Inventario i ON p.id_inventario = i.id_inventario
        WHERE i.stock_actual <= i.stock_minimo
        ORDER BY i.stock_actual ASC
    ");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $productos = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos con Stock Bajo</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container { max-width: 1000px; margin: 0 auto; }
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { color: #ff4444; font-size: 28px; }
        .btn-volver {
            background: #666;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
        }
        .alerta-info {
            background: #fff3cd;
            border-left: 4px solid #ff9800;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #856404;
        }
        .productos-box {
            background: white;
            padding: 25px;
            border-radius: 10px;
        }
        .producto-item {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 150px;
            gap: 15px;
            padding: 15px;
            border: 2px solid #ffebee;
            border-left: 5px solid #ff4444;
            border-radius: 8px;
            margin-bottom: 15px;
            align-items: center;
            background: #fff5f5;
        }
        .producto-info h3 { color: #333; margin-bottom: 5px; }
        .producto-info p { color: #666; font-size: 14px; }
        .producto-tipo {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            margin-top: 3px;
        }
        .tipo-normal { background: #e3f2fd; color: #1976D2; }
        .tipo-granel { background: #fff3e0; color: #F57C00; }
        .stock-info { text-align: center; }
        .stock-actual {
            font-size: 28px;
            font-weight: bold;
            color: #ff4444;
        }
        .stock-minimo {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .precio-info {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
        }
        .btn-agregar {
            background: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .vacio {
            text-align: center;
            padding: 60px;
            color: #4CAF50;
            font-size: 18px;
        }
        .vacio-icono {
            font-size: 64px;
            margin-bottom: 15px;
        }
        .contador {
            background: #ff4444;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>⚠️ Productos con Stock Bajo 
                    <span class="contador"><?php echo count($productos); ?> productos</span>
                </h1>
            </div>
            <a href="index.php" class="btn-volver">← Volver</a>
        </div>

        <?php if (!empty($productos)): ?>
            <div class="alerta-info">
                <strong>⚠️ Atención:</strong> Los siguientes productos tienen stock igual o menor al stock mínimo configurado. 
                Considera realizar una entrada de mercancía pronto.
            </div>

            <div class="productos-box">
                <?php foreach ($productos as $prod): ?>
                    <?php 
                    $esGranel = strpos($prod['codigo_barras'], 'GRANEL-') === 0;
                    $unidad = $esGranel ? 'kg' : 'unid';
                    $formato = $esGranel ? 3 : 0;
                    ?>
                    <div class="producto-item">
                        <div class="producto-info">
                            <h3><?php echo htmlspecialchars($prod['nombre_producto']); ?></h3>
                            <p>
                                <?php if (!$esGranel): ?>
                                    📦 Código: <?php echo htmlspecialchars($prod['codigo_barras']); ?>
                                <?php endif; ?>
                                <span class="producto-tipo <?php echo $esGranel ? 'tipo-granel' : 'tipo-normal'; ?>">
                                    <?php echo $esGranel ? '⚖️ A GRANEL' : '📦 NORMAL'; ?>
                                </span>
                            </p>
                        </div>
                        
                        <div class="stock-info">
                            <div class="stock-actual">
                                <?php echo number_format($prod['stock_actual'], $formato); ?>
                            </div>
                            <div class="stock-minimo">
                                Mínimo: <?php echo number_format($prod['stock_minimo'], $formato); ?> <?php echo $unidad; ?>
                            </div>
                        </div>
                        
                        <div class="precio-info">
                            $<?php echo number_format($prod['precio_venta'], 2); ?>
                            <div style="font-size: 12px; color: #666; font-weight: normal;">
                                /<?php echo $unidad; ?>
                            </div>
                        </div>
                        
                        <a href="entrada_mercancia.php?buscar=<?php echo urlencode($prod['nombre_producto']); ?>" class="btn-agregar">
                            ➕ Agregar Stock
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="productos-box">
                <div class="vacio">
                    <div class="vacio-icono">✅</div>
                    <h2>¡Todo en orden!</h2>
                    <p>No hay productos con stock bajo en este momento</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
