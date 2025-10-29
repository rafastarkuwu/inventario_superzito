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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago a Proveedores - SuperZito</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
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
        .opciones-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
        }
        .opcion-card {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s;
            cursor: pointer;
        }
        .opcion-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
        .opcion-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        .opcion-titulo {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
        }
        .opcion-descripcion {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
        }
        .opcion-nuevo {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
        }
        .opcion-nuevo .opcion-titulo,
        .opcion-nuevo .opcion-descripcion {
            color: white;
        }
        .opcion-lista {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: white;
        }
        .opcion-lista .opcion-titulo,
        .opcion-lista .opcion-descripcion {
            color: white;
        }
        @media (max-width: 768px) {
            .opciones-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💳 Pago a Proveedores</h1>
            <a href="index.php" class="btn-volver">← Volver al Dashboard</a>
        </div>

        <div class="opciones-grid">
            <!-- Opción 1: Agregar Nuevo Proveedor -->
            <a href="agregar_proveedor.php" class="opcion-card opcion-nuevo">
                <div class="opcion-icon">➕</div>
                <div class="opcion-titulo">Agregar Proveedor</div>
                <div class="opcion-descripcion">
                    Registrar un nuevo proveedor con su marca y tipo de productos
                </div>
            </a>

            <!-- Opción 2: Ver Proveedores y Pagar -->
            <a href="lista_proveedores.php" class="opcion-card opcion-lista">
                <div class="opcion-icon">📋</div>
                <div class="opcion-titulo">Proveedores Guardados</div>
                <div class="opcion-descripcion">
                    Ver lista de proveedores y realizar pagos desde la caja
                </div>
            </a>
        </div>
    </div>
</body>
</html>
