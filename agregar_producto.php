<?php
session_start();
require_once 'config.php';

// Verificar si el usuario está autenticado y es encargado
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'encargado') {
    header("Location: index.php");
    exit();
}

$usuario_nombre = $_SESSION['usuario_nombre'];
$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $codigo_barras = trim($_POST['codigo_barras']);
    $precio = floatval($_POST['precio']);
    $stock_inicial = intval($_POST['stock_inicial']);
    $stock_minimo = intval($_POST['stock_minimo']);
    
    // Validaciones
    if (empty($nombre) || empty($codigo_barras)) {
        $mensaje = "⚠️ El nombre y código de barras son obligatorios";
        $tipo_mensaje = 'warning';
    } elseif ($precio <= 0) {
        $mensaje = "⚠️ El precio debe ser mayor a 0";
        $tipo_mensaje = 'warning';
    } elseif ($stock_inicial < 0) {
        $mensaje = "⚠️ El stock inicial no puede ser negativo";
        $tipo_mensaje = 'warning';
    } else {
        try {
            // Verificar si el código de barras ya existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE codigo_barras = :codigo");
            $stmt->execute([':codigo' => $codigo_barras]);
            
            if ($stmt->fetchColumn() > 0) {
                $mensaje = "⚠️ Ya existe un producto con ese código de barras";
                $tipo_mensaje = 'warning';
            } else {
                // Insertar producto
                $stmt = $pdo->prepare("
                    INSERT INTO productos (nombre, codigo_barras, precio, stock, stock_minimo) 
                    VALUES (:nombre, :codigo, :precio, :stock, :stock_min)
                ");
                
                $stmt->execute([
                    ':nombre' => $nombre,
                    ':codigo' => $codigo_barras,
                    ':precio' => $precio,
                    ':stock' => $stock_inicial,
                    ':stock_min' => $stock_minimo
                ]);
                
                $producto_id = $pdo->lastInsertId();
                
                // Registrar en historial si hay stock inicial
                if ($stock_inicial > 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO historial_stock (producto_id, tipo, cantidad, usuario_nombre, fecha) 
                        VALUES (:producto_id, 'entrada', :cantidad, :usuario, NOW())
                    ");
                    
                    $stmt->execute([
                        ':producto_id' => $producto_id,
                        ':cantidad' => $stock_inicial,
                        ':usuario' => $usuario_nombre
                    ]);
                }
                
                $mensaje = "✅ Producto agregado exitosamente: " . htmlspecialchars($nombre);
                $tipo_mensaje = 'success';
                
                // Limpiar formulario
                $_POST = array();
            }
            
        } catch (Exception $e) {
            $mensaje = "❌ Error al agregar producto: " . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Producto - SCAV</title>
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
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            margin-bottom: 20px;
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

        .user-info {
            color: #666;
            font-size: 14px;
        }

        .mensaje {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
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

        .formulario {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .formulario h2 {
            color: #667eea;
            margin-bottom: 25px;
            font-size: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group small {
            display: block;
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .botones {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: #667eea;
            color: white;
            flex: 1;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .info-box {
            background: #e8eaf6;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .info-box h3 {
            color: #667eea;
            font-size: 16px;
            margin-bottom: 8px;
        }

        .info-box p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .botones {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>➕ Agregar Producto</h1>
                <div class="user-info">👤 Usuario: <strong><?php echo htmlspecialchars($usuario_nombre); ?></strong> | ⭐ Encargado</div>
            </div>
            <a href="index.php" class="btn btn-secondary">⬅️ Volver</a>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="formulario">
            <div class="info-box">
                <h3>📋 Instrucciones</h3>
                <p>Complete todos los campos para registrar un nuevo producto en el inventario. El código de barras debe ser único.</p>
            </div>

            <form method="POST" id="formProducto">
                <div class="form-group">
                    <label>📦 Nombre del Producto *</label>
                    <input type="text" 
                           name="nombre" 
                           placeholder="Ej: Coca Cola 600ml"
                           value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>"
                           required
                           autofocus>
                    <small>Nombre descriptivo del producto</small>
                </div>

                <div class="form-group">
                    <label>🔢 Código de Barras *</label>
                    <input type="text" 
                           name="codigo_barras" 
                           placeholder="Ej: 7501234567890"
                           value="<?php echo isset($_POST['codigo_barras']) ? htmlspecialchars($_POST['codigo_barras']) : ''; ?>"
                           required>
                    <small>Código único del producto (número o alfanumérico)</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>💰 Precio de Venta *</label>
                        <input type="number" 
                               name="precio" 
                               step="0.01" 
                               min="0.01"
                               placeholder="0.00"
                               value="<?php echo isset($_POST['precio']) ? $_POST['precio'] : ''; ?>"
                               required>
                        <small>Precio unitario del producto</small>
                    </div>

                    <div class="form-group">
                        <label>📊 Stock Inicial *</label>
                        <input type="number" 
                               name="stock_inicial" 
                               min="0"
                               placeholder="0"
                               value="<?php echo isset($_POST['stock_inicial']) ? $_POST['stock_inicial'] : '0'; ?>"
                               required>
                        <small>Cantidad inicial en inventario</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>⚠️ Stock Mínimo</label>
                    <input type="number" 
                           name="stock_minimo" 
                           min="0"
                           placeholder="5"
                           value="<?php echo isset($_POST['stock_minimo']) ? $_POST['stock_minimo'] : '5'; ?>">
                    <small>Cantidad mínima antes de alertar (por defecto: 5)</small>
                </div>

                <div class="botones">
                    <button type="submit" class="btn btn-primary">
                        ✅ Guardar Producto
                    </button>
                    <button type="reset" class="btn btn-secondary" onclick="return confirm('¿Limpiar el formulario?')">
                        🔄 Limpiar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Validación adicional del formulario
        document.getElementById('formProducto').addEventListener('submit', function(e) {
            const precio = parseFloat(document.querySelector('input[name="precio"]').value);
            const stock = parseInt(document.querySelector('input[name="stock_inicial"]').value);
            
            if (precio <= 0) {
                e.preventDefault();
                alert('⚠️ El precio debe ser mayor a 0');
                return false;
            }
            
            if (stock < 0) {
                e.preventDefault();
                alert('⚠️ El stock no puede ser negativo');
                return false;
            }
            
            return confirm('¿Confirmar registro del producto?');
        });

        // Auto-mayúsculas en nombre
        document.querySelector('input[name="nombre"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
        });
    </script>
</body>
</html>
