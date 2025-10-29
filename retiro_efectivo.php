<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nombre = $_SESSION['usuario_nombre'];

// Verificar si hay una caja abierta
$stmt = $pdo->prepare("
    SELECT * FROM Cajas 
    WHERE id_encargado = ? AND estado = 'abierta' 
    ORDER BY fecha_apertura DESC 
    LIMIT 1
");
$stmt->execute([$usuario_id]);
$caja_actual = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$caja_actual) {
    header("Location: index.php");
    exit();
}

// Calcular dinero disponible
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total), 0) as total_ventas
    FROM Ventas 
    WHERE id_encargado = ? 
    AND fecha_venta >= ?
");
$stmt->execute([$usuario_id, $caja_actual['fecha_apertura']]);
$ventas = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(monto), 0) as total_retiros
    FROM Retiros 
    WHERE id_caja = ?
");
$stmt->execute([$caja_actual['id_caja']]);
$retiros = $stmt->fetch(PDO::FETCH_ASSOC);

$monto_inicial = floatval($caja_actual['monto_inicial']);
$monto_ventas = floatval($ventas['total_ventas']);
$monto_retiros = floatval($retiros['total_retiros']);
$dinero_disponible = $monto_inicial + $monto_ventas - $monto_retiros;

$mensaje = '';
$tipo_mensaje = '';

