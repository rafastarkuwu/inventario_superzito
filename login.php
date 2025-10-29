<?php
session_start();
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $usuario = $_POST['usuario'];
    $password = $_POST['password'];
    $tipo = $_POST['tipo']; // 'encargado' o 'trabajador'
    
    try {
        if ($tipo == 'encargado') {
            $query = "SELECT e.*, p.nombre 
                     FROM Encargado e 
                     JOIN Persona p ON e.id_persona = p.id_persona 
                     WHERE e.usuario = :usuario AND e.password = :password";
        } else {
            $query = "SELECT t.*, p.nombre 
                     FROM Trabajadores t 
                     JOIN Persona p ON t.id_persona = p.id_persona 
                     WHERE t.usuario = :usuario AND t.password = :password";
        }
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':usuario', $usuario);
        $stmt->bindParam(':password', $password);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['usuario'] = $usuario;
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['tipo'] = $tipo;
            $_SESSION['id'] = $tipo == 'encargado' ? $user['id_encargado'] : $user['id_trabajador'];
            
            header("Location: index.php");
            exit();
        } else {
            $error = "Usuario o contraseña incorrectos";
        }
        
    } catch(Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Inventario</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 450px;
            width: 100%;
            animation: slideIn 0.5s;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #667eea;
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #666;
            font-size: 0.9em;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .alert-error {
            background: #fee;
            border-left: 4px solid #dc3545;
            color: #721c24;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .credentials-info {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            font-size: 13px;
        }
        
        .credentials-info h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .credentials-info p {
            margin: 5px 0;
            color: #666;
        }
        
        .credentials-info strong {
            color: #333;
        }
        
        .icon {
            font-size: 60px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-header">
        <div class="icon">🏪</div>
        <h1>Inventario SuperZito</h1>
        <p>Inicia sesión para continuar</p>
    </div>
    
    <?php if($error): ?>
        <div class="alert-error">
            <strong>⚠️ Error:</strong> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="form-group">
            <label>👤 Tipo de Usuario:</label>
            <select name="tipo" required>
                <option value="">Selecciona tu rol</option>
                <option value="encargado">🔑 Encargado</option>
                <option value="trabajador">👷 Trabajador</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>📧 Usuario:</label>
            <input type="text" name="usuario" placeholder="Ingresa tu usuario" required>
        </div>
        
        <div class="form-group">
            <label>🔒 Contraseña:</label>
            <input type="password" name="password" placeholder="Ingresa tu contraseña" required>
        </div>
        
        <button type="submit" class="btn-login">🚀 Iniciar Sesión</button>
    </form>
    
</div>

</body>
</html>