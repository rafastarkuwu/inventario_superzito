<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$caja_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($caja_id <= 0) {
    header("Location: index.php");
    exit();
}

try {
    // Obtener información de la caja cerrada
    $stmt = $pdo->prepare("
        SELECT c.*, CONCAT(p.nombre, ' ', p.apellido) as encargado_nombre
        FROM Cajas c
        INNER JOIN Encargado e ON c.id_encargado = e.id_encargado
        INNER JOIN Persona p ON e.id_persona = p.id_persona
        WHERE c.id_caja = ? AND c.estado = 'cerrada'
    ");
    $stmt->execute([$caja_id]);
    $caja = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$caja) {
        header("Location: index.php");
        exit();
    }
    
    // Obtener retiros de esta caja
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_retiros,
            COALESCE(SUM(monto), 0) as total_dinero_retiros
        FROM Retiros 
        WHERE id_caja = ?
    ");
    $stmt->execute([$caja_id]);
    $retiros = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener detalle de retiros para mostrar
    $stmt = $pdo->prepare("
        SELECT * FROM Retiros 
        WHERE id_caja = ? 
        ORDER BY fecha_retiro ASC
    ");
    $stmt->execute([$caja_id]);
    $detalle_retiros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $monto_inicial = floatval($caja['monto_inicial']);
    $monto_ventas = floatval($caja['monto_ventas']);
    $monto_retiros = floatval($retiros['total_dinero_retiros']);
    $monto_final = floatval($caja['monto_final']);
    
    $monto_esperado = $monto_inicial + $monto_ventas - $monto_retiros;
    $diferencia = $monto_final - $monto_esperado;
    
} catch (Exception $e) {
    $error = "Error al cargar el cierre: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Cierre de Caja</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container { max-width: 900px; margin: 0 auto; }
        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        .header h1 {
            color: #28a745;
            font-size: 36px;
            margin-bottom: 10px;
        }
        .success-icon {
            font-size: 80px;
            margin-bottom: 15px;
        }
        .info-box {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        .info-box h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 22px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }
        .info-item {
            background: #f8f9ff;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .info-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .info-value {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        .resumen-dinero {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        .resumen-dinero h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 22px;
        }
        .linea-dinero {
            display: flex;
            justify-content: space-between;
            padding: 15px;
            border-bottom: 1px solid #eee;
            font-size: 18px;
        }
        .linea-dinero:last-child {
            border-bottom: none;
        }
        .linea-dinero.positivo .valor {
            color: #4CAF50;
        }
        .linea-dinero.negativo .valor {
            color: #dc3545;
        }
        .linea-dinero.total {
            background: #667eea;
            color: white;
            border-radius: 10px;
            margin-top: 15px;
            font-size: 22px;
            font-weight: bold;
        }
        .linea-dinero.esperado {
            background: #f8f9ff;
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #667eea;
        }
        .diferencia-box {
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 20px;
        }
        .diferencia-box.positiva {
            background: #d4edda;
            border: 3px solid #28a745;
        }
        .diferencia-box.negativa {
            background: #f8d7da;
            border: 3px solid #dc3545;
        }
        .diferencia-box.exacta {
            background: #d1ecf1;
            border: 3px solid #17a2b8;
        }
        .diferencia-box .label {
            font-size: 18px;
            margin-bottom: 10px;
        }
        .diferencia-box .valor {
            font-size: 48px;
            font-weight: bold;
        }
        .retiros-box {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        .retiros-box h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 22px;
            border-bottom: 2px solid #FF9800;
            padding-bottom: 10px;
        }
        .retiro-item {
            background: #fff3e0;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 15px;
            align-items: center;
        }
        .retiro-hora {
            font-size: 14px;
            color: #666;
            font-weight: bold;
        }
        .retiro-info {
            flex: 1;
        }
        .retiro-motivo {
            font-weight: bold;
            color: #333;
            margin-bottom: 3px;
        }
        .retiro-obs {
            font-size: 12px;
            color: #666;
        }
        .retiro-monto {
            font-size: 20px;
            font-weight: bold;
            color: #dc3545;
        }
        .observaciones-box {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        .observaciones-box h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 20px;
        }
        .observaciones-text {
            background: #f8f9ff;
            padding: 20px;
            border-radius: 10px;
            color: #333;
            line-height: 1.6;
            white-space: pre-wrap;
        }
        .buttons {
            display: flex;
            gap: 15px;
        }
        .btn {
            flex: 1;
            padding: 18px;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        .btn-primary:hover {
            background: #45a049;
        }
        .btn-secondary {
            background: #667eea;
            color: white;
        }
        .btn-secondary:hover {
            background: #5568d3;
        }
        @media print {
            body { background: white; padding: 0; }
            .buttons { display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="success-icon">✅</div>
            <h1>Caja Cerrada Exitosamente</h1>
            <p style="color: #666; font-size: 16px; margin-top: 10px;">
                Reporte de Cierre #<?php echo $caja_id; ?>
            </p>
        </div>

        <!-- Información general -->
        <div class="info-box">
            <h2>📋 Información del Turno</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">👤 ENCARGADO</div>
                    <div class="info-value" style="font-size: 18px;">
                        <?php echo htmlspecialchars($caja['encargado_nombre']); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">📅 FECHA</div>
                    <div class="info-value" style="font-size: 18px;">
                        <?php echo date('d/m/Y', strtotime($caja['fecha_apertura'])); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">🕐 APERTURA</div>
                    <div class="info-value" style="font-size: 20px;">
                        <?php echo date('H:i', strtotime($caja['fecha_apertura'])); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">🕐 CIERRE</div>
                    <div class="info-value" style="font-size: 20px;">
                        <?php echo date('H:i', strtotime($caja['fecha_cierre'])); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumen de dinero -->
        <div class="resumen-dinero">
            <h2>💰 Resumen Financiero</h2>
            
            <div class="linea-dinero">
                <span>💵 Monto Inicial (Fondo de caja):</span>
                <strong class="valor">$<?php echo number_format($monto_inicial, 2); ?></strong>
            </div>
            
            <div class="linea-dinero positivo">
                <span>🧾 Total de Ventas del Turno:</span>
                <strong class="valor">+ $<?php echo number_format($monto_ventas, 2); ?></strong>
            </div>
            
            <?php if ($monto_retiros > 0): ?>
            <div class="linea-dinero negativo">
                <span>💸 Total de Retiros (<?php echo $retiros['total_retiros']; ?> retiros):</span>
                <strong class="valor">- $<?php echo number_format($monto_retiros, 2); ?></strong>
            </div>
            <?php endif; ?>
            
            <div class="linea-dinero esperado">
                <span><strong>💎 Dinero que debería haber:</strong></span>
                <strong style="color: #667eea; font-size: 20px;">
                    $<?php echo number_format($monto_esperado, 2); ?>
                </strong>
            </div>
            
            <div class="linea-dinero total">
                <span>💵 Dinero contado en caja:</span>
                <span>$<?php echo number_format($monto_final, 2); ?></span>
            </div>
        </div>

        <!-- Detalle de retiros si hay -->
        <?php if (!empty($detalle_retiros)): ?>
        <div class="retiros-box">
            <h2>💸 Detalle de Retiros del Turno</h2>
            <?php foreach ($detalle_retiros as $retiro): ?>
            <div class="retiro-item">
                <div class="retiro-hora">
                    🕐 <?php echo date('H:i', strtotime($retiro['fecha_retiro'])); ?>
                </div>
                <div class="retiro-info">
                    <div class="retiro-motivo"><?php echo htmlspecialchars($retiro['motivo']); ?></div>
                    <?php if ($retiro['observaciones']): ?>
                    <div class="retiro-obs"><?php echo htmlspecialchars($retiro['observaciones']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="retiro-monto">
                    -$<?php echo number_format($retiro['monto'], 2); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Diferencia -->
        <?php
        $clase_diferencia = 'exacta';
        $icono_diferencia = '✅';
        $texto_diferencia = '¡PERFECTO!';
        $subtexto = 'El dinero cuadra exactamente';
        
        if (abs($diferencia) >= 0.01) {
            if ($diferencia > 0) {
                $clase_diferencia = 'positiva';
                $icono_diferencia = '💰';
                $texto_diferencia = 'SOBRANTE';
                $subtexto = 'Hay más dinero del esperado';
            } else {
                $clase_diferencia = 'negativa';
                $icono_diferencia = '⚠️';
                $texto_diferencia = 'FALTANTE';
                $subtexto = 'Falta dinero en caja';
            }
        }
        ?>
        
        <div class="diferencia-box <?php echo $clase_diferencia; ?>">
            <div class="label"><?php echo $icono_diferencia; ?> <?php echo $texto_diferencia; ?></div>
            <div class="valor">
                <?php if (abs($diferencia) < 0.01): ?>
                    $0.00
                <?php else: ?>
                    $<?php echo number_format(abs($diferencia), 2); ?>
                <?php endif; ?>
            </div>
            <div style="margin-top: 10px; font-size: 16px;">
                <?php echo $subtexto; ?>
            </div>
        </div>

        <!-- Observaciones -->
        <?php if ($caja['observaciones']): ?>
        <div class="observaciones-box">
            <h2>📝 Observaciones</h2>
            <div class="observaciones-text">
                <?php echo htmlspecialchars($caja['observaciones']); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Botones -->
        <div class="buttons">
            <button onclick="window.print()" class="btn btn-primary">
                🖨️ Imprimir Reporte
            </button>
            <a href="apertura_caja.php" class="btn btn-secondary">
                🔄 Abrir Nueva Caja
            </a>
            <a href="index.php" class="btn btn-secondary">
                🏠 Ir al Inicio
            </a>
        </div>
    </div>
</body>
</html>
