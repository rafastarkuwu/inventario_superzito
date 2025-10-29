<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Verificar si hay una caja abierta
$stmt = $pdo->prepare("
    SELECT * FROM Cajas 
    WHERE id_encargado = ? AND estado = 'abierta' 
    ORDER BY fecha_apertura DESC 
    LIMIT 1
");
$stmt->execute([$usuario_id]);
$caja_abierta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$caja_abierta) {
    $_SESSION['mensaje'] = "❌ No tienes una caja abierta. Debes abrir una caja primero.";
    $_SESSION['tipo_mensaje'] = "error";
    header("Location: lista_proveedores.php");
    exit();
}

// Procesar el pago
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_proveedor = $_POST['id_proveedor'];
    $monto = floatval($_POST['monto']);
    $concepto = trim($_POST['concepto']);
    
    if ($monto <= 0) {
        $_SESSION['mensaje'] = "❌ El monto debe ser mayor a cero";
        $_SESSION['tipo_mensaje'] = "error";
        header("Location: lista_proveedores.php");
        exit();
    }
    
    try {
        $pdo->beginTransaction();
        
        // Verificar que el proveedor existe
        $stmt = $pdo->prepare("SELECT * FROM Proveedores WHERE id_proveedor = ?");
        $stmt->execute([$id_proveedor]);
        $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$proveedor) {
            throw new Exception("Proveedor no encontrado");
        }
        
        // Registrar el pago a proveedor
        $stmt = $pdo->prepare("
            INSERT INTO Pagos_Proveedores (id_proveedor, id_caja, id_usuario, monto, concepto)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id_proveedor,
            $caja_abierta['id_caja'],
            $usuario_id,
            $monto,
            $concepto ?: 'Pago a proveedor'
        ]);
        
        // Registrar el retiro en la tabla de Retiros (si existe)
        // Si tu sistema tiene una tabla de Retiros, descomentar esto:
        /*
        $stmt = $pdo->prepare("
            INSERT INTO Retiros (id_caja, id_usuario, monto, concepto, fecha_retiro)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $caja_abierta['id_caja'],
            $usuario_id,
            $monto,
            'Pago a proveedor: ' . $proveedor['nombre_proveedor']
        ]);
        */
        
        $pdo->commit();
        
        $_SESSION['mensaje'] = "✅ Pago realizado exitosamente a " . $proveedor['nombre_proveedor'] . " por $" . number_format($monto, 2);
        $_SESSION['tipo_mensaje'] = "success";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['mensaje'] = "❌ Error al procesar el pago: " . $e->getMessage();
        $_SESSION['tipo_mensaje'] = "error";
    }
    
    header("Location: lista_proveedores.php");
    exit();
}
?>
