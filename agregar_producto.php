<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        $db->beginTransaction();
        
        // Insertar en Inventario primero
        $query = "INSERT INTO Inventario (stock_actual, stock_minimo, id_encargado) 
                 VALUES (:stock, :stock_min, 1)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":stock", $_POST['stock_inicial']);
        $stmt->bindParam(":stock_min", $_POST['stock_minimo']);
        $stmt->execute();
        
        $id_inventario = $db->lastInsertId();
        
        // Insertar Producto
        $query = "INSERT INTO Productos (nombre_producto, precio_venta, codigo_barras, id_inventario) 
                 VALUES (:nombre, :precio, :codigo, :id_inv)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":nombre", $_POST['nombre_producto']);
        $stmt->bindParam(":precio", $_POST['precio_venta']);
        $stmt->bindParam(":codigo", $_POST['codigo_barras']);
        $stmt->bindParam(":id_inv", $id_inventario);
        $stmt->execute();
        
        $db->commit();
        
        header("Location: index.php?success=1");
        exit();
        
    } catch(Exception $e) {
        $db->rollBack();
        header("Location: index.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}
?>