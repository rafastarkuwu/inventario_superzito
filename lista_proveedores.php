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
$caja_abierta = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener todos los proveedores activos
$stmt = $pdo->query("
    SELECT * FROM Proveedores 
    WHERE estado = 'activo' 
    ORDER BY nombre_proveedor ASC
");
$proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proveedores Guardados - SuperZito</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
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
        .header h1 {
            color: #667eea;
            font-size: 28px;
        }
        .btn-volver {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }
        .alerta-caja {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        .alerta-caja h2 {
            margin-bottom: 10px;
        }
        .proveedores-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .proveedor-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .proveedor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.15);
        }
        .proveedor-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        .proveedor-icon {
            font-size: 48px;
        }
        .proveedor-info h3 {
            color: #333;
            font-size: 20px;
            margin-bottom: 5px;
        }
        .proveedor-marca {
            color: #667eea;
            font-weight: bold;
            font-size: 14px;
        }
        .proveedor-detalles {
            margin-bottom: 15px;
        }
        .detalle-item {
            margin-bottom: 10px;
            color: #666;
            font-size: 14px;
        }
        .detalle-label {
            font-weight: bold;
            color: #333;
        }
        .btn-pagar {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            transition: transform 0.2s;
        }
        .btn-pagar:hover {
            transform: scale(1.02);
        }
        .btn-pagar:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .no-proveedores {
            background: white;
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .no-proveedores-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .no-proveedores h2 {
            color: #333;
            margin-bottom: 15px;
        }
        .no-proveedores p {
            color: #666;
            margin-bottom: 20px;
        }
        .btn-agregar {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 15px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
        }
        
        /* Modal de pago */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            padding: 35px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .modal-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        .modal-header-icon {
            font-size: 48px;
        }
        .modal-header-text h2 {
            color: #333;
            font-size: 24px;
            margin-bottom: 5px;
        }
        .modal-header-text p {
            color: #666;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .modal-buttons {
            display: flex;
            gap: 10px;
        }
        .btn-confirmar {
            flex: 1;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-cancelar {
            flex: 1;
            background: #ff4444;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            font-size: 16px;
        }
        
        @media (max-width: 768px) {
            .proveedores-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Proveedores Guardados</h1>
            <a href="pago_proveedores.php" class="btn-volver">← Volver</a>
        </div>

        <?php if (!$caja_abierta): ?>
        <div class="alerta-caja">
            <h2>⚠️ No tienes una caja abierta</h2>
            <p>Debes abrir una caja para poder realizar pagos a proveedores</p>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['mensaje'])): ?>
        <div class="alerta-caja" style="background: <?php echo $_SESSION['tipo_mensaje'] === 'success' ? 'linear-gradient(135deg, #4CAF50 0%, #45a049 100%)' : 'linear-gradient(135deg, #ff4444 0%, #cc0000 100%)'; ?>;">
            <h2><?php echo $_SESSION['mensaje']; ?></h2>
        </div>
        <?php 
            unset($_SESSION['mensaje']);
            unset($_SESSION['tipo_mensaje']);
        endif; 
        ?>

        <?php if (empty($proveedores)): ?>
        <div class="no-proveedores">
            <div class="no-proveedores-icon">📦</div>
            <h2>No hay proveedores registrados</h2>
            <p>Agrega tu primer proveedor para comenzar a gestionar tus pagos</p>
            <a href="agregar_proveedor.php" class="btn-agregar">➕ Agregar Proveedor</a>
        </div>
        <?php else: ?>
        <div class="proveedores-grid">
            <?php foreach ($proveedores as $proveedor): ?>
            <div class="proveedor-card">
                <div class="proveedor-header">
                    <div class="proveedor-icon">🏢</div>
                    <div class="proveedor-info">
                        <h3><?php echo htmlspecialchars($proveedor['nombre_proveedor']); ?></h3>
                        <div class="proveedor-marca">
                            🏷️ <?php echo htmlspecialchars($proveedor['marca']); ?>
                        </div>
                    </div>
                </div>
                
                <div class="proveedor-detalles">
                    <div class="detalle-item">
                        <span class="detalle-label">📦 Productos:</span><br>
                        <?php echo htmlspecialchars($proveedor['tipo_productos']); ?>
                    </div>
                    
                    <?php if ($proveedor['telefono']): ?>
                    <div class="detalle-item">
                        <span class="detalle-label">📞 Teléfono:</span>
                        <?php echo htmlspecialchars($proveedor['telefono']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($proveedor['email']): ?>
                    <div class="detalle-item">
                        <span class="detalle-label">📧 Email:</span>
                        <?php echo htmlspecialchars($proveedor['email']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <button class="btn-pagar" 
                        onclick="abrirModalPago(<?php echo $proveedor['id_proveedor']; ?>, '<?php echo htmlspecialchars($proveedor['nombre_proveedor']); ?>')"
                        <?php echo !$caja_abierta ? 'disabled' : ''; ?>>
                    💰 Realizar Pago
                </button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal de pago -->
    <div class="modal" id="modalPago">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-header-icon">💳</div>
                <div class="modal-header-text">
                    <h2>Pago a Proveedor</h2>
                    <p id="nombreProveedor"></p>
                </div>
            </div>
            
            <form id="formPago" method="POST" action="procesar_pago_proveedor.php">
                <input type="hidden" name="id_proveedor" id="idProveedor">
                
                <div class="form-group">
                    <label>💵 Monto a Pagar</label>
                    <input type="number" name="monto" id="montoPago" 
                           step="0.01" min="0.01" required 
                           placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label>📝 Concepto (opcional)</label>
                    <input type="text" name="concepto" 
                           placeholder="Ej: Pago de mercancía semanal">
                </div>
                
                <div class="modal-buttons">
                    <button type="submit" class="btn-confirmar">
                        ✅ Confirmar Pago
                    </button>
                    <button type="button" class="btn-cancelar" onclick="cerrarModal()">
                        ❌ Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModalPago(idProveedor, nombreProveedor) {
            document.getElementById('modalPago').classList.add('active');
            document.getElementById('idProveedor').value = idProveedor;
            document.getElementById('nombreProveedor').textContent = nombreProveedor;
            document.getElementById('montoPago').focus();
        }

        function cerrarModal() {
            document.getElementById('modalPago').classList.remove('active');
            document.getElementById('formPago').reset();
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('modalPago').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });

        // Cerrar con tecla ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModal();
            }
        });
    </script>
</body>
</html>
