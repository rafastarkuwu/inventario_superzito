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
    $tipo_producto = $_POST['tipo_producto']; // 'normal' o 'granel'
    $codigo = $tipo_producto === 'normal' ? trim($_POST['codigo_barras']) : 'GRANEL-' . time();
    $precio = floatval($_POST['precio']);
    $stock = floatval($_POST['stock_inicial']); // Ahora acepta decimales para kg
    $stock_min = floatval($_POST['stock_minimo']);
    
    if (empty($nombre)) {
        $mensaje = "El nombre es obligatorio";
        $tipo_mensaje = 'error';
    } elseif ($tipo_producto === 'normal' && empty($_POST['codigo_barras'])) {
        $mensaje = "El código de barras es obligatorio para productos normales";
        $tipo_mensaje = 'error';
    } elseif ($precio <= 0) {
        $mensaje = "El precio debe ser mayor a 0";
        $tipo_mensaje = 'error';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Verificar código único solo si es producto normal con código real
            if ($tipo_producto === 'normal') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM Productos WHERE codigo_barras = ?");
                $stmt->execute([$codigo]);
                
                if ($stmt->fetchColumn() > 0) {
                    $mensaje = "Ya existe un producto con ese código";
                    $tipo_mensaje = 'error';
                    $pdo->rollBack();
                } else {
                    insertarProducto($pdo, $nombre, $codigo, $precio, $stock, $stock_min, $_SESSION['usuario_id']);
                    $mensaje = "✅ Producto agregado: " . htmlspecialchars($nombre);
                    $tipo_mensaje = 'success';
                    $_POST = array();
                }
            } else {
                // Producto a granel, código único generado
                insertarProducto($pdo, $nombre, $codigo, $precio, $stock, $stock_min, $_SESSION['usuario_id']);
                $mensaje = "✅ Producto a granel agregado: " . htmlspecialchars($nombre);
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

function insertarProducto($pdo, $nombre, $codigo, $precio, $stock, $stock_min, $id_enc) {
    // Insertar Inventario
    $stmt = $pdo->prepare("INSERT INTO Inventario (stock_actual, stock_minimo, id_encargado) VALUES (?, ?, ?)");
    $stmt->execute([$stock, $stock_min, $id_enc]);
    
    $id_inv = $pdo->lastInsertId();
    
    // Insertar Producto
    $stmt = $pdo->prepare("INSERT INTO Productos (nombre_producto, precio_venta, codigo_barras, id_inventario) VALUES (?, ?, ?, ?)");
    $stmt->execute([$nombre, $precio, $codigo, $id_inv]);
    
    $pdo->commit();
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
        .container { max-width: 700px; margin: 0 auto; }
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { color: #667eea; }
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
        .mensaje.success { background: #d4edda; color: #155724; }
        .mensaje.error { background: #f8d7da; color: #721c24; }
        .form-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
        }
        .tipo-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
        }
        .tipo-btn {
            flex: 1;
            padding: 15px;
            border: 3px solid #ddd;
            background: white;
            border-radius: 10px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
        }
        .tipo-btn:hover {
            border-color: #667eea;
        }
        .tipo-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .tipo-btn .icono {
            font-size: 32px;
            margin-bottom: 5px;
        }
        .tipo-btn .texto {
            font-weight: bold;
            font-size: 16px;
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
        .form-group input, .form-group select {
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
        .form-group small {
            color: #666;
            font-size: 13px;
            display: block;
            margin-top: 5px;
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
        .info-box {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2196F3;
        }
        .info-box .titulo {
            font-weight: bold;
            color: #1976D2;
            margin-bottom: 5px;
        }
        .info-box ul {
            margin-left: 20px;
            color: #555;
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
            <form method="POST" id="formProducto">
                <div class="tipo-selector">
                    <label class="tipo-btn active" onclick="seleccionarTipo('normal')">
                        <div class="icono">📦</div>
                        <div class="texto">Producto Normal</div>
                        <input type="radio" name="tipo_producto" value="normal" checked style="display:none;">
                    </label>
                    <label class="tipo-btn" onclick="seleccionarTipo('granel')">
                        <div class="icono">⚖️</div>
                        <div class="texto">Producto a Granel</div>
                        <input type="radio" name="tipo_producto" value="granel" style="display:none;">
                    </label>
                </div>

                <div class="info-box" id="infoNormal">
                    <div class="titulo">📦 Producto Normal:</div>
                    <ul>
                        <li>Tiene código de barras</li>
                        <li>Se vende por unidad</li>
                        <li>Ejemplos: Coca-Cola, Sabritas, Galletas</li>
                    </ul>
                </div>

                <div class="info-box" id="infoGranel" style="display:none; background:#fff3e0; border-left-color:#FF9800;">
                    <div class="titulo" style="color:#F57C00;">⚖️ Producto a Granel:</div>
                    <ul>
                        <li>NO tiene código de barras</li>
                        <li>Se vende por peso (kg)</li>
                        <li>Ejemplos: Limón, Azúcar, Arroz, Frijol</li>
                    </ul>
                </div>

                <div class="form-group">
                    <label>Nombre del Producto *</label>
                    <input type="text" name="nombre" required 
                           placeholder="Ej: Limón, Coca-Cola, etc."
                           value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
                </div>

                <div class="form-group" id="grupoCodigoBarras">
                    <label>Código de Barras *</label>
                    <input type="text" name="codigo_barras" id="inputCodigo"
                           placeholder="Escanea o escribe el código"
                           value="<?php echo isset($_POST['codigo_barras']) ? htmlspecialchars($_POST['codigo_barras']) : ''; ?>">
                    <small>Escanea el código de barras o ingrésalo manualmente</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label id="labelPrecio">Precio de Venta *</label>
                        <input type="number" name="precio" step="0.01" min="0.01" required
                               placeholder="0.00"
                               value="<?php echo isset($_POST['precio']) ? $_POST['precio'] : ''; ?>">
                        <small id="smallPrecio">Precio por unidad</small>
                    </div>

                    <div class="form-group">
                        <label id="labelStock">Stock Inicial *</label>
                        <input type="number" name="stock_inicial" id="inputStock" step="0.001" min="0" required
                               placeholder="0"
                               value="<?php echo isset($_POST['stock_inicial']) ? $_POST['stock_inicial'] : '0'; ?>">
                        <small id="smallStock">Cantidad en unidades</small>
                    </div>
                </div>

                <div class="form-group">
                    <label id="labelStockMin">Stock Mínimo</label>
                    <input type="number" name="stock_minimo" id="inputStockMin" step="0.001" min="0" 
                           placeholder="5"
                           value="<?php echo isset($_POST['stock_minimo']) ? $_POST['stock_minimo'] : '5'; ?>">
                    <small id="smallStockMin">Alerta cuando el stock esté bajo</small>
                </div>

                <button type="submit" class="btn-guardar">✅ Guardar Producto</button>
            </form>
        </div>
    </div>

    <script>
        function seleccionarTipo(tipo) {
            // Actualizar botones
            document.querySelectorAll('.tipo-btn').forEach(btn => btn.classList.remove('active'));
            event.currentTarget.classList.add('active');
            
            // Actualizar radio
            document.querySelector(`input[value="${tipo}"]`).checked = true;
            
            // Mostrar/ocultar info boxes
            document.getElementById('infoNormal').style.display = tipo === 'normal' ? 'block' : 'none';
            document.getElementById('infoGranel').style.display = tipo === 'granel' ? 'block' : 'none';
            
            // Ajustar campos del formulario
            const grupoCodigoBarras = document.getElementById('grupoCodigoBarras');
            const inputCodigo = document.getElementById('inputCodigo');
            const inputStock = document.getElementById('inputStock');
            const inputStockMin = document.getElementById('inputStockMin');
            
            if (tipo === 'normal') {
                // Producto normal
                grupoCodigoBarras.style.display = 'block';
                inputCodigo.required = true;
                inputStock.step = '1';
                inputStockMin.step = '1';
                
                document.getElementById('labelPrecio').textContent = 'Precio de Venta *';
                document.getElementById('smallPrecio').textContent = 'Precio por unidad';
                document.getElementById('labelStock').textContent = 'Stock Inicial *';
                document.getElementById('smallStock').textContent = 'Cantidad en unidades';
                document.getElementById('labelStockMin').textContent = 'Stock Mínimo';
                document.getElementById('smallStockMin').textContent = 'Alerta cuando el stock esté bajo';
            } else {
                // Producto a granel
                grupoCodigoBarras.style.display = 'none';
                inputCodigo.required = false;
                inputStock.step = '0.001';
                inputStockMin.step = '0.001';
                
                document.getElementById('labelPrecio').textContent = 'Precio por Kilogramo *';
                document.getElementById('smallPrecio').textContent = 'Precio por cada kg';
                document.getElementById('labelStock').textContent = 'Stock Inicial (kg) *';
                document.getElementById('smallStock').textContent = 'Cantidad en kilogramos';
                document.getElementById('labelStockMin').textContent = 'Stock Mínimo (kg)';
                document.getElementById('smallStockMin').textContent = 'Alerta cuando queden pocos kg';
            }
        }

        // Validar formulario
        document.getElementById('formProducto').addEventListener('submit', function(e) {
            const tipo = document.querySelector('input[name="tipo_producto"]:checked').value;
            
            if (tipo === 'normal') {
                const codigo = document.getElementById('inputCodigo').value.trim();
                if (!codigo) {
                    e.preventDefault();
                    alert('Por favor ingresa el código de barras');
                    document.getElementById('inputCodigo').focus();
                    return false;
                }
            }
        });
    </script>
</body>
</html>
