<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nombre = $_SESSION['usuario_nombre'];

// Verificar si ya hay una caja abierta
$stmt = $pdo->prepare("
    SELECT * FROM Cajas 
    WHERE id_encargado = ? AND estado = 'abierta' 
    ORDER BY fecha_apertura DESC 
    LIMIT 1
");
$stmt->execute([$usuario_id]);
$caja_abierta = $stmt->fetch(PDO::FETCH_ASSOC);

// Si ya hay caja abierta, redirigir al menú
if ($caja_abierta) {
    header("Location: index.php");
    exit();
}

$mensaje = '';
$tipo_mensaje = '';

// Procesar apertura
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['abrir_caja'])) {
    $monto_inicial = floatval($_POST['monto_inicial']);
    
    if ($monto_inicial < 0) {
        $mensaje = "❌ El monto inicial no puede ser negativo";
        $tipo_mensaje = "error";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO Cajas (id_caja, id_encargado, fecha_apertura, monto_inicial, estado) 
                VALUES (NULL, ?, NOW(), ?, 'abierta')
            ");
            $stmt->execute([$usuario_id, $monto_inicial]);
            
            // Redirigir al menú principal
            header("Location: index.php");
            exit();
        } catch (Exception $e) {
            $mensaje = "Error al abrir caja: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apertura de Caja</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .icono {
            font-size: 80px;
            margin-bottom: 20px;
        }
        h1 {
            color: #667eea;
            font-size: 32px;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            font-size: 16px;
        }
        .user-info {
            background: #f8f9ff;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        .user-info strong {
            color: #667eea;
            font-size: 18px;
        }
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            animation: slideIn 0.3s;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .form-group {
            margin-bottom: 25px;
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
            border-radius: 12px;
            font-size: 32px;
            text-align: center;
            font-weight: bold;
            color: #667eea;
        }
        .input-money:focus {
            outline: none;
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.3);
        }
        .hint {
            margin-top: 10px;
            font-size: 14px;
            color: #666;
            text-align: center;
        }
        .buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        .btn {
            flex: 1;
            padding: 18px;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        .btn-primary:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.4);
        }
        .btn-secondary {
            background: #666;
            color: white;
        }
        .btn-secondary:hover {
            background: #555;
        }
        .info-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .info-box h3 {
            color: #856404;
            font-size: 16px;
            margin-bottom: 10px;
        }
        .info-box ul {
            margin-left: 20px;
            color: #856404;
        }
        .info-box li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icono">💰</div>
            <h1>Apertura de Caja</h1>
            <p class="subtitle">Inicia tu turno de trabajo</p>
        </div>

        <div class="user-info">
            👤 <strong><?php echo htmlspecialchars($usuario_nombre); ?></strong>
            <div style="font-size: 14px; color: #666; margin-top: 5px;">
                📅 <?php echo date('l, d \d\e F \d\e Y'); ?>
            </div>
        </div>

        <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?>">
            <?php echo $mensaje; ?>
        </div>
        <?php endif; ?>

        <div class="info-box">
            <h3>📋 Instrucciones:</h3>
            <ul>
                <li>Cuenta el dinero físico en la caja</li>
                <li>Ingresa el monto exacto con el que inicias</li>
                <li>Este será tu fondo de arranque del día</li>
                <li>Puedes ingresar $0.00 si inicias sin fondo</li>
            </ul>
        </div>

        <form method="POST">
            <div class="form-group">
                <label>💵 ¿Con cuánto dinero inicias la caja?</label>
                <input 
                    type="number" 
                    name="monto_inicial" 
                    class="input-money"
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                    required
                    autofocus>
                <div class="hint">Ingresa el monto en pesos (Ej: 500.00)</div>
            </div>

            <div class="buttons">
                <button type="submit" name="abrir_caja" class="btn btn-primary">
                    ✅ Abrir Caja
                </button>
                <a href="logout.php" class="btn btn-secondary" style="text-align: center; line-height: 18px; text-decoration: none;">
                    ❌ Cancelar
                </a>
            </div>
        </form>
    </div>

    <script>
        // Auto-formatear el input de dinero
        const inputMoney = document.querySelector('.input-money');
        inputMoney.addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    </script>
</body>
</html>
