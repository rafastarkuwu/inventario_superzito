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

// Calcular ventas del turno actual
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_ventas,
        COALESCE(SUM(total), 0) as total_dinero
    FROM Ventas 
    WHERE id_encargado = ? 
    AND fecha_venta >= ?
");
$stmt->execute([$usuario_id, $caja_actual['fecha_apertura']]);
$ventas = $stmt->fetch(PDO::FETCH_ASSOC);

$monto_inicial = floatval($caja_actual['monto_inicial']);
$monto_ventas = floatval($ventas['total_dinero']);
$monto_esperado = $monto_inicial + $monto_ventas;

// Procesar cierre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cerrar_caja'])) {
    $monto_final = floatval($_POST['monto_final']);
    $observaciones = trim($_POST['observaciones']);
    
    try {
        $pdo->beginTransaction();
        
        // Actualizar la caja
        $stmt = $pdo->prepare("
            UPDATE Cajas 
            SET fecha_cierre = NOW(), 
                monto_ventas = ?,
                monto_final = ?,
                estado = 'cerrada',
                observaciones = ?
            WHERE id_caja = ?
        ");
        $stmt->execute([
            $monto_ventas,
            $monto_final,
            $observaciones,
            $caja_actual['id_caja']
        ]);
        
        $pdo->commit();
        
        // Redirigir a reporte de cierre
        header("Location: reporte_cierre.php?id=" . $caja_actual['id_caja']);
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al cerrar caja: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cierre de Caja</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            color: #667eea;
            font-size: 32px;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            font-size: 16px;
        }
        .alert-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: #856404;
        }
        .alert-error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: #721c24;
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .info-item {
            background: #f8f9ff;
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
            color: #667eea;
        }
        .monto-esperado {
            background: #d4edda;
            border: 3px solid #28a745;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 20px;
        }
        .monto-esperado .label {
            font-size: 18px;
            color: #155724;
            margin-bottom: 10px;
        }
        .monto-esperado .valor {
            font-size: 48px;
            font-weight: bold;
            color: #28a745;
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
            border: 3px solid #667eea;
            border-radius: 10px;
            font-size: 32px;
            text-align: center;
            font-weight: bold;
            color: #667eea;
        }
        .input-money:focus {
            outline: none;
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.3);
        }
        textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            font-family: Arial, sans-serif;
            resize: vertical;
            min-height: 100px;
        }
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        .diferencia {
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-top: 15px;
            font-size: 18px;
            font-weight: bold;
        }
        .diferencia.positiva {
            background: #d4edda;
            color: #155724;
        }
        .diferencia.negativa {
            background: #f8d7da;
            color: #721c24;
        }
        .diferencia.exacta {
            background: #d1ecf1;
            color: #0c5460;
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
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
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
        .hint {
            font-size: 14px;
            color: #666;
            margin-top: 8px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔒 Cierre de Caja</h1>
            <div class="subtitle">Finaliza tu turno de trabajo</div>
            <div style="margin-top: 10px; color: #4CAF50; font-weight: bold;">
                👤 <?php echo htmlspecialchars($usuario_nombre); ?>
            </div>
        </div>

        <div class="alert-warning">
            <strong>⚠️ ATENCIÓN:</strong> Estás por cerrar tu caja. Asegúrate de contar bien el dinero antes de continuar. Esta acción no se puede deshacer.
        </div>

        <?php if (isset($error)): ?>
        <div class="alert-error">
            <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- Información del turno -->
        <div class="info-box">
            <h2>📊 Resumen del Turno</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">🕐 Hora de Apertura</div>
                    <div class="info-value" style="font-size: 20px;">
                        <?php echo date('H:i', strtotime($caja_actual['fecha_apertura'])); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">💵 Monto Inicial</div>
                    <div class="info-value">
                        $<?php echo number_format($monto_inicial, 2); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">🧾 Total Ventas</div>
                    <div class="info-value">
                        <?php echo $ventas['total_ventas']; ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">💰 Dinero de Ventas</div>
                    <div class="info-value" style="color: #4CAF50;">
                        $<?php echo number_format($monto_ventas, 2); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monto esperado -->
        <div class="monto-esperado">
            <div class="label">💎 DINERO QUE DEBE HABER EN CAJA:</div>
            <div class="valor">$<?php echo number_format($monto_esperado, 2); ?></div>
            <div style="font-size: 14px; color: #155724; margin-top: 10px;">
                (Monto inicial + Ventas)
            </div>
        </div>

        <!-- Formulario de cierre -->
        <form method="POST" id="formCierre">
            <div class="form-box">
                <h2>💵 Cuenta el dinero en caja</h2>
                
                <div class="form-group">
                    <label>¿Cuánto dinero hay físicamente en la caja?</label>
                    <input 
                        type="number" 
                        name="monto_final" 
                        id="montoFinal"
                        class="input-money"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        required
                        autofocus>
                    <div class="hint">Cuenta billetes y monedas, ingresa el total exacto</div>
                </div>

                <div id="diferencia"></div>

                <div class="form-group" style="margin-top: 25px;">
                    <label>📝 Observaciones (opcional)</label>
                    <textarea 
                        name="observaciones" 
                        placeholder="¿Algún faltante, sobrante o comentario sobre el turno?"
                    ></textarea>
                </div>
            </div>

            <div class="buttons">
                <button type="submit" name="cerrar_caja" class="btn btn-danger" id="btnCerrar">
                    🔒 CERRAR CAJA
                </button>
                <a href="index.php" class="btn btn-secondary">
                    ← Cancelar
                </a>
            </div>
        </form>
    </div>

    <script>
        const montoEsperado = <?php echo $monto_esperado; ?>;
        const inputMonto = document.getElementById('montoFinal');
        const divDiferencia = document.getElementById('diferencia');
        const btnCerrar = document.getElementById('btnCerrar');

        inputMonto.addEventListener('input', function() {
            const montoFinal = parseFloat(this.value) || 0;
            const diferencia = montoFinal - montoEsperado;
            
            if (this.value && montoFinal >= 0) {
                let clase, texto, icono;
                
                if (Math.abs(diferencia) < 0.01) {
                    clase = 'exacta';
                    icono = '✅';
                    texto = '¡PERFECTO! El dinero cuadra exactamente';
                } else if (diferencia > 0) {
                    clase = 'positiva';
                    icono = '💰';
                    texto = `SOBRANTE de $${Math.abs(diferencia).toFixed(2)}`;
                } else {
                    clase = 'negativa';
                    icono = '⚠️';
                    texto = `FALTANTE de $${Math.abs(diferencia).toFixed(2)}`;
                }
                
                divDiferencia.className = 'diferencia ' + clase;
                divDiferencia.innerHTML = `${icono} ${texto}`;
            } else {
                divDiferencia.innerHTML = '';
            }
        });

        document.getElementById('formCierre').addEventListener('submit', function(e) {
            const montoFinal = parseFloat(inputMonto.value) || 0;
            const diferencia = montoFinal - montoEsperado;
            
            let mensaje = '🔒 ¿Confirmas el cierre de caja?\\n\\n';
            mensaje += `Monto esperado: $${montoEsperado.toFixed(2)}\\n`;
            mensaje += `Monto contado: $${montoFinal.toFixed(2)}\\n`;
            
            if (Math.abs(diferencia) < 0.01) {
                mensaje += '✅ El dinero cuadra perfectamente';
            } else if (diferencia > 0) {
                mensaje += `💰 Sobrante: $${Math.abs(diferencia).toFixed(2)}`;
            } else {
                mensaje += `⚠️ Faltante: $${Math.abs(diferencia).toFixed(2)}`;
            }
            
            if (!confirm(mensaje)) {
                e.preventDefault();
            }
        });

        // Auto-formatear al salir del input
        inputMonto.addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    </script>
</body>
</html>
