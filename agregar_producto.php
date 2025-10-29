<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'encargado') {
    header("Location: index.php");
    exit();
}

$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $codigo = trim($_POST['codigo_barras']);
    $precio = floatval($_POST['precio']);
    $stock = intval($_POST['stock_inicial']);
    $stock_min = intval($_POST['stock_minimo']);
    
    if (empty($nombre) || empty($codigo)) {
        $mensaje = "Nombre y código son obligatorios";
        $tipo_mensaje = 'error';
    } elseif ($precio <= 0) {
        $mensaje = "El precio debe ser mayor a 0";
        $tipo_mensaje = 'error';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Verificar código único
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Productos WHERE codigo_barras = :codigo");
            $stmt->execute([':codigo' => $codigo]);
            
            if ($stmt->fetchColumn() > 0) {
                $mensaje = "Ya existe un producto con ese código";
                $tipo_mensaje = 'error';
            } else {
                // Insertar Inventario
                $stmt = $pdo->prepare("INSERT INTO Inventario (stock_actual, stock_minimo, id_encargado) VALUES (:stock, :stock_min, :id_enc)");
                $stmt->execute([
                    ':stock' => $stock,
                    ':stock_min' => $stock_min,
                    ':id_enc' => $_SESSION['usuario_id']
                ]);
                
                $id_inv = $pdo->lastInsertId();
                
                // Insertar Producto
                $stmt = $pdo->prepare("INSERT INTO Productos (nombre_producto, precio_venta, codigo_barras, id_inventario) VALUES (:nombre, :precio, :codigo, :id_inv)");
                $stmt->execute([
                    ':nombre' => $nombre,
                    ':precio' => $precio,
                    ':codigo' => $codigo,
                    ':id_inv' => $id_inv
                ]);
                
                $pdo->commit();
                $mensaje = "✅ Producto agregado: " . htmlspecialchars($nombre);
                $tipo_mensaje = 'success';
                $_POST = array();
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = "Error: " . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Producto</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            color: #667eea;
        }
        .btn-volver {
            background: #666;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
        }
        .mensaje {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .mensaje.success {
            background: #d4edda;
            color: #155724;
        }
        .mensaje.error {
            background: #f8d7da;
            color: #721c24;
        }
        .form-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .form-group input:focus {
            border-color: #667eea;
            outline: none;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .btn-guardar {
            width: 100%;
            padding: 15px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
        }
        .btn-guardar:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>➕ Agregar Producto</h1>
            <a href="index.php" class="btn-volver">← Volver</a>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="form-box">
            <form method="POST">
                <div class="form-group">
                    <label>Nombre del Producto *</label>
                    <input type="text" name="nombre" required 
                           value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Código de Barras *</label>
                    <input type="text" name="codigo_barras" required
                           value="<?php echo isset($_POST['codigo_barras']) ? htmlspecialchars($_POST['codigo_barras']) : ''; ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Precio de Venta *</label>
                        <input type="number" name="precio" step="0.01" min="0.01" required
                               value="<?php echo isset($_POST['precio']) ? $_POST['precio'] : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Stock Inicial *</label>
                        <input type="number" name="stock_inicial" min="0" required
                               value="<?php echo isset($_POST['stock_inicial']) ? $_POST['stock_inicial'] : '0'; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Stock Mínimo</label>
                    <input type="number" name="stock_minimo" min="0" 
                           value="<?php echo isset($_POST['stock_minimo']) ? $_POST['stock_minimo'] : '5'; ?>">
                </div>

                <button type="submit" class="btn-guardar">✅ Guardar Producto</button>
            </form>
        </div>
    </div>
</body>
</html>