// Procesar retiro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retirar'])) {
    $monto_retiro = floatval($_POST['monto_retiro']);
    $motivo = trim($_POST['motivo']);
    
    if ($monto_retiro <= 0) {
        $mensaje = "❌ El monto debe ser mayor a cero";
        $tipo_mensaje = "error";
    } elseif ($monto_retiro > $dinero_disponible) {
        $mensaje = "❌ No hay suficiente dinero en caja. Disponible: $" . number_format($dinero_disponible, 2);
        $tipo_mensaje = "error";
    } elseif (empty($motivo)) {
        $mensaje = "❌ Debes especificar el motivo del retiro";
        $tipo_mensaje = "error";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO Retiros (id_caja, monto, motivo, fecha_retiro) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$caja_actual['id_caja'], $monto_retiro, $motivo]);
            
            $mensaje = "✅ Retiro registrado exitosamente. $" . number_format($monto_retiro, 2);
            $tipo_mensaje = "success";
            
            // Actualizar dinero disponible
            $dinero_disponible -= $monto_retiro;
            $monto_retiros += $monto_retiro;
            
        } catch (Exception $e) {
            $mensaje = "Error al registrar retiro: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// Obtener historial de retiros de hoy
$stmt = $pdo->prepare("
    SELECT * FROM Retiros 
    WHERE id_caja = ? 
    ORDER BY fecha_retiro DESC
");
$stmt->execute([$caja_actual['id_caja']]);
$historial_retiros = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retiro de Efectivo</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        .header h1 {
            color: #FF9800;
            font-size: 32px;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            font-size: 16px;
        }
        .alert {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            animation: slideIn 0.3s;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .info-caja {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        .info-caja h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 22px;
            border-bottom: 2px solid #FF9800;
            padding-bottom: 10px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .info-item {
            background: #fff3e0;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .info-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }
        .info-value {
            font-size: 28px;
            font-weight: bold;
            color: #FF9800;
        }
        .dinero-disponible {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        .dinero-disponible .label {
            font-size: 18px;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        .dinero-disponible .valor {
            font-size: 48px;
            font-weight: bold;
        }
        .form-box {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        .form-box h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 22px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
            color: #333;
            font-size: 16px;
        }
        .input-money {
            width: 100%;
            padding: 20px;
            border: 3px solid #FF9800;
            border-radius: 10px;
            font-size: 32px;
            text-align: center;
            font-weight: bold;
            color: #FF9800;
        }
        .input-money:focus {
            outline: none;
            box-shadow: 0 0 20px rgba(255, 152, 0, 0.3);
        }
        .input-text {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
        }
        .input-text:focus {
            outline: none;
            border-color: #FF9800;
        }
        .buttons {
            display: flex;
            gap: 15px;
        }
        .btn {
            flex: 1;
            padding: 20px;
            border: none;
            border-radius: 10px;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #FF9800;
            color: white;
        }
        .btn-primary:hover {
            background: #F57C00;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.4);
        }
        .btn-secondary {
            background: #666;
            color: white;
            text-decoration: none;
            text-align: center;
            line-height: 20px;
        }
        .btn-secondary:hover {
            background: #555;
        }
        .historial {
            background: white;
            padding: 25px;
            border-radius: 15px;
        }
        .historial h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 22px;
        }
        .retiro-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .retiro-info {
            flex: 1;
        }
        .retiro-monto {
            font-size: 24px;
            font-weight: bold;
            color: #dc3545;
        }
        .retiro-motivo {
            color: #666;
            margin-top: 5px;
        }
        .retiro-fecha {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        .hint {
            font-size: 14px;
            color: #666;
            margin-top: 8px;
            text-align: center;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💸 Retiro de Efectivo</h1>
            <div class="subtitle">Sacar dinero de la caja</div>
            <div style="margin-top: 10px; color: #FF9800; font-weight: bold;">
                👤 <?php echo htmlspecialchars($usuario_nombre); ?>
            </div>
        </div>

        <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?>">
            <?php echo $mensaje; ?>
        </div>
        <?php endif; ?>

        <div class="warning-box">
            <strong>⚠️ Importante:</strong> Los retiros quedan registrados en el sistema. Especifica claramente el motivo del retiro.
        </div>

        <!-- Información de la caja -->
        <div class="info-caja">
            <h2>📊 Estado de la Caja</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">💵 Monto Inicial</div>
                    <div class="info-value">$<?php echo number_format($monto_inicial, 2); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">💰 Total Ventas</div>
                    <div class="info-value" style="color: #4CAF50;">$<?php echo number_format($monto_ventas, 2); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">💸 Total Retiros</div>
                    <div class="info-value" style="color: #dc3545;">$<?php echo number_format($monto_retiros, 2); ?></div>
                </div>
            </div>
        </div>

        <!-- Dinero disponible -->
        <div class="dinero-disponible">
            <div class="label">💎 DINERO DISPONIBLE EN CAJA</div>
            <div class="valor">$<?php echo number_format($dinero_disponible, 2); ?></div>
        </div>

        <!-- Formulario de retiro -->
        <form method="POST" id="formRetiro">
            <div class="form-box">
                <h2>💸 Registrar Retiro</h2>
                
                <div class="form-group">
                    <label>¿Cuánto dinero vas a retirar?</label>
                    <input 
                        type="number" 
                        name="monto_retiro" 
                        id="montoRetiro"
                        class="input-money"
                        step="0.01"
                        min="0.01"
                        max="<?php echo $dinero_disponible; ?>"
                        placeholder="0.00"
                        required
                        autofocus>
                    <div class="hint">Máximo disponible: $<?php echo number_format($dinero_disponible, 2); ?></div>
                </div>

                <div class="form-group">
                    <label>📝 Motivo del retiro</label>
                    <input 
                        type="text" 
                        name="motivo" 
                        class="input-text"
                        placeholder="Ej: Pago a proveedor, Compra de cambio, etc."
                        required>
                    <div class="hint">Especifica claramente el motivo del retiro</div>
                </div>
            </div>

            <div class="buttons">
                <button type="submit" name="retirar" class="btn btn-primary">
                    💸 Registrar Retiro
                </button>
                <a href="index.php" class="btn btn-secondary">
                    ← Volver
                </a>
            </div>
        </form>

        <!-- Historial de retiros -->
        <?php if (count($historial_retiros) > 0): ?>
        <div class="historial" style="margin-top: 30px;">
            <h2>📋 Retiros del Turno Actual</h2>
            <?php foreach ($historial_retiros as $retiro): ?>
            <div class="retiro-item">
                <div class="retiro-info">
                    <div class="retiro-monto">-$<?php echo number_format($retiro['monto'], 2); ?></div>
                    <div class="retiro-motivo"><?php echo htmlspecialchars($retiro['motivo']); ?></div>
                    <div class="retiro-fecha">
                        🕐 <?php echo date('H:i', strtotime($retiro['fecha_retiro'])); ?> - 
                        📅 <?php echo date('d/m/Y', strtotime($retiro['fecha_retiro'])); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        const inputMonto = document.getElementById('montoRetiro');
        const maxDisponible = <?php echo $dinero_disponible; ?>;

        inputMonto.addEventListener('blur', function() {
            if (this.value) {
                let valor = parseFloat(this.value);
                if (valor > maxDisponible) {
                    alert('⚠️ El monto excede el dinero disponible en caja');
                    this.value = maxDisponible.toFixed(2);
                } else {
                    this.value = valor.toFixed(2);
                }
            }
        });

        document.getElementById('formRetiro').addEventListener('submit', function(e) {
            const monto = parseFloat(inputMonto.value);
            const motivo = document.querySelector('input[name="motivo"]').value;
            
            const mensaje = `💸 ¿Confirmas el retiro?\n\nMonto: $${monto.toFixed(2)}\nMotivo: ${motivo}\n\nEsta acción quedará registrada.`;
            
            if (!confirm(mensaje)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
