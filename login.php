<?php
session_start();
require_once 'config/database.php';

// Si ya inició sesión, redirigir al panel principal
if (isset($_SESSION['user_id'])) {
    header('Location: /seguimiento-regular/index.php');
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = strtolower(trim($_POST['login_input'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!empty($login_input) && !empty($password)) {
        // BUSCAMOS COINCIDENCIA YA SEA POR correo_sena O POR usuario
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE correo_sena = ? OR usuario = ? LIMIT 1");
        $stmt->execute([$login_input, $login_input]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verificamos si existe y comprobamos la contraseña
        if ($usuario && (password_verify($password, $usuario['password']) || $password === $usuario['password'])) {
            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['nombre'] = $usuario['nombre_completo']; 
            $_SESSION['email'] = $usuario['correo_sena'];
            $_SESSION['rol'] = $usuario['rol'];
            
            // Asignar el apoyo del usuario
            if ($usuario['rol'] === 'admin') {
                $_SESSION['apoyo_usuario'] = null;
                $_SESSION['apoyo_activo_id'] = 1;
            } else {
                $_SESSION['apoyo_usuario'] = (int)($usuario['apoyo_id'] ?? 1);
                $_SESSION['apoyo_activo_id'] = (int)($usuario['apoyo_id'] ?? 1);
            }

            // Si su contraseña está en texto plano, la encriptamos para el futuro
            if ($password === $usuario['password']) {
                $nuevo_hash = password_hash($password, PASSWORD_BCRYPT);
                $stmtHash = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                $stmtHash->execute([$nuevo_hash, $usuario['id']]);
            }

            header('Location: /seguimiento-regular/index.php');
            exit;
        } else {
            $error = "Credenciales incorrectas. Verifica tu usuario/correo y contraseña.";
        }
    } else {
        $error = "Por favor ingresa tu usuario y contraseña.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Apoyos Socioeconómicos SENA</title>
    <link rel="icon" type="image/webp" href="/seguimiento-regular/assets/img/logo_sena.webp">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f4f6f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            max-width: 420px;
            width: 100%;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            border: none;
        }
        .login-header {
            background: #39a900;
            color: #fff;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            padding: 2rem 1.5rem;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="card login-card bg-white">
    <div class="login-header">
        <img src="/seguimiento-regular/assets/img/logo_sena.webp" alt="SENA" style="width: 55px; height: auto;" class="mb-2">
        <h4 class="fw-bold mb-0">Gestión de Apoyos</h4>
        <small class="text-white-50">Regional Valle - CEAI</small>
    </div>
    <div class="card-body p-4">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small fw-bold">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold text-secondary">Usuario o Correo SENA</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                    <!-- Cambiamos type="email" por type="text" -->
                    <input type="text" name="login_input" class="form-control" placeholder="Ej: slopezr o usuario@sena.edu.co" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-bold text-secondary">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn btn-success w-100 fw-bold py-2 shadow-sm">
                <i class="bi bi-box-arrow-in-right me-1"></i> Ingresar al Sistema
            </button>
        </form>
    </div>
    <div class="card-footer bg-light text-center py-3 border-0 small text-muted">
        Servicio Nacional de Aprendizaje SENA &copy; 2026
    </div>
</div>

</body>
</html>