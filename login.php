<?php
session_start();
require_once 'config.php';

$error = '';

if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $password = trim($_POST['password']);
    
    if (empty($usuario) || empty($password)) {
        $error = 'Por favor ingresa usuario y contraseña';
    } else {
        try {
            // Buscar primero en Encargado
            $stmt = $pdo->prepare("SELECT id_encargado as id, id_persona, usuario, password FROM Encargado WHERE usuario = :usuario");
            $stmt->execute([':usuario' => $usuario]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $rol = 'encargado';
            
            // Si no está en Encargado, buscar en Trabajadores
            if (!$user) {
                $stmt = $pdo->prepare("SELECT id_trabajador as id, id_persona, usuario, password FROM Trabajadores WHERE usuario = :usuario");
                $stmt->execute([':usuario' => $usuario]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $rol = 'trabajador';
            }
            
            // Verificar contraseña
            if ($user && $user['password'] === $password) {
                // Obtener nombre de la tabla Persona
                $stmt = $pdo->prepare("SELECT nombre, apellidoP FROM Persona WHERE id_persona = :id");
                $stmt->execute([':id' => $user['id_persona']]);
                $persona = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $nombre_completo = $persona['nombre'] . ' ' . $persona['apellidoP'];
                
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_nombre'] = $nombre_completo;
                $_SESSION['nombre'] = $nombre_completo;
                $_SESSION['rol'] = $rol;
                $_SESSION['tipo'] = $rol;
                $_SESSION['usuario'] = $usuario;
                
                header("Location: index.php");
                exit();
            } else {
                $error = 'Usuario o contraseña incorrectos';
            }
        } catch (Exception $e) {
            $error = 'Error al iniciar sesión';
            error_log("Error login: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SuperZito</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 400px;
            width: 100%;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            color: #667eea;
            font-size: 2em;
            margin-bottom: 5px;
        }
        .logo p {
            color: #666;
            font-size: 14px;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn-login {
            width: 100%;
            padding: 15px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-login:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="logo">
            <h1>🏪 SuperZito</h1>
            <p>Sistema de Gestión de Inventario</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Usuario</label>
                <input type="text" name="usuario" required autofocus>
            </div>
            <div class="form-group">
                <label>Contraseña</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn-login">Iniciar Sesión</button>
        </form>
    </div>
</body>
</html>
