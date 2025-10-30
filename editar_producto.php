<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$mensaje = '';
$error = '';
$producto = null;

// Verificar que se recibió un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: gestionar_productos.php");
    exit();
}

$id_producto = $_GET['id'];

// Obtener datos del producto
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.id_producto,
            p.nombre_producto,
            p.codigo_barras,
            p.precio_venta,
            p.activo,
            p.id_inventario,
            COALESCE(i.stock_actual, 0) as stock_actual,
            COALESCE(i.stock_minimo, 0) as stock_minimo
        FROM Productos p
        LEFT JOIN Inventario i ON p.id_inventario = i.id_inventario
        WHERE p.id_producto = ?
    ");
    $stmt->execute([$id_producto]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$producto) {
        header("Location: gestionar_productos.php");
        exit();
    }
} catch (Exception $e) {
    $error = "Error al cargar el producto: " . $e->getMessage();
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $codigo_barras = trim($_POST['codigo_barras']);
    $precio_venta = floatval($_POST['precio_venta']);
    $stock_minimo = intval($_POST['stock_minimo']);
    
    // Validaciones
    if (empty($nombre)) {
        $error = "El nombre del producto es obligatorio";
    } elseif (empty($codigo_barras)) {
        $error = "El código de barras es obligatorio";
    } elseif ($precio_venta <= 0) {
        $error = "El precio debe ser mayor a 0";
    } elseif ($stock_minimo < 0) {
        $error = "El stock mínimo no puede ser negativo";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Verificar que el código de barras no esté siendo usado por otro producto
            $stmt = $pdo->prepare("SELECT id_producto FROM Productos WHERE codigo_barras = ? AND id_producto != ?");
            $stmt->execute([$codigo_barras, $id_producto]);
            if ($stmt->fetch()) {
                throw new Exception("El código de barras ya está siendo usado por otro producto");
            }
            
            // Actualizar producto
            $stmt = $pdo->prepare("
                UPDATE Productos 
                SET nombre_producto = ?, codigo_barras = ?, precio_venta = ?
                WHERE id_producto = ?
            ");
            $stmt->execute([$nombre, $codigo_barras, $precio_venta, $id_producto]);
            
            // Actualizar stock mínimo en inventario (si el producto tiene un inventario asociado)
            if ($producto['id_inventario']) {
                $stmt = $pdo->prepare("
                    UPDATE Inventario 
                    SET stock_minimo = ?
                    WHERE id_inventario = ?
                ");
                $stmt->execute([$stock_minimo, $producto['id_inventario']]);
            } else {
                // Si no tiene inventario, crear uno y asociarlo
                $stmt = $pdo->prepare("
                    INSERT INTO Inventario (stock_actual, stock_minimo)
                    VALUES (0, ?)
                ");
                $stmt->execute([$stock_minimo]);
                $nuevo_id_inventario = $pdo->lastInsertId();
                
                // Asociar el inventario al producto
                $stmt = $pdo->prepare("
                    UPDATE Productos 
                    SET id_inventario = ?
                    WHERE id_producto = ?
                ");
                $stmt->execute([$nuevo_id_inventario, $id_producto]);
            }
            
            $pdo->commit();
            $mensaje = "Producto actualizado exitosamente";
            
            // Recargar datos del producto
            $stmt = $pdo->prepare("
                SELECT 
                    p.id_producto,
                    p.nombre_producto,
                    p.codigo_barras,
                    p.precio_venta,
                    p.activo,
                    p.id_inventario,
                    COALESCE(i.stock_actual, 0) as stock_actual,
                    COALESCE(i.stock_minimo, 0) as stock_minimo
                FROM Productos p
                LEFT JOIN Inventario i ON p.id_inventario = i.id_inventario
                WHERE p.id_producto = ?
            ");
            $stmt->execute([$id_producto]);
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al actualizar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto - SuperZito</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .header-left h1 {
            color: #667eea;
            font-size: 28px;
            margin-bottom: 5px;
        }
        .header-left p {
            color: #666;
            font-size: 14px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .info-box {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 8px;
        }
        .info-box strong {
            color: #667eea;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .form-group label .required {
            color: #ff4444;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-group input:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
        }
        .form-group .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        .btn-primary {
            background: #667eea;
            color: white;
            flex: 1;
        }
        .btn-cancel {
            background: #6c757d;
            color: white;
            flex: 1;
        }
        
        .mensaje {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stock-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stock-card {
            background: #f8f9ff;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            border: 2px solid #e0e0e0;
        }
        .stock-card-value {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        .stock-card-label {
            font-size: 12px;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <h1>✏️ Editar Producto</h1>
                <p>Modificar información del producto</p>
            </div>
            <a href="gestionar_productos.php" class="btn btn-secondary">← Volver a la Lista</a>
        </div>

        <div class="form-container">
            <?php if ($mensaje): ?>
                <div class="mensaje">
                    <span style="font-size: 20px;">✅</span>
                    <span><?php echo htmlspecialchars($mensaje); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error">
                    <span style="font-size: 20px;">❌</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($producto): ?>
                <div class="info-box">
                    <strong>ID del Producto:</strong> <?php echo $producto['id_producto']; ?><br>
                    <strong>Estado:</strong> 
                    <?php if ($producto['activo']): ?>
                        <span style="color: #4CAF50;">✅ Activo</span>
                    <?php else: ?>
                        <span style="color: #ff4444;">❌ Inactivo</span>
                    <?php endif; ?>
                </div>

                <div class="stock-info">
                    <div class="stock-card">
                        <div class="stock-card-value"><?php echo $producto['stock_actual']; ?></div>
                        <div class="stock-card-label">Stock Actual</div>
                    </div>
                    <div class="stock-card">
                        <div class="stock-card-value"><?php echo $producto['stock_minimo']; ?></div>
                        <div class="stock-card-label">Stock Mínimo Actual</div>
                    </div>
                </div>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="nombre">
                            Nombre del Producto <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="nombre" 
                            name="nombre" 
                            value="<?php echo htmlspecialchars($producto['nombre_producto']); ?>"
                            required
                            placeholder="Ej: Coca Cola 600ml"
                        >
                    </div>

                    <div class="form-group">
                        <label for="codigo_barras">
                            Código de Barras <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="codigo_barras" 
                            name="codigo_barras" 
                            value="<?php echo htmlspecialchars($producto['codigo_barras']); ?>"
                            required
                            placeholder="Ej: 7501234567890"
                        >
                        <div class="help-text">El código debe ser único para cada producto</div>
                    </div>

                    <div class="form-group">
                        <label for="precio_venta">
                            Precio de Venta <span class="required">*</span>
                        </label>
                        <input 
                            type="number" 
                            id="precio_venta" 
                            name="precio_venta" 
                            step="0.01" 
                            min="0.01"
                            value="<?php echo $producto['precio_venta']; ?>"
                            required
                            placeholder="Ej: 15.50"
                        >
                        <div class="help-text">Precio al público en pesos</div>
                    </div>

                    <div class="form-group">
                        <label for="stock_minimo">
                            Stock Mínimo
                        </label>
                        <input 
                            type="number" 
                            id="stock_minimo" 
                            name="stock_minimo" 
                            min="0"
                            value="<?php echo $producto['stock_minimo']; ?>"
                            placeholder="Ej: 10"
                        >
                        <div class="help-text">Cantidad mínima antes de recibir alerta de stock bajo</div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            💾 Guardar Cambios
                        </button>
                        <a href="gestionar_productos.php" class="btn btn-cancel">
                            ❌ Cancelar
                        </a>
                    </div>
                </form>

                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
                    <p style="color: #666; font-size: 13px; text-align: center;">
                        💡 <strong>Nota:</strong> Para modificar el stock actual, usa "Entrada de Mercancía" desde el menú principal
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
