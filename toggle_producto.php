<?php
session_start();
require_once 'config.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Verificar que se recibieron los parámetros necesarios
if (!isset($_GET['id']) || !isset($_GET['accion'])) {
    header("Location: gestionar_productos.php");
    exit();
}

$id_producto = intval($_GET['id']);
$accion = $_GET['accion'];

try {
    // Verificar que el producto existe
    $stmt = $pdo->prepare("SELECT nombre_producto, activo FROM Productos WHERE id_producto = ?");
    $stmt->execute([$id_producto]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$producto) {
        $_SESSION['mensaje_error'] = "Producto no encontrado";
        header("Location: gestionar_productos.php");
        exit();
    }
    
    // Determinar el nuevo estado
    $nuevo_estado = ($accion === 'activar') ? 1 : 0;
    
    // Actualizar el estado del producto
    $stmt = $pdo->prepare("UPDATE Productos SET activo = ? WHERE id_producto = ?");
    $stmt->execute([$nuevo_estado, $id_producto]);
    
    // Mensaje de éxito
    $texto_accion = ($nuevo_estado == 1) ? 'activado' : 'desactivado';
    $_SESSION['mensaje_exito'] = "Producto '{$producto['nombre_producto']}' {$texto_accion} correctamente";
    
} catch (Exception $e) {
    $_SESSION['mensaje_error'] = "Error al cambiar el estado: " . $e->getMessage();
}

// Redirigir de vuelta a la página de gestión
header("Location: gestionar_productos.php");
exit();
?>
