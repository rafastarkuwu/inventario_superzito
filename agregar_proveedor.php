<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre_proveedor']);
    $marca = trim($_POST['marca']);
    $tipo_productos = trim($_POST['tipo_productos']);
    $telefono = trim($_POST['telefono']);
    $email = trim($_POST['email']);
    
    if (!empty($nombre) && !empty($marca) && !empty($tipo_productos)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO Proveedores (nombre_proveedor, marca, tipo_productos, telefono, email)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nombre, $marca, $tipo_productos, $telefono, $email]);
            
            $mensaje = "✅ Proveedor registrado exitosamente";
            $tipo_mensaje = "success";
        } catch (Exception $e) {
            $mensaje = "❌ Error al registrar proveedor: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "⚠️ Por favor completa todos los campos obligatorios";
        $tipo_mensaje = "warning";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Proveedor - SuperZito</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 700px;
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
        .form-container {
            background: white;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .mensaje {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }
        .mensaje.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .mensaje.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .mensaje.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
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
        .form-group label .required {
            color: #ff4444;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
        }
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 13px;
        }
        .btn-submit {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s;
        }
        .btn-submit:hover {
            transform: scale(1.02);
        }
        .icon-title {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        .icon-title .icon {
            font-size: 48px;
        }
        .icon-title .text h2 {
            color: #333;
            font-size: 24px;
            margin-bottom: 5px;
        }
        .icon-title .text p {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>➕ Agregar Proveedor</h1>
            <a href="pago_proveedores.php" class="btn-volver">← Volver</a>
        </div>

        <div class="form-container">
            <div class="icon-title">
                <div class="icon">📦</div>
                <div class="text">
                    <h2>Nuevo Proveedor</h2>
                    <p>Registra la información del proveedor</p>
                </div>
            </div>

            <?php if ($mensaje): ?>
                <div class="mensaje <?php echo $tipo_mensaje; ?>">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label>
                        Nombre del Proveedor <span class="required">*</span>
                    </label>
                    <input type="text" name="nombre_proveedor" required 
                           placeholder="Ej: Distribuidora La Moderna">
                </div>

                <div class="form-group">
                    <label>
                        Marca <span class="required">*</span>
                    </label>
                    <input type="text" name="marca" required 
                           placeholder="Ej: Coca-Cola, Sabritas, Bimbo">
                </div>

                <div class="form-group">
                    <label>
                        Tipo de Productos <span class="required">*</span>
                    </label>
                    <textarea name="tipo_productos" required 
                              placeholder="Ej: Refrescos, Lácteos, Papas, Dulces, etc."></textarea>
                    <small>Separa los tipos de productos con comas</small>
                </div>

                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="tel" name="telefono" 
                           placeholder="Ej: 55-1234-5678">
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" 
                           placeholder="Ej: contacto@proveedor.com">
                </div>

                <button type="submit" class="btn-submit">
                    ✅ Guardar Proveedor
                </button>
            </form>
        </div>
    </div>
</body>
</html>
