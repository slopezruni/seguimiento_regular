<?php
// login.php
session_start();
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $documento = trim($_POST['documento'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($documento) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? OR numero_identidad = ?");
            $stmt->execute([$documento, $documento]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario) {
                // Validación de contraseña
                if ($password === $usuario['password'] || password_verify($password, $usuario['password'])) {
                    
                    // Variable EXACTA que exige header.php para no rebotarte
                    $_SESSION['user_id'] = $usuario['id'];
                    
                    // Variables adicionales que utiliza tu index.php
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['documento']  = $usuario['numero_identidad'];
                    $_SESSION['usuario']    = $usuario['usuario'];
                    $_SESSION['nombre']     = $usuario['nombre_completo'];
                    $_SESSION['rol']        = $usuario['rol'];
                    
                    // Redirigimos al index principal de la raíz del proyecto
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Contraseña incorrecta.';
                }
            } else {
                $error = 'El usuario no existe.';
            }
        } catch (PDOException $e) {
            $error = 'Error de BD: ' . $e->getMessage();
        }
    } else {
        $error = 'Por favor, completa todos los campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - SENA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body.login-page {
            background-image: url('assets/img/fondo_login.jpeg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 8%;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            border: 0;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            border-top: 5px solid #39A900;
        }
        .btn-sena { background-color: #39A900; color: white; font-weight: bold; }
        .btn-sena:hover { background-color: #2d8500; color: white; }
    </style>
</head>
<body class="login-page">
    <div class="card login-card p-4 p-md-5">
        <h3 class="fw-bold text-center mb-1">INICIO DE SESIÓN</h3>
        <p class="text-muted small text-center mb-4">Sistema de Gestión de Apoyos</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 text-center"><b><?= $error ?></b></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label fw-bold small text-secondary">Usuario o N° Documento</label>
                <input type="text" class="form-control" name="documento" required autocomplete="off">
            </div>
            <div class="mb-4">
                <label class="form-label fw-bold small text-secondary">Contraseña</label>
                <input type="password" class="form-control" name="password" required>
            </div>
            <button type="submit" class="btn btn-sena w-100 py-2">Ingresar al Sistema</button>
        </form>
    </div>
</body>
</html>